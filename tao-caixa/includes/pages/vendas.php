<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Vendas do Caixa — listagem (somente leitura, Fase 1).
 * Mostra as vendas geradas a partir dos cards ganhos (origem=funil) e futuras avulsas.
 */

function tao_caixa_venda_status_badge( $st ) {
    $map = [
        'aberta'    => [ 'A receber', '#dbeafe', '#1d4ed8' ],
        'parcial'   => [ 'Parcial',   '#fef3c7', '#92400e' ],
        'quitada'   => [ 'Quitada',   '#dcfce7', '#166534' ],
        'cancelada' => [ 'Cancelada', '#f1f5f9', '#64748b' ],
        'estornada' => [ 'Estornada', '#fee2e2', '#991b1b' ],
    ];
    $m = $map[ $st ] ?? [ $st ?: '—', '#f1f5f9', '#64748b' ];
    return '<span style="font-size:11px;font-weight:700;padding:2px 8px;border-radius:10px;background:' . $m[1] . ';color:' . $m[2] . '">' . esc_html( $m[0] ) . '</span>';
}

function tao_caixa_venda_origem_badge( $o ) {
    $funil = ( $o !== 'avulsa' );
    $label = $funil ? '&#x1F517; Funil' : '&#x1F6D2; Avulsa';
    $bg    = $funil ? '#ede9fe' : '#ecfeff';
    $fg    = $funil ? '#6d28d9' : '#0e7490';
    return '<span style="font-size:11px;font-weight:600;padding:2px 8px;border-radius:10px;background:' . $bg . ';color:' . $fg . '">' . $label . '</span>';
}

