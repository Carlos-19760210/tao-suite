<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Estoque — Reposição (Pacote 2 / Fatia 3).
 * Mínimo/máximo/curva por ativo, alerta de abaixo do mínimo e geração de cotação.
 */
function tao_formula_page_estoque_reposicao() {
    if ( ! tao_formula_can_access() ) { echo '<p>Acesso negado.</p>'; return; }
    $url_cot = function_exists( 'cbpm_url' ) ? cbpm_url( 'cotacoes' ) : admin_url( 'admin.php?page=tao-cotacoes' );
    ?>
    <div class="wrap taof-wrap">
    <h1>📦 Estoque — Reposição <small style="font-size:12px;color:#94a3b8;font-weight:400">(mínimo/curva → cotação de compra)</small></h1>

    <div style="margin:14px 0;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <label style="font-size:13px"><input type="checkbox" id="taof-rp-abaixo"> só abaixo do mínimo</label>
        <button type="button" class="button" id="taof-rp-def">+ Definir mínimo de um ativo</button>
        <button type="button" class="button button-primary" id="taof-rp-cotar" disabled>🛒 Gerar cotação dos marcados</button>
        <span id="taof-rp-count" style="font-size:12px;color:#64748b"></span>
    </div>

    <div id="taof-rp-defbox" style="display:none;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:12px;margin-bottom:12px;position:relative">
        <input type="text" id="taof-rp-search" placeholder="buscar ativo…" style="width:240px;padding:6px 10px" autocomplete="off">
        <input type="hidden" id="taof-rp-aid">
        <span id="taof-rp-sel" style="font-size:12px;color:#16a34a"></span>
        <input type="text" id="taof-rp-min" placeholder="mín" style="width:70px;padding:6px">
        <input type="text" id="taof-rp-max" placeholder="máx" style="width:70px;padding:6px">
        <select id="taof-rp-curva" style="padding:6px"><option value="">curva</option><option>A</option><option>B</option><option>C</option></select>
        <button type="button" class="button button-primary" id="taof-rp-salvar">Salvar</button>
        <div id="taof-rp-dd" style="display:none;position:absolute;top:46px;left:12px;background:#fff;border:1px solid #cbd5e1;border-radius:6px;box-shadow:0 4px 14px rgba(0,0,0,.12);z-index:60;max-height:220px;overflow:auto;min-width:280px"></div>
    </div>

    <div id="taof-rp-lista"><p style="color:#94a3b8">Carregando…</p></div>

    <style>
    .taof-rp-tb{width:100%;border-collapse:collapse;background:#fff;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;font-size:13px}
    .taof-rp-tb th,.taof-rp-tb td{padding:7px 10px;border-bottom:1px solid #f1f5f9;text-align:left}
    .taof-rp-tb th{background:#f8fafc;font-size:11px;text-transform:uppercase;color:#64748b}
    .taof-rp-twrap{overflow-x:auto;min-width:0}
    .taof-rp-low{background:#fef2f2}
    </style>

    <script>
    jQuery(function($){
        var ajaxUrl=taoFormula.ajaxUrl, nonce=taoFormula.nonce, timer=null, DADOS=[];
        var urlCot=<?php echo wp_json_encode( $url_cot ); ?>;
        function esc(t){return $('<span>').text(t==null?'':t).html();}
        function nb(n){return parseFloat(n||0).toLocaleString('pt-BR');}

        function carregar(){
            $.getJSON(ajaxUrl,{action:'tao_formula_estq_reposicao',nonce:nonce,so_abaixo:$('#taof-rp-abaixo').is(':checked')?'1':''},function(r){
                DADOS=(r&&r.success&&r.data)?r.data:[];
                var abaixo=DADOS.filter(function(x){return x.abaixo;}).length;
                $('#taof-rp-count').text(DADOS.length+' ativo(s) · '+abaixo+' abaixo do mínimo');
                if(!DADOS.length){$('#taof-rp-lista').html('<p style="color:#94a3b8">Nenhum ativo com mínimo definido. Use “Definir mínimo”.</p>');upd();return;}
                var rows=DADOS.map(function(x,i){
                    return '<tr class="'+(x.abaixo?'taof-rp-low':'')+'">'+
                        '<td style="text-align:center"><input type="checkbox" class="taof-rp-ck" data-i="'+i+'" '+(x.abaixo?'checked':'')+'></td>'+
                        '<td><strong>'+esc(x.nome)+'</strong> <small style="color:#94a3b8">'+esc(x.codigo_fc||'')+'</small></td>'+
                        '<td style="text-align:right'+(x.abaixo?';color:#dc2626;font-weight:700':'')+'">'+nb(x.saldo)+' '+esc(x.unidade_padrao||'')+'</td>'+
                        '<td style="text-align:right">'+nb(x.est_min)+'</td>'+
                        '<td style="text-align:right">'+(x.est_max?nb(x.est_max):'—')+'</td>'+
                        '<td style="text-align:center">'+(x.curva||'—')+'</td>'+
                        '<td style="text-align:right;font-weight:600">'+nb(x.sugerido)+'</td>'+
                        '<td><button class="button button-small taof-rp-edit" data-i="'+i+'">editar</button></td></tr>';
                }).join('');
                $('#taof-rp-lista').html('<div class="taof-rp-twrap"><table class="taof-rp-tb"><tr><th></th><th>Ativo</th><th>Saldo</th><th>Mín</th><th>Máx</th><th>Curva</th><th>Sugerido</th><th></th></tr>'+rows+'</table></div>');
                upd();
            });
        }
        function upd(){var n=$('.taof-rp-ck:checked').length;$('#taof-rp-cotar').prop('disabled',n===0).text('🛒 Gerar cotação ('+n+')');}
        $(document).on('change','.taof-rp-ck',upd);
        $('#taof-rp-abaixo').on('change',carregar);

        // definir/editar mínimo
        function abrirDef(a){
            $('#taof-rp-defbox').show();
            $('#taof-rp-aid').val(a?a.id:''); $('#taof-rp-sel').text(a?a.nome:'');
            $('#taof-rp-search').val(a?a.nome:'');
            $('#taof-rp-min').val(a?a.est_min:''); $('#taof-rp-max').val(a&&a.est_max?a.est_max:''); $('#taof-rp-curva').val(a&&a.curva?a.curva:'');
        }
        $('#taof-rp-def').on('click',function(){abrirDef(null);});
        $(document).on('click','.taof-rp-edit',function(){abrirDef(DADOS[$(this).data('i')]);});
        $('#taof-rp-search').on('input',function(){
            var $inp=$(this),q=$inp.val().trim(); $('#taof-rp-aid').val('');
            if(q.length<2){$('#taof-rp-dd').hide();return;}
            clearTimeout(timer);timer=setTimeout(function(){
                $.getJSON(ajaxUrl,{action:'tao_formula_search_ativos',nonce:nonce,q:q,grupo:'M'},function(resp){
                    var l=(resp&&resp.success&&resp.data)?resp.data:[]; var $dd=$('#taof-rp-dd').empty();
                    l.forEach(function(a){$('<div style="padding:6px 10px;cursor:pointer;font-size:13px;border-bottom:1px solid #f1f5f9">')
                        .html(esc(a.nome)+' <small style="color:#94a3b8">['+esc(a.codigo_fc||'')+']</small>')
                        .on('mousedown',function(e){e.preventDefault();$('#taof-rp-aid').val(a.id);$('#taof-rp-sel').text(a.nome);$('#taof-rp-search').val(a.nome);$('#taof-rp-dd').hide();}).appendTo($dd);});
                    $dd.toggle(l.length>0);
                });
            },260);
        });
        $('#taof-rp-salvar').on('click',function(){
            var aid=$('#taof-rp-aid').val(); if(!aid){alert('Selecione o ativo.');return;}
            $.post(ajaxUrl,{action:'tao_formula_estq_def_min',nonce:nonce,ativo_id:aid,est_min:$('#taof-rp-min').val(),est_max:$('#taof-rp-max').val(),curva:$('#taof-rp-curva').val()},function(r){
                if(r.success){$('#taof-rp-defbox').hide();carregar();}else alert((r.data&&r.data.message)||'Erro');
            });
        });

        // gerar cotação
        $('#taof-rp-cotar').on('click',function(){
            var sel=[];$('.taof-rp-ck:checked').each(function(){sel.push(DADOS[$(this).data('i')]);});
            if(!sel.length)return;
            var $b=$(this).prop('disabled',true);
            $.post(ajaxUrl,{action:'tao_formula_estq_gerar_cotacao',nonce:nonce,itens:JSON.stringify(sel)},function(r){
                if(r.success){
                    if(confirm('✓ Cotação criada com '+r.data.itens+' item(ns). Abrir o módulo de Cotações agora?')) window.location.href=urlCot;
                    else { $b.prop('disabled',false); carregar(); }
                } else { $b.prop('disabled',false); alert((r.data&&r.data.message)||'Erro'); }
            }).fail(function(){$b.prop('disabled',false);alert('Falha na requisição');});
        });

        carregar();
    });
    </script>
    </div>
    <?php
}
