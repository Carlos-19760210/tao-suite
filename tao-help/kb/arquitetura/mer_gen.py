# -*- coding: utf-8 -*-
import sys; sys.stdout.reconfigure(encoding='utf-8', errors='replace')
import json, re
d=json.load(open(r'C:\tmp\cotfix\swagger.json', encoding='utf-8'))
defs=d.get('definitions',{}); tables=sorted(defs.keys())
fks=json.load(open(r'C:\tmp\cotfix\fks.json'))

def modulo(t):
    if t in ('clientes','crm_workspaces','empresa_config','crm_perfis','crm_perfil_usuarios','crm_permissoes','crm_alcadas','crm_planos','lgpd_acessos'): return 'nucleo'
    if t.startswith('caixa_') or t in ('contas_a_pagar','contas_pagar','fiscal_produtos','sngpc_arquivos','sngpc_movimentos'): return 'caixa'
    if t.startswith('cotac') or t in ('fornecedores','fornecedor_mensagens','precos_historico'): return 'cotacoes'
    if t.startswith('estoque_') or t in ('recebimento_depara','recebimento_nf','recebimento_nf_itens'): return 'estoque'
    if t.startswith('campanha') or t in ('listas_contatos','lista_contatos_itens'): return 'campanhas'
    if t.startswith('lab_') or t in ('orcamentos','ativos','ativos_sinonimos','ativo_precos_hist','formas_farmaceuticas','hist_formulas','hist_formulas_itens','laudo_modelos','prescritores','receita_logs','tipos_capsula','unidades_medida'): return 'formula'
    if t.startswith('crm_') or t in ('contato_enderecos','hist_clientes','cid10','historico'): return 'crm'
    if t in ('catalogo','catalogo_componentes','catalogo_disponibilidade','categorias','campos_extras','conteudo_dinamico','conectores_saida','cliente_produtos','leads','pedidos','mensagens_buffer'): return 'agente'
    if t=='entregas': return 'entregas'
    return 'outros'

MODS=[
 ('nucleo','Nucleo — Tenant & Acesso','Cada linha de TODO o sistema pertence a um <b>cliente</b> (tenant). Workspaces, perfis, alcadas e permissoes (RBAC).'),
 ('crm','CRM','Kanban + chat WhatsApp: cards, contatos, pipelines/estagios, mensagens, automacoes, campos, metas, NPS.'),
 ('agente','Agente / Catalogo','Atendimento automatico: catalogo, categorias, disponibilidade, leads, pedidos e buffer de mensagens.'),
 ('formula','Formula / Lab (manipulacao)','Ativos+sinonimos, formas, orcamentos, receita por IA, e o Lab (ordens, lotes, laudos, SNGPC de MP).'),
 ('caixa','Caixa / Financeiro / Fiscal','PDV/recebimento: vendas, recibos, pagamentos, recebiveis, taxas, operadoras, contas a pagar, NFC-e/SNGPC.'),
 ('cotacoes','Cotacoes (compras)','Fornecedores, cotacoes, propostas, precos, modelos de layout e historico de preco.'),
 ('estoque','Estoque','Movimentos, entradas por NF e de-para de fornecedor.'),
 ('campanhas','Campanhas','Campanhas de recompra/win-back, listas de contatos e historico de disparo.'),
 ('entregas','Entregas','Ultima milha (aba no card).'),
 ('outros','Outros',''),
]
grp={}
for t in tables: grp.setdefault(modulo(t),[]).append(t)

def ent(t): return re.sub(r'[^A-Za-z0-9_]','_',t)

def mermaid_for(ts):
    setts=set(ts); lines=["erDiagram"]; used=set(); seen=set()
    for tab,col,alvo,_ in fks:
        if tab in setts and alvo in setts:
            k=(alvo,tab,col)
            if k in seen: continue
            seen.add(k)
            lines.append('  '+ent(alvo)+' ||--o'+chr(123)+' '+ent(tab)+' : "'+col+'"')
            used.add(tab); used.add(alvo)
    for t in ts:
        if t not in used:
            lines.append('  '+ent(t)+' '+chr(123))
            lines.append('    uuid id PK')
            lines.append('  '+chr(125))
    return "\n".join(lines)

def hubs_externos(ts):
    setts=set(ts); ext=set()
    for tab,col,alvo,_ in fks:
        if tab in setts and alvo not in setts: ext.add(alvo)
    return sorted(ext)

