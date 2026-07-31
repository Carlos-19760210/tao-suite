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
    'chatbot-platform-settings'       => [ 'fn' => 'cbpm_page_settings',          'label' => 'Plataforma' ],
    'chatbot-platform-dashboard'      => [ 'fn' => 'cbpm_page_dashboard',         'label' => 'Dashboard' ],
    'chatbot-platform-neo-dashboard'  => [ 'fn' => 'cbpm_page_neo_dashboard',     'label' => 'Painel Neo' ],
];
$has_crm = function_exists( 'tao_crm_page_kanban_full' );
if ( $has_crm ) {
    $secoes['tao-crm-dashboard'] = [ 'fn' => 'tao_crm_page_dashboard',  'label' => 'Dashboard CRM' ];
    if ( function_exists( 'tao_crm_page_analise' ) )
        $secoes['tao-crm-analise'] = [ 'fn' => 'tao_crm_page_analise',  'label' => 'Análise' ];
    $secoes['tao-crm-kanban']   = [ 'fn' => 'tao_crm_page_kanban_full', 'label' => 'CRM Kanban' ];
    $secoes['tao-crm-inbox']    = [ 'fn' => 'tao_crm_page_inbox',       'label' => 'CRM Inbox' ];
    $secoes['tao-crm-contatos'] = [ 'fn' => 'tao_crm_page_contatos',    'label' => 'Contatos' ];
    $secoes['tao-crm-settings'] = [ 'fn' => 'tao_crm_page_settings',    'label' => 'CRM Configurações' ];
    if ( function_exists( 'tao_crm_page_perfis' ) ) {
        $secoes['tao-crm-perfis'] = [ 'fn' => 'tao_crm_page_perfis', 'label' => 'Perfis de Acesso' ];
    }
}

$has_formula = function_exists( 'tao_formula_page_dashboard' );
if ( $has_formula ) {
    $secoes['tao-formula']           = [ 'fn' => 'tao_formula_page_dashboard',      'label' => 'Fórmulas' ];
    $secoes['tao-formula-orcamentos']= [ 'fn' => 'tao_formula_page_orcamentos',     'label' => 'Orçamentos' ];
    $secoes['tao-formula-orc-novo']  = [ 'fn' => 'tao_formula_page_orcamento_novo', 'label' => 'Novo Orçamento' ];
    $secoes['tao-formula-historico'] = [ 'fn' => 'tao_formula_page_historico',      'label' => 'Histórico do Cliente' ];
    $secoes['tao-formula-prescritores'] = [ 'fn' => 'tao_formula_page_prescritores', 'label' => 'Prescritores' ];
    $secoes['tao-formula-fornecedores'] = [ 'fn' => 'tao_formula_page_fornecedores', 'label' => 'Fornecedores' ];
    $secoes['tao-formula-estoque-nf']   = [ 'fn' => 'tao_formula_page_estoque_nf',   'label' => 'Estoque — Entrada NF' ];
    $secoes['tao-formula-estoque-lotes']= [ 'fn' => 'tao_formula_page_estoque_lotes','label' => 'Estoque — Lotes' ];
    $secoes['tao-formula-estoque-inventario']= [ 'fn' => 'tao_formula_page_estoque_inventario','label' => 'Estoque — Inventário' ];
    $secoes['tao-formula-estoque-repo'] = [ 'fn' => 'tao_formula_page_estoque_reposicao','label' => 'Estoque — Reposição' ];
    $secoes['tao-formula-producao']     = [ 'fn' => 'tao_formula_page_producao',        'label' => 'Produção' ];
    $secoes['tao-formula-producao-interna'] = [ 'fn' => 'tao_formula_page_producao_interna', 'label' => 'Produção Interna' ];
    $secoes['tao-formula-livro']        = [ 'fn' => 'tao_formula_page_livro_receituario','label' => 'Livro de Receituário' ];
    $secoes['tao-formula-contas-pagar'] = [ 'fn' => 'tao_formula_page_contas_pagar',     'label' => 'Contas a Pagar' ];
    $secoes['tao-formula-sngpc']        = [ 'fn' => 'tao_formula_page_sngpc',            'label' => 'Controlados / SNGPC' ];
    $secoes['tao-formula-formas']    = [ 'fn' => 'tao_formula_page_formas',         'label' => 'Formas Farmacêuticas' ];
    $secoes['tao-formula-ativos']    = [ 'fn' => 'tao_formula_page_ativos',         'label' => 'Ativos' ];
    $secoes['tao-formula-config']    = [ 'fn' => 'tao_formula_page_config',         'label' => 'Fórmulas — Configurações' ];
}

