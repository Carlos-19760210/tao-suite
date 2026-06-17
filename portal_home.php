<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function cbpm_page_portal_home() {
    if ( ! cbpm_can_access() ) return;

    $is_frontend = ! empty( $GLOBALS['cbpm_is_frontend'] );

    // ── Período ────────────────────────────────────────────────────────────
    $periodo = sanitize_key( $_GET['periodo'] ?? '30d' );
    $periodos = [
        '7d'      => 'Últimos 7 dias',
        '30d'     => 'Últimos 30 dias',
        '90d'     => 'Últimos 90 dias',
        'mes'     => 'Este mês',
        'mes_ant' => 'Mês anterior',
    ];
    if ( ! isset( $periodos[ $periodo ] ) ) $periodo = '30d';

    $tz  = new DateTimeZone( 'America/Sao_Paulo' );
    $now = new DateTime( 'now', $tz );

    switch ( $periodo ) {
        case '7d':
            $date_from = ( clone $now )->modify( '-6 days' )->format( 'Y-m-d' ) . 'T00:00:00-03:00';
            $dias_crm  = 7;
            break;
        case '90d':
            $date_from = ( clone $now )->modify( '-89 days' )->format( 'Y-m-d' ) . 'T00:00:00-03:00';
            $dias_crm  = 90;
            break;
        case 'mes':
            $date_from = $now->format( 'Y-m' ) . '-01T00:00:00-03:00';
            $dias_crm  = (int) $now->format( 'j' );
            break;
        case 'mes_ant':
            $first = ( clone $now )->modify( 'first day of last month' );
            $last  = ( clone $now )->modify( 'last day of last month' );
            $date_from = $first->format( 'Y-m-d' ) . 'T00:00:00-03:00';
            $dias_crm  = (int) $last->format( 't' );
            break;
        default: // 30d
            $date_from = ( clone $now )->modify( '-29 days' )->format( 'Y-m-d' ) . 'T00:00:00-03:00';
            $dias_crm  = 30;
    }
    $qdt = '&criado_em=gte.' . urlencode( $date_from );

    // ── Workspace selector (master vê todos os negócios) ────────────────────
    $ph_crm_ws_id = sanitize_text_field( $_GET['crm_ws'] ?? '' );
    $ph_todos_ws  = [];
    if ( function_exists( 'cbpm_is_master' ) && cbpm_is_master() && function_exists( 'tao_crm_get_workspaces' ) ) {
        $ph_todos_ws = tao_crm_get_workspaces();
    }

    // ══ TAO Neo ════════════════════════════════════════════════════════════
    $r_hist  = cbpm_api( '/historico?select=phone' . $qdt . '&limit=5000' );
    $hist    = $r_hist['ok'] ? $r_hist['data'] : [];
    $conv_p  = count( array_unique( array_column( $hist, 'phone' ) ) );

    $r_leads  = cbpm_api( '/leads?select=id' . $qdt );
    $leads_p  = $r_leads['ok'] ? count( $r_leads['data'] ) : '—';

    $r_ped   = cbpm_api( '/pedidos?select=id,total' . $qdt );
    $ped_p   = $r_ped['ok'] ? count( $r_ped['data'] ) : 0;
    $fat_p   = $r_ped['ok'] ? array_sum( array_column( $r_ped['data'], 'total' ) ) : 0;

    $r_lr = cbpm_api( '/leads?select=nome,telefone,status,criado_em&order=criado_em.desc&limit=5' );
    $leads_recentes = $r_lr['ok'] ? ( $r_lr['data'] ?? [] ) : [];

    // ══ TAO CRM ════════════════════════════════════════════════════════════
    $has_crm    = function_exists( 'tao_crm_page_dashboard' );
    $crm_ok     = false;
    $abertos    = 0;
    $novos_crm  = 0;
    $taxa       = 0;
    $opor       = 0;
    $handoff    = 0;
    $ganhos_n   = 0;
    $perdidos_n = 0;
    $cards_recentes = [];

    if ( $has_crm ) {
        $ws = tao_crm_get_workspace( $ph_crm_ws_id ?: null );
        if ( $ws ) {
            $crm_ok    = true;
            $ws_id     = $ws['id'];
            $desde_crm = gmdate( 'c', strtotime( "-{$dias_crm} days" ) );

            $rc_all = tao_crm_api(
                "/crm_cards?workspace_id=eq.$ws_id" .
                "&select=id,fechado,estagio_id,criado_em,atendimento_humano,valor_oportunidade,titulo" .
                "&order=criado_em.desc&limit=2000"
            );
            $all_cards = $rc_all['ok'] ? ( $rc_all['data'] ?? [] ) : [];

            $re_pipes = tao_crm_api( "/crm_pipelines?workspace_id=eq.$ws_id&select=id" );
            $pipe_ids = array_column( $re_pipes['ok'] ? ( $re_pipes['data'] ?? [] ) : [], 'id' );
            $estagios = [];
            if ( $pipe_ids ) {
                $re_est = tao_crm_api( "/crm_estagios?pipeline_id=in.(" . implode( ',', $pipe_ids ) . ")&select=id,tipo" );
                foreach ( $re_est['ok'] ? ( $re_est['data'] ?? [] ) : [] as $e ) {
                    $estagios[ $e['id'] ] = $e['tipo'];
                }
            }

            $abertos_arr  = array_filter( $all_cards, fn($c) => empty( $c['fechado'] ) );
            $fechados_arr = array_filter( $all_cards, fn($c) => ! empty( $c['fechado'] ) );
            $ganhos_arr   = array_filter( $fechados_arr, fn($c) => ( $estagios[ $c['estagio_id'] ] ?? '' ) === 'ganho' );
            $perdidos_arr = array_filter( $fechados_arr, fn($c) => ( $estagios[ $c['estagio_id'] ] ?? '' ) === 'perdido' );
            $handoff_arr  = array_filter( $abertos_arr,  fn($c) => ! empty( $c['atendimento_humano'] ) );
            $novos_arr    = array_filter( $all_cards,    fn($c) => ( $c['criado_em'] ?? '' ) >= $desde_crm );

            $abertos    = count( $abertos_arr );
            $novos_crm  = count( $novos_arr );
            $ganhos_n   = count( $ganhos_arr );
            $perdidos_n = count( $perdidos_arr );
            $taxa       = count( $fechados_arr ) > 0 ? round( $ganhos_n / count( $fechados_arr ) * 100 ) : 0;
            $opor       = array_sum( array_column( array_values( $abertos_arr ), 'valor_oportunidade' ) );
            $handoff    = count( $handoff_arr );
            $cards_recentes = array_slice( array_values( $abertos_arr ), 0, 5 );
        }
    }

    // ══ TAO Fórmulas ══════════════════════════════════════════════════════
    $has_formula   = function_exists( 'tao_formula_page_dashboard' );
    $orc_pendentes = 0;
    $orc_hoje      = 0;
    $orc_aprovados = 0;
    $orc_recentes  = [];
    if ( $has_formula && function_exists( 'tao_formula_api' ) ) {
        $cid_formula = function_exists( 'cbpm_current_cliente_id' ) ? cbpm_current_cliente_id() : null;
        if ( $cid_formula ) {
            $hoje_str = ( new DateTime( 'now', new DateTimeZone('America/Sao_Paulo') ) )->format('Y-m-d');
            $ro = tao_formula_api( "/orcamentos?cliente_id=eq.$cid_formula&select=id,status,criado_em,nome_paciente,total_orcamento&order=criado_em.desc&limit=200" );
            $orcs = $ro['ok'] ? ( $ro['data'] ?? [] ) : [];
            foreach ( $orcs as $o ) {
                if ( substr( $o['criado_em'] ?? '', 0, 10 ) === $hoje_str ) $orc_hoje++;
                if ( ( $o['status'] ?? '' ) === 'pendente_revisao' ) $orc_pendentes++;
                if ( ( $o['status'] ?? '' ) === 'aprovado_farma' )   $orc_aprovados++;
            }
            $orc_recentes = array_slice( $orcs, 0, 5 );
        }
    }

    // ── URLs ────────────────────────────────────────────────────────────────
    $url_neo_dash     = $is_frontend ? cbpm_url( 'neo-dashboard' )     : admin_url( 'admin.php?page=chatbot-platform' );
    $url_crm_dash     = $is_frontend ? cbpm_url( 'crm-dashboard' )     : admin_url( 'admin.php?page=tao-crm' );
    $url_formula_dash = $is_frontend ? cbpm_url( 'formula-dashboard' ) : admin_url( 'admin.php?page=tao-formula' );
    $url_formula_orc  = $is_frontend ? cbpm_url( 'formula-orcamentos' ): admin_url( 'admin.php?page=tao-formula-orcamentos' );
    $url_leads    = $is_frontend ? cbpm_url( 'leads' )         : admin_url( 'admin.php?page=chatbot-platform-leads' );
    $url_kanban   = $is_frontend ? cbpm_url( 'crm-kanban' )    : admin_url( 'admin.php?page=tao-crm-kanban' );
    $url_inbox    = $is_frontend ? cbpm_url( 'crm-inbox' )     : admin_url( 'admin.php?page=tao-crm-inbox' );
    $url_self     = $is_frontend ? cbpm_url( 'dashboard' )     : admin_url( 'admin.php?page=chatbot-platform' );

    $label_periodo = $periodos[ $periodo ];
    ?>
    <style>
    .ph-wrap{max-width:1200px;margin:0 auto;padding:24px 20px;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
    .ph-header{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:28px;padding-bottom:18px;border-bottom:1px solid #e2e8f0}
    .ph-header h1{margin:0;font-size:22px;color:#1e293b;font-weight:700}
    .ph-period-form{display:flex;align-items:center;gap:8px}
    .ph-period-form label{font-size:12px;color:#64748b;font-weight:600}
    .ph-period-form select{font-size:13px;padding:6px 10px;border:1px solid #cbd5e1;border-radius:7px;background:#fff;color:#1e293b;cursor:pointer}
    .ph-product-block{background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:22px 24px;margin-bottom:24px;box-shadow:0 1px 4px rgba(0,0,0,.06)}
    .ph-product-header{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:18px}
    .ph-product-title{display:flex;align-items:center;gap:10px}
    .ph-product-title h2{margin:0;font-size:16px;font-weight:700;color:#1e293b}
    .ph-product-badge{font-size:11px;font-weight:600;color:#6366f1;background:#ede9fe;padding:3px 9px;border-radius:20px;letter-spacing:.4px}
    .ph-product-badge.green{background:#dcfce7;color:#166534}
    .ph-product-actions{display:flex;gap:8px;flex-wrap:wrap}
    .ph-btn{display:inline-flex;align-items:center;gap:5px;font-size:12px;font-weight:600;padding:6px 12px;border-radius:7px;text-decoration:none;border:1px solid transparent;transition:opacity .15s}
    .ph-btn-primary{background:#6366f1;color:#fff;border-color:#6366f1}.ph-btn-primary:hover{opacity:.88;color:#fff}
    .ph-btn-green{background:#10b981;color:#fff;border-color:#10b981}.ph-btn-green:hover{opacity:.88;color:#fff}
    .ph-btn-ghost{background:#f8fafc;color:#475569;border-color:#e2e8f0}.ph-btn-ghost:hover{background:#f1f5f9;color:#475569}
    .ph-kpi-row{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:16px}
    .ph-kpi{flex:1;min-width:130px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:14px 16px}
    .ph-kpi .kpi-label{display:block;font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px;font-weight:600}
    .ph-kpi .kpi-value{display:block;font-size:26px;font-weight:800;color:#1e293b;line-height:1}
    .ph-kpi .kpi-sub{display:block;font-size:11px;color:#94a3b8;margin-top:3px}
    .ph-kpi.kpi-green .kpi-value{color:#10b981}
    .ph-kpi.kpi-amber .kpi-value{color:#f59e0b}
    .ph-kpi.kpi-indigo .kpi-value{color:#6366f1}
    .ph-kpi.kpi-warn{border-color:#fde68a;background:#fffbeb}.ph-kpi.kpi-warn .kpi-value{color:#b45309}
    .ph-recent-title{font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.5px;margin:0 0 10px}
    .ph-recent-table{width:100%;border-collapse:collapse;font-size:13px}
    .ph-recent-table th{text-align:left;padding:5px 8px;color:#94a3b8;font-weight:600;font-size:11px;border-bottom:1px solid #f1f5f9}
    .ph-recent-table td{padding:7px 8px;border-bottom:1px solid #f8fafc;color:#374151}
    .ph-recent-table tr:last-child td{border-bottom:none}
    .ph-status-pill{font-size:10px;font-weight:700;padding:2px 7px;border-radius:20px;text-transform:uppercase;letter-spacing:.4px}
    .ph-status-novo{background:#dbeafe;color:#1d4ed8}
    .ph-status-contatado{background:#fef9c3;color:#854d0e}
    .ph-status-fechado{background:#dcfce7;color:#166534}
    .ph-status-perdido{background:#fee2e2;color:#991b1b}
    .ph-status-negociando{background:#ede9fe;color:#5b21b6}
    .ph-date-small{font-size:11px;color:#94a3b8}
    .ph-empty{font-size:13px;color:#94a3b8;padding:8px 0}
    .ph-grid2{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:4px}
    @media(max-width:860px){.ph-grid2{grid-template-columns:1fr}}
    .ph-block-inner{background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:14px 16px}
    .ph-tag-label{font-size:11px;font-weight:700;color:#6366f1;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;display:block}
    .ph-periodo-badge{display:inline-block;font-size:11px;font-weight:600;color:#475569;background:#f1f5f9;border:1px solid #e2e8f0;padding:2px 8px;border-radius:20px;margin-left:6px;vertical-align:middle}
    </style>

    <div class="ph-wrap">

        <div class="ph-header">
            <h1>&#x1F3E0; Visão Geral <span class="ph-periodo-badge"><?php echo esc_html( $label_periodo ); ?></span></h1>
            <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
            <?php if ( count( $ph_todos_ws ) > 1 ) : ?>
            <form method="get" style="display:flex;align-items:center;gap:6px">
                <?php if ( ! $is_frontend ) : ?><input type="hidden" name="page" value="chatbot-platform"><?php endif; ?>
                <?php if ( $periodo !== '30d' ) : ?><input type="hidden" name="periodo" value="<?php echo esc_attr( $periodo ); ?>"><?php endif; ?>
                <label style="font-size:12px;color:#64748b;font-weight:600;white-space:nowrap">&#x1F3E2; Negócio:</label>
                <select name="crm_ws" onchange="this.form.submit()"
                        style="font-size:13px;font-weight:600;padding:6px 10px;border:1px solid #cbd5e1;border-radius:7px;background:#fff;color:#1e293b;cursor:pointer">
                    <?php foreach ( $ph_todos_ws as $_pw ) : ?>
                    <option value="<?php echo esc_attr( $_pw['id'] ); ?>" <?php selected( $ph_crm_ws_id, $_pw['id'] ); ?>>
                        <?php echo esc_html( $_pw['nome'] ); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </form>
            <?php endif; ?>
            <form method="get" class="ph-period-form">
                <?php if ( ! $is_frontend ) : ?>
                <input type="hidden" name="page" value="chatbot-platform">
                <?php endif; ?>
                <?php if ( $ph_crm_ws_id ) : ?>
                <input type="hidden" name="crm_ws" value="<?php echo esc_attr( $ph_crm_ws_id ); ?>">
                <?php endif; ?>
                <label>Período:</label>
                <select name="periodo" onchange="this.form.submit()">
                    <?php foreach ( $periodos as $val => $lbl ) : ?>
                    <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $periodo, $val ); ?>><?php echo esc_html( $lbl ); ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
            </div>
        </div>

        <!-- ══ TAO Neo ═══════════════════════════════════════════════════════ -->
        <div class="ph-product-block">
            <div class="ph-product-header">
                <div class="ph-product-title">
                    <span style="font-size:20px">&#x1F916;</span>
                    <h2>TAO Neo</h2>
                    <span class="ph-product-badge">CHATBOT WHATSAPP</span>
                </div>
                <div class="ph-product-actions">
                    <a href="<?php echo esc_url( $url_leads ); ?>" class="ph-btn ph-btn-ghost">&#x1F4CB; Leads</a>
                    <a href="<?php echo esc_url( $url_neo_dash ); ?>" class="ph-btn ph-btn-primary">Dashboard &#x2192;</a>
                </div>
            </div>
            <div class="ph-kpi-row">
                <div class="ph-kpi kpi-indigo">
                    <span class="kpi-label">Conversas únicas</span>
                    <span class="kpi-value"><?php echo number_format( $conv_p ); ?></span>
                    <span class="kpi-sub"><?php echo esc_html( $label_periodo ); ?></span>
                </div>
                <div class="ph-kpi">
                    <span class="kpi-label">Leads gerados</span>
                    <span class="kpi-value"><?php echo is_int( $leads_p ) ? number_format( $leads_p ) : $leads_p; ?></span>
                    <span class="kpi-sub"><?php echo esc_html( $label_periodo ); ?></span>
                </div>
                <div class="ph-kpi kpi-green">
                    <span class="kpi-label">Pedidos</span>
                    <span class="kpi-value"><?php echo number_format( $ped_p ); ?></span>
                    <span class="kpi-sub"><?php echo esc_html( $label_periodo ); ?></span>
                </div>
                <?php if ( $fat_p > 0 ) : ?>
                <div class="ph-kpi kpi-amber">
                    <span class="kpi-label">Faturamento</span>
                    <span class="kpi-value">R$&nbsp;<?php echo number_format( $fat_p, 0, ',', '.' ); ?></span>
                    <span class="kpi-sub"><?php echo esc_html( $label_periodo ); ?></span>
                </div>
                <?php endif; ?>
            </div>
            <?php if ( ! empty( $leads_recentes ) ) : ?>
            <p class="ph-recent-title">Leads recentes</p>
            <table class="ph-recent-table">
                <thead><tr><th>Nome</th><th>Telefone</th><th>Status</th><th>Data</th></tr></thead>
                <tbody>
                <?php foreach ( $leads_recentes as $l ) :
                    $sn = $l['status'] ?? 'novo';
                    $dt = ! empty( $l['criado_em'] ) ? wp_date( 'd/m H:i', strtotime( $l['criado_em'] ) ) : '—';
                ?>
                <tr>
                    <td><?php echo esc_html( $l['nome'] ?? '—' ); ?></td>
                    <td><?php echo esc_html( $l['telefone'] ?? '—' ); ?></td>
                    <td><span class="ph-status-pill ph-status-<?php echo esc_attr( $sn ); ?>"><?php echo esc_html( $sn ); ?></span></td>
                    <td class="ph-date-small"><?php echo esc_html( $dt ); ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else : ?>
            <p class="ph-empty">Nenhum lead recente.</p>
            <?php endif; ?>
        </div>

        <!-- ══ TAO CRM ════════════════════════════════════════════════════════ -->
        <?php if ( $has_crm ) : ?>
        <div class="ph-product-block">
            <div class="ph-product-header">
                <div class="ph-product-title">
                    <span style="font-size:20px">&#x1F4BC;</span>
                    <h2>TAO CRM</h2>
                    <span class="ph-product-badge green">CRM CONVERSACIONAL</span>
                </div>
                <div class="ph-product-actions">
                    <a href="<?php echo esc_url( $url_inbox ); ?>" class="ph-btn ph-btn-ghost">&#x1F4AC; Inbox</a>
                    <a href="<?php echo esc_url( $url_kanban ); ?>" class="ph-btn ph-btn-ghost">&#x1F5C2; Kanban</a>
                    <a href="<?php echo esc_url( $url_crm_dash ); ?>" class="ph-btn ph-btn-green">Dashboard &#x2192;</a>
                </div>
            </div>
            <?php if ( $crm_ok ) : ?>
            <div class="ph-kpi-row">
                <div class="ph-kpi kpi-indigo">
                    <span class="kpi-label">Cards abertos</span>
                    <span class="kpi-value"><?php echo $abertos; ?></span>
                    <span class="kpi-sub">em andamento</span>
                </div>
                <div class="ph-kpi">
                    <span class="kpi-label">Novos leads</span>
                    <span class="kpi-value"><?php echo $novos_crm; ?></span>
                    <span class="kpi-sub"><?php echo esc_html( $label_periodo ); ?></span>
                </div>
                <div class="ph-kpi kpi-green">
                    <span class="kpi-label">Taxa de conversão</span>
                    <span class="kpi-value"><?php echo $taxa; ?>%</span>
                    <span class="kpi-sub"><?php echo $ganhos_n; ?> ganhos / <?php echo ($ganhos_n + $perdidos_n); ?> fechados</span>
                </div>
                <div class="ph-kpi kpi-amber">
                    <span class="kpi-label">Em oportunidades</span>
                    <span class="kpi-value">R$&nbsp;<?php echo number_format( $opor, 0, ',', '.' ); ?></span>
                    <span class="kpi-sub">cards abertos</span>
                </div>
                <?php if ( $handoff > 0 ) : ?>
                <div class="ph-kpi kpi-warn">
                    <span class="kpi-label">&#x26A0; Handoff ativo</span>
                    <span class="kpi-value"><?php echo $handoff; ?></span>
                    <span class="kpi-sub">aguardando humano</span>
                </div>
                <?php else : ?>
                <div class="ph-kpi kpi-green">
                    <span class="kpi-label">Handoff ativo</span>
                    <span class="kpi-value">0</span>
                    <span class="kpi-sub">&#x2705; sem fila</span>
                </div>
                <?php endif; ?>
            </div>
            <div class="ph-grid2">
                <div class="ph-block-inner">
                    <span class="ph-tag-label">Resultados — histórico geral</span>
                    <div style="display:flex;gap:20px;align-items:center;justify-content:space-around;padding:8px 0">
                        <div style="text-align:center">
                            <div style="font-size:32px;font-weight:800;color:#10b981"><?php echo $ganhos_n; ?></div>
                            <div style="font-size:12px;color:#64748b;font-weight:600">GANHOS</div>
                        </div>
                        <div style="font-size:22px;color:#cbd5e1">×</div>
                        <div style="text-align:center">
                            <div style="font-size:32px;font-weight:800;color:#ef4444"><?php echo $perdidos_n; ?></div>
                            <div style="font-size:12px;color:#64748b;font-weight:600">PERDIDOS</div>
                        </div>
                        <?php if ( $ganhos_n + $perdidos_n > 0 ) : ?>
                        <div style="text-align:center">
                            <div style="font-size:32px;font-weight:800;color:#6366f1"><?php echo $taxa; ?>%</div>
                            <div style="font-size:12px;color:#64748b;font-weight:600">CONVERSÃO</div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="ph-block-inner">
                    <span class="ph-tag-label">Últimos cards abertos</span>
                    <?php if ( empty( $cards_recentes ) ) : ?>
                    <p class="ph-empty">Nenhum card aberto.</p>
                    <?php else : ?>
                    <table class="ph-recent-table">
                        <tbody>
                        <?php foreach ( $cards_recentes as $card ) :
                            $dt_card = ! empty( $card['criado_em'] ) ? wp_date( 'd/m H:i', strtotime( $card['criado_em'] ) ) : '—';
                            $card_url = $is_frontend
                                ? cbpm_url( 'crm-kanban', [ 'action' => 'card', 'id' => $card['id'] ] )
                                : admin_url( 'admin.php?page=tao-crm-kanban&action=card&id=' . $card['id'] );
                        ?>
                        <tr>
                            <td><a href="<?php echo esc_url( $card_url ); ?>" style="color:#6366f1;text-decoration:none;font-weight:600;font-size:12px"><?php echo esc_html( $card['titulo'] ?? 'Card #' . $card['id'] ); ?></a></td>
                            <td class="ph-date-small"><?php echo esc_html( $dt_card ); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>
            <?php else : ?>
            <p class="ph-empty">Workspace CRM não configurado. <a href="<?php echo esc_url( $url_crm_dash ); ?>">Configurar</a></p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- ══ TAO Fórmulas ══════════════════════════════════════════════════ -->
        <?php if ( $has_formula ) : ?>
        <div class="ph-product-block">
            <div class="ph-product-header">
                <div class="ph-product-title">
                    <span style="font-size:20px">&#x1F9EA;</span>
                    <h2>TAO F&oacute;rmulas</h2>
                    <span class="ph-product-badge" style="background:#fef3c7;color:#92400e">COTA&Ccedil;&Atilde;O MANIPULA&Ccedil;&Atilde;O</span>
                </div>
                <div class="ph-product-actions">
                    <a href="<?php echo esc_url( $url_formula_orc ); ?>" class="ph-btn ph-btn-ghost">&#x1F4CB; Or&ccedil;amentos</a>
                    <a href="<?php echo esc_url( $url_formula_dash ); ?>" class="ph-btn ph-btn-primary" style="background:#d97706;border-color:#d97706">Dashboard &#x2192;</a>
                </div>
            </div>
            <div class="ph-kpi-row">
                <div class="ph-kpi kpi-warn">
                    <span class="kpi-label">Pendentes revis&atilde;o</span>
                    <span class="kpi-value"><?php echo $orc_pendentes; ?></span>
                    <span class="kpi-sub">aguardando farmac&ecirc;utico</span>
                </div>
                <div class="ph-kpi">
                    <span class="kpi-label">Or&ccedil;amentos hoje</span>
                    <span class="kpi-value"><?php echo $orc_hoje; ?></span>
                    <span class="kpi-sub">novos pedidos</span>
                </div>
                <div class="ph-kpi kpi-green">
                    <span class="kpi-label">Aprovados</span>
                    <span class="kpi-value"><?php echo $orc_aprovados; ?></span>
                    <span class="kpi-sub">prontos p/ envio</span>
                </div>
            </div>
            <?php if ( ! empty( $orc_recentes ) ) : ?>
            <p class="ph-recent-title">Or&ccedil;amentos recentes</p>
            <table class="ph-recent-table">
                <thead><tr><th>Paciente</th><th>Total</th><th>Status</th><th>Data</th></tr></thead>
                <tbody>
                <?php
                $status_labels = [
                    'pendente_revisao'  => ['label'=>'Pendente',  'class'=>'ph-status-novo'],
                    'aprovado_farma'    => ['label'=>'Aprovado',  'class'=>'ph-status-fechado'],
                    'enviado_paciente'  => ['label'=>'Enviado',   'class'=>'ph-status-contatado'],
                    'aceito_paciente'   => ['label'=>'Aceito',    'class'=>'ph-status-fechado'],
                    'rejeitado'         => ['label'=>'Rejeitado', 'class'=>'ph-status-perdido'],
                ];
                foreach ( $orc_recentes as $o ) :
                    $st   = $o['status'] ?? 'pendente_revisao';
                    $sl   = $status_labels[ $st ] ?? ['label'=>$st,'class'=>'ph-status-novo'];
                    $dt   = ! empty( $o['criado_em'] ) ? wp_date( 'd/m H:i', strtotime( $o['criado_em'] ) ) : '—';
                    $total= ! empty( $o['total_orcamento'] ) ? 'R$&nbsp;' . number_format( $o['total_orcamento'], 2, ',', '.' ) : '—';
                ?>
                <tr>
                    <td><?php echo esc_html( $o['nome_paciente'] ?? '—' ); ?></td>
                    <td><?php echo $total; ?></td>
                    <td><span class="ph-status-pill <?php echo esc_attr($sl['class']); ?>"><?php echo esc_html($sl['label']); ?></span></td>
                    <td class="ph-date-small"><?php echo esc_html( $dt ); ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else : ?>
            <p class="ph-empty">Nenhum or&ccedil;amento ainda. <a href="<?php echo esc_url($url_formula_orc); ?>">Ver or&ccedil;amentos</a></p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    </div>
    <?php
}