O='||--o'+chr(123)
ov=["erDiagram",
 '  clientes '+O+' crm_workspaces : "tenant"',
 '  clientes '+O+' ativos : "cadastro unico"',
 '  clientes '+O+' fornecedores : "cadastro unico"',
 '  clientes '+O+' crm_contatos : "cadastro unico"',
 '  crm_workspaces '+O+' crm_cards : "CRM"',
 '  crm_contatos '+O+' crm_cards : "contato"',
 '  crm_cards '+O+' orcamentos : "Formula"',
 '  crm_cards '+O+' caixa_vendas : "Caixa"',
 '  crm_cards '+O+' entregas : "Entregas"',
 '  ativos '+O+' cotacao_precos : "Cotacoes"',
 '  ativos '+O+' orcamentos : "itens"',
 '  fornecedores '+O+' cotacoes : "Compras"',
 '  caixa_vendas '+O+' caixa_recibos : "recebimento"',
]

def esc(s):
    return (s or '').replace('&','&amp;').replace('<','&lt;').replace('>','&gt;')

H=[]
H.append('<title>TAO Neo - Modelo de Dados (MER)</title>')
CSS = """<style>
:root{--bg:#f7f8fa;--card:#fff;--ink:#1e293b;--mut:#64748b;--line:#e2e8f0;--acc:#152C42;--accsoft:#e8eef5;--mono:'SFMono-Regular',Consolas,'Liberation Mono',Menlo,monospace}
@media (prefers-color-scheme:dark){:root{--bg:#0f1520;--card:#151d2b;--ink:#e6edf6;--mut:#93a1b5;--line:#243247;--acc:#7db3ff;--accsoft:#1b2740}}
:root[data-theme=dark]{--bg:#0f1520;--card:#151d2b;--ink:#e6edf6;--mut:#93a1b5;--line:#243247;--acc:#7db3ff;--accsoft:#1b2740}
:root[data-theme=light]{--bg:#f7f8fa;--card:#fff;--ink:#1e293b;--mut:#64748b;--line:#e2e8f0;--acc:#152C42;--accsoft:#e8eef5}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;line-height:1.5}
.wrap{max-width:1080px;margin:0 auto;padding:32px 20px 80px}
h1{font-size:30px;margin:0 0 4px;letter-spacing:-.02em}
.sub{color:var(--mut);font-size:15px;margin:0 0 22px}
.kpis{display:flex;flex-wrap:wrap;gap:10px;margin:0 0 26px}
.kpi{background:var(--card);border:1px solid var(--line);border-radius:10px;padding:10px 14px}
.kpi b{font-size:20px;display:block}.kpi span{font-size:12px;color:var(--mut)}
.card{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:20px 22px;margin:0 0 20px}
.card h2{margin:0 0 4px;font-size:20px}
.card p.d{color:var(--mut);font-size:14px;margin:0 0 12px}
.tabs{font-family:var(--mono);font-size:12px;color:var(--mut);margin:0 0 14px}
.tabs code{background:var(--accsoft);color:var(--ink);padding:1px 6px;border-radius:5px;margin:2px 4px 2px 0;display:inline-block}
.ext{font-size:12.5px;color:var(--mut);margin:12px 0 0}.ext b{color:var(--ink)}
.diag{overflow:auto;max-height:74vh;border:1px dashed var(--line);border-radius:10px;padding:10px;background:var(--bg);cursor:zoom-in;position:relative}
.diag:after{content:'⛶ clique para ampliar';position:absolute;top:8px;right:10px;font-size:11px;color:var(--mut);background:var(--card);border:1px solid var(--line);border-radius:12px;padding:2px 8px;pointer-events:none}
.diag svg{max-width:none !important;height:auto !important}
pre.mermaid{margin:0;min-width:0}
#zoomov{display:none;position:fixed;inset:0;z-index:99999;background:rgba(6,10,16,.93);flex-direction:column}
#zoomov .ztool{display:flex;gap:8px;align-items:center;justify-content:center;padding:10px;color:#fff;flex:0 0 auto}
#zoomov .ztool button{background:#1e2b3e;color:#fff;border:1px solid #35485f;border-radius:6px;padding:6px 12px;cursor:pointer;font-size:14px}
#zoomov #zlvl{min-width:56px;text-align:center;font-variant-numeric:tabular-nums}
#zoomov .zpane{flex:1 1 auto;overflow:auto;padding:20px}
#zoomov .zwrap{transform-origin:top left;display:inline-block;background:#fff;border-radius:8px;padding:16px}
.legend{font-size:12.5px;color:var(--mut)}
.nav{position:sticky;top:0;background:var(--bg);padding:10px 0;margin:0 0 18px;border-bottom:1px solid var(--line);display:flex;flex-wrap:wrap;gap:8px;z-index:5}
.nav a{font-size:12.5px;color:var(--acc);text-decoration:none;border:1px solid var(--line);border-radius:20px;padding:3px 10px}
</style>"""
H.append(CSS)
H.append('<div class="wrap">')
H.append('<h1>TAO Neo — Modelo de Dados (MER)</h1>')
H.append('<p class="sub">Ecossistema multi-tenant no Supabase/PostgreSQL. Tudo pertence a um <b>cliente</b> (tenant); cadastros de <b>ativos</b>, <b>fornecedores</b> e <b>contatos</b> sao unicos e compartilhados entre os modulos.</p>')
nmod=len([m for m in MODS if grp.get(m[0])])
H.append('<div class="kpis"><div class="kpi"><b>'+str(len(tables))+'</b><span>tabelas</span></div><div class="kpi"><b>'+str(len(fks))+'</b><span>relacoes (FK)</span></div><div class="kpi"><b>'+str(nmod)+'</b><span>modulos</span></div></div>')
H.append('<div class="nav">'+''.join('<a href="#'+m[0]+'">'+esc(m[1])+'</a>' for m in MODS if grp.get(m[0]))+'</div>')
H.append('<div class="card"><h2>Visao geral (hubs & modulos)</h2><p class="d">Como os modulos se conectam pelos cadastros centrais e pelo card do CRM.</p><div class="diag"><pre class="mermaid">'+esc("\n".join(ov))+'</pre></div></div>')
for key,label,desc in MODS:
    ts=sorted(grp.get(key,[]))
    if not ts: continue
    H.append('<div class="card" id="'+key+'">')
    H.append('<h2>'+esc(label)+' <span style="color:var(--mut);font-weight:400;font-size:14px">- '+str(len(ts))+' tabelas</span></h2>')
    if desc: H.append('<p class="d">'+desc+'</p>')
    H.append('<div class="tabs">'+''.join('<code>'+esc(t)+'</code>' for t in ts)+'</div>')
    H.append('<div class="diag"><pre class="mermaid">'+esc(mermaid_for(ts))+'</pre></div>')
    he=hubs_externos(ts)
    if he: H.append('<p class="ext"><b>Liga-se a (outros modulos):</b> '+', '.join('<code>'+esc(h)+'</code>' for h in he)+'</p>')
    H.append('</div>')
