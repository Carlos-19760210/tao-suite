<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function cbpm_page_campanhas() {
    if ( ! cbpm_can_access() ) return;

    $is_admin   = current_user_can( 'manage_options' );
    $cliente_id = cbpm_current_cliente_id();
    $action     = sanitize_key( $_GET['action'] ?? 'list' );
    $id         = sanitize_text_field( $_GET['id'] ?? '' );

    // ── STATUS (disparar / pausar / retomar) ─────────────────────────────────
    if ( $action === 'status' && $id && wp_verify_nonce( sanitize_text_field( $_REQUEST['_wpnonce'] ?? '' ), 'cbpm_status_' . $id ) ) {
        $novo = sanitize_key( $_POST['novo_status'] ?? '' );
        if ( in_array( $novo, [ 'em_andamento', 'pausada', 'concluida' ] ) ) {
            $filter = $is_admin ? '' : '&cliente_id=eq.' . urlencode( $cliente_id );
            $data   = [ 'status' => $novo ];
            if ( $novo === 'em_andamento' ) $data['iniciado_em']  = gmdate( 'c' );
            if ( $novo === 'concluida'    ) $data['concluido_em'] = gmdate( 'c' );
            if ( $novo === 'concluida'    ) cbpm_salvar_historico( $id, 'concluido' );
            $r = cbpm_api( '/campanhas?id=eq.' . urlencode( $id ) . $filter, 'PATCH', $data );
            if ( $r['ok'] && $novo === 'em_andamento' ) {
                cbpm_trigger_n8n( $id );
            }
        }
        cbpm_redirect( 'chatbot-platform-campanhas', [ 'action' => 'view', 'id' => $id ] );
    }

    // ── DELETE ───────────────────────────────────────────────────────────────
    if ( $action === 'delete' && $id ) {
        if ( ! wp_verify_nonce( sanitize_text_field( $_REQUEST['_wpnonce'] ?? '' ), 'cbpm_del_campanha_' . $id ) ) {
            cbpm_redirect( 'chatbot-platform-campanhas', [ 'cbpm_err' => 'nonce' ] );
        }
        $filter = $is_admin ? '' : '&cliente_id=eq.' . urlencode( $cliente_id );
        cbpm_api( '/campanha_contatos?campanha_id=eq.' . urlencode( $id ), 'DELETE' );
        $rd = cbpm_api( '/campanhas?id=eq.' . urlencode( $id ) . $filter, 'DELETE' );
        if ( ! $rd['ok'] ) {
            cbpm_redirect( 'chatbot-platform-campanhas', [ 'cbpm_err' => 'api' ] );
        }
        cbpm_redirect( 'chatbot-platform-campanhas', [ 'cbpm_ok' => 'del' ] );
    }

    // ── REPROCESSAR (mesmo público, reinicia contadores / contatos) ──────────
    if ( $action === 'reprocessar' && $id && $_SERVER['REQUEST_METHOD'] === 'POST' ) {
        if ( ! wp_verify_nonce( sanitize_text_field( $_POST['cbpm_reprocessar_nonce'] ?? '' ), 'cbpm_reprocessar_' . $id ) ) {
            cbpm_redirect( 'chatbot-platform-campanhas', [ 'action' => 'view', 'id' => $id, 'cbpm_err' => 'nonce' ] );
        }
        $filter       = $is_admin ? '' : '&cliente_id=eq.' . urlencode( $cliente_id );
        $novo_lote    = max( 1, intval( $_POST['lote_max_dia'] ?? 0 ) );
        $reiniciar    = ! empty( $_POST['reiniciar'] );

        if ( $reiniciar ) {
            cbpm_salvar_historico( $id, 'reprocessar' );
            // Marca todos os contatos da campanha de volta para pendente
            cbpm_api(
                '/campanha_contatos?campanha_id=eq.' . urlencode( $id ),
                'PATCH',
                [ 'status' => 'pendente', 'enviado_em' => null, 'mensagem_enviada' => null ]
            );
        }

        $patch = [
            'enviados'    => 0,
            'status'      => 'em_andamento',
            'iniciado_em' => gmdate( 'c' ),
            'concluido_em'=> null,
        ];
        if ( $novo_lote > 0 ) $patch['lote_max_dia'] = $novo_lote;
        if ( $reiniciar )     $patch['falhas'] = 0;

        cbpm_api( '/campanhas?id=eq.' . urlencode( $id ) . $filter, 'PATCH', $patch );
        cbpm_trigger_n8n( $id );

        $tipo = $reiniciar ? 'reprocessado' : 'proximo_lote';
        cbpm_redirect( 'chatbot-platform-campanhas', [ 'action' => 'view', 'id' => $id, 'cbpm_ok' => $tipo ] );
    }

    // ── NOVO DISPARO (substitui o público) ───────────────────────────────────
    if ( $action === 'redisparar' && $id && $_SERVER['REQUEST_METHOD'] === 'POST' ) {
        if ( ! wp_verify_nonce( sanitize_text_field( $_POST['cbpm_redisparar_nonce'] ?? '' ), 'cbpm_redisparar_' . $id ) ) {
            cbpm_redirect( 'chatbot-platform-campanhas', [ 'action' => 'view', 'id' => $id, 'cbpm_err' => 'nonce' ] );
        }
        $filter = $is_admin ? '' : '&cliente_id=eq.' . urlencode( $cliente_id );
        $rc = cbpm_api( '/campanhas?id=eq.' . urlencode( $id ) . $filter );
        if ( ! $rc['ok'] || empty( $rc['data'] ) ) {
            cbpm_redirect( 'chatbot-platform-campanhas', [ 'cbpm_err' => 'api' ] );
        }
        $lista_id_sel = sanitize_text_field( $_POST['lista_id_sel'] ?? '' );
        $contatos     = [];
        if ( isset( $_FILES['csv_contatos'] ) && $_FILES['csv_contatos']['error'] === UPLOAD_ERR_OK ) {
            $contatos = cbpm_parse_csv( $_FILES['csv_contatos']['tmp_name'] );
        } elseif ( $lista_id_sel ) {
            $rl = cbpm_api( '/lista_contatos_itens?lista_id=eq.' . urlencode( $lista_id_sel ) . '&select=nome,whatsapp,tratamento,empresa' );
            if ( $rl['ok'] ) $contatos = $rl['data'];
        }
        if ( empty( $contatos ) ) {
            cbpm_redirect( 'chatbot-platform-campanhas', [ 'action' => 'redisparar', 'id' => $id, 'cbpm_err' => 'sem_contatos' ] );
        }

        cbpm_salvar_historico( $id, 'redisparar' );
        cbpm_api( '/campanha_contatos?campanha_id=eq.' . urlencode( $id ), 'DELETE' );
        $counts = cbpm_inserir_contatos( $id, $contatos );

        $novo_lote = intval( $_POST['lote_max_dia'] ?? 0 );
        $patch = [
            'enviados'       => 0,
            'falhas'         => 0,
            'status'         => 'rascunho',
            'total_contatos' => $counts['total'],
            'iniciado_em'    => null,
            'concluido_em'   => null,
        ];
        if ( $novo_lote > 0 ) $patch['lote_max_dia'] = $novo_lote;
        cbpm_api( '/campanhas?id=eq.' . urlencode( $id ) . $filter, 'PATCH', $patch );

        $lista_nome = sanitize_text_field( $_POST['salvar_lista_nome'] ?? '' );
        if ( $lista_nome ) {
            $cid = $is_admin ? ( $rc['data'][0]['cliente_id'] ?? $cliente_id ) : $cliente_id;
            $rl  = cbpm_api( '/listas_contatos', 'POST',
                [ 'cliente_id' => $cid, 'nome' => $lista_nome, 'total' => $counts['total'] ],
                [ 'Prefer' => 'return=representation' ] );
            if ( $rl['ok'] && ! empty( $rl['data'] ) ) {
                $new_lista_id = $rl['data'][0]['id'];
                $lista_rows   = array_map( function( $c ) use ( $new_lista_id ) {
                    return [
                        'lista_id'   => $new_lista_id,
                        'nome'       => $c['nome'] ?? '',
                        'whatsapp'   => $c['whatsapp'] ?? '',
                        'tratamento' => $c['tratamento'] ?? '',
                        'empresa'    => $c['empresa'] ?? '',
                    ];
                }, $contatos );
                cbpm_api( '/lista_contatos_itens', 'POST', $lista_rows );
            }
        }

        cbpm_redirect( 'chatbot-platform-campanhas', [ 'action' => 'view', 'id' => $id, 'cbpm_ok' => 'redisparado', 'dup' => $counts['duplicados'] ] );
    }

    // ── SAVE ────────────────────────────────────────────────────────────────
    $error = '';
    if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['cbpm_camp_nonce'] ) ) {
        if ( ! wp_verify_nonce( sanitize_text_field( $_POST['cbpm_camp_nonce'] ), 'cbpm_save_campanha' ) ) {
            $error = 'Nonce inválido.';
        } else {
            $cid = $is_admin
                ? sanitize_text_field( $_POST['cliente_id'] ?? '' )
                : $cliente_id;

            $variantes_raw = $_POST['variantes'] ?? [];
            $variantes = array_values( array_filter( array_map( 'sanitize_textarea_field', $variantes_raw ) ) );

            $inst_id_save = sanitize_text_field( $_POST['instancia_id'] ?? '' );
            $data = [
                'cliente_id'     => $cid,
                'nome'           => sanitize_text_field( $_POST['nome'] ?? '' ),
                'cabecalho'      => sanitize_textarea_field( $_POST['cabecalho'] ?? '' ),
                'variantes'      => $variantes,
                'intervalo_min'  => max( 10, intval( $_POST['intervalo_min'] ?? 45 ) ),
                'intervalo_max'  => max( 20, intval( $_POST['intervalo_max'] ?? 180 ) ),
                'horario_inicio' => sanitize_text_field( $_POST['horario_inicio'] ?? '08:00' ),
                'horario_fim'    => sanitize_text_field( $_POST['horario_fim'] ?? '20:00' ),
                'lote_max_dia'   => max( 1, intval( $_POST['lote_max_dia'] ?? 80 ) ),
                'instancia_id'   => $inst_id_save ?: null,
            ];

            $edit_id = sanitize_text_field( $_POST['edit_id'] ?? '' );
            if ( $edit_id ) {
                $r = cbpm_api( '/campanhas?id=eq.' . urlencode( $edit_id ), 'PATCH', $data );
            } else {
                $r = cbpm_api( '/campanhas', 'POST', $data, [ 'Prefer' => 'return=representation' ] );
                if ( $r['ok'] && ! empty( $r['data'] ) ) {
                    $edit_id = $r['data'][0]['id'] ?? '';
                }
            }

            if ( ! $r['ok'] ) {
                $error = 'Erro ao salvar: ' . $r['error'];
            } else {
                $lista_nome   = sanitize_text_field( $_POST['salvar_lista_nome'] ?? '' );
                $lista_id_sel = sanitize_text_field( $_POST['lista_id_sel'] ?? '' );
                $contatos     = [];

                if ( isset( $_FILES['csv_contatos'] ) && $_FILES['csv_contatos']['error'] === UPLOAD_ERR_OK ) {
                    $contatos = cbpm_parse_csv( $_FILES['csv_contatos']['tmp_name'] );
                    error_log( 'CBPM CSV: arquivo=' . $_FILES['csv_contatos']['name'] . ' size=' . $_FILES['csv_contatos']['size'] . ' contatos=' . count( $contatos ) );
                } elseif ( $lista_id_sel ) {
                    $rl = cbpm_api( '/lista_contatos_itens?lista_id=eq.' . urlencode( $lista_id_sel ) . '&select=nome,whatsapp,tratamento,empresa' );
                    if ( $rl['ok'] ) $contatos = $rl['data'];
                }

                if ( $contatos && $edit_id ) {
                    cbpm_api( '/campanha_contatos?campanha_id=eq.' . urlencode( $edit_id ), 'DELETE' );
                    $counts = cbpm_inserir_contatos( $edit_id, $contatos );
                    error_log( 'CBPM INSERT contatos: total=' . $counts['total'] . ' duplicados=' . $counts['duplicados'] );
                    cbpm_api( '/campanhas?id=eq.' . urlencode( $edit_id ), 'PATCH', [ 'total_contatos' => $counts['total'] ] );

                    if ( $lista_nome ) {
                        $rl = cbpm_api( '/listas_contatos', 'POST',
                            [ 'cliente_id' => $cid, 'nome' => $lista_nome, 'total' => $counts['total'] ],
                            [ 'Prefer' => 'return=representation' ] );
                        if ( $rl['ok'] && ! empty( $rl['data'] ) ) {
                            $new_lista_id = $rl['data'][0]['id'];
                            $lista_rows   = array_map( function( $c ) use ( $new_lista_id ) {
                                return [
                                    'lista_id'   => $new_lista_id,
                                    'nome'       => $c['nome'] ?? '',
                                    'whatsapp'   => $c['whatsapp'] ?? '',
                                    'tratamento' => $c['tratamento'] ?? '',
                                    'empresa'    => $c['empresa'] ?? '',
                                ];
                            }, $contatos );
                            cbpm_api( '/lista_contatos_itens', 'POST', $lista_rows );
                        }
                    }
                } elseif ( $edit_id ) {
                    error_log( 'CBPM: sem contatos. files=' . json_encode( array_keys( $_FILES ) ) . ' edit_id=' . $edit_id );
                }

                cbpm_redirect( 'chatbot-platform-campanhas', [ 'cbpm_ok' => '1' ] );
            }
        }
    }

    // ── FETCH CAMPANHA ───────────────────────────────────────────────────────
    $campanha = null;
    if ( in_array( $action, [ 'edit', 'view', 'redisparar', 'historico' ] ) && $id ) {
        $r = cbpm_api( '/campanhas?id=eq.' . urlencode( $id ) );
        if ( $r['ok'] && ! empty( $r['data'] ) ) $campanha = $r['data'][0];
    }

    // ── LISTA ────────────────────────────────────────────────────────────────
    $filtro_cliente = sanitize_text_field( $_GET['filtro_cliente'] ?? '' );
    $campanhas = [];
    if ( $action === 'list' ) {
        $cf = $is_admin
            ? ( $filtro_cliente ? '&cliente_id=eq.' . urlencode( $filtro_cliente ) : '' )
            : '&cliente_id=eq.' . urlencode( $cliente_id );
        $r = cbpm_api( '/campanhas?select=*,clientes(nome_negocio)&order=criado_em.desc' . $cf );
        if ( $r['ok'] ) $campanhas = $r['data'];
    }

    $listas_salvas = [];
    $cf2 = $is_admin ? '' : '&cliente_id=eq.' . urlencode( $cliente_id );
    $rl  = cbpm_api( '/listas_contatos?select=id,nome,total&order=criado_em.desc' . $cf2 );
    if ( $rl['ok'] ) $listas_salvas = $rl['data'];

    // Instâncias Evolution disponíveis (do módulo TAO CRM)
    $instancias_evo = [];
    if ( function_exists( 'tao_crm_api' ) ) {
        $ri_evo = tao_crm_api( '/crm_instancias?ativo=eq.true&select=id,nome,workspace_id,evolution_instancia&order=nome.asc' );
        if ( $ri_evo['ok'] ) $instancias_evo = $ri_evo['data'];
    }

    $negocios = [];
    if ( $is_admin ) {
        $rn = cbpm_api( '/clientes?select=id,nome_negocio&order=nome_negocio.asc' );
        if ( $rn['ok'] ) $negocios = $rn['data'];
    }

    if ( isset( $_GET['cbpm_ok'] ) ) {
        $ok_msgs = [
            'del'          => 'Campanha excluída com sucesso.',
            'proximo_lote' => 'Próximo lote iniciado — enviados zerado, workflow disparado.',
            'reprocessado' => 'Campanha reprocessada do zero com o mesmo público.',
            'redisparado'  => 'Novo público carregado. A campanha voltou para Rascunho — verifique e dispare.'
                             . ( intval( $_GET['dup'] ?? 0 ) > 0 ? ' (' . intval( $_GET['dup'] ) . ' número(s) duplicado(s) ignorado(s).)' : '' ),
        ];
        cbpm_notice( $ok_msgs[ sanitize_key( $_GET['cbpm_ok'] ) ] ?? 'Campanha salva com sucesso.', 'success' );
    }
    if ( isset( $_GET['cbpm_err'] ) ) {
        $err_msgs = [
            'nonce'        => 'Sessão expirada. Recarregue a página e tente novamente.',
            'api'          => 'Erro ao excluir no servidor. Tente novamente.',
            'sem_contatos' => 'Nenhum contato encontrado. Faça upload de um CSV ou selecione uma lista.',
        ];
        cbpm_notice( $err_msgs[ sanitize_key( $_GET['cbpm_err'] ) ] ?? 'Erro desconhecido.', 'error' );
    }
    if ( $error ) cbpm_notice( $error, 'error' );

    $status_labels = [
        'rascunho'     => '✏️ Rascunho',
        'em_andamento' => '▶️ Em andamento',
        'pausada'      => '⏸️ Pausada',
        'concluida'    => '✅ Concluída',
    ];
    $status_colors = [
        'rascunho'     => '#646970',
        'em_andamento' => '#00a32a',
        'pausada'      => '#996800',
        'concluida'    => '#2271b1',
    ];
    ?>
    <div class="wrap cbpm-wrap">

    <?php if ( $action === 'list' ): ?>
        <h1>Campanhas
            <a href="<?php echo esc_url( cbpm_page_url( 'chatbot-platform-campanhas', [ 'action' => 'new' ] ) ); ?>"
               class="page-title-action">+ Nova campanha</a>
        </h1>

        <?php if ( $is_admin ): ?>
        <div style="margin-bottom:16px;display:flex;gap:12px;align-items:center;flex-wrap:wrap">
            <form method="get" style="display:flex;gap:8px;align-items:center">
                <input type="hidden" name="page" value="chatbot-platform-campanhas">
                <select name="filtro_cliente" onchange="this.form.submit()" style="min-width:200px">
                    <option value="">— Todos os negócios —</option>
                    <?php foreach ( $negocios as $n ): ?>
                    <option value="<?php echo esc_attr( $n['id'] ); ?>"
                        <?php selected( $filtro_cliente, $n['id'] ); ?>>
                        <?php echo esc_html( $n['nome_negocio'] ); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
        <?php endif; ?>

        <?php if ( empty( $campanhas ) ): ?>
            <p style="color:#646970">Nenhuma campanha encontrada. <a href="<?php echo esc_url( cbpm_page_url( 'chatbot-platform-campanhas', [ 'action' => 'new' ] ) ); ?>">Criar agora.</a></p>
        <?php else: ?>
        <div class="cbpm-table-container" style="overflow-x:auto">
        <table class="wp-list-table widefat striped cbpm-table">
            <thead><tr>
                <?php if ( $is_admin ): ?><th>Negócio</th><?php endif; ?>
                <th>Nome</th><th>Status</th><th>Contatos</th><th>Enviados</th><th>Falhas</th><th>Criada</th><th>Ações</th>
            </tr></thead>
            <tbody>
            <?php foreach ( $campanhas as $c ): ?>
            <tr>
                <?php if ( $is_admin ): ?>
                <td><?php echo esc_html( $c['clientes']['nome_negocio'] ?? '-' ); ?></td>
                <?php endif; ?>
                <td><strong><?php echo esc_html( $c['nome'] ); ?></strong></td>
                <td><span style="color:<?php echo esc_attr( $status_colors[ $c['status'] ] ?? '#646970' ); ?>;font-weight:600">
                    <?php echo esc_html( $status_labels[ $c['status'] ] ?? $c['status'] ); ?>
                </span></td>
                <td><?php echo intval( $c['total_contatos'] ); ?></td>
                <td style="color:#00a32a;font-weight:600"><?php echo intval( $c['enviados'] ); ?></td>
                <td style="color:#d63638"><?php echo intval( $c['falhas'] ); ?></td>
                <td><?php echo esc_html( substr( $c['criado_em'] ?? '', 0, 10 ) ); ?></td>
                <td style="white-space:nowrap">
                    <?php if ( $c['status'] === 'rascunho' && intval( $c['total_contatos'] ) > 0 ): ?>
                    <form method="post" style="display:inline">
                        <?php wp_nonce_field( 'cbpm_status_' . $c['id'], '_wpnonce' ); ?>
                        <input type="hidden" name="novo_status" value="em_andamento">
                        <button type="submit" class="button button-primary button-small"
                            formaction="<?php echo esc_url( cbpm_page_url( 'chatbot-platform-campanhas', [ 'action' => 'status', 'id' => $c['id'] ] ) ); ?>"
                            onclick="return confirm('Disparar esta campanha agora?')">▶ Disparar</button>
                    </form>
                    &nbsp;
                    <?php elseif ( $c['status'] === 'pausada' ): ?>
                    <form method="post" style="display:inline">
                        <?php wp_nonce_field( 'cbpm_status_' . $c['id'], '_wpnonce' ); ?>
                        <input type="hidden" name="novo_status" value="em_andamento">
                        <button type="submit" class="button button-primary button-small"
                            formaction="<?php echo esc_url( cbpm_page_url( 'chatbot-platform-campanhas', [ 'action' => 'status', 'id' => $c['id'] ] ) ); ?>">▶ Retomar</button>
                    </form>
                    &nbsp;
                    <?php elseif ( $c['status'] === 'em_andamento' ): ?>
                    <form method="post" style="display:inline">
                        <?php wp_nonce_field( 'cbpm_status_' . $c['id'], '_wpnonce' ); ?>
                        <input type="hidden" name="novo_status" value="pausada">
                        <button type="submit" class="button button-secondary button-small"
                            formaction="<?php echo esc_url( cbpm_page_url( 'chatbot-platform-campanhas', [ 'action' => 'status', 'id' => $c['id'] ] ) ); ?>">⏸ Pausar</button>
                    </form>
                    &nbsp;
                    <?php endif; ?>
                    <a href="<?php echo esc_url( cbpm_page_url( 'chatbot-platform-campanhas', [ 'action' => 'view', 'id' => $c['id'] ] ) ); ?>">Acompanhar</a>
                    <?php if ( in_array( $c['status'], [ 'rascunho', 'pausada' ] ) ): ?>
                    &nbsp;|&nbsp;<a href="<?php echo esc_url( cbpm_page_url( 'chatbot-platform-campanhas', [ 'action' => 'edit', 'id' => $c['id'] ] ) ); ?>">Editar</a>
                    <?php endif; ?>
                    &nbsp;|&nbsp;<a href="<?php echo esc_url( wp_nonce_url( cbpm_page_url( 'chatbot-platform-campanhas', [ 'action' => 'delete', 'id' => $c['id'] ] ), 'cbpm_del_campanha_' . $c['id'] ) ); ?>"
                       onclick="return confirm('Excluir esta campanha e todos os contatos?')" style="color:#d63638">Excluir</a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>

    <?php elseif ( $action === 'view' && $campanha ): ?>
        <?php cbpm_campanha_view( $campanha, $is_admin, $status_labels, $status_colors, $listas_salvas ); ?>

    <?php elseif ( $action === 'historico' && $campanha ): ?>
        <?php
        $hist_id = sanitize_text_field( $_GET['hist_id'] ?? '' );
        $rh      = $hist_id ? cbpm_api( '/campanha_historico?id=eq.' . urlencode( $hist_id ) ) : [ 'ok' => false ];
        $hist    = ( $rh['ok'] && ! empty( $rh['data'] ) ) ? $rh['data'][0] : null;
        $ri      = $hist_id ? cbpm_api( '/campanha_historico_itens?historico_id=eq.' . urlencode( $hist_id )
            . '&order=enviado_em.asc.nullslast&limit=2000' ) : [ 'ok' => false, 'data' => [] ];
        $itens   = ( $ri['ok'] ) ? $ri['data'] : [];
        $tipo_labels = [ 'concluido' => '✅ Concluído', 'reprocessar' => '↺ Reprocessado', 'redisparar' => '↺ Novo Disparo' ];
        ?>
        <h1>Histórico — <?php echo esc_html( $campanha['nome'] ); ?></h1>
        <p><a href="<?php echo esc_url( cbpm_page_url( 'chatbot-platform-campanhas', [ 'action' => 'view', 'id' => $campanha['id'] ] ) ); ?>">&larr; Voltar ao acompanhamento</a></p>

        <?php if ( $hist ): ?>
        <div style="display:flex;gap:20px;flex-wrap:wrap;margin-bottom:20px;background:#fff;border:1px solid #dcdcde;border-radius:4px;padding:16px 20px">
            <div><span style="font-size:11px;color:#646970;text-transform:uppercase">Tipo</span><br>
                <strong><?php echo esc_html( $tipo_labels[ $hist['tipo'] ] ?? $hist['tipo'] ); ?></strong></div>
            <div><span style="font-size:11px;color:#646970;text-transform:uppercase">Início</span><br>
                <strong><?php echo esc_html( cbpm_brt( $hist['iniciado_em'],  'd/m/Y H:i' ) ); ?></strong></div>
            <div><span style="font-size:11px;color:#646970;text-transform:uppercase">Encerrado</span><br>
                <strong><?php echo esc_html( cbpm_brt( $hist['encerrado_em'], 'd/m/Y H:i' ) ); ?></strong></div>
            <div><span style="font-size:11px;color:#646970;text-transform:uppercase">Total</span><br>
                <strong><?php echo intval( $hist['total'] ); ?></strong></div>
            <div><span style="font-size:11px;color:#00a32a;text-transform:uppercase">Enviados</span><br>
                <strong style="color:#00a32a"><?php echo intval( $hist['enviados'] ); ?></strong></div>
            <div><span style="font-size:11px;color:#d63638;text-transform:uppercase">Falhas</span><br>
                <strong style="color:#d63638"><?php echo intval( $hist['falhas'] ); ?></strong></div>
            <div><span style="font-size:11px;color:#646970;text-transform:uppercase">Lote</span><br>
                <strong><?php echo intval( $hist['lote_max'] ); ?></strong></div>
        </div>
        <?php endif; ?>

        <div style="background:#fff;border:1px solid #dcdcde;border-radius:4px;padding:20px">
            <h3 style="margin:0 0 12px;font-size:13px;text-transform:uppercase;letter-spacing:.4px;color:#50575e">
                Contatos neste processamento (<?php echo count( $itens ); ?>)
            </h3>
            <?php if ( $itens ): ?>
            <div style="overflow-x:auto">
            <table class="wp-list-table widefat fixed striped" style="font-size:12px">
                <thead><tr>
                    <th>Nome</th><th>WhatsApp</th><th>Mensagem enviada</th><th>Status</th><th>Horário</th>
                </tr></thead>
                <tbody>
                <?php
                $st_cor   = [ 'enviado' => '#00a32a', 'falha' => '#d63638', 'pendente' => '#996800', 'duplicidade' => '#8c8f94' ];
                $st_label = [ 'enviado' => '✓ Enviado', 'falha' => '✗ Falha', 'pendente' => '⏳ Pendente', 'duplicidade' => '⊘ Duplicidade' ];
                foreach ( $itens as $it ): ?>
                <tr>
                    <td><?php echo esc_html( $it['nome'] ); ?></td>
                    <td><?php echo esc_html( $it['whatsapp'] ); ?></td>
                    <td style="max-width:300px;white-space:pre-wrap;word-break:break-word;font-size:11px"><?php echo esc_html( $it['mensagem_enviada'] ?? '—' ); ?></td>
                    <td style="color:<?php echo esc_attr( $st_cor[ $it['status'] ] ?? '#646970' ); ?>;font-weight:600">
                        <?php echo esc_html( $st_label[ $it['status'] ] ?? $it['status'] ); ?>
                    </td>
                    <td><?php echo esc_html( cbpm_brt( $it['enviado_em'] ) ); ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php else: ?>
            <p style="color:#646970">Nenhum contato registrado neste processamento.</p>
            <?php endif; ?>
        </div>

    <?php elseif ( $action === 'redisparar' && $campanha ): ?>
        <h1>↺ Novo Disparo — <?php echo esc_html( $campanha['nome'] ); ?></h1>
        <p><a href="<?php echo esc_url( cbpm_page_url( 'chatbot-platform-campanhas', [ 'action' => 'view', 'id' => $campanha['id'] ] ) ); ?>">&larr; Voltar</a></p>

        <div style="background:#fff3cd;border:1px solid #ffc107;border-radius:4px;padding:12px 16px;margin-bottom:20px;font-size:13px">
            <strong>⚠ Atenção:</strong> Os contatos anteriores serão <strong>substituídos</strong> pelo novo público.
            As configurações da campanha (mensagem, variantes, intervalos) serão mantidas.
            Números duplicados no CSV são identificados e marcados como <em>Duplicidade</em> — não serão enviados.
        </div>

        <form method="post" enctype="multipart/form-data" class="cbpm-form cbpm-fv">
            <?php wp_nonce_field( 'cbpm_redisparar_' . $campanha['id'], 'cbpm_redisparar_nonce' ); ?>

            <div class="cbpm-fg-section"><h3>Novo público</h3></div>

            <?php if ( $listas_salvas ): ?>
            <div class="cbpm-fg">
                <label>Usar lista salva</label>
                <select name="lista_id_sel">
                    <option value="">-- Nenhuma (faça upload abaixo) --</option>
                    <?php foreach ( $listas_salvas as $l ): ?>
                    <option value="<?php echo esc_attr( $l['id'] ); ?>">
                        <?php echo esc_html( $l['nome'] ); ?> (<?php echo intval( $l['total'] ); ?> contatos)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div class="cbpm-fg">
                <label>Upload CSV</label>
                <input type="file" name="csv_contatos" accept=".csv,.txt" style="width:auto">
                <p class="description">Colunas: <code>nome</code>, <code>whatsapp</code>, <code>tratamento</code>, <code>empresa</code>.<br>
                Números duplicados são detectados e marcados automaticamente.</p>
            </div>

            <div class="cbpm-fg">
                <label>Lote máximo por dia</label>
                <div class="cbpm-inline-fields">
                    <input type="number" name="lote_max_dia" min="1" max="500" style="width:80px"
                           value="<?php echo intval( $campanha['lote_max_dia'] ?? 80 ); ?>">
                    <span>envios/dia — deixe igual para manter o atual</span>
                </div>
            </div>

            <div class="cbpm-fg">
                <label>Salvar lista para reuso</label>
                <input type="text" name="salvar_lista_nome"
                       placeholder="Ex: Clientes Maio/26 — deixe vazio para não salvar">
            </div>

            <div style="margin-top:8px;padding-top:16px;border-top:1px solid #dcdcde">
                <input type="submit" class="button button-primary button-large"
                       value="↺ Substituir público e preparar novo disparo"
                       onclick="return confirm('Substituir todos os contatos anteriores pelo novo público?\n\nA campanha voltará para Rascunho.')">
                &nbsp;
                <a href="<?php echo esc_url( cbpm_page_url( 'chatbot-platform-campanhas', [ 'action' => 'view', 'id' => $campanha['id'] ] ) ); ?>"
                   class="button button-secondary">Cancelar</a>
            </div>
        </form>

    <?php else: // new / edit ?>
        <h1><?php echo $campanha ? 'Editar campanha' : 'Nova campanha'; ?></h1>
        <p><a href="<?php echo esc_url( cbpm_page_url( 'chatbot-platform-campanhas' ) ); ?>">&larr; Voltar</a></p>

        <form method="post" enctype="multipart/form-data" class="cbpm-form cbpm-fv">
            <?php wp_nonce_field( 'cbpm_save_campanha', 'cbpm_camp_nonce' ); ?>
            <input type="hidden" name="edit_id" value="<?php echo esc_attr( $campanha['id'] ?? '' ); ?>">

            <?php if ( $is_admin ): ?>
            <div class="cbpm-fg">
                <label>Negócio *</label>
                <select name="cliente_id" required>
                    <option value="">-- Selecione --</option>
                    <?php foreach ( $negocios as $n ): ?>
                    <option value="<?php echo esc_attr( $n['id'] ); ?>"
                        <?php selected( $campanha['cliente_id'] ?? '', $n['id'] ); ?>>
                        <?php echo esc_html( $n['nome_negocio'] ); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div class="cbpm-fg">
                <label>Nome da campanha *</label>
                <input type="text" name="nome" required
                       value="<?php echo esc_attr( $campanha['nome'] ?? '' ); ?>">
            </div>

            <div class="cbpm-fg-section"><h3>Mensagem</h3></div>

            <div class="cbpm-fg">
                <label>Cabeçalho fixo *</label>
                <textarea name="cabecalho" rows="4" required
                          placeholder="Bom dia, [tratamento] [nome]!&#10;A [negocio] tem uma novidade para você..."><?php echo esc_textarea( $campanha['cabecalho'] ?? '' ); ?></textarea>
                <p class="description">Variáveis: <code>[nome]</code> <code>[tratamento]</code> <code>[negocio]</code> <code>[empresa]</code></p>
            </div>

            <div class="cbpm-fg">
                <label>Variantes *</label>
                <p class="description" style="margin-bottom:12px">Mínimo 2. Selecionadas em sequência (round-robin) a cada envio.</p>
                <div id="cbpm_variantes">
                    <?php
                    $vars = $campanha['variantes'] ?? ['',''];
                    if ( is_string( $vars ) ) $vars = json_decode( $vars, true ) ?? ['',''];
                    if ( count( $vars ) < 2 ) $vars = array_pad( $vars, 2, '' );
                    foreach ( $vars as $vi => $vt ):
                    ?>
                    <div class="cbpm-variante">
                        <div class="cbpm-variante-header">
                            <span>Variante <?php echo $vi + 1; ?></span>
                            <?php if ( $vi >= 2 ): ?>
                            <button type="button" class="button button-secondary cbpm-rm-var">✕ Remover</button>
                            <?php endif; ?>
                        </div>
                        <textarea name="variantes[]" rows="3"
                                  placeholder="Texto da variante <?php echo $vi + 1; ?>..."><?php echo esc_textarea( $vt ); ?></textarea>
                    </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" id="cbpm_add_var" class="button button-secondary" style="margin-top:4px">+ Adicionar variante</button>
            </div>

            <div class="cbpm-fg-section"><h3>Parâmetros de envio</h3></div>

            <?php if ( $instancias_evo ): ?>
            <div class="cbpm-fg">
                <label>Instância WhatsApp</label>
                <select name="instancia_id">
                    <option value="">— Padrão do workspace —</option>
                    <?php foreach ( $instancias_evo as $inst ): ?>
                    <option value="<?php echo esc_attr( $inst['id'] ); ?>"
                        <?php selected( $campanha['instancia_id'] ?? '', $inst['id'] ); ?>>
                        <?php echo esc_html( $inst['nome'] . ' (' . $inst['evolution_instancia'] . ')' ); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <p class="description">Qual número WhatsApp realizará os disparos desta campanha.</p>
            </div>
            <?php endif; ?>

            <div class="cbpm-fg">
                <label>Intervalo entre envios</label>
                <div class="cbpm-inline-fields">
                    <span>Mínimo</span>
                    <input type="number" name="intervalo_min" min="10" max="3600" style="width:80px"
                           value="<?php echo intval( $campanha['intervalo_min'] ?? 45 ); ?>">
                    <span>s &nbsp;&nbsp; Máximo</span>
                    <input type="number" name="intervalo_max" min="20" max="7200" style="width:80px"
                           value="<?php echo intval( $campanha['intervalo_max'] ?? 180 ); ?>">
                    <span>s</span>
                </div>
                <p class="description">Um valor aleatório é sorteado dentro deste intervalo antes de cada envio.</p>
            </div>

            <div class="cbpm-fg">
                <label>Horário permitido (BRT)</label>
                <div class="cbpm-inline-fields">
                    <span>Das</span>
                    <input type="time" name="horario_inicio" style="width:110px"
                           value="<?php echo esc_attr( $campanha['horario_inicio'] ?? '08:00' ); ?>">
                    <span>às</span>
                    <input type="time" name="horario_fim" style="width:110px"
                           value="<?php echo esc_attr( $campanha['horario_fim'] ?? '20:00' ); ?>">
                </div>
                <p class="description">Envios pausam fora deste intervalo e retomam automaticamente.</p>
            </div>

            <div class="cbpm-fg">
                <label>Lote máximo por processamento</label>
                <div class="cbpm-inline-fields">
                    <input type="number" name="lote_max_dia" min="1" max="500" style="width:80px"
                           value="<?php echo intval( $campanha['lote_max_dia'] ?? 80 ); ?>">
                    <span>envios por processamento</span>
                </div>
                <p class="description">Ao atingir este limite, a campanha para. Use "Próximo Lote" para continuar enviando ao mesmo público em outro momento.</p>
            </div>

            <div class="cbpm-fg-section"><h3>Lista de contatos</h3></div>

            <?php if ( $listas_salvas ): ?>
            <div class="cbpm-fg">
                <label>Usar lista salva</label>
                <select name="lista_id_sel" id="cbpm_lista_sel">
                    <option value="">-- Nenhuma (faça upload abaixo) --</option>
                    <?php foreach ( $listas_salvas as $l ): ?>
                    <option value="<?php echo esc_attr( $l['id'] ); ?>"
                        <?php selected( $campanha['lista_id'] ?? '', $l['id'] ); ?>>
                        <?php echo esc_html( $l['nome'] ); ?> (<?php echo intval( $l['total'] ); ?> contatos)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php else: ?>
            <div class="cbpm-fg">
                <label>Usar lista salva</label>
                <span style="color:#646970;font-size:13px">Nenhuma lista salva ainda.</span>
            </div>
            <?php endif; ?>

            <div class="cbpm-fg">
                <label>Upload CSV</label>
                <input type="file" name="csv_contatos" accept=".csv,.txt" style="width:auto">
                <p class="description">Colunas: <code>nome</code>, <code>whatsapp</code>, <code>tratamento</code>, <code>empresa</code>.<br>
                Primeira linha pode ser cabeçalho — detectada automaticamente. Números duplicados são identificados automaticamente.</p>
            </div>

            <div class="cbpm-fg">
                <label>Salvar lista para reuso</label>
                <input type="text" name="salvar_lista_nome"
                       placeholder="Ex: Clientes Farmácia Abr/26 — deixe vazio para não salvar">
            </div>

            <div style="margin-top:8px;padding-top:16px;border-top:1px solid #dcdcde">
                <input type="submit" class="button button-primary button-large" value="Salvar campanha">
            </div>
        </form>

        <script>
        (function(){
            var varCount = <?php echo count( $campanha['variantes'] ?? ['',''] ); ?>;
            document.getElementById('cbpm_add_var').addEventListener('click', function(){
                varCount++;
                var div = document.createElement('div');
                div.className = 'cbpm-variante';
                div.innerHTML = '<div class="cbpm-variante-header"><span>Variante '+varCount+'</span>'
                    + '<button type="button" class="button button-secondary cbpm-rm-var">✕ Remover</button></div>'
                    + '<textarea name="variantes[]" rows="3" placeholder="Texto da variante '+varCount+'..."></textarea>';
                document.getElementById('cbpm_variantes').appendChild(div);
                div.querySelector('.cbpm-rm-var').addEventListener('click', function(){ div.remove(); });
            });
            document.querySelectorAll('.cbpm-rm-var').forEach(function(btn){
                btn.addEventListener('click', function(){ btn.closest('.cbpm-variante').remove(); });
            });
        })();
        </script>
    <?php endif; ?>
    </div>
    <?php
}

