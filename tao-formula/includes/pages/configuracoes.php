<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function tao_formula_page_config() {
    if ( ! tao_formula_can_access() ) { echo '<p>Acesso negado.</p>'; return; }
    ?>
    <div class="wrap taof-wrap">
    <h1>⚙️ Fórmulas — Configurações</h1>

    <!-- ══ Empresa / Filial (rótulo RDC 67 + fiscal) ══════════════════ -->
    <h2>🏥 Dados da Farmácia <small style="font-size:12px;color:#94a3b8;font-weight:400">(rótulo obrigatório RDC 67 + emissão fiscal)</small></h2>
    <form id="taof-empresa-form" style="max-width:900px">
        <div style="display:grid;grid-template-columns:2fr 2fr 1fr;gap:10px 14px">
            <?php
            $ef = function( $lbl, $name, $ph = '' ) {
                echo '<div><label style="font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.4px;display:block;margin-bottom:2px">' . $lbl . '</label>' .
                     '<input type="text" name="' . $name . '"' . ( $ph ? ' placeholder="' . esc_attr( $ph ) . '"' : '' ) .
                     ' style="width:100%;padding:6px 8px;border:1px solid #d1d5db;border-radius:4px;font-size:13px"></div>';
            };
            $ef( 'Razão social', 'razao_social' );
            $ef( 'Nome fantasia', 'nome_fantasia' );
            $ef( 'CNPJ', 'cnpj' );
            $ef( 'Inscrição Estadual', 'inscr_estadual' );
            $ef( 'Inscrição Municipal', 'inscr_municipal' );
            $ef( 'CEP', 'cep' );
            ?>
        </div>
        <div style="display:grid;grid-template-columns:3fr 2fr 1fr;gap:10px 14px;margin-top:10px">
            <?php $ef( 'Endereço', 'endereco', 'Rua, nº' ); $ef( 'Bairro', 'bairro' ); $ef( 'UF', 'uf' ); ?>
        </div>
        <div style="display:grid;grid-template-columns:2fr 2fr 2fr;gap:10px 14px;margin-top:10px">
            <?php $ef( 'Cidade', 'cidade' ); $ef( 'Telefone', 'telefone' ); $ef( 'E-mail', 'email' ); ?>
        </div>
        <h3 style="margin:16px 0 6px;font-size:14px">Responsável Técnico <small style="color:#94a3b8;font-weight:400">(obrigatório no rótulo)</small></h3>
        <div style="display:grid;grid-template-columns:3fr 1fr 1fr;gap:10px 14px">
            <?php $ef( 'Farmacêutico(a) RT', 'rt_nome' ); $ef( 'CRF', 'rt_crf' ); $ef( 'UF do CRF', 'rt_uf' ); ?>
        </div>
        <h3 style="margin:16px 0 6px;font-size:14px">Licenças sanitárias</h3>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:10px 14px">
            <?php
            $ef( 'AFE (ANVISA)', 'licenca_afe' );
            $ef( 'CEVS (licença sanitária)', 'licenca_cevs' );
            $ef( 'CRF-PJ', 'licenca_crf_pj' );
            $ef( 'Autorização Especial', 'autorizacao_esp' );
            ?>
        </div>
        <p style="margin:12px 0 0">
            <button type="submit" class="button button-primary">💾 Salvar dados da farmácia</button>
            <span id="taof-empresa-msg" style="font-size:12px;margin-left:8px"></span>
        </p>
    </form>
    <script>
    jQuery(function($){
        var au = taoFormula.ajaxUrl, nc = taoFormula.nonce;
        var campos = ['razao_social','nome_fantasia','cnpj','inscr_estadual','inscr_municipal','endereco','bairro','cidade','uf','cep','telefone','email','rt_nome','rt_crf','rt_uf','licenca_afe','licenca_cevs','licenca_crf_pj','autorizacao_esp'];
        $.getJSON(au, {action:'tao_formula_empresa_get', nonce:nc}, function(r){
            if (r.success && r.data) campos.forEach(function(c){ $('#taof-empresa-form [name='+c+']').val(r.data[c]||''); });
            else if (!r.success && r.data && r.data.message) $('#taof-empresa-msg').css('color','#d97706').text(r.data.message);
        });
        $('#taof-empresa-form').on('submit', function(e){
            e.preventDefault();
            var $msg = $('#taof-empresa-msg');
            var data = $(this).serializeArray().reduce(function(o,f){o[f.name]=f.value;return o;},{});
            data.action = 'tao_formula_empresa_save'; data.nonce = nc;
            $msg.css('color','#64748b').text('Salvando…');
            $.post(au, data, function(r){
                $msg.css('color', r.success?'#16a34a':'#dc2626').text(r.success?'✓ Salvo!':((r.data&&r.data.message)||'Erro'));
            }).fail(function(){ $msg.css('color','#dc2626').text('Falha na requisição'); });
        });
    });
    </script>
    <hr style="margin:22px 0">


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
            <tr>
                <th><label for="taof-motor-v2">Motor farmacotécnico v2</label></th>
                <td>
                    <label>
                        <input type="checkbox" id="taof-motor-v2" name="motor_v2" value="1"
                               <?php checked( get_option( 'tao_formula_motor_v2' ), '1' ); ?>>
                        Ativar no editor de orçamentos
                    </label>
                    <p class="description">
                        Equivalência sal↔base do sinônimo · alerta de dose máxima ·
                        trava de substância restrita/bloqueada (GLP-1) · teor real do lote (FEFO).
                        Desligado, o cálculo permanece exatamente como hoje.
                    </p>
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
