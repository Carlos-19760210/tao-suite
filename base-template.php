<?php
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! function_exists( 'cbpm_can_access' ) || ! cbpm_can_access() ) wp_die( 'Acesso negado.' );

$supabase_url = cbpm_supabase_url();
$supabase_key = cbpm_supabase_key();

// ─── Mapa de seções (routing) ─────────────────────────────────────────────────
$secoes = [
    'chatbot-platform'                => [ 'fn' => 'cbpm_page_portal_home',     'label' => 'Visão Geral' ],
    'chatbot-platform-negocios'        => [ 'fn' => 'cbpm_page_clientes',        'label' => 'Negócios' ],
    'chatbot-platform-categorias'     => [ 'fn' => 'cbpm_page_categorias',       'label' => 'Categorias' ],
    'chatbot-platform-catalogo'       => [ 'fn' => 'cbpm_page_catalogo',          'label' => 'Catálogo' ],
    'chatbot-platform-disponibilidade'=> [ 'fn' => 'cbpm_page_disponibilidade',   'label' => 'Disponibilidade' ],
    'chatbot-platform-conteudo'       => [ 'fn' => 'cbpm_page_conteudo_dinamico', 'label' => 'Promoções/Avisos' ],
    'chatbot-platform-campanhas'      => [ 'fn' => 'cbpm_page_campanhas',         'label' => 'Campanhas' ],
    'chatbot-platform-listas'         => [ 'fn' => 'cbpm_page_listas_contatos',   'label' => 'Listas de Contatos' ],
    'chatbot-platform-leads'          => [ 'fn' => 'cbpm_page_leads',             'label' => 'Leads' ],
    'chatbot-platform-pedidos'        => [ 'fn' => 'cbpm_page_pedidos',           'label' => 'Pedidos' ],
    'chatbot-platform-historico'      => [ 'fn' => 'cbpm_page_historico',         'label' => 'Histórico' ],
    'chatbot-platform-campos-extras'  => [ 'fn' => 'cbpm_page_campos_extras',     'label' => 'Campos Extras' ],
    'chatbot-platform-conectores'     => [ 'fn' => 'cbpm_page_conectores',        'label' => 'Conectores' ],
    'chatbot-platform-usuarios'       => [ 'fn' => 'cbpm_page_usuarios',          'label' => 'Usuários' ],
    'chatbot-platform-settings'       => [ 'fn' => 'cbpm_page_settings',          'label' => 'Configurações' ],
    'chatbot-platform-dashboard'      => [ 'fn' => 'cbpm_page_dashboard',         'label' => 'Dashboard' ],
];
$has_crm = function_exists( 'tao_crm_page_kanban_full' );
if ( $has_crm ) {
    $secoes['tao-crm-dashboard'] = [ 'fn' => 'tao_crm_page_dashboard',  'label' => 'Dashboard CRM' ];
    $secoes['tao-crm-kanban']   = [ 'fn' => 'tao_crm_page_kanban_full', 'label' => 'CRM Kanban' ];
    $secoes['tao-crm-inbox']    = [ 'fn' => 'tao_crm_page_inbox',       'label' => 'CRM Inbox' ];
    $secoes['tao-crm-contatos'] = [ 'fn' => 'tao_crm_page_contatos',    'label' => 'Contatos' ];
    $secoes['tao-crm-settings'] = [ 'fn' => 'tao_crm_page_settings',    'label' => 'CRM Configurações' ];
}

$has_formula = function_exists( 'tao_formula_page_dashboard' );
if ( $has_formula ) {
    $secoes['tao-formula']           = [ 'fn' => 'tao_formula_page_dashboard',      'label' => 'TAO Fórmulas — Dashboard' ];
    $secoes['tao-formula-orcamentos']= [ 'fn' => 'tao_formula_page_orcamentos',     'label' => 'Orçamentos' ];
    $secoes['tao-formula-orc-novo']  = [ 'fn' => 'tao_formula_page_orcamento_novo', 'label' => 'Novo Orçamento' ];
    $secoes['tao-formula-formas']    = [ 'fn' => 'tao_formula_page_formas',         'label' => 'Formas Farmacêuticas' ];
    $secoes['tao-formula-ativos']    = [ 'fn' => 'tao_formula_page_ativos',         'label' => 'Ativos' ];
    $secoes['tao-formula-config']    = [ 'fn' => 'tao_formula_page_config',         'label' => 'TAO Fórmulas — Config' ];
}