// ── Tela de acompanhamento ────────────────────────────────────────────────
function cbpm_campanha_view( $campanha, $is_admin, $status_labels, $status_colors, $listas_salvas = [] ) {
    $id     = $campanha['id'];
    $status = $campanha['status'];
    $total  = intval( $campanha['total_contatos'] );

    // Busca contatos (últimos 500)
    $rc = cbpm_api( '/campanha_contatos?campanha_id=eq.' . urlencode( $id )
        . '&select=nome,whatsapp,mensagem_enviada,status,enviado_em&order=enviado_em.asc.nullslast&limit=500' );
    $contatos = $rc['ok'] ? $rc['data'] : [];

    // Contagem real por status
    $cnt = [ 'enviado' => 0, 'falha' => 0, 'pendente' => 0, 'duplicidade' => 0 ];
    foreach ( $contatos as $c ) {
        $s = $c['status'] ?? 'pendente';
        if ( isset( $cnt[$s] ) ) $cnt[$s]++;
    }
    $enviados_r  = $cnt['enviado'];
    $falhas_r    = $cnt['falha'];
    $pendentes_r = $cnt['pendente'];
    $duplicados  = $cnt['duplicidade'];
    $pct = $total > 0 ? round( $enviados_r / $total * 100 ) : 0;

    // Dados dos gráficos por hora
    $por_hora_env  = [];
    $por_hora_fail = [];
    foreach ( $contatos as $c ) {
        if ( ! $c['enviado_em'] ) continue;
        $h = gmdate( 'H', strtotime( $c['enviado_em'] ) - 10800 ) . ':00'; // BRT
        if ( $c['status'] === 'enviado' )     { $por_hora_env[$h]  = ( $por_hora_env[$h]  ?? 0 ) + 1; }
        elseif ( $c['status'] === 'falha' )   { $por_hora_fail[$h] = ( $por_hora_fail[$h] ?? 0 ) + 1; }
    }
    $horas_keys   = array_unique( array_merge( array_keys( $por_hora_env ), array_keys( $por_hora_fail ) ) );
    sort( $horas_keys );
    $chart_labels = $horas_keys;
    $chart_env    = array_map( fn($h) => $por_hora_env[$h]  ?? 0, $horas_keys );
    $chart_fail   = array_map( fn($h) => $por_hora_fail[$h] ?? 0, $horas_keys );
    $chart_pend   = array_map( fn($h) => 0, $horas_keys );
    if ( $pendentes_r > 0 ) {
        $chart_labels[] = 'Pendentes';
        $chart_env[]    = 0;
        $chart_fail[]   = 0;
        $chart_pend[]   = $pendentes_r;
    }
    $tem_grafico = ! empty( $horas_keys ) || $pendentes_r > 0;
    ?>
    <h1>Acompanhamento — <?php echo esc_html( $campanha['nome'] ); ?></h1>
    <p><a href="<?php echo esc_url( cbpm_page_url( 'chatbot-platform-campanhas' ) ); ?>">&larr; Voltar</a></p>

    <!-- Status + controles -->
    <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:16px">
        <span style="font-size:14px;font-weight:600;color:<?php echo esc_attr( $status_colors[$status] ?? '#646970' ); ?>">
            <?php echo esc_html( $status_labels[$status] ?? $status ); ?>
        </span>

        <?php
        // Botões de controle de status
        $status_acoes = [];
        if ( $status === 'rascunho' && $total > 0 )  $status_acoes = [ 'em_andamento' => [ 'label' => '▶ Disparar campanha', 'class' => 'button-primary',   'confirm' => 'Disparar agora?' ] ];
        if ( $status === 'em_andamento' )             $status_acoes = [ 'pausada'      => [ 'label' => '⏸ Pausar',           'class' => 'button-secondary', 'confirm' => '' ] ];
        if ( $status === 'pausada' )                  $status_acoes = [ 'em_andamento' => [ 'label' => '▶ Retomar',          'class' => 'button-primary',   'confirm' => '' ] ];
        foreach ( $status_acoes as $novo => $acao ):
            $url = cbpm_page_url( 'chatbot-platform-campanhas', [ 'action' => 'status', 'id' => $id ] );
        ?>
        <form method="post" style="display:inline">
            <?php wp_nonce_field( 'cbpm_status_' . $id, '_wpnonce' ); ?>
            <input type="hidden" name="novo_status" value="<?php echo esc_attr( $novo ); ?>">
            <button type="submit" class="button <?php echo esc_attr( $acao['class'] ); ?>"
                formaction="<?php echo esc_url( $url ); ?>"
                <?php if ( $acao['confirm'] ): ?>onclick="return confirm('<?php echo esc_js( $acao['confirm'] ); ?>')"<?php endif; ?>>
                <?php echo esc_html( $acao['label'] ); ?>
            </button>
        </form>
        <?php endforeach; ?>

        <?php if ( $status === 'rascunho' && $total === 0 ): ?>
        <span style="font-size:12px;color:#996800">⚠ Adicione contatos antes de disparar</span>
        <?php endif; ?>

        <!-- Botões de reprocessamento -->
        <?php if ( in_array( $status, [ 'em_andamento', 'pausada', 'concluida' ] ) && $pendentes_r > 0 ): ?>
        <button type="button" class="button button-secondary" onclick="cbpmToggleReproc('proximo_lote')">
            ⏭ Próximo Lote (<?php echo $pendentes_r; ?> pendentes)
        </button>
        <?php endif; ?>

        <?php if ( in_array( $status, [ 'pausada', 'concluida' ] ) ): ?>
        <button type="button" class="button button-secondary" onclick="cbpmToggleReproc('reprocessar')">
            ↺ Reprocessar Mesma Lista
        </button>
        <?php endif; ?>

        <?php if ( in_array( $status, [ 'rascunho', 'pausada', 'concluida' ] ) ): ?>
        <a href="<?php echo esc_url( cbpm_page_url( 'chatbot-platform-campanhas', [ 'action' => 'redisparar', 'id' => $id ] ) ); ?>"
           class="button button-secondary">↺ Novo Disparo (outro público)</a>
        <?php endif; ?>

        <div style="margin-left:auto">
            <button type="button" class="button button-secondary" id="cbpm_export_xlsx">⬇ Exportar XLSX</button>
        </div>
    </div>

    <!-- Painel inline de reprocessamento -->
    <div id="cbpm_reproc_panel" style="display:none;background:#f6f7f7;border:1px solid #c3c4c7;border-radius:4px;padding:16px 20px;margin-bottom:16px">
        <form method="post" id="cbpm_reproc_form">
            <?php wp_nonce_field( 'cbpm_reprocessar_' . $id, 'cbpm_reprocessar_nonce' ); ?>

            <div id="cbpm_reproc_proximo" style="display:none">
                <strong>⏭ Próximo Lote</strong>
                <p style="font-size:13px;margin:8px 0">Zera o contador de enviados e dispara o workflow — o próximo lote de contatos <em>pendentes</em> será processado até o limite abaixo. Os já enviados não são retocados.</p>
                <input type="hidden" name="reiniciar" value="0">
            </div>

            <div id="cbpm_reproc_reprocessar" style="display:none">
                <strong>↺ Reprocessar Mesma Lista</strong>
                <p style="font-size:13px;margin:8px 0">Marca <strong>todos</strong> os contatos de volta para pendente, zera contadores e reinicia a campanha do zero com o mesmo público.</p>
                <input type="hidden" name="reiniciar" value="1">
            </div>

            <div style="display:flex;gap:16px;align-items:flex-end;flex-wrap:wrap;margin-top:12px">
                <div>
                    <label style="display:block;font-size:13px;font-weight:600;margin-bottom:4px">Lote para este processamento</label>
                    <div style="display:flex;align-items:center;gap:8px">
                        <input type="number" name="lote_max_dia" min="1" max="500" style="width:80px"
                               value="<?php echo intval( $campanha['lote_max_dia'] ?? 80 ); ?>">
                        <span style="font-size:13px;color:#646970">envios (atual: <?php echo intval( $campanha['lote_max_dia'] ?? 80 ); ?>)</span>
                    </div>
                </div>
                <div>
                    <button type="submit"
                            formaction="<?php echo esc_url( cbpm_page_url( 'chatbot-platform-campanhas', [ 'action' => 'reprocessar', 'id' => $id ] ) ); ?>"
                            class="button button-primary"
                            id="cbpm_reproc_btn">Confirmar</button>
                    <button type="button" class="button button-secondary" onclick="cbpmToggleReproc(null)">Cancelar</button>
                </div>
            </div>
        </form>
    </div>

    <!-- KPI cards -->
    <div style="display:grid;grid-template-columns:repeat(<?php echo $duplicados > 0 ? 5 : 4; ?>,1fr);gap:12px;margin-bottom:20px">
        <?php
        cbpm_kpi_card( $total,       'Total',       '#2271b1' );
        cbpm_kpi_card( $enviados_r,  'Enviados',    '#00a32a' );
        cbpm_kpi_card( $falhas_r,    'Falhas',      '#d63638' );
        cbpm_kpi_card( $pendentes_r, 'Pendentes',   '#996800' );
        if ( $duplicados > 0 ) cbpm_kpi_card( $duplicados, 'Duplicidade', '#8c8f94' );
        ?>
    </div>

    <!-- Barra de progresso -->
    <div style="background:#f0f0f1;border-radius:4px;height:8px;margin-bottom:20px;overflow:hidden">
        <div style="background:#00a32a;width:<?php echo $pct; ?>%;height:100%;transition:width .5s"></div>
    </div>

    <!-- Gráficos -->
    <?php if ( $tem_grafico || $total > 0 ): ?>
    <div style="display:grid;grid-template-columns:1fr 200px;gap:16px;align-items:start;margin-bottom:16px">
        <?php if ( $tem_grafico ): ?>
        <div style="background:#fff;border:1px solid #dcdcde;border-radius:4px;padding:20px">
            <h3 style="margin:0 0 12px;font-size:13px;text-transform:uppercase;letter-spacing:.4px;color:#50575e">Envios por hora</h3>
            <canvas id="cbpm_chart_bar" height="80"></canvas>
        </div>
        <?php else: ?>
        <div></div>
        <?php endif; ?>
        <div style="background:#fff;border:1px solid #dcdcde;border-radius:4px;padding:20px">
            <h3 style="margin:0 0 12px;font-size:13px;text-transform:uppercase;letter-spacing:.4px;color:#50575e">Distribuição</h3>
            <canvas id="cbpm_chart_pie"></canvas>
        </div>
    </div>
    <?php endif; ?>

    <!-- Tabela de contatos -->
    <div style="background:#fff;border:1px solid #dcdcde;border-radius:4px;padding:20px">
        <h3 style="margin:0 0 12px;font-size:13px;text-transform:uppercase;letter-spacing:.4px;color:#50575e">
            Contatos (<?php echo count( $contatos ); ?>)
        </h3>
        <?php if ( $contatos ): ?>
        <div style="overflow-x:auto">
        <table class="wp-list-table widefat fixed striped" id="cbpm_tabela_contatos" style="font-size:12px">
            <thead><tr>
                <th>Nome</th><th>WhatsApp</th><th>Mensagem enviada</th><th>Status</th><th>Horário</th>
            </tr></thead>
            <tbody>
            <?php foreach ( $contatos as $c ):
                $st_cor   = [ 'enviado' => '#00a32a', 'falha' => '#d63638', 'pendente' => '#996800', 'duplicidade' => '#8c8f94', 'em_envio' => '#2271b1' ];
                $st_label = [ 'enviado' => '✓ Enviado', 'falha' => '✗ Falha', 'pendente' => '⏳ Pendente', 'duplicidade' => '⊘ Duplicidade', 'em_envio' => '↑ Enviando' ];
            ?>
            <tr>
                <td><?php echo esc_html( $c['nome'] ); ?></td>
                <td><?php echo esc_html( $c['whatsapp'] ); ?></td>
                <td style="max-width:300px;white-space:pre-wrap;word-break:break-word;font-size:11px"><?php echo esc_html( $c['mensagem_enviada'] ?? '—' ); ?></td>
                <td style="color:<?php echo esc_attr( $st_cor[$c['status']] ?? '#646970' ); ?>;font-weight:600">
                    <?php echo esc_html( $st_label[$c['status']] ?? $c['status'] ); ?>
                </td>
                <td><?php echo esc_html( cbpm_brt( $c['enviado_em'] ) ); ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php else: ?>
        <p style="color:#646970">Nenhum contato carregado ainda.</p>
        <?php endif; ?>
    </div>

    <!-- Scripts -->
    <?php if ( $tem_grafico || $total > 0 ): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
    (function(){
        <?php if ( $tem_grafico ): ?>
        new Chart(document.getElementById('cbpm_chart_bar'), {
            type: 'bar',
            data: {
                labels: <?php echo wp_json_encode( $chart_labels ); ?>,
                datasets: [
                    { label: 'Enviados',  data: <?php echo wp_json_encode( $chart_env );  ?>, backgroundColor: 'rgba(0,163,42,0.75)',   borderColor: '#00a32a', borderWidth: 1 },
                    { label: 'Falhas',    data: <?php echo wp_json_encode( $chart_fail ); ?>, backgroundColor: 'rgba(214,54,56,0.75)', borderColor: '#d63638', borderWidth: 1 },
                    { label: 'Pendentes', data: <?php echo wp_json_encode( $chart_pend ); ?>, backgroundColor: 'rgba(153,104,0,0.65)', borderColor: '#996800', borderWidth: 1 }
                ]
            },
            options: {
                plugins: { legend: { display: true, position: 'top', labels: { font: { size: 11 } } } },
                scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true, ticks: { precision: 0, stepSize: 1 } } }
            }
        });
        <?php endif; ?>
        new Chart(document.getElementById('cbpm_chart_pie'), {
            type: 'doughnut',
            data: {
                labels: ['Enviados', 'Falhas', 'Pendentes', 'Duplicidade'],
                datasets: [{ data: [<?php echo "$enviados_r,$falhas_r,$pendentes_r,$duplicados"; ?>],
                    backgroundColor: ['rgba(0,163,42,0.8)','rgba(214,54,56,0.8)','rgba(153,104,0,0.8)','rgba(140,143,148,0.8)'],
                    borderColor: ['#00a32a','#d63638','#996800','#8c8f94'], borderWidth: 2 }]
            },
            options: { plugins: { legend: { position: 'bottom', labels: { font: { size: 11 }, padding: 8 } } }, cutout: '55%' }
        });
    })();
    </script>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    <script>
    (function(){
        function cbpmToggleReproc(tipo) {
            var panel = document.getElementById('cbpm_reproc_panel');
            var divP  = document.getElementById('cbpm_reproc_proximo');
            var divR  = document.getElementById('cbpm_reproc_reprocessar');
            var btn   = document.getElementById('cbpm_reproc_btn');
            if (!tipo || (panel.style.display !== 'none' && panel.dataset.tipo === tipo)) {
                panel.style.display = 'none';
                return;
            }
            divP.style.display = tipo === 'proximo_lote'  ? '' : 'none';
            divR.style.display = tipo === 'reprocessar'   ? '' : 'none';
            // Garante o campo reiniciar correto
            var inputs = document.querySelectorAll('#cbpm_reproc_form input[name="reiniciar"]');
            inputs.forEach(function(inp) { inp.disabled = (inp.value === (tipo === 'reprocessar' ? '0' : '1')); });
            btn.textContent = tipo === 'proximo_lote' ? '⏭ Iniciar próximo lote' : '↺ Reprocessar';
            panel.dataset.tipo = tipo;
            panel.style.display = '';
            panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
        window.cbpmToggleReproc = cbpmToggleReproc;

        document.getElementById('cbpm_export_xlsx') && document.getElementById('cbpm_export_xlsx').addEventListener('click', function(){
            var rows = [['Nome','WhatsApp','Mensagem Enviada','Status','Horário']];
            document.querySelectorAll('#cbpm_tabela_contatos tbody tr').forEach(function(tr){
                var cells = tr.querySelectorAll('td');
                rows.push([cells[0].textContent.trim(), cells[1].textContent.trim(),
                           cells[2].textContent.trim(), cells[3].textContent.trim(), cells[4].textContent.trim()]);
            });
            var wb = XLSX.utils.book_new();
            var ws = XLSX.utils.aoa_to_sheet(rows);
            XLSX.utils.book_append_sheet(wb, ws, 'Campanha');
            XLSX.writeFile(wb, 'campanha_<?php echo esc_js( sanitize_title( $campanha['nome'] ) ); ?>.xlsx');
        });

        <?php if ( $status === 'em_andamento' ): ?>
        setTimeout(function(){ location.href = location.pathname + location.search + (location.search ? '&' : '?') + '_nc=' + Date.now(); }, 10000);
        <?php endif; ?>
    })();
    </script>

    <?php
    // Histórico de processamentos (só exibe se a tabela existir)
    $rh = cbpm_api( '/campanha_historico?campanha_id=eq.' . urlencode( $id ) . '&order=encerrado_em.desc&limit=20' );
    if ( $rh['ok'] && ! empty( $rh['data'] ) ):
        $tipo_labels = [
            'concluido'   => '✅ Concluído',
            'reprocessar' => '↺ Reprocessado',
            'redisparar'  => '↺ Novo Disparo',
        ];
    ?>
    <div style="background:#fff;border:1px solid #dcdcde;border-radius:4px;padding:20px;margin-top:16px">
        <h3 style="margin:0 0 12px;font-size:13px;text-transform:uppercase;letter-spacing:.4px;color:#50575e">
            Histórico de processamentos
        </h3>
        <table class="wp-list-table widefat fixed striped" style="font-size:12px">
            <thead><tr>
                <th style="width:140px">Início</th>
                <th style="width:140px">Encerramento</th>
                <th style="width:120px">Tipo</th>
                <th style="width:55px">Total</th>
                <th style="width:65px;color:#00a32a">Enviados</th>
                <th style="width:55px;color:#d63638">Falhas</th>
                <th style="width:50px">Lote</th>
                <th></th>
            </tr></thead>
            <tbody>
            <?php foreach ( $rh['data'] as $h ): ?>
            <tr>
                <td><?php echo esc_html( cbpm_brt( $h['iniciado_em'] ) ); ?></td>
                <td><?php echo esc_html( cbpm_brt( $h['encerrado_em'] ) ); ?></td>
                <td><?php echo esc_html( $tipo_labels[ $h['tipo'] ] ?? $h['tipo'] ); ?></td>
                <td><?php echo intval( $h['total'] ); ?></td>
                <td style="color:#00a32a;font-weight:600"><?php echo intval( $h['enviados'] ); ?></td>
                <td style="color:#d63638"><?php echo intval( $h['falhas'] ); ?></td>
                <td><?php echo intval( $h['lote_max'] ); ?></td>
                <td>
                    <a href="<?php echo esc_url( cbpm_page_url( 'chatbot-platform-campanhas',
                        [ 'action' => 'historico', 'id' => $id, 'hist_id' => $h['id'] ] ) ); ?>">
                        Ver contatos
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    <?php
}

