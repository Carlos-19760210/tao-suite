<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function tao_crm_page_dashboard() {
    if ( ! function_exists( 'cbpm_can_access' ) || ! cbpm_can_access() ) {
        echo '<div class="wrap"><p>Acesso negado.</p></div>'; return;
    }

    // Controle de acesso: usuário comum só vê o próprio negócio
    $_dash_is_master = current_user_can( 'manage_options' )
        || ( function_exists( 'cbpm_current_role' ) && cbpm_current_role() === 'master' );
    if ( ! $_dash_is_master ) {
        $ws = tao_crm_get_workspace();
    } else {
        $ws_id = sanitize_text_field( $_GET['workspace_id'] ?? '' );
        $ws    = tao_crm_get_workspace( $ws_id ?: null );
    }
    if ( ! $ws ) {
        echo '<div class="wrap"><div class="notice notice-warning"><p>Nenhum workspace configurado.</p></div></div>'; return;
    }
    $ws_id = $ws['id'];

    // ── Período ────────────────────────────────────────────────────────────────
    $dias  = max( 1, min( 180, intval( $_GET['dias'] ?? 30 ) ) );
    $desde = gmdate( 'c', strtotime( "-{$dias} days" ) );

    // ── Buscar cards ──────────────────────────────────────────────────────────
    $rc_all = tao_crm_api(
        "/crm_cards?workspace_id=eq.$ws_id" .
        "&select=id,fechado,estagio_id,responsavel_id,criado_em,movido_em,atendimento_humano,valor_oportunidade" .
        "&limit=2000"
    );
    $all = $rc_all['ok'] ? ( $rc_all['data'] ?? [] ) : [];

    // ── Estágios ──────────────────────────────────────────────────────────────
    // Pegar todos os pipeline_ids distintos dos cards
    $pipe_ids_raw = [];
    foreach ( $all as $c ) {
        if ( ! empty( $c['pipeline_id'] ) ) $pipe_ids_raw[] = $c['pipeline_id'];
    }
    // Buscar estágios via workspace (independente de pipeline_id nos cards)
    $re_pipes = tao_crm_api( "/crm_pipelines?workspace_id=eq.$ws_id&select=id" );
    $all_pipe_ids = [];
    foreach ( ( $re_pipes['ok'] ? ( $re_pipes['data'] ?? [] ) : [] ) as $p ) {
        $all_pipe_ids[] = $p['id'];
    }

    $estagios = [];
    if ( ! empty( $all_pipe_ids ) ) {
        $pipe_in = implode( ',', $all_pipe_ids );
        $re_est  = tao_crm_api( "/crm_estagios?pipeline_id=in.($pipe_in)&order=ordem.asc&select=id,nome,tipo,ordem,pipeline_id" );
        foreach ( ( $re_est['ok'] ? ( $re_est['data'] ?? [] ) : [] ) as $e ) {
            $estagios[ $e['id'] ] = $e;
        }
    }

    // ── Usuários WP ───────────────────────────────────────────────────────────
    $wp_users = [];
    foreach ( get_users( [ 'fields' => [ 'ID', 'display_name' ] ] ) as $u ) {
        $wp_users[ $u->ID ] = $u->display_name;
    }

    // ── Derivar listas ────────────────────────────────────────────────────────
    $abertos  = array_values( array_filter( $all, fn( $c ) => empty( $c['fechado'] ) ) );
    $fechados = array_values( array_filter( $all, fn( $c ) => ! empty( $c['fechado'] ) ) );
    $ganhos   = array_values( array_filter( $all, fn( $c ) => ! empty( $c['fechado'] ) && ( $estagios[ $c['estagio_id'] ]['tipo'] ?? '' ) === 'ganho' ) );
    $perdidos = array_values( array_filter( $all, fn( $c ) => ! empty( $c['fechado'] ) && ( $estagios[ $c['estagio_id'] ]['tipo'] ?? '' ) === 'perdido' ) );
    $em_and   = array_values( array_filter( $all, fn( $c ) => ! empty( $c['fechado'] ) && ! in_array( $estagios[ $c['estagio_id'] ]['tipo'] ?? '', [ 'ganho', 'perdido' ] ) ) );
    $handoff  = array_values( array_filter( $abertos, fn( $c ) => ! empty( $c['atendimento_humano'] ) ) );

    // Novos no período
    $novos_periodo = array_values( array_filter( $all, fn( $c ) => ( $c['criado_em'] ?? '' ) >= $desde ) );

    // Taxa de conversão
    $total_fechados = count( $fechados );
    $taxa = $total_fechados > 0 ? round( count( $ganhos ) / $total_fechados * 100 ) : 0;

    // Total em oportunidades (cards abertos) e receita gerada (ganhos)
    $total_oportunidades = array_sum( array_column( $abertos, 'valor_oportunidade' ) );
    $receita_gerada      = array_sum( array_column( $ganhos,  'valor_oportunidade' ) );

    // ── Dados para Gráfico 5: Receita por semana (ganhos) ────────────────────
    $receita_labels = [];
    $receita_data   = [];
    for ( $s = 7; $s >= 0; $s-- ) {
        $ini     = strtotime( '-' . ( $s * 7 + 6 ) . ' days midnight' );
        $fim     = strtotime( '-' . ( $s * 7 ) . ' days 23:59:59' );
        $ini_str = gmdate( 'c', $ini );
        $fim_str = gmdate( 'c', $fim );
        $val     = 0;
        foreach ( $ganhos as $c ) {
            $dt = $c['movido_em'] ?? $c['criado_em'] ?? '';
            if ( $dt >= $ini_str && $dt <= $fim_str ) $val += floatval( $c['valor_oportunidade'] ?? 0 );
        }
        $receita_labels[] = gmdate( 'd/m', $ini );
        $receita_data[]   = round( $val, 2 );
    }

    // ── Métricas de campanhas ─────────────────────────────────────────────────
    $cliente_id_ws = $ws['cliente_id'] ?? '';
    $camp_stats = [ 'total' => 0, 'ativas' => 0, 'enviados' => 0, 'taxa_entrega' => 0 ];
    if ( $cliente_id_ws ) {
        $rc_camp = tao_crm_api( "/campanhas?cliente_id=eq.$cliente_id_ws&select=id,status,enviados,total_contatos,falhas&limit=200" );
        if ( $rc_camp['ok'] && ! empty( $rc_camp['data'] ) ) {
            $camp_stats['total'] = count( $rc_camp['data'] );
            $total_env = 0; $total_cont = 0; $total_falhas = 0;
            foreach ( $rc_camp['data'] as $camp ) {
                if ( in_array( $camp['status'], [ 'ativo', 'pausado' ] ) ) $camp_stats['ativas']++;
                $total_env    += intval( $camp['enviados']       ?? 0 );
                $total_cont   += intval( $camp['total_contatos'] ?? 0 );
                $total_falhas += intval( $camp['falhas']         ?? 0 );
            }
            $camp_stats['enviados'] = $total_env;
            $camp_stats['taxa_entrega'] = $total_cont > 0 ? round( ( $total_env - $total_falhas ) / $total_cont * 100 ) : 0;
        }
    }

    // ── Dados para Gráfico 1: Funil por estágio (barras horizontais) ──────────
    $por_estagio_chart = [];
    foreach ( $abertos as $c ) {
        $est = $estagios[ $c['estagio_id'] ] ?? null;
        if ( ! $est ) continue;
        $tipo_est = $est['tipo'] ?? 'normal';
        if ( in_array( $tipo_est, [ 'ganho', 'perdido' ] ) ) continue;
        $nome = $est['nome'];
        $ordem = intval( $est['ordem'] );
        if ( ! isset( $por_estagio_chart[ $nome ] ) ) {
            $por_estagio_chart[ $nome ] = [ 'count' => 0, 'ordem' => $ordem ];
        }
        $por_estagio_chart[ $nome ]['count']++;
    }
    uasort( $por_estagio_chart, fn( $a, $b ) => $a['ordem'] <=> $b['ordem'] );
    $chart_estagio_labels = array_keys( $por_estagio_chart );
    $chart_estagio_data   = array_column( array_values( $por_estagio_chart ), 'count' );

    // ── Dados para Gráfico 2: Donut conversão ────────────────────────────────
    $chart_conv_data   = [ count( $ganhos ), count( $perdidos ), count( $em_and ), count( $abertos ) ];
    $chart_conv_labels = [ 'Ganhos', 'Perdidos', 'Fechados (outros)', 'Em andamento' ];
    $chart_conv_colors = [ '#10b981', '#ef4444', '#f59e0b', '#6366f1' ];

    // ── Dados para Gráfico 3: Novos leads por semana (8 semanas) ─────────────
    $semanas_labels = [];
    $semanas_data   = [];
    for ( $s = 7; $s >= 0; $s-- ) {
        $ini = strtotime( "-" . ( $s * 7 + 6 ) . " days midnight" );
        $fim = strtotime( "-" . ( $s * 7 ) . " days 23:59:59" );
        $ini_str = gmdate( 'c', $ini );
        $fim_str = gmdate( 'c', $fim );
        $label = gmdate( 'd/m', $ini );
        $count = 0;
        foreach ( $all as $c ) {
            $cr = $c['criado_em'] ?? '';
            if ( $cr >= $ini_str && $cr <= $fim_str ) $count++;
        }
        $semanas_labels[] = $label;
        $semanas_data[]   = $count;
    }

    // ── Dados para Gráfico 4: Top atendentes ─────────────────────────────────
    $por_atendente_chart = [];
    foreach ( $abertos as $c ) {
        $resp_id = intval( $c['responsavel_id'] ?? 0 );
        $nome    = $resp_id ? ( $wp_users[ $resp_id ] ?? "ID $resp_id" ) : 'Sem responsável';
        $por_atendente_chart[ $nome ] = ( $por_atendente_chart[ $nome ] ?? 0 ) + 1;
    }
    arsort( $por_atendente_chart );
    $por_atendente_chart = array_slice( $por_atendente_chart, 0, 10, true );
    $chart_att_labels = array_keys( $por_atendente_chart );
    $chart_att_data   = array_values( $por_atendente_chart );

    // ── Lembretes pendentes hoje ──────────────────────────────────────────────
    $today_end = gmdate( 'c', strtotime( 'today 23:59:59' ) );
    $rl = tao_crm_api(
        "/crm_lembretes?workspace_id=eq.$ws_id&completado=eq.false" .
        "&data_hora=lte." . urlencode( $today_end ) .
        "&order=data_hora.asc&limit=20"
    );
    $lembretes = $rl['ok'] ? ( $rl['data'] ?? [] ) : [];

    // ── Montar objeto de dados para JS ────────────────────────────────────────
    // Pre-fetch all workspaces for master filter bar
    $_dash_wss         = $_dash_is_master ? tao_crm_get_workspaces() : [];
    global $cbpm_is_frontend;
    $_dash_is_frontend = ! empty( $cbpm_is_frontend ) && function_exists( 'cbpm_url' );

    $dados_js = [
        'estagio'   => [ 'labels' => $chart_estagio_labels, 'data' => $chart_estagio_data ],
        'conv'      => [ 'labels' => $chart_conv_labels,    'data' => $chart_conv_data,  'colors' => $chart_conv_colors ],
        'semanas'   => [ 'labels' => $semanas_labels,        'data' => $semanas_data ],
        'atendente' => [ 'labels' => $chart_att_labels,      'data' => $chart_att_data ],
        'receita'   => [ 'labels' => $receita_labels,        'data' => $receita_data ],
        'wsId'      => $ws_id,
        'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
        'nonce'     => wp_create_nonce( 'tao_crm_nonce' ),
    ];

    $kanban_url = tao_crm_url( [ 'workspace_id' => $ws_id ] );
    ?>
    <style>
    .crm-dash-kpi-row{display:flex;gap:14px;flex-wrap:wrap;margin-bottom:24px}
    .crm-dash-kpi-card{flex:1;min-width:160px;background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:16px 20px;box-shadow:0 1px 3px rgba(0,0,0,.06)}
    .crm-dash-kpi-card .kpi-label{display:block;font-size:12px;color:#64748b;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px}
    .crm-dash-kpi-card .kpi-value{display:block;font-size:28px;font-weight:700;color:#1e293b;line-height:1}
    .crm-dash-kpi-card .kpi-sub{display:block;font-size:11px;color:#94a3b8;margin-top:4px}
    .crm-dash-kpi-card.kpi-green .kpi-value{color:#10b981}
    .crm-dash-kpi-card.kpi-red   .kpi-value{color:#ef4444}
    .crm-dash-kpi-card.kpi-amber .kpi-value{color:#f59e0b}
    .crm-dash-kpi-card.kpi-indigo .kpi-value{color:#6366f1}

    .crm-charts-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:28px}
    @media(max-width:900px){.crm-charts-grid{grid-template-columns:1fr}}
    .crm-chart-box{background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:20px;box-shadow:0 1px 3px rgba(0,0,0,.06)}
    .crm-chart-box h3{margin:0 0 14px;font-size:14px;color:#374151;font-weight:600}

    .crm-lembretes-box{background:#fff;border:1px solid #fde68a;border-radius:10px;padding:20px;margin-bottom:24px;box-shadow:0 1px 3px rgba(0,0,0,.06)}
    .crm-lembretes-box h3{margin:0 0 14px;font-size:14px;color:#92400e;font-weight:600}
    .crm-lembrete-row{display:flex;align-items:center;gap:10px;padding:7px 0;border-bottom:1px solid #fef3c7;font-size:13px}
    .crm-lembrete-row:last-child{border-bottom:none}
    .crm-lembrete-hora{flex:0 0 50px;font-weight:700;color:#b45309;font-size:12px}
    .crm-lembrete-titulo{flex:1;color:#1e293b}
    .crm-lembrete-link{font-size:11px;color:#6366f1;text-decoration:none}
    .crm-lembrete-link:hover{text-decoration:underline}

    .crm-topbar-dash{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:22px}
    .crm-topbar-dash h1{margin:0;font-size:20px;color:#1e293b}
    .crm-period-form{display:flex;align-items:center;gap:8px}
    .crm-period-form select{font-size:13px;padding:5px 10px;border:1px solid #cbd5e1;border-radius:6px;background:#fff}

    .crm-att-table{width:100%;border-collapse:collapse;font-size:13px}
    .crm-att-table th{text-align:left;padding:6px 8px;color:#64748b;font-weight:600;border-bottom:2px solid #f1f5f9}
    .crm-att-table td{padding:7px 8px;border-bottom:1px solid #f8fafc;color:#374151}
    .crm-att-table tr:last-child td{border-bottom:none}
    .crm-att-bar-wrap{background:#f1f5f9;border-radius:4px;height:8px;width:120px}
    .crm-att-bar{background:#6366f1;border-radius:4px;height:8px}

    .wa-status-dot{display:inline-block;width:10px;height:10px;border-radius:50%;margin-right:5px;vertical-align:middle;background:#94a3b8}
    .wa-status-dot.open{background:#10b981;box-shadow:0 0 0 3px rgba(16,185,129,.2)}
    .wa-status-dot.connecting{background:#f59e0b}
    .wa-status-dot.close,.wa-status-dot.closed,.wa-status-dot.qr{background:#ef4444}
    .crm-section-label{font-size:13px;font-weight:600;color:#374151;margin:0 0 12px;padding-bottom:8px;border-bottom:1px solid #f1f5f9}

    .crm-negocio-filter{display:flex;align-items:center;gap:8px;flex-wrap:wrap;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:12px 16px;margin-bottom:20px}
    .crm-negocio-filter__label{font-size:12px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.6px;white-space:nowrap;margin-right:4px}
    .crm-negocio-btn{display:inline-block;padding:6px 14px;border-radius:6px;font-size:13px;font-weight:600;color:#475569;background:#fff;border:1px solid #cbd5e1;text-decoration:none;transition:all .15s}
    .crm-negocio-btn:hover{background:#f1f5f9;color:#1e293b;border-color:#94a3b8;text-decoration:none}
    .crm-negocio-btn.active{background:#152C42;color:#fff;border-color:#152C42}
    .crm-camp-row{display:flex;gap:20px;flex-wrap:wrap;margin-bottom:20px}
    .crm-camp-stat{flex:1;min-width:100px;text-align:center}
    .crm-camp-stat .cs-val{font-size:24px;font-weight:700;color:#1e293b}
    .crm-camp-stat .cs-lab{font-size:11px;color:#94a3b8;text-transform:uppercase;letter-spacing:.4px}
    </style>

    <div class="wrap tao-crm-wrap">

        <!-- Topbar -->
        <div class="crm-topbar-dash">
            <h1 style="margin:0">&#x1F4CA; Vis&atilde;o Geral &mdash; <?php echo esc_html( $ws['nome'] ); ?></h1>
            <?php
            $_form_action = $_dash_is_frontend ? cbpm_url( 'crm-dashboard' ) : admin_url( 'admin.php' );
            ?>
            <form method="get" class="crm-period-form" action="<?php echo esc_url( $_form_action ); ?>">
                <?php if ( ! $_dash_is_frontend ) : ?>
                <input type="hidden" name="page" value="tao-crm">
                <?php endif; ?>
                <input type="hidden" name="workspace_id" value="<?php echo esc_attr( $ws_id ); ?>">
                <label style="font-size:13px;color:#64748b">Período:</label>
                <select name="dias" onchange="this.form.submit()">
                    <?php foreach ( [ 1 => 'Hoje', 7 => '7 dias', 30 => '30 dias', 90 => '90 dias', 180 => '6 meses' ] as $v => $l ) : ?>
                    <option value="<?php echo $v; ?>" <?php selected( $dias, $v ); ?>><?php echo $l; ?></option>
                    <?php endforeach; ?>
                </select>
                <a href="<?php echo esc_url( $kanban_url ); ?>" class="button button-secondary" style="font-size:13px">&#x1F5C2; Kanban</a>
                <?php
                $exp_url = add_query_arg( [
                    'action'       => 'tao_crm_export_relatorio_financeiro',
                    '_wpnonce'     => wp_create_nonce( 'tao_crm_export_relatorio_financeiro' ),
                    'workspace_id' => $ws_id,
                    'dias'         => $dias,
                ], admin_url( 'admin-post.php' ) );
                ?>
                <a href="<?php echo esc_url( $exp_url ); ?>" class="button button-secondary" style="font-size:13px" title="Exportar cards do período em CSV">&#x1F4E5; Exportar CSV</a>
            </form>
        </div>

        <?php if ( $_dash_is_master && ! empty( $_dash_wss ) ) : ?>
        <!-- Barra de filtro por negócio -->
        <div class="crm-negocio-filter">
            <span class="crm-negocio-filter__label">Negócio:</span>
            <?php foreach ( $_dash_wss as $_dws ) :
                $_dws_url = $_dash_is_frontend
                    ? cbpm_url( 'crm-dashboard', [ 'workspace_id' => $_dws['id'], 'dias' => $dias ] )
                    : add_query_arg( [ 'page' => 'tao-crm', 'workspace_id' => $_dws['id'], 'dias' => $dias ], admin_url( 'admin.php' ) );
            ?>
            <a href="<?php echo esc_url( $_dws_url ); ?>"
               class="crm-negocio-btn<?php echo ( $_dws['id'] === $ws_id ) ? ' active' : ''; ?>">
                <?php echo esc_html( $_dws['nome'] ); ?>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- KPI Cards -->
        <div class="crm-dash-kpi-row">
            <div class="crm-dash-kpi-card kpi-indigo">
                <span class="kpi-label">Cards abertos</span>
                <span class="kpi-value"><?php echo count( $abertos ); ?></span>
                <span class="kpi-sub">ativos no pipeline</span>
            </div>
            <div class="crm-dash-kpi-card">
                <span class="kpi-label">Novos leads (<?php echo $dias; ?>d)</span>
                <span class="kpi-value"><?php echo count( $novos_periodo ); ?></span>
                <span class="kpi-sub">criados no período</span>
            </div>
            <div class="crm-dash-kpi-card kpi-green">
                <span class="kpi-label">Taxa de conversão</span>
                <span class="kpi-value"><?php echo $taxa; ?>%</span>
                <span class="kpi-sub"><?php echo count( $ganhos ); ?> ganhos / <?php echo $total_fechados; ?> fechados</span>
            </div>
            <div class="crm-dash-kpi-card kpi-amber">
                <span class="kpi-label">Em aberto</span>
                <span class="kpi-value">R$&nbsp;<?php echo number_format( $total_oportunidades, 0, ',', '.' ); ?></span>
                <span class="kpi-sub">oportunidades ativas</span>
            </div>
            <div class="crm-dash-kpi-card kpi-green">
                <span class="kpi-label">Receita gerada</span>
                <span class="kpi-value">R$&nbsp;<?php echo number_format( $receita_gerada, 0, ',', '.' ); ?></span>
                <span class="kpi-sub"><?php echo count( $ganhos ); ?> negócios ganhos</span>
            </div>
            <div class="crm-dash-kpi-card <?php echo count( $handoff ) > 0 ? 'kpi-red' : ''; ?>">
                <span class="kpi-label">Handoff ativo</span>
                <span class="kpi-value"><?php echo count( $handoff ); ?></span>
                <span class="kpi-sub">aguardando atendimento</span>
            </div>
            <div class="crm-dash-kpi-card" id="crm-wa-kpi">
                <span class="kpi-label">WhatsApp</span>
                <span class="kpi-value" id="crm-wa-kpi-val" style="font-size:16px;line-height:1.4">
                    <span class="wa-status-dot"></span><span style="color:#94a3b8;font-size:14px">…</span>
                </span>
                <span class="kpi-sub" id="crm-wa-kpi-sub">Verificando</span>
            </div>
        </div>

        <!-- Gráficos 2x2 -->
        <div class="crm-charts-grid">

            <!-- 1. Funil por estágio -->
            <div class="crm-chart-box">
                <h3>&#x25B6; Funil por est&aacute;gio</h3>
                <?php if ( empty( $chart_estagio_labels ) ) : ?>
                <p style="color:#94a3b8;font-size:13px">Nenhum card aberto em fases ativas.</p>
                <?php else : ?>
                <canvas id="chartFunil" height="220"></canvas>
                <?php endif; ?>
            </div>

            <!-- 2. Donut conversão -->
            <div class="crm-chart-box">
                <h3>&#x25CB; Convers&atilde;o geral</h3>
                <canvas id="chartConv" height="220"></canvas>
            </div>

            <!-- 3. Novos leads por semana -->
            <div class="crm-chart-box">
                <h3>&#x1F4C5; Novos leads &mdash; &uacute;ltimas 8 semanas</h3>
                <canvas id="chartSemanas" height="220"></canvas>
            </div>

            <!-- 4. Top atendentes -->
            <div class="crm-chart-box">
                <h3>&#x1F465; Top atendentes (cards abertos)</h3>
                <?php if ( empty( $chart_att_labels ) ) : ?>
                <p style="color:#94a3b8;font-size:13px">Nenhum card aberto com responsável.</p>
                <?php else : ?>
                <table class="crm-att-table">
                    <thead><tr><th>Atendente</th><th>Cards</th><th style="width:130px"></th></tr></thead>
                    <tbody>
                    <?php
                    $max_att = max( $chart_att_data ?: [1] );
                    foreach ( $chart_att_labels as $i => $nome_att ) :
                        $qtd_att = $chart_att_data[ $i ];
                        $pct_att = round( $qtd_att / $max_att * 100 );
                    ?>
                    <tr>
                        <td><?php echo esc_html( $nome_att ); ?></td>
                        <td><strong><?php echo $qtd_att; ?></strong></td>
                        <td>
                            <div class="crm-att-bar-wrap">
                                <div class="crm-att-bar" style="width:<?php echo $pct_att; ?>%"></div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>

        </div><!-- .crm-charts-grid -->

        <!-- Gráfico receita + Métricas campanhas -->
        <div class="crm-charts-grid" style="grid-template-columns:1.6fr 1fr;margin-bottom:24px">

            <div class="crm-chart-box">
                <h3>&#x1F4B0; Receita gerada &mdash; &uacute;ltimas 8 semanas</h3>
                <?php if ( max( $receita_data ?: [0] ) > 0 ) : ?>
                <canvas id="chartReceita" height="200"></canvas>
                <?php else : ?>
                <p style="color:#94a3b8;font-size:13px">Nenhum negócio ganho com valor registrado no período.</p>
                <?php endif; ?>
            </div>

            <div class="crm-chart-box">
                <h3>&#x1F4E3; Campanhas WhatsApp</h3>
                <?php if ( $camp_stats['total'] > 0 ) : ?>
                <div class="crm-camp-row">
                    <div class="crm-camp-stat">
                        <div class="cs-val"><?php echo $camp_stats['total']; ?></div>
                        <div class="cs-lab">Total</div>
                    </div>
                    <div class="crm-camp-stat">
                        <div class="cs-val" style="color:#10b981"><?php echo $camp_stats['ativas']; ?></div>
                        <div class="cs-lab">Ativas</div>
                    </div>
                    <div class="crm-camp-stat">
                        <div class="cs-val" style="color:#6366f1"><?php echo number_format( $camp_stats['enviados'] ); ?></div>
                        <div class="cs-lab">Enviados</div>
                    </div>
                    <div class="crm-camp-stat">
                        <div class="cs-val" style="color:#f59e0b"><?php echo $camp_stats['taxa_entrega']; ?>%</div>
                        <div class="cs-lab">Entrega</div>
                    </div>
                </div>
                <?php else : ?>
                <p style="color:#94a3b8;font-size:13px">Nenhuma campanha criada.</p>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=tao-crm&section=campanhas&workspace_id=' . esc_attr( $ws_id ) ) ); ?>" class="button button-primary" style="font-size:12px">Criar primeira campanha</a>
                <?php endif; ?>
            </div>

        </div>

        <!-- Lembretes pendentes hoje -->
        <?php if ( ! empty( $lembretes ) ) : ?>
        <div class="crm-lembretes-box">
            <h3>&#x23F0; Lembretes pendentes hoje (<?php echo count( $lembretes ); ?>)</h3>
            <?php foreach ( $lembretes as $lem ) :
                $hora_str = '';
                if ( ! empty( $lem['data_hora'] ) ) {
                    $ts = strtotime( $lem['data_hora'] );
                    $hora_str = $ts ? wp_date( 'H:i', $ts ) : '';
                }
                $card_link = '';
                if ( ! empty( $lem['card_id'] ) ) {
                    $card_link = admin_url( 'admin.php?page=tao-crm-card&card_id=' . $lem['card_id'] );
                }
            ?>
            <div class="crm-lembrete-row">
                <span class="crm-lembrete-hora"><?php echo esc_html( $hora_str ); ?></span>
                <span class="crm-lembrete-titulo"><?php echo esc_html( $lem['titulo'] ?? '(sem título)' ); ?></span>
                <?php if ( $card_link ) : ?>
                <a href="<?php echo esc_url( $card_link ); ?>" class="crm-lembrete-link">Ver card #<?php echo esc_html( $lem['card_id'] ); ?></a>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else : ?>
        <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:14px 20px;margin-bottom:24px;font-size:13px;color:#166534">
            &#x2705; Nenhum lembrete pendente para hoje.
        </div>
        <?php endif; ?>

    </div><!-- .wrap -->

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
    var taoCrmDashboard = <?php echo wp_json_encode( $dados_js ); ?>;
    (function(){
        var d = taoCrmDashboard;
        Chart.defaults.font.family = '-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif';
        Chart.defaults.font.size   = 12;

        // Paleta de cores para estágios
        var stageColors = [
            '#6366f1','#3b82f6','#06b6d4','#10b981',
            '#f59e0b','#f97316','#ec4899','#8b5cf6',
            '#64748b','#14b8a6'
        ];

        // 1. Funil por estágio (barra horizontal)
        var ctxF = document.getElementById('chartFunil');
        if(ctxF && d.estagio.labels.length){
            new Chart(ctxF, {
                type: 'bar',
                data: {
                    labels: d.estagio.labels,
                    datasets:[{
                        label: 'Cards abertos',
                        data: d.estagio.data,
                        backgroundColor: d.estagio.labels.map(function(_,i){ return stageColors[i % stageColors.length]; }),
                        borderRadius: 4
                    }]
                },
                options:{
                    indexAxis: 'y',
                    responsive: true,
                    plugins:{ legend:{ display:false } },
                    scales:{
                        x:{ beginAtZero:true, ticks:{ stepSize:1 }, grid:{ color:'#f1f5f9' } },
                        y:{ grid:{ display:false } }
                    }
                }
            });
        }

        // 2. Donut conversão
        var ctxC = document.getElementById('chartConv');
        if(ctxC){
            new Chart(ctxC, {
                type: 'doughnut',
                data:{
                    labels: d.conv.labels,
                    datasets:[{
                        data: d.conv.data,
                        backgroundColor: d.conv.colors,
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options:{
                    responsive: true,
                    cutout: '62%',
                    plugins:{
                        legend:{ position:'bottom', labels:{ padding:14, usePointStyle:true } }
                    }
                }
            });
        }

        // 3. Novos leads por semana (linha)
        var ctxS = document.getElementById('chartSemanas');
        if(ctxS){
            new Chart(ctxS, {
                type: 'line',
                data:{
                    labels: d.semanas.labels,
                    datasets:[{
                        label: 'Novos leads',
                        data: d.semanas.data,
                        borderColor: '#6366f1',
                        backgroundColor: 'rgba(99,102,241,.12)',
                        fill: true,
                        tension: 0.35,
                        pointBackgroundColor: '#6366f1',
                        pointRadius: 4
                    }]
                },
                options:{
                    responsive: true,
                    plugins:{ legend:{ display:false } },
                    scales:{
                        y:{ beginAtZero:true, ticks:{ stepSize:1 }, grid:{ color:'#f1f5f9' } },
                        x:{ grid:{ display:false } }
                    }
                }
            });
        }

        // 5. Receita por semana (barra)
        var ctxR = document.getElementById('chartReceita');
        if(ctxR && d.receita && d.receita.data.some(function(v){ return v>0; })){
            new Chart(ctxR, {
                type: 'bar',
                data:{
                    labels: d.receita.labels,
                    datasets:[{
                        label: 'Receita (R$)',
                        data: d.receita.data,
                        backgroundColor: 'rgba(16,185,129,.7)',
                        borderColor: '#10b981',
                        borderWidth: 1,
                        borderRadius: 4
                    }]
                },
                options:{
                    responsive: true,
                    plugins:{ legend:{ display:false } },
                    scales:{
                        y:{ beginAtZero:true, ticks:{
                            callback: function(v){ return 'R$'+v.toLocaleString('pt-BR'); }
                        }, grid:{ color:'#f1f5f9' } },
                        x:{ grid:{ display:false } }
                    }
                }
            });
        }

        // Status WhatsApp via AJAX
        (function loadWaStatus(){
            if(!d.ajaxUrl || !d.wsId) return;
            var fd = new FormData();
            fd.append('action','tao_crm_wa_status');
            fd.append('nonce', d.nonce);
            fd.append('workspace_id', d.wsId);
            fetch(d.ajaxUrl, { method:'POST', body:fd, credentials:'same-origin' })
            .then(function(r){ return r.json(); })
            .then(function(resp){
                var kpiVal = document.getElementById('crm-wa-kpi-val');
                var kpiSub = document.getElementById('crm-wa-kpi-sub');
                if(!kpiVal) return;
                if(resp.success && resp.data && resp.data.length){
                    var insts = resp.data;
                    var html = '';
                    insts.forEach(function(inst){
                        var cls = inst.state === 'open' ? 'open' : (inst.state === 'connecting' ? 'connecting' : 'close');
                        var label = inst.state === 'open' ? 'Conectado' : (inst.state === 'connecting' ? 'Conectando' : 'Desconectado');
                        html += '<div style="font-size:13px;margin-bottom:2px"><span class="wa-status-dot '+cls+'"></span>'+label+'</div>';
                    });
                    kpiVal.innerHTML = html;
                    var names = insts.map(function(i){ return i.nome || i.instancia; });
                    if(kpiSub) kpiSub.textContent = names.join(', ');
                } else {
                    kpiVal.innerHTML = '<span style="color:#94a3b8;font-size:13px">Sem instância</span>';
                    if(kpiSub) kpiSub.textContent = 'Nenhuma configurada';
                }
            })
            .catch(function(){ /* silencia erro de rede */ });
        })();

    })();
    </script>
    <?php
}
