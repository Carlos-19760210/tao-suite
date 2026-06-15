<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function tao_crm_page_conversas() {
    if ( ! function_exists( 'cbpm_can_access' ) || ! cbpm_can_access() ) {
        echo '<div class="wrap"><p>Acesso negado.</p></div>'; return;
    }

    $ws_id = sanitize_text_field( $_GET['workspace_id'] ?? '' );
    $ws    = tao_crm_get_workspace( $ws_id ?: null );

    if ( ! $ws ) {
        echo '<div class="wrap"><div class="notice notice-warning"><p>Nenhum workspace configurado.</p></div></div>'; return;
    }
    $ws_id      = $ws['id'];
    $workspaces = tao_crm_get_workspaces();
    $base_url   = admin_url( 'admin.php?page=tao-crm-conversas' );
    $nonce      = wp_create_nonce( 'tao_crm_nonce' );
    ?>
    <div class="wrap" style="max-width:1200px">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:10px">
        <div style="display:flex;align-items:center;gap:12px">
            <h1 style="margin:0;font-size:20px">🗨️ Conversas Ativas no Chatbot</h1>
            <?php if ( count( $workspaces ) > 1 ) : ?>
            <form method="get" style="margin:0">
                <input type="hidden" name="page" value="tao-crm-conversas">
                <select name="workspace_id" onchange="this.form.submit()" style="padding:4px 8px;border-radius:4px;border:1px solid #ccc;font-size:13px">
                    <?php foreach ( $workspaces as $wk ) : ?>
                    <option value="<?php echo esc_attr( $wk['id'] ); ?>" <?php selected( $wk['id'], $ws_id ); ?>><?php echo esc_html( $wk['nome'] ); ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
            <?php else : ?>
            <span style="font-size:13px;color:#666;background:#f0f0f0;padding:3px 10px;border-radius:12px"><?php echo esc_html( $ws['nome'] ); ?></span>
            <?php endif; ?>
        </div>
        <div style="display:flex;align-items:center;gap:8px">
            <span id="crm-conv-status" style="font-size:12px;color:#888">Carregando…</span>
            <button id="crm-conv-refresh" class="button" style="display:flex;align-items:center;gap:4px;padding:4px 10px">⟳ Atualizar</button>
        </div>
    </div>

    <div id="crm-conv-list" style="display:flex;flex-direction:column;gap:10px;min-height:120px">
        <div style="text-align:center;padding:40px;color:#999;font-size:14px">Buscando conversas…</div>
    </div>
    </div>

    <style>
    .crm-conv-card { background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:14px 16px;display:flex;align-items:flex-start;gap:14px;transition:box-shadow .15s }
    .crm-conv-card:hover { box-shadow:0 2px 8px rgba(0,0,0,.08) }
    .crm-conv-avatar { width:42px;height:42px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0 }
    .crm-conv-body { flex:1;min-width:0 }
    .crm-conv-header { display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:4px }
    .crm-conv-nome { font-weight:600;font-size:14px;color:#1e293b }
    .crm-conv-phone { font-size:12px;color:#64748b }
    .crm-conv-badge { font-size:11px;font-weight:600;padding:2px 8px;border-radius:10px;white-space:nowrap }
    .badge-crm { background:#dcfce7;color:#166534 }
    .badge-novo { background:#dbeafe;color:#1e40af }
    .badge-card { background:#fef3c7;color:#92400e }
    .crm-conv-preview { font-size:13px;color:#475569;margin:4px 0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:480px }
    .crm-conv-meta { font-size:11px;color:#94a3b8;display:flex;align-items:center;gap:10px;margin-top:4px }
    .crm-conv-actions { display:flex;gap:6px;flex-shrink:0;align-items:flex-start;flex-wrap:wrap }
    .crm-conv-btn { font-size:12px!important;padding:4px 10px!important;height:auto!important;line-height:1.5!important;white-space:nowrap }
    .crm-conv-btn-interceptar { border-color:#0ea5e9!important;color:#0ea5e9!important }
    .crm-conv-btn-crm { border-color:#16a34a!important;color:#16a34a!important;font-weight:600!important }
    .crm-conv-btn-card { border-color:#d97706!important;color:#d97706!important }
    .crm-conv-empty { text-align:center;padding:60px 20px;color:#94a3b8 }
    .crm-conv-empty .crm-icon { font-size:48px;margin-bottom:12px }
    </style>

    <script>
    (function(){
        var WS_ID  = <?php echo wp_json_encode( $ws_id ); ?>;
        var NONCE  = <?php echo wp_json_encode( $nonce ); ?>;
        var AJAX   = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
        var CARD_BASE = <?php echo wp_json_encode( admin_url( 'admin.php?page=tao-crm-kanban&action=card&id=' ) ); ?>;
        var timer  = null;

        function ago(dateStr) {
            if (!dateStr) return '';
            var diff = Math.floor((Date.now() - new Date(dateStr).getTime()) / 1000);
            if (diff < 60) return diff + 's atrás';
            if (diff < 3600) return Math.floor(diff/60) + 'min atrás';
            if (diff < 86400) return Math.floor(diff/3600) + 'h atrás';
            return Math.floor(diff/86400) + 'd atrás';
        }

        function escHtml(s) {
            return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }

        function renderConversas(data) {
            var list = document.getElementById('crm-conv-list');
            var conversas = data.conversas || [];
            var total = data.total || 0;

            document.getElementById('crm-conv-status').textContent =
                total + ' conversa' + (total !== 1 ? 's' : '') + ' ativa' + (total !== 1 ? 's' : '') + ' · ' + new Date().toLocaleTimeString('pt-BR', {hour:'2-digit',minute:'2-digit'});

            if (conversas.length === 0) {
                list.innerHTML = '<div class="crm-conv-empty"><div class="crm-icon">🤖</div><div style="font-size:16px;margin-bottom:6px;color:#64748b">Nenhuma conversa ativa no momento</div><div style="font-size:13px">Quando um cliente iniciar conversa com o chatbot, ela aparecerá aqui.</div></div>';
                return;
            }

            var html = '';
            conversas.forEach(function(c) {
                var isCrm   = !!c.crm_contato;
                var hasCard = !!c.card_ativo;
                var nome    = escHtml(c.nome || c.phone);
                var phone   = escHtml(c.phone);
                var preview = c.ultima_msg ? (c.ultima_role === 'assistant' ? '🤖 ' : '👤 ') + escHtml(c.ultima_msg) : '<em style="color:#cbd5e1">Sem mensagens</em>';
                var msgs    = c.msg_count || 0;
                var criadoEm = c.criado_em || '';

                var badges = '';
                if (isCrm) {
                    var classif = c.crm_contato.classificacao ? ' · ' + escHtml(c.crm_contato.classificacao) : '';
                    var atend   = c.crm_contato.total_atendimentos ? ' · ' + c.crm_contato.total_atendimentos + ' atend.' : '';
                    badges += '<span class="crm-conv-badge badge-crm">✅ Cliente CRM' + classif + atend + '</span>';
                } else {
                    badges += '<span class="crm-conv-badge badge-novo">🆕 Novo contato</span>';
                }
                if (hasCard) {
                    badges += '<span class="crm-conv-badge badge-card">📋 Card ativo</span>';
                }

                var actions = '';
                if (hasCard) {
                    actions += '<a href="' + CARD_BASE + escHtml(c.card_ativo.id) + '" class="button crm-conv-btn crm-conv-btn-card">📋 Ver card</a>';
                } else if (isCrm) {
                    actions += '<button class="button crm-conv-btn crm-conv-btn-crm" onclick="interceptar(' + wp_json_encode_js(c) + ')">🤝 Criar Card (CRM)</button>';
                } else {
                    actions += '<button class="button crm-conv-btn crm-conv-btn-interceptar" onclick="interceptar(' + wp_json_encode_js(c) + ')">🤝 Interceptar</button>';
                }

                html += '<div class="crm-conv-card">'
                    + '<div class="crm-conv-avatar" style="background:' + (isCrm ? '#dcfce7' : '#dbeafe') + '">' + (isCrm ? '👤' : '💬') + '</div>'
                    + '<div class="crm-conv-body">'
                    +   '<div class="crm-conv-header">'
                    +     '<span class="crm-conv-nome">' + nome + '</span>'
                    +     '<span class="crm-conv-phone">📱 ' + phone + '</span>'
                    +     badges
                    +   '</div>'
                    +   '<div class="crm-conv-preview">' + preview + '</div>'
                    +   '<div class="crm-conv-meta">'
                    +     '<span>💬 ' + msgs + ' msg' + (msgs !== 1 ? 's' : '') + '</span>'
                    +     (criadoEm ? '<span>🕐 ' + escHtml(ago(criadoEm)) + '</span>' : '')
                    +   '</div>'
                    + '</div>'
                    + '<div class="crm-conv-actions">' + actions + '</div>'
                    + '</div>';
            });
            list.innerHTML = html;
        }

        function wp_json_encode_js(obj) {
            return JSON.stringify(obj).replace(/</g,'\\u003c').replace(/>/g,'\\u003e').replace(/&/g,'\\u0026').replace(/'/g,'\\u0027');
        }

        function load() {
            var fd = new FormData();
            fd.append('action', 'tao_crm_conversas_ativas');
            fd.append('nonce',  NONCE);
            fd.append('ws_id',  WS_ID);
            fetch(AJAX, {method:'POST', body:fd, credentials:'same-origin'})
                .then(function(r){ return r.json(); })
                .then(function(r){
                    if (r.success) renderConversas(r.data);
                    else document.getElementById('crm-conv-status').textContent = 'Erro: ' + (r.data || 'desconhecido');
                })
                .catch(function(){ document.getElementById('crm-conv-status').textContent = 'Erro de rede'; });
        }

        window.interceptar = function(c) {
            var label = c.crm_contato ? 'Criar card CRM para ' + c.nome + '?' : 'Interceptar conversa de ' + c.nome + ' e criar card?';
            if (!confirm(label + '\n\nO chatbot será pausado e você poderá atender este cliente manualmente.')) return;
            var fd = new FormData();
            fd.append('action', 'tao_crm_interceptar_conversa');
            fd.append('nonce',  NONCE);
            fd.append('ws_id',  WS_ID);
            fd.append('phone',  c.phone);
            fd.append('nome',   c.nome || '');
            fetch(AJAX, {method:'POST', body:fd, credentials:'same-origin'})
                .then(function(r){ return r.json(); })
                .then(function(r){
                    if (r.success) {
                        window.location.href = r.data.url;
                    } else {
                        alert('Erro: ' + (r.data || 'Não foi possível interceptar'));
                    }
                });
        };

        document.getElementById('crm-conv-refresh').addEventListener('click', function(){ clearInterval(timer); load(); timer = setInterval(load, 30000); });

        load();
        timer = setInterval(load, 30000);
    })();
    </script>
    <?php
}