H.append('<p class="legend">Notacao: <b>||--o'+chr(123)+'</b> = um-para-muitos (lado <b>||</b> e o pai/PK). Relacoes internas a cada modulo; ligacoes entre modulos aparecem em "Liga-se a". <b>Clique em qualquer diagrama para ampliar</b> (zoom + / -).</p>')
H.append('</div>')
ZJS = (
"<div id=\"zoomov\">"
"<div class=\"ztool\"><button data-z=\"-\">- zoom</button><span id=\"zlvl\">100%</span>"
"<button data-z=\"+\">+ zoom</button><button data-z=\"0\">Reset</button><button id=\"zclose\">Fechar X</button></div>"
"<div class=\"zpane\"><div class=\"zwrap\"></div></div></div>"
"<script>(function(){var ov=document.getElementById('zoomov'),wrap=ov.querySelector('.zwrap'),lvl=document.getElementById('zlvl'),z=1;"
"function apply(){wrap.style.transform='scale('+z+')';lvl.textContent=Math.round(z*100)+'%';}"
"document.addEventListener('click',function(e){var d=e.target.closest?e.target.closest('.diag'):null;if(!d||ov.contains(d))return;var s=d.querySelector('svg');if(!s)return;wrap.innerHTML='';wrap.appendChild(s.cloneNode(true));z=1;apply();ov.style.display='flex';});"
"document.getElementById('zclose').addEventListener('click',function(){ov.style.display='none';});"
"ov.addEventListener('click',function(e){if(e.target===ov||e.target.classList.contains('zpane'))ov.style.display='none';});"
"ov.querySelectorAll('[data-z]').forEach(function(b){b.addEventListener('click',function(e){e.stopPropagation();var v=b.getAttribute('data-z');if(v==='+')z=Math.min(6,z+0.25);else if(v==='-')z=Math.max(0.4,z-0.25);else z=1;apply();});});"
"document.addEventListener('keydown',function(e){if(e.key==='Escape')ov.style.display='none';});"
"})();</script>"
)
H.append(ZJS)
out=r'C:\Users\carlo\AppData\Local\Temp\claude\C--Users-carlo\cc1c8537-9fba-44a3-9e06-a41e1fb91f1d\scratchpad\mer_taoneo.html'
open(out,'w',encoding='utf-8').write("\n".join(H))
print("HTML gerado:", len("\n".join(H)), "bytes ->", out)
PY_DONE=1
