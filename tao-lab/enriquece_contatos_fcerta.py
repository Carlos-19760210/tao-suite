#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
ENRIQUECE CONTATOS com dados do FCerta (documento/CPF, sexo, nascimento, e-mail, endereço).
Preenche SÓ os campos VAZIOS no crm_contatos (o CRM é a fonte da verdade — nunca sobrescreve).
Alvo: contatos vinculados a paciente do histórico (hist_clientes.contato_id).
Requer: migration_v6 + junção já rodadas. Idempotente.
Uso: python enriquece_contatos_fcerta.py [--dry-run]
"""
import json, urllib.request, re, time, argparse, os

SB  = "https://gclayesytzzpzkjvgede.supabase.co/rest/v1"
KEY = "sb_secret_HpoqM6ujk2yD6la7KM3cuQ_pdWBK8jo"
CID = "62f98634-77ff-42f4-acaf-8561d56583da"
WS  = "7c4cae7f-7591-4955-8d7a-c8c5e19cf62d"
FB  = r"C:\Users\carlo\FCertaSync\fb25\fbembed.dll"
DB  = r"C:\Users\carlo\FCertaSync\work.ib"

def sb(path, method="GET", body=None):
    data = json.dumps(body).encode() if body is not None else None
    h = {"apikey": KEY, "Authorization": "Bearer " + KEY,
         "Content-Type": "application/json", "Prefer": "return=minimal"}
    return urllib.request.urlopen(urllib.request.Request(SB + path, data=data, method=method, headers=h))

def page(path, ps=1000):
    out, p = [], 0
    h = {"apikey": KEY, "Authorization": "Bearer " + KEY}
    while True:
        hh = dict(h); hh["Range-Unit"] = "items"; hh["Range"] = f"{p*ps}-{p*ps+ps-1}"
        c = json.loads(urllib.request.urlopen(urllib.request.Request(SB + path, headers=hh)).read().decode())
        out += c
        if len(c) < ps: return out
        p += 1

def s(v):
    if isinstance(v, bytes): v = v.decode("latin-1", "replace")
    return v.strip() if isinstance(v, str) else v

def main():
    ap = argparse.ArgumentParser(); ap.add_argument("--dry-run", action="store_true")
    args = ap.parse_args()
    if not os.path.isfile(DB): raise SystemExit(f"Banco não encontrado ({DB}) — rode sync_historico.py")

    # 1. contatos vinculados: cdcli -> contato_id
    hist = page(f"/hist_clientes?cliente_id=eq.{CID}&contato_id=not.is.null&select=cdcli,contato_id")
    cdcli2ct = { h["cdcli"]: h["contato_id"] for h in hist if h.get("cdcli") }
    print(f"pacientes vinculados: {len(cdcli2ct)}")

    # 2. estado atual dos contatos (p/ preencher só o vazio)
    atual = { c["id"]: c for c in
              page(f"/crm_contatos?workspace_id=eq.{WS}&select=id,email,cpf,data_nascimento,sexo,cep,logradouro,numero,bairro,cidade") }

    # 3. dados FCerta por CDCLI
    import fdb
    con = fdb.connect(database=DB, user="SYSDBA", password="masterkey", fb_library_name=FB, charset="NONE")
    cur = con.cursor()
    cur.execute("SELECT CDCLI, EMAIL, NRCNPJ, DTNAS, TPSEX FROM FC07000")
    dados = {}
    for cdcli, email, doc, dtnas, sexo in cur.fetchall():
        doc = re.sub(r"\D", "", s(doc) or "")
        dados[cdcli] = {
            "email": (s(email) or None),
            "cpf": (doc if 11 <= len(doc) <= 14 else None),
            "data_nascimento": (dtnas.isoformat() if dtnas else None),
            "sexo": (s(sexo) if s(sexo) in ("M", "F") else None),
        }
    # endereço: 1º registro com logradouro por cliente
    cur.execute("SELECT CDCLI, ENDER, ENDNR, BAIRR, MUNIC, NRCEP FROM FC07200 WHERE ENDER IS NOT NULL AND ENDER<>''")
    for cdcli, ender, endnr, bairr, munic, cep in cur.fetchall():
        d = dados.setdefault(cdcli, {})
        if "logradouro" in d: continue
        d["logradouro"] = s(ender) or None
        d["numero"]     = s(endnr) or None
        d["bairro"]     = s(bairr) or None
        d["cidade"]     = s(munic) or None
        d["cep"]        = re.sub(r"\D", "", s(cep) or "") or None
    con.close()

    # 4. monta patch só dos campos vazios no CRM
    CAMPOS = ["email", "cpf", "data_nascimento", "sexo", "cep", "logradouro", "numero", "bairro", "cidade"]
    to_patch, contrib = [], {c: 0 for c in CAMPOS}
    for cdcli, ct_id in cdcli2ct.items():
        fc = dados.get(cdcli); cur_ct = atual.get(ct_id)
        if not fc or not cur_ct: continue
        patch = {}
        for k in CAMPOS:
            novo = fc.get(k)
            if novo and not cur_ct.get(k):     # só preenche vazio
                patch[k] = novo; contrib[k] += 1
        if patch: to_patch.append((ct_id, patch))

    print(f"contatos a enriquecer: {len(to_patch)}")
    print("campos que serão preenchidos (novos):")
    for k, n in contrib.items(): print(f"  {k}: {n}")
    if args.dry_run: print("\n[dry-run] nada gravado."); return

    for i, (ct_id, patch) in enumerate(to_patch):
        for tent in range(3):
            try: sb(f"/crm_contatos?id=eq.{ct_id}", "PATCH", patch); break
            except Exception as e:
                if tent == 2: print(f"  falha {ct_id}: {e}"); break
                time.sleep(1)
        if i % 300 == 0: print(f"  {i}/{len(to_patch)}")
    print(f"\nENRIQUECIMENTO CONCLUÍDO — {len(to_patch)} contatos atualizados")

if __name__ == "__main__":
    main()
