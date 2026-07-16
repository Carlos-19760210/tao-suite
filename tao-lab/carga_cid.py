# -*- coding: utf-8 -*-
# CARGA CATÁLOGO CID-10 — FC99311 -> cid10 (codigo = CAPITDOE+CATEGDOE[.SUBCATDOE])
# CID-10 é público (OMS/Datasus) — base limpa p/ comercializar. ~12,4 mil códigos.
# Idempotente: upsert por codigo (on_conflict). Carga leve (referência estática).
# Regra: só rodar com OK do Carlos.
import fdb, json, urllib.request, sys, unicodedata

BASE = r"C:\Users\carlo\AppData\Local\Temp\claude\C--Users-carlo\a6c38159-6860-477a-83dc-c9e69ae557f1\scratchpad"
SB   = "https://gclayesytzzpzkjvgede.supabase.co/rest/v1"
KEY  = "sb_secret_HpoqM6ujk2yD6la7KM3cuQ_pdWBK8jo"
DRY  = "--commit" not in sys.argv

def s(v): return v.strip() if isinstance(v, str) else v
def noacc(t): return ''.join(c for c in unicodedata.normalize('NFD', t) if unicodedata.category(c) != 'Mn')

def sb_post(rows):
    data = json.dumps(rows).encode()
    h = {"apikey": KEY, "Authorization": "Bearer " + KEY, "Content-Type": "application/json",
         "Prefer": "resolution=merge-duplicates,return=minimal"}
    req = urllib.request.Request(SB + "/cid10?on_conflict=codigo", data=data, method="POST", headers=h)
    return urllib.request.urlopen(req).read()

con = fdb.connect(database=BASE + r"\fcerta_analise.ib", user="SYSDBA", password="masterkey",
                  fb_library_name=BASE + r"\fb25\fbembed.dll", charset="NONE")
cur = con.cursor()
cur.execute("SELECT CAPITDOE, CATEGDOE, SUBCATDOE, DESCRDOE FROM FC99311 WHERE DESCRDOE IS NOT NULL")
vistos, rows = set(), []
for cap, cat, sub, desc in cur.fetchall():
    cap, cat, sub, desc = s(cap), s(cat), s(sub), s(desc)
    if not cap or not cat or not desc: continue
    # sub vazio → código de 3 caracteres (categoria); senão CAPIT+CAT.SUB (sub '0' é válido, ex: E10.0)
    codigo = f"{cap}{cat}" + (f".{sub}" if sub not in (None, "") else "")
    if codigo in vistos: continue
    vistos.add(codigo)
    rows.append({"codigo": codigo, "descricao": desc, "capitulo": cap,
                 "busca": noacc((codigo + " " + desc).lower())})
con.close()
print(f"CID montados: {len(rows)}  (amostra: {[r['codigo'] for r in rows[:6]]})")

if DRY:
    print("== SIMULAÇÃO (use --commit para gravar) ==")
    for r in rows[:8]: print("  ", r["codigo"], "|", r["descricao"][:50])
else:
    print("== GRAVANDO em lotes de 500 ==")
    for i in range(0, len(rows), 500):
        sb_post(rows[i:i+500]); print(f"  {min(i+500, len(rows))}/{len(rows)}")
    print("OK")
