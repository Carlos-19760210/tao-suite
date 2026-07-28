#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
CONFRONTO DIÁRIO — FCerta × TAO Neo (validação da operação espelhada).
SOMENTE LEITURA nos dois lados (SELECT no FCerta, GET no Supabase). NÃO grava nada.

Para um dia, descobre as requisições dos DOIS lados:
  • FCerta: FC12100 com DTCAD = <dia>  (requisições cadastradas no dia)
  • TAO:    orcamentos 0001-<req>-<serie> — buscados por NÚMERO em qualquer data
            (a req do dia pode ser aprovada/importada no TAO no dia seguinte)
e confronta, por requisição/série: valor cheio, existência/OM e pesagem item-a-item.
Classifica a cobertura e sugere correções (não aplica).

Saídas: CONFRONTO_DIARIO_<dia>.md  +  CONFRONTO_DIARIO_<dia>.xlsx
Uso: python confronto_diario.py --db "C:\\...\\fcerta_28.ib" --dia 2026-07-27
"""
import fdb, json, urllib.request, argparse, datetime
import openpyxl
from openpyxl.styles import Font, PatternFill, Alignment, Border, Side

FB_DLL = r"C:\Users\carlo\FCertaSync\fb25\fbembed.dll"
SB  = "https://gclayesytzzpzkjvgede.supabase.co/rest/v1"
KEY = "sb_secret_HpoqM6ujk2yD6la7KM3cuQ_pdWBK8jo"
CID = "62f98634-77ff-42f4-acaf-8561d56583da"
APROVADOS = ("aprovado_farma", "aceito_paciente")

def dec(v): return v.decode("latin-1","replace").strip() if isinstance(v,(bytes,bytearray)) else (v.strip() if isinstance(v,str) else v)
def f(v, d=None):
    try: return float(v)
    except (TypeError, ValueError): return d
def serier_int(v):
    if isinstance(v,int): return v
    t=(dec(v) or "0").upper()
    if t.isdigit(): return int(t)
    if len(t)==1 and "A"<=t<="Z": return 10+ord(t)-ord("A")
    return 0
def sbq(p):
    h={"apikey":KEY,"Authorization":"Bearer "+KEY}
    return json.loads(urllib.request.urlopen(urllib.request.Request(SB+p,headers=h)).read().decode())
def sb_all(path):
    out,page=[],0
    while True:
        h={"apikey":KEY,"Authorization":"Bearer "+KEY,"Range-Unit":"items","Range":f"{page*1000}-{page*1000+999}"}
        ch=json.loads(urllib.request.urlopen(urllib.request.Request(SB+path,headers=h)).read().decode())
        out+=ch
        if len(ch)<1000: return out
        page+=1

ap=argparse.ArgumentParser()
ap.add_argument("--db", required=True)
ap.add_argument("--dia", required=True, help="YYYY-MM-DD")
args=ap.parse_args()
DIA=args.dia

con=fdb.connect(database=args.db, user="SYSDBA", password="masterkey", fb_library_name=FB_DLL, charset="NONE")
cur=con.cursor()
cod_por_ativo={a["id"]:str(a["codigo_fc"]).strip() for a in sb_all(f"/ativos?cliente_id=eq.{CID}&select=id,codigo_fc&order=id.asc") if a.get("codigo_fc")}

# ── FCerta: requisições cadastradas no dia ───────────────────────────────────
cur.execute("""SELECT NRRQU, SERIER, NOMEPA, PRCOBR, PRREAL, VRDSC, FLAGROM, INDCANCDAV
               FROM FC12100 WHERE CAST(DTCAD AS DATE)=?""",(DIA,))
fc={}
for nrrqu,serier,nomepa,prcobr,prreal,vrdsc,flagrom,indcanc in cur.fetchall():
    fc[(int(nrrqu),serier_int(serier))]={"nomepa":dec(nomepa),"prcobr":f(prcobr),"prreal":f(prreal),
        "vrdsc":f(vrdsc,0.0),"serier":serier,"cancel":(dec(indcanc)=="S"),"rom":(dec(flagrom) in ("S","1"))}

# ── TAO: todos os orçamentos 0001-* (indexa por req+série; aprovado tem prioridade) ──
d0=DIA; d1=(datetime.date.fromisoformat(DIA)+datetime.timedelta(days=1)).isoformat()
tao_orc={}; tao_dia=set()
for o in sb_all(f"/orcamentos?cliente_id=eq.{CID}&numero_orcamento=like.0001-*"
                "&select=numero_orcamento,status,valor_final_fc,total_orcamento,desconto_fc,card_id,criado_em&order=criado_em.asc"):
    parts=str(o["numero_orcamento"]).split("-")
    if len(parts)!=3: continue
    try: key=(int(parts[1]), int(parts[2]))
    except ValueError: continue
    prev=tao_orc.get(key)
    if prev is None or (o["status"] in APROVADOS) or (prev["status"] not in APROVADOS): tao_orc[key]=o
    ce=str(o.get("criado_em",""))
    if d0<=ce<d1: tao_dia.add(key)

chaves=sorted(set(fc)|tao_dia)

# ── Confronto (coleta estruturada) ───────────────────────────────────────────
comp=[]      # linha por req/série (aba Comparativo)
pesag=[]     # item a item (aba Pesagem)
correcoes=[] # (num, texto)
okval=okpes=totpes=0

for (nr,sr) in chaves:
    num=f"0001-{str(nr).zfill(6)}-{sr}"
    F=fc.get((nr,sr)); T=tao_orc.get((nr,sr))
    pac=(F["nomepa"] if F else "") or ""

    if F and not T:
        if F["cancel"]:
            comp.append([num,pac,"Cancelada no FCerta",F["prcobr"],None,None,"","", "OK"]); continue
        comp.append([num,pac,"Fechada no FCerta, fora do TAO",F["prcobr"],None,None,"","", "Fora do TAO"])
        correcoes.append((num,"Requisição fechada no FCerta e ausente no TAO — atendimento feito fora do TAO Neo.")); continue
    if T and not F:
        st=T["status"]; aprovado=st in APROVADOS
        cur.execute("SELECT FIRST 1 CAST(DTCAD AS DATE) FROM FC12100 WHERE NRRQU=?",(nr,))
        outra=cur.fetchone()
        vtao=f(T.get("valor_final_fc")) if T.get("valor_final_fc") is not None else f(T.get("total_orcamento"))
        if not aprovado and not outra:
            comp.append([num,pac,"Orçamento aberto no TAO (não fechado)",None,vtao,None,st,"", "Aberto"]); continue
        if outra:
            comp.append([num,pac,f"Reaberto — req FCerta de {outra[0]}",None,vtao,None,st,"", "Reaberto"])
            correcoes.append((num,f"Orçamento no TAO refere-se a requisição do FCerta de {outra[0]} (reaberto) — apenas conferir.")); continue
        comp.append([num,pac,"APROVADO no TAO, sem req no FCerta",None,vtao,None,st,"", "Inconsistência"])
        correcoes.append((num,"Aprovado no TAO porém não fechado no FCerta — conferir se a venda foi efetivada.")); continue

    # ambos existem
    val_fc=F["prcobr"]
    val_fin=f(T.get("valor_final_fc")) if T.get("valor_final_fc") is not None else f(T.get("total_orcamento"))
    desc=f(T.get("desconto_fc"),0.0) or 0.0
    val_tao=(val_fin+desc) if val_fin is not None else None
    dif=(round(val_tao-val_fc,2) if (val_fc is not None and val_tao is not None) else None)
    val_ok=(dif is not None and abs(dif)<=0.01)
    if val_ok: okval+=1
    st=T["status"]; aprovado=st in APROVADOS
    om=sbq(f"/lab_ordens?cliente_id=eq.{CID}&numero=eq.{num}&status=neq.cancelada&select=id,status&limit=1")
    om_txt=om[0]["status"] if om else "sem OM"

    situ = "OK" if (val_ok and aprovado and om) else ("Divergência de valor" if not val_ok else ("Sem OM" if not om else ("Não aprovado" if not aprovado else "Conferir")))
    resultado = "OK" if situ=="OK" else "Divergência"
    comp.append([num,pac,situ,val_fc,round(val_tao,2) if val_tao is not None else None,dif,st,om_txt,resultado])

    if not val_ok: correcoes.append((num,f"Valor cheio difere (FCerta R$ {val_fc} × TAO R$ {round(val_tao,2) if val_tao is not None else '—'}) — conferir desconto/valor final."))
    if not aprovado: correcoes.append((num,f"Orçamento em '{st}' — aprovar no TAO."))
    if aprovado and not om: correcoes.append((num,"Aprovado sem OM no TAO — verificar geração da Ordem de Manipulação."))

    if not om: continue
    c2=con.cursor()
    c2.execute("""SELECT CDPRO, DESCR, QUANT, QTREAL FROM FC12110
                  WHERE NRRQU=? AND SERIER=? AND TPCMP IN ('C','P')""",(nr,F["serier"]))
    fcit={str(cd):{"descr":dec(ds),"qtreal":f(qr),"quant":f(qt)} for cd,ds,qt,qr in c2.fetchall()}
    for it in sbq(f"/lab_ordem_itens?ordem_id=eq.{om[0]['id']}&select=ativo_id,descricao,qtd_pesar&order=ordem.asc"):
        m=fcit.get(cod_por_ativo.get(it.get("ativo_id") or "",""))
        if not m: continue
        totpes+=1
        a=m["qtreal"] if m["qtreal"] is not None else m["quant"]; b=f(it.get("qtd_pesar"))
        if a is None or b is None: continue
        confere=abs(a-b)<=max(0.001,0.001*abs(a))
        if confere: okpes+=1
        else: correcoes.append((num,f"Pesagem do item {m['descr']} difere (FCerta {a} × TAO {b}) — revisar quantidade na OM."))
        pesag.append([num,pac,m["descr"],a,b,round(b-a,4) if (a is not None and b is not None) else None,"OK" if confere else "DIVERGE"])

con.close()

# ── XLSX ─────────────────────────────────────────────────────────────────────
HDR=Font(bold=True,color="FFFFFF"); FILLH=PatternFill("solid",fgColor="1F4E78")
CEN=Alignment(horizontal="center"); BORD=Border(*[Side(style="thin",color="D9D9D9")]*4)
COR={"OK":"C6EFCE","Divergência":"FFC7CE","Fora do TAO":"FFEB9C","Aberto":"F2F2F2",
     "Reaberto":"FFEB9C","Inconsistência":"FFC7CE","DIVERGE":"FFC7CE"}
def estilo_aba(ws,ncols):
    for c in ws[1]:
        c.font=HDR; c.fill=FILLH; c.alignment=CEN
    ws.freeze_panes="A2"
    for col in ws.columns:
        w=max((len(str(c.value)) if c.value is not None else 0) for c in col)
        ws.column_dimensions[col[0].column_letter].width=min(max(w+2,10),46)

wb=openpyxl.Workbook()
# Resumo
ws=wb.active; ws.title="Resumo"
n_ok=sum(1 for r in comp if r[8]=="OK"); n_div=sum(1 for r in comp if r[8]=="Divergência")
n_fora=sum(1 for r in comp if r[8]=="Fora do TAO"); n_ab=sum(1 for r in comp if r[8]=="Aberto")
for i,(k,v) in enumerate([("Confronto Diário FCerta × TAO Neo",DIA),
        ("Backup FCerta",args.db),("Gerado",datetime.datetime.now().strftime("%Y-%m-%d %H:%M")),("",""),
        ("Requisições/séries confrontadas",len(comp)),("Batendo 100% (valor+aprovado+OM)",n_ok),
        ("Com divergência (valor/OM)",n_div),("Fechadas no FCerta fora do TAO",n_fora),
        ("Orçamentos abertos no TAO (normal)",n_ab),
        ("Valor cheio conferindo (±R$0,01)",okval),
        ("Itens de pesagem confrontados",totpes),("Itens de pesagem conferindo (±0,001)",okpes),
        ("Correções sugeridas",len(correcoes))],1):
    ws.cell(i,1,k).font=Font(bold=True); ws.cell(i,2,v)
ws.column_dimensions["A"].width=40; ws.column_dimensions["B"].width=48

# Comparativo
ws=wb.create_sheet("Comparativo")
ws.append(["Número","Paciente","Situação","Valor FCerta (cheio)","Valor TAO (bruto)","Diferença","Status orçamento","OM","Resultado"])
for r in comp:
    ws.append(r)
    fillcor=COR.get(r[8]);
    if fillcor:
        for c in ws[ws.max_row]: c.fill=PatternFill("solid",fgColor=fillcor)
for row in ws.iter_rows(min_row=2,min_col=4,max_col=6):
    for c in row:
        if isinstance(c.value,(int,float)): c.number_format='#,##0.00'
estilo_aba(ws,9)

# Divergências (valor/OM/pesagem) + correção
ws=wb.create_sheet("Divergências")
ws.append(["Número","Correção sugerida"])
for num,txt in correcoes:
    ws.append([num,txt])
    for c in ws[ws.max_row]: c.fill=PatternFill("solid",fgColor="FFF2CC")
estilo_aba(ws,2)

# Pesagem item-a-item
ws=wb.create_sheet("Pesagem")
ws.append(["Número","Paciente","Item","Qtd FCerta","Qtd TAO","Diferença","Confere?"])
for r in pesag:
    ws.append(r)
    if r[6]=="DIVERGE":
        for c in ws[ws.max_row]: c.fill=PatternFill("solid",fgColor="FFC7CE")
for row in ws.iter_rows(min_row=2,min_col=4,max_col=6):
    for c in row:
        if isinstance(c.value,(int,float)): c.number_format='0.0000'
estilo_aba(ws,7)

outx=rf"C:\Users\carlo\Downloads\CONFRONTO_DIARIO_{DIA}.xlsx"
wb.save(outx)
print(f"Comparativo: {len(comp)} linhas | OK {n_ok} | divergências {n_div} | fora do TAO {n_fora} | abertos {n_ab}")
print(f"Pesagem: {totpes} itens ({okpes} conferem) | Correções: {len(correcoes)}")
print("Gerado:", outx)
