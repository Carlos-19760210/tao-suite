<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Estoque — Valor do Estoque. Valorização por produto: saldo (lotes aprovados) × custo/venda.
 * Custo e Venda na unidade padrão (mesma do saldo); Compra unit é referência (por unidade de compra).
 * Custo/Compra/Venda unit são EDITÁVEIS aqui (gravam no cadastro do ativo).
 */
function tao_formula_page_valor_estoque() {
    if ( ! tao_formula_can_access() ) { echo '<p>Acesso negado.</p>'; return; }
    ?>
    <style>
    .ve-wrap{max-width:1080px}
    .ve-cards{display:flex;gap:12px;flex-wrap:wrap;margin:14px 0}
    .ve-card{flex:1;min-width:150px;border:1px solid #e5e7eb;border-radius:10px;padding:10px 14px;background:#fff}
    .ve-card .l{font-size:11px;color:#6b7280;text-transform:uppercase;letter-spacing:.4px}
    .ve-card .v{font-size:21px;font-weight:700;margin-top:2px}
    table.ve-tab{width:100%;border-collapse:collapse;font-size:13px;background:#fff}
    table.ve-tab th{position:sticky;top:0;background:#f8fafc;border-bottom:2px solid #e5e7eb;padding:8px 10px;text-align:right;font-size:11px;text-transform:uppercase;letter-spacing:.3px;color:#64748b}
    table.ve-tab th.l,table.ve-tab td.l{text-align:left}
    table.ve-tab td{padding:6px 10px;border-bottom:1px solid #f1f5f9;text-align:right}
    table.ve-tab tr:hover td{background:#f9fafb}
    .ve-in{width:92px;text-align:right;border:1px solid transparent;border-radius:5px;padding:3px 6px;background:transparent;font-size:13px}
    .ve-in:hover{border-color:#e5e7eb;background:#fff}
    .ve-in:focus{border-color:#2563eb;background:#fff;outline:none}
    .ve-tot{font-weight:600}
    </style>
    <div class="wrap taof-wrap ve-wrap">
    <h1>💰 Valor do Estoque <small style="font-size:12px;color:#94a3b8;font-weight:400">(saldo dos lotes aprovados × custo / venda — edite os unitários direto na tabela)</small></h1>

    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:end;margin:10px 0">
        <div><label style="font-size:11px;color:#64748b;display:block;margin-bottom:2px">Buscar produto</label><input id="ve-busca" style="width:240px;padding:6px 8px;border:1px solid #d1d5db;border-radius:6px"></div>
        <div><label style="font-size:11px;color:#64748b;display:block;margin-bottom:2px">Grupo</label>
            <select id="ve-grupo" style="padding:6px 8px;border:1px solid #d1d5db;border-radius:6px"><option value="">Todos</option><option value="M">Matéria-prima</option><option value="E">Embalagem</option></select></div>
        <div><label style="font-size:11px;color:#64748b;display:block;margin-bottom:2px">Status do lote</label>
            <select id="ve-status" style="padding:6px 8px;border:1px solid #d1d5db;border-radius:6px"><option value="">Todos (estoque físico)</option><option value="aprovado">Só liberados</option><option value="quarentena">Em quarentena</option></select></div>
        <span id="ve-msg" style="font-size:12px;color:#64748b;margin-bottom:6px"></span>
    </div>

    <div id="ve-cards" class="ve-cards"></div>
    <div style="overflow:auto;max-height:66vh;border:1px solid #e5e7eb;border-radius:10px">
        <table class="ve-tab">
            <thead><tr>
                <th class="l">Item</th>
                <th>Qtde</th>
                <th class="l">Un</th>
                <th>Custo unit</th>
                <th>Compra unit</th>
                <th>Custo total</th>
                <th>Venda unit</th>
                <th>Venda total</th>
            </tr></thead>
            <tbody id="ve-body"><tr><td colspan="8" style="text-align:left">carregando…</td></tr></tbody>
        </table>
    </div>
    </div>

    <script>
    jQuery(function($){
        var ajaxUrl=taoFormula.ajaxUrl, nonce=taoFormula.nonce, ITENS=[];
        function esc(s){ return String(s==null?'':s).replace(/[&<>]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;'}[c];}); }
        function m(v,d){ d=(d==null?2:d); return parseFloat(v||0).toLocaleString('pt-BR',{minimumFractionDigits:d,maximumFractionDigits:d}); }
        function R(v){ return 'R$ '+m(v,2); }
        function num(s){ return parseFloat(String(s==null?'':s).replace(/\./g,'').replace(',','.'))||0; }

        // valor do estoque = preço de COMPRA (fallback custo) × saldo
        function vbase(x){ return (x.compra_un>0)?x.compra_un:(x.custo_un>0?x.custo_un:0); }
        function totais(){
            var tc=0,tv=0; ITENS.forEach(function(x){ tc+=x.qtd*vbase(x); tv+=x.qtd*x.venda_un; });
            $('#ve-cards').html(
                '<div class="ve-card"><div class="l">Itens em estoque</div><div class="v">'+m(ITENS.length,0)+'</div></div>'+
                '<div class="ve-card"><div class="l">Valor de custo total</div><div class="v" style="color:#b45309">'+R(tc)+'</div></div>'+
                '<div class="ve-card"><div class="l">Valor de venda total</div><div class="v" style="color:#16a34a">'+R(tv)+'</div></div>'+
                '<div class="ve-card"><div class="l">Margem potencial</div><div class="v" style="color:#2563eb">'+R(tv-tc)+'</div></div>'
            );
        }
        function inp(i,campo,val){ return '<input class="ve-in" data-i="'+i+'" data-campo="'+campo+'" value="'+m(val,4)+'">'; }
        function render(){
            if(!ITENS.length){ $('#ve-body').html('<tr><td colspan="8" style="text-align:left;color:#64748b">Nenhum item com saldo.</td></tr>'); totais(); return; }
            var h='';
            ITENS.forEach(function(x,i){
                h+='<tr data-i="'+i+'"><td class="l"><b>'+esc(x.nome)+'</b></td>'+
                   '<td>'+m(x.qtd,3)+'</td><td class="l">'+esc(x.un)+'</td>'+
                   '<td>'+inp(i,'custo_por_unidade',x.custo_un)+'</td>'+
                   '<td>'+inp(i,'preco_compra',x.compra_un)+'</td>'+
                   '<td class="ve-tot ve-ct" style="color:#b45309">'+R(x.qtd*vbase(x))+'</td>'+
                   '<td>'+inp(i,'preco_venda',x.venda_un)+'</td>'+
                   '<td class="ve-tot ve-vt" style="color:#16a34a">'+R(x.qtd*x.venda_un)+'</td></tr>';
            });
            $('#ve-body').html(h); totais();
        }
        function carregar(){
            $('#ve-msg').text('carregando…');
            $.post(ajaxUrl,{action:'tao_formula_valor_estoque',nonce:nonce,busca:$('#ve-busca').val(),grupo:$('#ve-grupo').val(),status:$('#ve-status').val()},function(r){
                if(!r||!r.success){ $('#ve-msg').text('Erro.'); return; }
                ITENS=r.data.itens||[]; render(); $('#ve-msg').text(ITENS.length+' item(ns)');
            });
        }
        // edição inline: ao sair do campo, grava no ativo e recalcula
        $('#ve-body').on('change','.ve-in',function(){
            var $in=$(this), i=+$in.data('i'), campo=$in.data('campo'), val=num($in.val());
            var x=ITENS[i]; if(!x) return;
            $in.val(m(val,4));
            if(campo==='custo_por_unidade') x.custo_un=val; else if(campo==='preco_compra') x.compra_un=val; else x.venda_un=val;
            var $tr=$in.closest('tr');
            $tr.find('.ve-ct').text(R(x.qtd*vbase(x))); $tr.find('.ve-vt').text(R(x.qtd*x.venda_un)); totais();
            $in.css('background','#fefce8');
            $.post(ajaxUrl,{action:'tao_formula_valor_estoque_salvar',nonce:nonce,id:x.id,campo:campo,valor:val},function(rr){
                $in.css('background', (rr&&rr.success)?'#dcfce7':'#fee2e2');
                setTimeout(function(){ $in.css('background',''); },800);
            });
        });
        $('#ve-busca').on('keydown',function(e){ if(e.which===13) carregar(); });
        $('#ve-grupo').on('change',carregar);
        $('#ve-status').on('change',carregar);
        carregar();
    });
    </script>
    <?php
}
