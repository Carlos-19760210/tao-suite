#!/usr/bin/env python3
# -*- coding: utf-8 -*-
import os
"""
CONFRONTO DE CADASTRO DE ATIVOS — FCerta (FC03000) × TAO Neo (ativos).
SOMENTE LEITURA. Compara, por codigo_fc, os campos do cadastro — com foco nos que
afetam a PESAGEM (diluição, teor, densidade, fator de correção) — e os cadastrais
gerais (unidade, preços, DCB, nome). Aplica a mesma normalização do sync_ativos
(diluição 0->1, teor 0->100) para não acusar diferença onde é regra proposital.

Saída: CONFRONTO_ATIVOS_<data>.xlsx  (abas Resumo / Pesagem / Preços / Cadastro / Cobertura)
Uso: python confronto_ativos.py --db "C:\\...\\fcerta_28.ib"
"""
import fdb, json, urllib.request, argparse, datetime
import openpyxl
from openpyxl.styles import Font, PatternFill, Alignment

FB_DLL = r"C:\Users\carlo\FCertaSync\fb25\fbembed.dll"
SB  = "https://gclayesytzzpzkjvgede.supabase.co/rest/v1"
KEY = os.environ.get("SUPABASE_KEY", "sb_secret_HpoqM6ujk2yD6la7KM3cuQ_pdWBK8jo")
CID = "62f98634-77ff-42f4-acaf-8561d56583da"

def dec(v): return v.decode("latin-1","replace").strip() if isinstance(v,(bytes,bytearray)) else (v.strip() if isinstance(v,str) else v)
def num(v, d=None):
    try: return float(v)
    except (TypeError, ValueError): return d
def sb_all(path):
    out,page=[],0
    while True:
        h={"apikey":KEY,"Authorization":"Bearer "+KEY,"Range-Unit":"items","Range":f"{page*1000}-{page*1000+999}"}
        ch=json.loads(urllib.request.urlopen(urllib.request.Request(SB+path,headers=h)).read().decode())
        out+=ch
        if len(ch)<1000: return out
        page+=1

ap=argparse.ArgumentParser(); ap.add_argument("--db", required=True); args=ap.parse_args()
HOJE=datetime.date.today().isoformat()

con=fdb.connect(database=args.db, user="SYSDBA", password="masterkey", fb_library_name=FB_DLL, charset="NONE")
cur=con.cursor()

# ── FCerta (FC03000 × FC03100, ativos) ───────────────────────────────────────
fc={}
for grupo in ("M","E"):
    cur.execute(f"""SELECT p.CDPRO, TRIM(REPLACE(p.DESCR,'@','')), COALESCE(TRIM(p.UNIDA),''),
                           p.DILUICAO, p.TEOR, p.DENSIDADE, p.FATOR, p.PRCOM, p.PRVEN,
                           COALESCE(TRIM(p.CDDCB),''), e.PRCOMCTB
                    FROM FC03000 p JOIN FC03100 e ON e.CDPRO=p.CDPRO AND e.CDFIL=1
                    WHERE p.SITUA='A' AND p.GRUPO='{grupo}'""")
    for r in cur.fetchall():
        cod=str(r[0]).strip()
        dil=num(r[3],0) or 0; teo=num(r[4],0) or 0
        fc[cod]={"nome":dec(r[1]),"unidade":dec(r[2]),
                 "diluicao": dil if dil>0 else 1.0,      # normalização igual ao sync
                 "teor": teo if teo>0 else 100.0,
                 "densidade": num(r[5],1.0) or 1.0, "fator": num(r[6],1.0) or 1.0,
                 "preco_compra": num(r[7]), "preco_venda": num(r[8]),
                 "dcb": dec(r[9]), "preco_custo": num(r[10])}
con.close()

# ── TAO (ativos) ─────────────────────────────────────────────────────────────
tao={}
for a in sb_all(f"/ativos?cliente_id=eq.{CID}&ativo=eq.true&select=codigo_fc,nome,unidade,diluicao,teor,densidade,fator_correcao,preco_compra,preco_venda,preco_custo,dcb"):
    c=str(a.get("codigo_fc") or "").strip()
    if c: tao[c]=a

def difnum(a,b,rel=0.01):
    if a is None or b is None: return a!=b
    try: a=float(a); b=float(b)
    except (TypeError,ValueError): return True
    return abs(a-b) > max(0.001, rel*max(abs(a),abs(b)))

so_fc=[c for c in fc if c not in tao]
so_tao=[c for c in tao if c not in fc]

