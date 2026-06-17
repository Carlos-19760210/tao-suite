<?php
/**
 * Plugin Name: TAO Fórmulas
 * Description: Módulo de cotação para farmácia de manipulação — TAO Suite
 * Version:     1.2.0
 * Author:      TAO Suite
 * Text Domain: tao-formula
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'TAOF_VERSION',    '1.2.0' );
define( 'TAOF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TAOF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once TAOF_PLUGIN_DIR . 'includes/api.php';
require_once TAOF_PLUGIN_DIR . 'includes/ajax.php';
require_once TAOF_PLUGIN_DIR . 'includes/pages/dashboard.php';
require_once TAOF_PLUGIN_DIR . 'includes/pages/orcamentos.php';
require_once TAOF_PLUGIN_DIR . 'includes/pages/orcamento-novo.php';
require_once TAOF_PLUGIN_DIR . 'includes/pages/formas.php';
require_once TAOF_PLUGIN_DIR . 'includes/pages/ativos.php';
require_once TAOF_PLUGIN_DIR . 'includes/pages/configuracoes.php';

// ── Admin menu ────────────────────────────────────────────────────────────────
add_action( 'admin_menu', function() {
    if ( ! function_exists( 'cbpm_can_access' ) || ! cbpm_can_access() ) return;

    add_menu_page(
        'TAO Fórmulas', 'TAO Fórmulas', 'read',
        'tao-formula', 'tao_formula_page_dashboard',
        'dashicons-clipboard', 58
    );
    add_submenu_page( 'tao-formula', 'Dashboard',            'Dashboard',            'read', 'tao-formula',            'tao_formula_page_dashboard' );
    add_submenu_page( 'tao-formula', 'Orçamentos',           'Orçamentos',           'read', 'tao-formula-orcamentos', 'tao_formula_page_orcamentos' );
    add_submenu_page( 'tao-formula', 'Novo Orçamento',       'Novo Orçamento',       'read', 'tao-formula-orc-novo',  'tao_formula_page_orcamento_novo' );
    add_submenu_page( 'tao-formula', 'Formas Farmacêuticas', 'Formas Farmacêuticas', 'read', 'tao-formula-formas',    'tao_formula_page_formas' );
    add_submenu_page( 'tao-formula', 'Ativos',               'Ativos',               'read', 'tao-formula-ativos',    'tao_formula_page_ativos' );
    add_submenu_page( 'tao-formula', 'Configurações',        'Configurações',        'read', 'tao-formula-config',    'tao_formula_page_config' );
} );

// ── Assets (admin) ────────────────────────────────────────────────────────────
add_action( 'admin_enqueue_scripts', function( $hook ) {
    if ( strpos( $hook, 'tao-formula' ) === false ) return;
    wp_enqueue_style(  'tao-formula-css', TAOF_PLUGIN_URL . 'assets/formula-style.css', [], TAOF_VERSION );
    wp_enqueue_script( 'tao-formula-js',  TAOF_PLUGIN_URL . 'assets/formula-script.js', ['jquery'], TAOF_VERSION, true );
    wp_localize_script( 'tao-formula-js', 'taoFormula', [
        'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
        'nonce'       => wp_create_nonce( 'tao_formula_nonce' ),
        'supabaseUrl' => tao_formula_supabase_url(),
        'supabaseKey' => tao_formula_supabase_key(),
    ] );
    // JS extra somente na página de novo orçamento
    if ( strpos( $hook, 'tao-formula-orc-novo' ) !== false ) {
        wp_enqueue_script( 'tao-formula-orc-js', TAOF_PLUGIN_URL . 'assets/formula-orc.js', ['jquery', 'tao-formula-js'], TAOF_VERSION, true );
    }
} );
