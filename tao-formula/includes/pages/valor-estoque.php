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
    #ve-busca-wrap{position:relative}
    #ve-clear{position:absolute;right:8px;bottom:7px;cursor:pointer;color:#94a3b8;font-weight:700;font-size:15px;line-height:1;display:none}
    #ve-clear:hover{color:#475569}
    #ve-ac{display:none;position:absolute;left:0;right:0;top:100%;z-index:30;background:#fff;border:1px solid #d1d5db;border-top:none;border-radius:0 0 6px 6px;max-height:280px;overflow:auto;box-shadow:0 8px 20px rgba(0,0,0,.10)}
    .ve-ac-item{padding:6px 10px;cursor:pointer;font-size:13px;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between;gap:10px}
    .ve-ac-item:last-child{border-bottom:none}
    .ve-ac-item.hl,.ve-ac-item:hover{background:#eef2ff}
    .ve-ac-item .qt{color:#94a3b8;font-size:11px;white-space:nowrap}
    </style>
    <div class="wrap taof-wrap ve-wrap">
    <h1>💰 Valor do Estoque <small style="font-size:12px;color:#94a3b8;font-weight:400">(saldo dos lotes aprovados × custo / venda — edite os unitários direto na tabela)</small></h1>

    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:end;margin:10px 0">
        <div id="ve-busca-wrap"><label style="font-size:11px;color:#64748b;display:block;margin-bottom:2px">Buscar produto</label><input id="ve-busca" autocomplete="off" placeholder="digite para filtrar…" style="width:240px;padding:6px 26px 6px 8px;border:1px solid #d1d5db;border-radius:6px"><span id="ve-clear" title="Limpar busca">×</span><div id="ve-ac"></div></div>
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
                <th>Custo total</th>
                <th>Compra unit</th>
                <th>Compra total</th>
                <th>Venda unit</th>
                <th>Venda total</th>
            </tr></thead>
            <tbody id="ve-body"><tr><td colspan="8" style="text-align:left">carregando…</td></tr></tbody>
        </table>
    </div>
    </div>

    <script>
    jQuery(function($){
        var ajaxUrl=taoFormula.ajaxUrl, nonce=taoFormula.nonce, ITENS=[], TODOS=[], acIdx=-1;
        function esc(s){ return String(s==null?'':s).replace(/[&<>]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;'}[c];}); }
        function m(v,d){ d=(d==null?2:d); return parseFloat(v||0).toLocaleString('pt-BR',{minimumFractionDigits:d,maximumFractionDigits:d}); }
        function R(v){ return 'R$ '+m(v,2); }
        function num(s){ return parseFloat(String(s==null?'':s).replace(/\./g,'').replace(',','.'))||0; }

        // Cada total = seu próprio unitário × qtde (transparente).
        // Valorização ÂNCORA = CUSTO (custo_por_unidade, por unidade padrão — coerente e o que o
        // resto do sistema usa). A COMPRA fica como referência e é BLINDADA: item com compra_un
        // muito acima do custo (preço cadastrado por embalagem/lote/milheiro) é marcado "a revisar"
        // e NÃO contamina o valor do estoque — antes 1 etiqueta mal cadastrada inflava R$ milhões.
        function susp(x){ return x.custo_un>0 && x.compra_un > 5*x.custo_un; }
        function ptStyle(x){ return susp(x) ? 'background:#fef2f2;color:#dc2626' : 'color:#b45309'; }
        function ptHtml(x){ return (susp(x)?'⚠ ':'')+R(x.qtd*x.compra_un); }
        function totais(){
            var tCu=0,tv=0,nSusp=0;
            ITENS.forEach(function(x){ tCu+=x.qtd*x.custo_un; tv+=x.qtd*x.venda_un; if(susp(x)) nSusp++; });
            var html=
                '<div class="ve-card"><div class="l">Itens em estoque</div><div class="v">'+m(ITENS.length,0)+'</div></div>'+
                '<div class="ve-card"><div class="l">Valor do estoque (a custo)</div><div class="v" style="color:#b45309">'+R(tCu)+'</div></div>'+
                '<div class="ve-card"><div class="l">Valor a venda</div><div class="v" style="color:#16a34a">'+R(tv)+'</div></div>'+
                '<div class="ve-card"><div class="l">Margem potencial (venda−custo)</div><div class="v" style="color:#2563eb">'+R(tv-tCu)+'</div></div>';
            if(nSusp>0) html+='<div class="ve-card" style="border-color:#fca5a5;background:#fef2f2"><div class="l" style="color:#b91c1c">⚠ Preço de compra a revisar</div><div class="v" style="color:#dc2626;font-size:18px">'+m(nSusp,0)+' iten(s)</div><div style="font-size:11px;color:#b91c1c;margin-top:2px">compra &gt; 5× o custo — cadastro provável por embalagem</div></div>';
            $('#ve-cards').html(html);
        }
        function inp(i,campo,val){ return '<input class="ve-in" data-i="'+i+'" data-campo="'+campo+'" value="'+m(val,4)+'">'; }
        function render(){
            if(!ITENS.length){ $('#ve-body').html('<tr><td colspan="9" style="text-align:left;color:#64748b">Nenhum item com saldo.</td></tr>'); totais(); return; }
            var h='';
            ITENS.forEach(function(x,i){
                var cz = (x.custo_un>0)?'#374151':'#cbd5e1';  // custo não cadastrado = cinza claro
                var tt = susp(x) ? ' title="Preço de compra muito acima do custo — provavelmente cadastrado por embalagem/lote. Revise o cadastro."' : '';
                h+='<tr data-i="'+i+'"><td class="l"><b>'+esc(x.nome)+'</b></td>'+
                   '<td>'+m(x.qtd,3)+'</td><td class="l">'+esc(x.un)+'</td>'+
                   '<td>'+inp(i,'custo_por_unidade',x.custo_un)+'</td>'+
                   '<td class="ve-tot ve-ct" style="color:'+cz+'">'+R(x.qtd*x.custo_un)+'</td>'+
                   '<td'+tt+'>'+inp(i,'preco_compra',x.compra_un)+'</td>'+
                   '<td class="ve-tot ve-pt" style="'+ptStyle(x)+'"'+tt+'>'+ptHtml(x)+'</td>'+
                   '<td>'+inp(i,'preco_venda',x.venda_un)+'</td>'+
                   '<td class="ve-tot ve-vt" style="color:#16a34a">'+R(x.qtd*x.venda_un)+'</td></tr>';
            });
            $('#ve-body').html(h); totais();
        }
        // Servidor traz TODOS os itens do grupo/status; a BUSCA por texto é client-side (instantânea).
        function carregar(){
            $('#ve-msg').text('carregando…');
            $.post(ajaxUrl,{action:'tao_formula_valor_estoque',nonce:nonce,busca:'',grupo:$('#ve-grupo').val(),status:$('#ve-status').val()},function(r){
                if(!r||!r.success){ $('#ve-msg').text('Erro.'); return; }
                TODOS=r.data.itens||[]; aplicarBusca();
            });
        }
        function aplicarBusca(){
            var q=($('#ve-busca').val()||'').trim().toLowerCase();
            ITENS = q ? TODOS.filter(function(x){ return String(x.nome||'').toLowerCase().indexOf(q)>=0; }) : TODOS.slice();
            render();
            $('#ve-msg').text(q ? (ITENS.length+' de '+TODOS.length) : (TODOS.length+' item(ns)'));
            $('#ve-clear').css('display', q?'block':'none');
        }
        // autocomplete navegável (↑ ↓ Enter Esc)
        function fecharAC(){ $('#ve-ac').hide().empty(); acIdx=-1; }
        function abrirAC(){
            var q=($('#ve-busca').val()||'').trim().toLowerCase();
            if(!q){ fecharAC(); return; }
            var sug=TODOS.filter(function(x){ return String(x.nome||'').toLowerCase().indexOf(q)>=0; }).slice(0,10);
            if(!sug.length){ fecharAC(); return; }
            $('#ve-ac').html(sug.map(function(x){ return '<div class="ve-ac-item" data-nome="'+esc(x.nome)+'"><span>'+esc(x.nome)+'</span><span class="qt">'+m(x.qtd,0)+' '+esc(x.un)+'</span></div>'; }).join('')).show();
            acIdx=-1;
        }
        function selAC(nome){ $('#ve-busca').val(nome); fecharAC(); aplicarBusca(); }
        // edição inline: ao sair do campo, grava no ativo e recalcula
        $('#ve-body').on('change','.ve-in',function(){
            var $in=$(this), i=+$in.data('i'), campo=$in.data('campo'), val=num($in.val());
            var x=ITENS[i]; if(!x) return;
            $in.val(m(val,4));
            if(campo==='custo_por_unidade') x.custo_un=val; else if(campo==='preco_compra') x.compra_un=val; else x.venda_un=val;
            var $tr=$in.closest('tr');
            $tr.find('.ve-ct').text(R(x.qtd*x.custo_un)).css('color', x.custo_un>0?'#374151':'#cbd5e1');
            $tr.find('.ve-pt').html(ptHtml(x)).attr('style', ptStyle(x));
            $tr.find('.ve-vt').text(R(x.qtd*x.venda_un));
            totais();
            $in.css('background','#fefce8');
            $.post(ajaxUrl,{action:'tao_formula_valor_estoque_salvar',nonce:nonce,id:x.id,campo:campo,valor:val},function(rr){
                $in.css('background', (rr&&rr.success)?'#dcfce7':'#fee2e2');
                setTimeout(function(){ $in.css('background',''); },800);
            });
        });
        $('#ve-busca').on('input',function(){ aplicarBusca(); abrirAC(); });
        $('#ve-busca').on('keydown',function(e){
            var $it=$('#ve-ac .ve-ac-item');
            if(e.which===40){ if(!$it.length){ abrirAC(); return; } acIdx=Math.min(acIdx+1,$it.length-1); $it.removeClass('hl').eq(acIdx).addClass('hl'); e.preventDefault(); }
            else if(e.which===38){ if(!$it.length) return; acIdx=Math.max(acIdx-1,0); $it.removeClass('hl').eq(acIdx).addClass('hl'); e.preventDefault(); }
            else if(e.which===13){ if(acIdx>=0 && $it.length){ selAC($it.eq(acIdx).data('nome')); } else { fecharAC(); aplicarBusca(); } e.preventDefault(); }
            else if(e.which===27){ fecharAC(); }
        });
        $('#ve-ac').on('mousedown','.ve-ac-item',function(e){ e.preventDefault(); selAC($(this).data('nome')); });
        $('#ve-clear').on('click',function(){ $('#ve-busca').val(''); fecharAC(); aplicarBusca(); $('#ve-busca').focus(); });
        $(document).on('click',function(e){ if(!$(e.target).closest('#ve-busca-wrap').length) fecharAC(); });
        $('#ve-grupo').on('change',carregar);
        $('#ve-status').on('change',carregar);
        carregar();
    });
    </script>
    <?php
}