$has_caixa = function_exists( 'tao_caixa_page_dashboard' );
if ( $has_caixa ) {
    $secoes['tao-caixa-dashboard']   = [ 'fn' => 'tao_caixa_page_dashboard',    'label' => 'Caixa' ];
    $secoes['tao-caixa-vendas']      = [ 'fn' => 'tao_caixa_page_vendas',       'label' => 'Caixa — Vendas' ];
    $secoes['tao-caixa-sessao']      = [ 'fn' => 'tao_caixa_page_sessao',       'label' => 'Caixa — Sessão' ];
    $secoes['tao-caixa-conciliacao'] = [ 'fn' => 'tao_caixa_page_conciliacao',  'label' => 'Caixa — Conciliação' ];
    $secoes['tao-caixa-adquirentes'] = [ 'fn' => 'tao_caixa_page_adquirentes',  'label' => 'Caixa — Operadoras de Cartão' ];
    $secoes['tao-caixa-taxas']       = [ 'fn' => 'tao_caixa_page_taxas',        'label' => 'Caixa — Taxas (MDR)' ];
    $secoes['tao-caixa-formas']      = [ 'fn' => 'tao_caixa_page_formas_pgto',  'label' => 'Caixa — Formas de Pagamento' ];
}

$has_entregas = function_exists( 'tao_entregas_page_painel' );
if ( $has_entregas ) {
    $secoes['tao-entregas-painel'] = [ 'fn' => 'tao_entregas_page_painel', 'label' => 'Entregas' ];
}

$has_cotacoes = function_exists( 'tao_cotacoes_page_lista' );
if ( $has_cotacoes ) {
    $secoes['tao-cotacoes']              = [ 'fn' => 'tao_cotacoes_page_lista',        'label' => 'Cotações' ];
    $secoes['tao-cotacoes-nova']         = [ 'fn' => 'tao_cotacoes_page_nova',         'label' => 'Nova Cotação' ];
    $secoes['tao-cotacoes-fornecedores'] = [ 'fn' => 'tao_cotacoes_page_fornecedores', 'label' => 'Cotações — Fornecedores' ];
}

$page_atual = $_GET['page'] ?? 'chatbot-platform';
// O plugin mapeia slug 'negocios' → 'chatbot-platform', mas queremos Negócios e não Visão Geral
if ( get_query_var( 'cbpm_page', '' ) === 'negocios' ) $page_atual = 'chatbot-platform-negocios';
if ( ! isset( $secoes[ $page_atual ] ) ) $page_atual = 'chatbot-platform';
$fn = $secoes[ $page_atual ]['fn'] ?? 'cbpm_page_clientes';

// ─── Perfis de Acesso (tao-crm/includes/perfis.php): tela oculta p/ o perfil → home ──
$tem_perfis = function_exists( 'tao_crm_tela_da_secao' ) && function_exists( 'tao_crm_tela_oculta' );
if ( $tem_perfis ) {
    $tela_gate = tao_crm_tela_da_secao( $page_atual );
    if ( $tela_gate && tao_crm_tela_oculta( $tela_gate ) ) {
        $page_atual = 'chatbot-platform';
        $fn = $secoes['chatbot-platform']['fn'];
    }
}

// ─── Estrutura do menu accordion ─────────────────────────────────────────────
// Reorg por MÓDULO (Jun 2026): cada módulo 1× no topo; toda config recolhida em "Configurações".
// Slugs/rotas/chaves de itens preservados — só muda agrupamento/ordem/rótulo.
$nav = [];

