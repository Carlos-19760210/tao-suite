<?php
/**
 * Plugin Name: TAO Fórmulas
 * Description: Módulo de cotação para farmácia de manipulação — TAO Suite
 * Version:     1.2.2
 * Author:      TAO Suite
 * Text Domain: tao-formula
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'TAOF_VERSION',    '1.2.2' );
define( 'TAOF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TAOF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once TAOF_PLUGIN_DIR . 'includes/api.php';
require_once TAOF_PLUGIN_DIR . 'includes/ajax.php';
require_once TAOF_PLUGIN_DIR . 'includes/import.php';
require_once TAOF_PLUGIN_DIR . 'includes/pages/dashboard.php';
require_once TAOF_PLUGIN_DIR . 'includes/pages/orcamentos.php';
require_once TAOF_PLUGIN_DIR . 'includes/pages/orcamento-novo.php';
require_once TAOF_PLUGIN_DIR . 'includes/pages/formas.php';
require_once TAOF_PLUGIN_DIR . 'includes/pages/ativos.php';
require_once TAOF_PLUGIN_DIR . 'includes/pages/configuracoes.php';
require_once TAOF_PLUGIN_DIR . 'includes/pages/sinonimos.php';
require_once TAOF_PLUGIN_DIR . 'includes/pages/historico.php';
require_once TAOF_PLUGIN_DIR . 'includes/pages/prescritores.php';
require_once TAOF_PLUGIN_DIR . 'includes/pages/estoque-nf.php';
require_once TAOF_PLUGIN_DIR . 'includes/pages/estoque-lotes.php';
require_once TAOF_PLUGIN_DIR . 'includes/pages/estoque-reposicao.php';
require_once TAOF_PLUGIN_DIR . 'includes/pages/producao.php';
require_once TAOF_PLUGIN_DIR . 'includes/pages/livro-receituario.php';

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
    add_submenu_page( 'tao-formula', 'Histórico',            'Histórico',            'read', 'tao-formula-historico', 'tao_formula_page_historico' );
    add_submenu_page( 'tao-formula', 'Prescritores',         'Prescritores',         'read', 'tao-formula-prescritores', 'tao_formula_page_prescritores' );
    add_submenu_page( 'tao-formula', 'Estoque — Entrada NF', 'Estoque — Entrada NF', 'read', 'tao-formula-estoque-nf',  'tao_formula_page_estoque_nf' );
    add_submenu_page( 'tao-formula', 'Estoque — Lotes',      'Estoque — Lotes',      'read', 'tao-formula-estoque-lotes', 'tao_formula_page_estoque_lotes' );
    add_submenu_page( 'tao-formula', 'Estoque — Reposição',  'Estoque — Reposição',  'read', 'tao-formula-estoque-repo', 'tao_formula_page_estoque_reposicao' );
    add_submenu_page( 'tao-formula', 'Produção',             'Produção',             'read', 'tao-formula-producao',    'tao_formula_page_producao' );
    add_submenu_page( 'tao-formula', 'Livro de Receituário', 'Livro de Receituário', 'read', 'tao-formula-livro',       'tao_formula_page_livro_receituario' );
    add_submenu_page( 'tao-formula', 'Formas Farmacêuticas', 'Formas Farmacêuticas', 'read', 'tao-formula-formas',    'tao_formula_page_formas' );
    add_submenu_page( 'tao-formula', 'Ativos',               'Ativos',               'read', 'tao-formula-ativos',    'tao_formula_page_ativos' );
    add_submenu_page( 'tao-formula', 'Sinônimos',            'Sinônimos',            'read', 'tao-formula-sinonimos', 'tao_formula_page_sinonimos' );
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
        'motorOn'     => get_option( 'tao_formula_motor_v2' ) === '1',
    ] );
    // JS extra somente na página de novo orçamento
    if ( strpos( $hook, 'tao-formula-orc-novo' ) !== false ) {
        $orc_ver = filemtime( TAOF_PLUGIN_DIR . 'assets/formula-orc.js' ) ?: TAOF_VERSION;
        wp_enqueue_script( 'tao-formula-orc-js', TAOF_PLUGIN_URL . 'assets/formula-orc.js', ['jquery', 'tao-formula-js'], $orc_ver, true );
    }
} );
