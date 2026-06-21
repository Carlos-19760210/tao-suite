<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function tao_caixa_page_formas_pgto() {
    if ( ! tao_caixa_pode_operar() ) { echo '<div class="wrap"><p>Sem permissão para operar o caixa.</p></div>'; return; }
    tao_caixa_assets();

    $cid     = tao_caixa_cliente_id();
    $adqs    = [];
    $adq_map = [];
    $formas  = [];
    if ( $cid ) {
        $ra   = tao_caixa_api( "/caixa_adquirentes?cliente_id=eq.$cid&order=nome.asc&select=id,nome" );
        $adqs = $ra['ok'] ? ( $ra['data'] ?? [] ) : [];
        foreach ( $adqs as $a ) $adq_map[ $a['id'] ] = $a['nome'];

        $rf     = tao_caixa_api( "/caixa_formas_pagamento?cliente_id=eq.$cid&order=ordem.asc,nome.asc" );
        $formas = $rf['ok'] ? ( $rf['data'] ?? [] ) : [];
    }
    $tipos = [
        'dinheiro' => 'Dinheiro', 'pix' => 'PIX', 'debito' => 'Cartão Débito',
        'credito'  => 'Cartão Crédito', 'boleto' => 'Boleto', 'link' => 'Link de Pagamento', 'outro' => 'Outro',
    ];
    $canais = [
        'maquina' => 'Maquininha', 'link' => 'Link', 'pix' => 'PIX', 'dinheiro' => 'Dinheiro',
        'boleto'  => 'Boleto', 'manual' => 'Manual', 'outro' => 'Outro',
    ];
    ?>
    <div class="wrap taoc-wrap">
        <div class="taoc-bar">
            <h1>&#x1F4B3; Formas de Pagamento</h1>
            <button class="taoc-btn taoc-btn-primary" data-caixa-new data-modal="taoc-forma-modal" data-title="Nova Forma de Pagamento">+ Nova Forma</button>
        </div>

        <?php if ( ! $cid ) : ?>
        <div class="notice notice-warning"><p>Cliente não identificado.</p></div>
        <?php endif; ?>

        <?php if ( empty( $formas ) ) : ?>
        <div class="taoc-empty">
            <p>Nenhuma forma de pagamento cadastrada.</p>
            <button class="taoc-btn taoc-btn-primary" data-caixa-new data-modal="taoc-forma-modal" data-title="Nova Forma de Pagamento">+ Cadastrar primeira</button>
        </div>
        <?php else : ?>
        <table class="taoc-table">
            <thead>
                <tr><th>Nome</th><th>Tipo</th><th>Canal</th><th>Operadora</th><th style="text-align:right">Prazo</th><th style="text-align:right">Taxa fixa</th><th style="text-align:center">Status</th><th style="text-align:center;width:200px">Ações</th></tr>
            </thead>
            <tbody>
            <?php foreach ( $formas as $f ) :
                $json = wp_json_encode( [
                    'id'                     => $f['id'],
                    'nome'                   => $f['nome'] ?? '',
                    'tipo'                   => $f['tipo'] ?? 'dinheiro',
                    'canal'                  => $f['canal'] ?? '',
                    'adquirente_id'          => $f['adquirente_id'] ?? '',
                    'prazo_recebimento_dias' => $f['prazo_recebimento_dias'] ?? 0,
                    'taxa_pct'               => $f['taxa_pct'] ?? 0,
                    'conta_no_dinheiro'      => ! empty( $f['conta_no_dinheiro'] ) ? '1' : '0',
                    'ordem'                  => $f['ordem'] ?? 0,
                    'ativo'                  => ! empty( $f['ativo'] ) ? '1' : '0',
                ] );
                $ativo  = ! empty( $f['ativo'] );
                $is_cartao = in_array( $f['tipo'] ?? '', [ 'debito', 'credito', 'link' ], true );
            ?>
                <tr data-row data-id="<?php echo esc_attr( $f['id'] ); ?>" data-json='<?php echo esc_attr( $json ); ?>'>
                    <td><strong><?php echo esc_html( $f['nome'] ?? '' ); ?></strong></td>
                    <td><?php echo esc_html( $tipos[ $f['tipo'] ?? '' ] ?? $f['tipo'] ); ?></td>
                    <td><?php echo esc_html( ! empty( $f['canal'] ) ? ( $canais[ $f['canal'] ] ?? $f['canal'] ) : '—' ); ?></td>
                    <td><?php echo esc_html( ! empty( $f['adquirente_id'] ) ? ( $adq_map[ $f['adquirente_id'] ] ?? '—' ) : '—' ); ?></td>
                    <td style="text-align:right"><?php echo (int) ( $f['prazo_recebimento_dias'] ?? 0 ); ?> d</td>
                    <td style="text-align:right"><?php echo number_format( (float) ( $f['taxa_pct'] ?? 0 ), 3, ',', '.' ); ?>%</td>
                    <td style="text-align:center"><span class="taoc-pill <?php echo $ativo ? 'on' : 'off'; ?>"><?php echo $ativo ? 'Ativa' : 'Inativa'; ?></span></td>
                    <td style="text-align:center">
                        <?php if ( $is_cartao ) : ?>
                        <a class="taoc-btn" href="<?php echo esc_url( tao_caixa_url( 'caixa-taxas', [ 'forma' => $f['id'] ] ) ); ?>" title="Taxas por bandeira/parcelas">Taxas</a>
                        <?php endif; ?>
                        <button class="taoc-btn" data-caixa-edit data-modal="taoc-forma-modal">Editar</button>
                        <button class="taoc-btn taoc-btn-danger" data-caixa-del data-action="tao_caixa_delete_forma">Excluir</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- Modal -->
    <div id="taoc-forma-modal" class="taoc-modal">
        <div class="taoc-overlay"></div>
        <div class="taoc-box">
            <h2 data-title>Nova Forma de Pagamento</h2>
            <form data-action="tao_caixa_save_forma">
                <input type="hidden" name="id">
                <div class="taoc-field">
                    <label>Nome *</label>
                    <input type="text" name="nome" placeholder="Ex: Dinheiro, PIX, Crédito Cielo" required>
                </div>
                <div class="taoc-field">
                    <label>Tipo *</label>
                    <select name="tipo">
                        <?php foreach ( $tipos as $val => $lbl ) : ?>
                        <option value="<?php echo esc_attr( $val ); ?>"><?php echo esc_html( $lbl ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="taoc-field">
                    <label>Canal (para agrupar nos relatórios)</label>
                    <select name="canal">
                        <option value="">— Não definido —</option>
                        <?php foreach ( $canais as $val => $lbl ) : ?>
                        <option value="<?php echo esc_attr( $val ); ?>"><?php echo esc_html( $lbl ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="taoc-field">
                    <label>Operadora de Cartão (se cartão)</label>
                    <select name="adquirente_id">
                        <option value="">— Nenhuma —</option>
                        <?php foreach ( $adqs as $a ) : ?>
                        <option value="<?php echo esc_attr( $a['id'] ); ?>"><?php echo esc_html( $a['nome'] ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="taoc-field">
                    <label>Prazo de recebimento (dias) — p/ PIX/boleto</label>
                    <input type="number" name="prazo_recebimento_dias" min="0" max="180" step="1" value="0">
                </div>
                <div class="taoc-field">
                    <label>Taxa fixa (%) — p/ PIX/boleto sem tabela MDR</label>
                    <input type="number" name="taxa_pct" step="0.001" min="0" value="0">
                </div>
                <div class="taoc-field taoc-field-inline">
                    <input type="checkbox" name="conta_no_dinheiro" id="taoc-forma-cash" style="width:auto">
                    <label for="taoc-forma-cash" style="margin:0">Conta na conferência de dinheiro (só p/ Dinheiro)</label>
                </div>
                <div class="taoc-field">
                    <label>Ordem de exibição</label>
                    <input type="number" name="ordem" min="0" step="1" value="0">
                </div>
                <div class="taoc-field taoc-field-inline">
                    <input type="checkbox" name="ativo" id="taoc-forma-ativo" style="width:auto">
                    <label for="taoc-forma-ativo" style="margin:0">Forma ativa</label>
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
