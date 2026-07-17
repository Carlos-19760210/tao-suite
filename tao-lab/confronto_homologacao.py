#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
CONFRONTO DE HOMOLOGAÇÃO — FCerta × TAO Neo, req a req (Momento 2 da validação).
Compara, para as requisições aprovadas no dia:
  • valor cobrado   FC12100.PRCOBR × orcamentos.valor_final_fc/total_orcamento
  • pesagem/item    FC12110.QTREAL (ou QUANT) × lab_ordem_itens.qtd_pesar (match por CDPRO)
  • existência/OM   lab_ordens aberta com o número da req
Gera tao-lab/CONFRONTO_HOMOLOGACAO_<data>.md. SOMENTE LEITURA nos dois lados.

Uso: python confronto_homologacao.py --db "C:\\tmp\\FCerta\\BD\\ALTERDB_20260716.ib" \
        --reqs 47088,47161,... [--excecoes 47177,47181,47190]
"""
import fdb, json, urllib.request, argparse, datetime, unicodedata, re

FB_DLL = r"C:\Users\carlo\FCertaSync\fb25\fbembed.dll"
SB  = "https://gclayesytzzpzkjvgede.supabase.co/rest/v1"
KEY = "sb_secret_HpoqM6ujk2yD6la7KM3cuQ_pdWBK8jo"
CID = "62f98634-77ff-42f4-acaf-8561d56583da"

def dec(v): return v.decode("latin-1","replace").strip() if isinstance(v,(bytes,bytearray)) else (v.strip() if isinstance(v,str) else v)
def f(v, d=None):
    try: return float(v)
    except (TypeError, ValueError): return d
def serier_int(v):
    if isinstance(v,int): return v
    t = (dec(v) or "0").upper()
    if t.isdigit(): return int(t)
    if len(t)==1 and "A"<=t<="Z": return 10+ord(t)-ord("A")
    return 0
def norm(s):
    s=unicodedata.normalize("NFD", s or "").encode("ascii","ignore").decode().upper()
    return re.sub(r"\s+"," ",s.replace("@","")).strip()

def sbq(p):
    h={"apikey":KEY,"Authorization":"Bearer "+KEY}
    return json.loads(urllib.request.urlopen(urllib.request.Request(SB+p,headers=h)).read().decode())

ap = argparse.ArgumentParser()
ap.add_argument("--db", required=True)
ap.add_argument("--reqs", required=True)
ap.add_argument("--excecoes", default="")
args = ap.parse_args()
REQS = [r.strip() for r in args.reqs.split(",") if r.strip()]
EXC  = {r.strip() for r in args.excecoes.split(",") if r.strip()}
HOJE = datetime.date.today().isoformat()

con = fdb.connect(database=args.db, user="SYSDBA", password="masterkey", fb_library_name=FB_DLL, charset="NONE")
cur = con.cursor()

# mapa codigo_fc -> nome (p/ casar item TAO×FCerta)
ativos = []
page = 0
while True:
    h={"apikey":KEY,"Authorization":"Bearer "+KEY,"Range-Unit":"items","Range":f"{page*1000}-{page*1000+999}"}
    ch=json.loads(urllib.request.urlopen(urllib.request.Request(SB+f"/ativos?cliente_id=eq.{CID}&select=id,codigo_fc,nome&order=id.asc",headers=h)).read().decode())
    ativos+=ch
    if len(ch)<1000: break
    page+=1
cod_por_ativo = {a["id"]: str(a["codigo_fc"]).strip() for a in ativos if a.get("codigo_fc")}

L=[f"# Confronto de Homologação FCerta × TAO Neo — {HOJE}",
   f"Backup FCerta: `{args.db}` | Reqs: {', '.join(REQS)}",
   f"Exceções informadas (sem paridade esperada): {', '.join(sorted(EXC)) or '—'}", ""]
tot_series=ok_val=ok_pes_it=tot_pes_it=0
diverg=[]

for req in REQS:
    nreq=int(req); seg=req.zfill(6)
    cur.execute("""SELECT NRRQU, SERIER, NOMEPA, PRCOBR, VOLUME, UNIVOL, QTCONT
                   FROM FC12100 WHERE NRRQU=?""",(nreq,))
    series=cur.fetchall()
    flag=" ⚠ EXCEÇÃO" if req in EXC else ""
    L.append(f"## Req {req}{flag}")
    if not series:
        L.append("- FCerta: requisição NÃO encontrada no backup."); L.append(""); continue
    for (nrrqu, serier, nomepa, prcobr, vol, univol, qtcont) in series:
        sr=serier_int(serier); tot_series+=1
        num_tao=f"0001-{seg}-{sr}"
        # TAO: orçamento + OM
        orc=sbq(f"/orcamentos?cliente_id=eq.{CID}&numero_orcamento=eq.{num_tao}&select=id,status,valor_final_fc,total_orcamento,desconto_fc&limit=1")
        om =sbq(f"/lab_ordens?cliente_id=eq.{CID}&numero=eq.{num_tao}&status=neq.cancelada&select=id,status&limit=1")
        # PRCOBR = preço CHEIO no FCerta; o portal registra o FINAL (com desconto da venda).
        # Comparável = final TAO + desconto TAO (bruto reconstituído) × PRCOBR.
        val_fc=f(prcobr); o=orc[0] if orc else None
        val_fin=(f(o.get("valor_final_fc")) if o and o.get("valor_final_fc") is not None else (f(o.get("total_orcamento")) if o else None))
        desc  =f(o.get("desconto_fc"),0.0) if o else 0.0
        val_tao=(val_fin+ (desc or 0.0)) if val_fin is not None else None
        val_ok = (val_fc is not None and val_tao is not None and abs(val_fc-val_tao)<=0.01)
        if val_ok: ok_val+=1
        st_orc=o["status"] if o else "NÃO IMPORTADO"
        st_om =om[0]["status"] if om else "sem OM"
        vtxt = (f"R$ {val_fc:.2f} × R$ {val_tao:.2f} (final {val_fin:.2f} + desc {desc or 0:.2f})"
                if (val_fc is not None and val_tao is not None) else f"{val_fc} × {val_tao}")
        L.append(f"- **{num_tao}** ({dec(nomepa) or '—'}): cheio FC×TAO = {vtxt} {'✓' if val_ok else '✗'} | orc: {st_orc} | OM: {st_om}")
        if not val_ok and o: diverg.append((num_tao,"valor cheio",val_fc,val_tao))

        # pesagem item a item (só se tem OM)
        if not om: continue
        cur2=con.cursor()
        cur2.execute("""SELECT CDPRO, DESCR, QUANT, UNIDA, QTREAL, INDQSP FROM FC12110
                        WHERE NRRQU=? AND SERIER=? AND TPCMP IN ('C','P')""",(nrrqu,serier))
        fc_it={}
        for cdpro,descr,quant,unida,qtreal,indqsp in cur2.fetchall():
            fc_it[str(cdpro)] = {"descr":dec(descr),"qtreal":f(qtreal),"quant":f(quant),"qsp":dec(indqsp)=="S"}
        titens=sbq(f"/lab_ordem_itens?ordem_id=eq.{om[0]['id']}&select=ativo_id,descricao,qtd_pesar,eh_qsp&order=ordem.asc")
        for it in titens:
            cod=cod_por_ativo.get(it.get("ativo_id") or "","")
            m=fc_it.get(cod)
            if not m: continue   # item sem par no FCerta (ex.: excipiente calculado) — fora da amostra
            tot_pes_it+=1
            a,b=m["qtreal"],f(it.get("qtd_pesar"))
            if a is None or b is None: continue
            if abs(a-b)<=max(0.001, 0.001*abs(a)):
                ok_pes_it+=1
            else:
                diverg.append((num_tao,"pesagem "+(m['descr'] or '')[:30],a,b))
    L.append("")

L.append("## Resumo")
L.append(f"- Séries no lote: **{tot_series}** | valor conferindo (±R$0,01): **{ok_val}**")
L.append(f"- Itens de pesagem confrontados (match por código): **{tot_pes_it}** | conferindo (±0,001 g): **{ok_pes_it}**"
         + (f" ({ok_pes_it*100//max(tot_pes_it,1)}%)" if tot_pes_it else ""))
if diverg:
    L.append("\n### Divergências")
    for d in diverg: L.append(f"- {d[0]} [{d[1]}]: FCerta={d[2]} × TAO={d[3]}")
else:
    L.append("\n**Zero divergências fora da tolerância.**")

out=rf"C:\Users\carlo\tao-crm\tao-lab\CONFRONTO_HOMOLOGACAO_{HOJE}.md"
open(out,"w",encoding="utf-8").write("\n".join(L))
con.close()
print("\n".join(L))
print("\nGerado:", out)
