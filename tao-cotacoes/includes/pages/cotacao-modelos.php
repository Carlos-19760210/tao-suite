<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Cotações — Modelos de Proposta. Define 1× o layout da cotação de cada fornecedor
 * (a IA propõe as regras; o farmacêutico valida a prévia). Depois a importação de
 * propostas é DETERMINÍSTICA (sem IA).
 */
function tao_cotacoes_page_modelos() {
    if ( ! tao_cot_pode() ) { echo '<p>Sem permissão.</p>'; return; }
    tao_cot_assets();
    $cid   = tao_cot_cliente_id();
    $rf    = $cid ? tao_cot_api( "/fornecedores?cliente_id=eq.$cid&ativo=eq.true&select=id,nome&order=nome.asc&limit=2000" ) : [ 'ok' => false ];
    $forns = ( $rf['ok'] ?? false ) ? ( $rf['data'] ?? [] ) : [];
    ?>
    <div class="wrap taocot-wrap">
    <h1>&#x1F4D0; Modelos de Proposta <small style="font-size:12px;color:#94a3b8;font-weight:400">(layout da cotação por fornecedor — aprende 1×, importa sem IA)</small></h1>
    <p style="color:#475569;max-width:820px">Defina <b>uma vez</b> o layout da cotação de cada fornecedor: suba um PDF de exemplo, a IA propõe como ler e você <b>valida a prévia</b>. Depois, ao registrar o retorno desse fornecedor, o sistema lê os preços <b>sozinho, sem IA</b>. Fornecedor novo ou PDF escaneado cai na IA automaticamente.</p>

    <div style="margin:14px 0;padding:14px;border:2px dashed #cbd5e1;border-radius:10px;background:#f8fafc;max-width:660px">
        <strong>➕ Aprender o layout de um fornecedor</strong><br>
        <label style="display:block;margin:8px 0 2px;font-size:13px">Fornecedor</label>
        <select id="cm-forn" style="min-width:300px;padding:5px"><option value="">— selecione —</option>
            <?php foreach ( $forns as $f ) : ?><option value="<?php echo esc_attr( $f['id'] ); ?>"><?php echo esc_html( $f['nome'] ); ?></option><?php endforeach; ?>
        </select><br>
        <label style="display:block;margin:8px 0 2px;font-size:13px">Cotação de exemplo (PDF)</label>
        <input type="file" id="cm-file" accept=".pdf"><br>
        <button type="button" class="taocot-btn taocot-btn-primary" id="cm-sugerir" style="margin-top:10px">🤖 Analisar (IA propõe o layout)</button>
        <span id="cm-msg" style="font-size:12px;margin-left:8px"></span>
    </div>

    <div id="cm-editor" style="display:none;margin:14px 0;padding:14px;border:1px solid #cbd5e1;border-radius:10px;max-width:980px">
        <h3 style="margin:0 0 8px">Layout proposto — <span style="color:#64748b;font-size:13px">valide a prévia antes de salvar</span></h3>
        <div style="display:flex;gap:16px;flex-wrap:wrap;align-items:center">
            <label>Nome <input id="cm-nome" style="padding:4px" value="Modelo v1"></label>
            <label>Assinatura (trecho único do layout) <input id="cm-assin" style="padding:4px;width:240px"></label>
        </div>
        <label style="display:block;margin-top:10px;font-size:13px;color:#475569">Regras (editável — a IA já preencheu; ajuste só se a prévia estiver errada)</label>
        <textarea id="cm-regras" style="width:100%;height:150px;font-family:monospace;font-size:12px;padding:6px;border:1px solid #cbd5e1;border-radius:6px"></textarea>
        <h4 style="margin:12px 0 4px">Prévia — o que vai ser importado <span id="cm-cnt" style="color:#64748b;font-weight:400"></span></h4>
        <div style="max-height:300px;overflow:auto;border:1px solid #e2e8f0;border-radius:6px">
            <table class="taocot-table" id="cm-preview"><thead><tr><th>#</th><th>Item</th><th style="text-align:right">Preço</th><th>Unid.</th><th>Qtde mín</th><th>Validade</th></tr></thead><tbody></tbody></table>
        </div>
        <div style="margin-top:10px">
            <button type="button" class="taocot-btn" id="cm-testar">↻ Testar (reaplica)</button>
            <button type="button" class="taocot-btn taocot-btn-primary" id="cm-salvar">💾 Salvar modelo</button>
            <span id="cm-msg2" style="font-size:12px;margin-left:8px"></span>
        </div>
    </div>

    <h2>Modelos cadastrados</h2>
    <div id="cm-lista" style="max-width:980px">carregando…</div>
    </div>

    <script>
    (function(){
        var C = window.taoCot;
        var pags = null;
        function esc(s){ var d=document.createElement('div'); d.textContent=(s==null?'':String(s)); return d.innerHTML; }
        function money(v){ return (v==null||v==='')?'—':('R$ '+Number(v).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:4})); }

        // pdf.js lazy (extrai só o texto, no navegador)
        var _pdf=null;
        function pdfjs(){ if(_pdf) return _pdf; _pdf=new Promise(function(res,rej){
            var s=document.createElement('script'); s.src='https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/pdf.min.js';
            s.onload=function(){ try{ pdfjsLib.GlobalWorkerOptions.workerSrc='https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/pdf.worker.min.js'; res(); }catch(e){ rej(e);} }; s.onerror=rej; document.head.appendChild(s); }); return _pdf; }
        // Reconstrói LINHAS por posição vertical (Y) — pdf.js entrega fragmentos por célula.
        function itemsToLines(items){
            var arr=[]; items.forEach(function(it){ var s=it.str; if(s&&s.trim()!==''){ arr.push({x:it.transform[4], y:it.transform[5], s:s}); } });
            arr.sort(function(a,b){ return (b.y-a.y) || (a.x-b.x); });
            var lines=[], cur=[], lastY=null;
            arr.forEach(function(o){ if(lastY===null || Math.abs(o.y-lastY)<=3){ cur.push(o); } else { lines.push(cur); cur=[o]; } lastY=o.y; });
            if(cur.length) lines.push(cur);
            return lines.map(function(row){ return row.sort(function(a,b){return a.x-b.x;}).map(function(o){return o.s;}).join(' '); }).join('\n');
        }
        function extrair(file){ return pdfjs().then(function(){ return new Promise(function(res,rej){
            var fr=new FileReader(); fr.onload=function(){ pdfjsLib.getDocument({data:new Uint8Array(fr.result)}).promise.then(function(pdf){
                var out=[], ch=Promise.resolve(); for(var i=1;i<=pdf.numPages;i++){ (function(n){ ch=ch.then(function(){ return pdf.getPage(n).then(function(p){ return p.getTextContent().then(function(tc){ out[n-1]=itemsToLines(tc.items); }); }); }); })(i); }
                ch.then(function(){ res(out); }).catch(rej); }).catch(rej); }; fr.onerror=rej; fr.readAsArrayBuffer(file);
        }); }); }

        function preview(rows, total){
            var b=''; (rows||[]).forEach(function(d,i){ b+='<tr><td>'+(i+1)+'</td><td>'+esc(d.item||'—')+'</td><td style="text-align:right">'+money(d.preco)+'</td><td>'+esc(d.preco_unidade||'')+'</td><td>'+esc(d.qtde_min||'')+'</td><td>'+esc(d.validade||'')+'</td></tr>'; });
            document.querySelector('#cm-preview tbody').innerHTML = b || '<tr><td colspan="6" style="color:#dc2626">Nada extraído — ajuste as regras.</td></tr>';
            document.getElementById('cm-cnt').textContent = '· '+(total!=null?total:(rows||[]).length)+' item(ns)';
        }

        document.getElementById('cm-sugerir').addEventListener('click', function(){
            var forn=document.getElementById('cm-forn').value, f=document.getElementById('cm-file').files[0];
            var m=document.getElementById('cm-msg');
            if(!forn){ m.style.color='#dc2626'; m.textContent='Selecione o fornecedor.'; return; }
            if(!f){ m.style.color='#dc2626'; m.textContent='Selecione o PDF.'; return; }
            var b=this; b.disabled=true; m.style.color='#64748b'; m.textContent='Lendo o PDF…';
            extrair(f).then(function(p){
                var chars=(p.join('')||'').replace(/\s/g,'').length;
                if(chars<50){ m.style.color='#b45309'; m.textContent='PDF sem texto (escaneado) — este fornecedor usa o caminho por IA na importação.'; b.disabled=false; return; }
                pags=p; m.textContent='IA analisando o layout…';
                C.post('tao_cot_modelo_sugerir', { paginas: JSON.stringify(p) }).then(function(r){
                    b.disabled=false;
                    if(!r||!r.success){ m.style.color='#dc2626'; m.textContent=(r&&r.data)||'Falha.'; return; }
                    m.style.color='#16a34a'; m.textContent='✓ Layout proposto — valide a prévia.';
                    document.getElementById('cm-regras').value = JSON.stringify(r.data.regras||{}, null, 1);
                    document.getElementById('cm-assin').value = r.data.assinatura||'';
                    preview(r.data.preview, r.data.total);
                    document.getElementById('cm-editor').style.display='block';
                });
            }).catch(function(){ b.disabled=false; m.style.color='#dc2626'; m.textContent='Não consegui ler o PDF.'; });
        });

        document.getElementById('cm-testar').addEventListener('click', function(){
            if(!pags) return; var m=document.getElementById('cm-msg2'); m.style.color='#64748b'; m.textContent='Reaplicando…';
            var regras; try{ regras=JSON.parse(document.getElementById('cm-regras').value); }catch(e){ m.style.color='#dc2626'; m.textContent='JSON inválido nas regras.'; return; }
            C.post('tao_cot_modelo_testar', { paginas: JSON.stringify(pags), regras: JSON.stringify(regras) }).then(function(r){
                if(r&&r.success){ preview(r.data.preview, r.data.total); m.style.color='#16a34a'; m.textContent='✓ '+r.data.total+' item(ns)'; }
                else { m.style.color='#dc2626'; m.textContent='Falha ao testar.'; }
            });
        });

        document.getElementById('cm-salvar').addEventListener('click', function(){
            var forn=document.getElementById('cm-forn').value, m=document.getElementById('cm-msg2');
            var regras; try{ regras=JSON.parse(document.getElementById('cm-regras').value); }catch(e){ m.style.color='#dc2626'; m.textContent='JSON inválido.'; return; }
            m.style.color='#64748b'; m.textContent='Salvando…';
            C.post('tao_cot_modelo_salvar', { fornecedor_id: forn, nome: document.getElementById('cm-nome').value.trim()||'Modelo', assinatura: document.getElementById('cm-assin').value.trim(), regras: JSON.stringify(regras) }).then(function(r){
                if(r&&r.success){ m.style.color='#16a34a'; m.textContent='✓ Salvo!'; document.getElementById('cm-editor').style.display='none'; document.getElementById('cm-file').value=''; lista(); }
                else { m.style.color='#dc2626'; m.textContent=(r&&r.data)||'Falha ao salvar.'; }
            });
        });

        function lista(){
            C.post('tao_cot_modelos_lista', {}).then(function(r){
                var el=document.getElementById('cm-lista');
                if(!r||!r.success){ el.textContent='Erro ao carregar.'; return; }
                var ms=r.data||[]; if(!ms.length){ el.innerHTML='<p style="color:#64748b">Nenhum modelo ainda.</p>'; return; }
                var h='<table class="taocot-table"><thead><tr><th>Fornecedor</th><th>Modelo</th><th>Tipo</th><th>Origem</th><th></th></tr></thead><tbody>';
                ms.forEach(function(m){ h+='<tr><td>'+esc(m.fornecedor_nome)+'</td><td>'+esc(m.nome)+'</td><td>'+esc(m.tipo)+'</td><td>'+esc(m.origem||'')+'</td><td><button class="taocot-btn taocot-btn-danger cm-del" data-id="'+esc(m.id)+'">excluir</button></td></tr>'; });
                el.innerHTML=h+'</tbody></table>';
            });
        }
        document.addEventListener('click', function(e){ var t=e.target.closest('.cm-del'); if(!t) return;
            if(!confirm('Excluir este modelo?')) return;
            C.post('tao_cot_modelo_excluir', { id: t.getAttribute('data-id') }).then(function(){ lista(); });
        });
        lista();
    })();
    </script>
    <?php
}
