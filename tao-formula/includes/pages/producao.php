<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Produção — Ordens de Manipulação como LISTA agrupada pelas fases do funil Pós-Vendas.
 * O quadro (Kanban) é o funil do CRM; aqui: gerar OM, APROVAR a formulação (move Aguardando
 * Produção → Em Produção), pesar com lote e imprimir ficha/rótulo. Sem kanban interno.
 */
function tao_formula_page_producao() {
    if ( ! tao_formula_can_access() ) { echo '<p>Acesso negado.</p>'; return; }
    // placeholder no id → o cbpm_url mantém o "id=" (com id vazio ele omitia o "=", gerando URL inválida)
    $card_url = function_exists( 'cbpm_url' ) ? cbpm_url( 'crm-kanban', [ 'action' => 'card', 'id' => '__CID__' ] ) : '';
    ?>
    <div class="wrap taof-wrap">
    <h1>🧪 Produção — Ordens de Manipulação</h1>
    <p style="color:#64748b;margin:2px 0 12px;font-size:13px">Fila de OMs por fase do funil <strong>Pós-Vendas</strong>. O quadro visual (Kanban) fica no CRM — aqui você <strong>gera a OM</strong>, <strong>aprova a formulação</strong> e imprime a ficha / o rótulo.</p>

    <div style="margin:10px 0;position:relative;max-width:520px">
        <input type="text" id="taof-pd-orc" class="regular-text" style="width:360px" placeholder="Gerar OM: buscar orçamento (nº ou paciente)…" autocomplete="off">
        <div id="taof-pd-orcdd" style="display:none;position:absolute;z-index:60;background:#fff;border:1px solid #cbd5e1;border-radius:6px;box-shadow:0 4px 14px rgba(0,0,0,.12);max-height:240px;overflow:auto;width:360px"></div>
    </div>

    <div id="taof-pd-lista"><p style="color:#94a3b8">Carregando…</p></div>

    <div id="taof-pd-modal" style="display:none"><div class="taof-pd-ov"></div><div class="taof-pd-box"><div id="taof-pd-body"></div></div></div>

    <style>
    .taof-grp{margin:16px 0}
    .taof-grp h3{font-size:13px;text-transform:uppercase;letter-spacing:.4px;color:#475569;margin:0 0 8px;border-bottom:2px solid #e2e8f0;padding-bottom:5px}
    .taof-grp h3 .cnt{background:#e2e8f0;color:#475569;border-radius:10px;padding:0 8px;font-size:11px;margin-left:6px;font-weight:400}
    .taof-om{display:flex;justify-content:space-between;align-items:center;gap:10px;background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:10px 12px;margin-bottom:7px;flex-wrap:wrap}
    .taof-om .info{font-size:13px}
    .taof-om .num{font-family:monospace;color:#94a3b8;font-size:11px}
    .taof-om .acts{display:flex;gap:5px;flex-wrap:wrap}
    .taof-cardgrp{border:1px solid #e2e8f0;border-radius:8px;margin-bottom:10px;overflow:hidden}
    .taof-cardhd{display:flex;justify-content:space-between;align-items:center;gap:10px;background:#f8fafc;border-bottom:1px solid #e2e8f0;padding:8px 12px;flex-wrap:wrap}
    .taof-cardgrp .taof-om{border:0;border-top:1px solid #f1f5f9;border-radius:0;margin:0;background:#fff}
    .taof-pd-ctrl{font-size:10px;background:#fee2e2;color:#991b1b;padding:0 5px;border-radius:8px}
    #taof-pd-modal .taof-pd-ov{position:fixed;inset:0;background:rgba(15,23,42,.5);z-index:9998}
    #taof-pd-modal .taof-pd-box{position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:#fff;border-radius:10px;padding:18px 20px;z-index:9999;width:640px;max-width:96vw;max-height:90vh;overflow:auto;box-shadow:0 10px 40px rgba(0,0,0,.25)}
    .taof-pd-it{width:100%;border-collapse:collapse;font-size:13px}
    .taof-pd-it th,.taof-pd-it td{padding:5px 8px;border-bottom:1px solid #f1f5f9;text-align:left}
    </style>

    <script>
    jQuery(function($){
        var ajaxUrl=taoFormula.ajaxUrl, nonce=taoFormula.nonce, timer=null;
        var CARDURL=<?php echo wp_json_encode( $card_url ); ?>;
        function esc(t){return $('<span>').text(t==null?'':t).html();}
        function fdata(d){if(!d)return '—';var p=String(d).substring(0,10).split('-');return p.length===3?p[2]+'/'+p[1]+'/'+p[0]:d;}

        // ── Gerar OM (buscar orçamento) ──
        $('#taof-pd-orc').on('input',function(){
            var q=$(this).val().trim(); if(q.length<2){$('#taof-pd-orcdd').hide();return;}
            clearTimeout(timer);timer=setTimeout(function(){
                $.getJSON(ajaxUrl,{action:'tao_formula_prod_busca_orc',nonce:nonce,q:q},function(r){
                    var l=(r&&r.success&&r.data)?r.data:[]; var $dd=$('#taof-pd-orcdd').empty();
                    if(!l.length){$dd.html('<div style="padding:8px;color:#94a3b8;font-size:12px">nada</div>').show();return;}
                    l.forEach(function(o){$('<div style="padding:7px 10px;cursor:pointer;font-size:13px;border-bottom:1px solid #f1f5f9">')
                        .html('<strong>'+esc(o.nome_paciente||'—')+'</strong> <small style="color:#94a3b8">'+esc(o.numero_orcamento||'')+' · '+esc(o.forma_nome||'')+'</small>')
                        .on('mousedown',function(e){e.preventDefault();gerarOM(o);}).appendTo($dd);});
                    $dd.show();
                });
            },260);
        });
        $('#taof-pd-orc').on('blur',function(){setTimeout(function(){$('#taof-pd-orcdd').hide();},180);});
        function gerarOM(o){
            $('#taof-pd-orcdd').hide(); $('#taof-pd-orc').val('');
            if(!confirm('Gerar Ordem de Manipulação para "'+(o.nome_paciente||'')+'" ('+(o.numero_orcamento||'')+')?'))return;
            $.post(ajaxUrl,{action:'tao_formula_prod_gerar_om',nonce:nonce,orc_id:o.id},function(r){
                if(r.success){carregar();}else alert((r.data&&r.data.message)||'Erro');
            });
        }

        // ── Lista por fase do Pós-Vendas ──
        var GRUPOS=[
            {k:'aguardando',  t:'Aguardando Produção — aprovar a formulação', cor:'#f59e0b'},
            {k:'em_producao', t:'Em Produção — manipular',                    cor:'#2563eb'},
            {k:'outros',      t:'Outras fases',                               cor:'#94a3b8'}
        ];
        function carregar(){
            $.getJSON(ajaxUrl,{action:'tao_formula_prod_lista',nonce:nonce},function(r){
                if(!r.success){$('#taof-pd-lista').html('<p style="color:#dc2626">Erro ao carregar</p>');return;}
                var g=(r.data&&r.data.grupos)||{}, html='';
                GRUPOS.forEach(function(m){
                    var oms=g[m.k]||[]; if(!oms.length && m.k==='outros') return;
                    // agrupa as OMs por CARD (as fórmulas de um card são aprovadas juntas)
                    var porCard={}, ordem=[];
                    oms.forEach(function(o){ var c=o.card_id||('_'+o.id); if(!porCard[c]){porCard[c]=[]; ordem.push(c);} porCard[c].push(o); });
                    html+='<div class="taof-grp"><h3 style="border-color:'+m.cor+'">'+esc(m.t)+'<span class="cnt">'+ordem.length+' card(s) · '+oms.length+' OM(s)</span></h3>';
                    if(!oms.length){ html+='<div style="color:#94a3b8;font-size:12px;padding:2px 0">nenhuma OM nesta fase</div>'; }
                    ordem.forEach(function(cid){
                        var lista=porCard[cid], f=lista[0];
                        var cActs='';
                        if(m.k==='aguardando' && f.card_id) cActs+='<button class="button button-primary button-small taof-aprovar" data-card="'+esc(f.card_id)+'">✅ Aprovar formulação</button>';
                        if(m.k==='em_producao' && f.card_id) cActs+='<button class="button button-small taof-estornar" data-card="'+esc(f.card_id)+'">↩ Estornar</button>';
                        if(f.card_id && CARDURL) cActs+='<a class="button button-small" href="'+CARDURL.replace('__CID__',encodeURIComponent(f.card_id))+'" target="_blank">→ Card</a>';
                        html+='<div class="taof-cardgrp"><div class="taof-cardhd"><div><strong>'+esc(f.paciente_nome)+'</strong>'+
                            (f.whatsapp?' <small style="color:#64748b">📞 '+esc(f.whatsapp)+'</small>':'')+
                            ' <small style="color:#94a3b8">· '+lista.length+' fórmula'+(lista.length>1?'s':'')+' no card</small></div>'+
                            '<div class="acts">'+cActs+'</div></div>';
                        lista.forEach(function(o){
                            var vol=(o.volume?parseFloat(o.volume):'')+(o.unidade_vol?' '+o.unidade_vol:'');
                            var a='<button class="button button-small taof-abrir" data-id="'+esc(o.id)+'">🔬 Pesagem</button>'+
                                  '<button class="button button-small taof-ficha2" data-id="'+esc(o.id)+'">🖨 Ficha</button>'+
                                  '<button class="button button-small taof-rotulo2" data-id="'+esc(o.id)+'">🏷 Rótulo</button>'+
                                  '<button class="button button-small button-primary taof-concluir" data-id="'+esc(o.id)+'" data-nome="'+esc(o.numero)+'">✅ Concluir</button>';
                            html+='<div class="taof-om"><div class="info">'+(o.controlado?'<span class="taof-pd-ctrl">C</span> ':'')+
                                '<span class="num">OM '+esc(o.numero)+'</span> · '+esc(o.forma_farmac||'')+' '+esc(vol)+' · val '+fdata(o.dt_validade)+'</div>'+
                                '<div class="acts">'+a+'</div></div>';
                        });
                        html+='</div>';
                    });
                    html+='</div>';
                });
                $('#taof-pd-lista').html(html || '<p style="color:#94a3b8">Nenhuma OM aberta.</p>');
            });
        }

        // aprovar / estornar a formulação (reusa os handlers do card — move o card no Pós-Vendas)
        $(document).on('click','.taof-aprovar',function(){
            var card=$(this).data('card'); if(!card) return;
            if(!confirm('Aprovar a formulação? O card vai para "Em Produção".'))return;
            $.post(ajaxUrl,{action:'tao_formula_card_aprovar',nonce:nonce,card_id:card},function(r){
                if(r&&r.success) carregar(); else alert((r&&r.data&&(r.data.msg||r.data.message))||'Erro');
            },'json').fail(function(){alert('Falha na requisição');});
        });
        $(document).on('click','.taof-estornar',function(){
            var card=$(this).data('card'); if(!card) return;
            if(!confirm('Estornar a aprovação? O card volta para "Aguardando Produção".'))return;
            $.post(ajaxUrl,{action:'tao_formula_card_estornar',nonce:nonce,card_id:card},function(r){
                if(r&&r.success) carregar(); else alert((r&&r.data&&(r.data.msg||r.data.message))||'Erro');
            },'json').fail(function(){alert('Falha na requisição');});
        });
        $(document).on('click','.taof-ficha2', function(){ imprimirFicha($(this).data('id')); });
        $(document).on('click','.taof-rotulo2',function(){ imprimirRotulo($(this).data('id')); });
        $(document).on('click','.taof-abrir',  function(){ abrirOM($(this).data('id')); });

        // ── Concluir a OM = baixa de estoque explícita (única no fluxo com a chave desligada) ──
        $(document).on('click','.taof-concluir',function(){
            var id=$(this).data('id'), nome=$(this).data('nome');
            if(!confirm('Concluir a OM '+nome+'?\n\nIsto BAIXA o estoque dos lotes pesados (e escritura o SNGPC, se for controlado). Ação definitiva.'))return;
            $.post(ajaxUrl,{action:'tao_formula_prod_concluir_om',nonce:nonce,ordem_id:id},function(r){
                if(r&&r.success){ $('#taof-pd-modal').hide(); carregar(); }
                else alert((r&&r.data&&(r.data.msg||r.data.message))||'Erro ao concluir');
            },'json').fail(function(){alert('Falha na requisição');});
        });

        // ── Modal de pesagem (reusa prod_om) — sem "mover etapa" (a fase muda no funil) ──
        function abrirOM(id){
            $('#taof-pd-body').html('<p style="color:#94a3b8">Carregando…</p>'); $('#taof-pd-modal').show();
            $.getJSON(ajaxUrl,{action:'tao_formula_prod_om',nonce:nonce,ordem_id:id},function(r){
                if(!r.success){$('#taof-pd-body').html('<p style="color:#dc2626">Erro</p>');return;}
                var o=r.data.ordem, its=r.data.itens;
                var rows=its.map(function(it){
                    var loteSel;
                    if(it.eh_qsp){loteSel='<span style="color:#94a3b8">QSP</span>';}
                    else if(!it.lotes.length){loteSel='<span style="color:#dc2626;font-size:11px">sem lote aprovado</span>';}
                    else{loteSel='<select class="taof-pd-lote" data-it="'+it.id+'" style="font-size:12px;max-width:150px"><option value="">—</option>'+
                        it.lotes.map(function(l){return '<option value="'+l.id+'"'+(it.lote_mp_id===l.id?' selected':'')+'>'+esc(l.nr_lote)+' (val '+fdata(l.dt_validade)+', '+parseFloat(l.qtd_atual)+')</option>';}).join('')+'</select>';}
                    var pesar=it.eh_qsp?'QSP':(it.qtd_pesar!=null?'<strong>'+parseFloat(it.qtd_pesar)+' '+esc(it.unid_pesar||'g')+'</strong>':'—');
                    var corr=[]; if(it.teor_aplic&&it.teor_aplic!=100)corr.push('teor '+parseFloat(it.teor_aplic)+'%'); if(it.equiv_aplic&&it.equiv_aplic!=1)corr.push('equiv ×'+parseFloat(it.equiv_aplic)); if(it.diluicao_aplic&&it.diluicao_aplic!=1)corr.push('dil ×'+parseFloat(it.diluicao_aplic));
                    return '<tr><td><strong>'+esc(it.nome_ativo||it.descricao)+'</strong>'+(it.eh_qsp?' <small style="color:#94a3b8">(qsp)</small>':'')+
                        (it.descricao&&it.descricao!==it.nome_ativo?'<br><small style="color:#94a3b8">prescrição: '+esc(it.descricao)+'</small>':'')+'</td>'+
                        '<td style="text-align:right">'+(it.qtd_prescrita!=null?parseFloat(it.qtd_prescrita)+' '+esc(it.unidade||''):'—')+'</td>'+
                        '<td style="text-align:right">'+pesar+(corr.length?'<br><small style="color:#94a3b8">'+corr.join(' · ')+'</small>':'')+'</td>'+
                        '<td><input type="text" class="taof-pd-pesou" data-it="'+it.id+'" value="'+(it.qtd_pesada!=null?it.qtd_pesada:'')+'" style="width:70px;padding:3px 5px" placeholder="pesado"></td>'+
                        '<td>'+loteSel+'</td></tr>';
                }).join('');
                var blocoModo='<div style="margin:14px 0"><h3 style="font-size:13px;margin:0 0 4px">📋 Modo de preparo / precauções <small style="color:#94a3b8;font-weight:400">(RDC 67)</small></h3>'+
                    '<textarea id="taof-mp-txt" rows="3" style="width:100%;padding:6px;font-size:12px;border:1px solid #d1d5db;border-radius:4px;box-sizing:border-box" placeholder="Herdado da forma; ajuste se necessário">'+esc(o.modo_preparo||'')+'</textarea>'+
                    '<button class="button button-small taof-mp-salvar" data-om="'+o.id+'" style="margin-top:5px">💾 Salvar modo de preparo</button> <span id="taof-mp-msg" style="font-size:11px;margin-left:6px"></span></div>';
                var TPREC=['','notif_A','notif_B','especial_branca','receita_2vias','antimicrobiano'];
                var blocoCtrl=o.controlado?(
                    '<div style="margin:14px 0;padding:10px 12px;border:1px solid #fca5a5;background:#fef2f2;border-radius:6px">'+
                    '<h3 style="font-size:13px;margin:0 0 8px;color:#991b1b">🔒 Receita controlada (Portaria 344/98) — obrigatório para concluir</h3>'+
                    '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:12px">'+
                    '<div><label>Tipo de receita</label><select id="taof-rc-tp" style="width:100%;padding:4px">'+TPREC.map(function(t){return '<option value="'+t+'"'+(o.tp_receita===t?' selected':'')+'>'+(t||'—')+'</option>';}).join('')+'</select></div>'+
                    '<div><label>Nº notificação/receita</label><input id="taof-rc-nr" value="'+esc(o.nr_notificacao||'')+'" style="width:100%;padding:4px"></div>'+
                    '<div><label>Comprador</label><input id="taof-rc-comp" value="'+esc(o.comprador_nome||'')+'" style="width:100%;padding:4px"></div>'+
                    '<div style="display:flex;gap:6px"><div style="flex:1"><label>Doc</label><select id="taof-rc-doctp" style="width:100%;padding:4px">'+['RG','CPF','CNH'].map(function(t){return '<option'+(o.comprador_doc_tp===t?' selected':'')+'>'+t+'</option>';}).join('')+'</select></div>'+
                    '<div style="flex:2"><label>Nº doc</label><input id="taof-rc-docnr" value="'+esc(o.comprador_doc_nr||'')+'" style="width:100%;padding:4px"></div></div>'+
                    '</div>'+
                    '<button class="button button-small taof-rc-salvar" data-om="'+o.id+'" style="margin-top:8px">💾 Salvar dados da receita</button> <span id="taof-rc-msg" style="font-size:11px;margin-left:6px"></span>'+
                    '</div>'):'';
                $('#taof-pd-body').html(
                    '<h2 style="margin:0 0 2px;font-size:18px">OM '+esc(o.numero)+' — '+esc(o.paciente_nome)+(o.controlado?' <span style="font-size:11px;background:#fee2e2;color:#991b1b;padding:1px 7px;border-radius:10px;vertical-align:middle">🔒 Controlado</span>':'')+'</h2>'+
                    '<p style="margin:0 0 10px;color:#64748b;font-size:13px">'+esc(o.forma_farmac||'')+' '+(o.volume?parseFloat(o.volume)+esc(o.unidade_vol||''):'')+' · val. '+fdata(o.dt_validade)+(o.posologia?' · '+esc(o.posologia):'')+'</p>'+
                    '<h3 style="font-size:13px;margin:8px 0 4px">Ficha de Pesagem <small style="color:#94a3b8;font-weight:400">(ativo origem · quantidade a pesar · lote FEFO)</small></h3>'+
                    '<table class="taof-pd-it"><tr><th>Ativo (produto)</th><th>Dose prescr.</th><th>Qtd a pesar</th><th>Pesado</th><th>Lote usado</th></tr>'+rows+'</table>'+
                    blocoModo+blocoCtrl+
                    '<p style="margin:14px 0 0"><button class="button taof-ficha2" data-id="'+o.id+'">🖨 Ficha de Pesagem</button> <button class="button button-primary taof-rotulo2" data-id="'+o.id+'">🏷 Rótulo (RDC 67)</button> <button class="button taof-concluir" data-id="'+o.id+'" data-nome="'+esc(o.numero)+'">✅ Concluir OM</button> <button class="button" id="taof-pd-fechar">Fechar</button> <span id="taof-pd-msg" style="font-size:12px;margin-left:8px"></span></p>'+
                    '<p style="margin:8px 0 0;color:#94a3b8;font-size:11px">Concluir a OM <strong>baixa o estoque</strong> dos lotes pesados (+ SNGPC, se controlado) — a baixa só acontece quando você clica. Com a chave do fluxo novo ligada, mover o card para <strong>"Pronto para Entrega"</strong> também conclui.</p>'
                );
            });
        }
        $(document).on('change','.taof-pd-pesou',function(){salvarPesagem($(this).data('it'));});
        $(document).on('change','.taof-pd-lote', function(){salvarPesagem($(this).data('it'));});
        $(document).on('click','.taof-rc-salvar',function(){
            $.post(ajaxUrl,{action:'tao_formula_prod_receita_ctrl',nonce:nonce,ordem_id:$(this).data('om'),
                tp_receita:$('#taof-rc-tp').val(),nr_notificacao:$('#taof-rc-nr').val(),
                comprador_nome:$('#taof-rc-comp').val(),comprador_doc_tp:$('#taof-rc-doctp').val(),comprador_doc_nr:$('#taof-rc-docnr').val()},
            function(r){ $('#taof-rc-msg').css('color',r.success?'#16a34a':'#dc2626').text(r.success?'✓ salvo':((r.data&&r.data.message)||'erro')); setTimeout(function(){$('#taof-rc-msg').text('');},2500); });
        });
        $(document).on('click','.taof-mp-salvar',function(){
            $.post(ajaxUrl,{action:'tao_formula_prod_modo_preparo',nonce:nonce,ordem_id:$(this).data('om'),modo_preparo:$('#taof-mp-txt').val()},function(r){
                $('#taof-mp-msg').css('color',r.success?'#16a34a':'#dc2626').text(r.success?'✓ salvo':((r.data&&r.data.message)||'erro')); setTimeout(function(){$('#taof-mp-msg').text('');},2000); });
        });
        function salvarPesagem(itId){
            var qtd=$('.taof-pd-pesou[data-it="'+itId+'"]').val();
            var lote=$('.taof-pd-lote[data-it="'+itId+'"]').val()||'';
            $.post(ajaxUrl,{action:'tao_formula_prod_pesar',nonce:nonce,item_id:itId,qtd_pesada:qtd,lote_mp_id:lote},function(r){
                $('#taof-pd-msg').css('color',r.success?'#16a34a':'#dc2626').text(r.success?'✓ salvo':'erro'); setTimeout(function(){$('#taof-pd-msg').text('');},1500); });
        }
        $(document).on('click','#taof-pd-fechar',function(){$('#taof-pd-modal').hide();});
        $('#taof-pd-modal').on('click','.taof-pd-ov',function(){$('#taof-pd-modal').hide();});

        // ── Ficha de Manipulação — abre em MODAL (render server-side), imprime/exporta PDF ──
        function imprimirFicha(id){
            $('.taof-pd-box').css('max-width','900px');
            $('#taof-pd-body').html('<p style="color:#94a3b8;padding:30px;text-align:center">Carregando ficha…</p>');
            $('#taof-pd-modal').show();
            $.getJSON(ajaxUrl,{action:'tao_formula_prod_ficha',nonce:nonce,ordem_id:id},function(r){
                if(!r.success){ $('#taof-pd-body').html('<p style="color:#dc2626;padding:20px">'+((r.data&&r.data.message)||'Erro ao abrir a ficha')+'</p>'); return; }
                $('#taof-pd-body').html(
                    '<div class="taof-ficha-bar" style="display:flex;justify-content:flex-end;gap:8px;margin-bottom:12px">'+
                    '<button class="button button-primary" id="taof-ficha-print">🖨 Imprimir / Salvar PDF</button>'+
                    '<button class="button" id="taof-ficha-close">Fechar</button></div>'+ r.data.html );
            });
        }
        $(document).on('click','#taof-ficha-print',function(){
            // Janela própria só com a ficha (estilo A4 embutido) — window.print() na página imprimia o portal junto.
            var html=$('#taof-pd-body').clone(); html.find('.taof-ficha-bar').remove();
            var w=window.open('','taof_ficha','width=980,height=800');
            w.document.write('<html><head><title>Ficha de Manipulação</title></head><body onload="window.print()" style="margin:0">'+html.html()+'</body></html>');
            w.document.close();
        });
        $(document).on('click','#taof-ficha-close',function(){ $('#taof-pd-modal').hide(); $('.taof-pd-box').css('max-width',''); });
        // ── Rótulo RDC 67 imprimível ──
        function imprimirRotulo(id){
            $.getJSON(ajaxUrl,{action:'tao_formula_prod_rotulo',nonce:nonce,ordem_id:id},function(r){
                if(!r.success){alert((r.data&&r.data.message)||'Erro');return;}
                var d=r.data; var comp=(d.composicao||[]).map(function(c){return '<div>'+esc(c)+'</div>';}).join('');
                var html='<div style="font-family:Arial,sans-serif;font-size:12px;width:320px;border:1px solid #000;padding:10px;line-height:1.35">'+
                    '<div style="font-weight:bold;font-size:13px">'+esc(d.farmacia)+'</div>'+
                    '<div style="font-size:10px">CNPJ '+esc(d.cnpj)+' · '+esc(d.farm_end)+'</div>'+
                    '<div style="font-size:10px;border-bottom:1px solid #000;padding-bottom:4px;margin-bottom:4px">RT: '+esc(d.rt||'—')+'</div>'+
                    '<div><b>OM '+esc(d.om)+'</b> — <b>'+esc(d.advertencia)+'</b></div>'+
                    '<div>Paciente: <b>'+esc(d.paciente)+'</b></div>'+
                    (d.prescritor?'<div>Prescritor: '+esc(d.prescritor)+'</div>':'')+
                    '<div>Fórmula: '+esc(d.formula)+(d.qtd?' — '+esc(d.qtd)+' un':'')+'</div>'+
                    '<div style="margin:4px 0"><b>Composição:</b>'+comp+'</div>'+
                    (d.posologia?'<div><b>Posologia:</b> '+esc(d.posologia)+'</div>':'')+
                    '<div>Manipulado: '+fdata(d.dt_manip)+' · <b>Validade: '+fdata(d.validade)+'</b></div>'+
                    '<div style="font-size:10px;margin-top:3px">'+esc(d.conservacao)+'</div></div>';
                if(d.aviso) html='<p style="color:#d97706;font-size:12px">⚠ '+esc(d.aviso)+'</p>'+html;
                var w=window.open('','rotulo','width=420,height=560');
                w.document.write('<html><head><title>Rótulo OM '+esc(d.om)+'</title></head><body onload="window.print()" style="margin:12px">'+html+'</body></html>');
                w.document.close();
            });
        }

        carregar();
    });
    </script>
    </div>
    <?php
}
