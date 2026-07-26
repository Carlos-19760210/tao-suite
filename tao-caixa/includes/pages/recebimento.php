<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Recebimento de NF (entrada) — conferência.
 * Importa o XML → auto-match de/para + 3 valores (custo/compra/compra c/ frete);
 * a farmacêutica revisa/ajusta e salva as associações (aprendizado).
 * Fase 2b (a construir): "Confirmar" grava lote + contas a pagar + entrada SNGPC.
 */
function tao_caixa_page_recebimento() {
    if ( ! function_exists( 'tao_caixa_pode_operar' ) || ! tao_caixa_pode_operar() ) {
        echo '<div class="wrap"><p>Sem permissão para operar o caixa.</p></div>'; return;
    }
    if ( function_exists( 'tao_caixa_assets' ) ) tao_caixa_assets();
    $nonce = wp_create_nonce( 'tao_caixa_nonce' );
    $ajax  = admin_url( 'admin-ajax.php' );
    ?>
    <style>
      .rec-cel-ativo{position:relative}
      .rec-ativo{width:230px;padding:4px 6px;border:1px solid #cbd5e1;border-radius:5px;font-size:12px}
      .rec-drop{position:absolute;z-index:20;left:0;top:30px;background:#fff;border:1px solid #cbd5e1;border-radius:6px;box-shadow:0 6px 20px rgba(0,0,0,.12);min-width:230px;max-height:220px;overflow:auto;display:none}
      .rec-drop div{padding:6px 8px;font-size:12px;cursor:pointer;border-bottom:1px solid #f1f5f9}
      .rec-drop div:hover{background:#eff6ff}
      .rec-val{width:90px;padding:4px 6px;border:1px solid #cbd5e1;border-radius:5px;font-size:12px;text-align:right}
      .rec-badge{font-size:10px;font-weight:700;padding:2px 7px;border-radius:9px}
    </style>
    <div class="wrap">
        <h1 style="margin-bottom:4px">&#x1F4E5; Recebimento de NF (entrada)</h1>
        <p style="margin:0 0 12px;color:#64748b;font-size:13px">Importe o <b>XML da NFe de compra</b>. Confira/associe cada item ao ativo do TAO e valide os valores. <em>Custo = mercado · Compra = pago (s/ frete) · Compra c/ frete = base do preço de venda.</em> <b>Fase 2b</b> (gravar estoque + contas a pagar) entra em seguida.</p>

        <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:12px;max-width:760px">
            <input type="file" id="nfe-file" accept=".xml,text/xml" style="font-size:13px">
            <button type="button" class="button button-primary" id="nfe-import" style="margin-left:8px">Importar</button>
            <span id="nfe-msg" style="margin-left:10px;font-size:12px;color:#64748b"></span>
        </div>
        <div id="nfe-preview" style="margin-top:16px"></div>
    </div>

    <script>
    (function(){
        var ajax=<?php echo wp_json_encode( $ajax ); ?>, nonce=<?php echo wp_json_encode( $nonce ); ?>;
        var $file=document.getElementById('nfe-file'), $msg=document.getElementById('nfe-msg'), $prev=document.getElementById('nfe-preview');
        var CNPJ='';
        function post(action,params){ var b='action='+action+'&nonce='+encodeURIComponent(nonce); for(var k in params) b+='&'+k+'='+encodeURIComponent(params[k]); return fetch(ajax,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:b,credentials:'same-origin'}).then(function(r){return r.json();}); }
        function brl(v){ return 'R$ '+(Number(v)||0).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2}); }
        function esc(s){ return String(s==null?'':s).replace(/[&<>"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];}); }
        function br(d){ return d? d.split('-').reverse().join('/'):'—'; }
        function badge(sit){ var m={associado:['associado','#dcfce7','#166534'],novo:['novo — associar','#fef3c7','#92400e'],ignorar:['ignorado','#f1f5f9','#64748b']}; var b=m[sit]||m.novo; return '<span class="rec-badge" style="background:'+b[1]+';color:'+b[2]+'">'+b[0]+'</span>'; }

        document.getElementById('nfe-import').onclick=function(){
            var f=$file.files[0]; if(!f){ $msg.textContent='Escolha um XML.'; return; }
            $msg.textContent='Lendo…'; var rd=new FileReader();
            rd.onload=function(){ post('tao_caixa_receb_preview',{xml:rd.result}).then(render).catch(function(){$msg.textContent='Falha de rede.';}); };
            rd.readAsText(f,'ISO-8859-1');
        };
        function render(r){
            if(!r.success){ $msg.textContent='⚠ '+(r.data||'erro'); $prev.innerHTML=''; return; }
            $msg.textContent=''; var d=r.data; CNPJ=d.emitente.cnpj;
            var h='<div style="border:1px solid #e2e8f0;border-radius:8px;padding:12px">';
            h+='<div style="font-size:13px;margin-bottom:10px"><b>'+esc(d.emitente.nome)+'</b> · CNPJ '+esc(d.emitente.cnpj)+' · NF-e '+esc(d.nota.numero)+'/'+esc(d.nota.serie)+' · '+br(d.nota.emissao)+' · Frete '+brl(d.nota.valor_frete)+' · Total '+brl(d.total.nota)+'</div>';
            h+='<table class="widefat"><thead><tr><th>Item do fornecedor</th><th>Ativo TAO Neo</th><th>Situação</th><th>Custo (mercado)</th><th>Compra (pago)</th><th>Compra c/ frete</th></tr></thead><tbody>';
            (d.itens||[]).forEach(function(it,i){
                var lote=(it.lotes||[]).map(function(l){return esc(l.lote)+(l.validade?' ('+br(l.validade)+')':'');}).join(', ')||'—';
                h+='<tr data-i="'+i+'" data-cprod="'+esc(it.cprod)+'" data-frete="'+(it.frete_rateado||0)+'">';
                h+='<td style="font-size:12px"><b>'+esc(it.desc)+'</b><br><span style="color:#64748b">cód '+esc(it.cprod)+' · NCM '+esc(it.ncm)+' · '+esc(it.qcom)+' '+esc(it.ucom)+' · lote '+lote+'</span></td>';
                h+='<td class="rec-cel-ativo"><input class="rec-ativo" placeholder="buscar ativo…" value="'+esc(it.ativo_nome||'')+'" data-ativo-id="'+esc(it.ativo_id||'')+'"><div class="rec-drop"></div></td>';
                h+='<td class="rec-sit">'+badge(it.situacao)+'</td>';
                h+='<td><input type="number" step="any" class="rec-val rec-custo" value="'+(it.valor_custo||0)+'"></td>';
                h+='<td><input type="number" step="any" class="rec-val rec-compra" value="'+(it.valor_compra||0)+'"></td>';
                h+='<td class="rec-comfrete" style="font-weight:700;color:#0f172a">'+brl(it.valor_compra_frete)+'</td>';
                h+='</tr>';
            });
            h+='</tbody></table>';
            if((d.duplicatas||[]).length){
                h+='<h3 style="margin:14px 0 6px;font-size:14px">Parcelas (contas a pagar)</h3><table class="widefat striped" style="max-width:420px"><thead><tr><th>Parcela</th><th>Vencimento</th><th>Valor</th></tr></thead><tbody>';
                d.duplicatas.forEach(function(p){ h+='<tr><td>'+esc(p.numero)+'</td><td>'+br(p.venc)+'</td><td>'+brl(p.valor)+'</td></tr>'; });
                h+='</tbody></table>';
            }
            h+='<p style="margin-top:12px;color:#64748b;font-size:12px">Associe os itens "novos" e valide os valores. As associações são <b>aprendidas</b> (na próxima NF do mesmo fornecedor casam sozinhas). A gravação (estoque + contas a pagar) entra na <b>Fase 2b</b>.</p>';
            h+='</div>';
            $prev.innerHTML=h;
        }

        // com-frete = compra + frete rateado (recalcula ao editar compra)
        $prev.addEventListener('input',function(e){
            if(e.target.classList.contains('rec-compra')){
                var tr=e.target.closest('tr'); var frete=parseFloat(tr.dataset.frete)||0;
                var compra=parseFloat(e.target.value)||0;
                tr.querySelector('.rec-comfrete').textContent=brl(compra+frete);
            }
            if(e.target.classList.contains('rec-ativo')){
                var inp=e.target, q=inp.value.trim(), drop=inp.parentNode.querySelector('.rec-drop');
                inp.dataset.ativoId='';
                if(q.length<2){ drop.style.display='none'; return; }
                post('tao_caixa_ativo_busca',{q:q}).then(function(r){
                    if(!r.success){ drop.style.display='none'; return; }
                    drop.innerHTML=(r.data||[]).map(function(a){ return '<div data-id="'+esc(a.id)+'" data-custo="'+(a.preco_custo||0)+'">'+esc(a.nome)+'</div>'; }).join('') || '<div style="color:#94a3b8">sem resultado</div>';
                    drop.style.display='block';
                });
            }
        });
        $prev.addEventListener('click',function(e){
            var opt=e.target.closest('.rec-drop div');
            if(opt && opt.dataset.id){
                var drop=opt.parentNode, cel=drop.parentNode, tr=cel.closest('tr');
                var inp=cel.querySelector('.rec-ativo');
                inp.value=opt.textContent; inp.dataset.ativoId=opt.dataset.id; drop.style.display='none';
                // custo (mercado) do ativo, se houver
                var custo=parseFloat(opt.dataset.custo)||0;
                if(custo>0) tr.querySelector('.rec-custo').value=custo;
                tr.querySelector('.rec-sit').innerHTML=badge('associado');
                // aprende a associação
                post('tao_caixa_receb_depara_save',{fornecedor_cnpj:CNPJ,cprod:tr.dataset.cprod,ativo_id:opt.dataset.id,ignorar:'0'});
            }
        });
    })();
    </script>
    <?php
}