$has_caixa = function_exists( 'tao_caixa_page_dashboard' );
if ( $has_caixa ) {
    $secoes['tao-caixa-dashboard']   = [ 'fn' => 'tao_caixa_page_dashboard',    'label' => 'TAO Caixa' ];
    $secoes['tao-caixa-adquirentes'] = [ 'fn' => 'tao_caixa_page_adquirentes',  'label' => 'Caixa — Operadoras de Cartão' ];
    $secoes['tao-caixa-taxas']       = [ 'fn' => 'tao_caixa_page_taxas',        'label' => 'Caixa — Taxas (MDR)' ];
    $secoes['tao-caixa-formas']      = [ 'fn' => 'tao_caixa_page_formas_pgto',  'label' => 'Caixa — Formas de Pagamento' ];
}

$page_atual = $_GET['page'] ?? 'chatbot-platform';
// O plugin mapeia slug 'negocios' → 'chatbot-platform', mas queremos Negócios e não Visão Geral
if ( get_query_var( 'cbpm_page', '' ) === 'negocios' ) $page_atual = 'chatbot-platform-negocios';
if ( ! isset( $secoes[ $page_atual ] ) ) $page_atual = 'chatbot-platform';
$fn = $secoes[ $page_atual ]['fn'] ?? 'cbpm_page_clientes';

// ─── Estrutura do menu accordion ─────────────────────────────────────────────
$nav = [
    'config' => [
        'label' => 'Configura&ccedil;&atilde;o',
        'icon'  => '&#x2699;&#xFE0F;',
        'subs'  => [
            'cfg-geral' => [
                'label' => 'Geral',
                'icon'  => '&#x1F3E2;',
                'items' => [
                    [ 'slug' => 'chatbot-platform-negocios',  'label' => 'Neg&oacute;cios',           'url' => cbpm_url('negocios') ],
                    [ 'slug' => 'chatbot-platform-categorias','label' => 'Categorias',                'url' => cbpm_url('categorias') ],
                    [ 'slug' => 'chatbot-platform-usuarios',  'label' => 'Usu&aacute;rios',           'url' => cbpm_url('usuarios') ],
                    [ 'slug' => 'chatbot-platform-conectores','label' => 'Conectores',                'url' => cbpm_url('conectores') ],
                    [ 'slug' => 'chatbot-platform-settings',  'label' => 'Configura&ccedil;&otilde;es','url' => cbpm_url('configuracoes') ],
                ],
            ],
            'cfg-taon' => [
                'label' => 'TAO Neo',
                'icon'  => '&#x1F916;',
                'items' => [
                    [ 'slug' => 'chatbot-platform-catalogo',       'label' => 'Cat&aacute;logo',          'url' => cbpm_url('catalogo') ],
                    [ 'slug' => 'chatbot-platform-disponibilidade','label' => 'Disponibilidade',          'url' => cbpm_url('disponibilidade') ],
                    [ 'slug' => 'chatbot-platform-conteudo',       'label' => 'Promo&ccedil;&otilde;es/Avisos','url' => cbpm_url('conteudo') ],
                    [ 'slug' => 'chatbot-platform-campanhas',      'label' => 'Campanhas',                'url' => cbpm_url('campanhas') ],
                    [ 'slug' => 'chatbot-platform-listas',         'label' => 'Listas de Contatos',       'url' => cbpm_url('listas') ],
                    [ 'slug' => 'chatbot-platform-campos-extras',  'label' => 'Campos Extras',            'url' => cbpm_url('campos-extras') ],
                ],
            ],
        ],
    ],
    'operacao' => [
        'label' => 'Opera&ccedil;&atilde;o',
        'icon'  => '&#x25B6;&#xFE0F;',
        'subs'  => [
            'op-taon' => [
                'label' => 'TAO Neo',
                'icon'  => '&#x1F916;',
                'items' => [
                    [ 'slug' => 'chatbot-platform-dashboard','label' => 'Dashboard',         'url' => cbpm_url('neo-dashboard') ],
                    [ 'slug' => 'chatbot-platform-leads',    'label' => 'Leads',             'url' => cbpm_url('leads') ],
                    [ 'slug' => 'chatbot-platform-pedidos',  'label' => 'Pedidos',           'url' => cbpm_url('pedidos') ],
                    [ 'slug' => 'chatbot-platform-historico','label' => 'Hist&oacute;rico',  'url' => cbpm_url('historico') ],
                ],
            ],
        ],
    ],
];