function tao_caixa_page_vendas() {
    if ( ! tao_caixa_pode_operar() ) { echo '<div class="wrap"><p>Sem permissão para operar o caixa.</p></div>'; return; }
    tao_caixa_assets();

    $cid    = tao_caixa_cliente_id();
    $status = sanitize_text_field( $_GET['status'] ?? '' );
    $origem = sanitize_text_field( $_GET['origem'] ?? '' );

    $vendas     = [];
    $tot_geral  = 0.0;
    $tot_receber = 0.0;

    if ( $cid ) {
        $flt  = $status ? '&status=eq.' . rawurlencode( $status ) : '';
        $flt .= $origem ? '&origem=eq.' . rawurlencode( $origem ) : '';
        $rv = tao_caixa_api(
            "/caixa_vendas?cliente_id=eq.$cid$flt&order=criado_em.desc&limit=300" .
            "&select=id,paciente_nome,whatsapp,valor_total,valor_pago,status,origem,criado_em"
        );
        $vendas = $rv['ok'] ? ( $rv['data'] ?? [] ) : [];
        foreach ( $vendas as $v ) {
            $tot_geral += floatval( $v['valor_total'] ?? 0 );
            if ( in_array( $v['status'] ?? '', [ 'aberta', 'parcial' ], true ) ) {
                $tot_receber += floatval( $v['valor_total'] ?? 0 ) - floatval( $v['valor_pago'] ?? 0 );
            }
        }
    }

    $brl = function ( $v ) { return 'R$ ' . number_format( (float) $v, 2, ',', '.' ); };
    $url = function ( $params ) { return esc_url( tao_caixa_url( 'caixa-vendas', $params ) ); };
    ?>
    <div class="wrap taoc-wrap">
        <div class="taoc-bar">
            <h1>&#x1F9FE; Vendas do Caixa</h1>
        </div>

        <?php if ( ! $cid ) : ?>
        <div class="notice notice-warning"><p>Cliente não identificado.</p></div>
        <?php else : ?>

        <!-- KPIs -->
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:14px;margin-bottom:18px">
            <div class="taoc-card" style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:16px">
                <div style="font-size:12px;color:#64748b">Vendas listadas</div>
                <strong style="font-size:22px"><?php echo count( $vendas ); ?></strong>
            </div>
            <div class="taoc-card" style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:16px">
                <div style="font-size:12px;color:#64748b">Valor total</div>
                <strong style="font-size:22px"><?php echo $brl( $tot_geral ); ?></strong>
            </div>
            <div class="taoc-card" style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:16px">
                <div style="font-size:12px;color:#64748b">A receber (aberto/parcial)</div>
                <strong style="font-size:22px;color:#1d4ed8"><?php echo $brl( $tot_receber ); ?></strong>
            </div>
        </div>

        <!-- Filtros -->
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px;font-size:13px">
            <span style="color:#64748b;align-self:center">Status:</span>
            <a class="taoc-btn<?php echo $status===''?' taoc-btn-primary':''; ?>" href="<?php echo $url( $origem?[ 'origem'=>$origem ]:[] ); ?>">Todos</a>
            <?php foreach ( [ 'aberta'=>'A receber', 'parcial'=>'Parcial', 'quitada'=>'Quitada', 'cancelada'=>'Cancelada' ] as $sk => $sl ) :
                $p = [ 'status' => $sk ]; if ( $origem ) $p['origem'] = $origem; ?>
            <a class="taoc-btn<?php echo $status===$sk?' taoc-btn-primary':''; ?>" href="<?php echo $url( $p ); ?>"><?php echo esc_html( $sl ); ?></a>
            <?php endforeach; ?>
            <span style="width:1px;background:#e2e8f0;margin:0 4px"></span>
            <span style="color:#64748b;align-self:center">Origem:</span>
            <a class="taoc-btn<?php echo $origem===''?' taoc-btn-primary':''; ?>" href="<?php echo $url( $status?[ 'status'=>$status ]:[] ); ?>">Todas</a>
            <?php foreach ( [ 'funil'=>'Funil', 'avulsa'=>'Avulsa' ] as $ok => $ol ) :
                $p = [ 'origem' => $ok ]; if ( $status ) $p['status'] = $status; ?>
            <a class="taoc-btn<?php echo $origem===$ok?' taoc-btn-primary':''; ?>" href="<?php echo $url( $p ); ?>"><?php echo esc_html( $ol ); ?></a>
            <?php endforeach; ?>
        </div>

        <?php if ( empty( $vendas ) ) : ?>
        <div class="taoc-empty">
            <p>Nenhuma venda encontrada<?php echo ( $status || $origem ) ? ' com esse filtro' : ''; ?>.</p>
            <p style="font-size:12px;color:#94a3b8">As vendas nascem automaticamente quando um card é fechado como Ganho (cruza para o Pós-vendas).</p>
        </div>
        <?php else : ?>
        <table class="taoc-table">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Paciente</th>
                    <th>WhatsApp</th>
                    <th style="text-align:center">Origem</th>
                    <th style="text-align:center">Status</th>
                    <th style="text-align:right">Total</th>
                    <th style="text-align:right">Pago</th>
                    <th style="text-align:right">A receber</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $vendas as $v ) :
                $total = floatval( $v['valor_total'] ?? 0 );
                $pago  = floatval( $v['valor_pago'] ?? 0 );
                $receber = max( 0, $total - $pago );
                $dt = ! empty( $v['criado_em'] ) ? date_i18n( 'd/m/Y H:i', strtotime( $v['criado_em'] ) ) : '—';
            ?>
                <tr>
                    <td style="white-space:nowrap"><?php echo esc_html( $dt ); ?></td>
                    <td><strong><?php echo esc_html( $v['paciente_nome'] ?: '—' ); ?></strong></td>
                    <td style="color:#475569"><?php echo esc_html( $v['whatsapp'] ?: '—' ); ?></td>
                    <td style="text-align:center"><?php echo tao_caixa_venda_origem_badge( $v['origem'] ?? '' ); ?></td>
                    <td style="text-align:center"><?php echo tao_caixa_venda_status_badge( $v['status'] ?? '' ); ?></td>
                    <td style="text-align:right;font-weight:700"><?php echo $brl( $total ); ?></td>
                    <td style="text-align:right;color:#16a34a"><?php echo $brl( $pago ); ?></td>
                    <td style="text-align:right;color:<?php echo $receber > 0 ? '#1d4ed8' : '#94a3b8'; ?>"><?php echo $brl( $receber ); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p style="font-size:11px;color:#94a3b8;margin-top:10px">Exibindo as <?php echo count( $vendas ); ?> vendas mais recentes (máx. 300). Tela somente leitura — baixa de pagamento e estorno virão no PDV (Fase 1, passo 4).</p>
        <?php endif; ?>

        <?php endif; ?>
    </div>
    <?php
}
