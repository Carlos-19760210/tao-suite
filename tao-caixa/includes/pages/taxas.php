<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function tao_caixa_page_taxas() {
    if ( ! tao_caixa_pode_operar() ) { echo '<div class="wrap"><p>Sem permissão para operar o caixa.</p></div>'; return; }
    tao_caixa_assets();

    $cid  = tao_caixa_cliente_id();
    $adqs = [];
    $taxas = [];
    $adq_map = [];
    if ( $cid ) {
        $ra   = tao_caixa_api( "/caixa_adquirentes?cliente_id=eq.$cid&order=nome.asc&select=id,nome,ativo" );
        $adqs = $ra['ok'] ? ( $ra['data'] ?? [] ) : [];
        foreach ( $adqs as $a ) $adq_map[ $a['id'] ] = $a['nome'];

        $rt    = tao_caixa_api( "/caixa_taxas?cliente_id=eq.$cid&order=adquirente_id.asc,bandeira.asc,modalidade.asc,parcelas.asc" );
        $taxas = $rt['ok'] ? ( $rt['data'] ?? [] ) : [];
    }
    $bandeiras  = [ 'Visa', 'Mastercard', 'Elo', 'American Express', 'Hipercard', 'Diners' ];
    $modal_lbl  = [ 'debito' => 'Débito', 'credito' => 'Crédito' ];
    ?>
    <div class="wrap taoc-wrap">
        <div class="taoc-bar">
            <h1>&#x1F4CA; Tabela de Taxas (MDR)</h1>
            <?php if ( $adqs ) : ?>
            <button class="taoc-btn taoc-btn-primary" data-caixa-new data-modal="taoc-taxa-modal" data-title="Nova Taxa">+ Nova Taxa</button>
            <?php endif; ?>
        </div>

        <?php if ( ! $cid ) : ?>
        <div class="notice notice-warning"><p>Cliente não identificado.</p></div>
        <?php elseif ( ! $adqs ) : ?>
        <div class="taoc-empty">
            <p>Cadastre uma <strong>Operadora de Cartão</strong> antes de definir as taxas.</p>
            <a class="taoc-btn taoc-btn-primary" href="<?php echo esc_url( tao_caixa_url( 'caixa-adquirentes' ) ); ?>">Ir para Operadoras de Cartão</a>
        </div>
        <?php elseif ( empty( $taxas ) ) : ?>
        <div class="taoc-empty">
            <p>Nenhuma taxa cadastrada.</p>
            <button class="taoc-btn taoc-btn-primary" data-caixa-new data-modal="taoc-taxa-modal" data-title="Nova Taxa">+ Cadastrar primeira</button>
        </div>
        <?php else : ?>
        <table class="taoc-table">
            <thead>
                <tr><th>Operadora</th><th>Bandeira</th><th>Modalidade</th><th style="text-align:center">Parcelas</th><th style="text-align:right">Taxa</th><th style="text-align:right">Prazo</th><th style="text-align:center">Status</th><th style="text-align:center;width:150px">Ações</th></tr>
            </thead>
            <tbody>
            <?php foreach ( $taxas as $t ) :
                $json = wp_json_encode( [
                    'id'                     => $t['id'],
                    'adquirente_id'          => $t['adquirente_id'] ?? '',
                    'bandeira'               => $t['bandeira'] ?? '',
                    'modalidade'             => $t['modalidade'] ?? 'credito',
                    'parcelas'               => $t['parcelas'] ?? 1,
                    'taxa_pct'               => $t['taxa_pct'] ?? 0,
                    'prazo_recebimento_dias' => $t['prazo_recebimento_dias'] ?? 1,
                    'ativo'                  => ! empty( $t['ativo'] ) ? '1' : '0',
                ] );
                $ativo = ! empty( $t['ativo'] );
            ?>
                <tr data-row data-id="<?php echo esc_attr( $t['id'] ); ?>" data-json='<?php echo esc_attr( $json ); ?>'>
                    <td><strong><?php echo esc_html( $adq_map[ $t['adquirente_id'] ] ?? '—' ); ?></strong></td>
                    <td><?php echo esc_html( $t['bandeira'] ?? '' ); ?></td>
                    <td><?php echo esc_html( $modal_lbl[ $t['modalidade'] ?? '' ] ?? $t['modalidade'] ); ?></td>
                    <td style="text-align:center"><?php echo (int) ( $t['parcelas'] ?? 1 ); ?>x</td>
                    <td style="text-align:right"><?php echo number_format( (float) ( $t['taxa_pct'] ?? 0 ), 3, ',', '.' ); ?>%</td>
                    <td style="text-align:right"><?php echo (int) ( $t['prazo_recebimento_dias'] ?? 0 ); ?> d</td>
                    <td style="text-align:center"><span class="taoc-pill <?php echo $ativo ? 'on' : 'off'; ?>"><?php echo $ativo ? 'Ativa' : 'Inativa'; ?></span></td>
                    <td style="text-align:center">
                        <button class="taoc-btn" data-caixa-edit data-modal="taoc-taxa-modal">Editar</button>
                        <button class="taoc-btn taoc-btn-danger" data-caixa-del data-action="tao_caixa_delete_taxa">Excluir</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- Modal -->
    <div id="taoc-taxa-modal" class="taoc-modal">
        <div class="taoc-overlay"></div>
        <div class="taoc-box">
            <h2 data-title>Nova Taxa</h2>
            <form data-action="tao_caixa_save_taxa">
                <input type="hidden" name="id">
                <div class="taoc-field">
                    <label>Operadora de Cartão *</label>
                    <select name="adquirente_id" required>
                        <option value="">— Selecione —</option>
                        <?php foreach ( $adqs as $a ) : ?>
                        <option value="<?php echo esc_attr( $a['id'] ); ?>"><?php echo esc_html( $a['nome'] ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="taoc-field">
                    <label>Bandeira *</label>
                    <input type="text" name="bandeira" list="taoc-bandeiras" placeholder="Visa, Mastercard, Elo…" required>
                    <datalist id="taoc-bandeiras">
                        <?php foreach ( $bandeiras as $b ) : ?><option value="<?php echo esc_attr( $b ); ?>"><?php endforeach; ?>
                    </datalist>
                </div>
                <div class="taoc-field">
                    <label>Modalidade *</label>
                    <select name="modalidade">
                        <option value="credito">Crédito</option>
                        <option value="debito">Débito</option>
                    </select>
                </div>
                <div class="taoc-field">
                    <label>Parcelas (1 = à vista / débito)</label>
                    <input type="number" name="parcelas" min="1" max="24" step="1" value="1">
                </div>
                <div class="taoc-field">
                    <label>Taxa (%) *</label>
                    <input type="number" name="taxa_pct" step="0.001" min="0" placeholder="2,990" required>
                </div>
                <div class="taoc-field">
                    <label>Prazo de recebimento (dias)</label>
                    <input type="number" name="prazo_recebimento_dias" min="0" max="180" step="1" value="1">
                </div>
                <div class="taoc-field taoc-field-inline">
                    <input type="checkbox" name="ativo" id="taoc-taxa-ativo" style="width:auto">
                    <label for="taoc-taxa-ativo" style="margin:0">Taxa ativa</label>
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