if ( $has_crm ) {
    $nav['config']['subs']['cfg-crm'] = [
        'label' => 'TAO CRM',
        'icon'  => '&#x1F4BC;',
        'items' => [
            [ 'slug' => 'tao-crm-settings', 'label' => 'Configura&ccedil;&otilde;es', 'url' => cbpm_url('crm-settings') ],
        ],
    ];
    $nav['operacao']['subs']['op-crm'] = [
        'label' => 'TAO CRM',
        'icon'  => '&#x1F4BC;',
        'items' => [
            [ 'slug' => 'tao-crm-dashboard', 'label' => 'Dashboard', 'url' => cbpm_url('crm-dashboard') ],
            [ 'slug' => 'tao-crm-kanban', 'label' => 'Kanban', 'url' => cbpm_url('crm-kanban') ],
            [ 'slug' => 'tao-crm-inbox',  'label' => 'Inbox',  'url' => cbpm_url('crm-inbox') ],
        ],
    ];
    // Reordena: Contatos > TAO Neo > Campanhas > TAO CRM
    $nav['operacao']['subs'] = [
        'op-contatos' => [
            'label' => 'Contatos',
            'icon'  => '&#x1F465;',
            'items' => [
                [ 'slug' => 'tao-crm-contatos', 'label' => 'Contatos', 'url' => cbpm_url('crm-contatos') ],
            ],
        ],
        'op-taon' => $nav['operacao']['subs']['op-taon'],
        'op-camp' => [
            'label' => 'Campanhas',
            'icon'  => '&#x1F4E3;',
            'items' => [
                [ 'slug' => 'chatbot-platform-campanhas', 'label' => 'Campanhas',          'url' => cbpm_url('campanhas') ],
                [ 'slug' => 'chatbot-platform-listas',    'label' => 'Listas de Contatos', 'url' => cbpm_url('listas') ],
            ],
        ],
        'op-crm'  => $nav['operacao']['subs']['op-crm'],
    ];
}

if ( $has_formula ) {
    $nav['config']['subs']['cfg-formula'] = [
        'label' => 'TAO F&oacute;rmulas',
        'icon'  => '&#x1F9EA;',
        'items' => [
            [ 'slug' => 'tao-formula-formas',  'label' => 'Formas Farmac&ecirc;uticas', 'url' => cbpm_url('formula-formas') ],
            [ 'slug' => 'tao-formula-ativos',  'label' => 'Ativos',                     'url' => cbpm_url('formula-ativos') ],
            [ 'slug' => 'tao-formula-config',  'label' => 'Configura&ccedil;&otilde;es','url' => cbpm_url('formula-config') ],
        ],
    ];
    $nav['operacao']['subs']['op-formula'] = [
        'label' => 'TAO F&oacute;rmulas',
        'icon'  => '&#x1F9EA;',
        'items' => [
            [ 'slug' => 'tao-formula',            'label' => 'Dashboard',              'url' => cbpm_url('formula-dashboard') ],
            [ 'slug' => 'tao-formula-orcamentos', 'label' => 'Or&ccedil;amentos',      'url' => cbpm_url('formula-orcamentos') ],
            [ 'slug' => 'tao-formula-orc-novo',   'label' => '+ Novo Or&ccedil;amento','url' => cbpm_url('formula-novo-orc') ],
        ],
    ];
}

if ( $has_caixa && function_exists( 'tao_caixa_pode_operar' ) && tao_caixa_pode_operar() ) {
    $nav['config']['subs']['cfg-caixa'] = [
        'label' => 'TAO Caixa',
        'icon'  => '&#x1F4B0;',
        'items' => [
            [ 'slug' => 'tao-caixa-adquirentes', 'label' => 'Operadoras de Cart&atilde;o', 'url' => cbpm_url('caixa-adquirentes') ],
            [ 'slug' => 'tao-caixa-taxas',       'label' => 'Taxas (MDR)',                 'url' => cbpm_url('caixa-taxas') ],
            [ 'slug' => 'tao-caixa-formas',      'label' => 'Formas de Pagamento',         'url' => cbpm_url('caixa-formas') ],
        ],
    ];
    $nav['operacao']['subs']['op-caixa'] = [
        'label' => 'TAO Caixa',
        'icon'  => '&#x1F4B0;',
        'items' => [
            [ 'slug' => 'tao-caixa-dashboard', 'label' => 'Dashboard', 'url' => cbpm_url('caixa') ],
        ],
    ];
}