// 📞 Neo
$nav['neo'] = [
    'label' => 'Agente',
    'icon'  => '&#x1F4DE;',
    'items' => [
        [ 'slug' => 'chatbot-platform-neo-dashboard', 'label' => 'Painel',                       'url' => cbpm_url('neo-dashboard') ],
        [ 'slug' => 'chatbot-platform-pedidos',   'label' => 'Pedidos',                         'url' => cbpm_url('pedidos') ],
        [ 'slug' => 'chatbot-platform-leads',     'label' => 'Leads',                           'url' => cbpm_url('leads') ],
        [ 'slug' => 'chatbot-platform-historico', 'label' => 'Hist&oacute;rico',                'url' => cbpm_url('historico') ],
        [ 'slug' => 'chatbot-platform-conteudo',  'label' => 'Promo&ccedil;&otilde;es/Avisos',  'url' => cbpm_url('conteudo') ],
    ],
];

// 🎯 CRM
if ( $has_crm ) {
    $nav['crm'] = [
        'label' => 'CRM',
        'icon'  => '&#x1F3AF;',
        'items' => array_values( array_filter( [
            [ 'slug' => 'tao-crm-dashboard', 'label' => 'Painel',   'url' => cbpm_url('crm-dashboard') ],
            ( function_exists( 'tao_crm_is_gestor' ) && tao_crm_is_gestor() )
                ? [ 'slug' => 'tao-crm-analise', 'label' => 'Análise', 'url' => cbpm_url('crm-analise') ] : null,
            [ 'slug' => 'tao-crm-kanban',    'label' => 'Kanban',   'url' => cbpm_url('crm-kanban') ],
        ] ) ),
    ];
}

// 📇 Cadastros (transversal) — cliente único, prescritores, fornecedores, ativos, formas
$cad_items = [];
if ( $has_crm )     $cad_items[] = [ 'slug' => 'tao-crm-contatos', 'label' => 'Clientes / Contatos', 'url' => cbpm_url('crm-contatos') ];
if ( $has_formula ) {
    $cad_items[] = [ 'slug' => 'tao-formula-prescritores', 'label' => 'Prescritores',           'url' => cbpm_url('formula-prescritores') ];
    // Fornecedor é CADASTRO ÚNICO: Fórmula e Cotações usam a MESMA tabela `fornecedores`.
    // A tela do Fórmula é a completa (fiscal + licenças AFE/AE/VISA + qualificação RDC 67), então é a única exibida quando há Fórmula.
    $cad_items[] = [ 'slug' => 'tao-formula-fornecedores', 'label' => 'Fornecedores',           'url' => cbpm_url('formula-fornecedores') ];
    $cad_items[] = [ 'slug' => 'tao-formula-ativos',       'label' => 'Ativos',                 'url' => cbpm_url('formula-ativos') ];
    $cad_items[] = [ 'slug' => 'tao-formula-formas',       'label' => 'Formas Farmac&ecirc;uticas', 'url' => cbpm_url('formula-formas') ];
} elseif ( $has_cotacoes ) {
    // Sem Fórmula: a tela de Fornecedores das Cotações atende (mesma tabela `fornecedores`).
    $cad_items[] = [ 'slug' => 'tao-cotacoes-fornecedores', 'label' => 'Fornecedores', 'url' => cbpm_url('cotacoes-fornecedores') ];
}
if ( $cad_items ) {
    $nav['cadastros'] = [ 'label' => 'Cadastros', 'icon' => '&#x1F4C7;', 'items' => $cad_items ];
}

// 📦 Estoque (transversal — mesmo nível de Cadastros)
if ( $has_formula ) {
    $nav['estoque'] = [
        'label' => 'Estoque',
        'icon'  => '&#x1F4E6;',
        'items' => [
            [ 'slug' => 'tao-formula-estoque-nf',         'label' => 'Entrada NF',      'url' => cbpm_url('formula-estoque-nf') ],
            [ 'slug' => 'tao-formula-estoque-lotes',      'label' => 'Lotes',           'url' => cbpm_url('formula-estoque-lotes') ],
            [ 'slug' => 'tao-formula-estoque-inventario', 'label' => 'Invent&aacute;rio', 'url' => cbpm_url('formula-estoque-inventario') ],
            [ 'slug' => 'tao-formula-estoque-repo',       'label' => 'Reposi&ccedil;&atilde;o', 'url' => cbpm_url('formula-estoque-repo') ],
        ],
    ];
}

// 📣 Campanhas — módulo de topo, entrada SIMPLES (sem drill-down)
$nav['campanhas'] = [
    'label' => 'Campanhas',
    'icon'  => '&#x1F4E3;',
    'slug'  => 'chatbot-platform-campanhas',
    'url'   => cbpm_url('campanhas'),
];

