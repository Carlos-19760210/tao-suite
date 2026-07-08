<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Livro de Receituário (Pacote 3 / Fatia C) — RDC 67 + Lei 5.991 art. 42.
 * Relatório sequencial das Ordens de Manipulação por período (imprimível).
 */
function tao_formula_page_livro_receituario() {
    if ( ! tao_formula_can_access() ) { echo '<p>Acesso negado.</p>'; return; }
    ?>
    <div class="wrap taof-wrap">
    <h1>📖 Livro de Receituário <small style="font-size:12px;color:#94a3b8;font-weight:400">(registro sequencial das manipulações — RDC 67)</small></h1>

    <div style="margin:14px 0;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        De <input type="date" id="taof-lv-de"> até <input type="date" id="taof-lv-ate">
        <button type="button" class="button button-primary" id="taof-lv-buscar">Gerar</button>
        <button type="button" class="button" id="taof-lv-imprimir">🖨 Imprimir</button>
        <span id="taof-lv-count" style="font-size:12px;color:#64748b"></span>
    </div>

    <div id="taof-lv-lista"><p style="color:#94a3b8">Escolha o período e clique em Gerar.</p></div>

    <style>
    .taof-lv-tb{width:100%;border-collapse:collapse;background:#fff;border:1px solid #e2e8f0;font-size:12px}
    .taof-lv-tb th,.taof-lv-tb td{padding:6px 9px;border:1px solid #e2e8f0;text-align:left}
    .taof-lv-tb th{background:#f8fafc;font-size:11px;text-transform:uppercase;color:#64748b}
    .taof-lv-twrap{overflow-x:auto;min-width:0}
    @media print{.taof-hide-print,#adminmenumain,#wpadminbar,.taof-wrap h1 small{display:none!important}}
    </style>

    <script>
    jQuery(function($){
        var ajaxUrl=taoFormula.ajaxUrl, nonce=taoFormula.nonce;
        function esc(t){return $('<span>').text(t==null?'':t).html();}
        function fdata(d){if(!d)return '—';var s=String(d).substring(0,10).split('-');return s.length===3?s[2]+'/'+s[1]+'/'+s[0]:d;}
        // default: mês corrente
        var hoje=new Date(), y=hoje.getFullYear(), m=('0'+(hoje.getMonth()+1)).slice(-2);
        $('#taof-lv-de').val(y+'-'+m+'-01'); $('#taof-lv-ate').val(y+'-'+m+'-'+('0'+hoje.getDate()).slice(-2));

        function gerar(){
            $.getJSON(ajaxUrl,{action:'tao_formula_prod_livro',nonce:nonce,de:$('#taof-lv-de').val(),ate:$('#taof-lv-ate').val()},function(r){
                if(!r.success){$('#taof-lv-lista').html('<p style="color:#dc2626">Erro</p>');return;}
                var l=r.data.ordens||[];
                $('#taof-lv-count').text(l.length+' registro(s)');
                if(!l.length){$('#taof-lv-lista').html('<p style="color:#94a3b8">Nenhuma OM no período.</p>');return;}
                var rows=l.map(function(o){
                    var vol=(o.volume?parseFloat(o.volume):'')+(o.unidade_vol?' '+o.unidade_vol:'');
                    return '<tr>'+
                        '<td>'+o.numero+'</td>'+
                        '<td>'+fdata(o.criado_em)+'</td>'+
                        '<td>'+esc(o.paciente_nome)+'</td>'+
                        '<td>'+esc(o.prescritor||'—')+'</td>'+
                        '<td>'+esc(o.forma_farmac||'')+' '+esc(vol)+'</td>'+
                        '<td>'+fdata(o.dt_validade)+'</td>'+
                        '<td style="text-align:center">'+(o.controlado?'C':'')+'</td>'+
                        '<td>'+esc(o.status)+'</td></tr>';
                }).join('');
                $('#taof-lv-lista').html(
                    '<h3 style="margin:6px 0">Período '+fdata(r.data.de)+' a '+fdata(r.data.ate)+'</h3>'+
                    '<div class="taof-lv-twrap"><table class="taof-lv-tb"><tr><th>Nº OM</th><th>Data</th><th>Paciente</th><th>Prescritor</th><th>Fórmula</th><th>Validade</th><th>Ctrl</th><th>Situação</th></tr>'+rows+'</table></div>');
            });
        }
        $('#taof-lv-buscar').on('click',gerar);
        $('#taof-lv-imprimir').on('click',function(){window.print();});
        gerar();
    });
    </script>
    </div>
    <?php
}
