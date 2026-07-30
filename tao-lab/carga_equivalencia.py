# -*- coding: utf-8 -*-
import os
# FASE 1b — Equivalência sal<->base LIMPA: recalcula os 40 fatores (fator_equiv<>1) pelos
# pesos moleculares do PubChem (fonte pública), validando contra o valor herdado da Zanini.
# Descobre a convenção do sistema (qual razão bate com a maioria) e grava a calculada.
# Uso: python carga_equivalencia.py [--commit]
import json, urllib.request, urllib.parse, re, time, sys
SB="https://gclayesytzzpzkjvgede.supabase.co/rest/v1"
KEY=os.environ.get("SUPABASE_KEY", "sb_secret_HpoqM6ujk2yD6la7KM3cuQ_pdWBK8jo"); CID="62f98634-77ff-42f4-acaf-8561d56583da"
DRY="--commit" not in sys.argv
def sbq(p): return json.loads(urllib.request.urlopen(urllib.request.Request(SB+p,headers={"apikey":KEY,"Authorization":"Bearer "+KEY})).read())
def sb_patch(sid,body):
    d=json.dumps(body).encode(); h={"apikey":KEY,"Authorization":"Bearer "+KEY,"Content-Type":"application/json","Prefer":"return=minimal"}
    urllib.request.urlopen(urllib.request.Request(SB+f"/ativos_sinonimos?id=eq.{sid}",data=d,method="PATCH",headers=h)).read()
SAL=[('CLORIDRATO','hydrochloride'),('HCL','hydrochloride'),('SULFATO','sulfate'),('FOSFATO','phosphate'),
     ('OXALATO','oxalate'),('FUMARATO','fumarate'),('MESILATO','mesylate'),('MALEATO','maleate'),('TARTARATO','tartrate'),
     ('ACETATO','acetate'),('BROMIDRATO','hydrobromide'),('DECANOATO','decanoate'),('PROPIONATO','propionate'),
     ('ENANTATO','enanthate'),('CIPIONATO','cypionate'),('CITRATO','citrate'),('SUCCINATO','succinate'),('DIPROPIONATO','dipropionate'),('VALERATO','valerate'),('PAMOATO','pamoate')]
def variantes(nome):
    n=nome.upper(); n=re.sub(r'[@#?*]','',n); n=re.sub(r'\d+:\d+','',n)
    n=re.sub(r'\b(MP|BASE|PURO|PURA|VENCEU|VENCIDO|INTERN\w*|MICRONIZAD\w*)\b','',n)
    sal=''
    for pt,en in SAL:
        if pt in n: n=n.replace(pt,''); sal=en; break
    b=re.sub(r'\s+',' ',n).strip()
    def suf(x): return re.sub(r'INA\b','INE',re.sub(r'ICO\b','IC',x))
    cands=[b,suf(b)]
    out=[]
    for c in dict.fromkeys([c for c in cands if c]):
        if sal: out+=[(c+' '+sal).strip(),c]
        else: out.append(c)
    return list(dict.fromkeys(out))
def pm(nome):
    for v in variantes(nome):
        try:
            u='https://pubchem.ncbi.nlm.nih.gov/rest/pug/compound/name/'+urllib.parse.quote(v)+'/property/MolecularWeight/JSON'
            r=json.loads(urllib.request.urlopen(u,timeout=15).read())['PropertyTable']['Properties'][0]['MolecularWeight']
            time.sleep(1.0); return float(r), v
        except Exception: time.sleep(1.0)
    return None,None

# 40 pares (fator <> 1)
sins=sbq(f"/ativos_sinonimos?cliente_id=eq.{CID}&fator_equiv=neq.1&select=id,sinonimo,ativo_id,fator_equiv&limit=100")
ids=list({s['ativo_id'] for s in sins if s.get('ativo_id')})
nomes={}
for i in range(0,len(ids),50):
    for a in sbq(f"/ativos?id=in.({','.join(ids[i:i+50])})&select=id,nome&limit=50"): nomes[a['id']]=a['nome']
print(f"Pares a validar: {len(sins)}\n")
print(f"{'SINÔNIMO(sal)':26} {'BASE':22} {'Zanini':>8} {'PMbase':>8} {'PMsal':>8} {'b/s':>7} {'s/b':>7}")
rows=[]
for s in sins:
    base=nomes.get(s.get('ativo_id'),'?')
    pmb,_=pm(base); pms,_=pm(s['sinonimo'])
    bs=round(pmb/pms,4) if pmb and pms else None
    sb=round(pms/pmb,4) if pmb and pms else None
    rows.append((s,base,pmb,pms,bs,sb))
    print(f"{s['sinonimo'][:26]:26} {base[:22]:22} {str(s['fator_equiv']):>8} {str(pmb or '-'):>8} {str(pms or '-'):>8} {str(bs or '-'):>7} {str(sb or '-'):>7}")
# descobre a convenção: qual razão (b/s ou s/b) bate com o fator Zanini na maioria
def prox(a,b): return a and b and abs(a-b)/b < 0.05
nbs=sum(1 for s,_,_,_,bs,sb in rows if prox(bs,s['fator_equiv']))
nsb=sum(1 for s,_,_,_,bs,sb in rows if prox(sb,s['fator_equiv']))
conv='bs' if nbs>=nsb else 'sb'
print("\nConvenção detectada: " + ('base/sal' if conv=='bs' else 'sal/base') + f" (bate em {max(nbs,nsb)}/{len(rows)} com a Zanini)")
grav=0
for s,base,pmb,pms,bs,sb in rows:
    novo = bs if conv=='bs' else sb
    if novo and not DRY:
        sb_patch(s['id'], {'fator_equiv': novo}); grav+=1
calc=sum(1 for r in rows if (r[4] if conv=='bs' else r[5]))
print(('Simulado' if DRY else 'Gravado')+f": {calc} fatores recalculados de {len(rows)} (fonte PubChem)")