// 🧪 Fórmulas
if ( $has_formula ) {
    $nav['formula'] = [
        'label' => 'F&oacute;rmulas',
        'icon'  => '&#x1F9EA;',
        'items' => [
            [ 'slug' => 'tao-formula',            'label' => 'Painel',                 'url' => cbpm_url('formula-dashboard') ],
            [ 'slug' => 'tao-formula-orcamentos', 'label' => 'Or&ccedil;amentos',      'url' => cbpm_url('formula-orcamentos') ],
            [ 'slug' => 'tao-formula-orc-novo',   'label' => 'Novo Or&ccedil;amento',  'url' => cbpm_url('formula-novo-orc') ],
            [ 'slug' => 'tao-formula-historico',  'label' => 'Hist&oacute;rico',       'url' => cbpm_url('formula-historico') ],
            [ 'slug' => 'tao-formula-producao', 'label' => 'Produ&ccedil;&atilde;o', 'url' => cbpm_url('formula-producao') ],
            [ 'slug' => 'tao-formula-producao-interna', 'label' => 'Produ&ccedil;&atilde;o Interna', 'url' => cbpm_url('formula-producao-interna') ],
            [ 'slug' => 'tao-formula-livro', 'label' => 'Livro de Receitu&aacute;rio', 'url' => cbpm_url('formula-livro') ],
            [ 'slug' => 'tao-formula-sngpc', 'label' => 'Controlados / SNGPC', 'url' => cbpm_url('formula-sngpc') ],
        ],
    ];
}

// 💰 Financeiro — Caixa (sub-menu) + Contas a Pagar (sub-menu)
$fin_subs = [];
if ( $has_caixa && function_exists( 'tao_caixa_pode_operar' ) && tao_caixa_pode_operar() ) {
    $fin_subs['fin-caixa'] = [
        'label' => 'Caixa',
        'icon'  => '&#x1F4B5;',
        'items' => [
            [ 'slug' => 'tao-caixa-dashboard',   'label' => 'Painel',                      'url' => cbpm_url('caixa') ],
            [ 'slug' => 'tao-caixa-vendas',      'label' => 'Vendas',                      'url' => cbpm_url('caixa-vendas') ],
            [ 'slug' => 'tao-caixa-sessao',      'label' => 'Sess&atilde;o / Fechamento',  'url' => cbpm_url('caixa-sessao') ],
            [ 'slug' => 'tao-caixa-conciliacao', 'label' => 'Concilia&ccedil;&atilde;o',   'url' => cbpm_url('caixa-conciliacao') ],
        ],
    ];
}
if ( $has_formula ) {
    $fin_subs['fin-pagar'] = [
        'label' => 'Contas a Pagar',
        'icon'  => '&#x1F4C4;',
        'items' => [
            [ 'slug' => 'tao-formula-contas-pagar', 'label' => 'Contas a Pagar', 'url' => cbpm_url('formula-contas-pagar') ],
        ],
    ];
}
if ( $fin_subs ) {
    $nav['financeiro'] = [ 'label' => 'Financeiro', 'icon' => '&#x1F4B0;', 'subs' => $fin_subs ];
}

// 🚚 Entregas
if ( $has_entregas ) {
    $nav['entregas'] = [
        'label' => 'Entregas',
        'icon'  => '&#x1F69A;',
        'items' => [
            [ 'slug' => 'tao-entregas-painel', 'label' => 'Painel', 'url' => cbpm_url('entregas') ],
        ],
    ];
}

// 📋 Cotações (compras)
if ( $has_cotacoes && function_exists( 'tao_cot_pode' ) && tao_cot_pode() ) {
    $nav['cotacoes'] = [
        'label' => 'Cota&ccedil;&otilde;es',
        'icon'  => '&#x1F4CB;',
        'items' => [
            [ 'slug' => 'tao-cotacoes',      'label' => 'Cota&ccedil;&otilde;es',      'url' => cbpm_url('cotacoes') ],
            [ 'slug' => 'tao-cotacoes-nova', 'label' => 'Nova Cota&ccedil;&atilde;o',  'url' => cbpm_url('cotacoes-nova') ],
        ],
    ];
}

