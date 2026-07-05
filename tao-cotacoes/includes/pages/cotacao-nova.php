<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function tao_cotacoes_page_nova() {
    if ( ! tao_cot_pode() ) { echo '<div class="wrap"><p>Sem permissão para operar cotações.</p></div>'; return; }
    tao_cot_assets();

    $cid = tao_cot_cliente_id();
    $fornecedores = [];
    $instancias   = [];
    if ( $cid ) {
        $rf = tao_cot_api( "/fornecedores?cliente_id=eq.$cid&ativo=eq.true&order=nome.asc&limit=200" );
        $fornecedores = $rf['ok'] ? $rf['data'] : [];
        $instancias   = tao_cot_instancias( $cid );
    }
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
                <div><strong>&#x1F4C4; Subir planilha "Estoque mínimo" do Formula Certa</strong></div>
                <div class="taocot-muted">.xls — os itens entram já vinculados pelo código FC. Você também pode montar a lista manualmente abaixo.</div>
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
                <div style="display:flex;flex-wrap:wrap;gap:8px 22px">
                <?php foreach ( $fornecedores as $f ) : ?>
                    <label style="display:flex;align-items:center;gap:6px;font-size:13px">
                        <input type="checkbox" class="taocot-forn-chk" value="<?php echo esc_attr( $f['id'] ); ?>">
                        <strong><?php echo esc_html( $f['nome'] ); ?></strong>
                        <span class="taocot-muted"><?php echo esc_html( $f['whatsapp'] ); ?></span>
                    </label>
                <?php endforeach; ?>
                </div>
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
                <button class="taocot-btn taocot-btn-primary" id="taocot-btn-criar-enviar">&#x1F4E4; Criar e enviar aos fornecedores</button>
                <button class="taocot-btn" id="taocot-btn-rascunho">Salvar como rascunho</button>
                <span class="taocot-status-msg" id="taocot-criar-msg" style="margin:0"></span>
            </div>
        </div>
    </div>

    <script>
    (function(){
        var C = window.taoCot;
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
                var inU = document.createElement('input');
                inU.type='text'; inU.value = it.unidade||'';
                inU.style.cssText='width:52px;padding:4px 6px;border:1px solid #cbd5e1;border-radius:5px';
                inU.addEventListener('change', function(){ it.unidade = inU.value.trim(); });
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

        // Criação
        function criar(enviar, btn){
            var msg = document.getElementById('taocot-criar-msg');
            if(!itens.length){ alert('Inclua ao menos 1 item (planilha ou manual).'); return; }
            if(!itens.some(function(i){ return i.urgente; })){ alert('Marque ao menos 1 item urgente (⭐) — são os mandatórios da compra.'); return; }
            var forn = Array.prototype.filter.call(document.querySelectorAll('.taocot-forn-chk'), function(c){ return c.checked; }).map(function(c){ return c.value; });
            if(!forn.length){ alert('Selecione ao menos 1 fornecedor.'); return; }
            var inst = document.getElementById('taocot-instancia').value;
            if(!inst){ alert('Selecione a instância WhatsApp de envio.'); return; }
            if(enviar && !confirm('Enviar a solicitação de proposta agora para '+forn.length+' fornecedor(es) pelo WhatsApp?')) return;

            btn.disabled = true;
            msg.textContent = enviar ? 'Criando e enviando (há pausa entre envios)...' : 'Salvando...';
            C.post('tao_cot_criar_cotacao', {
                titulo: document.getElementById('taocot-titulo').value,
                instancia_id: inst,
                itens: JSON.stringify(itens),
                fornecedores: JSON.stringify(forn),
                enviar: enviar ? '1' : '0'
            }).then(function(r){
                if(r.success){
                    var u = <?php echo wp_json_encode( tao_cot_url( 'cotacoes' ) ); ?>;
                    location.href = u + (u.indexOf('?')>=0?'&':'?') + 'cot=' + r.data.id;
                } else { alert('Erro: '+(r.data||'falha')); btn.disabled=false; msg.textContent=''; }
            }).catch(function(){ alert('Falha de rede'); btn.disabled=false; msg.textContent=''; });
        }
        var be = document.getElementById('taocot-btn-criar-enviar');
        var br = document.getElementById('taocot-btn-rascunho');
        be.addEventListener('click', function(){ criar(true, be); });
        br.addEventListener('click', function(){ criar(false, br); });
    })();
    </script>
    <?php
}
