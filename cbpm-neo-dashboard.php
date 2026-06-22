<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Painel do Neo (chatbot) — métricas operacionais do atendimento.
 * Conversas, leads, pedidos, faturamento e conversão. SEM dados de CRM
 * (o consolidado/CRM fica na Visão Geral). Fontes: /historico, /leads, /pedidos.
 */
function cbpm_page_neo_dashboard() {
    if ( ! cbpm_can_access() ) return;

    $cid = function_exists( 'cbpm_current_cliente_id' ) ? cbpm_current_cliente_id() : null;
    $cf  = $cid ? '&cliente_id=eq.' . urlencode( $cid ) : '';

    // ── Período ──────────────────────────────────────────────────────────────
    $periodo  = sanitize_key( $_GET['periodo'] ?? '30d' );
    $periodos = [ 'hoje' => 'Hoje', '7d' => '7 dias', '30d' => '30 dias', '90d' => '90 dias', 'mes' => 'Este mês' ];
    if ( ! isset( $periodos[ $periodo ] ) ) $periodo = '30d';
    $tz  = new DateTimeZone( 'America/Sao_Paulo' );
    $now = new DateTime( 'now', $tz );
    switch ( $periodo ) {
        case 'hoje': $date_from = $now->format( 'Y-m-d' ); break;
        case '7d':   $date_from = ( clone $now )->modify( '-6 days' )->format( 'Y-m-d' ); break;
        case '90d':  $date_from = ( clone $now )->modify( '-89 days' )->format( 'Y-m-d' ); break;
        case 'mes':  $date_from = $now->format( 'Y-m' ) . '-01'; break;
        default:     $date_from = ( clone $now )->modify( '-29 days' )->format( 'Y-m-d' );
    }
    $qdt = '&criado_em=gte.' . $date_from . 'T00:00:00-03:00';

    // ── Dados (chatbot/Neo) ──────────────────────────────────────────────────
    $r = cbpm_api( '/historico?select=phone,criado_em' . $cf . $qdt . '&limit=5000' );
    $hist  = $r['ok'] ? ( $r['data'] ?? [] ) : [];
    $r = cbpm_api( '/leads?select=id,status,criado_em' . $cf . $qdt . '&limit=5000' );
    $leads = $r['ok'] ? ( $r['data'] ?? [] ) : [];
    $r = cbpm_api( '/pedidos?select=id,total,status,criado_em' . $cf . $qdt . '&limit=5000' );
    $ped   = $r['ok'] ? ( $r['data'] ?? [] ) : [];

    // ── KPIs ─────────────────────────────────────────────────────────────────
    $phones = []; foreach ( $hist as $h ) { if ( ! empty( $h['phone'] ) ) $phones[ $h['phone'] ] = 1; }
    $n_conv  = count( $phones );
    $n_leads = count( $leads );
    $n_ped   = count( $ped );
    $fat     = array_sum( array_map( function ( $p ) { return (float) ( $p['total'] ?? 0 ); }, $ped ) );
    $conv_pct = $n_leads > 0 ? round( $n_ped / $n_leads * 100, 1 ) : 0.0;

    // ── Por status ───────────────────────────────────────────────────────────
    $ls = []; foreach ( $leads as $l ) { $s = $l['status'] ?: 'novo'; $ls[ $s ] = ( $ls[ $s ] ?? 0 ) + 1; }
    $ps = []; foreach ( $ped as $p )   { $s = $p['status'] ?: 'novo'; $ps[ $s ] = ( $ps[ $s ] ?? 0 ) + 1; }
    $lbl_l = [ 'novo' => 'Novo', 'contatado' => 'Contatado', 'negociando' => 'Negociando', 'fechado' => 'Fechado', 'perdido' => 'Perdido' ];
    $lbl_p = [ 'novo' => 'Novo', 'confirmado' => 'Confirmado', 'entregue' => 'Entregue', 'cancelado' => 'Cancelado' ];

    // ── Recentes ─────────────────────────────────────────────────────────────
    $r = cbpm_api( '/leads?select=nome,telefone,status,criado_em' . $cf . '&order=criado_em.desc&limit=8' );
    $rec_leads = $r['ok'] ? ( $r['data'] ?? [] ) : [];
    $r = cbpm_api( '/pedidos?select=total,status,criado_em' . $cf . '&order=criado_em.desc&limit=8' );
    $rec_ped = $r['ok'] ? ( $r['data'] ?? [] ) : [];

    $brl = function ( $v ) { return 'R$ ' . number_format( (float) $v, 2, ',', '.' ); };
    $purl = function ( $p ) { return esc_url( cbpm_url( 'neo-dashboard', [ 'periodo' => $p ] ) ); };
    $dt = function ( $v ) { return $v ? date_i18n( 'd/m H:i', strtotime( $v ) ) : '—'; };
    ?>
    <div class="wrap" style="max-width:1100px">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin:8px 0 6px">
            <h1 style="margin:0">&#x1F4DE; Painel do Neo</h1>
            <div style="display:flex;gap:6px;font-size:13px">
                <?php foreach ( $periodos as $pk => $plabel ) : ?>
                <a href="<?php echo $purl( $pk ); ?>" class="button<?php echo $periodo === $pk ? ' button-primary' : ''; ?>"><?php echo esc_html( $plabel ); ?></a>
                <?php endforeach; ?>
            </div>
        </div>
        <p style="color:#646970;font-size:13px;margin:0 0 16px">Atendimento via chatbot &mdash; período: <strong><?php echo esc_html( $periodos[ $periodo ] ); ?></strong></p>

        <!-- KPIs -->
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:14px;margin-bottom:20px">
            <?php
            $kpis = [
                [ '&#x1F4AC; Conversas', $n_conv, '#1d2327' ],
                [ '&#x1F9F2; Leads',     $n_leads, '#1d2327' ],
                [ '&#x1F6CD;&#xFE0F; Pedidos', $n_ped, '#1d2327' ],
                [ '&#x1F4B5; Faturamento', $brl( $fat ), '#16a34a' ],
                [ '&#x1F501; Convers&atilde;o', $conv_pct . '%', '#1d4ed8' ],
            ];
            foreach ( $kpis as $k ) : ?>
            <div style="background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:16px">
                <div style="font-size:12px;color:#646970"><?php echo $k[0]; ?></div>
                <strong style="font-size:22px;color:<?php echo $k[2]; ?>"><?php echo esc_html( $k[1] ); ?></strong>
            </div>
            <?php endforeach; ?>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:18px">
            <!-- Leads por status -->
            <div>
                <h2 style="font-size:15px;margin:0 0 8px">Leads por status</h2>
                <?php if ( empty( $ls ) ) : ?><p style="color:#a7aaad;font-size:13px">Nenhum lead no período.</p>
                <?php else : ?>
                <table class="widefat striped" style="font-size:13px"><tbody>
                    <?php foreach ( $lbl_l as $sk => $sv ) : if ( empty( $ls[ $sk ] ) ) continue; ?>
                    <tr><td><?php echo esc_html( $sv ); ?></td><td style="text-align:right;font-weight:600"><?php echo (int) $ls[ $sk ]; ?></td></tr>
                    <?php endforeach; ?>
                </tbody></table>
                <?php endif; ?>
            </div>
            <!-- Pedidos por status -->
            <div>
                <h2 style="font-size:15px;margin:0 0 8px">Pedidos por status</h2>
                <?php if ( empty( $ps ) ) : ?><p style="color:#a7aaad;font-size:13px">Nenhum pedido no período.</p>
                <?php else : ?>
                <table class="widefat striped" style="font-size:13px"><tbody>
                    <?php foreach ( $lbl_p as $sk => $sv ) : if ( empty( $ps[ $sk ] ) ) continue; ?>
                    <tr><td><?php echo esc_html( $sv ); ?></td><td style="text-align:right;font-weight:600"><?php echo (int) $ps[ $sk ]; ?></td></tr>
                    <?php endforeach; ?>
                </tbody></table>
                <?php endif; ?>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:18px;margin-top:18px">
            <!-- Leads recentes -->
            <div>
                <h2 style="font-size:15px;margin:0 0 8px">Leads recentes</h2>
                <?php if ( empty( $rec_leads ) ) : ?><p style="color:#a7aaad;font-size:13px">&mdash;</p>
                <?php else : ?>
                <table class="widefat striped" style="font-size:13px">
                    <thead><tr><th>Nome</th><th>Telefone</th><th>Status</th><th>Quando</th></tr></thead>
                    <tbody>
                    <?php foreach ( $rec_leads as $l ) : ?>
                    <tr>
                        <td><?php echo esc_html( $l['nome'] ?: '—' ); ?></td>
                        <td><?php echo esc_html( $l['telefone'] ?: '—' ); ?></td>
                        <td><?php echo esc_html( $lbl_l[ $l['status'] ?? '' ] ?? ( $l['status'] ?: '—' ) ); ?></td>
                        <td style="white-space:nowrap"><?php echo esc_html( $dt( $l['criado_em'] ?? '' ) ); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
            <!-- Pedidos recentes -->
            <div>
                <h2 style="font-size:15px;margin:0 0 8px">Pedidos recentes</h2>
                <?php if ( empty( $rec_ped ) ) : ?><p style="color:#a7aaad;font-size:13px">&mdash;</p>
                <?php else : ?>
                <table class="widefat striped" style="font-size:13px">
                    <thead><tr><th>Valor</th><th>Status</th><th>Quando</th></tr></thead>
                    <tbody>
                    <?php foreach ( $rec_ped as $p ) : ?>
                    <tr>
                        <td style="font-weight:600"><?php echo $brl( $p['total'] ?? 0 ); ?></td>
                        <td><?php echo esc_html( $lbl_p[ $p['status'] ?? '' ] ?? ( $p['status'] ?: '—' ) ); ?></td>
                        <td style="white-space:nowrap"><?php echo esc_html( $dt( $p['criado_em'] ?? '' ) ); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}
