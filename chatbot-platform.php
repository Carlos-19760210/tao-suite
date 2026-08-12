<?php
/**
 * Plugin Name: Chatbot Platform
 * Description: Gerenciamento da plataforma multi-tenant de chatbots WhatsApp
 * Version: 2.2.0
 * Author: Carlo
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'CBPM_VERSION',    '2.3.0' );
define( 'CBPM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CBPM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

function cbpm_supabase_url() { return get_option( 'cbpm_supabase_url', 'https://gclayesytzzpzkjvgede.supabase.co' ); }
function cbpm_supabase_key() { return get_option( 'cbpm_supabase_key', '' ); }

add_action( 'init', function() {
    if ( ! defined('CBPM_SUPABASE_URL') ) define( 'CBPM_SUPABASE_URL', cbpm_supabase_url() );
    if ( ! defined('CBPM_SUPABASE_KEY') ) define( 'CBPM_SUPABASE_KEY', cbpm_supabase_key() );
}, 1 );

// ─── ROLE & TENANT HELPERS ────────────────────────────────────────────────────

function cbpm_register_roles() {
    if ( ! get_role( 'cbpm_cliente' ) )
        add_role( 'cbpm_cliente',     'Cliente Chatbot',  [ 'read' => true, 'cbpm_cliente'     => true ] );
    if ( ! get_role( 'cbpm_gestor' ) )
        add_role( 'cbpm_gestor',      'Gestor TAO',       [ 'read' => true, 'cbpm_gestor'      => true ] );
    if ( ! get_role( 'cbpm_operacional' ) )
        add_role( 'cbpm_operacional', 'Operacional TAO',  [ 'read' => true, 'cbpm_operacional' => true ] );
}
register_activation_hook( __FILE__, 'cbpm_register_roles' );
add_action( 'init', 'cbpm_register_roles' );

function cbpm_current_role() {
    if ( current_user_can( 'manage_options' ) )   return 'master';
    if ( current_user_can( 'cbpm_gestor' ) )      return 'gestor';
    if ( current_user_can( 'cbpm_operacional' ) ) return 'operacional';
    if ( current_user_can( 'cbpm_cliente' ) )     return 'gestor'; // legacy alias
    return null;
}
function cbpm_is_master()      { return cbpm_current_role() === 'master'; }
function cbpm_is_gestor()      { return cbpm_current_role() === 'gestor'; }
function cbpm_is_operacional() { return cbpm_current_role() === 'operacional'; }

function cbpm_current_cliente_id() {
    if ( cbpm_is_master() ) return null;
    // Multi-negócio: respeita o NEGÓCIO ATIVO do seletor (entre os liberados ao usuário).
    // Sem o tao-crm carregado ou sem resolução, cai no cliente fixo legado (user_meta).
    if ( function_exists( 'tao_crm_cliente_do_negocio_ativo' ) ) {
        $c = tao_crm_cliente_do_negocio_ativo();
        if ( $c ) return $c;
    }
    return get_user_meta( get_current_user_id(), 'cbpm_cliente_id', true ) ?: null;
}

function cbpm_can_access() {
    return cbpm_current_role() !== null;
}

// ─── REQUIRES ────────────────────────────────────────────────────────────────

require_once CBPM_PLUGIN_DIR . 'includes/api.php';
require_once CBPM_PLUGIN_DIR . 'includes/settings.php';
require_once CBPM_PLUGIN_DIR . 'includes/ajax.php';

require_once CBPM_PLUGIN_DIR . 'includes/pages/portal_home.php';
require_once CBPM_PLUGIN_DIR . 'includes/pages/dashboard.php';
require_once CBPM_PLUGIN_DIR . 'includes/pages/neo_dashboard.php';
require_once CBPM_PLUGIN_DIR . 'includes/pages/clientes.php';
require_once CBPM_PLUGIN_DIR . 'includes/pages/categorias.php';
require_once CBPM_PLUGIN_DIR . 'includes/pages/catalogo.php';
require_once CBPM_PLUGIN_DIR . 'includes/pages/disponibilidade.php';
require_once CBPM_PLUGIN_DIR . 'includes/pages/conteudo_dinamico.php';
require_once CBPM_PLUGIN_DIR . 'includes/pages/leads.php';
require_once CBPM_PLUGIN_DIR . 'includes/pages/pedidos.php';
require_once CBPM_PLUGIN_DIR . 'includes/pages/historico.php';
require_once CBPM_PLUGIN_DIR . 'includes/pages/campos_extras.php';
require_once CBPM_PLUGIN_DIR . 'includes/pages/conectores.php';
require_once CBPM_PLUGIN_DIR . 'includes/pages/usuarios.php';
require_once CBPM_PLUGIN_DIR . 'includes/pages/campanhas.php';
require_once CBPM_PLUGIN_DIR . 'includes/pages/listas_contatos.php';

require_once CBPM_PLUGIN_DIR . 'includes/frontend.php';

// ─── MENUS ────────────────────────────────────────────────────────────────────

add_action( 'admin_menu', 'cbpm_register_menus' );
function cbpm_register_menus() {
    $is_admin   = current_user_can( 'manage_options' );
    $is_cliente = current_user_can( 'cbpm_cliente' );

    if ( ! $is_admin && ! $is_cliente ) return;

    $cap = $is_admin ? 'manage_options' : 'cbpm_cliente';

    add_menu_page(
        'Chatbot Platform', 'Chatbot Platform', $cap,
        'chatbot-platform', 'cbpm_page_dashboard',
        'dashicons-format-chat', 30
    );

    // Dashboard: slug igual ao pai substitui o item auto-criado pelo WP
    add_submenu_page( 'chatbot-platform', 'Dashboard', 'Dashboard', $cap, 'chatbot-platform', 'cbpm_page_dashboard' );

    // Admin-only pages
    if ( $is_admin ) {
        add_submenu_page( 'chatbot-platform', 'Neg&oacute;cios',   'Neg&oacute;cios',   'manage_options', 'chatbot-platform-negocios',      'cbpm_page_clientes' );
        add_submenu_page( 'chatbot-platform', 'Categorias',        'Categorias',        'manage_options', 'chatbot-platform-categorias',    'cbpm_page_categorias' );
    }

    // cbpm_cliente: acesso ao próprio negócio
    if ( $is_cliente ) {
        add_submenu_page( 'chatbot-platform', 'Meu Neg&oacute;cio', 'Meu Neg&oacute;cio', 'cbpm_cliente', 'chatbot-platform-meu-negocio', 'cbpm_page_clientes' );
    }

    // Shared pages (admin + cliente)
    add_submenu_page( 'chatbot-platform', 'Cat&aacute;logo',               'Cat&aacute;logo',               $cap, 'chatbot-platform-catalogo',        'cbpm_page_catalogo' );
    add_submenu_page( 'chatbot-platform', 'Disponibilidade',               'Disponibilidade',               $cap, 'chatbot-platform-disponibilidade', 'cbpm_page_disponibilidade' );
    add_submenu_page( 'chatbot-platform', 'Promo&ccedil;&otilde;es/Avisos','Promo&ccedil;&otilde;es/Avisos',$cap, 'chatbot-platform-conteudo',        'cbpm_page_conteudo_dinamico' );
    add_submenu_page( 'chatbot-platform', 'Leads',                         'Leads',                         $cap, 'chatbot-platform-leads',           'cbpm_page_leads' );
    add_submenu_page( 'chatbot-platform', 'Pedidos',                       'Pedidos',                       $cap, 'chatbot-platform-pedidos',         'cbpm_page_pedidos' );
    add_submenu_page( 'chatbot-platform', 'Hist&oacute;rico',              'Hist&oacute;rico',              $cap, 'chatbot-platform-historico',       'cbpm_page_historico' );
    add_submenu_page( 'chatbot-platform', 'Campanhas',                     'Campanhas',                     $cap, 'chatbot-platform-campanhas',       'cbpm_page_campanhas' );
    add_submenu_page( 'chatbot-platform', 'Listas de Contatos',            'Listas de Contatos',            $cap, 'chatbot-platform-listas',          'cbpm_page_listas_contatos' );

    // Admin-only pages (continued)
    if ( $is_admin ) {
        add_submenu_page( 'chatbot-platform', 'Campos Extras',              'Campos Extras',              'manage_options', 'chatbot-platform-campos-extras',  'cbpm_page_campos_extras' );
        add_submenu_page( 'chatbot-platform', 'Conectores',                 'Conectores',                 'manage_options', 'chatbot-platform-conectores',      'cbpm_page_conectores' );
        add_submenu_page( 'chatbot-platform', 'Usu&aacute;rios',            'Usu&aacute;rios',            'manage_options', 'chatbot-platform-usuarios',        'cbpm_page_usuarios' );
        add_submenu_page( 'chatbot-platform', 'Configura&ccedil;&otilde;es','Configura&ccedil;&otilde;es','manage_options', 'chatbot-platform-settings',        'cbpm_page_settings' );
    }
}

// Redireciona /robos/ (admin home) para o Dashboard do plugin
add_action( 'admin_init', function() {
    if ( ! cbpm_can_access() || wp_doing_ajax() ) return;
    global $pagenow;
    if ( $pagenow === 'index.php' ) {
        wp_redirect( admin_url( 'admin.php?page=chatbot-platform' ) );
        exit;
    }
    // Impede cbpm_cliente de acessar outras telas do WP fora do plugin
    if ( current_user_can( 'cbpm_cliente' ) && ! current_user_can( 'manage_options' ) ) {
        $screen = get_current_screen();
        if ( $screen && strpos( $screen->id, 'chatbot-platform' ) === false ) {
            wp_redirect( admin_url( 'admin.php?page=chatbot-platform' ) );
            exit;
        }
    }
} );

add_action( 'admin_bar_menu', function( $bar ) {
    if ( ! is_user_logged_in() ) return;
    if ( cbpm_can_access() ) {
        $bar->add_node([
            'id'    => 'cbpm-portal',
            'title' => '&#x1F916; Portal TAO Neo',
            'href'  => home_url( '/robos/' ),
            'meta'  => [ 'target' => '_blank', 'title' => 'Abrir portal TAO Neo' ],
        ]);
    } elseif ( current_user_can( 'manage_options' ) ) {
        // Admin WP sem role cbpm: link direto
        $bar->add_node([
            'id'    => 'cbpm-portal',
            'title' => '&#x1F916; Portal TAO Neo',
            'href'  => home_url( '/robos/' ),
            'meta'  => [ 'target' => '_blank' ],
        ]);
    }
}, 100 );

add_action( 'admin_head', function() {
    if ( ! current_user_can( 'cbpm_cliente' ) ) return;
    $screen = get_current_screen();
    if ( ! $screen || strpos( $screen->id, 'chatbot-platform' ) === false ) return;
    echo '<script>document.addEventListener("DOMContentLoaded",function(){
        document.querySelectorAll("select[name=\'cliente_id\']").forEach(function(s){
            var h=document.createElement("input");
            h.type="hidden"; h.name="cliente_id"; h.value=s.value;
            s.parentNode.insertBefore(h,s);
            s.disabled=true;
        });
    });</script>';
} );

add_action( 'admin_enqueue_scripts', 'cbpm_enqueue_assets' );
function cbpm_enqueue_assets( $hook ) {
    if ( strpos( $hook, 'chatbot-platform' ) === false ) return;
    wp_enqueue_style( 'cbpm-style', CBPM_PLUGIN_URL . 'assets/style.css', [], CBPM_VERSION );
    wp_enqueue_script( 'cbpm-script', CBPM_PLUGIN_URL . 'assets/script.js', ['jquery'], CBPM_VERSION, true );
    wp_localize_script( 'cbpm-script', 'cbpm', [
        'ajax_url'     => admin_url( 'admin-ajax.php' ),
        'nonce'        => wp_create_nonce( 'cbpm_nonce' ),
        'supabase_url' => CBPM_SUPABASE_URL,
        'supabase_key' => '', // SEGURANÇA: nunca expor a chave no frontend — acesso ao Supabase é server-side (AJAX/PHP)
    ]);
}

// ─── WP-CRON: SYNC WOOCOMMERCE AGENDADO ──────────────────────────────────────

// Garante que o cron está agendado (executa na inicialização, não só na ativação)
add_action( 'init', function() {
    if ( ! wp_next_scheduled( 'cbpm_woo_sync_cron' ) ) {
        wp_schedule_event( time(), 'hourly', 'cbpm_woo_sync_cron' );
    }
} );

register_deactivation_hook( __FILE__, function() {
    wp_clear_scheduled_hook( 'cbpm_woo_sync_cron' );
} );

add_action( 'cbpm_woo_sync_cron', function() {
    $n8n_url = rtrim( get_option( 'cbpm_n8n_url', 'https://crowingbettafish-n8n.cloudfy.live' ), '/' );
    if ( ! $n8n_url ) return;

    $r = cbpm_api( '/clientes?catalog_source=eq.woocommerce&catalog_sync_mode=eq.scheduled&ativo=eq.true&select=id,catalog_sync_frequency,catalog_last_sync' );
    if ( ! $r['ok'] || empty( $r['data'] ) ) return;

    $freq_map = [ '1h' => 3600, '2h' => 7200, '6h' => 21600, '12h' => 43200, '24h' => 86400, '48h' => 172800, '7d' => 604800 ];
    $now      = time();

    foreach ( $r['data'] as $c ) {
        $freq_secs = $freq_map[ $c['catalog_sync_frequency'] ?? '6h' ] ?? 21600;
        $last_sync = $c['catalog_last_sync'] ? strtotime( $c['catalog_last_sync'] ) : 0;

        if ( ( $now - $last_sync ) >= $freq_secs ) {
            wp_remote_post( $n8n_url . '/webhook/woo-sync', [
                'headers'  => [ 'Content-Type' => 'application/json' ],
                'body'     => wp_json_encode( [ 'cliente_id' => $c['id'] ] ),
                'timeout'  => 10,
                'blocking' => false,
            ] );
        }
    }
} );
