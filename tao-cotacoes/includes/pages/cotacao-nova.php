<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function tao_cotacoes_page_nova() {
    if ( ! tao_cot_pode() ) { echo '<div class="wrap"><p>Sem permissão para operar cotações.</p></div>'; return; }
    tao_cot_assets();

    $cid = tao_cot_cliente_id();
    $fornecedores = [];
    $instancias   = [];
    $freq         = [];
    if ( $cid ) {
        $rf = tao_cot_api( "/fornecedores?cliente_id=eq.$cid&ativo=eq.true&order=nome.asc&limit=200" );
        $fornecedores = $rf['ok'] ? $rf['data'] : [];
        $instancias   = tao_cot_instancias( $cid );
        // frequência de uso (nº de cotações em que o fornecedor participou) — p/ sugerir os frequentes
        $rfq = tao_cot_api( "/cotacao_fornecedores?select=fornecedor_id&limit=5000" );
        foreach ( ( $rfq['ok'] ? $rfq['data'] : [] ) as $x ) {
            $fid = $x['fornecedor_id'] ?? '';
            if ( $fid ) $freq[ $fid ] = ( $freq[ $fid ] ?? 0 ) + 1;
        }
    }
    // Unidades de medida — fonte única do sistema (cadastro no Fórmula). Combo em vez de texto livre.
    $taocot_unidades = function_exists( 'tao_formula_unidades_opcoes' ) ? tao_formula_unidades_opcoes( $cid ) : [];
    ?>
    <div class="wrap taocot-wrap">
        <div class="taocot-bar">
            <h1>&#x2795; Nova Cotação</h1>
            <a class="taocot-btn" href="<?php echo esc_url( tao_cot_url( 'cotacoes' ) ); ?>">&larr; Voltar</a>
        </div>

        <?php if ( ! $cid ) : ?>
        <div class="notice notice-warning"><p>Cliente não identificado.</p></div>
        <?php return; endif; ?>

        <!-- 1. Planilha -->
        <div class="taocot-card">
            <h2>1. Itens para cotação</h2>
            <label class="taocot-upload" id="taocot-upload">
                <input type="file" id="taocot-file" accept=".xls,.xlsx">
                <div><strong>&#x1F4C4; Subir planilha de "Estoque mínimo"</strong></div>
                <div class="taocot-muted">.xls — os itens entram já vinculados pelo código. Você também pode montar a lista manualmente abaixo.</div>
            </label>
            <div class="taocot-status-msg" id="taocot-parse-msg"></div>

            <div class="taocot-tscroll">
            <table class="taocot-table" id="taocot-grid" style="display:none;margin-top:12px">
                <thead>
                    <tr>
                        <th style="width:36px" title="Urgente / mandatório de compra">⭐</th>
                        <th>Item</th>
                        <th>Cód. FC</th>
                        <th style="width:110px;text-align:right">Qtde</th>
                        <th style="width:70px">Un.</th>
                        <th style="text-align:right">Últ. pago</th>
                        <th style="width:46px"></th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
            </div>

            <div style="display:flex;gap:10px;align-items:center;margin-top:12px;flex-wrap:wrap">
                <div class="taocot-combo">
                    <input type="text" id="taocot-add-item" placeholder="+ Incluir item: digite para buscar no cadastro de ativos (↑/↓ e Enter)...">
                    <div class="taocot-combo-list"></div>
                </div>
                <span class="taocot-muted">Item fora do cadastro? Digite o nome e escolha "item livre".</span>
            </div>
            <p class="taocot-muted" style="margin-top:8px">⭐ Marque os itens <strong>urgentes</strong> (mandatórios desta compra) — eles vão destacados na mensagem e ajudam a escolher os fornecedores. É obrigatório ao menos 1.</p>
        </div>

        <!-- 2. Fornecedores -->
        <div class="taocot-card">
            <h2>2. Fornecedores participantes</h2>
            <?php if ( empty( $fornecedores ) ) : ?>
                <p class="taocot-muted">Nenhum fornecedor ativo. <a href="<?php echo esc_url( tao_cot_url( 'cotacoes-fornecedores' ) ); ?>">Cadastre os fornecedores</a> antes de criar a cotação.</p>
            <?php else : ?>
                <?php
                $forn_js = array_map( function ( $f ) use ( $freq ) {
                    return [ 'id' => $f['id'], 'nome' => $f['nome'], 'wa' => ! empty( $f['whatsapp'] ), 'freq' => (int) ( $freq[ $f['id'] ] ?? 0 ) ];
                }, $fornecedores );
                $frequentes = array_values( array_filter( $forn_js, function ( $f ) { return $f['freq'] > 0 && $f['wa']; } ) );
                usort( $frequentes, function ( $a, $b ) { return $b['freq'] - $a['freq']; } );
                $frequentes = array_slice( $frequentes, 0, 6 );
                ?>
                <div style="position:relative;max-width:460px">
                    <input type="text" id="taocot-forn-busca" placeholder="&#x1F50E; buscar fornecedor pelo nome&hellip;" autocomplete="off"
                           style="width:100%;padding:9px 12px;border:1.5px solid #cbd5e1;border-radius:8px;font-size:14px">
                    <div id="taocot-forn-dd" style="display:none;position:absolute;z-index:50;left:0;right:0;background:#fff;border:1px solid #cbd5e1;border-top:none;border-radius:0 0 8px 8px;max-height:260px;overflow:auto;box-shadow:0 8px 20px rgba(0,0,0,.12)"></div>
                </div>
                <div id="taocot-forn-selected" style="display:flex;flex-wrap:wrap;gap:6px;margin-top:10px"></div>
                <div id="taocot-forn-count" class="taocot-muted" style="margin-top:6px;font-size:12px">Nenhum fornecedor selecionado</div>
                <?php if ( $frequentes ) : ?>
                <div style="margin-top:12px">
                    <p class="taocot-muted" style="margin:0 0 5px;font-size:11px;text-transform:uppercase;letter-spacing:.05em">Seus fornecedores frequentes &mdash; toque para adicionar</p>
                    <div id="taocot-forn-sugeridos" style="display:flex;flex-wrap:wrap;gap:6px">
                    <?php foreach ( $frequentes as $fq ) : ?>
                        <button type="button" class="taocot-sugg" data-id="<?php echo esc_attr( $fq['id'] ); ?>" style="border:1px dashed #cbd5e1;border-radius:20px;padding:4px 11px;font-size:12px;background:transparent;cursor:pointer;color:#475569">+ <?php echo esc_html( $fq['nome'] ); ?> <span style="color:#94a3b8">&middot; <?php echo (int) $fq['freq']; ?>&times;</span></button>
                    <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                <script>window.TAOCOT_FORN = <?php echo wp_json_encode( $forn_js ); ?>;</script>
            <?php endif; ?>
        </div>

        <!-- 3. Envio -->
        <div class="taocot-card">
            <h2>3. Identificação e envio</h2>
            <div style="display:flex;gap:14px;flex-wrap:wrap">
                <div class="taocot-field" style="flex:2;min-width:240px">
                    <label>Título da cotação</label>
                    <input type="text" id="taocot-titulo" placeholder="Ex: Reposição <?php echo esc_attr( date_i18n( 'F/Y' ) ); ?>">
                </div>
                <div class="taocot-field" style="flex:1;min-width:220px">
                    <label>Instância WhatsApp de envio *</label>
                    <select id="taocot-instancia">
                        <option value="">— selecione —</option>
                        <?php foreach ( $instancias as $i ) : ?>
                        <option value="<?php echo esc_attr( $i['id'] ); ?>"><?php echo esc_html( ( $i['nome'] ?: $i['evolution_instancia'] ) ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="taocot-actions">
                <button class="taocot-btn taocot-btn-primary" id="taocot-btn-criar-enviar">&#x1F4E4; Revisar mensagem e enviar</button>
                <button class="taocot-btn" id="taocot-btn-rascunho">Salvar como rascunho</button>
                <span class="taocot-status-msg" id="taocot-criar-msg" style="margin:0"></span>
            </div>
        </div>
    </div>

    <!-- Modal: revisão da mensagem pelo farmacêutico antes do envio -->
    <div id="taocot-rev-modal" style="display:none;position:fixed;inset:0;z-index:100000">
        <div id="taocot-rev-overlay" style="position:absolute;inset:0;background:rgba(15,23,42,.55)"></div>
        <div style="position:relative;max-width:640px;margin:5vh auto;background:#fff;border-radius:12px;box-shadow:0 20px 50px rgba(0,0,0,.3);padding:20px 22px;max-height:88vh;overflow-y:auto">
            <h2 style="margin:0 0 4px;font-size:17px">&#x1F4E4; Revisar mensagem antes de enviar</h2>
            <p class="taocot-muted" style="margin:0 0 12px">Este texto será enviado a cada fornecedor. <code>{fornecedor}</code> é trocado pelo nome de cada um. Revise/edite e confirme.</p>
            <textarea id="taocot-rev-msg" style="width:100%;height:260px;padding:10px 12px;border:1px solid #cbd5e1;border-radius:8px;font-size:13px;font-family:inherit;line-height:1.5;box-sizing:border-box"></textarea>
            <div id="taocot-rev-dest" style="margin:12px 0;font-size:12px;color:#475569"></div>
            <div style="display:flex;gap:10px;align-items:center;margin-top:8px;flex-wrap:wrap">
                <button class="taocot-btn taocot-btn-primary" id="taocot-rev-enviar">&#x1F4E4; Confirmar e enviar</button>
                <button class="taocot-btn" id="taocot-rev-cancelar">Deixar como rascunho</button>
                <span class="taocot-status-msg" id="taocot-rev-status" style="margin:0"></span>
            </div>
        </div>
    </div>

    <script>
    (function(){
        var C = window.taoCot;
        var UNIDADES = <?php echo wp_json_encode( $taocot_unidades ); ?>;
        var grid = document.getElementById('taocot-grid');
        var tbody = grid.querySelector('tbody');
        var itens = []; // {codigo_fc, ativo_id, descricao, unidade, qtd, ult_pago, urgente, origem, sem_match}

        function fmt(v, dec){ return (v===null||v===undefined||isNaN(v)) ? '—' : Number(v).toLocaleString('pt-BR',{minimumFractionDigits:dec,maximumFractionDigits:dec}); }

        function render(){
            tbody.innerHTML = '';
            itens.forEach(function(it, idx){
                var tr = document.createElement('tr');
                if(it.urgente) tr.className = 'taocot-urgente';
                var tdS = document.createElement('td');
                var star = document.createElement('span');
                star.className = 'taocot-star' + (it.urgente?' on':'');
                star.textContent = '⭐'; star.title = 'Urgente / mandatório de compra';
                star.addEventListener('click', function(){ it.urgente = !it.urgente; render(); });
                tdS.appendChild(star); tr.appendChild(tdS);

                var tdN = document.createElement('td');
                var b = document.createElement('strong'); b.textContent = it.descricao; tdN.appendChild(b);
                if(!it.ativo_id){
                    var tag = document.createElement('span'); tag.className='taocot-muted';
                    tag.textContent = it.sem_match ? ' (código não encontrado no cadastro)' : ' (item livre)';
                    tdN.appendChild(tag);
                }
                tr.appendChild(tdN);

                var tdC = document.createElement('td'); tdC.textContent = it.codigo_fc||''; tr.appendChild(tdC);

                var tdQ = document.createElement('td'); tdQ.style.textAlign='right';
                var inQ = document.createElement('input');
                inQ.type='number'; inQ.step='0.01'; inQ.min='0'; inQ.value = it.qtd||0;
                inQ.style.cssText='width:90px;padding:4px 6px;border:1px solid #cbd5e1;border-radius:5px;text-align:right';
                inQ.addEventListener('change', function(){ it.qtd = parseFloat(inQ.value)||0; });
                tdQ.appendChild(inQ); tr.appendChild(tdQ);

                var tdU = document.createElement('td');
                var inU;
                if (UNIDADES && UNIDADES.length) {
                    inU = document.createElement('select');
                    inU.style.cssText='width:74px;padding:4px 6px;border:1px solid #cbd5e1;border-radius:5px';
                    var op0=document.createElement('option'); op0.value=''; op0.textContent='—'; inU.appendChild(op0);
                    var atual=(it.unidade||'').toUpperCase(), achou=false;
                    UNIDADES.forEach(function(u){ var o=document.createElement('option'); o.value=u.sigla; o.textContent=u.sigla; if(u.sigla===atual){o.selected=true;achou=true;} inU.appendChild(o); });
                    // unidade vinda da planilha que não está cadastrada: preserva como opção extra
                    if (atual && !achou) { var oe=document.createElement('option'); oe.value=atual; oe.textContent=atual+' (?)'; oe.selected=true; inU.appendChild(oe); }
                } else {
                    inU = document.createElement('input');
                    inU.type='text'; inU.value = it.unidade||'';
                    inU.style.cssText='width:52px;padding:4px 6px;border:1px solid #cbd5e1;border-radius:5px';
                }
                inU.addEventListener('change', function(){ it.unidade = (inU.value||'').trim(); });
                tdU.appendChild(inU); tr.appendChild(tdU);

                var tdP = document.createElement('td'); tdP.style.textAlign='right';
                tdP.textContent = it.ult_pago ? ('R$ '+fmt(it.ult_pago,4)) : '—';
                tr.appendChild(tdP);

                var tdX = document.createElement('td');
                var bx = document.createElement('button');
                bx.className='taocot-btn taocot-btn-danger'; bx.textContent='✕'; bx.title='Remover item';
                bx.style.padding='3px 8px';
                bx.addEventListener('click', function(){ itens.splice(idx,1); render(); });
                tdX.appendChild(bx); tr.appendChild(tdX);

                tbody.appendChild(tr);
            });
            grid.style.display = itens.length ? 'table' : 'none';
        }

        // Upload da planilha
        document.getElementById('taocot-file').addEventListener('change', function(){
            var f = this.files[0]; if(!f) return;
            var msg = document.getElementById('taocot-parse-msg');
            msg.textContent = 'Processando planilha...';
            C.postFile('tao_cot_parse_planilha', f).then(function(r){
                if(!r.success){ msg.textContent = 'Erro: ' + (r.data||'falha'); return; }
                var novos = r.data.itens||[];
                var jaTem = {};
                itens.forEach(function(i){ if(i.codigo_fc) jaTem[i.codigo_fc]=1; });
                var add = 0;
                novos.forEach(function(n){
                    if(n.codigo_fc && jaTem[n.codigo_fc]) return;
                    itens.push({ codigo_fc:n.codigo_fc, ativo_id:n.ativo_id, descricao:n.descricao,
                                 unidade:n.unidade, qtd:n.qtd, ult_pago:n.ult_pago, urgente:false,
                                 origem:'planilha', sem_match:n.sem_match });
                    add++;
                });
                var extra = r.data.precos_atualizados ? (' · '+r.data.precos_atualizados+' preço(s) de compra atualizados no cadastro') : '';
                msg.textContent = add + ' itens carregados da planilha. Confira a lista, ajuste quantidades e marque os urgentes ⭐.' + extra;
                render();
            }).catch(function(){ msg.textContent = 'Falha de rede ao processar a planilha.'; });
            this.value = '';
        });

        // Combo de inclusão manual
        C.combo({
            input: document.getElementById('taocot-add-item'),
            permitirLivre: true,
            onPick: function(r){
                itens.push({ codigo_fc:r.codigo_fc||'', ativo_id:r.ativo_id, descricao:r.nome,
                             unidade:'', qtd:0, ult_pago:null, urgente:false, origem:'manual', sem_match:false });
                render();
            }
        });

        var COT_URL = <?php echo wp_json_encode( tao_cot_url( 'cotacoes' ) ); ?>;
        var _cotId = null;
        function irParaCotacao(id){ location.href = COT_URL + (COT_URL.indexOf('?')>=0?'&':'?') + 'cot=' + id; }

        // Seletor de fornecedor: busca com autocomplete + selecionados (chips) + frequentes.
        // Cada selecionado mantém um checkbox oculto marcado (.taocot-forn-chk) — compatível com o submit.
        (function(){
            var busca = document.getElementById('taocot-forn-busca');
            if(!busca) return;
            var dd  = document.getElementById('taocot-forn-dd');
            var sel = document.getElementById('taocot-forn-selected');
            var cnt = document.getElementById('taocot-forn-count');
            var TODOS = window.TAOCOT_FORN || [];
            var escolhidos = {};
            function esc(t){ var d=document.createElement('div'); d.textContent=(t==null?'':t); return d.innerHTML; }
            function upd(){ var n=Object.keys(escolhidos).length; cnt.textContent = n? (n+' fornecedor(es) selecionado(s)') : 'Nenhum fornecedor selecionado'; }
            function addForn(f){
                if(!f || escolhidos[f.id]) return;
                escolhidos[f.id]=true;
                var chip=document.createElement('span');
                chip.style.cssText='background:#eef1fd;color:#3b5bdb;border:1px solid #dfe4ff;border-radius:20px;padding:4px 8px 4px 11px;font-size:12.5px;font-weight:600;display:inline-flex;align-items:center;gap:7px';
                chip.innerHTML = esc(f.nome) + (f.wa?'':' <span title="sem WhatsApp — não recebe" style="color:#b45309">⚠</span>')
                    + ' <input type="checkbox" class="taocot-forn-chk" value="'+esc(f.id)+'" checked style="display:none">'
                    + '<span class="taocot-chip-x" style="cursor:pointer;color:#94a3b8;font-weight:700">✕</span>';
                chip.querySelector('.taocot-chip-x').addEventListener('click', function(){
                    delete escolhidos[f.id]; chip.remove(); upd();
                    var sg=document.querySelector('.taocot-sugg[data-id="'+f.id+'"]'); if(sg) sg.style.display='';
                });
                sel.appendChild(chip); upd();
            }
            function render(q){
                q=(q||'').trim().toLowerCase();
                var lista = TODOS.filter(function(f){ return !escolhidos[f.id] && (!q || f.nome.toLowerCase().indexOf(q)>=0); }).slice(0,20);
                if(!lista.length){ dd.style.display='none'; return; }
                dd.innerHTML = lista.map(function(f){
                    var meta=(f.wa?'✓ WhatsApp':'⚠ sem WhatsApp')+(f.freq?(' · cotou '+f.freq+'×'):'');
                    return '<div class="taocot-dd-opt" data-id="'+esc(f.id)+'" style="padding:8px 12px;font-size:13px;border-top:1px solid #f1f5f9;cursor:pointer;display:flex;justify-content:space-between;gap:10px"><span>'+esc(f.nome)+'</span><span style="font-size:11px;color:#94a3b8;white-space:nowrap">'+meta+'</span></div>';
                }).join('');
                dd.style.display='block';
                Array.prototype.forEach.call(dd.querySelectorAll('.taocot-dd-opt'), function(o){
                    o.addEventListener('mousedown', function(e){ e.preventDefault();
                        var id=o.getAttribute('data-id'), f=TODOS.filter(function(x){return String(x.id)===String(id);})[0];
                        addForn(f); busca.value=''; render('');
                        var sg=document.querySelector('.taocot-sugg[data-id="'+id+'"]'); if(sg) sg.style.display='none';
                    });
                });
            }
            busca.addEventListener('input', function(){ render(this.value); });
            busca.addEventListener('focus', function(){ render(this.value); });
            document.addEventListener('click', function(e){ if(e.target!==busca && !dd.contains(e.target)) dd.style.display='none'; });
            Array.prototype.forEach.call(document.querySelectorAll('.taocot-sugg'), function(b){
                b.addEventListener('click', function(){ var id=b.getAttribute('data-id'); addForn(TODOS.filter(function(x){return String(x.id)===String(id);})[0]); b.style.display='none'; });
            });
            upd();
        })();

        // Criação — SEMPRE cria como rascunho; se "enviar", abre a revisão da mensagem antes
        function criar(enviar, btn){
            var msg = document.getElementById('taocot-criar-msg');
            if(!itens.length){ alert('Inclua ao menos 1 item (planilha ou manual).'); return; }
            if(!itens.some(function(i){ return i.urgente; })){ alert('Marque ao menos 1 item urgente (⭐) — são os mandatórios da compra.'); return; }
            var forn = Array.prototype.filter.call(document.querySelectorAll('.taocot-forn-chk'), function(c){ return c.checked; }).map(function(c){ return c.value; });
            if(!forn.length){ alert('Selecione ao menos 1 fornecedor.'); return; }
            var inst = document.getElementById('taocot-instancia').value;
            if(!inst){ alert('Selecione a instância WhatsApp de envio.'); return; }

            btn.disabled = true;
            msg.textContent = enviar ? 'Criando cotação...' : 'Salvando...';
            C.post('tao_cot_criar_cotacao', {
                titulo: document.getElementById('taocot-titulo').value,
                instancia_id: inst,
                itens: JSON.stringify(itens),
                fornecedores: JSON.stringify(forn),
                enviar: '0'   // nunca dispara direto: envio só após a revisão
            }).then(function(r){
                if(!r.success){ alert('Erro: '+(r.data||'falha')); btn.disabled=false; msg.textContent=''; return; }
                _cotId = r.data.id;
                if(!enviar){ irParaCotacao(_cotId); return; }   // rascunho puro
                // abre revisão da mensagem
                msg.textContent = 'Gerando prévia da mensagem...';
                C.post('tao_cot_preview_msg', { id: _cotId }).then(function(pr){
                    btn.disabled=false; msg.textContent='';
                    if(!pr.success){ alert('Cotação salva como rascunho, mas a prévia falhou: '+(pr.data||'')); irParaCotacao(_cotId); return; }
                    document.getElementById('taocot-rev-msg').value = pr.data.msg || '';
                    var dest = pr.data.fornecedores || [];
                    var semWa = dest.filter(function(d){ return !d.tem_wa; }).map(function(d){ return d.nome; });
                    var h = '<strong>'+dest.length+' fornecedor(es)</strong> receberão esta mensagem.';
                    if(semWa.length) h += ' <span style="color:#b45309">⚠ sem WhatsApp (não recebem): '+semWa.join(', ')+'</span>';
                    document.getElementById('taocot-rev-dest').innerHTML = h;
                    document.getElementById('taocot-rev-modal').style.display = 'block';
                }).catch(function(){ btn.disabled=false; msg.textContent=''; alert('Falha de rede na prévia.'); irParaCotacao(_cotId); });
            }).catch(function(){ alert('Falha de rede'); btn.disabled=false; msg.textContent=''; });
        }
        var be = document.getElementById('taocot-btn-criar-enviar');
        var br = document.getElementById('taocot-btn-rascunho');
        be.addEventListener('click', function(){ criar(true, be); });
        br.addEventListener('click', function(){ criar(false, br); });

        // Modal de revisão: confirmar envio com o texto revisado
        document.getElementById('taocot-rev-enviar').addEventListener('click', function(){
            var b = this, st = document.getElementById('taocot-rev-status');
            var txt = document.getElementById('taocot-rev-msg').value.trim();
            if(!txt){ alert('A mensagem não pode ficar vazia.'); return; }
            b.disabled = true; st.textContent = 'Enviando (há pausa entre envios)...';
            C.post('tao_cot_enviar_cotacao', { id: _cotId, msg_custom: txt }).then(function(r){
                irParaCotacao(_cotId);   // a tela da cotação mostra o resultado do envio
            }).catch(function(){ b.disabled=false; st.textContent=''; alert('Falha de rede no envio.'); });
        });
        function fecharRevisao(){ document.getElementById('taocot-rev-modal').style.display='none'; if(_cotId) irParaCotacao(_cotId); }
        document.getElementById('taocot-rev-cancelar').addEventListener('click', fecharRevisao);
        document.getElementById('taocot-rev-overlay').addEventListener('click', fecharRevisao);
    })();
    </script>
    <?php
}
