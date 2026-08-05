<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/** Controlados / SNGPC (Pacote 4) — livro de movimentos, lançamento, balanço e XML. */
function tao_formula_page_sngpc() {
    if ( ! tao_formula_can_access() ) { echo '<p>Acesso negado.</p>'; return; }
    ?>
    <div class="wrap taof-wrap">
    <h1>💊 Controlados / SNGPC <small style="font-size:12px;color:#94a3b8;font-weight:400">(escrituração Portaria 344/98 · RDC 27/2007)</small></h1>

    <div style="margin:14px 0;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        De <input type="date" id="taof-sg-de"> até <input type="date" id="taof-sg-ate">
        <select id="taof-sg-tipo" style="padding:6px 10px">
            <option value="">Todos os tipos</option>
            <option value="entrada">Entradas</option>
            <option value="saida">Saídas (dispensação)</option>
            <option value="perda">Perdas</option>
            <option value="transferencia">Transferências</option>
        </select>
        <button type="button" class="button" id="taof-sg-filtrar">Filtrar</button>
        <button type="button" class="button button-primary" id="taof-sg-novo">+ Lançar movimento</button>
        <button type="button" class="button" id="taof-sg-balanco">📊 Balanço</button>
        <button type="button" class="button" id="taof-sg-xml">📤 XML rascunho</button>
        <button type="button" class="button button-primary" id="taof-sg-fechar" title="Gera o XML no schema oficial da ANVISA e fecha o período. Não transmite.">🔒 Fechar período (oficial)</button>
        <button type="button" class="button" id="taof-sg-arquivos">📁 Arquivos</button>
        <button type="button" class="button" id="taof-sg-confronto" title="Compara o XML gerado com o do FCerta (sombra)">🔬 Confronto FCerta</button>
        <button type="button" class="button" id="taof-sg-print">🖨</button>
        <span id="taof-sg-count" style="font-size:12px;color:#64748b"></span>
    </div>

    <div id="taof-sg-area"><p style="color:#94a3b8">Carregando…</p></div>
    <div id="taof-sg-pagerbox" style="display:none;gap:10px;align-items:center;justify-content:center;margin:12px 0;font-size:13px;flex-wrap:wrap" class="taof-sg-noprint">
        <label>Itens por página:
            <select id="taof-sg-size" style="padding:3px 6px"><option value="20">20</option><option value="30" selected>30</option><option value="50">50</option></select>
        </label>
        <button type="button" class="button button-small" id="taof-sg-prev">‹ Anterior</button>
        <span id="taof-sg-pg" style="color:#64748b">—</span>
        <button type="button" class="button button-small" id="taof-sg-next">Próxima ›</button>
    </div>

    <div id="taof-sg-modal" style="display:none"><div class="taof-sg-ov"></div><div class="taof-sg-box"><div id="taof-sg-body"></div></div></div>

    <style>
    .taof-sg-tb{width:100%;border-collapse:collapse;background:#fff;border:1px solid #e2e8f0;font-size:12px}
    .taof-sg-tb th,.taof-sg-tb td{padding:6px 9px;border-bottom:1px solid #f1f5f9;text-align:left}
    .taof-sg-tb th{background:#f8fafc;font-size:11px;text-transform:uppercase;color:#64748b}
    .taof-sg-twrap{overflow-x:auto;min-width:0}
    .tp-entrada{color:#166534}.tp-saida{color:#1e40af}.tp-perda{color:#991b1b}.tp-transferencia{color:#92400e}
    #taof-sg-modal .taof-sg-ov{position:fixed;inset:0;background:rgba(15,23,42,.5);z-index:9998}
    #taof-sg-modal .taof-sg-box{position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:#fff;border-radius:10px;padding:18px 20px;z-index:9999;width:600px;max-width:96vw;max-height:92vh;overflow:auto;box-shadow:0 10px 40px rgba(0,0,0,.25)}
    .taof-sg-fld{margin-bottom:8px}.taof-sg-fld label{display:block;font-size:11px;color:#64748b;text-transform:uppercase;margin-bottom:2px}
    .taof-sg-fld input,.taof-sg-fld select{width:100%;padding:5px 8px;border:1px solid #d1d5db;border-radius:4px;font-size:13px}
    @media print{#adminmenumain,#wpadminbar,.taof-sg-noprint{display:none!important}}
    </style>

    <script>
    jQuery(function($){
        var ajaxUrl=taoFormula.ajaxUrl, nonce=taoFormula.nonce, timer=null;
        var pg=0, sz=30, total=0;
        function esc(t){return $('<span>').text(t==null?'':t).html();}
        function fdata(d){if(!d)return '—';var s=String(d).substring(0,10).split('-');return s.length===3?s[2]+'/'+s[1]+'/'+s[0]:d;}
        var hoje=new Date(),y=hoje.getFullYear(),m=('0'+(hoje.getMonth()+1)).slice(-2);
        $('#taof-sg-de').val(y+'-'+m+'-01'); $('#taof-sg-ate').val(y+'-'+m+'-'+('0'+hoje.getDate()).slice(-2));

        function pager(){
            var paginas=Math.max(1,Math.ceil(total/sz));
            $('#taof-sg-pg').text('Página '+(pg+1)+' de '+paginas);
            $('#taof-sg-prev').prop('disabled',pg<=0);
            $('#taof-sg-next').prop('disabled',pg>=paginas-1);
            $('#taof-sg-pagerbox').css('display','flex');
        }
        function carregar(reset){
            if(reset){pg=0;}
            $.getJSON(ajaxUrl,{action:'tao_formula_sngpc_lista',nonce:nonce,de:$('#taof-sg-de').val(),ate:$('#taof-sg-ate').val(),tipo:$('#taof-sg-tipo').val(),size:sz,offset:pg*sz},function(r){
                if(!r.success){$('#taof-sg-area').html('<p style="color:#dc2626">'+esc(r.data&&r.data.message||'Erro')+'</p>');return;}
                var l=(r.data&&r.data.items)||[]; total=(r.data&&r.data.total)||0; $('#taof-sg-count').text(total+' movimento(s)');
                if(!l.length){$('#taof-sg-area').html('<p style="color:#94a3b8">Nenhum movimento no período.</p>');pager();return;}
                var rows=l.map(function(x){
                    var extra=x.tipo==='saida'?(esc(x.comprador_nome||'')+(x.nr_notificacao?' · NR '+esc(x.nr_notificacao):'')):(x.tp_perda?esc(x.tp_perda):'');
                    return '<tr><td>'+fdata(x.dt_movimento)+'</td>'+
                        '<td class="tp-'+esc(x.tipo)+'"><strong>'+esc(x.tipo)+'</strong></td>'+
                        '<td>'+esc(x.ativo_nome)+' <small style="color:#94a3b8">'+esc(x.dcb||'')+'</small></td>'+
                        '<td>'+esc(x.classe_sngpc||'')+'</td>'+
                        '<td style="font-family:monospace">'+esc(x.nr_lote||'—')+'</td>'+
                        '<td style="text-align:right">'+parseFloat(x.quantidade)+' '+esc(x.unidade||'')+'</td>'+
                        '<td>'+esc(x.prescritor_nome||'')+'</td>'+
                        '<td>'+extra+'</td>'+
                        '<td style="text-align:center">'+(x.transmitido?'✓':'')+'</td></tr>';
                }).join('');
                $('#taof-sg-area').html('<div class="taof-sg-twrap"><table class="taof-sg-tb"><tr><th>Data</th><th>Tipo</th><th>Substância / DCB</th><th>Classe</th><th>Lote</th><th>Qtd</th><th>Prescritor</th><th>Comprador / Detalhe</th><th>Transm.</th></tr>'+rows+'</table></div>');
                pager();
            });
        }
        $('#taof-sg-filtrar,#taof-sg-tipo').on('click change',function(){carregar(true);});
        $('#taof-sg-print').on('click',function(){window.print();});
        $('#taof-sg-size').on('change',function(){sz=parseInt(this.value,10)||30;carregar(true);});
        $('#taof-sg-prev').on('click',function(){if(pg>0){pg--;carregar(false);}});
        $('#taof-sg-next').on('click',function(){pg++;carregar(false);});

        // ── Lançar movimento manual ──
        $('#taof-sg-novo').on('click',function(){
            var h='<h2 style="margin:0 0 12px;font-size:18px">Lançar movimento de controlado</h2><form id="taof-sg-form">'+
                '<div class="taof-sg-fld"><label>Tipo *</label><select name="tipo" id="taof-sg-t"><option value="entrada">Entrada (compra)</option><option value="saida">Saída (dispensação)</option><option value="perda">Perda</option><option value="transferencia">Transferência</option></select></div>'+
                '<div class="taof-sg-fld" style="position:relative"><label>Substância controlada *</label><input type="text" id="taof-sg-at" placeholder="buscar ativo controlado..." autocomplete="off"><input type="hidden" name="ativo_id" id="taof-sg-atid"><div id="taof-sg-atdd" style="display:none;position:absolute;z-index:60;background:#fff;border:1px solid #cbd5e1;border-radius:6px;box-shadow:0 4px 14px rgba(0,0,0,.12);max-height:200px;overflow:auto;width:100%"></div></div>'+
                '<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px">'+
                  '<div class="taof-sg-fld"><label>Quantidade *</label><input type="text" name="quantidade"></div>'+
                  '<div class="taof-sg-fld"><label>Lote</label><input type="text" name="nr_lote"></div>'+
                  '<div class="taof-sg-fld"><label>Data</label><input type="date" name="dt_movimento" value="'+$('#taof-sg-ate').val()+'"></div>'+
                '</div>'+
                '<div id="taof-sg-esp"></div>'+
                '<p style="margin:8px 0 0"><button type="submit" class="button button-primary">Lançar</button> <button type="button" class="button" id="taof-sg-cancel">Cancelar</button> <span id="taof-sg-msg" style="font-size:12px;margin-left:8px"></span></p></form>';
            $('#taof-sg-body').html(h); $('#taof-sg-modal').show(); campos();
            $('#taof-sg-t').on('change',campos);
            $('#taof-sg-cancel').on('click',function(){$('#taof-sg-modal').hide();});
            // busca ativo controlado
            $('#taof-sg-at').on('input',function(){
                var q=$(this).val().trim(); $('#taof-sg-atid').val('');
                if(q.length<2){$('#taof-sg-atdd').hide();return;}
                clearTimeout(timer);timer=setTimeout(function(){
                    $.getJSON(ajaxUrl,{action:'tao_formula_sngpc_busca_ativo',nonce:nonce,q:q},function(r){
                        var l=(r&&r.success&&r.data)?r.data:[]; var $dd=$('#taof-sg-atdd').empty();
                        if(!l.length){$dd.html('<div style="padding:6px 10px;color:#94a3b8;font-size:12px">nenhum controlado (marque o ativo como controlado no cadastro)</div>').show();return;}
                        l.forEach(function(a){$('<div style="padding:6px 10px;cursor:pointer;font-size:13px;border-bottom:1px solid #f1f5f9">')
                            .html(esc(a.nome)+' <small style="color:#94a3b8">'+esc(a.classe_sngpc||'')+'</small>')
                            .on('mousedown',function(e){e.preventDefault();$('#taof-sg-atid').val(a.id);$('#taof-sg-at').val(a.nome);$('#taof-sg-atdd').hide();}).appendTo($dd);});
                        $dd.show();
                    });
                },260);
            });
            $('#taof-sg-form').on('submit',function(e){
                e.preventDefault();
                if(!$('#taof-sg-atid').val()){alert('Selecione a substância controlada.');return;}
                var data=$(this).serializeArray().reduce(function(o,f){o[f.name]=f.value;return o;},{});
                data.action='tao_formula_sngpc_lancar'; data.nonce=nonce;
                $('#taof-sg-msg').css('color','#64748b').text('Lançando…');
                $.post(ajaxUrl,data,function(r){
                    if(r.success){$('#taof-sg-modal').hide();carregar();}else $('#taof-sg-msg').css('color','#dc2626').text((r.data&&r.data.message)||'Erro');
                });
            });
        });
        function campos(){
            var t=$('#taof-sg-t').val(), h='';
            if(t==='entrada')h='<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px"><div class="taof-sg-fld"><label>CNPJ fornecedor</label><input name="fornecedor_cnpj"></div><div class="taof-sg-fld"><label>Nota fiscal</label><input name="nf_numero"></div></div>';
            if(t==='saida')h='<div class="taof-sg-fld"><label>Tipo de receita</label><select name="tp_receita"><option value="">—</option><option>notif_A</option><option>notif_B</option><option>especial_branca</option><option>receita_2vias</option><option value="antimicrobiano">antimicrobiano</option></select></div>'+
                '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px"><div class="taof-sg-fld"><label>Nº notificação</label><input name="nr_notificacao"></div><div class="taof-sg-fld"><label>Prescritor</label><input name="prescritor_nome"></div></div>'+
                '<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px"><div class="taof-sg-fld"><label>Conselho</label><input name="prescritor_conselho" placeholder="CRM"></div><div class="taof-sg-fld"><label>Nº</label><input name="prescritor_nr"></div><div class="taof-sg-fld"><label>UF</label><input name="prescritor_uf"></div></div>'+
                '<div style="display:grid;grid-template-columns:2fr 1fr 1fr;gap:8px"><div class="taof-sg-fld"><label>Comprador</label><input name="comprador_nome"></div><div class="taof-sg-fld"><label>Documento</label><select name="comprador_doc_tp"><option>RG</option><option>CPF</option><option>CNH</option></select></div><div class="taof-sg-fld"><label>Nº doc</label><input name="comprador_doc_nr"></div></div>';
            if(t==='perda')h='<div class="taof-sg-fld"><label>Tipo de perda</label><select name="tp_perda"><option>quebra</option><option>vencimento</option><option>roubo</option><option>desvio</option><option>inutilizacao</option></select></div>';
            if(t==='transferencia')h='<div class="taof-sg-fld"><label>CNPJ destino</label><input name="cnpj_destino"></div>';
            $('#taof-sg-esp').html(h);
        }
        $('#taof-sg-modal').on('click','.taof-sg-ov',function(){$('#taof-sg-modal').hide();});

        // ── Balanço ──
        $('#taof-sg-balanco').on('click',function(){
            $.getJSON(ajaxUrl,{action:'tao_formula_sngpc_balanco',nonce:nonce,de:$('#taof-sg-de').val(),ate:$('#taof-sg-ate').val()},function(r){
                if(!r.success){alert('Erro');return;}
                var rows=(r.data.itens||[]).map(function(x){
                    return '<tr><td>'+esc(x.dcb||'')+'</td><td>'+esc(x.classe||'')+'</td>'+
                        '<td style="text-align:right;color:#166534">'+parseFloat(x.entrada).toLocaleString('pt-BR')+'</td>'+
                        '<td style="text-align:right;color:#1e40af">'+parseFloat(x.saida).toLocaleString('pt-BR')+'</td>'+
                        '<td style="text-align:right;color:#991b1b">'+parseFloat(x.perda).toLocaleString('pt-BR')+'</td>'+
                        '<td style="text-align:right;font-weight:700">'+parseFloat(x.saldo).toLocaleString('pt-BR')+' '+esc(x.unidade||'')+'</td></tr>';
                }).join('');
                $('#taof-sg-pagerbox').hide();
                $('#taof-sg-area').html('<h3>Balanço '+fdata(r.data.de)+' a '+fdata(r.data.ate)+' <small style="color:#94a3b8;font-weight:400">(BMPO — por substância)</small></h3>'+
                    '<div class="taof-sg-twrap"><table class="taof-sg-tb"><tr><th>Substância (DCB)</th><th>Classe</th><th>Entradas</th><th>Saídas</th><th>Perdas</th><th>Saldo</th></tr>'+(rows||'<tr><td colspan=6 style="color:#94a3b8">sem movimentos</td></tr>')+'</table></div>'+
                    '<p><button class="button" onclick="jQuery(\'#taof-sg-filtrar\').click()">← voltar ao livro</button></p>');
            });
        });

        // ── XML ──
        $('#taof-sg-xml').on('click',function(){
            if(!confirm('Gerar o XML dos movimentos ainda NÃO transmitidos? (você poderá marcar como transmitido depois de enviar à ANVISA)'))return;
            $.post(ajaxUrl,{action:'tao_formula_sngpc_xml',nonce:nonce,marcar:'0'},function(r){
                if(!r.success){alert((r.data&&r.data.message)||'Erro');return;}
                var w=window.open('','sngpcxml','width=760,height=640');
                w.document.write('<html><head><title>SNGPC — XML ('+r.data.movimentos+' mov.)</title></head><body style="margin:12px;font-family:monospace;font-size:12px"><p><b>'+r.data.movimentos+' movimento(s).</b> Salve/transmita à ANVISA e depois marque como transmitido no sistema.</p><pre style="white-space:pre-wrap;border:1px solid #ccc;padding:10px">'+$('<div>').text(r.data.xml).html()+'</pre></body></html>');
                w.document.close();
            });
        });

        // ── FASE A: Fechar período (XML oficial urn:sngpc-schema) ──
        $('#taof-sg-fechar').on('click',function(){
            if(!confirm('Fechar o período '+$('#taof-sg-de').val()+' a '+$('#taof-sg-ate').val()+' e gerar o XML OFICIAL (schema ANVISA)?\n\nNão transmite nada — apenas gera e arquiva para conferência/sombra.'))return;
            $.post(ajaxUrl,{action:'tao_formula_sngpc_fechar',nonce:nonce,de:$('#taof-sg-de').val(),ate:$('#taof-sg-ate').val()},function(r){
                if(!r.success){alert((r.data&&r.data.message)||'Erro');return;}
                var d=r.data;
                var w=window.open('','sngpcof','width=840,height=680');
                w.document.write('<html><head><title>SNGPC oficial — '+d.saidas+' saídas · '+d.perdas+' perdas</title></head><body style="margin:12px;font-family:monospace;font-size:12px">'+
                    '<p><b>Período fechado.</b> Saídas: '+d.saidas+' · Perdas: '+d.perdas+(d.entradas_pendentes?(' · <span style="color:#b45309">Entradas (bloco fora da Fase A): '+d.entradas_pendentes+'</span>'):'')+'<br>hash: '+d.hash+(d.sequencial?(' · sequencial: '+d.sequencial):'')+'</p>'+
                    (d.aviso?'<p style="color:#b45309">'+$('<div>').text(d.aviso).html()+'</p>':'')+
                    '<pre style="white-space:pre-wrap;border:1px solid #ccc;padding:10px">'+$('<div>').text(d.xml).html()+'</pre></body></html>');
                w.document.close();
                if(d.persistido)$('#taof-sg-arquivos').click();
            });
        });
        // ── Arquivos de período fechados ──
        $('#taof-sg-arquivos').on('click',function(){
            $.getJSON(ajaxUrl,{action:'tao_formula_sngpc_arquivos',nonce:nonce},function(r){
                if(!r.success){alert((r.data&&r.data.message)||'Erro');return;}
                var l=(r.data&&r.data.items)||[];
                var rows=l.map(function(x){
                    return '<tr><td>'+fdata(x.periodo_ini)+' a '+fdata(x.periodo_fim)+'</td><td style="font-family:monospace">'+esc(x.nome_arquivo||'')+'</td>'+
                        '<td style="text-align:right">'+(x.qtd_saidas||0)+'</td><td style="text-align:right">'+(x.qtd_perdas||0)+'</td>'+
                        '<td>'+esc(x.status||'')+'</td><td><a href="#" data-id="'+esc(x.id)+'" class="taof-sg-dl">baixar XML</a></td></tr>';
                }).join('');
                $('#taof-sg-pagerbox').hide();
                $('#taof-sg-area').html('<h3>Arquivos de período <small style="color:#94a3b8;font-weight:400">(SNGPC oficial — schema ANVISA)</small></h3>'+
                    '<div class="taof-sg-twrap"><table class="taof-sg-tb"><tr><th>Período</th><th>Arquivo</th><th>Saídas</th><th>Perdas</th><th>Status</th><th></th></tr>'+(rows||'<tr><td colspan=6 style="color:#94a3b8">nenhum período fechado ainda</td></tr>')+'</table></div>'+
                    '<p><button class="button" onclick="jQuery(\'#taof-sg-filtrar\').click()">← voltar ao livro</button></p>');
            });
        });
        $('#taof-sg-area').on('click','.taof-sg-dl',function(e){
            e.preventDefault(); var id=$(this).data('id');
            $.getJSON(ajaxUrl,{action:'tao_formula_sngpc_arquivo_baixar',nonce:nonce,id:id},function(r){
                if(!r.success){alert((r.data&&r.data.message)||'Erro');return;}
                var blob=new Blob([r.data.xml],{type:'application/xml'});
                var a=document.createElement('a');a.href=URL.createObjectURL(blob);a.download=(r.data.nome||'SNGPC')+'.xml';document.body.appendChild(a);a.click();a.remove();
            });
        });
        // ── Confronto com o FCerta (sombra) ──
        $('#taof-sg-confronto').on('click',function(){
            var h='<h2 style="margin:0 0 8px;font-size:18px">🔬 Confronto com o FCerta (sombra)</h2>'+
                '<p style="font-size:12px;color:#64748b;margin:0 0 8px">Gera o XML oficial do período <b>'+$('#taof-sg-de').val()+'</b> a <b>'+$('#taof-sg-ate').val()+'</b> e compara registro-a-registro com o XML real do FCerta.</p>'+
                '<div class="taof-sg-fld"><label>Cole aqui o XML do FCerta (mensagemSNGPC)</label><textarea id="taof-sg-fcxml" style="width:100%;height:170px;font-family:monospace;font-size:11px"></textarea></div>'+
                '<p><button type="button" class="button button-primary" id="taof-sg-confbtn">Comparar</button> <button type="button" class="button" id="taof-sg-cancel2">Fechar</button></p>'+
                '<div id="taof-sg-confres"></div>';
            $('#taof-sg-body').html(h);$('#taof-sg-modal').show();
            $('#taof-sg-cancel2').on('click',function(){$('#taof-sg-modal').hide();});
            $('#taof-sg-confbtn').on('click',function(){
                $('#taof-sg-confres').html('<p style="color:#64748b">comparando…</p>');
                $.post(ajaxUrl,{action:'tao_formula_sngpc_confronto',nonce:nonce,de:$('#taof-sg-de').val(),ate:$('#taof-sg-ate').val(),xml_fcerta:$('#taof-sg-fcxml').val()},function(r){
                    if(!r.success){$('#taof-sg-confres').html('<p style="color:#dc2626">'+esc((r.data&&r.data.message)||'Erro')+'</p>');return;}
                    var rows=(r.data.comparacao||[]).map(function(c){
                        var ok=c.so_gerado===0&&c.so_fcerta===0;
                        return '<tr><td>'+esc(c.tag)+'</td><td style="text-align:right">'+c.gerado+'</td><td style="text-align:right">'+c.fcerta+'</td>'+
                            '<td style="text-align:right;color:#166534">'+c.identicos+'</td><td style="text-align:right;color:#b45309">'+c.so_gerado+'</td><td style="text-align:right;color:#991b1b">'+c.so_fcerta+'</td>'+
                            '<td style="text-align:center">'+(ok?'✅':'⚠️')+'</td></tr>';
                    }).join('');
                    $('#taof-sg-confres').html('<table class="taof-sg-tb" style="margin-top:10px"><tr><th>Bloco</th><th>Gerado</th><th>FCerta</th><th>Idênticos</th><th>Só gerado</th><th>Só FCerta</th><th></th></tr>'+rows+'</table>');
                });
            });
        });

        carregar(true);
    });
    </script>
    </div>
    <?php
}
