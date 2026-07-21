<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Hub de Análise — tabela dinâmica (pivot) sobre um dataset achatado de OMs que
 * cruza CRM (card/funil/fase/responsável) + Fórmula (orçamento/forma/status/valor)
 * + Caixa (venda→recibo→pagamento→forma de pagamento). Read-only, restrito a gestão.
 * Os dashboards atuais continuam existindo — este é adicional.
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
        <p style="margin:0 0 12px;color:#64748b;font-size:13px">Cruza manipulações (OM) + funil + Caixa numa base só. Escolha um período, clique numa <b>visão pronta</b> para começar, e use a tabela para ir do resumo ao detalhe.</p>

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

        <!-- Visões prontas -->
        <div style="margin-top:10px;display:flex;gap:6px;align-items:center;flex-wrap:wrap">
            <strong style="font-size:12px;color:#475569">Visões prontas:</strong>
            <button type="button" class="button button-small an-vis" data-vis="pagamento">&#x1F4B3; Faturamento por forma de pagamento</button>
            <button type="button" class="button button-small an-vis" data-vis="forma">&#x1F48A; OMs por forma farmacêutica</button>
            <button type="button" class="button button-small an-vis" data-vis="responsavel">&#x1F464; Valor por responsável</button>
            <button type="button" class="button button-small an-vis" data-vis="dia">&#x1F4C5; OMs por dia</button>
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
        // CDN jsDelivr — o MESMO usado pelos outros painéis (Chart.js) neste portal
        var CSS = [ 'https://cdn.jsdelivr.net/npm/jquery-ui@1.13.2/themes/base/jquery-ui.min.css',
                    'https://cdn.jsdelivr.net/npm/pivottable@2.23.0/dist/pivot.min.css' ];
        var JQ   = 'https://cdn.jsdelivr.net/npm/jquery@3.6.4/dist/jquery.min.js';
        var JQUI = 'https://cdn.jsdelivr.net/npm/jquery-ui@1.13.2/dist/jquery-ui.min.js';
        var PIV  = 'https://cdn.jsdelivr.net/npm/pivottable@2.23.0/dist/pivot.min.js';
        var msg = document.getElementById('an-msg');

        function setMsg(t, cor){ msg.textContent = t; msg.style.color = cor || '#64748b'; }
        function css(h){ var l=document.createElement('link'); l.rel='stylesheet'; l.href=h; document.head.appendChild(l); }
        function js(src, cb){ var s=document.createElement('script'); s.src=src;
            s.onload=cb; s.onerror=function(){ setMsg('⚠ Não consegui carregar a biblioteca de tabela dinâmica (' + src + '). Pode ser bloqueio de rede/CDN — me avise que eu instalo uma cópia local.', '#dc2626'); };
            document.head.appendChild(s); }

        setMsg('Carregando biblioteca…');
        CSS.forEach(css);
        function step3(){ js(PIV, iniciar); }
        function step2(){ js(JQUI, step3); }
        if (window.jQuery) { step2(); } else { js(JQ, step2); }

        var DATA = [], curVis = 'pagamento';

        function iniciar(){
            var $ = window.jQuery;
            if (!$ || !$.fn || !$.fn.pivotUI) { setMsg('⚠ A biblioteca de pivot não inicializou. Me avise que eu troco a abordagem.', '#dc2626'); return; }

            function toISO(d){ return d.getFullYear()+'-'+('0'+(d.getMonth()+1)).slice(-2)+'-'+('0'+d.getDate()).slice(-2); }
            function preset(dias){ var a=new Date(), de=new Date(); de.setDate(de.getDate()-(dias-1));
                document.getElementById('an-de').value=toISO(de); document.getElementById('an-ate').value=toISO(a); }
            document.getElementById('an-mes').onclick=function(){ var n=new Date();
                document.getElementById('an-de').value=toISO(new Date(n.getFullYear(),n.getMonth(),1));
                document.getElementById('an-ate').value=toISO(n); carregar(); };
            Array.prototype.forEach.call(document.querySelectorAll('[data-preset]'), function(b){
                b.onclick=function(){ preset(parseInt(b.dataset.preset,10)); carregar(); }; });
            document.getElementById('an-aplicar').onclick=carregar;
            Array.prototype.forEach.call(document.querySelectorAll('.an-vis'), function(b){
                b.onclick=function(){ curVis=b.dataset.vis; render(); }; });

            function aggs(){
                var u=$.pivotUtilities, f=u.numberFormat({ thousandsSep:'.', decimalSep:',', prefix:'R$ ' });
                // cada valor é o GERADOR do agregador (recebe [coluna] via `vals`) — não chamar aqui
                return {
                    'Contagem de OMs' : u.aggregatorTemplates.count(),
                    'Soma (R$)'       : u.aggregatorTemplates.sum(f),
                    'Média (R$)'      : u.aggregatorTemplates.average(f)
                };
            }
            var VIS = {
                pagamento:   { rows:['Forma Pagto'],   cols:['Mes'],    agg:'Soma (R$)',        vals:['Valor Pago'] },
                forma:       { rows:['Forma Farmac.'], cols:['Status'], agg:'Contagem de OMs',  vals:[] },
                responsavel: { rows:['Responsavel'],   cols:['Mes'],    agg:'Soma (R$)',        vals:['Valor Orcado'] },
                dia:         { rows:['Data'],          cols:[],         agg:'Contagem de OMs',  vals:[] }
            };

            function render(){
                if (!DATA.length) { document.getElementById('an-pivot').innerHTML=''; setMsg('Nenhuma OM no período selecionado.', '#b45309'); return; }
                var v = VIS[curVis] || VIS.pagamento;
                $('#an-pivot').pivotUI(DATA, {
                    rows:v.rows, cols:v.cols, vals:v.vals,
                    aggregators:aggs(), aggregatorName:v.agg,
                    renderers:$.pivotUtilities.renderers, rendererName:'Table', unusedAttrsVertical:false
                }, true);
            }
            window.__anRender = render;

            function carregar(){
                var de=document.getElementById('an-de').value, ate=document.getElementById('an-ate').value;
                document.getElementById('an-status').textContent='carregando…';
                setMsg('Buscando dados do período…');
                $.ajax({ url:ajaxurl, type:'POST', dataType:'text',
                    data:{ action:'tao_crm_analise_dataset', nonce:nonce, workspace_id:wsId, de:de, ate:ate } })
                .done(function(txt){
                    var i=txt.indexOf('{'), resp; try{ resp=JSON.parse(i>0?txt.slice(i):txt); }
                    catch(e){ setMsg('⚠ Erro ao ler os dados (resposta inesperada). Me mande o que aparece no Console (F12).', '#dc2626'); return; }
                    if(!resp.success){ setMsg('⚠ ' + (resp.data||'Erro ao buscar dados'), '#dc2626'); return; }
                    DATA = (resp.data && resp.data.rows) || [];
                    document.getElementById('an-status').textContent = DATA.length + ' OMs no período';
                    setMsg('');
                    render();
                })
                .fail(function(x){ setMsg('⚠ Falha na requisição (HTTP ' + x.status + '). Me avise.', '#dc2626'); });
            }
            carregar();
        }
    })();
    </script>
    <?php
}
