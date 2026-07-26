<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Recebimento de NF (entrada) — Fase 1: importar o XML da NFe e ver a prévia.
 * Fase 2: gerar lote (estoque) + contas a pagar + entrada SNGPC a partir da prévia.
 */
function tao_caixa_page_recebimento() {
    if ( ! function_exists( 'tao_caixa_pode_operar' ) || ! tao_caixa_pode_operar() ) {
        echo '<div class="wrap"><p>Sem permissão para operar o caixa.</p></div>'; return;
    }
    if ( function_exists( 'tao_caixa_assets' ) ) tao_caixa_assets();
    $nonce = wp_create_nonce( 'tao_caixa_nonce' );
    $ajax  = admin_url( 'admin-ajax.php' );
    ?>
    <div class="wrap">
        <h1 style="margin-bottom:4px">&#x1F4E5; Recebimento de NF (entrada)</h1>
        <p style="margin:0 0 12px;color:#64748b;font-size:13px">Importe o <b>XML da NFe de compra</b> do fornecedor para conferir os itens (lote/validade/NCM) e as parcelas. <em>Fase 1: prévia (não grava). A gravação de estoque + contas a pagar entra na Fase 2.</em></p>

        <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:12px;max-width:700px">
            <input type="file" id="nfe-file" accept=".xml,text/xml" style="font-size:13px">
            <button type="button" class="button button-primary" id="nfe-import" style="margin-left:8px">Importar prévia</button>
            <span id="nfe-msg" style="margin-left:10px;font-size:12px;color:#64748b"></span>
        </div>

        <div id="nfe-preview" style="margin-top:16px"></div>
    </div>

    <script>
    (function(){
        var ajax=<?php echo wp_json_encode( $ajax ); ?>, nonce=<?php echo wp_json_encode( $nonce ); ?>;
        var $file=document.getElementById('nfe-file'), $msg=document.getElementById('nfe-msg'), $prev=document.getElementById('nfe-preview');
        function brl(v){ return 'R$ '+(Number(v)||0).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2}); }
        function esc(s){ return String(s==null?'':s).replace(/[&<>]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;'}[c];}); }
        function br(d){ return d? d.split('-').reverse().join('/') : '—'; }
        document.getElementById('nfe-import').onclick=function(){
            var f=$file.files[0];
            if(!f){ $msg.textContent='Escolha um arquivo XML.'; return; }
            $msg.textContent='Lendo…';
            var rd=new FileReader();
            rd.onload=function(){
                var body='action=tao_caixa_receb_preview&nonce='+encodeURIComponent(nonce)+'&xml='+encodeURIComponent(rd.result);
                fetch(ajax,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:body,credentials:'same-origin'})
                .then(function(r){return r.json();}).then(render).catch(function(){$msg.textContent='Falha de rede.';});
            };
            rd.readAsText(f,'ISO-8859-1');
        };
        function render(r){
            if(!r.success){ $msg.textContent='⚠ '+(r.data||'erro'); $prev.innerHTML=''; return; }
            $msg.textContent=''; var d=r.data;
            var h='<div style="border:1px solid #e2e8f0;border-radius:8px;padding:12px;max-width:1000px">';
            h+='<div style="display:flex;gap:24px;flex-wrap:wrap;font-size:13px;margin-bottom:10px">';
            h+='<div><b>Fornecedor:</b> '+esc(d.emitente.nome)+'<br><span style="color:#64748b">CNPJ '+esc(d.emitente.cnpj)+'</span></div>';
            h+='<div><b>NF-e:</b> nº '+esc(d.nota.numero)+' / série '+esc(d.nota.serie)+'<br><span style="color:#64748b">Emissão '+br(d.nota.emissao)+'</span></div>';
            h+='<div><b>Total da nota:</b> '+brl(d.total.nota)+'</div>';
            h+='</div>';
            // itens
            h+='<table class="widefat striped"><thead><tr><th>Cód.</th><th>Descrição</th><th>NCM</th><th>Un</th><th>Qtd</th><th>Vl unit.</th><th>Total</th><th>Lote / Validade</th></tr></thead><tbody>';
            (d.itens||[]).forEach(function(it){
                var lote=(it.lotes||[]).map(function(l){return esc(l.lote)+(l.validade?' ('+br(l.validade)+')':'');}).join('<br>')||'—';
                h+='<tr><td style="font-family:monospace;font-size:11px">'+esc(it.cprod)+'</td><td>'+esc(it.desc)+'</td><td>'+esc(it.ncm)+'</td><td>'+esc(it.ucom)+'</td><td>'+esc(it.qcom)+'</td><td>'+brl(it.vun)+'</td><td>'+brl(it.vprod)+'</td><td style="font-size:11px">'+lote+'</td></tr>';
            });
            h+='</tbody></table>';
            // duplicatas
            h+='<h3 style="margin:14px 0 6px;font-size:14px">Parcelas (contas a pagar)</h3>';
            if((d.duplicatas||[]).length){
                h+='<table class="widefat striped" style="max-width:420px"><thead><tr><th>Parcela</th><th>Vencimento</th><th>Valor</th></tr></thead><tbody>';
                d.duplicatas.forEach(function(p){ h+='<tr><td>'+esc(p.numero)+'</td><td>'+br(p.venc)+'</td><td>'+brl(p.valor)+'</td></tr>'; });
                h+='</tbody></table>';
            } else { h+='<p style="color:#94a3b8;font-size:13px">Sem duplicatas na nota (à vista).</p>'; }
            h+='<p style="margin-top:12px;color:#64748b;font-size:12px">✔ Leitura OK — '+(d.itens||[]).length+' itens, '+(d.duplicatas||[]).length+' parcela(s). <b>Fase 2</b> vai gerar os lotes no estoque e os títulos a pagar a partir daqui.</p>';
            h+='</div>';
            $prev.innerHTML=h;
        }
    })();
    </script>
    <?php
}
