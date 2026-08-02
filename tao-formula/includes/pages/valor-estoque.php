<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Estoque — Valor do Estoque. Visão simples de valorização por produto:
 * saldo (lotes aprovados) × custo/venda. Custo e Venda na unidade padrão (mesma do saldo);
 * Compra unit é exibida como referência (é por unidade de compra).
 */
function tao_formula_page_valor_estoque() {
    if ( ! tao_formula_can_access() ) { echo '<p>Acesso negado.</p>'; return; }
    ?>
    <div class="wrap taof-wrap">
    <h1>💰 Valor do Estoque <small style="font-size:12px;color:#94a3b8;font-weight:400">(saldo dos lotes aprovados × custo / venda)</small></h1>

    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:end;margin:12px 0">
        <div><label style="font-size:11px;color:#64748b;display:block">Buscar produto</label><input id="ve-busca" style="width:240px;padding:5px"></div>
        <div><label style="font-size:11px;color:#64748b;display:block">Grupo</label>
            <select id="ve-grupo" style="padding:5px"><option value="">Todos</option><option value="M">Matéria-prima</option><option value="E">Embalagem</option></select></div>
        <button type="button" class="button" id="ve-buscar">Filtrar</button>
        <span id="ve-msg" style="font-size:12px;color:#64748b"></span>
    </div>

    <div id="ve-tot" style="display:flex;gap:18px;flex-wrap:wrap;margin-bottom:10px"></div>
    <div style="overflow-x:auto"><table class="widefat" id="ve-tab" style="min-width:820px">
        <thead><tr>
            <th>Item</th>
            <th style="text-align:right">Qtde estoque</th>
            <th>Un</th>
            <th style="text-align:right">Custo unit</th>
            <th style="text-align:right">Compra unit</th>
            <th style="text-align:right">Custo total</th>
            <th style="text-align:right">Venda unit</th>
            <th style="text-align:right">Venda total</th>
        </tr></thead>
        <tbody><tr><td colspan="8">carregando…</td></tr></tbody>
    </table></div>
    </div>

    <script>
    jQuery(function($){
        var ajaxUrl=taoFormula.ajaxUrl, nonce=taoFormula.nonce;
        function esc(s){ return String(s==null?'':s).replace(/[&<>]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;'}[c];}); }
        function m(v,d){ d=(d==null?2:d); return parseFloat(v||0).toLocaleString('pt-BR',{minimumFractionDigits:d,maximumFractionDigits:d}); }
        function R(v){ return 'R$ '+m(v,2); }
        function card(lbl,val,cor){ return '<div style="border:1px solid #e2e8f0;border-radius:8px;padding:8px 14px;background:#f8fafc"><div style="font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.4px">'+lbl+'</div><div style="font-size:20px;font-weight:700;color:'+(cor||'#0f172a')+'">'+val+'</div></div>'; }

        function carregar(){
            $('#ve-msg').text('carregando…');
            $('#ve-tab tbody').html('<tr><td colspan="8">carregando…</td></tr>');
            $.post(ajaxUrl,{action:'tao_formula_valor_estoque',nonce:nonce,busca:$('#ve-busca').val(),grupo:$('#ve-grupo').val()},function(r){
                if(!r||!r.success){ $('#ve-msg').text('Erro.'); return; }
                var it=r.data.itens||[];
                $('#ve-tot').html(
                    card('Itens em estoque', m(r.data.tot_itens,0)) +
                    card('Valor de custo total', R(r.data.tot_custo), '#b45309') +
                    card('Valor de venda total', R(r.data.tot_venda), '#16a34a') +
                    card('Margem potencial', R((r.data.tot_venda||0)-(r.data.tot_custo||0)), '#2563eb')
                );
                if(!it.length){ $('#ve-tab tbody').html('<tr><td colspan="8" style="color:#64748b">Nenhum item com saldo.</td></tr>'); $('#ve-msg').text(''); return; }
                var h='';
                it.forEach(function(x){
                    h+='<tr><td><b>'+esc(x.nome)+'</b></td>'+
                       '<td style="text-align:right">'+m(x.qtd,3)+'</td>'+
                       '<td>'+esc(x.un)+'</td>'+
                       '<td style="text-align:right">'+R(x.custo_un)+'</td>'+
                       '<td style="text-align:right;color:#94a3b8">'+R(x.compra_un)+'</td>'+
                       '<td style="text-align:right;color:#b45309;font-weight:600">'+R(x.custo_tot)+'</td>'+
                       '<td style="text-align:right">'+R(x.venda_un)+'</td>'+
                       '<td style="text-align:right;color:#16a34a;font-weight:600">'+R(x.venda_tot)+'</td></tr>';
                });
                $('#ve-tab tbody').html(h);
                $('#ve-msg').text(it.length+' item(ns)');
            });
        }
        $('#ve-buscar').on('click',carregar);
        $('#ve-busca').on('keydown',function(e){ if(e.which===13) carregar(); });
        $('#ve-grupo').on('change',carregar);
        carregar();
    });
    </script>
    <?php
}
