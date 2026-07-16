#!/usr/bin/env python3
# CARGA 1 — grava dados técnicos FC03000 -> ativos (TAO). Aprovada pelo Carlos.
import fdb, json, urllib.request, re

BASE = r"C:\Users\carlo\AppData\Local\Temp\claude\C--Users-carlo\a6c38159-6860-477a-83dc-c9e69ae557f1\scratchpad"
SB   = "https://gclayesytzzpzkjvgede.supabase.co/rest/v1"
KEY  = "sb_secret_HpoqM6ujk2yD6la7KM3cuQ_pdWBK8jo"
CID  = "62f98634-77ff-42f4-acaf-8561d56583da"

def sb(path, method="GET", body=None, rng=None):
    data = json.dumps(body).encode() if body is not None else None
    h = {"apikey": KEY, "Authorization": "Bearer " + KEY, "Content-Type": "application/json",
         "Prefer": "return=minimal"}
    if rng: h["Range-Unit"] = "items"; h["Range"] = rng
    r = urllib.request.Request(SB + path, data=data, method=method, headers=h)
    return urllib.request.urlopen(r).read().decode()

def sbq(path, rng=None):
    h = {"apikey": KEY, "Authorization": "Bearer " + KEY}
    if rng: h["Range-Unit"] = "items"; h["Range"] = rng
    r = urllib.request.Request(SB + path, headers=h)
    return json.loads(urllib.request.urlopen(r).read().decode())

tao = {}
page = 0
while True:
    chunk = sbq(f"/ativos?cliente_id=eq.{CID}&select=id,codigo_fc,nome&order=id.asc",
                rng=f"{page*1000}-{page*1000+999}")
    for a in chunk:
        if a.get("codigo_fc"): tao[str(a["codigo_fc"]).strip()] = a
    if len(chunk) < 1000: break
    page += 1

con = fdb.connect(database=BASE + r"\fcerta_analise.ib", user="SYSDBA", password="masterkey",
                  fb_library_name=BASE + r"\fb25\fbembed.dll", charset="NONE")
cur = con.cursor()
def dec(v): return v.decode("latin-1", "replace").strip() if isinstance(v, (bytes, bytearray)) else v
cur.execute("SELECT CDPRO, DESCR, CDDCB, TEOR, DENSIDADE, DILUICAO, FATOR FROM FC03000 WHERE GRUPO='M'")
fc = {str(r[0]): {"nome": dec(r[1]), "dcb": dec(r[2]) or "", "teor": float(r[3] or 0),
                  "dens": float(r[4] or 0), "dilui": float(r[5] or 0), "fator": float(r[6] or 0)}
      for r in cur.fetchall()}
con.close()

def norm(s):
    import unicodedata
    s = unicodedata.normalize("NFD", s or "").encode("ascii", "ignore").decode().upper()
    return re.sub(r"\s+", " ", s.replace("@", "")).strip()
por_nome = {norm(a["nome"]): a for a in tao.values()}

SKIP_LIXO = ("NAO USAR", "?")
grav = {"total": 0, "dcb": 0, "teor": 0, "dens": 0, "markup": 0, "dilui": 0, "puro": 0}
for cod, m in fc.items():
    a = tao.get(cod)
    if not a: continue
    if any(s in m["nome"].upper() for s in SKIP_LIXO) and "1:" in m["nome"]: continue
    upd = {}
    if m["dcb"]: upd["dcb"] = m["dcb"]; grav["dcb"] += 1
    if m["teor"] and m["teor"] not in (1, 100):
        upd["teor_pct"] = m["teor"] if m["teor"] > 1 else m["teor"]*100; grav["teor"] += 1
    if m["dens"] and m["dens"] != 1: upd["densidade"] = m["dens"]; grav["dens"] += 1
    if m["fator"] and m["fator"] not in (0, 1): upd["markup_preco"] = m["fator"]; grav["markup"] += 1
    mm = re.search(r"\b1\s*:\s*(\d+)\b", m["nome"])
    if mm:
        upd["fator_diluicao"] = float(mm.group(1)); grav["dilui"] += 1
        base = norm(re.sub(r"\b1\s*:\s*\d+\b", "", m["nome"]))
        puro = por_nome.get(base) or por_nome.get(base.replace(" MP", "")) or por_nome.get(base + " MP")
        if not puro:
            cand = [v for k, v in por_nome.items() if k.startswith(base[:12]) and "1:" not in k]
            puro = cand[0] if len(cand) == 1 else None
        if puro and puro["id"] != a["id"]:
            upd["ativo_puro_id"] = puro["id"]; grav["puro"] += 1
    elif m["dilui"] and m["dilui"] not in (0, 1):
        upd["fator_diluicao"] = m["dilui"]; grav["dilui"] += 1
    if upd:
        sb(f"/ativos?id=eq.{a['id']}", "PATCH", upd)
        grav["total"] += 1

print("GRAVADO:", grav)

# verificação pós-carga
v = {}
for campo, filtro in [("dcb", "dcb=not.is.null"), ("densidade≠1", "densidade=neq.1"),
                      ("markup", "markup_preco=not.is.null"), ("fator_diluicao>1", "fator_diluicao=gt.1"),
                      ("vínculo pura", "ativo_puro_id=not.is.null")]:
    r = urllib.request.Request(f"{SB}/ativos?cliente_id=eq.{CID}&{filtro}&select=id",
        headers={"apikey": KEY, "Authorization": "Bearer " + KEY, "Prefer": "count=exact", "Range-Unit": "items", "Range": "0-0"})
    with urllib.request.urlopen(r) as resp:
        v[campo] = resp.headers.get("Content-Range", "?").split("/")[-1]
print("VERIFICAÇÃO no banco:", v)
