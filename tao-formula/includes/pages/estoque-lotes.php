<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Estoque — Lotes & Saldo (Pacote 2 / Fatia 2).
 * CQ de recebimento (RDC 67), saldo por lote, inventário (ajuste) e kardex.
 */
function tao_formula_page_estoque_lotes() {
    if ( ! tao_formula_can_access() ) { echo '<p>Acesso negado.</p>'; return; }
    ?>
    <div class="wrap taof-wrap">
    <h1>📦 Estoque — Lotes &amp; Saldo</h1>

    <div style="margin:14px 0;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <select id="taof-lt-status" style="padding:6px 10px">
            <option value="">Todos os status</option>
            <option value="quarentena">Quarentena (aguardando CQ)</option>
            <option value="aprovado">Aprovado</option>
            <option value="reprovado">Reprovado</option>
            <option value="vencido">Vencido</option>
            <option value="esgotado">Esgotado</option>
        </select>
        <input type="text" id="taof-lt-q" placeholder="buscar ativo ou lote…" style="width:240px;padding:6px 10px">
        <span id="taof-lt-count" style="font-size:12px;color:#64748b"></span>
    </div>

    <div id="taof-lt-lista"><p style="color:#94a3b8">Carregando…</p></div>

    <!-- modal kardex -->
    <div id="taof-lt-modal" style="display:none">
        <div class="taof-lt-ov"></div><div class="taof-lt-box"><div id="taof-lt-body"></div></div>
    </div>

    <style>
    .taof-lt-tb{width:100%;border-collapse:collapse;background:#fff;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;font-size:13px}
    .taof-lt-tb th,.taof-lt-tb td{padding:7px 10px;border-bottom:1px solid #f1f5f9;text-align:left}
    .taof-lt-tb th{background:#f8fafc;font-size:11px;text-transform:uppercase;color:#64748b}
    .taof-lt-twrap{overflow-x:auto;min-width:0}
    .taof-lt-pill{font-size:11px;padding:1px 8px;border-radius:10px}
    .st-quarentena{background:#fef3c7;color:#92400e}.st-aprovado{background:#dcfce7;color:#166534}
    .st-reprovado{background:#fee2e2;color:#991b1b}.st-vencido{background:#e5e7eb;color:#374151}.st-esgotado{background:#e5e7eb;color:#374151}
    #taof-lt-modal .taof-lt-ov{position:fixed;inset:0;background:rgba(15,23,42,.5);z-index:9998}
    #taof-lt-modal .taof-lt-box{position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:#fff;border-radius:10px;padding:18px 20px;z-index:9999;width:520px;max-width:96vw;max-height:88vh;overflow:auto;box-shadow:0 10px 40px rgba(0,0,0,.25)}
    </style>

    <script>
    jQuery(function($){
        var ajaxUrl=taoFormula.ajaxUrl, nonce=taoFormula.nonce, timer=null;
        function esc(t){return $('<span>').text(t==null?'':t).html();}
        function fdata(d){if(!d)return '—';var p=String(d).substring(0,10).split('-');return p.length===3?p[2]+'/'+p[1]+'/'+p[0]:d;}
        function venceProx(d){if(!d)return false;var dt=new Date(d),h=new Date();return (dt-h)/(864e5) < 90;}

        function carregar(){
            $.getJSON(ajaxUrl,{action:'tao_formula_estq_lotes',nonce:nonce,status:$('#taof-lt-status').val(),q:$('#taof-lt-q').val().trim()},function(r){
                var l=(r&&r.success&&r.data)?r.data:[];
                $('#taof-lt-count').text(l.length+' lote(s)');
                if(!l.length){$('#taof-lt-lista').html('<p style="color:#94a3b8">Nenhum lote.</p>');return;}
                var rows=l.map(function(x){
                    var venc=venceProx(x.dt_validade)&&x.status==='aprovado'?' style="color:#dc2626;font-weight:600"':'';
                    var acoes='';
                    if(x.status==='quarentena') acoes='<button class="button button-small taof-lt-aprovar" data-id="'+x.id+'">✔ Aprovar</button> <button class="button button-small taof-lt-reprovar" data-id="'+x.id+'">✖</button>';
                    acoes+=' <button class="button button-small taof-lt-ajuste" data-id="'+x.id+'" data-qt="'+x.qtd_atual+'" title="Inventário">⚖</button>';
                    acoes+=' <button class="button button-small taof-lt-kardex" data-aid="'+x.ativo_id+'" data-nome="'+esc(x.ativo_nome)+'">↔</button>';
                    return '<tr><td><strong>'+esc(x.ativo_nome)+'</strong> <small style="color:#94a3b8">'+esc(x.codigo_fc||'')+'</small></td>'+
                        '<td style="font-family:monospace">'+esc(x.nr_lote)+'</td>'+
                        '<td'+venc+'>'+fdata(x.dt_validade)+'</td>'+
                        '<td style="text-align:right">'+parseFloat(x.qtd_atual||0).toLocaleString('pt-BR')+' '+esc(x.unidade||'')+'</td>'+
                        '<td>'+(x.teor_pct?parseFloat(x.teor_pct)+'%':'—')+'</td>'+
                        '<td><span class="taof-lt-pill st-'+esc(x.status)+'">'+esc(x.status)+'</span></td>'+
                        '<td style="white-space:nowrap">'+acoes+'</td></tr>';
                }).join('');
                $('#taof-lt-lista').html('<div class="taof-lt-twrap"><table class="taof-lt-tb"><tr><th>Ativo</th><th>Lote</th><th>Validade</th><th>Saldo</th><th>Teor</th><th>Status</th><th></th></tr>'+rows+'</table></div>');
            });
        }
        $('#taof-lt-status').on('change',carregar);
        $('#taof-lt-q').on('input',function(){clearTimeout(timer);timer=setTimeout(carregar,300);});

        // CQ
        $(document).on('click','.taof-lt-aprovar',function(){
            var res=prompt('Resultado do CQ (opcional — laudo/observação):','Conforme');
            if(res===null)return;
            $.post(ajaxUrl,{action:'tao_formula_estq_lote_cq',nonce:nonce,lote_id:$(this).data('id'),acao:'aprovar',resultado:res},function(r){
                if(r.success)carregar();else alert((r.data&&r.data.message)||'Erro');
            });
        });
        $(document).on('click','.taof-lt-reprovar',function(){
            var res=prompt('Motivo da REPROVAÇÃO do lote:');
            if(!res)return;
            $.post(ajaxUrl,{action:'tao_formula_estq_lote_cq',nonce:nonce,lote_id:$(this).data('id'),acao:'reprovar',resultado:res},function(r){
                if(r.success)carregar();else alert((r.data&&r.data.message)||'Erro');
            });
        });
        // inventário
        $(document).on('click','.taof-lt-ajuste',function(){
            var atual=$(this).data('qt');
            var nova=prompt('Inventário — quantidade REAL contada do lote (atual no sistema: '+atual+'):',atual);
            if(nova===null||nova==='')return;
            $.post(ajaxUrl,{action:'tao_formula_estq_ajuste',nonce:nonce,lote_id:$(this).data('id'),qtd_nova:nova},function(r){
                if(r.success){alert('Ajuste lançado ('+(r.data.delta>=0?'+':'')+r.data.delta+').');carregar();}else alert((r.data&&r.data.message)||'Erro');
            });
        });
        // kardex
        $(document).on('click','.taof-lt-kardex',function(){
            var aid=$(this).data('aid'),nome=$(this).data('nome');
            $('#taof-lt-body').html('<h2 style="margin:0 0 10px;font-size:17px">↔ Kardex — '+esc(nome)+'</h2><p style="color:#94a3b8">Carregando…</p>');
            $('#taof-lt-modal').show();
            $.getJSON(ajaxUrl,{action:'tao_formula_estq_kardex',nonce:nonce,ativo_id:aid},function(r){
                var m=(r&&r.success&&r.data)?r.data:[];
                var rows=m.map(function(x){
                    var cor=x.quantidade>=0?'#16a34a':'#dc2626';
                    return '<tr><td>'+fdata(x.criado_em)+'</td><td>'+esc(x.tipo)+'</td><td style="color:#94a3b8">'+esc(x.origem||'')+'</td>'+
                        '<td style="text-align:right;color:'+cor+';font-weight:600">'+(x.quantidade>=0?'+':'')+parseFloat(x.quantidade).toLocaleString('pt-BR')+'</td></tr>';
                }).join('');
                $('#taof-lt-body').html('<h2 style="margin:0 0 10px;font-size:17px">↔ Kardex — '+esc(nome)+'</h2>'+
                    (m.length?'<div class="taof-lt-twrap"><table class="taof-lt-tb"><tr><th>Data</th><th>Tipo</th><th>Origem</th><th>Qtd</th></tr>'+rows+'</table></div>':'<p style="color:#94a3b8">Sem movimentos.</p>')+
                    '<p style="margin-top:12px"><button class="button" id="taof-lt-fechar">Fechar</button></p>');
            });
        });
        $(document).on('click','#taof-lt-fechar',function(){$('#taof-lt-modal').hide();});
        $('#taof-lt-modal').on('click','.taof-lt-ov',function(){$('#taof-lt-modal').hide();});

        carregar();
    });
    </script>
    </div>
    <?php
}
