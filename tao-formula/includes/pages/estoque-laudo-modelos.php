<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Estoque — Modelos de Laudo (Fase 2). Define 1× o layout do laudo de cada
 * fornecedor (a IA propõe os rótulos; o farmacêutico valida). Depois as
 * importações na Entrada de NF são determinísticas (sem IA).
 */
function tao_formula_page_laudo_modelos() {
    if ( ! tao_formula_can_access() ) { echo '<p>Acesso negado.</p>'; return; }
    $cli   = tao_formula_cliente_id();
    $rf    = $cli ? tao_formula_api( "/fornecedores?cliente_id=eq.$cli&select=id,nome&order=nome.asc&limit=2000" ) : [ 'ok' => false ];
    $forns = ( $rf['ok'] ?? false ) ? ( $rf['data'] ?? [] ) : [];
    ?>
    <div class="wrap taof-wrap">
    <h1>🧪 Modelos de Laudo <small style="font-size:12px;color:#94a3b8;font-weight:400">(layout do Certificado de Análise por fornecedor)</small></h1>
    <p style="color:#475569;max-width:760px">Defina <b>uma vez</b> o layout do laudo de cada fornecedor — a IA propõe os rótulos e você valida. Depois, na <b>Entrada de NF</b>, os laudos são lidos automaticamente <b>sem IA</b> e casados por lote.</p>

    <div style="margin:14px 0;padding:14px;border:2px dashed #cbd5e1;border-radius:10px;background:#f8fafc;max-width:640px">
        <strong>➕ Definir molde de um fornecedor</strong><br>
        <label style="display:block;margin:8px 0 2px;font-size:13px">Fornecedor</label>
        <select id="lm-forn" style="min-width:280px;padding:5px"><option value="">— selecione —</option>
            <?php foreach ( $forns as $f ) : ?><option value="<?php echo esc_attr( $f['id'] ); ?>"><?php echo esc_html( $f['nome'] ); ?></option><?php endforeach; ?>
        </select><br>
        <label style="display:block;margin:8px 0 2px;font-size:13px">Laudo de exemplo (PDF)</label>
        <input type="file" id="lm-file" accept=".pdf"><br>
        <button type="button" class="button button-primary" id="lm-sugerir" style="margin-top:10px">🤖 Analisar laudo (IA propõe o molde)</button>
        <span id="lm-msg" style="font-size:12px;margin-left:8px"></span>
    </div>

    <div id="lm-editor" style="display:none;margin:14px 0;padding:14px;border:1px solid #cbd5e1;border-radius:10px;max-width:900px">
        <h3 style="margin:0 0 8px">Molde proposto — <span style="color:#64748b;font-size:13px">revise e ajuste antes de salvar</span></h3>
        <div style="display:flex;gap:16px;flex-wrap:wrap;align-items:center">
            <label>Nome do molde <input id="lm-nome" style="padding:4px" value="Molde v1"></label>
            <label><input type="checkbox" id="lm-multi"> Multi-ativo (1 PDF com vários laudos)</label>
        </div>
        <div id="lm-split-wrap" style="margin-top:8px"><label>Marcador de início de cada laudo (regex): <input id="lm-split" style="padding:4px;width:220px;font-family:monospace"></label></div>
        <table class="widefat" style="margin-top:10px"><thead><tr><th style="width:150px">Campo</th><th>Regex (âncora por rótulo)</th></tr></thead><tbody id="lm-campos"></tbody></table>
        <label style="display:block;margin-top:8px">Assinatura (trecho único p/ auto-detectar entre moldes do mesmo fornecedor): <input id="lm-assin" style="padding:4px;width:260px"></label>
        <h4 style="margin:12px 0 4px">Prévia — o que o molde extrai <span id="lm-preview-cnt" style="color:#64748b;font-weight:400"></span></h4>
        <div style="max-height:260px;overflow:auto;border:1px solid #e2e8f0;border-radius:6px"><table class="widefat" id="lm-preview"><thead><tr><th>#</th><th>Nome</th><th>Lote</th><th>Validade</th><th>Fabricante</th></tr></thead><tbody></tbody></table></div>
        <div style="margin-top:10px"><button type="button" class="button" id="lm-testar">↻ Testar molde (reaplica)</button> <button type="button" class="button button-primary" id="lm-salvar">💾 Salvar molde</button> <span id="lm-msg2" style="font-size:12px;margin-left:8px"></span></div>
    </div>

    <h2>Moldes cadastrados</h2>
    <div id="lm-lista" style="max-width:900px">carregando…</div>
    </div>

    <script>
    jQuery(function($){
        var ajaxUrl = taoFormula.ajaxUrl, nonce = taoFormula.nonce;
        var pdfPags = null;   // texto das páginas do PDF em análise
        function esc(s){ return String(s==null?'':s).replace(/[&<>]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;'}[c];}); }

        // pdf.js lazy
        var _pdf=null;
        function pdfjs(){ if(_pdf) return _pdf; _pdf=new Promise(function(res,rej){
            var s=document.createElement('script'); s.src='https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/pdf.min.js';
            s.onload=function(){ try{ pdfjsLib.GlobalWorkerOptions.workerSrc='https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/pdf.worker.min.js'; res(); }catch(e){ rej(e);} }; s.onerror=rej; document.head.appendChild(s); }); return _pdf; }
        function extrair(file){ return pdfjs().then(function(){ return new Promise(function(res,rej){
            var fr=new FileReader(); fr.onload=function(){ pdfjsLib.getDocument({data:new Uint8Array(fr.result)}).promise.then(function(pdf){
                var pags=[], ch=Promise.resolve(); for(var i=1;i<=pdf.numPages;i++){ (function(n){ ch=ch.then(function(){ return pdf.getPage(n).then(function(p){ return p.getTextContent().then(function(tc){ pags[n-1]=tc.items.map(function(it){return it.str;}).join(' '); }); }); }); })(i); }
                ch.then(function(){ res(pags); }).catch(rej); }).catch(rej); }; fr.onerror=rej; fr.readAsArrayBuffer(file);
        }); }); }

        // Todos os campos do certificado (ordem de exibição). A IA preenche os que existem.
        var CAMPOS = ['nome','lote','lote_interno','nome_cientifico','sinonimia','parte_utilizada',
            'dcb','cas','formula_molecular','peso_molecular','cor_corpo','cor_tampa','numero',
            'dt_fabricacao','dt_validade','dt_emissao','origem','procedencia','fabricante',
            'conservacao_temp','conservacao_umid','higroscopico','fotossensivel',
            'conclusao','resultado','rt_nome','rt_crf','ensaios_bloco'];
        function preencherEditor(regras){
            $('#lm-multi').prop('checked', !!regras.multi_ativo);
            $('#lm-split').val(regras.split_inicio||'');
            $('#lm-split-wrap').toggle(!!regras.multi_ativo);
            var campos = regras.campos||{}, html='';
            // união: campos conhecidos + quaisquer extras que a IA tenha retornado
            var lista = CAMPOS.slice(); Object.keys(campos).forEach(function(c){ if(lista.indexOf(c)<0) lista.push(c); });
            lista.forEach(function(c){ var r=(campos[c]&&campos[c].regex)||''; var tipo=(campos[c]&&campos[c].tipo)||'';
                html+='<tr><td><b>'+c+'</b>'+(tipo?' <small style="color:#94a3b8">('+tipo+')</small>':'')+'</td><td><input class="lm-rx" data-campo="'+c+'" data-tipo="'+esc(tipo)+'" value="'+esc(r)+'" style="width:100%;font-family:monospace;font-size:12px;padding:3px" placeholder="(vazio = não extrai)"></td></tr>'; });
            $('#lm-campos').html(html);
        }
        function coletarRegras(){
            var regras={ multi_ativo: $('#lm-multi').is(':checked'), campos:{} };
            if(regras.multi_ativo && $('#lm-split').val().trim()) regras.split_inicio=$('#lm-split').val().trim();
            $('.lm-rx').each(function(){ var rx=$(this).val().trim(); if(rx){ regras.campos[$(this).data('campo')]={regex:rx, tipo:$(this).data('tipo')||''}; } });
            return regras;
        }
        function mostrarPreview(laudos, total){
            var b=''; (laudos||[]).forEach(function(d,i){ b+='<tr><td>'+(i+1)+'</td><td>'+esc(d.nome||'—')+'</td><td>'+esc(d.lote||'—')+'</td><td>'+esc(d.dt_validade||'—')+'</td><td>'+esc(d.fabricante||'—')+'</td></tr>'; });
            $('#lm-preview tbody').html(b||'<tr><td colspan="5" style="color:#dc2626">Nada extraído — ajuste os rótulos.</td></tr>');
            $('#lm-preview-cnt').text('· '+(total!=null?total:(laudos||[]).length)+' laudo(s)');
        }

        $('#lm-sugerir').on('click',function(){
            var forn=$('#lm-forn').val(), f=$('#lm-file')[0].files[0];
            if(!forn){ $('#lm-msg').css('color','#dc2626').text('Selecione o fornecedor.'); return; }
            if(!f){ $('#lm-msg').css('color','#dc2626').text('Selecione o PDF do laudo.'); return; }
            var $b=$(this).prop('disabled',true), $m=$('#lm-msg').css('color','#64748b').text('Lendo o PDF…');
            extrair(f).then(function(pags){
                var chars=(pags.join('')||'').replace(/\s/g,'').length;
                if(chars<50){ $m.css('color','#b45309').text('PDF digitalizado (sem texto) — este fornecedor precisa do caminho por IA na importação.'); $b.prop('disabled',false); return; }
                pdfPags=pags; $m.text('IA analisando o layout…');
                $.post(ajaxUrl,{action:'tao_formula_laudo_molde_sugerir',nonce:nonce,paginas:JSON.stringify(pags)},function(r){
                    $b.prop('disabled',false);
                    if(!r||!r.success){ $m.css('color','#dc2626').text((r&&r.data&&r.data.message)||'Falha.'); return; }
                    $m.css('color','#16a34a').text('✓ Molde proposto — revise abaixo.');
                    preencherEditor(r.data.regras||{}); mostrarPreview(r.data.preview, r.data.total);
                    if(r.data.assinatura) $('#lm-assin').val(r.data.assinatura);
                    $('#lm-editor').show();
                }).fail(function(){ $b.prop('disabled',false); $m.css('color','#dc2626').text('Falha na análise.'); });
            }).catch(function(){ $b.prop('disabled',false); $m.css('color','#dc2626').text('Não consegui ler o PDF.'); });
        });
        $('#lm-multi').on('change',function(){ $('#lm-split-wrap').toggle($(this).is(':checked')); });
        $('#lm-testar').on('click',function(){
            if(!pdfPags){ return; }
            var $m=$('#lm-msg2').css('color','#64748b').text('Reaplicando…');
            $.post(ajaxUrl,{action:'tao_formula_laudo_molde_testar',nonce:nonce,paginas:JSON.stringify(pdfPags),regras:JSON.stringify(coletarRegras())},function(r){
                if(r&&r.success){ mostrarPreview(r.data.preview,r.data.total); $m.css('color','#16a34a').text('✓ '+r.data.total+' laudo(s)'); }
                else $m.css('color','#dc2626').text('Falha ao testar.');
            });
        });
        $('#lm-salvar').on('click',function(){
            var forn=$('#lm-forn').val(), nome=$('#lm-nome').val().trim()||'Molde';
            var $m=$('#lm-msg2').css('color','#64748b').text('Salvando…');
            $.post(ajaxUrl,{action:'tao_formula_laudo_molde_salvar',nonce:nonce,fornecedor_id:forn,nome:nome,assinatura:$('#lm-assin').val().trim(),regras:JSON.stringify(coletarRegras())},function(r){
                if(r&&r.success){ $m.css('color','#16a34a').text('✓ Molde salvo!'); $('#lm-editor').hide(); $('#lm-file').val(''); carregarLista(); }
                else $m.css('color','#dc2626').text((r&&r.data&&r.data.message)||'Falha ao salvar.');
            });
        });

        function carregarLista(){
            $.post(ajaxUrl,{action:'tao_formula_laudo_modelos_lista',nonce:nonce},function(r){
                if(!r||!r.success){ $('#lm-lista').text('Erro ao carregar.'); return; }
                var ms=r.data||[]; if(!ms.length){ $('#lm-lista').html('<p style="color:#64748b">Nenhum molde ainda.</p>'); return; }
                var h='<table class="widefat"><thead><tr><th>Fornecedor</th><th>Molde</th><th>Multi</th><th>Campos</th><th></th></tr></thead><tbody>';
                ms.forEach(function(m){ var nc=Object.keys((m.regras&&m.regras.campos)||{}).length;
                    h+='<tr><td>'+esc(m.fornecedor_nome)+'</td><td>'+esc(m.nome)+'</td><td>'+(m.multi_ativo?'sim':'não')+'</td><td>'+nc+' campo(s)</td><td><button class="button button-small lm-del" data-id="'+esc(m.id)+'">excluir</button></td></tr>'; });
                $('#lm-lista').html(h+'</tbody></table>');
            });
        }
        $(document).on('click','.lm-del',function(){ if(!confirm('Excluir este molde?'))return;
            $.post(ajaxUrl,{action:'tao_formula_laudo_molde_excluir',nonce:nonce,id:$(this).data('id')},function(){ carregarLista(); }); });
        carregarLista();
    });
    </script>
    <?php
}
