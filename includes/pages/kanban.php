<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function tao_crm_page_kanban() {
    if ( ! function_exists( 'cbpm_can_access' ) || ! cbpm_can_access() ) {
        echo '<div class="wrap"><p>Acesso negado.</p></div>'; return;
    }

    $action = sanitize_key( $_GET['action'] ?? 'list' );
    if ( $action === 'card' ) { tao_crm_page_card(); return; }

    $ws_id = sanitize_text_field( $_GET['workspace_id'] ?? '' );
    $ws    = tao_crm_get_workspace( $ws_id ?: null );

    if ( ! $ws ) {
        echo '<div class="wrap"><div class="notice notice-warning"><p>';
        echo 'Nenhum workspace configurado. ';
        if ( current_user_can( 'manage_options' ) ) {
            echo '<a href="' . esc_url( tao_crm_settings_url() ) . '">Configure agora</a>.';
        }
        echo '</p></div></div>'; return;
    }
    $ws_id = $ws['id'];

    $rp        = tao_crm_api( "/crm_pipelines?workspace_id=eq.$ws_id&ativo=eq.true&order=ordem.asc" );
    $pipelines = $rp['ok'] ? ( $rp['data'] ?? [] ) : [];

    if ( empty( $pipelines ) ) {
        echo '<div class="wrap"><div class="notice notice-warning"><p>Nenhum pipeline criado. ';
        if ( current_user_can( 'manage_options' ) ) {
            echo '<a href="' . esc_url( tao_crm_settings_url( [ 'tab' => 'pipelines', 'workspace_id' => $ws_id ] ) ) . '">Criar pipeline</a>.';
        }
        echo '</p></div></div>'; return;
    }

    $pipeline_id = sanitize_text_field( $_GET['pipeline_id'] ?? '' );
    if ( ! $pipeline_id || ! in_array( $pipeline_id, array_column( $pipelines, 'id' ) ) ) {
        $pipeline_id = $pipelines[0]['id'];
    }
    $pipeline = array_values( array_filter( $pipelines, fn( $p ) => $p['id'] === $pipeline_id ) )[0];

    $view = sanitize_key( $_GET['view'] ?? 'kanban' );

    $re       = tao_crm_api( "/crm_estagios?pipeline_id=eq.$pipeline_id&order=ordem.asc" );
    $estagios = $re['ok'] ? ( $re['data'] ?? [] ) : [];

    // Atendentes veem só seus cards; gestores veem todos
    $cards_filter = '';
    if ( ! tao_crm_is_gestor( $ws_id ) ) {
        $uid = get_current_user_id();
        $cards_filter = "&or=(responsavel_id.eq.$uid,responsavel_id.is.null)";
    }
    $rc    = tao_crm_api( "/crm_cards?pipeline_id=eq.$pipeline_id&order=movido_em.desc&limit=500$cards_filter" );
    $cards = $rc['ok'] ? ( $rc['data'] ?? [] ) : [];

    if ( ! defined( 'TAO_CRM_KANBAN_PAGE_SIZE' ) ) define( 'TAO_CRM_KANBAN_PAGE_SIZE', 20 );

    // Map estágio id → nome para inbox
    $estagios_map = [];
    foreach ( $estagios as $e ) { $estagios_map[ $e['id'] ] = $e; }

    // Agrupar por estágio para kanban
    $cards_by_stage = [];
    foreach ( $cards as $card ) {
        $cards_by_stage[ $card['estagio_id'] ][] = $card;
    }

    // Contar não lidos
    $nao_lidos = 0;
    foreach ( $cards as $c ) {
        $msg  = $c['ultima_mensagem_em'] ?? '';
        $lida = $c['ultima_leitura_em']  ?? '';
        if ( $msg && ( ! $lida || $msg > $lida ) ) $nao_lidos++;
    }

    $base_url    = tao_crm_url( [ 'workspace_id' => $ws_id ] );
    $base_url_pl = tao_crm_url( [ 'workspace_id' => $ws_id, 'pipeline_id' => $pipeline_id ] );

    // Tags do workspace para filtro e exibição nos cards
    $tags_ws   = tao_crm_api( "/crm_tags?workspace_id=eq.$ws_id&order=nome.asc" );
    $tags_list = $tags_ws['ok'] ? ( $tags_ws['data'] ?? [] ) : [];

    // Atendentes com cards ativos neste pipeline
    $wp_users_k = [];
    foreach ( get_users( [ 'fields' => [ 'ID', 'display_name' ] ] ) as $u ) {
        $wp_users_k[ $u->ID ] = $u->display_name;
    }

    ?>
    <div class="wrap tao-crm-wrap">

        <div class="tao-crm-topbar">
            <h1 class="tao-crm-title">
                &#x1F4CB; CRM &mdash; <?php echo esc_html( $ws['nome'] ); ?>
                <?php if ( $nao_lidos > 0 ) : ?>
                <span class="tao-crm-inbox-badge"><?php echo $nao_lidos; ?></span>
                <?php endif; ?>
            </h1>
            <div class="tao-crm-actions">
                <!-- v1.5.0: busca global com AJAX -->
                <div style="position:relative">
                    <input type="search" id="tao-crm-search" placeholder="&#x1F50D; Buscar card, contato, WhatsApp..." class="tao-crm-search-input" autocomplete="off">
                    <div id="crm-search-dropdown" style="display:none;position:absolute;top:calc(100% + 4px);left:0;min-width:360px;
                         background:#fff;border:1px solid #e2e8f0;border-radius:8px;box-shadow:0 8px 24px rgba(0,0,0,.12);z-index:9999;overflow:hidden"></div>
                </div>
                <button class="button" id="tao-crm-filter-toggle" title="Filtros">Filtrar</button>
                <a href="<?php echo esc_url( add_query_arg( 'view', 'inbox',  $base_url_pl ) ); ?>"
                   class="button <?php echo $view === 'inbox'  ? 'button-primary' : ''; ?>">
                    &#x1F4E5; Inbox<?php if ( $nao_lidos > 0 ) echo ' (' . $nao_lidos . ')'; ?>
                </a>
                <a href="<?php echo esc_url( add_query_arg( 'view', 'kanban', $base_url_pl ) ); ?>"
                   class="button <?php echo $view === 'kanban' ? 'button-primary' : ''; ?>">
                    &#x1F5C2; Kanban
                </a>
                <button class="button" id="tao-crm-new-card-btn">+ Novo Card</button>
                <?php if ( current_user_can( 'manage_options' ) ) : ?>
                <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?tao_crm_export=csv&workspace_id=' . $ws_id . '&pipeline_id=' . $pipeline_id ), 'tao_crm_export_csv' ) ); ?>"
                   class="button" title="Exportar cards para planilha CSV">&#x2193; CSV</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Barra de filtros (kanban e inbox) -->
        <div class="tao-crm-filter-bar" id="tao-crm-filter-bar" style="display:none">
            <div class="filter-bar-inner">
                <label class="filter-label">
                    Atendente
                    <select id="tao-crm-filter-atendente">
                        <option value="">Todos</option>
                        <?php foreach ( $wp_users_k as $uid => $uname ) : ?>
                        <option value="<?php echo esc_attr( $uid ); ?>"><?php echo esc_html( $uname ); ?></option>
                        <?php endforeach; ?>
                        <option value="0">— Sem responsável</option>
                    </select>
                </label>
                <label class="filter-label">
                    Fase
                    <select id="tao-crm-filter-fase">
                        <option value="">Todas</option>
                        <?php foreach ( $estagios as $e ) :
                            if ( in_array( $e['tipo'], [ 'ganho', 'perdido' ] ) ) continue;
                        ?>
                        <option value="<?php echo esc_attr( $e['id'] ); ?>"><?php echo esc_html( $e['nome'] ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="filter-label">
                    Status
                    <select id="tao-crm-filter-status">
                        <option value="">Todos</option>
                        <option value="handoff">🙋 Aguardando atendimento</option>
                        <option value="aberto">Abertos</option>
                        <option value="fechado">Fechados</option>
                    </select>
                </label>
                <?php if ( ! empty( $tags_list ) ) : ?>
                <label class="filter-label">
                    Tag
                    <select id="tao-crm-filter-tag">
                        <option value="">Todas as tags</option>
                        <?php foreach ( $tags_list as $tag ) : ?>
                        <option value="<?php echo esc_attr( $tag['id'] ); ?>">
                            <?php echo esc_html( $tag['nome'] ); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <?php endif; ?>
                <?php if ( $view === 'kanban' ) : ?>
                <label class="filter-label filter-toggle-closed">
                    <input type="checkbox" id="tao-crm-show-closed">
                    Mostrar colunas encerradas (ganhos/perdidos)
                </label>
                <?php endif; ?>
                <button class="button button-small" id="tao-crm-filter-clear">Limpar filtros</button>
            </div>
        </div>

        <?php if ( count( $pipelines ) > 1 ) : ?>
        <div class="tao-crm-pipeline-tabs">
            <?php foreach ( $pipelines as $p ) : ?>
            <a href="<?php echo esc_url( add_query_arg( [ 'pipeline_id' => $p['id'], 'view' => $view ], $base_url ) ); ?>"
               class="tao-crm-tab <?php echo $p['id'] === $pipeline_id ? 'active' : ''; ?>">
                <?php echo esc_html( $p['nome'] ); ?>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ( $view === 'inbox' ) :
            // Ordenar por ultima_mensagem_em desc
            usort( $cards, fn( $a, $b ) =>
                strcmp( $b['ultima_mensagem_em'] ?? '', $a['ultima_mensagem_em'] ?? '' )
            );
        ?>

        <div class="tao-crm-inbox">
            <?php if ( empty( $cards ) ) : ?>
            <p style="color:#64748b;padding:20px">Nenhum card neste pipeline.</p>
            <?php else : foreach ( $cards as $card ) :
                $msg_ts  = $card['ultima_mensagem_em'] ?? '';
                $lida_ts = $card['ultima_leitura_em']  ?? '';
                $tem_nao_lida = $msg_ts && ( ! $lida_ts || $msg_ts > $lida_ts );
                $estagio = $estagios_map[ $card['estagio_id'] ] ?? null;
                $card_url = tao_crm_url( [ 'action' => 'card', 'id' => $card['id'] ] );
            ?>
            <a href="<?php echo esc_url( $card_url ); ?>"
               class="tao-crm-inbox-row <?php echo $tem_nao_lida ? 'has-unread' : ''; ?>"
               data-responsavel-id="<?php echo esc_attr( intval( $card['responsavel_id'] ?? 0 ) ); ?>"
               data-estagio-id="<?php echo esc_attr( $card['estagio_id'] ?? '' ); ?>"
               data-handoff="<?php echo ! empty( $card['atendimento_humano'] ) ? '1' : '0'; ?>"
               data-fechado="<?php echo ! empty( $card['fechado'] ) ? '1' : '0'; ?>"
               data-search="<?php echo esc_attr( mb_strtolower( ( $card['titulo'] ?: $card['contato_nome'] ) . ' ' . $card['contato_whatsapp'] . ' ' . $card['contato_nome'] ) ); ?>">
                <div class="inbox-avatar"><?php echo mb_substr( $card['contato_nome'] ?? '?', 0, 1 ); ?></div>
                <div class="inbox-info">
                    <div class="inbox-name">
                        <?php echo esc_html( $card['titulo'] ?: $card['contato_nome'] ); ?>
                        <?php if ( $tem_nao_lida ) : ?><span class="inbox-unread-dot"></span><?php endif; ?>
                    </div>
                    <div class="inbox-meta">
                        <?php echo esc_html( tao_crm_format_phone( $card['contato_whatsapp'] ) ); ?>
                    </div>
                </div>
                <div class="inbox-right">
                    <?php if ( $estagio ) : ?>
                    <span class="inbox-stage" style="background:<?php echo esc_attr( $estagio['cor'] ?? '#6366f1' ); ?>">
                        <?php echo esc_html( $estagio['nome'] ); ?>
                    </span>
                    <?php endif; ?>
                    <span class="inbox-time">
                        <?php echo $msg_ts ? esc_html( tao_crm_brt( $msg_ts, 'd/m H:i' ) ) : '—'; ?>
                    </span>
                </div>
            </a>
            <?php endforeach; endif; ?>
        </div>

        <?php else : // Kanban view

        // ── Queries em batch para tags e lembretes ────────────────────────────
        $card_ids = array_column( $cards, 'id' );

        // Tags dos cards (batch)
        $cards_tags_map = [];  // card_id => [ ['id'=>…,'nome'=>…,'cor'=>…], … ]
        if ( ! empty( $card_ids ) ) {
            $rct_batch = tao_crm_api( "/crm_cards_tags?card_id=in.(" . implode( ',', $card_ids ) . ")&select=card_id,crm_tags(id,nome,cor)" );
            foreach ( ( $rct_batch['ok'] ? ( $rct_batch['data'] ?? [] ) : [] ) as $row ) {
                $cid = $row['card_id'];
                if ( ! empty( $row['crm_tags'] ) ) {
                    $cards_tags_map[ $cid ][] = $row['crm_tags'];
                }
            }
        }

        // Lembretes pendentes (batch)
        $cards_lembretes = [];  // card_id => true
        if ( ! empty( $card_ids ) ) {
            $ids_str = implode( ',', $card_ids );
            $rl_batch = tao_crm_api( "/crm_lembretes?card_id=in.($ids_str)&completado=eq.false&select=card_id,data_hora" );
            foreach ( ( $rl_batch['ok'] ? ( $rl_batch['data'] ?? [] ) : [] ) as $row ) {
                $cards_lembretes[ $row['card_id'] ] = true;
            }
        }
        ?>

        <div class="tao-crm-board-wrap">
        <button class="tao-scroll-btn tao-scroll-left"  id="tao-scroll-left">&#10094;</button>
        <div class="tao-crm-board" id="tao-crm-board">
            <?php foreach ( $estagios as $estagio ) :
                $stage_cards = $cards_by_stage[ $estagio['id'] ] ?? [];
                $cor = tao_crm_stage_color( $estagio['cor'] );
            ?>
            <div class="tao-crm-column <?php echo in_array( $estagio['tipo'], [ 'ganho', 'perdido' ] ) ? 'column-closed' : ''; ?>"
                 data-stage-id="<?php echo esc_attr( $estagio['id'] ); ?>"
                 data-tipo="<?php echo esc_attr( $estagio['tipo'] ?? 'normal' ); ?>"
                 style="<?php echo in_array( $estagio['tipo'], [ 'ganho', 'perdido' ] ) ? 'display:none' : ''; ?>">

                <div class="tao-crm-column-header" style="border-top-color:<?php echo $cor; ?>">
                    <span class="stage-name"><?php echo esc_html( $estagio['nome'] ); ?></span>
                    <span class="stage-count"><?php echo count( $stage_cards ); ?></span>
                </div>

                <div class="tao-crm-cards-list"
                     data-stage-id="<?php echo esc_attr( $estagio['id'] ); ?>">
                    <?php foreach ( $stage_cards as $card_idx => $card ) :
                        $msg_ts  = $card['ultima_mensagem_em'] ?? '';
                        $lida_ts = $card['ultima_leitura_em']  ?? '';
                        $tem_nao_lida = $msg_ts && ( ! $lida_ts || $msg_ts > $lida_ts );
                        $card_url = tao_crm_url( [ 'action' => 'card', 'id' => $card['id'] ] );
                        $card_hidden = $card_idx >= TAO_CRM_KANBAN_PAGE_SIZE;
                    ?>
                    <?php
                        $card_tag_ids_arr = array_column( $cards_tags_map[ $card['id'] ] ?? [], 'id' );
                        $card_tem_lembrete = ! empty( $cards_lembretes[ $card['id'] ] );
                    ?>
                    <div class="tao-crm-card <?php echo $tem_nao_lida ? 'has-unread' : ''; ?>"
                         <?php echo $card_hidden ? 'style="display:none" data-hidden="1"' : ''; ?>
                         draggable="true"
                         data-card-id="<?php echo esc_attr( $card['id'] ); ?>"
                         data-stage-id="<?php echo esc_attr( $card['estagio_id'] ); ?>"
                         data-responsavel-id="<?php echo esc_attr( intval( $card['responsavel_id'] ?? 0 ) ); ?>"
                         data-handoff="<?php echo ! empty( $card['atendimento_humano'] ) ? '1' : '0'; ?>"
                         data-fechado="<?php echo ! empty( $card['fechado'] ) ? '1' : '0'; ?>"
                         data-movido-em="<?php echo esc_attr( $card['movido_em'] ?? '' ); ?>"
                         data-tags="<?php echo esc_attr( wp_json_encode( $card_tag_ids_arr ) ); ?>"
                         data-tem-lembrete="<?php echo $card_tem_lembrete ? '1' : '0'; ?>"
                         data-search="<?php echo esc_attr( mb_strtolower( ( $card['titulo'] ?: $card['contato_nome'] ) . ' ' . $card['contato_whatsapp'] . ' ' . $card['contato_nome'] ) ); ?>"
                         onclick="window.location='<?php echo esc_url( $card_url ); ?>'">
                        <label class="crm-card-checkbox-wrap" onclick="event.stopPropagation()" title="Selecionar">
                            <input type="checkbox" class="crm-card-checkbox" data-card-id="<?php echo esc_attr( $card['id'] ); ?>">
                        </label>
                        <?php if ( $tem_nao_lida ) : ?>
                        <span class="card-unread-dot" title="Mensagem não lida"></span>
                        <?php endif; ?>
                        <?php if ( $card_tem_lembrete ) : ?>
                        <span class="card-lembrete-icon" title="Lembrete pendente">🔔</span>
                        <?php endif; ?>
                        <?php if ( ! empty( $card['atendimento_humano'] ) ) : ?>
                        <span class="card-handoff-icon" title="Em atendimento humano">🙋</span>
                        <?php endif; ?>
                        <div class="card-title"><?php echo esc_html( $card['titulo'] ?: $card['contato_nome'] ); ?></div>
                        <div class="card-meta">
                            <span class="card-phone">
                                <?php if ( function_exists( 'tao_crm_is_lid_num' ) && tao_crm_is_lid_num( $card['contato_whatsapp'] ) ) : ?>
                                &#x26A0; <?php echo esc_html( substr( $card['contato_whatsapp'], 0, 8 ) . '...' ); ?>
                                <?php else : ?>
                                &#x1F4F1; <?php echo esc_html( tao_crm_format_phone( $card['contato_whatsapp'] ) ); ?>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="card-footer">
                            <span class="card-date"><?php echo esc_html( tao_crm_brt( $card['movido_em'] ) ); ?></span>
                            <?php
                            $valor_op = floatval( $card['valor_oportunidade'] ?? 0 );
                            if ( $valor_op > 0 ) : ?>
                            <span class="crm-card-valor">R$ <?php echo number_format( $valor_op, 2, ',', '.' ); ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if ( ! empty( $cards_tags_map[ $card['id'] ] ) ) : ?>
                        <div class="card-tags">
                            <?php foreach ( $cards_tags_map[ $card['id'] ] as $ct ) :
                                $tc = esc_attr( $ct['cor'] ?? '#6366f1' );
                            ?>
                            <span class="crm-tag-pill"
                                  style="background:<?php echo $tc; ?>20;color:<?php echo $tc; ?>;border:1px solid <?php echo $tc; ?>40">
                                <?php echo esc_html( $ct['nome'] ); ?>
                            </span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>

                    <?php if ( count( $stage_cards ) > TAO_CRM_KANBAN_PAGE_SIZE ) : ?>
                    <button class="tao-crm-ver-mais"
                            data-stage-id="<?php echo esc_attr( $estagio['id'] ); ?>"
                            data-total="<?php echo esc_attr( count( $stage_cards ) ); ?>">
                        &#x25BC; Ver mais <?php echo count( $stage_cards ) - TAO_CRM_KANBAN_PAGE_SIZE; ?> cards
                    </button>
                    <?php endif; ?>
                </div>

            </div>
            <?php endforeach; ?>
        </div>
        <button class="tao-scroll-btn tao-scroll-right" id="tao-scroll-right">&#10095;</button>
        </div><!-- /.tao-crm-board-wrap -->

        <!-- Toolbar de ações em lote (aparece quando cards são selecionados) -->
        <div id="crm-bulk-toolbar" style="display:none;position:fixed;bottom:24px;left:50%;transform:translateX(-50%);
             background:#1e293b;color:#fff;border-radius:12px;padding:10px 20px;box-shadow:0 8px 32px rgba(0,0,0,.35);
             display:none;align-items:center;gap:12px;z-index:8888;min-width:420px;max-width:90vw">
            <span id="crm-bulk-count" style="font-size:13px;font-weight:600;white-space:nowrap">0 selecionados</span>
            <button class="button button-primary" id="crm-bulk-transferir" style="font-size:12px">&#x1F500; Transferir</button>
            <button class="button" id="crm-bulk-ganho" style="font-size:12px;background:#16a34a;color:#fff;border-color:#16a34a">&#x2705; Fechar (ganho)</button>
            <button class="button" id="crm-bulk-perdido" style="font-size:12px;background:#dc2626;color:#fff;border-color:#dc2626">&#x274C; Fechar (perdido)</button>
            <button class="button" id="crm-bulk-deselect" style="font-size:12px;background:transparent;color:#94a3b8;border-color:#475569">Cancelar</button>
        </div>

        <!-- Modal de transferência em lote -->
        <div id="crm-bulk-transfer-modal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.5);z-index:9990;align-items:center;justify-content:center">
            <div style="background:#fff;border-radius:12px;padding:24px;width:360px;max-width:90vw">
                <h3 style="margin:0 0 14px">Transferir em lote</h3>
                <label style="display:block;margin-bottom:14px;font-size:13px">Responsável
                    <select id="crm-bulk-transfer-user" style="width:100%;margin-top:4px">
                        <?php foreach ( get_users(['fields'=>['ID','display_name']]) as $u ) : ?>
                        <option value="<?php echo esc_attr($u->ID); ?>"><?php echo esc_html($u->display_name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <div style="display:flex;gap:8px">
                    <button class="button button-primary" id="crm-bulk-transfer-confirm">Transferir</button>
                    <button class="button" id="crm-bulk-transfer-cancel">Cancelar</button>
                    <span id="crm-bulk-transfer-status" style="font-size:12px;align-self:center"></span>
                </div>
            </div>
        </div>

        <?php endif; ?>

    </div>

    <!-- Modal: Novo Card -->
    <div id="tao-crm-modal-card" class="tao-crm-modal" style="display:none">
        <div class="tao-crm-modal-content">
            <div class="tao-crm-modal-header">
                <h2>Novo Card</h2>
                <button class="tao-crm-modal-close" onclick="taoCrmCloseModal()">✕</button>
            </div>
            <form id="tao-crm-new-card-form">
                <input type="hidden" name="workspace_id" value="<?php echo esc_attr( $ws_id ); ?>">
                <input type="hidden" name="pipeline_id"  value="<?php echo esc_attr( $pipeline_id ); ?>">
                <div class="tao-crm-field">
                    <label>Estágio inicial</label>
                    <select name="estagio_id" required>
                        <?php foreach ( $estagios as $e ) : ?>
                        <option value="<?php echo esc_attr( $e['id'] ); ?>"><?php echo esc_html( $e['nome'] ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="tao-crm-field">
                    <label>Nome do contato *</label>
                    <input type="text" name="contato_nome" required placeholder="Ex: João Silva">
                </div>
                <div class="tao-crm-field">
                    <label>WhatsApp *</label>
                    <input type="text" name="contato_whatsapp" required placeholder="Ex: 5511999999999">
                </div>
                <div class="tao-crm-field">
                    <label>Título do card</label>
                    <input type="text" name="titulo" placeholder="Deixe em branco para usar o nome">
                </div>
                <div class="tao-crm-modal-footer">
                    <button type="button" class="button" onclick="taoCrmCloseModal()">Cancelar</button>
                    <button type="submit" class="button button-primary" id="tao-crm-save-card-btn">Criar Card</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Campos na entrada da fase -->
    <div id="tao-crm-entrada-modal" class="tao-crm-modal" style="display:none">
        <div class="tao-crm-modal-content" style="max-width:480px">
            <div class="tao-crm-modal-header">
                <h2>&#x1F4CB; Campos obrigat&oacute;rios — entrada na fase</h2>
                <button class="tao-crm-modal-close" id="tao-crm-entrada-fechar">&#x2715;</button>
            </div>
            <form id="tao-crm-entrada-form">
                <div id="tao-crm-entrada-fields" style="padding:16px 20px;display:flex;flex-direction:column;gap:14px">
                    <!-- preenchido dinamicamente pelo JS -->
                </div>
                <div class="tao-crm-modal-footer">
                    <button type="button" class="button" id="tao-crm-entrada-cancelar">Cancelar (manter fase atual)</button>
                    <button type="submit" class="button button-primary" id="tao-crm-entrada-btn">Confirmar e Mover</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    var taoCrmWorkspaceId = <?php echo wp_json_encode( $ws_id ); ?>;
    var taoCrmPipelineId  = <?php echo wp_json_encode( $pipeline_id ); ?>;
    var taoCrmLoadedAt    = <?php echo wp_json_encode( gmdate( 'c' ) ); ?>;
    // SLA por estágio (minutos para alerta / crítico)
    var taoCrmSlaMinutos = <?php
        $sla_map = [];
        foreach ( $estagios as $e ) {
            if ( ! in_array( $e['tipo'], [ 'ganho', 'perdido' ] ) ) {
                $sla_m = tao_crm_sla_minutos_estagio( $e['id'] );
                $sla_map[ $e['id'] ] = [ 'alerta' => $sla_m, 'critico' => $sla_m * 2 ];
            }
        }
        echo wp_json_encode( $sla_map );
    ?>;
    </script>
    <?php
}

function tao_crm_format_phone( $num ) {
    $n = preg_replace( '/\D/', '', $num );
    if ( strlen( $n ) === 13 ) return '+' . substr( $n, 0, 2 ) . ' (' . substr( $n, 2, 2 ) . ') ' . substr( $n, 4, 5 ) . '-' . substr( $n, 9 );
    if ( strlen( $n ) === 12 ) return '+' . substr( $n, 0, 2 ) . ' (' . substr( $n, 2, 2 ) . ') ' . substr( $n, 4, 4 ) . '-' . substr( $n, 8 );
    return $num;
}
