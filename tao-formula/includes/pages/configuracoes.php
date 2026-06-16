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
    <p style="color:#64748b;font-size:13px">
        As configurações de IA, Supabase e WhatsApp são gerenciadas nas
        <a href="<?php echo esc_url( function_exists('cbpm_url') ? cbpm_url('configuracoes') : admin_url('admin.php?page=chatbot-platform-settings') ); ?>">Configurações Gerais</a>.
    </p>
    </div>
    <?php
}
