<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Histórico FCerta — consulta de fórmulas por cliente + repetição.
 * Dados: hist_clientes / hist_formulas / hist_formulas_itens (carga da base FCerta).
 */
function tao_formula_page_historico() {
    if ( ! tao_formula_can_access() ) { echo '<p>Acesso negado.</p>'; return; }
    ?>
    <div class="wrap taof-wrap">
    <h1>🕘 Histórico do Cliente <small style="font-size:12px;color:#94a3b8;font-weight:400">(fórmulas FCerta 2018→2026 — consulta e repetição)</small></h1>

    <div style="margin:14px 0;max-width:520px;position:relative">
        <input type="text" id="taof-hist-busca" class="regular-text" style="width:100%"
               placeholder="Buscar cliente pelo nome (mín. 3 letras)..." autocomplete="off">
        <div id="taof-hist-dd" style="display:none;position:absolute;z-index:99;background:#fff;border:1px solid #cbd5e1;border-radius:6px;box-shadow:0 6px 18px rgba(0,0,0,.12);max-height:320px;overflow-y:auto;width:100%"></div>
    </div>

    <div id="taof-hist-cliente" style="display:none;margin-bottom:8px">
        <span style="font-size:15px;font-weight:700" id="taof-hist-cli-nome"></span>
        <span style="color:#94a3b8;font-size:12px" id="taof-hist-cli-meta"></span>
    </div>

    <div id="taof-hist-lista"></div>
    </div>

    <style>
    .taof-hist-item{cursor:pointer;padding:8px 12px;border-bottom:1px solid #f1f5f9;font-size:13px}
    .taof-hist-item:hover{background:#f0f9ff}
    .taof-hist-card{background:#fff;border:1px solid #e2e8f0;border-radius:8px;margin-bottom:10px;overflow:hidden}
    .taof-hist-head{display:flex;flex-wrap:wrap;gap:6px 16px;align-items:center;padding:10px 14px;cursor:pointer}
    .taof-hist-head:hover{background:#f8fafc}
    .taof-hist-badge{font-size:11px;padding:2px 8px;border-radius:10px;background:#e0f2fe;color:#0369a1;font-weight:600}
    .taof-hist-itens{display:none;border-top:1px solid #f1f5f9;padding:10px 14px;background:#fcfcfd}
    .taof-hist-itens table{width:100%;border-collapse:collapse;font-size:12px}
    .taof-hist-itens td,.taof-hist-itens th{padding:4px 8px;border-bottom:1px solid #f1f5f9;text-align:left}
    .taof-hist-twrap{overflow-x:auto;min-width:0}
    </style>

    <script>
    jQuery(function($){
        var ajaxUrl = taoFormula.ajaxUrl, nonce = taoFormula.nonce, timer = null;
        var editorBase = <?php echo wp_json_encode( tao_formula_url( 'formula-novo-orc' ) ); ?>;

        function esc(t){ return $('<span>').text(t==null?'':t).html(); }
        function fmtBR(n){ return parseFloat(n||0).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2}); }
        function fmtData(d){ if(!d) return '—'; var p=d.split('-'); return p.length===3 ? p[2]+'/'+p[1]+'/'+p[0] : d; }

        // ── Busca de cliente ─────────────────────────────────────────
        $('#taof-hist-busca').on('input', function(){
            clearTimeout(timer);
            var q = $(this).val().trim();
            if (q.length < 3) { $('#taof-hist-dd').hide().empty(); return; }
            timer = setTimeout(function(){
                $.getJSON(ajaxUrl, {action:'tao_formula_hist_busca', nonce:nonce, q:q}, function(resp){
                    var dd = $('#taof-hist-dd').empty();
                    if (!resp.success) { dd.append('<div class="taof-hist-item" style="color:#dc2626">'+esc(resp.data && resp.data.message || 'Erro')+'</div>').show(); return; }
                    var lista = resp.data || [];
                    if (!lista.length) { dd.append('<div class="taof-hist-item" style="color:#94a3b8">Nenhum cliente encontrado.</div>').show(); return; }
                    lista.forEach(function(c){
                        var meta = c.total_formulas + ' fórmula(s)' + (c.ultima ? ' · última ' + fmtData(c.ultima) : '');
                        $('<div class="taof-hist-item">')
                            .html('<strong>'+esc(c.nome)+'</strong> <span style="color:#94a3b8;font-size:11px">'+esc(meta)+'</span>')
                            .on('mousedown', function(e){ e.preventDefault(); abrirCliente(c); })
                            .appendTo(dd);
                    });
                    dd.show();
                });
            }, 300);
        });
        $('#taof-hist-busca').on('blur', function(){ setTimeout(function(){ $('#taof-hist-dd').hide(); }, 180); });

        // ── Fórmulas do cliente ──────────────────────────────────────
        function abrirCliente(c){
            $('#taof-hist-dd').hide().empty();
            $('#taof-hist-busca').val(c.nome);
            $('#taof-hist-cli-nome').text(c.nome);
            $('#taof-hist-cli-meta').text(' — ' + c.total_formulas + ' fórmula(s) no FCerta' + (c.cdcli ? ' · cód. ' + c.cdcli : ''));
            $('#taof-hist-cliente').show();
            $('#taof-hist-lista').html('<p style="color:#94a3b8">Carregando fórmulas…</p>');
            var params = {action:'tao_formula_hist_formulas', nonce:nonce};
            if (c.hist_cliente_id) params.hist_cliente_id = c.hist_cliente_id; else params.nome_paciente = c.nome;
            $.getJSON(ajaxUrl, params, function(resp){
                var out = $('#taof-hist-lista').empty();
                if (!resp.success) { out.html('<p style="color:#dc2626">'+esc(resp.data && resp.data.message || 'Erro')+'</p>'); return; }
                var fs = resp.data || [];
                if (!fs.length) { out.html('<p style="color:#94a3b8">Sem fórmulas para este cliente.</p>'); return; }
                fs.forEach(function(f){
                    var vol = (f.volume ? parseFloat(f.volume) : '') + (f.univol ? ' ' + f.univol : '');
                    var pot = (f.qt_potes > 1) ? ' × ' + f.qt_potes : '';
                    var card = $('<div class="taof-hist-card">').data('fid', f.id);
                    var resumo = f.resumo || '';
                    var head = $('<div class="taof-hist-head">').html(
                        '<span style="font-weight:700;color:#0f172a">' + fmtData(f.dt_cadastro) + '</span>' +
                        '<span>' + esc(vol + pot) + '</span>' +
                        (f.ind_repet ? '<span class="taof-hist-badge">repetição</span>' : '') +
                        '<span style="color:#334155;flex:1;min-width:180px" title="' + esc(resumo) + '">' +
                            esc(resumo.length > 90 ? resumo.substring(0, 90) + '…' : resumo) + '</span>' +
                        '<span style="font-weight:600">R$ ' + fmtBR(f.preco_cobrado) + '</span>' +
                        '<button type="button" class="button button-primary button-small taof-hist-repetir">↻ Repetir</button>'
                    );
                    card.data('posologia', f.posologia || '');
                    var body = $('<div class="taof-hist-itens">');
                    head.on('click', function(e){
                        if ($(e.target).hasClass('taof-hist-repetir')) return;
                        toggleItens(card, body, f.id);
                    });
                    head.find('.taof-hist-repetir').on('click', function(){ repetir(f.id, $(this)); });
                    card.append(head).append(body).appendTo(out);
                });
            });
        }

        // ── Itens (expande) ──────────────────────────────────────────
        function toggleItens(card, body, fid){
            if (body.is(':visible')) { body.slideUp(120); return; }
            if (body.data('ok')) { body.slideDown(120); return; }
            body.html('<p style="color:#94a3b8;margin:4px 0">Carregando…</p>').slideDown(120);
            $.getJSON(ajaxUrl, {action:'tao_formula_hist_itens', nonce:nonce, formula_id:fid}, function(resp){
                if (!resp.success) { body.html('<p style="color:#dc2626">Erro ao carregar itens.</p>'); return; }
                var rows = '';
                (resp.data || []).forEach(function(it){
                    var tipo = it.tpcmp === 'C' ? (it.is_qsp ? 'QSP' : 'ativo') : (it.tpcmp === 'E' ? 'embalagem' : 'cápsula');
                    rows += '<tr><td style="color:#94a3b8;font-family:monospace">' + esc(it.codigo_fc || '—') + '</td>' +
                            '<td>' + esc(it.descr) + '</td>' +
                            '<td>' + (it.dose != null ? parseFloat(it.dose) + ' ' + esc(it.unidade || '') : '—') + '</td>' +
                            '<td style="color:#64748b">' + tipo + '</td></tr>';
                });
                var posol = card.data('posologia');
                body.html(
                    (posol ? '<p style="margin:2px 0 8px;color:#0369a1;font-size:12px"><strong>Posologia:</strong> ' + esc(posol) + '</p>' : '') +
                    '<div class="taof-hist-twrap"><table><tr><th>Cód.</th><th>Componente</th><th>Dose</th><th></th></tr>' + rows + '</table></div>'
                ).data('ok', 1);
            });
        }

        // ── Repetir ──────────────────────────────────────────────────
        function repetir(fid, $btn){
            if ($btn.prop('disabled')) return;
            $btn.prop('disabled', true).text('Criando…');
            $.post(ajaxUrl, {action:'tao_formula_hist_repetir', nonce:nonce, formula_id:fid}, function(resp){
                if (resp.success && resp.data && resp.data.orc_id) {
                    // Portal usa URL amigável (sem query string) → '?'; wp-admin já tem '?page=' → '&'
                    var sep = editorBase.indexOf('?') > -1 ? '&' : '?';
                    window.location.href = editorBase + sep + 'orc_id=' + encodeURIComponent(resp.data.orc_id);
                } else {
                    alert('Erro ao criar a repetição: ' + (resp.data && resp.data.message || 'desconhecido'));
                    $btn.prop('disabled', false).text('↻ Repetir');
                }
            }).fail(function(){
                alert('Falha na requisição.');
                $btn.prop('disabled', false).text('↻ Repetir');
            });
        }
    });
    </script>
    <?php
}
