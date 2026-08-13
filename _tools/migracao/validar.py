# -*- coding: utf-8 -*-
# Validação READ-ONLY do backup FCerta restaurado x Supabase. NÃO escreve nada.
import sys, io, json, urllib.request
sys.stdout.reconfigure(encoding='utf-8', errors='replace')
import migra

cfg = migra.load_config(sys.argv[1])
url, key, cli = migra.supa(cfg)
# A chave do config.json está retornando 401 (rotacionada). Uso a chave válida
# atual só para a LEITURA da validação; não altero o config aqui.
try:
    key = io.open(r"C:\Users\carlo\FCertaSync\supabase_key.txt", encoding="utf-8").read().strip() or key
except Exception:
    pass

def sget(path):
    req = urllib.request.Request(url + "/rest/v1/" + path,
        headers={"apikey": key, "Authorization": "Bearer " + key})
    return json.loads(urllib.request.urlopen(req).read())

con = migra.fb_open(cfg); cur = con.cursor()
def scal(sql):
    cur.execute(sql); return cur.fetchone()[0]

print("== IDENTIDADE DO BACKUP ==")
cur.execute("select first 1 NRCNPJ from FC01000")
cnpj = migra._s(cur.fetchone()[0])
print(f"  CNPJ no backup : {cnpj}")
print(f"  CNPJ esperado  : {cfg['cnpj_emitente']}   -> {'OK' if cnpj==cfg['cnpj_emitente'] else '*** DIVERGE ***'}")

print("\n== FC03000 (produtos) ==")
tot   = scal("select count(*) from FC03000")
ativo = scal("select count(*) from FC03000 where SITUA='A'")
fisc  = scal("select count(*) from FC03000 where SITUA='A' and CLFISC is not null and trim(CLFISC)<>''")
grpM  = scal("select count(*) from FC03000 where SITUA='A' and GRUPO='M'")
print(f"  total={tot} | ativos={ativo} | ativos c/ NCM(CLFISC)={fisc} | grupo M(matéria-prima)={grpM}")

print("\n== FONTE CANÔNICA (o que o sync-fiscal enviaria) ==")
prods = migra.produtos_fonte(cfg)
cods_fonte = set(p["codigo"] for p in prods)
print(f"  produtos fiscais na fonte: {len(prods)}  (códigos únicos: {len(cods_fonte)})")

print("\n== SUPABASE (estado atual — read only) ==")
def count_table(t):
    req = urllib.request.Request(url + "/rest/v1/%s?select=id&cliente_id=eq.%s" % (t, cli),
        headers={"apikey": key, "Authorization": "Bearer " + key, "Prefer": "count=exact", "Range": "0-0"})
    return urllib.request.urlopen(req).headers.get("content-range", "?")
print(f"  fiscal_produtos: {count_table('fiscal_produtos')}")
print(f"  ativos         : {count_table('ativos')}")

# códigos já em fiscal_produtos (paginado)
cods_sb=set(); off=0
while True:
    page = sget("fiscal_produtos?select=codigo_fc&cliente_id=eq.%s&limit=1000&offset=%d" % (cli, off))
    if not page: break
    cods_sb.update(str(r["codigo_fc"]) for r in page if r.get("codigo_fc"))
    off += 1000
    if len(page) < 1000: break

novos = cods_fonte - cods_sb
print("\n== CONFRONTO (delta que um sync-fiscal aplicaria) ==")
print(f"  fiscal_produtos no Supabase: {len(cods_sb)}")
print(f"  códigos NOVOS na fonte (inserts): {len(novos)}")
print(f"  já existentes (updates preservando o contador): {len(cods_fonte & cods_sb)}")
if novos:
    ex=[p for p in prods if p['codigo'] in novos][:8]
    for p in ex: print(f"     + {p['codigo']:<8} {p['descricao'][:44]}")

con.close()
print("\n(validação read-only — nenhuma escrita no Supabase)")
