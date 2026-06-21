<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function tao_caixa_faixa_label( $min, $max ) {
    $min = (int) $min; $max = (int) $max;
    if ( $min <= 1 && $max <= 1 ) return '1x (à vista)';
    if ( $min === $max )          return $min . 'x';
    return $min . 'x a ' . $max . 'x';
}

function tao_caixa_page_taxas() {
    if ( ! tao_caixa_pode_operar() ) { echo '<div class="wrap"><p>Sem permissão para operar o caixa.</p></div>'; return; }
    tao_caixa_assets();

    $cid       = tao_caixa_cliente_id();
    $formas    = [];
    $forma_map = [];
    $taxas     = [];
    $forma_filtro = sanitize_text_field( $_GET['forma'] ?? '' );

    if ( $cid ) {
        $rf     = tao_caixa_api( "/caixa_formas_pagamento?cliente_id=eq.$cid&order=nome.asc&select=id,nome,tipo" );
        $formas = $rf['ok'] ? ( $rf['data'] ?? [] ) : [];
        foreach ( $formas as $f ) $forma_map[ $f['id'] ] = $f['nome'];

        $flt = $forma_filtro ? "&forma_pagamento_id=eq.$forma_filtro" : '';
        $rt    = tao_caixa_api( "/caixa_taxas?cliente_id=eq.$cid$flt&order=forma_pagamento_id.asc,parcela_min.asc" );
        $taxas = $rt['ok'] ? ( $rt['data'] ?? [] ) : [];
    }
    ?>
    <div class="wrap taoc-wrap">
        <div class="taoc-bar">
            <h1>&#x1F4CA; Taxas por Faixa de Parcelas</h1>
            <?php if ( $formas ) : ?>
            <button class="taoc-btn taoc-btn-primary" data-caixa-new data-modal="taoc-taxa-modal" data-title="Nova Taxa">+ Nova Taxa</button>
            <?php endif; ?>
        </div>

        <?php if ( $forma_filtro && isset( $forma_map[ $forma_filtro ] ) ) : ?>
        <p style="font-size:13px;color:#475569;margin:0 0 12px">
            Filtrando por forma: <strong><?php echo esc_html( $forma_map[ $forma_filtro ] ); ?></strong>
            &middot; <a href="<?php echo esc_url( tao_caixa_url( 'caixa-taxas' ) ); ?>">ver todas</a>
        </p>
        <?php endif; ?>

        <?php if ( ! $cid ) : ?>
        <div class="notice notice-warning"><p>Cliente não identificado.</p></div>
        <?php elseif ( ! $formas ) : ?>
        <div class="taoc-empty">
            <p>Cadastre uma <strong>Forma de Pagamento</strong> de cartão antes de definir as taxas.</p>
            <a class="taoc-btn taoc-btn-primary" href="<?php echo esc_url( tao_caixa_url( 'caixa-formas' ) ); ?>">Ir para Formas de Pagamento</a>
        </div>
        <?php elseif ( empty( $taxas ) ) : ?>
        <div class="taoc-empty">
            <p>Nenhuma faixa de taxa cadastrada.</p>
            <button class="taoc-btn taoc-btn-primary" data-caixa-new data-modal="taoc-taxa-modal" data-title="Nova Taxa">+ Cadastrar primeira</button>
        </div>
        <?php else : ?>
        <table class="taoc-table">
            <thead>
                <tr><th>Forma de Pagamento</th><th>Faixa de parcelas</th><th style="text-align:right">Taxa</th><th style="text-align:right">Prazo</th><th style="text-align:center">Status</th><th style="text-align:center;width:150px">Ações</th></tr>
            </thead>
            <tbody>
            <?php foreach ( $taxas as $t ) :
                $json = wp_json_encode( [
                    'id'                     => $t['id'],
                    'forma_pagamento_id'     => $t['forma_pagamento_id'] ?? '',
                    'parcela_min'            => $t['parcela_min'] ?? 1,
                    'parcela_max'            => $t['parcela_max'] ?? 1,
                    'taxa_pct'               => $t['taxa_pct'] ?? 0,
                    'prazo_recebimento_dias' => $t['prazo_recebimento_dias'] ?? 1,
                    'ativo'                  => ! empty( $t['ativo'] ) ? '1' : '0',
                ] );
                $ativo = ! empty( $t['ativo'] );
            ?>
                <tr data-row data-id="<?php echo esc_attr( $t['id'] ); ?>" data-json='<?php echo esc_attr( $json ); ?>'>
                    <td><strong><?php echo esc_html( $forma_map[ $t['forma_pagamento_id'] ] ?? '—' ); ?></strong></td>
                    <td><?php echo esc_html( tao_caixa_faixa_label( $t['parcela_min'] ?? 1, $t['parcela_max'] ?? 1 ) ); ?></td>
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
                    <label>Forma de Pagamento *</label>
                    <select name="forma_pagamento_id" required>
                        <option value="">— Selecione —</option>
                        <?php foreach ( $formas as $f ) : ?>
                        <option value="<?php echo esc_attr( $f['id'] ); ?>" <?php selected( $forma_filtro, $f['id'] ); ?>><?php echo esc_html( $f['nome'] ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="taoc-field taoc-field-inline">
                    <div style="flex:1">
                        <label>De (parcela mínima)</label>
                        <input type="number" name="parcela_min" min="1" max="24" step="1" value="1">
                    </div>
                    <div style="flex:1">
                        <label>Até (parcela máxima)</label>
                        <input type="number" name="parcela_max" min="1" max="24" step="1" value="1">
                    </div>
                </div>
                <p style="font-size:11px;color:#94a3b8;margin:-4px 0 8px">Ex.: 1 a 1 = à vista · 2 a 3 = até 3x · 4 a 6 = de 4x a 6x</p>
                <div class="taoc-field">
                    <label>Taxa (%) *</label>
                    <input type="number" name="taxa_pct" step="0.001" min="0" placeholder="0,890" required>
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
