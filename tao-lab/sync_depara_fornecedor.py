#!/usr/bin/env python3
# -*- coding: utf-8 -*-
import os
"""
SYNC DE-PARA FORNECEDOR — importa do FCerta a associação (fornecedor + código do produto
no fornecedor) → produto, para o TAO Neo pré-associar itens na Entrada de NF.
Fonte: FC11100 (FORNECID, CPROD=cód. no fornecedor, CDPRO=produto FCerta) + FC02000 (FORNECID→NRCNPJ).
Alvo:  estoque_forn_depara (cliente_id, fornecedor_id, cod_fornecedor=CPROD, ativo_id, descr_fornecedor).
Casa fornecedor por CNPJ e ativo por codigo_fc=CDPRO. Idempotente.

Uso:  python sync_depara_fornecedor.py --db "C:\\...\\fcerta_29.ib" [--commit]
"""
import fdb, json, urllib.request, urllib.error, argparse, re
FB=r"C:\Users\carlo\FCertaSync\fb25\fbembed.dll"
SB="https://gclayesytzzpzkjvgede.supabase.co/rest/v1"; KEY=os.environ.get("SUPABASE_KEY", "sb_secret_HpoqM6ujk2yD6la7KM3cuQ_pdWBK8jo")
CID="62f98634-77ff-42f4-acaf-8561d56583da"

def s(v): return str(v).strip() if v is not None else ""
def digits(v): return re.sub(r"\D","", s(v))
def ncod(v):
    t=s(v).split()[0] if s(v) else ""
    try: return str(int(t))
    except (ValueError,TypeError): return ""
def sb_all(path):
    out,page=[],0
    while True:
        h={"apikey":KEY,"Authorization":"Bearer "+KEY,"Range-Unit":"items","Range":f"{page*1000}-{page*1000+999}"}
        ch=json.loads(urllib.request.urlopen(urllib.request.Request(SB+path,headers=h)).read().decode())
        out+=ch
        if len(ch)<1000: return out
        page+=1
def sb_post(path, rows):
    r=urllib.request.Request(SB+path, data=json.dumps(rows).encode(), method="POST",
        headers={"apikey":KEY,"Authorization":"Bearer "+KEY,"Content-Type":"application/json","Prefer":"return=minimal"})
    return urllib.request.urlopen(r).status

ap=argparse.ArgumentParser(); ap.add_argument("--db",required=True); ap.add_argument("--commit",action="store_true")
args=ap.parse_args(); DRY=not args.commit

con=fdb.connect(database=args.db,user="SYSDBA",password="masterkey",fb_library_name=FB,charset="NONE")
cur=con.cursor()
# FORNECID -> CNPJ (só dígitos)
cur.execute("select FORNECID, NRCNPJ from FC02000")
cnpj_por_fid={s(a):digits(b) for a,b in cur.fetchall() if digits(b)}
# associações distintas (FORNECID, CPROD) -> (CDPRO, XPROD)
cur.execute("select FORNECID, CPROD, CDPRO, XPROD from FC11100 where CPROD is not null and CDPRO is not null and CPROD<>''")
assoc={}
for fid,cprod,cdpro,xprod in cur.fetchall():
    k=(s(fid), s(cprod))
    if k not in assoc: assoc[k]=(ncod(cdpro), s(xprod))
con.close()
print(f"FCerta: {len(assoc)} associações (FORNECID,CPROD)->CDPRO | {len(cnpj_por_fid)} fornecedores c/ CNPJ")

# TAO: mapas
forn_por_cnpj={digits(f.get("cnpj")):f["id"] for f in sb_all(f"/fornecedores?cliente_id=eq.{CID}&select=id,cnpj") if digits(f.get("cnpj"))}
ativo_por_cod={ncod(a.get("codigo_fc")):a["id"] for a in sb_all(f"/ativos?cliente_id=eq.{CID}&select=id,codigo_fc") if ncod(a.get("codigo_fc"))}
existe={(d["fornecedor_id"], s(d["cod_fornecedor"])) for d in sb_all(f"/estoque_forn_depara?cliente_id=eq.{CID}&select=fornecedor_id,cod_fornecedor")}
print(f"TAO: {len(forn_por_cnpj)} fornecedores c/ CNPJ | {len(ativo_por_cod)} ativos c/ codigo | {len(existe)} de-para já cadastrados")

plano=[]; sem_forn=sem_ativo=ja=0
for (fid,cprod),(cdpro,xprod) in assoc.items():
    cnpj=cnpj_por_fid.get(fid)
    forn_id=forn_por_cnpj.get(cnpj) if cnpj else None
    ativo_id=ativo_por_cod.get(cdpro)
    if not forn_id: sem_forn+=1; continue
    if not ativo_id: sem_ativo+=1; continue
    if (forn_id, cprod) in existe: ja+=1; continue
    plano.append({"cliente_id":CID,"fornecedor_id":forn_id,"cod_fornecedor":cprod,
                  "descr_fornecedor":xprod[:120] or None,"ativo_id":ativo_id})
    existe.add((forn_id,cprod))

print(f"\nPLANO: inserir {len(plano)} de-para | já cadastrados {ja} | fornecedor ausente no TAO {sem_forn} | ativo ausente {sem_ativo}")
for p in plano[:10]: print(f"  + forn {p['fornecedor_id'][:8]} · cod {p['cod_fornecedor']:<16} -> ativo {p['ativo_id'][:8]}  {p['descr_fornecedor'][:24] if p['descr_fornecedor'] else ''}")
if len(plano)>10: print(f"  ... +{len(plano)-10}")

if DRY:
    print("\nDRY-RUN: nada gravado (use --commit).")
else:
    err=ins=0
    for i in range(0,len(plano),500):
        batch=plano[i:i+500]
        try: sb_post("/estoque_forn_depara", batch); ins+=len(batch)
        except urllib.error.HTTPError as e: err+=1; print("  ERRO batch", e.read()[:160])
    print(f"\nGRAVADO: {ins} de-para inseridos | erros {err}")
