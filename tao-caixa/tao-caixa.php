<?php
/**
 * Plugin Name: TAO Caixa
 * Description: Módulo financeiro / caixa — TAO Suite
 * Version:     0.1.0
 * Author:      TAO Suite
 * Text Domain: tao-caixa
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'TAOC_VERSION',    '0.1.0' );
define( 'TAOC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TAOC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once TAOC_PLUGIN_DIR . 'includes/api.php';
require_once TAOC_PLUGIN_DIR . 'includes/permissao.php';
require_once TAOC_PLUGIN_DIR . 'includes/ajax.php';
require_once TAOC_PLUGIN_DIR . 'includes/venda.php';
require_once TAOC_PLUGIN_DIR . 'includes/pages/dashboard.php';
require_once TAOC_PLUGIN_DIR . 'includes/pages/vendas.php';
require_once TAOC_PLUGIN_DIR . 'includes/pages/adquirentes.php';
require_once TAOC_PLUGIN_DIR . 'includes/pages/taxas.php';
require_once TAOC_PLUGIN_DIR . 'includes/pages/formas-pagamento.php';

// Ao ativar: concede a permissão de operar caixa para Admin e Gestor
register_activation_hook( __FILE__, 'tao_caixa_on_activate' );

// ── Admin menu ────────────────────────────────────────────────────────────────
add_action( 'admin_menu', function() {
    if ( ! tao_caixa_pode_operar() ) return;

    add_menu_page(
        'TAO Caixa', 'TAO Caixa', 'read',
        'tao-caixa', 'tao_caixa_page_dashboard',
        'dashicons-money-alt', 59
    );
    add_submenu_page( 'tao-caixa', 'Dashboard',          'Dashboard',           'read', 'tao-caixa',             'tao_caixa_page_dashboard' );
    add_submenu_page( 'tao-caixa', 'Vendas',             'Vendas',              'read', 'tao-caixa-vendas',      'tao_caixa_page_vendas' );
    add_submenu_page( 'tao-caixa', 'Operadoras',         'Operadoras de Cartão','read', 'tao-caixa-adquirentes', 'tao_caixa_page_adquirentes' );
    add_submenu_page( 'tao-caixa', 'Taxas',              'Taxas (MDR)',         'read', 'tao-caixa-taxas',       'tao_caixa_page_taxas' );
    add_submenu_page( 'tao-caixa', 'Formas de Pagamento','Formas de Pagamento', 'read', 'tao-caixa-formas',      'tao_caixa_page_formas_pgto' );
} );
