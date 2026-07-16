# -*- coding: utf-8 -*-
# FASE 1 — BASE PRÓPRIA: fórmula molecular + peso molecular via PubChem (open data NIH).
# Fonte LIMPA (domínio público) p/ substituir a base Zanini herdada (ver memória
# project_base_propria_comercializacao). Preenche ativos.ft_formula_molecular/ft_peso_molecular.
# Tradução PT->EN por regras (sufixo + sal) + variantes; ~97% de acerto nos fármacos.
# Extratos/probióticos/marcas não têm PM (ficam vazios — correto).
# Idempotente: só preenche onde ft_peso_molecular está vazio. Salva PM p/ a fase de equivalência.
# Carga na base viva — rodar só com OK. Uso: python carga_pubchem.py [--commit] [--limit N]
import json, urllib.request, urllib.parse, re, time, sys

SB   = "https://gclayesytzzpzkjvgede.supabase.co/rest/v1"
KEY  = "sb_secret_HpoqM6ujk2yD6la7KM3cuQ_pdWBK8jo"
CID  = "62f98634-77ff-42f4-acaf-8561d56583da"
DRY  = "--commit" not in sys.argv
LIMIT = None
if "--limit" in sys.argv: LIMIT = int(sys.argv[sys.argv.index("--limit")+1])

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
    x=re.sub(r'ICO\b','IC',x); x=re.sub(r'AMIDA\b','AMIDE',x); x=re.sub(r'IDA\b','IDE',x)
    x=re.sub(r'OSE\b','OSE',x); x=re.sub(r'AZOL\b','AZOLE',x)
    return x
def variantes(nome):
    b,sal=base_sal(nome)
    if not b: return []
    cands=[b, suf(b)]
    if 'AC ' in b or 'ACIDO' in b:
        cands.append(suf(re.sub(r'\bAC\b|\bACIDO\b','',b).strip())+' acid')
    out=[]
    for c in dict.fromkeys([c for c in cands if c]):
        if sal: out.append((c+' '+sal).strip()); out.append(c)
        else:   out.append(c)
    return list(dict.fromkeys(out))
def pubchem(nome):
    try:
        u='https://pubchem.ncbi.nlm.nih.gov/rest/pug/compound/name/'+urllib.parse.quote(nome)+'/property/MolecularFormula,MolecularWeight/JSON'
        p=json.loads(urllib.request.urlopen(u,timeout=15).read())['PropertyTable']['Properties'][0]
        return p.get('MolecularFormula'), p.get('MolecularWeight')
    except Exception: return None,None

# ativos MP sem PM ainda (enriquecimento incremental)
ativos=[]; page=0
while True:
    ch=sbq(f"/ativos?cliente_id=eq.{CID}&grupo=eq.M&select=id,nome,ft_peso_molecular&order=nome.asc&limit=1000&offset={page*1000}")
    if not ch: break
    ativos+=ch
    if len(ch)<1000: break
    page+=1
alvo=[a for a in ativos if not a.get('ft_peso_molecular')]
if LIMIT: alvo=alvo[:LIMIT]
print(f"MP total {len(ativos)} | sem PM {len(alvo)} | processando {len(alvo)}{' (SIMULAÇÃO)' if DRY else ''}", flush=True)

# aguarda o PubChem liberar (recupera de blackout por rate limit) antes de começar
for tent in range(40):
    _,pmt = pubchem('aspirin')
    if pmt: print(f"PubChem OK (após {tent} espera(s)) — iniciando", flush=True); break
    print(f"PubChem bloqueado/instável, aguardando 60s... (tentativa {tent+1})", flush=True); time.sleep(60)
else:
    print("PubChem não liberou após 40 min — abortando."); sys.exit(1)

ok=0; pmmap={}
for i,a in enumerate(alvo):
    f=pm=None; usado=''
    for v in variantes(a['nome']):
        f,pm=pubchem(v); time.sleep(1.0)   # 1 req/s — ritmo seguro comprovado (10/10) p/ o IP penalizado
        if pm: usado=v; break
    if pm:
        ok+=1; pmmap[a['id']]={'nome':a['nome'],'formula':f,'pm':pm}
        if not DRY: sb_patch(a['id'], {'ft_formula_molecular':f,'ft_peso_molecular':str(pm)})
    if i<20 or i%100==0:
        print(f"  {'OK ' if pm else '-- '} {a['nome'][:30]:30} -> {usado[:26]:26} {pm or ''}")
print(f"\nEncontrados: {ok}/{len(alvo)} ({100*ok//max(len(alvo),1)}%)")
# salva PMs p/ a fase de equivalência (fator sal<->base)
with open('pubchem_pm.json','w',encoding='utf-8') as fp: json.dump(pmmap,fp,ensure_ascii=False)
print("PMs salvos em pubchem_pm.json")