// ⚙️ Configurações — recolhe toda a configuração (sub-grupos por módulo)
$cfg_subs = [];
$cfg_subs['cfg-geral'] = [
    'label' => 'Geral',
    'icon'  => '&#x1F3E2;',
    'items' => [
        [ 'slug' => 'chatbot-platform-negocios',  'label' => 'Neg&oacute;cios',            'url' => cbpm_url('negocios') ],
        [ 'slug' => 'chatbot-platform-categorias','label' => 'Categorias',                 'url' => cbpm_url('categorias') ],
        [ 'slug' => 'chatbot-platform-usuarios',  'label' => 'Usu&aacute;rios',            'url' => cbpm_url('usuarios') ],
        [ 'slug' => 'tao-crm-perfis',             'label' => 'Perfis de Acesso',           'url' => cbpm_url('crm-perfis') ],
        [ 'slug' => 'chatbot-platform-conectores','label' => 'Conectores',                 'url' => cbpm_url('conectores') ],
        [ 'slug' => 'chatbot-platform-settings',  'label' => 'Plataforma','url' => cbpm_url('configuracoes') ],
    ],
];
$cfg_subs['cfg-taon'] = [
    'label' => 'Agente',
    'icon'  => '&#x1F4DE;',
    'items' => [
        [ 'slug' => 'chatbot-platform-catalogo',       'label' => 'Cat&aacute;logo',     'url' => cbpm_url('catalogo') ],
        [ 'slug' => 'chatbot-platform-disponibilidade','label' => 'Disponibilidade',     'url' => cbpm_url('disponibilidade') ],
        [ 'slug' => 'chatbot-platform-campos-extras',  'label' => 'Campos Extras',       'url' => cbpm_url('campos-extras') ],
        [ 'slug' => 'chatbot-platform-listas',         'label' => 'Listas de Contatos',  'url' => cbpm_url('listas') ],
    ],
];
if ( $has_formula ) {
    $cfg_subs['cfg-formula'] = [
        'label' => 'F&oacute;rmulas',
        'icon'  => '&#x1F9EA;',
        'items' => [
            [ 'slug' => 'tao-formula-config', 'label' => 'Configura&ccedil;&otilde;es', 'url' => cbpm_url('formula-config') ],
        ],
    ];
}
if ( $has_caixa && function_exists( 'tao_caixa_pode_operar' ) && tao_caixa_pode_operar() ) {
    $cfg_subs['cfg-caixa'] = [
        'label' => 'Caixa',
        'icon'  => '&#x1F4B0;',
        'items' => [
            [ 'slug' => 'tao-caixa-adquirentes', 'label' => 'Operadoras de Cart&atilde;o', 'url' => cbpm_url('caixa-adquirentes') ],
            [ 'slug' => 'tao-caixa-taxas',       'label' => 'Taxas (MDR)',                 'url' => cbpm_url('caixa-taxas') ],
            [ 'slug' => 'tao-caixa-formas',      'label' => 'Formas de Pagamento',         'url' => cbpm_url('caixa-formas') ],
        ],
    ];
}
// Config→Cotações removido: o cadastro de Fornecedores (compras) foi para o grupo Cadastros.
if ( $has_crm ) {
    $cfg_subs['cfg-crm'] = [
        'label' => 'CRM',
        'icon'  => '&#x1F3AF;',
        'items' => [
            [ 'slug' => 'tao-crm-settings', 'label' => 'Configura&ccedil;&otilde;es', 'url' => cbpm_url('crm-settings') ],
        ],
    ];
}
// Perfis de Acesso é do SISTEMA (não do CRM): fica em Config→Geral junto de Usuários,
// e só para administrador. Remove da lista Geral se não for admin ou sem o módulo.
if ( ! ( current_user_can( 'manage_options' ) && function_exists( 'tao_crm_page_perfis' ) ) ) {
    $cfg_subs['cfg-geral']['items'] = array_values( array_filter( $cfg_subs['cfg-geral']['items'], function ( $it ) {
        return ( $it['slug'] ?? '' ) !== 'tao-crm-perfis';
    } ) );
}
$nav['config'] = [
    'label' => 'Configura&ccedil;&otilde;es',
    'icon'  => '&#x2699;&#xFE0F;',
    'subs'  => $cfg_subs,
];

