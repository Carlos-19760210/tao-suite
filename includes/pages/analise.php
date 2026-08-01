<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Hub de Análise — dois modos:
 *  • SIMPLES: respostas prontas (KPIs + perguntas de negócio) para quem não monta cubo.
 *    Bloco Financeiro (grão OM×ativo) + bloco Atendimento/Operação (grão card: ganho×perda
 *    pelo funil, TMR, fila de espera, status), ambos com abertura por responsável.
 *  • AVANÇADO: cubo denormalizado (WebDataRocks, pt-BR) para dimensionar livremente.
 * Usuário escolhe o TIPO de gráfico (barras/linha/pizza/rosca/área) em toda visão.
 * Read-only, restrito a gestão, tenant-scoped nos endpoints.
 */
function tao_crm_page_analise() {
    if ( ! function_exists( 'cbpm_can_access' ) || ! cbpm_can_access() ) {
        echo '<div class="wrap"><p>Acesso negado.</p></div>'; return;
    }
    $_is_master = current_user_can( 'manage_options' )
        || ( function_exists( 'cbpm_current_role' ) && cbpm_current_role() === 'master' );
    if ( ! $_is_master ) {
        $ws = tao_crm_get_workspace();
    } else {
        $ws_id_g = sanitize_text_field( $_GET['workspace_id'] ?? '' );
        $_def_ws = get_option( 'tao_crm_default_workspace_id', '' );
        $ws      = tao_crm_get_workspace( $ws_id_g ?: ( $_def_ws ?: null ) );
    }
    if ( ! $ws ) {
        echo '<div class="wrap"><div class="notice notice-warning"><p>Nenhum workspace configurado.</p></div></div>'; return;
    }
    $ws_id = $ws['id'];
    if ( ! tao_crm_is_gestor( $ws_id ) ) {
        echo '<div class="wrap"><div class="notice notice-warning"><p>&#x1F512; A Análise é restrita a perfis de gestão.</p></div></div>'; return;
    }

    $nonce   = wp_create_nonce( 'tao_crm_nonce' );
    $ajaxurl = admin_url( 'admin-ajax.php' );
    $mes_ini = gmdate( 'Y-m-01' );
    $hoje    = gmdate( 'Y-m-d' );
    // funis + equipe p/ os filtros do modo Relatório
    $rel_pipes = [];
    $rp_ = tao_crm_api( "/crm_pipelines?workspace_id=eq.$ws_id&select=id,nome&order=nome.asc" );
    foreach ( ( $rp_['ok'] ? ( $rp_['data'] ?? [] ) : [] ) as $p ) $rel_pipes[ $p['id'] ] = $p['nome'];
    $rel_equipe = function_exists( 'tao_crm_get_equipe_ws' ) ? tao_crm_get_equipe_ws( $ws_id ) : [];
    $topts   = '<option value="bar">Barras</option><option value="barh">Barras horizontais</option>'
             . '<option value="line">Linha</option><option value="area">Área</option>'
             . '<option value="pie">Pizza</option><option value="doughnut">Rosca</option>';
    ?>
    <style>
      .tao-analise h2{font-size:15px;margin:18px 0 8px;color:#334155}
      .an-kpis{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:10px}
      .an-kpi{flex:1 1 150px;min-width:130px;background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:10px 12px}
      .an-kpi b{display:block;font-size:22px;color:#0f172a;line-height:1.1}
      .an-kpi span{font-size:11px;color:#64748b}
      .an-gallery{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:10px}
      .an-qbtn{background:#f1f5f9;border:1px solid #e2e8f0;border-radius:8px;padding:8px 10px;cursor:pointer;font-size:12px;color:#334155}
      .an-qbtn:hover{background:#e2e8f0}
      .an-qbtn.on{background:#2563eb;border-color:#2563eb;color:#fff}
      .an-result{border:1px solid #e2e8f0;border-radius:10px;padding:12px;background:#fff}
      .an-rhead{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:6px}
      .an-rhead b{font-size:13px;color:#334155}
      .an-type{padding:4px 6px;border:1px solid #cbd5e1;border-radius:6px;font-size:12px}
      .an-sentence{background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:8px 10px;font-size:13px;color:#92400e;margin-bottom:8px}
      .an-mode{display:inline-flex;border:1px solid #cbd5e1;border-radius:8px;overflow:hidden;margin-left:8px}
      .an-mode button{border:0;background:#fff;padding:6px 12px;cursor:pointer;font-size:12px;color:#475569}
      .an-mode button.on{background:#2563eb;color:#fff}
      #rel-tbl th,#rel-tbl td{border:1px solid #e5e7eb;padding:5px 8px;text-align:left}
      #rel-tbl th{background:#f1f5f9;position:sticky;top:0;z-index:1;color:#334155}
      #rel-tbl tr:nth-child(even) td{background:#fafafa}
    </style>

    <div class="wrap tao-analise">
        <h1 style="margin-bottom:2px;display:inline-block">&#x1F4CA; Análise
            <span class="an-mode"><button id="mode-s" class="on">Simples</button><button id="mode-a">Avançado</button><button id="mode-r">Relatório</button></span>
        </h1>
        <p style="margin:6px 0 10px;color:#64748b;font-size:13px"><b>Simples</b>: respostas prontas, é só clicar. <b>Avançado</b>: o cubo, pra dimensionar livremente. Em qualquer visão você escolhe o tipo de gráfico.</p>

        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:10px 12px">
            <strong style="font-size:13px;color:#475569">Período:</strong>
            <input type="date" id="an-de"  value="<?php echo esc_attr( $mes_ini ); ?>" style="padding:5px 8px;border:1px solid #cbd5e1;border-radius:5px">
            <span style="color:#94a3b8">até</span>
            <input type="date" id="an-ate" value="<?php echo esc_attr( $hoje ); ?>" style="padding:5px 8px;border:1px solid #cbd5e1;border-radius:5px">
            <button type="button" class="button" id="an-hoje">Hoje</button>
            <button type="button" class="button" id="an-mes">Mês corrente</button>
            <button type="button" class="button" data-preset="7">7 dias</button>
            <button type="button" class="button" data-preset="30">30 dias</button>
            <button type="button" class="button" data-preset="90">90 dias</button>
            <button type="button" class="button button-primary" id="an-aplicar">Aplicar</button>
            <span id="an-status" style="font-size:12px;color:#64748b;margin-left:4px"></span>
        </div>
        <div style="margin-top:8px;display:flex;gap:6px;align-items:center;flex-wrap:wrap">
            <strong style="font-size:12px;color:#475569">Mostrar:</strong>
            <span class="an-mode" id="an-filtro"><button data-f="aprovadas" class="on">Aprovadas</button><button data-f="canceladas">Canceladas</button><button data-f="todas">Todas</button></span>
            <span style="font-size:11px;color:#94a3b8">— aplica ao <b>consumo/produção</b> (cubo + perguntas de ativo). Caixa, Aprovações e Perdas são leituras próprias.</span>
        </div>
        <div id="an-msg" style="margin-top:10px;color:#64748b"></div>

        <!-- ───────── MODO SIMPLES ───────── -->
        <div id="an-simples">
            <h2>&#x1F4B0; Financeiro &amp; Produção</h2>
            <div class="an-kpis" id="fin-kpis"></div>
            <div class="an-gallery" id="fin-gallery"></div>
            <div class="an-result">
                <div class="an-rhead"><b id="fin-title"></b>
                    <span style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                        <label id="fin-dim-l" style="display:none;font-size:12px;color:#64748b">Por: <select id="fin-dim" class="an-type"></select></label>
                        <label style="font-size:12px;color:#64748b">Ver como: <select id="fin-type" class="an-type"><?php echo $topts; ?></select></label>
                    </span></div>
                <div class="an-sentence" id="fin-sentence"></div>
                <div style="height:380px"><canvas id="fin-canvas"></canvas></div>
            </div>

            <h2>&#x1F3A7; Atendimento &amp; Vendas</h2>
            <div class="an-kpis" id="op-kpis"></div>
            <div class="an-gallery" id="op-gallery"></div>
            <div class="an-result">
                <div class="an-rhead"><b id="op-title"></b>
                    <span style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                        <button type="button" class="button button-small" id="op-back" style="display:none">&#x21A9; voltar</button>
                        <label id="op-dim-l" style="display:none;font-size:12px;color:#64748b">Por: <select id="op-dim" class="an-type"></select></label>
                        <label style="font-size:12px;color:#64748b">Ver como: <select id="op-type" class="an-type"><?php echo $topts; ?></select></label>
                    </span></div>
                <div class="an-sentence" id="op-sentence"></div>
                <div style="height:380px"><canvas id="op-canvas"></canvas></div>
            </div>
        </div>

        <!-- ───────── MODO AVANÇADO (cubo) ───────── -->
        <div id="an-avancado" style="display:none">
            <div style="margin:6px 0;display:flex;gap:6px;align-items:center;flex-wrap:wrap">
                <strong style="font-size:12px;color:#475569">Dados:</strong>
                <span class="an-mode" id="an-cube-ds"><button data-ds="consumo" class="on">Consumo (ganhas)</button><button data-ds="leads">Leads</button><button data-ds="perdas">Perdas</button><button data-ds="cards">Cards (relatório)</button><button data-ds="itens">Itens (relatório)</button></span>
                <strong style="font-size:12px;color:#475569;margin-left:6px">Visões prontas:</strong>
                <span id="an-vis-btns"></span>
                <button type="button" class="button" id="an-fields-toggle" style="margin-left:4px">&#x1F9F2; Painel de campos</button>
                <button type="button" class="button" id="an-chart-toggle">&#x1F4C8; Gráfico</button>
                <span style="font-size:11px;color:#94a3b8">— arraste os rótulos direto na planilha</span>
            </div>
            <div id="wdr-pivot" style="margin-top:8px;height:600px"></div>
            <div id="piv-chart-wrap" style="display:none;margin-top:12px;border:1px solid #e2e8f0;border-radius:8px;padding:12px">
                <div class="an-rhead"><b>Gráfico do cubo</b><label style="font-size:12px;color:#64748b">Ver como: <select id="piv-type" class="an-type"><?php echo $topts; ?></select></label></div>
                <div class="an-sentence" id="piv-msg" style="display:none"></div>
                <div style="height:400px"><canvas id="piv-canvas"></canvas></div>
            </div>
        </div>

        <!-- ───────── MODO RELATÓRIO (denormalizado, export XLSX) ───────── -->
        <div id="an-relatorio" style="display:none">
            <div style="display:flex;gap:10px;align-items:end;flex-wrap:wrap;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:10px 12px;margin-top:6px">
                <div style="display:flex;flex-direction:column;gap:3px">
                    <label style="font-size:11px;color:#64748b;font-weight:600">Grão</label>
                    <select id="rel-grao" class="an-type">
                        <option value="card">1 linha por card</option>
                        <option value="item">1 linha por item/ativo (repete o card)</option>
                    </select>
                </div>
                <div style="display:flex;flex-direction:column;gap:3px">
                    <label style="font-size:11px;color:#64748b;font-weight:600">Negócio</label>
                    <select id="rel-negocio" class="an-type">
                        <option value="todos">Todos</option><option value="ganho">Ganhos</option>
                        <option value="perda">Perdas / Cancelados</option><option value="andamento">Em andamento</option>
                    </select>
                </div>
                <div style="display:flex;flex-direction:column;gap:3px">
                    <label style="font-size:11px;color:#64748b;font-weight:600">Funil</label>
                    <select id="rel-funil" class="an-type"><option value="">Todos os funis</option>
                        <?php foreach ( $rel_pipes as $pid => $pnome ) : ?><option value="<?php echo esc_attr( $pid ); ?>"><?php echo esc_html( $pnome ); ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div style="display:flex;flex-direction:column;gap:3px">
                    <label style="font-size:11px;color:#64748b;font-weight:600">Responsável</label>
                    <select id="rel-resp" class="an-type"><option value="">Todos</option>
                        <?php foreach ( $rel_equipe as $u ) : ?><option value="<?php echo esc_attr( $u->ID ); ?>"><?php echo esc_html( $u->display_name ); ?></option><?php endforeach; ?>
                    </select>
                </div>
                <button type="button" class="button button-primary" id="rel-gerar">Gerar</button>
                <span style="flex:1"></span>
                <button type="button" class="button" id="rel-xlsx" disabled>&#x2B07; XLSX</button>
                <button type="button" class="button" id="rel-csv" disabled>CSV</button>
            </div>
            <p style="font-size:11px;color:#94a3b8;margin:6px 0">Usa o <b>período</b> definido acima (data de criação do card). Uma linha por card com todas as colunas + campos personalizados; no grão por item, a linha do card se repete para cada ativo.</p>
            <div id="rel-status" style="font-size:13px;color:#475569;margin:6px 0">Ajuste os filtros e clique em <b>Gerar</b>.</div>
            <div id="rel-tbl-wrap" style="display:none;overflow:auto;max-height:60vh;border:1px solid #e2e8f0;border-radius:8px">
                <table id="rel-tbl" style="border-collapse:collapse;font-size:12px;white-space:nowrap"><thead></thead><tbody></tbody></table>
            </div>
        </div>
    </div>

    <script>
    (function () {
        var ajaxurl = <?php echo wp_json_encode( $ajaxurl ); ?>;
        var nonce   = <?php echo wp_json_encode( $nonce ); ?>;
        var wsId    = <?php echo wp_json_encode( $ws_id ); ?>;
        var CHARTJS = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js';
        var DATALAB = 'https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js';
        var WDR_CSS = 'https://cdn.webdatarocks.com/latest/webdatarocks.min.css';
        var WDR_TB  = 'https://cdn.webdatarocks.com/latest/webdatarocks.toolbar.min.js';
        var WDR_CR  = 'https://cdn.webdatarocks.com/latest/webdatarocks.js';

        var msg=document.getElementById('an-msg');
        function setMsg(t,c){ msg.textContent=t||''; msg.style.color=c||'#64748b'; }
        function css(h){ var l=document.createElement('link'); l.rel='stylesheet'; l.href=h; document.head.appendChild(l); }
        function js(src,cb){ var s=document.createElement('script'); s.src=src; s.onload=cb;
            s.onerror=function(){ setMsg('⚠ Não consegui carregar '+src+'. Me avise.', '#dc2626'); }; document.head.appendChild(s); }
        function el(id){ return document.getElementById(id); }

        // ── formatação pt-BR ──
        var PAL=['#2563eb','#16a34a','#f59e0b','#db2777','#7c3aed','#0891b2','#ea580c','#0d9488','#4f46e5','#be123c'];
        function brl(v){ return 'R$ '+(v||0).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2}); }
        function num(v){ return (v||0).toLocaleString('pt-BR',{maximumFractionDigits:2}); }
        function fmtN(v,cur){ return cur?brl(v):num(v); }
        function pct(v){ return (v||0).toLocaleString('pt-BR',{maximumFractionDigits:1})+'%'; }
        function r2(v){ return Math.round((v||0)*100)/100; }

        // ── agregações ──
        function gSum(rows,dim,val,f){ var m={}; rows.forEach(function(r){ if(f&&!f(r))return; var k=r[dim]; if(k==null||k==='')return; m[k]=(m[k]||0)+(parseFloat(r[val])||0); }); return m; }
        function gCount(rows,dim,f){ var m={}; rows.forEach(function(r){ if(f&&!f(r))return; var k=r[dim]; if(k==null||k==='')return; m[k]=(m[k]||0)+1; }); return m; }
        function gAvg(rows,dim,val,f){ var s={},c={}; rows.forEach(function(r){ if(f&&!f(r))return; var v=r[val]; if(v==null||v==='')return; var k=r[dim]; if(k==null||k==='')return; s[k]=(s[k]||0)+parseFloat(v); c[k]=(c[k]||0)+1; }); var m={}; for(var k in s)m[k]=s[k]/c[k]; return m; }
        function top(m,n){ var a=Object.keys(m).map(function(k){return [k,m[k]];}).sort(function(x,y){return y[1]-x[1];}); return n?a.slice(0,n):a; }
        function sumVals(m){ var t=0; for(var k in m)t+=m[k]; return t; }
        function distinct(rows,key){ var s={}; rows.forEach(function(r){ if(r[key])s[r[key]]=1; }); return Object.keys(s).length; }

        // ── charts (com escolha de tipo) ──
        var SPECS={};
        function buildCfg(spec,type){
            var labels=spec.labels, ds=spec.datasets, isPie=(type==='pie'||type==='doughnut');
            var opt={ responsive:true, maintainAspectRatio:false, plugins:{ legend:{position:isPie?'right':'top'} } };
            var cfg={ options:opt };
            if(isPie){
                var d0=ds[0]||{data:[],label:''};
                cfg.type=type;
                cfg.data={ labels:labels, datasets:[{ label:d0.label, data:d0.data, backgroundColor:labels.map(function(_,i){return PAL[i%PAL.length];}), _cur:d0._cur }] };
                opt.plugins.datalabels={ color:'#fff', font:{size:11,weight:'bold'}, formatter:function(v,c){ var t=c.dataset.data.reduce(function(a,b){return a+(b||0);},0); return t&&v?Math.round(v/t*100)+'%':''; } };
            } else {
                cfg.type=(type==='line'||type==='area')?'line':'bar';
                var fill=(type==='area');
                cfg.data={ labels:labels, datasets:ds.map(function(x,i){ return { label:x.label, data:x.data, _cur:x._cur, backgroundColor:PAL[i%PAL.length], borderColor:PAL[i%PAL.length], fill:fill, tension:0.25 }; }) };
                if(type==='barh') opt.indexAxis='y';
                opt.plugins.datalabels={ anchor:'end', align:'end', clamp:true, font:{size:9}, formatter:function(v,c){ return v?fmtN(v,c.dataset._cur):''; } };
                opt.scales={}; var vaxis=(type==='barh')?'x':'y';
                opt.scales[vaxis]={ beginAtZero:true, ticks:{ callback:function(v){ return v.toLocaleString('pt-BR'); } } };
            }
            opt.plugins.tooltip={ callbacks:{ label:function(c){ return (c.dataset.label?c.dataset.label+': ':'')+fmtN(c.raw,c.dataset._cur); } } };
            return cfg;
        }
        var charts={};
        function drawSpec(prefix){
            var spec=SPECS[prefix]; if(!spec||!window.Chart) return;
            var type=el(prefix+'-type').value;
            if(charts[prefix]) charts[prefix].destroy();
            var cfg=buildCfg(spec,type);
            if(spec._onBar){
                cfg.options.onClick=function(evt,els){ if(els&&els.length){ var lbl=this.data.labels[els[0].index]; spec._onBar(lbl); } };
                cfg.options.onHover=function(e,els){ if(e.native&&e.native.target) e.native.target.style.cursor=(els&&els.length)?'pointer':'default'; };
            }
            charts[prefix]=new Chart(el(prefix+'-canvas').getContext('2d'), cfg);
        }
        function showSpec(prefix, spec){
            SPECS[prefix]=spec;
            if(el(prefix+'-title')) el(prefix+'-title').textContent=spec.title||'';
            if(el(prefix+'-sentence')){ el(prefix+'-sentence').textContent=spec.sentence||''; el(prefix+'-sentence').style.display=spec.sentence?'block':'none'; }
            // seletor de dimensão (só nas visões dimensionáveis)
            var dimL=el(prefix+'-dim-l'), dimS=el(prefix+'-dim');
            if(dimL&&dimS){
                if(spec._dims){ dimS.innerHTML=spec._dims.map(function(d){ return '<option value="'+d[0]+'"'+(d[0]===spec._dim?' selected':'')+'>'+d[1]+'</option>'; }).join(''); dimL.style.display=''; }
                else dimL.style.display='none';
            }
            var bk=el(prefix+'-back'); if(bk) bk.style.display=spec._drill?'':'none';   // voltar do drill
            var sel=el(prefix+'-type'); if(spec.defaultType && sel && !spec._keepType) sel.value=spec.defaultType;
            drawSpec(prefix);
        }

        // ── dados ──
        var finData=[], opData=[], aprovData=[], perdasData=[], fatData={vendas:[],pagamentos:[]}, curFin='fatdia', curOp='gp', curFiltro='aprovadas';

        // ── dimensões trocáveis + drill (visões simples) ──
        var PERDA_DIMS=[['Motivo Base','Motivo'],['Insumo/Medic.','Insumo / Medicação'],['Responsavel','Responsável'],['Classificação','Classificação da fórmula'],['Fase','Fase de onde saiu']];
        var CONSUMO_DIMS=[['Ativo','Ativo'],['Classificação','Classificação da fórmula'],['Forma Farmac.','Forma farmacêutica'],['Responsavel','Responsável']];
        var LEADS_DIMS=[['Origem','Origem (canal/número)'],['Como nos Conheceu','Como nos conheceu'],['Funil','Funil'],['Responsavel','Responsável'],['Fase','Fase atual'],['Classe','Situação (ganho/perda/andamento)']];
        var CONHECEU_DIMS=[['Como nos Conheceu','Como nos conheceu'],['Tipo de Fechamento','Tipo de fechamento'],['Origem','Origem (canal/número)'],['Responsavel','Responsável'],['Funil','Funil'],['Classe','Situação (ganho/perda/andamento)']];
        var opDimOv=null, finDimOv=null, opDrill=null;   // override de dimensão + filtro de drill (perdas)

        // Builder genérico de visão dimensionável (contagem ou soma sobre uma dimensão trocável).
        function dimSpec(rows, cfg){
            var dim = cfg.ov || cfg.defDim, filt=null, sub='';
            if(cfg.drill){ filt=function(r){ return String(r[cfg.drill.field])===String(cfg.drill.value); }; dim=cfg.drill.to; sub=' · '+cfg.drill.value; }
            var m = cfg.metric==='sum'? gSum(rows,dim,cfg.valField,filt) : gCount(rows,dim,filt);
            var t=top(m), L=t.map(function(x){return x[0];}), tot=sumVals(m);
            var dimLbl=(cfg.dims.filter(function(d){return d[0]===dim;})[0]||['',dim])[1];
            var spec={ title:cfg.baseTitle+' — por '+dimLbl+sub, defaultType:cfg.cur?'bar':'barh', labels:L,
                datasets:[{label:cfg.cur?(cfg.valLabel||'Valor (R$)'):'Cards',data:L.map(function(l){return cfg.cur?r2(m[l]):m[l];}),_cur:!!cfg.cur}],
                _dims:cfg.dims, _dim:dim, _drill:!!cfg.drill,
                sentence: t.length? ((cfg.cur?brl(tot):num(tot))+' '+cfg.noun+'; maior: "'+t[0][0]+'" ('+(cfg.cur?brl(t[0][1]):t[0][1])+', '+pct(tot?t[0][1]/tot*100:0)+').') : (cfg.empty||'Nada no período.') };
            // drill ao clicar numa barra: perdas (Falta de Insumo/Drogaria → Insumo) ou
            // origem (Como nos Conheceu → Tipo de Fechamento). drillWhen null = qualquer barra;
            // drillFromAny = drilla a partir de qualquer dimensão da visão.
            if(!cfg.drill && cfg.drillTo && (cfg.drillFromAny || dim===cfg.drillField)){
                var fromF=cfg.drillFromAny?dim:cfg.drillField;
                spec._onBar=function(lbl){ if(!cfg.drillWhen || cfg.drillWhen.indexOf(lbl)>=0){ opDrill={field:fromF,value:lbl,to:cfg.drillTo}; showSpec('op',specOp(curOp)); } };
            }
            return spec;
        }
        var PERDA_DRILL={drillField:'Motivo Base',drillTo:'Insumo/Medic.',drillWhen:['Falta de Insumo','Drogaria']};

        // Perguntas FINANCEIRO (dinheiro = Caixa; consumo = OMs ganhas/deduplicadas)
        function specFin(key){
            var D=finData, V=fatData.vendas||[], P=fatData.pagamentos||[], notm=function(r){ return r['Ativo'] && r['Ativo']!=='—'; };
            function br(d){ return d.split('-').reverse().join('/'); }
            if(key==='fatdia'){
                var m=gSum(V,'Data','ValorTotal'), L=Object.keys(m).sort(), best=top(m,1)[0], tot=sumVals(m);
                return { title:'💰 Faturado por dia (data de fechamento da venda)', defaultType:'line', labels:L,
                    datasets:[{label:'Faturado (R$)',data:L.map(function(l){return r2(m[l]);}),_cur:true}],
                    sentence: best? ('Faturado no período: '+brl(tot)+'. Melhor dia: '+br(best[0])+' ('+brl(best[1])+').') : 'Sem vendas no período.' };
            }
            if(key==='recdia'){
                var m=gSum(P,'Data','Bruto'), L=Object.keys(m).sort(), best=top(m,1)[0], tot=sumVals(m);
                return { title:'💵 Recebido por dia (data de recebimento)', defaultType:'line', labels:L,
                    datasets:[{label:'Recebido (R$)',data:L.map(function(l){return r2(m[l]);}),_cur:true}],
                    sentence: best? ('Recebido no período: '+brl(tot)+'. Melhor dia: '+br(best[0])+' ('+brl(best[1])+').') : 'Sem recebimentos no período.' };
            }
            if(key==='pagto'){
                var m=gSum(P,'Forma','Bruto'), t=top(m), L=t.map(function(x){return x[0];}), tot=sumVals(m);
                return { title:'💳 Recebido por forma de pagamento', defaultType:'doughnut', labels:L,
                    datasets:[{label:'Recebido (R$)',data:L.map(function(l){return r2(m[l]);}),_cur:true}],
                    sentence: t.length? (t[0][0]+' respondeu por '+pct(tot?t[0][1]/tot*100:0)+' do recebido ('+brl(t[0][1])+').') : 'Sem recebimentos no período.' };
            }
            if(key==='fatresp'){
                var m=gSum(V,'Responsavel','ValorTotal'), t=top(m), L=t.map(function(x){return x[0];}), tot=sumVals(m);
                return { title:'👤 Faturado por responsável', defaultType:'bar', labels:L,
                    datasets:[{label:'Faturado (R$)',data:L.map(function(l){return r2(m[l]);}),_cur:true}],
                    sentence: t.length? (t[0][0]+' lidera com '+brl(t[0][1])+' ('+pct(tot?t[0][1]/tot*100:0)+' do faturado).') : 'Sem vendas no período.' };
            }
            if(key==='ativos'){
                var custo=gSum(D,'Ativo','Custo Ativo (R$)',notm), qtd=gSum(D,'Ativo','Qtd (g)',notm);
                var t=top(custo,15), L=t.map(function(x){return x[0];}), tot=sumVals(custo), t3=t.slice(0,3).reduce(function(a,x){return a+x[1];},0);
                return { title:'💊 Ativos que mais consomem (OMs fechadas)', defaultType:'bar', labels:L,
                    datasets:[ {label:'Custo (R$)',data:L.map(function(l){return r2(custo[l]);}),_cur:true},
                               {label:'Qtd (g)',data:L.map(function(l){return r2(qtd[l]||0);}),_cur:false} ],
                    sentence: t.length? ('Os 3 ativos de maior custo concentram '+pct(tot?t3/tot*100:0)+' do custo de insumos: '+t.slice(0,3).map(function(x){return x[0];}).join(', ')+'.') : 'Sem produção no período.' };
            }
            // Custo de insumos (OMs fechadas) — dimensão trocável (Ativo/Classificação/Forma/Responsável).
            if(key==='classif'||key==='custoforma'){
                var defd = key==='classif'?'Classificação':'Forma Farmac.';
                return dimSpec(D, {dims:CONSUMO_DIMS, defDim:defd, ov:finDimOv, metric:'sum', valField:'Custo Ativo (R$)',
                    cur:true, valLabel:'Custo de insumos (R$)', baseTitle:'🧬 Custo de insumos', noun:'em insumos',
                    empty:'Sem produção no período.' });
            }
            // fallback antigo (não deve cair aqui — mantido por segurança)
            var m=gSum(D,'Forma Farmac.','Custo Ativo (R$)'), t=top(m), L=t.map(function(x){return x[0];}), tot=sumVals(m);
            return { title:'🧪 Custo de insumos por forma farmacêutica', defaultType:'bar', labels:L,
                datasets:[{label:'Custo (R$)',data:L.map(function(l){return r2(m[l]);}),_cur:true}],
                sentence: t.length? ('Maior custo de insumos: '+t[0][0]+' ('+brl(t[0][1])+', '+pct(tot?t[0][1]/tot*100:0)+').') : 'Sem produção no período.' };
        }

        // Perguntas ATENDIMENTO/OPERAÇÃO
        function specOp(key){
            var D=opData, A=aprovData, P=perdasData;
            function br(d){ return d.split('-').reverse().join('/'); }
            // Família de PERDAS — dimensão trocável (seletor "Por:") + drill em Falta de Insumo/Drogaria.
            //   Os botões são atalhos que só mudam a dimensão padrão; o motivo NÃO abre por insumo
            //   (fica agrupado em "Falta de Insumo"/"Drogaria"); o clique na barra é que detalha.
            if(key==='motivo'||key==='ondeperde'||key==='perdaclass'){
                var defd = key==='ondeperde'?'Fase' : key==='perdaclass'?'Classificação' : 'Motivo Base';
                var ttl  = key==='ondeperde'?'📉 Onde perdemos' : key==='perdaclass'?'🧪 Perdas por classificação' : '🚫 Por que perdemos';
                return dimSpec(P, {dims:PERDA_DIMS, defDim:defd, ov:opDimOv, metric:'count', cur:false,
                    baseTitle:ttl, noun:'perdas', empty:'Nenhuma perda no período.', drill:opDrill,
                    drillField:PERDA_DRILL.drillField, drillTo:PERDA_DRILL.drillTo, drillWhen:PERDA_DRILL.drillWhen });
            }
            if(key==='perdaresp'){
                return dimSpec(P, {dims:PERDA_DIMS, defDim:'Responsavel', ov:opDimOv, metric:'sum', valField:'Valor',
                    cur:true, valLabel:'Valor perdido (R$)', baseTitle:'👤 Perdas (valor)', noun:'em perdas',
                    empty:'Nenhuma perda no período.', drill:opDrill,
                    drillField:PERDA_DRILL.drillField, drillTo:PERDA_DRILL.drillTo, drillWhen:PERDA_DRILL.drillWhen });
            }
            if(key==='tempocanc'){
                var m=gAvg(P,'Motivo','Tempo (h)'), t=top(m), L=t.map(function(x){return x[0];});
                var slow=L.slice().sort(function(a,b){return m[b]-m[a];})[0];
                var all=P.map(function(r){return r['Tempo (h)'];}).filter(function(v){return v!=null&&v!=='';}).map(parseFloat);
                var med=all.length?all.reduce(function(a,b){return a+b;},0)/all.length:0;
                return { title:'⏱️ Tempo até o cancelamento (por motivo)', defaultType:'bar', labels:L,
                    datasets:[{label:'Horas até cancelar',data:L.map(function(l){return r2(m[l]);}),_cur:false}],
                    sentence: L.length? ('Média geral: '+num(r2(med))+' h da criação até cancelar. Demora mais em "'+slow+'" ('+num(r2(m[slow]))+' h).') : 'Sem perdas com tempo medido no período.' };
            }
            if(key==='aprovdia'){
                var m=gSum(A,'Data','Valor'), L=Object.keys(m).sort(), best=top(m,1)[0], tot=sumVals(m);
                return { title:'✅ Aprovações por dia (valor final, data da aprovação)', defaultType:'line', labels:L,
                    datasets:[{label:'Valor aprovado (R$)',data:L.map(function(l){return r2(m[l]);}),_cur:true}],
                    sentence: A.length? (A.length+' aprovações somando '+brl(tot)+'. Melhor dia: '+(best?br(best[0])+' ('+brl(best[1])+')':'—')+'.') : 'Nenhuma aprovação no período.' };
            }
            if(key==='aprovresp'){
                var mv=gSum(A,'Responsavel','Valor'), mc=gCount(A,'Responsavel'), t=top(mv), L=t.map(function(x){return x[0];}), tot=sumVals(mv);
                return { title:'✅ Aprovações por responsável', defaultType:'bar', labels:L,
                    datasets:[{label:'Valor aprovado (R$)',data:L.map(function(l){return r2(mv[l]);}),_cur:true}],
                    sentence: t.length? (t[0][0]+' lidera com '+brl(t[0][1])+' em '+(mc[t[0][0]]||0)+' aprovações ('+pct(tot?t[0][1]/tot*100:0)+' do valor aprovado).') : 'Nenhuma aprovação no período.' };
            }
            if(key==='gp'){
                var resp={}; D.forEach(function(r){ var k=r['Responsavel']; if(!resp[k])resp[k]={g:0,p:0}; if(r['Classe']==='Ganho')resp[k].g++; else if(r['Classe']==='Perda')resp[k].p++; });
                var L=Object.keys(resp).sort(function(a,b){return (resp[b].g+resp[b].p)-(resp[a].g+resp[a].p);});
                var G=D.filter(function(r){return r['Classe']==='Ganho';}).length, P=D.filter(function(r){return r['Classe']==='Perda';}).length;
                var conv=(G+P)? G/(G+P)*100:0;
                var bestR=L.slice().sort(function(a,b){ var ca=(resp[a].g+resp[a].p)?resp[a].g/(resp[a].g+resp[a].p):0, cb=(resp[b].g+resp[b].p)?resp[b].g/(resp[b].g+resp[b].p):0; return cb-ca; })[0];
                return { title:'🏆 Ganhos × Perdas por responsável', defaultType:'bar', labels:L,
                    datasets:[ {label:'Ganhos',data:L.map(function(l){return resp[l].g;}),_cur:false},
                               {label:'Perdas',data:L.map(function(l){return resp[l].p;}),_cur:false} ],
                    sentence: (G+P)? ('Conversão geral: '+pct(conv)+' ('+G+' ganhos × '+P+' perdas).'+(bestR?' Melhor conversão: '+bestR+'.':'')) : 'Sem cards classificados no período.' };
            }
            if(key==='origem'){
                return dimSpec(D, {dims:LEADS_DIMS, defDim:'Origem', ov:opDimOv, metric:'count', cur:false,
                    baseTitle:'📥 Leads por origem', noun:'leads', empty:'Nenhum lead no período.'});
            }
            if(key==='conheceu'){
                // clique numa barra (ex.: Google presumido) abre por Tipo de Fechamento
                return dimSpec(D, {dims:CONHECEU_DIMS, defDim:'Como nos Conheceu', ov:opDimOv, metric:'count', cur:false,
                    baseTitle:'📣 Como nos conheceu', noun:'leads', empty:'Nenhum lead no período.',
                    drill:opDrill, drillFromAny:true, drillTo:'Tipo de Fechamento'});
            }
            if(key==='leads'){
                var m=gCount(D,'Fase',function(r){return r['Classe']==='Em andamento';}), t=top(m), L=t.map(function(x){return x[0];}), tot=sumVals(m);
                return { title:'🚦 Onde estão os leads (funil de vendas)', defaultType:'barh', labels:L,
                    datasets:[{label:'Cards',data:L.map(function(l){return m[l];}),_cur:false}],
                    sentence: t.length? (tot+' leads em andamento; maior fila em "'+t[0][0]+'" ('+t[0][1]+').') : 'Nenhum lead em andamento no período.' };
            }
            if(key==='prod'){
                var m=gCount(D,'Fase',function(r){return r['Classe']==='Ganho';}), t=top(m), L=t.map(function(x){return x[0];}), tot=sumVals(m);
                return { title:'🏭 Produção → Entrega (pós-vendas)', defaultType:'barh', labels:L,
                    datasets:[{label:'Cards',data:L.map(function(l){return m[l];}),_cur:false}],
                    sentence: t.length? (tot+' cards ganhos em pós-vendas; concentração em "'+t[0][0]+'" ('+t[0][1]+').') : 'Nenhum card em pós-vendas no período.' };
            }
            if(key==='tmr'){
                var m=gAvg(D,'Responsavel','TMR (min)'), t=top(m), L=t.map(function(x){return x[0];});
                var fast=L.slice().sort(function(a,b){return m[a]-m[b];})[0];
                return { title:'⏱️ Tempo de resposta (TMR) por responsável', defaultType:'bar', labels:L,
                    datasets:[{label:'TMR (min)',data:L.map(function(l){return r2(m[l]);}),_cur:false}],
                    sentence: L.length? ('Só a resposta do atendente (bot/disparo excluídos). Mais rápido: '+fast+' ('+num(r2(m[fast]))+' min).') : 'Sem resposta humana para medir TMR no período.' };
            }
            if(key==='tma'){
                var m=gAvg(D,'Responsavel','TMA (h)'), t=top(m), L=t.map(function(x){return x[0];});
                var fast=L.slice().sort(function(a,b){return m[a]-m[b];})[0];
                return { title:'🕒 Tempo de atendimento (TMA) por responsável', defaultType:'bar', labels:L,
                    datasets:[{label:'TMA (h)',data:L.map(function(l){return r2(m[l]);}),_cur:false}],
                    sentence: L.length? ('Da criação até o fechamento (ganho ou perda). Mais ágil: '+fast+' ('+num(r2(m[fast]))+' h).') : 'Sem cards resolvidos no período.' };
            }
            if(key==='espera'){
                var m=gCount(D,'Responsavel',function(r){return r['Esperando']==1;}), t=top(m), L=t.map(function(x){return x[0];}), tot=sumVals(m);
                var esp=D.filter(function(r){return r['Esperando']==1;}), maxE=esp.reduce(function(a,r){return Math.max(a,parseFloat(r['Espera (min)'])||0);},0);
                return { title:'⏳ Clientes esperando agora — por responsável', defaultType:'bar', labels:L,
                    datasets:[{label:'Aguardando',data:L.map(function(l){return m[l];}),_cur:false}],
                    sentence: tot? (tot+' cliente(s) aguardando resposta; espera máxima '+num(r2(maxE))+' min.') : '🎉 Ninguém aguardando resposta agora.' };
            }
            // renov
            var m=gCount(D,'Fase',function(r){ return /renov/i.test(r['Fase']||''); }), t=top(m), L=t.map(function(x){return x[0];}), tot=sumVals(m);
            return { title:'🔁 Renovações', defaultType:'doughnut', labels:L,
                datasets:[{label:'Cards',data:L.map(function(l){return m[l];}),_cur:false}],
                sentence: t.length? (tot+' cards em fases de renovação; maior: "'+t[0][0]+'" ('+t[0][1]+').') : 'Sem cards em renovação no período.' };
        }

        function renderKPIs(id, arr){
            el(id).innerHTML = arr.map(function(k){ return '<div class="an-kpi"><b>'+k.value+'</b><span>'+k.label+'</span></div>'; }).join('');
        }
        function finKPIs(){
            var V=fatData.vendas||[], P=fatData.pagamentos||[], D=finData;
            var fat=V.reduce(function(a,r){return a+(parseFloat(r['ValorTotal'])||0);},0);
            var rec=P.reduce(function(a,r){return a+(parseFloat(r['Bruto'])||0);},0);
            var cst=D.reduce(function(a,r){return a+(parseFloat(r['Custo Ativo (R$)'])||0);},0);
            var nv=V.length; var ticket=nv?fat/nv:0;
            renderKPIs('fin-kpis',[
                {label:'Faturado (fechamento)',value:brl(fat)},{label:'Recebido (recebimento)',value:brl(rec)},
                {label:'Nº de vendas',value:num(nv)},{label:'Ticket médio',value:brl(ticket)},
                {label:'Custo insumos (fechadas)',value:brl(cst)}
            ]);
        }
        function opKPIs(){
            var D=opData;
            var apN=aprovData.length, apV=aprovData.reduce(function(a,r){return a+(parseFloat(r['Valor'])||0);},0);
            var pdN=perdasData.length, pdV=perdasData.reduce(function(a,r){return a+(parseFloat(r['Valor'])||0);},0);
            var conv=(apN+pdN)?apN/(apN+pdN)*100:0;
            var tmrs=D.map(function(r){return r['TMR (min)'];}).filter(function(v){return v!=null&&v!=='';}).map(parseFloat);
            var tmr=tmrs.length?tmrs.reduce(function(a,b){return a+b;},0)/tmrs.length:0;
            var tmas=D.map(function(r){return r['TMA (h)'];}).filter(function(v){return v!=null&&v!=='';}).map(parseFloat);
            var tma=tmas.length?tmas.reduce(function(a,b){return a+b;},0)/tmas.length:0;
            var esp=D.filter(function(r){return r['Esperando']==1;}).length;
            renderKPIs('op-kpis',[
                {label:'Aprovados',value:num(apN)},{label:'Valor aprovado',value:brl(apV)},
                {label:'Perdidos',value:num(pdN)},{label:'Valor perdido',value:brl(pdV)},
                {label:'Conversão',value:pct(conv)},{label:'TMR (atendente)',value:num(r2(tmr))+' min'},
                {label:'TMA médio',value:num(r2(tma))+' h'},{label:'Esperando agora',value:num(esp)}
            ]);
        }

        function gallery(id, items, cur, onpick){
            el(id).innerHTML = items.map(function(it){ return '<button class="an-qbtn'+(it.key===cur?' on':'')+'" data-k="'+it.key+'">'+it.label+'</button>'; }).join('');
            Array.prototype.forEach.call(el(id).querySelectorAll('.an-qbtn'), function(b){ b.onclick=function(){
                Array.prototype.forEach.call(el(id).querySelectorAll('.an-qbtn'),function(x){x.classList.remove('on');}); b.classList.add('on'); onpick(b.dataset.k); }; });
        }
        var FINQ=[{key:'fatdia',label:'💰 Faturado por dia'},{key:'recdia',label:'💵 Recebido por dia'},{key:'pagto',label:'💳 Forma de pagamento'},{key:'fatresp',label:'👤 Faturado por responsável'},{key:'ativos',label:'💊 Ativos que + consomem'},{key:'custoforma',label:'🧪 Custo por forma'},{key:'classif',label:'🧬 Custo por classificação'}];
        var OPQ=[{key:'aprovdia',label:'✅ Aprovações por dia'},{key:'aprovresp',label:'✅ Aprovações por responsável'},{key:'gp',label:'🏆 Ganhos × Perdas'},{key:'origem',label:'📥 Leads por origem'},{key:'conheceu',label:'📣 Como nos conheceu'},{key:'motivo',label:'🚫 Por que perdemos'},{key:'ondeperde',label:'📉 Onde perdemos'},{key:'perdaresp',label:'👤 Perdas por resp.'},{key:'perdaclass',label:'🧪 Perdas por classificação'},{key:'tempocanc',label:'⏱️ Tempo até cancelar'},{key:'leads',label:'🚦 Onde estão os leads'},{key:'prod',label:'🏭 Produção → Entrega'},{key:'tmr',label:'⏱️ Tempo de resposta'},{key:'tma',label:'🕒 Tempo de atendimento'},{key:'espera',label:'⏳ Quem está esperando'},{key:'renov',label:'🔁 Renovações'}];

        function renderSimples(){
            finKPIs(); opKPIs();
            showSpec('fin', specFin(curFin));
            showSpec('op',  specOp(curOp));
        }

        // ── AVANÇADO: cubo WebDataRocks ──
        var LOC={grid:{total:'Total',totals:'Totais',grandTotal:'Total Geral',blankMember:'(vazio)',dateInvalidCaption:'Data inválida'},
            fieldsList:{addCalculatedMeasure:'Adicionar valor calculado',allFields:'Todos os campos',collapseAll:'Recolher tudo',columnBox:'Solte e organize as Colunas',columns:'Colunas',dropField:'Solte o campo aqui',filterBox:'Solte e organize os Filtros',filters:'Filtros',flatHierarchyBox:'Selecione e organize as colunas',formulasGroupName:'Valores calculados',hierarchyBox:'Arraste as dimensões',measureBox:'Solte e organize os Valores',rowBox:'Solte e organize as Linhas',rows:'Linhas',subtitle:'Arraste e solte os campos para organizar',title:'Campos',values:'Valores'},
            toolbar:{and:'e',apply:'Aplicar',between:'Entre',cancel:'Cancelar',conditional:'Condicional',connect:'Conectar',credentials:'credenciais',csv:'CSV',done:'Concluir',fields:'Campos',format:'Formatar',fullscreen:'Tela cheia',grid:'Grade',landscape:'Paisagem',layout:'Layout',minimize:'Minimizar',none:'Nenhum',ok:'OK',open:'Abrir',options:'Opções',password:'Senha',portrait:'Retrato',save:'Salvar',space:'(Espaço)',subtotals:'Subtotais',then:'Depois',username:'Usuário',value:'Valor'},
            contextMenu:{clearSorting:'Limpar ordenação',collapse:'Recolher',drillThrough:'Detalhar',expand:'Expandir',openFilter:'Abrir filtro',sortColumnAsc:'Ordenar coluna (crescente)',sortColumnDesc:'Ordenar coluna (decrescente)',sortRowAsc:'Ordenar linha (crescente)',sortRowDesc:'Ordenar linha (decrescente)'},
            filter:{all:'Todos',amountFound:'{0} de {1} encontrados selecionados',amountSelected:'{0} de {1} selecionados',ascSort:'Az',bottom:'Menores',clearTopX:'Limpar',descSort:'zA',measuresPrompt:'Escolha o valor',multipleItems:'Vários itens',selectAll:'Selecionar tudo',selectAllResults:'Selecionar todos os resultados',sort:'Ordenar:',top:'Maiores',topX:'Top 10'},
            buttons:{apply:'Aplicar',cancel:'Cancelar',clear:'Limpar',no:'Não',ok:'OK',select:'Selecionar',yes:'Sim'},
            tooltips:{close:'Fechar',collapseIcon:'Recolher',column:'Coluna:',drillDown:'Detalhar',drillUp:'Agrupar',expandIcon:'Expandir',filterIcon:'Filtrar',filtered:'Filtrado',headerFit:'Duplo clique para ajustar',headerResize:'Arraste para redimensionar',row:'Linha:',sortIcon:'Ordenar',sortedAscIcon:'Ordenar'},
            months:{january:'Janeiro',february:'Fevereiro',march:'Março',april:'Abril',may:'Maio',june:'Junho',july:'Julho',august:'Agosto',september:'Setembro',october:'Outubro',november:'Novembro',december:'Dezembro'},
            monthsShort:{january:'Jan',february:'Fev',march:'Mar',april:'Abr',may:'Mai',june:'Jun',july:'Jul',august:'Ago',september:'Set',october:'Out',november:'Nov',december:'Dez'}};
        var META={'OM':{type:'string'},'Data':{type:'date string'},'Mes':{type:'string'},'Telefone':{type:'string'},'Paciente':{type:'string'},'Forma Farmac.':{type:'string'},'Status':{type:'string'},'Funil':{type:'string'},'Fase':{type:'string'},'Responsavel':{type:'string'},'Forma Pagto':{type:'string'},'Desfecho':{type:'string'},'Classificação':{type:'string'},'Ativo':{type:'string'},'Lote':{type:'string'},'Qtd (g)':{type:'number'},'Custo Ativo (R$)':{type:'number'},'Venda Ativo (R$)':{type:'number'},'Preço Venda OM (R$)':{type:'number'},'Valor Pago (R$)':{type:'number'}};
        function Mm(u,f){ return {uniqueName:u,aggregation:'sum',format:f}; }
        var VIEWS={
            ativo:{label:'💊 Ativo: qtd+custo+venda',slice:{rows:[{uniqueName:'Ativo'}],columns:[{uniqueName:'[Measures]'}],measures:[Mm('Qtd (g)','qtd'),Mm('Custo Ativo (R$)','brl'),Mm('Venda Ativo (R$)','brl')]}},
            fin:{label:'💰 Financeiro por forma',slice:{rows:[{uniqueName:'Forma Farmac.'}],columns:[{uniqueName:'[Measures]'}],measures:[Mm('Preço Venda OM (R$)','brl'),Mm('Valor Pago (R$)','brl')]}},
            pgto:{label:'💳 Pagamento × mês',slice:{rows:[{uniqueName:'Forma Pagto'}],columns:[{uniqueName:'Mes'},{uniqueName:'[Measures]'}],measures:[Mm('Valor Pago (R$)','brl')]}},
            resp:{label:'👤 Responsável × mês',slice:{rows:[{uniqueName:'Responsavel'}],columns:[{uniqueName:'Mes'},{uniqueName:'[Measures]'}],measures:[Mm('Preço Venda OM (R$)','brl')]}}
        };
        // Cubo de PERDAS (grão card): dimensione por motivo/insumo/ativo·medicação/responsável etc.
        var META_PERDA={'Data':{type:'date string'},'Mes':{type:'string'},'Motivo':{type:'string'},'Motivo Base':{type:'string'},'Insumo/Medic.':{type:'string'},'Fase':{type:'string'},'Responsavel':{type:'string'},'Classificação':{type:'string'},'Valor':{type:'number'},'Cards':{type:'number'}};
        var VIEWS_PERDA={
            motivo:{label:'🚫 Motivo → Insumo/Medicação',slice:{rows:[{uniqueName:'Motivo Base'},{uniqueName:'Insumo/Medic.'}],columns:[{uniqueName:'[Measures]'}],measures:[Mm('Cards','int'),Mm('Valor','brl')]}},
            insumo:{label:'💊 Insumo/Medicação (causa)',slice:{rows:[{uniqueName:'Insumo/Medic.'}],columns:[{uniqueName:'[Measures]'}],measures:[Mm('Cards','int'),Mm('Valor','brl')]}},
            resp:{label:'👤 Responsável × mês',slice:{rows:[{uniqueName:'Responsavel'}],columns:[{uniqueName:'Mes'},{uniqueName:'[Measures]'}],measures:[Mm('Cards','int'),Mm('Valor','brl')]}},
            classif:{label:'🧪 Classificação',slice:{rows:[{uniqueName:'Classificação'}],columns:[{uniqueName:'[Measures]'}],measures:[Mm('Cards','int'),Mm('Valor','brl')]}},
            fase:{label:'📉 Fase de onde saiu',slice:{rows:[{uniqueName:'Fase'}],columns:[{uniqueName:'[Measures]'}],measures:[Mm('Cards','int'),Mm('Valor','brl')]}}
        };
        // Cubo de LEADS (grão card): TODOS os dados do lead p/ dimensionar livremente,
        // incluindo "Como nos Conheceu?" (Google, indicação, etc.).
        var META_LEADS={'Data':{type:'date string'},'Mes':{type:'string'},'Como nos Conheceu':{type:'string'},'Tipo de Fechamento':{type:'string'},'Origem':{type:'string'},'Funil':{type:'string'},'Fase':{type:'string'},'Classe':{type:'string'},'Status':{type:'string'},'Classificação':{type:'string'},'Forma de Entrega':{type:'string'},'Responsavel':{type:'string'},'Valor':{type:'number'},'Leads':{type:'number'}};
        var VIEWS_LEADS={
            conheceu:{label:'📣 Como nos conheceu',slice:{rows:[{uniqueName:'Como nos Conheceu'}],columns:[{uniqueName:'[Measures]'}],measures:[Mm('Leads','int'),Mm('Valor','brl')]}},
            conhxfech:{label:'📣 Conheceu → tipo de fechamento',slice:{rows:[{uniqueName:'Como nos Conheceu'},{uniqueName:'Tipo de Fechamento'}],columns:[{uniqueName:'[Measures]'}],measures:[Mm('Leads','int'),Mm('Valor','brl')]}},
            conhxsit:{label:'📣 Conheceu × situação',slice:{rows:[{uniqueName:'Como nos Conheceu'}],columns:[{uniqueName:'Classe'},{uniqueName:'[Measures]'}],measures:[Mm('Leads','int')]}},
            origem:{label:'📥 Origem (canal/número)',slice:{rows:[{uniqueName:'Origem'}],columns:[{uniqueName:'[Measures]'}],measures:[Mm('Leads','int'),Mm('Valor','brl')]}},
            resp:{label:'👤 Responsável × mês',slice:{rows:[{uniqueName:'Responsavel'}],columns:[{uniqueName:'Mes'},{uniqueName:'[Measures]'}],measures:[Mm('Leads','int'),Mm('Valor','brl')]}},
            funil:{label:'🚦 Funil → fase',slice:{rows:[{uniqueName:'Funil'},{uniqueName:'Fase'}],columns:[{uniqueName:'[Measures]'}],measures:[Mm('Leads','int'),Mm('Valor','brl')]}},
            classif:{label:'🧬 Classificação',slice:{rows:[{uniqueName:'Classificação'}],columns:[{uniqueName:'[Measures]'}],measures:[Mm('Leads','int'),Mm('Valor','brl')]}}
        };
        // Cubo do RELATÓRIO (dados denormalizados completos, carregados sob demanda do
        // handler tao_crm_relatorio_dataset): grão card (todos os campos + personalizados)
        // e grão item (repete o card por ativo). META é dinâmico (colunas do relatório).
        var VIEWS_CARDS={
            conheceu:{label:'📣 Como nos conheceu',slice:{rows:[{uniqueName:'Como nos Conheceu'}],columns:[{uniqueName:'[Measures]'}],measures:[Mm('Cards','int'),Mm('Valor (R$)','brl')]}},
            resp:{label:'👤 Responsável',slice:{rows:[{uniqueName:'Responsável'}],columns:[{uniqueName:'[Measures]'}],measures:[Mm('Cards','int'),Mm('Valor (R$)','brl')]}},
            funil:{label:'🚦 Funil → Fase',slice:{rows:[{uniqueName:'Funil'},{uniqueName:'Fase'}],columns:[{uniqueName:'[Measures]'}],measures:[Mm('Cards','int'),Mm('Valor (R$)','brl')]}},
            negocio:{label:'🧩 Negócio (arraste os campos)',slice:{rows:[{uniqueName:'Negócio'}],columns:[{uniqueName:'[Measures]'}],measures:[Mm('Cards','int'),Mm('Valor (R$)','brl')]}}
        };
        var VIEWS_ITENS={
            ativo:{label:'💊 Ativo: qtd+custo+venda',slice:{rows:[{uniqueName:'Ativo'}],columns:[{uniqueName:'[Measures]'}],measures:[Mm('Itens','int'),Mm('Qtd (g)','qtd'),Mm('Custo Ativo (R$)','brl'),Mm('Venda Ativo (R$)','brl')]}},
            ativoconh:{label:'💊 Ativo × Como conheceu',slice:{rows:[{uniqueName:'Ativo'}],columns:[{uniqueName:'Como nos Conheceu'},{uniqueName:'[Measures]'}],measures:[Mm('Custo Ativo (R$)','brl')]}},
            formaativo:{label:'🧪 Forma → Ativo',slice:{rows:[{uniqueName:'Forma Farmac.'},{uniqueName:'Ativo'}],columns:[{uniqueName:'[Measures]'}],measures:[Mm('Itens','int'),Mm('Custo Ativo (R$)','brl'),Mm('Venda Ativo (R$)','brl')]}}
        };
        var relCube = {};   // cache dos dados do relatório p/ o cubo: {cards:{data}, itens:{data}}
        function carregarRelCube(grao, cb){
            var key = grao==='item'?'itens':'cards';
            if(relCube[key]){ cb(); return; }
            setMsg('Carregando dados do relatório para o cubo…');
            var body='action=tao_crm_relatorio_dataset&nonce='+encodeURIComponent(nonce)+'&workspace_id='+encodeURIComponent(wsId)
                +'&de='+encodeURIComponent(el('an-de').value)+'&ate='+encodeURIComponent(el('an-ate').value)
                +'&grao='+encodeURIComponent(grao)
                +'&negocio='+encodeURIComponent({aprovadas:'ganho',canceladas:'perda',todas:'todos'}[curFiltro]||'todos');   // filtro-mestre → negócio
            fetch(ajaxurl,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:body,credentials:'same-origin'})
            .then(function(r){return r.text();}).then(function(t){ var i=t.indexOf('{'),j; try{ j=JSON.parse(i>0?t.slice(i):t); }catch(e){ setMsg('⚠ Erro ao carregar o cubo do relatório.'); return; }
                if(!j||!j.success){ setMsg('⚠ '+((j&&j.data)||'Falha ao gerar.')); return; }
                var cols=j.data.colunas||[], rows=j.data.rows||[];
                var numCols={'Valor (R$)':1,'Qtd (g)':1,'Custo Ativo (R$)':1,'Venda Ativo (R$)':1};
                var measure = key==='itens'?'Itens':'Cards';
                var meta={}; cols.forEach(function(c){ meta[c]={type: numCols[c]?'number':'string'}; }); meta[measure]={type:'number'};
                var data=[meta];
                rows.forEach(function(r){ var o={}; cols.forEach(function(c,ci){ o[c]=r[ci]; }); o[measure]=1; data.push(o); });
                relCube[key]={ data:data };
                setMsg(''); cb();
            }).catch(function(){ setMsg('⚠ Erro de rede ao carregar o cubo do relatório.'); });
        }
        var wdr=null, wdrReady=false, curCube='consumo', curView='ativo', fieldsOpen=false, pivChartOn=false;
        function curViews(){ return curCube==='perdas'?VIEWS_PERDA : curCube==='leads'?VIEWS_LEADS : curCube==='cards'?VIEWS_CARDS : curCube==='itens'?VIEWS_ITENS : VIEWS; }
        function cubeRows(){
            if(curCube==='perdas'){ return [META_PERDA].concat(perdasData.map(function(r){
                return {'Data':r['Data'],'Mes':r['Mes'],'Motivo':r['Motivo'],'Motivo Base':r['Motivo Base'],'Insumo/Medic.':r['Insumo/Medic.'],'Fase':r['Fase'],'Responsavel':r['Responsavel'],'Classificação':r['Classificação'],'Valor':r['Valor'],'Cards':1}; })); }
            if(curCube==='leads'){ return [META_LEADS].concat(opData.map(function(r){
                return {'Data':r['Data'],'Mes':r['Mes'],'Como nos Conheceu':r['Como nos Conheceu'],'Tipo de Fechamento':r['Tipo de Fechamento'],'Origem':r['Origem'],'Funil':r['Funil'],'Fase':r['Fase'],'Classe':r['Classe'],'Status':r['Status'],'Classificação':r['Classificação'],'Forma de Entrega':r['Forma de Entrega'],'Responsavel':r['Responsavel'],'Valor':r['Valor'],'Leads':1}; })); }
            if(curCube==='cards'||curCube==='itens'){ var k=curCube==='itens'?'itens':'cards'; return relCube[k]?relCube[k].data:[{}]; }
            return [META].concat(finData);
        }
        function buildReport(slice){ return { dataSource:{type:'json',data:cubeRows()}, slice:slice,
            formats:[{name:'brl',decimalPlaces:2,decimalSeparator:',',thousandsSeparator:'.',currencySymbol:'R$ ',currencySymbolAlign:'left',nullValue:''},{name:'qtd',decimalPlaces:2,decimalSeparator:',',thousandsSeparator:'.',nullValue:''},{name:'int',decimalPlaces:0,decimalSeparator:',',thousandsSeparator:'.',nullValue:''}],
            options:{grid:{type:'compact',showGrandTotals:'on',showTotals:'on',title:''}} }; }
        function syncFieldsBtn(){ var b=el('an-fields-toggle'); if(b) b.className='button'+(fieldsOpen?' button-primary':''); }
        // Recarrega os dados do cubo PRESERVANDO a visão/slice atual. (updateData reseta o
        // slice p/ o default do WebDataRocks — 1º campo do META × Soma — daí o "OM e Soma Qtde".)
        function cubeReload(){ if(!wdr) return;
            if(curCube==='cards'||curCube==='itens'){ var k=curCube==='itens'?'itens':'cards';
                if(!relCube[k]){ carregarRelCube(curCube==='itens'?'item':'card', function(){ var r=wdr.getReport(); r.dataSource={type:'json',data:cubeRows()}; wdr.setReport(r); }); return; } }
            var rep=wdr.getReport(); rep.dataSource={type:'json',data:cubeRows()}; wdr.setReport(rep); }
        function renderViewBtns(){
            var V=curViews(), host=el('an-vis-btns'); if(!host) return; host.innerHTML='';
            Object.keys(V).forEach(function(k){ var b=document.createElement('button'); b.type='button'; b.className='button button-small'+(k===curView?' button-primary':''); b.style.marginRight='4px'; b.textContent=V[k].label;
                b.onclick=function(){ curView=k; wdr.setReport(buildReport(V[k].slice)); renderViewBtns(); }; host.appendChild(b); });
        }
        function ensureWDR(cb){
            if(wdrReady){ cb(); return; }
            css(WDR_CSS); js(WDR_TB, function(){ js(WDR_CR, function(){
                wdr=new WebDataRocks({ container:'#wdr-pivot', toolbar:true, height:600, global:{localization:LOC}, report:buildReport(curViews()[curView].slice) });
                wdr.on('update', function(){ if(pivChartOn) pivRefresh(); });
                wdr.on('reportcomplete', function(){ if(pivChartOn) pivRefresh(); });
                wdr.on('fieldslistopen', function(){ fieldsOpen=true; syncFieldsBtn(); });
                wdr.on('fieldslistclose', function(){ fieldsOpen=false; syncFieldsBtn(); });
                renderViewBtns();
                wdrReady=true; cb();
            }); });
        }
        function pivRefresh(){
            if(!pivChartOn||!wdr||!window.Chart) return;
            wdr.getData({}, function(raw){
                try{
                    var rows=raw.data||[]; if(rows.length<2){ el('piv-msg').style.display='block'; el('piv-msg').textContent='Sem dados para o gráfico.'; return; }
                    var vKeys=Object.keys(rows[0]).filter(function(k){return /^v\d+$/.test(k);}); if(!vKeys.length){ el('piv-msg').style.display='block'; el('piv-msg').textContent='Coloque uma medida em Valores.'; return; }
                    var meas=(wdr.getReport().slice&&wdr.getReport().slice.measures)||[];
                    var labels=[], ds=vKeys.map(function(vk,i){ var nm=(meas[i]&&meas[i].uniqueName)||('Valor '+(i+1)); return {label:nm,data:[],_cur:/R\$/.test(nm)}; });
                    for(var i=1;i<rows.length;i++){ var d=rows[i]; if(d.r0===undefined||d.r1!==undefined) continue; labels.push(String(d.r0)); vKeys.forEach(function(vk,j){ var v=d[vk]; ds[j].data.push(typeof v==='number'?v:0); }); }
                    if(!labels.length){ el('piv-msg').style.display='block'; el('piv-msg').textContent='Escolha um campo em Linhas.'; return; }
                    if(labels.length>30){ var idx=labels.map(function(_,i){return i;}).sort(function(a,b){return (ds[0].data[b]||0)-(ds[0].data[a]||0);}).slice(0,30); labels=idx.map(function(i){return labels[i];}); ds.forEach(function(x){x.data=idx.map(function(i){return x.data[i];});}); el('piv-msg').style.display='block'; el('piv-msg').textContent='Top 30 por '+ds[0].label+'.'; }
                    else el('piv-msg').style.display='none';
                    var spec={labels:labels,datasets:ds,_keepType:true,defaultType:el('piv-type').value}; showSpec('piv',spec);
                }catch(e){ el('piv-msg').style.display='block'; el('piv-msg').textContent='Erro no gráfico ('+e.message+').'; }
            });
        }

        // ── modo ──
        function setMode(m){
            el('mode-s').className=(m==='s'?'on':''); el('mode-a').className=(m==='a'?'on':''); el('mode-r').className=(m==='r'?'on':'');
            el('an-simples').style.display=(m==='s'?'block':'none'); el('an-avancado').style.display=(m==='a'?'block':'none'); el('an-relatorio').style.display=(m==='r'?'block':'none');
            if(m==='a') ensureWDR(function(){ cubeReload(); });
        }

        // ── MODO RELATÓRIO: dataset denormalizado (grão card ou item) + export XLSX/CSV ──
        var SHEETJS='https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js';
        var relCOLS=[], relROWS=[], sheetjsReady=false, REL_PREVIEW=300;
        function loadSheetJS(cb){ if(sheetjsReady){cb();return;} js(SHEETJS,function(){ sheetjsReady=!!window.XLSX; if(sheetjsReady)cb(); else relStatus('⚠ Não carregou a biblioteca de Excel; use o CSV.'); }); }
        function relStatus(h){ el('rel-status').innerHTML=h; }
        function relEsc(s){ return String(s).replace(/[&<>]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;'}[c];}); }
        function relGerar(){
            relStatus('Gerando… buscando cards e campos.'); el('rel-xlsx').disabled=true; el('rel-csv').disabled=true;
            var body='action=tao_crm_relatorio_dataset&nonce='+encodeURIComponent(nonce)+'&workspace_id='+encodeURIComponent(wsId)
                +'&de='+encodeURIComponent(el('an-de').value)+'&ate='+encodeURIComponent(el('an-ate').value)
                +'&grao='+encodeURIComponent(el('rel-grao').value)+'&negocio='+encodeURIComponent(el('rel-negocio').value)
                +'&pipeline_id='+encodeURIComponent(el('rel-funil').value)+'&responsavel_id='+encodeURIComponent(el('rel-resp').value);
            fetch(ajaxurl,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:body,credentials:'same-origin'})
            .then(function(r){return r.text();}).then(function(t){ var i=t.indexOf('{'),j; try{ j=JSON.parse(i>0?t.slice(i):t); }catch(e){ relStatus('⚠ Erro ao ler a resposta.'); return; }
                if(!j||!j.success){ relStatus('⚠ '+((j&&j.data)||'Falha ao gerar.')); return; }
                relCOLS=j.data.colunas||[]; relROWS=j.data.rows||[]; relRender();
                el('rel-xlsx').disabled=relROWS.length===0; el('rel-csv').disabled=relROWS.length===0;
                relStatus('<b>'+relROWS.length+'</b> linha(s) · <b>'+relCOLS.length+'</b> colunas'+(relROWS.length>REL_PREVIEW?' · mostrando os primeiros '+REL_PREVIEW+' (o export leva todos)':''));
            }).catch(function(){ relStatus('⚠ Erro de rede ao gerar.'); });
        }
        function relRender(){ var w=el('rel-tbl-wrap'); if(!relROWS.length){ w.style.display='none'; return; } w.style.display='block';
            el('rel-tbl').querySelector('thead').innerHTML='<tr>'+relCOLS.map(function(c){return '<th>'+relEsc(c)+'</th>';}).join('')+'</tr>';
            el('rel-tbl').querySelector('tbody').innerHTML=relROWS.slice(0,REL_PREVIEW).map(function(r){ return '<tr>'+r.map(function(v){return '<td>'+relEsc(v==null?'':String(v))+'</td>';}).join('')+'</tr>'; }).join('');
        }
        function relNome(ext){ return 'relatorio-cards_'+el('an-de').value+'_a_'+el('an-ate').value+'.'+ext; }
        function relXLSX(){ if(!relROWS.length)return; relStatus('Montando planilha…'); loadSheetJS(function(){
            var aoa=[relCOLS].concat(relROWS), ws=XLSX.utils.aoa_to_sheet(aoa);
            ws['!cols']=relCOLS.map(function(c,i){ var w=String(c).length; relROWS.forEach(function(r){ var v=r[i]==null?'':String(r[i]); if(v.length>w)w=v.length; }); return {wch:Math.min(Math.max(w+1,8),50)}; });
            var wb=XLSX.utils.book_new(); XLSX.utils.book_append_sheet(wb,ws,'Cards'); XLSX.writeFile(wb,relNome('xlsx'));
            relStatus('<b>'+relROWS.length+'</b> linha(s) exportadas para Excel.'); }); }
        function relCSV(){ if(!relROWS.length)return; function cell(v){ v=v==null?'':String(v); return /[";\n]/.test(v)?'"'+v.replace(/"/g,'""')+'"':v; }
            var lines=[relCOLS.map(cell).join(';')].concat(relROWS.map(function(r){return r.map(cell).join(';');}));
            var blob=new Blob(['﻿'+lines.join('\r\n')],{type:'text/csv;charset=utf-8;'});
            var a=document.createElement('a'); a.href=URL.createObjectURL(blob); a.download=relNome('csv'); a.click(); URL.revokeObjectURL(a.href); }

        // ── carregar (financeiro + operação) ──
        function toISO(d){ return d.getFullYear()+'-'+('0'+(d.getMonth()+1)).slice(-2)+'-'+('0'+d.getDate()).slice(-2); }
        function post(action, cb){
            var de=el('an-de').value, ate=el('an-ate').value;
            var body='action='+action+'&nonce='+encodeURIComponent(nonce)+'&workspace_id='+encodeURIComponent(wsId)+'&de='+encodeURIComponent(de)+'&ate='+encodeURIComponent(ate)+'&filtro='+encodeURIComponent(curFiltro);
            fetch(ajaxurl,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:body,credentials:'same-origin'})
            .then(function(r){return r.text();}).then(function(t){ var i=t.indexOf('{'),j; try{ j=JSON.parse(i>0?t.slice(i):t); }catch(e){ cb(null); return; } cb(j&&j.success?j.data:null); })
            .catch(function(){ cb(null); });
        }
        function carregar(){
            el('an-status').textContent='carregando…'; setMsg('Buscando dados do período…');
            relCube={};   // invalida o cache do cubo do relatório (período/filtro mudou)
            var done=0; function step(){ done++; if(done===3){ setMsg('');
                el('an-status').textContent=(fatData.vendas||[]).length+' vendas · '+finData.length+' linhas de consumo · '+opData.length+' cards';
                renderSimples();
                cubeReload();
            } }
            post('tao_crm_analise_dataset',    function(d){ finData=(d&&d.rows)||[]; step(); });
            post('tao_crm_operacao_dataset',   function(d){ opData=(d&&d.rows)||[]; aprovData=(d&&d.aprovacoes)||[]; perdasData=(d&&d.perdas)||[]; step(); });
            post('tao_crm_financeiro_dataset', function(d){ fatData={vendas:(d&&d.vendas)||[],pagamentos:(d&&d.pagamentos)||[]}; step(); });
        }

        function wireUI(){
            el('an-hoje').onclick=function(){ var h=toISO(new Date()); el('an-de').value=h; el('an-ate').value=h; carregar(); };
            el('an-mes').onclick=function(){ var n=new Date(); el('an-de').value=toISO(new Date(n.getFullYear(),n.getMonth(),1)); el('an-ate').value=toISO(n); carregar(); };
            Array.prototype.forEach.call(document.querySelectorAll('[data-preset]'), function(b){ b.onclick=function(){ var n=parseInt(b.dataset.preset,10),a=new Date(),d=new Date(); d.setDate(d.getDate()-(n-1)); el('an-de').value=toISO(d); el('an-ate').value=toISO(a); carregar(); }; });
            el('an-aplicar').onclick=carregar;
            Array.prototype.forEach.call(document.querySelectorAll('#an-filtro button'), function(b){ b.onclick=function(){
                curFiltro=b.dataset.f;
                Array.prototype.forEach.call(document.querySelectorAll('#an-filtro button'),function(x){ x.className=(x.dataset.f===curFiltro?'on':''); });
                carregar(); }; });
            el('mode-s').onclick=function(){ setMode('s'); }; el('mode-a').onclick=function(){ setMode('a'); }; el('mode-r').onclick=function(){ setMode('r'); };
            el('rel-gerar').onclick=relGerar; el('rel-xlsx').onclick=relXLSX; el('rel-csv').onclick=relCSV;
            // ao trocar de atalho, zera a dimensão override e o drill (volta ao padrão da visão)
            gallery('fin-gallery', FINQ, curFin, function(k){ curFin=k; finDimOv=null; showSpec('fin', specFin(k)); });
            gallery('op-gallery',  OPQ,  curOp,  function(k){ curOp=k; opDimOv=null; opDrill=null; showSpec('op',  specOp(k)); });
            el('fin-type').onchange=function(){ drawSpec('fin'); };
            el('op-type').onchange=function(){ drawSpec('op'); };
            el('piv-type').onchange=function(){ drawSpec('piv'); };
            // seletor de dimensão (visões prontas) + voltar do drill
            if(el('fin-dim')) el('fin-dim').onchange=function(){ finDimOv=this.value; showSpec('fin', specFin(curFin)); };
            if(el('op-dim'))  el('op-dim').onchange =function(){ opDimOv=this.value; opDrill=null; showSpec('op', specOp(curOp)); };
            if(el('op-back')) el('op-back').onclick=function(){ opDrill=null; showSpec('op', specOp(curOp)); };
            // troca de dataset do cubo (Consumo × Perdas)
            Array.prototype.forEach.call(document.querySelectorAll('#an-cube-ds button'), function(b){ b.onclick=function(){
                curCube=b.dataset.ds;
                Array.prototype.forEach.call(document.querySelectorAll('#an-cube-ds button'),function(x){ x.className=(x.dataset.ds===curCube?'on':''); });
                curView=Object.keys(curViews())[0];
                if(!wdr) return;
                var aplica=function(){ renderViewBtns(); wdr.setReport(buildReport(curViews()[curView].slice)); };
                if(curCube==='cards'||curCube==='itens') carregarRelCube(curCube==='itens'?'item':'card', aplica);
                else aplica();
            }; });
            el('an-fields-toggle').onclick=function(){ if(!wdr)return; try{ if(fieldsOpen) wdr.closeFieldsList(); else wdr.openFieldsList(); }catch(e){} };
            el('an-chart-toggle').onclick=function(){ pivChartOn=!pivChartOn; el('piv-chart-wrap').style.display=pivChartOn?'block':'none'; el('an-chart-toggle').className='button'+(pivChartOn?' button-primary':''); if(pivChartOn) pivRefresh(); };
        }

        setMsg('Carregando biblioteca de gráficos…');
        js(CHARTJS, function(){ js(DATALAB, function(){ try{ Chart.register(ChartDataLabels); }catch(e){} wireUI(); carregar(); }); });
    })();
    </script>
    <?php
}
