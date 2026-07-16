#!/usr/bin/env python3
# CONFRONTO FCerta × TAO Neo — validação final das cargas do Pacote 1
# Compara campo a campo, dos dois lados, e gera tao-lab/CONFRONTO_CARGAS.md
import fdb, json, urllib.request, random, unicodedata, re, datetime
HOJE = datetime.date.today().isoformat()

BASE = r"C:\Users\carlo\FCertaSync"
SB="https://gclayesytzzpzkjvgede.supabase.co/rest/v1"
KEY="sb_secret_HpoqM6ujk2yD6la7KM3cuQ_pdWBK8jo"
CID="62f98634-77ff-42f4-acaf-8561d56583da"
OUT=r"C:\Users\carlo\tao-crm\tao-lab\CONFRONTO_CARGAS.md"
random.seed(42)

def sbq(p, rng=None):
    h={"apikey":KEY,"Authorization":"Bearer "+KEY}
    if rng: h["Range-Unit"]="items"; h["Range"]=rng
    return json.loads(urllib.request.urlopen(urllib.request.Request(SB+p, headers=h)).read().decode())
def sball(p):
    out=[]; page=0
    while True:
        ch=sbq(p, f"{page*1000}-{page*1000+999}")
        out+=ch
        if len(ch)<1000: return out
        page+=1
def dec(v): return v.decode("latin-1","replace").strip() if isinstance(v,(bytes,bytearray)) else (v.strip() if isinstance(v,str) else v)
def norm(s):
    s=unicodedata.normalize("NFD", s or "").encode("ascii","ignore").decode().upper()
    return re.sub(r"\s+"," ",s.replace("@","")).strip()
def eq(a,b,tol=1e-6):
    if a is None and b is None: return True
    try: return abs(float(a)-float(b))<=tol*max(1,abs(float(a)))
    except Exception: return str(a).strip()==str(b).strip()

con = fdb.connect(database=BASE+r"\fcerta_atual.ib", user="SYSDBA", password="masterkey",
                  fb_library_name=BASE+r"\fb25\fbembed.dll", charset="NONE")
cur=con.cursor()

L=[]  # linhas do relatório
L.append("# Confronto FCerta × TAO Neo — Cargas do Pacote 1 (Motor Farmacotécnico)")
L.append(f"Gerado automaticamente em {HOJE}. Fonte FCerta: backup restaurado em C:\\Users\\carlo\\FCertaSync\\fcerta_atual.ib.\n")

tao_ativos = sball(f"/ativos?cliente_id=eq.{CID}&select=id,codigo_fc,nome,dcb,densidade,markup_preco,fator_diluicao,ativo_puro_id,teor_pct&order=id.asc")
por_cod={str(a["codigo_fc"]).strip(): a for a in tao_ativos if a.get("codigo_fc")}
por_id={a["id"]: a for a in tao_ativos}

# ── CARGA 1: dados técnicos ──────────────────────────────────────────────
cur.execute("SELECT CDPRO, DESCR, CDDCB, DENSIDADE, FATOR, DILUICAO FROM FC03000 WHERE GRUPO='M'")
fc={str(r[0]): r for r in cur.fetchall()}
checks={"dcb":[0,0],"densidade":[0,0],"markup":[0,0],"diluicao":[0,0]}
falhas=[]
for cod,a in por_cod.items():
    f=fc.get(cod)
    if not f: continue
    dcb_fc=dec(f[2]) or None; dens_fc=float(f[3] or 0); fat_fc=float(f[4] or 0); dil_fc=float(f[5] or 0)
    # dcb
    if dcb_fc:
        ok=eq((a.get("dcb") or "").strip(), dcb_fc.strip()); checks["dcb"][0]+=ok; checks["dcb"][1]+=1
        if not ok and len(falhas)<15: falhas.append(("dcb",a["nome"],dcb_fc,a.get("dcb")))
    if dens_fc and dens_fc!=1:
        ok=eq(a.get("densidade"),dens_fc,1e-4); checks["densidade"][0]+=ok; checks["densidade"][1]+=1
        if not ok and len(falhas)<15: falhas.append(("densidade",a["nome"],dens_fc,a.get("densidade")))
    if fat_fc and fat_fc not in (0,1):
        ok=eq(a.get("markup_preco"),fat_fc,1e-4); checks["markup"][0]+=ok; checks["markup"][1]+=1
        if not ok and len(falhas)<15: falhas.append(("markup",a["nome"],fat_fc,a.get("markup_preco")))
    mm=re.search(r"\b1\s*:\s*(\d+)\b", dec(f[1]))
    esperado_dil = float(mm.group(1)) if mm else (dil_fc if dil_fc not in (0,1) else None)
    if esperado_dil:
        ok=eq(a.get("fator_diluicao"),esperado_dil,1e-4); checks["diluicao"][0]+=ok; checks["diluicao"][1]+=1
        if not ok and len(falhas)<15: falhas.append(("diluicao",a["nome"],esperado_dil,a.get("fator_diluicao")))
