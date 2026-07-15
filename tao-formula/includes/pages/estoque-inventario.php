<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Estoque — Inventário em massa (contagem geral).
 * Congela o saldo, contagem lote a lote na planilha, apura diferenças e aplica os ajustes de uma vez.
 */
function tao_formula_page_estoque_inventario() {
    if ( ! tao_formula_can_access() ) { echo '<p>Acesso negado.</p>'; return; }
    ?>
    <div class="wrap taof-wrap">
    <h1>📋 Estoque — Inventário <small style="font-size:12px;color:#94a3b8;font-weight:400">(contagem geral)</small></h1>

    <div style="margin:14px 0"><button type="button" class="button button-primary" id="taof-inv-novo">+ Novo inventário</button></div>

    <div id="taof-inv-work" style="display:none"></div>

    <h2 style="margin-top:20px">Sessões</h2>
    <div id="taof-inv-lista"><p style="color:#94a3b8">Carregando…</p></div>
    <div style="display:flex;gap:10px;align-items:center;justify-content:center;margin:12px 0;font-size:13px;flex-wrap:wrap">
        <label>Itens por página:
            <select id="taof-inv-size" style="padding:3px 6px"><option value="20">20</option><option value="30" selected>30</option><option value="50">50</option></select>
        </label>
        <button type="button" class="button button-small" id="taof-inv-prev">‹ Anterior</button>
        <span id="taof-inv-pg" style="color:#64748b">—</span>
        <button type="button" class="button button-small" id="taof-inv-next">Próxima ›</button>
    </div>

    <div id="taof-inv-modal" style="display:none"><div class="taof-ov"></div><div class="taof-box"><div id="taof-inv-mbody"></div></div></div>

    <style>
    .taof-inv-tb{width:100%;border-collapse:collapse;background:#fff;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;font-size:13px}
    .taof-inv-tb th,.taof-inv-tb td{padding:7px 10px;border-bottom:1px solid #f1f5f9;text-align:left}
    .taof-inv-tb th{background:#f8fafc;font-size:11px;text-transform:uppercase;color:#64748b}
    .taof-inv-twrap{overflow-x:auto;min-width:0}
    .taof-inv-pill{font-size:11px;padding:1px 8px;border-radius:10px}
    .taof-inv-pill.aberta{background:#fef3c7;color:#92400e}.taof-inv-pill.fechada{background:#dcfce7;color:#166534}.taof-inv-pill.cancelada{background:#e2e8f0;color:#475569}
    .inv-dif-pos{color:#166534;font-weight:600}.inv-dif-neg{color:#dc2626;font-weight:600}
    #taof-inv-modal .taof-ov{position:fixed;inset:0;background:rgba(15,23,42,.5);z-index:9998}
    #taof-inv-modal .taof-box{position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:#fff;border-radius:10px;padding:20px 22px;z-index:9999;width:460px;max-width:96vw;box-shadow:0 10px 40px rgba(0,0,0,.25)}
    .inv-cont{width:90px;padding:3px 6px;border:1px solid #d1d5db;border-radius:4px;text-align:right}
    </style>

    <script>
    jQuery(function($){
        var ajaxUrl=taoFormula.ajaxUrl, nonce=taoFormula.nonce, WORK=null, timer=null;
        var pg=0, sz=30, total=0;
        function esc(t){return $('<span>').text(t==null?'':t).html();}
        function fdata(d){if(!d)return '—';var p=String(d).substring(0,10).split('-');return p.length===3?p[2]+'/'+p[1]+'/'+p[0]:d;}
        function nz(n){n=parseFloat(n);return isNaN(n)?'':n.toLocaleString('pt-BR',{maximumFractionDigits:4});}

        // nova sessão
        $('#taof-inv-novo').on('click',function(){
            var h='<h2 style="margin:0 0 14px;font-size:18px">📋 Novo inventário</h2>'+
                '<label style="font-size:11px;color:#64748b;text-transform:uppercase;display:block;margin-bottom:2px">Descrição</label>'+
                '<input type="text" id="inv-desc" style="width:100%;padding:6px 9px;border:1px solid #d1d5db;border-radius:5px;margin-bottom:12px" placeholder="ex: Inventário mensal julho">'+
                '<label style="font-size:11px;color:#64748b;text-transform:uppercase;display:block;margin-bottom:2px">Escopo</label>'+
                '<select id="inv-escopo" style="width:100%;padding:6px 9px;border:1px solid #d1d5db;border-radius:5px;margin-bottom:14px">'+
                '<option value="aprovado">Somente lotes aprovados (padrão)</option><option value="todos">Todos os lotes com saldo</option></select>'+
                '<p style="font-size:12px;color:#64748b;margin:0 0 12px">O saldo atual será congelado como base da contagem.</p>'+
                '<p><button type="button" class="button button-primary" id="inv-criar">Abrir e contar</button> '+
                '<button type="button" class="button" id="inv-cancel">Cancelar</button> <span id="inv-msg" style="font-size:12px;margin-left:6px"></span></p>';
            $('#taof-inv-mbody').html(h); $('#taof-inv-modal').show();
            $('#inv-cancel').on('click',function(){$('#taof-inv-modal').hide();});
            $('#inv-criar').on('click',function(){
                var $b=$(this).prop('disabled',true); $('#inv-msg').css('color','#64748b').text('Abrindo (pode levar alguns segundos)…');
                $.post(ajaxUrl,{action:'tao_formula_inv_abrir',nonce:nonce,descricao:$('#inv-desc').val(),status_lote:$('#inv-escopo').val()},function(r){
                    if(r.success){$('#taof-inv-modal').hide();abrir(r.data.id);carregarLista(true);}
                    else{$b.prop('disabled',false);$('#inv-msg').css('color','#dc2626').text((r.data&&r.data.message)||'Erro');}
                }).fail(function(){$b.prop('disabled',false);$('#inv-msg').css('color','#dc2626').text('Falha');});
            });
        });
        $('#taof-inv-modal').on('click','.taof-ov',function(){$('#taof-inv-modal').hide();});

        // planilha de contagem
        function abrir(id){
            $.getJSON(ajaxUrl,{action:'tao_formula_inv_get',nonce:nonce,id:id},function(r){
                if(!r.success){alert((r.data&&r.data.message)||'Erro');return;}
                WORK=r.data; renderWork();
                $('html,body').animate({scrollTop:$('#taof-inv-work').offset().top-40},300);
            });
        }
        function difCell(it){
            if(it.diferenca==null||!it.contado) return '<span style="color:#94a3b8">—</span>';
            var d=parseFloat(it.diferenca); if(Math.abs(d)<1e-9) return '<span style="color:#16a34a">ok</span>';
            return '<span class="'+(d>0?'inv-dif-pos':'inv-dif-neg')+'">'+(d>0?'+':'')+nz(d)+'</span>';
        }
        function renderWork(){
            var inv=WORK.inv, fechado=inv.status!=='aberta';
            var contados=WORK.itens.filter(function(x){return x.contado;}).length;
            var diverg=WORK.itens.filter(function(x){return x.contado&&Math.abs(parseFloat(x.diferenca||0))>1e-9;}).length;
            var head='<div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:12px 14px;margin:10px 0">'+
                '<strong>'+esc(inv.descricao)+'</strong> · <span class="taof-inv-pill '+esc(inv.status)+'">'+esc(inv.status)+'</span>'+
                ' · '+WORK.itens.length+' lotes · contados '+contados+' · divergências '+diverg+
                '<div style="margin-top:8px"><input type="text" id="inv-busca" placeholder="filtrar ativo/lote…" style="width:260px;padding:5px 9px;border:1px solid #d1d5db;border-radius:5px"></div></div>';
            var rows=WORK.itens.map(function(it){
                return '<tr data-item="'+esc(it.id)+'" data-k="'+esc((it.ativo_nome+' '+it.nr_lote).toLowerCase())+'">'+
                    '<td><strong>'+esc(it.ativo_nome)+'</strong></td>'+
                    '<td style="font-family:monospace">'+esc(it.nr_lote)+'</td>'+
                    '<td style="text-align:right">'+nz(it.qtd_sistema)+' '+esc(it.unidade||'')+'</td>'+
                    '<td style="text-align:right">'+(fechado?nz(it.qtd_contada):'<input type="text" class="inv-cont" value="'+(it.qtd_contada!=null?nz(it.qtd_contada):'')+'">')+'</td>'+
                    '<td style="text-align:right" class="inv-dif">'+difCell(it)+'</td></tr>';
            }).join('');
            var tbl='<div class="taof-inv-twrap" style="max-height:60vh;overflow:auto"><table class="taof-inv-tb"><thead><tr><th>Ativo</th><th>Lote</th><th>Sistema</th><th>Contado</th><th>Dif.</th></tr></thead><tbody>'+rows+'</tbody></table></div>';
            var acoes = fechado
                ? '<p style="margin:12px 0;color:'+(inv.status==='fechada'?'#16a34a':'#64748b')+'">'+(inv.status==='fechada'?'✓ Inventário fechado — '+diverg+' ajuste(s) aplicado(s).':'Inventário cancelado.')+' <button type="button" class="button" id="inv-fechar-tela">Fechar</button></p>'
                : '<p style="margin:12px 0"><button type="button" class="button button-primary" id="inv-aplicar">✔ Fechar inventário (aplica '+diverg+' ajuste(s))</button> '+
                  '<button type="button" class="button" id="inv-cancelar-sessao">Cancelar sessão</button> '+
                  '<button type="button" class="button" id="inv-fechar-tela">Sair sem fechar</button> <span id="inv-wmsg" style="font-size:12px;margin-left:6px"></span></p>';
            $('#taof-inv-work').html('<h2>Contagem</h2>'+head+tbl+acoes).show();
        }
        // busca/filtro
        $(document).on('input','#inv-busca',function(){
            var q=$(this).val().trim().toLowerCase();
            $('#taof-inv-work tbody tr').each(function(){$(this).toggle(!q||$(this).data('k').indexOf(q)>=0);});
        });
        // grava contagem ao sair do campo
        $(document).on('change','.inv-cont',function(){
            var $tr=$(this).closest('tr'),item=$tr.data('item'),v=$(this).val().trim();
            $.post(ajaxUrl,{action:'tao_formula_inv_contar',nonce:nonce,item_id:item,qtd_contada:v},function(r){
                if(r.success){
                    var it=WORK.itens.filter(function(x){return x.id===item;})[0];
                    if(it){ if(v===''){it.qtd_contada=null;it.diferenca=null;it.contado=false;} else {it.qtd_contada=parseFloat(v.replace(',','.'));it.diferenca=it.qtd_contada-parseFloat(it.qtd_sistema);it.contado=true;} }
                    $tr.find('.inv-dif').html(difCell(it||{}));
                }
            });
        });
        $(document).on('click','#inv-fechar-tela',function(){$('#taof-inv-work').hide().empty();WORK=null;});
        $(document).on('click','#inv-aplicar',function(){
            var diverg=WORK.itens.filter(function(x){return x.contado&&Math.abs(parseFloat(x.diferenca||0))>1e-9;}).length;
            if(!confirm('Fechar o inventário e aplicar '+diverg+' ajuste(s) de estoque? Lotes não contados ficam inalterados.'))return;
            var $b=$(this).prop('disabled',true); $('#inv-wmsg').css('color','#64748b').text('Aplicando…');
            $.post(ajaxUrl,{action:'tao_formula_inv_fechar',nonce:nonce,id:WORK.inv.id},function(r){
                if(r.success){abrir(WORK.inv.id);carregarLista();}
                else{$b.prop('disabled',false);$('#inv-wmsg').css('color','#dc2626').text((r.data&&r.data.message)||'Erro');}
            }).fail(function(){$b.prop('disabled',false);$('#inv-wmsg').css('color','#dc2626').text('Falha');});
        });
        $(document).on('click','#inv-cancelar-sessao',function(){
            if(!confirm('Cancelar esta sessão de inventário? Nada será ajustado.'))return;
            $.post(ajaxUrl,{action:'tao_formula_inv_cancelar',nonce:nonce,id:WORK.inv.id},function(r){
                if(r.success){abrir(WORK.inv.id);carregarLista();}else alert((r.data&&r.data.message)||'Erro');
            });
        });

        // lista de sessões
        function pager(){
            var paginas=Math.max(1,Math.ceil(total/sz));
            $('#taof-inv-pg').text('Página '+(pg+1)+' de '+paginas);
            $('#taof-inv-prev').prop('disabled',pg<=0);
            $('#taof-inv-next').prop('disabled',pg>=paginas-1);
        }
        function carregarLista(reset){
            if(reset){pg=0;}
            $.getJSON(ajaxUrl,{action:'tao_formula_inv_lista',nonce:nonce,size:sz,offset:pg*sz},function(r){
                var l=(r&&r.success&&r.data&&r.data.items)?r.data.items:[]; total=(r&&r.data&&r.data.total)||0;
                if(!l.length){$('#taof-inv-lista').html('<p style="color:#94a3b8">Nenhum inventário ainda.</p>');pager();return;}
                var rows=l.map(function(o){
                    return '<tr style="cursor:pointer" class="inv-row" data-id="'+esc(o.id)+'"><td>'+fdata(o.dt_abertura)+'</td>'+
                        '<td><strong>'+esc(o.descricao)+'</strong></td>'+
                        '<td style="text-align:right">'+esc(o.total_itens||0)+'</td>'+
                        '<td style="text-align:right">'+(o.status==='fechada'?esc(o.total_diverg||0):'—')+'</td>'+
                        '<td><span class="taof-inv-pill '+esc(o.status)+'">'+esc(o.status)+'</span></td></tr>';
                }).join('');
                $('#taof-inv-lista').html('<div class="taof-inv-twrap"><table class="taof-inv-tb"><tr><th>Abertura</th><th>Descrição</th><th>Lotes</th><th>Ajustes</th><th>Status</th></tr>'+rows+'</table></div>');
                pager();
            });
        }
        $(document).on('click','.inv-row',function(){abrir($(this).data('id'));});
        $('#taof-inv-size').on('change',function(){sz=parseInt(this.value,10)||30;carregarLista(true);});
        $('#taof-inv-prev').on('click',function(){if(pg>0){pg--;carregarLista(false);}});
        $('#taof-inv-next').on('click',function(){pg++;carregarLista(false);});
        carregarLista(true);
    });
    </script>
    </div>
    <?php
}
