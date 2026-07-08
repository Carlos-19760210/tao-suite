<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Produção — Ordem de Manipulação (Pacote 3 / Fatia A).
 * Kanban de etapas, geração de OM a partir do orçamento, pesagem c/ lote (rastreabilidade),
 * baixa de estoque na conclusão.
 */
function tao_formula_page_producao() {
    if ( ! tao_formula_can_access() ) { echo '<p>Acesso negado.</p>'; return; }
    ?>
    <div class="wrap taof-wrap">
    <h1>🧪 Produção — Ordens de Manipulação</h1>

    <div style="margin:14px 0;position:relative;max-width:520px">
        <input type="text" id="taof-pd-orc" class="regular-text" style="width:340px" placeholder="Gerar OM: buscar orçamento (nº ou paciente)…" autocomplete="off">
        <div id="taof-pd-orcdd" style="display:none;position:absolute;z-index:60;background:#fff;border:1px solid #cbd5e1;border-radius:6px;box-shadow:0 4px 14px rgba(0,0,0,.12);max-height:240px;overflow:auto;width:340px"></div>
    </div>

    <div id="taof-pd-kanban" class="taof-pd-kanban"><p style="color:#94a3b8">Carregando…</p></div>

    <div id="taof-pd-modal" style="display:none"><div class="taof-pd-ov"></div><div class="taof-pd-box"><div id="taof-pd-body"></div></div></div>

    <style>
    .taof-pd-kanban{display:flex;gap:10px;overflow-x:auto;padding-bottom:12px}
    .taof-pd-col{flex:0 0 220px;background:#f1f5f9;border-radius:8px;padding:8px;min-height:120px}
    .taof-pd-col h3{margin:2px 0 8px;font-size:12px;text-transform:uppercase;letter-spacing:.4px;color:#475569;display:flex;justify-content:space-between}
    .taof-pd-card{background:#fff;border:1px solid #e2e8f0;border-radius:7px;padding:8px 10px;margin-bottom:7px;cursor:pointer;font-size:13px}
    .taof-pd-card:hover{border-color:#94a3b8}
    .taof-pd-card .num{font-family:monospace;color:#94a3b8;font-size:11px}
    .taof-pd-ctrl{font-size:10px;background:#fee2e2;color:#991b1b;padding:0 5px;border-radius:8px}
    #taof-pd-modal .taof-pd-ov{position:fixed;inset:0;background:rgba(15,23,42,.5);z-index:9998}
    #taof-pd-modal .taof-pd-box{position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:#fff;border-radius:10px;padding:18px 20px;z-index:9999;width:640px;max-width:96vw;max-height:90vh;overflow:auto;box-shadow:0 10px 40px rgba(0,0,0,.25)}
    .taof-pd-it{width:100%;border-collapse:collapse;font-size:13px}
    .taof-pd-it th,.taof-pd-it td{padding:5px 8px;border-bottom:1px solid #f1f5f9;text-align:left}
    </style>

    <script>
    jQuery(function($){
        var ajaxUrl=taoFormula.ajaxUrl, nonce=taoFormula.nonce, timer=null, ETAPAS=[];
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

        // ── Kanban ──
        function carregar(){
            $.getJSON(ajaxUrl,{action:'tao_formula_prod_kanban',nonce:nonce},function(r){
                if(!r.success){$('#taof-pd-kanban').html('<p style="color:#dc2626">Erro</p>');return;}
                ETAPAS=r.data.etapas||[];
                var ordens=r.data.ordens||[];
                var porEtapa={}; ordens.forEach(function(o){(porEtapa[o.etapa_id]=porEtapa[o.etapa_id]||[]).push(o);});
                var cols=ETAPAS.map(function(e){
                    var cards=(porEtapa[e.id]||[]).map(function(o){
                        var vol=(o.volume?parseFloat(o.volume):'')+(o.unidade_vol?' '+o.unidade_vol:'');
                        return '<div class="taof-pd-card" data-id="'+o.id+'"><div><strong>'+esc(o.paciente_nome)+'</strong>'+(o.controlado?' <span class="taof-pd-ctrl">C</span>':'')+'</div>'+
                            '<div class="num">OM '+o.numero+' · '+esc(o.forma_farmac||'')+' '+esc(vol)+'</div>'+
                            '<div style="color:#94a3b8;font-size:11px">val. '+fdata(o.dt_validade)+'</div></div>';
                    }).join('');
                    return '<div class="taof-pd-col" data-etapa="'+e.id+'"><h3><span>'+esc(e.nome)+'</span><span>'+(porEtapa[e.id]||[]).length+'</span></h3>'+cards+'</div>';
                }).join('');
                $('#taof-pd-kanban').html(cols);
            });
        }

        // ── Detalhe / pesagem ──
        $(document).on('click','.taof-pd-card',function(){ abrirOM($(this).data('id')); });
        function abrirOM(id){
            $('#taof-pd-body').html('<p style="color:#94a3b8">Carregando…</p>'); $('#taof-pd-modal').show();
            $.getJSON(ajaxUrl,{action:'tao_formula_prod_om',nonce:nonce,ordem_id:id},function(r){
                if(!r.success){$('#taof-pd-body').html('<p style="color:#dc2626">Erro</p>');return;}
                var o=r.data.ordem, its=r.data.itens;
                var rows=its.map(function(it){
                    var loteSel;
                    if(it.eh_qsp){loteSel='<span style="color:#94a3b8">QSP</span>';}
                    else if(!it.lotes.length){loteSel='<span style="color:#dc2626;font-size:11px">sem lote aprovado</span>';}
                    else{
                        loteSel='<select class="taof-pd-lote" data-it="'+it.id+'" style="font-size:12px;max-width:150px"><option value="">—</option>'+
                            it.lotes.map(function(l){return '<option value="'+l.id+'"'+(it.lote_mp_id===l.id?' selected':'')+'>'+esc(l.nr_lote)+' (val '+fdata(l.dt_validade)+', '+parseFloat(l.qtd_atual)+')</option>';}).join('')+'</select>';
                    }
                    return '<tr><td>'+esc(it.descricao)+(it.eh_qsp?' <small style="color:#94a3b8">(qsp)</small>':'')+'</td>'+
                        '<td style="text-align:right">'+(it.qtd_prescrita!=null?parseFloat(it.qtd_prescrita)+' '+esc(it.unidade||''):'—')+'</td>'+
                        '<td><input type="text" class="taof-pd-pesou" data-it="'+it.id+'" value="'+(it.qtd_pesada!=null?it.qtd_pesada:'')+'" style="width:70px;padding:3px 5px" placeholder="pesado"></td>'+
                        '<td>'+loteSel+'</td></tr>';
                }).join('');
                var etapaBtns=ETAPAS.map(function(e){return '<button class="button button-small taof-pd-mover" data-om="'+o.id+'" data-etapa="'+e.id+'">'+esc(e.nome)+'</button>';}).join(' ');
                $('#taof-pd-body').html(
                    '<h2 style="margin:0 0 2px;font-size:18px">OM '+o.numero+' — '+esc(o.paciente_nome)+'</h2>'+
                    '<p style="margin:0 0 10px;color:#64748b;font-size:13px">'+esc(o.forma_farmac||'')+' '+(o.volume?parseFloat(o.volume)+esc(o.unidade_vol||''):'')+' · val. '+fdata(o.dt_validade)+(o.posologia?' · '+esc(o.posologia):'')+'</p>'+
                    '<h3 style="font-size:13px;margin:8px 0 4px">Pesagem &amp; lote <small style="color:#94a3b8;font-weight:400">(rastreabilidade — lote FEFO sugerido)</small></h3>'+
                    '<table class="taof-pd-it"><tr><th>Componente</th><th>Prescrito</th><th>Pesado</th><th>Lote usado</th></tr>'+rows+'</table>'+
                    '<h3 style="font-size:13px;margin:14px 0 4px">Mover para etapa</h3><div style="display:flex;gap:5px;flex-wrap:wrap">'+etapaBtns+'</div>'+
                    '<p style="margin:14px 0 0"><button class="button button-primary taof-pd-rotulo" data-om="'+o.id+'">🏷 Rótulo (RDC 67)</button> <button class="button" id="taof-pd-fechar">Fechar</button> <span id="taof-pd-msg" style="font-size:12px;margin-left:8px"></span></p>'
                );
            });
        }
        // salva pesagem ao editar
        $(document).on('change','.taof-pd-pesou',function(){salvarPesagem($(this).data('it'));});
        $(document).on('change','.taof-pd-lote',function(){salvarPesagem($(this).data('it'));});
        function salvarPesagem(itId){
            var qtd=$('.taof-pd-pesou[data-it="'+itId+'"]').val();
            var lote=$('.taof-pd-lote[data-it="'+itId+'"]').val()||'';
            $.post(ajaxUrl,{action:'tao_formula_prod_pesar',nonce:nonce,item_id:itId,qtd_pesada:qtd,lote_mp_id:lote},function(r){
                $('#taof-pd-msg').css('color',r.success?'#16a34a':'#dc2626').text(r.success?'✓ salvo':'erro');
                setTimeout(function(){$('#taof-pd-msg').text('');},1500);
            });
        }
        // mover etapa
        $(document).on('click','.taof-pd-mover',function(){
            var etapa=ETAPAS.filter(function(e){return e.id===$(this).data('etapa');})[0]||{};
            var fim=etapa.tipo==='final';
            if(fim && !confirm('Concluir a OM nesta etapa? Isso BAIXA o estoque dos lotes pesados.'))return;
            $.post(ajaxUrl,{action:'tao_formula_prod_mover',nonce:nonce,ordem_id:$(this).data('om'),etapa_id:$(this).data('etapa')},function(r){
                if(r.success){$('#taof-pd-modal').hide();carregar();
                    if(r.data.concluida) alert('OM concluída.'+(r.data.baixa!=null?' Estoque baixado em '+r.data.baixa+' item(ns).':''));
                }else alert((r.data&&r.data.message)||'Erro');
            });
        });
        $(document).on('click','#taof-pd-fechar',function(){$('#taof-pd-modal').hide();});
        $('#taof-pd-modal').on('click','.taof-pd-ov',function(){$('#taof-pd-modal').hide();});

        // ── Rótulo RDC 67 (janela imprimível) ──
        $(document).on('click','.taof-pd-rotulo',function(){
            $.getJSON(ajaxUrl,{action:'tao_formula_prod_rotulo',nonce:nonce,ordem_id:$(this).data('om')},function(r){
                if(!r.success){alert((r.data&&r.data.message)||'Erro');return;}
                var d=r.data;
                var comp=(d.composicao||[]).map(function(c){return '<div>'+esc(c)+'</div>';}).join('');
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
        });

        carregar();
    });
    </script>
    </div>
    <?php
}
