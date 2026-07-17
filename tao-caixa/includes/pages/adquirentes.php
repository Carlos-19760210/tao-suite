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
            <h1>&#x1F3E6; Operadoras de Cartão</h1>
            <button class="taoc-btn taoc-btn-primary" data-caixa-new data-modal="taoc-adq-modal" data-title="Nova Operadora de Cartão">+ Nova Operadora</button>
        </div>

        <?php if ( ! $cid ) : ?>
        <div class="notice notice-warning"><p>Cliente não identificado.</p></div>
        <?php endif; ?>

        <?php if ( empty( $rows ) ) : ?>
        <div class="taoc-empty">
            <p>Nenhuma operadora cadastrada.</p>
            <button class="taoc-btn taoc-btn-primary" data-caixa-new data-modal="taoc-adq-modal" data-title="Nova Operadora de Cartão">+ Cadastrar primeira</button>
        </div>
        <?php else : ?>
        <table class="taoc-table">
            <thead>
                <tr><th>Operadora</th><th>Recebimento</th><th style="text-align:right">Antecipação</th><th style="text-align:right">Prazo</th><th style="text-align:center">Status</th><th style="text-align:center;width:160px">Ações</th></tr>
            </thead>
            <tbody>
            <?php foreach ( $rows as $a ) :
                $json = wp_json_encode( [
                    'id'                    => $a['id'],
                    'nome'                  => $a['nome'] ?? '',
                    'taxa_antecipacao_pct'  => $a['taxa_antecipacao_pct'] ?? 0,
                    'politica_recebimento'  => $a['politica_recebimento'] ?? 'antecipado',
                    'antecipacao_modo'      => $a['antecipacao_modo'] ?? 'pct_fixo',
                    'prazo_antecipado_dias' => $a['prazo_antecipado_dias'] ?? 1,
                    'ativo'                 => ! empty( $a['ativo'] ) ? '1' : '0',
                ] );
                $ativo = ! empty( $a['ativo'] );
                $pol   = ( $a['politica_recebimento'] ?? 'antecipado' ) === 'fluxo' ? 'Fluxo de parcelas' : 'Antecipa tudo';
                $modo  = ( $a['antecipacao_modo'] ?? 'pct_fixo' ) === 'pct_mes' ? ' a.m.' : '';
            ?>
                <tr data-row data-id="<?php echo esc_attr( $a['id'] ); ?>" data-json='<?php echo esc_attr( $json ); ?>'>
                    <td><strong><?php echo esc_html( $a['nome'] ?? '' ); ?></strong></td>
                    <td><?php echo esc_html( $pol ); ?></td>
                    <td style="text-align:right"><?php echo number_format( (float) ( $a['taxa_antecipacao_pct'] ?? 0 ), 3, ',', '.' ); ?>%<?php echo esc_html( $modo ); ?></td>
                    <td style="text-align:right">D+<?php echo (int) ( $a['prazo_antecipado_dias'] ?? 1 ); ?></td>
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
                    <label>Política de recebimento</label>
                    <select name="politica_recebimento">
                        <option value="antecipado">Antecipa tudo (cai D+prazo com taxa de antecipação)</option>
                        <option value="fluxo">Fluxo de parcelas (recebe 1/N a cada 30 dias)</option>
                    </select>
                </div>
                <div class="taoc-field taoc-field-inline">
                    <div style="flex:1">
                        <label>Taxa de antecipação (%)</label>
                        <input type="number" name="taxa_antecipacao_pct" step="0.001" min="0" placeholder="0,000">
                    </div>
                    <div style="flex:1">
                        <label>Modo da antecipação</label>
                        <select name="antecipacao_modo">
                            <option value="pct_fixo">% fixo sobre o valor</option>
                            <option value="pct_mes">% ao mês (por parcela antecipada)</option>
                        </select>
                    </div>
                </div>
                <div class="taoc-field">
                    <label>Prazo do antecipado (dias)</label>
                    <input type="number" name="prazo_antecipado_dias" min="0" max="30" step="1" value="1">
                </div>
                <p style="font-size:11px;color:#94a3b8;margin:-4px 0 8px">A antecipação só age em <strong>crédito</strong> com política "Antecipa tudo". As taxas de MDR (débito/crédito × bandeira × parcelas) ficam na tela <strong>Taxas</strong>, vinculadas a esta operadora.</p>
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