// ── Converte timestamp UTC → BRT (UTC-3) independente do fuso do servidor ─
function cbpm_brt( $utc_str, $format = 'd/m H:i' ) {
    if ( ! $utc_str ) return '—';
    return gmdate( $format, strtotime( $utc_str ) - 10800 );
}

// ── Salva snapshot completo do processamento (cabeçalho + itens) ─────────
function cbpm_salvar_historico( $campanha_id, $tipo, $obs = '' ) {
    // Dados atuais da campanha
    $rc = cbpm_api( '/campanhas?id=eq.' . urlencode( $campanha_id )
        . '&select=enviados,falhas,total_contatos,lote_max_dia,iniciado_em' );
    if ( ! $rc['ok'] || empty( $rc['data'] ) ) return;
    $c = $rc['data'][0];

    // Cria cabeçalho e recupera o ID gerado
    $rh = cbpm_api( '/campanha_historico', 'POST', [
        'campanha_id'  => $campanha_id,
        'tipo'         => $tipo,
        'iniciado_em'  => $c['iniciado_em'] ?? gmdate( 'c' ),
        'encerrado_em' => gmdate( 'c' ),
        'total'        => intval( $c['total_contatos'] ),
        'enviados'     => intval( $c['enviados'] ),
        'falhas'       => intval( $c['falhas'] ),
        'lote_max'     => intval( $c['lote_max_dia'] ),
        'obs'          => $obs,
    ], [ 'Prefer' => 'return=representation' ] );

    if ( ! $rh['ok'] || empty( $rh['data'] ) ) return;
    $historico_id = $rh['data'][0]['id'];

    // Copia todos os contatos deste processamento como itens do histórico
    $ri = cbpm_api( '/campanha_contatos?campanha_id=eq.' . urlencode( $campanha_id )
        . '&select=nome,whatsapp,status,mensagem_enviada,enviado_em&limit=2000' );
    if ( ! $ri['ok'] || empty( $ri['data'] ) ) return;

    $itens = array_map( function( $cont ) use ( $historico_id ) {
        return [
            'historico_id'    => $historico_id,
            'nome'            => $cont['nome'],
            'whatsapp'        => $cont['whatsapp'],
            'status'          => $cont['status'],
            'mensagem_enviada'=> $cont['mensagem_enviada'] ?? null,
            'enviado_em'      => $cont['enviado_em']      ?? null,
        ];
    }, $ri['data'] );

    cbpm_api( '/campanha_historico_itens', 'POST', $itens );
}

