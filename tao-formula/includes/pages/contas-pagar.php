<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/** Financeiro — Contas a Pagar (duplicatas das NFs). Relatório imprimível p/ o contador. */
function tao_formula_page_contas_pagar() {
    if ( ! tao_formula_can_access() ) { echo '<p>Acesso negado.</p>'; return; }
    ?>
    <div class="wrap taof-wrap">
    <h1>💳 Contas a Pagar <small style="font-size:12px;color:#94a3b8;font-weight:400">(duplicatas das notas de compra)</small></h1>

    <div style="margin:14px 0;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <select id="taof-cp-status" style="padding:6px 10px">
            <option value="aberto">Em aberto</option>
            <option value="pago">Pagas</option>
            <option value="">Todas</option>
        </select>
        Venc. de <input type="date" id="taof-cp-de"> até <input type="date" id="taof-cp-ate">
        <button type="button" class="button" id="taof-cp-buscar">Filtrar</button>
        <button type="button" class="button" id="taof-cp-print">🖨 Relatório (contador)</button>
        <span id="taof-cp-tot" style="font-size:13px;color:#0f172a;font-weight:600"></span>
    </div>

    <div id="taof-cp-lista"><p style="color:#94a3b8">Carregando…</p></div>
    <div style="display:flex;gap:10px;align-items:center;justify-content:center;margin:12px 0;font-size:13px;flex-wrap:wrap" class="taof-hide-print">
        <label>Itens por página:
            <select id="taof-cp-size" style="padding:3px 6px"><option value="20">20</option><option value="30" selected>30</option><option value="50">50</option></select>
        </label>
        <button type="button" class="button button-small" id="taof-cp-prev">‹ Anterior</button>
        <span id="taof-cp-pg" style="color:#64748b">—</span>
        <button type="button" class="button button-small" id="taof-cp-next">Próxima ›</button>
    </div>

    <style>
    .taof-cp-tb{width:100%;border-collapse:collapse;background:#fff;border:1px solid #e2e8f0;font-size:13px}
    .taof-cp-tb th,.taof-cp-tb td{padding:6px 10px;border-bottom:1px solid #f1f5f9;text-align:left}
    .taof-cp-tb th{background:#f8fafc;font-size:11px;text-transform:uppercase;color:#64748b}
    .taof-cp-late{color:#dc2626;font-weight:600}
    @media print{#adminmenumain,#wpadminbar,.taof-hide-print{display:none!important}}
    </style>

    <script>
    jQuery(function($){
        var ajaxUrl=taoFormula.ajaxUrl, nonce=taoFormula.nonce;
        var pg=0, sz=30, total=0;
        function esc(t){return $('<span>').text(t==null?'':t).html();}
        function money(n){return 'R$ '+parseFloat(n||0).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2});}
        function fdata(d){if(!d)return '—';var s=String(d).substring(0,10).split('-');return s.length===3?s[2]+'/'+s[1]+'/'+s[0]:d;}
        function hoje(){return new Date().toISOString().substring(0,10);}

        function pager(){
            var paginas=Math.max(1,Math.ceil(total/sz));
            $('#taof-cp-pg').text('Página '+(pg+1)+' de '+paginas);
            $('#taof-cp-prev').prop('disabled',pg<=0);
            $('#taof-cp-next').prop('disabled',pg>=paginas-1);
        }
        function carregar(reset){
            if(reset){pg=0;}
            $.getJSON(ajaxUrl,{action:'tao_formula_cp_lista',nonce:nonce,status:$('#taof-cp-status').val(),de:$('#taof-cp-de').val(),ate:$('#taof-cp-ate').val(),size:sz,offset:pg*sz},function(r){
                if(!r.success){$('#taof-cp-lista').html('<p style="color:#dc2626">Erro</p>');return;}
                var l=(r.data&&r.data.items)||[]; total=(r.data&&r.data.total)||0;
                $('#taof-cp-tot').text('Aberto: '+money(r.data.total_aberto)+'  ·  Pago: '+money(r.data.total_pago));
                if(!l.length){$('#taof-cp-lista').html('<p style="color:#94a3b8">Nenhuma conta no filtro.</p>');pager();return;}
                var rows=l.map(function(c){
                    var late=c.status==='aberto' && c.vencimento && c.vencimento<hoje();
                    var acao=c.status==='pago'
                        ? '<button class="button button-small taof-cp-acao taof-hide-print" data-id="'+c.id+'" data-a="reabrir">reabrir</button>'
                        : '<button class="button button-small button-primary taof-cp-acao taof-hide-print" data-id="'+c.id+'" data-a="pagar">✔ pagar</button>';
                    return '<tr'+(late?' class="taof-cp-late"':'')+'>'+
                        '<td>'+esc(c.fornecedor)+'</td>'+
                        '<td>'+esc(c.numero_dup||'—')+'</td>'+
                        '<td>'+fdata(c.vencimento)+(late?' ⚠':'')+'</td>'+
                        '<td style="text-align:right">'+money(c.valor)+'</td>'+
                        '<td>'+esc(c.status)+(c.dt_pagamento?' '+fdata(c.dt_pagamento):'')+'</td>'+
                        '<td class="taof-hide-print">'+acao+'</td></tr>';
                }).join('');
                $('#taof-cp-lista').html('<div style="overflow-x:auto"><table class="taof-cp-tb"><tr><th>Fornecedor</th><th>Dup.</th><th>Vencimento</th><th>Valor</th><th>Situação</th><th class="taof-hide-print"></th></tr>'+rows+'</table></div>');
                pager();
            });
        }
        $('#taof-cp-buscar,#taof-cp-status').on('click change',function(){carregar(true);});
        $('#taof-cp-print').on('click',function(){window.print();});
        $('#taof-cp-size').on('change',function(){sz=parseInt(this.value,10)||30;carregar(true);});
        $('#taof-cp-prev').on('click',function(){if(pg>0){pg--;carregar(false);}});
        $('#taof-cp-next').on('click',function(){pg++;carregar(false);});
        $(document).on('click','.taof-cp-acao',function(){
            $.post(ajaxUrl,{action:'tao_formula_cp_pagar',nonce:nonce,id:$(this).data('id'),acao:$(this).data('a')},function(r){
                if(r.success)carregar(false);else alert((r.data&&r.data.message)||'Erro');
            });
        });
        carregar(true);
    });
    </script>
    </div>
    <?php
}