// ─── Perfis de Acesso: esconde do menu itens/grupos cuja tela está oculta ────
if ( $tem_perfis ) {
    $tp_visivel = function ( $it ) {
        $t = tao_crm_tela_da_secao( $it['slug'] ?? '' );
        return ! $t || ! tao_crm_tela_oculta( $t );
    };
    foreach ( $nav as $gk => $g ) {
        if ( isset( $g['slug'] ) && ! isset( $g['items'] ) && ! isset( $g['subs'] ) ) {   // entrada direta (ex.: Campanhas)
            if ( ! $tp_visivel( $g ) ) unset( $nav[ $gk ] );
            continue;
        }
        if ( ! empty( $g['items'] ) ) {
            $nav[ $gk ]['items'] = array_values( array_filter( $g['items'], $tp_visivel ) );
            if ( empty( $nav[ $gk ]['items'] ) && empty( $g['subs'] ) ) { unset( $nav[ $gk ] ); continue; }
        }
        if ( ! empty( $g['subs'] ) ) {
            foreach ( $g['subs'] as $sk => $s ) {
                $nav[ $gk ]['subs'][ $sk ]['items'] = array_values( array_filter( $s['items'] ?? [], $tp_visivel ) );
                if ( empty( $nav[ $gk ]['subs'][ $sk ]['items'] ) ) unset( $nav[ $gk ]['subs'][ $sk ] );
            }
            if ( empty( $nav[ $gk ]['subs'] ) && empty( $nav[ $gk ]['items'] ) ) unset( $nav[ $gk ] );
        }
    }
}