# diferenças por categoria
peso_rows=[]   # diluicao/teor/densidade/fator
preco_rows=[]  # compra/venda/custo
cad_rows=[]    # nome/unidade/dcb
PESO=[("diluicao","diluicao",100),("teor","teor",1),("densidade","densidade",1),("fator","fator_correcao",1)]
PRECO=[("preco_compra","preco_compra"),("preco_venda","preco_venda"),("preco_custo","preco_custo")]

for cod in sorted(set(fc)&set(tao)):
    F=fc[cod]; T=tao[cod]
    for label,tcol,_ in PESO:
        vf=F[label]; vt=num(T.get(tcol))
        if difnum(vf,vt):
            fator = (round(vf/vt,3) if (vt not in (None,0)) else "—")
            peso_rows.append([cod, F["nome"], label, vf, vt, fator])
    for label,tcol in PRECO:
        vf=F[label]; vt=num(T.get(tcol))
        if vf is not None and difnum(vf,vt,0.02):
            preco_rows.append([cod, F["nome"], label, vf, vt, (round((vt or 0)-vf,2) if vt is not None else None)])
    # nome/unidade/dcb
    for label,tcol in [("unidade","unidade"),("dcb","dcb")]:
        vf=(F[label] or "").strip().upper(); vt=(str(T.get(tcol) or "")).strip().upper()
        if vf and vf!=vt:
            cad_rows.append([cod, F["nome"], label, (F[label] or "").strip(), (str(T.get(tcol) or "")).strip()])

# ── XLSX ─────────────────────────────────────────────────────────────────────
HDR=Font(bold=True,color="FFFFFF"); FILLH=PatternFill("solid",fgColor="1F4E78"); CEN=Alignment(horizontal="center")
def aba(ws):
    for c in ws[1]: c.font=HDR; c.fill=FILLH; c.alignment=CEN
    ws.freeze_panes="A2"
    for col in ws.columns:
        w=max((len(str(c.value)) if c.value is not None else 0) for c in col)
        ws.column_dimensions[col[0].column_letter].width=min(max(w+2,10),46)

wb=openpyxl.Workbook()
ws=wb.active; ws.title="Resumo"
for i,(k,v) in enumerate([("Confronto de cadastro de ativos — FCerta × TAO Neo",HOJE),
        ("Backup FCerta",args.db),("",""),
        ("Ativos no FCerta (M+E ativos)",len(fc)),("Ativos no TAO (com codigo_fc)",len(tao)),
        ("Só no FCerta (faltam no TAO)",len(so_fc)),("Só no TAO (não no FCerta)",len(so_tao)),("",""),
        ("Diferenças que AFETAM PESAGEM (dilui/teor/dens/fator)",len(peso_rows)),
        ("Diferenças de preço",len(preco_rows)),
        ("Diferenças de unidade/DCB",len(cad_rows))],1):
    ws.cell(i,1,k).font=Font(bold=True); ws.cell(i,2,v)
ws.column_dimensions["A"].width=52; ws.column_dimensions["B"].width=46

ws=wb.create_sheet("Pesagem"); ws.append(["Código","Ativo","Campo","FCerta","TAO","Fator (FC/TAO)"])
for r in peso_rows:
    ws.append(r)
    for c in ws[ws.max_row]: c.fill=PatternFill("solid",fgColor="FFC7CE")
aba(ws)
ws=wb.create_sheet("Preços"); ws.append(["Código","Ativo","Campo","FCerta","TAO","Dif (TAO-FC)"]); [ws.append(r) for r in preco_rows]; aba(ws)
ws=wb.create_sheet("Cadastro"); ws.append(["Código","Ativo","Campo","FCerta","TAO"]); [ws.append(r) for r in cad_rows]; aba(ws)
ws=wb.create_sheet("Cobertura"); ws.append(["Situação","Código","Ativo"])
for c in so_fc: ws.append(["Só no FCerta", c, fc[c]["nome"]])
for c in so_tao: ws.append(["Só no TAO", c, tao[c].get("nome","")])
aba(ws)

outx=rf"C:\Users\carlo\Downloads\CONFRONTO_ATIVOS_{HOJE}.xlsx"
wb.save(outx)
print(f"FCerta {len(fc)} × TAO {len(tao)} ativos | só FCerta {len(so_fc)} | só TAO {len(so_tao)}")
print(f"Diferenças: PESAGEM {len(peso_rows)} | preço {len(preco_rows)} | unidade/DCB {len(cad_rows)}")
print("Gerado:", outx)