// ── Trigger N8N webhook ───────────────────────────────────────────────────
function cbpm_trigger_n8n( $campanha_id ) {
    $n8n = rtrim( get_option( 'cbpm_n8n_url', 'https://crowingbettafish-n8n.cloudfy.live' ), '/' );
    wp_remote_post( $n8n . '/webhook/campanha-disparo', [
        'headers'  => [ 'Content-Type' => 'application/json' ],
        'body'     => wp_json_encode( [ 'campanha_id' => $campanha_id ] ),
        'timeout'  => 5,
        'blocking' => false,
    ]);
}

// ── Inserção de contatos com deduplicação por whatsapp ────────────────────
function cbpm_inserir_contatos( $campanha_id, $contatos ) {
    $vistos   = [];
    $unicos   = [];
    $duplicas = [];

    foreach ( $contatos as $c ) {
        $num = preg_replace( '/\D/', '', $c['whatsapp'] ?? '' );
        if ( strlen( $num ) < 10 ) continue;
        $row = [
            'campanha_id' => $campanha_id,
            'nome'        => sanitize_text_field( $c['nome'] ?? '' ),
            'whatsapp'    => $num,
            'tratamento'  => sanitize_text_field( $c['tratamento'] ?? '' ),
            'empresa'     => sanitize_text_field( $c['empresa'] ?? '' ),
        ];
        if ( isset( $vistos[$num] ) ) {
            $row['status'] = 'duplicidade';
            $duplicas[]    = $row;
        } else {
            $vistos[$num]  = true;
            $row['status'] = 'pendente';
            $unicos[]      = $row;
        }
    }

    $todos = array_merge( $unicos, $duplicas );
    if ( $todos ) cbpm_api( '/campanha_contatos', 'POST', $todos );

    return [ 'total' => count( $todos ), 'unicos' => count( $unicos ), 'duplicados' => count( $duplicas ) ];
}

