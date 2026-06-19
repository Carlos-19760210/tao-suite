<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function tao_formula_page_sinonimos() {
    if ( ! tao_formula_can_access() ) { echo '<p>Acesso negado.</p>'; return; }
    $nonce = wp_create_nonce( 'tao_formula_nonce' );
    ?>
    <div class="wrap taof-wrap">
    <h1>🏷️ Sinônimos de Ativos</h1>

    <!-- Geração automática -->
    <div id="taof-sin-gen-box" style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;padding:16px 20px;margin-bottom:24px">
        <strong style="font-size:14px">🤖 Geração automática (GPT-4o)</strong>
        <p style="margin:6px 0 12px;color:#475569;font-size:13px">
            Processa ativos que ainda não têm sinônimos cadastrados, usando literatura farmacêutica brasileira e internacional.
        </p>
        <button type="button" id="taof-sin-gerar-btn" class="button button-primary">▶ Iniciar geração</button>
        <button type="button" id="taof-sin-parar-btn" class="button" style="display:none;margin-left:8px">⏹ Parar</button>
        <div id="taof-sin-progresso" style="display:none;margin-top:12px">
            <div style="background:#e0f2fe;border-radius:4px;height:8px;overflow:hidden;margin-bottom:8px">
                <div id="taof-sin-barra" style="background:#0ea5e9;height:8px;width:0%;transition:width .4s"></div>
            </div>
            <span id="taof-sin-texto" style="font-size:12px;color:#0369a1"></span>
        </div>
        <div id="taof-sin-gen-msg" style="margin-top:8px;font-size:13px"></div>
    </div>

    <!-- Abas -->
    <div style="display:flex;gap:0;border-bottom:2px solid #e2e8f0;margin-bottom:20px">
        <button type="button" id="taof-tab-busca" class="taof-sin-tab taof-sin-tab-active"
                style="padding:8px 20px;font-size:13px;font-weight:600;border:none;background:none;cursor:pointer;border-bottom:2px solid #2563eb;margin-bottom:-2px;color:#2563eb">
            🔍 Buscar ativo
        </button>
        <button type="button" id="taof-tab-todos" class="taof-sin-tab"
                style="padding:8px 20px;font-size:13px;font-weight:600;border:none;background:none;cursor:pointer;border-bottom:2px solid transparent;margin-bottom:-2px;color:#64748b">
            📋 Todos os ativos
        </button>
    </div>

    <!-- Painel Busca -->
    <div id="taof-painel-busca">
        <div style="margin-bottom:16px;display:flex;gap:8px;align-items:center">
            <input type="text" id="taof-sin-busca" placeholder="Buscar ativo por nome ou código FC…"
                   class="regular-text" style="font-size:13px">
            <button type="button" id="taof-sin-buscar-btn" class="button">🔍 Buscar</button>
        </div>
        <div id="taof-sin-resultados" style="font-size:13px"></div>
    </div>

    <!-- Painel Todos os Ativos -->
    <div id="taof-painel-todos" style="display:none">
        <div style="display:flex;gap:8px;align-items:center;margin-bottom:14px">
            <input type="text" id="taof-todos-filtro" placeholder="Filtrar por nome ou código FC…"
                   class="regular-text" style="font-size:13px">
            <button type="button" id="taof-todos-filtrar-btn" class="button">Filtrar</button>
            <button type="button" id="taof-todos-limpar-btn" class="button" style="display:none">✕ Limpar</button>
            <span id="taof-todos-status" style="font-size:12px;color:#94a3b8;margin-left:6px"></span>
        </div>
        <div id="taof-todos-lista" style="font-size:13px"></div>
        <div style="margin-top:14px;text-align:center">
            <button type="button" id="taof-todos-mais-btn" class="button" style="display:none">Carregar mais</button>
        </div>
    </div>

    </div><!-- .taof-wrap -->

    <script>
    (function($){
        var nonce   = '<?php echo esc_js( $nonce ); ?>';
        var ajaxurl = '<?php echo esc_js( admin_url('admin-ajax.php') ); ?>';
        var gerarTimer = null, gerando = false;

        // ── Tabs ──────────────────────────────────────────────────────────────
        $('#taof-tab-busca, #taof-tab-todos').on('click', function() {
            var isBusca = this.id === 'taof-tab-busca';
            $('#taof-tab-busca').css({ 'border-bottom-color': isBusca ? '#2563eb' : 'transparent', 'color': isBusca ? '#2563eb' : '#64748b' });
            $('#taof-tab-todos').css({ 'border-bottom-color': isBusca ? 'transparent' : '#2563eb', 'color': isBusca ? '#64748b' : '#2563eb' });
            $('#taof-painel-busca').toggle(isBusca);
            $('#taof-painel-todos').toggle(!isBusca);
            if (!isBusca && !$('#taof-todos-lista').children().length) carregarAtivos(0, true);
        });

        // ── Busca ativos ──────────────────────────────────────────────────────
        function buscarAtivo() {
            var q = $('#taof-sin-busca').val().trim();
            if (!q) { $('#taof-sin-resultados').html('<p style="color:#94a3b8">Digite um nome para buscar.</p>'); return; }
            $('#taof-sin-resultados').html('<p style="color:#94a3b8">Buscando…</p>');
            $.post(ajaxurl, { action:'tao_formula_buscar_ativos', nonce:nonce, q:q }, function(r){
                if (!r.success || !r.data.length) {
                    $('#taof-sin-resultados').html('<p style="color:#94a3b8">Nenhum ativo encontrado.</p>');
                    return;
                }
                renderTabelaAtivos('#taof-sin-resultados', r.data, true);
            });
        }

        $('#taof-sin-buscar-btn').on('click', buscarAtivo);
        $('#taof-sin-busca').on('keydown', function(e){ if(e.key==='Enter') buscarAtivo(); });

        // ── Painel "Todos" ────────────────────────────────────────────────────
        var todosOffset = 0, todosQ = '', todosCarregando = false;

        function carregarAtivos(offset, reset) {
            if (todosCarregando) return;
            todosCarregando = true;
            $('#taof-todos-mais-btn').hide();
            $('#taof-todos-status').text('Carregando…');

            $.post(ajaxurl, { action:'tao_formula_listar_ativos_pag', nonce:nonce, q:todosQ, offset:offset }, function(r){
                todosCarregando = false;
                if (!r.success) { $('#taof-todos-status').text('Erro ao carregar.'); return; }
                var ativos = r.data.ativos;
                todosOffset = offset + ativos.length;
                $('#taof-todos-status').text('');

                if (reset) $('#taof-todos-lista').empty();
                if (!ativos.length && reset) {
                    $('#taof-todos-lista').html('<p style="color:#94a3b8">Nenhum ativo encontrado.</p>');
                    return;
                }
                renderTabelaAtivos('#taof-todos-lista', ativos, false, !reset);
                if (r.data.has_more) $('#taof-todos-mais-btn').show();
            }).fail(function(){ todosCarregando = false; $('#taof-todos-status').text('Falha de comunicação.'); });
        }

        $('#taof-todos-filtrar-btn').on('click', function(){
            todosQ = $('#taof-todos-filtro').val().trim();
            $('#taof-todos-limpar-btn').toggle(!!todosQ);
            carregarAtivos(0, true);
        });
        $('#taof-todos-filtro').on('keydown', function(e){ if(e.key==='Enter') $('#taof-todos-filtrar-btn').click(); });
        $('#taof-todos-limpar-btn').on('click', function(){
            todosQ = '';
            $('#taof-todos-filtro').val('');
            $(this).hide();
            carregarAtivos(0, true);
        });
        $('#taof-todos-mais-btn').on('click', function(){ carregarAtivos(todosOffset, false); });

        // ── Renderiza tabela de ativos (compartilhada por busca e lista) ──────
        function renderTabelaAtivos(containerSel, ativos, loadCounts, append) {
            var $cont = $(containerSel);
            var $table = $cont.find('table.taof-sin-table');
            if (!$table.length || !append) {
                $table = $('<table class="widefat striped taof-sin-table" style="margin-top:4px"><thead><tr>' +
                    '<th style="width:160px">Código FC</th><th>Nome</th>' +
                    '<th style="width:130px">Sinônimos</th><th style="width:100px"></th>' +
                    '</tr></thead><tbody></tbody></table>');
                if (!append) $cont.empty();
                $cont.append($table);
            }
            var $tbody = $table.find('tbody');

            ativos.forEach(function(a){
                var countHtml = loadCounts
                    ? '<span class="taof-sin-count" id="cnt-'+a.id+'">…</span>'
                    : '<span class="taof-sin-count" id="cnt-'+a.id+'">' + (a.sinonimos_count > 0 ? a.sinonimos_count + ' sinônimo' + (a.sinonimos_count !== 1 ? 's' : '') : '<span style="color:#f59e0b">nenhum</span>') + '</span>';
                $tbody.append(
                    $('<tr data-id="'+a.id+'" data-nome="'+escHtml(a.nome)+'">').html(
                        '<td style="font-size:12px;color:#64748b">'+(a.codigo_fc||'—')+'</td>' +
                        '<td style="font-weight:600">'+escHtml(a.nome)+'</td>' +
                        '<td>'+countHtml+'</td>' +
                        '<td><button class="button button-small taof-sin-ver" data-id="'+a.id+'">Ver / Editar</button></td>'
                    )
                );
                $tbody.append(
                    $('<tr class="taof-sin-detalhe" id="det-'+a.id+'" style="display:none">').html('<td colspan="4" style="padding:10px 16px;background:#f8fafc"></td>')
                );
            });

            if (loadCounts) {
                ativos.forEach(function(a){ carregarContagem(a.id); });
            }
            bindVerBtns($cont);
        }

        // ── Contagem e detalhe ────────────────────────────────────────────────
        function carregarContagem(id) {
            $.post(ajaxurl, { action:'tao_formula_listar_sinonimos', nonce:nonce, ativo_id:id }, function(r){
                var count = r.success ? r.data.length : 0;
                $('#cnt-'+id).html(count > 0
                    ? count + ' sinônimo' + (count !== 1 ? 's' : '')
                    : '<span style="color:#f59e0b">nenhum</span>');
                var $det = $('#det-'+id+' td');
                if ($det.closest('tr').is(':visible')) renderDetalhe(id, r.data || []);
            });
        }

        function bindVerBtns($scope) {
            ($scope || $(document)).find('.taof-sin-ver').off('click').on('click', function(){
                var id = $(this).data('id');
                var $row = $('#det-'+id);
                if ($row.is(':visible')) { $row.hide(); return; }
                // Fecha outros detalhes da mesma tabela
                $(this).closest('table').find('.taof-sin-detalhe:visible').hide();
                $.post(ajaxurl, { action:'tao_formula_listar_sinonimos', nonce:nonce, ativo_id:id }, function(r){
                    renderDetalhe(id, r.data || []);
                    $row.show();
                });
            });
        }

        function renderDetalhe(id, sins) {
            var nome = $('tr[data-id="'+id+'"]').data('nome') || '';
            var $td = $('#det-'+id+' td');
            var tags = sins.map(function(s){
                return '<span style="display:inline-flex;align-items:center;gap:4px;background:#e0f2fe;color:#0369a1;border-radius:12px;padding:2px 10px;margin:2px;font-size:12px">' +
                    escHtml(s.sinonimo) +
                    '<button class="taof-sin-del-btn" data-sid="'+s.id+'" data-aid="'+id+'" style="background:none;border:none;cursor:pointer;color:#ef4444;font-size:14px;line-height:1;padding:0 2px">&times;</button>' +
                    '</span>';
            }).join('');
            var html = '<div style="margin-bottom:8px">' + (tags || '<span style="color:#94a3b8;font-size:12px">Nenhum sinônimo cadastrado.</span>') + '</div>' +
                '<div style="display:flex;gap:6px;align-items:center">' +
                '<input type="text" class="taof-sin-novo-input" placeholder="Novo sinônimo (ex: VIT D3)" style="font-size:12px;width:260px">' +
                '<button class="button button-small taof-sin-add-btn" data-aid="'+id+'">+ Adicionar</button>' +
                '<span class="taof-sin-add-msg" style="font-size:12px;margin-left:6px"></span>' +
                '</div>';
            $td.html(html);

            $td.find('.taof-sin-del-btn').on('click', function(){
                var sid = $(this).data('sid'), aid = $(this).data('aid');
                if (!confirm('Remover este sinônimo?')) return;
                $.post(ajaxurl, { action:'tao_formula_excluir_sinonimo', nonce:nonce, sin_id:sid }, function(r){
                    if (r.success) carregarContagem(aid);
                });
            });

            $td.find('.taof-sin-add-btn').on('click', function(){
                var aid = $(this).data('aid');
                var val = $td.find('.taof-sin-novo-input').val().trim().toUpperCase();
                var $msg = $td.find('.taof-sin-add-msg');
                if (!val) return;
                $.post(ajaxurl, { action:'tao_formula_salvar_sinonimo', nonce:nonce, ativo_id:aid, sinonimo:val }, function(r){
                    if (r.success) {
                        $td.find('.taof-sin-novo-input').val('');
                        $msg.text('Salvo!').css('color','#16a34a');
                        carregarContagem(aid);
                        setTimeout(function(){ $msg.text(''); }, 2000);
                    } else {
                        $msg.text(r.data?.message || 'Erro').css('color','#dc2626');
                    }
                });
            });

            $td.find('.taof-sin-novo-input').on('keydown', function(e){
                if (e.key === 'Enter') $td.find('.taof-sin-add-btn').click();
            });
        }

        // ── Geração automática ────────────────────────────────────────────────
        var totalProcessado = 0, totalInseridos = 0, currentOffset = 0, totalEstimado = 0;

        function rodarLote() {
            if (!gerando) return;
            $.post(ajaxurl, { action:'tao_formula_gerar_sinonimos_lote', nonce:nonce, offset: currentOffset }, function(r){
                if (!r.success) {
                    pararGeracao('❌ ' + (r.data && r.data.message ? r.data.message : JSON.stringify(r.data)), '#dc2626');
                    return;
                }
                var d = r.data;
                currentOffset   = d.next_offset || (currentOffset + 5);
                totalProcessado += d.processados || 0;
                totalInseridos  += d.inseridos   || 0;
                if (totalEstimado === 0 && totalProcessado > 0) totalEstimado = totalProcessado * 10;
                var pct = totalEstimado > 0 ? Math.min(99, Math.round(totalProcessado / totalEstimado * 100)) : 0;
                $('#taof-sin-barra').css('width', (d.done ? 100 : pct) + '%');
                $('#taof-sin-texto').text(totalProcessado + ' ativos percorridos · ' + (d.sem_sin||0) + ' processados neste lote · ' + totalInseridos + ' sinônimos inseridos no total');
                if (d.done) {
                    pararGeracao('✅ Concluído! ' + totalProcessado + ' ativos percorridos · ' + totalInseridos + ' sinônimos inseridos.', '#16a34a');
                } else {
                    gerarTimer = setTimeout(rodarLote, 300);
                }
            }).fail(function(){
                pararGeracao('❌ Falha de comunicação', '#dc2626');
            });
        }

        function pararGeracao(msg, cor) {
            gerando = false;
            clearTimeout(gerarTimer);
            $('#taof-sin-gerar-btn').show();
            $('#taof-sin-parar-btn').hide();
            if (msg) $('#taof-sin-gen-msg').html('<span style="color:'+cor+'">'+msg+'</span>');
        }

        $('#taof-sin-gerar-btn').on('click', function(){
            gerando = true;
            totalProcessado = 0;
            totalInseridos  = 0;
            currentOffset   = 0;
            totalEstimado   = 0;
            $(this).hide();
            $('#taof-sin-parar-btn').show();
            $('#taof-sin-progresso').show();
            $('#taof-sin-gen-msg').html('');
            $('#taof-sin-barra').css('width','0%');
            $('#taof-sin-texto').text('Iniciando…');
            rodarLote();
        });

        $('#taof-sin-parar-btn').on('click', function(){
            pararGeracao('⏹ Parado pelo usuário.', '#64748b');
        });

        function escHtml(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

    })(jQuery);
    </script>
    <?php
}
