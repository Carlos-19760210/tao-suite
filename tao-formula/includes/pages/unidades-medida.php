<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Cadastro — Unidades de Medida (CRUD). Padroniza as unidades (fim do texto livre)
 * e alimenta a conversão da Entrada de NF (fator por dimensão).
 */
function tao_formula_page_unidades_medida() {
    if ( ! tao_formula_can_access() ) { echo '<p>Acesso negado.</p>'; return; }
    ?>
    <div class="wrap taof-wrap">
    <h1>📏 Unidades de Medida <small style="font-size:12px;color:#94a3b8;font-weight:400">(padrão para compra/venda + conversão)</small></h1>
    <p style="color:#475569;max-width:760px">Cadastre as unidades usadas nos produtos. A <b>dimensão</b> (massa/volume/contagem) e o <b>fator para a base</b> definem a conversão automática na Entrada de NF (ex.: KG=1000, G=1 na massa → 1 KG = 1000 G).</p>

    <div style="margin:12px 0;padding:12px;border:1px solid #cbd5e1;border-radius:10px;background:#f8fafc;max-width:720px">
        <strong id="um-form-titulo">➕ Nova unidade</strong>
        <input type="hidden" id="um-id">
        <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:end;margin-top:8px">
            <div><label style="font-size:11px;color:#64748b;display:block">Sigla</label><input id="um-sigla" style="width:80px;padding:5px;text-transform:uppercase"></div>
            <div><label style="font-size:11px;color:#64748b;display:block">Nome</label><input id="um-nome" style="width:180px;padding:5px"></div>
            <div><label style="font-size:11px;color:#64748b;display:block">Dimensão</label>
                <select id="um-dim" style="padding:5px"><option value="massa">massa</option><option value="volume">volume</option><option value="contagem">contagem</option></select></div>
            <div><label style="font-size:11px;color:#64748b;display:block">Fator p/ a base</label><input id="um-fator" type="number" step="any" value="1" style="width:110px;padding:5px"></div>
            <div><label style="font-size:11px;color:#64748b;display:block">&nbsp;</label><label style="font-size:13px"><input type="checkbox" id="um-ativo" checked> ativo</label></div>
            <button type="button" class="button button-primary" id="um-salvar">Salvar</button>
            <button type="button" class="button" id="um-cancelar" style="display:none">Cancelar</button>
            <span id="um-msg" style="font-size:12px"></span>
        </div>
        <p style="font-size:11px;color:#94a3b8;margin:8px 0 0">Base de cada dimensão = fator 1 (massa: G · volume: ML · contagem: UN). Ex.: KG=1000, MG=0,001, LT=1000, MI (milheiro)=1000.</p>
    </div>

    <button type="button" class="button" id="um-seed" style="margin-bottom:10px">⚙ Criar unidades padrão (o que faltar)</button>
    <div id="um-lista">carregando…</div>
    </div>

    <script>
    jQuery(function($){
        var ajaxUrl=taoFormula.ajaxUrl, nonce=taoFormula.nonce;
        function esc(s){ return String(s==null?'':s).replace(/[&<>]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;'}[c];}); }
        function limpar(){ $('#um-id').val(''); $('#um-sigla').val(''); $('#um-nome').val(''); $('#um-dim').val('massa'); $('#um-fator').val('1'); $('#um-ativo').prop('checked',true);
            $('#um-form-titulo').text('➕ Nova unidade'); $('#um-cancelar').hide(); $('#um-msg').text(''); }
        $('#um-cancelar').on('click',limpar);
        $('#um-salvar').on('click',function(){
            var $m=$('#um-msg').css('color','#64748b').text('Salvando…');
            $.post(ajaxUrl,{action:'tao_formula_unidade_salvar',nonce:nonce,id:$('#um-id').val(),sigla:$('#um-sigla').val(),nome:$('#um-nome').val(),dimensao:$('#um-dim').val(),fator_base:$('#um-fator').val(),ativo:$('#um-ativo').is(':checked')?'1':'0'},function(r){
                if(r&&r.success){ $m.css('color','#16a34a').text('✓ salvo'); limpar(); carregar(); }
                else $m.css('color','#dc2626').text((r&&r.data&&r.data.message)||'Falha.');
            });
        });
        $('#um-seed').on('click',function(){ var $b=$(this).prop('disabled',true);
            $.post(ajaxUrl,{action:'tao_formula_unidades_seed',nonce:nonce},function(r){ $b.prop('disabled',false);
                if(r&&r.success){ $('#um-msg').css('color','#16a34a').text('✓ '+r.data.criadas+' unidade(s) criada(s)'); carregar(); } }); });
        function editar(u){ $('#um-id').val(u.id); $('#um-sigla').val(u.sigla); $('#um-nome').val(u.nome); $('#um-dim').val(u.dimensao); $('#um-fator').val(u.fator_base); $('#um-ativo').prop('checked',!!u.ativo);
            $('#um-form-titulo').text('✎ Editar unidade'); $('#um-cancelar').show(); $('html,body').animate({scrollTop:0},200); }
        function carregar(){
            $.post(ajaxUrl,{action:'tao_formula_unidades_lista',nonce:nonce},function(r){
                if(!r||!r.success){ $('#um-lista').text('Erro.'); return; }
                var us=r.data||[]; if(!us.length){ $('#um-lista').html('<p style="color:#64748b">Nenhuma unidade. Clique em “Criar unidades padrão”.</p>'); return; }
                var byd={}; us.forEach(function(u){ (byd[u.dimensao]=byd[u.dimensao]||[]).push(u); });
                var h='';
                ['massa','volume','contagem'].forEach(function(dim){ if(!byd[dim])return;
                    h+='<h3 style="margin:14px 0 4px;text-transform:capitalize">'+dim+' <small style="color:#94a3b8;font-weight:400">(base = fator 1)</small></h3>';
                    h+='<table class="widefat" style="max-width:640px"><thead><tr><th>Sigla</th><th>Nome</th><th>Fator</th><th>Ativo</th><th></th></tr></thead><tbody>';
                    byd[dim].forEach(function(u){ h+='<tr><td><b>'+esc(u.sigla)+'</b></td><td>'+esc(u.nome)+'</td><td>'+u.fator_base+'</td><td>'+(u.ativo?'sim':'não')+'</td>'+
                        '<td><a href="#" class="um-ed" data-u=\''+esc(JSON.stringify(u))+'\'>editar</a> · <a href="#" class="um-del" data-id="'+esc(u.id)+'" style="color:#dc2626">excluir</a></td></tr>'; });
                    h+='</tbody></table>';
                });
                $('#um-lista').html(h);
            });
        }
        $(document).on('click','.um-ed',function(e){ e.preventDefault(); editar(JSON.parse($(this).attr('data-u'))); });
        $(document).on('click','.um-del',function(e){ e.preventDefault(); if(!confirm('Excluir esta unidade?'))return;
            $.post(ajaxUrl,{action:'tao_formula_unidade_excluir',nonce:nonce,id:$(this).data('id')},function(){ carregar(); }); });
        carregar();
    });
    </script>
    <?php
}
