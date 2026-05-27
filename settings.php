<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function tao_crm_page_settings() {
    if ( ! current_user_can( 'manage_options' ) ) return;

    $tab = sanitize_key( $_GET['tab'] ?? 'workspaces' );

    // ── Salvar opções globais de integração ───────────────────────────────────
    if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['tao_crm_integracao_nonce'] ) ) {
        if ( wp_verify_nonce( sanitize_text_field( $_POST['tao_crm_integracao_nonce'] ), 'tao_crm_save_integracao' ) ) {
            $n8n_url      = esc_url_raw( $_POST['n8n_url']       ?? '' );
            $dispatch_key = sanitize_text_field( $_POST['dispatch_key'] ?? '' );
            if ( $n8n_url )      update_option( 'tao_crm_n8n_url',      $n8n_url );
            if ( $dispatch_key ) update_option( 'tao_crm_dispatch_key', $dispatch_key );
            tao_crm_notice( 'Configurações de integração salvas.' );
            $tab = 'integracao';
        }
    }

    // ── Salvar instância (form POST) ──────────────────────────────────────────
    if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['tao_crm_inst_nonce'] ) ) {
        if ( ! wp_verify_nonce( sanitize_text_field( $_POST['tao_crm_inst_nonce'] ), 'tao_crm_save_instancia_form' ) ) {
            tao_crm_notice( 'Nonce inválido.', 'error' );
        } else {
            $inst_ws    = sanitize_text_field( $_POST['tao_crm_inst_ws_id'] ?? '' );
            $inst_eid   = sanitize_text_field( $_POST['tao_crm_inst_edit_id'] ?? '' );
            $inst_data  = [
                'workspace_id'        => $inst_ws,
                'nome'                => sanitize_text_field( $_POST['inst_nome'] ?? '' ),
                'evolution_url'       => esc_url_raw( $_POST['inst_evolution_url'] ?? '' ),
                'evolution_key'       => sanitize_text_field( $_POST['inst_evolution_key'] ?? '' ),
                'evolution_instancia' => sanitize_text_field( $_POST['inst_evolution_instancia'] ?? '' ),
                'ativo'               => true,
            ];
            if ( $inst_eid ) {
                $r = tao_crm_api( "/crm_instancias?id=eq.$inst_eid", 'PATCH', $inst_data );
                tao_crm_notice( $r['ok'] ? 'Instância atualizada.' : 'Erro: ' . $r['error'], $r['ok'] ? 'success' : 'error' );
            } else {
                $r = tao_crm_api( '/crm_instancias', 'POST', $inst_data );
                tao_crm_notice( $r['ok'] ? 'Instância adicionada.' : 'Erro: ' . $r['error'], $r['ok'] ? 'success' : 'error' );
            }
            $tab = 'workspaces';
            $_GET['edit_ws'] = $inst_ws;
        }
    }

    // ── Salvar workspace ──────────────────────────────────────────────────────
    if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['tao_crm_ws_nonce'] ) ) {
        if ( ! wp_verify_nonce( sanitize_text_field( $_POST['tao_crm_ws_nonce'] ), 'tao_crm_save_workspace' ) ) {
            tao_crm_notice( 'Nonce inválido.', 'error' );
        } else {
            $edit_id = sanitize_text_field( $_POST['edit_id'] ?? '' );
            $data = [
                'nome'                 => sanitize_text_field( $_POST['nome'] ?? '' ),
                'cliente_id'           => sanitize_text_field( $_POST['cliente_id'] ?? '' ) ?: null,
                'evolution_url'        => esc_url_raw( $_POST['evolution_url'] ?? '' ),
                'evolution_key'        => sanitize_text_field( $_POST['evolution_key'] ?? '' ),
                'evolution_instancia'  => sanitize_text_field( $_POST['evolution_instancia'] ?? '' ),
                'ativo'                => true,
            ];
            if ( $edit_id ) {
                $r = tao_crm_api( "/crm_workspaces?id=eq.$edit_id", 'PATCH', $data );
                tao_crm_notice( $r['ok'] ? 'Workspace atualizado.' : 'Erro: ' . $r['error'], $r['ok'] ? 'success' : 'error' );
            } else {
                $r = tao_crm_api( '/crm_workspaces', 'POST', $data, [ 'Prefer' => 'return=representation' ] );
                tao_crm_notice( $r['ok'] ? 'Workspace criado.' : 'Erro: ' . $r['error'], $r['ok'] ? 'success' : 'error' );
                if ( $r['ok'] ) $tab = 'workspaces';
            }
        }
    }

    // ── Salvar pipeline ───────────────────────────────────────────────────────
    if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['tao_crm_pipe_nonce'] ) ) {
        if ( ! wp_verify_nonce( sanitize_text_field( $_POST['tao_crm_pipe_nonce'] ), 'tao_crm_save_pipeline' ) ) {
            tao_crm_notice( 'Nonce inválido.', 'error' );
        } else {
            $ws_id_pipe = sanitize_text_field( $_POST['workspace_id'] ?? '' );
            $pipe_data  = [
                'workspace_id' => $ws_id_pipe,
                'nome'         => sanitize_text_field( $_POST['pipeline_nome'] ?? '' ),
                'ordem'        => intval( $_POST['pipeline_ordem'] ?? 0 ),
                'ativo'        => true,
            ];
            $pipe_edit = sanitize_text_field( $_POST['pipeline_edit_id'] ?? '' );
            if ( $pipe_edit ) {
                $r = tao_crm_api( "/crm_pipelines?id=eq.$pipe_edit", 'PATCH', $pipe_data );
            } else {
                $r = tao_crm_api( '/crm_pipelines', 'POST', $pipe_data );
            }
            tao_crm_notice( $r['ok'] ? 'Pipeline salvo.' : 'Erro: ' . $r['error'], $r['ok'] ? 'success' : 'error' );
        }
    }

    // ── Salvar estágios (batch) ───────────────────────────────────────────────
    if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['tao_crm_stages_nonce'] ) ) {
        if ( ! wp_verify_nonce( sanitize_text_field( $_POST['tao_crm_stages_nonce'] ), 'tao_crm_save_stages' ) ) {
            tao_crm_notice( 'Nonce inválido.', 'error' );
        } else {
            $pipeline_id_s = sanitize_text_field( $_POST['stages_pipeline_id'] ?? '' );
            $nomes   = $_POST['stage_nome']  ?? [];
            $cores   = $_POST['stage_cor']   ?? [];
            $tipos   = $_POST['stage_tipo']  ?? [];
            $ids     = $_POST['stage_id']    ?? [];

            $ok = true;
            foreach ( $nomes as $i => $nome ) {
                $nome = sanitize_text_field( $nome );
                if ( ! $nome ) continue;
                $stage_data = [
                    'pipeline_id' => $pipeline_id_s,
                    'nome'        => $nome,
                    'cor'         => sanitize_hex_color( $cores[ $i ] ?? '#6366f1' ) ?: '#6366f1',
                    'tipo'        => in_array( $tipos[ $i ] ?? '', [ 'normal', 'ganho', 'perdido', 'handoff' ] ) ? $tipos[ $i ] : 'normal',
                    'ordem'       => $i,
                ];
                $sid = sanitize_text_field( $ids[ $i ] ?? '' );
                if ( $sid ) {
                    $r = tao_crm_api( "/crm_estagios?id=eq.$sid", 'PATCH', $stage_data );
                } else {
                    $r = tao_crm_api( '/crm_estagios', 'POST', $stage_data );
                }
                if ( ! $r['ok'] ) $ok = false;
            }
            tao_crm_notice( $ok ? 'Estágios salvos.' : 'Alguns estágios falharam.', $ok ? 'success' : 'error' );
        }
    }

    $workspaces  = tao_crm_get_workspaces();
    $ws_id_sel   = sanitize_text_field( $_GET['workspace_id'] ?? ( $workspaces[0]['id'] ?? '' ) );

    ?>
    <div class="wrap tao-crm-wrap">
        <h1>⚙ CRM — Configurações</h1>

        <!-- Seletor de negócio: sempre visível -->
        <div class="tao-crm-ws-bar">
            <span class="ws-label">🏢 Negócio:</span>
            <?php foreach ( $workspaces as $w ) : ?>
            <a href="<?php echo esc_url( tao_crm_settings_url( ['tab' => $tab, 'workspace_id' => $w['id']] ) ); ?>"
               class="tao-crm-ws-btn <?php echo $w['id'] === $ws_id_sel ? 'active' : ''; ?>">
               <?php echo esc_html( $w['nome'] ); ?>
            </a>
            <?php endforeach; ?>
            <?php if ( $tab !== 'workspaces' ) : ?>
            <a href="<?php echo esc_url( tao_crm_settings_url( ['tab' => 'workspaces'] ) ); ?>" class="tao-crm-ws-manage">+ Novo negócio</a>
            <?php endif; ?>
        </div>

        <nav class="tao-crm-settings-tabs">
            <a href="<?php echo esc_url( tao_crm_settings_url( ['tab' => 'workspaces'] ) ); ?>"
               class="<?php echo $tab === 'workspaces' ? 'active' : ''; ?>">Workspaces</a>
            <a href="<?php echo esc_url( tao_crm_settings_url( ['tab' => 'pipelines', 'workspace_id' => $ws_id_sel] ) ); ?>"
               class="<?php echo $tab === 'pipelines' ? 'active' : ''; ?>">Pipelines & Estágios</a>
            <a href="<?php echo esc_url( tao_crm_settings_url( ['tab' => 'campos', 'workspace_id' => $ws_id_sel] ) ); ?>"
               class="<?php echo $tab === 'campos' ? 'active' : ''; ?>">📋 Campos Parametrizáveis</a>
            <a href="<?php echo esc_url( tao_crm_settings_url( ['tab' => 'automacoes', 'workspace_id' => $ws_id_sel] ) ); ?>"
               class="<?php echo $tab === 'automacoes' ? 'active' : ''; ?>">🤖 Automações</a>
            <a href="<?php echo esc_url( tao_crm_settings_url( ['tab' => 'integracao'] ) ); ?>"
               class="<?php echo $tab === 'integracao' ? 'active' : ''; ?>">🔗 Integração</a>
        </nav>

        <?php if ( $tab === 'workspaces' ) : ?>

        <div class="tao-crm-settings-section">
            <h2>Workspaces</h2>
            <p>Cada workspace corresponde a um cliente/negócio com sua própria instância Evolution API.</p>

            <table class="wp-list-table widefat fixed striped">
                <thead><tr>
                    <th>Nome</th><th>Instância Evolution</th><th>URL Evolution</th><th>Status</th><th></th>
                </tr></thead>
                <tbody>
                <?php if ( empty( $workspaces ) ) : ?>
                    <tr><td colspan="5">Nenhum workspace criado ainda.</td></tr>
                <?php else : foreach ( $workspaces as $w ) : ?>
                    <tr>
                        <td><?php echo esc_html( $w['nome'] ); ?></td>
                        <td><code><?php echo esc_html( $w['evolution_instancia'] ); ?></code></td>
                        <td><?php echo esc_html( $w['evolution_url'] ); ?></td>
                        <td><?php echo $w['ativo'] ? '✅ Ativo' : '⏸ Inativo'; ?></td>
                        <td>
                            <a href="<?php echo esc_url( tao_crm_settings_url( ['tab' => 'workspaces', 'edit_ws' => $w['id']] ) ); ?>"
                               class="button button-small">Editar</a>
                            <a href="<?php echo esc_url( tao_crm_settings_url( ['tab' => 'pipelines', 'workspace_id' => $w['id']] ) ); ?>"
                               class="button button-small">Pipelines</a>
                            <button class="button button-small tao-crm-del-workspace"
                                    data-ws-id="<?php echo esc_attr( $w['id'] ); ?>"
                                    data-nome="<?php echo esc_attr( $w['nome'] ); ?>"
                                    style="color:#dc2626;border-color:#dc2626">🗑 Excluir</button>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>

            <?php
            $edit_ws = null;
            $edit_ws_id = sanitize_text_field( $_GET['edit_ws'] ?? '' );
            if ( $edit_ws_id ) {
                foreach ( $workspaces as $w ) {
                    if ( $w['id'] === $edit_ws_id ) { $edit_ws = $w; break; }
                }
            }
            ?>

            <hr>
            <h3><?php echo $edit_ws ? 'Editar workspace' : 'Novo workspace'; ?></h3>
            <form method="post">
                <?php wp_nonce_field( 'tao_crm_save_workspace', 'tao_crm_ws_nonce' ); ?>
                <?php if ( $edit_ws ) : ?>
                    <input type="hidden" name="edit_id" value="<?php echo esc_attr( $edit_ws['id'] ); ?>">
                <?php endif; ?>

                <table class="form-table">
                    <tr>
                        <th>Nome do workspace *</th>
                        <td><input type="text" name="nome" class="regular-text" required
                                   value="<?php echo esc_attr( $edit_ws['nome'] ?? '' ); ?>"
                                   placeholder="Ex: Magis-TAO Farmácia"></td>
                    </tr>
                    <tr>
                        <th>Evolution API URL *</th>
                        <td><input type="url" name="evolution_url" class="regular-text" required
                                   value="<?php echo esc_attr( $edit_ws['evolution_url'] ?? '' ); ?>"
                                   placeholder="https://minha-evolution.dominio.com"></td>
                    </tr>
                    <tr>
                        <th>Evolution API Key *</th>
                        <td><input type="password" name="evolution_key" class="regular-text" required
                                   value="<?php echo esc_attr( $edit_ws['evolution_key'] ?? '' ); ?>"></td>
                    </tr>
                    <tr>
                        <th>Nome da instância *</th>
                        <td><input type="text" name="evolution_instancia" class="regular-text" required
                                   value="<?php echo esc_attr( $edit_ws['evolution_instancia'] ?? '' ); ?>"
                                   placeholder="Ex: magistao-principal"></td>
                    </tr>
                </table>
                <p><input type="submit" class="button button-primary"
                          value="<?php echo $edit_ws ? 'Atualizar workspace' : 'Criar workspace'; ?>"></p>
            </form>

            <?php if ( $edit_ws ) : // Instâncias — só mostra quando editando um workspace
                $ri_all = tao_crm_api( "/crm_instancias?workspace_id=eq.{$edit_ws['id']}&order=criado_em.asc" );
                $instancias = $ri_all['ok'] ? ( $ri_all['data'] ?? [] ) : [];
                $edit_inst = null;
                $edit_inst_id = sanitize_text_field( $_GET['edit_inst'] ?? '' );
                if ( $edit_inst_id ) {
                    foreach ( $instancias as $inst ) { if ( $inst['id'] === $edit_inst_id ) { $edit_inst = $inst; break; } }
                }
            ?>
            <hr>
            <h3>📱 Instâncias WhatsApp</h3>
            <table class="wp-list-table widefat fixed striped" style="margin-bottom:12px">
                <thead><tr><th>Nome</th><th>Instância Evolution</th><th>URL</th><th>Status</th><th style="width:130px"></th></tr></thead>
                <tbody>
                <?php if ( empty( $instancias ) ) : ?>
                    <tr><td colspan="5">Nenhuma instância cadastrada ainda.</td></tr>
                <?php else : foreach ( $instancias as $inst ) : ?>
                    <tr>
                        <td><?php echo esc_html( $inst['nome'] ); ?></td>
                        <td><code><?php echo esc_html( $inst['evolution_instancia'] ); ?></code></td>
                        <td><?php echo esc_html( $inst['evolution_url'] ); ?></td>
                        <td><?php echo $inst['ativo'] ? '✅ Ativa' : '⏸ Inativa'; ?></td>
                        <td>
                            <a href="<?php echo esc_url( tao_crm_settings_url( ['tab'=>'workspaces','edit_ws'=>$edit_ws['id'],'edit_inst'=>$inst['id']] ) ); ?>"
                               class="button button-small">Editar</a>
                            <button class="button button-small tao-crm-del-instancia"
                                    data-inst-id="<?php echo esc_attr( $inst['id'] ); ?>"
                                    data-nome="<?php echo esc_attr( $inst['nome'] ); ?>"
                                    style="color:#dc2626;border-color:#dc2626">🗑</button>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
            <h3><?php echo $edit_inst ? 'Editar instância' : 'Nova instância'; ?></h3>
            <form method="post">
                <?php wp_nonce_field( 'tao_crm_save_instancia_form', 'tao_crm_inst_nonce' ); ?>
                <input type="hidden" name="tao_crm_inst_ws_id" value="<?php echo esc_attr( $edit_ws['id'] ); ?>">
                <?php if ( $edit_inst ) : ?>
                    <input type="hidden" name="tao_crm_inst_edit_id" value="<?php echo esc_attr( $edit_inst['id'] ); ?>">
                <?php endif; ?>
                <table class="form-table">
                    <tr><th>Nome *</th>
                        <td><input type="text" name="inst_nome" class="regular-text" required
                                   value="<?php echo esc_attr( $edit_inst['nome'] ?? '' ); ?>"
                                   placeholder="Ex: Principal, SAC, Vendas"></td></tr>
                    <tr><th>Evolution URL *</th>
                        <td><input type="url" name="inst_evolution_url" class="regular-text" required
                                   value="<?php echo esc_attr( $edit_inst['evolution_url'] ?? '' ); ?>"></td></tr>
                    <tr><th>Evolution Key *</th>
                        <td><input type="password" name="inst_evolution_key" class="regular-text" required
                                   value="<?php echo esc_attr( $edit_inst['evolution_key'] ?? '' ); ?>"></td></tr>
                    <tr><th>Nome da instância *</th>
                        <td><input type="text" name="inst_evolution_instancia" class="regular-text" required
                                   value="<?php echo esc_attr( $edit_inst['evolution_instancia'] ?? '' ); ?>"
                                   placeholder="Ex: magistao-farmacia-2"></td></tr>
                </table>
                <p><input type="submit" class="button button-primary"
                          value="<?php echo $edit_inst ? 'Atualizar instância' : 'Adicionar instância'; ?>"></p>
            </form>
            <?php endif; // fim if $edit_ws instâncias ?>
        </div>

        <?php elseif ( $tab === 'pipelines' ) :

            $rp = tao_crm_api( "/crm_pipelines?workspace_id=eq.$ws_id_sel&order=ordem.asc" );
            $pipelines = $rp['ok'] ? ( $rp['data'] ?? [] ) : [];

            $pipe_id_sel = sanitize_text_field( $_GET['pipeline_id'] ?? ( $pipelines[0]['id'] ?? '' ) );
            $re = $pipe_id_sel ? tao_crm_api( "/crm_estagios?pipeline_id=eq.$pipe_id_sel&order=ordem.asc" ) : [ 'ok' => true, 'data' => [] ];
            $estagios = $re['ok'] ? ( $re['data'] ?? [] ) : [];

            // Template farmácia (se solicitado)
            if ( isset( $_GET['template'] ) && $_GET['template'] === 'farmacia' && $pipe_id_sel ) {
                check_admin_referer( 'tao_crm_template_farmacia' );
                $template_stages = [
                    [ 'nome' => 'Novo Lead',        'cor' => '#3b82f6', 'tipo' => 'normal', 'ordem' => 0 ],
                    [ 'nome' => 'Em Atendimento',   'cor' => '#f59e0b', 'tipo' => 'normal', 'ordem' => 1 ],
                    [ 'nome' => 'Proposta Enviada', 'cor' => '#8b5cf6', 'tipo' => 'normal', 'ordem' => 2 ],
                    [ 'nome' => 'Análise Técnica',  'cor' => '#06b6d4', 'tipo' => 'normal', 'ordem' => 3 ],
                    [ 'nome' => 'Retorno Agendado', 'cor' => '#f97316', 'tipo' => 'normal', 'ordem' => 4 ],
                    [ 'nome' => 'Negociação Final', 'cor' => '#ec4899', 'tipo' => 'normal', 'ordem' => 5 ],
                    [ 'nome' => 'Venda Concluída',  'cor' => '#10b981', 'tipo' => 'ganho',   'ordem' => 6 ],
                    [ 'nome' => 'Cancelado',        'cor' => '#ef4444', 'tipo' => 'perdido', 'ordem' => 7 ],
                ];
                foreach ( $template_stages as $ts ) {
                    tao_crm_api( '/crm_estagios', 'POST', array_merge( $ts, [ 'pipeline_id' => $pipe_id_sel ] ) );
                }
                tao_crm_notice( 'Template Farmácia de Manipulação aplicado.' );
                $re = tao_crm_api( "/crm_estagios?pipeline_id=eq.$pipe_id_sel&order=ordem.asc" );
                $estagios = $re['ok'] ? ( $re['data'] ?? [] ) : [];
            }
        ?>

        <div class="tao-crm-settings-section">
            <h2>Pipelines & Estágios</h2>

            <!-- Pipelines -->
            <?php
            $pos_vendas_opt = get_option( 'tao_crm_pos_vendas_pipeline_' . $ws_id_sel, '' );
            ?>
            <div style="margin-bottom:24px;">
                <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:10px; align-items:center;">
                    <?php foreach ( $pipelines as $p ) :
                        $is_pos = $pos_vendas_opt === $p['id'];
                    ?>
                    <div style="display:flex;align-items:center;gap:4px;">
                        <a href="<?php echo esc_url( tao_crm_settings_url( ['tab' => 'pipelines', 'workspace_id' => $ws_id_sel, 'pipeline_id' => $p['id']] ) ); ?>"
                           class="button <?php echo $p['id'] === $pipe_id_sel ? 'button-primary' : ''; ?>">
                            <?php echo esc_html( $p['nome'] ); ?>
                            <?php if ( $is_pos ) echo ' 🔄'; ?>
                        </a>
                        <button class="button button-small tao-crm-del-pipeline"
                                data-pl-id="<?php echo esc_attr( $p['id'] ); ?>"
                                data-nome="<?php echo esc_attr( $p['nome'] ); ?>"
                                title="Excluir pipeline"
                                style="color:#dc2626;border-color:#dc2626;padding:2px 6px">🗑</button>
                    </div>
                    <?php endforeach; ?>
                </div>
                <p style="font-size:12px;color:#64748b;margin:0">
                    🔄 = pipeline de Pós-vendas (cards ganhos são movidos para ele automaticamente).
                    <?php if ( $pipe_id_sel ) : ?>
                    <label style="margin-left:12px">
                        <input type="checkbox" id="tao-crm-pos-vendas-check"
                               data-ws-id="<?php echo esc_attr( $ws_id_sel ); ?>"
                               data-pl-id="<?php echo esc_attr( $pipe_id_sel ); ?>"
                               <?php checked( $pos_vendas_opt, $pipe_id_sel ); ?>>
                        Usar pipeline selecionado como Pós-vendas
                    </label>
                    <?php endif; ?>
                </p>
            </div>

            <!-- Novo pipeline -->
            <form method="post" style="margin-bottom:32px; padding:16px; background:#f9f9f9; border:1px solid #ddd; border-radius:4px;">
                <?php wp_nonce_field( 'tao_crm_save_pipeline', 'tao_crm_pipe_nonce' ); ?>
                <input type="hidden" name="workspace_id" value="<?php echo esc_attr( $ws_id_sel ); ?>">
                <strong>Novo pipeline: </strong>
                <input type="text" name="pipeline_nome" placeholder="Nome do pipeline" required style="width:200px">
                <input type="number" name="pipeline_ordem" value="<?php echo count( $pipelines ); ?>" style="width:60px">
                <input type="submit" class="button" value="Criar">
            </form>

            <?php if ( $pipe_id_sel ) : ?>

            <!-- Estágios -->
            <h3>Estágios do pipeline selecionado</h3>

            <?php if ( empty( $estagios ) ) : ?>
            <p>
                Nenhum estágio criado ainda.
                <a href="<?php echo esc_url( wp_nonce_url(
                    tao_crm_settings_url( ['tab' => 'pipelines', 'workspace_id' => $ws_id_sel, 'pipeline_id' => $pipe_id_sel, 'template' => 'farmacia'] ),
                    'tao_crm_template_farmacia'
                ) ); ?>" class="button button-secondary">✨ Aplicar template Farmácia de Manipulação</a>
            </p>
            <?php endif; ?>

            <form method="post">
                <?php wp_nonce_field( 'tao_crm_save_stages', 'tao_crm_stages_nonce' ); ?>
                <input type="hidden" name="stages_pipeline_id" value="<?php echo esc_attr( $pipe_id_sel ); ?>">

                <table class="wp-list-table widefat fixed" id="tao-crm-stages-table">
                    <thead><tr>
                        <th style="width:30px">#</th>
                        <th>Nome do estágio</th>
                        <th style="width:80px">Cor</th>
                        <th style="width:110px">Tipo</th>
                        <th style="width:60px"></th>
                    </tr></thead>
                    <tbody id="tao-crm-stages-body">
                    <?php foreach ( $estagios as $i => $e ) : ?>
                    <tr>
                        <td><?php echo $i + 1; ?></td>
                        <td>
                            <input type="hidden" name="stage_id[]"
                                   value="<?php echo esc_attr( $e['id'] ); ?>">
                            <input type="text" name="stage_nome[]"
                                   value="<?php echo esc_attr( $e['nome'] ); ?>"
                                   class="regular-text" required>
                        </td>
                        <td>
                            <input type="color" name="stage_cor[]"
                                   value="<?php echo esc_attr( $e['cor'] ?: '#6366f1' ); ?>">
                        </td>
                        <td>
                            <select name="stage_tipo[]">
                                <option value="normal"  <?php selected( $e['tipo'], 'normal' ); ?>>Normal</option>
                                <option value="handoff" <?php selected( $e['tipo'], 'handoff' ); ?>>🙋 Handoff</option>
                                <option value="ganho"   <?php selected( $e['tipo'], 'ganho' ); ?>>✅ Ganho</option>
                                <option value="perdido" <?php selected( $e['tipo'], 'perdido' ); ?>>✗ Perdido</option>
                            </select>
                        </td>
                        <td>
                            <button type="button" class="button button-small tao-crm-del-estagio"
                                    data-stage-id="<?php echo esc_attr( $e['id'] ); ?>"
                                    data-nome="<?php echo esc_attr( $e['nome'] ); ?>"
                                    style="color:#dc2626;border-color:#dc2626" title="Excluir estágio">🗑</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <!-- Linha em branco para novo estágio -->
                    <tr id="tao-crm-new-stage-row">
                        <td>—</td>
                        <td>
                            <input type="hidden" name="stage_id[]" value="">
                            <input type="text" name="stage_nome[]" placeholder="Novo estágio..." class="regular-text">
                        </td>
                        <td><input type="color" name="stage_cor[]" value="#6366f1"></td>
                        <td>
                            <select name="stage_tipo[]">
                                <option value="normal">Normal</option>
                                <option value="handoff">🙋 Handoff</option>
                                <option value="ganho">✅ Ganho</option>
                                <option value="perdido">✗ Perdido</option>
                            </select>
                        </td>
                    </tr>
                    </tbody>
                </table>
                <p>
                    <input type="submit" class="button button-primary" value="Salvar estágios">
                    <button type="button" class="button" onclick="taoCrmAddStageRow()">+ Adicionar estágio</button>
                </p>
            </form>

            <?php endif; ?>
        </div>

        <?php elseif ( $tab === 'campos' ) :

            $rp_all   = tao_crm_api( "/crm_pipelines?workspace_id=eq.$ws_id_sel&order=ordem.asc" );
            $pls_all  = $rp_all['ok'] ? ( $rp_all['data'] ?? [] ) : [];
            $pipe_c   = sanitize_text_field( $_GET['pipeline_id'] ?? ( $pls_all[0]['id'] ?? '' ) );

            $re_all   = $pipe_c ? tao_crm_api( "/crm_estagios?pipeline_id=eq.$pipe_c&order=ordem.asc" ) : [ 'ok' => true, 'data' => [] ];
            $ests_all = $re_all['ok'] ? ( $re_all['data'] ?? [] ) : [];

            $rc_all   = $pipe_c ? tao_crm_api( "/crm_campos_definicao?pipeline_id=eq.$pipe_c&order=criado_em.asc" ) : [ 'ok' => true, 'data' => [] ];
            $campos_all = $rc_all['ok'] ? ( $rc_all['data'] ?? [] ) : [];

            // Carregar assignments por campo
            $assignments = [];
            foreach ( $campos_all as $campo ) {
                $ra = tao_crm_api( "/crm_campos_estagio?campo_id=eq.{$campo['id']}" );
                foreach ( ( $ra['ok'] ? ( $ra['data'] ?? [] ) : [] ) as $a ) {
                    $assignments[ $campo['id'] ][ $a['estagio_id'] ] = $a;
                }
            }

            $edit_campo_id = sanitize_text_field( $_GET['edit_campo'] ?? '' );
            $edit_campo    = null;
            foreach ( $campos_all as $c ) { if ( $c['id'] === $edit_campo_id ) { $edit_campo = $c; break; } }

        ?>
        <div class="tao-crm-settings-section">
            <h2>Campos Parametrizáveis</h2>
            <p>Defina campos extras por estágio × pipeline × workspace. Campos obrigatórios bloqueiam o avanço de estágio se não preenchidos.</p>

            <div class="tao-crm-pipe-bar">
                <span class="pipe-label">Pipeline:</span>
                <?php foreach ( $pls_all as $p ) : ?>
                <a href="<?php echo esc_url( tao_crm_settings_url( ['tab' => 'campos', 'workspace_id' => $ws_id_sel, 'pipeline_id' => $p['id']] ) ); ?>"
                   class="button button-small <?php echo $p['id'] === $pipe_c ? 'button-primary' : ''; ?>">
                   <?php echo esc_html( $p['nome'] ); ?>
                </a>
                <?php endforeach; ?>
            </div>

            <div class="campos-layout">

                <!-- Lista de campos -->
                <div class="campos-list-panel">
                    <h3>Campos definidos (<?php echo count( $campos_all ); ?>)</h3>
                    <?php if ( empty( $campos_all ) ) : ?>
                    <p style="color:#64748b;font-size:13px">Nenhum campo criado ainda.</p>
                    <?php else : ?>
                    <table class="wp-list-table widefat fixed striped" style="font-size:13px">
                        <thead><tr><th>Nome</th><th style="width:100px">Tipo</th><th style="width:80px">Fases</th><th style="width:70px"></th></tr></thead>
                        <tbody>
                        <?php foreach ( $campos_all as $c ) :
                            $n_ests = count( $assignments[ $c['id'] ] ?? [] );
                            $n_req  = count( array_filter( $assignments[ $c['id'] ] ?? [], fn($a) => $a['obrigatorio'] ) );
                        ?>
                        <tr <?php echo $c['id'] === $edit_campo_id ? 'style="background:#eff6ff"' : ''; ?>>
                            <td><strong><?php echo esc_html( $c['nome'] ); ?></strong>
                                <br><small style="color:#94a3b8"><?php echo esc_html( $c['chave'] ); ?></small></td>
                            <td><code><?php echo esc_html( $c['tipo'] ); ?></code></td>
                            <td><?php echo $n_ests; ?> estágio<?php echo $n_ests !== 1 ? 's' : ''; ?>
                                <?php if ( $n_req ) echo "<br><small style='color:#dc2626'>$n_req obrigatório" . ( $n_req > 1 ? 's' : '' ) . "</small>"; ?>
                            </td>
                            <td>
                                <a href="<?php echo esc_url( tao_crm_settings_url( ['tab' => 'campos', 'workspace_id' => $ws_id_sel, 'pipeline_id' => $pipe_c, 'edit_campo' => $c['id']] ) ); ?>"
                                   class="button button-small">✏</a>
                                <button class="button button-small tao-crm-del-campo"
                                        data-campo-id="<?php echo esc_attr( $c['id'] ); ?>"
                                        data-nome="<?php echo esc_attr( $c['nome'] ); ?>">🗑</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>

                <!-- Formulário de edição / criação -->
                <div class="campos-form-panel">
                    <h3><?php echo $edit_campo ? 'Editar campo: ' . esc_html( $edit_campo['nome'] ) : 'Novo campo'; ?></h3>
                    <form id="tao-crm-campo-form">
                        <input type="hidden" id="cf-campo-id"    value="<?php echo esc_attr( $edit_campo['id']    ?? '' ); ?>">
                        <input type="hidden" id="cf-workspace-id" value="<?php echo esc_attr( $ws_id_sel ); ?>">
                        <input type="hidden" id="cf-pipeline-id"  value="<?php echo esc_attr( $pipe_c ); ?>">

                        <div class="tao-crm-field">
                            <label>Nome do campo *</label>
                            <input type="text" id="cf-nome" required
                                   value="<?php echo esc_attr( $edit_campo['nome'] ?? '' ); ?>"
                                   placeholder="Ex: Receituário, CNPJ, Valor do pedido">
                        </div>
                        <div class="tao-crm-field">
                            <label>Chave interna *</label>
                            <input type="text" id="cf-chave"
                                   value="<?php echo esc_attr( $edit_campo['chave'] ?? '' ); ?>"
                                   placeholder="Ex: receituario, cnpj, valor_pedido"
                                   pattern="[a-z0-9_]+" title="Apenas letras minúsculas, números e _">
                        </div>
                        <div class="tao-crm-field">
                            <label>Tipo</label>
                            <select id="cf-tipo">
                                <?php foreach ( [ 'text'=>'Texto curto','textarea'=>'Texto longo','number'=>'Número',
                                    'date'=>'Data','select'=>'Seleção','boolean'=>'Sim/Não','phone'=>'Telefone','email'=>'E-mail' ] as $v => $l ) : ?>
                                <option value="<?php echo $v; ?>" <?php selected( $edit_campo['tipo'] ?? 'text', $v ); ?>><?php echo $l; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="tao-crm-field" id="cf-opcoes-wrap" style="display:<?php echo ( $edit_campo['tipo'] ?? '' ) === 'select' ? 'flex' : 'none'; ?>">
                            <label>Opções (uma por linha)</label>
                            <textarea id="cf-opcoes" rows="4" placeholder="Opção A&#10;Opção B&#10;Opção C"><?php
                                $ops = $edit_campo['opcoes'] ?? [];
                                if ( is_string( $ops ) ) $ops = json_decode( $ops, true ) ?? [];
                                echo esc_textarea( implode( "\n", (array) $ops ) );
                            ?></textarea>
                        </div>

                        <?php if ( ! empty( $ests_all ) ) : ?>
                        <div class="tao-crm-field">
                            <label>Visibilidade por fase</label>
                            <small style="color:#64748b;margin-bottom:6px;display:block">Em quais fases do pipeline este campo aparece? Marque "Obrigatório" para bloquear avanço se vazio.</small>
                            <table style="width:100%;font-size:12px;border-collapse:collapse">
                                <thead><tr>
                                    <th style="text-align:left;padding:4px">Fase</th>
                                    <th style="width:50px;text-align:center">Mostrar</th>
                                    <th style="width:65px;text-align:center">Obrig.</th>
                                    <th style="width:140px;text-align:center">Ordem</th>
                                </tr></thead>
                                <tbody>
                                <?php foreach ( $ests_all as $est ) :
                                    $asn = $assignments[ $edit_campo['id'] ?? '' ][ $est['id'] ] ?? null;
                                    $on  = $asn !== null;
                                    $req = $asn['obrigatorio'] ?? false;
                                    $ord = $asn['ordem'] ?? 0;
                                ?>
                                <tr style="border-bottom:1px solid #f1f5f9">
                                    <td style="padding:4px">
                                        <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:<?php echo esc_attr( $est['cor'] ?? '#6366f1' ); ?>;margin-right:4px"></span>
                                        <?php echo esc_html( $est['nome'] ); ?>
                                    </td>
                                    <td style="text-align:center">
                                        <input type="checkbox" class="est-on" data-est="<?php echo esc_attr( $est['id'] ); ?>" <?php checked( $on ); ?>>
                                    </td>
                                    <td style="text-align:center">
                                        <input type="checkbox" class="est-req" data-est="<?php echo esc_attr( $est['id'] ); ?>" <?php checked( $req ); ?> <?php echo ! $on ? 'disabled' : ''; ?>>
                                    </td>
                                    <td style="text-align:center">
                                        <input type="number" class="est-ord" data-est="<?php echo esc_attr( $est['id'] ); ?>"
                                               value="<?php echo esc_attr( $ord ); ?>" min="0" style="width:110px;font-size:12px" <?php echo ! $on ? 'disabled' : ''; ?>>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>

                        <div style="padding:12px 20px;display:flex;gap:8px">
                            <button type="submit" class="button button-primary" id="cf-submit">
                                <?php echo $edit_campo ? 'Atualizar campo' : 'Criar campo'; ?>
                            </button>
                            <?php if ( $edit_campo ) : ?>
                            <a href="<?php echo esc_url( tao_crm_settings_url( ['tab' => 'campos', 'workspace_id' => $ws_id_sel, 'pipeline_id' => $pipe_c] ) ); ?>"
                               class="button">+ Novo campo</a>
                            <?php endif; ?>
                            <span id="cf-status" style="font-size:12px;align-self:center"></span>
                        </div>
                    </form>
                </div>

            </div><!-- .campos-layout -->
        </div>

        <?php elseif ( $tab === 'automacoes' ) :

            $rp_a  = tao_crm_api( "/crm_pipelines?workspace_id=eq.$ws_id_sel&order=ordem.asc" );
            $pls_a = $rp_a['ok'] ? ( $rp_a['data'] ?? [] ) : [];
            $pipe_a = sanitize_text_field( $_GET['pipeline_id'] ?? ( $pls_a[0]['id'] ?? '' ) );

            $re_a   = $pipe_a ? tao_crm_api( "/crm_estagios?pipeline_id=eq.$pipe_a&order=ordem.asc" ) : [ 'ok' => true, 'data' => [] ];
            $ests_a_raw = $re_a['ok'] ? ( $re_a['data'] ?? [] ) : [];
            // Deduplicar por nome (cada fase aparece uma só vez mesmo que o banco tenha IDs duplicados)
            $ests_a_seen = [];
            $ests_a = array_values( array_filter( $ests_a_raw, function( $e ) use ( &$ests_a_seen ) {
                $key = strtolower( trim( $e['nome'] ) );
                if ( isset( $ests_a_seen[ $key ] ) ) return false;
                return $ests_a_seen[ $key ] = true;
            } ) );

            $est_a  = sanitize_text_field( $_GET['estagio_id'] ?? ( $ests_a[0]['id'] ?? '' ) );

            $ra_all = $est_a ? tao_crm_api( "/crm_automacoes?estagio_id=eq.$est_a&order=ordem.asc" ) : [ 'ok' => true, 'data' => [] ];
            $autos  = $ra_all['ok'] ? ( $ra_all['data'] ?? [] ) : [];

            $edit_auto_id = sanitize_text_field( $_GET['edit_auto'] ?? '' );
            $edit_auto    = null;
            foreach ( $autos as $a ) { if ( $a['id'] === $edit_auto_id ) { $edit_auto = $a; break; } }

            $wp_users_a = get_users( [ 'fields' => [ 'ID', 'display_name' ] ] );
        ?>
        <div class="tao-crm-settings-section">
            <h2>🤖 Automações</h2>
            <p>Configure mensagens automáticas e regras de movimentação. Selecione o pipeline e depois a fase.</p>

            <div class="tao-crm-pipe-bar">
                <span class="pipe-label">Pipeline:</span>
                <?php foreach ( $pls_a as $p ) : ?>
                <a href="<?php echo esc_url( tao_crm_settings_url( ['tab' => 'automacoes', 'workspace_id' => $ws_id_sel, 'pipeline_id' => $p['id']] ) ); ?>"
                   class="button button-small <?php echo $p['id'] === $pipe_a ? 'button-primary' : ''; ?>">
                    <?php echo esc_html( $p['nome'] ); ?>
                </a>
                <?php endforeach; ?>
            </div>

            <?php if ( ! empty( $ests_a ) ) : ?>
            <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:20px">
                <?php foreach ( $ests_a as $est ) :
                    $is_sel  = $est['id'] === $est_a;
                    $cor_est = esc_attr( $est['cor'] ?? '#6366f1' );
                ?>
                <a href="<?php echo esc_url( tao_crm_settings_url( ['tab' => 'automacoes', 'workspace_id' => $ws_id_sel, 'pipeline_id' => $pipe_a, 'estagio_id' => $est['id']] ) ); ?>"
                   class="button"
                   style="<?php echo $is_sel ? "background:$cor_est;border-color:$cor_est;color:#fff" : ''; ?>">
                    <?php echo esc_html( $est['nome'] ); ?>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if ( ! $est_a ) : ?>
            <p style="color:#64748b">Selecione um estágio acima para ver e criar automações.</p>
            <?php else : ?>

            <div class="campos-layout">

                <!-- Lista de automações -->
                <div class="campos-list-panel">
                    <?php
                    $est_nome_a = '';
                    foreach ( $ests_a as $e ) { if ( $e['id'] === $est_a ) { $est_nome_a = $e['nome']; break; } }
                    ?>
                    <h3>Automações — <?php echo esc_html( $est_nome_a ); ?> (<?php echo count( $autos ); ?>)</h3>

                    <?php if ( empty( $autos ) ) : ?>
                    <p style="color:#64748b;font-size:13px">Nenhuma automação nesta fase.</p>
                    <?php else : ?>
                    <table class="wp-list-table widefat fixed striped" style="font-size:13px">
                        <thead><tr>
                            <th>Nome</th>
                            <th style="width:120px">Gatilho</th>
                            <th style="width:110px">Ação</th>
                            <th style="width:40px">⚡</th>
                            <th style="width:60px"></th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ( $autos as $a ) :
                            $tipo_labels = [
                                'entrar_fase'       => '▶ Entrar fase',
                                'sair_fase'         => '◀ Sair fase',
                                'tempo_na_fase'     => '⏱ ' . $a['delay_minutos'] . 'min',
                                'recebeu_mensagem'  => '💬 Receber msg',
                            ];
                            $acao_labels = [
                                'enviar_mensagem'      => '📤 Msg',
                                'mover_fase'           => '➡ Mover',
                                'atribuir_responsavel' => '👤 Atribuir',
                            ];
                        ?>
                        <tr <?php echo $a['id'] === $edit_auto_id ? 'style="background:#eff6ff"' : ''; ?>>
                            <td><strong><?php echo esc_html( $a['nome'] ); ?></strong></td>
                            <td><small><?php echo esc_html( $tipo_labels[ $a['tipo'] ] ?? $a['tipo'] ); ?></small></td>
                            <td><small><?php echo esc_html( $acao_labels[ $a['acao'] ] ?? $a['acao'] ); ?></small></td>
                            <td><?php echo $a['ativo'] ? '✅' : '⏸'; ?></td>
                            <td>
                                <a href="<?php echo esc_url( tao_crm_settings_url( ['tab' => 'automacoes', 'workspace_id' => $ws_id_sel, 'pipeline_id' => $pipe_a, 'estagio_id' => $est_a, 'edit_auto' => $a['id']] ) ); ?>"
                                   class="button button-small">✏</a>
                                <button class="button button-small tao-crm-del-automacao"
                                        data-auto-id="<?php echo esc_attr( $a['id'] ); ?>"
                                        data-nome="<?php echo esc_attr( $a['nome'] ); ?>">🗑</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>

                <!-- Formulário de edição / criação -->
                <div class="campos-form-panel">
                    <h3><?php echo $edit_auto ? 'Editar: ' . esc_html( $edit_auto['nome'] ) : 'Nova automação'; ?></h3>
                    <form id="tao-crm-auto-form">
                        <input type="hidden" id="af-auto-id"      value="<?php echo esc_attr( $edit_auto['id']        ?? '' ); ?>">
                        <input type="hidden" id="af-workspace-id"  value="<?php echo esc_attr( $ws_id_sel ); ?>">
                        <input type="hidden" id="af-pipeline-id"   value="<?php echo esc_attr( $pipe_a ); ?>">
                        <input type="hidden" id="af-estagio-id"    value="<?php echo esc_attr( $est_a ); ?>">

                        <div class="tao-crm-field">
                            <label>Nome da automação *</label>
                            <input type="text" id="af-nome" required
                                   value="<?php echo esc_attr( $edit_auto['nome'] ?? '' ); ?>"
                                   placeholder="Ex: Boas-vindas ao novo lead">
                        </div>

                        <div class="tao-crm-field">
                            <label>Gatilho *</label>
                            <select id="af-tipo">
                                <?php $tipo_sel = $edit_auto['tipo'] ?? 'entrar_fase'; ?>
                                <option value="entrar_fase"      <?php selected( $tipo_sel, 'entrar_fase' ); ?>>▶ Ao entrar na fase</option>
                                <option value="sair_fase"        <?php selected( $tipo_sel, 'sair_fase' ); ?>>◀ Ao sair da fase</option>
                                <option value="tempo_na_fase"    <?php selected( $tipo_sel, 'tempo_na_fase' ); ?>>⏱ Após X minutos na fase</option>
                                <option value="recebeu_mensagem" <?php selected( $tipo_sel, 'recebeu_mensagem' ); ?>>💬 Ao receber mensagem</option>
                            </select>
                        </div>

                        <div class="tao-crm-field" id="af-delay-wrap"
                             style="display:<?php echo ( $edit_auto['tipo'] ?? '' ) === 'tempo_na_fase' ? 'flex' : 'none'; ?>">
                            <label>Minutos de espera</label>
                            <input type="number" id="af-delay" min="1" style="max-width:100px"
                                   value="<?php echo esc_attr( $edit_auto['delay_minutos'] ?? 60 ); ?>">
                        </div>

                        <div class="tao-crm-field">
                            <label>Ação *</label>
                            <select id="af-acao">
                                <?php $acao_sel = $edit_auto['acao'] ?? 'enviar_mensagem'; ?>
                                <option value="enviar_mensagem"      <?php selected( $acao_sel, 'enviar_mensagem' ); ?>>📤 Enviar mensagem WhatsApp</option>
                                <option value="mover_fase"           <?php selected( $acao_sel, 'mover_fase' ); ?>>➡ Mover para outra fase</option>
                                <option value="atribuir_responsavel" <?php selected( $acao_sel, 'atribuir_responsavel' ); ?>>👤 Atribuir responsável</option>
                            </select>
                        </div>

                        <div class="tao-crm-field" id="af-msg-wrap"
                             style="display:<?php echo in_array( $edit_auto['acao'] ?? 'enviar_mensagem', [ 'enviar_mensagem', '' ] ) && ! $edit_auto ? 'flex' : ( ( $edit_auto['acao'] ?? '' ) === 'enviar_mensagem' ? 'flex' : 'none' ); ?>">
                            <label>Mensagem</label>
                            <textarea id="af-mensagem" rows="5"
                                      placeholder="Olá {nome}, bem-vindo! 😊"><?php echo esc_textarea( $edit_auto['mensagem'] ?? '' ); ?></textarea>
                            <small style="color:#64748b;margin-top:4px">
                                Variáveis: <code>{nome}</code> <code>{telefone}</code> <code>{titulo}</code> <code>{campo:chave}</code>
                            </small>
                        </div>

                        <div class="tao-crm-field" id="af-fase-wrap"
                             style="display:<?php echo ( $edit_auto['acao'] ?? '' ) === 'mover_fase' ? 'flex' : 'none'; ?>">
                            <label>Fase destino</label>
                            <select id="af-para-estagio">
                                <option value="">— Selecione —</option>
                                <?php foreach ( $ests_a as $e ) : ?>
                                <option value="<?php echo esc_attr( $e['id'] ); ?>"
                                    <?php selected( $edit_auto['para_estagio_id'] ?? '', $e['id'] ); ?>>
                                    <?php echo esc_html( $e['nome'] ); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="tao-crm-field" id="af-resp-wrap"
                             style="display:<?php echo ( $edit_auto['acao'] ?? '' ) === 'atribuir_responsavel' ? 'flex' : 'none'; ?>">
                            <label>Responsável</label>
                            <select id="af-responsavel">
                                <option value="">— Selecione —</option>
                                <?php foreach ( $wp_users_a as $u ) : ?>
                                <option value="<?php echo esc_attr( $u->ID ); ?>"
                                    <?php selected( intval( $edit_auto['responsavel_id'] ?? 0 ), $u->ID ); ?>>
                                    <?php echo esc_html( $u->display_name ); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="tao-crm-field">
                            <label>Ordem</label>
                            <input type="number" id="af-ordem" min="0" style="max-width:80px"
                                   value="<?php echo esc_attr( $edit_auto['ordem'] ?? 0 ); ?>">
                        </div>

                        <div class="tao-crm-field" style="flex-direction:row;align-items:center;gap:8px;padding-left:0">
                            <label style="font-weight:normal">
                                <input type="checkbox" id="af-ativo" <?php checked( $edit_auto['ativo'] ?? true ); ?>>
                                Automação ativa
                            </label>
                        </div>

                        <div style="padding:12px 20px;display:flex;gap:8px">
                            <button type="submit" class="button button-primary" id="af-submit">
                                <?php echo $edit_auto ? 'Atualizar automação' : 'Criar automação'; ?>
                            </button>
                            <?php if ( $edit_auto ) : ?>
                            <a href="<?php echo esc_url( tao_crm_settings_url( ['tab' => 'automacoes', 'workspace_id' => $ws_id_sel, 'pipeline_id' => $pipe_a, 'estagio_id' => $est_a] ) ); ?>"
                               class="button">+ Nova</a>
                            <?php endif; ?>
                            <span id="af-status" style="font-size:12px;align-self:center"></span>
                        </div>
                    </form>
                </div>

            </div><!-- .campos-layout -->
            <?php endif; ?>
        </div>

        <?php elseif ( $tab === 'integracao' ) : ?>

        <div class="tao-crm-settings-section">
            <h2>🔗 Integração — N8N & Dispatch</h2>
            <p>Configure as URLs e chaves usadas pelo Webhook de entrada (Evolution API → WordPress).</p>

            <form method="post">
                <?php wp_nonce_field( 'tao_crm_save_integracao', 'tao_crm_integracao_nonce' ); ?>
                <table class="form-table">
                    <tr>
                        <th>URL do N8N (Chatbot Genérico)</th>
                        <td>
                            <input type="url" name="n8n_url" class="large-text"
                                   value="<?php echo esc_attr( get_option( 'tao_crm_n8n_url', '' ) ); ?>"
                                   placeholder="https://meu-n8n.dominio.com/webhook/chatbot-generic">
                            <p class="description">URL do webhook N8N que recebe as mensagens do chatbot genérico v6.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Chave de Dispatch (X-Tao-Key)</th>
                        <td>
                            <input type="text" name="dispatch_key" class="regular-text"
                                   value="<?php echo esc_attr( get_option( 'tao_crm_dispatch_key', 'tao-crm-dispatch-2026' ) ); ?>"
                                   placeholder="tao-crm-dispatch-2026">
                            <p class="description">Chave enviada no header <code>X-Tao-Key</code> pelo Evolution API ao chamar o endpoint REST do CRM.</p>
                        </td>
                    </tr>
                </table>
                <p><input type="submit" class="button button-primary" value="Salvar configurações de integração"></p>
            </form>

            <hr style="margin:24px 0">
            <h3>Endpoint REST</h3>
            <p>Configure o Evolution API para enviar webhooks para:</p>
            <code style="display:block;padding:10px;background:#f1f5f9;border-radius:4px;font-size:13px">
                <?php echo esc_html( get_rest_url( null, 'tao-crm/v1/dispatch' ) ); ?>
            </code>
            <p style="margin-top:6px;font-size:12px;color:#64748b">
                Header obrigatório: <code>X-Tao-Key: <?php echo esc_html( get_option( 'tao_crm_dispatch_key', 'tao-crm-dispatch-2026' ) ); ?></code>
            </p>
        </div>

        <?php endif; ?>
    </div>

    <script>
    function taoCrmAddStageRow() {
        var row = document.getElementById('tao-crm-new-stage-row').cloneNode(true);
        row.removeAttribute('id');
        row.querySelectorAll('input[type=text]').forEach(function(i){ i.value=''; i.placeholder='Novo estágio...'; });
        document.getElementById('tao-crm-stages-body').appendChild(row);
    }

    (function($){
        // Deletar workspace
        $(document).on('click', '.tao-crm-del-workspace', function(){
            var wsId = $(this).data('ws-id');
            var nome = $(this).data('nome');
            if(!confirm('Excluir workspace "' + nome + '"? Esta ação desativará o workspace e pode interromper integrações ativas.')) return;
            var $btn = $(this).prop('disabled', true).text('Excluindo...');
            $.post(ajaxurl, { action:'tao_crm_delete_workspace', nonce:'<?php echo esc_js( wp_create_nonce( 'tao_crm_nonce' ) ); ?>', ws_id:wsId }, function(resp){
                if(resp.success){ $btn.closest('tr').fadeOut(400, function(){ location.reload(); }); }
                else { $btn.prop('disabled',false).text('🗑 Excluir'); alert('Erro: ' + (resp.data || 'Tente novamente')); }
            }, 'json');
        });

        // Deletar pipeline
        $(document).on('click', '.tao-crm-del-pipeline', function(){
            var plId = $(this).data('pl-id');
            var nome = $(this).data('nome');
            if(!confirm('Excluir pipeline "' + nome + '"? Todos os cards neste pipeline continuarão existindo, mas sem pipeline associado.')) return;
            var $btn = $(this).prop('disabled', true).text('...');
            $.post(ajaxurl, { action:'tao_crm_delete_pipeline', nonce:'<?php echo esc_js( wp_create_nonce( 'tao_crm_nonce' ) ); ?>', pl_id:plId }, function(resp){
                if(resp.success){ location.reload(); }
                else { $btn.prop('disabled',false).text('🗑'); alert('Erro: ' + (resp.data || 'Tente novamente')); }
            }, 'json');
        });

        // Deletar estágio
        $(document).on('click', '.tao-crm-del-estagio', function(){
            var stageId = $(this).data('stage-id');
            var nome    = $(this).data('nome');
            if(!confirm('Excluir estágio "' + nome + '"? Esta ação é irreversível.')) return;
            var $btn = $(this).prop('disabled', true).text('...');
            $.post(ajaxurl, { action:'tao_crm_delete_estagio', nonce:'<?php echo esc_js( wp_create_nonce( 'tao_crm_nonce' ) ); ?>', stage_id:stageId }, function(resp){
                if(resp.success){ $btn.closest('tr').fadeOut(400, function(){ $(this).remove(); }); }
                else { $btn.prop('disabled',false).text('🗑'); alert('Erro: ' + (resp.data || 'Tente novamente')); }
            }, 'json');
        });

        // Deletar instância
        $(document).on('click', '.tao-crm-del-instancia', function(){
            var instId = $(this).data('inst-id');
            var nome   = $(this).data('nome');
            if(!confirm('Excluir instância "' + nome + '"? Os cards existentes perderão a referência a esta instância.')) return;
            var $btn = $(this).prop('disabled', true).text('...');
            $.post(ajaxurl, { action:'tao_crm_delete_instancia', nonce:'<?php echo esc_js( wp_create_nonce( 'tao_crm_nonce' ) ); ?>', inst_id:instId }, function(resp){
                if(resp.success){ $btn.closest('tr').fadeOut(400, function(){ $(this).remove(); }); }
                else { $btn.prop('disabled',false).text('🗑'); alert('Erro: ' + (resp.data || 'Tente novamente')); }
            }, 'json');
        });

        // Toggle pós-vendas
        $('#tao-crm-pos-vendas-check').on('change', function(){
            var wsId = $(this).data('ws-id');
            var plId = $(this).is(':checked') ? $(this).data('pl-id') : '';
            $.post(ajaxurl, { action:'tao_crm_set_pos_vendas', nonce:'<?php echo esc_js( wp_create_nonce( 'tao_crm_nonce' ) ); ?>', ws_id:wsId, pl_id:plId }, function(resp){
                if(resp.success){ location.reload(); }
                else { alert('Erro ao salvar configuração.'); }
            }, 'json');
        });
    })(jQuery);
    </script>
    <?php
}