// ── Helpers ───────────────────────────────────────────────────────────────
function cbpm_parse_csv( $filepath ) {
    $contatos = [];
    $handle   = fopen( $filepath, 'r' );
    if ( ! $handle ) return [];

    $first = fgets( $handle );
    rewind( $handle );
    $delim = ( substr_count( $first, ';' ) >= substr_count( $first, ',' ) ) ? ';' : ',';

    $header  = null;
    $row_num = 0;
    while ( ( $row = fgetcsv( $handle, 0, $delim ) ) !== false ) {
        if ( ! $row || count( $row ) < 2 ) continue;
        $row_num++;

        if ( $row_num === 1 ) {
            $lower = array_map( 'strtolower', array_map( 'trim', $row ) );
            if ( in_array( 'nome', $lower ) || in_array( 'whatsapp', $lower ) || in_array( 'name', $lower ) ) {
                $header = $lower;
                continue;
            }
        }

        if ( $header ) {
            $i_nome  = array_search( 'nome', $header ) ?: array_search( 'name', $header ) ?: 0;
            $i_whats = array_search( 'whatsapp', $header ) ?: array_search( 'telefone', $header ) ?: array_search( 'phone', $header ) ?: 1;
            $i_trat  = array_search( 'tratamento', $header ) !== false ? array_search( 'tratamento', $header ) : null;
            $nome    = trim( $row[$i_nome]  ?? '' );
            $whats   = trim( $row[$i_whats] ?? '' );
            $trat    = $i_trat !== null ? trim( $row[$i_trat] ?? '' ) : '';
        } else {
            $nome  = trim( $row[0] ?? '' );
            $whats = trim( $row[1] ?? '' );
            $trat  = trim( $row[2] ?? '' );
        }

        if ( ! $nome || ! $whats ) continue;
        $whats = preg_replace( '/\D/', '', $whats );
        if ( strlen( $whats ) < 10 ) continue;

        $contatos[] = [ 'nome' => $nome, 'whatsapp' => $whats, 'tratamento' => $trat ];
    }
    fclose( $handle );
    return $contatos;
}
