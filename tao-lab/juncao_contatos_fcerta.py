#!/usr/bin/env python3
# -*- coding: utf-8 -*-
import os
"""
JUNÇÃO DE CONTATOS FCerta → base ÚNICA do CRM (crm_contatos)
Chave: CELULAR (FC07200.NRFAX+NRDDDFAX) normalizado p/ whatsapp 55DDD9XXXXXXXX.

Para cada paciente do histórico (hist_clientes) com celular:
  1) normaliza o celular;
  2) procura crm_contatos (workspace Magis) por esse whatsapp;
  3) EXISTE  → só vincula hist_clientes.contato_id (não altera o contato);
  4) NÃO existe → cria contato (origem='fcerta', SEM opt-in de campanha via tag)
     e vincula.

SALVAGUARDA DE CAMPANHA: contatos criados levam origem='fcerta' + tag
'historico-fcerta' → campanhas devem filtrar esses (não têm opt-in de WhatsApp).

Requer: migration_v6_contato_unico.sql rodada.
Idempotente: só cria quem não existe; só vincula quem está sem contato_id.
Uso:  python juncao_contatos_fcerta.py --dry-run   (só conta, não grava)
      python juncao_contatos_fcerta.py             (executa)
      python juncao_contatos_fcerta.py --no-create (só vincula os que já existem)
"""
import json, urllib.request, re, time, argparse, os

SB  = "https://gclayesytzzpzkjvgede.supabase.co/rest/v1"
KEY = os.environ.get("SUPABASE_KEY", "sb_secret_HpoqM6ujk2yD6la7KM3cuQ_pdWBK8jo")
CID = "62f98634-77ff-42f4-acaf-8561d56583da"
WS  = "7c4cae7f-7591-4955-8d7a-c8c5e19cf62d"   # workspace Magis-TAO
FB  = r"C:\Users\carlo\FCertaSync\fb25\fbembed.dll"
DB  = r"C:\Users\carlo\FCertaSync\work.ib"      # cópia de trabalho (sync_historico gera)

def sb(path, method="GET", body=None, prefer="return=minimal", rng=None):
    data = json.dumps(body).encode() if body is not None else None
    h = {"apikey": KEY, "Authorization": "Bearer " + KEY,
         "Content-Type": "application/json", "Prefer": prefer}
    if rng: h["Range-Unit"] = "items"; h["Range"] = rng
    return urllib.request.urlopen(urllib.request.Request(SB + path, data=data, method=method, headers=h))

def sbq(path, rng=None):
    return json.loads(sb(path, rng=rng).read().decode())

def page(path, ps=1000):
    out, p = [], 0
    while True:
        c = sbq(path, rng=f"{p*ps}-{p*ps+ps-1}")
        out += c
        if len(c) < ps: return out
        p += 1

def s(v): return v.strip() if isinstance(v, str) else ("" if v is None else str(v))

def norm_wpp(ddd, fax):
    d = re.sub(r"\D", "", s(ddd)) or "11"
    n = re.sub(r"\D", "", s(fax))
    if len(n) == 8: n = "9" + n           # celular antigo sem o 9
    if len(n) != 9: return None
    return "55" + d + n

def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--dry-run",   action="store_true")
    ap.add_argument("--no-create", action="store_true", help="só vincula contatos existentes")
    args = ap.parse_args()

    if not os.path.isfile(DB):
        raise SystemExit(f"Banco de trabalho não encontrado ({DB}). Rode antes: python sync_historico.py")

    # 1. whatsapps já no CRM Magis → id
    print("carregando contatos do CRM...")
    crm = { s(c["whatsapp"]): c["id"] for c in
            page(f"/crm_contatos?workspace_id=eq.{WS}&select=id,whatsapp") if c.get("whatsapp") }
    print(f"  contatos CRM Magis: {len(crm)}")

    # 2. hist_clientes ainda sem contato_id
    hist = page(f"/hist_clientes?cliente_id=eq.{CID}&contato_id=is.null&select=id,cdcli,nome")
    print(f"  pacientes histórico sem vínculo: {len(hist)}")

    # 3. CDCLI → celular (FC07200)
    import fdb
    con = fdb.connect(database=DB, user="SYSDBA", password="masterkey", fb_library_name=FB, charset="NONE")
    cur = con.cursor()
    cur.execute("SELECT CDCLI, NRDDDFAX, NRFAX FROM FC07200 WHERE NRFAX IS NOT NULL AND NRFAX<>''")
    cel = {}
    for cdcli, ddd, fax in cur.fetchall():
        w = norm_wpp(ddd, fax)
        if w and cdcli not in cel: cel[cdcli] = w
    con.close()

    vinc, criar = [], []          # (hist_id, contato_id) / (hist_id, nome, wpp, cdcli)
    sem_cel = 0
    for hc in hist:
        w = cel.get(hc["cdcli"])
        if not w: sem_cel += 1; continue
        if w in crm:
            vinc.append((hc["id"], crm[w]))
        else:
            criar.append((hc["id"], hc["nome"], w, hc["cdcli"]))

    print(f"\n  a VINCULAR (contato já existe): {len(vinc)}")
    print(f"  a CRIAR (contato novo, origem=fcerta): {len(criar)}")
    print(f"  sem celular no FCerta: {sem_cel}")
    if args.dry_run:
        print("\n[dry-run] nada gravado."); return

    # 4. Vincula os existentes
    for i, (hid, cid) in enumerate(vinc):
        sb(f"/hist_clientes?id=eq.{hid}", "PATCH", {"contato_id": cid})
        if i % 100 == 0: print(f"  vinculados {i}/{len(vinc)}")
    print(f"  vinculados: {len(vinc)}")

    # 5. Cria os novos (com salvaguarda de campanha) e vincula
    if args.no_create:
        print("  --no-create: pulando criação."); return
    feitos = 0
    for hid, nome, wpp, cdcli in criar:
        try:
            r = sb("/crm_contatos", "POST",
                   {"workspace_id": WS, "nome": nome, "whatsapp": wpp,
                    "origem": "fcerta", "cdcli_fcerta": cdcli, "tags": ["historico-fcerta"]},
                   prefer="return=representation")
            novo = json.loads(r.read().decode())
            cid = novo[0]["id"] if isinstance(novo, list) else novo["id"]
            sb(f"/hist_clientes?id=eq.{hid}", "PATCH", {"contato_id": cid})
            feitos += 1
            if feitos % 200 == 0: print(f"  criados {feitos}/{len(criar)}")
        except Exception as e:
            print(f"  falha cdcli {cdcli}: {e}"); time.sleep(1)
    print(f"  criados+vinculados: {feitos}")
    print("\nJUNÇÃO CONCLUÍDA")

if __name__ == "__main__":
    main()
