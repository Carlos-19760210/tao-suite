<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Hub de Análise — tabela dinâmica (pivot) sobre datasets achatados que cruzam
 * CRM + Fórmula + Caixa. Duas bases: OM/financeiro e Consumo de ativo.
 * Read-only, restrito a gestão. Os dashboards atuais continuam existindo.
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
    ?>
    <div class="wrap tao-analise">
        <h1 style="margin-bottom:2px">&#x1F4CA; Análise</h1>
        <p style="margin:0 0 10px;color:#64748b;font-size:13px">Cruza manipulações (OM) + funil + Caixa numa base só. Escolha a base e o período, clique numa <b>visão pronta</b>, e use a tabela para ir do resumo ao detalhe.</p>

        <!-- Base -->
        <div style="display:flex;gap:6px;align-items:center;margin-bottom:8px">
            <strong style="font-size:12px;color:#475569">Base:</strong>
            <button type="button" class="button an-base" data-base="om">&#x1F4B0; Financeiro (por OM)</button>
            <button type="button" class="button an-base" data-base="ativo">&#x1F48A; Consumo de ativo</button>
        </div>

        <!-- Período -->
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:10px 12px">
            <strong style="font-size:13px;color:#475569">Período:</strong>
            <input type="date" id="an-de"  value="<?php echo esc_attr( $mes_ini ); ?>" style="padding:5px 8px;border:1px solid #cbd5e1;border-radius:5px">
            <span style="color:#94a3b8">até</span>
            <input type="date" id="an-ate" value="<?php echo esc_attr( $hoje ); ?>" style="padding:5px 8px;border:1px solid #cbd5e1;border-radius:5px">
            <button type="button" class="button" id="an-mes">Mês corrente</button>
            <button type="button" class="button" data-preset="7">7 dias</button>
            <button type="button" class="button" data-preset="30">30 dias</button>
            <button type="button" class="button" data-preset="90">90 dias</button>
            <button type="button" class="button button-primary" id="an-aplicar">Aplicar período</button>
            <span id="an-status" style="font-size:12px;color:#64748b;margin-left:4px"></span>
        </div>

        <!-- Visões prontas (dinâmicas por base) -->
        <div style="margin-top:10px;display:flex;gap:6px;align-items:center;flex-wrap:wrap">
            <strong style="font-size:12px;color:#475569">Visões prontas:</strong>
            <span id="an-vis-btns"></span>
            <span style="font-size:11px;color:#94a3b8">— ou arraste os campos na tabela para montar sua própria visão</span>
        </div>

        <div id="an-msg" style="margin-top:12px;color:#64748b"></div>
        <div id="an-pivot" style="margin-top:10px;overflow-x:auto"></div>
    </div>

    <script>
    (function () {
        var ajaxurl = <?php echo wp_json_encode( $ajaxurl ); ?>;
        var nonce   = <?php echo wp_json_encode( $nonce ); ?>;
        var wsId    = <?php echo wp_json_encode( $ws_id ); ?>;
        var CSS = [ 'https://cdn.jsdelivr.net/npm/jquery-ui@1.13.2/themes/base/jquery-ui.min.css',
                    'https://cdn.jsdelivr.net/npm/pivottable@2.23.0/dist/pivot.min.css' ];
        var JQ='https://cdn.jsdelivr.net/npm/jquery@3.6.4/dist/jquery.min.js',
            JQUI='https://cdn.jsdelivr.net/npm/jquery-ui@1.13.2/dist/jquery-ui.min.js',
            PIV='https://cdn.jsdelivr.net/npm/pivottable@2.23.0/dist/pivot.min.js';
        var msg=document.getElementById('an-msg');
        function setMsg(t,c){ msg.textContent=t; msg.style.color=c||'#64748b'; }
        function css(h){ var l=document.createElement('link'); l.rel='stylesheet'; l.href=h; document.head.appendChild(l); }
        function js(src,cb){ var s=document.createElement('script'); s.src=src; s.onload=cb;
            s.onerror=function(){ setMsg('⚠ Não consegui carregar a biblioteca de tabela dinâmica ('+src+'). Me avise.', '#dc2626'); };
            document.head.appendChild(s); }

        setMsg('Carregando biblioteca…');
        CSS.forEach(css);
        function s3(){ js(PIV, iniciar); } function s2(){ js(JQUI, s3); }
        if (window.jQuery) s2(); else js(JQ, s2);

        var DATA=[], curVis='', curBase='om';

        // visões por base: {rows, cols, agg, vals, label}
        var VIS_OM = {
            pagamento:   { rows:['Forma Pagto'],   cols:['Mes'],    agg:'Soma (R$)', vals:['Valor Pago'],   label:'💳 Faturamento por forma de pagamento' },
            margem:      { rows:['Forma Farmac.'], cols:[],         agg:'Soma (R$)', vals:['Margem (R$)'],  label:'📈 Margem por forma farmacêutica' },
            responsavel: { rows:['Responsavel'],   cols:['Mes'],    agg:'Soma (R$)', vals:['Valor Orcado'], label:'👤 Valor por responsável' },
            forma:       { rows:['Forma Farmac.'], cols:['Status'], agg:'Contagem',  vals:[],               label:'💊 OMs por forma farmacêutica' },
            dia:         { rows:['Data'],          cols:[],         agg:'Contagem',  vals:[],               label:'📅 OMs por dia' }
        };
        var VIS_ATIVO = {
            consumo:  { rows:['Ativo'], cols:['Mes'], agg:'Soma (qtd)', vals:['Qtd (g)'],    label:'⚖️ Consumo (g) por ativo' },
            custo:    { rows:['Ativo'], cols:[],      agg:'Soma (R$)',  vals:['Custo (R$)'], label:'💰 Custo por ativo' },
            ativo_om: { rows:['Ativo'], cols:[],      agg:'Contagem',   vals:[],             label:'🔢 Nº de OMs por ativo' }
        };

        function iniciar(){
            var $=window.jQuery;
            if(!$||!$.fn||!$.fn.pivotUI){ setMsg('⚠ A biblioteca de pivot não inicializou. Me avise.', '#dc2626'); return; }
            function toISO(d){ return d.getFullYear()+'-'+('0'+(d.getMonth()+1)).slice(-2)+'-'+('0'+d.getDate()).slice(-2); }
            function preset(dias){ var a=new Date(),de=new Date(); de.setDate(de.getDate()-(dias-1));
                document.getElementById('an-de').value=toISO(de); document.getElementById('an-ate').value=toISO(a); }
            document.getElementById('an-mes').onclick=function(){ var n=new Date();
                document.getElementById('an-de').value=toISO(new Date(n.getFullYear(),n.getMonth(),1));
                document.getElementById('an-ate').value=toISO(n); carregar(); };
            Array.prototype.forEach.call(document.querySelectorAll('[data-preset]'), function(b){ b.onclick=function(){ preset(parseInt(b.dataset.preset,10)); carregar(); }; });
            document.getElementById('an-aplicar').onclick=carregar;
            Array.prototype.forEach.call(document.querySelectorAll('.an-base'), function(b){ b.onclick=function(){ setBase(b.dataset.base); }; });

            function setBase(b){
                curBase=b;
                Array.prototype.forEach.call(document.querySelectorAll('.an-base'), function(x){ x.className='button an-base'+(x.dataset.base===b?' button-primary':''); });
                renderVisBtns();
                carregar();
            }
            function renderVisBtns(){
                var vis = curBase==='ativo' ? VIS_ATIVO : VIS_OM;
                var host=document.getElementById('an-vis-btns'); host.innerHTML='';
                Object.keys(vis).forEach(function(k){
                    var b=document.createElement('button'); b.type='button'; b.className='button button-small'; b.style.marginRight='4px';
                    b.textContent=vis[k].label; b.onclick=function(){ curVis=k; render(); };
                    host.appendChild(b);
                });
                curVis = Object.keys(vis)[0];
            }
            function aggs(){
                var u=$.pivotUtilities, f=u.numberFormat({thousandsSep:'.',decimalSep:',',prefix:'R$ '}),
                    n=u.numberFormat({thousandsSep:'.',decimalSep:','});
                return {
                    'Contagem'   : u.aggregatorTemplates.count(),
                    'Soma (R$)'  : u.aggregatorTemplates.sum(f),
                    'Soma (qtd)' : u.aggregatorTemplates.sum(n),
                    'Média (R$)' : u.aggregatorTemplates.average(f)
                };
            }
            function render(){
                if(!DATA.length){ document.getElementById('an-pivot').innerHTML=''; setMsg('Nenhum registro no período selecionado.', '#b45309'); return; }
                setMsg('');
                var vis = curBase==='ativo' ? VIS_ATIVO : VIS_OM;
                var v = vis[curVis] || vis[Object.keys(vis)[0]];
                $('#an-pivot').pivotUI(DATA, {
                    rows:v.rows, cols:v.cols, vals:v.vals, aggregators:aggs(), aggregatorName:v.agg,
                    renderers:$.pivotUtilities.renderers, rendererName:'Table', unusedAttrsVertical:false
                }, true);
            }
            window.__anRender=render;

            function carregar(){
                var de=document.getElementById('an-de').value, ate=document.getElementById('an-ate').value;
                document.getElementById('an-status').textContent='carregando…'; setMsg('Buscando dados do período…');
                $.ajax({ url:ajaxurl, type:'POST', dataType:'text',
                    data:{ action:'tao_crm_analise_dataset', nonce:nonce, workspace_id:wsId, de:de, ate:ate, base:curBase } })
                .done(function(txt){
                    var i=txt.indexOf('{'),resp; try{ resp=JSON.parse(i>0?txt.slice(i):txt); }
                    catch(e){ setMsg('⚠ Erro ao ler os dados. Me mande o Console (F12).', '#dc2626'); return; }
                    if(!resp.success){ setMsg('⚠ '+(resp.data||'Erro'), '#dc2626'); return; }
                    DATA=(resp.data&&resp.data.rows)||[];
                    document.getElementById('an-status').textContent = DATA.length + (curBase==='ativo'?' linhas de ativo':' OMs') + ' no período';
                    render();
                })
                .fail(function(x){ setMsg('⚠ Falha na requisição (HTTP '+x.status+').', '#dc2626'); });
            }

            setBase('om');   // base inicial + primeira carga
        }
    })();
    </script>
    <?php
}
