<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Frontend /robos/ â€” roteamento para as paginas do plugin.
 */

add_filter( 'query_vars', function( $vars ) {
    $vars[] = 'cbpm_page';
    $vars[] = 'cbpm_sub';
    return $vars;
});

add_action( 'init', function() {
    add_rewrite_rule( '^robos/?$',             'index.php?cbpm_page=dashboard', 'top' );
    add_rewrite_rule( '^robos/login/?$',        'index.php?cbpm_page=login',     'top' );
    add_rewrite_rule( '^robos/([^/]+)/?$',      'index.php?cbpm_page=$matches[1]', 'top' );
});

register_activation_hook( CBPM_PLUGIN_DIR . 'chatbot-platform.php', function() {
    add_rewrite_rule( '^robos/?$',          'index.php?cbpm_page=dashboard', 'top' );
    add_rewrite_rule( '^robos/login/?$',    'index.php?cbpm_page=login',     'top' );
    add_rewrite_rule( '^robos/([^/]+)/?$',  'index.php?cbpm_page=$matches[1]', 'top' );
    flush_rewrite_rules();
});

add_action( 'template_redirect', function() {
    $cbpm_page = get_query_var( 'cbpm_page' );
    if ( $cbpm_page === '' ) return;

    // Login: sempre acessÃ­vel (nÃ£o redireciona antes de mostrar o form)
    if ( $cbpm_page === 'login' ) {
        if ( is_user_logged_in() && cbpm_can_access() ) {
            wp_redirect( cbpm_url() ); exit;
        }
        add_filter( 'show_admin_bar', '__return_false', 999 );
        while ( ob_get_level() > 0 ) ob_end_clean();
        nocache_headers();
        header( 'X-LiteSpeed-Cache-Control: no-cache,no-store' );
        include CBPM_PLUGIN_DIR . 'templates/login.php';
        exit;
    }

    // Demais pÃ¡ginas: exige autenticaÃ§Ã£o
    if ( ! is_user_logged_in() ) {
        $back = home_url( '/robos/' . ( $cbpm_page !== 'dashboard' ? $cbpm_page . '/' : '' ) );
        wp_redirect( home_url( '/robos/login/?redirect_to=' . urlencode( $back ) ) );
        exit;
    }
    if ( ! cbpm_can_access() ) {
        wp_die( 'Acesso negado. Sua conta nÃ£o tem permissÃ£o para acessar o portal.', 403 );
    }

    global $cbpm_is_frontend;
    $cbpm_is_frontend = true;

    $map = [
        'dashboard'      => 'chatbot-platform-dashboard',
        'negocios'       => 'chatbot-platform',
        'categorias'     => 'chatbot-platform-categorias',
        'catalogo'       => 'chatbot-platform-catalogo',
        'disponibilidade'=> 'chatbot-platform-disponibilidade',
        'conteudo'       => 'chatbot-platform-conteudo',
        'leads'          => 'chatbot-platform-leads',
        'pedidos'        => 'chatbot-platform-pedidos',
        'historico'      => 'chatbot-platform-historico',
        'campos-extras'  => 'chatbot-platform-campos-extras',
        'configuracoes'  => 'chatbot-platform-settings',
        'conectores'     => 'chatbot-platform-conectores',
        'campanhas'      => 'chatbot-platform-campanhas',
        'listas'         => 'chatbot-platform-listas',
        'crm'            => 'tao-crm-dashboard',
        'crm-dashboard'  => 'tao-crm-dashboard',
        'crm-inbox'      => 'tao-crm-inbox',
        'crm-kanban'     => 'tao-crm-kanban',
        'crm-settings'   => 'tao-crm-settings',
        'crm-workspaces' => 'tao-crm-workspaces',
        'crm-pipelines'  => 'tao-crm-pipelines',
        'crm-campos'     => 'tao-crm-campos',
        'crm-automacoes' => 'tao-crm-automacoes',
        'crm-contatos'   => 'tao-crm-contatos',
        'usuarios'       => 'chatbot-platform-usuarios',
        'formula'            => 'tao-formula',
        'formula-dashboard'  => 'tao-formula',
        'formula-orcamentos' => 'tao-formula-orcamentos',
        'formula-novo-orc'   => 'tao-formula-orc-novo',
        'formula-formas'     => 'tao-formula-formas',
        'formula-ativos'     => 'tao-formula-ativos',
        'formula-config'     => 'tao-formula-config',
    ];

    $page_slug = $map[ $cbpm_page ] ?? 'chatbot-platform-dashboard';
    $_GET['page'] = $page_slug;

    // Desabilita a admin bar do WordPress
    add_filter( 'show_admin_bar', '__return_false', 999 );

    // Esvazia TODOS os output buffers (inclusive o do LiteSpeed Cache)
    // sem isso o LiteSpeed processa o HTML e quebra o <head>
    while ( ob_get_level() > 0 ) {
        ob_end_clean();
    }

    nocache_headers();
    header( 'X-LiteSpeed-Cache-Control: no-cache,no-store' );
    header( 'X-Accel-Buffering: no' );
    include CBPM_PLUGIN_DIR . "templates/base.php";
    exit;
});

if ( ! function_exists( 'cbpm_url' ) ) {
    function cbpm_url( $section = 'dashboard', $params = [] ) {
        $base = home_url( '/robos/' . ( $section !== 'dashboard' ? $section . '/' : '' ) );
        return $params ? add_query_arg( $params, $base ) : $base;
    }
}