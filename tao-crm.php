<?php
/**
 * Plugin Name: TAO CRM
 * Description: Módulo CRM do TAO CRM — pipeline visual com chat WhatsApp nativo
 * Version: 1.1.2
 * Author: Carlo
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'TAO_CRM_VERSION', '1.6.2' );

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
require_once TAO_CRM_DIR . 'includes/pages/dashboard.php';
require_once TAO_CRM_DIR . 'includes/pages/kanban.php';
require_once TAO_CRM_DIR . 'includes/pages/card.php';
require_once TAO_CRM_DIR . 'includes/pages/settings.php';
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

    $cap          = 'cbpm_cliente';
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

    wp_enqueue_style( 'tao-crm-style', TAO_CRM_URL . 'assets/crm-style.css', [], TAO_CRM_VERSION );
    wp_enqueue_script( 'tao-crm-script', TAO_CRM_URL . 'assets/crm-script.js', [ 'jquery' ], TAO_CRM_VERSION, true );

    $ws_notif = function_exists( 'tao_crm_get_workspace' ) ? tao_crm_get_workspace() : null;
    wp_localize_script( 'tao-crm-script', 'taoCrm', [
        'ajax_url'     => admin_url( 'admin-ajax.php' ),
        'nonce'        => wp_create_nonce( 'tao_crm_nonce' ),
        'supabase_url' => function_exists( 'cbpm_supabase_url' ) ? cbpm_supabase_url() : get_option( 'cbpm_supabase_url', '' ),
        'supabase_key' => function_exists( 'cbpm_supabase_key' ) ? cbpm_supabase_key() : get_option( 'cbpm_supabase_key', '' ),
        'card_base_url'=> admin_url( 'admin.php?page=tao-crm-kanban&action=card&id=' ),
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
    $rc = tao_crm_api( "/crm_cards?id=eq.$card_id&select=contato_nome,contato_whatsapp,titulo,workspace_id,estagio_id,instancia_id" );
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
function tao_crm_disparar_automacoes( $card_id, $estagio_id, $tipo, $force_immediate = false ) {
    if ( ! $estagio_id ) return;
    $ra = tao_crm_api( "/crm_automacoes?estagio_id=eq.$estagio_id&tipo=eq.$tipo&ativo=eq.true&order=ordem.asc" );
    if ( ! $ra['ok'] || empty( $ra['data'] ) ) return;
    $now = time();
    foreach ( $ra['data'] as $auto ) {
        $delay = intval( $auto['delay_minutos'] ?? 0 );
        if ( $force_immediate || $delay === 0 ) {
            tao_crm_executar_automacao_item( $auto, $card_id );
        } else {
            tao_crm_api( '/crm_automacoes_fila', 'POST', [
                'automacao_id' => $auto['id'],
                'card_id'      => $card_id,
                'estagio_id'   => $estagio_id,
                'executar_em'  => gmdate( 'c', $now + $delay * 60 ),
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
    // Usa formato Z (ex: 2026-06-12T23:00:00Z) — evita o + do fuso que quebra a URL do Supabase
    $agora = gmdate( 'Y-m-d\TH:i:s\Z' );
    $r = tao_crm_api( "/crm_automacoes_fila?executado_em=is.null&executar_em=lte.$agora&limit=50&order=executar_em.asc" );
    if ( ! $r['ok'] || empty( $r['data'] ) ) return;
    foreach ( $r['data'] as $item ) {
        $ra = tao_crm_api( "/crm_automacoes?id=eq.{$item['automacao_id']}&ativo=eq.true" );
        if ( ! $ra['ok'] || empty( $ra['data'] ) ) {
            tao_crm_api( "/crm_automacoes_fila?id=eq.{$item['id']}", 'PATCH', [
                'executado_em' => gmdate( 'c' ), 'resultado' => 'skip', 'detalhe' => 'Automação inativa ou removida',
            ] );
            continue;
        }
        $auto = $ra['data'][0];
        if ( in_array( $auto['tipo'], [ 'entrar_fase', 'tempo_na_fase' ] ) ) {
            $rcc = tao_crm_api( "/crm_cards?id=eq.{$item['card_id']}&select=estagio_id" );
            if ( ! $rcc['ok'] || empty( $rcc['data'] ) || $rcc['data'][0]['estagio_id'] !== $item['estagio_id'] ) {
                tao_crm_api( "/crm_automacoes_fila?id=eq.{$item['id']}", 'PATCH', [
                    'executado_em' => gmdate( 'c' ), 'resultado' => 'skip', 'detalhe' => 'Card saiu do estágio',
                ] );
                continue;
            }
        }
        $res = tao_crm_executar_automacao_item( $auto, $item['card_id'] );
        tao_crm_api( "/crm_automacoes_fila?id=eq.{$item['id']}", 'PATCH', [
            'executado_em' => gmdate( 'c' ),
            'resultado'    => $res['ok'] ? 'ok' : 'erro',
            'detalhe'      => $res['detalhe'] ?? '',
        ] );
    }
}

function tao_crm_processar_agendadas_fn() {
    do_action( 'tao_crm_processar_agendadas' );
}

// ─── AJAX: STATUS WHATSAPP ───────────────────────────────────────────────────

add_action( 'wp_ajax_tao_crm_wa_status', 'tao_crm_ajax_wa_status' );
function tao_crm_ajax_wa_status() {
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

    $r = tao_crm_api( "/crm_cards?id=eq.$card_id", 'PATCH', [
        'estagio_id' => $estagio_id,
        'movido_em'  => gmdate( 'c' ),
    ] );

    if ( ! $r['ok'] ) wp_send_json_error( $r['error'] );

    // Salvar valores de campos fornecidos (campos na entrada da fase)
    foreach ( (array) ( $_POST['valores'] ?? [] ) as $campo_id => $valor ) {
        $campo_id = sanitize_text_field( $campo_id );
        $valor    = sanitize_text_field( $valor );
        if ( ! $campo_id ) continue;
        $ex = tao_crm_api( "/crm_cards_valores?card_id=eq.$card_id&campo_id=eq.$campo_id&limit=1" );
        if ( $ex['ok'] && ! empty( $ex['data'] ) ) {
            tao_crm_api( "/crm_cards_valores?card_id=eq.$card_id&campo_id=eq.$campo_id", 'PATCH', [ 'valor' => $valor ] );
        } else {
            tao_crm_api( '/crm_cards_valores', 'POST', [ 'card_id' => $card_id, 'campo_id' => $campo_id, 'valor' => $valor ] );
        }
    }

    tao_crm_api( '/crm_cards_historico', 'POST', [
        'card_id'         => $card_id,
        'de_estagio_id'   => $de_estagio,
        'para_estagio_id' => $estagio_id,
        'usuario_id'      => get_current_user_id(),
    ] );

    if ( $de_estagio && $de_estagio !== $estagio_id ) {
        tao_crm_disparar_automacoes( $card_id, $de_estagio, 'sair_fase', true );
        tao_crm_cancelar_fila( $card_id, $de_estagio );
        tao_crm_disparar_automacoes( $card_id, $estagio_id, 'entrar_fase' );
        tao_crm_disparar_automacoes( $card_id, $estagio_id, 'tempo_na_fase' );
    }

    // Fecha o card ao mover para estágio terminal (ganho/perdido)
    $re_tipo = tao_crm_api( "/crm_estagios?id=eq.$estagio_id&select=tipo&limit=1" );
    if ( $re_tipo['ok'] && ! empty( $re_tipo['data'] ) && in_array( $re_tipo['data'][0]['tipo'] ?? '', [ 'ganho', 'perdido' ] ) ) {
        tao_crm_api( "/crm_cards?id=eq.$card_id", 'PATCH', [ 'fechado' => true, 'status' => 'fechado' ] );
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

    wp_send_json_success();
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
    $r = tao_crm_api( "/crm_campos_estagio?estagio_id=eq.$estagio_id&na_entrada=eq.true&order=ordem.asc" );
    if ( ! $r['ok'] ) wp_send_json_error( $r['error'] );
    $assigns = $r['data'] ?? [];
    if ( empty( $assigns ) ) { wp_send_json_success( [ 'campos' => [], 'valores' => [] ] ); return; }
    $campo_ids     = array_column( $assigns, 'campo_id' );
    $ordem_map     = array_column( $assigns, 'ordem',      'campo_id' );
    $obrigatorio_map = array_column( $assigns, 'obrigatorio', 'campo_id' );
    $r2   = tao_crm_api( '/crm_campos_definicao?id=in.(' . implode( ',', $campo_ids ) . ')&select=id,nome,tipo,opcoes,chave' );
    $defs = $r2['ok'] ? ( $r2['data'] ?? [] ) : [];
    foreach ( $defs as &$def ) {
        $def['obrigatorio'] = ! empty( $obrigatorio_map[ $def['id'] ] );
    }
    unset( $def );
    usort( $defs, fn( $a, $b ) => ( $ordem_map[ $a['id'] ] ?? 0 ) <=> ( $ordem_map[ $b['id'] ] ?? 0 ) );
    $valores = [];
    if ( $card_id ) {
        $ids_str = implode( ',', $campo_ids );
        $rv = tao_crm_api( "/crm_cards_valores?card_id=eq.$card_id&campo_id=in.($ids_str)&select=campo_id,valor" );
        if ( $rv['ok'] && ! empty( $rv['data'] ) ) {
            foreach ( $rv['data'] as $v ) $valores[ $v['campo_id'] ] = $v['valor'];
        }
    }
    wp_send_json_success( [ 'campos' => $defs, 'valores' => $valores ] );
}

// ─── AJAX: CAMPOS OBRIGATÓRIOS DO ESTÁGIO GANHO (dado card_id) ───────────────

add_action( 'wp_ajax_tao_crm_get_ganho_campos',        'tao_crm_ajax_get_ganho_campos' );
add_action( 'wp_ajax_nopriv_tao_crm_get_ganho_campos', 'tao_crm_ajax_get_ganho_campos' );
function tao_crm_ajax_get_ganho_campos() {
    $raw_nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
    if ( ! wp_verify_nonce( $raw_nonce, 'tao_crm_nonce' ) ) wp_send_json_error( 'Sessão expirada.' );
    $card_id = sanitize_text_field( $_POST['card_id'] ?? '' );
    if ( ! $card_id ) wp_send_json_error( 'card_id obrigatório' );
    $rc = tao_crm_api( "/crm_cards?id=eq.$card_id&select=pipeline_id&limit=1" );
    if ( ! $rc['ok'] || empty( $rc['data'] ) ) wp_send_json_error( 'Card não encontrado' );
    $pipeline_id = $rc['data'][0]['pipeline_id'] ?? '';
    if ( ! $pipeline_id ) wp_send_json_success( [ 'campos' => [], 'valores' => [], 'ganho_stage_id' => '' ] );
    $rg = tao_crm_api( "/crm_estagios?pipeline_id=eq.$pipeline_id&tipo=eq.ganho&limit=1" );
    if ( ! $rg['ok'] || empty( $rg['data'] ) ) wp_send_json_success( [ 'campos' => [], 'valores' => [], 'ganho_stage_id' => '' ] );
    $ganho_stage_id = $rg['data'][0]['id'];
    $r = tao_crm_api( "/crm_campos_estagio?estagio_id=eq.$ganho_stage_id&na_entrada=eq.true&order=ordem.asc" );
    if ( ! $r['ok'] || empty( $r['data'] ) ) wp_send_json_success( [ 'campos' => [], 'valores' => [], 'ganho_stage_id' => $ganho_stage_id ] );
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
    wp_send_json_success( [ 'campos' => $defs, 'valores' => $valores, 'ganho_stage_id' => $ganho_stage_id ] );
}

// ─── AJAX: ENVIAR MENSAGEM ────────────────────────────────────────────────────

add_action( 'wp_ajax_tao_crm_send_message', 'tao_crm_ajax_send_message' );
function tao_crm_ajax_send_message() {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    if ( ! function_exists( 'cbpm_can_access' ) || ! cbpm_can_access() ) wp_send_json_error( 'Acesso negado' );

    $card_id  = sanitize_text_field( $_POST['card_id']  ?? '' );
    $mensagem = sanitize_textarea_field( $_POST['mensagem'] ?? '' );
    if ( ! $card_id || ! $mensagem ) wp_send_json_error( 'Dados inválidos' );

    $rc = tao_crm_api( "/crm_cards?id=eq.$card_id&select=contato_whatsapp,workspace_id,instancia_id,responsavel_id" );
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

    $rc = tao_crm_api( "/crm_cards?id=eq.$card_id&select=contato_whatsapp,workspace_id,instancia_id,responsavel_id" );
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

    if ( ! $workspace_id || ! $pipeline_id || ! $estagio_id || ! $nome || strlen( $whats ) < 10 ) {
        wp_send_json_error( 'Preencha todos os campos obrigatórios' );
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
    if ( ! in_array( $tipo, [ 'entrar_fase','sair_fase','tempo_na_fase','recebeu_mensagem','sem_resposta' ] ) ||
         ! in_array( $acao, [ 'enviar_mensagem','mover_fase','atribuir_responsavel','notificar_email','atribuir_responsavel_rr' ] ) ) {
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

add_action( 'wp_ajax_tao_crm_fechar_card', 'tao_crm_ajax_fechar_card' );
function tao_crm_ajax_fechar_card() {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    if ( ! function_exists( 'cbpm_can_access' ) || ! cbpm_can_access() ) wp_send_json_error( 'Acesso negado' );

    $card_id = sanitize_text_field( $_POST['card_id']   ?? '' );
    $tipo    = sanitize_key( $_POST['tipo']              ?? '' );
    $motivo  = sanitize_textarea_field( $_POST['motivo'] ?? '' );
    $valores = [];
    foreach ( $_POST as $k => $v ) {
        if ( preg_match( '/^valores\[([a-f0-9\-]+)\]$/', $k, $m ) ) {
            $valores[ $m[1] ] = sanitize_textarea_field( wp_unslash( $v ) );
        }
    }

    if ( ! $card_id || ! in_array( $tipo, [ 'ganho', 'perdido' ] ) ) wp_send_json_error( 'Dados inválidos' );

    $rc = tao_crm_api( "/crm_cards?id=eq.$card_id&select=estagio_id,pipeline_id,contato_whatsapp,workspace_id,instancia_id&limit=1" );
    if ( ! $rc['ok'] || empty( $rc['data'] ) ) wp_send_json_error( 'Card não encontrado' );
    $card = $rc['data'][0];

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
        foreach ( $card_ids as $cid ) {
            $rc = tao_crm_api( "/crm_cards?id=eq.$cid&limit=1" );
            if ( ! $rc['ok'] || empty( $rc['data'] ) ) { $results[$cid] = 'err'; continue; }
            $card = $rc['data'][0];
            // Reutiliza lógica de fechar
            $_POST['card_id'] = $cid;
            $_POST['tipo']    = $tipo;
            // Busca estágio de fechamento
            $re = tao_crm_api( "/crm_estagios?pipeline_id=eq.{$card['pipeline_id']}&tipo=eq.$tipo&order=ordem.asc&limit=1" );
            if ( ! $re['ok'] || empty( $re['data'] ) ) { $results[$cid] = 'no_stage'; continue; }
            $close_stage = $re['data'][0]['id'];
            $de = $card['estagio_id'];
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
            ] );
            tao_crm_fire_webhook( $card['workspace_id'], 'card_fechado_' . $tipo, [ 'card_id' => $cid ] );
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
            } else {
                $num = $is_lid ? $num_lid : str_replace( [ '@s.whatsapp.net', '@g.us' ], '', $jid );
                if ( $is_lid && $num_lid ) {
                    $resolved = tao_crm_lid_to_phone( $num_lid );
                    if ( $resolved ) $num = $resolved;
                }
            }
            $num_plain = $num;
            if ( ! $num || strpos( $jid, '@g.us' ) !== false ) continue;

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

            // ── Detecta pedido de opt-out na mensagem entrante ────────────────────
            if ( ! $from_me && $tipo === 'text' ) {
                static $optout_kws = [ 'stop', 'parar', 'cancelar mensagens', 'nao quero mais', 'não quero mais', 'descadastrar', 'remover da lista' ];
                $ct_lc = mb_strtolower( $conteudo );
                foreach ( $optout_kws as $kw ) {
                    if ( strpos( $ct_lc, $kw ) !== false ) {
                        tao_crm_set_opt_out( $num );
                        tao_crm_evolution_send( $inst, $num, 'Você foi removido da nossa lista. Para voltar ao atendimento, envie qualquer mensagem.' );
                        continue 2;
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
            $card_id         = null;
            $card_estagio_id = null;
            if ( $r['ok'] && ! empty( $r['data'] ) ) {
                $card_id         = $r['data'][0]['id'];
                $card_estagio_id = $r['data'][0]['estagio_id'];
                // Renova o lock do chatbot: transient pode ter expirado mesmo com card aberto
                if ( ! $from_me ) tao_crm_lock_chatbot( $num_plain, $WS_ID );
            }
            tao_crm_log_error( 'dispatch', '[2] card_humano=' . ( $card_id ? substr($card_id,0,8) : 'none' ), [ 'num' => $num_plain, 'from_me' => $from_me, 'msg' => mb_substr($conteudo,0,80) ] );

            // ── 2c. Busca card aberto de tracking (Pós Vendas, sem bloquear chatbot) ─
            // Busca em TODAS as instâncias para garantir que cards de pós-vendas sejam detectados
            // independentemente da instância em que foram criados.
            $tracking_card_id    = $card_id;
            $pos_vendas_card     = null;
            if ( ! $card_id ) {
                $rt = tao_crm_api( "/crm_cards?workspace_id=eq.$WS_ID&contato_whatsapp=eq.$num&fechado=eq.false&atendimento_humano=eq.false&select=id,estagio_id,titulo,pipeline_id&order=criado_em.desc&limit=1" );
                if ( $rt['ok'] && ! empty( $rt['data'] ) ) {
                    $tracking_card_id = $rt['data'][0]['id'];
                    $pos_vendas_card  = $rt['data'][0];
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
            $is_handoff_req = tao_crm_is_user_handoff_request( $conteudo );
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
            if ( ! $from_me && ! $card_id && ! $pos_vendas_card && $N8N_URL && empty( $fw_cache[ $WS_ID ] ) && ! ( $is_handoff_req && ! tao_crm_esta_em_horario( $WS_ID ) ) ) {
                $fw_ev = $ev;
                if ( isset( $fw_ev['data'] ) ) {
                    $fw_ev['_crm_retorno']    = $is_retorno;
                    $fw_ev['_crm_contato_id'] = $contato_id;

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
            $is_handoff_phrase = tao_crm_is_user_handoff_request( $conteudo );
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
                    tao_crm_disparar_automacoes( $card_id, $card_estagio_id, 'entrar_fase' );
                    tao_crm_disparar_automacoes( $card_id, $card_estagio_id, 'tempo_na_fase' );
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
                        tao_crm_disparar_automacoes( $card_id, $HANDOFF_STAGE_ID, 'entrar_fase' );
                        tao_crm_disparar_automacoes( $card_id, $HANDOFF_STAGE_ID, 'tempo_na_fase' );
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
                        tao_crm_disparar_automacoes( $card_id, $HANDOFF_STAGE_ID, 'entrar_fase' );
                        tao_crm_disparar_automacoes( $card_id, $HANDOFF_STAGE_ID, 'tempo_na_fase' );
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
                        tao_crm_disparar_automacoes( $card_id, $HANDOFF_STAGE_ID, 'entrar_fase' );
                        tao_crm_disparar_automacoes( $card_id, $HANDOFF_STAGE_ID, 'tempo_na_fase' );
                    }
                }
            }

            // ── 5. Sem card nem tracking → sem mais nada a fazer ────────────────
            if ( ! $card_id && ! $tracking_card_id ) continue;

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
                    tao_crm_disparar_automacoes( $card_id, $card_estagio_id, 'recebeu_mensagem' );
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

    if ( ! $r['ok'] ) return new WP_Error( 'create_failed', $r['error'] ?? 'Erro ao criar card', [ 'status' => 500 ] );

    $new_card = $r['data'][0];
    if ( $contato_id ) tao_crm_api( '/rpc/crm_contato_novo_atendimento', 'POST', [ 'p_id' => $contato_id ] );
    if ( function_exists( 'tao_crm_lock_chatbot' ) ) tao_crm_lock_chatbot( $whatsapp, $workspace_id );
    tao_crm_disparar_automacoes( $new_card['id'], $estagio_id, 'entrar_fase' );
    tao_crm_disparar_automacoes( $new_card['id'], $estagio_id, 'tempo_na_fase' );
    tao_crm_fire_webhook( $workspace_id, 'card_criado', [ 'card' => $new_card ] );

    return rest_ensure_response( [ 'ok' => true, 'card_id' => $new_card['id'], 'criado' => true ] );
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
    $rc = tao_crm_api( "/crm_cards?id=eq.$card_id&select=workspace_id&limit=1" );
    if ( ! $rc['ok'] || empty( $rc['data'] ) ) wp_send_json_error( 'Card não encontrado' );
    $ws_id = $rc['data'][0]['workspace_id'] ?? '';
    if ( ! tao_crm_is_gestor( $ws_id ) ) wp_send_json_error( 'Acesso negado — apenas gestores podem reabrir cards' );
    $r = tao_crm_api( "/crm_cards?id=eq.$card_id", 'PATCH', [ 'fechado' => false, 'status' => 'aberto' ] );
    $r['ok'] ? wp_send_json_success() : wp_send_json_error( $r['error'] );
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
        $rc = tao_crm_api( "/crm_cards?id=eq.$cid&select=contato_whatsapp,workspace_id&limit=1" );
        if ( ! $rc['ok'] || empty( $rc['data'] ) ) {
            tao_crm_api( "/crm_msgs_agendadas?id=eq.$mid", 'PATCH', [ 'enviado' => true, 'erro' => 'card não encontrado' ] );
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
        } else {
            tao_crm_api( "/crm_msgs_agendadas?id=eq.$mid", 'PATCH', [ 'enviado' => true, 'erro' => 'falha no envio WhatsApp' ] );
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
 * Recalcula a soma dos itens e atualiza crm_cards.valor_oportunidade.
 */
function tao_crm_sync_valor_oportunidade( string $card_id ): void {
    $r = tao_crm_api( "/crm_card_itens?card_id=eq.$card_id&select=total" );
    if ( ! $r['ok'] ) return;
    $total = array_sum( array_column( $r['data'] ?? [], 'total' ) );
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
