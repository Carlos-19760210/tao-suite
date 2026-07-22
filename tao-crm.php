<?php
/**
 * Plugin Name: TAO CRM
 * Description: Módulo CRM do TAO CRM — pipeline visual com chat WhatsApp nativo
 * Version: 1.1.2
 * Author: Carlo
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'TAO_CRM_VERSION', '1.6.4' );

function tao_crm_is_handoff_msg( $text ) {
    static $kws = [
        'vou te conectar','vou transferir','transferindo para','atendente humano',
        'nossa equipe irá','nossa equipe vai','aguarde atendimento','falar com um atendente',
        'um atendente irá','vou conectar você',
        'fila de atendimento','fila de espera','encaminhar você','encaminhar para um',
        'consultor humano','consultor disponível','consultor irá','atendente disponível',
        'colocar em fila','entrará em contato','entrar em contato em breve',
    ];
    $t = mb_strtolower( $text );
    foreach ( $kws as $k ) { if ( strpos( $t, $k ) !== false ) return true; }
    return false;
}

// Detecta solicitação de atendente humano vinda do USUÁRIO (mensagem entrante)
function tao_crm_is_user_handoff_request( $text ) {
    static $kws = [
        'quero falar com atendente','quero um atendente','quero atendente',
        'falar com atendente','falar com um atendente','falar com humano',
        'falar com uma pessoa','falar com um humano','quero falar com um humano',
        'preciso de atendente','preciso falar com atendente','atendimento humano',
        'quero suporte humano','suporte humano','quero suporte com atendente',
        'me conecta com atendente','conecta com atendente','quero ser atendido',
        'quero falar com alguém','quero falar com alguem',
    ];
    $t = mb_strtolower( $text );
    foreach ( $kws as $k ) { if ( strpos( $t, $k ) !== false ) return true; }
    return false;
}
// ─── MAPEAMENTO @LID → TELEFONE REAL ─────────────────────────────────────────
// Persiste em WP options. Quando o agente atualiza o número no card,
// o vínculo lid → phone é salvo e resolvido automaticamente no dispatch.

function tao_crm_lid_to_phone( $lid_num ) {
    return get_option( 'tao_crm_lid_' . $lid_num, '' );
}

function tao_crm_save_lid_mapping( $lid_num, $real_phone ) {
    if ( $lid_num && $real_phone ) update_option( 'tao_crm_lid_' . $lid_num, $real_phone, false );
}

function tao_crm_is_lid_num( $num ) {
    // @lid numbers são ≥ 13 dígitos e não seguem formato BR (máx 13 com DDI)
    return preg_match( '/^\d{14,}$/', $num );
}

define( 'TAO_CRM_DIR',     plugin_dir_path( __FILE__ ) );
define( 'TAO_CRM_URL',     plugin_dir_url( __FILE__ ) );

require_once TAO_CRM_DIR . 'includes/functions.php';
require_once TAO_CRM_DIR . 'includes/perfis.php';
require_once TAO_CRM_DIR . 'includes/pages/dashboard.php';
require_once TAO_CRM_DIR . 'includes/pages/kanban.php';
require_once TAO_CRM_DIR . 'includes/pages/card.php';
require_once TAO_CRM_DIR . 'includes/pages/settings.php';
require_once TAO_CRM_DIR . 'includes/pages/analise.php';
// tao_crm_page_conversas is defined inline below (no separate file needed)

// ─── CRON: AUTOMAÇÕES ─────────────────────────────────────────────────────────

add_filter( 'cron_schedules', function( $s ) {
    $s['tao_crm_every_minute'] = [ 'interval' => 60, 'display' => 'Every Minute (TAO CRM)' ];
    $s['tao_crm_hourly']       = [ 'interval' => 3600, 'display' => 'Every Hour (TAO CRM)' ];
    return $s;
} );

// Bootstrap: cria arquivos novos que ainda não existem no servidor (roda 1x por admin)
add_action( 'admin_init', function() {
    if ( get_option( 'tao_crm_v140_bootstrap_done' ) ) return;
    if ( ! current_user_can( 'manage_options' ) ) return;
    $dir = TAO_CRM_DIR . 'includes/pages/';
    foreach ( [ 'onboarding.php' ] as $stub ) {
        if ( ! file_exists( $dir . $stub ) ) {
            @file_put_contents( $dir . $stub, '<?php // TAO CRM v1.4.0 placeholder — será substituído via editor ?>' );
        }
    }
    update_option( 'tao_crm_v140_bootstrap_done', '1' );
} );

// Garante execução da fila em qualquer request autenticado (admin page load OU ajax)
// DOING_AJAX foi removido da exclusão: o polling de msgs dispara a fila a cada 4s
add_action( 'shutdown', function() {
    if ( ! is_admin() || ! is_user_logged_in() ) return;
    if ( function_exists( 'tao_crm_processar_fila_fn' ) )      tao_crm_processar_fila_fn();
    if ( function_exists( 'tao_crm_processar_agendadas_fn' ) ) tao_crm_processar_agendadas_fn();
} );

add_action( 'init', function() {
    if ( ! wp_next_scheduled( 'tao_crm_processar_fila' ) ) {
        wp_schedule_event( time(), 'tao_crm_every_minute', 'tao_crm_processar_fila' );
    }
    if ( ! wp_next_scheduled( 'tao_crm_check_instances' ) ) {
        wp_schedule_event( time(), 'tao_crm_hourly', 'tao_crm_check_instances' );
    }
    if ( ! wp_next_scheduled( 'tao_crm_check_lembretes' ) ) {
        wp_schedule_event( time(), 'tao_crm_every_minute', 'tao_crm_check_lembretes' );
    }
    if ( ! wp_next_scheduled( 'tao_crm_check_sem_resposta' ) ) {
        wp_schedule_event( time(), 'tao_crm_hourly', 'tao_crm_check_sem_resposta' );
    }
    if ( ! wp_next_scheduled( 'tao_crm_renovacao_check' ) ) {
        wp_schedule_event( time(), 'tao_crm_hourly', 'tao_crm_renovacao_check' );
    }
    if ( ! wp_next_scheduled( 'tao_crm_processar_agendadas' ) ) {
        wp_schedule_event( time(), 'tao_crm_every_minute', 'tao_crm_processar_agendadas' );
    }
    if ( ! wp_next_scheduled( 'tao_crm_limpeza_semanal' ) ) {
        wp_schedule_event( time(), 'weekly', 'tao_crm_limpeza_semanal' );
    }
    if ( ! wp_next_scheduled( 'tao_crm_backup_semanal' ) ) {
        wp_schedule_event( time(), 'weekly', 'tao_crm_backup_semanal' );
    }
} );

// ─── CRON: BACKUP SEMANAL SUPABASE ───────────────────────────────────────────

add_action( 'tao_crm_backup_semanal', 'tao_crm_executar_backup' );
function tao_crm_executar_backup() {
    $upload = wp_upload_dir();
    $dir    = $upload['basedir'] . '/tao-crm-backups';
    if ( ! is_dir( $dir ) ) {
        wp_mkdir_p( $dir );
        file_put_contents( $dir . '/.htaccess', "Deny from all\n" );
        file_put_contents( $dir . '/index.php', '<?php // Silence is golden' );
    }

    $tabelas = [
        'crm_workspaces'    => 'id,nome,cliente_id,ativo,evolution_instancia,dispatch_key,criado_em',
        'crm_pipelines'     => 'id,workspace_id,nome,ativo,ordem',
        'crm_estagios'      => 'id,pipeline_id,nome,tipo,cor,ordem',
        'crm_cards'         => 'id,workspace_id,pipeline_id,estagio_id,titulo,contato_nome,contato_whatsapp,responsavel_id,fechado,status,criado_em,movido_em,valor_oportunidade',
        'crm_contatos'      => '*',
        'crm_tags'          => '*',
        'crm_cards_tags'    => '*',
        'crm_automacoes'    => '*',
        'crm_msg_templates' => '*',
        'crm_instancias'    => 'id,workspace_id,nome,evolution_instancia,evolution_url,ativo,criado_em',
        'crm_planos'        => '*',
    ];

    $backup = [ 'gerado_em' => gmdate( 'c' ), 'tabelas' => [], 'totais' => [] ];

    foreach ( $tabelas as $tabela => $sel ) {
        $rows   = [];
        $offset = 0;
        do {
            $r     = tao_crm_api( "/$tabela?select=$sel&limit=1000&offset=$offset&order=criado_em.asc" );
            $batch = ( $r['ok'] && is_array( $r['data'] ) ) ? $r['data'] : [];
            $rows  = array_merge( $rows, $batch );
            $offset += 1000;
        } while ( count( $batch ) === 1000 );

        $backup['tabelas'][ $tabela ] = $rows;
        $backup['totais'][ $tabela ]  = count( $rows );
    }

    $file = $dir . '/backup-' . gmdate( 'Y-m-d_H-i' ) . '.json.gz';
    $gz   = gzopen( $file, 'wb9' );
    gzwrite( $gz, wp_json_encode( $backup ) );
    gzclose( $gz );

    // Mantém apenas os 7 backups mais recentes
    $all = glob( $dir . '/backup-*.json.gz' );
    if ( $all ) {
        usort( $all, fn( $a, $b ) => filemtime( $b ) - filemtime( $a ) );
        foreach ( array_slice( $all, 7 ) as $old ) @unlink( $old );
    }

    update_option( 'tao_crm_ultimo_backup', [
        'ts'     => gmdate( 'c' ),
        'file'   => basename( $file ),
        'rows'   => array_sum( $backup['totais'] ),
        'totais' => $backup['totais'],
    ], false );
}

add_action( 'wp_ajax_tao_crm_run_backup', function () {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Acesso negado' );
    tao_crm_executar_backup();
    wp_send_json_success( get_option( 'tao_crm_ultimo_backup', [] ) );
} );

add_action( 'admin_post_tao_crm_download_backup', function () {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Acesso negado' );
    check_admin_referer( 'tao_crm_download_backup' );
    $upload = wp_upload_dir();
    $dir    = $upload['basedir'] . '/tao-crm-backups';
    $files  = glob( $dir . '/backup-*.json.gz' ) ?: [];
    if ( empty( $files ) ) wp_die( 'Nenhum backup disponível. Clique em "Gerar agora" primeiro.' );
    usort( $files, fn( $a, $b ) => filemtime( $b ) - filemtime( $a ) );
    $file = $files[0];
    header( 'Content-Type: application/gzip' );
    header( 'Content-Disposition: attachment; filename="' . basename( $file ) . '"' );
    header( 'Content-Length: ' . filesize( $file ) );
    readfile( $file );
    exit;
} );

// ─── CRON: LIMPEZA SEMANAL ────────────────────────────────────────────────────

add_action( 'tao_crm_limpeza_semanal', 'tao_crm_executar_limpeza_semanal' );
function tao_crm_executar_limpeza_semanal() {
    $resultado = [];

    // Mensagens do N8N > 7 dias
    $r = tao_crm_api( '/historico?criado_em=lt.' . gmdate( 'Y-m-d\TH:i:s\Z', strtotime( '-7 days' ) ), 'DELETE' );
    $resultado['historico'] = $r['ok'] ? 'ok' : ( $r['error'] ?? 'erro' );

    // Fila de automações executadas há mais de 30 dias
    $r = tao_crm_api( '/crm_automacoes_fila?executado_em=lt.' . gmdate( 'Y-m-d\TH:i:s\Z', strtotime( '-30 days' ) ) . '&executado_em=not.is.null', 'DELETE' );
    $resultado['automacoes_fila'] = $r['ok'] ? 'ok' : ( $r['error'] ?? 'erro' );

    // Mensagens agendadas já enviadas há mais de 30 dias
    $r = tao_crm_api( '/crm_msgs_agendadas?enviado=eq.true&enviado_em=lt.' . gmdate( 'Y-m-d\TH:i:s\Z', strtotime( '-30 days' ) ), 'DELETE' );
    $resultado['msgs_agendadas'] = $r['ok'] ? 'ok' : ( $r['error'] ?? 'erro' );

    update_option( 'tao_limpeza_ultimo_resultado', [ 'ts' => gmdate( 'c' ), 'resultado' => $resultado ], false );
}

// ─── MENUS ───────────────────────────────────────────────────────────────────

// Capability dinâmica: gestores recebem 'tao_crm_gestor' sem role WP dedicada
add_filter( 'user_has_cap', 'tao_crm_dynamic_caps', 10, 3 );
function tao_crm_dynamic_caps( $allcaps, $caps, $args ) {
    if ( empty( $allcaps['cbpm_cliente'] ) ) return $allcaps;
    foreach ( $caps as $cap ) {
        if ( $cap === 'tao_crm_gestor' && tao_crm_is_gestor() ) {
            $allcaps['tao_crm_gestor'] = true;
        }
    }
    return $allcaps;
}

add_action( 'admin_menu', 'tao_crm_register_menus', 20 );
function tao_crm_register_menus() {
    if ( ! function_exists( 'cbpm_can_access' ) || ! cbpm_can_access() ) return;

    // Admin WP não tem a cap 'cbpm_cliente' (role dos atendentes) — sem o fallback
    // p/ manage_options, o wp-admin nega as páginas do CRM ao próprio admin (403).
    $cap          = current_user_can( 'manage_options' ) ? 'manage_options' : 'cbpm_cliente';
    $cap_gestor   = current_user_can( 'manage_options' ) ? 'manage_options' : 'tao_crm_gestor';
    $cap_adm      = 'manage_options';

    add_menu_page( 'TAO CRM', 'TAO CRM <span id="tao-crm-badge" style="background:#ef4444;color:#fff;font-size:9px;font-weight:700;border-radius:10px;padding:1px 6px;margin-left:4px;vertical-align:middle;display:none"></span>', $cap, 'tao-crm', 'tao_crm_page_dashboard', 'dashicons-networking', 56 );

    // Mesmo slug do pai → aparece como primeiro item sem duplicar
    add_submenu_page( 'tao-crm', 'Dashboard', 'Dashboard', $cap,        'tao-crm',              'tao_crm_page_dashboard' );
    add_submenu_page( 'tao-crm', 'Inbox',      'Inbox <span id="tao-crm-inbox-badge" style="background:#ef4444;color:#fff;font-size:9px;font-weight:700;border-radius:10px;padding:1px 5px;margin-left:3px;vertical-align:middle;display:none"></span>', $cap, 'tao-crm-inbox', 'tao_crm_page_inbox' );
    add_submenu_page( 'tao-crm', 'Conversas', 'Conversas',  $cap, 'tao-crm-conversas',   'tao_crm_page_conversas_wrap' );
    add_submenu_page( 'tao-crm', 'Kanban',    'Kanban',     $cap, 'tao-crm-kanban',      'tao_crm_page_kanban_full' );
    add_submenu_page( 'tao-crm', 'Contatos',  'Contatos',   $cap, 'tao-crm-contatos',    'tao_crm_page_contatos' );

    // Configurações: visível para admins e gestores
    add_submenu_page( 'tao-crm', 'Configurações', 'Configurações', $cap_gestor, 'tao-crm-settings',   'tao_crm_page_settings' );
    add_submenu_page( 'tao-crm', 'Workspaces',         '↳ Workspaces',          $cap_gestor, 'tao-crm-workspaces',  'tao_crm_settings_workspaces' );
    add_submenu_page( 'tao-crm', 'Pipelines e Estágios','↳ Pipelines e Estágios',$cap_gestor, 'tao-crm-pipelines',   'tao_crm_settings_pipelines' );
    add_submenu_page( 'tao-crm', 'Campos',              '↳ Campos',              $cap_gestor, 'tao-crm-campos',      'tao_crm_settings_campos' );
    add_submenu_page( 'tao-crm', 'Automações',          '↳ Automações',          $cap_gestor, 'tao-crm-automacoes',  'tao_crm_settings_automacoes' );

    // Admin-only
    if ( current_user_can( 'manage_options' ) ) {
        add_submenu_page( 'tao-crm', 'Onboarding',       '↳ Onboarding',       $cap_adm, 'tao-crm-onboarding', 'tao_crm_page_onboarding' );
        add_submenu_page( 'tao-crm', 'Docs de Webhooks', '↳ Docs de Webhooks', $cap_adm, 'tao-crm-docs-wh',   'tao_crm_page_docs_webhooks' );
    }
}

// Wrappers que forçam a aba correta em settings.php
function tao_crm_page_inbox()             { $_GET['view'] = 'inbox';      tao_crm_page_kanban(); }
function tao_crm_page_kanban_full()       { tao_crm_page_kanban(); }
function tao_crm_page_conversas_wrap()    { tao_crm_page_conversas(); }

// ─── PÁGINA: CONVERSAS ATIVAS NO CHATBOT ─────────────────────────────────────
function tao_crm_page_conversas() {
    if ( ! function_exists( 'cbpm_can_access' ) || ! cbpm_can_access() ) {
        echo '<div class="wrap"><p>Acesso negado.</p></div>'; return;
    }
    $ws_id      = sanitize_text_field( $_GET['workspace_id'] ?? '' );
    $ws         = tao_crm_get_workspace( $ws_id ?: null );
    if ( ! $ws ) { echo '<div class="wrap"><div class="notice notice-warning"><p>Nenhum workspace configurado.</p></div></div>'; return; }
    $ws_id      = $ws['id'];
    $workspaces = tao_crm_get_workspaces();
    $nonce      = wp_create_nonce( 'tao_crm_nonce' );
    ?>
    <div class="wrap" style="max-width:1200px">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:10px">
        <div style="display:flex;align-items:center;gap:12px">
            <h1 style="margin:0;font-size:20px">🗨️ Conversas Ativas no Chatbot</h1>
            <?php if ( count( $workspaces ) > 1 ) : ?>
            <form method="get" style="margin:0">
                <input type="hidden" name="page" value="tao-crm-conversas">
                <select name="workspace_id" onchange="this.form.submit()" style="padding:4px 8px;border-radius:4px;border:1px solid #ccc;font-size:13px">
                    <?php foreach ( $workspaces as $wk ) : ?>
                    <option value="<?php echo esc_attr( $wk['id'] ); ?>" <?php selected( $wk['id'], $ws_id ); ?>><?php echo esc_html( $wk['nome'] ); ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
            <?php else : ?>
            <span style="font-size:13px;color:#666;background:#f0f0f0;padding:3px 10px;border-radius:12px"><?php echo esc_html( $ws['nome'] ); ?></span>
            <?php endif; ?>
        </div>
        <div style="display:flex;align-items:center;gap:8px">
            <span id="crm-conv-status" style="font-size:12px;color:#888">Carregando…</span>
            <button id="crm-conv-refresh" class="button">⟳ Atualizar</button>
        </div>
    </div>
    <div id="crm-conv-list" style="display:flex;flex-direction:column;gap:10px;min-height:120px">
        <div style="text-align:center;padding:40px;color:#999;font-size:14px">Buscando conversas…</div>
    </div>
    </div>
    <style>
    .crm-conv-card{background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:14px 16px;display:flex;align-items:flex-start;gap:14px;transition:box-shadow .15s}
    .crm-conv-card:hover{box-shadow:0 2px 8px rgba(0,0,0,.08)}
    .crm-conv-avatar{width:42px;height:42px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}
    .crm-conv-body{flex:1;min-width:0}
    .crm-conv-header{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:4px}
    .crm-conv-nome{font-weight:600;font-size:14px;color:#1e293b}
    .crm-conv-phone{font-size:12px;color:#64748b}
    .crm-conv-badge{font-size:11px;font-weight:600;padding:2px 8px;border-radius:10px;white-space:nowrap}
    .badge-crm{background:#dcfce7;color:#166534}.badge-novo{background:#dbeafe;color:#1e40af}.badge-card{background:#fef3c7;color:#92400e}
    .crm-conv-preview{font-size:13px;color:#475569;margin:4px 0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:480px}
    .crm-conv-meta{font-size:11px;color:#94a3b8;display:flex;align-items:center;gap:10px;margin-top:4px}
    .crm-conv-actions{display:flex;gap:6px;flex-shrink:0;align-items:flex-start;flex-wrap:wrap}
    .crm-conv-btn{font-size:12px!important;padding:4px 10px!important;height:auto!important;line-height:1.5!important;white-space:nowrap}
    .crm-conv-btn-interceptar{border-color:#0ea5e9!important;color:#0ea5e9!important}
    .crm-conv-btn-crm{border-color:#16a34a!important;color:#16a34a!important;font-weight:600!important}
    .crm-conv-btn-card{border-color:#d97706!important;color:#d97706!important}
    .crm-conv-empty{text-align:center;padding:60px 20px;color:#94a3b8}
    </style>
    <script>
    (function(){
        var WS_ID=<?php echo wp_json_encode($ws_id);?>,NONCE=<?php echo wp_json_encode($nonce);?>,AJAX=<?php echo wp_json_encode(admin_url('admin-ajax.php'));?>,CARD_BASE=<?php echo wp_json_encode(admin_url('admin.php?page=tao-crm-kanban&action=card&id='));?>,timer=null;
        function ago(d){if(!d)return'';var s=Math.floor((Date.now()-new Date(d).getTime())/1000);if(s<60)return s+'s';if(s<3600)return Math.floor(s/60)+'min';if(s<86400)return Math.floor(s/3600)+'h';return Math.floor(s/86400)+'d';}
        function esc(s){return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
        function jse(o){return JSON.stringify(o).replace(/</g,'\\u003c').replace(/>/g,'\\u003e').replace(/&/g,'\\u0026').replace(/'/g,'\\u0027');}
        function render(data){
            var list=document.getElementById('crm-conv-list'),cvs=data.conversas||[],tot=data.total||0;
            document.getElementById('crm-conv-status').textContent=tot+' conversa'+(tot!==1?'s':'')+' ativa'+(tot!==1?'s':'')+' · '+new Date().toLocaleTimeString('pt-BR',{hour:'2-digit',minute:'2-digit'});
            if(!cvs.length){list.innerHTML='<div class="crm-conv-empty"><div style="font-size:48px;margin-bottom:12px">🤖</div><div style="font-size:16px;margin-bottom:6px;color:#64748b">Nenhuma conversa ativa no momento</div><div style="font-size:13px">Quando um cliente iniciar conversa com o chatbot, ela aparecerá aqui.</div></div>';return;}
            var html='';
            cvs.forEach(function(c){
                var isCrm=!!c.crm_contato,hasCard=!!c.card_ativo,nome=esc(c.nome||c.phone),phone=esc(c.phone);
                var preview=c.ultima_msg?(c.ultima_role==='assistant'?'🤖 ':'👤 ')+esc(c.ultima_msg):'<em style="color:#cbd5e1">Sem mensagens</em>';
                var badges='';
                if(isCrm){var cl=c.crm_contato.classificacao?' · '+esc(c.crm_contato.classificacao):'',at=c.crm_contato.total_atendimentos?' · '+c.crm_contato.total_atendimentos+' atend.':'';badges+='<span class="crm-conv-badge badge-crm">✅ Cliente CRM'+cl+at+'</span>';}
                else badges+='<span class="crm-conv-badge badge-novo">🆕 Novo contato</span>';
                if(hasCard)badges+='<span class="crm-conv-badge badge-card">📋 Card ativo</span>';
                var actions='';
                if(hasCard)actions+='<a href="'+CARD_BASE+esc(c.card_ativo.id)+'" class="button crm-conv-btn crm-conv-btn-card">📋 Ver card</a>';
                else if(isCrm)actions+='<button class="button crm-conv-btn crm-conv-btn-crm" onclick="crmInterceptar('+jse(c)+')">🤝 Criar Card (CRM)</button>';
                else actions+='<button class="button crm-conv-btn crm-conv-btn-interceptar" onclick="crmInterceptar('+jse(c)+')">🤝 Interceptar</button>';
                html+='<div class="crm-conv-card"><div class="crm-conv-avatar" style="background:'+(isCrm?'#dcfce7':'#dbeafe')+'">'+(isCrm?'👤':'💬')+'</div><div class="crm-conv-body"><div class="crm-conv-header"><span class="crm-conv-nome">'+nome+'</span><span class="crm-conv-phone">📱 '+phone+'</span>'+badges+'</div><div class="crm-conv-preview">'+preview+'</div><div class="crm-conv-meta"><span>💬 '+c.msg_count+' msg'+(c.msg_count!==1?'s':'')+'</span>'+(c.criado_em?'<span>🕐 '+esc(ago(c.criado_em))+'</span>':'')+'</div></div><div class="crm-conv-actions">'+actions+'</div></div>';
            });
            list.innerHTML=html;
        }
        function load(){
            var fd=new FormData();fd.append('action','tao_crm_conversas_ativas');fd.append('nonce',NONCE);fd.append('ws_id',WS_ID);
            fetch(AJAX,{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json();}).then(function(r){if(r.success)render(r.data);else document.getElementById('crm-conv-status').textContent='Erro: '+(r.data||'?');}).catch(function(){document.getElementById('crm-conv-status').textContent='Erro de rede';});
        }
        window.crmInterceptar=function(c){
            if(!confirm((c.crm_contato?'Criar card CRM para ':'Interceptar conversa de ')+c.nome+'?\n\nO chatbot será pausado e você assumirá o atendimento.'))return;
            var fd=new FormData();fd.append('action','tao_crm_interceptar_conversa');fd.append('nonce',NONCE);fd.append('ws_id',WS_ID);fd.append('phone',c.phone);fd.append('nome',c.nome||'');
            fetch(AJAX,{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json();}).then(function(r){if(r.success)window.location.href=r.data.url;else alert('Erro: '+(r.data||'Não foi possível interceptar'));});
        };
        document.getElementById('crm-conv-refresh').addEventListener('click',function(){clearInterval(timer);load();timer=setInterval(load,30000);});
        load();timer=setInterval(load,30000);
    })();
    </script>
    <?php
}
function tao_crm_settings_workspaces()    { $_GET['tab'] = 'workspaces';  tao_crm_page_settings(); }
function tao_crm_settings_pipelines()     { $_GET['tab'] = 'pipelines';   tao_crm_page_settings(); }
function tao_crm_settings_campos()        { $_GET['tab'] = 'campos';      tao_crm_page_settings(); }
function tao_crm_settings_automacoes()    { $_GET['tab'] = 'automacoes';  tao_crm_page_settings(); }
function tao_crm_page_onboarding() {
    $f = TAO_CRM_DIR . 'includes/pages/onboarding.php';
    if ( file_exists( $f ) ) { include $f; }
    else { echo '<div class="wrap"><div class="notice notice-warning"><p>Wizard de onboarding não disponível. <a href="' . admin_url('admin.php?page=tao-crm-settings&tab=workspaces') . '">Ir para Configurações</a>.</p></div></div>'; }
}
function tao_crm_page_campos_direct()     { $_GET['tab'] = 'campos';      tao_crm_page_settings(); }
function tao_crm_page_contatos()          { tao_crm_ajax_page_contatos(); }
function tao_crm_ajax_page_contatos() {
    if ( ! function_exists( 'cbpm_can_access' ) || ! cbpm_can_access() ) { echo '<p>Acesso negado.</p>'; return; }
    include TAO_CRM_DIR . 'includes/pages/contatos.php';
}

function tao_crm_page_docs_webhooks() {
    if ( ! current_user_can( 'manage_options' ) ) { echo '<p>Acesso negado.</p>'; return; }
    $dispatch_url = site_url( '/wp-json/tao-crm/v1/dispatch' );
    $lead_url     = site_url( '/wp-json/tao-crm/v1/lead-to-card' );
    ?>
    <div class="wrap tao-crm-wrap">
    <div class="tao-crm-topbar"><h1>&#x1F4DA; Documentação — Webhooks &amp; APIs</h1></div>
    <div style="max-width:860px">

    <div class="tao-crm-settings-section" style="margin-bottom:20px">
        <h2>&#x1F4E5; Endpoint: Receber Mensagens (dispatch)</h2>
        <p><strong>URL:</strong> <code><?php echo esc_html( $dispatch_url ); ?></code></p>
        <p><strong>Método:</strong> POST &bull; <strong>Content-Type:</strong> application/json &bull; <strong>Header:</strong> <code>X-Tao-Key: &lt;dispatch_key&gt;</code></p>
        <p>Recebe eventos da Evolution API (mensagens WhatsApp entrantes e saintes). A chave pode ser a global (opção <code>tao_crm_dispatch_key</code>) ou a <code>dispatch_key</code> do workspace específico em <code>crm_workspaces</code>.</p>
        <h3>Payload de exemplo (mensagem entrante)</h3>
        <pre style="background:#1e293b;color:#e2e8f0;padding:14px;border-radius:6px;overflow-x:auto;font-size:12px"><?php echo esc_html( json_encode([
            'event'    => 'messages.upsert',
            'instance' => 'TAO-Neo',
            'data'     => [
                'key'         => [ 'remoteJid' => '5511999999999@s.whatsapp.net', 'fromMe' => false ],
                'message'     => [ 'conversation' => 'Olá, quero informações!' ],
                'pushName'    => 'Carlos Silva',
                'messageType' => 'conversation',
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ); ?></pre>
    </div>

    <div class="tao-crm-settings-section" style="margin-bottom:20px">
        <h2>&#x2795; Endpoint: Criar Card (lead-to-card)</h2>
        <p><strong>URL:</strong> <code><?php echo esc_html( $lead_url ); ?></code></p>
        <p><strong>Método:</strong> POST &bull; <strong>Content-Type:</strong> application/json &bull; <strong>Header:</strong> <code>X-Tao-Key: &lt;dispatch_key&gt;</code></p>
        <h3>Payload de exemplo</h3>
        <pre style="background:#1e293b;color:#e2e8f0;padding:14px;border-radius:6px;overflow-x:auto;font-size:12px"><?php echo esc_html( json_encode([
            'workspace_id' => 'uuid-do-workspace',
            'nome'         => 'Carlos Silva',
            'whatsapp'     => '5511999999999',
            'titulo'       => 'Lead via formulário',
            'pipeline_id'  => null,
            'estagio_id'   => null,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ); ?></pre>
        <p style="color:#64748b;font-size:13px"><em>Se <code>pipeline_id</code> ou <code>estagio_id</code> forem null, o sistema detecta automaticamente o pipeline ativo e o estágio handoff.</em></p>
    </div>

    <div class="tao-crm-settings-section" style="margin-bottom:20px">
        <h2>&#x1F511; Webhooks de Saída — Verificação HMAC</h2>
        <p>Quando um webhook de saída tem <code>secret</code> configurado, o TAO CRM assina cada requisição com um header <code>X-Tao-Signature: hmac-sha256=HASH</code>.</p>
        <h3>Verificação em PHP</h3>
        <pre style="background:#1e293b;color:#e2e8f0;padding:14px;border-radius:6px;overflow-x:auto;font-size:12px"><?php echo esc_html( '<?php
$secret    = \'seu_secret_do_webhook\';
$payload   = file_get_contents(\'php://input\');
$sig_raw   = $_SERVER[\'HTTP_X_TAO_SIGNATURE\'] ?? \'\';
$sig_hash  = str_replace(\'hmac-sha256=\', \'\', $sig_raw);
$expected  = hash_hmac(\'sha256\', $payload, $secret);

if (!hash_equals($expected, $sig_hash)) {
    http_response_code(401);
    exit(\'Signature inválida\');
}

$data = json_decode($payload, true);
// processar $data...' ); ?></pre>
        <h3>Verificação em Node.js</h3>
        <pre style="background:#1e293b;color:#e2e8f0;padding:14px;border-radius:6px;overflow-x:auto;font-size:12px"><?php echo esc_html( 'const crypto = require(\'crypto\');

function verify(secret, payload, sigHeader) {
    const hash = crypto.createHmac(\'sha256\', secret)
        .update(payload).digest(\'hex\');
    const expected = \'hmac-sha256=\' + hash;
    return crypto.timingSafeEqual(
        Buffer.from(expected), Buffer.from(sigHeader)
    );
}' ); ?></pre>
    </div>

    <div class="tao-crm-settings-section">
        <h2>&#x1F4CB; Eventos de Saída Disponíveis</h2>
        <table style="width:100%;border-collapse:collapse;font-size:13px">
            <thead><tr style="background:#f1f5f9">
                <th style="text-align:left;padding:8px 12px;border:1px solid #e2e8f0">Evento</th>
                <th style="text-align:left;padding:8px 12px;border:1px solid #e2e8f0">Disparado quando</th>
            </tr></thead>
            <tbody>
            <?php $eventos = [
                [ 'card_criado',   'Um novo card é criado no pipeline' ],
                [ 'card_movido',   'Card é movido entre estágios (drag-and-drop ou automação)' ],
                [ 'card_fechado',  'Card é fechado como ganho ou perdido' ],
                [ 'card_reaberto', 'Card fechado é reaberto por um gestor' ],
                [ 'msg_recebida',  'Mensagem WhatsApp entrante salva no card' ],
                [ 'msg_enviada',   'Mensagem WhatsApp sainte salva no card' ],
            ];
            foreach ( $eventos as [$ev, $desc] ) : ?>
            <tr>
                <td style="padding:8px 12px;border:1px solid #e2e8f0"><code><?php echo esc_html($ev); ?></code></td>
                <td style="padding:8px 12px;border:1px solid #e2e8f0"><?php echo esc_html($desc); ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p style="color:#64748b;font-size:12px;margin-top:12px">Configure webhooks de saída em Configurações → Webhooks.</p>
    </div>

    </div><!-- max-width -->
    </div><!-- wrap -->
    <?php
}

add_action( 'wp_ajax_tao_crm_save_contato', 'tao_crm_ajax_save_contato' );
function tao_crm_ajax_save_contato() {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    if ( ! function_exists( 'cbpm_can_access' ) || ! cbpm_can_access() ) wp_send_json_error( 'no access' );

    $id           = sanitize_text_field( $_POST['id'] ?? '' );
    $workspace_id = sanitize_text_field( $_POST['workspace_id'] ?? '' );

    $data = array_filter( [
        'nome'         => sanitize_text_field( $_POST['nome'] ?? '' ),
        'whatsapp'     => preg_replace( '/\D/', '', sanitize_text_field( $_POST['whatsapp'] ?? '' ) ),
        'email'        => sanitize_email( $_POST['email'] ?? '' ),
        'cpf'          => sanitize_text_field( $_POST['cpf'] ?? '' ),
        'cep'          => preg_replace( '/\D/', '', sanitize_text_field( $_POST['cep'] ?? '' ) ),
        'logradouro'   => sanitize_text_field( $_POST['logradouro'] ?? '' ),
        'numero'       => sanitize_text_field( $_POST['numero'] ?? '' ),
        'complemento'  => sanitize_text_field( $_POST['complemento'] ?? '' ),
        'bairro'       => sanitize_text_field( $_POST['bairro'] ?? '' ),
        'cidade'       => sanitize_text_field( $_POST['cidade'] ?? '' ),
        'classificacao'=> sanitize_text_field( $_POST['classificacao'] ?? '' ),
        'observacoes'  => sanitize_textarea_field( $_POST['observacoes'] ?? '' ),
    ], fn( $v ) => $v !== '' );

    if ( $id ) {
        $r = tao_crm_api( "/crm_contatos?id=eq.$id", 'PATCH', $data,
            [ 'Prefer' => 'return=representation' ] );
    } else {
        if ( ! $workspace_id || empty( $data['nome'] ) || empty( $data['whatsapp'] ) ) {
            wp_send_json_error( 'Nome e WhatsApp s&atilde;o obrigat&oacute;rios' );
        }
        $data['workspace_id'] = $workspace_id;
        $r = tao_crm_api( '/crm_contatos', 'POST', $data,
            [ 'Prefer' => 'return=representation' ] );
    }

    if ( $r['ok'] ) {
        $saved = is_array( $r['data'] ) ? ( $r['data'][0] ?? $r['data'] ) : [];
        wp_send_json_success( [ 'contato' => $saved ] );
    } else {
        wp_send_json_error( $r['error'] ?? 'Erro ao salvar contato' );
    }
}

// ─── AJAX: PERFIL 360° DO CONTATO ────────────────────────────────────────────

add_action( 'wp_ajax_tao_crm_contato_perfil', 'tao_crm_ajax_contato_perfil' );
function tao_crm_ajax_contato_perfil() {
    while ( ob_get_level() > 0 ) ob_end_clean();   // descarta BOM/saída espúria antes do JSON
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    if ( ! function_exists( 'cbpm_can_access' ) || ! cbpm_can_access() ) wp_send_json_error( 'no access' );

    $contato_id = sanitize_text_field( $_POST['contato_id'] ?? '' );
    $whatsapp   = preg_replace( '/\D/', '', sanitize_text_field( $_POST['whatsapp'] ?? '' ) );
    if ( ! $contato_id && ! $whatsapp ) wp_send_json_error( 'dados insuficientes' );

    $out = [ 'cards' => [], 'pedidos' => [], 'leads' => [] ];

    // Cards CRM
    if ( $whatsapp ) {
        $rc = tao_crm_api( "/crm_cards?contato_whatsapp=eq.$whatsapp&order=criado_em.desc&select=id,titulo,contato_nome,estagio_id,fechado,criado_em,movido_em&limit=20" );
        $out['cards'] = $rc['ok'] ? ( $rc['data'] ?? [] ) : [];
    }

    // Pedidos (phone armazenado como dígitos ou com @s.whatsapp.net)
    if ( $whatsapp ) {
        $num_enc = rawurlencode( $whatsapp );
        $rp = tao_crm_api( "/pedidos?phone=ilike.*$num_enc*&order=criado_em.desc&select=id,nome_cliente,status,valor_total,itens,tipo_entrega,criado_em&limit=20" );
        $out['pedidos'] = $rp['ok'] ? ( $rp['data'] ?? [] ) : [];
    }

    // Leads
    if ( $whatsapp ) {
        $num_enc = rawurlencode( $whatsapp );
        $rl = tao_crm_api( "/leads?phone=ilike.*$num_enc*&order=criado_em.desc&select=id,nome,status,interesse,criado_em&limit=20" );
        $out['leads'] = $rl['ok'] ? ( $rl['data'] ?? [] ) : [];
    }

    // Estágios (para resolver nome do estagio_id dos cards)
    $ids = array_filter( array_column( $out['cards'], 'estagio_id' ) );
    if ( $ids ) {
        $re = tao_crm_api( '/crm_estagios?id=in.(' . implode( ',', array_unique( $ids ) ) . ')&select=id,nome,cor' );
        $estagios = [];
        if ( $re['ok'] ) foreach ( $re['data'] ?? [] as $e ) $estagios[ $e['id'] ] = $e;
        foreach ( $out['cards'] as &$c ) {
            $e = $estagios[ $c['estagio_id'] ] ?? null;
            $c['estagio_nome'] = $e['nome'] ?? '—';
            $c['estagio_cor']  = $e['cor']  ?? '#6b7280';
        }
        unset( $c );
    }

    wp_send_json_success( $out );
}

// ─── ASSETS ──────────────────────────────────────────────────────────────────

// Adiciona body class 'tao-crm-page' em todas as páginas do plugin (usado pelo CSS mobile)
add_filter( 'admin_body_class', function( $classes ) {
    $screen = get_current_screen();
    if ( $screen && ( strpos( $screen->id, 'tao-crm' ) !== false || strpos( $screen->id, 'tao_crm' ) !== false ) ) {
        $classes .= ' tao-crm-page';
    }
    return $classes;
} );

add_action( 'admin_enqueue_scripts', 'tao_crm_enqueue_notif_badge' );
function tao_crm_enqueue_notif_badge() {
    if ( ! function_exists( 'cbpm_can_access' ) || ! cbpm_can_access() ) return;
    $ws = function_exists( 'tao_crm_get_workspace' ) ? tao_crm_get_workspace() : null;
    if ( ! $ws ) return;
    $nonce  = wp_create_nonce( 'tao_crm_nonce' );
    $ws_id  = esc_js( $ws['id'] );
    $ajax   = esc_js( admin_url( 'admin-ajax.php' ) );
    wp_add_inline_script( 'jquery', <<<JS
(function(){
    if(typeof taoCrm !== 'undefined' && taoCrm.ws_id) return; // já carregado pelo script principal
    var wsId='{$ws_id}', nonce='{$nonce}', ajaxUrl='{$ajax}';
    if(!wsId) return;
    function doPoll(){
        var fd=new FormData(); fd.append('action','tao_crm_notif_count'); fd.append('nonce',nonce); fd.append('workspace_id',wsId);
        fetch(ajaxUrl,{method:'POST',body:fd,credentials:'same-origin'})
        .then(function(r){return r.json();})
        .then(function(resp){
            var c=(resp.success&&resp.data)?resp.data.count||0:0;
            ['tao-crm-badge','tao-crm-inbox-badge'].forEach(function(id){
                var el=document.getElementById(id);
                if(!el)return;
                el.textContent=c; el.style.display=c>0?'inline-block':'none';
            });
            var t=document.title.replace(/^\(\d+\)\s*/,'');
            document.title=c>0?'('+c+') '+t:t;
        }).catch(function(){});
    }
    document.addEventListener('DOMContentLoaded',function(){ doPoll(); setInterval(doPoll,60000); });
})();
JS
    );
}

add_action( 'admin_enqueue_scripts', 'tao_crm_enqueue_assets' );
function tao_crm_enqueue_assets( $hook ) {
    // Carrega em qualquer página do CRM (tao-crm, tao-crm-inbox, tao-crm-campos, tao-crm-settings)
    if ( strpos( $hook, 'tao-crm' ) === false && strpos( $hook, 'tao_crm' ) === false ) return;

    $_crm_css = TAO_CRM_DIR . 'assets/crm-style.css';
    $_crm_js  = TAO_CRM_DIR . 'assets/crm-script.js';
    $_ver_css = file_exists( $_crm_css ) ? filemtime( $_crm_css ) : TAO_CRM_VERSION;
    $_ver_js  = file_exists( $_crm_js )  ? filemtime( $_crm_js )  : TAO_CRM_VERSION;
    wp_enqueue_style( 'tao-crm-style', TAO_CRM_URL . 'assets/crm-style.css', [], $_ver_css );
    wp_enqueue_script( 'tao-crm-script', TAO_CRM_URL . 'assets/crm-script.js', [ 'jquery' ], $_ver_js, true );

    $ws_notif = function_exists( 'tao_crm_get_workspace' ) ? tao_crm_get_workspace() : null;
    wp_localize_script( 'tao-crm-script', 'taoCrm', [
        'ajax_url'     => admin_url( 'admin-ajax.php' ),
        'nonce'        => wp_create_nonce( 'tao_crm_nonce' ),
        'supabase_url' => function_exists( 'cbpm_supabase_url' ) ? cbpm_supabase_url() : get_option( 'cbpm_supabase_url', '' ),
        'supabase_key' => function_exists( 'cbpm_supabase_key' ) ? cbpm_supabase_key() : get_option( 'cbpm_supabase_key', '' ),
        // Ficha do card abre SEMPRE no portal /robos/ (não no wp-admin)
        'card_base_url'=> function_exists( 'cbpm_url' )
            ? cbpm_url( 'crm-kanban', [ 'action' => 'card', 'id' => '' ] )
            : admin_url( 'admin.php?page=tao-crm-kanban&action=card&id=' ),
        'adminUrl'     => admin_url(),
        'ws_id'        => $ws_notif['id'] ?? '',
    ] );
}

// ─── AUTOMAÇÕES: HELPERS E CRON ──────────────────────────────────────────────

function tao_crm_renderizar_mensagem( $tpl, $card, $vals ) {
    $txt = str_replace(
        [ '{nome}', '{telefone}', '{titulo}' ],
        [ $card['contato_nome'] ?? '', $card['contato_whatsapp'] ?? '', $card['titulo'] ?? '' ],
        $tpl
    );
    return preg_replace_callback( '/\{campo:([a-z0-9_]+)\}/', function( $m ) use ( $vals ) {
        return $vals[ $m[1] ] ?? '';
    }, $txt );
}

function tao_crm_get_card_valores_por_chave( $card_id ) {
    $rv = tao_crm_api( "/crm_cards_valores?card_id=eq.$card_id" );
    if ( ! $rv['ok'] || empty( $rv['data'] ) ) return [];
    $ids = array_column( $rv['data'], 'campo_id' );
    $rc  = tao_crm_api( '/crm_campos_definicao?id=in.(' . implode( ',', $ids ) . ')&select=id,chave' );
    $map = [];
    foreach ( ( $rc['ok'] ? ( $rc['data'] ?? [] ) : [] ) as $d ) { $map[ $d['id'] ] = $d['chave']; }
    $result = [];
    foreach ( $rv['data'] as $v ) {
        if ( isset( $map[ $v['campo_id'] ] ) ) $result[ $map[ $v['campo_id'] ] ] = $v['valor'];
    }
    return $result;
}

function tao_crm_executar_automacao_item( $auto, $card_id ) {
    $rc = tao_crm_api( "/crm_cards?id=eq.$card_id&select=contato_nome,contato_whatsapp,titulo,workspace_id,estagio_id,instancia_id,pipeline_id,fechado" );
    if ( ! $rc['ok'] || empty( $rc['data'] ) ) return [ 'ok' => false, 'detalhe' => 'Card não encontrado' ];
    $card = $rc['data'][0];

    switch ( $auto['acao'] ) {
        case 'enviar_mensagem':
            if ( empty( $auto['mensagem'] ) ) return [ 'ok' => false, 'detalhe' => 'Mensagem vazia' ];
            $evo_cfg = tao_crm_get_evo_creds( $card );
            if ( ! $evo_cfg ) return [ 'ok' => false, 'detalhe' => 'Sem Evolution configurado' ];
            $texto = tao_crm_renderizar_mensagem( $auto['mensagem'], $card, tao_crm_get_card_valores_por_chave( $card_id ) );
            $sent  = tao_crm_evolution_send_with_retry( $evo_cfg, $card['contato_whatsapp'], $texto );
            if ( $sent ) {
                tao_crm_api( '/crm_mensagens', 'POST', [
                    'card_id'        => $card_id,
                    'workspace_id'   => $card['workspace_id'],
                    'direcao'        => 'out',
                    'tipo'           => 'text',
                    'conteudo'       => $texto,
                    'remetente_nome' => 'Automação',
                    'enviado_em'     => gmdate( 'c' ),
                ], [ 'Prefer' => 'return=minimal' ] );
            }
            return $sent ? [ 'ok' => true, 'detalhe' => 'Enviado' ] : [ 'ok' => false, 'detalhe' => 'Falha ao enviar via Evolution' ];

        case 'mover_fase':
            if ( empty( $auto['para_estagio_id'] ) ) return [ 'ok' => false, 'detalhe' => 'Fase destino não definida' ];
            $de = $card['estagio_id'];
            if ( $de === $auto['para_estagio_id'] ) return [ 'ok' => true, 'detalhe' => 'Já no estágio' ];
            $r = tao_crm_api( "/crm_cards?id=eq.$card_id", 'PATCH', [
                'estagio_id' => $auto['para_estagio_id'],
                'movido_em'  => gmdate( 'c' ),
            ] );
            if ( ! $r['ok'] ) return [ 'ok' => false, 'detalhe' => $r['error'] ];
            tao_crm_api( '/crm_cards_historico', 'POST', [
                'card_id'         => $card_id,
                'de_estagio_id'   => $de,
                'para_estagio_id' => $auto['para_estagio_id'],
                'usuario_id'      => 0,
            ] );
            tao_crm_cancelar_fila( $card_id, $de );
            tao_crm_disparar_automacoes( $card_id, $auto['para_estagio_id'], 'entrar_fase' );
            tao_crm_disparar_automacoes( $card_id, $auto['para_estagio_id'], 'tempo_na_fase' );
            return [ 'ok' => true, 'detalhe' => 'Movido' ];

        case 'atribuir_responsavel':
            if ( empty( $auto['responsavel_id'] ) ) return [ 'ok' => false, 'detalhe' => 'Responsável não definido' ];
            $r = tao_crm_api( "/crm_cards?id=eq.$card_id", 'PATCH', [ 'responsavel_id' => intval( $auto['responsavel_id'] ) ] );
            return $r['ok'] ? [ 'ok' => true, 'detalhe' => 'Atribuído' ] : [ 'ok' => false, 'detalhe' => $r['error'] ];

        case 'notificar_email':
            if ( empty( $auto['email_destino'] ) ) return [ 'ok' => false, 'detalhe' => 'Email destino não definido' ];
            $assunto = '[TAO CRM] ' . ( $card['titulo'] ?? $card['contato_nome'] );
            $corpo   = ! empty( $auto['mensagem'] )
                ? tao_crm_renderizar_mensagem( $auto['mensagem'], $card, tao_crm_get_card_valores_por_chave( $card_id ) )
                : "Card '{$card['titulo']}' requer atenção.";
            $ok = wp_mail( $auto['email_destino'], $assunto, $corpo );
            return $ok ? [ 'ok' => true, 'detalhe' => 'Email enviado' ] : [ 'ok' => false, 'detalhe' => 'Falha ao enviar email' ];

        case 'fechar_perdido':
            // Fecha o card como perdido (ex.: Última Tentativa sem resposta após N min)
            if ( ! empty( $card['fechado'] ) ) return [ 'ok' => true, 'detalhe' => 'Card já fechado' ];
            if ( empty( $card['pipeline_id'] ) ) return [ 'ok' => false, 'detalhe' => 'Card sem pipeline' ];
            $rp = tao_crm_api( "/crm_estagios?pipeline_id=eq.{$card['pipeline_id']}&tipo=eq.perdido&limit=1" );
            if ( ! $rp['ok'] || empty( $rp['data'] ) ) return [ 'ok' => false, 'detalhe' => 'Pipeline sem estágio do tipo perdido' ];
            $perdido_id = $rp['data'][0]['id'];
            $de         = $card['estagio_id'];
            $r = tao_crm_api( "/crm_cards?id=eq.$card_id", 'PATCH', [
                'estagio_id' => $perdido_id,
                'movido_em'  => gmdate( 'c' ),
                'fechado'    => true,
                'status'     => 'fechado',
            ] );
            if ( ! $r['ok'] ) return [ 'ok' => false, 'detalhe' => $r['error'] ];
            // Motivo da perda: usa o campo "mensagem" da automação (permite alinhar com a
            // lista padrão de motivos, ex. "Não responde os contatos"); fallback genérico.
            $motivo_auto = trim( (string) ( $auto['mensagem'] ?? '' ) );
            tao_crm_api( '/crm_cards_historico', 'POST', [
                'card_id'         => $card_id,
                'de_estagio_id'   => $de,
                'para_estagio_id' => $perdido_id,
                'usuario_id'      => 0,
                'motivo'          => $motivo_auto !== '' ? $motivo_auto : 'Automação: ' . ( $auto['nome'] ?? 'encerrado por inatividade' ),
                'obs'             => 'Automação: ' . ( $auto['nome'] ?? '' ),
            ] );
            tao_crm_cancelar_fila( $card_id );
            if ( function_exists( 'tao_crm_fire_webhook' ) ) {
                tao_crm_fire_webhook( $card['workspace_id'], 'card_fechado_perdido', [ 'card_id' => $card_id, 'motivo' => 'automacao' ] );
            }
            return [ 'ok' => true, 'detalhe' => 'Card fechado como perdido' ];

        case 'atribuir_responsavel_rr':
            $rr = tao_crm_api( "/crm_round_robin?workspace_id=eq.{$card['workspace_id']}&limit=1" );
            if ( ! $rr['ok'] || empty( $rr['data'] ) ) return [ 'ok' => false, 'detalhe' => 'Round-robin não configurado' ];
            $rr_rec   = $rr['data'][0];
            $user_ids = $rr_rec['user_ids'] ?? [];
            if ( is_string( $user_ids ) ) $user_ids = json_decode( $user_ids, true ) ?: [];
            if ( empty( $user_ids ) ) return [ 'ok' => false, 'detalhe' => 'Nenhum atendente no round-robin' ];
            $idx      = intval( $rr_rec['next_idx'] ?? 0 ) % count( $user_ids );
            $next_idx = ( $idx + 1 ) % count( $user_ids );
            $resp_id  = intval( $user_ids[ $idx ] );
            tao_crm_api( "/crm_round_robin?id=eq.{$rr_rec['id']}", 'PATCH', [ 'next_idx' => $next_idx ] );
            $r = tao_crm_api( "/crm_cards?id=eq.$card_id", 'PATCH', [ 'responsavel_id' => $resp_id ] );
            return $r['ok'] ? [ 'ok' => true, 'detalhe' => "Atribuído (round-robin) ao user $resp_id" ] : [ 'ok' => false, 'detalhe' => $r['error'] ];
    }
    return [ 'ok' => false, 'detalhe' => 'Ação desconhecida' ];
}

/**
 * $force_immediate = true → sai_fase: executa inline sem passar pela fila
 * delay = 0 → executa imediatamente; delay > 0 → agenda na fila
 */
function tao_crm_disparar_automacoes( $card_id, $estagio_id, $tipo, $force_immediate = false, $ws_id = null ) {
    if ( ! $estagio_id ) return;
    $ra = tao_crm_api( "/crm_automacoes?estagio_id=eq.$estagio_id&tipo=eq.$tipo&ativo=eq.true&order=ordem.asc" );
    if ( ! $ra['ok'] || empty( $ra['data'] ) ) return;
    $now     = time();
    $proximo = null; // calculado lazy só quando encontrar enviar_mensagem
    foreach ( $ra['data'] as $auto ) {
        $delay = intval( $auto['delay_minutos'] ?? 0 );
        // Restrição de horário comercial aplica-se somente a envios de mensagem
        $fora_horario = false;
        if ( ! $force_immediate && ( $auto['acao'] ?? '' ) === 'enviar_mensagem' ) {
            if ( $proximo === null ) {
                if ( ! $ws_id ) {
                    $rc    = tao_crm_api( "/crm_cards?id=eq.$card_id&select=workspace_id&limit=1" );
                    $ws_id = ( $rc['ok'] && ! empty( $rc['data'] ) ) ? ( $rc['data'][0]['workspace_id'] ?? null ) : null;
                }
                $proximo = $ws_id ? tao_crm_proximo_horario_comercial( $ws_id ) : $now;
            }
            $fora_horario = ( $proximo > $now );
        }
        if ( $force_immediate || ( $delay === 0 && ! $fora_horario ) ) {
            // Dedup via transient: impede re-disparo imediato da mesma automação no mesmo card
            if ( ! $force_immediate ) {
                $dk = 'tao_crm_auto_' . md5( $card_id . $auto['id'] );
                if ( get_transient( $dk ) ) continue;
                set_transient( $dk, 1, 300 );
            }
            tao_crm_executar_automacao_item( $auto, $card_id );
        } else {
            // delay > 0 OU enviar_mensagem fora do horário comercial → enfileira
            $executar_em = ( $delay === 0 ) ? $proximo : $now + $delay * 60;
            $rq = tao_crm_api( "/crm_automacoes_fila?automacao_id=eq.{$auto['id']}&card_id=eq.$card_id&executado_em=is.null&limit=1" );
            if ( $rq['ok'] && ! empty( $rq['data'] ) ) continue;
            tao_crm_api( '/crm_automacoes_fila', 'POST', [
                'automacao_id' => $auto['id'],
                'card_id'      => $card_id,
                'estagio_id'   => $estagio_id,
                'executar_em'  => gmdate( 'c', $executar_em ),
            ] );
        }
    }
}

function tao_crm_cancelar_fila( $card_id, $estagio_id = null ) {
    $filter = $estagio_id ? "&estagio_id=eq.$estagio_id" : '';
    tao_crm_api( "/crm_automacoes_fila?card_id=eq.$card_id$filter&executado_em=is.null", 'DELETE' );
}

add_action( 'tao_crm_processar_fila', 'tao_crm_processar_fila_fn' );
function tao_crm_processar_fila_fn() {
    // Lock global: impede execuções simultâneas em requests paralelos.
    // Não é 100% atômico no MySQL, mas o claim por item abaixo garante a segurança real.
    $lock_val = uniqid( 'fila_', true );
    if ( false !== get_transient( 'tao_crm_fila_lock' ) ) return;
    set_transient( 'tao_crm_fila_lock', $lock_val, 55 );
    // Confirma que este processo ganhou o lock (reduz race condition do get+set)
    if ( get_transient( 'tao_crm_fila_lock' ) !== $lock_val ) return;

    // Usa formato Z (ex: 2026-06-12T23:00:00Z) — evita o + do fuso que quebra a URL do Supabase
    $agora = gmdate( 'Y-m-d\TH:i:s\Z' );
    $r = tao_crm_api( "/crm_automacoes_fila?executado_em=is.null&executar_em=lte.$agora&limit=50&order=executar_em.asc" );
    if ( ! $r['ok'] || empty( $r['data'] ) ) {
        delete_transient( 'tao_crm_fila_lock' );
        return;
    }
    $executados = []; // dedup por card_id|automacao_id neste ciclo
    foreach ( $r['data'] as $item ) {
        $dedup_key = $item['card_id'] . '|' . $item['automacao_id'];
        if ( isset( $executados[ $dedup_key ] ) ) {
            tao_crm_api( "/crm_automacoes_fila?id=eq.{$item['id']}", 'PATCH', [
                'executado_em' => gmdate( 'c' ), 'resultado' => 'skip', 'detalhe' => 'Entrada duplicada na fila',
            ] );
            continue;
        }
        $ra = tao_crm_api( "/crm_automacoes?id=eq.{$item['automacao_id']}&ativo=eq.true" );
        if ( ! $ra['ok'] || empty( $ra['data'] ) ) {
            tao_crm_api( "/crm_automacoes_fila?id=eq.{$item['id']}", 'PATCH', [
                'executado_em' => gmdate( 'c' ), 'resultado' => 'skip', 'detalhe' => 'Automação inativa ou removida',
            ] );
            continue;
        }
        $auto = $ra['data'][0];
        // Horário comercial: pula sem marcar — item permanece na fila para próxima janela
        if ( ( $auto['acao'] ?? '' ) === 'enviar_mensagem' ) {
            $fila_ws_id = $auto['workspace_id'] ?? null;
            if ( $fila_ws_id && ! tao_crm_esta_em_horario( $fila_ws_id ) ) continue;
        }
        if ( in_array( $auto['tipo'], [ 'entrar_fase', 'tempo_na_fase' ] ) ) {
            $rcc = tao_crm_api( "/crm_cards?id=eq.{$item['card_id']}&select=estagio_id" );
            if ( ! $rcc['ok'] || empty( $rcc['data'] ) || $rcc['data'][0]['estagio_id'] !== $item['estagio_id'] ) {
                tao_crm_api( "/crm_automacoes_fila?id=eq.{$item['id']}", 'PATCH', [
                    'executado_em' => gmdate( 'c' ), 'resultado' => 'skip', 'detalhe' => 'Card saiu do estágio',
                ] );
                continue;
            }
        }
        // Claim atômico: PATCH com filtro executado_em=is.null + return=representation.
        // O PostgreSQL serializa UPDATEs concorrentes na mesma linha — só um processo
        // vence; o outro recebe array vazio e é descartado, prevenindo envio duplicado.
        $claim = tao_crm_api(
            "/crm_automacoes_fila?id=eq.{$item['id']}&executado_em=is.null",
            'PATCH',
            [ 'executado_em' => gmdate( 'c' ), 'resultado' => 'processando', 'detalhe' => '' ],
            [ 'Prefer' => 'return=representation' ]
        );
        if ( ! $claim['ok'] || empty( $claim['data'] ) ) continue; // Outro processo venceu
        $executados[ $dedup_key ] = true;
        $res = tao_crm_executar_automacao_item( $auto, $item['card_id'] );
        tao_crm_api( "/crm_automacoes_fila?id=eq.{$item['id']}", 'PATCH', [
            'resultado' => $res['ok'] ? 'ok' : 'erro',
            'detalhe'   => $res['detalhe'] ?? '',
        ] );
    }
    delete_transient( 'tao_crm_fila_lock' );
}

function tao_crm_processar_agendadas_fn() {
    do_action( 'tao_crm_processar_agendadas' );
}

// ─── AJAX: STATUS WHATSAPP ───────────────────────────────────────────────────

add_action( 'wp_ajax_tao_crm_wa_status', 'tao_crm_ajax_wa_status' );
function tao_crm_ajax_wa_status() {
    while ( ob_get_level() > 0 ) ob_end_clean();   // descarta BOM/saída espúria antes do JSON
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    if ( ! function_exists( 'cbpm_can_access' ) || ! cbpm_can_access() ) wp_send_json_error( 'Acesso negado' );

    $ws_id = sanitize_text_field( $_POST['workspace_id'] ?? '' );
    if ( ! $ws_id ) wp_send_json_error( 'workspace_id obrigatório' );

    $cache_key = 'tao_wa_status_' . md5( $ws_id );
    $cached    = get_transient( $cache_key );
    if ( $cached !== false ) {
        wp_send_json_success( $cached );
    }

    $ri = tao_crm_api( "/crm_instancias?workspace_id=eq.$ws_id&ativo=eq.true&select=id,nome,evolution_instancia,evolution_url,evolution_key" );
    if ( ! $ri['ok'] || empty( $ri['data'] ) ) {
        wp_send_json_success( [] );
    }

    $result = [];
    foreach ( $ri['data'] as $inst ) {
        $url = rtrim( $inst['evolution_url'] ?? '', '/' );
        $key = $inst['evolution_key']      ?? '';
        $nom = $inst['evolution_instancia'] ?? '';
        if ( ! $url || ! $nom ) {
            $result[] = [ 'nome' => $inst['nome'] ?? $nom, 'instancia' => $nom, 'state' => 'unknown' ];
            continue;
        }
        $r = wp_remote_get( "$url/instance/connectionState/$nom", [
            'headers' => [ 'apikey' => $key ],
            'timeout' => 5,
        ] );
        if ( is_wp_error( $r ) ) {
            $result[] = [ 'nome' => $inst['nome'] ?? $nom, 'instancia' => $nom, 'state' => 'error' ];
            continue;
        }
        $body  = json_decode( wp_remote_retrieve_body( $r ), true );
        $state = $body['state'] ?? ( $body['instance']['state'] ?? 'unknown' );
        $result[] = [ 'nome' => $inst['nome'] ?? $nom, 'instancia' => $nom, 'state' => $state ];
    }

    set_transient( $cache_key, $result, 60 );
    wp_send_json_success( $result );
}

// ─── AJAX: CONTAGEM DE NOTIFICAÇÕES ─────────────────────────────────────────

add_action( 'wp_ajax_tao_crm_notif_count', 'tao_crm_ajax_notif_count' );
function tao_crm_ajax_notif_count() {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    if ( ! function_exists( 'cbpm_can_access' ) || ! cbpm_can_access() ) wp_send_json_error( 'Acesso negado' );

    $ws_id = sanitize_text_field( $_POST['workspace_id'] ?? '' );
    if ( ! $ws_id ) { wp_send_json_success( [ 'count' => 0 ] ); }

    // Handoff abertos = clientes aguardando atendente humano
    $r = tao_crm_api( "/crm_cards?workspace_id=eq.$ws_id&atendimento_humano=eq.true&fechado=eq.false&select=id&limit=200" );
    $total = $r['ok'] ? count( $r['data'] ?? [] ) : 0;

    // Cards nunca lidos (nova mensagem, operador ainda não viu)
    $r2 = tao_crm_api( "/crm_cards?workspace_id=eq.$ws_id&atendimento_humano=eq.true&fechado=eq.false&ultima_leitura_em=is.null&select=id&limit=200" );
    $nao_lidos = $r2['ok'] ? count( $r2['data'] ?? [] ) : 0;

    wp_send_json_success( [ 'count' => $total, 'nao_lidos' => $nao_lidos ] );
}

/**
 * Regra de produto: todo negócio GANHO no Funil de Vendas cruza para o Pós-vendas.
 * Move o card para o 1º estágio do pipeline de pós-vendas (se configurado e diferente do atual),
 * registra histórico, dispara webhook e o evento de venda (tao_caixa_card_ganho).
 * @return string|false  id do estágio de pós-vendas se cruzou; false se não há pós-vendas (caller fecha no lugar).
 */
function tao_crm_cruzar_para_pos_vendas( $card_id, array $card, $de_estagio, $motivo = '' ) {
    $ws = $card['workspace_id'] ?? '';
    if ( ! $ws ) return false;
    $pos_pl_id = get_option( 'tao_crm_pos_vendas_pipeline_' . $ws, '' );
    if ( ! $pos_pl_id ) {
        $rall   = tao_crm_api( "/crm_pipelines?workspace_id=eq.$ws&ativo=eq.true&order=ordem.asc&limit=2" );
        $all_pl = $rall['ok'] ? ( $rall['data'] ?? [] ) : [];
        if ( count( $all_pl ) >= 2 ) $pos_pl_id = $all_pl[1]['id'];
    }
    if ( ! $pos_pl_id || $pos_pl_id === ( $card['pipeline_id'] ?? '' ) ) return false;
    $rps = tao_crm_api( "/crm_estagios?pipeline_id=eq.$pos_pl_id&order=ordem.asc&limit=1" );
    if ( ! $rps['ok'] || empty( $rps['data'] ) ) return false;
    $pos_stage_id = $rps['data'][0]['id'];
    $r = tao_crm_api( "/crm_cards?id=eq.$card_id", 'PATCH', [
        'pipeline_id'        => $pos_pl_id,
        'estagio_id'         => $pos_stage_id,
        'movido_em'          => gmdate( 'c' ),
        'atendimento_humano' => false,
    ] );
    if ( ! $r['ok'] ) return false;
    if ( $de_estagio ) tao_crm_api( '/crm_cards_historico', 'POST', [
        'card_id'         => $card_id,
        'de_estagio_id'   => $de_estagio,
        'para_estagio_id' => $pos_stage_id,
        'usuario_id'      => get_current_user_id(),
        'motivo'          => $motivo ?: 'Negócio ganho → Pós-vendas',
    ] );
    tao_crm_fire_webhook( $ws, 'card_fechado_ganho', [ 'card_id' => $card_id ] );
    do_action( 'tao_caixa_card_ganho', $card_id, $ws );
    return $pos_stage_id;
}

// ─── Responsável: quem altera o card assume a responsabilidade ───────────────
// Regra (Carlos): qualquer alteração no card → o autor vira o responsável.
// Só age se houver usuário logado e se ele for diferente do responsável atual.
function tao_crm_assumir_responsavel( $card_id ) {
    if ( ! $card_id ) return;
    $uid = get_current_user_id();
    if ( ! $uid ) return;
    if ( function_exists( 'cbpm_can_access' ) && ! cbpm_can_access() ) return;

    $rc = tao_crm_api( "/crm_cards?id=eq.$card_id&select=responsavel_id&limit=1" );
    if ( ! $rc['ok'] || empty( $rc['data'] ) ) return;
    $atual = intval( $rc['data'][0]['responsavel_id'] ?? 0 );
    if ( $atual === $uid ) return;

    $u  = wp_get_current_user();
    $de = '—';
    if ( $atual ) { $du = get_userdata( $atual ); if ( $du ) $de = $du->display_name; }

    tao_crm_api( "/crm_cards?id=eq.$card_id", 'PATCH', [ 'responsavel_id' => $uid ] );
    tao_crm_api( '/crm_cards_historico', 'POST', [
        'card_id'    => $card_id,
        'usuario_id' => $uid,
        'motivo'     => "Responsável: {$de} → {$u->display_name} (alterou o card)",
        'criado_em'  => gmdate( 'c' ),
    ] );
}
// Pré-gancho (prioridade 1, antes do handler) nas ações que MEXEM no card.
// Exclui as explícitas (save_responsavel, transferir_card) e mensagem/anexo (já fazem inline).
foreach ( [
    'move_card', 'save_valor', 'fechar_card', 'reabrir_card', 'save_nota', 'update_card_info',
    'set_card_tags', 'save_lembrete', 'complete_lembrete', 'delete_lembrete',
    'save_valor_oportunidade', 'save_desconto', 'save_comentario', 'delete_comentario',
    'save_card_item', 'delete_card_item', 'save_msg_agendada', 'enviar_orcamento_formula',
] as $_acao_card ) {
    add_action( "wp_ajax_tao_crm_$_acao_card", function () {
        if ( ! check_ajax_referer( 'tao_crm_nonce', 'nonce', false ) ) return;
        $cid = sanitize_text_field( $_POST['card_id'] ?? '' );
        if ( $cid ) tao_crm_assumir_responsavel( $cid );
    }, 1 );
}

// ─── AJAX: MOVER CARD ────────────────────────────────────────────────────────

add_action( 'wp_ajax_tao_crm_move_card', 'tao_crm_ajax_move_card' );
function tao_crm_ajax_move_card() {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    if ( ! function_exists( 'cbpm_can_access' ) || ! cbpm_can_access() ) wp_send_json_error( 'Acesso negado' );

    $card_id    = sanitize_text_field( $_POST['card_id']    ?? '' );
    $estagio_id = sanitize_text_field( $_POST['estagio_id'] ?? '' );
    if ( ! $card_id || ! $estagio_id ) wp_send_json_error( 'Dados inválidos' );

    $rc = tao_crm_api( "/crm_cards?id=eq.$card_id&select=estagio_id,pipeline_id,workspace_id" );
    $card_atual  = ( $rc['ok'] && ! empty( $rc['data'] ) ) ? $rc['data'][0] : [];
    $de_estagio  = $card_atual['estagio_id'] ?? null;

    // Persiste JÁ os valores preenchidos no modal — se a validação abaixo bloquear o move,
    // o que o usuário digitou não se perde (antes, era descartado e a crítica se repetia).
    $_vals_post = [];
    foreach ( (array) ( $_POST['valores'] ?? [] ) as $_pc => $_pv ) {
        $_pc = sanitize_text_field( $_pc );
        $_pv = sanitize_text_field( $_pv );
        if ( $_pc && $_pv !== '' ) $_vals_post[ $_pc ] = $_pv;
    }
    if ( $_vals_post ) tao_crm_salvar_campos_card( $card_id, $_vals_post );

    // ── EXCLUSIVO PÓS-VENDAS: exige os campos obrigatórios da fase ATUAL antes de avançar ──
    //    (regra só do funil de Pós-vendas; os demais funis seguem a regra padrão abaixo, inalterada)
    if ( $de_estagio && $de_estagio !== $estagio_id ) {
        $_pv = get_option( 'tao_crm_pos_vendas_pipeline_' . ( $card_atual['workspace_id'] ?? '' ), '' );
        if ( ! $_pv && ! empty( $card_atual['workspace_id'] ) ) {
            $_rpl = tao_crm_api( "/crm_pipelines?workspace_id=eq.{$card_atual['workspace_id']}&ativo=eq.true&order=ordem.asc&select=id&limit=2" );
            $_apl = $_rpl['ok'] ? ( $_rpl['data'] ?? [] ) : [];
            if ( count( $_apl ) >= 2 ) $_pv = $_apl[1]['id'];
        }
        if ( $_pv && ( $card_atual['pipeline_id'] ?? '' ) === $_pv ) {
            $_rco  = tao_crm_api( "/crm_campos_estagio?estagio_id=eq.$de_estagio&na_entrada=eq.true&obrigatorio=eq.true" );
            $_cobr = $_rco['ok'] ? ( $_rco['data'] ?? [] ) : [];
            if ( $_cobr ) {
                $_cids = array_column( $_cobr, 'campo_id' );
                $_rv   = tao_crm_api( "/crm_cards_valores?card_id=eq.$card_id&campo_id=in.(" . implode( ',', $_cids ) . ")&select=campo_id,valor" );
                $_vals = [];
                foreach ( ( $_rv['ok'] ? ( $_rv['data'] ?? [] ) : [] ) as $_v ) $_vals[ $_v['campo_id'] ] = $_v['valor'];
                foreach ( (array) ( $_POST['valores'] ?? [] ) as $_c => $_vv ) { $_c = sanitize_text_field( $_c ); if ( $_vv !== '' && $_vv !== null ) $_vals[ $_c ] = $_vv; }
                $_faltam = [];
                foreach ( $_cobr as $_cf ) { $_cid = $_cf['campo_id']; $_vx = $_vals[ $_cid ] ?? ''; if ( $_vx === '' || $_vx === null ) $_faltam[] = $_cid; }
                if ( $_faltam ) {
                    $_rcd   = tao_crm_api( '/crm_campos_definicao?id=in.(' . implode( ',', $_faltam ) . ')&select=nome' );
                    $_nomes = array_map( function ( $d ) { return trim( str_replace( '\\', '', $d['nome'] ?? '' ) ); }, $_rcd['ok'] ? ( $_rcd['data'] ?? [] ) : [] );
                    wp_send_json_error( [ 'code' => 'campos_faltando', 'campos' => $_nomes, 'msg' => 'Preencha os campos obrigatórios desta fase (Pós-vendas) antes de avançar: ' . implode( ', ', $_nomes ) ] );
                }
            }
        }
    }

    // Validar campos obrigatórios na saída do estágio — bloqueia apenas se destino também os exige
    if ( $de_estagio && $de_estagio !== $estagio_id ) {
        $rce = tao_crm_api( "/crm_campos_estagio?estagio_id=eq.$de_estagio&obrigatorio=eq.true" );
        if ( $rce['ok'] && ! empty( $rce['data'] ) ) {
            $req_ids = array_column( $rce['data'], 'campo_id' );
            $rvals   = tao_crm_api( "/crm_cards_valores?card_id=eq.$card_id" );
            $filled  = [];
            foreach ( ( $rvals['ok'] ? ( $rvals['data'] ?? [] ) : [] ) as $v ) {
                if ( $v['valor'] !== '' && $v['valor'] !== null ) $filled[ $v['campo_id'] ] = true;
            }
            // Considera valores submetidos junto ao request (ainda não persistidos no DB)
            foreach ( (array) ( $_POST['valores'] ?? [] ) as $_cid => $_cval ) {
                $_cid = sanitize_text_field( $_cid );
                if ( $_cval !== '' && $_cval !== null && $_cid ) $filled[ $_cid ] = true;
            }
            $missing_ids = array_values( array_filter( $req_ids, fn( $id ) => ! isset( $filled[ $id ] ) ) );
            if ( ! empty( $missing_ids ) ) {
                // Verifica se a fase destino também exige algum desses campos ausentes
                $r_dest   = tao_crm_api( "/crm_campos_estagio?estagio_id=eq.$estagio_id&obrigatorio=eq.true" );
                $dest_ids = $r_dest['ok'] ? array_column( $r_dest['data'] ?? [], 'campo_id' ) : [];
                $conflito = array_values( array_intersect( $missing_ids, $dest_ids ) );
                if ( ! empty( $conflito ) ) {
                    $rcd   = tao_crm_api( '/crm_campos_definicao?id=in.(' . implode( ',', $conflito ) . ')&select=nome' );
                    $nomes = array_column( $rcd['ok'] ? ( $rcd['data'] ?? [] ) : [], 'nome' );
                    wp_send_json_error( [
                        'code'   => 'campos_faltando',
                        'campos' => $nomes,
                        'msg'    => 'Preencha os campos obrigatórios antes de mover: ' . implode( ', ', $nomes ),
                    ] );
                }
            }
        }
    }

    // Regra: mover p/ "Flw Orçamento Enviado" ou QUALQUER fase posterior exige ≥1 item OU ≥1 orçamento (valor sozinho não basta)
    // EXCEÇÃO (Carlos 18/07): "Análise Técnica" não exige — o card chega lá justamente PARA a análise gerar o orçamento.
    if ( $de_estagio !== $estagio_id ) {
        $rde = tao_crm_api( "/crm_estagios?id=eq.$estagio_id&select=nome,ordem,pipeline_id,tipo&limit=1" );
        if ( $rde['ok'] && ! empty( $rde['data'] ) ) {
            // Estágios TERMINAIS não aceitam arrasto direto: perdido exige motivo e
            // ganho exige confirmação do Valor Final — o front reencaminha pro fluxo de fechamento.
            $dtipo = $rde['data'][0]['tipo'] ?? 'normal';
            if ( $dtipo === 'perdido' ) {
                wp_send_json_error( [ 'code' => 'perdido_motivo', 'msg' => 'Para cancelar o negócio, informe o motivo do cancelamento.' ] );
            }
            if ( $dtipo === 'ganho' ) {
                $rvv = tao_crm_api( "/crm_cards?id=eq.$card_id&select=valor_oportunidade&limit=1" );
                wp_send_json_error( [
                    'code'  => 'ganho_confirmar',
                    'valor' => ( $rvv['ok'] && ! empty( $rvv['data'] ) ) ? (float) ( $rvv['data'][0]['valor_oportunidade'] ?? 0 ) : 0,
                    'msg'   => 'Confirme o Valor Final do negócio para fechar como ganho.',
                ] );
            }
            $dord = (int) ( $rde['data'][0]['ordem'] ?? 0 );
            $dpl  = $rde['data'][0]['pipeline_id'] ?? '';
            $flw_ord = -1;
            if ( $dpl ) {
                $rall = tao_crm_api( "/crm_estagios?pipeline_id=eq.$dpl&select=nome,ordem" );
                foreach ( ( $rall['ok'] ? ( $rall['data'] ?? [] ) : [] ) as $s ) {
                    $sn = mb_strtoupper( $s['nome'] ?? '' );
                    if ( strpos( $sn, 'ORÇAMENTO ENVIADO' ) !== false || strpos( $sn, 'ORCAMENTO ENVIADO' ) !== false ) { $flw_ord = (int) ( $s['ordem'] ?? 0 ); break; }
                }
            }
            $dnome      = mb_strtoupper( $rde['data'][0]['nome'] ?? '' );
            $eh_analise = ( mb_strpos( $dnome, 'ANÁLISE TÉCNICA' ) !== false || mb_strpos( $dnome, 'ANALISE TECNICA' ) !== false );
            if ( ! $eh_analise && $flw_ord >= 0 && $dord >= $flw_ord && ! tao_crm_card_tem_negocio( $card_id ) ) {
                wp_send_json_error( [
                    'code' => 'sem_negocio',
                    'msg'  => 'Adicione ao menos um item ou orçamento ao negócio antes de movimentar para essa fase.',
                ] );
            }
        }
    }

    // Trava plugável: módulos (ex: tao-entregas) podem vetar a ENTRADA em certos estágios.
    // Retorno esperado: ['bloqueado'=>true,'code'=>..,'msg'=>..] — desacoplado do CRM.
    if ( $de_estagio !== $estagio_id ) {
        $veto = apply_filters( 'tao_crm_veto_mover_card', null, $card_id, $estagio_id, $card_atual );
        if ( is_array( $veto ) && ! empty( $veto['bloqueado'] ) ) {
            wp_send_json_error( [ 'code' => $veto['code'] ?? 'bloqueado', 'msg' => $veto['msg'] ?? 'Movimentação bloqueada.' ] );
        }
    }

    $r = tao_crm_api( "/crm_cards?id=eq.$card_id", 'PATCH', [
        'estagio_id' => $estagio_id,
        'movido_em'  => gmdate( 'c' ),
    ] );

    if ( ! $r['ok'] ) wp_send_json_error( $r['error'] );

    // (valores do modal já foram persistidos no INÍCIO do handler via tao_crm_salvar_campos_card —
    //  o loop duplicado que regravava cada campo aqui foi removido: eram 2 chamadas × campo à toa)

    tao_crm_api( '/crm_cards_historico', 'POST', [
        'card_id'         => $card_id,
        'de_estagio_id'   => $de_estagio,
        'para_estagio_id' => $estagio_id,
        'usuario_id'      => get_current_user_id(),
    ] );

    // ── PERFORMANCE: o card JÁ ESTÁ movido e registrado — responde ao atendente AGORA e
    //    executa o pós-processamento (automações, hooks de módulos, webhook, fila) com a
    //    conexão fechada. O atendente é liberado em ~1s; o resto roda no servidor.
    tao_crm_responder_e_continuar( [ 'success' => true, 'data' => null ] );

    if ( $de_estagio && $de_estagio !== $estagio_id ) {
        tao_crm_disparar_automacoes( $card_id, $de_estagio, 'sair_fase', true );
        tao_crm_cancelar_fila( $card_id, $de_estagio );
        tao_crm_disparar_automacoes( $card_id, $estagio_id, 'entrar_fase' );
        tao_crm_disparar_automacoes( $card_id, $estagio_id, 'tempo_na_fase' );
        tao_crm_nps_disparar( $card_id, $estagio_id );   // envia pesquisa NPS ao entrar no estágio NPS
        // Hook plugável: card entrou em nova fase (módulos reagem — ex.: Fórmula conclui a OM em "Pronto para Entrega")
        do_action( 'tao_crm_card_movido', $card_id, $estagio_id, $de_estagio, $card_atual );
    }

    // Mover para estágio terminal (ganho/perdido)
    $re_tipo   = tao_crm_api( "/crm_estagios?id=eq.$estagio_id&select=tipo&limit=1" );
    $tipo_dest = ( $re_tipo['ok'] && ! empty( $re_tipo['data'] ) ) ? ( $re_tipo['data'][0]['tipo'] ?? '' ) : '';
    if ( in_array( $tipo_dest, [ 'ganho', 'perdido' ], true ) ) {
        // Regra: ganho no Funil de Vendas cruza pro Pós-vendas (se houver); senão fecha no lugar.
        $cruzou = ( $tipo_dest === 'ganho' )
            ? tao_crm_cruzar_para_pos_vendas( $card_id, $card_atual, $de_estagio )
            : false;
        if ( ! $cruzou ) {
            tao_crm_api( "/crm_cards?id=eq.$card_id", 'PATCH', [ 'fechado' => true, 'status' => ( $tipo_dest === 'ganho' ? 'ganho' : 'fechado' ) ] );
        }
        // Limpa historico do chatbot para este número permitindo nova conversa limpa
        $rc_wh = tao_crm_api( "/crm_cards?id=eq.$card_id&select=contato_whatsapp,workspace_id&limit=1" );
        if ( $rc_wh['ok'] && ! empty( $rc_wh['data'] ) ) {
            tao_crm_reset_chatbot_historico( $rc_wh['data'][0]['contato_whatsapp'], $rc_wh['data'][0]['workspace_id'] );
        }
    }

    tao_crm_fire_webhook( $card_atual['workspace_id'] ?? '', 'card_movido', [
        'card_id'    => $card_id,
        'estagio_id' => $estagio_id,
        'de_estagio' => $de_estagio,
    ] );

    // Processa fila de automações vencidas (WP-cron não confiável sem tráfego)
    tao_crm_processar_fila_fn();

    wp_die();   // resposta já foi enviada em tao_crm_responder_e_continuar()
}

/**
 * Envia a resposta JSON AGORA e fecha a conexão com o navegador, deixando o restante
 * do handler rodar em segundo plano no servidor (LiteSpeed/FastCGI). O atendente não
 * espera automações/hooks/webhooks — só a gravação essencial, que acontece antes.
 */
function tao_crm_responder_e_continuar( array $payload ) {
    ignore_user_abort( true );
    $json = wp_json_encode( $payload );
    while ( ob_get_level() > 0 ) ob_end_clean();
    if ( ! headers_sent() ) {
        status_header( 200 );
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'Content-Length: ' . strlen( $json ) );
        header( 'Connection: close' );
    }
    echo $json;
    if ( function_exists( 'fastcgi_finish_request' ) ) {
        fastcgi_finish_request();
    } elseif ( function_exists( 'litespeed_finish_request' ) ) {
        litespeed_finish_request();
    } else {
        flush();
    }
    if ( function_exists( 'set_time_limit' ) ) @set_time_limit( 90 );
}

// ─── AJAX: CAMPOS OBRIGATÓRIOS DA FASE DESTINO ───────────────────────────────

add_action( 'wp_ajax_tao_crm_get_campos_destino', 'tao_crm_ajax_get_campos_destino' );
function tao_crm_ajax_get_campos_destino() {
    $raw_nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
    if ( ! wp_verify_nonce( $raw_nonce, 'tao_crm_nonce' ) ) wp_send_json_error( 'Sessão expirada.' );
    if ( ! function_exists( 'cbpm_can_access' ) || ! cbpm_can_access() ) wp_send_json_error( 'Acesso negado' );
    $estagio_id = sanitize_text_field( $_POST['estagio_id'] ?? '' );
    $card_id    = sanitize_text_field( $_POST['card_id']    ?? '' );
    if ( ! $estagio_id ) wp_send_json_error( 'estagio_id obrigatório' );
    $r = tao_crm_api( "/crm_campos_estagio?estagio_id=eq.$estagio_id&order=ordem.asc" );
    if ( ! $r['ok'] ) wp_send_json_error( $r['error'] );
    $all = $r['data'] ?? [];
    if ( empty( $all ) ) { wp_send_json_success( [ 'campos' => [], 'valores' => [] ] ); return; }

    $valores = [];
    if ( $card_id ) {
        $ids_str = implode( ',', array_column( $all, 'campo_id' ) );
        $rv = tao_crm_api( "/crm_cards_valores?card_id=eq.$card_id&campo_id=in.($ids_str)&select=campo_id,valor" );
        if ( $rv['ok'] && ! empty( $rv['data'] ) ) {
            foreach ( $rv['data'] as $v ) $valores[ $v['campo_id'] ] = $v['valor'];
        }
    }

    // Modal deve oferecer TUDO que a validação do move pode exigir:
    // na_entrada=true sempre; obrigatório sem na_entrada entra quando está vazio no card
    // (senão o move bloqueia por um campo que o modal nunca pergunta — beco sem saída).
    $assigns = array_values( array_filter( $all, function ( $a ) use ( $valores, $card_id ) {
        if ( ! empty( $a['na_entrada'] ) ) return true;
        if ( empty( $a['obrigatorio'] ) || ! $card_id ) return false;
        $v = $valores[ $a['campo_id'] ] ?? '';
        return ( $v === '' || $v === null );
    } ) );
    if ( empty( $assigns ) ) { wp_send_json_success( [ 'campos' => [], 'valores' => $valores ] ); return; }

    $campo_ids       = array_column( $assigns, 'campo_id' );
    $ordem_map       = array_column( $assigns, 'ordem',       'campo_id' );
    $obrigatorio_map = array_column( $assigns, 'obrigatorio', 'campo_id' );
    $r2   = tao_crm_api( '/crm_campos_definicao?id=in.(' . implode( ',', $campo_ids ) . ')&select=id,nome,tipo,opcoes,chave' );
    $defs = $r2['ok'] ? ( $r2['data'] ?? [] ) : [];
    foreach ( $defs as &$def ) {
        $def['obrigatorio'] = ! empty( $obrigatorio_map[ $def['id'] ] );
    }
    unset( $def );
    usort( $defs, fn( $a, $b ) => ( $ordem_map[ $a['id'] ] ?? 0 ) <=> ( $ordem_map[ $b['id'] ] ?? 0 ) );
    wp_send_json_success( [ 'campos' => $defs, 'valores' => $valores ] );
}

// ─── AJAX: CAMPOS OBRIGATÓRIOS DO ESTÁGIO GANHO (dado card_id) ───────────────

/**
 * O card tem "negócio" (item ou orçamento)?
 * Regra: ≥1 item do negócio OU ≥1 orçamento de fórmula. Valor sozinho NÃO conta.
 */
function tao_crm_card_tem_negocio( $card_id ) {
    if ( ! $card_id ) return false;
    $ri = tao_crm_api( "/crm_card_itens?card_id=eq.$card_id&select=id&limit=1" );
    if ( $ri['ok'] && ! empty( $ri['data'] ) ) return true;
    $ro = tao_crm_api( "/orcamentos?card_id=eq.$card_id&select=id&limit=1" );
    if ( $ro['ok'] && ! empty( $ro['data'] ) ) return true;
    return false;
}

add_action( 'wp_ajax_tao_crm_get_ganho_campos',        'tao_crm_ajax_get_ganho_campos' );
add_action( 'wp_ajax_nopriv_tao_crm_get_ganho_campos', 'tao_crm_ajax_get_ganho_campos' );
function tao_crm_ajax_get_ganho_campos() {
    $raw_nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
    if ( ! wp_verify_nonce( $raw_nonce, 'tao_crm_nonce' ) ) wp_send_json_error( 'Sessão expirada.' );
    $card_id = sanitize_text_field( $_POST['card_id'] ?? '' );
    if ( ! $card_id ) wp_send_json_error( 'card_id obrigatório' );
    $tem_negocio = tao_crm_card_tem_negocio( $card_id );
    $rc = tao_crm_api( "/crm_cards?id=eq.$card_id&select=pipeline_id,valor_oportunidade&limit=1" );
    if ( ! $rc['ok'] || empty( $rc['data'] ) ) wp_send_json_error( 'Card não encontrado' );
    $pipeline_id = $rc['data'][0]['pipeline_id'] ?? '';
    $valor_card  = (float) ( $rc['data'][0]['valor_oportunidade'] ?? 0 );   // Valor Final p/ confirmação do ganho
    if ( ! $pipeline_id ) wp_send_json_success( [ 'campos' => [], 'valores' => [], 'ganho_stage_id' => '', 'tem_negocio' => $tem_negocio, 'valor' => $valor_card ] );
    $rg = tao_crm_api( "/crm_estagios?pipeline_id=eq.$pipeline_id&tipo=eq.ganho&limit=1" );
    if ( ! $rg['ok'] || empty( $rg['data'] ) ) wp_send_json_success( [ 'campos' => [], 'valores' => [], 'ganho_stage_id' => '', 'tem_negocio' => $tem_negocio, 'valor' => $valor_card ] );
    $ganho_stage_id = $rg['data'][0]['id'];
    $r = tao_crm_api( "/crm_campos_estagio?estagio_id=eq.$ganho_stage_id&na_entrada=eq.true&order=ordem.asc" );
    if ( ! $r['ok'] || empty( $r['data'] ) ) wp_send_json_success( [ 'campos' => [], 'valores' => [], 'ganho_stage_id' => $ganho_stage_id, 'tem_negocio' => $tem_negocio, 'valor' => $valor_card ] );
    $assigns         = $r['data'];
    $campo_ids       = array_column( $assigns, 'campo_id' );
    $ordem_map       = array_column( $assigns, 'ordem',      'campo_id' );
    $obrigatorio_map = array_column( $assigns, 'obrigatorio', 'campo_id' );
    $r2   = tao_crm_api( '/crm_campos_definicao?id=in.(' . implode( ',', $campo_ids ) . ')&select=id,nome,tipo,opcoes,chave' );
    $defs = $r2['ok'] ? ( $r2['data'] ?? [] ) : [];
    foreach ( $defs as &$def ) {
        $def['obrigatorio'] = ! empty( $obrigatorio_map[ $def['id'] ] );
    }
    unset( $def );
    usort( $defs, fn( $a, $b ) => ( $ordem_map[ $a['id'] ] ?? 0 ) <=> ( $ordem_map[ $b['id'] ] ?? 0 ) );
    $ids_str = implode( ',', $campo_ids );
    $rv = tao_crm_api( "/crm_cards_valores?card_id=eq.$card_id&campo_id=in.($ids_str)&select=campo_id,valor" );
    $valores = [];
    if ( $rv['ok'] && ! empty( $rv['data'] ) ) {
        foreach ( $rv['data'] as $v ) $valores[ $v['campo_id'] ] = $v['valor'];
    }
    wp_send_json_success( [ 'campos' => $defs, 'valores' => $valores, 'ganho_stage_id' => $ganho_stage_id, 'tem_negocio' => $tem_negocio, 'valor' => $valor_card ] );
}

// ─── AJAX: ENVIAR MENSAGEM ────────────────────────────────────────────────────

add_action( 'wp_ajax_tao_crm_send_message', 'tao_crm_ajax_send_message' );
function tao_crm_ajax_send_message() {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    if ( ! function_exists( 'cbpm_can_access' ) || ! cbpm_can_access() ) wp_send_json_error( 'Acesso negado' );

    $card_id  = sanitize_text_field( $_POST['card_id']  ?? '' );
    $mensagem = sanitize_textarea_field( $_POST['mensagem'] ?? '' );
    if ( ! $card_id || ! $mensagem ) wp_send_json_error( 'Dados inválidos' );

    $rc = tao_crm_api( "/crm_cards?id=eq.$card_id&select=contato_whatsapp,workspace_id,instancia_id,responsavel_id,estagio_id" );
    if ( ! $rc['ok'] || empty( $rc['data'] ) ) wp_send_json_error( 'Card não encontrado' );

    $card       = $rc['data'][0];
    $ws_id      = $card['workspace_id'];
    $resp_atual = intval( $card['responsavel_id'] ?? 0 );
    $evo_cfg    = tao_crm_get_evo_creds( $card );
    if ( ! $evo_cfg ) wp_send_json_error( 'Sem Evolution configurado para este card' );

    // Marca no cache: quando o dispatch receber o SEND_MESSAGE de volta, não duplica
    set_transient( 'tao_crm_fwd_' . md5( $card['contato_whatsapp'] . $mensagem ), 1, 60 );

    // Fire-and-forget: não bloqueia aguardando resposta da Evolution
    tao_crm_evolution_send( $evo_cfg, $card['contato_whatsapp'], $mensagem, false );

    $user = wp_get_current_user();
    $rm   = tao_crm_api( '/crm_mensagens', 'POST', [
        'card_id'        => $card_id,
        'workspace_id'   => $ws_id,
        'direcao'        => 'out',
        'tipo'           => 'text',
        'conteudo'       => $mensagem,
        'remetente_nome' => $user->display_name,
        'enviado_em'     => gmdate( 'c' ),
    ], [ 'Prefer' => 'return=representation' ] );

    // Quem responde assume a responsabilidade do card automaticamente
    $responsavel_changed = null;
    if ( $user->ID && $user->ID !== $resp_atual ) {
        $de_nome = '—';
        if ( $resp_atual ) {
            $de_user = get_userdata( $resp_atual );
            if ( $de_user ) $de_nome = $de_user->display_name;
        }
        tao_crm_api( "/crm_cards?id=eq.$card_id", 'PATCH', [ 'responsavel_id' => $user->ID ] );
        tao_crm_api( '/crm_cards_historico', 'POST', [
            'card_id'    => $card_id,
            'usuario_id' => $user->ID,
            'motivo'     => "Responsável: {$de_nome} → {$user->display_name} (respondeu mensagem)",
            'criado_em'  => gmdate( 'c' ),
        ] );
        $responsavel_changed = [ 'id' => $user->ID, 'nome' => $user->display_name ];
    }

    // Gatilho "enviou_mensagem": atendente respondeu pelo CRM → automações da fase atual
    // (ex.: Em Conversa → Aguarda Resp Conversa; Em Negociação → Aguard Resp Negociação)
    if ( ! empty( $card['estagio_id'] ) ) {
        tao_crm_disparar_automacoes( $card_id, $card['estagio_id'], 'enviou_mensagem', false, $ws_id );
    }

    wp_send_json_success( [ 'msg' => $rm['ok'] ? $rm['data'][0] : null, 'responsavel_changed' => $responsavel_changed ] );
}

add_action( 'wp_ajax_tao_crm_send_attachment', 'tao_crm_ajax_send_attachment' );
function tao_crm_ajax_send_attachment() {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    if ( ! function_exists( 'cbpm_can_access' ) || ! cbpm_can_access() ) wp_send_json_error( 'Acesso negado' );

    $card_id = sanitize_text_field( $_POST['card_id'] ?? '' );
    $caption = sanitize_textarea_field( $_POST['caption'] ?? '' );
    if ( ! $card_id || empty( $_FILES['arquivo'] ) ) wp_send_json_error( 'Dados inválidos' );

    $file = $_FILES['arquivo'];
    if ( $file['error'] !== UPLOAD_ERR_OK ) wp_send_json_error( 'Erro no upload: ' . $file['error'] );

    $max_bytes = 20 * 1024 * 1024; // 20 MB
    if ( $file['size'] > $max_bytes ) wp_send_json_error( 'Arquivo muito grande (máx 20 MB)' );

    $rc = tao_crm_api( "/crm_cards?id=eq.$card_id&select=contato_whatsapp,workspace_id,instancia_id,responsavel_id,estagio_id" );
    if ( ! $rc['ok'] || empty( $rc['data'] ) ) wp_send_json_error( 'Card não encontrado' );

    $card       = $rc['data'][0];
    $ws_id      = $card['workspace_id'];
    $resp_atual = intval( $card['responsavel_id'] ?? 0 );
    $evo_cfg    = tao_crm_get_evo_creds( $card );
    if ( ! $evo_cfg ) wp_send_json_error( 'Sem Evolution configurado para este card' );

    $mimetype = mime_content_type( $file['tmp_name'] ) ?: $file['type'];
    $filename = sanitize_file_name( $file['name'] );

    // Salva antes do envio para obter URL pública (Evolution busca o arquivo via URL)
    $midia_url = tao_crm_save_media_file( file_get_contents( $file['tmp_name'] ), $mimetype, $filename );
    if ( ! $midia_url ) wp_send_json_error( 'Erro ao salvar arquivo no servidor' );

    $evo_r = tao_crm_evolution_send_media( $evo_cfg, $card['contato_whatsapp'], $midia_url, $mimetype, $filename, $caption );
    if ( ! $evo_r['ok'] ) wp_send_json_error( 'Erro ao enviar: ' . $evo_r['error'] );

    // Marca no cache para evitar duplicata no dispatch
    set_transient( 'tao_crm_fwd_' . md5( $card['contato_whatsapp'] . ( $caption ?: $filename ) ), 1, 60 );

    // Determina tipo CRM
    if ( str_starts_with( $mimetype, 'image/' ) )     $tipo = 'image';
    elseif ( str_starts_with( $mimetype, 'video/' ) ) $tipo = 'video';
    elseif ( str_starts_with( $mimetype, 'audio/' ) ) $tipo = 'audio';
    else                                              $tipo = 'document';

    $user = wp_get_current_user();
    $conteudo = $caption ?: $filename;
    $rm = tao_crm_api( '/crm_mensagens', 'POST', [
        'card_id'        => $card_id,
        'workspace_id'   => $ws_id,
        'direcao'        => 'out',
        'tipo'           => $tipo,
        'conteudo'       => $conteudo,
        'midia_url'      => $midia_url ?? null,
        'remetente_nome' => $user->display_name,
        'enviado_em'     => gmdate( 'c' ),
    ], [ 'Prefer' => 'return=representation' ] );

    // Quem responde assume a responsabilidade do card automaticamente
    $responsavel_changed = null;
    if ( $user->ID && $user->ID !== $resp_atual ) {
        $de_nome = '—';
        if ( $resp_atual ) {
            $de_user = get_userdata( $resp_atual );
            if ( $de_user ) $de_nome = $de_user->display_name;
        }
        tao_crm_api( "/crm_cards?id=eq.$card_id", 'PATCH', [ 'responsavel_id' => $user->ID ] );
        tao_crm_api( '/crm_cards_historico', 'POST', [
            'card_id'    => $card_id,
            'usuario_id' => $user->ID,
            'motivo'     => "Responsável: {$de_nome} → {$user->display_name} (respondeu mensagem)",
            'criado_em'  => gmdate( 'c' ),
        ] );
        $responsavel_changed = [ 'id' => $user->ID, 'nome' => $user->display_name ];
    }

    // Gatilho "enviou_mensagem": atendente respondeu pelo CRM → automações da fase atual
    // (ex.: Em Conversa → Aguarda Resp Conversa; Em Negociação → Aguard Resp Negociação)
    if ( ! empty( $card['estagio_id'] ) ) {
        tao_crm_disparar_automacoes( $card_id, $card['estagio_id'], 'enviou_mensagem', false, $ws_id );
    }

    wp_send_json_success( [ 'msg' => $rm['ok'] ? $rm['data'][0] : null, 'responsavel_changed' => $responsavel_changed ] );
}

// ─── AJAX: BUSCAR MENSAGENS NOVAS (polling) ───────────────────────────────────

add_action( 'wp_ajax_tao_crm_poll_messages', 'tao_crm_ajax_poll_messages' );
function tao_crm_ajax_poll_messages() {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    if ( ! function_exists( 'cbpm_can_access' ) || ! cbpm_can_access() ) wp_send_json_error( 'Acesso negado' );

    $card_id  = sanitize_text_field( $_POST['card_id']  ?? '' );
    $desde    = sanitize_text_field( $_POST['desde']    ?? '' );
    if ( ! $card_id ) wp_send_json_error( 'card_id obrigatório' );

    // rawurlencode para preservar o + do fuso horário na URL do Supabase
    $filtro = $desde ? '&enviado_em=gt.' . rawurlencode( $desde ) : '';
    $r = tao_crm_api( "/crm_mensagens?card_id=eq.$card_id$filtro&order=enviado_em.asc&limit=50" );

    wp_send_json_success( $r['ok'] ? $r['data'] : [] );
}

// ─── AJAX: CRIAR CARD ─────────────────────────────────────────────────────────

add_action( 'wp_ajax_tao_crm_create_card', 'tao_crm_ajax_create_card' );
function tao_crm_ajax_create_card() {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    if ( ! function_exists( 'cbpm_can_access' ) || ! cbpm_can_access() ) wp_send_json_error( 'Acesso negado' );

    $workspace_id = sanitize_text_field( $_POST['workspace_id'] ?? '' );
    $pipeline_id  = sanitize_text_field( $_POST['pipeline_id']  ?? '' );
    $estagio_id   = sanitize_text_field( $_POST['estagio_id']   ?? '' );
    $nome         = sanitize_text_field( $_POST['contato_nome'] ?? '' );
    $whats        = preg_replace( '/\D/', '', $_POST['contato_whatsapp'] ?? '' );
    $titulo       = sanitize_text_field( $_POST['titulo'] ?? '' ) ?: $nome;
    $instancia_id = sanitize_text_field( $_POST['instancia_id'] ?? '' ) ?: null;
    $contato_id   = sanitize_text_field( $_POST['contato_id'] ?? '' ) ?: null;   // vínculo ao contato escolhido no autocomplete

    if ( ! $workspace_id || ! $pipeline_id || ! $estagio_id || ! $nome || strlen( $whats ) < 10 ) {
        wp_send_json_error( 'Preencha todos os campos obrigatórios' );
    }

    // Contato único: se não veio vinculado do autocomplete (cliente novo), registra/acha
    // o contato pelo WhatsApp no workspace e vincula (cadastra se não existir).
    if ( ! $contato_id ) {
        $up = tao_crm_upsert_contato( $workspace_id, $whats, $nome );
        $contato_id = $up['id'] ?? null;
    }

    $card_data = [
        'workspace_id'      => $workspace_id,
        'pipeline_id'       => $pipeline_id,
        'estagio_id'        => $estagio_id,
        'contato_nome'      => $nome,
        'contato_whatsapp'  => $whats,
        'titulo'            => $titulo,
        'responsavel_id'    => get_current_user_id(),
        'atendimento_humano' => true,
        'criado_em'         => gmdate( 'c' ),
        'movido_em'         => gmdate( 'c' ),
    ];
    if ( $instancia_id ) $card_data['instancia_id'] = $instancia_id;
    if ( $contato_id )   $card_data['contato_id']   = $contato_id;

    $r = tao_crm_api( '/crm_cards', 'POST', $card_data, [ 'Prefer' => 'return=representation' ] );

    if ( ! $r['ok'] ) wp_send_json_error( $r['error'] );
    $new_card = $r['data'][0];
    tao_crm_disparar_automacoes( $new_card['id'], $estagio_id, 'entrar_fase' );
    tao_crm_disparar_automacoes( $new_card['id'], $estagio_id, 'tempo_na_fase' );
    tao_crm_fire_webhook( $workspace_id, 'card_criado', [ 'card' => $new_card ] );
    wp_send_json_success( [ 'card' => $new_card ] );
}

// ─── AJAX: MARCAR CARD COMO LIDO ─────────────────────────────────────────────

add_action( 'wp_ajax_tao_crm_mark_read', 'tao_crm_ajax_mark_read' );
function tao_crm_ajax_mark_read() {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    if ( ! function_exists( 'cbpm_can_access' ) || ! cbpm_can_access() ) wp_send_json_error( 'Acesso negado' );
    $card_id = sanitize_text_field( $_POST['card_id'] ?? '' );
    if ( ! $card_id ) wp_send_json_error( 'card_id obrigatório' );
    tao_crm_api( "/crm_cards?id=eq.$card_id", 'PATCH', [ 'ultima_leitura_em' => gmdate( 'c' ) ] );
    wp_send_json_success();
}

// ─── AJAX: SALVAR VALOR DE CAMPO ─────────────────────────────────────────────

add_action( 'wp_ajax_tao_crm_save_valor', 'tao_crm_ajax_save_valor' );
function tao_crm_ajax_save_valor() {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    if ( ! function_exists( 'cbpm_can_access' ) || ! cbpm_can_access() ) wp_send_json_error( 'Acesso negado' );

    $card_id  = sanitize_text_field( $_POST['card_id']  ?? '' );
    $campo_id = sanitize_text_field( $_POST['campo_id'] ?? '' );
    $valor    = sanitize_textarea_field( $_POST['valor'] ?? '' );
    if ( ! $card_id || ! $campo_id ) wp_send_json_error( 'Dados inválidos' );

    $r = tao_crm_api( '/crm_cards_valores', 'POST', [
        'card_id'      => $card_id,
        'campo_id'     => $campo_id,
        'valor'        => $valor,
        'atualizado_em'=> gmdate( 'c' ),
    ], [ 'Prefer' => 'resolution=merge-duplicates,return=minimal' ] );

    if ( ! $r['ok'] ) wp_send_json_error( $r['error'] );
    wp_send_json_success();
}

// ─── AJAX: SALVAR RESPONSÁVEL ─────────────────────────────────────────────────

add_action( 'wp_ajax_tao_crm_save_responsavel', 'tao_crm_ajax_save_responsavel' );
function tao_crm_ajax_save_responsavel() {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    if ( ! function_exists( 'cbpm_can_access' ) || ! cbpm_can_access() ) wp_send_json_error( 'Acesso negado' );
    $card_id  = sanitize_text_field( $_POST['card_id']       ?? '' );
    $resp_id  = intval( $_POST['responsavel_id'] ?? 0 );
    if ( ! $card_id ) wp_send_json_error( 'Dados inválidos' );
    $r = tao_crm_api( "/crm_cards?id=eq.$card_id", 'PATCH', [ 'responsavel_id' => $resp_id ?: null ] );
    if ( ! $r['ok'] ) wp_send_json_error( $r['error'] );
    wp_send_json_success();
}

// ─── AJAX: SALVAR CAMPOS DEFINIÇÃO (Settings) ────────────────────────────────

add_action( 'wp_ajax_tao_crm_save_campo_def', 'tao_crm_ajax_save_campo_def' );
function tao_crm_ajax_save_campo_def() {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Acesso negado' );

    $campo_id    = sanitize_text_field( $_POST['campo_id']    ?? '' );
    $workspace_id= sanitize_text_field( $_POST['workspace_id']?? '' );
    $pipeline_id = sanitize_text_field( $_POST['pipeline_id'] ?? '' );
    $nome        = sanitize_text_field( $_POST['nome']        ?? '' );
    $chave       = sanitize_key( $_POST['chave']              ?? '' );
    $tipo        = sanitize_key( $_POST['tipo']               ?? 'text' );
    $opcoes_raw  = sanitize_textarea_field( $_POST['opcoes']  ?? '' );

    if ( ! $nome || ! $chave || ! $workspace_id ) wp_send_json_error( 'Preencha nome, chave e workspace' );

    $tipos_validos = [ 'text','textarea','number','date','select','boolean','phone','email' ];
    if ( ! in_array( $tipo, $tipos_validos ) ) wp_send_json_error( 'Tipo inválido' );

    $opcoes = null;
    if ( $tipo === 'select' && $opcoes_raw ) {
        $linhas = array_filter( array_map( 'trim', explode( "\n", $opcoes_raw ) ) );
        $opcoes = array_values( $linhas );
    }

    $data = compact( 'workspace_id', 'pipeline_id', 'nome', 'chave', 'tipo' );
    if ( $opcoes ) $data['opcoes'] = $opcoes;

    if ( $campo_id ) {
        $r = tao_crm_api( "/crm_campos_definicao?id=eq.$campo_id", 'PATCH', $data );
    } else {
        $r = tao_crm_api( '/crm_campos_definicao', 'POST', $data, [ 'Prefer' => 'return=representation' ] );
        if ( $r['ok'] ) $campo_id = $r['data'][0]['id'] ?? '';
    }

    if ( ! $r['ok'] ) wp_send_json_error( $r['error'] );

    // Salvar atribuições de estágios
    $estagios_on   = $_POST['estagio_on']   ?? [];
    $estagios_req  = $_POST['estagio_req']  ?? [];
    $estagios_ent  = $_POST['estagio_ent']  ?? [];
    $estagios_ord  = $_POST['estagio_ord']  ?? [];

    // Deletar assignments antigos
    tao_crm_api( "/crm_campos_estagio?campo_id=eq.$campo_id", 'DELETE' );

    // Inserir novos
    foreach ( (array) $estagios_on as $est_id ) {
        $est_id = sanitize_text_field( $est_id );
        tao_crm_api( '/crm_campos_estagio', 'POST', [
            'campo_id'    => $campo_id,
            'estagio_id'  => $est_id,
            'obrigatorio' => in_array( $est_id, (array) $estagios_req ),
            'na_entrada'  => in_array( $est_id, (array) $estagios_ent ),
            'ordem'       => intval( $estagios_ord[ $est_id ] ?? 0 ),
        ] );
    }

    wp_send_json_success( [ 'campo_id' => $campo_id ] );
}

// ─── AJAX: DELETAR CAMPO DEFINIÇÃO ────────────────────────────────────────────

add_action( 'wp_ajax_tao_crm_delete_campo', 'tao_crm_ajax_delete_campo' );
function tao_crm_ajax_delete_campo() {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Acesso negado' );
    $campo_id = sanitize_text_field( $_POST['campo_id'] ?? '' );
    if ( ! $campo_id ) wp_send_json_error( 'campo_id obrigatório' );
    tao_crm_api( "/crm_campos_estagio?campo_id=eq.$campo_id", 'DELETE' );
    tao_crm_api( "/crm_cards_valores?campo_id=eq.$campo_id", 'DELETE' );
    $r = tao_crm_api( "/crm_campos_definicao?id=eq.$campo_id", 'DELETE' );
    if ( ! $r['ok'] ) wp_send_json_error( $r['error'] );
    wp_send_json_success();
}

// ─── AJAX: EXCLUIR ESTÁGIO ───────────────────────────────────────────────────

if ( ! function_exists( 'tao_crm_ajax_delete_estagio' ) ) {
    add_action( 'wp_ajax_tao_crm_delete_estagio', 'tao_crm_ajax_delete_estagio' );
    function tao_crm_ajax_delete_estagio() {
        check_ajax_referer( 'tao_crm_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Acesso negado' );
        $est_id = sanitize_text_field( $_POST['est_id'] ?? '' );
        if ( ! $est_id ) wp_send_json_error( 'est_id obrigatório' );
        tao_crm_api( "/crm_campos_estagio?estagio_id=eq.$est_id", 'DELETE' );
        tao_crm_api( "/crm_automacoes?estagio_id=eq.$est_id", 'DELETE' );
        $r = tao_crm_api( "/crm_estagios?id=eq.$est_id", 'DELETE' );
        if ( ! $r['ok'] ) wp_send_json_error( $r['error'] );
        wp_send_json_success();
    }
}

// ─── TEMP: GRAVAR ARQUIVO DE PLUGIN VIA AJAX ─────────────────────────────────

if ( ! function_exists( 'tao_crm_temp_write_plugin' ) ) {
    add_action( 'wp_ajax_tao_crm_temp_write_plugin', 'tao_crm_temp_write_plugin' );
    function tao_crm_temp_write_plugin() {
        check_ajax_referer( 'tao_crm_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Acesso negado' );
        $rel     = sanitize_text_field( $_POST['file'] ?? '' );
        $content = wp_unslash( $_POST['content'] ?? '' );
        if ( ! $rel || ! $content ) wp_send_json_error( 'Parâmetros inválidos' );
        $base = realpath( WP_PLUGIN_DIR );
        $full = $base . DIRECTORY_SEPARATOR . ltrim( str_replace( [ '..', "\0" ], '', $rel ), '/\\' );
        $dir  = realpath( dirname( $full ) );
        if ( ! $dir || strpos( $dir . DIRECTORY_SEPARATOR, $base . DIRECTORY_SEPARATOR ) !== 0 ) {
            wp_send_json_error( 'Caminho não permitido' );
        }
        $ok = file_put_contents( $full, $content );
        if ( $ok === false ) wp_send_json_error( 'Falha ao gravar' );
        wp_send_json_success( [ 'bytes' => $ok, 'path' => $full ] );
    }
}

// ─── AJAX: SALVAR AUTOMAÇÃO ──────────────────────────────────────────────────

add_action( 'wp_ajax_tao_crm_save_automacao', 'tao_crm_ajax_save_automacao' );
function tao_crm_ajax_save_automacao() {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Acesso negado' );

    $auto_id      = sanitize_text_field( $_POST['auto_id']         ?? '' );
    $workspace_id = sanitize_text_field( $_POST['workspace_id']    ?? '' );
    $pipeline_id  = sanitize_text_field( $_POST['pipeline_id']     ?? '' );
    $estagio_id   = sanitize_text_field( $_POST['estagio_id']      ?? '' );
    $nome         = sanitize_text_field( $_POST['nome']            ?? '' );
    $tipo         = sanitize_key( $_POST['tipo']                   ?? '' );
    $delay        = intval( $_POST['delay_minutos']                ?? 0 );
    $acao         = sanitize_key( $_POST['acao']                   ?? '' );
    $mensagem     = sanitize_textarea_field( $_POST['mensagem']    ?? '' );
    $para_estagio = sanitize_text_field( $_POST['para_estagio_id'] ?? '' );
    $resp_id      = intval( $_POST['responsavel_id']               ?? 0 );
    $ativo        = ! empty( $_POST['ativo'] );
    $ordem        = intval( $_POST['ordem']                        ?? 0 );

    if ( ! $workspace_id || ! $pipeline_id || ! $estagio_id || ! $nome || ! $tipo || ! $acao ) {
        wp_send_json_error( 'Campos obrigatórios faltando' );
    }
    if ( ! in_array( $tipo, [ 'entrar_fase','sair_fase','tempo_na_fase','recebeu_mensagem','enviou_mensagem','sem_resposta' ] ) ||
         ! in_array( $acao, [ 'enviar_mensagem','mover_fase','atribuir_responsavel','notificar_email','atribuir_responsavel_rr','fechar_perdido' ] ) ) {
        wp_send_json_error( 'Tipo ou ação inválidos' );
    }

    $data = compact( 'workspace_id', 'pipeline_id', 'estagio_id', 'nome', 'tipo', 'acao', 'ativo', 'ordem' );
    $data['delay_minutos']   = $delay;
    $data['mensagem']        = $mensagem ?: null;
    $data['para_estagio_id'] = $para_estagio ?: null;
    $data['responsavel_id']  = $resp_id ?: null;
    $data['email_destino']   = sanitize_email( $_POST['email_destino'] ?? '' ) ?: null;

    if ( $auto_id ) {
        $r = tao_crm_api( "/crm_automacoes?id=eq.$auto_id", 'PATCH', $data );
    } else {
        $r = tao_crm_api( '/crm_automacoes', 'POST', $data, [ 'Prefer' => 'return=representation' ] );
        if ( $r['ok'] ) $auto_id = $r['data'][0]['id'] ?? '';
    }

    if ( ! $r['ok'] ) wp_send_json_error( $r['error'] );
    wp_send_json_success( [ 'auto_id' => $auto_id ] );
}

// ─── AJAX: DELETAR AUTOMAÇÃO ──────────────────────────────────────────────────

add_action( 'wp_ajax_tao_crm_delete_automacao', 'tao_crm_ajax_delete_automacao' );
function tao_crm_ajax_delete_automacao() {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Acesso negado' );
    $auto_id = sanitize_text_field( $_POST['auto_id'] ?? '' );
    if ( ! $auto_id ) wp_send_json_error( 'auto_id obrigatório' );
    tao_crm_api( "/crm_automacoes_fila?automacao_id=eq.$auto_id&executado_em=is.null", 'DELETE' );
    $r = tao_crm_api( "/crm_automacoes?id=eq.$auto_id", 'DELETE' );
    if ( ! $r['ok'] ) wp_send_json_error( $r['error'] );
    wp_send_json_success();
}

// ─── AJAX: FECHAR CARD (venda concluída / cancelado) ─────────────────────────

function tao_crm_salvar_campos_card( $card_id, $valores ) {
    if ( empty( $valores ) || ! is_array( $valores ) ) return;
    foreach ( $valores as $campo_id => $valor ) {
        $campo_id = sanitize_text_field( $campo_id );
        if ( ! $campo_id ) continue;
        $ex = tao_crm_api( "/crm_cards_valores?card_id=eq.$card_id&campo_id=eq.$campo_id&limit=1" );
        if ( $ex['ok'] && ! empty( $ex['data'] ) ) {
            tao_crm_api( "/crm_cards_valores?card_id=eq.$card_id&campo_id=eq.$campo_id", 'PATCH', [ 'valor' => $valor ] );
        } else {
            tao_crm_api( '/crm_cards_valores', 'POST', [ 'card_id' => $card_id, 'campo_id' => $campo_id, 'valor' => $valor ] );
        }
    }
}

// ─── AJAX: SALVAR CAMPOS OBRIGATÓRIOS (enforcement Pós-vendas ao abrir o card) ─
add_action( 'wp_ajax_tao_crm_salvar_campos_obrig', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    if ( ! function_exists( 'cbpm_can_access' ) || ! cbpm_can_access() ) wp_send_json_error( 'Acesso negado' );
    $card_id = sanitize_text_field( $_POST['card_id'] ?? '' );
    if ( ! $card_id ) wp_send_json_error( 'card_id obrigatório' );
    $valores = [];
    foreach ( (array) ( $_POST['valores'] ?? [] ) as $cid => $val ) {
        $cid = sanitize_text_field( $cid );
        if ( $cid ) $valores[ $cid ] = sanitize_textarea_field( wp_unslash( $val ) );
    }
    if ( empty( $valores ) ) wp_send_json_error( 'Nenhum campo enviado' );
    tao_crm_salvar_campos_card( $card_id, $valores );
    wp_send_json_success();
} );

add_action( 'wp_ajax_tao_crm_fechar_card', 'tao_crm_ajax_fechar_card' );
function tao_crm_ajax_fechar_card() {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    if ( ! function_exists( 'cbpm_can_access' ) || ! cbpm_can_access() ) wp_send_json_error( 'Acesso negado' );

    $card_id = sanitize_text_field( $_POST['card_id']   ?? '' );
    $tipo    = sanitize_key( $_POST['tipo']              ?? '' );
    $motivo  = sanitize_textarea_field( $_POST['motivo'] ?? '' );
    // O PHP converte "valores[uuid]=x" em $_POST['valores'][uuid] — ler o ARRAY.
    // (o formato antigo com chave literal nunca chega; era por isso que os campos
    //  preenchidos no modal de fechamento NÃO eram persistidos)
    $valores = [];
    foreach ( (array) ( $_POST['valores'] ?? [] ) as $k => $v ) {
        $k = sanitize_text_field( $k );
        if ( $k ) $valores[ $k ] = sanitize_textarea_field( wp_unslash( $v ) );
    }
    // Fallback legado: chaves literais "valores[...]" (caso algum cliente envie assim)
    foreach ( $_POST as $k => $v ) {
        if ( preg_match( '/^valores\[([a-f0-9\-]+)\]$/', $k, $m ) ) {
            $valores[ $m[1] ] = sanitize_textarea_field( wp_unslash( $v ) );
        }
    }

    if ( ! $card_id || ! in_array( $tipo, [ 'ganho', 'perdido' ] ) ) wp_send_json_error( 'Dados inválidos' );

    // Cancelamento exige motivo; "Falta de Insumo" exige o insumo (após os dois-pontos)
    if ( $tipo === 'perdido' ) {
        if ( $motivo === '' ) wp_send_json_error( 'Informe o motivo do cancelamento.' );
        if ( preg_match( '/^falta de insumo\s*:?\s*$/iu', $motivo ) ) wp_send_json_error( 'Informe qual insumo faltou.' );
    }

    $rc = tao_crm_api( "/crm_cards?id=eq.$card_id&select=estagio_id,pipeline_id,contato_whatsapp,workspace_id,instancia_id,valor_oportunidade&limit=1" );
    if ( ! $rc['ok'] || empty( $rc['data'] ) ) wp_send_json_error( 'Card não encontrado' );
    $card = $rc['data'][0];

    // Ganho exige confirmação explícita do Valor Final pelo usuário (o front mostra
    // o valor e só envia valor_ok=1 após o "Sim"; sem isso o card não é movimentado)
    if ( $tipo === 'ganho' && empty( $_POST['valor_ok'] ) ) {
        wp_send_json_error( [
            'code'  => 'ganho_confirmar',
            'valor' => (float) ( $card['valor_oportunidade'] ?? 0 ),
            'msg'   => 'Confirme o Valor Final do negócio para fechar como ganho.',
        ] );
    }

    // ── EXCLUSIVO PÓS-VENDAS: concluir (ganho) exige os campos obrigatórios da FASE ATUAL ──
    //    (Cancelar/perdido NÃO é bloqueado, p/ permitir descartar cards errados. Demais funis: inalterado.)
    if ( $tipo === 'ganho' && ! empty( $card['estagio_id'] ) ) {
        $_pv = get_option( 'tao_crm_pos_vendas_pipeline_' . ( $card['workspace_id'] ?? '' ), '' );
        if ( ! $_pv && ! empty( $card['workspace_id'] ) ) {
            $_rpl = tao_crm_api( "/crm_pipelines?workspace_id=eq.{$card['workspace_id']}&ativo=eq.true&order=ordem.asc&select=id&limit=2" );
            $_apl = $_rpl['ok'] ? ( $_rpl['data'] ?? [] ) : [];
            if ( count( $_apl ) >= 2 ) $_pv = $_apl[1]['id'];
        }
        if ( $_pv && ( $card['pipeline_id'] ?? '' ) === $_pv ) {
            $_rco  = tao_crm_api( "/crm_campos_estagio?estagio_id=eq.{$card['estagio_id']}&na_entrada=eq.true&obrigatorio=eq.true" );
            $_cobr = $_rco['ok'] ? ( $_rco['data'] ?? [] ) : [];
            if ( $_cobr ) {
                $_cids = array_column( $_cobr, 'campo_id' );
                $_rv   = tao_crm_api( "/crm_cards_valores?card_id=eq.$card_id&campo_id=in.(" . implode( ',', $_cids ) . ")&select=campo_id,valor" );
                $_vals = [];
                foreach ( ( $_rv['ok'] ? ( $_rv['data'] ?? [] ) : [] ) as $_v ) $_vals[ $_v['campo_id'] ] = $_v['valor'];
                foreach ( $valores as $_c => $_vv ) { if ( $_vv !== '' && $_vv !== null ) $_vals[ $_c ] = $_vv; }
                $_faltam = [];
                foreach ( $_cobr as $_cf ) { $_cid = $_cf['campo_id']; $_vx = $_vals[ $_cid ] ?? ''; if ( $_vx === '' || $_vx === null ) $_faltam[] = $_cid; }
                if ( $_faltam ) {
                    $_rcd   = tao_crm_api( '/crm_campos_definicao?id=in.(' . implode( ',', $_faltam ) . ')&select=nome' );
                    $_nomes = array_map( function ( $d ) { return trim( str_replace( '\\', '', $d['nome'] ?? '' ) ); }, $_rcd['ok'] ? ( $_rcd['data'] ?? [] ) : [] );
                    wp_send_json_error( [ 'code' => 'campos_pos', 'msg' => 'Preencha os campos obrigatórios desta fase para concluir o card.' ] );
                }
            }
        }
    }

    // ── TRAVA de módulos plugáveis no fechar-ganho (desacoplado; no-op se ninguém responder) ──
    //    Ex.: o Fórmula veta quando há item MP com sinônimo sem ativo correspondente na base
    //    (não se aprova/gera OM de fórmula incompleta — RDC 67). Roda para AMBOS os caminhos
    //    de ganho (cruzar p/ Pós-vendas ou fechar no estágio terminal), antes de qualquer movimentação.
    if ( $tipo === 'ganho' ) {
        $veto_ganho = apply_filters( 'tao_crm_veto_fechar_ganho', null, $card_id, $card );
        if ( is_array( $veto_ganho ) && ! empty( $veto_ganho['veto'] ) ) {
            wp_send_json_error( [ 'code' => $veto_ganho['code'] ?? 'veto_ganho', 'msg' => $veto_ganho['msg'] ?? 'Pendência antes de fechar como ganho.' ] );
        }
    }

    $re = tao_crm_api( "/crm_estagios?pipeline_id=eq.{$card['pipeline_id']}&tipo=eq.$tipo&limit=1" );
    if ( ! $re['ok'] || empty( $re['data'] ) ) {
        $label = $tipo === 'ganho' ? 'Ganho (✅)' : 'Perdido (✗)';
        wp_send_json_error( "Nenhum estágio do tipo $label configurado. Acesse Configurações → Pipelines e Estágios e defina o tipo do estágio." );
    }

    $de_estagio      = $card['estagio_id'];
    $de_pipeline     = $card['pipeline_id'];

    // ── Para "ganho": verifica se há pipeline de pós-vendas configurado ─────
    if ( $tipo === 'ganho' ) {
        $pos_pl_id = get_option( 'tao_crm_pos_vendas_pipeline_' . $card['workspace_id'], '' );
        if ( ! $pos_pl_id ) {
            // Auto-detect: segundo pipeline ativo do workspace = Pós-vendas
            $rall   = tao_crm_api( "/crm_pipelines?workspace_id=eq.{$card['workspace_id']}&ativo=eq.true&order=ordem.asc&limit=2" );
            $all_pl = $rall['ok'] ? ( $rall['data'] ?? [] ) : [];
            if ( count( $all_pl ) >= 2 ) {
                $pos_pl_id = $all_pl[1]['id'];
            }
        }
        if ( $pos_pl_id && $pos_pl_id !== $de_pipeline ) {
            // Busca o primeiro estágio do pipeline de pós-vendas
            $rps = tao_crm_api( "/crm_estagios?pipeline_id=eq.$pos_pl_id&order=ordem.asc&limit=1" );
            if ( $rps['ok'] && ! empty( $rps['data'] ) ) {
                $pos_stage_id = $rps['data'][0]['id'];

                // Move o card para o pipeline de pós-vendas sem fechar
                $r = tao_crm_api( "/crm_cards?id=eq.$card_id", 'PATCH', [
                    'pipeline_id'        => $pos_pl_id,
                    'estagio_id'         => $pos_stage_id,
                    'movido_em'          => gmdate( 'c' ),
                    'atendimento_humano' => false,
                    // fechado = false — card continua ativo no pós-vendas
                ] );
                if ( ! $r['ok'] ) wp_send_json_error( $r['error'] );

                tao_crm_api( '/crm_cards_historico', 'POST', [
                    'card_id'         => $card_id,
                    'de_estagio_id'   => $de_estagio,
                    'para_estagio_id' => $pos_stage_id,
                    'usuario_id'      => get_current_user_id(),
                    'motivo'          => $motivo ?: 'Negócio ganho → Pós-vendas',
                ] );

                tao_crm_salvar_campos_card( $card_id, $valores );
                tao_crm_disparar_automacoes( $card_id, $de_estagio, 'sair_fase', true );
                tao_crm_cancelar_fila( $card_id, $de_estagio );
                tao_crm_disparar_automacoes( $card_id, $pos_stage_id, 'entrar_fase' );
                tao_crm_reset_chatbot_historico( $card['contato_whatsapp'] ?? '', $card['workspace_id'] ?? '' );
                tao_crm_fire_webhook( $card['workspace_id'], 'card_fechado_ganho', [ 'card_id' => $card_id ] );
                // Negócio fechado → gera a venda no Caixa (listener isolado; nunca quebra este fluxo)
                do_action( 'tao_caixa_card_ganho', $card_id, $card['workspace_id'] ?? '' );
                // Negócio ganho → Pós-vendas: garante a entrega (listener isolado no módulo tao-entregas)
                do_action( 'tao_entregas_card_ganho', $card_id, $card['workspace_id'] ?? '' );
                // Negócio ganho → cria a OM do módulo Fórmula (listener isolado; o card nasce com a OM)
                do_action( 'tao_formula_card_ganho', $card_id, $card['workspace_id'] ?? '' );
                wp_send_json_success( [ 'pos_vendas' => true ] );
                return;
            }
        }
    }

    // ── Fluxo padrão: move para estágio terminal e fecha o card ─────────────
    $estagio_destino = $re['data'][0]['id'];

    $r = tao_crm_api( "/crm_cards?id=eq.$card_id", 'PATCH', [
        'estagio_id' => $estagio_destino,
        'movido_em'  => gmdate( 'c' ),
        'fechado'    => true,
        'status'     => 'fechado',
    ] );
    if ( ! $r['ok'] ) wp_send_json_error( $r['error'] );

    tao_crm_salvar_campos_card( $card_id, $valores );

    tao_crm_api( '/crm_cards_historico', 'POST', [
        'card_id'         => $card_id,
        'de_estagio_id'   => $de_estagio,
        'para_estagio_id' => $estagio_destino,
        'usuario_id'      => get_current_user_id(),
        'motivo'          => $motivo ?: null,
    ] );

    if ( $de_estagio !== $estagio_destino ) {
        tao_crm_disparar_automacoes( $card_id, $de_estagio, 'sair_fase', true );
        tao_crm_cancelar_fila( $card_id, $de_estagio );
        tao_crm_disparar_automacoes( $card_id, $estagio_destino, 'entrar_fase' );
        tao_crm_disparar_automacoes( $card_id, $estagio_destino, 'tempo_na_fase' );
    }

    tao_crm_reset_chatbot_historico( $card['contato_whatsapp'] ?? '', $card['workspace_id'] ?? '' );
    tao_crm_fire_webhook( $card['workspace_id'], 'card_fechado_' . $tipo, [ 'card_id' => $card_id, 'motivo' => $motivo ] );

    // CSAT: envia avaliação somente quando fechado a partir do pipeline de Pós Vendas
    // ($pos_pl_id só está definido e igual a $de_pipeline quando o card já estava no PV)
    if ( $tipo === 'ganho' && ! empty( $pos_pl_id ) && ! empty( get_option( 'tao_crm_csat_ativo_' . $card['workspace_id'] ) ) ) {
        $csat_msg = get_option( 'tao_crm_csat_msg_' . $card['workspace_id'],
            'Como você avalia nosso atendimento? Responda com um número de 1 a 5 ⭐' );
        $evo_cfg = tao_crm_get_evo_creds( $card );
        if ( $evo_cfg ) {
            tao_crm_evolution_send( $evo_cfg, $card['contato_whatsapp'], $csat_msg );
            // Marca número como pendente de resposta CSAT (24h)
            $csat_num = preg_replace( '/\D/', '', $card['contato_whatsapp'] );
            set_transient( 'tao_crm_csat_pend_' . $card['workspace_id'] . '_' . $csat_num, '1', DAY_IN_SECONDS );
        }
    }

    wp_send_json_success();
}

// ─── NPS: envia a pesquisa quando o card entra no estágio "NPS" do Pós-Vendas ──
function tao_crm_nps_disparar( $card_id, $estagio_id ) {
    $rc = tao_crm_api( "/crm_cards?id=eq.$card_id&select=id,workspace_id,instancia_id,contato_whatsapp,contato_nome&limit=1" );
    if ( ! $rc['ok'] || empty( $rc['data'] ) ) return;
    $card = $rc['data'][0];
    $ws   = $card['workspace_id'] ?? '';
    if ( ! $ws ) return;
    if ( ! get_option( 'tao_crm_nps_ativo_' . $ws, 1 ) ) return;   // ativo por padrão

    // É o estágio de NPS? (opção configurável, senão pelo nome "NPS")
    $nps_stage = get_option( 'tao_crm_nps_stage_' . $ws, '' );
    if ( $nps_stage ) {
        if ( $estagio_id !== $nps_stage ) return;
    } else {
        $re   = tao_crm_api( "/crm_estagios?id=eq.$estagio_id&select=nome&limit=1" );
        $nome = ( $re['ok'] && ! empty( $re['data'] ) ) ? trim( mb_strtoupper( $re['data'][0]['nome'] ?? '' ) ) : '';
        if ( $nome !== 'NPS' ) return;
    }
    update_option( 'tao_crm_nps_stage_' . $ws, $estagio_id, false );   // memoriza o estágio NPS p/ o dispatch detectar o retorno

    $fone = preg_replace( '/\D/', '', $card['contato_whatsapp'] ?? '' );
    if ( strlen( $fone ) < 10 ) return;
    if ( get_transient( 'tao_crm_nps_sent_' . $card_id ) ) return;   // não reenvia se mover de novo

    $msg = get_option( 'tao_crm_nps_msg_' . $ws,
        "Sua opinião é muito importante para nós! 🙏\n\nDe *0 a 10*, o quanto você recomendaria a nossa farmácia a um amigo ou familiar?\n\n_Responda apenas com o número (0 a 10)._" );
    $evo = tao_crm_get_evo_creds( $card );
    if ( ! $evo ) return;

    tao_crm_evolution_send( $evo, $card['contato_whatsapp'], $msg );
    set_transient( 'tao_crm_nps_pend_' . $ws . '_' . $fone, $card_id, 7 * DAY_IN_SECONDS );
    set_transient( 'tao_crm_nps_sent_' . $card_id, '1', 30 * DAY_IN_SECONDS );
    if ( function_exists( 'tao_crm_lock_chatbot' ) ) tao_crm_lock_chatbot( $fone, $ws );   // não deixa o bot responder o número durante a pesquisa
    tao_crm_log_error( 'nps', 'pesquisa enviada card=' . substr( $card_id, 0, 8 ), [ 'num' => $fone ] );
}

// ════════════════════════════════════════════════════════════════════════════
//  RENOVAÇÃO PÓS-VENDAS (após NPS) — lembrete por data de abertura + clonagem
// ════════════════════════════════════════════════════════════════════════════

// Estado de renovação por card, em wp_options (persistente; sobrevive a cache flush)
function tao_crm_renov_get( $card_id ) { $v = get_option( 'tao_crm_renov_' . $card_id, [] ); return is_array( $v ) ? $v : []; }
function tao_crm_renov_set( $card_id, $st ) { update_option( 'tao_crm_renov_' . $card_id, $st, false ); }
function tao_crm_renov_del( $card_id ) { delete_option( 'tao_crm_renov_' . $card_id ); }

// Resolve os estágios da renovação por NOME (cacheado em option por workspace)
function tao_crm_renov_stages( $ws_id ) {
    if ( ! $ws_id ) return [];
    $cached = get_option( 'tao_crm_renov_stages_' . $ws_id, null );
    if ( is_array( $cached ) && ! empty( $cached['renovacao'] ) && ! empty( $cached['aguardando'] ) ) return $cached;

    $funil = ''; $pv = get_option( 'tao_crm_pos_vendas_pipeline_' . $ws_id, '' );
    $rp = tao_crm_api( "/crm_pipelines?workspace_id=eq.$ws_id&ativo=eq.true&order=ordem.asc&limit=3" );
    $pls = $rp['ok'] ? ( $rp['data'] ?? [] ) : [];
    if ( ! empty( $pls[0] ) ) $funil = $pls[0]['id'];
    if ( ! $pv && ! empty( $pls[1] ) ) $pv = $pls[1]['id'];

    $map = [ 'pos' => $pv, 'funil' => $funil, 'renovacao' => '', 'renovado' => '', 'sem_resposta' => '', 'nao_renovado' => '', 'aguardando' => '' ];
    if ( $pv ) {
        $re = tao_crm_api( "/crm_estagios?pipeline_id=eq.$pv&select=id,nome" );
        foreach ( ( $re['ok'] ? ( $re['data'] ?? [] ) : [] ) as $e ) {
            $n = mb_strtoupper( trim( $e['nome'] ?? '' ) );
            if ( strpos( $n, 'RENOVA' ) !== false && strpos( $n, 'CURSO' ) !== false ) $map['renovacao'] = $e['id'];
            elseif ( $n === 'RENOVADO' ) $map['renovado'] = $e['id'];
            elseif ( strpos( $n, 'SEM RESPOSTA' ) !== false ) $map['sem_resposta'] = $e['id'];
            elseif ( strpos( $n, 'RENOVADO' ) !== false && ( strpos( $n, 'NAO' ) !== false || strpos( $n, 'NÃO' ) !== false ) ) $map['nao_renovado'] = $e['id'];
        }
    }
    if ( $funil ) {
        $rh = tao_crm_api( "/crm_estagios?pipeline_id=eq.$funil&tipo=eq.handoff&limit=1" );
        if ( $rh['ok'] && ! empty( $rh['data'] ) ) $map['aguardando'] = $rh['data'][0]['id'];
    }
    if ( $map['renovacao'] ) update_option( 'tao_crm_renov_stages_' . $ws_id, $map, false );
    return $map;
}

// "Fórmula para quanto tempo? (Dias)" do card — < 10 ou ausente => 30
function tao_crm_formula_dias( $card_id ) {
    $vals = function_exists( 'tao_crm_get_card_valores_por_chave' ) ? tao_crm_get_card_valores_por_chave( $card_id ) : [];
    $d = intval( $vals['formula_para_quanto_tempo_dias'] ?? 0 );
    return ( $d < 10 ) ? 30 : $d;
}

// Data-base da renovação = ENTREGA (aproximada pela entrada no estágio NPS,
// que acontece quando a fórmula é entregue). Fallback: criação do card.
function tao_crm_renov_base_ts( $card ) {
    $nps_stage = get_option( 'tao_crm_nps_stage_' . ( $card['workspace_id'] ?? '' ), '' );
    if ( $nps_stage && ! empty( $card['id'] ) ) {
        $rh = tao_crm_api( "/crm_cards_historico?card_id=eq.{$card['id']}&para_estagio_id=eq.$nps_stage&select=criado_em&order=criado_em.desc&limit=1" );
        if ( $rh['ok'] && ! empty( $rh['data'][0]['criado_em'] ) ) return strtotime( $rh['data'][0]['criado_em'] );
    }
    return strtotime( $card['criado_em'] );
}

// Adiciona N dias ÚTEIS a um timestamp (pula sáb/dom; dia calculado no horário BRT ~UTC-3).
function tao_crm_add_dias_uteis( $ts, $n ) {
    $d = (int) $ts;
    while ( $n > 0 ) {
        $d += DAY_IN_SECONDS;
        $wd = (int) gmdate( 'w', $d - 3 * HOUR_IN_SECONDS );   // 0=dom, 6=sáb
        if ( $wd !== 0 && $wd !== 6 ) $n--;
    }
    return $d;
}

// Cria um card NOVO em Funil › Aguardando Atendimento aproveitando ESTRUTURA + orçamentos
// (renumerados, estado zerado) + itens de venda do card origem. Campos obrigatórios do card
// NÃO são copiados (nascem vazios). Retorna o id do card novo (ou null). NÃO mexe no origem.
function tao_crm_renov_clonar_card( $card, $rsd, $titulo_prefixo = 'RENOVACAO' ) {
    if ( empty( $rsd['funil'] ) || empty( $rsd['aguardando'] ) ) return null;
    $tel = preg_replace( '/\D/', '', $card['contato_whatsapp'] ?? '' );
    $rn = tao_crm_api( '/crm_cards', 'POST', [
        'workspace_id'       => $card['workspace_id'],
        'pipeline_id'        => $rsd['funil'],
        'estagio_id'         => $rsd['aguardando'],
        'instancia_id'       => $card['instancia_id'] ?? null,
        'contato_id'         => $card['contato_id'] ?? null,
        'titulo'             => trim( $titulo_prefixo . ' ' . $tel ),
        'contato_nome'       => $card['contato_nome'] ?: $tel,
        'contato_whatsapp'   => $card['contato_whatsapp'],
        'atendimento_humano' => true,
        'criado_em'          => gmdate( 'c' ),
        'movido_em'          => gmdate( 'c' ),
    ], [ 'Prefer' => 'return=representation' ] );
    $novo_id = ( $rn['ok'] && ! empty( $rn['data'] ) ) ? $rn['data'][0]['id'] : null;
    if ( ! $novo_id ) return null;

    $gerar_num = function_exists( 'tao_formula_gerar_numero' );
    $ro = tao_crm_api( "/orcamentos?card_id=eq.{$card['id']}&select=*" );
    foreach ( ( $ro['ok'] ? ( $ro['data'] ?? [] ) : [] ) as $o ) {
        $cli = $o['cliente_id'] ?? '';
        foreach ( [ 'id','criado_em','atualizado_em','aprovado_em','validado_por','validado_em',
                    'validacao_automatica','concentracoes_validadas','motivo_rejeicao','ajustes_farma',
                    'enviado_em','aceito_paciente_em','estimativa_enviada_em','interesse_confirmado_em',
                    'expira_em','previsao_retirada','farmaceutico_id','txt_path','txt_gerado_em',
                    'valor_final_fc' ] as $_k ) unset( $o[ $_k ] );
        $o['card_id']          = $novo_id;
        $o['status']           = 'pendente_revisao';
        $o['numero_orcamento'] = ( $gerar_num && $cli ) ? tao_formula_gerar_numero( $cli, $novo_id )
                                                        : ( $o['numero_orcamento'] ?? '' );
        tao_crm_api( '/orcamentos', 'POST', $o );
    }
    $ri = tao_crm_api( "/crm_card_itens?card_id=eq.{$card['id']}&select=*" );
    foreach ( ( $ri['ok'] ? ( $ri['data'] ?? [] ) : [] ) as $it ) {
        unset( $it['id'], $it['criado_em'], $it['atualizado_em'] );
        $it['card_id'] = $novo_id;
        tao_crm_api( '/crm_card_itens', 'POST', $it );
    }
    if ( function_exists( 'tao_crm_sync_valor_oportunidade' ) ) tao_crm_sync_valor_oportunidade( $novo_id );
    if ( function_exists( 'tao_crm_disparar_automacoes' ) ) tao_crm_disparar_automacoes( $novo_id, $rsd['aguardando'], 'entrar_fase', false, $card['workspace_id'] );
    return $novo_id;
}

// SIM claro → renovação efetiva: clona card novo e move o ORIGEM p/ Renovado.
function tao_crm_renovar_card( $card, $rsd ) {
    $ws  = $card['workspace_id'];
    $tel = preg_replace( '/\D/', '', $card['contato_whatsapp'] ?? '' );
    $novo_id = tao_crm_renov_clonar_card( $card, $rsd, 'RENOVACAO' );
    if ( ! empty( $rsd['renovado'] ) ) {
        tao_crm_api( "/crm_cards?id=eq.{$card['id']}", 'PATCH', [ 'estagio_id' => $rsd['renovado'], 'movido_em' => gmdate( 'c' ) ] );
        tao_crm_api( '/crm_cards_historico', 'POST', [
            'card_id'         => $card['id'],
            'de_estagio_id'   => $rsd['renovacao'] ?: null,
            'para_estagio_id' => $rsd['renovado'],
            'usuario_id'      => 0,
            'motivo'          => 'Renovação aceita',
            'obs'             => 'Renovação: cliente confirmou; novo card ' . (string) $novo_id,
        ] );
    }
    tao_crm_renov_del( $card['id'] );
    if ( $tel ) tao_crm_lock_chatbot( $tel, $ws );
    tao_crm_evolution_send( tao_crm_get_evo_creds( $card ), $card['contato_whatsapp'], 'Que ótimo! 🎉 Já encaminhei sua renovação para nossa equipe — em breve entramos em contato.' );
    tao_crm_log_error( 'renovacao', 'renovado: novo card=' . substr( (string) $novo_id, 0, 8 ) . ' de=' . substr( $card['id'], 0, 8 ), [ 'tel' => $tel ] );
    return $novo_id;
}

// Retorno que NÃO é sim nem não claro → abre card de tratamento em Aguardando Atendimento.
// O ORIGEM permanece em "Renovação em Curso" (só sai quando renovado de fato ou fecha em 30 dias).
function tao_crm_renov_abrir_tratamento( $card, $rsd ) {
    $ws  = $card['workspace_id'];
    $tel = preg_replace( '/\D/', '', $card['contato_whatsapp'] ?? '' );
    $novo_id = tao_crm_renov_clonar_card( $card, $rsd, 'RETORNO RENOVACAO' );
    tao_crm_api( '/crm_cards_historico', 'POST', [
        'card_id'         => $card['id'],
        'de_estagio_id'   => $rsd['renovacao'] ?: null,
        'para_estagio_id' => $rsd['renovacao'] ?: null,
        'usuario_id'      => 0,
        'motivo'          => 'Retorno de renovação (a tratar)',
        'obs'             => 'Renovação: cliente respondeu (não foi sim/não claro); card de tratamento ' . (string) $novo_id . '. Origem segue em Renovação em Curso.',
    ] );
    tao_crm_renov_del( $card['id'] );   // já respondeu → para de lembrar; origem fica na coluna
    if ( $tel ) tao_crm_lock_chatbot( $tel, $ws );
    tao_crm_evolution_send( tao_crm_get_evo_creds( $card ), $card['contato_whatsapp'], 'Recebi sua mensagem! 🌿 Já encaminhei para nossa equipe te atender — em breve entramos em contato.' );
    tao_crm_log_error( 'renovacao', 'tratamento: novo card=' . substr( (string) $novo_id, 0, 8 ) . ' de=' . substr( $card['id'], 0, 8 ), [ 'tel' => $tel ] );
    return $novo_id;
}

// Cron horário: envia o lembrete (abertura + formula_dias) e move p/ Sem Resposta após 15 dias
add_action( 'tao_crm_renovacao_check', 'tao_crm_renovacao_cron' );
function tao_crm_renovacao_cron() {
    $rws = tao_crm_api( "/crm_workspaces?select=id&limit=200" );
    foreach ( ( $rws['ok'] ? ( $rws['data'] ?? [] ) : [] ) as $w ) {
        $ws_id = $w['id'];
        $rsd = tao_crm_renov_stages( $ws_id );
        if ( empty( $rsd['renovacao'] ) ) continue;
        if ( ! get_option( 'tao_crm_renov_ativo_' . $ws_id, 1 ) ) continue;   // renovação desligada p/ este workspace
        $r_snooze  = (int) get_option( 'tao_crm_renov_snooze_'  . $ws_id, 5 );
        $r_semresp = (int) get_option( 'tao_crm_renov_semresp_' . $ws_id, 15 );
        // Anti-rajada: máx. N lembretes por ciclo do cron (resto fica p/ a próxima hora),
        // com pausa entre envios e somente dentro do horário comercial do workspace.
        $max_ciclo = max( 1, (int) get_option( 'tao_crm_renov_maxrun_' . $ws_id, 3 ) );
        $env_ciclo = 0;
        $em_horario = ! function_exists( 'tao_crm_esta_em_horario' ) || tao_crm_esta_em_horario( $ws_id );
        $rc = tao_crm_api( "/crm_cards?workspace_id=eq.$ws_id&estagio_id=eq.{$rsd['renovacao']}&fechado=eq.false&select=id,contato_nome,contato_whatsapp,criado_em,movido_em,instancia_id,workspace_id,atendimento_humano&limit=500" );
        foreach ( ( $rc['ok'] ? ( $rc['data'] ?? [] ) : [] ) as $card ) {
            // Fecha por tempo: 30 dias na coluna "Renovação em Curso" sem ser renovado → Não Renovado.
            $mov = strtotime( $card['movido_em'] ?? ( $card['criado_em'] ?? '' ) );
            if ( $mov && ( time() - $mov ) >= 30 * DAY_IN_SECONDS ) {
                if ( ! empty( $rsd['nao_renovado'] ) ) {
                    tao_crm_api( "/crm_cards?id=eq.{$card['id']}", 'PATCH', [ 'estagio_id' => $rsd['nao_renovado'], 'movido_em' => gmdate( 'c' ) ] );
                    tao_crm_api( '/crm_cards_historico', 'POST', [
                        'card_id' => $card['id'], 'de_estagio_id' => $rsd['renovacao'], 'para_estagio_id' => $rsd['nao_renovado'],
                        'usuario_id' => 0, 'motivo' => 'Renovação não concluída em 30 dias',
                        'obs' => 'Renovação: 30 dias na coluna sem renovar — encerrado automaticamente',
                    ] );
                }
                tao_crm_renov_del( $card['id'] );
                continue;
            }
            if ( ! empty( $card['atendimento_humano'] ) ) continue;
            $cid = $card['id'];
            $st  = tao_crm_renov_get( $cid );
            $enviado = $st['enviado_em'] ?? null;
            if ( ! $enviado ) {
                $due = ! empty( $st['proximo'] ) ? strtotime( $st['proximo'] )
                     : ( tao_crm_renov_base_ts( $card ) + tao_crm_formula_dias( $cid ) * DAY_IN_SECONDS );
                if ( time() >= $due ) {
                    if ( ! $em_horario || $env_ciclo >= $max_ciclo ) continue;   // fica pendente p/ o próximo ciclo
                    if ( $env_ciclo > 0 ) sleep( rand( 8, 20 ) );                // nunca 2 msgs no mesmo instante
                    $nome   = trim( $card['contato_nome'] ?? '' );
                    $padrao = "Olá" . ( $nome ? " $nome" : '' ) . "! Notamos que sua fórmula está acabando. 🌿\n\nDeseja renovar?\nResponda *1* para RENOVAR, *2* para não renovar ou *3* para te lembrarmos novamente em {dias} dias.";
                    $msg    = get_option( 'tao_crm_renov_msg_' . $ws_id, $padrao );
                    $msg    = str_replace( [ '{nome}', '{dias}' ], [ $nome, $r_snooze ], $msg );
                    tao_crm_evolution_send( tao_crm_get_evo_creds( $card ), $card['contato_whatsapp'], $msg );
                    tao_crm_lock_chatbot( preg_replace( '/\D/', '', $card['contato_whatsapp'] ), $ws_id );
                    tao_crm_renov_set( $cid, [ 'enviado_em' => gmdate( 'c' ), 'proximo' => null ] );
                    $env_ciclo++;
                    // Evento datado p/ o indicador "Renovações" do painel
                    tao_crm_api( '/crm_cards_historico', 'POST', [
                        'card_id'         => $cid,
                        'de_estagio_id'   => $rsd['renovacao'],
                        'para_estagio_id' => $rsd['renovacao'],
                        'usuario_id'      => 0,
                        'motivo'          => 'Lembrete de renovação enviado',
                        'obs'             => 'Renovação: lembrete enviado',
                    ] );
                    tao_crm_log_error( 'renovacao', 'lembrete enviado card=' . substr( $cid, 0, 8 ), [ 'ws' => substr( $ws_id, 0, 8 ) ] );
                }
            } else {
                if ( time() >= strtotime( $enviado ) + $r_semresp * DAY_IN_SECONDS ) {
                    if ( ! empty( $rsd['sem_resposta'] ) ) {
                        tao_crm_api( "/crm_cards?id=eq.$cid", 'PATCH', [ 'estagio_id' => $rsd['sem_resposta'], 'movido_em' => gmdate( 'c' ) ] );
                        tao_crm_api( '/crm_cards_historico', 'POST', [
                            'card_id'         => $cid,
                            'de_estagio_id'   => $rsd['renovacao'],
                            'para_estagio_id' => $rsd['sem_resposta'],
                            'usuario_id'      => 0,
                            'motivo'          => 'Não responde os contatos',
                            'obs'             => 'Renovação: ' . $r_semresp . ' dias sem resposta ao lembrete',
                        ] );
                    }
                    tao_crm_renov_del( $cid );
                    tao_crm_log_error( 'renovacao', 'sem resposta 15d card=' . substr( $cid, 0, 8 ), [ 'ws' => substr( $ws_id, 0, 8 ) ] );
                }
            }
        }
    }
}

// ─── AJAX: ESTATÍSTICAS CSAT ──────────────────────────────────────────────────
add_action( 'wp_ajax_tao_crm_get_csat_stats', 'tao_crm_ajax_get_csat_stats' );
function tao_crm_ajax_get_csat_stats() {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Acesso negado' );
    $ws_id     = sanitize_text_field( $_POST['ws_id'] ?? '' );
    $respostas = (array) get_option( "tao_crm_csat_respostas_$ws_id", [] );
    if ( empty( $respostas ) ) {
        wp_send_json_success( [ 'total' => 0, 'media' => 0, 'dist' => [], 'recentes' => [] ] );
        return;
    }
    $total   = count( $respostas );
    $soma    = array_sum( array_column( $respostas, 'nota' ) );
    $media   = round( $soma / $total, 2 );
    $notas   = array_column( $respostas, 'nota' );
    $dist    = array_count_values( $notas );
    ksort( $dist );
    $recentes = array_slice( array_reverse( $respostas ), 0, 20 );
    wp_send_json_success( [ 'total' => $total, 'media' => $media, 'dist' => $dist, 'recentes' => $recentes ] );
}

// ─── AJAX: INBOX — cards com mensagens não lidas ──────────────────────────────

// ─── AJAX: ANÁLISE DE PREÇOS DO CARD (todos os orçamentos) ────────────────────
// Resumo por orçamento (calculado, cobrado, custo, margem) + consolidado.
// Custo = Σ MPs (custo_por_unidade do item, fallback cadastro de ativos)
//       + embalagens (quantidade × custo) + cápsulas (tipos_capsula → ativo).
add_action( 'wp_ajax_tao_crm_card_analise_precos', 'tao_crm_ajax_card_analise_precos' );
function tao_crm_ajax_card_analise_precos() {
    while ( ob_get_level() > 0 ) ob_end_clean();
    nocache_headers();
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    if ( ! function_exists( 'cbpm_can_access' ) || ! cbpm_can_access() ) wp_send_json_error( 'Acesso negado' );
    $card_id = sanitize_text_field( $_POST['card_id'] ?? '' );
    if ( ! $card_id ) wp_send_json_error( 'card_id obrigatório' );

    // Valor do concorrente: persiste quando enviado (vazio = limpar)
    if ( isset( $_POST['valor_concorrente'] ) ) {
        $vc_raw = preg_replace( '/[^\d.,]/', '', (string) wp_unslash( $_POST['valor_concorrente'] ) );
        // "1.234,56" → 1234.56 | "150.50" → 150.50 | "150,50" → 150.50
        if ( strpos( $vc_raw, ',' ) !== false ) $vc_raw = str_replace( [ '.', ',' ], [ '', '.' ], $vc_raw );
        if ( $vc_raw === '' || (float) $vc_raw <= 0 ) delete_option( 'tao_crm_conc_' . $card_id );
        else update_option( 'tao_crm_conc_' . $card_id, (float) $vc_raw, false );
    }

    $ro = tao_crm_api( "/orcamentos?card_id=eq.$card_id&select=id,numero_orcamento,total_orcamento,valor_final_fc,itens,forma_vol,forma_unidade,qtde_potes,cliente_id,custo_fixo_aplicado,acrescimo_aplicado,forma_id&order=criado_em.asc" );
    if ( ! $ro['ok'] ) wp_send_json_error( $ro['error'] );
    $orcs = $ro['data'] ?? [];
    if ( ! $orcs ) wp_send_json_success( [ 'linhas' => [], 'total' => null, 'valor_concorrente' => (float) get_option( 'tao_crm_conc_' . $card_id, 0 ) ] );

    // ── 1ª passada: decodifica itens e coleta fallbacks necessários ─────────
    $need_ativo = [];   // ativo_ids de MPs sem custo no item
    $need_caps  = [];   // "tipo|numero" de cápsulas
    foreach ( $orcs as &$o ) {
        $it = is_string( $o['itens'] ) ? json_decode( $o['itens'], true ) : $o['itens'];
        $o['_itens'] = is_array( $it ) ? $it : [];
        foreach ( $o['_itens'] as $i ) {
            if ( ( $i['tipo'] ?? '' ) === 'mp' ) {
                if ( (float) ( $i['custo_por_unidade'] ?? 0 ) <= 0 && ! empty( $i['ativo_id'] ) ) $need_ativo[ $i['ativo_id'] ] = 1;
                if ( ! empty( $i['capsula_tipo'] ) ) $need_caps[ strtolower( $i['capsula_tipo'] ) . '|' . ( $i['capsula_numero'] ?? '' ) ] = 1;
            }
        }
    }
    unset( $o );

    // Fallback de custo das MPs pelo cadastro de ativos
    $ativo_custo = [];
    if ( $need_ativo ) {
        $ra = tao_crm_api( '/ativos?id=in.(' . implode( ',', array_keys( $need_ativo ) ) . ')&select=id,custo_por_unidade,preco_compra' );
        foreach ( ( $ra['ok'] ? ( $ra['data'] ?? [] ) : [] ) as $a ) {
            $ativo_custo[ $a['id'] ] = (float) ( $a['custo_por_unidade'] ?? 0 ) ?: (float) ( $a['preco_compra'] ?? 0 );
        }
    }

    // Custo unitário da cápsula: tipos_capsula (tipo+numero) → ativo (cdpro_fc) → fallback nome INCOLOR
    $caps_custo = [];
    if ( $need_caps ) {
        $cli = $orcs[0]['cliente_id'] ?? '';
        $rtc = tao_crm_api( "/tipos_capsula?cliente_id=eq.$cli&select=tipo,numero,cdpro_fc" );
        $tc_map = [];
        foreach ( ( $rtc['ok'] ? ( $rtc['data'] ?? [] ) : [] ) as $tc ) $tc_map[ strtolower( $tc['tipo'] ) . '|' . $tc['numero'] ] = $tc['cdpro_fc'] ?? '';
        $codes = array_filter( array_map( function ( $k ) use ( $tc_map ) { return $tc_map[ $k ] ?? ''; }, array_keys( $need_caps ) ) );
        $code_price = [];
        if ( $codes ) {
            $rca = tao_crm_api( '/ativos?codigo_fc=in.(' . implode( ',', array_unique( $codes ) ) . ')&select=codigo_fc,custo_por_unidade,preco_compra,preco_venda' );
            foreach ( ( $rca['ok'] ? ( $rca['data'] ?? [] ) : [] ) as $a ) {
                $code_price[ $a['codigo_fc'] ] = (float) ( $a['custo_por_unidade'] ?? 0 ) ?: ( (float) ( $a['preco_compra'] ?? 0 ) ?: (float) ( $a['preco_venda'] ?? 0 ) );
            }
        }
        $incolor = null;   // lazy: só busca se precisar
        foreach ( array_keys( $need_caps ) as $k ) {
            $custo_u = $code_price[ $tc_map[ $k ] ?? '' ] ?? 0;
            if ( $custo_u <= 0 ) {
                if ( $incolor === null ) {
                    $ri = tao_crm_api( "/ativos?cliente_id=eq.$cli&nome=ilike.*INCOLOR*&select=nome,custo_por_unidade,preco_compra,preco_venda&limit=100" );
                    $incolor = $ri['ok'] ? ( $ri['data'] ?? [] ) : [];
                }
                $num = explode( '|', $k )[1];
                foreach ( $incolor as $ia ) {
                    if ( $num !== '' && preg_match( '/\b' . preg_quote( $num, '/' ) . '\b/', strtoupper( $ia['nome'] ?? '' ) ) ) {
                        $custo_u = (float) ( $ia['custo_por_unidade'] ?? 0 ) ?: ( (float) ( $ia['preco_compra'] ?? 0 ) ?: (float) ( $ia['preco_venda'] ?? 0 ) );
                        break;
                    }
                }
            }
            $caps_custo[ $k ] = $custo_u;
        }
    }

    // Custo fixo cadastrado por forma farmacêutica (fallback quando o orçamento não tem CF salvo)
    $forma_cf  = [];
    $forma_ids = array_filter( array_unique( array_column( $orcs, 'forma_id' ) ) );
    if ( $forma_ids ) {
        $rf = tao_crm_api( '/formas_farmaceuticas?id=in.(' . implode( ',', $forma_ids ) . ')&select=id,custo_fixo,custo_fixo_tipo' );
        foreach ( ( $rf['ok'] ? ( $rf['data'] ?? [] ) : [] ) as $f ) {
            $forma_cf[ $f['id'] ] = [ 'valor' => (float) ( $f['custo_fixo'] ?? 0 ), 'tipo' => $f['custo_fixo_tipo'] ?? '' ];
        }
    }

    // ── 2ª passada: custo e margem por orçamento ─────────────────────────────
    // Custo = Ativos (MPs) + Embalagens + Cápsulas + Custo Fixo aplicado.
    // CF sem valor salvo no orçamento → usa o cadastro da forma: 'pct' aplica o % sobre
    // o custo (ativos+emb+cáps); 'R' usa o valor fixo.
    // Acréscimo aplicado é exibido na composição, mas NÃO soma (compõe o preço, não o custo).
    $linhas = [];
    $tot    = [ 'calculado' => 0.0, 'cobrado' => 0.0, 'custo' => 0.0,
                'ativos' => 0.0, 'embalagens' => 0.0, 'capsulas' => 0.0, 'custo_fixo' => 0.0, 'acrescimo' => 0.0 ];
    foreach ( $orcs as $o ) {
        $c_mp = 0.0; $c_emb = 0.0; $c_caps = 0.0; $sem_custo = false;
        $cap_key = ''; $n_per_dose = 1;
        foreach ( $o['_itens'] as $i ) {
            $tipo_i = $i['tipo'] ?? '';
            if ( $tipo_i === 'mp' ) {
                $cpu = (float) ( $i['custo_por_unidade'] ?? 0 );
                if ( $cpu <= 0 ) $cpu = $ativo_custo[ $i['ativo_id'] ?? '' ] ?? 0;
                $qtd = (float) ( $i['qtd_total_g'] ?? 0 );
                if ( $cpu <= 0 && $qtd > 0 ) $sem_custo = true;
                $c_mp += $qtd * $cpu;
                if ( ! empty( $i['capsula_tipo'] ) ) {
                    $cap_key    = strtolower( $i['capsula_tipo'] ) . '|' . ( $i['capsula_numero'] ?? '' );
                    $n_per_dose = max( 1, intval( $i['n_caps_por_dose'] ?? 1 ) );
                }
            } elseif ( $tipo_i === 'emb' ) {
                $cpu = (float) ( $i['custo_por_unidade'] ?? 0 );
                $qty = (float) ( $i['quantidade'] ?? 1 );
                $c_emb += $cpu > 0 ? $qty * $cpu : (float) ( $i['subtotal'] ?? 0 );
            }
        }
        // Cápsulas: total = doses (forma_vol) × potes × cápsulas por dose
        if ( $cap_key && stripos( (string) ( $o['forma_unidade'] ?? '' ), 'cap' ) !== false ) {
            $ncaps  = (float) ( $o['forma_vol'] ?? 0 ) * max( 1, intval( $o['qtde_potes'] ?? 1 ) ) * $n_per_dose;
            $cap_cu = $caps_custo[ $cap_key ] ?? 0;
            if ( $cap_cu <= 0 && $ncaps > 0 ) $sem_custo = true;
            $c_caps = $ncaps * $cap_cu;
        }
        $c_fixo = (float) ( $o['custo_fixo_aplicado'] ?? 0 );
        if ( $c_fixo <= 0 && ! empty( $forma_cf[ $o['forma_id'] ?? '' ]['valor'] ) ) {
            $fc = $forma_cf[ $o['forma_id'] ];
            $c_fixo = ( $fc['tipo'] === 'pct' )
                ? round( ( $c_mp + $c_emb + $c_caps ) * $fc['valor'] / 100, 2 )
                : $fc['valor'];   // 'R' (ou legado sem tipo): valor fixo em R$
        }
        $acresc = (float) ( $o['acrescimo_aplicado'] ?? 0 );
        $custo  = $c_mp + $c_emb + $c_caps + $c_fixo;
        $calculado = (float) ( $o['total_orcamento'] ?? 0 );
        $cobrado   = (float) ( $o['valor_final_fc'] ?? 0 ) ?: $calculado;
        $linhas[]  = [
            'numero'     => $o['numero_orcamento'] ?: '—',
            'calculado'  => round( $calculado, 2 ),
            'cobrado'    => round( $cobrado, 2 ),
            'custo'      => round( $custo, 2 ),
            'comp'       => [
                'ativos'     => round( $c_mp, 2 ),
                'embalagens' => round( $c_emb, 2 ),
                'capsulas'   => round( $c_caps, 2 ),
                'custo_fixo' => round( $c_fixo, 2 ),
                'acrescimo'  => round( $acresc, 2 ),
            ],
            'margem_rs'  => round( $cobrado - $custo, 2 ),
            'margem_pct' => $custo > 0 ? round( ( $cobrado - $custo ) / $custo * 100, 1 ) : null,
            'sem_custo'  => $sem_custo,
        ];
        $tot['calculado']  += $calculado;
        $tot['cobrado']    += $cobrado;
        $tot['custo']      += $custo;
        $tot['ativos']     += $c_mp;
        $tot['embalagens'] += $c_emb;
        $tot['capsulas']   += $c_caps;
        $tot['custo_fixo'] += $c_fixo;
        $tot['acrescimo']  += $acresc;
    }
    $total = [
        'calculado'  => round( $tot['calculado'], 2 ),
        'cobrado'    => round( $tot['cobrado'], 2 ),
        'custo'      => round( $tot['custo'], 2 ),
        'comp'       => [
            'ativos'     => round( $tot['ativos'], 2 ),
            'embalagens' => round( $tot['embalagens'], 2 ),
            'capsulas'   => round( $tot['capsulas'], 2 ),
            'custo_fixo' => round( $tot['custo_fixo'], 2 ),
            'acrescimo'  => round( $tot['acrescimo'], 2 ),
        ],
        'margem_rs'  => round( $tot['cobrado'] - $tot['custo'], 2 ),
        'margem_pct' => $tot['custo'] > 0 ? round( ( $tot['cobrado'] - $tot['custo'] ) / $tot['custo'] * 100, 1 ) : null,
    ];
    wp_send_json_success( [
        'linhas'            => $linhas,
        'total'             => $total,
        'valor_concorrente' => (float) get_option( 'tao_crm_conc_' . $card_id, 0 ),
    ] );
}

// ─── AJAX: Enviar orçamento(s) de fórmula via WhatsApp ───────────────────────

function tao_crm_rodape_orcamento() {
    return get_option( 'tao_formula_msg_rodape',
        "- Trabalhamos com entrega ( *Segunda a sexta* ), mediante taxa, via motoboy e SEDEX/DHL\n" .
        "- Acima de R\$ 100,00 parcelamos em 3 x no cartão\n" .
        "- Prazo de entrega: 2 a 3 dias úteis após aprovação do orçamento\n\n" .
        "*-->> CASO TENHA UMA PROPOSTA MELHOR, NOS ENCAMINHE QUE FAREMOS O POSSÍVEL TE ATENDER!*\n\n" .
        "Caso tenha interesse em fechar, nos informe se será *ENTREGA* ou *RETIRADA*. " .
        "Se for entrega, informe o *endereço* e a *forma de pagamento*.\n\nAtt,\nMagis-TAO"
    );
}

function tao_crm_build_orcamento_msg( array $orcs, string $nome, string $rodape ): string {
    $blocos      = [];
    $total_geral = 0;   // final (com desconto)
    $bruto_geral = 0;   // valor de venda calculado (sem desconto)
    $desc_geral  = 0;   // desconto oferecido (R$)
    $brl = function ( $v ) { return number_format( (float) $v, 2, ',', '.' ); };
    foreach ( $orcs as $o ) {
        $numero  = $o['numero_orcamento'] ?? '—';
        $total   = (float) ( $o['total_orcamento'] ?? 0 );          // valor com desconto (final)
        $desc    = (float) ( $o['desconto_fc'] ?? 0 );              // desconto oferecido em R$
        // Desconto pode estar só em % (sem o R$): deriva o cheio a partir do percentual
        if ( $desc <= 0.005 ) {
            $pct = (float) ( $o['desconto_pct'] ?? 0 );
            if ( $pct > 0 && $pct < 100 ) $desc = round( $total / ( 1 - $pct / 100 ) - $total, 2 );
        }
        $bruto   = $total + $desc;                                   // valor de venda calculado
        $total_geral += $total; $bruto_geral += $bruto; $desc_geral += $desc;
        $itens   = is_string( $o['itens'] ) ? json_decode( $o['itens'], true ) : ( $o['itens'] ?? [] );
        $obs     = trim( $o['observacoes'] ?? '' );
        // Orçamentos importados (sem itens): usa a descrição original preservada em observacoes
        if ( empty( $itens ) && $obs ) {
            $descr = $obs;
        } else {
            $descr = function_exists( 'tao_formula_build_descricao' )
                ? tao_formula_build_descricao( $o['forma_nome'] ?? '', $o['forma_vol'] ?? 0, $o['forma_unidade'] ?? 'g', $itens, $o['qtde_potes'] ?? 1 )
                : 'FORMULA MANIPULADA - ' . strtoupper( $o['forma_nome'] ?? '' );
        }
        // Cada ITEM (fórmula) mostra o SEU preço total; o fechamento traz VALOR TOTAL + VALOR COM DESCONTO.
        $blocos[] = "ORC:{$numero}\n{$descr}\nValor R\$: " . $brl( $bruto );
    }
    $msg  = "Prezado(a) *{$nome}*,\n\n";
    $msg .= "Seguem detalhes da sua solicitação de orçamento:\n\n\n";
    $msg .= implode( "\n\n", $blocos ) . "\n\n";
    $msg .= "VALOR TOTAL: R\$ " . $brl( $bruto_geral ) . "\n";
    if ( $desc_geral > 0.005 ) {
        $msg .= "\n*VALOR COM DESCONTO: R\$ " . $brl( $total_geral ) . "*\n";
    }
    $msg .= "\n" . $rodape;
    return $msg;
}

add_action( 'wp_ajax_tao_crm_preview_orcamento_formula', 'tao_crm_ajax_preview_orcamento_formula' );
function tao_crm_ajax_preview_orcamento_formula() {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    if ( ! function_exists( 'cbpm_can_access' ) || ! cbpm_can_access() ) wp_send_json_error( 'Acesso negado' );

    $card_id = sanitize_text_field( $_POST['card_id'] ?? '' );
    $orc_ids = array_filter( array_map( 'sanitize_text_field', (array) ( $_POST['orc_ids'] ?? [] ) ) );
    if ( ! $card_id || empty( $orc_ids ) ) wp_send_json_error( 'Dados inválidos' );

    if ( ! function_exists( 'tao_formula_api' ) ) wp_send_json_error( 'Plugin TAO Fórmulas não ativo' );

    $rc = tao_crm_api( "/crm_cards?id=eq.$card_id&select=contato_nome&limit=1" );
    if ( ! $rc['ok'] || empty( $rc['data'] ) ) wp_send_json_error( 'Card não encontrado' );
    $nome = $rc['data'][0]['contato_nome'] ?? 'cliente';

    $ids_str = implode( ',', $orc_ids );
    $ro = tao_formula_api(
        "/orcamentos?id=in.($ids_str)" .
        "&select=numero_orcamento,forma_nome,forma_vol,forma_unidade,qtde_potes," .
        "total_orcamento,desconto_fc,desconto_pct,itens,observacoes,nome_paciente&order=criado_em.asc"
    );
    if ( ! $ro['ok'] || empty( $ro['data'] ) ) wp_send_json_error( 'Orçamentos não encontrados' );

    $msg = tao_crm_build_orcamento_msg( $ro['data'], $nome, tao_crm_rodape_orcamento() );
    wp_send_json_success( [ 'mensagem' => $msg ] );
}

add_action( 'wp_ajax_tao_crm_enviar_orcamento_formula', 'tao_crm_ajax_enviar_orcamento_formula' );
function tao_crm_ajax_enviar_orcamento_formula() {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    if ( ! function_exists( 'cbpm_can_access' ) || ! cbpm_can_access() ) wp_send_json_error( 'Acesso negado' );

    $card_id = sanitize_text_field( $_POST['card_id'] ?? '' );
    $orc_ids = array_filter( array_map( 'sanitize_text_field', (array) ( $_POST['orc_ids'] ?? [] ) ) );
    if ( ! $card_id || empty( $orc_ids ) ) wp_send_json_error( 'Dados inválidos' );

    $rc = tao_crm_api( "/crm_cards?id=eq.$card_id&select=contato_whatsapp,workspace_id,instancia_id,contato_nome&limit=1" );
    if ( ! $rc['ok'] || empty( $rc['data'] ) ) wp_send_json_error( 'Card não encontrado' );
    $card    = $rc['data'][0];
    $evo_cfg = tao_crm_get_evo_creds( $card );
    if ( ! $evo_cfg ) wp_send_json_error( 'Sem Evolution configurado para este card' );

    if ( ! function_exists( 'tao_formula_api' ) ) wp_send_json_error( 'Plugin TAO Fórmulas não ativo' );

    $msg_custom = trim( wp_unslash( $_POST['mensagem'] ?? '' ) );
    if ( $msg_custom ) {
        $msg = $msg_custom;
    } else {
        $ids_str = implode( ',', $orc_ids );
        $ro = tao_formula_api(
            "/orcamentos?id=in.($ids_str)" .
            "&select=numero_orcamento,forma_nome,forma_vol,forma_unidade,qtde_potes," .
            "total_orcamento,desconto_fc,desconto_pct,itens,observacoes,nome_paciente&order=criado_em.asc"
        );
        if ( ! $ro['ok'] || empty( $ro['data'] ) ) wp_send_json_error( 'Orçamentos não encontrados' );
        $nome = $card['contato_nome'] ?? 'cliente';
        $msg  = tao_crm_build_orcamento_msg( $ro['data'], $nome, tao_crm_rodape_orcamento() );
    }

    $ok = tao_crm_evolution_send_with_retry( $evo_cfg, $card['contato_whatsapp'], $msg );
    if ( ! $ok ) wp_send_json_error( 'Falha ao enviar via Evolution' );

    $user = wp_get_current_user();
    tao_crm_api( '/crm_mensagens', 'POST', [
        'card_id'        => $card_id,
        'workspace_id'   => $card['workspace_id'],
        'direcao'        => 'out',
        'tipo'           => 'text',
        'conteudo'       => $msg,
        'remetente_nome' => $user->display_name,
        'enviado_em'     => gmdate( 'c' ),
    ], [ 'Prefer' => 'return=representation' ] );

    foreach ( $orc_ids as $oid ) {
        tao_formula_api( "/orcamentos?id=eq.$oid", 'PATCH', [
            'status'        => 'enviado_paciente',
            'atualizado_em' => gmdate( 'c' ),
        ] );
    }

    wp_send_json_success( 'Mensagem enviada' );
}

add_action( 'wp_ajax_tao_crm_inbox_count', 'tao_crm_ajax_inbox_count' );
function tao_crm_ajax_inbox_count() {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    if ( ! function_exists( 'cbpm_can_access' ) || ! cbpm_can_access() ) wp_send_json_error( 'Acesso negado' );
    $ws_id = sanitize_text_field( $_POST['workspace_id'] ?? '' );
    if ( ! $ws_id ) wp_send_json_success( [ 'count' => 0 ] );
    // Cards abertos com ultima_mensagem_em > ultima_leitura_em (ou leitura nula)
    $r = tao_crm_api( "/crm_cards?workspace_id=eq.$ws_id&fechado=eq.false&ultima_mensagem_em=not.is.null&select=id,ultima_mensagem_em,ultima_leitura_em&limit=200" );
    $count = 0;
    foreach ( ( $r['ok'] ? ( $r['data'] ?? [] ) : [] ) as $c ) {
        $msg = $c['ultima_mensagem_em'] ?? '';
        $lida = $c['ultima_leitura_em'] ?? '';
        if ( $msg && ( ! $lida || $msg > $lida ) ) $count++;
    }
    wp_send_json_success( [ 'count' => $count ] );
}

// ─── AJAX: NOTA INTERNA ───────────────────────────────────────────────────────

add_action( 'wp_ajax_tao_crm_save_nota', 'tao_crm_ajax_save_nota' );
function tao_crm_ajax_save_nota() {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    if ( ! function_exists( 'cbpm_can_access' ) || ! cbpm_can_access() ) wp_send_json_error( 'Acesso negado' );

    $card_id  = sanitize_text_field( $_POST['card_id'] ?? '' );
    $conteudo = sanitize_textarea_field( $_POST['conteudo'] ?? '' );
    if ( ! $card_id || ! $conteudo ) wp_send_json_error( 'Dados inválidos' );

    $rc = tao_crm_api( "/crm_cards?id=eq.$card_id&select=workspace_id&limit=1" );
    if ( ! $rc['ok'] || empty( $rc['data'] ) ) wp_send_json_error( 'Card não encontrado' );
    $workspace_id = $rc['data'][0]['workspace_id'];

    $user = wp_get_current_user();
    $r = tao_crm_api( '/crm_mensagens', 'POST', [
        'card_id'        => $card_id,
        'workspace_id'   => $workspace_id,
        'direcao'        => 'note',
        'tipo'           => 'nota',
        'conteudo'       => $conteudo,
        'remetente_nome' => $user->display_name ?: $user->user_login,
        'enviado_em'     => gmdate( 'c' ),
    ], [ 'Prefer' => 'return=representation' ] );

    if ( ! $r['ok'] ) wp_send_json_error( $r['error'] );
    wp_send_json_success( $r['data'][0] ?? [] );
}

// ─── AJAX: EDITAR DADOS DO CARD ───────────────────────────────────────────────

add_action( 'wp_ajax_tao_crm_update_card_info', 'tao_crm_ajax_update_card_info' );
function tao_crm_ajax_update_card_info() {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    if ( ! function_exists( 'cbpm_can_access' ) || ! cbpm_can_access() ) wp_send_json_error( 'Acesso negado' );

    $card_id = sanitize_text_field( $_POST['card_id'] ?? '' );
    if ( ! $card_id ) wp_send_json_error( 'card_id obrigatório' );

    $patch = [];
    if ( isset( $_POST['titulo'] ) )            $patch['titulo']            = sanitize_text_field( $_POST['titulo'] );
    if ( isset( $_POST['contato_nome'] ) )      $patch['contato_nome']      = sanitize_text_field( $_POST['contato_nome'] );
    if ( isset( $_POST['contato_whatsapp'] ) )  $patch['contato_whatsapp']  = preg_replace( '/\D/', '', $_POST['contato_whatsapp'] );

    // Campos do contato (salvos em crm_contatos se contato_id existir)
    $patch_contato = [];
    if ( isset( $_POST['contato_email'] ) ) $patch_contato['email'] = sanitize_email( $_POST['contato_email'] );
    if ( isset( $_POST['contato_cpf'] ) )   $patch_contato['cpf']   = preg_replace( '/\D/', '', $_POST['contato_cpf'] );
    if ( isset( $_POST['contato_cep'] ) )          $patch_contato['cep']          = preg_replace( '/\D/', '', sanitize_text_field( $_POST['contato_cep'] ) );
    if ( isset( $_POST['contato_logradouro'] ) )   $patch_contato['logradouro']   = sanitize_text_field( $_POST['contato_logradouro'] );
    if ( isset( $_POST['contato_numero'] ) )       $patch_contato['numero']       = sanitize_text_field( $_POST['contato_numero'] );
    if ( isset( $_POST['contato_complemento'] ) )  $patch_contato['complemento']  = sanitize_text_field( $_POST['contato_complemento'] );
    if ( isset( $_POST['contato_bairro'] ) )       $patch_contato['bairro']       = sanitize_text_field( $_POST['contato_bairro'] );
    if ( isset( $_POST['contato_cidade'] ) )       $patch_contato['cidade']       = sanitize_text_field( $_POST['contato_cidade'] );
    if ( isset( $_POST['contato_classificacao'] ) ) $patch_contato['classificacao'] = sanitize_text_field( $_POST['contato_classificacao'] );
    if ( isset( $_POST['contato_observacao'] ) )   $patch_contato['observacoes']  = sanitize_textarea_field( $_POST['contato_observacao'] );

    if ( empty( $patch ) && empty( $patch_contato ) ) wp_send_json_error( 'Nenhum campo enviado' );

    // Se o telefone foi atualizado, verifica se o anterior era @lid → salva mapeamento
    if ( ! empty( $patch['contato_whatsapp'] ) ) {
        $rc_old = tao_crm_api( "/crm_cards?id=eq.$card_id&select=contato_whatsapp&limit=1" );
        if ( $rc_old['ok'] && ! empty( $rc_old['data'] ) ) {
            $old_phone = $rc_old['data'][0]['contato_whatsapp'] ?? '';
            $new_phone = $patch['contato_whatsapp'];
            if ( tao_crm_is_lid_num( $old_phone ) && ! tao_crm_is_lid_num( $new_phone ) && $new_phone ) {
                tao_crm_save_lid_mapping( $old_phone, $new_phone );
            }
        }
    }

    if ( ! empty( $patch ) ) {
        $r = tao_crm_api( "/crm_cards?id=eq.$card_id", 'PATCH', $patch );
        if ( ! $r['ok'] ) wp_send_json_error( $r['error'] );
    }

    // Atualiza crm_contatos se houver email/CPF e contato_id vinculado
    if ( ! empty( $patch_contato ) ) {
        $rc_cid = tao_crm_api( "/crm_cards?id=eq.$card_id&select=contato_id&limit=1" );
        $contato_id = $rc_cid['ok'] && ! empty( $rc_cid['data'] ) ? ( $rc_cid['data'][0]['contato_id'] ?? null ) : null;
        if ( $contato_id ) {
            $patch_contato['atualizado_em'] = gmdate( 'c' );
            tao_crm_api( "/crm_contatos?id=eq.$contato_id", 'PATCH', $patch_contato );
        }
    }

    wp_send_json_success();
}

// ─── AJAX: DELETAR WORKSPACE ─────────────────────────────────────────────────

add_action( 'wp_ajax_tao_crm_delete_workspace', 'tao_crm_ajax_delete_workspace' );
function tao_crm_ajax_delete_workspace() {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Acesso negado' );
    $ws_id = sanitize_text_field( $_POST['ws_id'] ?? '' );
    if ( ! $ws_id ) wp_send_json_error( 'ID inválido' );
    $r = tao_crm_api( "/crm_workspaces?id=eq.$ws_id", 'PATCH', [ 'ativo' => false ] );
    if ( ! $r['ok'] ) wp_send_json_error( $r['error'] );
    wp_send_json_success();
}

// ─── AJAX: DELETAR PIPELINE ───────────────────────────────────────────────────

add_action( 'wp_ajax_tao_crm_delete_pipeline', 'tao_crm_ajax_delete_pipeline' );
function tao_crm_ajax_delete_pipeline() {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Acesso negado' );
    $pl_id = sanitize_text_field( $_POST['pl_id'] ?? '' );
    if ( ! $pl_id ) wp_send_json_error( 'ID inválido' );
    $r = tao_crm_api( "/crm_pipelines?id=eq.$pl_id", 'PATCH', [ 'ativo' => false ] );
    if ( ! $r['ok'] ) wp_send_json_error( $r['error'] );
    wp_send_json_success();
}

// ─── AJAX: KANBAN CHECK (polling de alterações) ───────────────────────────────

add_action( 'wp_ajax_tao_crm_kanban_check', 'tao_crm_ajax_kanban_check' );
function tao_crm_ajax_kanban_check() {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    if ( ! function_exists( 'cbpm_can_access' ) || ! cbpm_can_access() ) wp_send_json_error( 'Acesso negado' );
    $pipeline_id = sanitize_text_field( $_POST['pipeline_id'] ?? '' );
    if ( ! $pipeline_id ) wp_send_json_error( 'Pipeline inválido' );
    $since       = sanitize_text_field( $_POST['since'] ?? '' );

    $r = tao_crm_api( "/crm_cards?pipeline_id=eq.$pipeline_id&fechado=eq.false&select=movido_em,ultima_mensagem_em,criado_em,atendimento_humano&order=movido_em.desc&limit=50" );
    if ( ! $r['ok'] || empty( $r['data'] ) ) {
        wp_send_json_success( [ 'last' => '', 'new_handoff' => false ] );
        return;
    }
    $last        = '';
    $new_handoff = false;
    foreach ( $r['data'] as $c ) {
        $ts = max( $c['movido_em'] ?? '', $c['ultima_mensagem_em'] ?? '', $c['criado_em'] ?? '' );
        if ( $ts > $last ) $last = $ts;
        if ( ! empty( $c['atendimento_humano'] ) && $since && ( ( $c['criado_em'] ?? '' ) > $since ) ) {
            $new_handoff = true;
        }
    }
    wp_send_json_success( [ 'last' => $last, 'new_handoff' => $new_handoff ] );
}

// ─── AJAX: AÇÃO EM LOTE (bulk actions no Kanban) ─────────────────────────────

add_action( 'wp_ajax_tao_crm_bulk_action', 'tao_crm_ajax_bulk_action' );
function tao_crm_ajax_bulk_action() {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    if ( ! function_exists( 'cbpm_can_access' ) || ! cbpm_can_access() ) wp_send_json_error( 'Acesso negado' );

    $action   = sanitize_key( $_POST['bulk_action'] ?? '' );
    $card_ids = array_filter( array_map( 'sanitize_text_field', (array) ( $_POST['card_ids'] ?? [] ) ) );
    if ( empty( $card_ids ) ) wp_send_json_error( 'Nenhum card selecionado' );

    $results = [];
    if ( $action === 'transferir' ) {
        $novo_uid = intval( $_POST['novo_responsavel_id'] ?? 0 );
        if ( ! $novo_uid ) wp_send_json_error( 'Responsável inválido' );
        foreach ( $card_ids as $cid ) {
            $r = tao_crm_api( "/crm_cards?id=eq.$cid", 'PATCH', [ 'responsavel_id' => $novo_uid ] );
            $results[ $cid ] = $r['ok'] ? 'ok' : 'err';
        }
    } elseif ( in_array( $action, [ 'fechar_ganho', 'fechar_perdido' ], true ) ) {
        $tipo = $action === 'fechar_ganho' ? 'ganho' : 'perdido';
        $bulk_motivo = sanitize_textarea_field( wp_unslash( $_POST['motivo'] ?? '' ) );
        if ( $tipo === 'perdido' && $bulk_motivo === '' ) wp_send_json_error( 'Informe o motivo do cancelamento.' );
        foreach ( $card_ids as $cid ) {
            $rc = tao_crm_api( "/crm_cards?id=eq.$cid&limit=1" );
            if ( ! $rc['ok'] || empty( $rc['data'] ) ) { $results[$cid] = 'err'; continue; }
            $card = $rc['data'][0];
            $de   = $card['estagio_id'];

            // GANHO: todo ganho do Funil de Vendas cruza pro Pós-vendas (mesma regra do individual)
            if ( $tipo === 'ganho' && tao_crm_cruzar_para_pos_vendas( $cid, $card, $de ) ) {
                $results[ $cid ] = 'ok';
                continue;
            }

            // Fallback (perdido, ou ganho sem pós-vendas configurado): fecha no estágio terminal
            $re = tao_crm_api( "/crm_estagios?pipeline_id=eq.{$card['pipeline_id']}&tipo=eq.$tipo&order=ordem.asc&limit=1" );
            if ( ! $re['ok'] || empty( $re['data'] ) ) { $results[$cid] = 'no_stage'; continue; }
            $close_stage = $re['data'][0]['id'];
            tao_crm_api( "/crm_cards?id=eq.$cid", 'PATCH', [
                'estagio_id' => $close_stage,
                'fechado'    => true,
                'status'     => $tipo,
                'movido_em'  => gmdate( 'c' ),
            ] );
            if ( $de ) tao_crm_api( '/crm_cards_historico', 'POST', [
                'card_id'         => $cid,
                'de_estagio_id'   => $de,
                'para_estagio_id' => $close_stage,
                'usuario_id'      => get_current_user_id(),
                'motivo'          => $tipo === 'perdido' ? $bulk_motivo : null,
            ] );
            tao_crm_fire_webhook( $card['workspace_id'], 'card_fechado_' . $tipo, [ 'card_id' => $cid, 'motivo' => $bulk_motivo ?: null ] );
            $results[ $cid ] = 'ok';
        }
    } else {
        wp_send_json_error( 'Ação inválida' );
    }

    $ok_count  = count( array_filter( $results, fn( $v ) => $v === 'ok' ) );
    wp_send_json_success( [ 'total' => count( $card_ids ), 'ok' => $ok_count, 'results' => $results ] );
}

// ─── AJAX: CONFIGURAR PIPELINE PÓS-VENDAS ────────────────────────────────────

add_action( 'wp_ajax_tao_crm_set_pos_vendas', 'tao_crm_ajax_set_pos_vendas' );
function tao_crm_ajax_set_pos_vendas() {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Acesso negado' );
    $ws_id = sanitize_text_field( $_POST['ws_id'] ?? '' );
    $pl_id = sanitize_text_field( $_POST['pl_id'] ?? '' );
    if ( ! $ws_id ) wp_send_json_error( 'Workspace inválido' );
    $opt_key = 'tao_crm_pos_vendas_pipeline_' . $ws_id;
    if ( $pl_id ) {
        update_option( $opt_key, $pl_id );
    } else {
        delete_option( $opt_key );
    }
    wp_send_json_success();
}

// ─── REST API: DISPATCH EVOLUTION ─────────────────────────────────────────────

// ─── WEBHOOK DE SAÍDA ─────────────────────────────────────────────────────────

function tao_crm_fire_webhook( string $workspace_id, string $evento, array $payload ) {
    if ( ! $workspace_id ) return;
    $rw = tao_crm_api( "/crm_webhooks_saida?workspace_id=eq.$workspace_id&evento=eq.$evento&ativo=eq.true" );
    if ( ! $rw['ok'] || empty( $rw['data'] ) ) return;
    $body = wp_json_encode( array_merge( $payload, [ 'evento' => $evento, 'workspace_id' => $workspace_id, 'ts' => gmdate( 'c' ) ] ) );
    foreach ( $rw['data'] as $wh ) {
        if ( empty( $wh['url'] ) ) continue;
        $headers = [ 'Content-Type' => 'application/json' ];
        if ( ! empty( $wh['secret'] ) ) {
            $headers['X-Tao-Signature'] = 'hmac-sha256=' . hash_hmac( 'sha256', $body, $wh['secret'] );
        }
        wp_remote_post( $wh['url'], [
            'body'     => $body,
            'headers'  => $headers,
            'timeout'  => 3,
            'blocking' => false,
        ] );
    }
}

// ─── REST API: DISPATCH EVOLUTION + LEAD-TO-CARD ─────────────────────────────

add_action( 'rest_api_init', 'tao_crm_register_rest' );
function tao_crm_register_rest() {
    register_rest_route( 'tao-crm/v1', '/dispatch', [
        'methods'             => 'POST',
        'callback'            => 'tao_crm_rest_dispatch',
        'permission_callback' => '__return_true',
    ] );
    register_rest_route( 'tao-crm/v1', '/lead-to-card', [
        'methods'             => 'POST',
        'callback'            => 'tao_crm_rest_lead_to_card',
        'permission_callback' => '__return_true',
    ] );
    // Endpoint para cron externo (cron-job.org ou similar)
    // GET /wp-json/tao-crm/v1/cron?key=CHAVE
    register_rest_route( 'tao-crm/v1', '/cron', [
        'methods'             => 'GET',
        'callback'            => 'tao_crm_rest_cron',
        'permission_callback' => '__return_true',
    ] );
}

function tao_crm_rest_cron( WP_REST_Request $req ) {
    $key = sanitize_text_field( $req->get_param('key') ?? '' );
    $stored = get_option( 'tao_crm_cron_key', '' );
    if ( ! $stored ) {
        // Gera chave na primeira chamada
        $stored = wp_generate_password( 32, false );
        update_option( 'tao_crm_cron_key', $stored );
    }
    if ( ! hash_equals( $stored, $key ) ) {
        return new WP_REST_Response( [ 'error' => 'chave inválida' ], 403 );
    }
    $t = microtime(true);
    tao_crm_processar_fila_fn();
    tao_crm_processar_agendadas_fn();
    return new WP_REST_Response( [ 'ok' => true, 'ms' => round( (microtime(true) - $t) * 1000 ), 'ts' => gmdate('c') ], 200 );
}

function tao_crm_upsert_contato( string $workspace_id, string $whatsapp, string $nome = '' ): array {
    $rc = tao_crm_api( "/crm_contatos?workspace_id=eq.$workspace_id&whatsapp=eq.$whatsapp&select=id,nome&limit=1" );
    if ( $rc['ok'] && ! empty( $rc['data'] ) ) {
        $ct = $rc['data'][0];
        $ct_nome_atual = $ct['nome'] ?? '';
        $is_placeholder = ! $ct_nome_atual || $ct_nome_atual === $whatsapp || preg_match( '/^\+?\d{8,}$/', $ct_nome_atual );
        if ( $nome && $nome !== $whatsapp && $is_placeholder ) {
            tao_crm_api( "/crm_contatos?id=eq.{$ct['id']}", 'PATCH', [
                'nome'          => $nome,
                'atualizado_em' => gmdate( 'c' ),
            ] );
        }
        return [ 'id' => $ct['id'], 'is_retorno' => true ];
    }
    $rc2 = tao_crm_api( '/crm_contatos', 'POST', [
        'workspace_id'  => $workspace_id,
        'whatsapp'      => $whatsapp,
        'nome'          => $nome ?: $whatsapp,
        'criado_em'     => gmdate( 'c' ),
        'atualizado_em' => gmdate( 'c' ),
    ], [ 'Prefer' => 'return=representation' ] );
    $id = ( $rc2['ok'] && ! empty( $rc2['data'] ) ) ? $rc2['data'][0]['id'] : null;
    return [ 'id' => $id, 'is_retorno' => false ];
}

// ─── HELPERS: OPT-OUT, HORÁRIO DE ATENDIMENTO, LOG DE ERROS, SLA ─────────────

function tao_crm_num_opt_out( $num ) {
    return (bool) get_option( 'tao_crm_optout_' . preg_replace( '/\D/', '', $num ), false );
}
function tao_crm_set_opt_out( $num, $remove = false ) {
    $key = 'tao_crm_optout_' . preg_replace( '/\D/', '', $num );
    $remove ? delete_option( $key ) : update_option( $key, gmdate( 'c' ), false );
}

function tao_crm_get_horario_ws( $ws_id ) {
    $dias_padrao = [];
    for ( $d = 0; $d <= 6; $d++ ) {
        $dias_padrao[ (string) $d ] = [
            'ativo'     => ( $d >= 1 && $d <= 5 ),
            'abertura'  => '08:00',
            'fechamento'=> '18:00',
        ];
    }
    $def = [
        'ativo'    => false,
        'timezone' => 'America/Sao_Paulo',
        'mensagem' => 'Olá! Nosso horário de atendimento é de segunda a sexta das 08:00 às 18:00. Em breve retornaremos!',
        'dias'     => $dias_padrao,
    ];
    $stored = get_option( 'tao_crm_horario_ws_' . $ws_id, '' );
    if ( ! $stored ) return $def;
    $saved = (array) json_decode( $stored, true );

    // Backwards compat: formato antigo tinha abertura/fechamento na raiz + dias como array
    if ( isset( $saved['abertura'] ) && ! isset( $saved['dias']['0'] ) ) {
        $dias_antigos  = (array) ( $saved['dias'] ?? [ 1, 2, 3, 4, 5 ] );
        $dias_novo     = $dias_padrao;
        foreach ( range( 0, 6 ) as $d ) {
            $dias_novo[ (string) $d ]['ativo']     = in_array( $d, $dias_antigos );
            $dias_novo[ (string) $d ]['abertura']  = $saved['abertura']  ?? '08:00';
            $dias_novo[ (string) $d ]['fechamento']= $saved['fechamento'] ?? '18:00';
        }
        $saved['dias'] = $dias_novo;
        unset( $saved['abertura'], $saved['fechamento'] );
    }

    return array_merge( $def, $saved );
}

function tao_crm_esta_em_horario( $ws_id ) {
    $h = tao_crm_get_horario_ws( $ws_id );
    if ( empty( $h['ativo'] ) ) return true;
    try {
        $tz  = new DateTimeZone( $h['timezone'] ?: 'America/Sao_Paulo' );
        $now = new DateTime( 'now', $tz );
        $dow = (string) (int) $now->format( 'w' ); // '0'=Dom..'6'=Sáb
        $dia = $h['dias'][ $dow ] ?? null;
        if ( ! $dia || empty( $dia['ativo'] ) ) return false;
        $ab = DateTime::createFromFormat( 'H:i', $dia['abertura'],   $tz );
        $fe = DateTime::createFromFormat( 'H:i', $dia['fechamento'], $tz );
        return ( $now >= $ab && $now < $fe );
    } catch ( Exception $e ) {
        return true;
    }
}

function tao_crm_proximo_horario_comercial( $ws_id ) {
    $h = tao_crm_get_horario_ws( $ws_id );
    if ( empty( $h['ativo'] ) ) return time();
    try {
        $tz  = new DateTimeZone( $h['timezone'] ?: 'America/Sao_Paulo' );
        $now = new DateTime( 'now', $tz );
        for ( $i = 0; $i < 8; $i++ ) {
            $day = clone $now;
            if ( $i > 0 ) {
                $day->modify( "+{$i} days" );
                $day->setTime( 0, 0, 0 );
            }
            $dow = (string) (int) $day->format( 'w' );
            $dia = $h['dias'][ $dow ] ?? null;
            if ( ! $dia || empty( $dia['ativo'] ) ) continue;
            $y = (int) $day->format( 'Y' );
            $m = (int) $day->format( 'm' );
            $d = (int) $day->format( 'd' );
            $ab = DateTime::createFromFormat( 'H:i', $dia['abertura'], $tz );
            $ab->setDate( $y, $m, $d );
            $fe = DateTime::createFromFormat( 'H:i', $dia['fechamento'], $tz );
            $fe->setDate( $y, $m, $d );
            if ( $i === 0 && $now >= $ab && $now < $fe ) return time(); // já está aberto
            if ( $i === 0 && $now < $ab ) return $ab->getTimestamp();    // abre mais tarde hoje
            if ( $i > 0 ) return $ab->getTimestamp();                    // próximo dia útil
        }
        $fb = new DateTime( 'tomorrow', $tz );
        $fb->setTime( 8, 0, 0 );
        return $fb->getTimestamp();
    } catch ( Exception $e ) {
        return time();
    }
}

function tao_crm_log_error( $type, $msg, $context = [] ) {
    $log = get_option( 'tao_crm_error_log', [] );
    if ( ! is_array( $log ) ) $log = [];
    array_unshift( $log, [ 'ts' => gmdate( 'c' ), 'type' => $type, 'msg' => $msg, 'context' => $context ] );
    if ( count( $log ) > 100 ) array_splice( $log, 100 );
    update_option( 'tao_crm_error_log', $log, false );
}

function tao_crm_sla_minutos_estagio( $estagio_id ) {
    return max( 1, (int) get_option( 'tao_crm_sla_m_' . $estagio_id, 480 ) );
}

// ─── AJAX: LOG DE ERROS ───────────────────────────────────────────────────────
add_action( 'wp_ajax_tao_crm_get_error_log',   'tao_crm_ajax_get_error_log' );
add_action( 'wp_ajax_tao_crm_clear_error_log', 'tao_crm_ajax_clear_error_log' );
function tao_crm_ajax_get_error_log() {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Acesso negado' );
    wp_send_json_success( [ 'log' => get_option( 'tao_crm_error_log', [] ) ] );
}
function tao_crm_ajax_clear_error_log() {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Acesso negado' );
    delete_option( 'tao_crm_error_log' );
    wp_send_json_success();
}

// ─── AJAX: SALVAR HORÁRIO DE ATENDIMENTO ─────────────────────────────────────
add_action( 'wp_ajax_tao_crm_save_horario', 'tao_crm_ajax_save_horario' );
function tao_crm_ajax_save_horario() {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Acesso negado' );
    $ws_id = sanitize_text_field( $_POST['ws_id'] ?? '' );
    if ( ! $ws_id ) wp_send_json_error( 'Workspace inválido' );

    $dias_raw = $_POST['dias'] ?? [];
    $dias = [];
    for ( $d = 0; $d <= 6; $d++ ) {
        $key = (string) $d;
        $dia_raw = $dias_raw[ $key ] ?? [];
        $dias[ $key ] = [
            'ativo'     => ! empty( $dia_raw['ativo'] ),
            'abertura'  => preg_replace( '/[^0-9:]/', '', $dia_raw['abertura']  ?? '08:00' ),
            'fechamento'=> preg_replace( '/[^0-9:]/', '', $dia_raw['fechamento'] ?? '18:00' ),
        ];
    }

    $data = [
        'ativo'    => ! empty( $_POST['ativo'] ),
        'timezone' => sanitize_text_field( $_POST['timezone'] ?? 'America/Sao_Paulo' ),
        'mensagem' => sanitize_textarea_field( $_POST['mensagem'] ?? '' ),
        'dias'     => $dias,
    ];
    update_option( 'tao_crm_horario_ws_' . $ws_id, wp_json_encode( $data ), false );
    wp_send_json_success();
}

// ─── AJAX: SALVAR SLA POR ESTÁGIO ────────────────────────────────────────────
add_action( 'wp_ajax_tao_crm_save_sla_estagio', 'tao_crm_ajax_save_sla_estagio' );
function tao_crm_ajax_save_sla_estagio() {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Acesso negado' );
    $stage_id = sanitize_text_field( $_POST['stage_id'] ?? '' );
    $minutos  = max( 1, intval( $_POST['minutos'] ?? 480 ) );
    if ( ! $stage_id ) wp_send_json_error( 'Estágio inválido' );
    update_option( 'tao_crm_sla_m_' . $stage_id, $minutos, false );
    wp_send_json_success();
}

// ─── AJAX: DEVOLVER AO CHATBOT ────────────────────────────────────────────────
add_action( 'wp_ajax_tao_crm_devolver_chatbot', 'tao_crm_ajax_devolver_chatbot' );
function tao_crm_ajax_devolver_chatbot() {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    if ( ! function_exists( 'cbpm_can_access' ) || ! cbpm_can_access() ) wp_send_json_error( 'Acesso negado' );
    $card_id = sanitize_text_field( $_POST['card_id'] ?? '' );
    if ( ! $card_id ) wp_send_json_error( 'Card inválido' );
    $rc = tao_crm_api( "/crm_cards?id=eq.$card_id&select=contato_whatsapp,workspace_id&limit=1" );
    if ( ! $rc['ok'] || empty( $rc['data'] ) ) wp_send_json_error( 'Card não encontrado' );
    $card = $rc['data'][0];
    if ( ! tao_crm_is_gestor( $card['workspace_id'] ) ) wp_send_json_error( 'Apenas gestores' );
    $ws_id   = $card['workspace_id'];
    $contato = $card['contato_whatsapp'];
    // Clear ALL open atendimento_humano=true cards for this contact (not just the clicked one)
    tao_crm_api( "/crm_cards?workspace_id=eq.$ws_id&contato_whatsapp=eq.$contato&fechado=eq.false&atendimento_humano=eq.true", 'PATCH', [ 'atendimento_humano' => false ] );
    tao_crm_reset_chatbot_historico( $contato, $ws_id );
    wp_send_json_success();
}

// ─── AJAX: RECUPERAR ATENDIMENTO (inverte o Devolver) ────────────────────────
add_action( 'wp_ajax_tao_crm_recuperar_atendimento', 'tao_crm_ajax_recuperar_atendimento' );
function tao_crm_ajax_recuperar_atendimento() {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    if ( ! function_exists( 'cbpm_can_access' ) || ! cbpm_can_access() ) wp_send_json_error( 'Acesso negado' );
    $card_id = sanitize_text_field( $_POST['card_id'] ?? '' );
    if ( ! $card_id ) wp_send_json_error( 'Card inválido' );
    $rc = tao_crm_api( "/crm_cards?id=eq.$card_id&select=contato_whatsapp,workspace_id,estagio_id,pipeline_id,titulo,fechado&limit=1" );
    if ( ! $rc['ok'] || empty( $rc['data'] ) ) wp_send_json_error( 'Card não encontrado' );
    $card = $rc['data'][0];
    if ( ! empty( $card['fechado'] ) ) wp_send_json_error( 'Card já fechado' );
    $ws_id   = $card['workspace_id'];
    $contato = $card['contato_whatsapp'];
    $pl_id   = $card['pipeline_id'];
    $handoff_stage_id = null;
    $rhs = tao_crm_api( "/crm_estagios?pipeline_id=eq.$pl_id&tipo=eq.handoff&limit=1" );
    if ( $rhs['ok'] && ! empty( $rhs['data'] ) ) $handoff_stage_id = $rhs['data'][0]['id'];
    $patch = [ 'atendimento_humano' => true ];
    if ( $handoff_stage_id ) {
        $patch['estagio_id'] = $handoff_stage_id;
        $patch['movido_em']  = gmdate( 'c' );
    }
    tao_crm_api( "/crm_cards?id=eq.$card_id", 'PATCH', $patch );
    if ( function_exists( 'tao_crm_lock_chatbot' ) ) tao_crm_lock_chatbot( $contato, $ws_id );
    if ( $handoff_stage_id ) {
        tao_crm_disparar_automacoes( $card_id, $handoff_stage_id, 'entrar_fase' );
        tao_crm_disparar_automacoes( $card_id, $handoff_stage_id, 'tempo_na_fase' );
    }
    wp_send_json_success();
}

// ─── AJAX: CONVERSAS ATIVAS NO CHATBOT ───────────────────────────────────────
add_action( 'wp_ajax_tao_crm_conversas_ativas', 'tao_crm_ajax_conversas_ativas' );
function tao_crm_ajax_conversas_ativas() {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    if ( ! function_exists( 'cbpm_can_access' ) || ! cbpm_can_access() ) wp_send_json_error( 'Acesso negado' );

    $ws_id = sanitize_text_field( $_POST['ws_id'] ?? '' );
    if ( ! $ws_id ) wp_send_json_error( 'Workspace inválido' );

    $rw = tao_crm_api( "/crm_workspaces?id=eq.$ws_id&select=cliente_id&limit=1" );
    if ( ! $rw['ok'] || empty( $rw['data'] ) ) wp_send_json_error( 'Workspace não encontrado' );
    $cliente_id = $rw['data'][0]['cliente_id'] ?? '';
    if ( ! $cliente_id ) wp_send_json_error( 'cliente_id não configurado no workspace' );

    // Leads com chatbot ativo (status=novo)
    $rl    = tao_crm_api( "/leads?cliente_id=eq.$cliente_id&status=eq.novo&order=criado_em.desc&limit=100" );
    $leads = $rl['ok'] ? ( $rl['data'] ?? [] ) : [];

    if ( empty( $leads ) ) {
        wp_send_json_success( [ 'conversas' => [], 'total' => 0 ] );
    }

    $phones     = array_unique( array_column( $leads, 'phone' ) );
    $phones_enc = implode( ',', $phones );

    // Historico ativo para esses phones
    $rh       = tao_crm_api( "/historico?cliente_id=eq.$cliente_id&phone=in.($phones_enc)" );
    $hist_map = [];
    if ( $rh['ok'] ) {
        foreach ( ( $rh['data'] ?? [] ) as $h ) {
            $hist_map[ $h['phone'] ] = $h;
        }
    }

    // Contatos CRM para esses phones (strip non-digits para match)
    $crm_ct_map = [];
    $rc         = tao_crm_api( "/crm_contatos?workspace_id=eq.$ws_id&whatsapp=in.($phones_enc)&select=id,nome,whatsapp,classificacao,total_atendimentos&limit=100" );
    if ( $rc['ok'] ) {
        foreach ( ( $rc['data'] ?? [] ) as $ct ) {
            $k = preg_replace( '/\D/', '', $ct['whatsapp'] );
            $crm_ct_map[ $k ] = $ct;
        }
    }

    // Cards abertos para esses phones
    $cards_map = [];
    $rc2       = tao_crm_api( "/crm_cards?workspace_id=eq.$ws_id&contato_whatsapp=in.($phones_enc)&fechado=eq.false&select=id,titulo,estagio_id,atendimento_humano,contato_whatsapp&order=criado_em.desc&limit=100" );
    if ( $rc2['ok'] ) {
        foreach ( ( $rc2['data'] ?? [] ) as $c ) {
            $k = preg_replace( '/\D/', '', $c['contato_whatsapp'] );
            if ( ! isset( $cards_map[ $k ] ) ) $cards_map[ $k ] = $c;
        }
    }

    $conversas = [];
    $leads_by_phone = [];
    foreach ( $leads as $l ) { $leads_by_phone[ $l['phone'] ] = $l; }

    foreach ( $phones as $phone ) {
        $lead = $leads_by_phone[ $phone ] ?? null;
        if ( ! $lead ) continue;
        $hist = $hist_map[ $phone ] ?? null;
        if ( ! $hist ) continue; // sem histórico = conversa não iniciada ainda

        $k         = preg_replace( '/\D/', '', $phone );
        $messages  = is_array( $hist['messages'] )
            ? $hist['messages']
            : ( json_decode( $hist['messages'] ?? '[]', true ) ?: [] );
        $msg_count = count( $messages );

        $ultima_msg  = '';
        $ultima_role = 'user';
        if ( ! empty( $messages ) ) {
            $last        = end( $messages );
            $ultima_msg  = $last['content'] ?? ( $last['text'] ?? '' );
            $r           = $last['role']    ?? ( $last['type'] ?? 'user' );
            $ultima_role = in_array( $r, [ 'human', 'user' ] ) ? 'user' : 'assistant';
            $ultima_msg  = mb_substr( strip_tags( $ultima_msg ), 0, 120 );
        }

        $conversas[] = [
            'phone'       => $phone,
            'nome'        => $lead['nome'] ?? $phone,
            'lead_id'     => $lead['id'],
            'criado_em'   => $lead['criado_em'],
            'msg_count'   => $msg_count,
            'ultima_msg'  => $ultima_msg,
            'ultima_role' => $ultima_role,
            'crm_contato' => $crm_ct_map[ $k ] ?? null,
            'card_ativo'  => $cards_map[ $k ] ?? null,
        ];
    }

    // Sem card = mais urgente; dentro de cada grupo, mais recente primeiro
    usort( $conversas, function ( $a, $b ) {
        $a_c = ! empty( $a['card_ativo'] );
        $b_c = ! empty( $b['card_ativo'] );
        if ( $a_c !== $b_c ) return $a_c ? 1 : -1;
        return strcmp( $b['criado_em'], $a['criado_em'] );
    } );

    wp_send_json_success( [ 'conversas' => $conversas, 'total' => count( $conversas ) ] );
}

// ─── AJAX: INTERCEPTAR CONVERSA (bloqueia chatbot + cria/reativa card) ────────
add_action( 'wp_ajax_tao_crm_interceptar_conversa', 'tao_crm_ajax_interceptar_conversa' );
function tao_crm_ajax_interceptar_conversa() {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    if ( ! function_exists( 'cbpm_can_access' ) || ! cbpm_can_access() ) wp_send_json_error( 'Acesso negado' );

    $ws_id = sanitize_text_field( $_POST['ws_id']  ?? '' );
    $phone = sanitize_text_field( $_POST['phone']  ?? '' );
    $nome  = sanitize_text_field( $_POST['nome']   ?? '' );
    if ( ! $ws_id || ! $phone ) wp_send_json_error( 'Parâmetros inválidos' );

    $phone_clean = preg_replace( '/\D/', '', $phone );

    // Pipeline e estágio de handoff
    $rpl = tao_crm_api( "/crm_pipelines?workspace_id=eq.$ws_id&ativo=eq.true&order=ordem.asc&limit=1" );
    $pl_id = ( $rpl['ok'] && ! empty( $rpl['data'] ) ) ? $rpl['data'][0]['id'] : null;
    if ( ! $pl_id ) wp_send_json_error( 'Pipeline não configurado' );

    $handoff_stage_id = null;
    $rhs = tao_crm_api( "/crm_estagios?pipeline_id=eq.$pl_id&tipo=eq.handoff&limit=1" );
    if ( $rhs['ok'] && ! empty( $rhs['data'] ) ) $handoff_stage_id = $rhs['data'][0]['id'];
    if ( ! $handoff_stage_id ) wp_send_json_error( 'Estágio de handoff não configurado no pipeline' );

    // Contato CRM (enriquece nome e vincula contato_id)
    $contato_id = null;
    $rc = tao_crm_api( "/crm_contatos?workspace_id=eq.$ws_id&whatsapp=eq.$phone_clean&limit=1" );
    if ( $rc['ok'] && ! empty( $rc['data'] ) ) {
        $contato_id = $rc['data'][0]['id'];
        if ( ! $nome ) $nome = $rc['data'][0]['nome'] ?? '';
    }
    if ( ! $nome ) $nome = $phone_clean;

    // Verifica card aberto → reativa
    $rc_card = tao_crm_api( "/crm_cards?workspace_id=eq.$ws_id&contato_whatsapp=eq.$phone_clean&fechado=eq.false&order=criado_em.desc&limit=1" );
    if ( $rc_card['ok'] && ! empty( $rc_card['data'] ) ) {
        $existing = $rc_card['data'][0];
        tao_crm_api( "/crm_cards?id=eq.{$existing['id']}", 'PATCH', [
            'atendimento_humano' => true,
            'estagio_id'         => $handoff_stage_id,
            'movido_em'          => gmdate( 'c' ),
        ] );
        if ( function_exists( 'tao_crm_lock_chatbot' ) ) tao_crm_lock_chatbot( $phone_clean, $ws_id );
        tao_crm_disparar_automacoes( $existing['id'], $handoff_stage_id, 'entrar_fase' );
        tao_crm_disparar_automacoes( $existing['id'], $handoff_stage_id, 'tempo_na_fase' );
        $url = admin_url( 'admin.php?page=tao-crm-kanban&action=card&id=' . $existing['id'] );
        wp_send_json_success( [ 'card_id' => $existing['id'], 'url' => $url, 'criado' => false ] );
    }

    // Cria novo card
    $instancia_id = null;
    $ri = tao_crm_api( "/crm_instancias?workspace_id=eq.$ws_id&ativo=eq.true&limit=1" );
    if ( $ri['ok'] && ! empty( $ri['data'] ) ) $instancia_id = $ri['data'][0]['id'];

    $rc_new = tao_crm_api( '/crm_cards', 'POST', [
        'workspace_id'       => $ws_id,
        'pipeline_id'        => $pl_id,
        'estagio_id'         => $handoff_stage_id,
        'instancia_id'       => $instancia_id,
        'contato_id'         => $contato_id,
        'titulo'             => $nome,
        'contato_nome'       => $nome,
        'contato_whatsapp'   => $phone_clean,
        'atendimento_humano' => true,
        'criado_em'          => gmdate( 'c' ),
        'movido_em'          => gmdate( 'c' ),
    ], [ 'Prefer' => 'return=representation' ] );

    if ( ! $rc_new['ok'] || empty( $rc_new['data'] ) ) wp_send_json_error( 'Erro ao criar card: ' . ( $rc_new['error'] ?? '' ) );

    $card_id = $rc_new['data'][0]['id'];
    if ( $contato_id ) tao_crm_api( '/rpc/crm_contato_novo_atendimento', 'POST', [ 'p_id' => $contato_id ] );
    if ( function_exists( 'tao_crm_lock_chatbot' ) ) tao_crm_lock_chatbot( $phone_clean, $ws_id );
    tao_crm_disparar_automacoes( $card_id, $handoff_stage_id, 'entrar_fase' );
    tao_crm_disparar_automacoes( $card_id, $handoff_stage_id, 'tempo_na_fase' );

    $url = admin_url( 'admin.php?page=tao-crm-kanban&action=card&id=' . $card_id );
    wp_send_json_success( [ 'card_id' => $card_id, 'url' => $url, 'criado' => true ] );
}

// ─── AJAX: LGPD — EXCLUSÃO DE DADOS DO CONTATO ───────────────────────────────
add_action( 'wp_ajax_tao_crm_delete_contact_data', 'tao_crm_ajax_delete_contact_data' );
function tao_crm_ajax_delete_contact_data() {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Acesso negado' );
    $whatsapp = sanitize_text_field( $_POST['whatsapp'] ?? '' );
    $ws_id    = sanitize_text_field( $_POST['ws_id']    ?? '' );
    if ( ! $whatsapp || ! $ws_id ) wp_send_json_error( 'Parâmetros inválidos' );
    $rc = tao_crm_api( "/crm_cards?workspace_id=eq.$ws_id&contato_whatsapp=eq.$whatsapp&select=id" );
    $card_ids = array_column( $rc['ok'] ? ( $rc['data'] ?? [] ) : [], 'id' );
    foreach ( $card_ids as $cid ) {
        foreach ( [ 'crm_mensagens', 'crm_cards_tags', 'crm_lembretes', 'crm_cards_historico', 'crm_comentarios', 'crm_cards_valores', 'crm_msgs_agendadas' ] as $tbl ) {
            tao_crm_api( "/$tbl?card_id=eq.$cid", 'DELETE' );
        }
    }
    if ( ! empty( $card_ids ) ) {
        tao_crm_api( "/crm_cards?workspace_id=eq.$ws_id&contato_whatsapp=eq.$whatsapp", 'DELETE' );
    }
    tao_crm_api( "/crm_contatos?workspace_id=eq.$ws_id&whatsapp=eq.$whatsapp", 'DELETE' );
    tao_crm_set_opt_out( $whatsapp, true );
    tao_crm_log_error( 'lgpd', 'Dados excluídos para: ' . $whatsapp, [ 'ws_id' => $ws_id, 'cards' => count( $card_ids ) ] );
    wp_send_json_success( [ 'cards_excluidos' => count( $card_ids ) ] );
}

// ─── AJAX: SALVAR CSAT ────────────────────────────────────────────────────────
add_action( 'wp_ajax_tao_crm_save_csat', 'tao_crm_ajax_save_csat' );
function tao_crm_ajax_save_csat() {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Acesso negado' );
    $ws_id   = sanitize_text_field( $_POST['ws_id'] ?? '' );
    $ativo   = ! empty( $_POST['ativo'] );
    $msg     = sanitize_textarea_field( $_POST['mensagem'] ?? '' );
    if ( ! $ws_id ) wp_send_json_error( 'Workspace inválido' );
    update_option( 'tao_crm_csat_ativo_' . $ws_id, $ativo, false );
    if ( $msg ) update_option( 'tao_crm_csat_msg_' . $ws_id, $msg, false );
    wp_send_json_success();
}

// ─── AJAX: SALVAR CONFIG NPS ──────────────────────────────────────────────────
add_action( 'wp_ajax_tao_crm_save_nps', 'tao_crm_ajax_save_nps' );
function tao_crm_ajax_save_nps() {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Acesso negado' );
    $ws_id = sanitize_text_field( $_POST['ws_id'] ?? '' );
    if ( ! $ws_id ) wp_send_json_error( 'Workspace inválido' );
    update_option( 'tao_crm_nps_ativo_' . $ws_id, ! empty( $_POST['ativo'] ) ? 1 : 0, false );
    $msg = sanitize_textarea_field( $_POST['mensagem'] ?? '' );
    if ( $msg ) update_option( 'tao_crm_nps_msg_' . $ws_id, $msg, false );
    wp_send_json_success();
}

// ─── AJAX: SALVAR CONFIG RENOVAÇÃO ────────────────────────────────────────────
add_action( 'wp_ajax_tao_crm_save_renov', 'tao_crm_ajax_save_renov' );
function tao_crm_ajax_save_renov() {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Acesso negado' );
    $ws_id = sanitize_text_field( $_POST['ws_id'] ?? '' );
    if ( ! $ws_id ) wp_send_json_error( 'Workspace inválido' );
    update_option( 'tao_crm_renov_ativo_' . $ws_id, ! empty( $_POST['ativo'] ) ? 1 : 0, false );
    $msg = sanitize_textarea_field( $_POST['mensagem'] ?? '' );
    if ( $msg ) update_option( 'tao_crm_renov_msg_' . $ws_id, $msg, false );
    $snooze  = max( 1, min( 60, intval( $_POST['snooze']  ?? 5 ) ) );
    $semresp = max( 1, min( 90, intval( $_POST['semresp'] ?? 15 ) ) );
    update_option( 'tao_crm_renov_snooze_'  . $ws_id, $snooze,  false );
    update_option( 'tao_crm_renov_semresp_' . $ws_id, $semresp, false );
    wp_send_json_success();
}

function tao_crm_rest_dispatch( WP_REST_Request $req ) {
    $provided_key = $req->get_header( 'X-Tao-Key' ) ?: $req->get_param( 'key' );
    $global_key   = get_option( 'tao_crm_dispatch_key', 'tao-crm-dispatch-2026' );
    $auth_ok      = $provided_key && hash_equals( $global_key, (string) $provided_key );

    // Per-workspace key: resolve from instance name in first event body
    if ( ! $auth_ok && $provided_key ) {
        $body_preview = $req->get_json_params() ?: [];
        $evs_preview  = isset( $body_preview[0] ) ? $body_preview : [ $body_preview ];
        $inst_preview = $evs_preview[0]['instance'] ?? '';
        if ( $inst_preview ) {
            $ri_auth = tao_crm_api( "/crm_instancias?evolution_instancia=eq.$inst_preview&ativo=eq.true&select=workspace_id&limit=1" );
            if ( $ri_auth['ok'] && ! empty( $ri_auth['data'] ) ) {
                $ws_id_auth = $ri_auth['data'][0]['workspace_id'];
                $rw_auth    = tao_crm_api( "/crm_workspaces?id=eq.$ws_id_auth&select=dispatch_key&limit=1" );
                $ws_key     = ( $rw_auth['ok'] && ! empty( $rw_auth['data'] ) ) ? ( $rw_auth['data'][0]['dispatch_key'] ?? '' ) : '';
                $auth_ok    = $ws_key && hash_equals( $ws_key, (string) $provided_key );
            }
        }
    }

    if ( ! $auth_ok ) {
        return new WP_Error( 'unauthorized', 'Unauthorized', [ 'status' => 401 ] );
    }

    $raw  = $req->get_body();
    $body = $req->get_json_params() ?: [];

    $eventos = isset( $body[0] ) ? $body : [ $body ];
    $salvos  = 0;

    // Caches por request: evita múltiplas queries à API para o mesmo workspace/pipeline
    $inst_cache = []; // instancia_name → crm_instancias row
    $pl_cache   = []; // ws_id          → pipeline_id
    $hs_cache   = []; // pl_id          → handoff stage_id
    $fw_cache   = []; // ws_id          → já encaminhou ao N8N neste request
    $ct_cache   = []; // ws_id|num      → [ id, is_retorno ]
    $fs_cache   = []; // pl_id          → primeiro estágio do funil
    $fs2_cache  = []; // pl_id          → primeiro estágio ATIVO (≠ fase muda do opt-in)

    foreach ( $eventos as $ev ) {
        $evento = strtolower( $ev['event'] ?? $ev['type'] ?? '' );

        // ── Handle messages.update (delivery/read acknowledgement) ────────────
        if ( $evento === 'messages.update' ) {
            $instancia = $ev['instance'] ?? '';
            if ( ! isset( $inst_cache[ $instancia ] ) ) {
                $ri = tao_crm_api( "/crm_instancias?evolution_instancia=eq.$instancia&ativo=eq.true&select=id,workspace_id,evolution_url,evolution_key,evolution_instancia&limit=1" );
                $inst_cache[ $instancia ] = ( $ri['ok'] && ! empty( $ri['data'] ) ) ? $ri['data'][0] : null;
            }
            if ( $inst_cache[ $instancia ] ) {
                static $status_map = [
                    'pending'      => 'pending',
                    'server_ack'   => 'sent',
                    'delivery_ack' => 'delivered',
                    'read'         => 'read',
                ];
                $upd_items = isset( $ev['data'][0] ) ? $ev['data'] : [ $ev['data'] ];
                foreach ( $upd_items as $upd ) {
                    $wamid  = $upd['id'] ?? '';
                    $status = strtolower( $upd['status'] ?? '' );
                    if ( ! $wamid || ! isset( $status_map[ $status ] ) ) continue;
                    tao_crm_api( "/crm_mensagens?wamid=eq.$wamid", 'PATCH', [ 'status_entrega' => $status_map[ $status ] ] );
                }
            }
            continue;
        }

        if ( strpos( $evento, 'message' ) === false ) continue;

        // ── Resolve instância + workspace pelo nome da instância Evolution ───────
        $instancia = $ev['instance'] ?? '';
        if ( ! isset( $inst_cache[ $instancia ] ) ) {
            $ri = tao_crm_api( "/crm_instancias?evolution_instancia=eq.$instancia&ativo=eq.true&select=id,workspace_id,evolution_url,evolution_key,evolution_instancia&limit=1" );
            $inst_cache[ $instancia ] = ( $ri['ok'] && ! empty( $ri['data'] ) ) ? $ri['data'][0] : null;
        }
        $inst = $inst_cache[ $instancia ];
        // Instâncias sem workspace_id pertencem ao TAO Neo puro — N8N processa, CRM ignora
        if ( ! $inst || ! $inst['workspace_id'] ) continue;

        $INST_ID = $inst['id'];
        $WS_ID   = $inst['workspace_id'];
        $N8N_URL = get_option( 'tao_crm_n8n_url', '' );

        // ── Resolve pipeline padrão do workspace ────────────────────────────────
        if ( ! isset( $pl_cache[ $WS_ID ] ) ) {
            $rpl = tao_crm_api( "/crm_pipelines?workspace_id=eq.$WS_ID&ativo=eq.true&order=ordem.asc&limit=1" );
            $pl_cache[ $WS_ID ] = ( $rpl['ok'] && ! empty( $rpl['data'] ) ) ? $rpl['data'][0]['id'] : null;
        }
        $PL_ID = $pl_cache[ $WS_ID ];

        // ── Resolve estágio de handoff: tipo='handoff' ou fallback constante ────
        if ( $PL_ID && ! isset( $hs_cache[ $PL_ID ] ) ) {
            $rhs = tao_crm_api( "/crm_estagios?pipeline_id=eq.$PL_ID&tipo=eq.handoff&limit=1" );
            $hs_cache[ $PL_ID ] = ( $rhs['ok'] && ! empty( $rhs['data'] ) )
                ? $rhs['data'][0]['id']
                : null;
        }
        $HANDOFF_STAGE_ID = $PL_ID ? ( $hs_cache[ $PL_ID ] ?? null ) : null;

        $msgs = $ev['data']['messages'] ?? ( isset( $ev['data'] ) ? [ $ev['data'] ] : [] );

        foreach ( $msgs as $msg ) {
            $from_me = ! empty( $msg['key']['fromMe'] );
            $jid     = $msg['key']['remoteJid'] ?? '';
            $jid_alt = $msg['key']['remoteJidAlt'] ?? '';
            $is_lid  = strpos( $jid, '@lid' ) !== false;
            $num_lid = $is_lid ? str_replace( '@lid', '', $jid ) : '';
            // Evolution v2.3.7+: remoteJidAlt contém o @s.whatsapp.net real
            if ( $is_lid && $jid_alt && strpos( $jid_alt, '@s.whatsapp.net' ) !== false ) {
                $num = str_replace( '@s.whatsapp.net', '', $jid_alt );
                tao_crm_save_lid_mapping( $num_lid, $num );
            } else {
                $num = $is_lid ? $num_lid : str_replace( [ '@s.whatsapp.net', '@g.us' ], '', $jid );
                if ( $is_lid && $num_lid ) {
                    $resolved = tao_crm_lid_to_phone( $num_lid );
                    if ( $resolved ) $num = $resolved;
                }
            }
            $num_plain = $num;
            if ( ! $num || strpos( $jid, '@g.us' ) !== false ) continue;

            // ── Rota @lid (19/07): em sessão re-pareada o envio ao número puro é aceito
            // mas NÃO entrega (ack ERROR); só o @lid entrega (DELIVERY_ACK provado).
            // O webhook chega TRADUZIDO (remoteJid=número real, addressingMode='lid'),
            // então o @lid verdadeiro é recuperado do store da Evolution pelo key.id
            // e cacheado por instância+número (option tao_crm_lidroute_*).
            $lid_jid = $is_lid ? $jid : '';
            if ( ! $lid_jid && ( $msg['key']['addressingMode'] ?? '' ) === 'lid' ) {
                $route_opt = 'tao_crm_lidroute_' . ( $inst['evolution_instancia'] ?? '' ) . '_' . $num;
                $lid_jid   = get_option( $route_opt, '' );
                if ( ! $lid_jid && ! empty( $msg['key']['id'] ) ) {
                    $rfm = wp_remote_post( rtrim( $inst['evolution_url'] ?? '', '/' ) . '/chat/findMessages/' . rawurlencode( $inst['evolution_instancia'] ?? '' ), [
                        'headers' => [ 'Content-Type' => 'application/json', 'apikey' => $inst['evolution_key'] ?? '' ],
                        'body'    => wp_json_encode( [ 'where' => [ 'key' => [ 'id' => $msg['key']['id'] ] ], 'limit' => 1 ] ),
                        'timeout' => 8,
                    ] );
                    if ( ! is_wp_error( $rfm ) ) {
                        $bfm  = json_decode( wp_remote_retrieve_body( $rfm ), true );
                        $recs = $bfm['messages']['records'] ?? ( is_array( $bfm ) ? $bfm : [] );
                        $rj   = $recs[0]['key']['remoteJid'] ?? '';
                        if ( strpos( $rj, '@lid' ) !== false ) $lid_jid = $rj;
                    }
                }
            }
            if ( $lid_jid && $num ) {
                update_option( 'tao_crm_lidroute_' . ( $inst['evolution_instancia'] ?? '' ) . '_' . $num, $lid_jid, false );
            }

            $tipo = 'text'; $conteudo = ''; $midia = null;
            $m    = $msg['message'] ?? [];

            if ( isset( $m['conversation'] ) )            { $conteudo = $m['conversation']; }
            elseif ( isset( $m['extendedTextMessage'] ) ) { $conteudo = $m['extendedTextMessage']['text'] ?? ''; }
            elseif ( isset( $m['imageMessage'] ) )        { $tipo = 'image';    $conteudo = $m['imageMessage']['caption'] ?? '[imagem]'; }
            elseif ( isset( $m['audioMessage'] ) )        { $tipo = 'audio';    $conteudo = '[áudio]'; }
            elseif ( isset( $m['documentMessage'] ) )     { $tipo = 'document'; $conteudo = $m['documentMessage']['fileName'] ?? '[doc]'; }
            elseif ( isset( $m['videoMessage'] ) )        { $tipo = 'video';    $conteudo = $m['videoMessage']['caption'] ?? '[vídeo]'; }
            elseif ( isset( $m['stickerMessage'] ) )      { $tipo = 'sticker';  $conteudo = '[sticker]'; }
            else                                          { continue; }

            // ── 1a. Mídia incoming: baixa da Evolution imediatamente ───────────
            if ( $tipo !== 'text' && $tipo !== 'sticker' && ! $from_me ) {
                $midia = tao_crm_download_media( $inst, $msg['key'], $m );
            }

            // ── Fornecedor de cotação (TAO Cotações): conversa 100% fora do CRM ──
            // Sem card, sem Kanban, sem N8N — a mensagem vai pra thread do módulo.
            if ( function_exists( 'tao_cotacoes_num_fornecedor' ) ) {
                $_cot_forn = tao_cotacoes_num_fornecedor( $WS_ID, $num );
                if ( $_cot_forn ) {
                    $_cot_mime = $m['imageMessage']['mimetype'] ?? $m['documentMessage']['mimetype']
                              ?? $m['audioMessage']['mimetype'] ?? $m['videoMessage']['mimetype'] ?? null;
                    tao_cotacoes_fornecedor_msg( $WS_ID, $num, $INST_ID, $from_me, $tipo, $conteudo, $midia ?? null, $_cot_mime, $_cot_forn );
                    continue;
                }
            }

            // ── 1. Lookup/create contato ─────────────────────────────────────────
            $contato_id = null;
            $is_retorno = false;
            if ( ! $from_me ) {
                $ct_key = $WS_ID . '|' . $num;
                if ( ! isset( $ct_cache[ $ct_key ] ) ) {
                    $push_prov = trim( $msg['pushName'] ?? '' );
                    $nome_prov = ( $push_prov && $push_prov !== '.' ) ? $push_prov : $num;
                    $ct_cache[ $ct_key ] = tao_crm_upsert_contato( $WS_ID, $num, $nome_prov );
                }
                $contato_id = $ct_cache[ $ct_key ]['id'];
                $is_retorno = $ct_cache[ $ct_key ]['is_retorno'];
            }

            // ── Opt-out: ignora número que pediu exclusão da lista ────────────────
            if ( ! $from_me && tao_crm_num_opt_out( $num ) ) continue;


            // opt-out: verificado APÓS o check de atendimento humano (mais abaixo)

            // ── NPS: detecta resposta 0-10 (prioridade sobre CSAT) ──────────────────
            if ( ! $from_me && $tipo === 'text' && preg_match( '/^\s*(10|[0-9])\s*$/', trim( $conteudo ) ) ) {
                $nps_num  = preg_replace( '/\D/', '', $num );
                $nps_card = get_transient( 'tao_crm_nps_pend_' . $WS_ID . '_' . $nps_num );
                if ( ! $nps_card ) {
                    // Fallback robusto (não depende de transient): card aberto no estágio "NPS"
                    $nps_stage_id = get_option( 'tao_crm_nps_stage_' . $WS_ID, '' );
                    if ( $nps_stage_id ) {
                        $rc_nps = tao_crm_api( "/crm_cards?workspace_id=eq.$WS_ID&contato_whatsapp=eq.$num&fechado=eq.false&estagio_id=eq.$nps_stage_id&select=id&order=criado_em.desc&limit=1" );
                        if ( $rc_nps['ok'] && ! empty( $rc_nps['data'] ) ) $nps_card = $rc_nps['data'][0]['id'];
                    }
                }
                if ( $nps_card ) {
                    // dedupe: só registra se esse card ainda não respondeu
                    $rc_ex = tao_crm_api( "/crm_nps?card_id=eq.$nps_card&select=id&limit=1" );
                    if ( ! ( $rc_ex['ok'] && ! empty( $rc_ex['data'] ) ) ) {
                        delete_transient( 'tao_crm_nps_pend_' . $WS_ID . '_' . $nps_num );
                        $nota = (int) trim( $conteudo );
                        $cat  = $nota >= 9 ? 'promotor' : ( $nota >= 7 ? 'neutro' : 'detrator' );
                        tao_crm_api( '/crm_nps', 'POST', [
                            'workspace_id'     => $WS_ID,
                            'card_id'          => $nps_card,
                            'contato_whatsapp' => $nps_num,
                            'nota'             => $nota,
                            'categoria'        => $cat,
                            'respondido_em'    => gmdate( 'c' ),
                        ] );
                        if ( $cat === 'promotor' ) {
                            tao_crm_evolution_send( $inst, $num, 'Que alegria! 🎉 Muito obrigado pela nota ' . $nota . ' — sua recomendação significa muito pra nós! 💚' );
                        } elseif ( $cat === 'neutro' ) {
                            tao_crm_evolution_send( $inst, $num, 'Obrigado pela avaliação (' . $nota . ')! 🙏 Seguimos melhorando pra te atender cada vez melhor.' );
                        } else {
                            tao_crm_evolution_send( $inst, $num, 'Obrigado pelo retorno. 🙏 Sentimos muito que a experiência não tenha sido a melhor — vamos trabalhar para melhorar.' );
                        }
                        // Pós-NPS: move o card para "Renovação em Curso" e inicia o ciclo de renovação
                        $rs_nps = tao_crm_renov_stages( $WS_ID );
                        if ( ! empty( $rs_nps['renovacao'] ) ) {
                            tao_crm_api( "/crm_cards?id=eq.$nps_card", 'PATCH', [ 'estagio_id' => $rs_nps['renovacao'], 'movido_em' => gmdate( 'c' ) ] );
                            $nps_stage_cancel = get_option( 'tao_crm_nps_stage_' . $WS_ID, '' );
                            if ( $nps_stage_cancel && function_exists( 'tao_crm_cancelar_fila' ) ) tao_crm_cancelar_fila( $nps_card, $nps_stage_cancel );
                            tao_crm_renov_set( $nps_card, [ 'enviado_em' => null, 'proximo' => null ] );
                        }
                        tao_crm_log_error( 'nps', 'resposta ' . $nota . ' (' . $cat . ') -> Renovacao card=' . substr( (string) $nps_card, 0, 8 ), [ 'num' => $nps_num ] );
                        continue; // não processa como mensagem normal
                    }
                }
            }

            // ── Renovação em Curso: captura 1/2/3 do lembrete de renovação ──────────
            if ( ! $from_me && $tipo === 'text' ) {
                $rsd = tao_crm_renov_stages( $WS_ID );
                if ( ! empty( $rsd['renovacao'] ) ) {
                    $rrc = tao_crm_api( "/crm_cards?workspace_id=eq.$WS_ID&contato_whatsapp=eq.$num&estagio_id=eq.{$rsd['renovacao']}&fechado=eq.false&select=id,contato_id,contato_nome,contato_whatsapp,instancia_id,workspace_id&order=criado_em.desc&limit=1" );
                    if ( $rrc['ok'] && ! empty( $rrc['data'] ) ) {
                        $rcard = $rrc['data'][0];
                        $rst   = tao_crm_renov_get( $rcard['id'] );
                        if ( ! empty( $rst['enviado_em'] ) ) {   // só age se há lembrete aguardando resposta
                            $rtxt   = mb_strtolower( trim( $conteudo ) );
                            $is_neg = (bool) preg_match( '/^\s*2\b/', $rtxt ) || preg_match( '/\b(n[aã]o|agora n[aã]o)\b/u', $rtxt );
                            $is_zzz = (bool) preg_match( '/^\s*3\b/', $rtxt ) || strpos( $rtxt, 'lembr' ) !== false || strpos( $rtxt, '5 dia' ) !== false || strpos( $rtxt, 'depois' ) !== false;
                            $is_yes = (bool) preg_match( '/^\s*1\b/', $rtxt ) || preg_match( '/\b(sim|quero|renovar|renova|pode|aceito|claro|isso|com certeza|bora|vamos)\b/u', $rtxt );
                            if ( $is_zzz ) {
                                $r_snz = (int) get_option( 'tao_crm_renov_snooze_' . $WS_ID, 5 );
                                $prox  = tao_crm_add_dias_uteis( time(), $r_snz );
                                tao_crm_renov_set( $rcard['id'], [ 'enviado_em' => null, 'proximo' => gmdate( 'c', $prox ) ] );
                                tao_crm_evolution_send( $inst, $num, 'Combinado! Vou te lembrar em ' . $r_snz . ' dias úteis. 🌿' );
                            } elseif ( $is_neg ) {
                                if ( ! empty( $rsd['nao_renovado'] ) ) {
                                    tao_crm_api( "/crm_cards?id=eq.{$rcard['id']}", 'PATCH', [ 'estagio_id' => $rsd['nao_renovado'], 'movido_em' => gmdate( 'c' ) ] );
                                    tao_crm_api( '/crm_cards_historico', 'POST', [
                                        'card_id'         => $rcard['id'],
                                        'de_estagio_id'   => $rsd['renovacao'],
                                        'para_estagio_id' => $rsd['nao_renovado'],
                                        'usuario_id'      => 0,
                                        'motivo'          => 'Não há mais interesse no serviço',
                                        'obs'             => 'Renovação: cliente respondeu que não quer renovar',
                                    ] );
                                }
                                tao_crm_renov_del( $rcard['id'] );
                                tao_crm_unlock_chatbot( $num, $WS_ID );
                                tao_crm_evolution_send( $inst, $num, 'Tudo bem! Quando quiser renovar, é só nos chamar. 🌿' );
                            } elseif ( $is_yes ) {
                                tao_crm_renovar_card( $rcard, $rsd );          // SIM claro → renovação efetiva (origem → Renovado)
                            } else {
                                tao_crm_renov_abrir_tratamento( $rcard, $rsd ); // ambíguo → abre card p/ tratar; origem fica em Renovação
                            }
                            tao_crm_log_error( 'renovacao', 'resposta "' . substr( $rtxt, 0, 14 ) . '" card=' . substr( $rcard['id'], 0, 8 ), [ 'num' => $num ] );
                            continue;
                        }
                    }
                }
            }

            // ── CSAT: detecta resposta 1-5 de cliente pendente ──────────────────────
            if ( ! $from_me && $tipo === 'text' && preg_match( '/^\s*[1-5]\s*$/', $conteudo ) ) {
                $csat_num = preg_replace( '/\D/', '', $num );
                $csat_key = 'tao_crm_csat_pend_' . $WS_ID . '_' . $csat_num;
                if ( get_transient( $csat_key ) ) {
                    delete_transient( $csat_key );
                    $nota      = (int) trim( $conteudo );
                    $respostas = (array) get_option( "tao_crm_csat_respostas_$WS_ID", [] );
                    $respostas[] = [ 'nota' => $nota, 'num' => $num, 'em' => gmdate( 'c' ) ];
                    if ( count( $respostas ) > 500 ) $respostas = array_slice( $respostas, -500 );
                    update_option( "tao_crm_csat_respostas_$WS_ID", $respostas, false );
                    continue; // não processa como mensagem normal
                }
            }

            // ── 2. Busca card em atendimento humano ativo (bloqueia chatbot) ────────
            // Busca em TODAS as instâncias: lock expirado não deve reativar o bot se card ainda existe.
            $r = tao_crm_api( "/crm_cards?workspace_id=eq.$WS_ID&contato_whatsapp=eq.$num&fechado=eq.false&atendimento_humano=eq.true&select=id,estagio_id,fechado&order=criado_em.desc&limit=1" );
            $card_id              = null;
            $card_estagio_id      = null;
            // chatbot_primary: workspaces onde o N8N gerencia todo o fluxo (ex: Iluminar).
            // Nesse modo, atendimento_humano=true só bloqueia o chatbot quando o card está no estágio handoff.
            $_chatbot_primary     = (bool) get_option( 'tao_crm_chatbot_primary_' . $WS_ID, false );
            $_n8n_blocked_by_card = false;
            if ( $r['ok'] && ! empty( $r['data'] ) ) {
                $card_id         = $r['data'][0]['id'];
                $card_estagio_id = $r['data'][0]['estagio_id'];
                $is_handoff_card = $HANDOFF_STAGE_ID && ( $card_estagio_id === $HANDOFF_STAGE_ID );
                if ( $is_handoff_card || ! $_chatbot_primary ) {
                    // Padrão: atendimento humano bloqueia chatbot em qualquer estágio
                    $_n8n_blocked_by_card = true;
                    if ( ! $from_me ) tao_crm_lock_chatbot( $num_plain, $WS_ID );
                } else {
                    // chatbot_primary + card fora do handoff: N8N continua gerenciando
                    if ( ! $from_me ) tao_crm_unlock_chatbot( $num_plain, $WS_ID );
                }
            }
            tao_crm_log_error( 'dispatch', '[2] card_humano=' . ( $card_id ? substr($card_id,0,8) : 'none' ) . ' n8n_blocked=' . ( $_n8n_blocked_by_card ? 'sim' : 'nao' ), [ 'num' => $num_plain, 'from_me' => $from_me, 'msg' => mb_substr($conteudo,0,80) ] );

            // ── Detecta pedido de opt-out — só COMANDO explícito (evita falso positivo: "vou parar de tomar", "pode parar" etc. não optam o cliente fora) ─
            if ( ! $from_me && $tipo === 'text' && ! $_n8n_blocked_by_card ) {
                $ct_lc = trim( mb_strtolower( $conteudo ) );
                // a mensagem INTEIRA precisa ser o comando
                $optout_exato  = [ 'stop', 'parar', 'pare', 'sair', 'cancelar', 'descadastrar', 'remover', 'unsubscribe' ];
                // ou conter uma frase inequívoca
                $optout_frase  = [ 'cancelar mensagens', 'parar de receber', 'parar mensagens', 'remover da lista', 'não quero mais receber', 'nao quero mais receber', 'não quero mais mensagens', 'nao quero mais mensagens', 'descadastrar' ];
                $is_optout = in_array( $ct_lc, $optout_exato, true );
                if ( ! $is_optout ) {
                    foreach ( $optout_frase as $kw ) { if ( strpos( $ct_lc, $kw ) !== false ) { $is_optout = true; break; } }
                }
                if ( $is_optout ) {
                    tao_crm_set_opt_out( $num );
                    tao_crm_evolution_send( $inst, $num, 'Você foi removido da nossa lista. Para voltar ao atendimento, envie qualquer mensagem.' );
                    continue 2; // pula para próxima mensagem no foreach($msgs)
                }
            }

            // ── 2c. Busca card aberto de tracking (Pós Vendas, sem bloquear chatbot) ─
            // Busca em TODAS as instâncias para garantir que cards de pós-vendas sejam detectados
            // independentemente da instância em que foram criados.
            $tracking_card_id      = $card_id;
            $tracking_card_estagio = '';
            $pos_vendas_card     = null;
            if ( ! $card_id ) {
                $rt = tao_crm_api( "/crm_cards?workspace_id=eq.$WS_ID&contato_whatsapp=eq.$num&fechado=eq.false&atendimento_humano=eq.false&select=id,estagio_id,titulo,pipeline_id&order=criado_em.desc&limit=1" );
                if ( $rt['ok'] && ! empty( $rt['data'] ) ) {
                    $tracking_card_id      = $rt['data'][0]['id'];
                    $tracking_card_estagio = $rt['data'][0]['estagio_id'] ?? '';
                    // Só bloqueia N8N se o card está num pipeline secundário (pós-vendas).
                    // Cards no pipeline principal não devem impedir o chatbot de atuar.
                    if ( ( $rt['data'][0]['pipeline_id'] ?? '' ) !== $PL_ID ) {
                        $pos_vendas_card = $rt['data'][0];
                    }
                }
            }
            // [2c-pv] Fallback: card de pós-vendas fechado nos últimos 90 dias.
            // Reativa imediatamente e bloqueia encaminhamento ao N8N (evita msg de horário + novo card de vendas).
            if ( ! $from_me && ! $card_id && ! $pos_vendas_card ) {
                $_pv_pl_2c = get_option( 'tao_crm_pos_vendas_pipeline_' . $WS_ID, '' );
                if ( ! $_pv_pl_2c ) {
                    $_r_pls_2c   = tao_crm_api( "/crm_pipelines?workspace_id=eq.$WS_ID&ativo=eq.true&order=ordem.asc&limit=2" );
                    $_all_pls_2c = $_r_pls_2c['ok'] ? ( $_r_pls_2c['data'] ?? [] ) : [];
                    if ( count( $_all_pls_2c ) >= 2 ) $_pv_pl_2c = $_all_pls_2c[1]['id'];
                }
                if ( $_pv_pl_2c ) {
                    $_since_2c = gmdate( 'c', strtotime( '-90 days' ) );
                    $r_pvc_2c  = tao_crm_api( "/crm_cards?workspace_id=eq.$WS_ID&contato_whatsapp=eq.$num&pipeline_id=eq.$_pv_pl_2c&criado_em=gte.$_since_2c&select=id,estagio_id,titulo,pipeline_id,fechado&order=criado_em.desc&limit=1" );
                    if ( $r_pvc_2c['ok'] && ! empty( $r_pvc_2c['data'] ) ) {
                        $pvc_found        = $r_pvc_2c['data'][0];
                        $pos_vendas_card  = $pvc_found;
                        $tracking_card_id = $pvc_found['id'];
                        $card_id          = $pvc_found['id'];   // bloqueia step 2b (N8N forward)
                        $card_estagio_id  = $pvc_found['estagio_id'];
                        tao_crm_api( "/crm_cards?id=eq.{$card_id}", 'PATCH', [
                            'atendimento_humano' => true,
                            'fechado'            => false,
                            'movido_em'          => gmdate( 'c' ),
                        ] );
                        tao_crm_lock_chatbot( $num_plain, $WS_ID );
                        tao_crm_log_error( 'dispatch', '[2c-pv] pós-vendas reativado sem encaminhar N8N card=' . substr($card_id,0,8), [ 'num' => $num_plain ] );
                    }
                }
            }
            tao_crm_log_error( 'dispatch', '[2c] tracking_card=' . ( $tracking_card_id ? substr($tracking_card_id,0,8) : 'none' ) . ' pos_vendas=' . ( $pos_vendas_card ? substr($pos_vendas_card['id'],0,8) : 'none' ) );

            // ── 2d. Opt-in do bot (chave por workspace — ex.: Iluminar em número
            // compartilhado). Contato NOVO só ativa o bot se a mensagem contiver um
            // termo-gatilho (o wa.me do site/IG/FB manda o texto pronto); sem gatilho
            // o card nasce na fase "muda" (Pessoal/Profissional) e o bot fica MUDO.
            // Card na fase muda = mudo sempre — mover o card no Kanban liga/desliga.
            $_optin_frases = $_chatbot_primary ? trim( (string) get_option( 'tao_crm_optin_frases_' . $WS_ID, '' ) ) : '';
            $_mute_stage   = $_optin_frases !== '' ? (string) get_option( 'tao_crm_mute_stage_' . $WS_ID, '' ) : '';
            $_optin_match  = false;
            if ( $_optin_frases !== '' && $tipo === 'text' ) {
                $_lc_optin = mb_strtolower( $conteudo );
                foreach ( explode( '|', mb_strtolower( $_optin_frases ) ) as $_t_optin ) {
                    $_t_optin = trim( $_t_optin );
                    if ( $_t_optin !== '' && mb_strpos( $_lc_optin, $_t_optin ) !== false ) { $_optin_match = true; break; }
                }
            }
            $_bot_mudo = false;
            if ( $_optin_frases !== '' && ! $from_me ) {
                if ( $_mute_stage && $tracking_card_estagio === $_mute_stage )  $_bot_mudo = true;
                elseif ( ! $tracking_card_id && ! $_optin_match )               $_bot_mudo = true;
                if ( $_bot_mudo ) tao_crm_log_error( 'dispatch', '[2d] bot MUDO (opt-in)', [ 'num' => $num_plain, 'match' => $_optin_match ? 1 : 0, 'card' => $tracking_card_id ? substr( $tracking_card_id, 0, 8 ) : 'novo' ] );
            }

            // ── 2e. Conflito cross-instância: número ativo em outra instância ─────────
            // Se não encontrou card nesta instância mas há um aberto em outra, avisa e descarta.
            if ( ! $from_me && ! $tracking_card_id ) {
                $rc_conf = tao_crm_api( "/crm_cards?workspace_id=eq.$WS_ID&contato_whatsapp=eq.$num&fechado=eq.false&instancia_id=neq.$INST_ID&select=id&limit=1" );
                if ( $rc_conf['ok'] && ! empty( $rc_conf['data'] ) ) {
                    tao_crm_lock_chatbot( $num_plain, $WS_ID ); // garante que N8N não responda cross-instância
                    tao_crm_evolution_send( $inst, $num, '⚠️ Este número já está em atendimento em outro canal. Assim que o atendimento atual for concluído, responderemos por aqui.' );
                    tao_crm_log_error( 'dispatch', '[2e] conflito cross-instancia', [ 'num' => $num_plain, 'inst' => $instancia ] );
                    continue;
                }
            }

            // ── 2d. Verifica resposta de agendamento fora do horário ─────────────
            // (chatbot_primary: pedido genérico de humano não conta como handoff — a IA decide)
            $is_handoff_req = ! $_chatbot_primary && tao_crm_is_user_handoff_request( $conteudo );
            $agend_key      = 'tao_crm_agend_' . $WS_ID . '_' . $num_plain;
            if ( ! $from_me && ! $card_id && get_transient( $agend_key ) ) {
                $resp_lc     = mb_strtolower( trim( $conteudo ) );
                $afirmativos = [ 'sim', 'quero', 'pode', 'ok', 'tá', 'ta', 'bom', 'claro', 'vamos',
                                 'com certeza', 'quero sim', 'pode ser', 'fechado', 'combinado', 'perfeito', 'ótimo', 'otimo' ];
                $is_afirm = false;
                foreach ( $afirmativos as $w ) {
                    if ( strpos( $resp_lc, $w ) !== false ) { $is_afirm = true; break; }
                }
                delete_transient( $agend_key );
                if ( $is_afirm ) {
                    if ( $pos_vendas_card && $HANDOFF_STAGE_ID ) {
                        $card_id = $pos_vendas_card['id'];
                        tao_crm_api( "/crm_cards?id=eq.$card_id", 'PATCH', [
                            'estagio_id' => $HANDOFF_STAGE_ID,
                            'movido_em'  => gmdate( 'c' ),
                        ] );
                    } elseif ( $HANDOFF_STAGE_ID && $PL_ID ) {
                        $push_ag = trim( $msg['pushName'] ?? '' );
                        $nome_ag = ( $push_ag && $push_ag !== '.' ) ? $push_ag : $num_plain;
                        $rc_ag   = tao_crm_api( '/crm_cards', 'POST', [
                            'workspace_id'     => $WS_ID,
                            'pipeline_id'      => $PL_ID,
                            'estagio_id'       => $HANDOFF_STAGE_ID,
                            'instancia_id'     => $INST_ID,
                            'contato_id'       => $contato_id,
                            'titulo'           => $nome_ag,
                            'contato_nome'     => $nome_ag,
                            'contato_whatsapp' => $num,
                            'criado_em'        => gmdate( 'c' ),
                            'movido_em'        => gmdate( 'c' ),
                        ], [ 'Prefer' => 'return=representation' ] );
                        if ( $rc_ag['ok'] && ! empty( $rc_ag['data'] ) ) {
                            $card_id = $rc_ag['data'][0]['id'];
                            if ( $contato_id ) tao_crm_api( '/rpc/crm_contato_novo_atendimento', 'POST', [ 'p_id' => $contato_id ] );
                        }
                    }
                    $h_ag  = tao_crm_get_horario_ws( $WS_ID );
                    $tz_ag = new DateTimeZone( $h_ag['timezone'] ?: 'America/Sao_Paulo' );
                    $prox_ag = '';
                    for ( $d = 1; $d <= 7; $d++ ) {
                        $test_ag = ( new DateTime( 'now', $tz_ag ) )->modify( "+$d day" );
                        $dow_ag  = (string)(int)$test_ag->format( 'w' );
                        $dia_ag  = $h_ag['dias'][$dow_ag] ?? null;
                        if ( $dia_ag && ! empty( $dia_ag['ativo'] ) ) { $prox_ag = $dia_ag['abertura'] . ' do dia ' . $test_ag->format('d/m'); break; }
                    }
                    $confirm = "✅ *Perfeito!* Seu atendimento está agendado.\n\n"
                             . "Um de nossos atendentes irá te chamar assim que estivermos em horário de atendimento"
                             . ( $prox_ag ? " (a partir das *$prox_ag*)" : '' ) . ". 😊\n\n"
                             . "Enquanto isso, pode continuar me enviando suas dúvidas — estou aqui 24h!";
                    tao_crm_evolution_send( $inst, $num, $confirm );
                    // $card_id definido → steps 2b e 3 não executam; mensagem salva no card normalmente
                }
            }

            // ── 2b. Encaminha ao N8N uma vez por workspace, só sem card aberto ──────
            // Bloqueia N8N se cliente tem card no pós-vendas (evita msg de horário + criação de novo card de vendas)
            // Transient por contato evita duplo encaminhamento quando mensagens chegam em paralelo (ex: 2 imagens simultâneas)
            $_n8n_fwd_key = 'tao_crm_n8n_fwd_' . md5( $WS_ID . $num );
            if ( ! $from_me ) {
                tao_crm_log_error( 'dispatch', '[2b] fwd_check', [
                    'blocked' => $_n8n_blocked_by_card ? 1 : 0,
                    'posv'    => $pos_vendas_card ? 1 : 0,
                    'url_ok'  => $N8N_URL ? 1 : 0,
                    'fw_cache'=> empty( $fw_cache[ $WS_ID ] ) ? 0 : 1,
                    'trans'   => get_transient( $_n8n_fwd_key ) ? 1 : 0,
                    'handoff' => $is_handoff_req ? 1 : 0,
                    'horario' => tao_crm_esta_em_horario( $WS_ID ) ? 1 : 0,
                ] );
            }
            if ( ! $from_me && ! $_bot_mudo && ! $_n8n_blocked_by_card && ! $pos_vendas_card && $N8N_URL && empty( $fw_cache[ $WS_ID ] ) && ! get_transient( $_n8n_fwd_key ) && ! ( $is_handoff_req && ! tao_crm_esta_em_horario( $WS_ID ) ) ) {
                tao_crm_log_error( 'dispatch', '[2b] FORWARD para N8N', [ 'num' => $num ] );
                set_transient( $_n8n_fwd_key, 1, 30 );
                $fw_ev = $ev;
                if ( isset( $fw_ev['data'] ) ) {
                    $fw_ev['_crm_retorno']    = $is_retorno;
                    $fw_ev['_crm_contato_id'] = $contato_id;

                    // Conversa via @lid: reescreve o encaminhado p/ o N8N responder ao
                    // PRÓPRIO @lid (único destino que entrega em sessão re-pareada, ex.: Iluminar).
                    // NÃO aplicar onde o número puro já entrega: reescrever aqui fazia o N8N
                    // identificar o contato pelo @lid e criar contato/card FANTASMA no LID de
                    // 15 dígitos (incidente 20/07 — cards não nasciam no número real). Gate por
                    // workspace: a farmácia (7c4cae7f) NUNCA reescreve; lista ajustável sem deploy
                    // via option tao_crm_lid_skip_ws (CSV de workspace_id).
                    $_lid_skip_ws = array_filter( array_map( 'trim', explode( ',',
                        get_option( 'tao_crm_lid_skip_ws', '7c4cae7f-7591-4955-8d7a-c8c5e19cf62d' ) ) ) );
                    if ( $lid_jid && ! in_array( $WS_ID, $_lid_skip_ws, true ) && isset( $fw_ev['data']['key'] ) ) {
                        $fw_ev['data']['key']['remoteJid'] = $lid_jid;
                        unset( $fw_ev['data']['key']['remoteJidAlt'] );
                        $fw_ev['sender'] = '';
                    }

                    // Contexto de pedido em Pós Vendas (informa TAO para disambiguação)
                    if ( $pos_vendas_card ) {
                        $fw_ev['_crm_pos_vendas'] = [
                            'id'    => $pos_vendas_card['id'],
                            'titulo' => $pos_vendas_card['titulo'] ?? '',
                        ];
                    }

                    // Perfil completo do contato (sempre que tiver contato_id)
                    if ( $contato_id ) {
                        $rc_ct = tao_crm_api( "/crm_contatos?id=eq.$contato_id&select=id,nome,whatsapp,email,cpf,classificacao,observacoes,total_atendimentos,ultimo_atendimento_em&limit=1" );
                        if ( $rc_ct['ok'] && ! empty( $rc_ct['data'] ) ) {
                            $fw_ev['_crm_contato'] = $rc_ct['data'][0];
                        }
                        // Cards CRM recentes
                        $rc_cards = tao_crm_api( "/crm_cards?workspace_id=eq.$WS_ID&contato_whatsapp=eq.$num&order=criado_em.desc&select=id,titulo,estagio_id,fechado,criado_em&limit=5" );
                        if ( $rc_cards['ok'] && ! empty( $rc_cards['data'] ) ) {
                            $fw_ev['_crm_cards_recentes'] = $rc_cards['data'];
                        }
                    }

                    // Pedidos e leads recentes — só para contatos recorrentes
                    if ( $is_retorno ) {
                        $num_enc = rawurlencode( $num );
                        $rc_ped = tao_crm_api( "/pedidos?phone=ilike.*$num_enc*&order=criado_em.desc&select=id,nome_cliente,status,valor_total,itens,criado_em&limit=5" );
                        if ( $rc_ped['ok'] && ! empty( $rc_ped['data'] ) ) {
                            $fw_ev['_crm_pedidos_recentes'] = $rc_ped['data'];
                        }
                        $rc_leads = tao_crm_api( "/leads?phone=ilike.*$num_enc*&order=criado_em.desc&select=id,nome,status,interesse,criado_em&limit=5" );
                        if ( $rc_leads['ok'] && ! empty( $rc_leads['data'] ) ) {
                            $fw_ev['_crm_leads_recentes'] = $rc_leads['data'];
                        }
                    }
                }
                wp_remote_post( $N8N_URL, [
                    'body'     => wp_json_encode( $fw_ev ),
                    'headers'  => [ 'Content-Type' => 'application/json' ],
                    'timeout'  => 1,
                    'blocking' => false,
                ] );
                $fw_cache[ $WS_ID ] = true;

                // Backfill contato_id em leads/pedidos sem vínculo (silencioso)
                if ( $contato_id ) {
                    $num_enc = rawurlencode( $num );
                    tao_crm_api( "/leads?phone=ilike.*$num_enc*&contato_id=is.null",   'PATCH', [ 'contato_id' => $contato_id ] );
                    tao_crm_api( "/pedidos?phone=ilike.*$num_enc*&contato_id=is.null", 'PATCH', [ 'contato_id' => $contato_id ] );
                }
            }

            // ── 3. Handoff detectado na mensagem ENTRANTE do usuário ────────────
            // Evolution v2 não dispara webhook para mensagens enviadas via API,
            // então detectamos o handoff diretamente na solicitação do usuário.
            // Em workspaces chatbot_primary (ex.: Iluminar) o pedido GENÉRICO de humano
            // NÃO escala — quem decide o handoff é a IA (intenção HANDOFF nos casos
            // direcionados: crise, parcerias, imprensa, problemas com pedido).
            $is_handoff_phrase = ! $_chatbot_primary && tao_crm_is_user_handoff_request( $conteudo );
            tao_crm_log_error( 'dispatch', '[3] handoff_check: from_me=' . ($from_me?'1':'0') . ' card_id=' . ($card_id?substr($card_id,0,8):'none') . ' HANDOFF_STAGE=' . ($HANDOFF_STAGE_ID?substr($HANDOFF_STAGE_ID,0,8):'none') . ' PL_ID=' . ($PL_ID?substr($PL_ID,0,8):'none') . ' is_req=' . ($is_handoff_phrase?'SIM':'NÃO') );
            if ( ! $from_me && ! $card_id && $HANDOFF_STAGE_ID && $PL_ID && $is_handoff_phrase ) {
                // Verifica horário de atendimento antes de criar handoff
                if ( ! tao_crm_esta_em_horario( $WS_ID ) ) {
                    $h_cfg  = tao_crm_get_horario_ws( $WS_ID );
                    $tz_fh  = new DateTimeZone( $h_cfg['timezone'] ?: 'America/Sao_Paulo' );
                    $prox_fh = '';
                    for ( $d = 1; $d <= 7; $d++ ) {
                        $test_fh = ( new DateTime( 'now', $tz_fh ) )->modify( "+$d day" );
                        $dow_fh  = (string)(int)$test_fh->format( 'w' );
                        $dia_fh  = $h_cfg['dias'][$dow_fh] ?? null;
                        if ( $dia_fh && ! empty( $dia_fh['ativo'] ) ) { $prox_fh = $dia_fh['abertura'] . ' do dia ' . $test_fh->format('d/m'); break; }
                    }
                    $dias_nomes_fh = [ 'Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb' ];
                    $dias_texto_fh = [];
                    foreach ( $h_cfg['dias'] as $d_num => $d_cfg_fh ) {
                        if ( ! empty( $d_cfg_fh['ativo'] ) ) $dias_texto_fh[] = $dias_nomes_fh[ (int)$d_num ] . ' ' . $d_cfg_fh['abertura'] . '–' . $d_cfg_fh['fechamento'];
                    }
                    $msg_fora  = "Olá! 😊 Agradecemos o contato.\n\n";
                    $msg_fora .= "⏰ *Horário de atendimento humano:*\n" . implode( ', ', $dias_texto_fh ) . "\n\n";
                    $msg_fora .= "💬 Nosso atendimento virtual está disponível 24h e pode te ajudar com dúvidas e orientações agora mesmo!\n\n";
                    $msg_fora .= "👤 O atendimento humano retomará no próximo dia útil" . ( $prox_fh ? ", a partir das *$prox_fh*" : '' ) . ".\n\n";
                    $msg_fora .= "Gostaria de deixar seu atendimento *agendado* para quando abrirmos? Basta responder *SIM*. 📋";
                    tao_crm_evolution_send( $inst, $num, $msg_fora );
                    set_transient( $agend_key, 1, DAY_IN_SECONDS );
                    continue;
                }
                $push = trim( $msg['pushName'] ?? '' );
                // pushName '.' ou vazio em @lid → usa o número limpo como nome provisório
                $nome = ( $push && $push !== '.' ) ? $push : $num_plain;

                // ── Se já existe card aberto (ex: após Devolver ao Chatbot ou em Pós-Vendas), reativa ──
                if ( $pos_vendas_card ) {
                    $card_id = $pos_vendas_card['id'];

                    // Detecta se o card está no pipeline de pós-vendas
                    $_pv_pl = get_option( 'tao_crm_pos_vendas_pipeline_' . $WS_ID, '' );
                    if ( ! $_pv_pl ) {
                        $_rall = tao_crm_api( "/crm_pipelines?workspace_id=eq.$WS_ID&ativo=eq.true&order=ordem.asc&limit=2" );
                        $_all  = $_rall['ok'] ? ( $_rall['data'] ?? [] ) : [];
                        if ( count( $_all ) >= 2 ) $_pv_pl = $_all[1]['id'];
                    }
                    $_em_pv = $_pv_pl && ( ( $pos_vendas_card['pipeline_id'] ?? '' ) === $_pv_pl );

                    if ( $_em_pv ) {
                        // Cliente em pós-vendas: não move para o funil de vendas; ativa atendimento humano no mesmo estágio
                        $card_estagio_id = $pos_vendas_card['estagio_id'];
                        tao_crm_log_error( 'dispatch', '[3a] POS-VENDAS: ativando humano sem mover pipeline card=' . substr($card_id,0,8) );
                        tao_crm_api( "/crm_cards?id=eq.$card_id", 'PATCH', [
                            'atendimento_humano' => true,
                            'fechado'            => false,
                            'movido_em'          => gmdate( 'c' ),
                        ] );
                    } else {
                        // Card "devolvido ao chatbot" no funil de vendas: reativa e move para handoff
                        $card_estagio_id = $HANDOFF_STAGE_ID;
                        tao_crm_log_error( 'dispatch', '[3a] REATIVANDO card=' . substr($card_id,0,8) . ' pipeline_id=' . substr($PL_ID,0,8) . ' estagio=' . substr($HANDOFF_STAGE_ID,0,8) );
                        tao_crm_api( "/crm_cards?id=eq.$card_id", 'PATCH', [
                            'atendimento_humano' => true,
                            'pipeline_id'        => $PL_ID,
                            'estagio_id'         => $HANDOFF_STAGE_ID,
                            'movido_em'          => gmdate( 'c' ),
                        ] );
                    }
                    tao_crm_lock_chatbot( $num_plain, $WS_ID );
                    tao_crm_disparar_automacoes( $card_id, $card_estagio_id, 'entrar_fase', false, $WS_ID );
                    tao_crm_disparar_automacoes( $card_id, $card_estagio_id, 'tempo_na_fase', false, $WS_ID );
                    $gestor_ids = array_unique( array_merge(
                        (array) get_option( 'tao_crm_gestores_global', [] ),
                        (array) get_option( 'tao_crm_gestores_ws_' . $WS_ID, [] )
                    ) );
                    if ( empty( $gestor_ids ) ) $gestor_ids = [ 1 ];
                    $card_url_email = admin_url( 'admin.php?page=tao-crm-kanban&action=card&id=' . $card_id );
                    $email_subj = '[TAO CRM] Retomada de atendimento: ' . $nome;
                    $email_body = "Um cliente retomou o atendimento humano após ser devolvido ao chatbot.\n\nNome: $nome\nWhatsApp: $num\n\nAcesse o card:\n$card_url_email";
                    foreach ( $gestor_ids as $gid ) {
                        $gu = get_userdata( intval( $gid ) );
                        if ( $gu && is_email( $gu->user_email ) ) wp_mail( $gu->user_email, $email_subj, $email_body );
                    }
                } else {
                    // ── Cria novo card de handoff ────────────────────────────────────
                    tao_crm_log_error( 'dispatch', '[3b] CRIANDO novo card handoff nome=' . $nome . ' pipeline=' . substr($PL_ID,0,8) . ' estagio=' . substr($HANDOFF_STAGE_ID,0,8) );
                    $card_titulo = $nome;
                    $rc = tao_crm_api( '/crm_cards', 'POST', [
                        'workspace_id'       => $WS_ID,
                        'pipeline_id'        => $PL_ID,
                        'estagio_id'         => $HANDOFF_STAGE_ID,
                        'instancia_id'       => $INST_ID,
                        'contato_id'         => $contato_id,
                        'titulo'             => $card_titulo,
                        'contato_nome'       => $nome,
                        'contato_whatsapp'   => $num,
                        'atendimento_humano' => true,
                        'criado_em'          => gmdate( 'c' ),
                        'movido_em'          => gmdate( 'c' ),
                    ], [ 'Prefer' => 'return=representation' ] );
                    if ( $rc['ok'] && ! empty( $rc['data'] ) ) {
                        $card_id         = $rc['data'][0]['id'];
                        $card_estagio_id = $HANDOFF_STAGE_ID;
                        tao_crm_log_error( 'dispatch', '[3b] card criado OK id=' . substr($card_id,0,8) );
                        if ( $contato_id ) tao_crm_api( '/rpc/crm_contato_novo_atendimento', 'POST', [ 'p_id' => $contato_id ] );
                        tao_crm_lock_chatbot( $num_plain, $WS_ID );
                        tao_crm_disparar_automacoes( $card_id, $HANDOFF_STAGE_ID, 'entrar_fase', false, $WS_ID );
                        tao_crm_disparar_automacoes( $card_id, $HANDOFF_STAGE_ID, 'tempo_na_fase', false, $WS_ID );
                        $gestor_ids = array_unique( array_merge(
                            (array) get_option( 'tao_crm_gestores_global', [] ),
                            (array) get_option( 'tao_crm_gestores_ws_' . $WS_ID, [] )
                        ) );
                        if ( empty( $gestor_ids ) ) $gestor_ids = [ 1 ];
                        $card_url_email = admin_url( 'admin.php?page=tao-crm-kanban&action=card&id=' . $card_id );
                        $email_subj = '[TAO CRM] Novo atendimento: ' . $nome;
                        $email_body = "Um cliente solicitou atendimento humano.\n\nNome: $nome\nWhatsApp: $num\n\nAcesse o card:\n$card_url_email";
                        foreach ( $gestor_ids as $gid ) {
                            $gu = get_userdata( intval( $gid ) );
                            if ( $gu && is_email( $gu->user_email ) ) wp_mail( $gu->user_email, $email_subj, $email_body );
                        }
                    }
                }
            }

            // ── 4. Handoff: cria ou move card para o estágio de handoff ─────────
            if ( $from_me && $HANDOFF_STAGE_ID && tao_crm_is_handoff_msg( $conteudo ) ) {
                $nome = $from_me ? $num : ( $msg['pushName'] ?? $num );
                if ( $card_id ) {
                    $de = $card_estagio_id;
                    if ( $de !== $HANDOFF_STAGE_ID ) {
                        tao_crm_api( "/crm_cards?id=eq.$card_id", 'PATCH', [
                            'atendimento_humano' => true,
                            'estagio_id'         => $HANDOFF_STAGE_ID,
                            'movido_em'          => gmdate( 'c' ),
                        ] );
                        tao_crm_api( '/crm_cards_historico', 'POST', [
                            'card_id'         => $card_id,
                            'de_estagio_id'   => $de,
                            'para_estagio_id' => $HANDOFF_STAGE_ID,
                            'usuario_id'      => 0,
                        ] );
                        if ( $de ) tao_crm_disparar_automacoes( $card_id, $de, 'sair_fase', true );
                        if ( $de ) tao_crm_cancelar_fila( $card_id, $de );
                        tao_crm_disparar_automacoes( $card_id, $HANDOFF_STAGE_ID, 'entrar_fase', false, $WS_ID );
                        tao_crm_disparar_automacoes( $card_id, $HANDOFF_STAGE_ID, 'tempo_na_fase', false, $WS_ID );
                        tao_crm_lock_chatbot( $num_plain, $WS_ID );
                        $card_estagio_id = $HANDOFF_STAGE_ID;
                    }
                } else {
                    if ( ! $PL_ID ) continue;
                    // Bug 7: previne phantom cards por respostas automáticas do chatbot (ex: "obrigado" → N8N responde → phantom card)
                    // Só cria card via fromMe se a última interação deste contato foi há mais de 24h
                    $_since_24h = gmdate( 'c', time() - 86400 );
                    $_r_recent  = tao_crm_api( "/crm_cards?workspace_id=eq.$WS_ID&contato_whatsapp=eq.$num&ultima_mensagem_em=gte.$_since_24h&select=id&limit=1" );
                    if ( $_r_recent['ok'] && ! empty( $_r_recent['data'] ) ) continue; // interação recente (<24h) → ignora
                    // Para handoff iniciado pelo agente, lookup de contato pode ser necessário
                    if ( ! $contato_id ) {
                        $ct_key2 = $WS_ID . '|' . $num;
                        if ( ! isset( $ct_cache[ $ct_key2 ] ) ) {
                            $ct_cache[ $ct_key2 ] = tao_crm_upsert_contato( $WS_ID, $num, $nome );
                        }
                        $contato_id = $ct_cache[ $ct_key2 ]['id'];
                        $is_retorno = $ct_cache[ $ct_key2 ]['is_retorno'];
                    }
                    $rc = tao_crm_api( '/crm_cards', 'POST', [
                        'workspace_id'       => $WS_ID,
                        'pipeline_id'        => $PL_ID,
                        'estagio_id'         => $HANDOFF_STAGE_ID,
                        'instancia_id'       => $INST_ID,
                        'contato_id'         => $contato_id,
                        'titulo'             => $nome,
                        'contato_nome'       => $nome,
                        'contato_whatsapp'   => $num,
                        'atendimento_humano' => true,
                        'criado_em'          => gmdate( 'c' ),
                        'movido_em'          => gmdate( 'c' ),
                    ], [ 'Prefer' => 'return=representation' ] );
                    if ( $rc['ok'] && ! empty( $rc['data'] ) ) {
                        $card_id         = $rc['data'][0]['id'];
                        $card_estagio_id = $HANDOFF_STAGE_ID;
                        if ( $contato_id ) tao_crm_api( '/rpc/crm_contato_novo_atendimento', 'POST', [ 'p_id' => $contato_id ] );
                        tao_crm_lock_chatbot( $num, $WS_ID );
                        tao_crm_disparar_automacoes( $card_id, $HANDOFF_STAGE_ID, 'entrar_fase', false, $WS_ID );
                        tao_crm_disparar_automacoes( $card_id, $HANDOFF_STAGE_ID, 'tempo_na_fase', false, $WS_ID );
                    }
                }
            }

            // ── 2g. Chatbot-primary (ex.: Iluminar): todo novo contato ganha card ──
            // no 1º estágio do funil (ex.: "Curioso"), sem travar o chatbot —
            // o funil fica visível no Kanban desde a primeira mensagem.
            if ( ! $from_me && ! $card_id && ! $tracking_card_id && $_chatbot_primary && $PL_ID ) {
                if ( ! isset( $fs_cache[ $PL_ID ] ) ) {
                    $rfs = tao_crm_api( "/crm_estagios?pipeline_id=eq.$PL_ID&order=ordem.asc&limit=1" );
                    $fs_cache[ $PL_ID ] = ( $rfs['ok'] && ! empty( $rfs['data'] ) ) ? $rfs['data'][0]['id'] : null;
                }
                // Opt-in ligado: sem gatilho → card na fase muda (Pessoal/Profissional);
                // com gatilho → primeira fase ATIVA do funil (pula a fase muda).
                $_estagio_2g = $fs_cache[ $PL_ID ];
                if ( $_mute_stage ) {
                    if ( $_bot_mudo ) {
                        $_estagio_2g = $_mute_stage;
                    } elseif ( $_estagio_2g === $_mute_stage ) {
                        if ( ! isset( $fs2_cache[ $PL_ID ] ) ) {
                            $rfs2 = tao_crm_api( "/crm_estagios?pipeline_id=eq.$PL_ID&id=neq.$_mute_stage&order=ordem.asc&limit=1" );
                            $fs2_cache[ $PL_ID ] = ( $rfs2['ok'] && ! empty( $rfs2['data'] ) ) ? $rfs2['data'][0]['id'] : $_estagio_2g;
                        }
                        $_estagio_2g = $fs2_cache[ $PL_ID ];
                    }
                }
                if ( ! empty( $_estagio_2g ) ) {
                    $push_2g = trim( $msg['pushName'] ?? '' );
                    $nome_2g = ( $push_2g && $push_2g !== '.' ) ? $push_2g : $num_plain;
                    $rc2g = tao_crm_api( '/crm_cards', 'POST', [
                        'workspace_id'       => $WS_ID,
                        'pipeline_id'        => $PL_ID,
                        'estagio_id'         => $_estagio_2g,
                        'instancia_id'       => $INST_ID,
                        'contato_id'         => $contato_id,
                        'titulo'             => $nome_2g,
                        'contato_nome'       => $nome_2g,
                        'contato_whatsapp'   => $num,
                        'atendimento_humano' => false,
                        'criado_em'          => gmdate( 'c' ),
                        'movido_em'          => gmdate( 'c' ),
                    ], [ 'Prefer' => 'return=representation' ] );
                    if ( $rc2g['ok'] && ! empty( $rc2g['data'] ) ) {
                        $tracking_card_id = $rc2g['data'][0]['id'];
                        if ( $contato_id ) tao_crm_api( '/rpc/crm_contato_novo_atendimento', 'POST', [ 'p_id' => $contato_id ] );
                        tao_crm_log_error( 'dispatch', '[2g] card auto-criado (chatbot_primary) ' . substr( $tracking_card_id, 0, 8 ), [ 'num' => $num_plain ] );
                    }
                }
            }

            // ── 2h. Chatbot-primary: progressão automática do card por intenção ────
            // Move o card conforme o que a pessoa expressa (nunca regride; nome do
            // estágio destino é resolvido por fragmento — funciona em qualquer funil).
            if ( ! $from_me && $tipo === 'text' && $_chatbot_primary && $tracking_card_id && ! $card_id ) {
                $ct2h  = mb_strtolower( $conteudo );
                $alvo  = '';
                if ( preg_match( '/receber o livro|quero o livro|ser herdeiro|herdeiro da luz/u', $ct2h ) )                    $alvo = 'HERDEIRO';
                elseif ( preg_match( '/auto[\s\-]?irradia|para mim mesm|minha propria entrada|minha própria entrada/u', $ct2h ) ) $alvo = 'AUTO-IRRADIA';
                elseif ( preg_match( '/patrocinar|patrocinio|patrocínio|irradiador|quero doar/u', $ct2h ) )                     $alvo = 'IRRADIADOR';
                elseif ( preg_match( '/me cadastrei|fiz o cadastro|acabei de me cadastrar|cadastro feito/u', $ct2h ) )          $alvo = 'PORTAL';
                if ( $alvo ) {
                    if ( ! isset( $prog_cache[ $PL_ID ] ) ) {
                        $rpe = tao_crm_api( "/crm_estagios?pipeline_id=eq.$PL_ID&select=id,nome,ordem&order=ordem.asc" );
                        $prog_cache[ $PL_ID ] = $rpe['ok'] ? ( $rpe['data'] ?? [] ) : [];
                    }
                    $dest = null;
                    foreach ( $prog_cache[ $PL_ID ] as $e2h ) {
                        $n2h = mb_strtoupper( $e2h['nome'] );
                        $hit = ( $alvo === 'PORTAL' ) ? ( strpos( $n2h, 'PORTAL' ) !== false ) : ( strpos( $n2h, $alvo ) !== false );
                        if ( $hit ) { $dest = $e2h; break; }
                    }
                    if ( $dest ) {
                        $rcur = tao_crm_api( "/crm_cards?id=eq.$tracking_card_id&select=estagio_id&limit=1" );
                        $cur_id = ( $rcur['ok'] && ! empty( $rcur['data'] ) ) ? $rcur['data'][0]['estagio_id'] : '';
                        $cur_ord = -1;
                        foreach ( $prog_cache[ $PL_ID ] as $e2h ) { if ( $e2h['id'] === $cur_id ) { $cur_ord = (int) $e2h['ordem']; break; } }
                        if ( $cur_id && $cur_id !== $dest['id'] && (int) $dest['ordem'] > $cur_ord ) {
                            tao_crm_api( "/crm_cards?id=eq.$tracking_card_id", 'PATCH', [ 'estagio_id' => $dest['id'], 'movido_em' => gmdate( 'c' ) ] );
                            tao_crm_api( '/crm_cards_historico', 'POST', [
                                'card_id'         => $tracking_card_id,
                                'de_estagio_id'   => $cur_id,
                                'para_estagio_id' => $dest['id'],
                                'usuario_id'      => 0,
                                'motivo'          => 'Progressão automática (intenção: ' . $alvo . ')',
                            ] );
                            tao_crm_log_error( 'dispatch', '[2h] card ' . substr( $tracking_card_id, 0, 8 ) . ' → ' . $dest['nome'], [ 'num' => $num_plain ] );
                        }
                    }
                }
            }

            // ── 5. Sem card nem tracking → armazena msg pendente e pula ─────────
            // Mensagens recebidas antes do card existir ficam em transient (1h)
            // e são flushadas ao card assim que ele aparecer num dispatch seguinte.
            if ( ! $card_id && ! $tracking_card_id ) {
                if ( ! $from_me && $conteudo ) {
                    $_pk      = 'tao_crm_pending_' . md5( $WS_ID . $num_plain );
                    $_pending = get_transient( $_pk ) ?: [];
                    $_pending[] = [
                        'tipo'           => $tipo,
                        'conteudo'       => $conteudo,
                        'midia_url'      => $midia,
                        'remetente_nome' => $msg['pushName'] ?? $num,
                        'enviado_em'     => gmdate( 'c' ),
                        'wamid'          => $msg['key']['id'] ?? null,
                    ];
                    set_transient( $_pk, $_pending, HOUR_IN_SECONDS );
                }
                continue;
            }

            // ── 5b. Flush de mensagens pendentes (recebidas antes do card existir) ──
            $_flush_card = $card_id ?: $tracking_card_id;
            $_pk_flush   = 'tao_crm_pending_' . md5( $WS_ID . $num_plain );
            $_pending_flush = get_transient( $_pk_flush );
            if ( $_pending_flush ) {
                delete_transient( $_pk_flush );
                foreach ( $_pending_flush as $_pm ) {
                    tao_crm_api( '/crm_mensagens', 'POST', [
                        'card_id'        => $_flush_card,
                        'workspace_id'   => $WS_ID,
                        'direcao'        => 'in',
                        'tipo'           => $_pm['tipo'],
                        'conteudo'       => $_pm['conteudo'],
                        'midia_url'      => $_pm['midia_url'] ?? null,
                        'remetente_nome' => $_pm['remetente_nome'],
                        'enviado_em'     => $_pm['enviado_em'],
                        'wamid'          => $_pm['wamid'] ?? null,
                        'status_entrega' => null,
                    ], [ 'Prefer' => 'return=minimal' ] );
                }
            }

            // ── 6. Salva mensagem (dedup: atendente já marcou via transient) ─────
            $save_card_id = $card_id ?: $tracking_card_id;
            $skip_save    = false;
            if ( $from_me ) {
                $tk = 'tao_crm_fwd_' . md5( $num . sanitize_textarea_field( $conteudo ) );
                if ( get_transient( $tk ) ) { delete_transient( $tk ); $skip_save = true; }
            }
            if ( ! $skip_save ) {
                tao_crm_api( '/crm_mensagens', 'POST', [
                    'card_id'        => $save_card_id,
                    'workspace_id'   => $WS_ID,
                    'direcao'        => $from_me ? 'out' : 'in',
                    'tipo'           => $tipo,
                    'conteudo'       => $conteudo,
                    'midia_url'      => $midia,
                    'remetente_nome' => $from_me ? $instancia : ( $msg['pushName'] ?? $num ),
                    'enviado_em'     => gmdate( 'c' ),
                    'wamid'          => $msg['key']['id'] ?? null,
                    'status_entrega' => $from_me ? 'sent' : null,
                ], [ 'Prefer' => 'return=minimal' ] );
            }

            // ── 7. Atualiza timestamp do card e automações ───────────────────────
            if ( ! $from_me ) {
                $patch_card = [ 'ultima_mensagem_em' => gmdate( 'c' ) ];
                $push_name  = trim( $msg['pushName'] ?? '' );
                if ( strlen( $push_name ) > 1 && $push_name !== 'Você' ) {
                    $patch_card['contato_nome'] = $push_name;
                    $patch_card['titulo']       = $push_name;
                    // Sincroniza nome também no registro de contato
                    if ( $contato_id ) {
                        tao_crm_api( "/crm_contatos?id=eq.$contato_id", 'PATCH', [
                            'nome'          => $push_name,
                            'atualizado_em' => gmdate( 'c' ),
                        ] );
                    }
                }
                tao_crm_api( "/crm_cards?id=eq.$save_card_id", 'PATCH', $patch_card );
                // Automações de mensagem só para cards em atendimento humano ativo
                if ( $card_id && $card_estagio_id ) {
                    tao_crm_disparar_automacoes( $card_id, $card_estagio_id, 'recebeu_mensagem', false, $WS_ID );
                }
            }

            $salvos++;
        }
    }

    // Aproveita o webhook para processar automações pendentes (WP-cron não é confiável sem tráfego)
    tao_crm_processar_fila_fn();

    return rest_ensure_response( [ 'ok' => true, 'processados' => $salvos ] );
}

// ─── REST: LEAD → CARD (N8N cria card diretamente no CRM) ────────────────────

function tao_crm_rest_lead_to_card( WP_REST_Request $req ) {
    $provided_key = $req->get_header( 'X-Tao-Key' ) ?: $req->get_param( 'key' );
    $global_key   = get_option( 'tao_crm_dispatch_key', 'tao-crm-dispatch-2026' );
    $auth_ok      = $provided_key && hash_equals( $global_key, (string) $provided_key );

    // Per-workspace key: resolve from workspace_id or cliente_id in body
    if ( ! $auth_ok && $provided_key ) {
        $body_auth   = $req->get_json_params() ?: [];
        $ws_id_auth  = sanitize_text_field( $body_auth['workspace_id'] ?? '' );
        $ws_key_auth = '';
        if ( ! $ws_id_auth && ! empty( $body_auth['cliente_id'] ) ) {
            $cid_auth = sanitize_text_field( $body_auth['cliente_id'] );
            $rw_cid   = tao_crm_api( "/crm_workspaces?cliente_id=eq.$cid_auth&ativo=eq.true&select=id,dispatch_key&limit=1" );
            if ( $rw_cid['ok'] && ! empty( $rw_cid['data'] ) ) {
                $ws_key_auth = $rw_cid['data'][0]['dispatch_key'] ?? '';
            }
        } elseif ( $ws_id_auth ) {
            $rw_auth     = tao_crm_api( "/crm_workspaces?id=eq.$ws_id_auth&select=dispatch_key&limit=1" );
            $ws_key_auth = ( $rw_auth['ok'] && ! empty( $rw_auth['data'] ) ) ? ( $rw_auth['data'][0]['dispatch_key'] ?? '' ) : '';
        }
        $auth_ok = $ws_key_auth && hash_equals( $ws_key_auth, (string) $provided_key );
    }

    if ( ! $auth_ok ) {
        return new WP_Error( 'unauthorized', 'Unauthorized', [ 'status' => 401 ] );
    }

    $body         = $req->get_json_params() ?: [];
    $workspace_id = sanitize_text_field( $body['workspace_id'] ?? '' );
    $pipeline_id  = sanitize_text_field( $body['pipeline_id']  ?? '' );
    $estagio_id   = sanitize_text_field( $body['estagio_id']   ?? '' );
    $nome         = sanitize_text_field( $body['nome']         ?? '' );
    $whatsapp     = preg_replace( '/\D/', '', $body['whatsapp'] ?? '' );
    $titulo       = sanitize_text_field( $body['titulo']       ?? '' ) ?: $nome;

    // Aceita cliente_id como alternativa a workspace_id (N8N chatbot)
    if ( ! $workspace_id && ! empty( $body['cliente_id'] ) ) {
        $cliente_id = sanitize_text_field( $body['cliente_id'] );
        $rw = tao_crm_api( "/crm_workspaces?cliente_id=eq.$cliente_id&ativo=eq.true&limit=1" );
        $workspace_id = ( $rw['ok'] && ! empty( $rw['data'] ) ) ? $rw['data'][0]['id'] : '';
    }

    if ( ! $workspace_id || strlen( $whatsapp ) < 10 ) {
        return new WP_Error( 'invalid_data', 'workspace_id (ou cliente_id) e whatsapp são obrigatórios', [ 'status' => 400 ] );
    }

    // Auto-detect pipeline
    if ( ! $pipeline_id ) {
        $rpl = tao_crm_api( "/crm_pipelines?workspace_id=eq.$workspace_id&ativo=eq.true&order=ordem.asc&limit=1" );
        $pipeline_id = ( $rpl['ok'] && ! empty( $rpl['data'] ) ) ? $rpl['data'][0]['id'] : '';
    }
    if ( ! $pipeline_id ) return new WP_Error( 'no_pipeline', 'Nenhum pipeline encontrado', [ 'status' => 400 ] );

    // Auto-detect estágio (handoff → primeiro)
    if ( ! $estagio_id ) {
        $rhs = tao_crm_api( "/crm_estagios?pipeline_id=eq.$pipeline_id&tipo=eq.handoff&limit=1" );
        if ( $rhs['ok'] && ! empty( $rhs['data'] ) ) {
            $estagio_id = $rhs['data'][0]['id'];
        } else {
            $res = tao_crm_api( "/crm_estagios?pipeline_id=eq.$pipeline_id&order=ordem.asc&limit=1" );
            $estagio_id = ( $res['ok'] && ! empty( $res['data'] ) ) ? $res['data'][0]['id'] : '';
        }
    }
    if ( ! $estagio_id ) return new WP_Error( 'no_stage', 'Nenhum estágio encontrado', [ 'status' => 400 ] );

    // Evita duplicata: card aberto para este número
    // Quando novo_atendimento=true, primeiro verifica se já existe card com atendimento_humano=true.
    // Se só existe card com atendimento_humano=false (ex: após Devolver ao Chatbot),
    // reativa esse card em vez de criar um duplicado.
    // ── Lock anti-duplicata: serializa check+insert por (workspace+telefone) ──
    // Sem isto, duas chamadas quase simultâneas (webhook entregue 2x) fazem o SELECT
    // antes de qualquer INSERT e criam dois cards. O GET_LOCK serializa os processos
    // (a 2ª chamada espera, e então o SELECT já enxerga o card criado pela 1ª).
    global $wpdb;
    $_ltc_lock = 'tcltc_' . substr( md5( $workspace_id . '|' . $whatsapp ), 0, 40 );
    $wpdb->get_var( $wpdb->prepare( "SELECT GET_LOCK(%s, %d)", $_ltc_lock, 8 ) );
    try {

    $novo_atendimento = ! empty( $body['novo_atendimento'] );
    $dedup_filter     = "/crm_cards?workspace_id=eq.$workspace_id&contato_whatsapp=eq.$whatsapp&fechado=eq.false&order=criado_em.desc&limit=1";
    $rc_ex            = tao_crm_api( $dedup_filter );
    if ( $rc_ex['ok'] && ! empty( $rc_ex['data'] ) ) {
        $existing = $rc_ex['data'][0];
        if ( $existing['atendimento_humano'] ) {
            // Já existe card em atendimento humano ativo — retorna sem criar
            return rest_ensure_response( [ 'ok' => true, 'card_id' => $existing['id'], 'criado' => false ] );
        }
        if ( $novo_atendimento ) {
            // Reativa o card existente (ex: após Devolver ao Chatbot) em vez de criar duplicata
            tao_crm_api( "/crm_cards?id=eq.{$existing['id']}", 'PATCH', [ 'atendimento_humano' => true ] );
            if ( function_exists( 'tao_crm_lock_chatbot' ) ) tao_crm_lock_chatbot( $whatsapp, $workspace_id );
            return rest_ensure_response( [ 'ok' => true, 'card_id' => $existing['id'], 'criado' => false ] );
        }
        // Card aberto sem atendimento humano: retorna sem criar
        return rest_ensure_response( [ 'ok' => true, 'card_id' => $existing['id'], 'criado' => false ] );
    }

    // Sem card aberto: verifica se há card de pós-vendas nos últimos 90 dias (mesmo fechado).
    // Evita criar novo card de vendas para cliente que já está/esteve no pós-vendas.
    // Apenas quando criando no pipeline de vendas (pipeline_id != pós-vendas).
    $_pv_pl_ltc = get_option( 'tao_crm_pos_vendas_pipeline_' . $workspace_id, '' );
    if ( ! $_pv_pl_ltc ) {
        $_rall_ltc  = tao_crm_api( "/crm_pipelines?workspace_id=eq.$workspace_id&ativo=eq.true&order=ordem.asc&limit=2" );
        $_all_ltc   = $_rall_ltc['ok'] ? ( $_rall_ltc['data'] ?? [] ) : [];
        if ( count( $_all_ltc ) >= 2 ) $_pv_pl_ltc = $_all_ltc[1]['id'];
    }
    if ( $_pv_pl_ltc && $pipeline_id !== $_pv_pl_ltc ) {
        $_since_ltc = gmdate( 'c', strtotime( '-90 days' ) );
        $r_pv_ltc   = tao_crm_api( "/crm_cards?workspace_id=eq.$workspace_id&contato_whatsapp=eq.$whatsapp&pipeline_id=eq.$_pv_pl_ltc&criado_em=gte.$_since_ltc&select=id,fechado,atendimento_humano&order=criado_em.desc&limit=1" );
        if ( $r_pv_ltc['ok'] && ! empty( $r_pv_ltc['data'] ) ) {
            $pv_ex = $r_pv_ltc['data'][0];
            tao_crm_api( "/crm_cards?id=eq.{$pv_ex['id']}", 'PATCH', [
                'atendimento_humano' => true,
                'fechado'            => false,
                'movido_em'          => gmdate( 'c' ),
            ]);
            if ( function_exists( 'tao_crm_lock_chatbot' ) ) tao_crm_lock_chatbot( $whatsapp, $workspace_id );
            return rest_ensure_response( [ 'ok' => true, 'card_id' => $pv_ex['id'], 'criado' => false, 'reativado_pv' => true ] );
        }
    }

    $ct         = tao_crm_upsert_contato( $workspace_id, $whatsapp, $nome );
    $contato_id = $ct['id'];

    $inst_id = sanitize_text_field( $body['instancia_id'] ?? '' );
    if ( ! $inst_id ) {
        $ri = tao_crm_api( "/crm_instancias?workspace_id=eq.$workspace_id&ativo=eq.true&limit=1" );
        $inst_id = ( $ri['ok'] && ! empty( $ri['data'] ) ) ? $ri['data'][0]['id'] : null;
    }

    $r = tao_crm_api( '/crm_cards', 'POST', [
        'workspace_id'       => $workspace_id,
        'pipeline_id'        => $pipeline_id,
        'estagio_id'         => $estagio_id,
        'instancia_id'       => $inst_id,
        'contato_id'         => $contato_id,
        'titulo'             => $titulo,
        'contato_nome'       => $nome ?: $whatsapp,
        'contato_whatsapp'   => $whatsapp,
        'atendimento_humano' => true,
        'criado_em'          => gmdate( 'c' ),
        'movido_em'          => gmdate( 'c' ),
    ], [ 'Prefer' => 'return=representation' ] );

    if ( ! $r['ok'] ) {
        // Corrida com o dispatch (2g/handoff): o gatilho anti-duplicata do banco barra
        // este insert quando um card acabou de nascer por outro caminho (<120s).
        // Recupera o card existente e responde idempotente em vez de erro.
        $r_retry = tao_crm_api( $dedup_filter );
        if ( $r_retry['ok'] && ! empty( $r_retry['data'] ) ) {
            return rest_ensure_response( [ 'ok' => true, 'card_id' => $r_retry['data'][0]['id'], 'criado' => false, 'recuperado' => true ] );
        }
        return new WP_Error( 'create_failed', $r['error'] ?? 'Erro ao criar card', [ 'status' => 500 ] );
    }

    $new_card = $r['data'][0];
    if ( $contato_id ) tao_crm_api( '/rpc/crm_contato_novo_atendimento', 'POST', [ 'p_id' => $contato_id ] );
    if ( function_exists( 'tao_crm_lock_chatbot' ) ) tao_crm_lock_chatbot( $whatsapp, $workspace_id );
    tao_crm_disparar_automacoes( $new_card['id'], $estagio_id, 'entrar_fase' );
    tao_crm_disparar_automacoes( $new_card['id'], $estagio_id, 'tempo_na_fase' );
    tao_crm_fire_webhook( $workspace_id, 'card_criado', [ 'card' => $new_card ] );

    return rest_ensure_response( [ 'ok' => true, 'card_id' => $new_card['id'], 'criado' => true ] );

    } finally {
        $wpdb->query( $wpdb->prepare( "SELECT RELEASE_LOCK(%s)", $_ltc_lock ) );
    }
}

// ─── CRON: MONITORAR INSTÂNCIAS EVOLUTION ────────────────────────────────────

add_action( 'tao_crm_check_instances', 'tao_crm_check_instances_fn' );
function tao_crm_check_instances_fn() {
    $ri = tao_crm_api( '/crm_instancias?ativo=eq.true' );
    if ( ! $ri['ok'] || empty( $ri['data'] ) ) return;
    foreach ( $ri['data'] as $inst ) {
        $url = rtrim( $inst['evolution_url'] ?? '', '/' );
        $key = $inst['evolution_key'] ?? '';
        $nom = $inst['evolution_instancia'] ?? '';
        if ( ! $url || ! $key || ! $nom ) continue;
        $r = wp_remote_get( "$url/instance/connectionState/$nom", [
            'headers' => [ 'apikey' => $key ],
            'timeout' => 5,
        ] );
        if ( is_wp_error( $r ) ) {
            tao_crm_instancia_notificar( $inst, 'erro_conexao', $r->get_error_message() );
            continue;
        }
        $resp_body = json_decode( wp_remote_retrieve_body( $r ), true );
        $state     = $resp_body['state'] ?? ( $resp_body['instance']['state'] ?? 'unknown' );
        if ( $state !== 'open' ) {
            tao_crm_instancia_notificar( $inst, 'desconectada', $state );
            // Tenta reconexão automática para estados não-QR (qr exige scan manual)
            if ( $state !== 'qr' ) {
                $r_rst = wp_remote_request( "$url/instance/restart/$nom", [
                    'method'  => 'PUT',
                    'headers' => [ 'apikey' => $key ],
                    'timeout' => 10,
                ] );
                $rst_ok = ! is_wp_error( $r_rst ) && wp_remote_retrieve_response_code( $r_rst ) < 400;
                tao_crm_log_error( 'instance', 'Auto-restart ' . ( $rst_ok ? 'enviado' : 'falhou' ) . ' para ' . $nom, [ 'state' => $state ] );
            }
        } else {
            // Limpa alerta anterior se agora está conectado
            delete_option( 'tao_crm_inst_status_' . $inst['id'] );
        }
    }
}

function tao_crm_instancia_notificar( array $inst, string $tipo, string $detalhe ) {
    $prev = get_option( 'tao_crm_inst_status_' . $inst['id'] );
    update_option( 'tao_crm_inst_status_' . $inst['id'], [
        'tipo'       => $tipo,
        'detalhe'    => $detalhe,
        'checado_em' => gmdate( 'c' ),
    ], false );
    // Envia email só se não havia alerta anterior (evita spam a cada hora)
    if ( ! $prev ) {
        $admin_email = get_option( 'admin_email' );
        if ( $admin_email ) {
            wp_mail(
                $admin_email,
                '[TAO CRM] Instância Evolution desconectada: ' . ( $inst['evolution_instancia'] ?? '' ),
                "Instância: {$inst['evolution_instancia']}\nStatus: $detalhe\nWorkspace: {$inst['workspace_id']}\n\nVerifique o painel Evolution e reconecte o QR code."
            );
        }
    }
}

// ─── EXPORT CSV ───────────────────────────────────────────────────────────────

add_action( 'admin_init', 'tao_crm_export_csv_handler' );
function tao_crm_export_csv_handler() {
    if ( ( $_GET['tao_crm_export'] ?? '' ) !== 'csv' ) return;
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Acesso negado' );
    check_admin_referer( 'tao_crm_export_csv' );

    $ws_id = sanitize_text_field( $_GET['workspace_id'] ?? '' );
    $pl_id = sanitize_text_field( $_GET['pipeline_id']  ?? '' );
    $filter = '';
    if ( $ws_id ) $filter .= "&workspace_id=eq.$ws_id";
    if ( $pl_id ) $filter .= "&pipeline_id=eq.$pl_id";

    $r     = tao_crm_api( "/crm_cards?order=criado_em.desc&limit=5000$filter" );
    $cards = $r['ok'] ? ( $r['data'] ?? [] ) : [];

    // Mapas
    $estagios_map = [];
    $users_map    = [];
    foreach ( get_users( [ 'fields' => [ 'ID', 'display_name' ] ] ) as $u ) {
        $users_map[ $u->ID ] = $u->display_name;
    }
    $estagio_ids = array_filter( array_unique( array_column( $cards, 'estagio_id' ) ) );
    if ( $estagio_ids ) {
        $re = tao_crm_api( '/crm_estagios?id=in.(' . implode( ',', $estagio_ids ) . ')&select=id,nome' );
        if ( $re['ok'] ) foreach ( $re['data'] ?? [] as $e ) $estagios_map[ $e['id'] ] = $e['nome'];
    }

    while ( ob_get_level() > 0 ) ob_end_clean();   // descarta byte espúrio (?/0x3F) antes do BOM — senão o Excel ignora o BOM e quebra os acentos
    header( 'Content-Type: text/csv; charset=UTF-8' );
    header( 'Content-Disposition: attachment; filename="tao-crm-' . date( 'Y-m-d' ) . '.csv"' );
    header( 'Cache-Control: no-cache, no-store' );
    header( 'Pragma: no-cache' );

    $out = fopen( 'php://output', 'w' );
    fprintf( $out, chr(0xEF) . chr(0xBB) . chr(0xBF) ); // UTF-8 BOM para Excel
    fputcsv( $out, [ 'ID', 'Título', 'Contato', 'WhatsApp', 'Estágio', 'Responsável', 'Criado em', 'Movido em', 'Fechado' ], ';' );
    foreach ( $cards as $c ) {
        fputcsv( $out, [
            $c['id'],
            $c['titulo'] ?? $c['contato_nome'],
            $c['contato_nome'],
            $c['contato_whatsapp'],
            $estagios_map[ $c['estagio_id'] ] ?? $c['estagio_id'],
            $users_map[ intval( $c['responsavel_id'] ?? 0 ) ] ?? '',
            tao_crm_brt( $c['criado_em'], 'd/m/Y H:i' ),
            tao_crm_brt( $c['movido_em'], 'd/m/Y H:i' ),
            $c['fechado'] ? 'Sim' : 'Não',
        ], ';' );
    }
    fclose( $out );
    exit;
}

// ─── AJAX: TEMPLATES DE MENSAGEM ─────────────────────────────────────────────

add_action( 'wp_ajax_tao_crm_get_templates', 'tao_crm_ajax_get_templates' );
function tao_crm_ajax_get_templates() {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    if ( ! function_exists( 'cbpm_can_access' ) || ! cbpm_can_access() ) wp_send_json_error( 'no access' );
    $ws_id = sanitize_text_field( $_POST['workspace_id'] ?? '' );
    if ( ! $ws_id ) wp_send_json_error( 'workspace_id obrigatório' );
    $r = tao_crm_api( "/crm_msg_templates?workspace_id=eq.$ws_id&order=nome.asc" );
    wp_send_json_success( $r['ok'] ? ( $r['data'] ?? [] ) : [] );
}

add_action( 'wp_ajax_tao_crm_save_template', 'tao_crm_ajax_save_template' );
function tao_crm_ajax_save_template() {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Acesso negado' );
    $id           = sanitize_text_field( $_POST['id']           ?? '' );
    $workspace_id = sanitize_text_field( $_POST['workspace_id'] ?? '' );
    $nome         = sanitize_text_field( $_POST['nome']         ?? '' );
    $conteudo     = sanitize_textarea_field( $_POST['conteudo'] ?? '' );
    if ( ! $workspace_id || ! $nome || ! $conteudo ) wp_send_json_error( 'Campos obrigatórios faltando' );
    $data = compact( 'workspace_id', 'nome', 'conteudo' );
    if ( $id ) {
        $r = tao_crm_api( "/crm_msg_templates?id=eq.$id", 'PATCH', $data );
    } else {
        $r = tao_crm_api( '/crm_msg_templates', 'POST', $data, [ 'Prefer' => 'return=representation' ] );
    }
    if ( ! $r['ok'] ) wp_send_json_error( $r['error'] );
    wp_send_json_success( $id ? [] : ( $r['data'][0] ?? [] ) );
}

add_action( 'wp_ajax_tao_crm_delete_template', 'tao_crm_ajax_delete_template' );
function tao_crm_ajax_delete_template() {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Acesso negado' );
    $id = sanitize_text_field( $_POST['id'] ?? '' );
    if ( ! $id ) wp_send_json_error( 'id obrigatório' );
    $r = tao_crm_api( "/crm_msg_templates?id=eq.$id", 'DELETE' );
    if ( ! $r['ok'] ) wp_send_json_error( $r['error'] );
    wp_send_json_success();
}

// ─── AJAX: WEBHOOKS DE SAÍDA ──────────────────────────────────────────────────

add_action( 'wp_ajax_tao_crm_get_webhooks_saida', 'tao_crm_ajax_get_webhooks_saida' );
function tao_crm_ajax_get_webhooks_saida() {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Acesso negado' );
    $ws_id = sanitize_text_field( $_POST['workspace_id'] ?? '' );
    if ( ! $ws_id ) wp_send_json_error( 'workspace_id obrigatório' );
    $r = tao_crm_api( "/crm_webhooks_saida?workspace_id=eq.$ws_id&order=nome.asc" );
    wp_send_json_success( $r['ok'] ? ( $r['data'] ?? [] ) : [] );
}

add_action( 'wp_ajax_tao_crm_save_webhook_saida', 'tao_crm_ajax_save_webhook_saida' );
function tao_crm_ajax_save_webhook_saida() {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Acesso negado' );
    $id           = sanitize_text_field( $_POST['id']           ?? '' );
    $workspace_id = sanitize_text_field( $_POST['workspace_id'] ?? '' );
    $nome         = sanitize_text_field( $_POST['nome']         ?? '' );
    $evento       = sanitize_key( $_POST['evento']              ?? '' );
    $url          = esc_url_raw( $_POST['url']                  ?? '' );
    $ativo        = ! empty( $_POST['ativo'] );
    $secret       = sanitize_text_field( $_POST['secret'] ?? '' );
    $eventos_ok   = [ 'card_criado', 'card_movido', 'card_fechado_ganho', 'card_fechado_perdido' ];
    if ( ! $workspace_id || ! $evento || ! $url || ! in_array( $evento, $eventos_ok ) ) {
        wp_send_json_error( 'Campos obrigatórios faltando ou evento inválido' );
    }
    $data = compact( 'workspace_id', 'nome', 'evento', 'url', 'ativo' );
    // Gera secret automaticamente ao criar; ao editar mantém o existente se não enviado novo
    if ( ! $id ) {
        $data['secret'] = $secret ?: wp_generate_uuid4();
    } elseif ( $secret ) {
        $data['secret'] = $secret;
    }
    if ( $id ) {
        $r = tao_crm_api( "/crm_webhooks_saida?id=eq.$id", 'PATCH', $data );
    } else {
        $r = tao_crm_api( '/crm_webhooks_saida', 'POST', $data, [ 'Prefer' => 'return=representation' ] );
    }
    if ( ! $r['ok'] ) wp_send_json_error( $r['error'] );
    wp_send_json_success( $id ? [] : ( $r['data'][0] ?? [] ) );
}

add_action( 'wp_ajax_tao_crm_delete_webhook_saida', 'tao_crm_ajax_delete_webhook_saida' );
function tao_crm_ajax_delete_webhook_saida() {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Acesso negado' );
    $id = sanitize_text_field( $_POST['id'] ?? '' );
    if ( ! $id ) wp_send_json_error( 'id obrigatório' );
    $r = tao_crm_api( "/crm_webhooks_saida?id=eq.$id", 'DELETE' );
    if ( ! $r['ok'] ) wp_send_json_error( $r['error'] );
    wp_send_json_success();
}

// ─── AJAX: ROUND-ROBIN ───────────────────────────────────────────────────────

add_action( 'wp_ajax_tao_crm_get_round_robin', 'tao_crm_ajax_get_round_robin' );
function tao_crm_ajax_get_round_robin() {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Acesso negado' );
    $ws_id = sanitize_text_field( $_POST['workspace_id'] ?? '' );
    if ( ! $ws_id ) wp_send_json_error( 'workspace_id obrigatório' );
    $r = tao_crm_api( "/crm_round_robin?workspace_id=eq.$ws_id&limit=1" );
    wp_send_json_success( ( $r['ok'] && ! empty( $r['data'] ) ) ? $r['data'][0] : null );
}

add_action( 'wp_ajax_tao_crm_save_round_robin', 'tao_crm_ajax_save_round_robin' );
function tao_crm_ajax_save_round_robin() {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Acesso negado' );
    $id           = sanitize_text_field( $_POST['id']           ?? '' );
    $workspace_id = sanitize_text_field( $_POST['workspace_id'] ?? '' );
    $user_ids     = array_map( 'intval', (array) ( $_POST['user_ids'] ?? [] ) );
    if ( ! $workspace_id ) wp_send_json_error( 'workspace_id obrigatório' );
    $data = [ 'workspace_id' => $workspace_id, 'user_ids' => $user_ids, 'next_idx' => 0 ];
    if ( $id ) {
        unset( $data['next_idx'] ); // não reseta o ponteiro ao editar a lista
        $r = tao_crm_api( "/crm_round_robin?id=eq.$id", 'PATCH', [ 'workspace_id' => $workspace_id, 'user_ids' => $user_ids ] );
    } else {
        $r = tao_crm_api( '/crm_round_robin', 'POST', $data, [ 'Prefer' => 'return=representation' ] );
    }
    if ( ! $r['ok'] ) wp_send_json_error( $r['error'] );
    wp_send_json_success();
}


// ── v1.3.0: Tags, Lembretes, Histórico, Valor, Billing ───────────────────────

// ── Tags ──────────────────────────────────────────────────────────────────────

add_action( 'wp_ajax_tao_crm_get_tags', function () {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    $ws_id = sanitize_text_field( $_POST['workspace_id'] ?? '' );
    $r     = tao_crm_api( "/crm_tags?workspace_id=eq.$ws_id&order=nome.asc" );
    $r['ok'] ? wp_send_json_success( $r['data'] ) : wp_send_json_error( $r['error'] );
} );

add_action( 'wp_ajax_tao_crm_save_tag', function () {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    $ws_id = sanitize_text_field( $_POST['workspace_id'] ?? '' );
    if ( ! tao_crm_is_gestor( $ws_id ) ) wp_send_json_error( 'Acesso negado' );
    $id   = sanitize_text_field( $_POST['id'] ?? '' );
    $data = [
        'workspace_id' => $ws_id,
        'nome'         => sanitize_text_field( $_POST['nome'] ?? '' ),
        'cor'          => sanitize_hex_color( $_POST['cor'] ?? '#6366f1' ) ?: '#6366f1',
    ];
    if ( $id ) {
        $r = tao_crm_api( "/crm_tags?id=eq.$id", 'PATCH', $data, [ 'Prefer' => 'return=representation' ] );
    } else {
        $r = tao_crm_api( '/crm_tags', 'POST', $data, [ 'Prefer' => 'return=representation' ] );
    }
    $r['ok'] ? wp_send_json_success( $r['data'][0] ?? [] ) : wp_send_json_error( $r['error'] );
} );

add_action( 'wp_ajax_tao_crm_delete_tag', function () {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    $ws_id = sanitize_text_field( $_POST['workspace_id'] ?? '' );
    if ( ! tao_crm_is_gestor( $ws_id ) ) wp_send_json_error( 'Acesso negado' );
    $id = sanitize_text_field( $_POST['id'] ?? '' );
    $r  = tao_crm_api( "/crm_tags?id=eq.$id", 'DELETE' );
    $r['ok'] ? wp_send_json_success() : wp_send_json_error( $r['error'] );
} );

add_action( 'wp_ajax_tao_crm_get_card_tags', function () {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    $card_id = sanitize_text_field( $_POST['card_id'] ?? '' );
    $r       = tao_crm_api( "/crm_cards_tags?card_id=eq.$card_id&select=tag_id,crm_tags(id,nome,cor)" );
    if ( ! $r['ok'] ) { wp_send_json_error( $r['error'] ); return; }
    $tags = array_filter( array_map( fn( $row ) => $row['crm_tags'] ?? null, $r['data'] ?? [] ) );
    wp_send_json_success( array_values( $tags ) );
} );

add_action( 'wp_ajax_tao_crm_set_card_tags', function () {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    $card_id = sanitize_text_field( $_POST['card_id'] ?? '' );
    $tag_ids = array_map( 'sanitize_text_field', (array) ( $_POST['tag_ids'] ?? [] ) );
    tao_crm_api( "/crm_cards_tags?card_id=eq.$card_id", 'DELETE' );
    foreach ( array_filter( $tag_ids ) as $tag_id ) {
        tao_crm_api( '/crm_cards_tags', 'POST', [ 'card_id' => $card_id, 'tag_id' => $tag_id ] );
    }
    wp_send_json_success();
} );

// ── Lembretes ─────────────────────────────────────────────────────────────────

add_action( 'wp_ajax_tao_crm_get_lembretes', function () {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    $card_id = sanitize_text_field( $_POST['card_id'] ?? '' );
    $r       = tao_crm_api( "/crm_lembretes?card_id=eq.$card_id&order=data_hora.asc" );
    $r['ok'] ? wp_send_json_success( $r['data'] ) : wp_send_json_error( $r['error'] );
} );

add_action( 'wp_ajax_tao_crm_save_lembrete', function () {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    $id      = sanitize_text_field( $_POST['id'] ?? '' );
    $card_id = sanitize_text_field( $_POST['card_id'] ?? '' );
    $ws_id   = sanitize_text_field( $_POST['workspace_id'] ?? '' );
    $dh_raw  = sanitize_text_field( $_POST['data_hora'] ?? '' );
    $dh_iso  = $dh_raw;
    if ( $dh_raw ) {
        try {
            $dt     = new DateTime( $dh_raw, new DateTimeZone( wp_timezone_string() ) );
            $dt->setTimezone( new DateTimeZone( 'UTC' ) );
            $dh_iso = $dt->format( 'c' );
        } catch ( Exception $e ) {}
    }
    $data = [
        'card_id'      => $card_id,
        'workspace_id' => $ws_id,
        'user_id'      => get_current_user_id(),
        'titulo'       => sanitize_text_field( $_POST['titulo'] ?? '' ),
        'descricao'    => sanitize_textarea_field( $_POST['descricao'] ?? '' ),
        'data_hora'    => $dh_iso,
        'completado'   => false,
    ];
    if ( $id ) {
        $r = tao_crm_api( "/crm_lembretes?id=eq.$id", 'PATCH', $data, [ 'Prefer' => 'return=representation' ] );
    } else {
        $r = tao_crm_api( '/crm_lembretes', 'POST', $data, [ 'Prefer' => 'return=representation' ] );
    }
    $r['ok'] ? wp_send_json_success( $r['data'][0] ?? [] ) : wp_send_json_error( $r['error'] );
} );

add_action( 'wp_ajax_tao_crm_complete_lembrete', function () {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    $id = sanitize_text_field( $_POST['id'] ?? '' );
    $r  = tao_crm_api( "/crm_lembretes?id=eq.$id", 'PATCH', [ 'completado' => true, 'notificado' => true ] );
    $r['ok'] ? wp_send_json_success() : wp_send_json_error( $r['error'] );
} );

add_action( 'wp_ajax_tao_crm_delete_lembrete', function () {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    $id = sanitize_text_field( $_POST['id'] ?? '' );
    $r  = tao_crm_api( "/crm_lembretes?id=eq.$id", 'DELETE' );
    $r['ok'] ? wp_send_json_success() : wp_send_json_error( $r['error'] );
} );

// ── Histórico ─────────────────────────────────────────────────────────────────

add_action( 'wp_ajax_tao_crm_get_historico', function () {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    $card_id = sanitize_text_field( $_POST['card_id'] ?? '' );
    $r       = tao_crm_api( "/crm_cards_historico?card_id=eq.$card_id&order=criado_em.desc&limit=50&select=id,de_estagio_id,para_estagio_id,usuario_id,criado_em" );
    $r['ok'] ? wp_send_json_success( $r['data'] ) : wp_send_json_error( $r['error'] );
} );

// ── Valor de oportunidade ─────────────────────────────────────────────────────

add_action( 'wp_ajax_tao_crm_save_valor_oportunidade', function () {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    $card_id = sanitize_text_field( $_POST['card_id'] ?? '' );
    $valor   = floatval( $_POST['valor'] ?? 0 );
    $r       = tao_crm_api( "/crm_cards?id=eq.$card_id", 'PATCH', [ 'valor_oportunidade' => $valor ] );
    $r['ok'] ? wp_send_json_success() : wp_send_json_error( $r['error'] );
} );

// ── Salvar desconto concedido no card (recalcula valor de oportunidade) ──────────
add_action( 'wp_ajax_tao_crm_save_desconto', function () {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    if ( ! function_exists( 'cbpm_can_access' ) || ! cbpm_can_access() ) wp_send_json_error( 'Acesso negado' );
    $card_id = sanitize_text_field( $_POST['card_id'] ?? '' );
    $desc    = floatval( $_POST['desconto'] ?? 0 );
    if ( $desc < 0 ) $desc = 0;                       // sem limite superior; não permite negativo
    $dtipo   = ( ( $_POST['desconto_tipo'] ?? 'valor' ) === 'pct' ) ? 'pct' : 'valor';
    if ( ! $card_id ) wp_send_json_error( 'card_id obrigatório' );
    $r = tao_crm_api( "/crm_cards?id=eq.$card_id", 'PATCH', [ 'desconto' => $desc, 'desconto_tipo' => $dtipo ] );
    if ( ! $r['ok'] ) wp_send_json_error( $r['error'] ?? 'Erro ao salvar desconto' );
    // Recalcula o valor apenas se houver itens/orçamentos (card manual mantém o valor digitado)
    $tem = false;
    $ri  = tao_crm_api( "/crm_card_itens?card_id=eq.$card_id&select=id&limit=1" );
    if ( $ri['ok'] && ! empty( $ri['data'] ) ) $tem = true;
    if ( ! $tem ) {
        $ro = tao_crm_api( "/orcamentos?card_id=eq.$card_id&select=id&limit=1" );
        if ( $ro['ok'] && ! empty( $ro['data'] ) ) $tem = true;
    }
    if ( $tem ) tao_crm_sync_valor_oportunidade( $card_id );
    $rc    = tao_crm_api( "/crm_cards?id=eq.$card_id&select=valor_oportunidade&limit=1" );
    $valor = ( $rc['ok'] && ! empty( $rc['data'] ) ) ? floatval( $rc['data'][0]['valor_oportunidade'] ?? 0 ) : 0;
    wp_send_json_success( [ 'valor' => $valor ] );
} );

// ── Billing / Planos ──────────────────────────────────────────────────────────

function tao_crm_get_plano_limites( string $plano ): array {
    $planos = [
        'free'     => [ 'label' => 'Gratuito', 'cards' => 50,   'usuarios' => 2,  'instancias' => 1,  'automacoes' => 3  ],
        'starter'  => [ 'label' => 'Starter',  'cards' => 500,  'usuarios' => 5,  'instancias' => 2,  'automacoes' => 10 ],
        'pro'      => [ 'label' => 'Pro',       'cards' => 5000, 'usuarios' => 15, 'instancias' => 5,  'automacoes' => 50 ],
        'business' => [ 'label' => 'Business',  'cards' => -1,   'usuarios' => -1, 'instancias' => -1, 'automacoes' => -1 ],
    ];
    return $planos[ $plano ] ?? $planos['free'];
}

add_action( 'wp_ajax_tao_crm_get_plano_info', function () {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    $ws_id = sanitize_text_field( $_POST['workspace_id'] ?? '' );
    $r     = tao_crm_api( "/crm_workspaces?id=eq.$ws_id&select=plano,plano_expira_em&limit=1" );
    $ws    = $r['ok'] && ! empty( $r['data'] ) ? $r['data'][0] : [];
    $plano = $ws['plano'] ?? 'free';
    $lim   = tao_crm_get_plano_limites( $plano );
    wp_send_json_success( [ 'plano' => $plano, 'label' => $lim['label'], 'limites' => $lim, 'expira_em' => $ws['plano_expira_em'] ?? null ] );
} );

add_action( 'wp_ajax_tao_crm_admin_set_plano', function () {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Acesso negado' );
    $ws_id = sanitize_text_field( $_POST['workspace_id'] ?? '' );
    $plano = sanitize_text_field( $_POST['plano'] ?? 'free' );
    $dias  = intval( $_POST['dias'] ?? 0 );
    $expira = null;
    if ( $dias > 0 ) {
        $dt = new DateTime( 'now', new DateTimeZone( 'UTC' ) );
        $dt->modify( "+$dias days" );
        $expira = $dt->format( 'c' );
    }
    $r = tao_crm_api( "/crm_workspaces?id=eq.$ws_id", 'PATCH', [ 'plano' => $plano, 'plano_expira_em' => $expira ] );
    $r['ok'] ? wp_send_json_success( [ 'plano' => $plano, 'expira_em' => $expira ] ) : wp_send_json_error( $r['error'] );
} );

// ── Cron de lembretes (registrado em init junto com os outros crons) ──────────

add_action( 'tao_crm_check_lembretes', 'tao_crm_processar_lembretes' );

function tao_crm_processar_lembretes(): void {
    $now      = rawurlencode( ( new DateTime( 'now', new DateTimeZone( 'UTC' ) ) )->format( 'c' ) );
    $r        = tao_crm_api( "/crm_lembretes?data_hora=lte.$now&completado=eq.false&notificado=eq.false&limit=20" );
    if ( ! $r['ok'] || empty( $r['data'] ) ) return;
    foreach ( $r['data'] as $lem ) {
        $uid  = intval( $lem['user_id'] ?? 0 );
        $u    = get_userdata( $uid );
        if ( $u && $u->user_email ) {
            $link = admin_url( 'admin.php?page=tao-crm-kanban&action=card&id=' . ( $lem['card_id'] ?? '' ) );
            wp_mail( $u->user_email,
                '[TAO CRM] Lembrete: ' . ( $lem['titulo'] ?? '' ),
                ( $lem['titulo'] ?? '' ) . "\n\n" . ( $lem['descricao'] ?? '' ) . "\n\nCard: $link"
            );
        }
        tao_crm_api( "/crm_lembretes?id=eq.{$lem['id']}", 'PATCH', [ 'notificado' => true ] );
    }
}



// ─── v1.4.0: Transferir, Import, Relatorio, Sem-Resposta ────────────────────

// ── AJAX: Transferir card ────────────────────────────────────────────────────
add_action( 'wp_ajax_tao_crm_transferir_card', 'tao_crm_ajax_transferir_card' );
function tao_crm_ajax_transferir_card() {
    check_ajax_referer( 'tao_crm_nonce', '_wpnonce' );
    $card_id  = sanitize_text_field( $_POST['card_id']         ?? '' );
    $novo_uid = intval( $_POST['novo_responsavel_id']           ?? 0 );
    if ( ! $card_id || ! $novo_uid ) wp_send_json_error( 'Parâmetros obrigatórios ausentes' );

    // Busca dados do card (workspace_id vem do banco, não confiamos no POST)
    $rc = tao_crm_api( "/crm_cards?id=eq.$card_id&select=id,titulo,responsavel_id,workspace_id" );
    if ( ! $rc['ok'] || empty( $rc['data'] ) ) wp_send_json_error( 'Card não encontrado' );
    $card         = $rc['data'][0];
    $workspace_id = $card['workspace_id'] ?? '';
    if ( ! tao_crm_is_gestor( $workspace_id ) ) wp_send_json_error( 'Acesso negado' );
    $old_uid      = intval( $card['responsavel_id'] ?? 0 );
    $novo_user    = get_userdata( $novo_uid );
    if ( ! $novo_user ) wp_send_json_error( 'Usuário não encontrado' );

    // Atualiza responsavel_id no card
    $rp = tao_crm_api( "/crm_cards?id=eq.$card_id", 'PATCH', [ 'responsavel_id' => $novo_uid ] );
    if ( ! $rp['ok'] ) wp_send_json_error( 'Erro ao atualizar card: ' . $rp['error'] );

    // Registra no histórico
    $old_user = $old_uid ? get_userdata( $old_uid ) : null;
    $old_nome = $old_user ? $old_user->display_name : 'Ninguém';
    tao_crm_api( '/crm_historico', 'POST', [
        'card_id'      => $card_id,
        'workspace_id' => $card['workspace_id'],
        'user_id'      => get_current_user_id(),
        'tipo'         => 'transferencia',
        'de'           => $old_nome,
        'para'         => $novo_user->display_name,
    ] );

    // E-mail para o novo atendente
    $subject = '[TAO CRM] Card transferido para você: ' . $card['nome'];
    $msg     = sprintf(
        "Olá %s,\n\nO card \"%s\" foi transferido para você no TAO CRM.\n\nAcesse: %s\n\n— TAO CRM",
        $novo_user->display_name,
        $card['nome'],
        admin_url( 'admin.php?page=tao-crm-kanban' )
    );
    wp_mail( $novo_user->user_email, $subject, $msg );

    wp_send_json_success( [ 'responsavel_nome' => $novo_user->display_name ] );
}

// ── AJAX: Importar leads via CSV ─────────────────────────────────────────────
add_action( 'wp_ajax_tao_crm_import_csv', 'tao_crm_ajax_import_csv' );
function tao_crm_ajax_import_csv() {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Acesso negado' );

    $workspace_id = sanitize_text_field( $_POST['workspace_id'] ?? '' );
    $pipeline_id  = sanitize_text_field( $_POST['pipeline_id']  ?? '' );
    if ( ! $workspace_id ) wp_send_json_error( 'workspace_id obrigatório' );

    if ( empty( $_FILES['csv_file']['tmp_name'] ) ) wp_send_json_error( 'Arquivo CSV não enviado' );
    $tmp  = $_FILES['csv_file']['tmp_name'];
    $rows = array_map( 'str_getcsv', file( $tmp ) );
    if ( count( $rows ) < 2 ) wp_send_json_error( 'CSV vazio ou apenas cabeçalho' );

    // Normaliza cabeçalho
    $header = array_map( fn( $h ) => mb_strtolower( trim( $h ) ), $rows[0] );
    $col    = fn( $k ) => array_search( $k, $header, true );

    // Busca o primeiro estágio do pipeline
    $est_id = '';
    if ( $pipeline_id ) {
        $re = tao_crm_api( "/crm_estagios?pipeline_id=eq.$pipeline_id&order=ordem.asc&limit=1" );
        $est_id = $re['ok'] && ! empty( $re['data'] ) ? $re['data'][0]['id'] : '';
    }

    // Índices das colunas
    $i_nome  = array_search( 'nome',        $header, true );
    $i_wa    = array_search( 'whatsapp',    $header, true );
    $i_email = array_search( 'email',       $header, true );
    $i_obs   = array_search( 'observacoes', $header, true );

    $importados = 0; $duplicados = 0; $erros = 0;
    foreach ( array_slice( $rows, 1 ) as $row ) {
        if ( count( $row ) < 2 ) continue;
        $nome     = $i_nome  !== false ? sanitize_text_field( $row[ $i_nome ]  ?? '' ) : '';
        $whatsapp = $i_wa    !== false ? preg_replace( '/\D/', '', $row[ $i_wa ] ?? '' ) : '';
        $email    = $i_email !== false ? sanitize_email( $row[ $i_email ] ?? '' ) : '';
        $obs      = $i_obs   !== false ? sanitize_textarea_field( $row[ $i_obs ] ?? '' ) : '';

        if ( ! $nome && ! $whatsapp ) { $erros++; continue; }
        if ( ! $nome ) $nome = $whatsapp;

        // Dedup por whatsapp
        $existente = null;
        if ( $whatsapp ) {
            $rc = tao_crm_api( "/crm_contatos?workspace_id=eq.$workspace_id&whatsapp=eq.$whatsapp&limit=1" );
            if ( $rc['ok'] && ! empty( $rc['data'] ) ) { $existente = $rc['data'][0]; $duplicados++; }
        }

        if ( ! $existente ) {
            $rc2 = tao_crm_api( '/crm_contatos', 'POST', array_filter( [
                'workspace_id' => $workspace_id,
                'nome'         => $nome,
                'whatsapp'     => $whatsapp,
                'email'        => $email,
            ] ), [ 'Prefer' => 'return=representation' ] );
            if ( ! $rc2['ok'] ) { $erros++; continue; }
        }

        // Cria card no pipeline
        if ( $pipeline_id ) {
            $card_data = array_filter( [
                'workspace_id' => $workspace_id,
                'pipeline_id'  => $pipeline_id,
                'estagio_id'   => $est_id ?: null,
                'nome'         => $nome,
                'whatsapp'     => $whatsapp,
                'status'       => 'aberto',
                'observacoes'  => $obs,
            ] );
            $rcard = tao_crm_api( '/crm_cards', 'POST', $card_data );
            if ( ! $rcard['ok'] ) { $erros++; continue; }
        }
        $importados++;
    }

    wp_send_json_success( [
        'importados' => $importados,
        'duplicados' => $duplicados,
        'erros'      => $erros,
    ] );
}

// ── Admin-post: Exportar relatório CSV ───────────────────────────────────────
add_action( 'admin_post_tao_crm_export_relatorio', 'tao_crm_export_relatorio_csv' );
function tao_crm_export_relatorio_csv() {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Acesso negado' );
    check_admin_referer( 'tao_crm_export_relatorio' );

    $workspace_id = sanitize_text_field( $_GET['workspace_id'] ?? '' );
    $de   = sanitize_text_field( $_GET['de']   ?? date( 'Y-m-01' ) );
    $ate  = sanitize_text_field( $_GET['ate']  ?? date( 'Y-m-d' ) );

    // Busca cards no período
    $q = "/crm_cards?workspace_id=eq.$workspace_id&criado_em=gte.$de&criado_em=lte.{$ate}T23:59:59&select=responsavel_id,status,valor_oportunidade,estagio_id";
    $rc = tao_crm_api( $q );
    $cards = $rc['ok'] ? ( $rc['data'] ?? [] ) : [];

    // Agrupa por atendente
    $stats = [];
    foreach ( $cards as $c ) {
        $uid = intval( $c['responsavel_id'] ?? 0 );
        if ( ! isset( $stats[$uid] ) ) {
            $u = $uid ? get_userdata( $uid ) : null;
            $stats[$uid] = [
                'nome'       => $u ? $u->display_name : 'Sem atendente',
                'total'      => 0,
                'abertos'    => 0,
                'fechados'   => 0,
                'valor_total'=> 0,
            ];
        }
        $stats[$uid]['total']++;
        if ( ( $c['status'] ?? '' ) === 'fechado' ) $stats[$uid]['fechados']++;
        else $stats[$uid]['abertos']++;
        $stats[$uid]['valor_total'] += floatval( $c['valor_oportunidade'] ?? 0 );
    }

    while ( ob_get_level() > 0 ) ob_end_clean();   // descarta byte espúrio (?/0x3F) antes do BOM
    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename="relatorio_crm_' . date('Y-m-d') . '.csv"' );
    $out = fopen( 'php://output', 'w' );
    fprintf( $out, chr(0xEF).chr(0xBB).chr(0xBF) ); // BOM UTF-8
    fputcsv( $out, [ 'Atendente', 'Total Cards', 'Abertos', 'Fechados', 'Valor Total (R$)' ] );
    foreach ( $stats as $row ) {
        fputcsv( $out, [
            $row['nome'],
            $row['total'],
            $row['abertos'],
            $row['fechados'],
            number_format( $row['valor_total'], 2, ',', '.' ),
        ] );
    }
    fclose( $out );
    exit;
}

// ─── v1.8.0: RELATÓRIO FINANCEIRO CSV ────────────────────────────────────────
add_action( 'admin_post_tao_crm_export_relatorio_financeiro', 'tao_crm_export_relatorio_financeiro' );
function tao_crm_export_relatorio_financeiro() {
    if ( ! current_user_can( 'manage_options' ) && ! tao_crm_is_gestor( sanitize_text_field( $_GET['workspace_id'] ?? '' ) ) ) {
        wp_die( 'Acesso negado' );
    }
    check_admin_referer( 'tao_crm_export_relatorio_financeiro' );

    $ws_id = sanitize_text_field( $_GET['workspace_id'] ?? '' );
    $dias  = max( 7, min( 365, intval( $_GET['dias'] ?? 30 ) ) );
    $desde = gmdate( 'Y-m-d\TH:i:s\Z', strtotime( "-{$dias} days" ) );
    $ate   = gmdate( 'Y-m-d\T23:59:59\Z' );

    // Busca cards do período com todos os campos relevantes
    $rc = tao_crm_api(
        "/crm_cards?workspace_id=eq.$ws_id" .
        "&criado_em=gte.$desde&criado_em=lte.$ate" .
        "&select=id,titulo,contato_nome,contato_whatsapp,status,fechado,responsavel_id," .
               "pipeline_id,estagio_id,valor_oportunidade,criado_em,movido_em,meta" .
        "&order=criado_em.desc&limit=5000"
    );
    $cards = $rc['ok'] ? ( $rc['data'] ?? [] ) : [];

    // Mapas auxiliares
    $estagios_map  = [];
    $pipelines_map = [];
    $res_pipes = tao_crm_api( "/crm_pipelines?workspace_id=eq.$ws_id&select=id,nome" );
    foreach ( $res_pipes['ok'] ? $res_pipes['data'] : [] as $p ) {
        $pipelines_map[ $p['id'] ] = $p['nome'];
    }
    $estagio_ids = array_unique( array_column( $cards, 'estagio_id' ) );
    if ( ! empty( $estagio_ids ) ) {
        $res_est = tao_crm_api( '/crm_estagios?id=in.(' . implode( ',', $estagio_ids ) . ')&select=id,nome,tipo' );
        foreach ( $res_est['ok'] ? $res_est['data'] : [] as $e ) {
            $estagios_map[ $e['id'] ] = $e;
        }
    }

    while ( ob_get_level() > 0 ) ob_end_clean();   // descarta byte espúrio (?/0x3F) antes do BOM
    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename="relatorio_financeiro_' . date( 'Y-m-d' ) . '.csv"' );
    $out = fopen( 'php://output', 'w' );
    fprintf( $out, chr(0xEF).chr(0xBB).chr(0xBF) ); // BOM UTF-8 — compatível com Excel

    fputcsv( $out, [
        'ID', 'Título', 'Contato', 'WhatsApp', 'Responsável',
        'Pipeline', 'Estágio', 'Status', 'Valor (R$)',
        'Criado em', 'Última movimentação',
    ] );

    foreach ( $cards as $c ) {
        $uid  = intval( $c['responsavel_id'] ?? 0 );
        $u    = $uid ? get_userdata( $uid ) : null;
        $est  = $estagios_map[ $c['estagio_id'] ?? '' ] ?? [];
        $meta = is_string( $c['meta'] ) ? ( json_decode( $c['meta'], true ) ?: [] ) : ( $c['meta'] ?: [] );

        $status_label = match( $c['status'] ?? '' ) {
            'fechado'    => 'Ganho',
            'cancelado'  => 'Perdido',
            default      => 'Aberto',
        };

        fputcsv( $out, [
            substr( $c['id'] ?? '', 0, 8 ),
            $c['titulo']           ?? $c['contato_nome'] ?? '',
            $c['contato_nome']     ?? '',
            tao_crm_format_phone( $c['contato_whatsapp'] ?? '' ),
            $u ? $u->display_name  : 'Sem atendente',
            $pipelines_map[ $c['pipeline_id'] ?? '' ] ?? '',
            $est['nome']           ?? '',
            $status_label,
            number_format( floatval( $c['valor_oportunidade'] ?? 0 ), 2, ',', '.' ),
            tao_crm_brt( $c['criado_em']  ?? '', 'd/m/Y H:i' ),
            tao_crm_brt( $c['movido_em']  ?? '', 'd/m/Y H:i' ),
        ] );
    }

    // ── Linha de totais ───────────────────────────────────────────────────────
    $total_ganho    = array_sum( array_map( fn($c) => $c['status']==='fechado'   ? floatval($c['valor_oportunidade']??0) : 0, $cards ) );
    $total_perdido  = count( array_filter( $cards, fn($c) => $c['status']==='cancelado' ) );
    $total_ganhos   = count( array_filter( $cards, fn($c) => $c['status']==='fechado' ) );
    $total_abertos  = count( array_filter( $cards, fn($c) => !in_array($c['status']??'', ['fechado','cancelado']) ) );

    fputcsv( $out, [] ); // linha em branco
    fputcsv( $out, [ '=== RESUMO ===' ] );
    fputcsv( $out, [ 'Total de cards no período', count($cards) ] );
    fputcsv( $out, [ 'Ganhos',  $total_ganhos,  '', '', number_format($total_ganho,2,',','.') ] );
    fputcsv( $out, [ 'Perdidos', $total_perdido ] );
    fputcsv( $out, [ 'Abertos',  $total_abertos ] );
    fputcsv( $out, [ 'Período', "Últimos $dias dias (até " . date('d/m/Y') . ')' ] );

    fclose( $out );
    exit;
}

// ── CRON: Automação sem resposta ─────────────────────────────────────────────
add_action( 'tao_crm_check_sem_resposta', 'tao_crm_processar_sem_resposta' );
function tao_crm_processar_sem_resposta() {
    // Busca automações do tipo sem_resposta
    $ra = tao_crm_api( "/crm_automacoes?tipo=eq.sem_resposta&ativo=eq.true" );
    if ( ! $ra['ok'] || empty( $ra['data'] ) ) return;

    foreach ( $ra['data'] as $auto ) {
        $ws_id  = $auto['workspace_id'];
        $pipe   = $auto['pipeline_id'] ?? '';
        $horas  = intval( $auto['horas_sem_resposta'] ?? 24 );
        $acao   = $auto['acao'] ?? '';
        $limite = gmdate( 'Y-m-d\TH:i:s\Z', time() - $horas * 3600 );

        // Cards abertos onde a última mensagem é do cliente e é mais antiga que $limite
        $q = "/crm_cards?workspace_id=eq.$ws_id&status=eq.aberto" . ( $pipe ? "&pipeline_id=eq.$pipe" : '' );
        $rcs = tao_crm_api( $q );
        if ( ! $rcs['ok'] ) continue;

        foreach ( $rcs['data'] as $card ) {
            $cid = $card['id'];
            // Última mensagem do card
            $rm = tao_crm_api( "/crm_mensagens?card_id=eq.$cid&order=criado_em.desc&limit=1" );
            if ( ! $rm['ok'] || empty( $rm['data'] ) ) continue;
            $ult = $rm['data'][0];
            // Só dispara se a última mensagem é do cliente (entrante) e mais antiga que o limite
            if ( ( $ult['direcao'] ?? '' ) !== 'in' ) continue;
            if ( ( $ult['criado_em'] ?? '' ) > $limite ) continue;

            // Verifica se já processou (flag no card para evitar spam)
            $flag_key = 'sem_resposta_auto_' . $auto['id'];
            $flags    = json_decode( $card['meta'] ?? '{}', true ) ?: [];
            if ( isset( $flags[$flag_key] ) && $flags[$flag_key] === $ult['id'] ) continue;

            // Executa ação
            if ( $acao === 'notificar_email' ) {
                $uid = intval( $card['responsavel_id'] ?? 0 );
                if ( $uid ) {
                    $u = get_userdata( $uid );
                    if ( $u ) {
                        wp_mail( $u->user_email,
                            '[TAO CRM] Lead sem resposta: ' . $card['nome'],
                            "O lead {$card['nome']} está aguardando resposta há mais de {$horas}h.\n\nAcesse: " . admin_url('admin.php?page=tao-crm-kanban')
                        );
                    }
                }
            }

            // Atualiza flag
            $flags[$flag_key] = $ult['id'];
            tao_crm_api( "/crm_cards?id=eq.$cid", 'PATCH', [ 'meta' => wp_json_encode( $flags ) ] );
        }
    }
}


// ─── v1.5.0: Comentarios, Busca, Reabrir, Metas, Agendamento, Dedup ─────────

// ── Comentários internos ─────────────────────────────────────────────────────
add_action( 'wp_ajax_tao_crm_get_comentarios', 'tao_crm_ajax_get_comentarios' );
function tao_crm_ajax_get_comentarios() {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    if ( ! function_exists( 'cbpm_can_access' ) || ! cbpm_can_access() ) wp_send_json_error( 'no access' );
    $card_id = sanitize_text_field( $_POST['card_id'] ?? '' );
    if ( ! $card_id ) wp_send_json_error( 'card_id obrigatório' );
    $r = tao_crm_api( "/crm_comentarios?card_id=eq.$card_id&order=criado_em.asc" );
    if ( ! $r['ok'] ) wp_send_json_error( $r['error'] );
    $uid = get_current_user_id();
    $data = array_map( function( $c ) use ( $uid ) {
        $u = get_userdata( intval( $c['user_id'] ?? 0 ) );
        $c['autor_nome'] = $u ? $u->display_name : 'Equipe';
        $c['can_delete']  = ( intval( $c['user_id'] ) === $uid ) || current_user_can( 'manage_options' );
        return $c;
    }, $r['data'] ?? [] );
    wp_send_json_success( $data );
}

add_action( 'wp_ajax_tao_crm_save_comentario', 'tao_crm_ajax_save_comentario' );
function tao_crm_ajax_save_comentario() {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    if ( ! function_exists( 'cbpm_can_access' ) || ! cbpm_can_access() ) wp_send_json_error( 'no access' );
    $card_id  = sanitize_text_field( $_POST['card_id']  ?? '' );
    $conteudo = sanitize_textarea_field( $_POST['conteudo'] ?? '' );
    if ( ! $card_id || ! $conteudo ) wp_send_json_error( 'Campos obrigatórios' );
    // Busca workspace_id do card
    $rc = tao_crm_api( "/crm_cards?id=eq.$card_id&select=workspace_id&limit=1" );
    $ws_id = $rc['ok'] && ! empty( $rc['data'] ) ? $rc['data'][0]['workspace_id'] : '';
    $r = tao_crm_api( '/crm_comentarios', 'POST', [
        'card_id'      => $card_id,
        'workspace_id' => $ws_id,
        'user_id'      => get_current_user_id(),
        'conteudo'     => $conteudo,
    ], [ 'Prefer' => 'return=representation' ] );
    $r['ok'] ? wp_send_json_success( $r['data'][0] ?? [] ) : wp_send_json_error( $r['error'] );
}

add_action( 'wp_ajax_tao_crm_delete_comentario', 'tao_crm_ajax_delete_comentario' );
function tao_crm_ajax_delete_comentario() {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    $id = sanitize_text_field( $_POST['id'] ?? '' );
    if ( ! $id ) wp_send_json_error( 'id obrigatório' );
    // Só o autor ou admin pode excluir
    $rc = tao_crm_api( "/crm_comentarios?id=eq.$id&select=user_id&limit=1" );
    if ( ! $rc['ok'] || empty( $rc['data'] ) ) wp_send_json_error( 'Não encontrado' );
    $owner = intval( $rc['data'][0]['user_id'] ?? 0 );
    if ( $owner !== get_current_user_id() && ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Acesso negado' );
    $r = tao_crm_api( "/crm_comentarios?id=eq.$id", 'DELETE' );
    $r['ok'] ? wp_send_json_success() : wp_send_json_error( $r['error'] );
}

// ── Busca global ─────────────────────────────────────────────────────────────
add_action( 'wp_ajax_tao_crm_search_global', 'tao_crm_ajax_search_global' );
function tao_crm_ajax_search_global() {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    if ( ! function_exists( 'cbpm_can_access' ) || ! cbpm_can_access() ) wp_send_json_error( 'no access' );
    $q        = sanitize_text_field( $_POST['q'] ?? '' );
    $ws_id    = sanitize_text_field( $_POST['workspace_id'] ?? '' );
    if ( strlen( $q ) < 2 ) wp_send_json_success( [] );

    $q_enc = urlencode( $q );
    $ws_filter = $ws_id ? "&workspace_id=eq.$ws_id" : '';

    // Gestor vê todos; atendente só vê os próprios
    $cards_filter = '';
    if ( ! current_user_can( 'manage_options' ) ) {
        $uid = get_current_user_id();
        $is_gestor = false;
        if ( $ws_id ) {
            $is_gestor = tao_crm_is_gestor( $ws_id );
        }
        if ( ! $is_gestor ) {
            $cards_filter = "&responsavel_id=eq.$uid";
        }
    }

    $results = [];

    // Busca por título ou contato do card
    $r1 = tao_crm_api( "/crm_cards?titulo=ilike.*$q_enc*$ws_filter$cards_filter&select=id,titulo,contato_whatsapp,contato_nome,status,estagio_id,pipeline_id,workspace_id&limit=10" );
    if ( $r1['ok'] ) {
        foreach ( $r1['data'] ?? [] as $c ) {
            $results[] = [ 'tipo' => 'card', 'id' => $c['id'], 'titulo' => $c['titulo'], 'sub' => $c['contato_whatsapp'] ?? $c['contato_nome'] ?? '', 'status' => $c['status'] ?? 'aberto', 'pipeline_id' => $c['pipeline_id'] ?? '', 'workspace_id' => $c['workspace_id'] ?? '' ];
        }
    }

    // Busca por WhatsApp do contato
    $wa_q = preg_replace( '/\D/', '', $q );
    if ( strlen( $wa_q ) >= 5 ) {
        $r2 = tao_crm_api( "/crm_cards?contato_whatsapp=like.*$wa_q*$ws_filter$cards_filter&select=id,titulo,contato_whatsapp,status,pipeline_id,workspace_id&limit=5" );
        if ( $r2['ok'] ) {
            $existing_ids = array_column( $results, 'id' );
            foreach ( $r2['data'] ?? [] as $c ) {
                if ( in_array( $c['id'], $existing_ids, true ) ) continue;
                $results[] = [ 'tipo' => 'card', 'id' => $c['id'], 'titulo' => $c['titulo'], 'sub' => $c['contato_whatsapp'] ?? '', 'status' => $c['status'] ?? 'aberto', 'pipeline_id' => $c['pipeline_id'] ?? '', 'workspace_id' => $c['workspace_id'] ?? '' ];
            }
        }
    }

    // Busca por Nº de Requisição (o operador procura pelo #requisição do card).
    // A requisição = segmento do meio de orcamentos.numero_orcamento (mesma régua do Kanban):
    // acha o orçamento pelo número e traz o card vinculado. Cobre o número DERIVADO do orçamento
    // (quando o campo "Número Requisição" não foi digitado à mão). Filtro de ws/responsável é
    // aplicado na 2ª query (crm_cards), então respeita a permissão do usuário.
    if ( preg_match( '/\d/', $q ) ) {
        $existing_ids = array_column( $results, 'id' );
        $r_orc = tao_crm_api( "/orcamentos?numero_orcamento=ilike.*$q_enc*&select=card_id,numero_orcamento&limit=20" );
        $orc_card_ids = [];
        if ( $r_orc['ok'] ) {
            foreach ( $r_orc['data'] ?? [] as $o ) {
                $ocid = $o['card_id'] ?? '';
                if ( $ocid && ! in_array( $ocid, $existing_ids, true ) && ! isset( $orc_card_ids[ $ocid ] ) ) {
                    $orc_card_ids[ $ocid ] = $o['numero_orcamento'] ?? '';
                }
            }
        }
        if ( $orc_card_ids ) {
            $ids_in = implode( ',', array_keys( $orc_card_ids ) );
            $r_oc = tao_crm_api( "/crm_cards?id=in.($ids_in)$ws_filter$cards_filter&select=id,titulo,contato_whatsapp,contato_nome,status,estagio_id,pipeline_id,workspace_id&limit=10" );
            if ( $r_oc['ok'] ) {
                foreach ( $r_oc['data'] ?? [] as $c ) {
                    $results[] = [ 'tipo' => 'card', 'id' => $c['id'], 'titulo' => $c['titulo'], 'sub' => 'Req/Orç: ' . ( $orc_card_ids[ $c['id'] ] ?? '' ), 'status' => $c['status'] ?? 'aberto', 'pipeline_id' => $c['pipeline_id'] ?? '', 'workspace_id' => $c['workspace_id'] ?? '' ];
                }
            }
        }
    }

    // Busca em contatos
    $r3 = tao_crm_api( "/crm_contatos?nome=ilike.*$q_enc*$ws_filter&select=id,nome,whatsapp,email&limit=5" );
    if ( $r3['ok'] ) {
        foreach ( $r3['data'] ?? [] as $c ) {
            $results[] = [ 'tipo' => 'contato', 'id' => $c['id'], 'titulo' => $c['nome'], 'sub' => $c['whatsapp'] ?? $c['email'] ?? '', 'workspace_id' => $c['workspace_id'] ?? '' ];
        }
    }

    wp_send_json_success( array_slice( $results, 0, 15 ) );
}

// ── Reabrir card ─────────────────────────────────────────────────────────────
add_action( 'wp_ajax_tao_crm_reabrir_card', 'tao_crm_ajax_reabrir_card' );
function tao_crm_ajax_reabrir_card() {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    $card_id = sanitize_text_field( $_POST['card_id'] ?? '' );
    if ( ! $card_id ) wp_send_json_error( 'card_id obrigatório' );
    $rc = tao_crm_api( "/crm_cards?id=eq.$card_id&select=workspace_id,estagio_id,pipeline_id&limit=1" );
    if ( ! $rc['ok'] || empty( $rc['data'] ) ) wp_send_json_error( 'Card não encontrado' );
    $card  = $rc['data'][0];
    $ws_id = $card['workspace_id'] ?? '';
    if ( ! tao_crm_is_gestor( $ws_id ) ) wp_send_json_error( 'Acesso negado — apenas gestores podem reabrir cards' );

    // Volta o card para a fase de ORIGEM (de onde saiu ao ser fechado), se a fase
    // atual for terminal (ganho/perdido). Fallback: primeira fase normal do funil.
    $est_atual = $card['estagio_id'] ?? '';
    $destino   = null;
    $re_atual  = $est_atual ? tao_crm_api( "/crm_estagios?id=eq.$est_atual&select=id,nome,tipo,pipeline_id" ) : [ 'ok' => false, 'data' => [] ];
    $tipo_atual = ( $re_atual['ok'] && ! empty( $re_atual['data'] ) ) ? ( $re_atual['data'][0]['tipo'] ?? '' ) : '';

    if ( in_array( $tipo_atual, [ 'ganho', 'perdido' ], true ) ) {
        // Origem = de_estagio do último movimento PARA a fase terminal atual
        $rh = tao_crm_api( "/crm_cards_historico?card_id=eq.$card_id&para_estagio_id=eq.$est_atual&de_estagio_id=not.is.null&select=de_estagio_id&order=criado_em.desc&limit=1" );
        $origem = ( $rh['ok'] && ! empty( $rh['data'] ) ) ? $rh['data'][0]['de_estagio_id'] : null;
        if ( $origem ) {
            $ro = tao_crm_api( "/crm_estagios?id=eq.$origem&select=id,nome,tipo,pipeline_id" );
            if ( $ro['ok'] && ! empty( $ro['data'] )
                 && ! in_array( $ro['data'][0]['tipo'] ?? '', [ 'ganho', 'perdido' ], true )
                 && ( $ro['data'][0]['pipeline_id'] ?? '' ) === ( $card['pipeline_id'] ?? '' ) ) {
                $destino = $ro['data'][0];
            }
        }
        if ( ! $destino && ! empty( $card['pipeline_id'] ) ) {
            $rf = tao_crm_api( "/crm_estagios?pipeline_id=eq.{$card['pipeline_id']}&tipo=eq.normal&order=ordem.asc&limit=1" );
            if ( $rf['ok'] && ! empty( $rf['data'] ) ) $destino = $rf['data'][0];
        }
    }

    $patch = [ 'fechado' => false, 'status' => 'aberto' ];
    if ( $destino ) {
        $patch['estagio_id'] = $destino['id'];
        $patch['movido_em']  = gmdate( 'c' );
    }
    $r = tao_crm_api( "/crm_cards?id=eq.$card_id", 'PATCH', $patch );
    if ( ! $r['ok'] ) wp_send_json_error( $r['error'] );

    // Auditoria (sem disparar automações de entrar_fase — reabertura é ato administrativo)
    $nome_atual = ( $re_atual['ok'] && ! empty( $re_atual['data'] ) ) ? ( $re_atual['data'][0]['nome'] ?? '' ) : '';
    tao_crm_api( '/crm_cards_historico', 'POST', [
        'card_id'         => $card_id,
        'usuario_id'      => get_current_user_id(),
        'de_estagio_id'   => $est_atual ?: null,
        'para_estagio_id' => $destino ? $destino['id'] : ( $est_atual ?: null ),
        'motivo'          => 'Card reaberto',
        'obs'             => $destino ? ( 'Reaberto: ' . $nome_atual . ' → ' . $destino['nome'] ) : 'Reaberto na fase atual',
        'criado_em'       => gmdate( 'c' ),
    ] );
    wp_send_json_success( [ 'estagio_destino' => $destino['nome'] ?? null ] );
}

// ── Metas por atendente ───────────────────────────────────────────────────────
add_action( 'wp_ajax_tao_crm_get_metas', 'tao_crm_ajax_get_metas' );
function tao_crm_ajax_get_metas() {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Acesso negado' );
    $ws_id = sanitize_text_field( $_POST['workspace_id'] ?? '' );
    $mes   = intval( $_POST['mes'] ?? date( 'n' ) );
    $ano   = intval( $_POST['ano'] ?? date( 'Y' ) );
    $r = tao_crm_api( "/crm_metas?workspace_id=eq.$ws_id&mes=eq.$mes&ano=eq.$ano" );
    if ( ! $r['ok'] ) wp_send_json_error( $r['error'] );
    // Enriquece com realizados
    $data = [];
    foreach ( $r['data'] ?? [] as $m ) {
        $uid = intval( $m['user_id'] ?? 0 );
        $u   = $uid ? get_userdata( $uid ) : null;
        $m['nome_usuario'] = $u ? $u->display_name : "Usuário $uid";
        // Cards fechados no mês/ano por esse usuário
        $de  = sprintf( '%04d-%02d-01', $ano, $mes );
        $ate = sprintf( '%04d-%02d-01', $mes === 12 ? $ano + 1 : $ano, $mes === 12 ? 1 : $mes + 1 );
        $rc = tao_crm_api( "/crm_cards?workspace_id=eq.$ws_id&responsavel_id=eq.$uid&status=eq.fechado&criado_em=gte.$de&criado_em=lt.$ate&select=valor_oportunidade" );
        $cards_f = $rc['ok'] ? ( $rc['data'] ?? [] ) : [];
        $m['realizado_cards'] = count( $cards_f );
        $m['realizado_valor'] = array_sum( array_column( $cards_f, 'valor_oportunidade' ) );
        $data[] = $m;
    }
    wp_send_json_success( $data );
}

add_action( 'wp_ajax_tao_crm_save_meta', 'tao_crm_ajax_save_meta' );
function tao_crm_ajax_save_meta() {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Acesso negado' );
    $ws_id      = sanitize_text_field( $_POST['workspace_id'] ?? '' );
    $user_id    = intval( $_POST['user_id'] ?? 0 );
    $mes        = intval( $_POST['mes'] ?? date( 'n' ) );
    $ano        = intval( $_POST['ano'] ?? date( 'Y' ) );
    $meta_cards = intval( $_POST['meta_cards'] ?? 0 );
    $meta_valor = floatval( str_replace( ',', '.', $_POST['meta_valor'] ?? '0' ) );
    if ( ! $ws_id || ! $user_id ) wp_send_json_error( 'workspace_id e user_id obrigatórios' );
    // Upsert
    $existing = tao_crm_api( "/crm_metas?workspace_id=eq.$ws_id&user_id=eq.$user_id&mes=eq.$mes&ano=eq.$ano&limit=1" );
    $workspace_id = $ws_id;
    $data = compact( 'workspace_id', 'user_id', 'mes', 'ano', 'meta_cards', 'meta_valor' );
    if ( $existing['ok'] && ! empty( $existing['data'] ) ) {
        $meta_id = $existing['data'][0]['id'];
        $r = tao_crm_api( "/crm_metas?id=eq.$meta_id", 'PATCH', $data );
    } else {
        $r = tao_crm_api( '/crm_metas', 'POST', $data, [ 'Prefer' => 'return=representation' ] );
    }
    $r['ok'] ? wp_send_json_success() : wp_send_json_error( $r['error'] );
}

// ── Mensagens agendadas ───────────────────────────────────────────────────────
add_action( 'wp_ajax_tao_crm_save_msg_agendada', 'tao_crm_ajax_save_msg_agendada' );
function tao_crm_ajax_save_msg_agendada() {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    if ( ! function_exists( 'cbpm_can_access' ) || ! cbpm_can_access() ) wp_send_json_error( 'no access' );
    $card_id      = sanitize_text_field( $_POST['card_id']       ?? '' );
    $conteudo     = sanitize_textarea_field( $_POST['conteudo']  ?? '' );
    $agendado_str = sanitize_text_field( $_POST['agendado_para'] ?? '' );
    $para_estagio = sanitize_text_field( $_POST['para_estagio_id'] ?? '' );   // Retorno Futuro: mover card ao enviar
    if ( ! $card_id || ! $conteudo || ! $agendado_str ) wp_send_json_error( 'Campos obrigatórios' );
    // Parse datetime-local → UTC ISO
    $ts = strtotime( $agendado_str );
    if ( ! $ts || $ts <= time() ) wp_send_json_error( 'Data/hora deve ser no futuro' );
    $rc = tao_crm_api( "/crm_cards?id=eq.$card_id&select=workspace_id&limit=1" );
    $ws_id = $rc['ok'] && ! empty( $rc['data'] ) ? $rc['data'][0]['workspace_id'] : '';
    $r = tao_crm_api( '/crm_msgs_agendadas', 'POST', [
        'card_id'       => $card_id,
        'workspace_id'  => $ws_id,
        'user_id'       => get_current_user_id(),
        'conteudo'      => $conteudo,
        'agendado_para' => gmdate( 'c', $ts ),
    ], [ 'Prefer' => 'return=representation' ] );
    // Fase de destino fica em wp_options (sem migration); o cron move o card após enviar
    if ( $r['ok'] && $para_estagio && ! empty( $r['data'][0]['id'] ) ) {
        update_option( 'tao_crm_agmov_' . $r['data'][0]['id'], $para_estagio, false );
    }
    $r['ok'] ? wp_send_json_success() : wp_send_json_error( $r['error'] );
}

add_action( 'tao_crm_processar_agendadas', 'tao_crm_processar_msgs_agendadas' );
function tao_crm_processar_msgs_agendadas() {
    $agora = gmdate( 'Y-m-d\TH:i:s\Z' );
    $r = tao_crm_api( "/crm_msgs_agendadas?enviado=eq.false&agendado_para=lte.$agora&select=id,card_id,workspace_id,conteudo" );
    if ( ! $r['ok'] || empty( $r['data'] ) ) return;
    foreach ( $r['data'] as $msg ) {
        $mid    = $msg['id'];
        $cid    = $msg['card_id'];
        $ws_id  = $msg['workspace_id'];
        $texto  = $msg['conteudo'];
        // Busca dados do card para enviar via Evolution
        $rc = tao_crm_api( "/crm_cards?id=eq.$cid&select=contato_whatsapp,workspace_id,estagio_id,fechado&limit=1" );
        if ( ! $rc['ok'] || empty( $rc['data'] ) ) {
            tao_crm_api( "/crm_msgs_agendadas?id=eq.$mid", 'PATCH', [ 'enviado' => true, 'erro' => 'card não encontrado' ] );
            delete_option( 'tao_crm_agmov_' . $mid );
            continue;
        }
        $card = $rc['data'][0];
        $ok   = tao_crm_enviar_whatsapp( $card['workspace_id'], $card['contato_whatsapp'], $texto );
        if ( $ok ) {
            tao_crm_api( '/crm_mensagens', 'POST', [
                'card_id'      => $cid,
                'workspace_id' => $ws_id,
                'direcao'      => 'out',
                'tipo'         => 'text',
                'conteudo'     => $texto,
            ] );
            tao_crm_api( "/crm_msgs_agendadas?id=eq.$mid", 'PATCH', [ 'enviado' => true, 'enviado_em' => gmdate( 'c' ) ] );
            // Retorno Futuro: mensagem enviada → devolve o card ao fluxo (fase escolhida no agendamento)
            $mover = get_option( 'tao_crm_agmov_' . $mid, '' );
            if ( $mover ) {
                delete_option( 'tao_crm_agmov_' . $mid );
                if ( empty( $card['fechado'] ) && $card['estagio_id'] !== $mover ) {
                    tao_crm_api( "/crm_cards?id=eq.$cid", 'PATCH', [ 'estagio_id' => $mover, 'movido_em' => gmdate( 'c' ) ] );
                    tao_crm_api( '/crm_cards_historico', 'POST', [
                        'card_id'         => $cid,
                        'de_estagio_id'   => $card['estagio_id'],
                        'para_estagio_id' => $mover,
                        'usuario_id'      => 0,
                        'motivo'          => 'Retorno futuro: mensagem agendada enviada',
                    ] );
                    tao_crm_cancelar_fila( $cid, $card['estagio_id'] );
                    tao_crm_disparar_automacoes( $cid, $mover, 'entrar_fase', false, $ws_id );
                    tao_crm_disparar_automacoes( $cid, $mover, 'tempo_na_fase', false, $ws_id );
                }
            }
        } else {
            tao_crm_api( "/crm_msgs_agendadas?id=eq.$mid", 'PATCH', [ 'enviado' => true, 'erro' => 'falha no envio WhatsApp' ] );
            delete_option( 'tao_crm_agmov_' . $mid );
        }
    }
}

// ── Helper: enviar mensagem WhatsApp via Evolution ────────────────────────────
if ( ! function_exists( 'tao_crm_enviar_whatsapp' ) ) {
    function tao_crm_enviar_whatsapp( $workspace_id, $whatsapp, $texto ) {
        $rw = tao_crm_api( "/crm_workspaces?id=eq.$workspace_id&select=evolution_url,evolution_key,evolution_instancia&limit=1" );
        if ( ! $rw['ok'] || empty( $rw['data'] ) ) return false;
        return tao_crm_evolution_send_with_retry( $rw['data'][0], $whatsapp, $texto );
    }
}

// ─── v1.8.0: ITENS DE VENDA POR CARD ─────────────────────────────────────────

/**
 * Calcula o total de um item.
 * desconto_tipo='pct'   → total = qtd × preco × (1 - desc/100)
 * desconto_tipo='valor' → total = (qtd × preco) − desc
 */
function tao_crm_calcular_item_total( float $qtd, float $preco, string $tipo, float $desc ): float {
    $bruto = $qtd * $preco;
    if ( $tipo === 'valor' ) {
        return max( 0.0, $bruto - $desc );
    }
    // 'pct'
    $pct = max( 0.0, min( 100.0, $desc ) );
    return max( 0.0, $bruto * ( 1 - $pct / 100 ) );
}

/**
 * Recalcula valor_oportunidade do card = soma dos itens do negócio + soma dos orçamentos de fórmula.
 */
function tao_crm_sync_valor_oportunidade( string $card_id ): void {
    if ( ! $card_id ) return;
    $total = 0.0;

    // Itens do negócio (catálogo / + Item)
    $ri = tao_crm_api( "/crm_card_itens?card_id=eq.$card_id&select=total" );
    if ( $ri['ok'] ) $total += array_sum( array_column( $ri['data'] ?? [], 'total' ) );

    // Orçamentos de fórmula vinculados ao card.
    // O valor do card reflete o que foi IMPORTADO (valor_final_fc = com acréscimo/desconto do FC);
    // fallback para total_orcamento (orçamentos criados manualmente, sem valor FC).
    $ro = tao_crm_api( "/orcamentos?card_id=eq.$card_id&select=total_orcamento,valor_final_fc" );
    if ( $ro['ok'] ) {
        foreach ( $ro['data'] ?? [] as $o ) {
            $ov = floatval( $o['valor_final_fc'] ?? 0 );
            if ( $ov <= 0 ) $ov = floatval( $o['total_orcamento'] ?? 0 );
            $total += $ov;
        }
    }

    // Desconto concedido no card (R$ ou %) — subtraído do subtotal (sem limite; valor mínimo 0)
    $rd    = tao_crm_api( "/crm_cards?id=eq.$card_id&select=desconto,desconto_tipo&limit=1" );
    $dval  = 0.0; $dtipo = 'valor';
    if ( $rd['ok'] && ! empty( $rd['data'] ) ) {
        $dval  = floatval( $rd['data'][0]['desconto'] ?? 0 );
        $dtipo = $rd['data'][0]['desconto_tipo'] ?? 'valor';
    }
    $desc_reais = ( $dtipo === 'pct' ) ? ( $total * $dval / 100 ) : $dval;
    $total = max( 0, $total - $desc_reais );

    tao_crm_api( "/crm_cards?id=eq.$card_id", 'PATCH', [ 'valor_oportunidade' => round( $total, 2 ) ] );
}

/**
 * Verifica se o usuário logado tem acesso ao card (pelo workspace_id).
 * Retorna o workspace_id ou false.
 */
function tao_crm_check_card_access( string $card_id ) {
    $rc = tao_crm_api( "/crm_cards?id=eq.$card_id&select=workspace_id,responsavel_id&limit=1" );
    if ( ! $rc['ok'] || empty( $rc['data'] ) ) return false;
    $card = $rc['data'][0];
    $ws   = $card['workspace_id'] ?? '';
    if ( tao_crm_is_gestor( $ws ) ) return $ws;
    // Atendente só acessa cards atribuídos a ele
    if ( intval( $card['responsavel_id'] ?? 0 ) === get_current_user_id() ) return $ws;
    return false;
}

// ── GET produtos do catálogo TAO Neo para o workspace do card ─────────────────
// Retorna [] se o workspace não tiver cliente_id (CRM standalone sem TAO Neo).
add_action( 'wp_ajax_tao_crm_get_catalogo_para_card', function () {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    $card_id = sanitize_text_field( $_POST['card_id'] ?? '' );
    if ( ! $card_id ) wp_send_json_error( 'card_id inválido' );

    $ws = tao_crm_check_card_access( $card_id );
    if ( ! $ws ) wp_send_json_error( 'Acesso negado' );

    $rw = tao_crm_api( "/crm_workspaces?id=eq.$ws&select=cliente_id&limit=1" );
    $cliente_id = $rw['ok'] && ! empty( $rw['data'] ) ? ( $rw['data'][0]['cliente_id'] ?? '' ) : '';

    if ( ! $cliente_id ) {
        wp_send_json_success( [] ); // workspace sem TAO Neo — sem catálogo vinculado
        return;
    }

    $rc = tao_crm_api( "/catalogo?cliente_id=eq.$cliente_id&disponivel=eq.true&order=nome.asc&select=id,nome,preco,tipo&limit=500" );
    wp_send_json_success( $rc['ok'] ? ( $rc['data'] ?? [] ) : [] );
} );

// ── GET itens de um card ──────────────────────────────────────────────────────
add_action( 'wp_ajax_tao_crm_get_card_itens', function () {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    $card_id = sanitize_text_field( $_POST['card_id'] ?? '' );
    if ( ! $card_id ) wp_send_json_error( 'card_id inválido' );
    if ( ! tao_crm_check_card_access( $card_id ) ) wp_send_json_error( 'Acesso negado' );

    $r = tao_crm_api( "/crm_card_itens?card_id=eq.$card_id&order=ordem.asc,criado_em.asc" );
    $r['ok'] ? wp_send_json_success( $r['data'] ?? [] ) : wp_send_json_error( $r['error'] );
} );

// ── Análise (hub) — dataset achatado de OMs cruzando CRM + Fórmula + Caixa. Read-only, gestão.
//    Cada linha = uma OM/orçamento no período, com telefone/paciente/funil/fase/responsável,
//    forma farmacêutica, status, e (via card→venda→recibo→pagamento) a FORMA DE PAGAMENTO + valor pago.
add_action( 'wp_ajax_tao_crm_analise_dataset', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    nocache_headers();
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    $ws = sanitize_text_field( $_POST['workspace_id'] ?? '' );
    if ( ! $ws || ! tao_crm_is_gestor( $ws ) ) wp_send_json_error( 'Acesso negado' );
    $de  = sanitize_text_field( $_POST['de']  ?? '' );
    $ate = sanitize_text_field( $_POST['ate'] ?? '' );
    if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $de ) )  $de  = gmdate( 'Y-m-01' );
    if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $ate ) ) $ate = gmdate( 'Y-m-d' );

    // Cubo denormalizado: 1 linha por OM × ativo (grão fino). Medidas de ativo (qtd/
    // custo/venda calculados pelo motor) somam livres; medidas de OM (preço/pago) são
    // atribuídas 1× por OM/card — convivem sem reconciliação (há custo fixo/margem no meio).
    $rw  = tao_crm_api( "/crm_workspaces?id=eq.$ws&select=cliente_id&limit=1" );
    $cli = ( $rw['ok'] && ! empty( $rw['data'] ) ) ? ( $rw['data'][0]['cliente_id'] ?? '' ) : '';
    if ( ! $cli ) wp_send_json_success( [ 'rows' => [], 'de' => $de, 'ate' => $ate ] );

    $ate_fim = $ate . 'T23:59:59';
    $ro = tao_crm_api( "/orcamentos?cliente_id=eq.$cli&criado_em=gte.$de&criado_em=lte.$ate_fim" .
                       "&select=id,card_id,numero_orcamento,forma_nome,status,total_orcamento,criado_em,nome_paciente,itens" .
                       "&order=criado_em.desc&limit=5000" );
    $oms = ( $ro['ok'] ? ( $ro['data'] ?? [] ) : [] );
    if ( ! $oms ) wp_send_json_success( [ 'rows' => [], 'de' => $de, 'ate' => $ate ] );

    $card_ids = array_values( array_unique( array_filter( array_column( $oms, 'card_id' ) ) ) );
    $cards = [];
    if ( $card_ids ) {
        $rc = tao_crm_api( "/crm_cards?id=in.(" . implode( ',', $card_ids ) . ")&select=id,contato_whatsapp,contato_nome,pipeline_id,estagio_id,responsavel_id" );
        foreach ( ( $rc['ok'] ? ( $rc['data'] ?? [] ) : [] ) as $c ) $cards[ $c['id'] ] = $c;
    }
    $pl_map = []; $est_map = []; $pl_ispos = [];
    $rp = tao_crm_api( "/crm_pipelines?workspace_id=eq.$ws&select=id,nome" );
    foreach ( ( $rp['ok'] ? ( $rp['data'] ?? [] ) : [] ) as $p ) {
        $pl_map[ $p['id'] ]   = $p['nome'];
        $pl_ispos[ $p['id'] ] = (bool) preg_match( '/p[o\x{00F3}]s.?\s*venda|pos.?\s*venda/iu', $p['nome'] );
    }
    $re = tao_crm_api( "/crm_estagios?select=id,nome&limit=2000" );
    foreach ( ( $re['ok'] ? ( $re['data'] ?? [] ) : [] ) as $e ) $est_map[ $e['id'] ] = $e['nome'];
    $resp_map = [];
    foreach ( array_values( array_unique( array_filter( array_map( function ( $c ) { return $c['responsavel_id'] ?? 0; }, $cards ) ) ) ) as $uid ) {
        $u = get_userdata( (int) $uid ); if ( $u ) $resp_map[ $uid ] = $u->display_name;
    }

    // ── Só OMs que FECHARAM (card no funil de pós-vendas) e SÓ a última versão por
    //    requisição (numero_orcamento = prefixo-requisição-versão) — evita inflar
    //    consumo/custo somando revisões e cotações não fechadas.
    $best = [];
    foreach ( $oms as $o ) {
        $cidcard = $o['card_id'] ?? '';
        $c = $cards[ $cidcard ] ?? null;
        if ( ! $c || empty( $pl_ispos[ $c['pipeline_id'] ?? '' ] ) ) continue;   // não fechou
        $num  = (string) ( $o['numero_orcamento'] ?? '' );
        $posd = strrpos( $num, '-' );
        $base = ( $posd !== false ) ? substr( $num, 0, $posd ) : $num;
        $ver  = ( $posd !== false ) ? intval( substr( $num, $posd + 1 ) ) : 0;
        if ( ! isset( $best[ $base ] ) || $ver > $best[ $base ]['v'] ) $best[ $base ] = [ 'v' => $ver, 'o' => $o ];
    }
    $oms = array_map( function ( $x ) { return $x['o']; }, array_values( $best ) );
    if ( ! $oms ) wp_send_json_success( [ 'rows' => [], 'de' => $de, 'ate' => $ate ] );
    $card_ids = array_values( array_unique( array_filter( array_column( $oms, 'card_id' ) ) ) );
    // Caixa: card → venda → recibo → pagamento → forma
    $venda_por_card = []; $venda_ids = [];
    if ( $card_ids ) {
        $rv = tao_crm_api( "/caixa_vendas?card_id=in.(" . implode( ',', $card_ids ) . ")&select=id,card_id,valor_pago" );
        foreach ( ( $rv['ok'] ? ( $rv['data'] ?? [] ) : [] ) as $v ) { $venda_por_card[ $v['card_id'] ][] = $v; $venda_ids[] = $v['id']; }
    }
    $recibo_por_venda = []; $recibo_ids = [];
    if ( $venda_ids ) {
        $rrv = tao_crm_api( "/caixa_recibo_vendas?venda_id=in.(" . implode( ',', array_unique( $venda_ids ) ) . ")&select=venda_id,recibo_id" );
        foreach ( ( $rrv['ok'] ? ( $rrv['data'] ?? [] ) : [] ) as $r ) { $recibo_por_venda[ $r['venda_id'] ][] = $r['recibo_id']; $recibo_ids[] = $r['recibo_id']; }
    }
    $forma_por_recibo = [];
    if ( $recibo_ids ) {
        $rpg  = tao_crm_api( "/caixa_pagamentos?recibo_id=in.(" . implode( ',', array_unique( $recibo_ids ) ) . ")&select=recibo_id,forma_pagamento_id,bandeira" );
        $pags = ( $rpg['ok'] ? ( $rpg['data'] ?? [] ) : [] );
        $fids = array_values( array_unique( array_filter( array_column( $pags, 'forma_pagamento_id' ) ) ) );
        $fnome = [];
        if ( $fids ) {
            $rf = tao_crm_api( "/caixa_formas_pagamento?id=in.(" . implode( ',', $fids ) . ")&select=id,nome" );
            foreach ( ( $rf['ok'] ? ( $rf['data'] ?? [] ) : [] ) as $f ) $fnome[ $f['id'] ] = $f['nome'];
        }
        foreach ( $pags as $p ) {
            $nome = $fnome[ $p['forma_pagamento_id'] ?? '' ] ?? '';
            $band = $p['bandeira'] ?? '';
            if ( $nome ) $forma_por_recibo[ $p['recibo_id'] ][ trim( $nome . ' ' . $band ) ] = 1;
        }
    }

    // ── Lote (rastreabilidade TAO Lab): OM → lab_ordens → lab_ordem_itens → lab_lotes_mp.
    //    Tenant-safe: parte de orcamento_id IN (OMs deste cliente). Hoje 0/221 itens têm
    //    lote gravado → coluna nasce "—" e acende sozinha quando a pesagem registrar lote.
    $om_ids = array_values( array_unique( array_filter( array_column( $oms, 'id' ) ) ) );
    $lote_por_om_at = [];
    if ( $om_ids ) {
        $rlo = tao_crm_api( "/lab_ordens?orcamento_id=in.(" . implode( ',', $om_ids ) . ")&select=id,orcamento_id" );
        $ordem2om = [];
        foreach ( ( $rlo['ok'] ? ( $rlo['data'] ?? [] ) : [] ) as $lo ) $ordem2om[ $lo['id'] ] = $lo['orcamento_id'];
        if ( $ordem2om ) {
            $rli = tao_crm_api( "/lab_ordem_itens?ordem_id=in.(" . implode( ',', array_keys( $ordem2om ) ) . ")&lote_mp_id=not.is.null&select=ordem_id,ativo_id,lote_mp_id" );
            $lis = ( $rli['ok'] ? ( $rli['data'] ?? [] ) : [] );
            $lote_ids = array_values( array_unique( array_filter( array_column( $lis, 'lote_mp_id' ) ) ) );
            $lote_nr = [];
            if ( $lote_ids ) {
                $rll = tao_crm_api( "/lab_lotes_mp?id=in.(" . implode( ',', $lote_ids ) . ")&select=id,nr_lote,lote_interno" );
                foreach ( ( $rll['ok'] ? ( $rll['data'] ?? [] ) : [] ) as $l ) $lote_nr[ $l['id'] ] = ( $l['nr_lote'] ?: ( $l['lote_interno'] ?: '' ) );
            }
            foreach ( $lis as $li ) {
                $omid = $ordem2om[ $li['ordem_id'] ] ?? '';
                $nr   = $lote_nr[ $li['lote_mp_id'] ] ?? '';
                if ( $omid && ! empty( $li['ativo_id'] ) && $nr ) $lote_por_om_at[ $omid ][ $li['ativo_id'] ] = $nr;
            }
        }
    }

    // ── Cubo: 1 linha por OM × ativo. Medidas de ativo = valores calculados pelo motor
    //    (subtotal = custo real c/ potes; preco_venda = venda). Medidas de OM (preço da OM
    //    e pago) atribuídas 1× por OM/card p/ não duplicar ao explodir em ativos.
    $rows = [];
    $card_pago_visto = [];
    foreach ( $oms as $o ) {
        $c     = $cards[ $o['card_id'] ?? '' ] ?? [];
        $data  = substr( (string) ( $o['criado_em'] ?? '' ), 0, 10 );
        $its   = is_string( $o['itens'] ?? null ) ? json_decode( $o['itens'], true ) : ( $o['itens'] ?? [] );
        if ( ! is_array( $its ) ) $its = [];
        $omid  = $o['id'] ?? '';
        $tel   = $c['contato_whatsapp'] ?? '';
        $pac   = $o['nome_paciente'] ?: ( $c['contato_nome'] ?? '' );
        $resp  = $resp_map[ $c['responsavel_id'] ?? 0 ] ?? '';
        $cid_card = $o['card_id'] ?? '';

        // forma de pagamento do card (via Caixa)
        $formas = [];
        foreach ( $venda_por_card[ $cid_card ] ?? [] as $v )
            foreach ( $recibo_por_venda[ $v['id'] ] ?? [] as $rid )
                foreach ( array_keys( $forma_por_recibo[ $rid ] ?? [] ) as $fk ) $formas[ $fk ] = 1;
        $forma_pg = $formas ? implode( ' + ', array_keys( $formas ) ) : '—';

        // medidas de OM (1× por OM / 1× por card)
        $orcado = (float) ( $o['total_orcamento'] ?? 0 );
        $vpago  = 0;
        if ( $cid_card && empty( $card_pago_visto[ $cid_card ] ) ) {
            foreach ( $venda_por_card[ $cid_card ] ?? [] as $v ) $vpago += (float) ( $v['valor_pago'] ?? 0 );
            $card_pago_visto[ $cid_card ] = 1;
        }

        // dimensões da OM (repetidas em cada linha de ativo)
        $dim = [
            'OM'            => $o['numero_orcamento'] ?? '',
            'Data'          => $data,
            'Mes'           => substr( $data, 0, 7 ),
            'Telefone'      => $tel,
            'Paciente'      => $pac,
            'Forma Farmac.' => $o['forma_nome'] ?? '',
            'Status'        => $o['status'] ?? '',
            'Funil'         => $pl_map[ $c['pipeline_id'] ?? '' ] ?? '',
            'Fase'          => $est_map[ $c['estagio_id'] ?? '' ] ?? '',
            'Responsavel'   => $resp,
            'Forma Pagto'   => $forma_pg,
        ];

        $primeiro = true; $tem_ativo = false;
        foreach ( $its as $it ) {
            if ( ( $it['tipo'] ?? 'mp' ) !== 'mp' ) continue;
            $nome_at = $it['nome'] ?: ( $it['nome_prescricao'] ?? '' );
            if ( ! $nome_at || strtoupper( trim( $nome_at ) ) === 'EXCIPIENTE BASE' ) continue;
            $tem_ativo = true;
            $rows[] = array_merge( $dim, [
                'Ativo'               => $nome_at,
                'Lote'                => $lote_por_om_at[ $omid ][ $it['ativo_id'] ?? '' ] ?? '—',
                'Qtd (g)'             => round( (float) ( $it['qtd_total_g'] ?? 0 ), 4 ),
                'Custo Ativo (R$)'    => round( (float) ( $it['subtotal'] ?? 0 ), 2 ),
                'Venda Ativo (R$)'    => round( (float) ( $it['preco_venda'] ?? 0 ), 2 ),
                'Preço Venda OM (R$)' => $primeiro ? round( $orcado, 2 ) : 0,
                'Valor Pago (R$)'     => $primeiro ? round( $vpago, 2 ) : 0,
            ] );
            $primeiro = false;
        }
        if ( ! $tem_ativo ) {   // OM sem ativos (só embalagem/serviço): mantém a OM no cubo
            $rows[] = array_merge( $dim, [
                'Ativo'               => '—',
                'Lote'                => '—',
                'Qtd (g)'             => 0,
                'Custo Ativo (R$)'    => 0,
                'Venda Ativo (R$)'    => 0,
                'Preço Venda OM (R$)' => round( $orcado, 2 ),
                'Valor Pago (R$)'     => round( $vpago, 2 ),
            ] );
        }
    }
    wp_send_json_success( [ 'rows' => $rows, 'de' => $de, 'ate' => $ate ] );
} );

// ── Dataset FINANCEIRO (fonte: Caixa). Faturado = caixa_vendas.valor_total por data de
//    FECHAMENTO (venda.criado_em). Recebido = caixa_pagamentos.valor_bruto por data de
//    RECEBIMENTO (pagamento.criado_em), não estornados. Tenant-scoped, restrito a gestão.
add_action( 'wp_ajax_tao_crm_financeiro_dataset', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    nocache_headers();
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    $ws = sanitize_text_field( $_POST['workspace_id'] ?? '' );
    if ( ! $ws || ! tao_crm_is_gestor( $ws ) ) wp_send_json_error( 'Acesso negado' );
    $de  = sanitize_text_field( $_POST['de']  ?? '' );
    $ate = sanitize_text_field( $_POST['ate'] ?? '' );
    if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $de ) )  $de  = gmdate( 'Y-m-01' );
    if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $ate ) ) $ate = gmdate( 'Y-m-d' );
    $ate_fim = $ate . 'T23:59:59';

    $rw  = tao_crm_api( "/crm_workspaces?id=eq.$ws&select=cliente_id&limit=1" );
    $cli = ( $rw['ok'] && ! empty( $rw['data'] ) ) ? ( $rw['data'][0]['cliente_id'] ?? '' ) : '';
    if ( ! $cli ) wp_send_json_success( [ 'vendas' => [], 'pagamentos' => [], 'de' => $de, 'ate' => $ate ] );

    // vendas do período (por FECHAMENTO)
    $rv = tao_crm_api( "/caixa_vendas?cliente_id=eq.$cli&criado_em=gte.$de&criado_em=lte.$ate_fim" .
                       "&select=id,card_id,valor_total,valor_pago,status,criado_em&order=criado_em.desc&limit=20000" );
    $vendas = ( $rv['ok'] ? ( $rv['data'] ?? [] ) : [] );

    // pagamentos do período (por RECEBIMENTO), não estornados
    $rpg = tao_crm_api( "/caixa_pagamentos?cliente_id=eq.$cli&criado_em=gte.$de&criado_em=lte.$ate_fim&estornado=eq.false" .
                        "&select=id,recibo_id,forma_pagamento_id,bandeira,modalidade,valor_bruto,valor_liquido,criado_em&order=criado_em.desc&limit=40000" );
    $pags = ( $rpg['ok'] ? ( $rpg['data'] ?? [] ) : [] );

    $fids = array_values( array_unique( array_filter( array_column( $pags, 'forma_pagamento_id' ) ) ) );
    $fnome = [];
    if ( $fids ) {
        $rf = tao_crm_api( "/caixa_formas_pagamento?id=in.(" . implode( ',', $fids ) . ")&select=id,nome" );
        foreach ( ( $rf['ok'] ? ( $rf['data'] ?? [] ) : [] ) as $f ) $fnome[ $f['id'] ] = $f['nome'];
    }

    // responsável: card → user. Para pagamentos: recibo → venda → card.
    $card_por_venda = []; $all_cards = [];
    foreach ( $vendas as $v ) { $card_por_venda[ $v['id'] ] = $v['card_id'] ?? ''; if ( ! empty( $v['card_id'] ) ) $all_cards[] = $v['card_id']; }
    $recibo_ids = array_values( array_unique( array_filter( array_column( $pags, 'recibo_id' ) ) ) );
    $venda_por_recibo = [];
    foreach ( array_chunk( $recibo_ids, 100 ) as $ch ) {
        if ( ! $ch ) continue;
        $rrv = tao_crm_api( "/caixa_recibo_vendas?recibo_id=in.(" . implode( ',', $ch ) . ")&select=recibo_id,venda_id" );
        foreach ( ( $rrv['ok'] ? ( $rrv['data'] ?? [] ) : [] ) as $r ) $venda_por_recibo[ $r['recibo_id'] ] = $r['venda_id'];
    }
    $faltam = array_values( array_diff( array_values( array_unique( array_filter( array_values( $venda_por_recibo ) ) ) ), array_keys( $card_por_venda ) ) );
    foreach ( array_chunk( $faltam, 100 ) as $ch ) {
        if ( ! $ch ) continue;
        $rv2 = tao_crm_api( "/caixa_vendas?id=in.(" . implode( ',', $ch ) . ")&select=id,card_id" );
        foreach ( ( $rv2['ok'] ? ( $rv2['data'] ?? [] ) : [] ) as $v ) { $card_por_venda[ $v['id'] ] = $v['card_id'] ?? ''; if ( ! empty( $v['card_id'] ) ) $all_cards[] = $v['card_id']; }
    }
    $resp_por_card = [];
    foreach ( array_chunk( array_values( array_unique( array_filter( $all_cards ) ) ), 100 ) as $ch ) {
        if ( ! $ch ) continue;
        $rc = tao_crm_api( "/crm_cards?id=in.(" . implode( ',', $ch ) . ")&select=id,responsavel_id" );
        foreach ( ( $rc['ok'] ? ( $rc['data'] ?? [] ) : [] ) as $c ) $resp_por_card[ $c['id'] ] = $c['responsavel_id'] ?? 0;
    }
    $resp_nome = [];
    foreach ( array_values( array_unique( array_filter( $resp_por_card ) ) ) as $uid ) { $u = get_userdata( (int) $uid ); if ( $u ) $resp_nome[ $uid ] = $u->display_name; }
    $resp_de_card = function ( $cid ) use ( $resp_por_card, $resp_nome ) {
        return $resp_nome[ $resp_por_card[ $cid ] ?? 0 ] ?? '— sem resp —';
    };

    $out_v = [];
    foreach ( $vendas as $v ) {
        $data = substr( (string) ( $v['criado_em'] ?? '' ), 0, 10 );
        $out_v[] = [
            'Data'        => $data,
            'Mes'         => substr( $data, 0, 7 ),
            'Responsavel' => $resp_de_card( $v['card_id'] ?? '' ),
            'Status'      => $v['status'] ?? '',
            'ValorTotal'  => (float) ( $v['valor_total'] ?? 0 ),
            'ValorPago'   => (float) ( $v['valor_pago'] ?? 0 ),
        ];
    }
    $out_p = [];
    foreach ( $pags as $p ) {
        $data  = substr( (string) ( $p['criado_em'] ?? '' ), 0, 10 );
        $vid   = $venda_por_recibo[ $p['recibo_id'] ?? '' ] ?? '';
        $cid   = $card_por_venda[ $vid ] ?? '';
        $forma = trim( ( $fnome[ $p['forma_pagamento_id'] ?? '' ] ?? '' ) . ' ' . ( $p['bandeira'] ?? '' ) );
        $out_p[] = [
            'Data'        => $data,
            'Mes'         => substr( $data, 0, 7 ),
            'Forma'       => $forma !== '' ? $forma : ( $p['modalidade'] ?? '—' ),
            'Responsavel' => $cid ? $resp_de_card( $cid ) : '— sem resp —',
            'Bruto'       => (float) ( $p['valor_bruto'] ?? 0 ),
            'Liquido'     => (float) ( $p['valor_liquido'] ?? 0 ),
        ];
    }
    wp_send_json_success( [ 'vendas' => $out_v, 'pagamentos' => $out_p, 'de' => $de, 'ate' => $ate ] );
} );

// ── Dataset de OPERAÇÃO/ATENDIMENTO (grão do CARD): ganho×perda pelo funil, status,
//    TMR (1ª resposta) e fila de espera via crm_mensagens. Tenant-scoped, restrito a gestão.
//    GANHO = card no funil de Pós-vendas (mesmo card cruza os funis). PERDA = perdido/cancelado.
add_action( 'wp_ajax_tao_crm_operacao_dataset', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    nocache_headers();
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    $ws = sanitize_text_field( $_POST['workspace_id'] ?? '' );
    if ( ! $ws || ! tao_crm_is_gestor( $ws ) ) wp_send_json_error( 'Acesso negado' );
    $de  = sanitize_text_field( $_POST['de']  ?? '' );
    $ate = sanitize_text_field( $_POST['ate'] ?? '' );
    if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $de ) )  $de  = gmdate( 'Y-m-01' );
    if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $ate ) ) $ate = gmdate( 'Y-m-d' );
    $ate_fim = $ate . 'T23:59:59';

    // funis + fases do workspace (pós-vendas identificado pelo nome do funil)
    $pl_map = []; $pl_ispos = [];
    $rp = tao_crm_api( "/crm_pipelines?workspace_id=eq.$ws&select=id,nome" );
    foreach ( ( $rp['ok'] ? ( $rp['data'] ?? [] ) : [] ) as $p ) {
        $pl_map[ $p['id'] ]   = $p['nome'];
        $pl_ispos[ $p['id'] ] = (bool) preg_match( '/p[o\x{00F3}]s.?\s*venda|pos.?\s*venda/iu', $p['nome'] );
    }
    $est_map = []; $est_ispos = [];
    $re = tao_crm_api( "/crm_estagios?select=id,nome,pipeline_id&limit=2000" );
    foreach ( ( $re['ok'] ? ( $re['data'] ?? [] ) : [] ) as $e ) {
        $est_map[ $e['id'] ]   = $e['nome'];
        $est_ispos[ $e['id'] ] = ! empty( $pl_ispos[ $e['pipeline_id'] ?? '' ] );
    }
    // Remetentes que NÃO são atendente humano (bot/disparo) — p/ o TMR contar só a
    // resposta da EQUIPE. Humano = display_name do WP; bot = 'Automação'; disparo =
    // nome/evolution_instancia da instância.
    $inst_excl = [ 'Automação' => 1, 'Automacao' => 1 ];
    $ri = tao_crm_api( "/crm_instancias?workspace_id=eq.$ws&select=nome,evolution_instancia" );
    foreach ( ( $ri['ok'] ? ( $ri['data'] ?? [] ) : [] ) as $i ) {
        if ( ! empty( $i['evolution_instancia'] ) ) $inst_excl[ $i['evolution_instancia'] ] = 1;
        if ( ! empty( $i['nome'] ) )                 $inst_excl[ $i['nome'] ] = 1;
    }

    // cards do período (coorte por criação)
    $rc = tao_crm_api( "/crm_cards?workspace_id=eq.$ws&criado_em=gte.$de&criado_em=lte.$ate_fim" .
                       "&select=id,titulo,contato_nome,status,fechado,pipeline_id,estagio_id,responsavel_id,valor_oportunidade,criado_em,movido_em,ultima_mensagem_em&order=criado_em.desc&limit=5000" );
    $cards = ( $rc['ok'] ? ( $rc['data'] ?? [] ) : [] );
    if ( ! $cards ) wp_send_json_success( [ 'rows' => [], 'de' => $de, 'ate' => $ate, 'agora' => gmdate( 'c' ) ] );

    $resp_map = [];
    foreach ( array_values( array_unique( array_filter( array_column( $cards, 'responsavel_id' ) ) ) ) as $uid ) {
        $u = get_userdata( (int) $uid ); if ( $u ) $resp_map[ $uid ] = $u->display_name;
    }

    // mensagens em lotes → TMR (1ª entrada → 1ª resposta do ATENDENTE humano) e espera
    $card_ids = array_column( $cards, 'id' );
    $tmr_card = []; $espera_card = [];
    $now = time();
    foreach ( array_chunk( $card_ids, 100 ) as $chunk ) {
        $rm = tao_crm_api( "/crm_mensagens?card_id=in.(" . implode( ',', $chunk ) . ")&direcao=in.(in,out)" .
                           "&select=card_id,direcao,enviado_em,remetente_nome&order=enviado_em.asc&limit=50000" );
        $por = [];
        foreach ( ( $rm['ok'] ? ( $rm['data'] ?? [] ) : [] ) as $m ) $por[ $m['card_id'] ][] = $m;
        foreach ( $por as $cid => $ms ) {
            $t_in = null;
            foreach ( $ms as $m ) {
                $ts = strtotime( $m['enviado_em'] );
                if ( $m['direcao'] === 'in' && $t_in === null ) { $t_in = $ts; }
                elseif ( $m['direcao'] === 'out' && $t_in !== null && empty( $inst_excl[ $m['remetente_nome'] ?? '' ] ) ) {
                    $tmr_card[ $cid ] = max( 0, $ts - $t_in ); break;   // só conta resposta humana
                }
            }
            $last = end( $ms );
            if ( $last && $last['direcao'] === 'in' ) $espera_card[ $cid ] = max( 0, $now - strtotime( $last['enviado_em'] ) );
        }
    }

    // TMA exato via histórico: criação → 1ª transição de RESOLUÇÃO (entrou em pós-vendas
    // = ganho, ou foi cancelado = perda). Sem migration; funciona retroativo.
    $resol_card = [];
    foreach ( array_chunk( $card_ids, 100 ) as $chunk ) {
        $rh = tao_crm_api( "/crm_cards_historico?card_id=in.(" . implode( ',', $chunk ) . ")" .
                           "&select=card_id,para_estagio_id,criado_em&order=criado_em.asc&limit=50000" );
        foreach ( ( $rh['ok'] ? ( $rh['data'] ?? [] ) : [] ) as $h ) {
            $cid = $h['card_id']; if ( isset( $resol_card[ $cid ] ) ) continue;
            $pe  = $h['para_estagio_id'] ?? '';
            if ( ! empty( $est_ispos[ $pe ] ) || stripos( $est_map[ $pe ] ?? '', 'cancelad' ) !== false )
                $resol_card[ $cid ] = strtotime( $h['criado_em'] );
        }
    }

    $rows = [];
    foreach ( $cards as $c ) {
        $pid   = $c['pipeline_id'] ?? '';
        $ispos = $pl_ispos[ $pid ] ?? false;
        $fase  = $est_map[ $c['estagio_id'] ?? '' ] ?? '';
        $stat  = $c['status'] ?? '';
        if ( $ispos ) $classe = 'Ganho';
        elseif ( $stat === 'perdido' || stripos( $fase, 'cancelad' ) !== false ) $classe = 'Perda';
        else $classe = 'Em andamento';
        $data = substr( (string) ( $c['criado_em'] ?? '' ), 0, 10 );
        $cid  = $c['id'];
        $esperando = isset( $espera_card[ $cid ] ) && empty( $c['fechado'] );
        $tma_h = ( isset( $resol_card[ $cid ] ) && ! empty( $c['criado_em'] ) )
            ? round( max( 0, $resol_card[ $cid ] - strtotime( $c['criado_em'] ) ) / 3600, 1 ) : null;
        $rows[] = [
            'Card'         => $c['titulo'] ?: ( $c['contato_nome'] ?? '' ),
            'Responsavel'  => $resp_map[ $c['responsavel_id'] ?? 0 ] ?? '— sem resp —',
            'Funil'        => $ispos ? ( $pl_map[ $pid ] ?? 'Pós-vendas' ) : ( $pl_map[ $pid ] ?? 'Vendas' ),
            'Fase'         => $fase,
            'Classe'       => $classe,
            'Status'       => $stat,
            'Data'         => $data,
            'Mes'          => substr( $data, 0, 7 ),
            'Valor'        => (float) ( $c['valor_oportunidade'] ?? 0 ),
            'TMR (min)'    => isset( $tmr_card[ $cid ] )    ? round( $tmr_card[ $cid ] / 60, 1 )    : null,
            'TMA (h)'      => $tma_h,
            'Espera (min)' => $esperando                    ? round( $espera_card[ $cid ] / 60, 1 ) : null,
            'Esperando'    => $esperando ? 1 : 0,
        ];
    }
    wp_send_json_success( [ 'rows' => $rows, 'de' => $de, 'ate' => $ate, 'agora' => gmdate( 'c' ) ] );
} );

// ── Busca de contato por nome OU WhatsApp (autocomplete do Novo Card). Read-only.
add_action( 'wp_ajax_tao_crm_contato_busca', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    $ws = sanitize_text_field( $_POST['workspace_id'] ?? ( $_GET['workspace_id'] ?? '' ) );
    $q  = trim( sanitize_text_field( $_POST['q'] ?? ( $_GET['q'] ?? '' ) ) );
    if ( ! $ws || mb_strlen( $q ) < 2 ) { wp_send_json_success( [] ); }
    $enc  = rawurlencode( $q );
    $base = "/crm_contatos?workspace_id=eq.$ws&nome=ilike.*{$enc}*" .
            "&select=id,nome,whatsapp&order=nome.asc&limit=10";
    $r = tao_crm_api( $base . '&anonimizado=eq.false' );
    if ( ! $r['ok'] ) $r = tao_crm_api( $base );   // fallback se a coluna não existir
    wp_send_json_success( $r['ok'] ? ( $r['data'] ?? [] ) : [] );
} );

// ── Histórico de atendimento do cliente: cards anteriores × data, com seus itens
//    e orçamentos, para consulta e repetição a partir do card. Read-only.
add_action( 'wp_ajax_tao_crm_hist_atendimento', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    $card_id = sanitize_text_field( $_POST['card_id'] ?? '' );
    if ( ! $card_id ) wp_send_json_error( 'card_id inválido' );
    $ws = tao_crm_check_card_access( $card_id );
    if ( ! $ws ) wp_send_json_error( 'Acesso negado' );

    $rc = tao_crm_api( "/crm_cards?id=eq.$card_id&select=contato_whatsapp,workspace_id&limit=1" );
    if ( ! $rc['ok'] || empty( $rc['data'] ) ) wp_send_json_error( 'Card não encontrado' );
    $wa = $rc['data'][0]['contato_whatsapp'] ?? '';
    if ( ! $wa ) wp_send_json_success( [ 'cards' => [] ] );

    $rk = tao_crm_api( "/crm_cards?contato_whatsapp=eq.$wa&workspace_id=eq.$ws&id=neq.$card_id" .
                       "&select=id,titulo,contato_nome,status,fechado,criado_em,pipeline_id,valor_oportunidade" .
                       "&order=criado_em.desc&limit=60" );
    $cards = ( $rk['ok'] ? ( $rk['data'] ?? [] ) : [] );
    if ( ! $cards ) wp_send_json_success( [ 'cards' => [] ] );

    $ids = implode( ',', array_map( function ( $c ) { return $c['id']; }, $cards ) );

    $ri = tao_crm_api( "/crm_card_itens?card_id=in.($ids)&select=id,card_id,descricao,quantidade,preco_unitario,desconto_tipo,desconto_valor,total,catalogo_id,ordem&order=ordem.asc" );
    $itens_por_card = [];
    foreach ( ( $ri['ok'] ? ( $ri['data'] ?? [] ) : [] ) as $it ) $itens_por_card[ $it['card_id'] ][] = $it;

    $ro = tao_crm_api( "/orcamentos?card_id=in.($ids)&select=id,card_id,numero_orcamento,forma_nome,forma_vol,forma_unidade,qtde_potes,total_orcamento,status,criado_em&order=criado_em.asc" );
    $orcs_por_card = [];
    foreach ( ( $ro['ok'] ? ( $ro['data'] ?? [] ) : [] ) as $o ) $orcs_por_card[ $o['card_id'] ][] = $o;

    $pl_map = [];
    $rp = tao_crm_api( "/crm_pipelines?workspace_id=eq.$ws&select=id,nome" );
    foreach ( ( $rp['ok'] ? ( $rp['data'] ?? [] ) : [] ) as $p ) $pl_map[ $p['id'] ] = $p['nome'];

    $out = [];
    foreach ( $cards as $c ) {
        $its  = $itens_por_card[ $c['id'] ] ?? [];
        $orcs = $orcs_por_card[ $c['id'] ] ?? [];
        if ( ! $its && ! $orcs ) continue;   // só cards com algo repetível
        $out[] = [
            'id'         => $c['id'],
            'titulo'     => $c['titulo'] ?: ( $c['contato_nome'] ?? '' ),
            'data'       => $c['criado_em'],
            'status'     => ! empty( $c['fechado'] ) ? ( $c['status'] ?? 'fechado' ) : 'aberto',
            'pipeline'   => $pl_map[ $c['pipeline_id'] ] ?? '',
            'valor'      => $c['valor_oportunidade'],
            'itens'      => $its,
            'orcamentos' => $orcs,
        ];
    }
    wp_send_json_success( [ 'cards' => $out ] );
} );

// ── SAVE (insert ou update) de item ──────────────────────────────────────────
add_action( 'wp_ajax_tao_crm_save_card_item', function () {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );

    $card_id       = sanitize_text_field( $_POST['card_id']       ?? '' );
    $item_id       = sanitize_text_field( $_POST['item_id']       ?? '' ); // vazio = novo
    $catalogo_id   = sanitize_text_field( $_POST['catalogo_id']   ?? '' ); // opcional — FK para catalogo do TAO Neo
    $descricao     = sanitize_text_field( $_POST['descricao']     ?? '' );
    $quantidade    = max( 0.001, floatval( $_POST['quantidade']   ?? 1 ) );
    $preco         = max( 0.0,   floatval( $_POST['preco_unitario'] ?? 0 ) );
    $desc_tipo     = in_array( $_POST['desconto_tipo'] ?? '', [ 'pct', 'valor' ] )
                        ? sanitize_text_field( $_POST['desconto_tipo'] )
                        : 'pct';
    $desc_valor    = max( 0.0, floatval( $_POST['desconto_valor'] ?? 0 ) );
    $ordem         = intval( $_POST['ordem'] ?? 0 );

    if ( ! $card_id || ! $descricao ) wp_send_json_error( 'Dados obrigatórios ausentes' );

    $ws = tao_crm_check_card_access( $card_id );
    if ( ! $ws ) wp_send_json_error( 'Acesso negado' );

    $total = tao_crm_calcular_item_total( $quantidade, $preco, $desc_tipo, $desc_valor );

    $payload = [
        'card_id'        => $card_id,
        'workspace_id'   => $ws,
        'catalogo_id'    => $catalogo_id ?: null, // null = entrada manual
        'descricao'      => $descricao,
        'quantidade'     => $quantidade,
        'preco_unitario' => $preco,
        'desconto_tipo'  => $desc_tipo,
        'desconto_valor' => $desc_valor,
        'total'          => $total,
        'ordem'          => $ordem,
        'atualizado_em'  => gmdate( 'c' ),
    ];

    if ( $item_id ) {
        $r = tao_crm_api( "/crm_card_itens?id=eq.$item_id&card_id=eq.$card_id", 'PATCH', $payload,
                          [ 'Prefer' => 'return=representation' ] );
    } else {
        $r = tao_crm_api( '/crm_card_itens', 'POST', $payload,
                          [ 'Prefer' => 'return=representation' ] );
    }

    if ( ! $r['ok'] ) wp_send_json_error( $r['error'] );

    tao_crm_sync_valor_oportunidade( $card_id );
    wp_send_json_success( $r['data'][0] ?? [] );
} );

// ── DELETE item ───────────────────────────────────────────────────────────────
add_action( 'wp_ajax_tao_crm_delete_card_item', function () {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );

    $card_id = sanitize_text_field( $_POST['card_id'] ?? '' );
    $item_id = sanitize_text_field( $_POST['item_id'] ?? '' );

    if ( ! $card_id || ! $item_id ) wp_send_json_error( 'Dados inválidos' );
    if ( ! tao_crm_check_card_access( $card_id ) ) wp_send_json_error( 'Acesso negado' );

    $r = tao_crm_api( "/crm_card_itens?id=eq.$item_id&card_id=eq.$card_id", 'DELETE' );
    if ( ! $r['ok'] ) wp_send_json_error( $r['error'] );

    tao_crm_sync_valor_oportunidade( $card_id );
    wp_send_json_success();
} );

// ─── v1.8.0: CAMPO TIPO ARQUIVO — UPLOAD PARA SUPABASE STORAGE ───────────────

add_action( 'wp_ajax_tao_crm_upload_campo_arquivo', 'tao_crm_ajax_upload_campo_arquivo' );
function tao_crm_ajax_upload_campo_arquivo() {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );

    $card_id  = sanitize_text_field( $_POST['card_id']  ?? '' );
    $campo_id = sanitize_text_field( $_POST['campo_id'] ?? '' );

    if ( ! $card_id || ! $campo_id ) wp_send_json_error( 'Dados inválidos' );
    if ( empty( $_FILES['arquivo'] ) ) wp_send_json_error( 'Nenhum arquivo recebido' );

    $ws = tao_crm_check_card_access( $card_id );
    if ( ! $ws ) wp_send_json_error( 'Acesso negado' );

    $file = $_FILES['arquivo'];
    if ( $file['error'] !== UPLOAD_ERR_OK ) wp_send_json_error( 'Erro no upload: código ' . $file['error'] );
    if ( $file['size'] > 20 * 1024 * 1024 ) wp_send_json_error( 'Arquivo muito grande (máx 20 MB)' );

    $sb_url = get_option( 'tao_crm_supabase_url', '' );
    $sb_key = get_option( 'tao_crm_supabase_key', '' );
    if ( ! $sb_url || ! $sb_key ) wp_send_json_error( 'Supabase não configurado' );

    $ext      = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
    $safe_ext = preg_replace( '/[^a-z0-9]/', '', $ext );
    $filename = sanitize_file_name( pathinfo( $file['name'], PATHINFO_FILENAME ) );
    $path     = "$ws/$card_id/$campo_id/{$filename}_" . time() . ( $safe_ext ? ".$safe_ext" : '' );
    $bucket   = 'tao-crm-campos';
    $mime     = mime_content_type( $file['tmp_name'] ) ?: ( $file['type'] ?: 'application/octet-stream' );

    $upload_url = rtrim( $sb_url, '/' ) . "/storage/v1/object/$bucket/$path";
    $body       = file_get_contents( $file['tmp_name'] );

    $resp = wp_remote_request( $upload_url, [
        'method'  => 'POST',
        'headers' => [
            'Authorization' => 'Bearer ' . $sb_key,
            'Content-Type'  => $mime,
            'x-upsert'      => 'true',
        ],
        'body'    => $body,
        'timeout' => 30,
    ] );

    if ( is_wp_error( $resp ) ) wp_send_json_error( $resp->get_error_message() );
    $code = wp_remote_retrieve_response_code( $resp );
    if ( $code < 200 || $code >= 300 ) {
        wp_send_json_error( 'Supabase Storage erro ' . $code . ': ' . wp_remote_retrieve_body( $resp ) );
    }

    // URL de acesso via REST (service_role — uso interno, nunca exposto ao cliente)
    $file_url = rtrim( $sb_url, '/' ) . "/storage/v1/object/authenticated/$bucket/$path";

    // Salva (upsert) em crm_cards_valores
    $rv = tao_crm_api( "/crm_cards_valores?card_id=eq.$card_id&campo_id=eq.$campo_id", 'GET' );
    if ( $rv['ok'] && ! empty( $rv['data'] ) ) {
        tao_crm_api( "/crm_cards_valores?card_id=eq.$card_id&campo_id=eq.$campo_id", 'PATCH',
                     [ 'valor' => $file_url, 'campo_nome' => $file['name'] ] );
    } else {
        tao_crm_api( '/crm_cards_valores', 'POST', [
            'card_id'    => $card_id,
            'campo_id'   => $campo_id,
            'valor'      => $file_url,
            'campo_nome' => $file['name'],
        ] );
    }

    wp_send_json_success( [
        'url'      => $file_url,
        'filename' => $file['name'],
        'size'     => $file['size'],
        'mime'     => $mime,
        'stored'   => 'STORAGE:' . $path . ':' . $file['name'],
    ] );
}

// ── Download de arquivo do campo (gera URL assinada no Supabase e redireciona) ─
add_action( 'wp_ajax_tao_crm_download_campo_arquivo', 'tao_crm_ajax_download_campo_arquivo' );
function tao_crm_ajax_download_campo_arquivo() {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );

    $card_id  = sanitize_text_field( $_GET['card_id']  ?? '' );
    $campo_id = sanitize_text_field( $_GET['campo_id'] ?? '' );
    if ( ! $card_id || ! $campo_id ) wp_die( 'Dados inválidos', 400 );
    if ( ! tao_crm_check_card_access( $card_id ) ) wp_die( 'Acesso negado', 403 );

    $rv = tao_crm_api( "/crm_cards_valores?card_id=eq.$card_id&campo_id=eq.$campo_id&limit=1" );
    if ( ! $rv['ok'] || empty( $rv['data'] ) ) wp_die( 'Arquivo não encontrado', 404 );

    $val = $rv['data'][0]['valor'] ?? '';
    if ( ! str_starts_with( $val, 'STORAGE:' ) ) wp_die( 'Arquivo inválido', 400 );

    $parts  = explode( ':', $val, 3 );
    $path   = $parts[1] ?? '';
    $bucket = 'tao-crm-campos';

    $sb_url = get_option( 'tao_crm_supabase_url', '' );
    $sb_key = get_option( 'tao_crm_supabase_key', '' );
    if ( ! $sb_url || ! $sb_key || ! $path ) wp_die( 'Configuração ausente', 500 );

    // Solicita URL assinada (1 hora)
    $sign_url = rtrim( $sb_url, '/' ) . "/storage/v1/object/sign/$bucket/$path";
    $resp = wp_remote_post( $sign_url, [
        'headers' => [
            'Authorization' => 'Bearer ' . $sb_key,
            'Content-Type'  => 'application/json',
        ],
        'body'    => wp_json_encode( [ 'expiresIn' => 3600 ] ),
        'timeout' => 10,
    ] );

    if ( is_wp_error( $resp ) ) wp_die( 'Erro ao gerar link: ' . $resp->get_error_message(), 500 );
    $body = json_decode( wp_remote_retrieve_body( $resp ), true );
    $signed = $body['signedURL'] ?? '';
    if ( ! $signed ) wp_die( 'Não foi possível gerar link de download', 500 );

    $full_url = rtrim( $sb_url, '/' ) . '/storage/v1' . $signed;
    wp_redirect( $full_url );
    exit;
}

// ─── LIMPEZA DE CARDS ANTIGOS (admin-only) ────────────────────────────────────
// Uso: POST wp-admin/admin-ajax.php
//   action=tao_crm_limpar_cards_antigos
//   nonce=<taoCrm.nonce>
//   workspace_name=Magis   (busca parcial, case-insensitive)
//   cutoff_date=2026-06-12 (exclui cards com criado_em < essa data)
//   dry_run=1              (apenas conta — não apaga)
//   dry_run=0              (executa a deleção)
add_action( 'wp_ajax_tao_crm_limpar_cards_antigos', function () {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Acesso negado — apenas admins' );

    $ws_name    = sanitize_text_field( $_POST['workspace_name'] ?? '' );
    $cutoff_raw = sanitize_text_field( $_POST['cutoff_date']    ?? '' );
    $dry_run    = ( ( $_POST['dry_run'] ?? '1' ) !== '0' );

    if ( ! $ws_name )    wp_send_json_error( 'workspace_name obrigatório' );
    if ( ! $cutoff_raw ) wp_send_json_error( 'cutoff_date obrigatório (YYYY-MM-DD)' );

    $cutoff_iso = rawurlencode( $cutoff_raw . 'T00:00:00' );
    $ws_q       = rawurlencode( '*' . $ws_name . '*' );

    // 1. Encontra workspaces que casam com o nome
    $rw = tao_crm_api( "/crm_workspaces?nome=ilike.$ws_q&select=id,nome" );
    if ( ! $rw['ok'] || empty( $rw['data'] ) ) {
        wp_send_json_error( 'Nenhum workspace encontrado para: ' . $ws_name );
    }

    $log        = [];
    $total_excl = 0;

    foreach ( $rw['data'] as $ws ) {
        $ws_id   = $ws['id'];
        $ws_nome = $ws['nome'];

        // 2. Busca cards criados ANTES do cutoff (em lotes de 200)
        $offset   = 0;
        $card_ids = [];
        do {
            $rc = tao_crm_api( "/crm_cards?workspace_id=eq.$ws_id&criado_em=lt.$cutoff_iso&select=id&limit=200&offset=$offset" );
            if ( ! $rc['ok'] || empty( $rc['data'] ) ) break;
            foreach ( $rc['data'] as $c ) $card_ids[] = $c['id'];
            $offset += 200;
        } while ( count( $rc['data'] ?? [] ) === 200 );

        $count = count( $card_ids );
        $log[] = [ 'workspace' => $ws_nome, 'ws_id' => $ws_id, 'cards_encontrados' => $count ];

        if ( $count === 0 || $dry_run ) continue;

        // 3. Apaga registros dependentes em lotes de 50
        $tabelas_dep = [
            'crm_mensagens',
            'crm_card_itens',
            'crm_cards_valores',
            'crm_cards_tags',
            'crm_lembretes',
            'crm_cards_historico',
            'crm_msgs_agendadas',
        ];

        foreach ( array_chunk( $card_ids, 50 ) as $chunk ) {
            $ids_csv = implode( ',', $chunk );
            foreach ( $tabelas_dep as $tabela ) {
                tao_crm_api( "/$tabela?card_id=in.($ids_csv)", 'DELETE' );
            }
            // Apaga os próprios cards
            tao_crm_api( "/crm_cards?id=in.($ids_csv)", 'DELETE' );
        }

        $total_excl += $count;
        $log[ count( $log ) - 1 ]['cards_excluidos'] = $count;
    }

    wp_send_json_success( [
        'dry_run'        => $dry_run,
        'cutoff'         => $cutoff_raw,
        'total_excluido' => $total_excl,
        'detalhes'       => $log,
    ] );
} );
