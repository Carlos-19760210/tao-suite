<?php

if ( ! defined( 'ABSPATH' ) ) exit;



function tao_crm_page_settings() {

    if ( ! current_user_can( 'manage_options' ) ) return;



    $tab = sanitize_key( $_GET['tab'] ?? 'workspaces' );



    // ── Admin: alterar plano do workspace ────────────────────────────────────

    if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['tao_crm_set_plano_nonce'] ) ) {

        if ( current_user_can( 'manage_options' ) && wp_verify_nonce( sanitize_text_field( $_POST['tao_crm_set_plano_nonce'] ), 'tao_crm_set_plano' ) ) {

            $ws_plano         = sanitize_text_field( $_POST['workspace_id'] ?? '' );
            $novo_plano       = sanitize_key( $_POST['novo_plano'] ?? 'essencial' );
            $trial_ate        = sanitize_text_field( $_POST['trial_ate'] ?? '' );
            $lim_usuarios     = max( 0, intval( $_POST['limite_usuarios']     ?? 3 ) );
            $lim_instancias   = max( 0, intval( $_POST['limite_instancias']   ?? 1 ) );
            $lim_campanhas    = max( 0, intval( $_POST['limite_campanhas_mes'] ?? 0 ) );

            $plano_data = [
                'plano'               => $novo_plano,
                'trial_ate'           => $trial_ate ? $trial_ate . 'T00:00:00Z' : null,
                'limite_usuarios'     => $lim_usuarios,
                'limite_instancias'   => $lim_instancias,
                'limite_campanhas_mes'=> $lim_campanhas,
                'renovado_em'         => gmdate( 'c' ),
            ];

            // Upsert: atualiza se já existe, cria caso contrário
            $rcheck = tao_crm_api( "/crm_planos?workspace_id=eq.$ws_plano&ativo=eq.true&limit=1" );
            if ( $rcheck['ok'] && ! empty( $rcheck['data'] ) ) {
                $plano_id = $rcheck['data'][0]['id'];
                $rpl = tao_crm_api( "/crm_planos?id=eq.$plano_id", 'PATCH', $plano_data );
            } else {
                $plano_data['workspace_id'] = $ws_plano;
                $rpl = tao_crm_api( '/crm_planos', 'POST', $plano_data );
            }

            tao_crm_notice( $rpl['ok'] ? 'Plano atualizado com sucesso.' : 'Erro: ' . ( $rpl['error'] ?? '' ), $rpl['ok'] ? 'success' : 'error' );

            $tab = 'planos';

        }

    }



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



    // ── Salvar gestores globais ──────────────────────────────────────────────────

    if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['tao_crm_gestores_global_nonce'] ) ) {

        if ( current_user_can( 'manage_options' ) && wp_verify_nonce( sanitize_text_field( $_POST['tao_crm_gestores_global_nonce'] ), 'tao_crm_save_gestores_global' ) ) {

            $ids = array_values( array_map( 'intval', array_filter( (array) ( $_POST['gestores_global'] ?? [] ) ) ) );

            // Remove admins from the list (they're auto-gestores, no need to store)

            $ids = array_values( array_filter( $ids, fn( $id ) => ! user_can( $id, 'manage_options' ) ) );

            update_option( 'tao_crm_gestores_global', $ids, false );

            tao_crm_notice( 'Gestores globais salvos.' );

            $tab = 'equipe';

        }

    }

    // ── Salvar gestores do workspace ──────────────────────────────────────────

    if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['tao_crm_gestores_ws_nonce'] ) ) {

        if ( current_user_can( 'manage_options' ) && wp_verify_nonce( sanitize_text_field( $_POST['tao_crm_gestores_ws_nonce'] ), 'tao_crm_save_gestores' ) ) {

            $ws_id = sanitize_text_field( $_POST['workspace_id'] ?? '' );

            if ( $ws_id ) {

                $ids = array_values( array_map( 'intval', array_filter( (array) ( $_POST['gestores_ws'] ?? [] ) ) ) );

                $gestores_global = (array) get_option( 'tao_crm_gestores_global', [] );

                // Remove admins and global gestores from ws list (redundant)

                $ids = array_values( array_filter( $ids, fn( $id ) => ! user_can( $id, 'manage_options' ) && ! in_array( $id, $gestores_global, true ) ) );

                // ── Enforcement de limite de usuários ─────────────────────────
                $rp_eq = tao_crm_api( "/crm_planos?workspace_id=eq.$ws_id&ativo=eq.true&select=limite_usuarios&limit=1" );
                $lim_u = (int) ( ( $rp_eq['ok'] && ! empty( $rp_eq['data'] ) ) ? ( $rp_eq['data'][0]['limite_usuarios'] ?? 3 ) : 3 );
                $vendedores = (array) get_option( 'tao_crm_vendedores_global', [] );
                $total = count( array_unique( array_merge( $ids, $vendedores ) ) );
                if ( $lim_u > 0 && $total > $lim_u ) {
                    tao_crm_notice( "Limite de usuários atingido: o plano permite $lim_u usuários (gestores + vendedores). Faça upgrade para adicionar mais.", 'error' );
                    $tab = 'equipe';
                } else {

                update_option( 'tao_crm_gestores_ws_' . $ws_id, $ids, false );

                tao_crm_notice( 'Gestores do workspace salvos.' );

                }

            }

            $tab = 'equipe';

        }

    }

    // ── Salvar vendedores ─────────────────────────────────────────────────────

    if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['tao_crm_vendedores_nonce'] ) ) {

        if ( current_user_can( 'manage_options' ) && wp_verify_nonce( sanitize_text_field( $_POST['tao_crm_vendedores_nonce'] ), 'tao_crm_save_vendedores' ) ) {

            $ids = array_values( array_map( 'intval', array_filter( (array) ( $_POST['vendedores_global'] ?? [] ) ) ) );

            $ids = array_values( array_filter( $ids, fn( $id ) => ! user_can( $id, 'manage_options' ) ) );

            // ── Aviso de limite (vendedores são globais, verificamos contra workspace selecionado) ──
            $ws_vend = sanitize_text_field( $_POST['workspace_id_vend'] ?? $ws_id_sel ?? '' );
            if ( $ws_vend ) {
                $rp_v = tao_crm_api( "/crm_planos?workspace_id=eq.$ws_vend&ativo=eq.true&select=limite_usuarios&limit=1" );
                $lim_v = (int) ( ( $rp_v['ok'] && ! empty( $rp_v['data'] ) ) ? ( $rp_v['data'][0]['limite_usuarios'] ?? 3 ) : 3 );
                $gest_ws_v = (array) get_option( 'tao_crm_gestores_ws_' . $ws_vend, [] );
                $total_v = count( array_unique( array_merge( $ids, $gest_ws_v ) ) );
                if ( $lim_v > 0 && $total_v > $lim_v ) {
                    tao_crm_notice( "Atenção: o total de usuários ($total_v) excede o limite do plano ($lim_v). Faça upgrade ou remova usuários.", 'warning' );
                }
            }

            update_option( 'tao_crm_vendedores_global', $ids, false );

            tao_crm_notice( 'Vendedores salvos.' );

            $tab = 'equipe';

        }

    }

    // ── Salvar workspace ──────────────────────────────────────────────────────

    if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['tao_crm_ws_nonce'] ) ) {

        if ( ! wp_verify_nonce( sanitize_text_field( $_POST['tao_crm_ws_nonce'] ), 'tao_crm_save_workspace' ) ) {

            tao_crm_notice( 'Nonce inválido.', 'error' );

        } else {

            $edit_id = sanitize_text_field( $_POST['edit_id'] ?? '' );

            $data = [

                'nome'                    => sanitize_text_field( $_POST['nome'] ?? '' ),

                'cliente_id'              => sanitize_text_field( $_POST['cliente_id'] ?? '' ) ?: null,

                'evolution_url'           => esc_url_raw( $_POST['evolution_url'] ?? '' ),

                'evolution_key'           => sanitize_text_field( $_POST['evolution_key'] ?? '' ),

                'evolution_instancia'     => sanitize_text_field( $_POST['evolution_instancia'] ?? '' ),

                'modo_recepcionista'      => ! empty( $_POST['modo_recepcionista'] ),

                'mensagem_recepcionista'  => sanitize_textarea_field( $_POST['mensagem_recepcionista'] ?? '' ),

                'ativo'                   => true,

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

    <div class="wrap crm-settings-page">
    <div class="crm-settings-layout">

        <!-- ── Sidebar ── -->
        <aside class="crm-settings-sidebar">

            <div class="crm-sidebar-header">
                <svg class="crm-sidebar-logo-icon" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg" style="background:#2563eb;padding:5px;box-sizing:border-box">
                    <rect x="5" y="5" width="7" height="7" rx="1" fill="#fff"/>
                    <rect x="16" y="5" width="7" height="7" rx="1" fill="#fff"/>
                    <rect x="5" y="16" width="7" height="7" rx="1" fill="#fff"/>
                    <rect x="16" y="16" width="7" height="7" rx="1" fill="#fff"/>
                </svg>
                <div>
                    <div class="crm-sidebar-brand">TAO CRM</div>
                    <div class="crm-sidebar-subtitle">Configurações</div>
                </div>
            </div>

            <div class="crm-sidebar-ws-wrap">
                <?php foreach ( $workspaces as $w ) : ?>
                <a href="<?php echo esc_url( tao_crm_settings_url( ['tab' => $tab, 'workspace_id' => $w['id']] ) ); ?>"
                   class="crm-sidebar-ws-item <?php echo $w['id'] === $ws_id_sel ? 'active' : ''; ?>">
                    <span class="crm-ws-dot"></span>
                    <?php echo esc_html( $w['nome'] ); ?>
                </a>
                <?php endforeach; ?>
                <a href="<?php echo esc_url( tao_crm_settings_url( ['tab' => 'workspaces'] ) ); ?>" class="crm-sidebar-ws-add">+ Novo negócio</a>
            </div>

            <nav class="crm-sidebar-nav">
                <?php
                $__ni = function( $label, $svg, $key, $url ) use ( $tab ) {
                    $cls = ( $tab === $key ) ? ' active' : '';
                    echo '<a href="' . esc_url( $url ) . '" class="crm-nav-item' . $cls . '">'
                       . $svg
                       . '<span>' . esc_html( $label ) . '</span>'
                       . '</a>';
                };

                echo '<div class="crm-nav-group-label">Global</div>';
                $__ni( 'Integração',
                    '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M12.316 3.051a1 1 0 01.633 1.265l-4 12a1 1 0 11-1.898-.632l4-12a1 1 0 011.265-.633zM5.707 6.293a1 1 0 010 1.414L3.414 10l2.293 2.293a1 1 0 11-1.414 1.414l-3-3a1 1 0 010-1.414l3-3a1 1 0 011.414 0zm8.586 0a1 1 0 011.414 0l3 3a1 1 0 010 1.414l-3 3a1 1 0 11-1.414-1.414L16.586 10l-2.293-2.293a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>',
                    'integracao', tao_crm_settings_url( ['tab' => 'integracao'] ) );
                $__ni( 'Equipe',
                    '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/></svg>',
                    'equipe', tao_crm_settings_url( ['tab' => 'equipe', 'workspace_id' => $ws_id_sel] ) );
                $__ni( 'Planos',
                    '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z"/><path fill-rule="evenodd" d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z" clip-rule="evenodd"/></svg>',
                    'planos', tao_crm_settings_url( ['tab' => 'planos'] ) );

                echo '<div class="crm-nav-group-label">Configuração</div>';
                $__ni( 'Workspaces',
                    '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M5 3a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2V5a2 2 0 00-2-2H5zM5 11a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2v-2a2 2 0 00-2-2H5zM11 5a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V5zM11 13a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>',
                    'workspaces', tao_crm_settings_url( ['tab' => 'workspaces'] ) );
                $__ni( 'Pipelines',
                    '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 3a1 1 0 000 2h11a1 1 0 100-2H3zm0 4a1 1 0 000 2h7a1 1 0 100-2H3zm0 4a1 1 0 100 2h4a1 1 0 100-2H3z" clip-rule="evenodd"/></svg>',
                    'pipelines', tao_crm_settings_url( ['tab' => 'pipelines', 'workspace_id' => $ws_id_sel] ) );
                $__ni( 'Campos',
                    '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/><path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"/></svg>',
                    'campos', tao_crm_settings_url( ['tab' => 'campos', 'workspace_id' => $ws_id_sel] ) );
                $__ni( 'Templates',
                    '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"/></svg>',
                    'templates', tao_crm_settings_url( ['tab' => 'templates', 'workspace_id' => $ws_id_sel] ) );
                $__ni( 'Tags',
                    '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M17.707 9.293a1 1 0 010 1.414l-7 7a1 1 0 01-1.414 0l-7-7A.997.997 0 012 10V5a3 3 0 013-3h5c.256 0 .512.098.707.293l7 7zM5 6a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>',
                    'tags', tao_crm_settings_url( ['tab' => 'tags', 'workspace_id' => $ws_id_sel] ) );
                $__ni( 'Webhooks',
                    '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M12.586 4.586a2 2 0 112.828 2.828l-3 3a2 2 0 01-2.828 0 1 1 0 00-1.414 1.414 4 4 0 005.656 0l3-3a4 4 0 00-5.656-5.656l-1.5 1.5a1 1 0 101.414 1.414l1.5-1.5zm-5 5a2 2 0 012.828 0 1 1 0 101.414-1.414 4 4 0 00-5.656 0l-3 3a4 4 0 105.656 5.656l1.5-1.5a1 1 0 10-1.414-1.414l-1.5 1.5a2 2 0 11-2.828-2.828l3-3z" clip-rule="evenodd"/></svg>',
                    'webhooks', tao_crm_settings_url( ['tab' => 'webhooks', 'workspace_id' => $ws_id_sel] ) );

                echo '<div class="crm-nav-group-label">Operação</div>';
                $__ni( 'Automações',
                    '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z" clip-rule="evenodd"/></svg>',
                    'automacoes', tao_crm_settings_url( ['tab' => 'automacoes', 'workspace_id' => $ws_id_sel] ) );
                $__ni( 'Horário',
                    '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/></svg>',
                    'horario', tao_crm_settings_url( ['tab' => 'horario'] ) );
                $__ni( 'SLA',
                    '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>',
                    'sla', tao_crm_settings_url( ['tab' => 'sla'] ) );
                $__ni( 'CSAT',
                    '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>',
                    'csat', tao_crm_settings_url( ['tab' => 'csat'] ) );
                $__ni( 'Metas',
                    '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5 3a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2V5a2 2 0 00-2-2H5zm9 4a1 1 0 10-2 0v6a1 1 0 102 0V7zm-3 2a1 1 0 10-2 0v4a1 1 0 102 0V9zm-3 3a1 1 0 10-2 0v1a1 1 0 102 0v-1z" clip-rule="evenodd"/></svg>',
                    'metas', tao_crm_settings_url( ['tab' => 'metas', 'workspace_id' => $ws_id_sel] ) );

                echo '<div class="crm-nav-group-label">Sistema</div>';
                $__ni( 'Importar',
                    '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>',
                    'importar', tao_crm_settings_url( ['tab' => 'importar'] ) );
                $__ni( 'LGPD',
                    '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>',
                    'lgpd', tao_crm_settings_url( ['tab' => 'lgpd'] ) );
                $__ni( 'Logs',
                    '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>',
                    'logs', tao_crm_settings_url( ['tab' => 'logs'] ) );
                ?>
            </nav>

        </aside><!-- .crm-settings-sidebar -->

        <!-- ── Conteúdo principal ── -->
        <main class="crm-settings-main">

            <div class="crm-settings-topbar">
                <div class="crm-settings-breadcrumb">TAO CRM &rsaquo; Configurações</div>
                <h1 class="crm-settings-page-title"><?php
                    $__tl = [
                        'workspaces' => 'Workspaces',
                        'pipelines'  => 'Pipelines & Estágios',
                        'campos'     => 'Campos',
                        'templates'  => 'Templates',
                        'tags'       => 'Tags',
                        'webhooks'   => 'Webhooks',
                        'equipe'     => 'Equipe',
                        'integracao' => 'Integração',
                        'planos'     => 'Planos',
                        'importar'   => 'Importar',
                        'automacoes' => 'Automações',
                        'horario'    => 'Horário',
                        'sla'        => 'SLA',
                        'csat'       => 'CSAT',
                        'metas'      => 'Metas',
                        'lgpd'       => 'LGPD',
                        'logs'       => 'Logs',
                    ];
                    echo esc_html( $__tl[ $tab ] ?? ucfirst( $tab ) );
                ?></h1>
            </div>



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

                        <td><?php echo $w['ativo'] ? 'Ativo' : 'Inativo'; ?></td>

                        <td>

                            <a href="<?php echo esc_url( tao_crm_settings_url( ['tab' => 'workspaces', 'edit_ws' => $w['id']] ) ); ?>"

                               class="button button-small">Editar</a>

                            <a href="<?php echo esc_url( tao_crm_settings_url( ['tab' => 'pipelines', 'workspace_id' => $w['id']] ) ); ?>"

                               class="button button-small">Pipelines</a>

                            <button class="button button-small tao-crm-del-workspace"

                                    data-ws-id="<?php echo esc_attr( $w['id'] ); ?>"

                                    data-nome="<?php echo esc_attr( $w['nome'] ); ?>"

                                    style="color:#dc2626;border-color:#dc2626">Excluir</button>

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

                    <tr>

                        <th>Modo Recepcionista</th>

                        <td>
                            <label>
                                <input type="checkbox" name="modo_recepcionista" value="1"
                                       <?php checked( ! empty( $edit_ws['modo_recepcionista'] ) ); ?>>
                                Apenas cumprimentar o cliente e criar card no Kanban (sem fluxo chatbot)
                            </label>
                            <p class="description">Quando ativo, o TAO Neo envia a mensagem abaixo, cria o card e encerra. Ideal para atendimento 100% humano.</p>
                        </td>

                    </tr>

                    <tr id="row-mensagem-recepcionista">

                        <th>Mensagem de boas-vindas</th>

                        <td><textarea name="mensagem_recepcionista" rows="3" class="large-text"
                                      placeholder="Ex: Olá! 👋 Obrigado pelo contato. Um de nossos atendentes entrará em contato em breve."><?php echo esc_textarea( $edit_ws['mensagem_recepcionista'] ?? 'Olá! 👋 Obrigado pelo contato. Um atendente entrará em contato com você em breve.' ); ?></textarea></td>

                    </tr>

                </table>

                <script>
                (function(){
                    var cb = document.querySelector('[name="modo_recepcionista"]');
                    var row = document.getElementById('row-mensagem-recepcionista');
                    if(!cb || !row) return;
                    function tog(){ row.style.display = cb.checked ? '' : 'none'; }
                    tog();
                    cb.addEventListener('change', tog);
                })();
                </script>

                <p><input type="submit" class="button button-primary"

                          value="<?php echo $edit_ws ? 'Atualizar workspace' : 'Criar workspace'; ?>"></p>

            </form>

            <?php if ( $edit_ws ) : // ── Instâncias adicionais (só ao editar) ──

                $r_insts = tao_crm_api( "/crm_instancias?workspace_id=eq.{$edit_ws['id']}&ativo=eq.true&order=criado_em.asc" );
                $insts   = $r_insts['ok'] ? ( $r_insts['data'] ?? [] ) : [];

                $edit_inst_id = sanitize_text_field( $_GET['edit_inst'] ?? '' );
                $edit_inst    = null;
                foreach ( $insts as $inst ) {
                    if ( $inst['id'] === $edit_inst_id ) { $edit_inst = $inst; break; }
                }
            ?>

            <hr style="margin:28px 0">
            <h3 style="margin-bottom:6px">&#x1F4DE; Instâncias de WhatsApp adicionais</h3>
            <p class="description" style="margin-bottom:16px">Cada instância é um número de WhatsApp separado. A instância principal está configurada acima (em "Nome da instância"). As instâncias adicionais permitem conectar outros números ao mesmo negócio.</p>

            <?php if ( ! empty( $insts ) ) : ?>
            <table class="wp-list-table widefat fixed striped" style="margin-bottom:16px">
                <thead><tr>
                    <th>Nome</th><th>Instância (Evolution)</th><th style="width:130px">Modo Recepcionista</th><th style="width:130px"></th>
                </tr></thead>
                <tbody>
                <?php foreach ( $insts as $inst ) : ?>
                <tr>
                    <td><?php echo esc_html( $inst['nome'] ); ?></td>
                    <td><code><?php echo esc_html( $inst['evolution_instancia'] ); ?></code></td>
                    <td>
                        <?php if ( ! empty( $inst['modo_recepcionista'] ) ) : ?>
                            <span style="background:#dcfce7;color:#166534;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600">Recepcionista</span>
                        <?php else : ?>
                            <span style="background:#f1f5f9;color:#64748b;padding:2px 8px;border-radius:10px;font-size:11px">Chatbot</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="<?php echo esc_url( tao_crm_settings_url( ['tab' => 'workspaces', 'edit_ws' => $edit_ws['id'], 'edit_inst' => $inst['id']] ) ); ?>"
                           class="button button-small">Editar</a>
                        <button class="button button-small tao-crm-del-instancia"
                                data-inst-id="<?php echo esc_attr( $inst['id'] ); ?>"
                                data-nome="<?php echo esc_attr( $inst['nome'] ); ?>"
                                style="color:#dc2626;border-color:#dc2626">Excluir</button>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else : ?>
            <p style="color:#64748b;font-size:13px;margin-bottom:12px">Nenhuma instância adicional cadastrada.</p>
            <?php endif; ?>

            <h4><?php echo $edit_inst ? 'Editar instância' : 'Adicionar instância'; ?></h4>
            <form id="tao-crm-inst-form">
                <?php wp_nonce_field( 'tao_crm_nonce', 'tao_crm_nonce_inst' ); ?>
                <input type="hidden" name="edit_id" id="crm-inst-edit-id" value="<?php echo esc_attr( $edit_inst['id'] ?? '' ); ?>">
                <input type="hidden" name="workspace_id" value="<?php echo esc_attr( $edit_ws['id'] ); ?>">
                <table class="form-table" style="max-width:600px">
                    <tr>
                        <th>Nome *</th>
                        <td><input type="text" name="nome" id="crm-inst-nome" class="regular-text" required
                                   placeholder="Ex: Recepção / Número 2"
                                   value="<?php echo esc_attr( $edit_inst['nome'] ?? '' ); ?>"></td>
                    </tr>
                    <tr>
                        <th>Evolution API URL *</th>
                        <td><input type="url" name="evolution_url" id="crm-inst-evo-url" class="regular-text" required
                                   placeholder="<?php echo esc_attr( $edit_ws['evolution_url'] ?? 'https://...' ); ?>"
                                   value="<?php echo esc_attr( $edit_inst['evolution_url'] ?? $edit_ws['evolution_url'] ?? '' ); ?>"></td>
                    </tr>
                    <tr>
                        <th>Evolution API Key *</th>
                        <td><input type="password" name="evolution_key" id="crm-inst-evo-key" class="regular-text" required
                                   value="<?php echo esc_attr( $edit_inst['evolution_key'] ?? $edit_ws['evolution_key'] ?? '' ); ?>"></td>
                    </tr>
                    <tr>
                        <th>Nome da instância *</th>
                        <td><input type="text" name="evolution_instancia" id="crm-inst-evo-inst" class="regular-text" required
                                   placeholder="Ex: magistao-recepcao"
                                   value="<?php echo esc_attr( $edit_inst['evolution_instancia'] ?? '' ); ?>"></td>
                    </tr>
                    <tr>
                        <th>Modo Recepcionista</th>
                        <td>
                            <label>
                                <input type="checkbox" name="modo_recepcionista" id="crm-inst-recep" value="1"
                                       <?php checked( ! empty( $edit_inst['modo_recepcionista'] ) ); ?>>
                                Apenas cumprimentar e criar card (sem fluxo chatbot)
                            </label>
                        </td>
                    </tr>
                    <tr id="crm-inst-row-msg" style="<?php echo empty( $edit_inst['modo_recepcionista'] ) ? 'display:none' : ''; ?>">
                        <th>Mensagem de boas-vindas</th>
                        <td><textarea name="mensagem_recepcionista" id="crm-inst-msg" rows="3" class="large-text"
                                      placeholder="Olá! 👋 Um atendente entrará em contato em breve."><?php echo esc_textarea( $edit_inst['mensagem_recepcionista'] ?? '' ); ?></textarea></td>
                    </tr>
                </table>
                <p>
                    <button type="submit" class="button button-primary" id="crm-inst-submit">
                        <?php echo $edit_inst ? 'Atualizar instância' : 'Salvar instância'; ?>
                    </button>
                    <?php if ( $edit_inst ) : ?>
                    <a href="<?php echo esc_url( tao_crm_settings_url( ['tab' => 'workspaces', 'edit_ws' => $edit_ws['id']] ) ); ?>"
                       class="button" style="margin-left:8px">Cancelar</a>
                    <?php endif; ?>
                    <span id="crm-inst-msg-status" style="margin-left:12px;font-size:13px"></span>
                </p>
            </form>
            <script>
            (function(){
                var _ajaxUrl = <?php echo json_encode( admin_url('admin-ajax.php') ); ?>;
                var _nonce   = <?php echo json_encode( wp_create_nonce('tao_crm_nonce') ); ?>;

                var cb = document.getElementById('crm-inst-recep');
                var row = document.getElementById('crm-inst-row-msg');
                if(cb && row){ cb.addEventListener('change', function(){ row.style.display = this.checked ? '' : 'none'; }); }

                var form = document.getElementById('tao-crm-inst-form');
                if(!form) return;
                form.addEventListener('submit', function(e){
                    e.preventDefault();
                    var btn = document.getElementById('crm-inst-submit');
                    var status = document.getElementById('crm-inst-msg-status');

                    if(!_ajaxUrl){
                        status.style.color='#dc2626';
                        status.textContent = 'Erro: ajax_url não encontrado. Acesse via painel admin.';
                        btn.disabled = false; btn.textContent = 'Salvar instância';
                        return;
                    }

                    btn.disabled = true; btn.textContent = 'Salvando...';
                    var fd = new FormData(form);
                    fd.set('action', 'tao_crm_save_instancia');
                    fd.set('nonce', _nonce);
                    if(!fd.get('modo_recepcionista')) fd.set('modo_recepcionista','');

                    fetch(_ajaxUrl, { method:'POST', body: fd })
                        .then(function(r){ return r.text(); })
                        .then(function(txt){
                            var res = null;
                            try {
                                // Remove qualquer lixo antes do JSON (avisos PHP, etc.)
                                var j = txt.indexOf('{');
                                res = JSON.parse(j >= 0 ? txt.substring(j) : txt);
                            } catch(e){ res = null; }
                            if(res && res.success){
                                status.style.color='#166534';
                                status.textContent = 'Salvo!';
                                setTimeout(function(){ window.location.reload(); }, 600);
                            } else {
                                var errMsg = (res && res.data) ? res.data : txt.substring(0,300);
                                status.style.color='#dc2626';
                                status.textContent = 'Erro: ' + errMsg;
                                btn.disabled = false;
                                btn.textContent = document.getElementById('crm-inst-edit-id').value ? 'Atualizar instância' : 'Salvar instância';
                            }
                        })
                        .catch(function(err){
                            status.style.color='#dc2626';
                            status.textContent = 'Erro de rede: ' + (err.message || err);
                            btn.disabled = false;
                            btn.textContent = document.getElementById('crm-inst-edit-id').value ? 'Atualizar instância' : 'Salvar instância';
                        });
                });

                // Delete
                document.querySelectorAll('.tao-crm-del-instancia').forEach(function(delBtn){
                    delBtn.addEventListener('click', function(){
                        if(!confirm('Excluir instância "' + this.dataset.nome + '"?')) return;
                        var instId = this.dataset.instId;
                        fetch(_ajaxUrl, {
                            method:'POST',
                            headers:{'Content-Type':'application/x-www-form-urlencoded'},
                            body: 'action=tao_crm_delete_instancia&nonce=' + encodeURIComponent(_nonce) + '&inst_id=' + encodeURIComponent(instId)
                        }).then(function(r){ return r.json(); }).then(function(res){
                            if(res.success){ window.location.reload(); }
                            else { alert('Erro ao excluir: ' + (res.data || '')); }
                        });
                    });
                });

            })();
            </script>

            <?php endif; // end $edit_ws ?>

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

                            <?php if ( $is_pos ) echo ' &#x1F504;'; ?>

                        </a>

                        <button class="button button-small tao-crm-del-pipeline"

                                data-pl-id="<?php echo esc_attr( $p['id'] ); ?>"

                                data-nome="<?php echo esc_attr( $p['nome'] ); ?>"

                                title="Excluir pipeline"

                                style="color:#dc2626;border-color:#dc2626;padding:2px 6px">&#x2715;</button>

                    </div>

                    <?php endforeach; ?>

                </div>

                <p style="font-size:12px;color:#64748b;margin:0">

                    &#x1F504; = pipeline de Pós-vendas (cards ganhos são movidos para ele automaticamente).

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

                        <th style="width:44px"></th>

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

                                <option value="handoff" <?php selected( $e['tipo'], 'handoff' ); ?>>&#x1F64B; Handoff</option>

                                <option value="ganho"   <?php selected( $e['tipo'], 'ganho' ); ?>>Ganho</option>

                                <option value="perdido" <?php selected( $e['tipo'], 'perdido' ); ?>>✗ Perdido</option>

                            </select>

                        </td>

                        <td>

                            <button type="button" class="button button-small tao-crm-del-estagio"

                                    data-est-id="<?php echo esc_attr( $e['id'] ); ?>"

                                    data-nome="<?php echo esc_attr( $e['nome'] ); ?>"

                                    style="color:#dc2626;border-color:#dc2626;padding:2px 6px"

                                    title="Excluir estágio">&#x2715;</button>

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

                                <option value="handoff">&#x1F64B; Handoff</option>

                                <option value="ganho">Ganho</option>

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

                    <table class="wp-list-table widefat striped" style="font-size:13px">

                        <thead><tr><th>Nome</th><th style="width:140px">Tipo</th><th style="width:150px">Fases</th><th style="width:76px"></th></tr></thead>

                        <tbody>

                        <?php foreach ( $campos_all as $c ) :

                            $n_ests = count( $assignments[ $c['id'] ] ?? [] );

                            $n_req  = count( array_filter( $assignments[ $c['id'] ] ?? [], fn($a) => $a['obrigatorio'] ) );

                            $n_ent  = count( array_filter( $assignments[ $c['id'] ] ?? [], fn($a) => $a['na_entrada'] ?? false ) );

                        ?>

                        <tr <?php echo $c['id'] === $edit_campo_id ? 'style="background:#eff6ff"' : ''; ?>>

                            <td><strong><?php echo esc_html( $c['nome'] ); ?></strong>

                                <br><small style="color:#94a3b8"><?php echo esc_html( $c['chave'] ); ?></small></td>

                            <td><code><?php echo esc_html( $c['tipo'] ); ?></code></td>

                            <td><?php echo $n_ests; ?> estágio<?php echo $n_ests !== 1 ? 's' : ''; ?>

                                <?php if ( $n_req ) echo "<br><small style='color:#dc2626'>$n_req obrigatório" . ( $n_req > 1 ? 's' : '' ) . "</small>"; ?>

                                <?php if ( $n_ent ) echo "<br><small style='color:#7c3aed'>&#x25BA; $n_ent na entrada</small>"; ?>

                            </td>

                            <td>

                                <a href="<?php echo esc_url( tao_crm_settings_url( ['tab' => 'campos', 'workspace_id' => $ws_id_sel, 'pipeline_id' => $pipe_c, 'edit_campo' => $c['id']] ) ); ?>"

                                   class="button button-small">✏</a>

                                <button class="button button-small tao-crm-del-campo"

                                        data-campo-id="<?php echo esc_attr( $c['id'] ); ?>"

                                        data-nome="<?php echo esc_attr( $c['nome'] ); ?>">&#x2715;</button>

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

                                    'date'=>'Data','select'=>'Seleção','boolean'=>'Sim/Não','phone'=>'Telefone','email'=>'E-mail',
                                    'arquivo'=>'Arquivo / Documento' ] as $v => $l ) : ?>

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

                            <small style="color:#64748b;margin-bottom:6px;display:block">Em quais fases o campo aparece? <strong>Obrigatório</strong> bloqueia o avanço se vazio. <strong>Entrada</strong> abre formulário assim que o card chega na fase.</small>

                            <table style="width:100%;font-size:12px;border-collapse:collapse">

                                <thead><tr>

                                    <th style="text-align:left;padding:4px">Fase</th>

                                    <th style="width:50px;text-align:center">Mostrar</th>

                                    <th style="width:80px;text-align:center">Obrigatório</th>

                                    <th style="width:60px;text-align:center">Entrada</th>

                                    <th style="width:90px;text-align:center">Ordem</th>

                                </tr></thead>

                                <tbody>

                                <?php foreach ( $ests_all as $est ) :

                                    $asn = $assignments[ $edit_campo['id'] ?? '' ][ $est['id'] ] ?? null;

                                    $on  = $asn !== null;

                                    $req = $asn['obrigatorio'] ?? false;

                                    $ent = $asn['na_entrada']  ?? false;

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

                                        <input type="checkbox" class="est-ent" data-est="<?php echo esc_attr( $est['id'] ); ?>" <?php checked( $ent ); ?> <?php echo ! $on ? 'disabled' : ''; ?>

                                               title="Exibir formulário de preenchimento ao entrar nesta fase">

                                    </td>

                                    <td style="text-align:center">

                                        <input type="number" class="est-ord" data-est="<?php echo esc_attr( $est['id'] ); ?>"

                                               value="<?php echo esc_attr( $ord ); ?>" min="0" style="width:90px;font-size:12px" <?php echo ! $on ? 'disabled' : ''; ?>>

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

            <h2>Automa&ccedil;&otilde;es</h2>

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

                                'recebeu_mensagem'  => '&#x1F4AC; Receber msg',

                                'sem_resposta'      => '&#x23F0; ' . ( $a['horas_sem_resposta'] ?? 24 ) . 'h s/resposta',

                            ];

                            $acao_labels = [

                                'enviar_mensagem'      => '&#x1F4E4; Msg',

                                'mover_fase'           => '➡ Mover',

                                'atribuir_responsavel' => '&#x1F464; Atribuir',

                                'notificar_email'      => '&#x1F4E7; E-mail',

                            ];

                        ?>

                        <tr <?php echo $a['id'] === $edit_auto_id ? 'style="background:#eff6ff"' : ''; ?>>

                            <td><strong><?php echo esc_html( $a['nome'] ); ?></strong></td>

                            <td><small><?php echo esc_html( $tipo_labels[ $a['tipo'] ] ?? $a['tipo'] ); ?></small></td>

                            <td><small><?php echo esc_html( $acao_labels[ $a['acao'] ] ?? $a['acao'] ); ?></small></td>

                            <td><?php echo $a['ativo'] ? '&#x2705;' : '&#x23F8;'; ?></td>

                            <td>

                                <a href="<?php echo esc_url( tao_crm_settings_url( ['tab' => 'automacoes', 'workspace_id' => $ws_id_sel, 'pipeline_id' => $pipe_a, 'estagio_id' => $est_a, 'edit_auto' => $a['id']] ) ); ?>"

                                   class="button button-small">✏</a>

                                <button class="button button-small tao-crm-del-automacao"

                                        data-auto-id="<?php echo esc_attr( $a['id'] ); ?>"

                                        data-nome="<?php echo esc_attr( $a['nome'] ); ?>">&#x2715;</button>

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

                                <option value="recebeu_mensagem" <?php selected( $tipo_sel, 'recebeu_mensagem' ); ?>>&#x1F4AC; Ao receber mensagem</option>

                                <option value="sem_resposta"     <?php selected( $tipo_sel, 'sem_resposta' ); ?>>&#x23F0; Lead sem resposta há X horas</option>

                            </select>

                        </div>



                        <div class="tao-crm-field" id="af-delay-wrap"

                             style="display:<?php echo ( $edit_auto['tipo'] ?? '' ) === 'tempo_na_fase' ? 'flex' : 'none'; ?>">

                            <label>Minutos de espera</label>

                            <input type="number" id="af-delay" min="1" style="max-width:100px"

                                   value="<?php echo esc_attr( $edit_auto['delay_minutos'] ?? 60 ); ?>">

                        </div>



                        <div class="tao-crm-field" id="af-horas-wrap"

                             style="display:<?php echo ( $edit_auto['tipo'] ?? '' ) === 'sem_resposta' ? 'flex' : 'none'; ?>">

                            <label>Horas sem resposta</label>

                            <input type="number" id="af-horas" min="1" max="720" style="max-width:100px"

                                   value="<?php echo esc_attr( $edit_auto['horas_sem_resposta'] ?? 24 ); ?>">

                            <small style="color:#64748b;margin-top:2px">O cron verifica a cada hora. Mín. 1h.</small>

                        </div>



                        <div class="tao-crm-field">

                            <label>Ação *</label>

                            <select id="af-acao">

                                <?php $acao_sel = $edit_auto['acao'] ?? 'enviar_mensagem'; ?>

                                <option value="enviar_mensagem"      <?php selected( $acao_sel, 'enviar_mensagem' ); ?>>&#x1F4E4; Enviar mensagem WhatsApp</option>

                                <option value="mover_fase"           <?php selected( $acao_sel, 'mover_fase' ); ?>>➡ Mover para outra fase</option>

                                <option value="atribuir_responsavel" <?php selected( $acao_sel, 'atribuir_responsavel' ); ?>>&#x1F464; Atribuir responsável</option>

                                <option value="notificar_email"      <?php selected( $acao_sel, 'notificar_email' ); ?>>&#x1F4E7; Notificar atendente por e-mail</option>

                            </select>

                        </div>



                        <div class="tao-crm-field" id="af-msg-wrap"

                             style="display:<?php echo in_array( $edit_auto['acao'] ?? 'enviar_mensagem', [ 'enviar_mensagem', '' ] ) && ! $edit_auto ? 'flex' : ( ( $edit_auto['acao'] ?? '' ) === 'enviar_mensagem' ? 'flex' : 'none' ); ?>">

                            <label>Mensagem</label>

                            <textarea id="af-mensagem" rows="5"

                                      placeholder="Olá {nome}, bem-vindo! &#x1F60A;"><?php echo esc_textarea( $edit_auto['mensagem'] ?? '' ); ?></textarea>

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

                            <button type="button" class="button button-primary" id="af-submit">

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



        <?php elseif ( $tab === 'tags' ) : ?>

        <div class="tao-crm-settings-section">
            <h2>&#x1F3F7; Tags</h2>
            <p>Crie e gerencie tags para categorizar cards. As tags s&atilde;o vinculadas ao workspace selecionado.</p>

            <div class="campos-layout">

                <!-- Lista de tags -->
                <div class="campos-list-panel">
                    <h3>Tags cadastradas</h3>
                    <table class="wp-list-table widefat fixed striped" style="font-size:13px">
                        <thead>
                            <tr>
                                <th style="width:30px"></th>
                                <th>Nome</th>
                                <th style="width:80px">Cor</th>
                                <th style="width:110px"></th>
                            </tr>
                        </thead>
                        <tbody id="tao-tags-body">
                            <tr><td colspan="4" style="color:#94a3b8">Carregando...</td></tr>
                        </tbody>
                    </table>
                </div>

                <!-- Formulário criar/editar -->
                <div class="campos-form-panel">
                    <h3 id="tao-tag-form-title">Nova tag</h3>
                    <input type="hidden" id="tao-tag-id">
                    <input type="hidden" id="tao-tag-ws" value="<?php echo esc_attr( $ws_id_sel ); ?>">
                    <div style="display:flex;flex-direction:column;gap:14px">
                        <div class="tao-crm-field">
                            <label style="font-size:13px;font-weight:600">Nome da tag *</label>
                            <input type="text" id="tao-tag-nome" class="regular-text"
                                   style="width:100%;margin-top:4px"
                                   placeholder="Ex: VIP, Urgente, Farmácia">
                        </div>
                        <div class="tao-crm-field">
                            <label style="font-size:13px;font-weight:600">Cor</label>
                            <div style="display:flex;align-items:center;gap:10px;margin-top:4px">
                                <input type="color" id="tao-tag-cor" value="#6366f1" style="width:48px;height:36px;border:1px solid #cbd5e1;border-radius:6px;cursor:pointer;padding:2px">
                                <span id="tao-tag-cor-hex" style="font-size:13px;color:#64748b">#6366f1</span>
                            </div>
                        </div>
                        <div style="display:flex;gap:8px;align-items:center">
                            <button class="button button-primary" id="tao-tag-save">Salvar</button>
                            <button class="button" id="tao-tag-new">Limpar</button>
                            <span id="tao-tag-status" style="font-size:12px"></span>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <script>
        (function($){
            var wsId = $('#tao-tag-ws').val();

            // Atualizar hex ao mudar cor
            $('#tao-tag-cor').on('input change', function(){
                $('#tao-tag-cor-hex').text(this.value);
            });

            function tagDot(cor){
                return $('<span>').css({
                    display:'inline-block', width:'12px', height:'12px',
                    borderRadius:'50%', background: cor || '#6366f1',
                    border:'1px solid rgba(0,0,0,.15)', flexShrink:0
                });
            }

            function loadTags(){
                crmPost({ action:'tao_crm_get_tags', nonce:taoCrm.nonce, workspace_id:wsId }, function(resp){
                    var $b = $('#tao-tags-body').empty();
                    if(!resp.success || !resp.data || !resp.data.length){
                        $b.append('<tr><td colspan="4" style="color:#94a3b8">Nenhuma tag cadastrada ainda.</td></tr>');
                        return;
                    }
                    resp.data.forEach(function(t){
                        var cor = t.cor || '#6366f1';
                        $b.append($('<tr>').append(
                            $('<td>').css('text-align','center').append(tagDot(cor)),
                            $('<td>').text(t.nome),
                            $('<td>').append(
                                $('<code>').text(cor).css('font-size','11px')
                            ),
                            $('<td>').append(
                                $('<button>').addClass('button button-small').text('Editar').css('margin-right','4px').on('click', function(){
                                    $('#tao-tag-id').val(t.id);
                                    $('#tao-tag-nome').val(t.nome);
                                    $('#tao-tag-cor').val(cor);
                                    $('#tao-tag-cor-hex').text(cor);
                                    $('#tao-tag-form-title').text('Editar tag: ' + t.nome);
                                    $('#tao-tag-status').text('');
                                }),
                                $('<button>').addClass('button button-small').text('Excluir')
                                    .css({ color:'#dc2626', 'border-color':'#dc2626' })
                                    .on('click', function(){
                                        if(!confirm('Excluir tag "' + t.nome + '"?')) return;
                                        crmPost({ action:'tao_crm_delete_tag', nonce:taoCrm.nonce, id:t.id }, function(r){
                                            if(r.success) loadTags();
                                            else alert('Erro: ' + (r.data || 'Falha ao excluir'));
                                        });
                                    })
                            )
                        ));
                    });
                });
            }

            jQuery(loadTags);

            $('#tao-tag-new').on('click', function(){
                $('#tao-tag-id').val('');
                $('#tao-tag-nome').val('');
                $('#tao-tag-cor').val('#6366f1');
                $('#tao-tag-cor-hex').text('#6366f1');
                $('#tao-tag-form-title').text('Nova tag');
                $('#tao-tag-status').text('');
            });

            $('#tao-tag-save').on('click', function(){
                var nome = $('#tao-tag-nome').val().trim();
                var cor  = $('#tao-tag-cor').val();
                if(!nome){ alert('O nome da tag é obrigatório.'); return; }
                var $btn = $(this).prop('disabled', true).text('Salvando...');
                crmPost({
                    action:      'tao_crm_save_tag',
                    nonce:       taoCrm.nonce,
                    id:          $('#tao-tag-id').val(),
                    workspace_id: wsId,
                    nome:        nome,
                    cor:         cor
                }, function(r){
                    $btn.prop('disabled', false).text('Salvar');
                    if(r.success){
                        $('#tao-tag-status').css('color','green').text('✔ Salvo');
                        $('#tao-tag-id').val('');
                        $('#tao-tag-nome').val('');
                        $('#tao-tag-cor').val('#6366f1');
                        $('#tao-tag-cor-hex').text('#6366f1');
                        $('#tao-tag-form-title').text('Nova tag');
                        loadTags();
                    } else {
                        $('#tao-tag-status').css('color','red').text('✘ ' + (r.data || 'Erro ao salvar'));
                    }
                });
            });
        })(jQuery);
        </script>

        <?php elseif ( $tab === 'templates' ) : ?>

        <div class="tao-crm-settings-section">
            <h2>&#x1F4DD; Templates de Mensagem</h2>
            <p>Templates prontos para inserir no chat. Os atendentes selecionam via dropdown na caixa de mensagem do card.</p>

            <div class="campos-layout">
                <div class="campos-list-panel">
                    <h3>Templates cadastrados</h3>
                    <table class="wp-list-table widefat fixed striped">
                        <thead><tr><th>Nome</th><th>Conteúdo (prévia)</th><th></th></tr></thead>
                        <tbody id="tao-tpl-body">
                            <tr><td colspan="3" style="color:#94a3b8">Carregando...</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="campos-form-panel">
                    <h3 id="tao-tpl-form-title">Novo template</h3>
                    <input type="hidden" id="tao-tpl-id">
                    <input type="hidden" id="tao-tpl-ws" value="<?php echo esc_attr( $ws_id_sel ); ?>">
                    <div style="display:flex;flex-direction:column;gap:12px">
                        <label style="font-size:13px;font-weight:600">Nome
                            <input type="text" id="tao-tpl-nome" class="regular-text" style="width:100%;margin-top:4px" placeholder="Ex: Saudação inicial">
                        </label>
                        <label style="font-size:13px;font-weight:600">Conteúdo
                            <small style="font-weight:400;color:#64748b">Variáveis: {nome} {telefone} {titulo}</small>
                            <textarea id="tao-tpl-conteudo" rows="5" style="width:100%;margin-top:4px;border:1px solid #cbd5e1;border-radius:4px;padding:8px;font-size:13px;font-family:inherit" placeholder="Olá {nome}, como posso ajudar?"></textarea>
                        </label>
                        <div style="display:flex;gap:8px;align-items:center">
                            <button class="button button-primary" id="tao-tpl-save">Salvar</button>
                            <button class="button" id="tao-tpl-new">Limpar</button>
                            <span id="tao-tpl-status" style="font-size:12px"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script>
        (function(){
          function boot(){
            if(typeof window.jQuery==='undefined'||typeof window.crmPost==='undefined'||typeof window.taoCrm==='undefined'){ return setTimeout(boot,50); }
            var $=window.jQuery;
            var wsId = $('#tao-tpl-ws').val();
            function loadTpl(){
                crmPost({ action:'tao_crm_get_templates', nonce:taoCrm.nonce, workspace_id:wsId }, function(resp){
                    var $b = $('#tao-tpl-body').empty();
                    if(!resp.success||!resp.data||!resp.data.length){
                        $b.append('<tr><td colspan="3" style="color:#94a3b8">Nenhum template ainda.</td></tr>'); return;
                    }
                    resp.data.forEach(function(t){
                        var prev = t.conteudo.length>60 ? t.conteudo.substr(0,60)+'...' : t.conteudo;
                        $b.append($('<tr>').append(
                            $('<td>').text(t.nome),
                            $('<td>').text(prev).css('color','#64748b'),
                            $('<td>').append(
                                $('<button>').addClass('button button-small').text('Editar').on('click',function(){
                                    $('#tao-tpl-id').val(t.id); $('#tao-tpl-nome').val(t.nome); $('#tao-tpl-conteudo').val(t.conteudo);
                                    $('#tao-tpl-form-title').text('Editar template');
                                }), ' ',
                                $('<button>').addClass('button button-small').text('Excluir').css({color:'#dc2626','border-color':'#dc2626'}).on('click',function(){
                                    if(!confirm('Excluir template "'+t.nome+'"?')) return;
                                    crmPost({action:'tao_crm_delete_template',nonce:taoCrm.nonce,id:t.id},function(r){ if(r.success) loadTpl(); else alert('Erro: '+(r.data||'')); }, function(e){ alert('Erro: '+e); });
                                })
                            )
                        ));
                    });
                }, function(e){ $('#tao-tpl-body').html('<tr><td colspan="3" style="color:#dc2626">Erro ao carregar: '+e+'</td></tr>'); });
            }
            loadTpl();
            $('#tao-tpl-new').on('click',function(){ $('#tao-tpl-id').val(''); $('#tao-tpl-nome,#tao-tpl-conteudo').val(''); $('#tao-tpl-form-title').text('Novo template'); $('#tao-tpl-status').text(''); });
            $('#tao-tpl-save').on('click',function(e){
                e.preventDefault();
                var nome=$('#tao-tpl-nome').val().trim(), conteudo=$('#tao-tpl-conteudo').val().trim();
                if(!nome||!conteudo){ alert('Preencha nome e conteúdo'); return; }
                var $btn=$(this).prop('disabled',true).text('Salvando...');
                crmPost({action:'tao_crm_save_template',nonce:taoCrm.nonce,id:$('#tao-tpl-id').val(),workspace_id:wsId,nome:nome,conteudo:conteudo},
                    function(r){ $btn.prop('disabled',false).text('Salvar'); if(r.success){ $('#tao-tpl-status').css('color','green').text('✔ Salvo'); loadTpl(); } else { $('#tao-tpl-status').css('color','red').text('✘ '+(r.data||'Erro')); }},
                    function(e){ $btn.prop('disabled',false).text('Salvar'); $('#tao-tpl-status').css('color','red').text('✘ '+e); });
            });
          }
          boot();
        })();
        </script>

        <?php elseif ( $tab === 'webhooks' ) : ?>

        <div class="tao-crm-settings-section">
            <h2>&#x1F517; Webhooks de Sa&iacute;da</h2>
            <p>Dispare notificações para sistemas externos quando cards são criados, movidos ou fechados.</p>
            <p><strong>Eventos:</strong> <code>card_criado</code> &bull; <code>card_movido</code> &bull; <code>card_fechado_ganho</code> &bull; <code>card_fechado_perdido</code></p>

            <div class="campos-layout">
                <div class="campos-list-panel">
                    <h3>Webhooks cadastrados</h3>
                    <table class="wp-list-table widefat fixed striped">
                        <thead><tr><th>Nome</th><th>Evento</th><th>URL</th><th>Ativo</th><th></th></tr></thead>
                        <tbody id="tao-wh-body">
                            <tr><td colspan="5" style="color:#94a3b8">Carregando...</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="campos-form-panel">
                    <h3 id="tao-wh-form-title">Novo webhook</h3>
                    <input type="hidden" id="tao-wh-id">
                    <input type="hidden" id="tao-wh-ws" value="<?php echo esc_attr( $ws_id_sel ); ?>">
                    <div style="display:flex;flex-direction:column;gap:12px">
                        <label style="font-size:13px;font-weight:600">Nome (opcional)
                            <input type="text" id="tao-wh-nome" class="regular-text" style="width:100%;margin-top:4px" placeholder="Ex: Notificar sistema X">
                        </label>
                        <label style="font-size:13px;font-weight:600">Evento *
                            <select id="tao-wh-evento" style="width:100%;margin-top:4px">
                                <option value="card_criado">card_criado</option>
                                <option value="card_movido">card_movido</option>
                                <option value="card_fechado_ganho">card_fechado_ganho</option>
                                <option value="card_fechado_perdido">card_fechado_perdido</option>
                            </select>
                        </label>
                        <label style="font-size:13px;font-weight:600">URL de destino *
                            <input type="url" id="tao-wh-url" class="regular-text" style="width:100%;margin-top:4px" placeholder="https://n8n.exemplo.com/webhook/...">
                        </label>
                        <label style="font-size:13px;font-weight:600;display:flex;align-items:center;gap:8px">
                            <input type="checkbox" id="tao-wh-ativo" checked> Ativo
                        </label>
                        <div style="display:flex;gap:8px;align-items:center">
                            <button class="button button-primary" id="tao-wh-save">Salvar</button>
                            <button class="button" id="tao-wh-new">Limpar</button>
                            <span id="tao-wh-status" style="font-size:12px"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script>
        (function($){
            var wsId = $('#tao-wh-ws').val();
            function loadWh(){
                crmPost({action:'tao_crm_get_webhooks_saida',nonce:taoCrm.nonce,workspace_id:wsId},function(resp){
                    var $b = $('#tao-wh-body').empty();
                    if(!resp.success||!resp.data||!resp.data.length){
                        $b.append('<tr><td colspan="5" style="color:#94a3b8">Nenhum webhook ainda.</td></tr>'); return;
                    }
                    resp.data.forEach(function(w){
                        $b.append($('<tr>').append(
                            $('<td>').text(w.nome||'—'),
                            $('<td>').append($('<code>').text(w.evento)),
                            $('<td>').text(w.url.length>35?w.url.substr(0,35)+'...':w.url).css('font-size','11px'),
                            $('<td>').text(w.ativo?'✓':'✗'),
                            $('<td>').append(
                                $('<button>').addClass('button button-small').text('Editar').on('click',function(){
                                    $('#tao-wh-id').val(w.id); $('#tao-wh-nome').val(w.nome||'');
                                    $('#tao-wh-evento').val(w.evento); $('#tao-wh-url').val(w.url);
                                    $('#tao-wh-ativo').prop('checked',w.ativo); $('#tao-wh-form-title').text('Editar webhook');
                                }), ' ',
                                $('<button>').addClass('button button-small').text('Excluir').css({color:'#dc2626','border-color':'#dc2626'}).on('click',function(){
                                    if(!confirm('Excluir webhook?')) return;
                                    crmPost({action:'tao_crm_delete_webhook_saida',nonce:taoCrm.nonce,id:w.id},function(r){ if(r.success) loadWh(); else alert('Erro: '+(r.data||'')); });
                                })
                            )
                        ));
                    });
                });
            }
            jQuery(loadWh);
            $('#tao-wh-new').on('click',function(){ $('#tao-wh-id').val(''); $('#tao-wh-nome,#tao-wh-url').val(''); $('#tao-wh-ativo').prop('checked',true); $('#tao-wh-form-title').text('Novo webhook'); $('#tao-wh-status').text(''); });
            $('#tao-wh-save').on('click',function(){
                var url=$('#tao-wh-url').val().trim();
                if(!url){ alert('URL obrigatória'); return; }
                var $btn=$(this).prop('disabled',true).text('Salvando...');
                crmPost({action:'tao_crm_save_webhook_saida',nonce:taoCrm.nonce,id:$('#tao-wh-id').val(),workspace_id:wsId,nome:$('#tao-wh-nome').val(),evento:$('#tao-wh-evento').val(),url:url,ativo:$('#tao-wh-ativo').is(':checked')?'1':''},
                    function(r){ $btn.prop('disabled',false).text('Salvar'); if(r.success){ $('#tao-wh-status').css('color','green').text('✔ Salvo'); loadWh(); } else { $('#tao-wh-status').css('color','red').text('✘ '+(r.data||'Erro')); }});
            });
        })(jQuery);
        </script>

        <?php elseif ( $tab === 'equipe' ) : ?>

        <?php if ( ! current_user_can( 'manage_options' ) ) : ?>
        <div class="notice notice-error"><p>Acesso restrito a administradores.</p></div>
        <?php else :
            $all_users = get_users( [ 'orderby' => 'display_name', 'fields' => [ 'ID', 'display_name', 'user_email' ] ] );
            $gestores_global = (array) get_option( 'tao_crm_gestores_global', [] );
            $gestores_ws     = $ws_id_sel ? (array) get_option( 'tao_crm_gestores_ws_' . $ws_id_sel, [] ) : [];
        ?>

        <div class="tao-crm-settings-section">
            <h2>&#x1F465; Equipe &amp; Acessos</h2>
            <p>Três níveis de acesso: <strong>Admin</strong> (WP) → acesso total &bull; <strong>Gestor</strong> → vê todos os cards + configurações &bull; <strong>Vendedor</strong> → vê apenas seus próprios cards.</p>

            <div class="campos-layout">
                <!-- Gestores Globais -->
                <div class="campos-form-panel" style="flex:1">
                    <h3>&#x1F30E; Gestores Globais</h3>
                    <p style="color:#64748b;font-size:13px">Acesso a <em>todos</em> os workspaces como gestor.</p>
                    <form method="post" action="">
                        <?php wp_nonce_field( 'tao_crm_save_gestores_global', 'tao_crm_gestores_global_nonce' ); ?>
                        <input type="hidden" name="tao_crm_action" value="save_gestores_global">
                        <div style="display:flex;flex-direction:column;gap:6px;max-height:320px;overflow-y:auto;border:1px solid #e2e8f0;border-radius:6px;padding:10px">
                            <?php foreach ( $all_users as $u ) :
                                $is_admin = user_can( $u->ID, 'manage_options' );
                            ?>
                            <label style="display:flex;align-items:center;gap:8px;font-size:13px<?php echo $is_admin ? ';color:#94a3b8' : ''; ?>">
                                <input type="checkbox" name="gestores_global[]" value="<?php echo esc_attr( $u->ID ); ?>"
                                    <?php checked( $is_admin || in_array( (int) $u->ID, $gestores_global, true ) ); ?>
                                    <?php disabled( $is_admin ); ?>>
                                <span><?php echo esc_html( $u->display_name ); ?></span>
                                <span style="color:#94a3b8;font-size:11px"><?php echo esc_html( $u->user_email ); ?></span>
                                <?php if ( $is_admin ) : ?><span style="font-size:10px;background:#f1f5f9;padding:1px 6px;border-radius:10px">admin</span><?php endif; ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        <div style="margin-top:10px">
                            <button type="submit" class="button button-primary">Salvar gestores globais</button>
                        </div>
                    </form>
                </div>

                <!-- Gestores do Workspace selecionado -->
                <div class="campos-form-panel" style="flex:1">
                    <h3>&#x1F3E2; Gestores deste Workspace</h3>
                    <?php if ( $ws_id_sel ) : ?>
                    <p style="color:#64748b;font-size:13px">Gestor apenas no workspace selecionado no topo.</p>
                    <form method="post" action="">
                        <?php wp_nonce_field( 'tao_crm_save_gestores', 'tao_crm_gestores_ws_nonce' ); ?>
                        <input type="hidden" name="tao_crm_action" value="save_gestores_ws">
                        <input type="hidden" name="workspace_id" value="<?php echo esc_attr( $ws_id_sel ); ?>">
                        <div style="display:flex;flex-direction:column;gap:6px;max-height:320px;overflow-y:auto;border:1px solid #e2e8f0;border-radius:6px;padding:10px">
                            <?php foreach ( $all_users as $u ) :
                                $is_admin = user_can( $u->ID, 'manage_options' );
                                $is_global = in_array( (int) $u->ID, $gestores_global, true );
                            ?>
                            <label style="display:flex;align-items:center;gap:8px;font-size:13px<?php echo ( $is_admin || $is_global ) ? ';color:#94a3b8' : ''; ?>">
                                <input type="checkbox" name="gestores_ws[]" value="<?php echo esc_attr( $u->ID ); ?>"
                                    <?php checked( $is_admin || $is_global || in_array( (int) $u->ID, $gestores_ws, true ) ); ?>
                                    <?php disabled( $is_admin || $is_global ); ?>>
                                <span><?php echo esc_html( $u->display_name ); ?></span>
                                <span style="color:#94a3b8;font-size:11px"><?php echo esc_html( $u->user_email ); ?></span>
                                <?php if ( $is_admin ) : ?><span style="font-size:10px;background:#f1f5f9;padding:1px 6px;border-radius:10px">admin</span>
                                <?php elseif ( $is_global ) : ?><span style="font-size:10px;background:#dbeafe;color:#1d4ed8;padding:1px 6px;border-radius:10px">global</span><?php endif; ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        <div style="margin-top:10px">
                            <button type="submit" class="button button-primary">Salvar gestores do workspace</button>
                        </div>
                    </form>
                    <?php else : ?>
                    <p style="color:#94a3b8">Selecione um workspace no topo da página.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Vendedores -->
            <div style="margin-top:24px">
                <h3>&#x1F4BC; Vendedores</h3>
                <p style="color:#64748b;font-size:13px">Vendedores têm acesso ao CRM mas veem apenas seus próprios cards. Gestores e admins não precisam ser listados aqui.</p>
                <?php
                $vendedores_global = (array) get_option( 'tao_crm_vendedores_global', [] );
                $gestores_global   = (array) get_option( 'tao_crm_gestores_global', [] );
                ?>
                <form method="post" action="">
                    <?php wp_nonce_field( 'tao_crm_save_vendedores', 'tao_crm_vendedores_nonce' ); ?>
                    <input type="hidden" name="tao_crm_action" value="save_vendedores">
                    <input type="hidden" name="workspace_id_vend" value="<?php echo esc_attr( $ws_id_sel ); ?>">
                    <div style="display:flex;flex-direction:column;gap:6px;max-height:320px;overflow-y:auto;border:1px solid #e2e8f0;border-radius:6px;padding:10px;max-width:420px">
                        <?php foreach ( $all_users as $u ) :
                            $is_admin  = user_can( $u->ID, 'manage_options' );
                            $is_gestor = in_array( (int) $u->ID, $gestores_global, true );
                            $disabled  = $is_admin || $is_gestor;
                        ?>
                        <label style="display:flex;align-items:center;gap:8px;font-size:13px<?php echo $disabled ? ';color:#94a3b8' : ''; ?>">
                            <input type="checkbox" name="vendedores_global[]" value="<?php echo esc_attr( $u->ID ); ?>"
                                <?php checked( ! $disabled && in_array( (int) $u->ID, $vendedores_global, true ) ); ?>
                                <?php disabled( $disabled ); ?>>
                            <span><?php echo esc_html( $u->display_name ); ?></span>
                            <span style="color:#94a3b8;font-size:11px"><?php echo esc_html( $u->user_email ); ?></span>
                            <?php if ( $is_admin ) : ?><span style="font-size:10px;background:#f1f5f9;padding:1px 6px;border-radius:10px">admin</span>
                            <?php elseif ( $is_gestor ) : ?><span style="font-size:10px;background:#dbeafe;color:#1d4ed8;padding:1px 6px;border-radius:10px">gestor</span><?php endif; ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <div style="margin-top:10px">
                        <button type="submit" class="button button-primary">Salvar vendedores</button>
                    </div>
                </form>
            </div>
        </div>

        <?php endif; ?>

        <?php elseif ( $tab === 'integracao' ) : ?>



        <div class="tao-crm-settings-section">

            <h2>Integra&ccedil;&atilde;o &mdash; N8N &amp; Dispatch</h2>

            <div class="notice notice-warning inline" style="margin:0 0 16px;padding:8px 12px;border-radius:6px">
                <p><strong>&#x23F0; Cron real obrigat&oacute;rio no Hostinger</strong><br>
                O WP-Cron padr&atilde;o s&oacute; dispara quando algu&eacute;m visita o site. Para automa&ccedil;&otilde;es de tempo funcionarem corretamente, configure um cron job real no cPanel do Hostinger:</p>
                <pre style="background:#1e293b;color:#e2e8f0;padding:10px 14px;border-radius:4px;font-size:12px;margin:8px 0 0">*/1 * * * * curl -s <?php echo esc_url( site_url( 'wp-cron.php?doing_wp_cron' ) ); ?> &gt; /dev/null 2&gt;&amp;1</pre>
                <p style="margin:6px 0 0;font-size:12px;color:#64748b">cPanel &rarr; Cron Jobs &rarr; Cole o comando acima com intervalo de 1 minuto.</p>
            </div>

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

            <hr style="margin:24px 0">

            <h3>&#x1F4BE; Backup de Dados</h3>

            <p>Gera um arquivo <code>.json.gz</code> com todos os dados do CRM (cards, contatos, automações, planos etc.) via API do Supabase. Os últimos 7 backups são mantidos automaticamente. O cron semanal gera o backup toda segunda-feira.</p>

            <?php
            $ultimo_bk = get_option( 'tao_crm_ultimo_backup', [] );
            if ( ! empty( $ultimo_bk['ts'] ) ) :
                $ts_fmt = date_i18n( 'd/m/Y H:i', strtotime( $ultimo_bk['ts'] ) );
            ?>
            <div class="notice notice-success inline" style="margin:0 0 14px;padding:8px 12px;border-radius:6px">
                <p><strong>Último backup:</strong> <?php echo esc_html( $ts_fmt ); ?> &mdash;
                <?php echo esc_html( number_format( $ultimo_bk['rows'] ?? 0 ) ); ?> registros
                (<?php echo esc_html( $ultimo_bk['file'] ?? '' ); ?>)</p>
            </div>
            <?php else : ?>
            <div class="notice notice-info inline" style="margin:0 0 14px;padding:8px 12px;border-radius:6px">
                <p>Nenhum backup gerado ainda. Clique em <strong>Gerar agora</strong> para criar o primeiro.</p>
            </div>
            <?php endif; ?>

            <p>
                <button type="button" id="tao-crm-run-backup" class="button button-secondary">
                    &#x25B6;&#xFE0F; Gerar agora
                </button>
                <?php if ( ! empty( $ultimo_bk['file'] ) ) : ?>
                &nbsp;
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
                    <?php wp_nonce_field( 'tao_crm_download_backup' ); ?>
                    <input type="hidden" name="action" value="tao_crm_download_backup">
                    <button type="submit" class="button button-secondary">&#x2B07;&#xFE0F; Baixar último backup</button>
                </form>
                <?php endif; ?>
            </p>

            <div id="tao-crm-backup-result" style="display:none;margin-top:10px"></div>

            <script>
            jQuery(function($){
                $('#tao-crm-run-backup').on('click', function(){
                    var $btn = $(this);
                    $btn.prop('disabled', true).text('Gerando…');
                    $('#tao-crm-backup-result').hide();
                    $.post(ajaxurl, { action: 'tao_crm_run_backup', nonce: taoCrm.nonce }, function(r){
                        $btn.prop('disabled', false).html('&#x25B6;&#xFE0F; Gerar agora');
                        if (r.success) {
                            var d = r.data;
                            $('#tao-crm-backup-result')
                                .html('<div class="notice notice-success inline" style="padding:8px 12px;border-radius:6px"><p><strong>Backup gerado:</strong> ' + (d.file||'') + ' &mdash; ' + (d.rows||0) + ' registros. <a href="' + location.href + '">Recarregue</a> para ver o botão de download.</p></div>')
                                .show();
                        } else {
                            $('#tao-crm-backup-result')
                                .html('<div class="notice notice-error inline" style="padding:8px 12px;border-radius:6px"><p>Erro ao gerar backup: ' + (r.data||'desconhecido') + '</p></div>')
                                .show();
                        }
                    }).fail(function(){
                        $btn.prop('disabled', false).html('&#x25B6;&#xFE0F; Gerar agora');
                        $('#tao-crm-backup-result').html('<div class="notice notice-error inline" style="padding:8px 12px;border-radius:6px"><p>Falha na requisição AJAX.</p></div>').show();
                    });
                });
            });
            </script>

        </div>



        <?php elseif ( $tab === 'planos' ) : ?>



        <!-- v1.4.0: Planos -->

        <div class="tao-crm-settings-section" style="max-width:860px">

            <h2>&#x1F4B3; Planos &amp; Billing</h2>



            <?php

            // Busca plano ativo da tabela crm_planos (v1.5+)
            $rplano = $ws_id_sel ? tao_crm_api( "/crm_planos?workspace_id=eq.$ws_id_sel&ativo=eq.true&order=criado_em.desc&limit=1" ) : [ 'ok' => false ];
            $plano_row = ( $rplano['ok'] && ! empty( $rplano['data'] ) ) ? $rplano['data'][0] : [];

            $plano_nome     = $plano_row['plano']               ?? 'essencial';
            $plano_trial    = $plano_row['trial_ate']            ?? '';
            $lim_usuarios   = (int) ( $plano_row['limite_usuarios']      ?? 3 );
            $lim_campanhas  = (int) ( $plano_row['limite_campanhas_mes'] ?? 0 );
            $lim_instancias = (int) ( $plano_row['limite_instancias']    ?? 1 );

            // Contadores de uso
            $rc_open    = tao_crm_api( "/crm_cards?workspace_id=eq.$ws_id_sel&fechado=eq.false&select=id" );
            $cards_open = $rc_open['ok'] ? count( $rc_open['data'] ?? [] ) : 0;

            $rc_inst    = $ws_id_sel ? tao_crm_api( "/crm_instancias?workspace_id=eq.$ws_id_sel&ativo=eq.true&select=id" ) : [ 'ok' => false ];
            $inst_count = $rc_inst['ok'] ? count( $rc_inst['data'] ?? [] ) : 0;

            $users      = get_users( [ 'meta_key' => 'cbpm_can_access', 'meta_value' => '1' ] );
            $user_count = count( $users );

            $planos = [
                'essencial'    => [ 'nome' => 'Essencial',   'cor' => '#64748b', 'preco' => 'R$ 97/mês',    'usuarios' => 3,  'instancias' => 1, 'campanhas' => 0 ],
                'profissional' => [ 'nome' => 'Profissional','cor' => '#3b82f6', 'preco' => 'R$ 197/mês',   'usuarios' => 10, 'instancias' => 3, 'campanhas' => 5 ],
                'empresarial'  => [ 'nome' => 'Empresarial', 'cor' => '#8b5cf6', 'preco' => 'Personalizado','usuarios' => 0,  'instancias' => 0, 'campanhas' => 0 ],
            ];

            ?>



            <?php if ( ! $ws_id_sel ) : ?>
            <div class="notice notice-warning inline"><p>Selecione um workspace no topo para ver os dados do plano.</p></div>
            <?php else : ?>

            <!-- Plano atual -->

            <div class="crm-plano-card" style="margin-bottom:24px">

                <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px">

                    <span style="background:<?php echo esc_attr($planos[$plano_nome]['cor'] ?? '#64748b'); ?>;color:#fff;padding:4px 12px;border-radius:20px;font-size:13px;font-weight:700">

                        <?php echo esc_html( strtoupper( $planos[$plano_nome]['nome'] ?? $plano_nome ) ); ?>

                    </span>

                    <span style="font-size:14px;color:#475569"><?php echo esc_html( $planos[$plano_nome]['preco'] ?? '' ); ?></span>

                    <?php if ( $plano_trial ) : ?>

                    <span style="font-size:12px;background:#fef9c3;color:#854d0e;padding:2px 8px;border-radius:10px">Trial até <?php echo esc_html( date( 'd/m/Y', strtotime($plano_trial) ) ); ?></span>

                    <?php endif; ?>

                </div>



                <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px">

                    <?php

                    $uso_items = [

                        [ 'label' => 'Usuários ativos', 'uso' => $user_count, 'limite' => $lim_usuarios ],

                        [ 'label' => 'Instâncias WA',   'uso' => $inst_count, 'limite' => $lim_instancias ],

                        [ 'label' => 'Campanhas/mês',   'uso' => 0,           'limite' => $lim_campanhas ],

                    ];

                    foreach ( $uso_items as $item ) :

                        $pct = $item['limite'] > 0 ? min(100, round($item['uso'] / $item['limite'] * 100)) : 0;

                        $cor_bar = $pct >= 90 ? '#dc2626' : ( $pct >= 70 ? '#f59e0b' : '#3b82f6' );

                    ?>

                    <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:12px">

                        <div style="font-size:11px;color:#64748b;text-transform:uppercase;margin-bottom:6px"><?php echo esc_html($item['label']); ?></div>

                        <div style="font-size:20px;font-weight:700;color:#1e293b;margin-bottom:4px">

                            <?php echo esc_html($item['uso']); ?>

                            <?php if ($item['limite'] > 0) echo '<span style="font-size:13px;color:#94a3b8"> / ' . esc_html($item['limite']) . '</span>'; else echo '<span style="font-size:13px;color:#94a3b8"> / ∞</span>'; ?>

                        </div>

                        <?php if ($item['limite'] > 0) : ?>

                        <div class="crm-uso-barra-bg" style="height:4px;background:#e2e8f0;border-radius:2px">

                            <div style="height:4px;background:<?php echo esc_attr($cor_bar); ?>;border-radius:2px;width:<?php echo esc_attr($pct); ?>%"></div>

                        </div>

                        <?php endif; ?>

                    </div>

                    <?php endforeach; ?>

                </div>

            </div>



            <!-- Admin: alterar plano -->

            <h3>Alterar Plano (Admin)</h3>

            <form method="post" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:16px">

                <?php wp_nonce_field( 'tao_crm_set_plano', 'tao_crm_set_plano_nonce' ); ?>

                <input type="hidden" name="workspace_id" value="<?php echo esc_attr($ws_id_sel); ?>">

                <table class="form-table" style="margin:0">

                    <tr>

                        <th style="padding:8px 0">Plano</th>

                        <td><select name="novo_plano" class="regular-text">

                            <?php foreach ( $planos as $key => $pl ) : ?>

                            <option value="<?php echo esc_attr($key); ?>" <?php selected( $plano_nome, $key ); ?>><?php echo esc_html($pl['nome']); ?> — <?php echo esc_html($pl['preco']); ?></option>

                            <?php endforeach; ?>

                        </select></td>

                    </tr>

                    <tr>

                        <th style="padding:8px 0">Trial até</th>

                        <td><input type="date" name="trial_ate" value="<?php echo esc_attr( $plano_trial ? substr($plano_trial,0,10) : '' ); ?>" class="regular-text">

                        <p class="description">Deixe em branco se não for trial.</p></td>

                    </tr>

                    <tr>

                        <th style="padding:8px 0">Limite usuários</th>

                        <td><input type="number" name="limite_usuarios" value="<?php echo esc_attr($lim_usuarios); ?>" min="1" class="small-text"> <span class="description">(0 = ilimitado)</span></td>

                    </tr>

                    <tr>

                        <th style="padding:8px 0">Limite instâncias</th>

                        <td><input type="number" name="limite_instancias" value="<?php echo esc_attr($lim_instancias); ?>" min="1" class="small-text"> <span class="description">(0 = ilimitado)</span></td>

                    </tr>

                    <tr>

                        <th style="padding:8px 0">Campanhas/mês</th>

                        <td><input type="number" name="limite_campanhas_mes" value="<?php echo esc_attr($lim_campanhas); ?>" min="0" class="small-text"> <span class="description">(0 = sem acesso)</span></td>

                    </tr>

                </table>

                <p><input type="submit" class="button button-primary" value="Salvar Plano"></p>

            </form>



            <!-- Tabela comparativa -->

            <h3 style="margin-top:24px">Comparativo de Planos</h3>

            <table style="width:100%;border-collapse:collapse;font-size:13px">

                <thead>

                    <tr style="background:#f1f5f9">

                        <th style="text-align:left;padding:8px 12px;border:1px solid #e2e8f0">Recurso</th>

                        <?php foreach ( $planos as $pl ) : ?><th style="text-align:center;padding:8px 12px;border:1px solid #e2e8f0"><?php echo esc_html($pl['nome']); ?></th><?php endforeach; ?>

                    </tr>

                </thead>

                <tbody>

                    <?php $features = [

                        [ 'Usuários',       'usuarios'   ],

                        [ 'Instâncias WA',  'instancias' ],

                        [ 'Campanhas/mês',  'campanhas'  ],

                    ];

                    foreach ( $features as [$label,$key] ) : ?>

                    <tr>

                        <td style="padding:8px 12px;border:1px solid #e2e8f0"><?php echo esc_html($label); ?></td>

                        <?php foreach ( $planos as $pl ) :

                            $v = $pl[$key] ?? 0; ?>

                        <td style="text-align:center;padding:8px 12px;border:1px solid #e2e8f0"><?php echo $v === 0 ? '∞' : esc_html($v); ?></td>

                        <?php endforeach; ?>

                    </tr>

                    <?php endforeach; ?>

                    <tr>

                        <td style="padding:8px 12px;border:1px solid #e2e8f0;font-weight:700">Preço</td>

                        <?php foreach ( $planos as $pl ) : ?>

                        <td style="text-align:center;padding:8px 12px;border:1px solid #e2e8f0;font-weight:700;color:#1e293b"><?php echo esc_html($pl['preco']); ?></td>

                        <?php endforeach; ?>

                    </tr>

                </tbody>

            </table>

            <!-- Relatório de desempenho -->

            <hr style="margin:24px 0">

            <h3>&#x1F4CA; Relatório de Desempenho</h3>

            <p>Baixe um CSV com o desempenho dos atendentes no período selecionado.</p>

            <form method="get" action="<?php echo admin_url('admin-post.php'); ?>" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">

                <input type="hidden" name="action" value="tao_crm_export_relatorio">

                <input type="hidden" name="workspace_id" value="<?php echo esc_attr($ws_id_sel); ?>">

                <?php wp_nonce_field( 'tao_crm_export_relatorio', '_wpnonce', true, true ); ?>

                <label style="font-size:13px">De: <input type="date" name="de" value="<?php echo esc_attr(date('Y-m-01')); ?>" style="margin-left:4px"></label>

                <label style="font-size:13px">Até: <input type="date" name="ate" value="<?php echo esc_attr(date('Y-m-d')); ?>" style="margin-left:4px"></label>

                <button type="submit" class="button">&#x2B07; Baixar CSV</button>

            </form>

            <?php endif; // ws_id_sel check ?>

        </div>



        <?php elseif ( $tab === 'importar' ) : ?>



        <!-- v1.4.0: Importar CSV -->

        <div class="tao-crm-settings-section" style="max-width:700px">

            <h2>&#x2B06; Importar Leads (CSV)</h2>

            <p>Importe contatos e cards a partir de um arquivo CSV. O arquivo deve ter cabeçalho na primeira linha.</p>



            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:16px;margin-bottom:16px">

                <strong>Colunas aceitas:</strong>

                <code style="display:block;margin-top:8px;padding:8px;background:#1e293b;color:#e2e8f0;border-radius:4px;font-size:12px">nome, whatsapp, email, observacoes</code>

                <p class="description" style="margin-top:8px">A coluna <strong>whatsapp</strong> é usada para deduplicação — leads com o mesmo número não serão duplicados.</p>

            </div>



            <!-- Download template -->

            <p>

                <a href="data:text/csv;charset=utf-8,nome%2Cwhatsapp%2Cemail%2Cobservacoes%0AJo%C3%A3o%20Silva%2C5511999990001%2Cjoao%40email.com%2CLead%20importado"

                   download="template_importacao.csv" class="button">

                    &#x2B07; Baixar template CSV

                </a>

            </p>



            <form id="crm-import-form" enctype="multipart/form-data" style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:20px">

                <table class="form-table" style="margin:0">

                    <tr>

                        <th>Workspace</th>

                        <td>

                            <?php

                            $rw_imp = tao_crm_api( '/crm_workspaces?ativo=eq.true&order=nome.asc' );

                            $ws_imp = $rw_imp['ok'] ? ( $rw_imp['data'] ?? [] ) : [];

                            ?>

                            <select id="crm-import-ws" class="regular-text">

                                <?php foreach ( $ws_imp as $w ) : ?>

                                <option value="<?php echo esc_attr($w['id']); ?>" <?php selected($w['id'], $ws_id_sel); ?>><?php echo esc_html($w['nome']); ?></option>

                                <?php endforeach; ?>

                            </select>

                        </td>

                    </tr>

                    <tr>

                        <th>Pipeline (opcional)</th>

                        <td>

                            <select id="crm-import-pipeline" class="regular-text">

                                <option value="">— Nenhum (só contato) —</option>

                                <?php

                                $rp_imp = tao_crm_api( "/crm_pipelines?workspace_id=eq.$ws_id_sel&order=nome.asc" );

                                foreach ( ($rp_imp['ok'] ? ($rp_imp['data'] ?? []) : []) as $pl ) :

                                ?>

                                <option value="<?php echo esc_attr($pl['id']); ?>"><?php echo esc_html($pl['nome']); ?></option>

                                <?php endforeach; ?>

                            </select>

                            <p class="description">Se selecionado, um card será criado para cada lead no primeiro estágio do pipeline.</p>

                        </td>

                    </tr>

                    <tr>

                        <th>Arquivo CSV</th>

                        <td><input type="file" id="crm-import-file" accept=".csv,text/csv" required></td>

                    </tr>

                </table>

                <p>

                    <button type="button" id="crm-import-btn" class="button button-primary">&#x2B06; Importar</button>

                    <span id="crm-import-status" style="margin-left:12px;font-size:13px"></span>

                </p>

            </form>



            <script>

            (function($){

                $('#crm-import-btn').on('click', function(){

                    var file = $('#crm-import-file')[0].files[0];

                    if (!file){ alert('Selecione um arquivo CSV'); return; }

                    var ws   = $('#crm-import-ws').val();

                    var pipe = $('#crm-import-pipeline').val();

                    var fd   = new FormData();

                    fd.append('action',       'tao_crm_import_csv');

                    fd.append('nonce',        taoCrm.nonce);

                    fd.append('workspace_id', ws);

                    fd.append('pipeline_id',  pipe);

                    fd.append('csv_file',     file);

                    $('#crm-import-status').text('Importando...');

                    $('#crm-import-btn').prop('disabled', true);

                    $.ajax({

                        url:         ajaxurl,

                        type:        'POST',

                        data:        fd,

                        contentType: false,

                        processData: false,

                        success: function(r){

                            if(r.success){

                                var d = r.data;

                                $('#crm-import-status').html(

                                    '<span style="color:#16a34a">&#x2714; Importados: ' + d.importados +

                                    ' | Duplicados ignorados: ' + d.duplicados +

                                    ' | Erros: ' + d.erros + '</span>'

                                );

                            } else {

                                $('#crm-import-status').html('<span style="color:#dc2626">&#x2718; ' + (r.data||'Erro') + '</span>');

                            }

                        },

                        error: function(){ $('#crm-import-status').html('<span style="color:#dc2626">Erro na requisição</span>'); },

                        complete: function(){ $('#crm-import-btn').prop('disabled', false); }

                    });

                });

            })(jQuery);

            </script>

        </div>



        <?php elseif ( $tab === 'metas' ) : ?>

        <!-- v1.5.0: Metas -->

        <div class="tao-crm-settings-section" style="max-width:900px">

            <h2>&#x1F3AF; Metas por Atendente</h2>

            <p>Defina metas mensais de cards fechados e valor de oportunidade para cada atendente.</p>



            <?php

            $mes_m = intval( $_GET['mes'] ?? date( 'n' ) );

            $ano_m = intval( $_GET['ano'] ?? date( 'Y' ) );

            $wp_users_m = get_users( [ 'fields' => [ 'ID', 'display_name' ] ] );

            ?>



            <!-- Seletor de mês/ano -->

            <div style="display:flex;gap:10px;align-items:center;margin-bottom:20px;flex-wrap:wrap">

                <?php

                $meses = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];

                for ( $m = 1; $m <= 12; $m++ ) :

                    $cls = $m === $mes_m ? 'button-primary' : '';

                ?>

                <a href="<?php echo esc_url( tao_crm_settings_url( ['tab'=>'metas','mes'=>$m,'ano'=>$ano_m,'workspace_id'=>$ws_id_sel] ) ); ?>"

                   class="button button-small <?php echo $cls; ?>"><?php echo $meses[$m-1]; ?></a>

                <?php endfor; ?>

                <input type="number" id="crm-meta-ano" value="<?php echo $ano_m; ?>" min="2024" max="2030"

                       style="width:70px;border:1px solid #cbd5e1;border-radius:4px;padding:4px 8px;font-size:13px"

                       onchange="window.location='<?php echo esc_js( tao_crm_settings_url(['tab'=>'metas','mes'=>$mes_m,'workspace_id'=>$ws_id_sel,'ano'=>'']) ); ?>'+this.value">

            </div>



            <!-- Tabela de metas -->

            <table class="wp-list-table widefat fixed" style="font-size:13px">

                <thead>

                    <tr>

                        <th>Atendente</th>

                        <th style="width:130px;text-align:right">Meta Cards</th>

                        <th style="width:150px;text-align:right">Realizado</th>

                        <th style="width:150px;text-align:right">Meta Valor R$</th>

                        <th style="width:150px;text-align:right">Realizado R$</th>

                        <th style="width:80px;text-align:center">%</th>

                        <th style="width:80px"></th>

                    </tr>

                </thead>

                <tbody id="crm-metas-tbody">

                    <?php

                    $rme = tao_crm_api( "/crm_metas?workspace_id=eq.$ws_id_sel&mes=eq.$mes_m&ano=eq.$ano_m" );

                    $metas_map = [];

                    if ( $rme['ok'] ) {

                        foreach ( $rme['data'] ?? [] as $m ) $metas_map[ $m['user_id'] ] = $m;

                    }

                    // Caliza realizados por usuário

                    $de_m  = sprintf( '%04d-%02d-01', $ano_m, $mes_m );

                    $prox_m = $mes_m === 12 ? 1 : $mes_m + 1;

                    $prox_a = $mes_m === 12 ? $ano_m + 1 : $ano_m;

                    $ate_m = sprintf( '%04d-%02d-01', $prox_a, $prox_m );

                    $rc_cards = tao_crm_api( "/crm_cards?workspace_id=eq.$ws_id_sel&status=eq.fechado&criado_em=gte.$de_m&criado_em=lt.$ate_m&select=responsavel_id,valor_oportunidade" );

                    $realizados = [];

                    if ( $rc_cards['ok'] ) {

                        foreach ( $rc_cards['data'] ?? [] as $c ) {

                            $uid = $c['responsavel_id'] ?? 0;

                            if ( ! isset( $realizados[$uid] ) ) $realizados[$uid] = ['cards'=>0,'valor'=>0];

                            $realizados[$uid]['cards']++;

                            $realizados[$uid]['valor'] += floatval( $c['valor_oportunidade'] ?? 0 );

                        }

                    }

                    foreach ( $wp_users_m as $u ) :

                        $uid  = $u->ID;

                        $meta = $metas_map[ $uid ] ?? null;

                        $meta_c = intval( $meta['meta_cards'] ?? 0 );

                        $meta_v = floatval( $meta['meta_valor'] ?? 0 );

                        $real_c = $realizados[$uid]['cards'] ?? 0;

                        $real_v = $realizados[$uid]['valor'] ?? 0;

                        $pct    = $meta_c > 0 ? min( 100, round( $real_c / $meta_c * 100 ) ) : 0;

                        $cor    = $pct >= 100 ? '#16a34a' : ( $pct >= 60 ? '#f59e0b' : '#dc2626' );

                    ?>

                    <tr data-uid="<?php echo esc_attr($uid); ?>">

                        <td><?php echo esc_html( $u->display_name ); ?></td>

                        <td style="text-align:right">

                            <input type="number" class="crm-meta-cards" data-uid="<?php echo $uid; ?>" min="0"

                                   value="<?php echo esc_attr( $meta_c ); ?>"

                                   style="width:70px;text-align:right;border:1px solid #cbd5e1;border-radius:4px;padding:2px 6px;font-size:13px">

                        </td>

                        <td style="text-align:right;font-weight:600"><?php echo $real_c; ?></td>

                        <td style="text-align:right">

                            <input type="number" class="crm-meta-valor" data-uid="<?php echo $uid; ?>" min="0" step="0.01"

                                   value="<?php echo esc_attr( number_format( $meta_v, 2, '.', '' ) ); ?>"

                                   style="width:100px;text-align:right;border:1px solid #cbd5e1;border-radius:4px;padding:2px 6px;font-size:13px">

                        </td>

                        <td style="text-align:right;font-weight:600">R$ <?php echo number_format( $real_v, 2, ',', '.' ); ?></td>

                        <td style="text-align:center">

                            <?php if ( $meta_c > 0 ) : ?>

                            <span style="color:<?php echo $cor; ?>;font-weight:700"><?php echo $pct; ?>%</span>

                            <?php else : ?>

                            <span style="color:#94a3b8">—</span>

                            <?php endif; ?>

                        </td>

                        <td style="text-align:center">

                            <button class="button button-small crm-salvar-meta" data-uid="<?php echo $uid; ?>">Salvar</button>

                            <span class="crm-meta-status" style="font-size:11px;display:block;margin-top:2px"></span>

                        </td>

                    </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>



            <script>

            (function($){

                $('.crm-salvar-meta').on('click', function(){

                    var uid  = $(this).data('uid');

                    var row  = $(this).closest('tr');

                    var $st  = row.find('.crm-meta-status');

                    var mc   = row.find('.crm-meta-cards').val();

                    var mv   = row.find('.crm-meta-valor').val();

                    $(this).prop('disabled', true);

                    crmPost({

                        action:        'tao_crm_save_meta',

                        nonce:         taoCrm.nonce,

                        workspace_id:  '<?php echo esc_js($ws_id_sel); ?>',

                        user_id:       uid,

                        mes:           <?php echo $mes_m; ?>,

                        ano:           <?php echo $ano_m; ?>,

                        meta_cards:    mc,

                        meta_valor:    mv

                    }, function(r){

                        if(r.success){ $st.css('color','#16a34a').text('✔'); }

                        else { $st.css('color','#dc2626').text('✘ '+r.data); }

                        $(row).find('.crm-salvar-meta').prop('disabled',false);

                        setTimeout(function(){ $st.text(''); }, 2500);

                    });

                });

            })(jQuery);

            </script>

        </div>



        <?php elseif ( $tab === 'horario' ) : ?>
        <!-- Horário de Atendimento -->
        <div class="tao-crm-settings-section">
            <h2>&#x23F0; Horário de Atendimento</h2>
            <p style="color:#64748b;font-size:13px">Defina o horário de atendimento por dia da semana. Fora do horário configurado, solicitações de handoff retornam mensagem automática ao cliente.</p>
            <?php
            $h_cfg       = tao_crm_get_horario_ws( $ws_id_sel );
            $dias_labels = [ 0 => 'Domingo', 1 => 'Segunda', 2 => 'Terça', 3 => 'Quarta', 4 => 'Quinta', 5 => 'Sexta', 6 => 'Sábado' ];
            $dias_cfg    = $h_cfg['dias'] ?? [];
            ?>
            <form id="crm-horario-form">
            <table class="form-table">
                <tr><th>Ativo</th><td><label><input type="checkbox" name="ativo" id="crm-h-ativo" <?php checked( $h_cfg['ativo'] ); ?>> Habilitar controle de horário</label></td></tr>
                <tr><th>Fuso horário</th><td>
                    <input type="text" name="timezone" value="<?php echo esc_attr( $h_cfg['timezone'] ?? 'America/Sao_Paulo' ); ?>" class="regular-text" placeholder="America/Sao_Paulo">
                    <p class="description">Ex: America/Sao_Paulo, America/Manaus, America/Fortaleza</p>
                </td></tr>
                <tr><th>Horários por dia</th><td>
                    <table style="border-collapse:collapse;min-width:440px">
                        <thead>
                            <tr style="background:#f1f5f9;font-size:12px;font-weight:600;color:#475569">
                                <th style="padding:6px 10px;text-align:left;border:1px solid #e2e8f0">Dia</th>
                                <th style="padding:6px 10px;text-align:center;border:1px solid #e2e8f0">Aberto</th>
                                <th style="padding:6px 10px;text-align:left;border:1px solid #e2e8f0">Abertura</th>
                                <th style="padding:6px 10px;text-align:left;border:1px solid #e2e8f0">Fechamento</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ( $dias_labels as $d => $label ) :
                            $dc  = $dias_cfg[ (string) $d ] ?? [ 'ativo' => ( $d >= 1 && $d <= 5 ), 'abertura' => '08:00', 'fechamento' => '18:00' ];
                            $row_bg = $dc['ativo'] ? '' : 'background:#f8fafc;color:#94a3b8';
                        ?>
                            <tr style="<?php echo $row_bg; ?>">
                                <td style="padding:6px 10px;border:1px solid #e2e8f0;font-weight:500"><?php echo $label; ?></td>
                                <td style="padding:6px 10px;border:1px solid #e2e8f0;text-align:center">
                                    <input type="checkbox" class="crm-dia-ativo" data-dia="<?php echo $d; ?>"
                                           name="dias[<?php echo $d; ?>][ativo]" value="1"
                                           <?php checked( ! empty( $dc['ativo'] ) ); ?>>
                                </td>
                                <td style="padding:6px 10px;border:1px solid #e2e8f0">
                                    <input type="time" class="crm-dia-time" id="crm-ab-<?php echo $d; ?>"
                                           name="dias[<?php echo $d; ?>][abertura]"
                                           value="<?php echo esc_attr( $dc['abertura'] ?? '08:00' ); ?>"
                                           style="width:110px" <?php echo empty( $dc['ativo'] ) ? 'disabled' : ''; ?>>
                                </td>
                                <td style="padding:6px 10px;border:1px solid #e2e8f0">
                                    <input type="time" class="crm-dia-time" id="crm-fe-<?php echo $d; ?>"
                                           name="dias[<?php echo $d; ?>][fechamento]"
                                           value="<?php echo esc_attr( $dc['fechamento'] ?? '18:00' ); ?>"
                                           style="width:110px" <?php echo empty( $dc['ativo'] ) ? 'disabled' : ''; ?>>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p class="description" style="margin-top:6px">Dias não marcados como "Aberto" são tratados como folga — handoff bloqueado o dia inteiro.</p>
                </td></tr>
                <tr><th>Mensagem fora do horário</th><td>
                    <textarea name="mensagem" rows="3" class="large-text"><?php echo esc_textarea( $h_cfg['mensagem'] ?? '' ); ?></textarea>
                    <p class="description">Enviada ao cliente quando tenta handoff fora do horário.</p>
                </td></tr>
            </table>
            <button type="submit" class="button button-primary">Salvar horário</button>
            <span id="crm-horario-status" style="margin-left:8px;font-size:13px"></span>
            </form>
            <script>
            (function($){
                // Toggle inputs when "Aberto" checkbox changes
                $(document).on('change', '.crm-dia-ativo', function(){
                    var d = $(this).data('dia');
                    var on = $(this).is(':checked');
                    $('#crm-ab-' + d + ', #crm-fe-' + d).prop('disabled', !on);
                    $(this).closest('tr').css({'background': on ? '' : '#f8fafc', 'color': on ? '' : '#94a3b8'});
                });

                $('#crm-horario-form').on('submit', function(e){
                    e.preventDefault();
                    var payload = {
                        action:   'tao_crm_save_horario',
                        nonce:    taoCrm.nonce,
                        ws_id:    <?php echo wp_json_encode( $ws_id_sel ); ?>,
                        ativo:    $('#crm-h-ativo').is(':checked') ? '1' : '',
                        timezone: $('[name="timezone"]').val(),
                        mensagem: $('[name="mensagem"]').val(),
                        dias:     {}
                    };
                    <?php foreach ( array_keys( $dias_labels ) as $d ) : ?>
                    payload.dias[<?php echo $d; ?>] = {
                        ativo:     $('[name="dias[<?php echo $d; ?>][ativo]"]').is(':checked') ? '1' : '',
                        abertura:  $('[name="dias[<?php echo $d; ?>][abertura]"]').val(),
                        fechamento:$('[name="dias[<?php echo $d; ?>][fechamento]"]').val()
                    };
                    <?php endforeach; ?>
                    crmPost(payload, function(r){
                        $('#crm-horario-status').text(r.success ? '✔ Salvo' : '✘ ' + (r.data||'Erro')).css('color', r.success ? '#16a34a' : '#dc2626');
                        setTimeout(function(){ $('#crm-horario-status').text(''); }, 2500);
                    });
                });
            })(jQuery);
            </script>
        </div>

        <?php elseif ( $tab === 'sla' ) : ?>
        <!-- SLA por Estágio -->
        <div class="tao-crm-settings-section">
            <h2>&#x23F1; SLA por Estágio</h2>
            <p style="color:#64748b;font-size:13px">Define quantos minutos um card pode ficar em cada estágio antes de acender o badge de alerta no Kanban.</p>
            <?php
            $rpl_s = tao_crm_api( "/crm_pipelines?workspace_id=eq.$ws_id_sel&ativo=eq.true&order=ordem.asc" );
            foreach ( $rpl_s['ok'] ? ( $rpl_s['data'] ?? [] ) : [] as $pl_s ) :
                $rest_s = tao_crm_api( "/crm_estagios?pipeline_id=eq.{$pl_s['id']}&order=ordem.asc" );
            ?>
            <h3 style="margin-top:16px;font-size:14px;color:#1e293b"><?php echo esc_html( $pl_s['nome'] ); ?></h3>
            <table class="widefat" style="width:auto;margin-bottom:12px">
                <thead><tr><th>Estágio</th><th>Alerta (min)</th><th>Crítico (min)</th><th></th></tr></thead>
                <tbody>
                <?php foreach ( $rest_s['ok'] ? ( $rest_s['data'] ?? [] ) : [] as $e_s ) :
                    if ( in_array( $e_s['tipo'], ['ganho','perdido'] ) ) continue;
                    $sla_m = tao_crm_sla_minutos_estagio( $e_s['id'] );
                ?>
                <tr>
                    <td><?php echo esc_html( $e_s['nome'] ); ?></td>
                    <td><input type="number" class="sla-alerta" min="1" max="2880" value="<?php echo esc_attr( $sla_m ); ?>" style="width:70px" data-stage-id="<?php echo esc_attr( $e_s['id'] ); ?>"></td>
                    <td><input type="number" class="sla-critico" min="1" max="2880" value="<?php echo esc_attr( $sla_m * 2 ); ?>" style="width:70px" data-stage-id="<?php echo esc_attr( $e_s['id'] ); ?>"></td>
                    <td><button class="button button-small sla-save-btn" data-stage-id="<?php echo esc_attr( $e_s['id'] ); ?>">Salvar</button> <span class="sla-status" style="font-size:11px"></span></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endforeach; ?>
            <script>
            (function($){
                $('.sla-save-btn').on('click', function(){
                    var sid = $(this).data('stage-id');
                    var row = $(this).closest('tr');
                    var m   = row.find('.sla-alerta').val();
                    var $st = row.find('.sla-status');
                    crmPost({action:'tao_crm_save_sla_estagio', nonce:taoCrm.nonce, stage_id:sid, minutos:m}, function(r){
                        $st.text(r.success ? '✔ salvo' : '✘').css('color', r.success ? '#16a34a' : '#dc2626');
                        setTimeout(function(){ $st.text(''); }, 2000);
                    });
                });
            })(jQuery);
            </script>
        </div>

        <?php elseif ( $tab === 'logs' ) : ?>
        <!-- Log de Erros -->
        <div class="tao-crm-settings-section">
            <h2>&#x1F6A8; Log de Eventos do Sistema</h2>
            <button class="button" id="crm-log-refresh">&#x21BB; Atualizar</button>
            <button class="button" id="crm-log-clear" style="margin-left:8px;color:#dc2626;border-color:#dc2626">&#x1F5D1; Limpar log</button>
            <div id="crm-log-table" style="margin-top:16px;font-family:monospace;font-size:12px;background:#0f172a;color:#e2e8f0;border-radius:8px;padding:16px;max-height:500px;overflow-y:auto">
                <em style="color:#64748b">Carregando...</em>
            </div>
            <script>
            (function($){
                function loadLog(){
                    crmPost({action:'tao_crm_get_error_log', nonce:taoCrm.nonce}, function(r){
                        var $d = $('#crm-log-table');
                        if(!r.success || !r.data.log || !r.data.log.length){ $d.html('<em style="color:#64748b">Nenhum evento registrado.</em>'); return; }
                        var html = '';
                        r.data.log.forEach(function(e){
                            var color = e.type==='lgpd'?'#f0c04b': e.type==='instance'?'#60a5fa':'#f87171';
                            html += '<div style="border-bottom:1px solid #1e293b;padding:4px 0"><span style="color:'+color+'">['+(e.type||'?')+']</span> <span style="color:#94a3b8">'+e.ts+'</span> '+e.msg+'</div>';
                        });
                        $d.html(html);
                    });
                }
                loadLog();
                $('#crm-log-refresh').on('click', loadLog);
                $('#crm-log-clear').on('click', function(){
                    if(!confirm('Limpar todo o log?')) return;
                    crmPost({action:'tao_crm_clear_error_log', nonce:taoCrm.nonce}, function(){ loadLog(); });
                });
            })(jQuery);
            </script>
        </div>

        <?php elseif ( $tab === 'lgpd' ) : ?>
        <!-- LGPD: Exclusão de Dados -->
        <div class="tao-crm-settings-section">
            <h2>&#x1F512; LGPD &mdash; Exclusão de Dados do Contato</h2>
            <div class="notice notice-warning inline" style="margin:0 0 16px;padding:8px 12px;border-radius:6px">
                <p><strong>&#x26A0; Ação irreversível.</strong> Remove todas as mensagens, cards e dados de contato do número informado.</p>
            </div>
            <table class="form-table">
                <tr><th>WhatsApp do contato</th><td><input type="text" id="crm-lgpd-num" class="regular-text" placeholder="5511999999999"></td></tr>
            </table>
            <button class="button" id="crm-lgpd-btn" style="color:#dc2626;border-color:#dc2626">&#x1F6AB; Excluir dados do contato</button>
            <span id="crm-lgpd-status" style="margin-left:8px;font-size:13px"></span>
            <script>
            (function($){
                $('#crm-lgpd-btn').on('click', function(){
                    var num = $('#crm-lgpd-num').val().trim();
                    if(!num){ alert('Informe o número do contato.'); return; }
                    if(!confirm('ATENÇÃO: Todos os dados do número '+num+' serão excluídos permanentemente. Confirmar?')) return;
                    $(this).prop('disabled', true);
                    crmPost({action:'tao_crm_delete_contact_data', nonce:taoCrm.nonce, whatsapp:num, ws_id:<?php echo wp_json_encode($ws_id_sel); ?>}, function(r){
                        if(r.success){
                            $('#crm-lgpd-status').text('✔ Dados excluídos. Cards removidos: '+r.data.cards_excluidos).css('color','#16a34a');
                            $('#crm-lgpd-num').val('');
                        } else {
                            $('#crm-lgpd-status').text('✘ Erro: '+(r.data||'falha')).css('color','#dc2626');
                        }
                        $('#crm-lgpd-btn').prop('disabled',false);
                    });
                });
            })(jQuery);
            </script>
        </div>

        <?php elseif ( $tab === 'csat' ) : ?>
        <!-- CSAT -->
        <div class="tao-crm-settings-section">
            <h2>&#x2B50; CSAT &mdash; Pesquisa de Satisfação</h2>
            <p style="color:#64748b;font-size:13px">Quando habilitado, uma mensagem é enviada ao cliente automaticamente ao fechar um card como "Negócio Ganho".</p>
            <?php
            $csat_ativo = ! empty( get_option( 'tao_crm_csat_ativo_' . $ws_id_sel ) );
            $csat_msg   = get_option( 'tao_crm_csat_msg_' . $ws_id_sel, 'Como você avalia nosso atendimento? Responda com um número de 1 a 5 ⭐' );
            ?>
            <form id="crm-csat-form">
            <table class="form-table">
                <tr><th>Ativo</th><td><label><input type="checkbox" name="ativo" id="crm-csat-ativo" <?php checked( $csat_ativo ); ?>> Habilitar CSAT</label></td></tr>
                <tr><th>Mensagem enviada ao cliente</th><td>
                    <textarea name="mensagem" rows="3" class="large-text"><?php echo esc_textarea( $csat_msg ); ?></textarea>
                </td></tr>
            </table>
            <button type="submit" class="button button-primary">Salvar</button>
            <span id="crm-csat-status" style="margin-left:8px;font-size:13px"></span>
            </form>
            <script>
            (function($){
                $('#crm-csat-form').on('submit', function(e){
                    e.preventDefault();
                    crmPost({action:'tao_crm_save_csat', nonce:taoCrm.nonce, ws_id:<?php echo wp_json_encode($ws_id_sel); ?>, ativo:$('#crm-csat-ativo').is(':checked')?'1':'', mensagem:$('textarea[name="mensagem"]').val()}, function(r){
                        $('#crm-csat-status').text(r.success?'✔ Salvo':'✘ Erro').css('color', r.success?'#16a34a':'#dc2626');
                        setTimeout(function(){ $('#crm-csat-status').text(''); }, 2500);
                    });
                });
            })(jQuery);
            </script>

            <!-- Dashboard CSAT -->
            <div style="margin-top:28px;padding-top:20px;border-top:1px solid #e2e8f0">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
                    <h3 style="margin:0;font-size:15px">&#x1F4CA; Respostas coletadas</h3>
                    <button class="button" id="crm-csat-load-btn" onclick="crmLoadCsatStats(false)">Carregar</button>
                </div>
                <div id="crm-csat-stats-area" style="min-height:60px">
                    <p style="color:#94a3b8;font-size:13px">Clique em "Carregar" para ver as estatísticas.</p>
                </div>
            </div>
            <script>
            function crmLoadCsatStats(auto) {
                var btn = document.getElementById('crm-csat-load-btn');
                if (btn) btn.disabled = true;
                crmPost({action:'tao_crm_get_csat_stats', nonce:taoCrm.nonce, ws_id:<?php echo wp_json_encode($ws_id_sel); ?>}, function(r) {
                    if (btn) btn.disabled = false;
                    var area = document.getElementById('crm-csat-stats-area');
                    if (!r.success) { area.innerHTML = '<p style="color:#dc2626">Erro ao carregar.</p>'; return; }
                    var d = r.data;
                    if (d.total === 0) { area.innerHTML = '<p style="color:#94a3b8;font-size:13px">Nenhuma resposta coletada ainda.</p>'; return; }
                    var stars = ['1 ⭐','2 ⭐⭐','3 ⭐⭐⭐','4 ⭐⭐⭐⭐','5 ⭐⭐⭐⭐⭐'];
                    var html = '<div style="display:flex;gap:20px;flex-wrap:wrap;margin-bottom:16px">'
                        + '<div style="background:#f0fdf4;border-radius:8px;padding:12px 20px;text-align:center"><div style="font-size:28px;font-weight:700;color:#16a34a">'+d.media+'</div><div style="font-size:11px;color:#64748b">Média ('+d.total+' respostas)</div></div>'
                        + '</div>';
                    html += '<table style="width:100%;max-width:360px;border-collapse:collapse;font-size:13px">'
                        + '<tr><th style="text-align:left;padding:4px 8px;color:#64748b">Nota</th><th style="padding:4px 8px;color:#64748b">Qtd</th><th style="padding:4px 8px;color:#64748b">%</th><th style="padding:4px 8px;color:#64748b">Barra</th></tr>';
                    for (var nota = 5; nota >= 1; nota--) {
                        var qt = d.dist[nota] || 0;
                        var pct = d.total > 0 ? Math.round(qt/d.total*100) : 0;
                        var barColor = nota >= 4 ? '#16a34a' : (nota === 3 ? '#f59e0b' : '#dc2626');
                        html += '<tr><td style="padding:4px 8px">'+stars[nota-1]+'</td><td style="padding:4px 8px;text-align:right">'+qt+'</td><td style="padding:4px 8px;text-align:right">'+pct+'%</td>'
                            + '<td style="padding:4px 8px"><div style="height:8px;width:'+pct+'%;background:'+barColor+';border-radius:4px;min-width:2px"></div></td></tr>';
                    }
                    html += '</table>';
                    if (d.recentes && d.recentes.length) {
                        html += '<h4 style="margin:16px 0 8px;font-size:13px">Últimas respostas</h4>'
                            + '<table style="width:100%;max-width:480px;border-collapse:collapse;font-size:12px">'
                            + '<tr><th style="text-align:left;padding:3px 6px;color:#64748b">WhatsApp</th><th style="padding:3px 6px;color:#64748b">Nota</th><th style="text-align:left;padding:3px 6px;color:#64748b">Data</th></tr>';
                        d.recentes.forEach(function(r) {
                            var num = r.num ? r.num.replace(/(\d{2})(\d{2})(\d{4,5})(\d{4})/, '+$1 ($2) $3-$4') : '-';
                            var em  = r.em ? r.em.substring(0,10) : '-';
                            var nt  = r.nota;
                            var clr = nt >= 4 ? '#16a34a' : (nt === 3 ? '#d97706' : '#dc2626');
                            html += '<tr><td style="padding:3px 6px">'+num+'</td><td style="padding:3px 6px;text-align:center;font-weight:600;color:'+clr+'">'+nt+' ⭐</td><td style="padding:3px 6px">'+em+'</td></tr>';
                        });
                        html += '</table>';
                    }
                    area.innerHTML = html;
                });
            }
            </script>
        </div>

<?php endif; ?>

        </main><!-- .crm-settings-main -->

    </div><!-- .crm-settings-layout -->
    </div><!-- .wrap -->



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

                else { $btn.prop('disabled',false).text('Excluir'); alert('Erro: ' + (resp.data || 'Tente novamente')); }

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

                else { $btn.prop('disabled',false).text('&#x2715;'); alert('Erro: ' + (resp.data || 'Tente novamente')); }

            }, 'json');

        });



        // Deletar estágio

        $(document).on('click', '.tao-crm-del-estagio', function(){

            var estId = $(this).data('est-id');

            var nome  = $(this).data('nome');

            if(!confirm('Excluir estágio "' + nome + '"?\n\nCards neste estágio perderão a associação com ele.')) return;

            var $btn = $(this).prop('disabled', true).text('...');

            $.post(ajaxurl, { action:'tao_crm_delete_estagio', nonce:'<?php echo esc_js( wp_create_nonce( 'tao_crm_nonce' ) ); ?>', est_id:estId }, function(resp){

                if(resp.success){ location.reload(); }

                else { $btn.prop('disabled',false).html('&#x2715;'); alert('Erro: ' + (resp.data || 'Tente novamente')); }

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

