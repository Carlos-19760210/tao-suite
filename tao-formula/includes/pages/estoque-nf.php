<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Estoque — Entrada de NF (Pacote 2 / Fatia 1).
 * Upload do XML da NF-e → conferência assistida (de-para + destino do valor) → efetivar.
 */
function tao_formula_page_estoque_nf() {
    if ( ! tao_formula_can_access() ) { echo '<p>Acesso negado.</p>'; return; }
    ?>
    <div class="wrap taof-wrap">
    <h1>📦 Estoque — Entrada de NF <small style="font-size:12px;color:#94a3b8;font-weight:400">(importação de nota fiscal de compra)</small></h1>

    <div style="margin:14px 0;padding:14px;border:2px dashed #cbd5e1;border-radius:10px;background:#f8fafc;max-width:560px">
        <label style="font-weight:600;display:block;margin-bottom:6px">Importar XML da NF-e</label>
        <input type="file" id="taof-nf-file" accept=".xml,text/xml">
        <button type="button" class="button button-primary" id="taof-nf-upload">Carregar</button>
        <span id="taof-nf-upmsg" style="font-size:12px;margin-left:8px"></span>
    </div>

    <div id="taof-nf-conf" style="display:none"></div>

    <h2 style="margin-top:22px">Entradas recentes</h2>
    <div id="taof-nf-lista"><p style="color:#94a3b8">Carregando…</p></div>
    <div style="display:flex;gap:10px;align-items:center;justify-content:center;margin:12px 0;font-size:13px;flex-wrap:wrap">
        <label>Itens por página:
            <select id="taof-nf-size" style="padding:3px 6px"><option value="20">20</option><option value="30" selected>30</option><option value="50">50</option></select>
        </label>
        <button type="button" class="button button-small" id="taof-nf-prev">‹ Anterior</button>
        <span id="taof-nf-pg" style="color:#64748b">—</span>
        <button type="button" class="button button-small" id="taof-nf-next">Próxima ›</button>
    </div>

    <style>
    .taof-nf-tb{width:100%;border-collapse:collapse;background:#fff;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;font-size:13px}
    .taof-nf-tb th,.taof-nf-tb td{padding:7px 10px;border-bottom:1px solid #f1f5f9;text-align:left}
    .taof-nf-tb th{background:#f8fafc;font-size:11px;text-transform:uppercase;color:#64748b}
    .taof-nf-twrap{overflow-x:auto;min-width:0}
    .taof-nf-assoc{border-color:#f97316!important;background:#fff7ed}
    .taof-nf-pill{font-size:11px;padding:1px 8px;border-radius:10px}
    .taof-nf-pill.ok{background:#dcfce7;color:#166534}.taof-nf-pill.pend{background:#fef3c7;color:#92400e}
    </style>

    <script>
    jQuery(function($){
        var ajaxUrl=taoFormula.ajaxUrl, nonce=taoFormula.nonce, NF=null;
        var pg=0, sz=30, total=0;
        function esc(t){return $('<span>').text(t==null?'':t).html();}
        function money(n){return 'R$ '+parseFloat(n||0).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2});}
        function fdata(d){if(!d)return '—';var p=String(d).substring(0,10).split('-');return p.length===3?p[2]+'/'+p[1]+'/'+p[0]:d;}

        // ── Upload ──
        $('#taof-nf-upload').on('click', function(){
            var f=$('#taof-nf-file')[0].files[0];
            if(!f){alert('Selecione o XML da NF-e.');return;}
            var fd=new FormData(); fd.append('action','tao_formula_nf_upload'); fd.append('nonce',nonce); fd.append('xml',f);
            var $m=$('#taof-nf-upmsg').css('color','#64748b').text('Lendo XML…');
            $.ajax({url:ajaxUrl,method:'POST',data:fd,processData:false,contentType:false}).done(function(r){
                if(!r.success){$m.css('color','#dc2626').text((r.data&&r.data.message)||'Erro');return;}
                $m.text(''); NF=r.data; renderConf();
            }).fail(function(){$m.css('color','#dc2626').text('Falha no upload');});
        });

        // ── Conferência ──
        function linhaItem(it,i){
            var a=it.ativo||{};
            var uc = it.unidade_compra || a.unidade || it.unidade || 'un';   // unidade de COMPRA (cadastro FCerta)
            // referência do cadastro: unidade de compra + última compra (venda NÃO é exibida aqui) + trocar ativo
            var gran = (it.ativo_id && it.ativo)
                ? '<br><small style="color:#64748b">compra <b>'+esc(uc)+'</b>'
                  + (parseFloat(a.preco_compra)>0 ? ' · últ. compra '+money(a.preco_compra)+'/'+esc(uc) : ' · sem compra ant.')
                  + ' · <a href="#" class="taof-nf-trocar" data-i="'+i+'" style="color:#2563eb;text-decoration:none">✎ trocar</a></small>'
                : '';
            var assoc = it.ativo_id ? '<span class="taof-nf-pill ok">'+esc(it.ativo? it.ativo.nome : 'associado')+'</span>'+gran
                                    : '<input type="text" class="taof-nf-search taof-nf-assoc" data-i="'+i+'" placeholder="buscar ativo..." style="width:150px;padding:4px 6px;border:1px solid #d1d5db;border-radius:4px"><div class="taof-nf-dd" data-i="'+i+'" style="display:none;position:absolute;z-index:50;background:#fff;border:1px solid #cbd5e1;border-radius:6px;box-shadow:0 4px 14px rgba(0,0,0,.12);max-height:220px;overflow:auto;min-width:260px"></div>';
            var dv=it.destino_valor||'compra';
            var sel='<select class="taof-nf-dv" data-i="'+i+'" style="padding:3px 4px;font-size:12px" title="Onde o valor pago atualiza no ativo">'+
                [['compra','→ compra'],['custo','→ custo'],['ambos','→ ambos']].map(function(o){return '<option value="'+o[0]+'"'+(dv===o[0]?' selected':'')+'>'+o[1]+'</option>';}).join('')+'</select>';
            var base='<strong>'+money(it.valor_compra_frete)+'</strong>'+
                (it.frete_rateado>0?'<br><small style="color:#94a3b8">compra '+money(it.valor_compra)+' + frete '+money(it.frete_rateado)+'</small>':'');
            return '<tr data-i="'+i+'">'+
                '<td style="font-family:monospace;color:#94a3b8">'+esc(it.cod_fornecedor)+'</td>'+
                '<td>'+esc(it.descr_xml)+'</td>'+
                '<td style="position:relative">'+assoc+'</td>'+
                '<td style="text-align:right;white-space:nowrap"><small style="color:#94a3b8">'+parseFloat(it.quantidade)+' '+esc(it.unidade)+' →</small><br>'+
                    '<input type="number" step="any" class="taof-nf-edit" data-i="'+i+'" data-campo="qtd_compra" value="'+parseFloat(it.qtd_compra!=null?it.qtd_compra:it.quantidade)+'" style="width:76px;text-align:right;padding:2px 4px"> '+esc(uc)+'</td>'+
                '<td style="text-align:right;white-space:nowrap"><strong>'+money(it.valor_prod)+'</strong>'+(parseFloat(it.desconto)>0?'<br><small style="color:#94a3b8">− desc '+money(it.desconto)+'</small>':'')+'</td>'+
                '<td style="text-align:right;white-space:nowrap"><input type="number" step="any" class="taof-nf-edit" data-i="'+i+'" data-campo="valor_compra" value="'+parseFloat(it.valor_compra||0)+'" style="width:88px;text-align:right;padding:2px 4px"><small style="color:#94a3b8">/'+esc(uc)+'</small></td>'+
                '<td style="white-space:nowrap"><input type="text" class="taof-nf-edit" data-i="'+i+'" data-campo="lote" value="'+esc(it.lote||'')+'" placeholder="lote" style="width:104px;padding:2px 4px"><br>'+
                    '<input type="date" class="taof-nf-edit" data-i="'+i+'" data-campo="dt_val" value="'+esc((it.dt_val||'').substring(0,10))+'" style="padding:1px 4px;font-size:11px" title="validade"></td>'+
                '<td style="text-align:right;white-space:nowrap">'+base+'<br>'+sel+'</td></tr>';
        }
        function renderConf(){
            var f=NF.fornecedor;
            var cnpjNF=esc(NF.cnpj_emitente||'');
            // Fornecedor é OBRIGATÓRIO e deve casar com o emitente da NF (achado por CNPJ → confere por construção)
            var fornBlock = f
                ? '<span class="taof-nf-pill ok">✓ '+esc(f.nome)+'</span> <small style="color:#16a34a">CNPJ confere com a NF</small>'
                : '<span style="color:#dc2626;font-weight:600">⛔ CNPJ '+cnpjNF+' não cadastrado como fornecedor</span> '+
                  '<button type="button" class="button button-small" id="taof-nf-cadforn">➕ Cadastrar fornecedor com os dados da NF</button>';
            var hoje=new Date().toISOString().slice(0,10);
            if(NF._dt_entrada==null) NF._dt_entrada=hoje;
            var head='<div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:12px 14px;margin:10px 0;line-height:1.9">'+
                '<strong>NF '+esc(NF.numero)+'/'+esc(NF.serie)+'</strong> · emissão '+fdata(NF.dt_emissao)+' · '+money(NF.valor_total)+'<br>'+
                'Emitente: <strong>'+esc(NF.razao||'')+'</strong> <small style="color:#64748b">CNPJ '+cnpjNF+'</small><br>'+
                'Fornecedor (TAO): '+fornBlock+'<br>'+
                '<label style="font-size:13px;font-weight:600">Data de entrada: <input type="date" id="taof-nf-dtentrada" value="'+esc(NF._dt_entrada)+'" style="padding:3px 6px;font-weight:400"></label>'+
                ' · '+NF.itens.length+' itens · '+ (NF.duplicatas.length)+' duplicata(s)'+
                (NF.valor_frete>0?' · frete '+money(NF.valor_frete)+' <small style="color:#94a3b8">(rateado por valor na base de venda)</small>':'')+'</div>';
            // banner de alertas de conformidade do fornecedor (RDC 67)
            var al=(NF.forn_alertas||[]);
            if(al.length){
                var temErro=al.some(function(a){return a.nivel==='erro';});
                var bg=temErro?'#fef2f2':'#fffbeb', bd=temErro?'#fecaca':'#fde68a', cor=temErro?'#991b1b':'#92400e';
                head+='<div style="background:'+bg+';border:1px solid '+bd+';border-radius:8px;padding:10px 14px;margin:0 0 10px;color:'+cor+';font-size:13px">'+
                    '<strong>'+(temErro?'⛔ Atenção no recebimento (RDC 67):':'⚠ Observações do fornecedor:')+'</strong><ul style="margin:6px 0 0;padding-left:18px">'+
                    al.map(function(a){return '<li>'+esc(a.msg)+'</li>';}).join('')+'</ul></div>';
            }
            var rows=NF.itens.map(linhaItem).join('');
            var tbl='<div class="taof-nf-twrap"><table class="taof-nf-tb"><tr><th>Cód. forn.</th><th>Descrição (XML)</th><th>Ativo TAO</th><th>Qtd</th><th style="text-align:right">Valor item (NF)</th><th style="text-align:right">Pago/un. compra</th><th>Lote</th><th style="text-align:right">Base venda (c/ frete) → destino</th></tr>'+rows+'</table></div>';
            var pend=NF.itens.filter(function(x){return !x.ativo_id;}).length;
            var podeEf = f && pend===0;
            var msg = !f ? '⛔ selecione/cadastre o fornecedor da NF para continuar' : (pend?('⚠ '+pend+' item(ns) sem ativo — associe ou troque'):'');
            var btn='<p style="margin:12px 0"><button type="button" class="button button-primary" id="taof-nf-efetivar" '+(podeEf?'':'disabled')+'>✔ Efetivar entrada</button> '+
                '<span id="taof-nf-efmsg" style="font-size:12px;margin-left:8px;color:#b45309">'+msg+'</span></p>';
            $('#taof-nf-conf').html('<h2>Conferência</h2>'+head+tbl+btn).show();
        }

        // busca de ativo p/ associar (grava a associação no efetivar)
        $(document).on('input','.taof-nf-search',function(){
            var $inp=$(this),i=$inp.data('i'),$dd=$('.taof-nf-dd[data-i="'+i+'"]');
            var q=$inp.val().trim(); if(q.length<2){$dd.hide();return;}
            clearTimeout($inp.data('t'));
            $inp.data('t',setTimeout(function(){
                $.getJSON(ajaxUrl,{action:'tao_formula_search_ativos',nonce:nonce,q:q,grupo:'M'},function(resp){
                    var lista=(resp&&resp.success&&resp.data)?resp.data:[]; $dd.empty();
                    if(!lista.length){$dd.html('<div style="padding:6px 10px;color:#94a3b8;font-size:12px">nada</div>').show();return;}
                    lista.forEach(function(a){
                        $('<div style="padding:6px 10px;cursor:pointer;font-size:13px;border-bottom:1px solid #f1f5f9">')
                          .html(esc(a.nome)+' <small style="color:#94a3b8">['+esc(a.codigo_fc||'')+']</small>')
                          .on('mousedown',function(e){e.preventDefault();
                              NF.itens[i].ativo_id=a.id; NF.itens[i].ativo={nome:a.nome,codigo_fc:a.codigo_fc};
                              renderConf();
                          }).appendTo($dd);
                    });
                    $dd.show();
                });
            },260));
        });
        $(document).on('blur','.taof-nf-search',function(){var i=$(this).data('i');setTimeout(function(){$('.taof-nf-dd[data-i="'+i+'"]').hide();},180);});
        $(document).on('change','.taof-nf-dv',function(){NF.itens[$(this).data('i')].destino_valor=this.value;});
        $(document).on('change','#taof-nf-dtentrada',function(){NF._dt_entrada=this.value;});
        // edição inline de item antes de efetivar (qtd de compra / valor pago / lote / validade)
        $(document).on('change','.taof-nf-edit',function(){
            var i=$(this).data('i'), campo=$(this).data('campo'), v=this.value, it=NF.itens[i];
            if(campo==='qtd_compra'){ it.qtd_compra=Math.max(0,parseFloat(v)||0); recalcItem(it); }
            else if(campo==='valor_compra'){ it.valor_compra=Math.max(0,parseFloat(v)||0); it._valor_manual=true; recalcItem(it); }
            else if(campo==='lote'){ it.lote=v; }
            else if(campo==='dt_val'){ it.dt_val=v; }
            renderConf();
        });
        // recalcula frete rateado + base de venda quando o atendente muda qtd ou valor do item
        function recalcItem(it){
            var q=parseFloat(it.qtd_compra)||1;
            if(!it._valor_manual){ var vp=parseFloat(it.valor_prod)||0, vd=parseFloat(it.desconto)||0; it.valor_compra=Math.round((vp-vd)/q*1e6)/1e6; }
            var ft=parseFloat(it.frete_item)||0;
            it.frete_rateado=Math.round(ft/q*1e6)/1e6;                 // frete por unidade acompanha a qtd editada
            it.valor_compra_frete=Math.round((parseFloat(it.valor_compra)+it.frete_rateado)*1e6)/1e6;
        }
        // trocar o ativo associado (auto-match do de-para pode estar errado) → reabre a busca
        $(document).on('click','.taof-nf-trocar',function(e){e.preventDefault();var i=$(this).data('i');NF.itens[i].ativo_id=null;NF.itens[i].ativo=null;renderConf();
            setTimeout(function(){$('.taof-nf-search[data-i="'+i+'"]').focus();},30);});

        // ── Cadastro rápido do fornecedor a partir do XML ──
        $(document).on('click','#taof-nf-cadforn',function(){
            var $b=$(this).prop('disabled',true).text('Cadastrando…');
            $.post(ajaxUrl,{action:'tao_formula_nf_forn_cadastrar',nonce:nonce,emitente:JSON.stringify(NF.emitente||{cnpj:NF.cnpj_emitente,razao_social:NF.razao})},function(r){
                if(r.success){ NF.fornecedor={id:r.data.id,nome:r.data.nome}; NF.forn_alertas=[{nivel:'aviso',msg:'Fornecedor recém-cadastrado — complete licenças (AFE/VISA) e qualificação no cadastro de Fornecedores.'}]; renderConf(); }
                else { $b.prop('disabled',false).text('➕ Cadastrar fornecedor com os dados da NF'); alert((r.data&&r.data.message)||'Erro ao cadastrar'); }
            }).fail(function(){$b.prop('disabled',false).text('➕ Cadastrar fornecedor com os dados da NF');alert('Falha na requisição');});
        });

        // ── Efetivar ──
        $(document).on('click','#taof-nf-efetivar',function(){
            var pend=NF.itens.filter(function(x){return !x.ativo_id;});
            if(pend.length){alert('Associe todos os itens antes de efetivar.');return;}
            var $b=$(this).prop('disabled',true), $m=$('#taof-nf-efmsg').css('color','#64748b').text('Efetivando…');
            if(!NF.fornecedor){alert('Selecione/cadastre o fornecedor da NF antes de efetivar.');return;}
            var payload={cnpj_emitente:NF.cnpj_emitente,chave_nfe:NF.chave_nfe,numero:NF.numero,serie:NF.serie,dt_emissao:NF.dt_emissao,dt_entrada:NF._dt_entrada,valor_total:NF.valor_total,fornecedor_id:NF.fornecedor?NF.fornecedor.id:null,itens:NF.itens,duplicatas:NF.duplicatas};
            $.post(ajaxUrl,{action:'tao_formula_nf_efetivar',nonce:nonce,payload:JSON.stringify(payload)},function(r){
                if(r.success){
                    $('#taof-nf-conf').hide().empty();
                    $('#taof-nf-file').val('');
                    $('#taof-nf-upmsg').css('color','#16a34a').text('✓ NF efetivada: '+r.data.itens+' itens, '+r.data.lotes+' lote(s), '+r.data.contas_pagar+' conta(s) a pagar, '+r.data.depara_aprendidos+' associação(ões) aprendida(s)'+((r.data.sngpc_entradas>0)?', '+r.data.sngpc_entradas+' entrada(s) SNGPC':''));
                    carregarLista(true);
                } else { $b.prop('disabled',false); $m.css('color','#dc2626').text((r.data&&r.data.message)||'Erro'); }
            }).fail(function(){$b.prop('disabled',false);$m.css('color','#dc2626').text('Falha na requisição');});
        });

        // ── Lista ──
        function pager(){
            var paginas=Math.max(1,Math.ceil(total/sz));
            $('#taof-nf-pg').text('Página '+(pg+1)+' de '+paginas);
            $('#taof-nf-prev').prop('disabled',pg<=0);
            $('#taof-nf-next').prop('disabled',pg>=paginas-1);
        }
        function carregarLista(reset){
            if(reset){pg=0;}
            $.getJSON(ajaxUrl,{action:'tao_formula_nf_lista',nonce:nonce,size:sz,offset:pg*sz},function(r){
                var l=(r&&r.success&&r.data&&r.data.items)?r.data.items:[]; total=(r&&r.data&&r.data.total)||0;
                if(!l.length){$('#taof-nf-lista').html('<p style="color:#94a3b8">Nenhuma entrada ainda.</p>');pager();return;}
                var rows=l.map(function(e){
                    return '<tr><td>'+fdata(e.dt_entrada)+'</td><td>'+esc(e.numero||'')+'/'+esc(e.serie||'')+'</td>'+
                        '<td style="font-family:monospace;font-size:12px">'+esc(e.cnpj_emitente||'')+'</td>'+
                        '<td style="text-align:right">'+money(e.valor_total)+'</td>'+
                        '<td><span class="taof-nf-pill '+(e.status==='efetivada'?'ok':'pend')+'">'+esc(e.status)+'</span></td></tr>';
                }).join('');
                $('#taof-nf-lista').html('<div class="taof-nf-twrap"><table class="taof-nf-tb"><tr><th>Entrada</th><th>NF</th><th>CNPJ emitente</th><th>Valor</th><th>Status</th></tr>'+rows+'</table></div>');
                pager();
            });
        }
        $('#taof-nf-size').on('change',function(){sz=parseInt(this.value,10)||30;carregarLista(true);});
        $('#taof-nf-prev').on('click',function(){if(pg>0){pg--;carregarLista(false);}});
        $('#taof-nf-next').on('click',function(){pg++;carregarLista(false);});
        carregarLista(true);
    });
    </script>
    </div>
    <?php
}
