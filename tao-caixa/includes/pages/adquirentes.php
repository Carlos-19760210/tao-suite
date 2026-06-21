<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function tao_caixa_page_adquirentes() {
    if ( ! tao_caixa_pode_operar() ) { echo '<div class="wrap"><p>Sem permissão para operar o caixa.</p></div>'; return; }
    tao_caixa_assets();

    $cid  = tao_caixa_cliente_id();
    $rows = [];
    if ( $cid ) {
        $r    = tao_caixa_api( "/caixa_adquirentes?cliente_id=eq.$cid&order=nome.asc" );
        $rows = $r['ok'] ? ( $r['data'] ?? [] ) : [];
    }
    ?>
    <div class="wrap taoc-wrap">
        <div class="taoc-bar">
            <h1>&#x1F3E6; Adquirentes (Operadoras)</h1>
            <button class="taoc-btn taoc-btn-primary" data-caixa-new data-modal="taoc-adq-modal" data-title="Nova Operadora">+ Nova Operadora</button>
        </div>

        <?php if ( ! $cid ) : ?>
        <div class="notice notice-warning"><p>Cliente não identificado.</p></div>
        <?php endif; ?>

        <?php if ( empty( $rows ) ) : ?>
        <div class="taoc-empty">
            <p>Nenhuma operadora cadastrada.</p>
            <button class="taoc-btn taoc-btn-primary" data-caixa-new data-modal="taoc-adq-modal" data-title="Nova Operadora">+ Cadastrar primeira</button>
        </div>
        <?php else : ?>
        <table class="taoc-table">
            <thead>
                <tr><th>Operadora</th><th style="text-align:right">Taxa antecipação</th><th style="text-align:center">Status</th><th style="text-align:center;width:160px">Ações</th></tr>
            </thead>
            <tbody>
            <?php foreach ( $rows as $a ) :
                $json = wp_json_encode( [
                    'id'                   => $a['id'],
                    'nome'                 => $a['nome'] ?? '',
                    'taxa_antecipacao_pct' => $a['taxa_antecipacao_pct'] ?? 0,
                    'ativo'                => ! empty( $a['ativo'] ) ? '1' : '0',
                ] );
                $ativo = ! empty( $a['ativo'] );
            ?>
                <tr data-row data-id="<?php echo esc_attr( $a['id'] ); ?>" data-json='<?php echo esc_attr( $json ); ?>'>
                    <td><strong><?php echo esc_html( $a['nome'] ?? '' ); ?></strong></td>
                    <td style="text-align:right"><?php echo number_format( (float) ( $a['taxa_antecipacao_pct'] ?? 0 ), 3, ',', '.' ); ?>%</td>
                    <td style="text-align:center"><span class="taoc-pill <?php echo $ativo ? 'on' : 'off'; ?>"><?php echo $ativo ? 'Ativa' : 'Inativa'; ?></span></td>
                    <td style="text-align:center">
                        <button class="taoc-btn" data-caixa-edit data-modal="taoc-adq-modal">Editar</button>
                        <button class="taoc-btn taoc-btn-danger" data-caixa-del data-action="tao_caixa_delete_adquirente">Excluir</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- Modal -->
    <div id="taoc-adq-modal" class="taoc-modal">
        <div class="taoc-overlay"></div>
        <div class="taoc-box">
            <h2 data-title>Nova Operadora</h2>
            <form data-action="tao_caixa_save_adquirente">
                <input type="hidden" name="id">
                <div class="taoc-field">
                    <label>Nome da operadora *</label>
                    <input type="text" name="nome" placeholder="Ex: Cielo, Rede, Stone" required>
                </div>
                <div class="taoc-field">
                    <label>Taxa de antecipação (% a.m.)</label>
                    <input type="number" name="taxa_antecipacao_pct" step="0.001" min="0" placeholder="0,000">
                </div>
                <div class="taoc-field taoc-field-inline">
                    <input type="checkbox" name="ativo" id="taoc-adq-ativo" style="width:auto">
                    <label for="taoc-adq-ativo" style="margin:0">Operadora ativa</label>
                </div>
                <div class="taoc-actions">
                    <button type="submit" class="taoc-btn taoc-btn-primary">Salvar</button>
                    <button type="button" class="taoc-btn" data-caixa-cancel>Cancelar</button>
                </div>
            </form>
        </div>
    </div>
    <?php
}