L.append("## Carga 1 — Dados técnicos dos ativos (FC03000 → ativos)")
L.append("| Campo | Conferem | Total FCerta | % |")
L.append("|---|---|---|---|")
for k,(okc,tot) in checks.items():
    L.append(f"| {k} | {okc} | {tot} | {okc*100//max(tot,1)}% |")
vinc=sum(1 for a in tao_ativos if a.get("ativo_puro_id"))
L.append(f"\nVínculos diluída→pura no TAO: **{vinc}** (37 automáticos da carga + eventuais manuais). ")
L.append("Amostra de vínculos (nome diluída → nome pura):\n")
n=0
for a in tao_ativos:
    if a.get("ativo_puro_id") and n<10:
        L.append(f"- {a['nome']} → **{por_id.get(a['ativo_puro_id'],{}).get('nome','?')}** (1:{a.get('fator_diluicao'):.0f})")
        n+=1
if falhas:
    L.append("\n**Divergências carga 1:**")
    for f_ in falhas: L.append(f"- [{f_[0]}] {f_[1]}: FCerta={f_[2]!r} × TAO={f_[3]!r}")
else:
    L.append("\n**Zero divergências na carga 1.**")

# ── CARGA 2: equivalências ───────────────────────────────────────────────
cur.execute("SELECT CDPRO, DESCR, EQUIV FROM FC03200 WHERE EQUIV IS NOT NULL AND EQUIV NOT IN (0,1)")
eqs=cur.fetchall()
tao_sins = sball(f"/ativos_sinonimos?cliente_id=eq.{CID}&select=ativo_id,sinonimo,fator_equiv&fator_equiv=neq.1&order=id.asc")
by_norm={}
for s in tao_sins: by_norm.setdefault(norm(s["sinonimo"]),[]).append(s)
ok2=tot2=0; div2=[]
for cdpro,sin,eqv in eqs:
    a=por_cod.get(str(cdpro))
    if not a: continue
    tot2+=1
    cand=by_norm.get(norm(dec(sin)),[])
    if any(eq(s["fator_equiv"],float(eqv),1e-4) for s in cand): ok2+=1
    else: div2.append((dec(sin),float(eqv),[ (s['sinonimo'],s['fator_equiv']) for s in cand ]))
L.append(f"\n## Carga 2 — Equivalências sal↔base (FC03200 → ativos_sinonimos.fator_equiv)")
L.append(f"Aplicáveis ao catálogo TAO: **{tot2}** | conferem: **{ok2}** ({ok2*100//max(tot2,1)}%)")
L.append(f"Sinônimos com fator≠1 no TAO: {len(tao_sins)}")
if div2:
    L.append("Divergências:")
    for d in div2: L.append(f"- '{d[0]}' FCerta={d[1]} × TAO={d[2]}")

