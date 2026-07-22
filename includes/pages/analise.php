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
    </style>

    <div class="wrap tao-analise">
        <h1 style="margin-bottom:2px;display:inline-block">&#x1F4CA; Análise
            <span class="an-mode"><button id="mode-s" class="on">Simples</button><button id="mode-a">Avançado</button></span>
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
        <div id="an-msg" style="margin-top:10px;color:#64748b"></div>

        <!-- ───────── MODO SIMPLES ───────── -->
        <div id="an-simples">
            <h2>&#x1F4B0; Financeiro &amp; Produção</h2>
            <div class="an-kpis" id="fin-kpis"></div>
            <div class="an-gallery" id="fin-gallery"></div>
            <div class="an-result">
                <div class="an-rhead"><b id="fin-title"></b><label style="font-size:12px;color:#64748b">Ver como: <select id="fin-type" class="an-type"><?php echo $topts; ?></select></label></div>
                <div class="an-sentence" id="fin-sentence"></div>
                <div style="height:380px"><canvas id="fin-canvas"></canvas></div>
            </div>

            <h2>&#x1F3A7; Atendimento &amp; Vendas</h2>
            <div class="an-kpis" id="op-kpis"></div>
            <div class="an-gallery" id="op-gallery"></div>
            <div class="an-result">
                <div class="an-rhead"><b id="op-title"></b><label style="font-size:12px;color:#64748b">Ver como: <select id="op-type" class="an-type"><?php echo $topts; ?></select></label></div>
                <div class="an-sentence" id="op-sentence"></div>
                <div style="height:380px"><canvas id="op-canvas"></canvas></div>
            </div>
        </div>

        <!-- ───────── MODO AVANÇADO (cubo) ───────── -->
        <div id="an-avancado" style="display:none">
            <div style="margin:6px 0;display:flex;gap:6px;align-items:center;flex-wrap:wrap">
                <strong style="font-size:12px;color:#475569">Visões prontas:</strong>
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
            charts[prefix]=new Chart(el(prefix+'-canvas').getContext('2d'), buildCfg(spec,type));
        }
        function showSpec(prefix, spec){
            SPECS[prefix]=spec;
            if(el(prefix+'-title')) el(prefix+'-title').textContent=spec.title||'';
            if(el(prefix+'-sentence')){ el(prefix+'-sentence').textContent=spec.sentence||''; el(prefix+'-sentence').style.display=spec.sentence?'block':'none'; }
            var sel=el(prefix+'-type'); if(spec.defaultType && sel && !spec._keepType) sel.value=spec.defaultType;
            drawSpec(prefix);
        }

        // ── dados ──
        var finData=[], opData=[], fatData={vendas:[],pagamentos:[]}, curFin='fatdia', curOp='gp';

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
            // custo por forma farmacêutica (consumo das fechadas)
            var m=gSum(D,'Forma Farmac.','Custo Ativo (R$)'), t=top(m), L=t.map(function(x){return x[0];}), tot=sumVals(m);
            return { title:'🧪 Custo de insumos por forma farmacêutica', defaultType:'bar', labels:L,
                datasets:[{label:'Custo (R$)',data:L.map(function(l){return r2(m[l]);}),_cur:true}],
                sentence: t.length? ('Maior custo de insumos: '+t[0][0]+' ('+brl(t[0][1])+', '+pct(tot?t[0][1]/tot*100:0)+').') : 'Sem produção no período.' };
        }

        // Perguntas ATENDIMENTO/OPERAÇÃO
        function specOp(key){
            var D=opData;
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
                    sentence: L.length? ('Resposta mais rápida: '+fast+' ('+num(r2(m[fast]))+' min). ⚠️ respostas automáticas do bot podem influenciar.') : 'Sem mensagens para medir TMR no período.' };
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
            var g=D.filter(function(r){return r['Classe']==='Ganho';}).length, p=D.filter(function(r){return r['Classe']==='Perda';}).length;
            var conv=(g+p)?g/(g+p)*100:0;
            var tmrs=D.map(function(r){return r['TMR (min)'];}).filter(function(v){return v!=null&&v!=='';}).map(parseFloat);
            var tmr=tmrs.length?tmrs.reduce(function(a,b){return a+b;},0)/tmrs.length:0;
            var esp=D.filter(function(r){return r['Esperando']==1;}).length;
            renderKPIs('op-kpis',[
                {label:'Ganhos',value:num(g)},{label:'Perdas',value:num(p)},
                {label:'Conversão',value:pct(conv)},{label:'TMR médio',value:num(r2(tmr))+' min'},
                {label:'Esperando agora',value:num(esp)}
            ]);
        }

        function gallery(id, items, cur, onpick){
            el(id).innerHTML = items.map(function(it){ return '<button class="an-qbtn'+(it.key===cur?' on':'')+'" data-k="'+it.key+'">'+it.label+'</button>'; }).join('');
            Array.prototype.forEach.call(el(id).querySelectorAll('.an-qbtn'), function(b){ b.onclick=function(){
                Array.prototype.forEach.call(el(id).querySelectorAll('.an-qbtn'),function(x){x.classList.remove('on');}); b.classList.add('on'); onpick(b.dataset.k); }; });
        }
        var FINQ=[{key:'fatdia',label:'💰 Faturado por dia'},{key:'recdia',label:'💵 Recebido por dia'},{key:'pagto',label:'💳 Forma de pagamento'},{key:'fatresp',label:'👤 Faturado por responsável'},{key:'ativos',label:'💊 Ativos que + consomem'},{key:'custoforma',label:'🧪 Custo por forma'}];
        var OPQ=[{key:'gp',label:'🏆 Ganhos × Perdas'},{key:'leads',label:'🚦 Onde estão os leads'},{key:'prod',label:'🏭 Produção → Entrega'},{key:'tmr',label:'⏱️ Tempo de resposta'},{key:'espera',label:'⏳ Quem está esperando'},{key:'renov',label:'🔁 Renovações'}];

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
        var META={'OM':{type:'string'},'Data':{type:'date string'},'Mes':{type:'string'},'Telefone':{type:'string'},'Paciente':{type:'string'},'Forma Farmac.':{type:'string'},'Status':{type:'string'},'Funil':{type:'string'},'Fase':{type:'string'},'Responsavel':{type:'string'},'Forma Pagto':{type:'string'},'Ativo':{type:'string'},'Lote':{type:'string'},'Qtd (g)':{type:'number'},'Custo Ativo (R$)':{type:'number'},'Venda Ativo (R$)':{type:'number'},'Preço Venda OM (R$)':{type:'number'},'Valor Pago (R$)':{type:'number'}};
        function Mm(u,f){ return {uniqueName:u,aggregation:'sum',format:f}; }
        var VIEWS={
            ativo:{label:'💊 Ativo: qtd+custo+venda',slice:{rows:[{uniqueName:'Ativo'}],columns:[{uniqueName:'[Measures]'}],measures:[Mm('Qtd (g)','qtd'),Mm('Custo Ativo (R$)','brl'),Mm('Venda Ativo (R$)','brl')]}},
            fin:{label:'💰 Financeiro por forma',slice:{rows:[{uniqueName:'Forma Farmac.'}],columns:[{uniqueName:'[Measures]'}],measures:[Mm('Preço Venda OM (R$)','brl'),Mm('Valor Pago (R$)','brl')]}},
            pgto:{label:'💳 Pagamento × mês',slice:{rows:[{uniqueName:'Forma Pagto'}],columns:[{uniqueName:'Mes'},{uniqueName:'[Measures]'}],measures:[Mm('Valor Pago (R$)','brl')]}},
            resp:{label:'👤 Responsável × mês',slice:{rows:[{uniqueName:'Responsavel'}],columns:[{uniqueName:'Mes'},{uniqueName:'[Measures]'}],measures:[Mm('Preço Venda OM (R$)','brl')]}},
            drill:{label:'🔎 Telefone→OM→Ativo→Lote',slice:{rows:[{uniqueName:'Telefone'},{uniqueName:'OM'},{uniqueName:'Ativo'},{uniqueName:'Lote'}],columns:[{uniqueName:'[Measures]'}],measures:[Mm('Qtd (g)','qtd'),Mm('Custo Ativo (R$)','brl'),Mm('Venda Ativo (R$)','brl'),Mm('Preço Venda OM (R$)','brl'),Mm('Valor Pago (R$)','brl')]}}
        };
        var wdr=null, wdrReady=false, curView='ativo', fieldsOpen=false, pivChartOn=false;
        function buildReport(slice){ return { dataSource:{type:'json',data:[META].concat(finData)}, slice:slice,
            formats:[{name:'brl',decimalPlaces:2,decimalSeparator:',',thousandsSeparator:'.',currencySymbol:'R$ ',currencySymbolAlign:'left',nullValue:''},{name:'qtd',decimalPlaces:2,decimalSeparator:',',thousandsSeparator:'.',nullValue:''}],
            options:{grid:{type:'compact',showGrandTotals:'on',showTotals:'on',title:''}} }; }
        function syncFieldsBtn(){ var b=el('an-fields-toggle'); if(b) b.className='button'+(fieldsOpen?' button-primary':''); }
        function ensureWDR(cb){
            if(wdrReady){ cb(); return; }
            css(WDR_CSS); js(WDR_TB, function(){ js(WDR_CR, function(){
                wdr=new WebDataRocks({ container:'#wdr-pivot', toolbar:true, height:600, global:{localization:LOC}, report:buildReport(VIEWS[curView].slice) });
                wdr.on('update', function(){ if(pivChartOn) pivRefresh(); });
                wdr.on('reportcomplete', function(){ if(pivChartOn) pivRefresh(); });
                wdr.on('fieldslistopen', function(){ fieldsOpen=true; syncFieldsBtn(); });
                wdr.on('fieldslistclose', function(){ fieldsOpen=false; syncFieldsBtn(); });
                var host=el('an-vis-btns'); host.innerHTML='';
                Object.keys(VIEWS).forEach(function(k){ var b=document.createElement('button'); b.type='button'; b.className='button button-small'; b.style.marginRight='4px'; b.textContent=VIEWS[k].label; b.onclick=function(){ curView=k; wdr.setReport(buildReport(VIEWS[k].slice)); }; host.appendChild(b); });
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
            el('mode-s').className=(m==='s'?'on':''); el('mode-a').className=(m==='a'?'on':'');
            el('an-simples').style.display=(m==='s'?'block':'none'); el('an-avancado').style.display=(m==='a'?'block':'none');
            if(m==='a') ensureWDR(function(){ if(wdr) wdr.updateData({data:[META].concat(finData)}); });
        }

        // ── carregar (financeiro + operação) ──
        function toISO(d){ return d.getFullYear()+'-'+('0'+(d.getMonth()+1)).slice(-2)+'-'+('0'+d.getDate()).slice(-2); }
        function post(action, cb){
            var de=el('an-de').value, ate=el('an-ate').value;
            var body='action='+action+'&nonce='+encodeURIComponent(nonce)+'&workspace_id='+encodeURIComponent(wsId)+'&de='+encodeURIComponent(de)+'&ate='+encodeURIComponent(ate);
            fetch(ajaxurl,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:body,credentials:'same-origin'})
            .then(function(r){return r.text();}).then(function(t){ var i=t.indexOf('{'),j; try{ j=JSON.parse(i>0?t.slice(i):t); }catch(e){ cb(null); return; } cb(j&&j.success?j.data:null); })
            .catch(function(){ cb(null); });
        }
        function carregar(){
            el('an-status').textContent='carregando…'; setMsg('Buscando dados do período…');
            var done=0; function step(){ done++; if(done===3){ setMsg('');
                el('an-status').textContent=(fatData.vendas||[]).length+' vendas · '+finData.length+' linhas de consumo · '+opData.length+' cards';
                renderSimples();
                if(wdr) wdr.updateData({data:[META].concat(finData)});
            } }
            post('tao_crm_analise_dataset',    function(d){ finData=(d&&d.rows)||[]; step(); });
            post('tao_crm_operacao_dataset',   function(d){ opData=(d&&d.rows)||[]; step(); });
            post('tao_crm_financeiro_dataset', function(d){ fatData={vendas:(d&&d.vendas)||[],pagamentos:(d&&d.pagamentos)||[]}; step(); });
        }

        function wireUI(){
            el('an-hoje').onclick=function(){ var h=toISO(new Date()); el('an-de').value=h; el('an-ate').value=h; carregar(); };
            el('an-mes').onclick=function(){ var n=new Date(); el('an-de').value=toISO(new Date(n.getFullYear(),n.getMonth(),1)); el('an-ate').value=toISO(n); carregar(); };
            Array.prototype.forEach.call(document.querySelectorAll('[data-preset]'), function(b){ b.onclick=function(){ var n=parseInt(b.dataset.preset,10),a=new Date(),d=new Date(); d.setDate(d.getDate()-(n-1)); el('an-de').value=toISO(d); el('an-ate').value=toISO(a); carregar(); }; });
            el('an-aplicar').onclick=carregar;
            el('mode-s').onclick=function(){ setMode('s'); }; el('mode-a').onclick=function(){ setMode('a'); };
            gallery('fin-gallery', FINQ, curFin, function(k){ curFin=k; showSpec('fin', specFin(k)); });
            gallery('op-gallery',  OPQ,  curOp,  function(k){ curOp=k;  showSpec('op',  specOp(k)); });
            el('fin-type').onchange=function(){ drawSpec('fin'); };
            el('op-type').onchange=function(){ drawSpec('op'); };
            el('piv-type').onchange=function(){ drawSpec('piv'); };
            el('an-fields-toggle').onclick=function(){ if(!wdr)return; try{ if(fieldsOpen) wdr.closeFieldsList(); else wdr.openFieldsList(); }catch(e){} };
            el('an-chart-toggle').onclick=function(){ pivChartOn=!pivChartOn; el('piv-chart-wrap').style.display=pivChartOn?'block':'none'; el('an-chart-toggle').className='button'+(pivChartOn?' button-primary':''); if(pivChartOn) pivRefresh(); };
        }

        setMsg('Carregando biblioteca de gráficos…');
        js(CHARTJS, function(){ js(DATALAB, function(){ try{ Chart.register(ChartDataLabels); }catch(e){} wireUI(); carregar(); }); });
    })();
    </script>
    <?php
}
