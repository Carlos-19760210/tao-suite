#!/usr/bin/env python3
# DRY-RUN Carga 1 — dados técnicos FC03000 -> ativos (TAO). SOMENTE LEITURA.
import fdb, json, urllib.request, re, csv

BASE = r"C:\Users\carlo\FCertaSync"
SB   = "https://gclayesytzzpzkjvgede.supabase.co/rest/v1"
KEY  = "sb_secret_HpoqM6ujk2yD6la7KM3cuQ_pdWBK8jo"
CID  = "62f98634-77ff-42f4-acaf-8561d56583da"   # Magis

def sb(path, rng=None):
    h = {"apikey": KEY, "Authorization": "Bearer " + KEY}
    if rng: h["Range-Unit"] = "items"; h["Range"] = rng
    r = urllib.request.Request(SB + path, headers=h)
    return json.loads(urllib.request.urlopen(r).read().decode())

# TAO: ativos da Magis (paginado)
tao = {}
page = 0
while True:
    chunk = sb(f"/ativos?cliente_id=eq.{CID}&select=id,codigo_fc,nome&order=id.asc",
               rng=f"{page*1000}-{page*1000+999}")
    for a in chunk:
        if a.get("codigo_fc"): tao[str(a["codigo_fc"]).strip()] = a
    if len(chunk) < 1000: break
    page += 1
print("ativos TAO (Magis, com codigo_fc):", len(tao))

# FCerta: MPs grupo M
con = fdb.connect(database=BASE + r"\fcerta_atual.ib", user="SYSDBA", password="masterkey",
                  fb_library_name=BASE + r"\fb25\fbembed.dll", charset="NONE")
cur = con.cursor()
def dec(v): return v.decode("latin-1", "replace").strip() if isinstance(v, (bytes, bytearray)) else v
cur.execute("""SELECT p.CDPRO, p.DESCR, p.CDDCB, p.TEOR, p.DENSIDADE, p.DILUICAO, p.FATOR
               FROM FC03000 p WHERE p.GRUPO='M'""")
fc = {str(r[0]): {"nome": dec(r[1]), "dcb": dec(r[2]) or "", "teor": float(r[3] or 0),
                  "dens": float(r[4] or 0), "dilui": float(r[5] or 0), "fator": float(r[6] or 0)}
      for r in cur.fetchall()}
print("MPs FCerta (grupo M):", len(fc))
con.close()

def norm(s):
    import unicodedata
    s = unicodedata.normalize("NFD", s or "").encode("ascii", "ignore").decode().upper()
    return re.sub(r"\s+", " ", s.replace("@", "")).strip()

# índice por nome p/ achar a MP pura das diluídas
por_nome = {norm(a["nome"]): a for a in tao.values()}

acoes = []          # linhas do relatório
c = {"match": 0, "sem_tao": 0, "dcb": 0, "teor": 0, "dens": 0, "dilui_nom": 0,
     "markup": 0, "diluida": 0, "puro_ok": 0, "puro_nao": 0}
for cod, m in fc.items():
    a = tao.get(cod)
    if not a:
        c["sem_tao"] += 1
        continue
    c["match"] += 1
    upd = {}
    if m["dcb"]:                          upd["dcb"] = m["dcb"]; c["dcb"] += 1
    if m["teor"] and m["teor"] not in (1, 100): upd["teor_pct"] = m["teor"] if m["teor"] > 1 else m["teor"]*100; c["teor"] += 1
    if m["dens"] and m["dens"] != 1:      upd["densidade"] = m["dens"]; c["dens"] += 1
    if m["fator"] and m["fator"] not in (0, 1): upd["markup_preco"] = m["fator"]; c["markup"] += 1

    # diluída pelo nome "X 1:N"
    mm = re.search(r"\b1\s*:\s*(\d+)\b", m["nome"])
    if mm:
        c["diluida"] += 1
        fator_dil = float(mm.group(1))
        upd["fator_diluicao"] = fator_dil
        base = norm(re.sub(r"\b1\s*:\s*\d+\b", "", m["nome"]))
        puro = por_nome.get(base) or por_nome.get(base.replace(" MP", "")) or por_nome.get(base + " MP")
        if not puro:  # tenta prefixo
            cand = [v for k, v in por_nome.items() if k.startswith(base[:12]) and "1:" not in k]
            puro = cand[0] if len(cand) == 1 else None
        if puro and puro["id"] != a["id"]:
            upd["ativo_puro_id"] = puro["id"]; upd["_puro_nome"] = puro["nome"]; c["puro_ok"] += 1
        else:
            upd["_puro_nome"] = "(NÃO ENCONTRADO — revisar)"; c["puro_nao"] += 1
    elif m["dilui"] and m["dilui"] not in (0, 1):
        upd["fator_diluicao"] = m["dilui"]; c["dilui_nom"] += 1

    if upd:
        acoes.append({"codigo_fc": cod, "ativo": a["nome"], **upd})

print("\n===== RESUMO DO DRY-RUN (nada gravado) =====")
print(f"casados por codigo_fc: {c['match']} | MPs do FCerta sem ativo no TAO: {c['sem_tao']}")
print(f"DCB a gravar: {c['dcb']} | teor≠100: {c['teor']} | densidade≠1: {c['dens']}")
print(f"markup (ex-FATOR) a gravar: {c['markup']}")
print(f"MPs diluídas (nome 1:N): {c['diluida']} -> vínculo c/ pura OK: {c['puro_ok']} | SEM pura: {c['puro_nao']}")
print(f"diluição nominal (campo, sem 1:N no nome): {c['dilui_nom']}")
print(f"TOTAL de ativos que receberiam update: {len(acoes)}")

with open(BASE + r"\dryrun_carga1.csv", "w", newline="", encoding="utf-8-sig") as f:
    campos = ["codigo_fc","ativo","dcb","teor_pct","densidade","fator_diluicao","_puro_nome","markup_preco"]
    w = csv.DictWriter(f, fieldnames=campos, extrasaction="ignore", delimiter=";")
    w.writeheader()
    for a_ in acoes: w.writerow(a_)
print("\nrelatório completo: scratchpad\\dryrun_carga1.csv")
print("\namostra (diluídas com vínculo):")
n=0
for a_ in acoes:
    if "ativo_puro_id" in a_ and n < 8:
        print(f"   {a_['ativo'][:34]:36s} 1:{a_['fator_diluicao']:<6.0f} -> pura: {a_['_puro_nome'][:30]}")
        n += 1
print("\ndiluídas SEM pura encontrada (revisar manualmente):")
for a_ in acoes:
    if a_.get("_puro_nome","").startswith("(NÃO"):
        print(f"   {a_['ativo'][:40]}")
