# -*- coding: utf-8 -*-
import os
# FASE 1 (paralela) — fórmula + peso molecular via PubChem. Mesma lógica do carga_pubchem.py,
# com 4 workers + throttle global (<=4,5 req/s, dentro do limite do PubChem).
# Idempotente: só processa MP sem ft_peso_molecular. Uso: python carga_pubchem_par.py [--commit]
import json, urllib.request, urllib.parse, re, time, sys, threading
from concurrent.futures import ThreadPoolExecutor

SB="https://gclayesytzzpzkjvgede.supabase.co/rest/v1"
KEY=os.environ.get("SUPABASE_KEY", "sb_secret_HpoqM6ujk2yD6la7KM3cuQ_pdWBK8jo")
CID="62f98634-77ff-42f4-acaf-8561d56583da"
DRY="--commit" not in sys.argv
WORKERS=2
LIMIT=int(sys.argv[sys.argv.index("--limit")+1]) if "--limit" in sys.argv else None

def sbq(path):
    return json.loads(urllib.request.urlopen(urllib.request.Request(SB+path, headers={"apikey":KEY,"Authorization":"Bearer "+KEY})).read())
def sb_patch(aid, body):
    data=json.dumps(body).encode()
    h={"apikey":KEY,"Authorization":"Bearer "+KEY,"Content-Type":"application/json","Prefer":"return=minimal"}
    urllib.request.urlopen(urllib.request.Request(SB+f"/ativos?id=eq.{aid}", data=data, method="PATCH", headers=h)).read()

SAL=[('CLORIDRATO','hydrochloride'),('HCL','hydrochloride'),('SULFATO','sulfate'),('FOSFATO','phosphate'),
     ('OXALATO','oxalate'),('FUMARATO','fumarate'),('MESILATO','mesylate'),('MALEATO','maleate'),
     ('TARTARATO','tartrate'),('ACETATO','acetate'),('BROMIDRATO','hydrobromide'),('NITRATO','nitrate'),
     ('DECANOATO','decanoate'),('PROPIONATO','propionate'),('ENANTATO','enanthate'),('CIPIONATO','cypionate'),
     ('SODICO','sodium'),('DE SODIO','sodium'),('DE CALCIO','calcium'),('DE POTASSIO','potassium'),
     ('DE MAGNESIO','magnesium'),('DE ZINCO','zinc'),('CITRATO','citrate'),('SUCCINATO','succinate')]
def base_sal(n):
    n=n.upper(); n=re.sub(r'[@#?*]','',n); n=re.sub(r'\d+:\d+','',n)
    n=re.sub(r'\b(MP|BASE|PURO|PURA|VENCEU|VENCIDO|INTERN\w*|MICRONIZAD\w*|IMPORTAD\w*|NACIONAL|EUR|USP|BR)\b','',n)
    sal=''
    for pt,en in SAL:
        if pt in n: n=n.replace(pt,''); sal=en; break
    return re.sub(r'\s+',' ',n).strip(), sal
def suf(x):
    x=re.sub(r'INA\b','INE',x); x=re.sub(r'ÍNA\b','INE',x); x=re.sub(r'EÍNA\b','EINE',x)
    x=re.sub(r'ICO\b','IC',x); x=re.sub(r'AMIDA\b','AMIDE',x); x=re.sub(r'IDA\b','IDE',x); x=re.sub(r'AZOL\b','AZOLE',x)
    return x
def variantes(nome):
    b,sal=base_sal(nome)
    if not b: return []
    cands=[b, suf(b)]
    if 'AC ' in b or 'ACIDO' in b:
        cands.append(suf(re.sub(r'\bAC\b|\bACIDO\b','',b).strip())+' acid')
    out=[]
    for c in dict.fromkeys([c for c in cands if c]):
        if sal: out+= [(c+' '+sal).strip(), c]
        else:   out.append(c)
    return list(dict.fromkeys(out))

_lock=threading.Lock(); _next=[0.0]
def throttle():           # garante espaçamento global ~0.22s entre requisições
    with _lock:
        now=time.time(); wait=max(0.0,_next[0]-now); _next[0]=max(now,_next[0])+0.34
    if wait>0: time.sleep(wait)
def pubchem(nome):
    throttle()
    try:
        u='https://pubchem.ncbi.nlm.nih.gov/rest/pug/compound/name/'+urllib.parse.quote(nome)+'/property/MolecularFormula,MolecularWeight/JSON'
        p=json.loads(urllib.request.urlopen(u,timeout=15).read())['PropertyTable']['Properties'][0]
        return p.get('MolecularFormula'), p.get('MolecularWeight')
    except Exception: return None,None

# alvo: MP sem PM
ativos=[]; page=0
while True:
    ch=sbq(f"/ativos?cliente_id=eq.{CID}&grupo=eq.M&select=id,nome,ft_peso_molecular&order=nome.asc&limit=1000&offset={page*1000}")
    if not ch: break
    ativos+=ch
    if len(ch)<1000: break
    page+=1
alvo=[a for a in ativos if not a.get('ft_peso_molecular')]
if LIMIT: alvo=alvo[:LIMIT]
print(f"MP total {len(ativos)} | a processar {len(alvo)}{' (SIMULAÇÃO)' if DRY else ''} | {WORKERS} workers")

prog=[0,0]; pmmap={}; plock=threading.Lock()
def work(a):
    f=pm=None
    for v in variantes(a['nome']):
        f,pm=pubchem(v)
        if pm: break
    with plock:
        prog[0]+=1
        if pm:
            prog[1]+=1; pmmap[a['id']]={'nome':a['nome'],'formula':f,'pm':pm}
        if prog[0]%100==0: print(f"  {prog[0]}/{len(alvo)} processados, {prog[1]} com PM")
    if pm and not DRY: sb_patch(a['id'], {'ft_formula_molecular':f,'ft_peso_molecular':str(pm)})

t0=time.time()
with ThreadPoolExecutor(max_workers=WORKERS) as ex:
    list(ex.map(work, alvo))
print(f"\nEncontrados: {prog[1]}/{len(alvo)} em {int(time.time()-t0)}s")
with open('pubchem_pm.json','w',encoding='utf-8') as fp: json.dump(pmmap,fp,ensure_ascii=False)
print("PMs salvos em pubchem_pm.json")
