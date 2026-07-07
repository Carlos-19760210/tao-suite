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

    <div style="margin:14px 0;display:flex;gap:8px;align-items:flex-start">
        <div style="position:relative;flex:1;max-width:520px">
            <input type="text" id="taof-hist-busca" class="regular-text" style="width:100%"
                   placeholder="Buscar cliente pelo nome (mín. 3 letras)..." autocomplete="off">
            <div id="taof-hist-dd" style="display:none;position:absolute;z-index:99;background:#fff;border:1px solid #cbd5e1;border-radius:6px;box-shadow:0 6px 18px rgba(0,0,0,.12);max-height:320px;overflow-y:auto;width:100%"></div>
        </div>
        <button type="button" class="button button-primary" id="taof-cli-novo">+ Novo Cliente</button>
    </div>

    <div id="taof-hist-cliente" style="display:none;margin-bottom:8px">
        <span style="font-size:15px;font-weight:700" id="taof-hist-cli-nome"></span>
        <span style="color:#94a3b8;font-size:12px" id="taof-hist-cli-meta"></span>
        <button type="button" class="button button-small" id="taof-cli-editar" style="margin-left:8px;display:none">✏️ Editar dados</button>
        <span id="taof-cli-saude" style="margin-left:8px"></span>
    </div>

    <div id="taof-hist-lista"></div>

    <!-- Modal cliente -->
    <div id="taof-cli-modal" style="display:none">
        <div class="taof-cli-overlay"></div>
        <div class="taof-cli-box"><div id="taof-cli-body"></div></div>
    </div>
    </div>

    <style>
    .taof-hist-item{cursor:pointer;padding:8px 12px;border-bottom:1px solid #f1f5f9;font-size:13px}
    .taof-hist-item:hover{background:#f0f9ff}
    .taof-hist-item.taof-hist-hl{background:#eff6ff}
    .taof-hist-card{background:#fff;border:1px solid #e2e8f0;border-radius:8px;margin-bottom:10px;overflow:hidden}
    .taof-hist-head{display:flex;flex-wrap:wrap;gap:6px 16px;align-items:center;padding:10px 14px;cursor:pointer}
    .taof-hist-head:hover{background:#f8fafc}
    .taof-hist-badge{font-size:11px;padding:2px 8px;border-radius:10px;background:#e0f2fe;color:#0369a1;font-weight:600}
    .taof-hist-itens{display:none;border-top:1px solid #f1f5f9;padding:10px 14px;background:#fcfcfd}
    .taof-hist-itens table{width:100%;border-collapse:collapse;font-size:12px}
    .taof-hist-itens td,.taof-hist-itens th{padding:4px 8px;border-bottom:1px solid #f1f5f9;text-align:left}
    .taof-hist-twrap{overflow-x:auto;min-width:0}
    .taof-cli-saude-tag{display:inline-block;font-size:11px;padding:1px 8px;border-radius:10px;background:#fef3c7;color:#92400e;margin-left:4px}
    #taof-cli-modal .taof-cli-overlay{position:fixed;inset:0;background:rgba(15,23,42,.5);z-index:9998}
    #taof-cli-modal .taof-cli-box{position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:#fff;border-radius:10px;padding:20px 22px;z-index:9999;width:560px;max-width:96vw;max-height:92vh;overflow-y:auto;box-shadow:0 10px 40px rgba(0,0,0,.25)}
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
                        $('<div class="taof-hist-item" data-sel="1">')
                            .html('<strong>'+esc(c.nome)+'</strong> <span style="color:#94a3b8;font-size:11px">'+esc(meta)+'</span>')
                            .data('cli', c)
                            .on('mousedown', function(e){ e.preventDefault(); abrirCliente(c); })
                            .appendTo(dd);
                    });
                    dd.show();
                });
            }, 300);
        });
        $('#taof-hist-busca').on('blur', function(){ setTimeout(function(){ $('#taof-hist-dd').hide(); }, 180); });

        // Navegação por teclado no dropdown de clientes (setas + Enter + Esc)
        $('#taof-hist-busca').on('keydown', function(e){
            var $dd = $('#taof-hist-dd');
            if (!$dd.is(':visible')) {
                if (e.key === 'ArrowDown' && $(this).val().trim().length >= 3) { e.preventDefault(); $(this).trigger('input'); }
                return;
            }
            var $items = $dd.find('.taof-hist-item[data-sel]');
            if (!$items.length) return;
            var $cur = $items.filter('.taof-hist-hl');
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                $items.removeClass('taof-hist-hl');
                var $nxt;
                if (e.key === 'ArrowDown') $nxt = $cur.length ? $cur.nextAll('[data-sel]').first() : $();
                else                       $nxt = $cur.length ? $cur.prevAll('[data-sel]').first() : $();
                if (!$nxt || !$nxt.length) $nxt = (e.key === 'ArrowDown') ? $items.first() : $items.last();
                $nxt.addClass('taof-hist-hl');
                // mantém o item visível dentro do dropdown rolável
                var el = $nxt[0], box = $dd[0];
                if (el.offsetTop < box.scrollTop) box.scrollTop = el.offsetTop;
                else if (el.offsetTop + el.offsetHeight > box.scrollTop + box.clientHeight)
                    box.scrollTop = el.offsetTop + el.offsetHeight - box.clientHeight;
            } else if (e.key === 'Enter') {
                var $sel = $items.filter('.taof-hist-hl');
                if ($sel.length) { e.preventDefault(); abrirCliente($sel.data('cli')); }
            } else if (e.key === 'Escape') {
                $dd.hide().empty();
            }
        });

        // ── Fórmulas do cliente ──────────────────────────────────────
        var _cliAtual = null;
        function abrirCliente(c){
            _cliAtual = c;
            $('#taof-hist-dd').hide().empty();
            $('#taof-hist-busca').val(c.nome);
            $('#taof-hist-cli-nome').text(c.nome);
            $('#taof-hist-cli-meta').text(' — ' + c.total_formulas + ' fórmula(s) no FCerta' + (c.cdcli ? ' · cód. ' + c.cdcli : ''));
            // Editar dados do contato: sempre que há registro de histórico (com contato → edita; sem → cria+vincula)
            $('#taof-cli-editar').toggle(!!c.hist_cliente_id).text(c.contato_id ? '✏️ Editar dados' : '➕ Cadastrar contato');
            $('#taof-cli-saude').empty();
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

        // ── Cadastro de cliente (dados básicos + características de saúde) ──
        function chk(name,lbl,on){
            return '<label style="display:inline-flex;align-items:center;gap:5px;font-size:13px;margin-right:16px">' +
                   '<input type="checkbox" name="'+name+'" value="1"'+(on?' checked':'')+'> '+lbl+'</label>';
        }
        function cinp(lbl,name,val,type){
            return '<div><label style="font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.4px;display:block;margin-bottom:2px">'+lbl+'</label>' +
                   '<input type="'+(type||'text')+'" name="'+name+'" value="'+esc(val)+'" style="width:100%;padding:5px 8px;border:1px solid #d1d5db;border-radius:4px;font-size:13px"></div>';
        }
        // c = contato do CRM (ou {nome} p/ novo). histId = hist_cliente a vincular ao criar.
        function formCliente(c, histId){
            c = c || {};
            var isNovo = !c.id;   // c.id = crm_contatos.id
            var sexo = c.sexo || '';
            var html = '<h2 style="margin:0 0 4px;font-size:18px">'+(isNovo?'➕ Novo Cliente':'✏️ '+esc(c.nome))+'</h2>';
            html += '<p style="margin:0 0 12px;font-size:11px;color:#94a3b8">Cadastro único — o mesmo contato do CRM/Agente/Campanha.</p>';
            html += '<form id="taof-cli-form">';
            html += '<div style="display:grid;grid-template-columns:2fr 1fr;gap:10px 14px;margin-bottom:10px">' +
                    cinp('Nome *','nome',c.nome) +
                    '<div><label style="font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.4px;display:block;margin-bottom:2px">Sexo</label>' +
                    '<select name="sexo" style="width:100%;padding:5px 8px;border:1px solid #d1d5db;border-radius:4px;font-size:13px">' +
                    '<option value="">—</option><option value="F"'+(sexo==='F'?' selected':'')+'>Feminino</option><option value="M"'+(sexo==='M'?' selected':'')+'>Masculino</option></select></div></div>';
            html += '<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px 14px;margin-bottom:12px">' +
                    cinp('Nascimento','dt_nascimento',(c.data_nascimento||'').substring(0,10),'date') +
                    cinp('WhatsApp'+(isNovo?' *':''),'whatsapp',c.whatsapp) +
                    cinp('E-mail','email',c.email) + '</div>';
            html += '<div style="background:#f8fafc;border-radius:6px;padding:10px 12px;margin-bottom:10px">' +
                    '<div style="font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.4px;margin-bottom:6px">Características de saúde (comuns)</div>' +
                    chk('saude_obesidade','Obesidade',c.saude_obesidade) +
                    chk('saude_colesterol','Colesterol',c.saude_colesterol) +
                    chk('saude_pressao','Pressão',c.saude_pressao) +
                    chk('saude_diabetes','Diabetes',c.saude_diabetes) + '</div>';
            html += '<div style="margin-bottom:10px">'+cinp('Alergias','alergias',c.alergias)+'</div>';
            html += '<div style="margin-bottom:10px">'+cinp('Observações','observacoes',c.observacoes)+'</div>';
            html += '<p style="margin:6px 0 0"><button type="submit" class="button button-primary">💾 Salvar</button> ' +
                    '<button type="button" class="button" id="taof-cli-cancel">Cancelar</button> ' +
                    '<span id="taof-cli-msg" style="font-size:12px;margin-left:8px"></span></p></form>';
            $('#taof-cli-body').html(html);
            $('#taof-cli-modal').show();
            $('#taof-cli-form input[name=nome]').focus();
            $('#taof-cli-cancel').on('click', function(){ $('#taof-cli-modal').hide(); });
            $('#taof-cli-form').on('submit', function(e){
                e.preventDefault();
                var $msg = $('#taof-cli-msg');
                var data = {action:'tao_formula_cliente_save', nonce:nonce};
                $(this).serializeArray().forEach(function(f){ data[f.name]=f.value; });
                ['saude_obesidade','saude_colesterol','saude_pressao','saude_diabetes'].forEach(function(k){ if(!(k in data)) data[k]='0'; });
                if (!isNovo) data.id = c.id;               // edita o contato
                if (histId) data.hist_id = histId;         // vincula o contato ao paciente do histórico
                $msg.css('color','#64748b').text('Salvando…');
                $.post(ajaxUrl, data, function(r){
                    if (r.success) {
                        $('#taof-cli-modal').hide();
                        var cid = r.data && (r.data.contato_id || r.data.id);
                        if (_cliAtual) { _cliAtual.contato_id = cid; }   // passa a ter contato
                        $('#taof-cli-editar').text('✏️ Editar dados');
                        if (cid) carregarSaude(cid);
                    } else $msg.css('color','#dc2626').text((r.data && r.data.message) || 'Erro');
                }).fail(function(){ $msg.css('color','#dc2626').text('Falha na requisição'); });
            });
        }
        // Selo de características de saúde ao lado do nome (contato do CRM)
        function carregarSaude(contatoId){
            if (!contatoId) { $('#taof-cli-saude').empty(); return; }
            $.getJSON(ajaxUrl, {action:'tao_formula_cliente_get', nonce:nonce, id:contatoId}, function(r){
                if (!r.success || !r.data) return;
                var d = r.data, tags = [];
                if (d.saude_obesidade) tags.push('Obesidade');
                if (d.saude_colesterol) tags.push('Colesterol');
                if (d.saude_pressao) tags.push('Pressão');
                if (d.saude_diabetes) tags.push('Diabetes');
                if (d.alergias) tags.push('⚠ Alergias: '+d.alergias);
                $('#taof-cli-saude').html(tags.map(function(t){ return '<span class="taof-cli-saude-tag">'+esc(t)+'</span>'; }).join(''));
            });
        }
        $('#taof-cli-editar').on('click', function(){
            if (!_cliAtual) return;
            if (_cliAtual.contato_id) {
                // Edita o contato único existente
                $.getJSON(ajaxUrl, {action:'tao_formula_cliente_get', nonce:nonce, id:_cliAtual.contato_id}, function(r){
                    formCliente(r.success && r.data ? r.data : {id:_cliAtual.contato_id, nome:_cliAtual.nome});
                });
            } else {
                // Paciente do histórico sem contato (sem celular) → cria e vincula
                formCliente({nome:_cliAtual.nome}, _cliAtual.hist_cliente_id);
            }
        });
        $('#taof-cli-novo').on('click', function(){ formCliente({}); });
        $('#taof-cli-modal').on('click','.taof-cli-overlay',function(){ $('#taof-cli-modal').hide(); });

        // Ao abrir um cliente, mostra o selo de saúde (do contato vinculado)
        var _origAbrir = abrirCliente;
        abrirCliente = function(c){ _origAbrir(c); carregarSaude(c.contato_id); };
    });
    </script>
    <?php
}
