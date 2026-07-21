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
        <h1 style="margin-bottom:4px">&#x1F4CA; Análise
            <small style="font-size:13px;color:#94a3b8;font-weight:400">— tabela dinâmica (OM &times; funil &times; Caixa)</small>
        </h1>
        <p style="margin:0 0 12px;color:#64748b;font-size:13px">Arraste as dimensões (linhas/colunas) e escolha a medida. Expanda para ir do macro ao micro (ex.: telefone &rarr; manipulação &rarr; forma de pagamento).</p>

        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:10px 12px">
            <strong style="font-size:13px;color:#475569">Período:</strong>
            <input type="date" id="an-de"  value="<?php echo esc_attr( $mes_ini ); ?>" style="padding:5px 8px;border:1px solid #cbd5e1;border-radius:5px">
            <span style="color:#94a3b8">até</span>
            <input type="date" id="an-ate" value="<?php echo esc_attr( $hoje ); ?>" style="padding:5px 8px;border:1px solid #cbd5e1;border-radius:5px">
            <button type="button" class="button" id="an-mes">Mês corrente</button>
            <button type="button" class="button" data-preset="7">7 dias</button>
            <button type="button" class="button" data-preset="30">30 dias</button>
            <button type="button" class="button" data-preset="90">90 dias</button>
            <button type="button" class="button button-primary" id="an-aplicar">Aplicar</button>
            <span id="an-status" style="font-size:12px;color:#64748b;margin-left:4px"></span>
        </div>

        <div id="an-pivot" style="margin-top:14px;overflow-x:auto">Carregando…</div>
    </div>

    <script>
    (function () {
        var ajaxurl = <?php echo wp_json_encode( $ajaxurl ); ?>;
        var nonce   = <?php echo wp_json_encode( $nonce ); ?>;
        var wsId    = <?php echo wp_json_encode( $ws_id ); ?>;
        var JQUI_CSS = 'https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.13.2/themes/base/jquery-ui.min.css';
        var PIV_CSS  = 'https://cdnjs.cloudflare.com/ajax/libs/pivottable/2.23.0/pivot.min.css';
        var JQ_JS    = 'https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.4/jquery.min.js';
        var JQUI_JS  = 'https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.13.2/jquery-ui.min.js';
        var PIV_JS   = 'https://cdnjs.cloudflare.com/ajax/libs/pivottable/2.23.0/pivot.min.js';

        function css(href){ var l=document.createElement('link'); l.rel='stylesheet'; l.href=href; document.head.appendChild(l); }
        function js(src, cb){ var s=document.createElement('script'); s.src=src; s.onload=cb; s.onerror=function(){ document.getElementById('an-pivot').textContent='Falha ao carregar a biblioteca de tabela dinâmica.'; }; document.head.appendChild(s); }

        css(JQUI_CSS); css(PIV_CSS);
        function afterJqUi(){ js(PIV_JS, iniciar); }
        function afterJq(){ js(JQUI_JS, afterJqUi); }
        if (window.jQuery) { afterJq(); } else { js(JQ_JS, afterJq); }

        function iniciar(){
            var $ = window.jQuery;
            function toISO(d){ return d.getFullYear()+'-'+('0'+(d.getMonth()+1)).slice(-2)+'-'+('0'+d.getDate()).slice(-2); }
            function preset(dias){ var a=new Date(), de=new Date(); de.setDate(de.getDate()-(dias-1));
                document.getElementById('an-de').value=toISO(de); document.getElementById('an-ate').value=toISO(a); }

            document.getElementById('an-mes').onclick = function(){ var n=new Date();
                document.getElementById('an-de').value=toISO(new Date(n.getFullYear(),n.getMonth(),1));
                document.getElementById('an-ate').value=toISO(n); carregar(); };
            Array.prototype.forEach.call(document.querySelectorAll('[data-preset]'), function(b){
                b.onclick=function(){ preset(parseInt(b.dataset.preset,10)); carregar(); }; });
            document.getElementById('an-aplicar').onclick = carregar;

            function carregar(){
                var de=document.getElementById('an-de').value, ate=document.getElementById('an-ate').value;
                var st=document.getElementById('an-status'); st.textContent='carregando…';
                $.ajax({ url:ajaxurl, type:'POST', dataType:'text',
                    data:{ action:'tao_crm_analise_dataset', nonce:nonce, workspace_id:wsId, de:de, ate:ate } })
                .done(function(txt){
                    var i=txt.indexOf('{'), resp; try{ resp=JSON.parse(i>0?txt.slice(i):txt); }
                    catch(e){ st.textContent='erro ao ler dados'; return; }
                    if(!resp.success){ st.textContent=(resp.data||'erro'); return; }
                    var rows=(resp.data && resp.data.rows) || [];
                    st.textContent = rows.length + ' OMs no período';
                    var util=$.pivotUtilities, fmt=util.numberFormat({ thousandsSep:'.', decimalSep:',', prefix:'R$ ' });
                    var aggs={
                        'Contagem de OMs'      : util.aggregatorTemplates.count()(),
                        'Soma Valor Orçado'    : util.aggregatorTemplates.sum(fmt)(['Valor Orcado']),
                        'Soma Valor Pago'      : util.aggregatorTemplates.sum(fmt)(['Valor Pago']),
                        'Ticket Médio (Orçado)': util.aggregatorTemplates.average(fmt)(['Valor Orcado'])
                    };
                    $('#an-pivot').pivotUI(rows, {
                        rows:['Forma Farmac.'], cols:['Mes'],
                        aggregators: aggs, aggregatorName:'Soma Valor Orçado',
                        renderers: util.renderers, rendererName:'Table',
                        unusedAttrsVertical:false
                    }, true);
                })
                .fail(function(x){ st.textContent='erro HTTP '+x.status; });
            }
            carregar();
        }
    })();
    </script>
    <?php
}