// Detecta qual grupo/sub contém a página atual (para abrir automaticamente)
$active_group = '';
$active_sub   = '';
foreach ( $nav as $gid => $group ) {
    foreach ( $group['subs'] as $sid => $sub ) {
        foreach ( $sub['items'] as $item ) {
            if ( $item['slug'] === $page_atual ) {
                $active_group = $gid;
                $active_sub   = $sid;
                break 3;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Plataforma de Rob&ocirc;s &mdash; <?php echo esc_html( $secoes[ $page_atual ]['label'] ); ?></title>
    <link rel="stylesheet" href="<?php echo esc_url( CBPM_PLUGIN_URL . 'assets/style.css' ); ?>?v=<?php echo CBPM_VERSION; ?>">
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background: #f0f0f1; color: #1d2327; }
        .cbpm-layout { display: flex; min-height: 100vh; }

        /* ── Sidebar ── */
        .cbpm-sidebar {
            width: 240px; flex-shrink: 0; background: #1d2327; color: #a7aaad;
            display: flex; flex-direction: column; position: sticky; top: 0; height: 100vh; overflow-y: auto;
        }
        .cbpm-sidebar-logo {
            padding: 20px 18px 14px; font-size: 14px; font-weight: 700; color: #fff;
            border-bottom: 1px solid #2c3338; display: flex; align-items: center; gap: 8px; flex-shrink: 0;
        }
        .cbpm-sidebar-logo .icon { font-size: 22px; }
        .cbpm-sidebar nav { padding: 6px 0; flex: 1; }
        .cbpm-sidebar-footer {
            padding: 12px 18px; border-top: 1px solid #2c3338; font-size: 11px; color: #72777c; flex-shrink: 0;
        }
        .cbpm-sidebar-footer a { color: #72777c; text-decoration: none; }
        .cbpm-sidebar-footer a:hover { color: #a7aaad; }

        /* ── Accordion: grupos de 1º nível ── */
        .cbpm-grp-hdr {
            display: flex; align-items: center; gap: 8px;
            padding: 9px 18px; cursor: pointer; user-select: none;
            color: #72777c; font-size: 10px; font-weight: 700;
            text-transform: uppercase; letter-spacing: .8px;
            border-left: 3px solid transparent;
        }
        .cbpm-grp-hdr:hover { color: #c3c4c7; background: rgba(255,255,255,.04); }
        .cbpm-grp-hdr .cbpm-chv { margin-left: auto; font-size: 10px; transition: transform .2s; display: inline-block; }
        .cbpm-grp.open > .cbpm-grp-hdr .cbpm-chv { transform: rotate(90deg); }
        .cbpm-grp-body { display: none; }
        .cbpm-grp.open > .cbpm-grp-body { display: block; }

        /* ── Accordion: subseções de 2º nível ── */
        .cbpm-sub-hdr {
            display: flex; align-items: center; gap: 8px;
            padding: 7px 18px 7px 28px; cursor: pointer; user-select: none;
            color: #a7aaad; font-size: 12px; font-weight: 600;
            border-left: 3px solid transparent;
        }
        .cbpm-sub-hdr:hover { color: #fff; background: rgba(255,255,255,.05); }
        .cbpm-sub-hdr .cbpm-chv { margin-left: auto; font-size: 10px; transition: transform .2s; display: inline-block; }
        .cbpm-sub.open > .cbpm-sub-hdr .cbpm-chv { transform: rotate(90deg); }
        .cbpm-sub-body { display: none; }
        .cbpm-sub.open > .cbpm-sub-body { display: block; }

        /* ── Links de 3º nível ── */
        .cbpm-nav-direct {
            display: flex; align-items: center; gap: 8px;
            padding: 9px 18px; cursor: pointer;
            color: #a7aaad; font-size: 10px; font-weight: 700;
            text-transform: uppercase; letter-spacing: .8px;
            border-left: 3px solid transparent;
            text-decoration: none;
        }
        .cbpm-nav-direct:hover { color: #c3c4c7; background: rgba(255,255,255,.04); }
        .cbpm-nav-direct.active { color: #fff; background: #2271b1; border-left-color: #72aee6; }
        .cbpm-nav-link {
            display: block; padding: 7px 18px 7px 42px;
            color: #a7aaad; text-decoration: none; font-size: 13px;
            border-left: 3px solid transparent; transition: .12s;
        }
        .cbpm-nav-link:hover { color: #fff; background: #2c3338; }
        .cbpm-nav-link.active { color: #fff; background: #2271b1; border-left-color: #72aee6; }

        /* ── Main ── */
        .cbpm-main { flex: 1; padding: 24px 28px; min-width: 0; overflow-x: auto; }
        .cbpm-breadcrumb { font-size: 12px; color: #72777c; margin-bottom: 8px; }
        .cbpm-breadcrumb a { color: #72777c; text-decoration: none; }
        .cbpm-breadcrumb a:hover { color: #2271b1; }

        /* ── Mobile: topbar fixa + drawer lateral ── */
        .cbpm-mobile-topbar {
            display: none;
            align-items: center;
            gap: 12px;
            padding: 0 16px;
            height: 52px;
            background: #1d2327;
            position: sticky;
            top: 0;
            z-index: 200;
            flex-shrink: 0;
        }
        .cbpm-hamburger {
            background: none; border: none; cursor: pointer;
            padding: 6px; color: #c3c4c7;
            display: flex; flex-direction: column; gap: 5px; flex-shrink: 0;
        }
        .cbpm-hamburger span {
            display: block; width: 22px; height: 2px;
            background: currentColor; border-radius: 2px; transition: .2s;
        }
        .cbpm-mobile-title { font-size: 14px; font-weight: 700; color: #fff; flex: 1; }
        .cbpm-backdrop {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,.55); z-index: 299;
        }
        .cbpm-backdrop.open { display: block; }

        @media (max-width: 768px) {
            .cbpm-mobile-topbar { display: flex; }

            /* Layout vertical: topbar + content */
            .cbpm-layout { flex-direction: column; }

            /* Sidebar como drawer oculto */
            .cbpm-sidebar {
                position: fixed;
                top: 0; left: 0;
                width: 240px;
                height: 100vh;
                z-index: 300;
                transform: translateX(-240px);
                transition: transform .25s ease;
                overflow-y: auto;
                overflow-x: hidden;
            }
            .cbpm-sidebar.open { transform: translateX(0); }

            /* Main: largura total, sem margem lateral */
            .cbpm-main {
                padding: 14px;
                overflow-x: hidden;
                min-width: 0;
                width: 100%;
                box-sizing: border-box;
            }
        }

        /* ── Layout geral ── */
        .wrap { max-width: 100%; }
        .cbpm-wrap { width: 100%; box-sizing: border-box; }
        .cbpm-form { width: 100%; }
        .form-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .form-table th { padding: 12px 16px 12px 0; text-align: left; width: 200px; vertical-align: top; font-weight: 600; font-size: 13px; white-space: nowrap; }
        .form-table td { padding: 8px 0; word-wrap: break-word; overflow-wrap: break-word; }
        .form-table .description { color: #646970; font-size: 12px; margin: 4px 0 0; }
        input[type=text], input[type=url], input[type=email], input[type=password],
        input[type=number], input[type=datetime-local], textarea {
            border: 1px solid #8c8f94; border-radius: 4px; padding: 6px 10px;
            font-size: 13px; font-family: inherit; color: #1d2327;
            box-sizing: border-box; width: 100%; max-width: 100%;
        }
        select { border: 1px solid #8c8f94; border-radius: 4px; padding: 6px 10px; font-size: 13px; font-family: inherit; color: #1d2327; box-sizing: border-box; max-width: 100%; }
        input.small-text, input[type=number].small-text { width: 80px !important; }
        input.regular-text, .regular-text { width: 100%; max-width: 100%; }
        input.large-text, textarea.large-text { width: 100%; }
        textarea { resize: vertical; width: 100%; box-sizing: border-box; }
        .button, input[type=submit], button[type=submit] {
            display: inline-block; padding: 6px 16px; border-radius: 3px;
            border: 1px solid #2271b1; background: #fff; color: #2271b1;
            cursor: pointer; font-size: 13px; text-decoration: none; font-family: inherit;
            white-space: nowrap; line-height: 1.4; vertical-align: middle;
        }
        .button:hover, input[type=submit]:hover, button[type=submit]:hover { background: #f0f5fb; }
        .button-primary, input[type=submit].button-primary { background: #2271b1; color: #fff; border-color: #2271b1; }
        .button-primary:hover, input[type=submit].button-primary:hover { background: #135e96; border-color: #135e96; color: #fff; }
        .button-secondary { border-color: #8c8f94; color: #3c434a; background: #fff; }
        .button-secondary:hover { background: #f6f7f7; }
        p.submit { padding: 16px 0 0; margin: 0; }
        .notice { padding: 10px 14px; border-left: 4px solid #72aee6; background: #fff; margin-bottom: 16px; border-radius: 0 4px 4px 0; }
        .notice-success { border-left-color: #00a32a; }
        .notice-error   { border-left-color: #d63638; }
        .notice-info    { border-left-color: #72aee6; }
        .cbpm-table-container { overflow-x: auto; width: 100%; }
        .wp-list-table { width: 100%; border-collapse: collapse; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,.08); min-width: 500px; }
        .wp-list-table thead tr { border-bottom: 2px solid #e2e4e7; }
        .wp-list-table th, .wp-list-table td { padding: 10px 14px; text-align: left; word-wrap: break-word; overflow-wrap: break-word; }
        .wp-list-table th { font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: .4px; color: #50575e; white-space: nowrap; }
        .wp-list-table tbody tr:nth-child(even) { background: #f9f9f9; }
        .wp-list-table tbody tr:hover { background: #f0f5fb; }
        .page-title-action { font-size: 13px; font-weight: 400; padding: 5px 12px; margin-left: 10px; vertical-align: middle; }
        h1 { font-size: 22px; margin: 0 0 16px; word-wrap: break-word; }
        h2 { font-size: 17px; margin: 20px 0 12px; }
        h3 { font-size: 14px; margin: 16px 0 8px; color: #50575e; text-transform: uppercase; letter-spacing: .4px; }
        code { background: #f0f0f0; padding: 2px 5px; border-radius: 3px; font-size: 12px; word-break: break-all; }
        hr { border: none; border-top: 1px solid #e2e4e7; margin: 20px 0; }
        label { cursor: pointer; }
        optgroup { font-style: normal; font-weight: 600; }
        .submit { padding: 16px 0 0; }
        .cbpm-filters { margin: 12px 0 16px; display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
        .cbpm-filters select, .cbpm-filters input[type=text] { width: auto; }

        /* ── Chat (histórico de conversas) ── */
        .cbpm-chat { width: 100%; max-height: 65vh; overflow-y: auto; border: 1px solid #e2e4e7; background: #f0f0f1; padding: 16px; border-radius: 6px; margin-top: 16px; box-sizing: border-box; }
        .cbpm-msg { margin: 10px 0; display: flex; flex-direction: column; }
        .cbpm-msg-user  { align-items: flex-end; }
        .cbpm-msg-bot   { align-items: flex-start; }
        .cbpm-msg-agent { align-items: flex-end; }
        .cbpm-msg-bubble { max-width: 75%; padding: 8px 12px; border-radius: 12px; font-size: 13px; line-height: 1.5; word-wrap: break-word; overflow-wrap: break-word; white-space: pre-wrap; }
        .cbpm-msg-user  .cbpm-msg-bubble { background: #2271b1; color: #fff; border-bottom-right-radius: 3px; }
        .cbpm-msg-bot   .cbpm-msg-bubble { background: #e1ecf4; color: #1d2327; border: 1px solid #c8dde9; border-bottom-left-radius: 3px; }
        .cbpm-msg-agent .cbpm-msg-bubble { background: #00a32a; color: #fff; border-bottom-right-radius: 3px; }
        .cbpm-msg-meta { font-size: 11px; color: #72777c; margin-top: 3px; }
    </style>
    <?php if ( in_array( $fn, [ 'tao_crm_page_kanban_full', 'tao_crm_page_inbox', 'tao_crm_page_settings' ], true ) && defined( 'TAO_CRM_URL' ) ): ?>
    <link rel="stylesheet" href="<?php echo esc_url( TAO_CRM_URL . 'assets/crm-style.css' ); ?>?v=<?php echo TAO_CRM_VERSION; ?>">
    <?php endif; ?>
    <?php if ( strpos( $fn, 'tao_formula_' ) === 0 && defined( 'TAOF_PLUGIN_URL' ) ): ?>
    <link rel="stylesheet" href="<?php echo esc_url( TAOF_PLUGIN_URL . 'assets/formula-style.css' ); ?>?v=<?php echo TAOF_VERSION; ?>">
    <?php endif; ?>
</head>
<body>
<?php
$_mobile_label = $secoes[$page_atual]['label'] ?? 'Portal';
?>
<div class="cbpm-mobile-topbar">
    <button class="cbpm-hamburger" id="cbpmBurger" aria-label="Abrir menu">
        <span></span><span></span><span></span>
    </button>
    <span class="cbpm-mobile-title"><?php echo esc_html($_mobile_label); ?></span>
</div>
<div class="cbpm-backdrop" id="cbpmBackdrop"></div>
<div class="cbpm-layout">

    <aside class="cbpm-sidebar">
        <div class="cbpm-sidebar-logo">
            <span class="icon">&#x1F916;</span>
            <span class="cbpm-logo-text">Plataforma Rob&ocirc;s</span>
        </div>
        <nav>
        <?php
        $home_active = ($page_atual === 'chatbot-platform') ? ' active' : '';
        ?>
        <a href="<?php echo esc_url( cbpm_url('dashboard') ); ?>" class="cbpm-nav-direct<?php echo $home_active; ?>">
            <span>&#x1F3E0;</span>
            <span class="label">Vis&atilde;o Geral</span>
        </a>
        <?php foreach ( $nav as $gid => $group ):
            $g_open = ( $active_group === $gid ) ? ' open' : '';
        ?>
            <div class="cbpm-grp<?php echo $g_open; ?>" data-grp="<?php echo esc_attr( $gid ); ?>">
                <div class="cbpm-grp-hdr">
                    <span><?php echo $group['icon']; ?></span>
                    <span class="label"><?php echo $group['label']; ?></span>
                    <span class="cbpm-chv">&#x276F;</span>
                </div>
                <div class="cbpm-grp-body">
                <?php foreach ( $group['subs'] as $sid => $sub ):
                    $s_open = ( $active_sub === $sid ) ? ' open' : '';
                ?>
                    <div class="cbpm-sub<?php echo $s_open; ?>" data-sub="<?php echo esc_attr( $sid ); ?>">
                        <div class="cbpm-sub-hdr">
                            <span><?php echo $sub['icon']; ?></span>
                            <span class="label"><?php echo $sub['label']; ?></span>
                            <span class="cbpm-chv">&#x276F;</span>
                        </div>
                        <div class="cbpm-sub-body">
                        <?php foreach ( $sub['items'] as $item ):
                            $cls = ( $item['slug'] === $page_atual ) ? ' active' : '';
                        ?>
                            <a href="<?php echo esc_url( $item['url'] ); ?>" class="cbpm-nav-link<?php echo $cls; ?>"><?php echo $item['label']; ?></a>
                        <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
        </nav>
        <div class="cbpm-sidebar-footer">
            <?php echo esc_html( wp_get_current_user()->display_name ); ?><br>
            <a href="<?php echo esc_url( wp_logout_url( home_url('/robos/') ) ); ?>">Sair</a>
            &nbsp;&middot;&nbsp;
            <a href="<?php echo esc_url( admin_url() ); ?>">wp-admin</a>
        </div>
    </aside>

    <main class="cbpm-main">
        <div class="cbpm-breadcrumb">
            <a href="<?php echo esc_url( cbpm_url() ); ?>">Plataforma Rob&ocirc;s</a>
            <?php if ( $page_atual !== 'chatbot-platform' ): ?>
                &rsaquo; <?php echo esc_html( $secoes[ $page_atual ]['label'] ); ?>
            <?php endif; ?>
        </div>
        <?php
        if ( function_exists( $fn ) ) {
            call_user_func( $fn );
        } else {
            echo '<p>P&aacute;gina n&atilde;o encontrada.</p>';
        }
        ?>
    </main>
</div>

<script src="<?php echo esc_url( includes_url('js/jquery/jquery.min.js') ); ?>"></script>
<script>
window.cbpm = {
    ajax_url:     "<?php echo esc_js( admin_url('admin-ajax.php') ); ?>",
    nonce:        "<?php echo esc_js( wp_create_nonce('cbpm_nonce') ); ?>",
    supabase_url: "<?php echo esc_js( $supabase_url ); ?>",
    supabase_key: "<?php echo esc_js( $supabase_key ); ?>"
};
// ── Accordion ──
(function(){
    // Restaura estado salvo (só para grupos/subs sem item ativo — esses já estão abertos pelo PHP)
    document.querySelectorAll('.cbpm-grp').forEach(function(g){
        var id = g.dataset.grp;
        if (!g.classList.contains('open') && localStorage.getItem('cg:'+id)==='1') g.classList.add('open');
        g.querySelector('.cbpm-grp-hdr').addEventListener('click', function(){
            g.classList.toggle('open');
            localStorage.setItem('cg:'+id, g.classList.contains('open')?'1':'0');
        });
    });
    document.querySelectorAll('.cbpm-sub').forEach(function(s){
        var id = s.dataset.sub;
        if (!s.classList.contains('open') && localStorage.getItem('cs:'+id)==='1') s.classList.add('open');
        s.querySelector('.cbpm-sub-hdr').addEventListener('click', function(e){
            e.stopPropagation();
            s.classList.toggle('open');
            localStorage.setItem('cs:'+id, s.classList.contains('open')?'1':'0');
        });
    });
})();
// ── Mobile drawer ──
(function(){
    var sidebar  = document.querySelector('.cbpm-sidebar');
    var backdrop = document.getElementById('cbpmBackdrop');
    var burger   = document.getElementById('cbpmBurger');
    if (!burger || !sidebar) return;
    function open()  { sidebar.classList.add('open');  backdrop.classList.add('open');  document.body.style.overflow='hidden'; }
    function close() { sidebar.classList.remove('open'); backdrop.classList.remove('open'); document.body.style.overflow=''; }
    burger.addEventListener('click', function(){ sidebar.classList.contains('open') ? close() : open(); });
    backdrop.addEventListener('click', close);
    sidebar.querySelectorAll('.cbpm-nav-link, .cbpm-nav-direct').forEach(function(l){ l.addEventListener('click', close); });
})();
</script>
<script src="<?php echo esc_url( CBPM_PLUGIN_URL . 'assets/script.js' ); ?>?v=<?php echo CBPM_VERSION; ?>"></script>
<?php if ( strpos( $fn, 'tao_formula_' ) === 0 && defined( 'TAOF_PLUGIN_URL' ) ): ?>
<script>
window.taoFormula = <?php echo wp_json_encode( [
    'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
    'nonce'       => wp_create_nonce( 'tao_formula_nonce' ),
    'supabaseUrl' => tao_formula_supabase_url(),
    'supabaseKey' => tao_formula_supabase_key(),
] ); ?>;
</script>
<script src="<?php echo esc_url( TAOF_PLUGIN_URL . 'assets/formula-script.js' ); ?>?v=<?php echo TAOF_VERSION; ?>"></script>
<?php if ( $fn === 'tao_formula_page_orcamento_novo' ): ?>
<script src="<?php echo esc_url( TAOF_PLUGIN_URL . 'assets/formula-orc.js' ); ?>?v=<?php echo TAOF_VERSION; ?>"></script>
<?php endif; ?>
<?php endif; ?>
<?php if ( in_array( $fn, [ 'tao_crm_page_kanban_full', 'tao_crm_page_inbox', 'tao_crm_page_settings' ], true ) && defined( 'TAO_CRM_URL' ) ): ?>
<script>
window.taoCrm = <?php echo wp_json_encode( [
    'ajax_url'     => admin_url( 'admin-ajax.php' ),
    'nonce'        => wp_create_nonce( 'tao_crm_nonce' ),
    'supabase_url' => function_exists( 'cbpm_supabase_url' ) ? cbpm_supabase_url() : get_option( 'cbpm_supabase_url', '' ),
    'supabase_key' => function_exists( 'cbpm_supabase_key' ) ? cbpm_supabase_key() : get_option( 'cbpm_supabase_key', '' ),
    'card_base_url'=> cbpm_url( 'crm-kanban', [ 'action' => 'card', 'id' => '' ] ),
    'adminUrl'     => admin_url(),
] ); ?>;
</script>
<script src="<?php echo esc_url( TAO_CRM_URL . 'assets/crm-script.js' ); ?>?v=<?php echo TAO_CRM_VERSION; ?>"></script>
<?php endif; ?>
</body>
</html>
