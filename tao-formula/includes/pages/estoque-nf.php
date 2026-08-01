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

    <h2 style="margin-top:22px">Entradas de NF</h2>
    <div style="margin:8px 0;font-size:13px">
        <label>Fornecedor:
            <select id="taof-nf-forn-filtro" style="padding:3px 6px;min-width:220px"><option value="">Todos</option></select>
        </label>
    </div>
    <div id="taof-nf-lista"><p style="color:#94a3b8">Carregando…</p></div>
    <div id="taof-nf-detalhe" style="display:none;margin:12px 0;padding:14px;border:1px solid #cbd5e1;border-radius:10px;background:#fff"></div>
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
                        $('<div class="taof-nf-ac-item" style="padding:6px 10px;cursor:pointer;font-size:13px;border-bottom:1px solid #f1f5f9">')
                          .html(esc(a.nome)+' <small style="color:#94a3b8">['+esc(a.codigo_fc||'')+']</small>')
                          .data('ativo',a)
                          .on('mousedown',function(e){e.preventDefault();selAtivo(i,a);})
                          .appendTo($dd);
                    });
                    $dd.show();
                });
            },260));
        });
        // conversão de unidade — usa o mapa de Unidades de Medida (CRUD) vindo no upload;
        // fallback embutido se a tabela ainda estiver vazia. Espelha a PHP tao_formula_conv_unid.
        function convUnid(q,de,pa){
            de=(de||'').toUpperCase().trim(); pa=(pa||'').toUpperCase().trim();
            if(!de||!pa) return null; if(de===pa) return q;
            var M=(NF&&NF.unidades)||null;
            if(M&&M[de]&&M[pa]) return (M[de].dim===M[pa].dim && M[pa].f)? q*M[de].f/M[pa].f : null;
            var dims=[{KG:1000,G:1,GR:1,MG:.001,MCG:1e-6},{L:1000,LT:1000,ML:1},{MIL:1000,MILHEIRO:1000,MI:1000,MILH:1000,UN:1,UND:1,UNID:1,CAP:1,CAPS:1,CPR:1,COMP:1,PC:1}];
            for(var i=0;i<dims.length;i++){ if(dims[i][de]!=null&&dims[i][pa]!=null) return q*dims[i][de]/dims[i][pa]; }
            return null;
        }
        // associa o ativo escolhido ao item (guarda unidade + RECALCULA a qtd p/ a granularidade)
        function selAtivo(i,a){
            NF.itens[i].ativo_id=a.id;
            NF.itens[i].ativo={nome:a.nome,codigo_fc:a.codigo_fc,unidade:a.unidade,unidade_padrao:a.unidade_padrao,preco_compra:a.preco_compra};
            var it=NF.itens[i], uc=a.unidade||it.unidade;
            // converte da unidade da NF (qtd original) p/ a unidade de compra do ativo (menor granularidade)
            var q2=convUnid(parseFloat(it.quantidade)||0, it.unidade, uc);
            if(q2!=null && q2>0){ it.qtd_compra=Math.round(q2*1e4)/1e4; it.unidade_compra=uc; }
            else { it.qtd_compra=parseFloat(it.quantidade)||0; it.unidade_compra=it.unidade; }   // sem conversão possível
            recalcItem(it);
            renderConf();
        }
        // navegação por teclado no autocomplete (↑ ↓ Enter Esc)
        $(document).on('keydown','.taof-nf-search',function(e){
            var i=$(this).data('i'), $dd=$('.taof-nf-dd[data-i="'+i+'"]');
            if(!$dd.is(':visible'))return;
            var $items=$dd.find('.taof-nf-ac-item'); if(!$items.length)return;
            var idx=$items.index($items.filter('.hl'));
            if(e.key==='ArrowDown'){e.preventDefault();idx=Math.min(idx+1,$items.length-1);}
            else if(e.key==='ArrowUp'){e.preventDefault();idx=Math.max(idx-1,0);}
            else if(e.key==='Enter'){e.preventDefault();var $c=$items.filter('.hl');if($c.length)selAtivo(i,$c.data('ativo'));return;}
            else if(e.key==='Escape'){$dd.hide();return;}
            else return;
            $items.removeClass('hl').css('background','');
            var $sel=$items.eq(idx<0?0:idx).addClass('hl').css('background','#e0e7ff');
            if($sel.length&&$sel[0].scrollIntoView)$sel[0].scrollIntoView({block:'nearest'});
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
        var fornCarregado=false;
        function carregarLista(reset){
            if(reset){pg=0;}
            var fid=$('#taof-nf-forn-filtro').val()||'';
            $.getJSON(ajaxUrl,{action:'tao_formula_nf_lista',nonce:nonce,size:sz,offset:pg*sz,fornecedor_id:fid},function(r){
                var l=(r&&r.success&&r.data&&r.data.items)?r.data.items:[]; total=(r&&r.data&&r.data.total)||0;
                // popula o filtro de fornecedores (1x)
                if(!fornCarregado && r.data && r.data.fornecedores){
                    var opt='<option value="">Todos</option>'+r.data.fornecedores.map(function(f){return '<option value="'+esc(f.id)+'">'+esc(f.nome)+'</option>';}).join('');
                    $('#taof-nf-forn-filtro').html(opt); fornCarregado=true;
                }
                if(!l.length){$('#taof-nf-lista').html('<p style="color:#94a3b8">Nenhuma entrada.</p>');pager();return;}
                var rows=l.map(function(e){
                    var est=e.status==='estornada';
                    return '<tr'+(est?' style="opacity:.55"':'')+'><td>'+fdata(e.dt_entrada)+'</td><td>'+esc(e.numero||'')+'/'+esc(e.serie||'')+'</td>'+
                        '<td>'+esc(e.fornecedor_nome||'—')+'</td>'+
                        '<td style="text-align:right">'+money(e.valor_total)+'</td>'+
                        '<td><span class="taof-nf-pill '+(est?'pend':(e.status==='efetivada'?'ok':'pend'))+'">'+esc(e.status)+'</span></td>'+
                        '<td><a href="#" class="taof-nf-ver" data-id="'+esc(e.id)+'" style="color:#2563eb;text-decoration:none">🔎 detalhe</a></td></tr>';
                }).join('');
                $('#taof-nf-lista').html('<div class="taof-nf-twrap"><table class="taof-nf-tb"><tr><th>Entrada</th><th>NF</th><th>Fornecedor</th><th>Valor</th><th>Status</th><th></th></tr>'+rows+'</table></div>');
                pager();
            });
        }
        $('#taof-nf-size').on('change',function(){sz=parseInt(this.value,10)||30;carregarLista(true);});
        $('#taof-nf-forn-filtro').on('change',function(){carregarLista(true);$('#taof-nf-detalhe').hide().empty();});
        $('#taof-nf-prev').on('click',function(){if(pg>0){pg--;carregarLista(false);}});
        $('#taof-nf-next').on('click',function(){pg++;carregarLista(false);});

        // ── Detalhe da entrada (drill-down) ──
        $(document).on('click','.taof-nf-ver',function(e){e.preventDefault();
            var id=$(this).data('id'), $d=$('#taof-nf-detalhe').html('<p style="color:#64748b">Carregando detalhe…</p>').show();
            $.getJSON(ajaxUrl,{action:'tao_formula_nf_detalhe',nonce:nonce,id:id},function(r){
                if(!r.success){$d.html('<p style="color:#dc2626">'+((r.data&&r.data.message)||'Erro')+'</p>');return;}
                var c=r.data.cabecalho, its=r.data.itens||[], lotes=r.data.lotes||[], contas=r.data.contas||[];
                var estor=c.status==='estornada';
                var h='<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">'+
                    '<div><strong>NF '+esc(c.numero||'')+'/'+esc(c.serie||'')+'</strong> · '+esc(c.fornecedor_nome||'')+
                    ' <small style="color:#64748b">CNPJ '+esc(c.cnpj_emitente||'')+'</small><br>'+
                    '<small style="color:#64748b">emissão '+fdata(c.dt_emissao)+' · entrada '+fdata(c.dt_entrada)+' · '+money(c.valor_total)+' · <b>'+esc(c.status)+'</b></small></div>'+
                    '<div>'+(estor?'<span class="taof-nf-pill pend">estornada</span>':'<button type="button" class="button button-small taof-nf-laudos" data-id="'+esc(c.id)+'">📎 Importar laudos da NF</button> <button type="button" class="button button-small taof-nf-estornar" data-id="'+esc(c.id)+'">↩ Estornar entrada</button>')+
                    ' <button type="button" class="button button-small taof-nf-fechar-det">✕ Fechar</button></div></div>';
                var ir=its.map(function(x){var a=x.ativo||{};return '<tr><td>'+esc(a.nome||x.descr_xml||'')+' <small style="color:#94a3b8">'+esc(a.codigo_fc||'')+'</small></td>'+
                    '<td style="text-align:right">'+parseFloat(x.quantidade)+' '+esc(x.unidade||'')+'</td>'+
                    '<td style="text-align:right">'+money(x.valor_compra_frete||x.valor_compra)+'/'+esc(a.unidade||'un')+'</td>'+
                    '<td>'+esc(x.lote||'—')+(x.dt_val?' <small style="color:#94a3b8">val '+fdata(x.dt_val)+'</small>':'')+'</td></tr>';}).join('');
                h+='<div class="taof-nf-twrap" style="margin-top:10px"><table class="taof-nf-tb"><tr><th>Item</th><th>Qtd</th><th>Base venda</th><th>Lote</th></tr>'+ir+'</table></div>';
                if(lotes.length){var lr=lotes.map(function(l){return '<tr><td>'+esc(l.nr_lote)+'</td><td style="text-align:right">'+parseFloat(l.qtd_atual)+'/'+parseFloat(l.qtd_inicial)+' '+esc(l.unidade||'')+'</td><td>'+fdata(l.dt_validade)+'</td><td>'+esc(l.status)+'</td></tr>';}).join('');
                    h+='<p style="margin:10px 0 4px;font-weight:600">Lotes gerados</p><div class="taof-nf-twrap"><table class="taof-nf-tb"><tr><th>Lote</th><th>Saldo</th><th>Validade</th><th>Status</th></tr>'+lr+'</table></div>';}
                if(contas.length){var cr=contas.map(function(x){return '<tr><td>'+esc(x.numero_dup||'')+'</td><td>'+fdata(x.vencimento)+'</td><td style="text-align:right">'+money(x.valor)+'</td><td>'+esc(x.status)+'</td></tr>';}).join('');
                    h+='<p style="margin:10px 0 4px;font-weight:600">Contas a pagar</p><div class="taof-nf-twrap"><table class="taof-nf-tb"><tr><th>Dup.</th><th>Venc.</th><th>Valor</th><th>Status</th></tr>'+cr+'</table></div>';}
                $d.html(h);
            });
        });
        $(document).on('click','.taof-nf-fechar-det',function(){$('#taof-nf-detalhe').hide().empty();});
        $(document).on('click','.taof-nf-estornar',function(){
            var id=$(this).data('id');
            if(!confirm('Estornar esta entrada? Remove os lotes gerados, cancela as contas a pagar e as entradas SNGPC. Bloqueia se algo já foi consumido/pago/transmitido. Os preços do ativo NÃO são revertidos.'))return;
            var $b=$(this).prop('disabled',true).text('Estornando…');
            $.post(ajaxUrl,{action:'tao_formula_nf_estornar',nonce:nonce,id:id},function(r){
                if(r.success){alert('✓ '+r.data.message+' ('+r.data.lotes_removidos+' lote(s), '+r.data.contas_canceladas+' conta(s), '+r.data.sngpc_removidos+' SNGPC).\n\n'+r.data.obs);
                    $('#taof-nf-detalhe').hide().empty();carregarLista(false);}
                else{$b.prop('disabled',false).text('↩ Estornar entrada');alert((r.data&&r.data.message)||'Erro ao estornar');}
            }).fail(function(){$b.prop('disabled',false).text('↩ Estornar entrada');alert('Falha na requisição');});
        });
        // Importar laudos da NF: MOLDE do fornecedor (determinístico, sem IA) → IA como fallback
        $(document).on('click','.taof-nf-laudos',function(){
            var id=$(this).data('id');
            if($('#taof-nf-laudos-box').length){$('#taof-nf-laudos-box').remove();return;}
            $('#taof-nf-detalhe').append(
                '<div id="taof-nf-laudos-box" data-id="'+esc(id)+'" style="margin-top:12px;padding:12px;border:1px solid #cbd5e1;border-radius:8px;background:#f8fafc">'+
                '<strong>📎 Importar laudos da NF</strong> <small style="color:#64748b">— usa o molde do fornecedor (sem IA); PDF digitalizado ou sem molde cai na IA</small><br>'+
                '<input type="file" id="taof-nf-laudos-file" accept=".pdf,image/*" style="margin:8px 0"><br>'+
                '<button type="button" class="button button-primary" id="taof-nf-laudos-go">▶ Processar laudos</button> '+
                '<span id="taof-nf-laudos-msg" style="font-size:12px;margin-left:6px"></span></div>');
        });
        // pdf.js (lazy): extrai o TEXTO por página no navegador
        var _pdfjs=null;
        function carregarPdfJs(){ if(_pdfjs) return _pdfjs; _pdfjs=new Promise(function(res,rej){
            var s=document.createElement('script'); s.src='https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/pdf.min.js';
            s.onload=function(){ try{ pdfjsLib.GlobalWorkerOptions.workerSrc='https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/pdf.worker.min.js'; res(window.pdfjsLib); }catch(e){ rej(e); } };
            s.onerror=rej; document.head.appendChild(s); }); return _pdfjs; }
        function extrairTextoPDF(file){ return carregarPdfJs().then(function(){ return new Promise(function(res,rej){
            var fr=new FileReader(); fr.onload=function(){
                pdfjsLib.getDocument({data:new Uint8Array(fr.result)}).promise.then(function(pdf){
                    var pags=[], chain=Promise.resolve();
                    for(var i=1;i<=pdf.numPages;i++){ (function(n){ chain=chain.then(function(){ return pdf.getPage(n).then(function(p){ return p.getTextContent().then(function(tc){ pags[n-1]=tc.items.map(function(it){return it.str;}).join(' '); }); }); }); })(i); }
                    chain.then(function(){ res(pags); }).catch(rej);
                }).catch(rej);
            }; fr.onerror=rej; fr.readAsArrayBuffer(file);
        }); }); }
        // Fluxo IA (fallback): sobe o PDF e pergunta 1 lote por vez
        function processarLaudosIA(id,f,$b,$m){
            $m.css('color','#64748b').text('Subindo o PDF (IA)…');
            var fd=new FormData(); fd.append('action','tao_formula_nf_laudos_prep'); fd.append('nonce',nonce); fd.append('entrada_id',id); fd.append('laudo',f);
            $.ajax({url:ajaxUrl,method:'POST',data:fd,processData:false,contentType:false}).done(function(r){
                if(!r.success){$b.prop('disabled',false);$m.css('color','#dc2626').text((r.data&&r.data.message)||'Erro');return;}
                var fileId=r.data.file_id, laudoUrl=r.data.laudo_url, lotes=r.data.lotes||[];
                if(!lotes.length){$b.prop('disabled',false);$m.css('color','#b45309').text('Nenhum lote nesta NF.');return;}
                var i=0, apl=0, fora=0, sem=[];
                function fim(){ $.post(ajaxUrl,{action:'tao_formula_nf_laudos_cleanup',nonce:nonce,file_id:fileId}); $b.prop('disabled',false);
                    $m.css('color','#16a34a').text('✓ (IA) '+apl+' de '+lotes.length+' lote(s)'+(fora>0?' · ⚠ '+fora+' fora':'')+(sem.length?' · sem laudo: '+sem.join(', '):'')); }
                function proximo(){ if(i>=lotes.length){fim();return;} var lt=lotes[i];
                    $m.css('color','#64748b').text('IA '+(i+1)+'/'+lotes.length+' (lote '+lt.nr_lote+')…');
                    $.post(ajaxUrl,{action:'tao_formula_nf_laudo_lote_ia',nonce:nonce,lote_id:lt.id,file_id:fileId,laudo_url:laudoUrl,nr_lote:lt.nr_lote},function(rr){
                        if(rr.success&&rr.data.aplicado){apl++;if(rr.data.fora)fora++;} else sem.push(lt.nr_lote); i++;proximo();
                    }).fail(function(){sem.push(lt.nr_lote);i++;proximo();});
                }
                proximo();
            }).fail(function(){$b.prop('disabled',false);$m.css('color','#dc2626').text('Falha ao subir o PDF');});
        }
        $(document).on('click','#taof-nf-laudos-go',function(){
            var id=$('#taof-nf-laudos-box').data('id'), f=$('#taof-nf-laudos-file')[0].files[0];
            if(!f){$('#taof-nf-laudos-msg').css('color','#dc2626').text('Selecione o PDF dos laudos.');return;}
            var $b=$(this).prop('disabled',true), $m=$('#taof-nf-laudos-msg').css('color','#64748b').text('Lendo o PDF…');
            var isPdf = f.type==='application/pdf' || /\.pdf$/i.test(f.name||'');
            if(!isPdf){ processarLaudosIA(id,f,$b,$m); return; }   // imagem → IA
            extrairTextoPDF(f).then(function(pags){
                var chars=(pags.join('')||'').replace(/\s/g,'').length;
                if(chars<50){ $m.text('PDF digitalizado (sem texto) — usando IA…'); processarLaudosIA(id,f,$b,$m); return; }
                $m.text('Aplicando o molde do fornecedor…');
                $.post(ajaxUrl,{action:'tao_formula_nf_laudos_molde',nonce:nonce,entrada_id:id,paginas:JSON.stringify(pags)},function(r){
                    if(r&&r.success){ var d=r.data;
                        var msg='✓ Molde "'+d.molde+'": '+d.casados+' de '+d.laudos+' laudo(s) casados'+(d.fora>0?' · ⚠ '+d.fora+' fora da especificação':'');
                        if((d.lotes_sem_laudo||[]).length) msg+=' · lotes sem laudo: '+d.lotes_sem_laudo.join(', ');
                        if((d.sem_molde_match||[]).length) msg+=' · laudos sem lote na NF: '+d.sem_molde_match.join(', ');
                        $m.css('color', d.fora>0?'#b45309':'#16a34a').text(msg); $b.prop('disabled',false);
                    } else if(r&&r.data&&r.data.code==='sem_molde'){ $m.text('Sem molde p/ este fornecedor — usando IA…'); processarLaudosIA(id,f,$b,$m); }
                    else { $m.css('color','#dc2626').text((r&&r.data&&r.data.message)||'Falha.'); $b.prop('disabled',false); }
                }).fail(function(){ $m.css('color','#dc2626').text('Falha ao aplicar o molde.'); $b.prop('disabled',false); });
            }).catch(function(){ $m.text('Não consegui ler o PDF — usando IA…'); processarLaudosIA(id,f,$b,$m); });
        });
        carregarLista(true);
    });
    </script>
    </div>
    <?php
}
