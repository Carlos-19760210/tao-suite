<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function tao_cotacoes_page_fornecedores() {
    if ( ! tao_cot_pode() ) { echo '<div class="wrap"><p>Sem permissão para operar cotações.</p></div>'; return; }
    tao_cot_assets();

    $cid  = tao_cot_cliente_id();
    $rows = [];
    if ( $cid ) {
        $r    = tao_cot_api( "/fornecedores?cliente_id=eq.$cid&order=ativo.desc,nome.asc&limit=500" );
        $rows = $r['ok'] ? ( $r['data'] ?? [] ) : [];
    }
    ?>
    <div class="wrap taocot-wrap">
        <div class="taocot-bar">
            <h1>&#x1F69A; Fornecedores</h1>
            <div>
                <a class="taocot-btn" href="<?php echo esc_url( tao_cot_url( 'cotacoes' ) ); ?>">&larr; Cotações</a>
                <button class="taocot-btn taocot-btn-primary" data-cot-new data-modal="taocot-forn-modal" data-title="Novo Fornecedor">+ Novo Fornecedor</button>
            </div>
        </div>

        <?php if ( ! $cid ) : ?>
        <div class="notice notice-warning"><p>Cliente não identificado.</p></div>
        <?php endif; ?>

        <?php if ( empty( $rows ) ) : ?>
        <div class="taocot-empty">
            <p>Nenhum fornecedor cadastrado. Os fornecedores recebem a solicitação de proposta pelo WhatsApp.</p>
            <button class="taocot-btn taocot-btn-primary" data-cot-new data-modal="taocot-forn-modal" data-title="Novo Fornecedor">+ Cadastrar primeiro</button>
        </div>
        <?php else : ?>
        <div class="taocot-tscroll">
        <table class="taocot-table">
            <thead>
                <tr><th>Fornecedor</th><th>WhatsApp</th><th>Contato</th><th style="text-align:center">Status</th><th style="text-align:center;width:160px">Ações</th></tr>
            </thead>
            <tbody>
            <?php foreach ( $rows as $f ) :
                $json = wp_json_encode( [
                    'id'       => $f['id'],
                    'nome'     => $f['nome'] ?? '',
                    'whatsapp' => $f['whatsapp'] ?? '',
                    'contato'  => $f['contato'] ?? '',
                    'obs'      => $f['obs'] ?? '',
                    'ativo'    => ! empty( $f['ativo'] ) ? '1' : '0',
                ] );
                $ativo = ! empty( $f['ativo'] );
            ?>
                <tr data-row data-id="<?php echo esc_attr( $f['id'] ); ?>" data-json='<?php echo esc_attr( $json ); ?>'>
                    <td><strong><?php echo esc_html( $f['nome'] ?? '' ); ?></strong>
                        <?php if ( ! empty( $f['obs'] ) ) : ?><div class="taocot-muted"><?php echo esc_html( $f['obs'] ); ?></div><?php endif; ?>
                    </td>
                    <td><?php echo esc_html( $f['whatsapp'] ?? '' ); ?></td>
                    <td><?php echo esc_html( $f['contato'] ?? '' ); ?></td>
                    <td style="text-align:center"><span class="taocot-pill <?php echo $ativo ? 'on' : 'off'; ?>"><?php echo $ativo ? 'Ativo' : 'Inativo'; ?></span></td>
                    <td style="text-align:center">
                        <button class="taocot-btn" data-cot-edit data-modal="taocot-forn-modal">Editar</button>
                        <button class="taocot-btn taocot-btn-danger" data-cot-del data-action="tao_cot_delete_fornecedor">Excluir</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <p class="taocot-muted">Quando um fornecedor cadastrado responder no WhatsApp, o card dele vai direto para <strong>Aguardando Atendimento</strong> (o bot não responde fornecedores). Fornecedor já usado em cotação é desativado em vez de excluído.</p>
        <?php endif; ?>
    </div>

    <!-- Modal -->
    <div id="taocot-forn-modal" class="taocot-modal">
        <div class="taocot-overlay"></div>
        <div class="taocot-box">
            <h2 data-title>Novo Fornecedor</h2>
            <form data-action="tao_cot_save_fornecedor">
                <input type="hidden" name="id">
                <div class="taocot-field">
                    <label>Nome do fornecedor *</label>
                    <input type="text" name="nome" placeholder="Ex: FAGRON, FLORIEN, INFINITY" required>
                </div>
                <div class="taocot-field">
                    <label>WhatsApp (com DDD, só números) *</label>
                    <input type="text" name="whatsapp" placeholder="Ex: 5511999998888" required>
                </div>
                <div class="taocot-field">
                    <label>Pessoa de contato</label>
                    <input type="text" name="contato" placeholder="Ex: Maria (vendas)">
                </div>
                <div class="taocot-field">
                    <label>Observações</label>
                    <textarea name="obs" rows="2" placeholder="Prazo de entrega, pedido mínimo..."></textarea>
                </div>
                <div class="taocot-field taocot-field-inline">
                    <input type="checkbox" name="ativo" id="taocot-forn-ativo" style="width:auto">
                    <label for="taocot-forn-ativo" style="margin:0">Fornecedor ativo</label>
                </div>
                <div class="taocot-actions">
                    <button type="submit" class="taocot-btn taocot-btn-primary">Salvar</button>
                    <button type="button" class="taocot-btn" data-cot-cancel>Cancelar</button>
                </div>
            </form>
        </div>
    </div>
    <?php
}