// Detecta grupo/sub do item ativo (auto-expande no load) — suporta módulo (itens diretos),
// grupo Configurações (subs) e entrada direta (Campanhas, sem itens).
$active_group = '';
$active_sub   = '';
foreach ( $nav as $gid => $entry ) {
    if ( isset( $entry['url'] ) ) continue; // entrada direta (Campanhas)
    if ( isset( $entry['subs'] ) ) {
        foreach ( $entry['subs'] as $sid => $sub ) {
            foreach ( $sub['items'] as $item ) {
                if ( $item['slug'] === $page_atual ) { $active_group = $gid; $active_sub = $sid; break 3; }
            }
        }
    } else {
        foreach ( $entry['items'] as $item ) {
            if ( $item['slug'] === $page_atual ) { $active_group = $gid; break 2; }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TAO Neo &mdash; <?php echo esc_html( $secoes[ $page_atual ]['label'] ); ?></title>
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
        #cbpm-sb-collapse { margin-left:auto; background:none; border:none; color:#a7aaad; cursor:pointer; font-size:15px; padding:2px 7px; border-radius:4px; line-height:1; }
        #cbpm-sb-collapse:hover { color:#fff; background:rgba(255,255,255,.08); }
        .cbpm-sidebar { transition: margin-left .2s ease; }
        #cbpm-sb-open { position:fixed; top:12px; left:12px; z-index:200; background:#1d2327; color:#fff; border:none; border-radius:6px; width:36px; height:36px; cursor:pointer; font-size:17px; display:none; box-shadow:0 2px 8px rgba(0,0,0,.2); }
        body.cbpm-sb-off .cbpm-sidebar { margin-left:-240px; }
        body.cbpm-sb-off #cbpm-sb-open { display:block; }
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
            /* Células não quebram → a tabela rola dentro do .cbpm-tscroll (envolvido via JS) */
            .cbpm-main table:not(.form-table){ min-width:0 !important; }
            .cbpm-main table:not(.form-table) td, .cbpm-main table:not(.form-table) th{ white-space:nowrap; }
            /* Quadro de totais do orçamento: cabe na tela (rótulo quebra, valor à direita visível) */
            .taof-totais-table{ width:100% !important; }
            .taof-totais-table td{ white-space:normal !important; }
            .taof-totais-table .taof-res-val{ white-space:nowrap !important; text-align:right; }
            /* Card: cabeçalho das seções (Itens/Orçamentos) com botões quebra linha */
            .crm-itens-header{ flex-wrap:wrap; gap:6px; }
            #taof-sim-range{ flex:1 1 80px; min-width:70px; width:auto !important; }
            #taof-sim-aplicar{ margin-left:0 !important; }
            /* Formulários label|campo empilham (label em cima, campo full-width embaixo) */
            .form-table, .form-table tbody, .form-table tr, .form-table th, .form-table td{ display:block !important; width:100% !important; }
            .form-table th{ width:auto !important; white-space:normal !important; padding:10px 0 3px !important; }
            .form-table td{ padding:0 0 12px !important; }
        }

        /* ── Layout geral ── */
        .wrap { max-width: 100%; }
        .cbpm-tscroll { overflow-x: auto; -webkit-overflow-scrolling: touch; max-width: 100%; }
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
    <script src="<?php echo esc_url( includes_url('js/jquery/jquery.min.js') ); ?>"></script>
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

    <button type="button" id="cbpm-sb-open" title="Abrir menu" aria-label="Abrir menu">&#x2630;</button>
    <aside class="cbpm-sidebar">
        <div class="cbpm-sidebar-logo">
            <span class="icon">&#x1F916;</span>
            <span class="cbpm-logo-text">TAO Neo</span>
            <button type="button" id="cbpm-sb-collapse" title="Recolher menu" aria-label="Recolher menu">&#x276E;</button>
        </div>
        <nav>
        <?php
        $home_active = ($page_atual === 'chatbot-platform') ? ' active' : '';
        ?>
        <a href="<?php echo esc_url( cbpm_url('dashboard') ); ?>" class="cbpm-nav-direct<?php echo $home_active; ?>">
            <span>&#x1F3E0;</span>
            <span class="label">Vis&atilde;o Geral</span>
        </a>
        <?php foreach ( $nav as $gid => $entry ):
            // Entrada direta de topo (Campanhas) — sem drill-down
            if ( isset( $entry['url'] ) ):
                $d_active = ( ( $entry['slug'] ?? '' ) === $page_atual ) ? ' active' : '';
        ?>
            <a href="<?php echo esc_url( $entry['url'] ); ?>" class="cbpm-nav-direct<?php echo $d_active; ?>">
                <span><?php echo $entry['icon']; ?></span>
                <span class="label"><?php echo $entry['label']; ?></span>
            </a>
        <?php
                continue;
            endif;
            $g_open = ( $active_group === $gid ) ? ' open' : '';
        ?>
            <div class="cbpm-grp<?php echo $g_open; ?>" data-grp="<?php echo esc_attr( $gid ); ?>">
                <div class="cbpm-grp-hdr">
                    <span><?php echo $entry['icon']; ?></span>
                    <span class="label"><?php echo $entry['label']; ?></span>
                    <span class="cbpm-chv">&#x276F;</span>
                </div>
                <div class="cbpm-grp-body">
                <?php if ( isset( $entry['subs'] ) ): // Configurações: subs → itens (3 níveis) ?>
                    <?php foreach ( $entry['subs'] as $sid => $sub ):
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
                <?php else: // Módulo: itens diretos (2 níveis) ?>
                    <?php foreach ( $entry['items'] as $item ):
                        $cls = ( $item['slug'] === $page_atual ) ? ' active' : '';
                    ?>
                        <a href="<?php echo esc_url( $item['url'] ); ?>" class="cbpm-nav-link<?php echo $cls; ?>"><?php echo $item['label']; ?></a>
                    <?php endforeach; ?>
                <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
        </nav>
        <div class="cbpm-sidebar-footer">
            <?php
            // Seletor de NEGÓCIO (multi-tenant): só aparece quando o usuário tem acesso a >1.
            if ( function_exists( 'tao_crm_negocios_permitidos' ) ) {
                $__negs = tao_crm_negocios_permitidos();
                if ( is_array( $__negs ) && count( $__negs ) > 1 ) {
                    $__ativo = function_exists( 'tao_crm_negocio_ativo' ) ? tao_crm_negocio_ativo() : '';
                    echo '<div style="margin-bottom:10px">';
                    echo '<label style="font-size:11px;opacity:.7;display:block;margin-bottom:2px">🏢 Negócio</label>';
                    echo '<select onchange="var u=new URL(location.href);u.searchParams.set(&quot;workspace_id&quot;,this.value);location.href=u.toString();" style="width:100%;padding:4px 6px;border-radius:4px;border:1px solid #cbd5e1;font-size:12px">';
                    foreach ( $__negs as $__n ) {
                        echo '<option value="' . esc_attr( $__n['id'] ) . '"' . selected( $__n['id'], $__ativo, false ) . '>' . esc_html( $__n['nome'] ) . '</option>';
                    }
                    echo '</select></div>';
                }
            }
            ?>
            <?php echo esc_html( wp_get_current_user()->display_name ); ?><br>
            <a href="<?php echo esc_url( wp_logout_url( home_url('/robos/') ) ); ?>">Sair</a>
            &nbsp;&middot;&nbsp;
            <a href="<?php echo esc_url( admin_url() ); ?>">wp-admin</a>
        </div>
    </aside>

    <main class="cbpm-main">
        <div class="cbpm-breadcrumb">
            <a href="<?php echo esc_url( cbpm_url() ); ?>">TAO Neo</a>
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

<script>
window.cbpm = {
    ajax_url:     "<?php echo esc_js( admin_url('admin-ajax.php') ); ?>",
    nonce:        "<?php echo esc_js( wp_create_nonce('cbpm_nonce') ); ?>",
    supabase_url: "<?php echo esc_js( $supabase_url ); ?>",
    supabase_key: ""  /* removido do frontend por segurança — acesso ao Supabase é server-side */
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
// ── Recolher / expandir a sidebar (desktop, com memória) ──
(function(){
    var collapse = document.getElementById('cbpm-sb-collapse');
    var openBtn  = document.getElementById('cbpm-sb-open');
    if (localStorage.getItem('cbpm:sb') === 'off') document.body.classList.add('cbpm-sb-off');
    function set(off){ document.body.classList.toggle('cbpm-sb-off', off); localStorage.setItem('cbpm:sb', off ? 'off' : 'on'); }
    if (collapse) collapse.addEventListener('click', function(){ set(true);  });
    if (openBtn)  openBtn.addEventListener('click',  function(){ set(false); });
})();
</script>
<script>
/* Envolve toda tabela de dados num container rolável (evita corte no mobile) */
(function(){
  function wrapTables(){
    var main = document.querySelector('.cbpm-main'); if(!main) return;
    var tables = main.querySelectorAll('table');
    for (var i=0;i<tables.length;i++){
      var t = tables[i];
      if (t.classList.contains('form-table') || t.classList.contains('taof-totais-table')) continue;
      var p = t.parentNode; if(!p) continue;
      if (p.classList && p.classList.contains('cbpm-tscroll')) continue;
      // já está dentro de um container com scroll horizontal? não embrulha de novo
      var anc = p, already = false;
      while (anc && anc !== main) { var ox = getComputedStyle(anc).overflowX; if (ox === 'auto' || ox === 'scroll') { already = true; break; } anc = anc.parentNode; }
      if (already) continue;
      var w = document.createElement('div');
      w.className = 'cbpm-tscroll';
      p.insertBefore(w, t);
      w.appendChild(t);
    }
  }
  if (document.readyState !== 'loading') wrapTables();
  else document.addEventListener('DOMContentLoaded', wrapTables);
})();
</script>
<script src="<?php echo esc_url( CBPM_PLUGIN_URL . 'assets/script.js' ); ?>?v=<?php echo CBPM_VERSION; ?>"></script>
<?php if ( strpos( $fn, 'tao_formula_' ) === 0 && defined( 'TAOF_PLUGIN_URL' ) ): ?>
<script>
window.taoFormula = <?php echo wp_json_encode( [
    'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
    'nonce'       => wp_create_nonce( 'tao_formula_nonce' ),
    'supabaseUrl' => tao_formula_supabase_url(),
    'supabaseKey' => '',   // removido do frontend por segurança (server-side only)
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
    'supabase_key' => '',   // removido do frontend por segurança (server-side only)
    'card_base_url'=> cbpm_url( 'crm-kanban', [ 'action' => 'card', 'id' => '' ] ),
    'adminUrl'     => admin_url(),
] ); ?>;
</script>
<script src="<?php echo esc_url( TAO_CRM_URL . 'assets/crm-script.js' ); ?>?v=<?php echo @filemtime( TAO_CRM_DIR . 'assets/crm-script.js' ); ?>"></script>
<?php endif; ?>
</body>
</html>
