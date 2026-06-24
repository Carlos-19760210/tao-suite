<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function cbpm_page_dashboard() {
    if ( ! cbpm_can_access() ) return;

    $is_admin      = current_user_can( 'manage_options' );
    $my_cliente_id = cbpm_current_cliente_id();

    // ── Filtro negócio — usa crm_workspaces (autenticação garantida via tao_crm_api) ──
    $todos_ws_dash  = [];
    $filter_crm_ws  = sanitize_text_field( $_GET['crm_ws'] ?? '' );
    $filter_id      = sanitize_text_field( $_GET['filter_cliente_id'] ?? '' );
    $filter_nome    = '';
    $cbpm_locked_dash = false;

    if ( $is_admin ) {
        // Carrega todos os workspaces CRM para o combo (usa cbpm_api — sempre disponível aqui)
        $rws = cbpm_api( '/crm_workspaces?ativo=eq.true&select=id,nome,cliente_id&order=nome.asc' );
        if ( $rws['ok'] ) $todos_ws_dash = $rws['data'] ?? [];
        // Se escolheu um workspace, deriva o cliente_id correspondente
        if ( $filter_crm_ws ) {
            foreach ( $todos_ws_dash as $_dws ) {
                if ( $_dws['id'] === $filter_crm_ws ) {
                    $filter_id   = $_dws['cliente_id'] ?? '';
                    $filter_nome = $_dws['nome'] ?? '';
                    break;
                }
            }
        }
        if ( ! cbpm_is_master() ) { $own = cbpm_current_cliente_id(); if ( $own ) $filter_id = $own; $cbpm_locked_dash = true; }
    } else {
        $filter_id = $my_cliente_id ?? '';
        if ( $filter_id ) {
            $rc = cbpm_api( '/clientes?id=eq.' . urlencode( $filter_id ) . '&select=nome_negocio' );
            if ( $rc['ok'] && ! empty( $rc['data'] ) ) $filter_nome = $rc['data'][0]['nome_negocio'] ?? '';
        }
    }

    // ── Filtro período ──────────────────────────────────────────────────────
    $periodo = sanitize_key( $_GET['periodo'] ?? '30d' );
    $periodos = [
        'hoje'    => 'Hoje',
        '7d'      => 'Últimos 7 dias',
        '30d'     => 'Últimos 30 dias',
        '90d'     => 'Últimos 90 dias',
        'mes'     => 'Este mês',
        'mes_ant' => 'Mês anterior',
    ];
    if ( ! isset( $periodos[ $periodo ] ) ) $periodo = '30d';

    $tz_offset = '-03:00'; // BRT
    $now       = new DateTime( 'now', new DateTimeZone( 'America/Sao_Paulo' ) );

    switch ( $periodo ) {
        case 'hoje':
            $date_from = $now->format( 'Y-m-d' ) . 'T00:00:00' . $tz_offset;
            $date_to   = $now->format( 'Y-m-d' ) . 'T23:59:59' . $tz_offset;
            $chart_fmt  = 'H:i';
            $chart_days = 0;
            break;
        case '7d':
            $date_from = ( clone $now )->modify( '-6 days' )->format( 'Y-m-d' ) . 'T00:00:00' . $tz_offset;
            $date_to   = null;
            $chart_days = 7;
            break;
        case '90d':
            $date_from = ( clone $now )->modify( '-89 days' )->format( 'Y-m-d' ) . 'T00:00:00' . $tz_offset;
            $date_to   = null;
            $chart_days = 90;
            break;
        case 'mes':
            $date_from = $now->format( 'Y-m' ) . '-01T00:00:00' . $tz_offset;
            $date_to   = null;
            $chart_days = (int) $now->format( 'j' );
            break;
        case 'mes_ant':
            $first = ( clone $now )->modify( 'first day of last month' );
            $last  = ( clone $now )->modify( 'last day of last month' );
            $date_from = $first->format( 'Y-m-d' ) . 'T00:00:00' . $tz_offset;
            $date_to   = $last->format( 'Y-m-d' ) . 'T23:59:59' . $tz_offset;
            $chart_days = (int) $last->format( 't' );
            break;
        default: // 30d
            $date_from = ( clone $now )->modify( '-29 days' )->format( 'Y-m-d' ) . 'T00:00:00' . $tz_offset;
            $date_to   = null;
            $chart_days = 30;
    }

    // ── Filtro categoria ────────────────────────────────────────────────────
    $filter_cat    = sanitize_text_field( $_GET['filter_cat'] ?? '' );
    $categorias_list = [];
    if ( $filter_id ) {
        $rc = cbpm_api( '/categorias?cliente_id=eq.' . urlencode( $filter_id ) . '&select=nome&order=nome.asc' );
        if ( $rc['ok'] ) $categorias_list = array_column( $rc['data'], 'nome' );
    } elseif ( $is_admin && ! $filter_id ) {
        $rc = cbpm_api( '/categorias?select=nome&order=nome.asc' );
        if ( $rc['ok'] ) {
            $all_cats = array_unique( array_column( $rc['data'], 'nome' ) );
            sort( $all_cats );
            $categorias_list = $all_cats;
        }
    }

    // ── Monta query string base ─────────────────────────────────────────────
    $cf  = $filter_id ? '&cliente_id=eq.' . urlencode( $filter_id ) : '';
    $qdt = '&criado_em=gte.' . urlencode( $date_from );
    if ( $date_to ) $qdt .= '&criado_em=lte.' . urlencode( $date_to );

    // ── Negócios ativos (admin sem filtro) ──────────────────────────────────
    $n_clientes = 0;
    if ( $is_admin && ! $filter_id ) {
        $r = cbpm_api( '/clientes?ativo=eq.true&select=id' );
        if ( $r['ok'] ) $n_clientes = count( $r['data'] );
    }

    // ── Conversas ───────────────────────────────────────────────────────────
    $r_hist = cbpm_api( '/historico?select=phone,criado_em' . $cf . $qdt . '&limit=5000' );
    $hist   = $r_hist['ok'] ? $r_hist['data'] : [];

    // ── Leads ───────────────────────────────────────────────────────────────
    $leads_qs = '/leads?select=id,criado_em,status' . $cf . $qdt;
    $r_leads  = cbpm_api( $leads_qs );
    $leads_all = $r_leads['ok'] ? $r_leads['data'] : [];

    // ── Pedidos ─────────────────────────────────────────────────────────────
    $r_ped   = cbpm_api( '/pedidos?select=id,criado_em,total,status' . $cf . $qdt );
    $ped_all = $r_ped['ok'] ? $r_ped['data'] : [];

    // ── Aggregate por dia ───────────────────────────────────────────────────
    if ( $periodo === 'hoje' ) {
        // Bucket por hora
        $buckets = [];
        for ( $h = 0; $h <= 23; $h++ ) {
            $buckets[ sprintf( '%02d:00', $h ) ] = [ 'phones' => [], 'leads' => 0, 'ped' => 0 ];
        }
        foreach ( $hist as $m ) {
            $h = substr( $m['criado_em'] ?? '', 11, 2 ) . ':00';
            if ( isset( $buckets[$h] ) ) $buckets[$h]['phones'][ $m['phone'] ] = 1;
        }
        foreach ( $leads_all as $l ) {
            $h = substr( $l['criado_em'] ?? '', 11, 2 ) . ':00';
            if ( isset( $buckets[$h] ) ) $buckets[$h]['leads']++;
        }
        foreach ( $ped_all as $p ) {
            $h = substr( $p['criado_em'] ?? '', 11, 2 ) . ':00';
            if ( isset( $buckets[$h] ) ) $buckets[$h]['ped']++;
        }
    } else {
        // Bucket por dia
        $buckets = [];
        $start = new DateTime( substr( $date_from, 0, 10 ), new DateTimeZone( 'America/Sao_Paulo' ) );
        $end   = $date_to
            ? new DateTime( substr( $date_to, 0, 10 ), new DateTimeZone( 'America/Sao_Paulo' ) )
            : clone $now;
        for ( $d = clone $start; $d <= $end; $d->modify( '+1 day' ) ) {
            $buckets[ $d->format( 'Y-m-d' ) ] = [ 'phones' => [], 'leads' => 0, 'ped' => 0 ];
        }
        foreach ( $hist as $m ) {
            $d = substr( $m['criado_em'] ?? '', 0, 10 );
            if ( isset( $buckets[$d] ) ) $buckets[$d]['phones'][ $m['phone'] ] = 1;
        }
        foreach ( $leads_all as $l ) {
            $d = substr( $l['criado_em'] ?? '', 0, 10 );
            if ( isset( $buckets[$d] ) ) $buckets[$d]['leads']++;
        }
        foreach ( $ped_all as $p ) {
            $d = substr( $p['criado_em'] ?? '', 0, 10 );
            if ( isset( $buckets[$d] ) ) $buckets[$d]['ped']++;
        }
    }

    // ── KPIs ────────────────────────────────────────────────────────────────
    $total_conv  = array_sum( array_map( fn($b) => count( $b['phones'] ), $buckets ) );
    $total_leads = count( $leads_all );
    $total_ped   = count( $ped_all );
    $faturamento = array_sum( array_column( $ped_all, 'total' ) );

    // ── Chart ───────────────────────────────────────────────────────────────
    if ( $periodo === 'hoje' ) {
        $chart_labels = array_keys( $buckets );
    } else {
        $chart_labels = array_map(
            fn($dt) => ( strlen($dt) === 10 )
                ? date( 'd/m', strtotime( $dt ) )
                : $dt,
            array_keys( $buckets )
        );
    }
    $chart_conv  = array_map( fn($b) => count( $b['phones'] ), array_values( $buckets ) );
    $chart_leads = array_column( array_values( $buckets ), 'leads' );
    $chart_ped   = array_column( array_values( $buckets ), 'ped' );

    // ── Status leads ────────────────────────────────────────────────────────
    $leads_status = [];
    foreach ( $leads_all as $l ) {
        $s = $l['status'] ?? 'novo';
        $leads_status[$s] = ( $leads_status[$s] ?? 0 ) + 1;
    }

    // ── Recentes ────────────────────────────────────────────────────────────
    $cf2      = $filter_id ? 'cliente_id=eq.' . urlencode( $filter_id ) . '&' : '';
    $r_rl     = cbpm_api( '/leads?' . $cf2 . 'select=nome,telefone,status,criado_em,clientes(nome_negocio)&order=criado_em.desc&limit=5' );
    $recent_leads = $r_rl['ok'] ? $r_rl['data'] : [];

    $r_rp     = cbpm_api( '/pedidos?' . $cf2 . 'select=total,status,criado_em,clientes(nome_negocio)&order=criado_em.desc&limit=5' );
    $recent_ped = $r_rp['ok'] ? $r_rp['data'] : [];

    $status_labels_leads = [ 'novo' => 'Novo', 'contatado' => 'Contatado', 'negociando' => 'Negociando', 'fechado' => 'Fechado', 'perdido' => 'Perdido' ];
    $status_labels_ped   = [ 'novo' => 'Novo', 'confirmado' => 'Confirmado', 'entregue' => 'Entregue', 'cancelado' => 'Cancelado' ];

    // ── URL base do dashboard (frontend ou wp-admin) ────────────────────────
    global $cbpm_is_frontend;
    $is_frontend = ! empty( $cbpm_is_frontend );
    $dash_url    = $is_frontend ? cbpm_url( 'dashboard' ) : admin_url( 'admin.php?page=chatbot-platform' );

    // Parâmetros fixos para o form (negócio e categoria persistem ao mudar período)
    $form_hidden = '';
    if ( ! $is_frontend ) $form_hidden .= '<input type="hidden" name="page" value="chatbot-platform">';
    if ( $filter_id )     $form_hidden .= '<input type="hidden" name="filter_cliente_id" value="' . esc_attr( $filter_id ) . '">';
    if ( $filter_cat )    $form_hidden .= '<input type="hidden" name="filter_cat" value="' . esc_attr( $filter_cat ) . '">';

    // ── CRM: busca dados se produto ativo e cliente selecionado ─────────────────
    $crm_ws_ids = []; $crm_cards_open = []; $crm_cards_closed = [];
    $crm_msgs = []; $crm_estagios_map = [];

    $crm_workspaces_list = [];
    $crm_filter_ws = sanitize_text_field( $_GET['crm_ws'] ?? '' );
    if ( cbpm_tem_produto('crm') && ( $filter_id || cbpm_is_master() ) ) {
        if ( $filter_id ) {
            $rws = cbpm_api( "/crm_workspaces?cliente_id=eq.$filter_id&ativo=eq.true&select=id,nome&order=nome.asc" );
        } else {
            $rws = cbpm_api( "/crm_workspaces?ativo=eq.true&select=id,nome&order=nome.asc" );
        }
        $crm_workspaces_list = $rws['ok'] ? ( $rws['data'] ?? [] ) : [];
        // Filtro por workspace específico (param crm_ws)
        if ( $crm_filter_ws && cbpm_is_master() ) {
            $crm_workspaces_list = array_values( array_filter( $crm_workspaces_list, fn($w) => $w['id'] === $crm_filter_ws ) );
        }
        $crm_ws_ids = array_column( $crm_workspaces_list, 'id' );

        if ( $crm_ws_ids ) {
            $wsf = count($crm_ws_ids) === 1
                ? 'workspace_id=eq.' . $crm_ws_ids[0]
                : 'workspace_id=in.(' . implode(',', $crm_ws_ids) . ')';

            // Cards abertos (sem filtro de período — posição atual)
            $ro = cbpm_api( "/crm_cards?$wsf&fechado=eq.false&select=id,estagio_id&limit=500" );
            $crm_cards_open = $ro['ok'] ? ( $ro['data'] ?? [] ) : [];

            // Cards fechados no período
            $qdt_crm = 'movido_em=gte.' . urlencode( $date_from );
            if ( $date_to ) $qdt_crm .= '&movido_em=lte.' . urlencode( $date_to );
            $rc = cbpm_api( "/crm_cards?$wsf&fechado=eq.true&$qdt_crm&select=id,criado_em,movido_em,estagio_id&limit=500" );
            $crm_cards_closed = $rc['ok'] ? ( $rc['data'] ?? [] ) : [];

            // Mensagens no período
            $qdt_msg = 'enviado_em=gte.' . urlencode( $date_from );
            if ( $date_to ) $qdt_msg .= '&enviado_em=lte.' . urlencode( $date_to );
            $rm = cbpm_api( "/crm_mensagens?$wsf&$qdt_msg&select=id,direcao&limit=2000" );
            $crm_msgs = $rm['ok'] ? ( $rm['data'] ?? [] ) : [];

            // Estágios (para gráfico donut)
            $rpl = cbpm_api( "/crm_pipelines?$wsf&ativo=eq.true&select=id&order=ordem.asc&limit=5" );
            $pl_ids = $rpl['ok'] ? array_column( $rpl['data'], 'id' ) : [];
            if ( $pl_ids ) {
                $plf = count($pl_ids) === 1
                    ? 'pipeline_id=eq.' . $pl_ids[0]
                    : 'pipeline_id=in.(' . implode(',', $pl_ids) . ')';
                $re = cbpm_api( "/crm_estagios?$plf&select=id,nome,cor,tipo&order=ordem.asc" );
                if ( $re['ok'] ) {
                    foreach ( $re['data'] ?? [] as $e ) {
                        $crm_estagios_map[ $e['id'] ] = $e;
                    }
                }
            }
        }
    }

    // KPIs CRM
    $crm_n_open   = count( $crm_cards_open );
    $crm_n_closed = count( $crm_cards_closed );
    $crm_n_msgs_in  = count( array_filter( $crm_msgs, fn($m) => ($m['direcao'] ?? '') === 'in' ) );
    $crm_n_msgs_out = count( array_filter( $crm_msgs, fn($m) => ($m['direcao'] ?? '') === 'out' ) );

    // TMA — média de (movido_em - criado_em) para cards fechados no período
    $tma_fmt = '&mdash;';
    if ( $crm_cards_closed ) {
        $durs = array_filter( array_map( function($c) {
            if ( empty($c['criado_em']) || empty($c['movido_em']) ) return null;
            $d = strtotime($c['movido_em']) - strtotime($c['criado_em']);
            return $d > 0 ? $d : null;
        }, $crm_cards_closed ) );
        if ( $durs ) {
            $avg = array_sum($durs) / count($durs);
            $tma_fmt = $avg >= 86400
                ? round($avg/86400,1) . ' dias'
                : ( $avg >= 3600 ? round($avg/3600,1) . ' h' : round($avg/60) . ' min' );
        }
    }

    // Cards por estágio (abertos) para gráfico
    $cards_estagio_labels = [];
    $cards_estagio_data   = [];
    $cards_estagio_colors = [];
    $estagio_counts = [];
    foreach ( $crm_cards_open as $card ) {
        $eid = $card['estagio_id'] ?? '';
        $estagio_counts[$eid] = ($estagio_counts[$eid] ?? 0) + 1;
    }
    foreach ( $crm_estagios_map as $eid => $e ) {
        if ( isset( $estagio_counts[$eid] ) ) {
            $cards_estagio_labels[] = $e['nome'];
            $cards_estagio_data[]   = $estagio_counts[$eid];
            $cor = $e['cor'] ?? '#B38E6C';
            $cards_estagio_colors[] = $cor;
        }
    }
    ?>
    <div class="wrap cbpm-wrap">
        <h1>Dashboard</h1>

        <?php if ( $is_admin && ! empty( $todos_ws_dash ) && ! $cbpm_locked_dash ) : ?>
        <!-- ── Seletor de negócio (proeminente, auto-filtra) ──────────────── -->
        <form method="get" action="<?php echo esc_url( $dash_url ); ?>" id="cbpm-negocio-form"
              style="background:#152C42;border-radius:6px;padding:14px 20px;margin-bottom:16px;display:flex;align-items:center;gap:12px;flex-wrap:wrap">
            <?php if ( ! $is_frontend ): ?><input type="hidden" name="page" value="chatbot-platform"><?php endif; ?>
            <?php if ( $periodo !== '30d' ): ?><input type="hidden" name="periodo" value="<?php echo esc_attr($periodo); ?>"><?php endif; ?>
            <?php if ( $filter_cat ): ?><input type="hidden" name="filter_cat" value="<?php echo esc_attr($filter_cat); ?>"><?php endif; ?>
            <span style="font-size:13px;font-weight:700;color:#fff;white-space:nowrap">&#x1F3E2; Negócio:</span>
            <select name="crm_ws" id="cbpm-negocio-select"
                    onchange="document.getElementById('cbpm-negocio-form').submit()"
                    style="font-size:14px;font-weight:600;padding:6px 12px;border-radius:4px;border:none;min-width:200px;cursor:pointer">
                <option value="">— Todos os negócios —</option>
                <?php foreach ( $todos_ws_dash as $_dw ): ?>
                    <option value="<?php echo esc_attr( $_dw['id'] ); ?>" <?php selected( $filter_crm_ws, $_dw['id'] ); ?>>
                        <?php echo esc_html( $_dw['nome'] ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if ( $filter_crm_ws ): ?>
            <a href="<?php echo esc_url( $is_frontend ? cbpm_url('dashboard') : admin_url('admin.php?page=chatbot-platform') ); ?>"
               style="font-size:12px;color:#93c5fd;text-decoration:underline">Limpar</a>
            <?php endif; ?>
        </form>
        <?php elseif ( $filter_nome ) : ?>
        <div style="background:#152C42;border-radius:6px;padding:12px 20px;margin-bottom:16px">
            <span style="font-size:13px;font-weight:700;color:#fff">&#x1F3E2; <?php echo esc_html($filter_nome); ?></span>
        </div>
        <?php endif; ?>

        <!-- ── Barra de filtros ─────────────────────────────────────────── -->
        <form method="get" action="<?php echo esc_url( $dash_url ); ?>"
              style="background:#fff;border:1px solid #dcdcde;border-radius:4px;padding:12px 16px;margin-bottom:20px;display:flex;align-items:center;gap:12px;flex-wrap:wrap">

            <?php if ( ! $is_frontend ): ?>
                <input type="hidden" name="page" value="chatbot-platform">
            <?php endif; ?>
            <?php if ( $filter_crm_ws ): ?>
                <input type="hidden" name="crm_ws" value="<?php echo esc_attr($filter_crm_ws); ?>">
            <?php endif; ?>

            <?php /* Período */ ?>
            <div style="display:flex;align-items:center;gap:6px">
                <label style="font-size:12px;color:#646970;white-space:nowrap">Período</label>
                <select name="periodo" style="font-size:12px">
                    <?php foreach ( $periodos as $k => $label ): ?>
                        <option value="<?php echo esc_attr( $k ); ?>" <?php selected( $periodo, $k ); ?>>
                            <?php echo esc_html( $label ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php /* Categoria */ ?>
            <?php if ( $categorias_list ): ?>
                <div style="width:1px;height:24px;background:#dcdcde"></div>
                <div style="display:flex;align-items:center;gap:6px">
                    <label style="font-size:12px;color:#646970;white-space:nowrap">Categoria</label>
                    <select name="filter_cat" style="font-size:12px">
                        <option value="">— Todas —</option>
                        <?php foreach ( $categorias_list as $cat ): ?>
                            <option value="<?php echo esc_attr( $cat ); ?>" <?php selected( $filter_cat, $cat ); ?>>
                                <?php echo esc_html( $cat ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <button type="submit" class="button button-primary" style="font-size:12px;padding:4px 14px;margin-left:4px">Filtrar</button>

            <?php if ( $filter_id || $filter_cat || $periodo !== '30d' ): ?>
                <a href="<?php echo esc_url( $is_frontend ? cbpm_url('dashboard') : admin_url('admin.php?page=chatbot-platform') ); ?>"
                   class="button button-secondary" style="font-size:12px;padding:4px 12px;text-decoration:none">Limpar</a>
            <?php endif; ?>

            <span style="margin-left:auto;font-size:11px;color:#646970">
                <?php echo esc_html( $periodos[ $periodo ] ); ?>
                <?php if ( $filter_cat ) echo ' · ' . esc_html( $filter_cat ); ?>
            </span>
        </form>

        <!-- ── Agente ───────────────────────────────────────────────────── -->
        <h2 style="font-family:'Playfair Display',serif;font-size:20px;color:#152C42;margin:0 0 14px;display:flex;align-items:center;gap:8px">
            &#x1F916; Agente
            <span style="font-family:'Inter',sans-serif;font-size:12px;font-weight:400;color:#6B7280;margin-left:4px"><?php echo esc_html($periodos[$periodo]); ?></span>
        </h2>

        <!-- ── KPIs ─────────────────────────────────────────────────────── -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(155px,1fr));gap:12px;margin-bottom:20px">
            <?php if ( $is_admin && ! $filter_id ): ?>
                <?php cbpm_kpi_card( $n_clientes, 'Negócios ativos', '#2271b1' ); ?>
            <?php endif; ?>
            <?php cbpm_kpi_card( $total_conv,  'Conversas',  '#0073aa' ); ?>
            <?php cbpm_kpi_card( $total_leads, 'Leads',      '#00a32a' ); ?>
            <?php cbpm_kpi_card( $total_ped,   'Pedidos',    '#996800' ); ?>
            <?php cbpm_kpi_card( 'R$&nbsp;' . number_format( $faturamento, 2, ',', '.' ), 'Faturamento', '#d63638', true ); ?>
        </div>

        <style>@media(max-width:768px){.cbpm-g2{grid-template-columns:1fr !important}}</style>
        <!-- ── Gráficos ──────────────────────────────────────────────────── -->
        <div class="cbpm-g2" style="display:grid;grid-template-columns:2fr 1fr;gap:12px;margin-bottom:12px">
            <div style="background:#fff;border:1px solid #dcdcde;border-radius:4px;padding:20px">
                <h3 style="margin:0 0 14px;font-size:13px;text-transform:uppercase;letter-spacing:.4px;color:#50575e">
                    Atividade — <?php echo esc_html( $periodos[ $periodo ] ); ?>
                </h3>
                <div style="position:relative;height:200px"><canvas id="cbpm-chart-activity"></canvas></div>
            </div>
            <div style="background:#fff;border:1px solid #dcdcde;border-radius:4px;padding:20px">
                <h3 style="margin:0 0 14px;font-size:13px;text-transform:uppercase;letter-spacing:.4px;color:#50575e">Leads por status</h3>
                <?php if ( $leads_status ): ?>
                    <div style="position:relative;height:200px"><canvas id="cbpm-chart-status"></canvas></div>
                <?php else: ?>
                    <p style="color:#646970;font-size:13px">Sem dados.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- ── Recentes ──────────────────────────────────────────────────── -->
        <div class="cbpm-g2" style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div style="background:#fff;border:1px solid #dcdcde;border-radius:4px;padding:20px">
                <h3 style="margin:0 0 10px;font-size:13px;text-transform:uppercase;letter-spacing:.4px;color:#50575e">Últimos leads</h3>
                <?php if ( $recent_leads ): ?>
                <table class="wp-list-table widefat fixed striped" style="font-size:12px">
                    <thead><tr>
                        <?php if ( $is_admin ): ?><th>Negócio</th><?php endif; ?>
                        <th>Nome</th><th>Telefone</th><th>Status</th><th>Data</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ( $recent_leads as $l ): ?>
                    <tr>
                        <?php if ( $is_admin ): ?><td><?php echo esc_html( $l['clientes']['nome_negocio'] ?? '-' ); ?></td><?php endif; ?>
                        <td><?php echo esc_html( $l['nome'] ?? '-' ); ?></td>
                        <td><?php echo esc_html( $l['telefone'] ?? '-' ); ?></td>
                        <td><?php echo esc_html( $status_labels_leads[ $l['status'] ?? '' ] ?? $l['status'] ?? '-' ); ?></td>
                        <td><?php echo esc_html( substr( $l['criado_em'] ?? '', 0, 10 ) ); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?><p style="color:#646970;font-size:13px">Nenhum lead.</p><?php endif; ?>
            </div>
            <div style="background:#fff;border:1px solid #dcdcde;border-radius:4px;padding:20px">
                <h3 style="margin:0 0 10px;font-size:13px;text-transform:uppercase;letter-spacing:.4px;color:#50575e">Últimos pedidos</h3>
                <?php if ( $recent_ped ): ?>
                <table class="wp-list-table widefat fixed striped" style="font-size:12px">
                    <thead><tr>
                        <?php if ( $is_admin ): ?><th>Negócio</th><?php endif; ?>
                        <th>Total</th><th>Status</th><th>Data</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ( $recent_ped as $p ): ?>
                    <tr>
                        <?php if ( $is_admin ): ?><td><?php echo esc_html( $p['clientes']['nome_negocio'] ?? '-' ); ?></td><?php endif; ?>
                        <td>R$ <?php echo number_format( floatval( $p['total'] ?? 0 ), 2, ',', '.' ); ?></td>
                        <td><?php echo esc_html( $status_labels_ped[ $p['status'] ?? '' ] ?? $p['status'] ?? '-' ); ?></td>
                        <td><?php echo esc_html( substr( $p['criado_em'] ?? '', 0, 10 ) ); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?><p style="color:#646970;font-size:13px">Nenhum pedido.</p><?php endif; ?>
            </div>
        </div>

        <?php if ( cbpm_tem_produto('crm') && $crm_ws_ids ): ?>
        <hr style="margin:28px 0 20px">
        <div style="margin-bottom:16px">
            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:12px">
                <h2 style="font-family:'Playfair Display',serif;font-size:20px;color:#152C42;margin:0;display:flex;align-items:center;gap:8px">
                    &#x1F5C2; TAO CRM
                    <span style="font-family:'Inter',sans-serif;font-size:12px;font-weight:400;color:#6B7280"><?php echo esc_html($periodos[$periodo]); ?></span>
                </h2>
                <?php if ( function_exists('cbpm_url') ): ?>
                <a href="<?php echo esc_url( $crm_filter_ws ? cbpm_url( 'crm-dashboard', [ 'workspace_id' => $crm_filter_ws ] ) : cbpm_url('crm-dashboard') ); ?>"
                   style="font-size:12px;color:#B38E6C;text-decoration:none;white-space:nowrap;font-weight:600">Ver dashboard completo →</a>
                <?php endif; ?>
            </div>
            <?php
            // Buscar lista completa de workspaces para os botões de filtro (sem o filtro aplicado)
            $_all_ws_for_btns = [];
            if ( cbpm_is_master() && function_exists('cbpm_url') ) {
                $_rws_all = cbpm_api( "/crm_workspaces?ativo=eq.true&select=id,nome&order=nome.asc" );
                $_all_ws_for_btns = $_rws_all['ok'] ? ( $_rws_all['data'] ?? [] ) : [];
            }
            if ( cbpm_is_master() && count( $_all_ws_for_btns ) > 0 && function_exists('cbpm_url') ):
                // URL base mantendo filtros existentes
                $_base_params = array_filter( [ 'filter_cliente_id' => $filter_id, 'periodo' => ( $periodo !== '30d' ? $periodo : '' ), 'filter_cat' => ( $filter_cat ?? '' ) ] );
                $_todos_url   = add_query_arg( $_base_params, cbpm_url('dashboard') );
            ?>
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:10px 14px">
                <span style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.5px;white-space:nowrap">Negócio:</span>
                <a href="<?php echo esc_url( $_todos_url ); ?>"
                   style="display:inline-block;padding:5px 12px;border-radius:5px;font-size:12px;font-weight:600;text-decoration:none;white-space:nowrap;
                          <?php echo ! $crm_filter_ws ? 'background:#152C42;color:#fff;border:1px solid #152C42' : 'background:#fff;color:#475569;border:1px solid #cbd5e1'; ?>">
                    Todos
                </a>
                <?php foreach ( $_all_ws_for_btns as $_bws ):
                    $_bws_url = add_query_arg( array_merge( $_base_params, [ 'crm_ws' => $_bws['id'] ] ), cbpm_url('dashboard') );
                    $_is_active = ( $_bws['id'] === $crm_filter_ws );
                ?>
                <a href="<?php echo esc_url( $_bws_url ); ?>"
                   style="display:inline-block;padding:5px 12px;border-radius:5px;font-size:12px;font-weight:600;text-decoration:none;white-space:nowrap;
                          <?php echo $_is_active ? 'background:#152C42;color:#fff;border:1px solid #152C42' : 'background:#fff;color:#475569;border:1px solid #cbd5e1'; ?>">
                    <?php echo esc_html( $_bws['nome'] ); ?>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- KPI cards CRM -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(155px,1fr));gap:12px;margin-bottom:20px">
            <?php cbpm_kpi_card( $crm_n_open,   'Cards em aberto',    '#152C42' ); ?>
            <?php cbpm_kpi_card( $crm_n_closed, 'Atendimentos encerrados', '#B38E6C' ); ?>
            <?php cbpm_kpi_card( $crm_n_msgs_in,  'Msgs recebidas',  '#0073aa' ); ?>
            <?php cbpm_kpi_card( $crm_n_msgs_out, 'Msgs enviadas',   '#00a32a' ); ?>
            <?php cbpm_kpi_card( $tma_fmt, 'TMA (tempo médio)', '#996800', true ); ?>
        </div>

        <!-- Gráficos CRM -->
        <div class="cbpm-g2" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:20px">

            <!-- Mensagens por dia -->
            <div style="background:#fff;border:1px solid #dcdcde;border-radius:4px;padding:20px">
                <h3 style="margin:0 0 14px;font-size:13px;text-transform:uppercase;letter-spacing:.4px;color:#50575e">
                    Mensagens — <?php echo esc_html($periodos[$periodo]); ?>
                </h3>
                <div style="position:relative;height:200px"><canvas id="crm-chart-msgs"></canvas></div>
            </div>

            <!-- Cards por estágio (donut) -->
            <div style="background:#fff;border:1px solid #dcdcde;border-radius:4px;padding:20px">
                <h3 style="margin:0 0 14px;font-size:13px;text-transform:uppercase;letter-spacing:.4px;color:#50575e">
                    Cards abertos por est&aacute;gio
                </h3>
                <?php if ( $cards_estagio_data ): ?>
                    <div style="position:relative;height:220px"><canvas id="crm-chart-estagios"></canvas></div>
                <?php else: ?>
                    <p style="color:#646970;font-size:13px;margin:20px 0">Sem cards em aberto.</p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; // tem_crm ?>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
    (function(){
        if ( window.Chart ) Chart.defaults.maintainAspectRatio = false;
        var labels = <?php echo wp_json_encode( $chart_labels ); ?>;
        new Chart(document.getElementById('cbpm-chart-activity'), {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    { label: 'Conversas', data: <?php echo wp_json_encode( $chart_conv ); ?>,
                      borderColor: '#0073aa', backgroundColor: 'rgba(0,115,170,0.08)', tension: 0.3, fill: true, pointRadius: 2 },
                    { label: 'Leads',     data: <?php echo wp_json_encode( $chart_leads ); ?>,
                      borderColor: '#00a32a', backgroundColor: 'rgba(0,163,42,0.08)',  tension: 0.3, fill: true, pointRadius: 2 },
                    { label: 'Pedidos',   data: <?php echo wp_json_encode( $chart_ped ); ?>,
                      borderColor: '#d63638', backgroundColor: 'rgba(214,54,56,0.08)', tension: 0.3, fill: true, pointRadius: 2 },
                ]
            },
            options: {
                plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } },
                scales: { y: { beginAtZero: true, ticks: { precision: 0, stepSize: 1 } } }
            }
        });
        <?php if ( $leads_status ): ?>
        new Chart(document.getElementById('cbpm-chart-status'), {
            type: 'doughnut',
            data: {
                labels: <?php echo wp_json_encode( array_map( fn($k) => $status_labels_leads[$k] ?? $k, array_keys( $leads_status ) ) ); ?>,
                datasets: [{ data: <?php echo wp_json_encode( array_values( $leads_status ) ); ?>,
                    backgroundColor: ['#2271b1','#00a32a','#f0b429','#3c434a','#cc1818'] }]
            },
            options: { plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } }, cutout: '60%' }
        });
        <?php endif; ?>
        <?php if ( cbpm_tem_produto('crm') && $crm_ws_ids ): ?>
        // Mensagens CRM por dia
        <?php
        // Monta buckets de mensagens por dia
        $msg_buckets_in  = array_fill_keys( array_keys($buckets), 0 );
        $msg_buckets_out = array_fill_keys( array_keys($buckets), 0 );
        foreach ( $crm_msgs as $m ) {
            $key = $periodo === 'hoje'
                ? ( substr($m['enviado_em'] ?? '', 11, 2) . ':00' )
                : substr($m['enviado_em'] ?? '', 0, 10);
            if ( isset($msg_buckets_in[$key]) ) {
                if ( ($m['direcao'] ?? '') === 'in'  ) $msg_buckets_in[$key]++;
                if ( ($m['direcao'] ?? '') === 'out' ) $msg_buckets_out[$key]++;
            }
        }
        ?>
        new Chart(document.getElementById('crm-chart-msgs'), {
            type: 'bar',
            data: {
                labels: <?php echo wp_json_encode($chart_labels); ?>,
                datasets: [
                    { label: 'Recebidas', data: <?php echo wp_json_encode(array_values($msg_buckets_in)); ?>,
                      backgroundColor: 'rgba(0,115,170,0.7)', borderRadius: 3 },
                    { label: 'Enviadas',  data: <?php echo wp_json_encode(array_values($msg_buckets_out)); ?>,
                      backgroundColor: 'rgba(179,142,108,0.7)', borderRadius: 3 }
                ]
            },
            options: {
                plugins: { legend: { position:'bottom', labels:{ boxWidth:12, font:{size:11} } } },
                scales: { x:{ stacked:false }, y:{ beginAtZero:true, ticks:{ precision:0, stepSize:1 } } }
            }
        });
        <?php if ( $cards_estagio_data ): ?>
        new Chart(document.getElementById('crm-chart-estagios'), {
            type: 'doughnut',
            data: {
                labels: <?php echo wp_json_encode($cards_estagio_labels); ?>,
                datasets: [{ data: <?php echo wp_json_encode($cards_estagio_data); ?>,
                    backgroundColor: <?php echo wp_json_encode($cards_estagio_colors); ?> }]
            },
            options: { plugins:{ legend:{ position:'bottom', labels:{ boxWidth:12, font:{size:11} } } }, cutout:'55%' }
        });
        <?php endif; ?>
        <?php endif; // tem_crm && crm_ws_ids ?>
    })();
    </script>
    <?php
}

function cbpm_kpi_card( $value, $label, $color, $raw = false ) {
    ?>
    <div style="background:#fff;border:1px solid #dcdcde;border-left:4px solid <?php echo esc_attr( $color ); ?>;border-radius:4px;padding:16px 20px">
        <div style="font-size:26px;font-weight:700;color:<?php echo esc_attr( $color ); ?>"><?php echo $raw ? $value : esc_html( $value ); ?></div>
        <div style="color:#646970;font-size:12px;margin-top:4px"><?php echo esc_html( $label ); ?></div>
    </div>
    <?php
}
