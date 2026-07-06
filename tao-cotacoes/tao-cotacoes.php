<?php
/**
 * Plugin Name: TAO Cotações
 * Description: Módulo de cotações de compra (procurement) — TAO Suite
 * Version:     1.0.0
 * Author:      TAO Suite
 * Text Domain: tao-cotacoes
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'TAOCOT_VERSION',    '1.0.0' );
define( 'TAOCOT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TAOCOT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once TAOCOT_PLUGIN_DIR . 'includes/api.php';
require_once TAOCOT_PLUGIN_DIR . 'includes/ajax.php';
require_once TAOCOT_PLUGIN_DIR . 'includes/dispatch.php';
require_once TAOCOT_PLUGIN_DIR . 'includes/proposta.php';
require_once TAOCOT_PLUGIN_DIR . 'includes/pages/cotacoes.php';
require_once TAOCOT_PLUGIN_DIR . 'includes/pages/cotacao-nova.php';
require_once TAOCOT_PLUGIN_DIR . 'includes/pages/fornecedores.php';

// ── Admin menu ────────────────────────────────────────────────────────────────
add_action( 'admin_menu', function() {
    if ( ! tao_cot_pode() ) return;

    add_menu_page(
        'Cotações', 'Cotações', 'read',
        'tao-cotacoes', 'tao_cotacoes_page_lista',
        'dashicons-tag', 58
    );
    add_submenu_page( 'tao-cotacoes', 'Cotações',     'Cotações',      'read', 'tao-cotacoes',              'tao_cotacoes_page_lista' );
    add_submenu_page( 'tao-cotacoes', 'Nova Cotação', 'Nova Cotação',  'read', 'tao-cotacoes-nova',         'tao_cotacoes_page_nova' );
    add_submenu_page( 'tao-cotacoes', 'Fornecedores', 'Fornecedores',  'read', 'tao-cotacoes-fornecedores', 'tao_cotacoes_page_fornecedores' );
} );
