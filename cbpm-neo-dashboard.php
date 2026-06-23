<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Painel do Neo (chatbot) — métricas operacionais do atendimento.
 * Conversas, leads, pedidos, faturamento, conversão, TMR/TMA + gráfico.
 * Filtro por negócio (admin). SEM dados de CRM. Fontes: /historico, /leads, /pedidos.
 */
function cbpm_page_neo_dashboard() {
    if ( ! cbpm_can_access() ) return;

    $is_admin  = current_user_can( 'manage_options' );
    $is_master = function_exists( 'cbpm_is_master' ) ? cbpm_is_master() : false;
    $own_cid   = function_exists( 'cbpm_current_cliente_id' ) ? cbpm_current_cliente_id() : null;

    // ── Filtro por negócio ───────────────────────────────────────────────────
    $clientes = [];
    $filter_cliente = sanitize_text_field( $_GET['cliente'] ?? '' );
    if ( $is_admin && $is_master ) {
        $rc = cbpm_api( '/clientes?ativo=eq.true&select=id,nome_negocio&order=nome_negocio.asc' );
        $clientes = $rc['ok'] ? ( $rc['data'] ?? [] ) : [];
    } else {
        $filter_cliente = $own_cid ?: '';  // não-master: travado no próprio negócio
    }
    $cf = $filter_cliente ? '&cliente_id=eq.' . urlencode( $filter_cliente ) : '';

    // ── Período ──────────────────────────────────────────────────────────────
    $periodo  = sanitize_key( $_GET['periodo'] ?? '30d' );
    $periodos = [ 'hoje' => 'Hoje', '7d' => '7 dias', '30d' => '30 dias', '90d' => '90 dias', 'mes' => 'Este mês' ];
    if ( ! isset( $periodos[ $periodo ] ) ) $periodo = '30d';
    $tz  = new DateTimeZone( 'America/Sao_Paulo' );
    $now = new DateTime( 'now', $tz );
    switch ( $periodo ) {
        case 'hoje': $start = new DateTime( $now->format( 'Y-m-d' ), $tz ); break;
        case '7d':   $start = ( clone $now )->modify( '-6 days' ); break;
        case '90d':  $start = ( clone $now )->modify( '-89 days' ); break;
        case 'mes':  $start = new DateTime( $now->format( 'Y-m' ) . '-01', $tz ); break;
        default:     $start = ( clone $now )->modify( '-29 days' );
    }
    $date_from = $start->format( 'Y-m-d' );
    $qdt = '&criado_em=gte.' . $date_from . 'T00:00:00-03:00';

    // ── Dados ────────────────────────────────────────────────────────────────
    $r = cbpm_api( '/historico?select=phone,role,criado_em' . $cf . $qdt . '&order=phone.asc,criado_em.asc&limit=8000' );
    $msgs  = $r['ok'] ? ( $r['data'] ?? [] ) : [];
    $r = cbpm_api( '/leads?select=id,status,criado_em' . $cf . $qdt . '&limit=8000' );
    $leads = $r['ok'] ? ( $r['data'] ?? [] ) : [];
    $r = cbpm_api( '/pedidos?select=id,total,status,criado_em' . $cf . $qdt . '&limit=8000' );
    $ped   = $r['ok'] ? ( $r['data'] ?? [] ) : [];

    // ── KPIs básicos ─────────────────────────────────────────────────────────
    $phones = []; foreach ( $msgs as $m ) { if ( ! empty( $m['phone'] ) ) $phones[ $m['phone'] ] = 1; }
    $n_conv  = count( $phones );
    $n_leads = count( $leads );
    $n_ped   = count( $ped );
    $fat     = array_sum( array_map( function ( $p ) { return (float) ( $p['total'] ?? 0 ); }, $ped ) );
    $conv_pct = $n_leads > 0 ? round( $n_ped / $n_leads * 100, 1 ) : 0.0;

    // ── TMR / TMA (segmenta sessões: gap > 30min = nova sessão) ──────────────
    $GAP = 1800;
    $sessions = []; $cur = null; $cur_phone = null; $prev = null;
    foreach ( $msgs as $m ) {
        $ph = $m['phone']; $t = strtotime( $m['criado_em'] ); $role = $m['role'] ?? '';
        if ( $ph !== $cur_phone || ( $prev !== null && $t - $prev > $GAP ) ) {
            if ( $cur ) $sessions[] = $cur;
            $cur = [ 'first' => $t, 'last' => $t, 'fuser' => null, 'fresp' => null ];
            $cur_phone = $ph;
        }
        $cur['last'] = $t;
        if ( $role === 'user' && $cur['fuser'] === null ) $cur['fuser'] = $t;
        if ( $role !== 'user' && $cur['fuser'] !== null && $cur['fresp'] === null ) $cur['fresp'] = $t;
        $prev = $t;
    }
    if ( $cur ) $sessions[] = $cur;
    $tma_s = 0; $tma_n = 0; $tmr_s = 0; $tmr_n = 0;
    foreach ( $sessions as $s ) {
        $tma_s += ( $s['last'] - $s['first'] ); $tma_n++;
        if ( $s['fuser'] !== null && $s['fresp'] !== null && $s['fresp'] >= $s['fuser'] ) { $tmr_s += ( $s['fresp'] - $s['fuser'] ); $tmr_n++; }
    }
    $tma = $tma_n ? $tma_s / $tma_n : 0;
    $tmr = $tmr_n ? $tmr_s / $tmr_n : 0;
    $fmt_dur = function ( $sec ) {
        $sec = (int) round( $sec );
        if ( $sec < 60 ) return $sec . 's';
        if ( $sec < 3600 ) return floor( $sec / 60 ) . 'm ' . ( $sec % 60 ) . 's';
        return floor( $sec / 3600 ) . 'h ' . floor( ( $sec % 3600 ) / 60 ) . 'm';
    };

    // ── Séries por dia (gráfico) ─────────────────────────────────────────────
    $buckets = [];
    for ( $d = clone $start; $d <= $now; $d->modify( '+1 day' ) ) $buckets[ $d->format( 'Y-m-d' ) ] = [ 'ph' => [], 'l' => 0, 'p' => 0 ];
    foreach ( $msgs as $m )  { $k = substr( $m['criado_em'] ?? '', 0, 10 ); if ( isset( $buckets[ $k ] ) ) $buckets[ $k ]['ph'][ $m['phone'] ] = 1; }
    foreach ( $leads as $l ) { $k = substr( $l['criado_em'] ?? '', 0, 10 ); if ( isset( $buckets[ $k ] ) ) $buckets[ $k ]['l']++; }
    foreach ( $ped as $p )   { $k = substr( $p['criado_em'] ?? '', 0, 10 ); if ( isset( $buckets[ $k ] ) ) $buckets[ $k ]['p']++; }
    $chart_labels = array_map( function ( $k ) { return date( 'd/m', strtotime( $k ) ); }, array_keys( $buckets ) );
    $chart_conv   = array_map( function ( $b ) { return count( $b['ph'] ); }, array_values( $buckets ) );
    $chart_leads  = array_map( function ( $b ) { return $b['l']; }, array_values( $buckets ) );
    $chart_ped    = array_map( function ( $b ) { return $b['p']; }, array_values( $buckets ) );

    // ── Status + recentes ────────────────────────────────────────────────────
    $ls = []; foreach ( $leads as $l ) { $s = $l['status'] ?: 'novo'; $ls[ $s ] = ( $ls[ $s ] ?? 0 ) + 1; }
    $ps = []; foreach ( $ped as $p )   { $s = $p['status'] ?: 'novo'; $ps[ $s ] = ( $ps[ $s ] ?? 0 ) + 1; }
    $lbl_l = [ 'novo' => 'Novo', 'contatado' => 'Contatado', 'negociando' => 'Negociando', 'fechado' => 'Fechado', 'perdido' => 'Perdido' ];
    $lbl_p = [ 'novo' => 'Novo', 'confirmado' => 'Confirmado', 'entregue' => 'Entregue', 'cancelado' => 'Cancelado' ];
    $r = cbpm_api( '/leads?select=nome,telefone,status,criado_em' . $cf . '&order=criado_em.desc&limit=8' );
    $rec_leads = $r['ok'] ? ( $r['data'] ?? [] ) : [];
    $r = cbpm_api( '/pedidos?select=total,status,criado_em' . $cf . '&order=criado_em.desc&limit=8' );
    $rec_ped = $r['ok'] ? ( $r['data'] ?? [] ) : [];

    $brl  = function ( $v ) { return 'R$ ' . number_format( (float) $v, 2, ',', '.' ); };
    $purl = function ( $p ) use ( $filter_cliente ) { $a = [ 'periodo' => $p ]; if ( $filter_cliente ) $a['cliente'] = $filter_cliente; return esc_url( cbpm_url( 'neo-dashboard', $a ) ); };
    $dt   = function ( $v ) { return $v ? date_i18n( 'd/m H:i', strtotime( $v ) ) : '—'; };
    $base_url = esc_url( cbpm_url( 'neo-dashboard' ) );
    ?>
    <div class="wrap" style="max-width:1100px">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin:8px 0 6px">
            <h1 style="margin:0">&#x1F4DE; Painel do Agente</h1>
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;font-size:13px">
                <?php if ( $is_admin && $is_master && $clientes ) : ?>
                <select id="neo-cliente" style="padding:6px 8px;border:1px solid #cbd5e1;border-radius:6px;font-size:13px">
                    <option value="">Todos os negócios</option>
                    <?php foreach ( $clientes as $c ) : ?>
                    <option value="<?php echo esc_attr( $c['id'] ); ?>" <?php selected( $filter_cliente, $c['id'] ); ?>><?php echo esc_html( $c['nome_negocio'] ?: '—' ); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
                <?php foreach ( $periodos as $pk => $plabel ) : ?>
                <a href="<?php echo $purl( $pk ); ?>" class="button<?php echo $periodo === $pk ? ' button-primary' : ''; ?>"><?php echo esc_html( $plabel ); ?></a>
                <?php endforeach; ?>
            </div>
        </div>
        <p style="color:#646970;font-size:13px;margin:0 0 16px">Atendimento via chatbot &mdash; período: <strong><?php echo esc_html( $periodos[ $periodo ] ); ?></strong></p>

        <!-- KPIs -->
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:14px;margin-bottom:20px">
            <?php
            $kpis = [
                [ '&#x1F4AC; Conversas',       (string) $n_conv,    '#1d2327' ],
                [ '&#x1F9F2; Leads',           (string) $n_leads,   '#1d2327' ],
                [ '&#x1F6CD;&#xFE0F; Pedidos', (string) $n_ped,     '#1d2327' ],
                [ '&#x1F4B5; Faturamento',     $brl( $fat ),        '#16a34a' ],
                [ '&#x1F501; Convers&atilde;o', $conv_pct . '%',    '#1d4ed8' ],
                [ '&#x23F1;&#xFE0F; TMR (resposta)', $fmt_dur( $tmr ), '#7c3aed' ],
                [ '&#x1F557; TMA (atendimento)', $fmt_dur( $tma ),  '#7c3aed' ],
            ];
            foreach ( $kpis as $k ) : ?>
            <div style="background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:16px">
                <div style="font-size:12px;color:#646970"><?php echo $k[0]; ?></div>
                <strong style="font-size:21px;color:<?php echo $k[2]; ?>"><?php echo esc_html( $k[1] ); ?></strong>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Gráfico -->
        <div style="background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:16px;margin-bottom:18px">
            <h2 style="font-size:15px;margin:0 0 10px">Atividade no período</h2>
            <canvas id="neoChart" height="90"></canvas>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:18px">
            <div>
                <h2 style="font-size:15px;margin:0 0 8px">Leads por status</h2>
                <?php if ( empty( $ls ) ) : ?><p style="color:#a7aaad;font-size:13px">Nenhum lead no período.</p>
                <?php else : ?><table class="widefat striped" style="font-size:13px"><tbody>
                    <?php foreach ( $lbl_l as $sk => $sv ) : if ( empty( $ls[ $sk ] ) ) continue; ?>
                    <tr><td><?php echo esc_html( $sv ); ?></td><td style="text-align:right;font-weight:600"><?php echo (int) $ls[ $sk ]; ?></td></tr>
                    <?php endforeach; ?>
                </tbody></table><?php endif; ?>
            </div>
            <div>
                <h2 style="font-size:15px;margin:0 0 8px">Pedidos por status</h2>
                <?php if ( empty( $ps ) ) : ?><p style="color:#a7aaad;font-size:13px">Nenhum pedido no período.</p>
                <?php else : ?><table class="widefat striped" style="font-size:13px"><tbody>
                    <?php foreach ( $lbl_p as $sk => $sv ) : if ( empty( $ps[ $sk ] ) ) continue; ?>
                    <tr><td><?php echo esc_html( $sv ); ?></td><td style="text-align:right;font-weight:600"><?php echo (int) $ps[ $sk ]; ?></td></tr>
                    <?php endforeach; ?>
                </tbody></table><?php endif; ?>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:18px;margin-top:18px">
            <div>
                <h2 style="font-size:15px;margin:0 0 8px">Leads recentes</h2>
                <?php if ( empty( $rec_leads ) ) : ?><p style="color:#a7aaad;font-size:13px">&mdash;</p>
                <?php else : ?><table class="widefat striped" style="font-size:13px">
                    <thead><tr><th>Nome</th><th>Telefone</th><th>Status</th><th>Quando</th></tr></thead><tbody>
                    <?php foreach ( $rec_leads as $l ) : ?>
                    <tr><td><?php echo esc_html( $l['nome'] ?: '—' ); ?></td><td><?php echo esc_html( $l['telefone'] ?: '—' ); ?></td>
                    <td><?php echo esc_html( $lbl_l[ $l['status'] ?? '' ] ?? ( $l['status'] ?: '—' ) ); ?></td>
                    <td style="white-space:nowrap"><?php echo esc_html( $dt( $l['criado_em'] ?? '' ) ); ?></td></tr>
                    <?php endforeach; ?></tbody></table><?php endif; ?>
            </div>
            <div>
                <h2 style="font-size:15px;margin:0 0 8px">Pedidos recentes</h2>
                <?php if ( empty( $rec_ped ) ) : ?><p style="color:#a7aaad;font-size:13px">&mdash;</p>
                <?php else : ?><table class="widefat striped" style="font-size:13px">
                    <thead><tr><th>Valor</th><th>Status</th><th>Quando</th></tr></thead><tbody>
                    <?php foreach ( $rec_ped as $p ) : ?>
                    <tr><td style="font-weight:600"><?php echo $brl( $p['total'] ?? 0 ); ?></td>
                    <td><?php echo esc_html( $lbl_p[ $p['status'] ?? '' ] ?? ( $p['status'] ?: '—' ) ); ?></td>
                    <td style="white-space:nowrap"><?php echo esc_html( $dt( $p['criado_em'] ?? '' ) ); ?></td></tr>
                    <?php endforeach; ?></tbody></table><?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
    (function(){
        var sel = document.getElementById('neo-cliente');
        if (sel) sel.addEventListener('change', function(){
            var u = new URL('<?php echo $base_url; ?>', window.location.origin);
            u.searchParams.set('periodo', '<?php echo esc_js( $periodo ); ?>');
            if (this.value) u.searchParams.set('cliente', this.value);
            window.location.href = u.toString();
        });
        var el = document.getElementById('neoChart');
        if (el && window.Chart) new Chart(el, {
            type: 'line',
            data: {
                labels: <?php echo wp_json_encode( $chart_labels ); ?>,
                datasets: [
                    { label: 'Conversas', data: <?php echo wp_json_encode( $chart_conv ); ?>,  borderColor:'#2271b1', backgroundColor:'rgba(34,113,177,.1)', tension:.3, fill:true },
                    { label: 'Leads',     data: <?php echo wp_json_encode( $chart_leads ); ?>, borderColor:'#16a34a', backgroundColor:'rgba(22,163,74,.08)', tension:.3, fill:true },
                    { label: 'Pedidos',   data: <?php echo wp_json_encode( $chart_ped ); ?>,   borderColor:'#d97706', backgroundColor:'rgba(217,119,6,.08)', tension:.3, fill:true }
                ]
            },
            options: { responsive:true, maintainAspectRatio:true, plugins:{ legend:{ position:'bottom' } }, scales:{ y:{ beginAtZero:true, ticks:{ precision:0 } } } }
        });
    })();
    </script>
    <?php
}
