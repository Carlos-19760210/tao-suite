<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function tao_formula_page_config() {
    if ( ! tao_formula_can_access() ) { echo '<p>Acesso negado.</p>'; return; }
    ?>
    <div class="wrap taof-wrap">
    <h1>⚙️ TAO Fórmulas — Configurações</h1>

    <form id="taof-config-form">
        <table class="form-table taof-form-table">
            <tr>
                <th><label for="taof-margem-padrao">Margem padrão (%)</label></th>
                <td>
                    <input type="number" id="taof-margem-padrao" name="margem_padrao" class="small-text" step="0.1" min="0" max="100"
                           value="<?php echo esc_attr( get_option('tao_formula_margem_padrao', 30) ); ?>">
                    <p class="description">Valor inicial ao cadastrar novas formas farmacêuticas.</p>
                </td>
            </tr>
        </table>

        <p>
            <button type="submit" class="button button-primary">Salvar</button>
            <span class="taof-spinner spinner" style="float:none;visibility:hidden"></span>
            <span class="taof-msg" style="display:none;margin-left:10px"></span>
        </p>
    </form>

    <hr>

    <!-- Chave OpenAI API -->
    <h2>🤖 IA — Análise de Receitas</h2>
    <table class="form-table taof-form-table">
        <tr>
            <th><label for="taof-openai-key">Chave OpenAI API</label></th>
            <td>
                <input type="password" id="taof-openai-key" class="regular-text"
                       value="<?php echo esc_attr( get_option('tao_formula_openai_key', '') ); ?>"
                       autocomplete="new-password">
                <button type="button" class="button button-small" id="taof-save-openai">Salvar</button>
                <span id="taof-openai-msg" style="margin-left:8px;display:none"></span>
                <p class="description">Usada para analisar receitas enviadas pelo card CRM (GPT-4o). Obtenha em <strong>platform.openai.com/api-keys</strong>.</p>
            </td>
        </tr>
    </table>
    <script>
    document.getElementById('taof-save-openai').addEventListener('click', function(){
        var key = document.getElementById('taof-openai-key').value.trim();
        var msg = document.getElementById('taof-openai-msg');
        if (!key) { msg.style.display='inline'; msg.style.color='#c00'; msg.textContent='Informe a chave.'; return; }
        fetch(ajaxurl, {
            method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body: new URLSearchParams({ action:'tao_formula_save_openai_key', key: key,
                _wpnonce:'<?php echo esc_js( wp_create_nonce('tao_formula_nonce') ); ?>' })
        }).then(r=>r.json()).then(d=>{
            msg.style.display='inline';
            msg.style.color = d.success ? '#0a0' : '#c00';
            msg.textContent  = d.success ? 'Salvo!' : (d.data?.message||'Erro');
        });
    });
    </script>

    <hr>

    <!-- Chave API para N8N / IA -->
    <h2>🤖 Integração IA (N8N)</h2>
    <?php
    $ia_key = get_option( 'tao_formula_ia_api_key', '' );
    if ( ! $ia_key ) {
        $ia_key = bin2hex( random_bytes( 24 ) );
        update_option( 'tao_formula_ia_api_key', $ia_key );
    }
    $endpoint_url = admin_url( 'admin-ajax.php' );
    ?>
    <table class="form-table taof-form-table">
        <tr>
            <th>URL do Endpoint</th>
            <td>
                <code style="user-select:all;font-size:12px"><?php echo esc_html( $endpoint_url ); ?></code>
                <p class="description">Use esta URL no N8N (HTTP Request POST).</p>
            </td>
        </tr>
        <tr>
            <th>Action</th>
            <td><code>tao_formula_criar_orcamento_ia</code></td>
        </tr>
        <tr>
            <th>api_key (campo POST)</th>
            <td>
                <code id="taof-ia-key" style="user-select:all;font-size:12px;word-break:break-all"><?php echo esc_html( $ia_key ); ?></code>
                &nbsp;
                <button type="button" class="button button-small" id="taof-regen-key">Gerar nova chave</button>
                <p class="description">Copie para o N8N. Se gerar nova chave, atualize o N8N.</p>
            </td>
        </tr>
    </table>
    <script>
    document.getElementById('taof-regen-key').addEventListener('click', function(){
        if (!confirm('Gerar nova chave? O fluxo N8N precisará ser atualizado.')) return;
        fetch(ajaxurl, {
            method: 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded'},
            body: new URLSearchParams({ action: 'tao_formula_regen_ia_key', _wpnonce: '<?php echo esc_js( wp_create_nonce('tao_formula_nonce') ); ?>' })
        }).then(r=>r.json()).then(d=>{
            if (d.success) document.getElementById('taof-ia-key').textContent = d.data.key;
        });
    });
    </script>

    <hr>
    <p style="color:#64748b;font-size:13px">
        As configurações de IA, Supabase e WhatsApp são gerenciadas nas
        <a href="<?php echo esc_url( function_exists('cbpm_url') ? cbpm_url('configuracoes') : admin_url('admin.php?page=chatbot-platform-settings') ); ?>">Configurações Gerais</a>.
    </p>
    </div>
    <?php
}
