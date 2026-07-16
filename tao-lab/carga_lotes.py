#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
CARGA LOTES — FC03140 (filial 1) → lab_lotes_mp. Rodada RECORRENTE de equalização.
Regras (nada destrutivo):
  • Lote já espelhado (ativo_id + nr_lote + lote_interno) → PATCH só de
    qtd_atual / dt_validade / teor_pct, e somente se mudou.
  • Lote novo no FCerta com ESTAT>0 e validade futura → INSERT no padrão da
    carga original (status='aprovado', origem='fornecedor', unidade='g').
  • Lote que só existe no TAO (ex.: Entrada NF feita no TAO) → NÃO É TOCADO.
  • Nunca deleta; nunca mexe em status de lote existente.
Uso: python carga_lotes.py            # dry-run (só relata)
     python carga_lotes.py --commit   # grava
"""
import fdb, json, urllib.request, sys, datetime

DB     = r"C:\Users\carlo\FCertaSync\fcerta_atual.ib"
FB_DLL = r"C:\Users\carlo\FCertaSync\fb25\fbembed.dll"
SB     = "https://gclayesytzzpzkjvgede.supabase.co/rest/v1"
KEY    = "sb_secret_HpoqM6ujk2yD6la7KM3cuQ_pdWBK8jo"
CID    = "62f98634-77ff-42f4-acaf-8561d56583da"
DRY    = "--commit" not in sys.argv

def dec(v): return v.decode("latin-1","replace").strip() if isinstance(v,(bytes,bytearray)) else (v.strip() if isinstance(v,str) else v)

def sb_req(path, method="GET", body=None):
    h={"apikey":KEY,"Authorization":"Bearer "+KEY,"Content-Type":"application/json","Prefer":"return=minimal"}
    data=json.dumps(body).encode() if body is not None else None
    return urllib.request.urlopen(urllib.request.Request(SB+path,data=data,method=method,headers=h)).read()

def sb_all(path):
    out,page=[],0
    while True:
        h={"apikey":KEY,"Authorization":"Bearer "+KEY,"Range-Unit":"items","Range":f"{page*1000}-{page*1000+999}"}
        ch=json.loads(urllib.request.urlopen(urllib.request.Request(SB+path,headers=h)).read().decode())
        out+=ch
        if len(ch)<1000: return out
        page+=1

hoje = datetime.date.today().isoformat()

# ── TAO: ativos (codigo_fc→id) e lotes existentes ───────────────────────────
ativos = sb_all(f"/ativos?cliente_id=eq.{CID}&select=id,codigo_fc,nome&order=id.asc")
por_cod = {str(a["codigo_fc"]).strip(): a for a in ativos if a.get("codigo_fc")}
lotes = sb_all(f"/lab_lotes_mp?cliente_id=eq.{CID}&select=id,ativo_id,nr_lote,lote_interno,dt_validade,qtd_atual,teor_pct,origem,status&order=id.asc")
idx3 = {(l["ativo_id"], (l["nr_lote"] or "").strip(), (l.get("lote_interno") or "").strip()): l for l in lotes}
idx2 = {}
for l in lotes: idx2.setdefault((l["ativo_id"], (l["nr_lote"] or "").strip()), []).append(l)
print(f"TAO: {len(lotes)} lotes | {len(por_cod)} ativos com codigo_fc")

# ── FCerta: todos os lotes da filial 1 ───────────────────────────────────────
con = fdb.connect(database=DB, user="SYSDBA", password="masterkey", fb_library_name=FB_DLL, charset="NONE")
cur = con.cursor()
cur.execute("""SELECT CDPRO, NRLOT, CTLOT, DTVAL, DTFAB, ESTAT, TEOR
               FROM FC03140 WHERE CDFIL=1 AND NRLOT IS NOT NULL""")
fc = cur.fetchall(); con.close()
print(f"FCerta: {len(fc)} lotes (filial 1)")

def f(v, d=None):
    try: return float(v)
    except (TypeError, ValueError): return d

patches, inserts, sem_ativo, sem_val = [], [], 0, 0
vistos = set()
for cdpro, nrlot, ctlot, dtval, dtfab, estat, teor in fc:
    a = por_cod.get(str(cdpro).strip())
    if not a: sem_ativo += 1; continue
    nr, ct = dec(nrlot) or "", str(ctlot).strip() if ctlot is not None else ""
    chave = (a["id"], nr, ct)
    if chave in vistos: continue
    vistos.add(chave)
    qtd, teor_v = f(estat, 0.0), f(teor)
    if teor_v is not None and teor_v <= 0: teor_v = None   # TEOR=0 no FCerta = "não informado" (0% quebraria o motor)
    val = str(dtval)[:10] if dtval else None

    t = idx3.get(chave)
    if t is None:
        cands = [l for l in idx2.get((a["id"], nr), []) if not (l.get("lote_interno") or "").strip()]
        t = cands[0] if len(cands) == 1 else None
    if t is not None:
        upd = {}
        if abs((f(t.get("qtd_atual"), 0.0) or 0.0) - qtd) > 1e-6: upd["qtd_atual"] = qtd
        if val and str(t.get("dt_validade") or "")[:10] != val: upd["dt_validade"] = val
        if teor_v is not None and abs((f(t.get("teor_pct")) or -1) - teor_v) > 1e-6: upd["teor_pct"] = teor_v
        if upd: patches.append((t["id"], a["nome"], nr, upd))
    else:
        if qtd <= 0: continue                    # lote novo mas já esgotado — não interessa
        if not val or val < hoje: sem_val += 1; continue   # vencido/sem validade não entra
        inserts.append({"cliente_id": CID, "ativo_id": a["id"], "nr_lote": nr,
                        "lote_interno": ct or None, "origem": "fornecedor",
                        "dt_validade": val,
                        "dt_fabricacao": str(dtfab)[:10] if dtfab else None,
                        "qtd_inicial": qtd, "qtd_atual": qtd, "unidade": "g",
                        "teor_pct": teor_v, "status": "aprovado"})

print(f"\nPLANO: atualizar {len(patches)} lotes | inserir {len(inserts)} novos | "
      f"sem ativo no TAO: {sem_ativo} | novos já vencidos/sem validade: {sem_val}")
print("Atualizações (amostra 10):")
for _id, nome, nr, upd in patches[:10]: print(f"  ~ {nome[:35]:35} lote {nr}: {upd}")
print("Inserções (amostra 10):")
for i in inserts[:10]:
    nome = next((a['nome'] for a in ativos if a['id']==i['ativo_id']), '?')
    print(f"  + {nome[:35]:35} lote {i['nr_lote']} qtd {i['qtd_atual']} val {i['dt_validade']}")

if DRY:
    print("\nDRY-RUN: nada gravado (use --commit).")
else:
    err = 0
    for _id, nome, nr, upd in patches:
        try: sb_req(f"/lab_lotes_mp?id=eq.{_id}", "PATCH", upd)
        except Exception as e: err += 1; print(f"  ERRO PATCH {nome} {nr}: {e}")
    for i in range(0, len(inserts), 200):
        sb_req("/lab_lotes_mp", "POST", inserts[i:i+200])
    print(f"GRAVADO: {len(patches)-err} atualizados ({err} erros) | {len(inserts)} inseridos")