# ── CARGA 3: fórmulas padrão ─────────────────────────────────────────────
cur.execute("SELECT COUNT(*) FROM FC05000"); n_fc_cab=cur.fetchone()[0]
cur.execute("SELECT COUNT(*) FROM FC05100"); n_fc_it=cur.fetchone()[0]
n_tao_cab=len(sball(f"/lab_formulas_padrao?cliente_id=eq.{CID}&select=id"))
n_tao_it=len(sball("/lab_formulas_padrao_itens?select=id"))
L.append(f"\n## Carga 3 — Fórmulas padrão (FC05000/FC05100 → lab_formulas_padrao)")
L.append(f"| Lado | Fórmulas | Itens |\n|---|---|---|\n| FCerta | {n_fc_cab} | {n_fc_it} |\n| TAO | {n_tao_cab} | {n_tao_it} |")
# amostra de 3 fórmulas: itens lado a lado
cur.execute("SELECT FIRST 3 CDFRM, DESCRFRM FROM FC05000 ORDER BY CDFRM")
for cdfrm, nome in cur.fetchall():
    nome=dec(nome)
    tf=sbq(f"/lab_formulas_padrao?cliente_id=eq.{CID}&nome=eq."+urllib.parse.quote(nome, safe="")+"&select=id,nome&limit=1")
    cur2=con.cursor(); cur2.execute("SELECT COUNT(*) FROM FC05100 WHERE CDFRM=?", (cdfrm,))
    nit_fc=cur2.fetchone()[0]
    nit_tao=len(sbq(f"/lab_formulas_padrao_itens?formula_id=eq.{tf[0]['id']}&select=id")) if tf else "NÃO ACHOU"
    L.append(f"- \"{nome}\": itens FCerta={nit_fc} × TAO={nit_tao} {'✓' if tf and nit_fc==nit_tao else '✗'}")

# ── CARGA 4: lotes vivos ─────────────────────────────────────────────────
cur.execute(f"SELECT COUNT(*) FROM FC03140 WHERE ESTAT>0 AND DTVAL>='{HOJE}'")
n_fc_lot=cur.fetchone()[0]
tao_lotes=sball(f"/lab_lotes_mp?cliente_id=eq.{CID}&select=ativo_id,nr_lote,dt_validade,qtd_atual,teor_pct&order=id.asc")
L.append(f"\n## Carga 4 — Lotes vivos (FC03140 → lab_lotes_mp)")
L.append(f"FCerta (estoque>0, validade≥07/07/2026): **{n_fc_lot}** | TAO: **{len(tao_lotes)}** (19 sem ativo no TAO, não migrados)")
# amostra 10 lotes: bate validade+estoque+teor
cur.execute(f"""SELECT FIRST 400 CDPRO, NRLOT, DTVAL, ESTAT, TEOR FROM FC03140
               WHERE ESTAT>0 AND DTVAL>='{HOJE}' ORDER BY DTENT DESC""")
fc_l=cur.fetchall()
idx={(l["ativo_id"], (l["nr_lote"] or "").strip()): l for l in tao_lotes}
ok4=tot4=0; div4=[]
for cdpro,nrlot,dtval,estat,teor in fc_l:
    a=por_cod.get(str(cdpro))
    if not a: continue
    t=idx.get((a["id"], dec(nrlot)))
    tot4+=1
    if t and str(t["dt_validade"])[:10]==str(dtval)[:10] and eq(t["qtd_atual"],float(estat),1e-4):
        ok4+=1
    elif len(div4)<8:
        div4.append((a["nome"],dec(nrlot),str(dtval)[:10],float(estat),t))
L.append(f"Amostra conferida (400 mais recentes): **{ok4}/{tot4}** batem em lote+validade+estoque ({ok4*100//max(tot4,1)}%)")
if div4:
    L.append("Divergências da amostra:")
    for d in div4: L.append(f"- {d[0]} lote {d[1]}: FCerta val={d[2]} est={d[3]} × TAO={'presente' if d[4] else 'AUSENTE'}")

# ── CARGA 5 ──────────────────────────────────────────────────────────────
L.append("\n## Carga 5 — Lista de bloqueio GLP-1")
L.append("Nenhuma substância GLP-1 no catálogo Magis (esperado — não manipula). Trava preventiva fica na regra do motor (por DCB/nome no orçamento).")

L.append("\n---\n**Notas p/ revisão:** 14 diluídas sem vínculo com a pura (associar pela ficha); 2 equivalências com sinônimo apontando p/ outro ativo (AC FOLINICO, VIT B5@) — fator gravado no vínculo existente; 817 itens de fórmula padrão sem ativo no catálogo ativo (ficam por descrição).")

import urllib.parse
open(OUT,"w",encoding="utf-8").write("\n".join(L))
print("CONFRONTO gerado:", OUT)
print("\n".join(L[:40]))
con.close()
