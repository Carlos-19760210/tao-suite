#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
CONFRONTO DIÁRIO — FCerta × TAO Neo (validação da operação espelhada).
SOMENTE LEITURA nos dois lados (SELECT no FCerta, GET no Supabase). NÃO grava nada.

Para um dia, descobre automaticamente as requisições dos DOIS lados:
  • FCerta: FC12100 com DTCAD = <dia>  (requisições cadastradas no dia)
  • TAO:    orcamentos criados no <dia> com número 0001-<req>-<serie>
e confronta, por requisição/série:
  • valor cheio     FC12100.PRCOBR × (orcamentos.valor_final_fc + desconto_fc)
  • existência/OM   lab_ordens com o número da req (status)
  • pesagem/item    FC12110.QTREAL × lab_ordem_itens.qtd_pesar (match por CDPRO)
e para cada diferença sugere a CORREÇÃO (não aplica). Gera CONFRONTO_DIARIO_<dia>.md.

Uso: python confronto_diario.py --db "C:\\...\\fcerta_28.ib" --dia 2026-07-27
"""
import fdb, json, urllib.request, argparse, datetime, unicodedata, re

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

# mapa ativo_id -> codigo_fc (p/ casar item TAO×FCerta)
cod_por_ativo={a["id"]:str(a["codigo_fc"]).strip() for a in sb_all(f"/ativos?cliente_id=eq.{CID}&select=id,codigo_fc&order=id.asc") if a.get("codigo_fc")}

# ── Lado FCerta: requisições cadastradas no dia ──────────────────────────────
cur.execute("""SELECT NRRQU, SERIER, NOMEPA, PRCOBR, PRREAL, VRDSC, NRORC, SERIEO, FLAGROM, INDCANCDAV
               FROM FC12100 WHERE CAST(DTCAD AS DATE)=?""",(DIA,))
fc={}   # (nrrqu,serie_int) -> dados
for nrrqu,serier,nomepa,prcobr,prreal,vrdsc,nrorc,serieo,flagrom,indcanc in cur.fetchall():
    sr=serier_int(serier)
    fc[(int(nrrqu),sr)]={"nomepa":dec(nomepa),"prcobr":f(prcobr),"prreal":f(prreal),"vrdsc":f(vrdsc,0.0),
                         "serier":serier,"cancel":(dec(indcanc)=="S"),"rom":(dec(flagrom) in ("S","1"))}

# ── Lado TAO: TODOS os orçamentos 0001-* (indexa por req+série; aprovado tem prioridade).
#    Busca por NÚMERO, não por data: a req do FCerta do dia pode ter sido importada/aprovada
#    no TAO em outro dia (ex.: aprovada no dia seguinte). tao_dia = criados no próprio dia.
d0=DIA; d1=(datetime.date.fromisoformat(DIA)+datetime.timedelta(days=1)).isoformat()
tao_orc={}; tao_dia=set()
for o in sb_all(f"/orcamentos?cliente_id=eq.{CID}&numero_orcamento=like.0001-*"
                "&select=numero_orcamento,status,valor_final_fc,total_orcamento,desconto_fc,card_id,criado_em&order=criado_em.asc"):
    parts=str(o["numero_orcamento"]).split("-")
    if len(parts)!=3: continue
    try: key=(int(parts[1]), int(parts[2]))
    except ValueError: continue
    prev=tao_orc.get(key)
    if prev is None or (o["status"] in APROVADOS) or (prev["status"] not in APROVADOS):
        tao_orc[key]=o
    ce=str(o.get("criado_em",""))
    if d0<=ce<d1: tao_dia.add(key)

# chaves = requisições do FCerta do dia  ∪  orçamentos criados no TAO no dia
chaves=sorted(set(fc)|tao_dia)

L=[f"# Confronto Diário FCerta × TAO Neo — {DIA}",
   f"Backup FCerta: `{args.db}` (somente leitura) · gerado {datetime.datetime.now().strftime('%Y-%m-%d %H:%M')}",
   f"Requisições/séries no dia: **{len(chaves)}** (FCerta {len(fc)} · TAO {len(tao_orc)})", ""]
tot=okval=okpes=totpes=0
diverg=[]; correcoes=[]

for (nr,sr) in chaves:
    num=f"0001-{str(nr).zfill(6)}-{sr}"
    F=fc.get((nr,sr)); T=tao_orc.get((nr,sr))
    tot+=1

    # cobertura
    if F and not T:
        if F["cancel"]:
            L.append(f"- **{num}** ({F['nomepa'] or '—'}): cancelada no FCerta, ausente no TAO — OK."); continue
        L.append(f"- **{num}** ({F['nomepa'] or '—'}): no FCerta (R$ {F['prcobr']}), **não passou pelo TAO** (atendimento fora do TAO).")
        correcoes.append(f"{num}: requisição fechada no FCerta e ausente no TAO — atendimento feito fora do TAO Neo.")
        continue
    if T and not F:
        st=T["status"]; aprovado=st in APROVADOS
        # a req existe no FCerta em OUTRA data?
        cur.execute("SELECT FIRST 1 CAST(DTCAD AS DATE) FROM FC12100 WHERE NRRQU=?",(nr,))
        outra=cur.fetchone()
        if not aprovado and not outra:
            L.append(f"- {num}: orçamento **aberto** no TAO ({st}), não fechado no FCerta — normal (aguardando decisão do cliente)."); continue
        if outra:
            L.append(f"- **{num}**: no TAO ({st}); no FCerta a req é de **{outra[0]}**, não do dia — orçamento reaberto/editado.")
            correcoes.append(f"{num}: orçamento no TAO refere-se a requisição do FCerta de {outra[0]} (reaberto) — apenas conferir, não é do dia.")
            continue
        # aprovado no TAO mas sem req no FCerta em data nenhuma
        L.append(f"- **{num}**: **APROVADO no TAO** ({st}) mas **sem requisição no FCerta** — inconsistência.")
        correcoes.append(f"{num}: aprovado no TAO porém não fechado no FCerta — conferir se a venda foi efetivada no FCerta.")
        continue

    # ambos existem — confronto de valor (cheio)
    val_fc=F["prcobr"]
    val_fin=f(T.get("valor_final_fc")) if T.get("valor_final_fc") is not None else f(T.get("total_orcamento"))
    desc=f(T.get("desconto_fc"),0.0) or 0.0
    val_tao=(val_fin+desc) if val_fin is not None else None
    val_ok=(val_fc is not None and val_tao is not None and abs(val_fc-val_tao)<=0.01)
    if val_ok: okval+=1
    st=T["status"]; aprovado=st in APROVADOS
    om=sbq(f"/lab_ordens?cliente_id=eq.{CID}&numero=eq.{num}&status=neq.cancelada&select=id,status&limit=1")
    om_txt=om[0]["status"] if om else "sem OM"

    flag="✓" if (val_ok and aprovado and om) else "✗"
    L.append(f"- **{num}** ({F['nomepa'] or '—'}): cheio FC×TAO = R$ {val_fc} × R$ {val_tao} "
             f"{'✓' if val_ok else '✗'} | orç: {st} | OM: {om_txt} {flag}")

    if not val_ok:
        diverg.append((num,"valor cheio",val_fc,val_tao))
        correcoes.append(f"{num}: valor cheio difere (FCerta R$ {val_fc} × TAO R$ {val_tao}) — conferir desconto/valor final do orçamento no TAO.")
    if not aprovado:
        correcoes.append(f"{num}: orçamento em '{st}' — aprovar no TAO (a requisição existe no FCerta).")
    if aprovado and not om:
        correcoes.append(f"{num}: aprovado sem OM no TAO — verificar geração da Ordem de Manipulação.")

    # pesagem item-a-item (só se há OM no TAO)
    if not om: continue
    c2=con.cursor()
    c2.execute("""SELECT CDPRO, DESCR, QUANT, QTREAL FROM FC12110
                  WHERE NRRQU=? AND SERIER=? AND TPCMP IN ('C','P')""",(nr,F["serier"]))
    fcit={str(cd):{"descr":dec(ds),"qtreal":f(qr),"quant":f(qt)} for cd,ds,qt,qr in c2.fetchall()}
    for it in sbq(f"/lab_ordem_itens?ordem_id=eq.{om[0]['id']}&select=ativo_id,descricao,qtd_pesar&order=ordem.asc"):
        cod=cod_por_ativo.get(it.get("ativo_id") or "","")
        m=fcit.get(cod)
        if not m: continue
        totpes+=1
        a=m["qtreal"] if m["qtreal"] is not None else m["quant"]; b=f(it.get("qtd_pesar"))
        if a is None or b is None: continue
        if abs(a-b)<=max(0.001,0.001*abs(a)): okpes+=1
        else:
            diverg.append((num,"pesagem "+(m["descr"] or "")[:28],a,b))
            correcoes.append(f"{num}: pesagem do item {m['descr']} difere (FCerta {a} × TAO {b}) — revisar quantidade na OM.")

L.append("")
L.append("## Resumo")
L.append(f"- Requisições/séries confrontadas: **{tot}** | valor cheio conferindo (±R$0,01): **{okval}**")
L.append(f"- Itens de pesagem confrontados: **{totpes}** | conferindo (±0,001): **{okpes}**"+(f" ({okpes*100//max(totpes,1)}%)" if totpes else ""))
L.append(f"- Correções sugeridas: **{len(correcoes)}**")
if diverg:
    L.append("\n### Divergências")
    for d in diverg: L.append(f"- {d[0]} [{d[1]}]: FCerta={d[2]} × TAO={d[3]}")
if correcoes:
    L.append("\n### Correções previstas (NÃO aplicadas — decisão do Carlos)")
    for c in correcoes: L.append(f"- [ ] {c}")
if not diverg and not correcoes:
    L.append("\n**Zero divergências e zero correções — bases espelhadas.**")

con.close()
out=rf"C:\Users\carlo\tao-crm\tao-lab\CONFRONTO_DIARIO_{DIA}.md"
open(out,"w",encoding="utf-8").write("\n".join(L))
print("\n".join(L))
print("\nGerado:", out)
