<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Financeiro — Contas a Pagar COMPLETO (v2, 18/08/2026).
 * Todas as contas da farmácia: duplicatas de NF (automáticas) + lançamentos manuais
 * com categoria e parcelas. Baixa com data/forma/valor; dinheiro em sessão aberta
 * gera SANGRIA automática no Caixa. Sem a migration v2, degrada pro modo antigo.
 */
function tao_formula_page_contas_pagar() {
    if ( ! tao_formula_can_access() ) { echo '<p>Acesso negado.</p>'; return; }
    ?>
    <div class="wrap taof-wrap">
    <h1>💳 Contas a Pagar <small style="font-size:12px;color:#94a3b8;font-weight:400">(todas as contas — NF + lançamentos)</small></h1>

    <div style="margin:14px 0;display:flex;gap:8px;align-items:center;flex-wrap:wrap" class="taof-hide-print">
        <button type="button" class="button button-primary" id="taof-cp-nova" style="display:none">+ Nova conta</button>
        <select id="taof-cp-status" style="padding:6px 10px">
            <option value="aberto">Em aberto</option>
            <option value="pago">Pagas</option>
            <option value="">Todas</option>
        </select>
        <select id="taof-cp-cat" style="padding:6px 10px;display:none"><option value="">Todas as categorias</option></select>
        Venc. de <input type="date" id="taof-cp-de"> até <input type="date" id="taof-cp-ate">
        <button type="button" class="button" id="taof-cp-buscar">Filtrar</button>
        <button type="button" class="button" id="taof-cp-print">🖨 Relatório (contador)</button>
        <span id="taof-cp-tot" style="font-size:13px;color:#0f172a;font-weight:600"></span>
    </div>

    <div id="taof-cp-lista"><p style="color:#94a3b8">Carregando…</p></div>
    <div style="display:flex;gap:10px;align-items:center;justify-content:center;margin:12px 0;font-size:13px;flex-wrap:wrap" class="taof-hide-print">
        <label>Itens por página:
            <select id="taof-cp-size" style="padding:3px 6px"><option value="20">20</option><option value="30" selected>30</option><option value="50">50</option></select>
        </label>
        <button type="button" class="button button-small" id="taof-cp-prev">‹ Anterior</button>
        <span id="taof-cp-pg" style="color:#64748b">—</span>
        <button type="button" class="button button-small" id="taof-cp-next">Próxima ›</button>
    </div>

    <!-- Modal: nova conta / editar -->
    <div id="taof-cp-modal" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.45);z-index:99990">
      <div style="background:#fff;max-width:460px;margin:7vh auto;padding:20px 22px;border-radius:10px;box-shadow:0 20px 50px rgba(0,0,0,.25)">
        <h2 id="taof-cpm-titulo" style="margin:0 0 14px;font-size:16px">Nova conta</h2>
        <input type="hidden" id="taof-cpm-id">
        <div style="display:grid;gap:10px">
            <label>Descrição *<br><input type="text" id="taof-cpm-desc" style="width:100%" placeholder="ex.: Aluguel agosto, Energia, Contador"></label>
            <label>Credor / fornecedor<br><input type="text" id="taof-cpm-credor" style="width:100%" placeholder="ex.: Imobiliária Silva, Enel"></label>
            <div style="display:flex;gap:10px">
                <label style="flex:1">Categoria<br><select id="taof-cpm-cat" style="width:100%"></select></label>
                <label style="width:120px">Valor (R$) *<br><input type="number" id="taof-cpm-valor" step="0.01" min="0.01" style="width:100%"></label>
            </div>
            <div style="display:flex;gap:10px">
                <label style="flex:1">1º vencimento *<br><input type="date" id="taof-cpm-venc" style="width:100%"></label>
                <label style="width:120px" id="taof-cpm-parc-wrap">Parcelas<br><input type="number" id="taof-cpm-parc" value="1" min="1" max="60" style="width:100%" title="Valor informado é POR PARCELA; vencimentos mensais"></label>
            </div>
            <small id="taof-cpm-hint" style="color:#94a3b8">Parcelado? O valor é por parcela; os vencimentos seguem de mês em mês.</small>
        </div>
        <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px">
            <button type="button" class="button" id="taof-cpm-cancelar">Cancelar</button>
            <button type="button" class="button button-primary" id="taof-cpm-salvar">Salvar</button>
        </div>
      </div>
    </div>

    <!-- Modal: baixa (pagar) -->
    <div id="taof-cp-baixa" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.45);z-index:99990">
      <div style="background:#fff;max-width:420px;margin:9vh auto;padding:20px 22px;border-radius:10px;box-shadow:0 20px 50px rgba(0,0,0,.25)">
        <h2 style="margin:0 0 6px;font-size:16px">Registrar pagamento</h2>
        <p id="taof-cpb-info" style="margin:0 0 14px;color:#64748b;font-size:13px"></p>
        <input type="hidden" id="taof-cpb-id">
        <div style="display:grid;gap:10px">
            <div style="display:flex;gap:10px">
                <label style="flex:1">Data do pagamento<br><input type="date" id="taof-cpb-data" style="width:100%"></label>
                <label style="width:130px">Valor pago (R$)<br><input type="number" id="taof-cpb-valor" step="0.01" min="0.01" style="width:100%"></label>
            </div>
            <label>Forma de pagamento<br><select id="taof-cpb-forma" style="width:100%"></select></label>
            <small id="taof-cpb-hint" style="color:#94a3b8">Dinheiro com caixa aberto gera <b>sangria automática</b> na gaveta.</small>
        </div>
        <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px">
            <button type="button" class="button" id="taof-cpb-cancelar">Cancelar</button>
            <button type="button" class="button button-primary" id="taof-cpb-confirmar">✔ Confirmar pagamento</button>
        </div>
      </div>
    </div>

    <style>
    .taof-cp-tb{width:100%;border-collapse:collapse;background:#fff;border:1px solid #e2e8f0;font-size:13px}
    .taof-cp-tb th,.taof-cp-tb td{padding:6px 10px;border-bottom:1px solid #f1f5f9;text-align:left}
    .taof-cp-tb th{background:#f8fafc;font-size:11px;text-transform:uppercase;color:#64748b}
    .taof-cp-late{color:#dc2626;font-weight:600}
    .taof-cp-badge{font-size:10px;border-radius:9px;padding:1px 7px;font-weight:600}
    .taof-cp-b-nf{background:#eff6ff;color:#1d4ed8}
    .taof-cp-b-man{background:#f0fdf4;color:#15803d}
    .taof-cp-b-rec{background:#fef3c7;color:#b45309}
    .taof-cp-confv{background:#fef2f2;color:#b91c1c;font-size:10px;border-radius:9px;padding:1px 7px;font-weight:600}
    @media print{#adminmenumain,#wpadminbar,.taof-hide-print{display:none!important}}
    </style>

    <script>
    jQuery(function($){
        var ajaxUrl=taoFormula.ajaxUrl, nonce=taoFormula.nonce;
        var pg=0, sz=30, total=0, V2=false, CATS=[], FORMAS=[], ITENS=[];
        function esc(t){return $('<span>').text(t==null?'':t).html();}
        function money(n){return 'R$ '+parseFloat(n||0).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2});}
        function fdata(d){if(!d)return '—';var s=String(d).substring(0,10).split('-');return s.length===3?s[2]+'/'+s[1]+'/'+s[0]:d;}
        function hoje(){return new Date().toISOString().substring(0,10);}
        function badge(o){
            if(o==='manual') return '<span class="taof-cp-badge taof-cp-b-man">manual</span>';
            if(o==='recorrencia') return '<span class="taof-cp-badge taof-cp-b-rec">fixa</span>';
            return '<span class="taof-cp-badge taof-cp-b-nf">NF</span>';
        }
        function pager(){
            var paginas=Math.max(1,Math.ceil(total/sz));
            $('#taof-cp-pg').text('Página '+(pg+1)+' de '+paginas);
            $('#taof-cp-prev').prop('disabled',pg<=0);
            $('#taof-cp-next').prop('disabled',pg>=paginas-1);
        }
        function carregar(reset){
            if(reset){pg=0;}
            $.getJSON(ajaxUrl,{action:'tao_formula_cp_lista',nonce:nonce,status:$('#taof-cp-status').val(),
                categoria:$('#taof-cp-cat').val()||'',de:$('#taof-cp-de').val(),ate:$('#taof-cp-ate').val(),size:sz,offset:pg*sz},function(r){
                if(!r.success){$('#taof-cp-lista').html('<p style="color:#dc2626">Erro</p>');return;}
                V2=!!r.data.v2; CATS=r.data.categorias||[]; FORMAS=r.data.formas||[];
                var l=(r.data&&r.data.items)||[]; total=(r.data&&r.data.total)||0; ITENS=l;
                $('#taof-cp-nova').toggle(V2);
                if(V2 && $('#taof-cp-cat option').length<=1){
                    CATS.forEach(function(c){$('#taof-cp-cat').append($('<option>').val(c.id).text(c.nome));});
                }
                $('#taof-cp-cat').toggle(V2);
                $('#taof-cp-tot').text('Aberto: '+money(r.data.total_aberto)+'  ·  Pago: '+money(r.data.total_pago));
                if(!l.length){$('#taof-cp-lista').html('<p style="color:#94a3b8">Nenhuma conta no filtro.</p>');pager();return;}
                var rows=l.map(function(c){
                    var late=c.status==='aberto' && c.vencimento && c.vencimento<hoje();
                    var manual=(c.origem==='manual'||c.origem==='recorrencia');
                    var acoes='';
                    if(c.status==='pago'){
                        acoes='<button class="button button-small taof-cp-acao taof-hide-print" data-id="'+c.id+'" data-a="reabrir">reabrir</button>';
                    } else if(c.status==='aberto'){
                        acoes='<button class="button button-small button-primary taof-cp-pagar-btn taof-hide-print" data-id="'+c.id+'">✔ pagar</button>';
                        if(V2 && manual) acoes+=' <button class="button button-small taof-cp-edit taof-hide-print" data-id="'+c.id+'">✏</button>'+
                                               ' <button class="button button-small taof-cp-del taof-hide-print" data-id="'+c.id+'" style="color:#dc2626">🗑</button>';
                    }
                    var nome=esc(c.descricao||('Dup. '+(c.numero_dup||'—')));
                    if(c.confirmar_valor) nome+=' <span class="taof-cp-confv">confirmar valor</span>';
                    var parc=c.parcelas_total?(' <small style="color:#94a3b8">'+c.parcela_n+'/'+c.parcelas_total+'</small>'):'';
                    return '<tr'+(late?' class="taof-cp-late"':'')+'>'+
                        '<td>'+(V2?badge(c.origem)+' ':'')+nome+parc+'</td>'+
                        '<td>'+esc(c.fornecedor)+'</td>'+
                        (V2?'<td>'+esc(c.categoria||'—')+'</td>':'')+
                        '<td>'+fdata(c.vencimento)+(late?' ⚠':'')+'</td>'+
                        '<td style="text-align:right">'+money(c.valor)+'</td>'+
                        '<td>'+esc(c.status)+(c.dt_pagamento?' '+fdata(c.dt_pagamento):'')+
                            (c.status==='pago'&&c.valor_pago&&parseFloat(c.valor_pago)!==parseFloat(c.valor)?' <small style="color:#64748b">('+money(c.valor_pago)+')</small>':'')+'</td>'+
                        '<td class="taof-hide-print" style="white-space:nowrap">'+acoes+'</td></tr>';
                }).join('');
                $('#taof-cp-lista').html('<div style="overflow-x:auto"><table class="taof-cp-tb"><tr><th>Conta</th><th>Credor</th>'+
                    (V2?'<th>Categoria</th>':'')+'<th>Vencimento</th><th>Valor</th><th>Situação</th><th class="taof-hide-print"></th></tr>'+rows+'</table></div>');
                pager();
            });
        }
        $('#taof-cp-buscar,#taof-cp-status,#taof-cp-cat').on('click change',function(){carregar(true);});
        $('#taof-cp-print').on('click',function(){window.print();});
        $('#taof-cp-size').on('change',function(){sz=parseInt(this.value,10)||30;carregar(true);});
        $('#taof-cp-prev').on('click',function(){if(pg>0){pg--;carregar(false);}});
        $('#taof-cp-next').on('click',function(){pg++;carregar(false);});

        // ── Nova conta / editar ──────────────────────────────────────────────
        function abrirModal(c){
            $('#taof-cpm-id').val(c?c.id:'');
            $('#taof-cpm-titulo').text(c?'Editar conta':'Nova conta');
            $('#taof-cpm-desc').val(c?(c.descricao||''):'');
            $('#taof-cpm-credor').val(c?(c.fornecedor==='—'?'':c.fornecedor):'');
            $('#taof-cpm-valor').val(c?c.valor:'');
            $('#taof-cpm-venc').val(c?String(c.vencimento||'').substring(0,10):'');
            $('#taof-cpm-parc').val(1);
            $('#taof-cpm-parc-wrap,#taof-cpm-hint').toggle(!c);   // parcelas só na criação
            var $cat=$('#taof-cpm-cat').empty().append($('<option>').val('').text('— sem categoria —'));
            CATS.forEach(function(x){$cat.append($('<option>').val(x.id).text(x.nome));});
            if(c&&c.categoria_id)$cat.val(c.categoria_id);
            $('#taof-cp-modal').show();
            $('#taof-cpm-desc').focus();
        }
        $('#taof-cp-nova').on('click',function(){abrirModal(null);});
        $(document).on('click','.taof-cp-edit',function(){
            var id=$(this).data('id'), c=ITENS.find(function(x){return x.id===id;});
            if(c)abrirModal(c);
        });
        $('#taof-cpm-cancelar').on('click',function(){$('#taof-cp-modal').hide();});
        $('#taof-cpm-salvar').on('click',function(){
            var id=$('#taof-cpm-id').val();
            var dados={nonce:nonce,descricao:$('#taof-cpm-desc').val(),credor:$('#taof-cpm-credor').val(),
                       categoria_id:$('#taof-cpm-cat').val(),valor:$('#taof-cpm-valor').val(),vencimento:$('#taof-cpm-venc').val()};
            if(id){dados.action='tao_formula_cp_editar';dados.id=id;}
            else{dados.action='tao_formula_cp_nova';dados.parcelas=$('#taof-cpm-parc').val();}
            $.post(ajaxUrl,dados,function(r){
                if(!r.success){alert((r.data&&r.data.message)||'Erro');return;}
                $('#taof-cp-modal').hide();carregar(false);
            });
        });
        $(document).on('click','.taof-cp-del',function(){
            var id=$(this).data('id');
            if(!confirm('Excluir esta conta?'))return;
            $.post(ajaxUrl,{action:'tao_formula_cp_excluir',nonce:nonce,id:id},function(r){
                if(!r.success){alert((r.data&&r.data.message)||'Erro');return;}
                carregar(false);
            });
        });

        // ── Baixa ────────────────────────────────────────────────────────────
        $(document).on('click','.taof-cp-pagar-btn',function(){
            var id=$(this).data('id'), c=ITENS.find(function(x){return x.id===id;});
            if(!c)return;
            if(!V2){ // modo legado: baixa direta
                $.post(ajaxUrl,{action:'tao_formula_cp_pagar',nonce:nonce,id:id,acao:'pagar'},function(r){
                    if(r.success)carregar(false);else alert((r.data&&r.data.message)||'Erro');
                });
                return;
            }
            $('#taof-cpb-id').val(id);
            $('#taof-cpb-info').text((c.descricao||('Dup. '+(c.numero_dup||'')))+' — '+c.fornecedor+' · vence '+fdata(c.vencimento));
            $('#taof-cpb-data').val(hoje());
            $('#taof-cpb-valor').val(c.valor);
            var $f=$('#taof-cpb-forma').empty().append($('<option>').val('').text('— não informar —'));
            FORMAS.forEach(function(x){$f.append($('<option>').val(x.id).text(x.nome+(x.conta_no_dinheiro?' (gaveta)':'')));});
            $('#taof-cp-baixa').show();
        });
        $('#taof-cpb-cancelar').on('click',function(){$('#taof-cp-baixa').hide();});
        $('#taof-cpb-confirmar').on('click',function(){
            $.post(ajaxUrl,{action:'tao_formula_cp_pagar',nonce:nonce,id:$('#taof-cpb-id').val(),acao:'pagar',
                            data:$('#taof-cpb-data').val(),valor_pago:$('#taof-cpb-valor').val(),forma_id:$('#taof-cpb-forma').val()},function(r){
                if(!r.success){alert((r.data&&r.data.message)||'Erro');return;}
                $('#taof-cp-baixa').hide();
                if(r.data&&r.data.aviso)alert(r.data.aviso);
                carregar(false);
            });
        });
        $(document).on('click','.taof-cp-acao',function(){ // reabrir
            $.post(ajaxUrl,{action:'tao_formula_cp_pagar',nonce:nonce,id:$(this).data('id'),acao:$(this).data('a')},function(r){
                if(r.success)carregar(false);else alert((r.data&&r.data.message)||'Erro');
            });
        });
        carregar(true);
    });
    </script>
    </div>
    <?php
}
