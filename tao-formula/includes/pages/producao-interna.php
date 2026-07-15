<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Produção Interna — diluições e bases manipuladas na farmácia (espelho FC18000).
 * Escolhe o diluído/base + quantidade → receita escalada → pesagem FEFO → gera lote.
 */
function tao_formula_page_producao_interna() {
    if ( ! tao_formula_can_access() ) { echo '<p>Acesso negado.</p>'; return; }
    ?>
    <div class="wrap taof-wrap">
    <h1>🧫 Produção Interna <small style="font-size:12px;color:#94a3b8;font-weight:400">(diluições e bases manipuladas)</small></h1>

    <div style="margin:14px 0"><button type="button" class="button button-primary" id="taof-pi-nova">+ Nova produção</button></div>

    <div id="taof-pi-work" style="display:none"></div>

    <h2 style="margin-top:20px">Ordens de produção</h2>
    <div style="margin:6px 0"><select id="taof-pi-fstatus" style="padding:4px 8px">
        <option value="">Todas</option><option value="aberta">Abertas</option><option value="concluida">Concluídas</option><option value="cancelada">Canceladas</option>
    </select></div>
    <div id="taof-pi-lista"><p style="color:#94a3b8">Carregando…</p></div>
    <div style="display:flex;gap:10px;align-items:center;justify-content:center;margin:12px 0;font-size:13px;flex-wrap:wrap">
        <label>Itens por página:
            <select id="taof-pi-size" style="padding:3px 6px"><option value="20">20</option><option value="30" selected>30</option><option value="50">50</option></select>
        </label>
        <button type="button" class="button button-small" id="taof-pi-prev">‹ Anterior</button>
        <span id="taof-pi-pg" style="color:#64748b">—</span>
        <button type="button" class="button button-small" id="taof-pi-next">Próxima ›</button>
    </div>

    <!-- Modal nova -->
    <div id="taof-pi-modal" style="display:none">
        <div class="taof-overlay"></div>
        <div class="taof-modal-box"><div id="taof-pi-modal-body"></div></div>
    </div>

    <style>
    .taof-pi-tb{width:100%;border-collapse:collapse;background:#fff;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;font-size:13px}
    .taof-pi-tb th,.taof-pi-tb td{padding:7px 10px;border-bottom:1px solid #f1f5f9;text-align:left}
    .taof-pi-tb th{background:#f8fafc;font-size:11px;text-transform:uppercase;color:#64748b}
    .taof-pi-twrap{overflow-x:auto;min-width:0}
    .taof-pi-pill{font-size:11px;padding:1px 8px;border-radius:10px}
    .taof-pi-pill.aberta{background:#fef3c7;color:#92400e}.taof-pi-pill.concluida{background:#dcfce7;color:#166534}.taof-pi-pill.cancelada{background:#e2e8f0;color:#475569}
    #taof-pi-modal .taof-overlay{position:fixed;inset:0;background:rgba(15,23,42,.5);z-index:9998}
    #taof-pi-modal .taof-modal-box{position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:#fff;border-radius:10px;padding:20px 22px;z-index:9999;width:560px;max-width:96vw;max-height:90vh;overflow-y:auto;box-shadow:0 10px 40px rgba(0,0,0,.25)}
    .taof-pi-lbl{font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.4px;display:block;margin-bottom:2px}
    .taof-pi-in{width:100%;padding:6px 9px;border:1px solid #d1d5db;border-radius:5px;font-size:13px}
    .taof-pi-dd{position:absolute;z-index:60;background:#fff;border:1px solid #cbd5e1;border-radius:6px;box-shadow:0 4px 14px rgba(0,0,0,.12);max-height:210px;overflow:auto;min-width:280px;display:none}
    </style>

    <script>
    jQuery(function($){
        var ajaxUrl=taoFormula.ajaxUrl, nonce=taoFormula.nonce, NEW={}, WORK=null;
        var pg=0, sz=30, total=0;
        function esc(t){return $('<span>').text(t==null?'':t).html();}
        function fdata(d){if(!d)return '—';var p=String(d).substring(0,10).split('-');return p.length===3?p[2]+'/'+p[1]+'/'+p[0]:d;}
        function nz(n,d){n=parseFloat(n);return isNaN(n)?'—':n.toLocaleString('pt-BR',{maximumFractionDigits:d==null?4:d});}

        // ── Modal nova produção ──
        $('#taof-pi-nova').on('click', function(){
            NEW={};
            var h='<h2 style="margin:0 0 14px;font-size:18px">🧫 Nova produção interna</h2>';
            h+='<div style="margin-bottom:12px;position:relative"><label class="taof-pi-lbl">Produto a produzir (diluído / base) *</label>'+
               '<input type="text" id="pi-ativo" class="taof-pi-in" placeholder="ex: FINASTERIDA 1:10" autocomplete="off"><div id="pi-ativo-dd" class="taof-pi-dd"></div></div>';
            h+='<div style="margin-bottom:12px;position:relative"><label class="taof-pi-lbl">Receita (fórmula) *</label>'+
               '<input type="text" id="pi-formula" class="taof-pi-in" placeholder="selecione o produto primeiro" autocomplete="off"><div id="pi-formula-dd" class="taof-pi-dd"></div>'+
               '<label style="font-size:12px;display:block;margin-top:5px"><input type="checkbox" id="pi-vincular" checked> vincular esta receita ao produto (lembra na próxima vez)</label></div>';
            h+='<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px">'+
               '<div><label class="taof-pi-lbl">Quantidade a produzir *</label><input type="text" id="pi-qtd" class="taof-pi-in" placeholder="ex: 100"></div>'+
               '<div><label class="taof-pi-lbl">Unidade</label><input type="text" id="pi-unid" class="taof-pi-in" value="g" readonly style="background:#f8fafc"></div></div>';
            h+='<p><button type="button" class="button button-primary" id="pi-criar">Criar e ir para pesagem</button> '+
               '<button type="button" class="button" id="pi-cancel">Cancelar</button> <span id="pi-msg" style="font-size:12px;margin-left:6px"></span></p>';
            $('#taof-pi-modal-body').html(h); $('#taof-pi-modal').show();
            $('#pi-cancel').on('click',function(){$('#taof-pi-modal').hide();});
            $('#pi-ativo').focus();
        });
        $('#taof-pi-modal').on('click','.taof-overlay',function(){$('#taof-pi-modal').hide();});

        // autocomplete ativo produzido
        $(document).on('input','#pi-ativo',function(){
            var $i=$(this),$dd=$('#pi-ativo-dd'),q=$i.val().trim(); if(q.length<2){$dd.hide();return;}
            clearTimeout($i.data('t')); $i.data('t',setTimeout(function(){
                $.getJSON(ajaxUrl,{action:'tao_formula_prodint_ativos',nonce:nonce,q:q},function(r){
                    var l=(r&&r.success&&r.data)?r.data:[]; $dd.empty();
                    if(!l.length){$dd.html('<div style="padding:6px 10px;color:#94a3b8;font-size:12px">nada</div>').show();return;}
                    l.forEach(function(a){
                        $('<div style="padding:6px 10px;cursor:pointer;font-size:13px;border-bottom:1px solid #f1f5f9">')
                        .html(esc(a.nome)+' <small style="color:#94a3b8">['+esc(a.codigo_fc||'')+']</small>'+(a.formula_producao_id?' <span style="color:#16a34a">•receita✓</span>':''))
                        .on('mousedown',function(e){e.preventDefault();
                            NEW.ativo=a; $('#pi-ativo').val(a.nome); $('#pi-unid').val(a.unidade_padrao||'g'); $dd.hide();
                            // pré-carrega a receita vinculada
                            if(a.formula_producao_id){ carregarReceitaVinculada(a); }
                            else { $('#pi-formula').val('').attr('placeholder','busque a receita pelo nome'); NEW.formula=null; }
                            $('#pi-formula').focus();
                        }).appendTo($dd);
                    });
                    $dd.show();
                });
            },260));
        });
        function carregarReceitaVinculada(a){
            // busca o nome da fórmula vinculada
            $.getJSON(ajaxUrl,{action:'tao_formula_prodint_formula_busca',nonce:nonce,q:a.nome.substring(0,6)},function(r){
                var l=(r&&r.success&&r.data)?r.data:[]; var f=l.filter(function(x){return x.id===a.formula_producao_id;})[0]||l[0];
                if(f){ NEW.formula=f; $('#pi-formula').val(f.nome); } else { $('#pi-formula').val('').attr('placeholder','busque a receita'); }
            });
        }
        // autocomplete fórmula
        $(document).on('input','#pi-formula',function(){
            var $i=$(this),$dd=$('#pi-formula-dd'),q=$i.val().trim(); if(q.length<2){$dd.hide();return;}
            clearTimeout($i.data('t')); $i.data('t',setTimeout(function(){
                $.getJSON(ajaxUrl,{action:'tao_formula_prodint_formula_busca',nonce:nonce,q:q},function(r){
                    var l=(r&&r.success&&r.data)?r.data:[]; $dd.empty();
                    if(!l.length){$dd.html('<div style="padding:6px 10px;color:#94a3b8;font-size:12px">nada</div>').show();return;}
                    l.forEach(function(f){
                        $('<div style="padding:6px 10px;cursor:pointer;font-size:13px;border-bottom:1px solid #f1f5f9">')
                        .text(f.nome).on('mousedown',function(e){e.preventDefault();NEW.formula=f;$('#pi-formula').val(f.nome);$dd.hide();}).appendTo($dd);
                    });
                    $dd.show();
                });
            },260));
        });
        $(document).on('blur','#pi-ativo,#pi-formula',function(){var id=this.id;setTimeout(function(){$('#'+id+'-dd').hide();},180);});

        // criar OP
        $(document).on('click','#pi-criar',function(){
            if(!NEW.ativo){$('#pi-msg').css('color','#dc2626').text('Selecione o produto');return;}
            if(!NEW.formula){$('#pi-msg').css('color','#dc2626').text('Selecione a receita');return;}
            var qtd=$('#pi-qtd').val().trim(); if(!qtd){$('#pi-msg').css('color','#dc2626').text('Informe a quantidade');return;}
            var $b=$(this).prop('disabled',true); $('#pi-msg').css('color','#64748b').text('Criando…');
            $.post(ajaxUrl,{action:'tao_formula_prodint_nova',nonce:nonce,ativo_id:NEW.ativo.id,formula_id:NEW.formula.id,quantidade:qtd,vincular_receita:$('#pi-vincular').is(':checked')?1:0},function(r){
                if(r.success){$('#taof-pi-modal').hide();abrirPesagem(r.data.id);carregarLista(true);}
                else{$b.prop('disabled',false);$('#pi-msg').css('color','#dc2626').text((r.data&&r.data.message)||'Erro');}
            }).fail(function(){$b.prop('disabled',false);$('#pi-msg').css('color','#dc2626').text('Falha');});
        });

        // ── Pesagem / conclusão ──
        function abrirPesagem(id){
            $.getJSON(ajaxUrl,{action:'tao_formula_prodint_get',nonce:nonce,id:id},function(r){
                if(!r.success){alert((r.data&&r.data.message)||'Erro');return;}
                WORK=r.data; renderWork();
                $('html,body').animate({scrollTop:$('#taof-pi-work').offset().top-40},300);
            });
        }
        function renderWork(){
            var op=WORK.op, concl=op.status==='concluida';
            var head='<div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:12px 14px;margin:10px 0">'+
                '<strong>'+esc(op.ativo_nome)+'</strong> · produzir <strong>'+nz(op.quantidade)+' '+esc(op.unidade)+'</strong>'+
                ' · lote <strong>'+esc(op.nr_lote)+'</strong> · validade '+fdata(op.dt_validade)+
                (op.teor_pct?' · teor '+nz(op.teor_pct,2)+'%':'')+(op.fator_diluicao?' · fator 1:'+nz(op.fator_diluicao,2):'')+
                ' · <span class="taof-pi-pill '+esc(op.status)+'">'+esc(op.status)+'</span></div>';
            var rows=WORK.itens.map(function(it,i){
                var lotesOpts='<option value="">— lote —</option>'+(it.lotes||[]).map(function(l){
                    return '<option value="'+esc(l.id)+'"'+(it.lote_mp_id===l.id?' selected':'')+'>'+esc(l.nr_lote)+' (val '+fdata(l.dt_validade)+', '+nz(l.qtd_atual)+')</option>';}).join('');
                var semAtivo=!it.ativo_id;
                return '<tr data-item="'+esc(it.id)+'">'+
                    '<td>'+esc(it.descricao)+(it.eh_qsp?' <small style="color:#0369a1">(veículo/QSP)</small>':'')+'</td>'+
                    '<td style="text-align:right">'+nz(it.qtd_teorica)+' '+esc(it.unidade)+'</td>'+
                    '<td>'+(semAtivo?'<em style="color:#94a3b8">sem ativo</em>':'<select class="pi-lote" '+(concl?'disabled':'')+' style="font-size:12px;max-width:220px">'+lotesOpts+'</select>')+'</td>'+
                    '<td><input type="text" class="pi-pesado" '+(concl?'disabled':'')+' value="'+(it.qtd_pesada!=null?nz(it.qtd_pesada):'')+'" placeholder="pesado" style="width:80px;padding:3px 6px;border:1px solid #d1d5db;border-radius:4px"></td>'+
                    '</tr>';
            }).join('');
            var tbl='<div class="taof-pi-twrap"><table class="taof-pi-tb"><tr><th>Insumo</th><th>Qtd teórica</th><th>Lote (FEFO)</th><th>Pesado</th></tr>'+rows+'</table></div>';
            var acoes = concl
                ? '<p style="color:#16a34a;margin:12px 0">✓ Produção concluída — lote '+esc(op.nr_lote)+' disponível no estoque.</p>'
                : '<p style="margin:12px 0"><button type="button" class="button button-primary" id="pi-concluir">✔ Concluir produção (baixa insumos + gera lote)</button> '+
                  '<button type="button" class="button" id="pi-fechar">Fechar</button> <span id="pi-wmsg" style="font-size:12px;margin-left:6px"></span></p>';
            $('#taof-pi-work').html('<h2>Pesagem</h2>'+head+tbl+acoes).show();
        }
        // salva pesagem ao mudar lote/qtd
        $(document).on('change','.pi-lote, .pi-pesado',function(){
            var $tr=$(this).closest('tr'),item=$tr.data('item');
            var lote=$tr.find('.pi-lote').val()||'', pesado=$tr.find('.pi-pesado').val().trim();
            $.post(ajaxUrl,{action:'tao_formula_prodint_pesar',nonce:nonce,item_id:item,lote_mp_id:lote,qtd_pesada:pesado});
        });
        $(document).on('click','#pi-fechar',function(){$('#taof-pi-work').hide().empty();WORK=null;});
        $(document).on('click','#pi-concluir',function(){
            if(!confirm('Concluir? Isto baixa os insumos do estoque e gera o lote do produzido.'))return;
            var $b=$(this).prop('disabled',true); $('#pi-wmsg').css('color','#64748b').text('Concluindo…');
            $.post(ajaxUrl,{action:'tao_formula_prodint_concluir',nonce:nonce,id:WORK.op.id},function(r){
                if(r.success){abrirPesagem(WORK.op.id);carregarLista();}
                else{$b.prop('disabled',false);$('#pi-wmsg').css('color','#dc2626').text((r.data&&r.data.message)||'Erro');}
            }).fail(function(){$b.prop('disabled',false);$('#pi-wmsg').css('color','#dc2626').text('Falha');});
        });

        // ── Lista ──
        function pager(){
            var paginas=Math.max(1,Math.ceil(total/sz));
            $('#taof-pi-pg').text('Página '+(pg+1)+' de '+paginas);
            $('#taof-pi-prev').prop('disabled',pg<=0);
            $('#taof-pi-next').prop('disabled',pg>=paginas-1);
        }
        function carregarLista(reset){
            if(reset){pg=0;}
            $.getJSON(ajaxUrl,{action:'tao_formula_prodint_lista',nonce:nonce,status:$('#taof-pi-fstatus').val(),size:sz,offset:pg*sz},function(r){
                var l=(r&&r.success&&r.data&&r.data.items)?r.data.items:[]; total=(r&&r.data&&r.data.total)||0;
                if(!l.length){$('#taof-pi-lista').html('<p style="color:#94a3b8">Nenhuma produção ainda.</p>');pager();return;}
                var rows=l.map(function(o){
                    return '<tr style="cursor:pointer" class="pi-row" data-id="'+esc(o.id)+'"><td>'+fdata(o.dt_producao)+'</td>'+
                        '<td><strong>'+esc(o.ativo_nome)+'</strong></td>'+
                        '<td style="text-align:right">'+nz(o.quantidade)+' '+esc(o.unidade)+'</td>'+
                        '<td style="font-family:monospace;font-size:12px">'+esc(o.nr_lote||'')+'</td>'+
                        '<td>'+fdata(o.dt_validade)+'</td>'+
                        '<td><span class="taof-pi-pill '+esc(o.status)+'">'+esc(o.status)+'</span></td></tr>';
                }).join('');
                $('#taof-pi-lista').html('<div class="taof-pi-twrap"><table class="taof-pi-tb"><tr><th>Data</th><th>Produto</th><th>Qtd</th><th>Lote</th><th>Validade</th><th>Status</th></tr>'+rows+'</table></div>');
                pager();
            });
        }
        $(document).on('click','.pi-row',function(){abrirPesagem($(this).data('id'));});
        $('#taof-pi-fstatus').on('change',function(){carregarLista(true);});
        $('#taof-pi-size').on('change',function(){sz=parseInt(this.value,10)||30;carregarLista(true);});
        $('#taof-pi-prev').on('click',function(){if(pg>0){pg--;carregarLista(false);}});
        $('#taof-pi-next').on('click',function(){pg++;carregarLista(false);});
        carregarLista(true);
    });
    </script>
    </div>
    <?php
}
