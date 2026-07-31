<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * PERFIS DE ACESSO (Fase 1) — negócio × perfil × tela × recurso × permissão.
 * Desenho aprovado pelo Carlos (19/07/2026):
 *   • crm_perfis / crm_perfil_usuarios / crm_permissoes (migration_perfis_v1.sql)
 *   • Permissões: oculto | leitura | opera
 *   • REGRA DE OURO: usuário SEM perfil atribuído = comportamento atual (nada muda
 *     até atribuir) e administrador WP sempre enxerga tudo. Perfil atribuído sem
 *     regra explícita para uma tela nova = opera (catálogo cresce sem trancar ninguém).
 *   • Fase 1 aplica no nível de TELA (módulos consultam tao_crm_tela_oculta()).
 *     Fase 2 = recursos finos (orcamento.aprovar, caixa.estornar, ...).
 */

// ── Catálogo de telas (a matriz da UI lê DAQUI — tela nova = 1 linha aqui) ────
// Cobre TODA a solução TAO Neo: Agente, CRM, Campanhas, Cadastros, Fórmulas,
// Estoque, Financeiro, Entregas, Cotações e Configurações.
function tao_crm_catalogo_telas() {
    return [
        'neo'            => 'Agente — Painel',
        'neo_pedidos'    => 'Agente — Pedidos',
        'neo_leads'      => 'Agente — Leads',
        'neo_historico'  => 'Agente — Histórico',
        'neo_conteudo'   => 'Agente — Promoções / Avisos',
        'kanban'         => 'CRM — Painel / Kanban / Cards',
        'contatos'       => 'Cadastros — Clientes / Contatos',
        'campanhas'      => 'Campanhas (+ Listas de Contatos)',
        'cadastros'      => 'Cadastros — Prescritores / Fornecedores / Ativos / Formas',
        'formula'        => 'Fórmulas — Orçamentos / Editor / Histórico',
        'formula_estoque'=> 'Estoque — NF / Lotes / Inventário / Reposição',
        'formula_prod'   => 'Fórmulas — Produção',
        'formula_sngpc'  => 'Fórmulas — SNGPC / Livro',
        'caixa'          => 'Caixa — PDV / Vendas / Sessão / Conciliação',
        'contas_pagar'   => 'Financeiro — Contas a Pagar',
        'cotacoes'       => 'Cotações (compras)',
        'entregas'       => 'Entregas',
        'crm_config'     => 'Config — CRM',
        'formula_config' => 'Config — Fórmulas',
        'caixa_config'   => 'Config — Caixa (Operadoras / Taxas / Formas)',
        'plataforma_config' => 'Config — Plataforma / Agente (Negócios, Usuários, Conectores, Catálogo)',
    ];
}

/**
 * Mapa seção do portal → tela do catálogo. É o que liga o roteador (/robos/*)
 * ao perfil: dispatcher e menu do portal consultam aqui. Seção não mapeada
 * (home, telas novas) = sem gate — nada tranca por omissão.
 */
function tao_crm_tela_da_secao( $secao ) {
    static $map = [
        'chatbot-platform-neo-dashboard'   => 'neo',
        'chatbot-platform-dashboard'       => 'neo',
        'chatbot-platform-pedidos'         => 'neo_pedidos',
        'chatbot-platform-leads'           => 'neo_leads',
        'chatbot-platform-historico'       => 'neo_historico',
        'chatbot-platform-conteudo'        => 'neo_conteudo',
        'chatbot-platform-campanhas'       => 'campanhas',
        'chatbot-platform-listas'          => 'campanhas',
        'chatbot-platform-negocios'        => 'plataforma_config',
        'chatbot-platform-categorias'      => 'plataforma_config',
        'chatbot-platform-usuarios'        => 'plataforma_config',
        'chatbot-platform-conectores'      => 'plataforma_config',
        'chatbot-platform-settings'        => 'plataforma_config',
        'chatbot-platform-catalogo'        => 'plataforma_config',
        'chatbot-platform-disponibilidade' => 'plataforma_config',
        'chatbot-platform-campos-extras'   => 'plataforma_config',
        'tao-crm-dashboard'                => 'kanban',
        'tao-crm-kanban'                   => 'kanban',
        'tao-crm-inbox'                    => 'kanban',
        'tao-crm-contatos'                 => 'contatos',
        'tao-crm-settings'                 => 'crm_config',
        'tao-formula'                      => 'formula',
        'tao-formula-orcamentos'           => 'formula',
        'tao-formula-orc-novo'             => 'formula',
        'tao-formula-historico'            => 'formula',
        'tao-formula-prescritores'         => 'cadastros',
        'tao-formula-fornecedores'         => 'cadastros',
        'tao-formula-ativos'               => 'cadastros',
        'tao-formula-formas'               => 'cadastros',
        'tao-cotacoes-fornecedores'        => 'cadastros',
        'tao-formula-estoque-nf'           => 'formula_estoque',
        'tao-formula-estoque-lotes'        => 'formula_estoque',
        'tao-formula-estoque-inventario'   => 'formula_estoque',
        'tao-formula-estoque-repo'         => 'formula_estoque',
        'tao-formula-producao'             => 'formula_prod',
        'tao-formula-producao-interna'     => 'formula_prod',
        'tao-formula-livro'                => 'formula_sngpc',
        'tao-formula-sngpc'                => 'formula_sngpc',
        'tao-formula-contas-pagar'         => 'contas_pagar',
        'tao-formula-config'               => 'formula_config',
        'tao-caixa-dashboard'              => 'caixa',
        'tao-caixa-vendas'                 => 'caixa',
        'tao-caixa-sessao'                 => 'caixa',
        'tao-caixa-conciliacao'            => 'caixa',
        'tao-caixa-adquirentes'            => 'caixa_config',
        'tao-caixa-taxas'                  => 'caixa_config',
        'tao-caixa-formas'                 => 'caixa_config',
        'tao-cotacoes'                     => 'cotacoes',
        'tao-cotacoes-nova'                => 'cotacoes',
        'tao-entregas-painel'              => 'entregas',
    ];
    return $map[ $secao ] ?? '';
}

// ── Engine ────────────────────────────────────────────────────────────────────
/** Perfil do usuário logado NO NEGÓCIO ATIVO. null = sem perfil (legado) ou master. */
function tao_crm_perfil_usuario() {
    static $cache = [];
    $uid = get_current_user_id();
    if ( ! $uid || tao_crm_is_master() ) return null;
    $ws = tao_crm_negocio_ativo();
    if ( ! $ws ) return null;
    if ( isset( $cache[ $ws ] ) ) return $cache[ $ws ];
    $ck = 'tao_perfil_u' . $uid . '_' . $ws;
    $t  = get_transient( $ck );
    if ( $t !== false ) return $cache[ $ws ] = ( $t === 'none' ? null : $t );
    $r   = tao_crm_api( "/crm_perfil_usuarios?usuario_id=eq.$uid&workspace_id=eq.$ws&select=perfil_id&limit=1" );
    $pid = ( $r['ok'] && ! empty( $r['data'] ) ) ? $r['data'][0]['perfil_id'] : null;
    set_transient( $ck, $pid ?: 'none', 10 * MINUTE_IN_SECONDS );
    return $cache[ $ws ] = $pid;
}

// ── NEGÓCIOS (multi-tenant) — acesso por negócio ancorado nos vínculos de perfil ──
/** É master? (vê todos os negócios) */
function tao_crm_is_master() {
    return current_user_can( 'manage_options' ) || ( function_exists( 'cbpm_is_master' ) && cbpm_is_master() );
}

/**
 * Negócios (workspaces) que o usuário pode acessar.
 *  • master → TODOS os ativos;
 *  • senão  → workspaces onde tem vínculo em crm_perfil_usuarios (RBAC por ws);
 *  • fallback legado (sem vínculo) → o negócio único do cbpm_cliente_id.
 * Retorna array de workspaces (linhas). Cacheado por request.
 */
function tao_crm_negocios_permitidos() {
    static $cache = null;
    if ( $cache !== null ) return $cache;
    if ( tao_crm_is_master() ) return $cache = tao_crm_get_workspaces();
    $uid = get_current_user_id();
    if ( ! $uid ) return $cache = [];
    $r   = tao_crm_api( "/crm_perfil_usuarios?usuario_id=eq.$uid&select=workspace_id&limit=200" );
    $ids = array_values( array_unique( array_filter( array_column( $r['ok'] ? ( $r['data'] ?? [] ) : [], 'workspace_id' ) ) ) );
    if ( $ids ) {
        $rw = tao_crm_api( "/crm_workspaces?id=in.(" . implode( ',', $ids ) . ")&ativo=eq.true&order=nome.asc" );
        $ws = $rw['ok'] ? ( $rw['data'] ?? [] ) : [];
        if ( $ws ) return $cache = $ws;
    }
    $w = tao_crm_get_workspace();   // legado: negócio único do cliente
    return $cache = $w ? [ $w ] : [];
}

/** IDs dos negócios permitidos. */
function tao_crm_negocios_permitidos_ids() {
    return array_values( array_filter( array_column( tao_crm_negocios_permitidos(), 'id' ) ) );
}

/** O usuário pode acessar este negócio (workspace)? Base da trava anti-vazamento. */
function tao_crm_pode_acessar_ws( $ws_id ) {
    if ( ! $ws_id ) return false;
    if ( tao_crm_is_master() ) return true;
    return in_array( $ws_id, tao_crm_negocios_permitidos_ids(), true );
}

/** Aborta o AJAX (wp_send_json_error) se o usuário não pode acessar o negócio. */
function tao_crm_guard_ws( $ws_id ) {
    if ( ! tao_crm_pode_acessar_ws( $ws_id ) ) wp_send_json_error( 'Acesso negado ao negócio.' );
}

/**
 * Negócio ATIVO da sessão, SEMPRE validado contra os permitidos:
 *  1) ?workspace_id do request, se permitido (e persiste a escolha);
 *  2) último escolhido (user_meta), se ainda permitido;
 *  3) 1º permitido.  Retorna null se o usuário não tem nenhum negócio.
 */
function tao_crm_negocio_ativo() {
    static $cache = false;
    if ( $cache !== false ) return $cache;
    $permitidos = tao_crm_negocios_permitidos_ids();
    if ( ! $permitidos ) return $cache = null;
    $uid = get_current_user_id();
    $req = sanitize_text_field( $_REQUEST['workspace_id'] ?? '' );
    if ( $req && in_array( $req, $permitidos, true ) ) {
        if ( $uid ) update_user_meta( $uid, 'tao_crm_negocio_ativo', $req );
        return $cache = $req;
    }
    $saved = $uid ? get_user_meta( $uid, 'tao_crm_negocio_ativo', true ) : '';
    if ( $saved && in_array( $saved, $permitidos, true ) ) return $cache = $saved;
    return $cache = $permitidos[0];
}

/** Mapa de permissões do perfil: ["tela|recurso" => permissao]. Cache 10 min. */
function tao_crm_permissoes_do_perfil( $perfil_id ) {
    static $cache = [];
    if ( isset( $cache[ $perfil_id ] ) ) return $cache[ $perfil_id ];
    $t = get_transient( 'tao_perms_' . $perfil_id );
    if ( is_array( $t ) ) return $cache[ $perfil_id ] = $t;
    $r = tao_crm_api( "/crm_permissoes?perfil_id=eq.$perfil_id&select=tela,recurso,permissao&limit=1000" );
    $map = [];
    foreach ( ( $r['ok'] ? ( $r['data'] ?? [] ) : [] ) as $p ) {
        $map[ $p['tela'] . '|' . ( $p['recurso'] ?: '*' ) ] = $p['permissao'];
    }
    set_transient( 'tao_perms_' . $perfil_id, $map, 10 * MINUTE_IN_SECONDS );
    return $cache[ $perfil_id ] = $map;
}

/** Permissão efetiva: 'oculto' | 'leitura' | 'opera'. Sem perfil/admin ⇒ opera (legado). */
function tao_crm_permissao( $tela, $recurso = '*' ) {
    $pid = tao_crm_perfil_usuario();
    if ( ! $pid ) return 'opera';
    $map = tao_crm_permissoes_do_perfil( $pid );
    return $map[ $tela . '|' . $recurso ] ?? $map[ $tela . '|*' ] ?? 'opera';
}

/** Atalhos para os módulos (ponto único de gate por tela). */
function tao_crm_tela_oculta( $tela )  { return tao_crm_permissao( $tela ) === 'oculto'; }
function tao_crm_pode_operar( $tela )  { return tao_crm_permissao( $tela ) === 'opera'; }

/**
 * Gate de MÓDULO (usado pelos can_access): nega só quando TODAS as telas do
 * módulo estão ocultas. O bloqueio tela a tela é do dispatcher do portal —
 * aqui é o cinto de segurança dos AJAX sem derrubar telas ainda permitidas.
 */
function tao_crm_modulo_todo_oculto( array $telas ) {
    if ( ! tao_crm_perfil_usuario() ) return false;
    foreach ( $telas as $t ) {
        if ( ! tao_crm_tela_oculta( $t ) ) return false;
    }
    return true;
}

// ── Alçadas (etapa 1): até quanto o perfil decide sozinho ────────────────────
// A permissão diz ONDE o usuário chega; a alçada diz ATÉ QUANTO decide sem um
// gestor. Sem linha cadastrada (ou sem perfil/admin) = sem limite.
function tao_crm_catalogo_alcadas() {
    return [
        'orcamento.desconto_pct' => [ 'label' => 'Desconto máximo no orçamento (Fórmulas)',        'unidade' => '%'  ],
        'pdv.desconto_valor'     => [ 'label' => 'Desconto adicional máximo no recebimento (PDV)', 'unidade' => 'R$' ],
    ];
}

/** Mapa de alçadas do perfil: [recurso => limite]. Cache 10 min. */
function tao_crm_alcadas_do_perfil( $perfil_id ) {
    static $cache = [];
    if ( isset( $cache[ $perfil_id ] ) ) return $cache[ $perfil_id ];
    $t = get_transient( 'tao_alc_' . $perfil_id );
    if ( is_array( $t ) ) return $cache[ $perfil_id ] = $t;
    $r = tao_crm_api( "/crm_alcadas?perfil_id=eq.$perfil_id&select=recurso,limite&limit=200" );
    $map = [];
    foreach ( ( $r['ok'] ? ( $r['data'] ?? [] ) : [] ) as $a ) {
        $map[ $a['recurso'] ] = (float) $a['limite'];
    }
    set_transient( 'tao_alc_' . $perfil_id, $map, 10 * MINUTE_IN_SECONDS );
    return $cache[ $perfil_id ] = $map;
}

/** Limite do usuário logado para o recurso; null = sem limite (admin, sem perfil ou sem cadastro). */
function tao_crm_alcada( $recurso ) {
    $pid = tao_crm_perfil_usuario();
    if ( ! $pid ) return null;
    $map = tao_crm_alcadas_do_perfil( $pid );
    return isset( $map[ $recurso ] ) ? (float) $map[ $recurso ] : null;
}

// ── Seeds automáticos: perfis padrão com matriz preenchida ────────────────────
function tao_crm_perfis_seed_padrao( $ws_id ) {
    $telas = array_keys( tao_crm_catalogo_telas() );
    $perfis = [
        'Gestor'       => [],   // tudo opera (default) — sem exceções
        'Farmacêutica' => [ 'crm_config' => 'leitura', 'caixa_config' => 'leitura', 'plataforma_config' => 'leitura' ],
        'Atendente'    => [ 'crm_config' => 'oculto', 'formula_config' => 'oculto', 'caixa_config' => 'oculto', 'plataforma_config' => 'oculto', 'formula_sngpc' => 'leitura', 'cotacoes' => 'oculto', 'contas_pagar' => 'oculto' ],
        'Financeiro'   => [ 'formula' => 'leitura', 'formula_prod' => 'leitura', 'formula_sngpc' => 'leitura', 'formula_config' => 'oculto', 'kanban' => 'leitura', 'campanhas' => 'oculto', 'crm_config' => 'oculto', 'plataforma_config' => 'oculto', 'neo_conteudo' => 'oculto' ],
    ];
    $criados = 0;
    foreach ( $perfis as $nome => $exc ) {
        $ex = tao_crm_api( "/crm_perfis?workspace_id=eq.$ws_id&nome=eq." . rawurlencode( $nome ) . "&select=id&limit=1" );
        if ( $ex['ok'] && ! empty( $ex['data'] ) ) continue;   // idempotente
        $rp = tao_crm_api( '/crm_perfis', 'POST', [ 'workspace_id' => $ws_id, 'nome' => $nome, 'descricao' => 'Perfil padrão (seed automático)', 'ativo' => true ], [ 'Prefer' => 'return=representation' ] );
        if ( ! $rp['ok'] || empty( $rp['data'] ) ) continue;
        $pid = $rp['data'][0]['id'];
        $rows = [];
        foreach ( $telas as $t ) {
            $rows[] = [ 'perfil_id' => $pid, 'tela' => $t, 'recurso' => '*', 'permissao' => $exc[ $t ] ?? 'opera' ];
        }
        tao_crm_api( '/crm_permissoes', 'POST', $rows );
        $criados++;
    }
    return $criados;
}

// ── AJAX (CRUD — admin only) ─────────────────────────────────────────────────
function tao_crm_perfis_guard() {
    // Byte/BOM de algum arquivo vaza antes do JSON e quebra o parse no jQuery
    // (gotcha da casa) — limpa o buffer antes de responder.
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Apenas administradores gerenciam perfis.' );
}

add_action( 'wp_ajax_tao_crm_perfis_listar', function () {
    tao_crm_perfis_guard();
    $ws = sanitize_text_field( $_POST['workspace_id'] ?? '' );
    $rp = tao_crm_api( "/crm_perfis?workspace_id=eq.$ws&order=nome.asc&select=id,nome,descricao,ativo" );
    $ru = tao_crm_api( "/crm_perfil_usuarios?workspace_id=eq.$ws&select=usuario_id,perfil_id" );
    $perfis = $rp['ok'] ? ( $rp['data'] ?? [] ) : [];
    $ids = array_column( $perfis, 'id' );
    $perms = [];
    $alcadas = [];
    if ( $ids ) {
        $in = implode( ',', $ids );
        $rr = tao_crm_api( "/crm_permissoes?perfil_id=in.($in)&select=perfil_id,tela,recurso,permissao&limit=2000" );
        $perms = $rr['ok'] ? ( $rr['data'] ?? [] ) : [];
        $ra = tao_crm_api( "/crm_alcadas?perfil_id=in.($in)&select=perfil_id,recurso,limite&limit=500" );
        $alcadas = $ra['ok'] ? ( $ra['data'] ?? [] ) : [];   // tabela pode não existir ainda → lista vazia
    }
    $users = [];
    foreach ( get_users( [ 'fields' => [ 'ID', 'display_name', 'user_login' ] ] ) as $u ) {
        $users[] = [ 'id' => (int) $u->ID, 'nome' => $u->display_name ?: $u->user_login ];
    }
    wp_send_json_success( [
        'perfis' => $perfis, 'vinculos' => $ru['ok'] ? ( $ru['data'] ?? [] ) : [],
        'permissoes' => $perms, 'usuarios' => $users, 'telas' => tao_crm_catalogo_telas(),
        'alcadas' => $alcadas, 'catalogo_alcadas' => tao_crm_catalogo_alcadas(),
    ] );
} );

add_action( 'wp_ajax_tao_crm_perfil_salvar', function () {
    tao_crm_perfis_guard();
    $ws   = sanitize_text_field( $_POST['workspace_id'] ?? '' );
    $id   = sanitize_text_field( $_POST['id'] ?? '' );
    $nome = trim( sanitize_text_field( $_POST['nome'] ?? '' ) );
    if ( ! $ws || ! $nome ) wp_send_json_error( 'Informe o nome do perfil.' );
    $body = [ 'nome' => $nome, 'descricao' => sanitize_text_field( $_POST['descricao'] ?? '' ), 'ativo' => ( $_POST['ativo'] ?? '1' ) === '1' ];
    if ( $id ) {
        $r = tao_crm_api( "/crm_perfis?id=eq.$id", 'PATCH', $body, [ 'Prefer' => 'return=representation' ] );
    } else {
        $body['workspace_id'] = $ws;
        $r = tao_crm_api( '/crm_perfis', 'POST', $body, [ 'Prefer' => 'return=representation' ] );
    }
    $r['ok'] ? wp_send_json_success( $r['data'][0] ?? [] ) : wp_send_json_error( $r['error'] ?? 'erro' );
} );

add_action( 'wp_ajax_tao_crm_perfil_excluir', function () {
    tao_crm_perfis_guard();
    $id = sanitize_text_field( $_POST['id'] ?? '' );
    if ( ! $id ) wp_send_json_error( 'id' );
    $r = tao_crm_api( "/crm_perfis?id=eq.$id", 'DELETE' );   // permissões, alçadas e vínculos caem por cascade
    delete_transient( 'tao_perms_' . $id );
    delete_transient( 'tao_alc_' . $id );
    $r['ok'] ? wp_send_json_success() : wp_send_json_error( $r['error'] ?? 'erro' );
} );

add_action( 'wp_ajax_tao_crm_perfil_atribuir', function () {
    tao_crm_perfis_guard();
    $ws  = sanitize_text_field( $_POST['workspace_id'] ?? '' );
    $uid = (int) ( $_POST['usuario_id'] ?? 0 );
    $pid = sanitize_text_field( $_POST['perfil_id'] ?? '' );
    if ( ! $ws || ! $uid ) wp_send_json_error( 'dados' );
    tao_crm_api( "/crm_perfil_usuarios?workspace_id=eq.$ws&usuario_id=eq.$uid", 'DELETE' );
    if ( $pid ) tao_crm_api( '/crm_perfil_usuarios', 'POST', [ 'workspace_id' => $ws, 'perfil_id' => $pid, 'usuario_id' => $uid ] );
    delete_transient( 'tao_perfil_u' . $uid . '_' . $ws );   // cache do perfil é por negócio
    wp_send_json_success();
} );

add_action( 'wp_ajax_tao_crm_permissoes_salvar', function () {
    tao_crm_perfis_guard();
    $pid   = sanitize_text_field( $_POST['perfil_id'] ?? '' );
    $lista = json_decode( wp_unslash( $_POST['permissoes'] ?? '[]' ), true );
    if ( ! $pid || ! is_array( $lista ) ) wp_send_json_error( 'dados' );
    tao_crm_api( "/crm_permissoes?perfil_id=eq.$pid", 'DELETE' );
    $rows = [];
    foreach ( $lista as $p ) {
        $perm = in_array( $p['permissao'] ?? '', [ 'oculto', 'leitura', 'opera' ], true ) ? $p['permissao'] : 'opera';
        $rows[] = [ 'perfil_id' => $pid, 'tela' => sanitize_text_field( $p['tela'] ?? '' ), 'recurso' => sanitize_text_field( $p['recurso'] ?? '*' ) ?: '*', 'permissao' => $perm ];
    }
    if ( $rows ) tao_crm_api( '/crm_permissoes', 'POST', $rows );
    delete_transient( 'tao_perms_' . $pid );
    // Alçadas do perfil (mesma gravação: substitui tudo pelo que veio da tela)
    if ( isset( $_POST['alcadas'] ) ) {
        $alc = json_decode( wp_unslash( $_POST['alcadas'] ), true );
        if ( is_array( $alc ) ) {
            tao_crm_api( "/crm_alcadas?perfil_id=eq.$pid", 'DELETE' );
            $arows = [];
            foreach ( $alc as $a ) {
                $rec = sanitize_text_field( $a['recurso'] ?? '' );
                if ( $rec === '' || ! isset( $a['limite'] ) || ! is_numeric( $a['limite'] ) ) continue;
                $arows[] = [ 'perfil_id' => $pid, 'recurso' => $rec, 'limite' => (float) $a['limite'] ];
            }
            if ( $arows ) tao_crm_api( '/crm_alcadas', 'POST', $arows );
            delete_transient( 'tao_alc_' . $pid );
        }
    }
    wp_send_json_success();
} );

add_action( 'wp_ajax_tao_crm_perfis_seed', function () {
    tao_crm_perfis_guard();
    $ws = sanitize_text_field( $_POST['workspace_id'] ?? '' );
    if ( ! $ws ) wp_send_json_error( 'workspace' );
    wp_send_json_success( [ 'criados' => tao_crm_perfis_seed_padrao( $ws ) ] );
} );

// ── Tela de administração (wp-admin → TAO CRM → Perfis de Acesso) ────────────
add_action( 'admin_menu', function () {
    add_submenu_page( 'tao-crm', 'Perfis de Acesso', 'Perfis de Acesso', 'manage_options', 'tao-crm-perfis', 'tao_crm_page_perfis' );
}, 99 );

function tao_crm_page_perfis() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    $wss = function_exists( 'tao_crm_get_workspaces' ) ? tao_crm_get_workspaces() : [];
    $nonce = wp_create_nonce( 'tao_crm_nonce' );
    ?>
    <style>
    /* Estilo próprio: a tela roda também no portal /robos/, onde o CSS do wp-admin não existe */
    #tp-wrap { max-width: 900px; }
    #tp-wrap h1 { font-size: 22px; margin: 0 0 8px; }
    #tp-wrap h2 { font-size: 16px; margin: 24px 0 8px; }
    #tp-wrap table.tp-tab { width: 100%; border-collapse: collapse; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; }
    #tp-wrap table.tp-tab th { text-align: left; font-size: 12px; color: #64748b; padding: 8px 10px; border-bottom: 1px solid #e2e8f0; background: #f8fafc; }
    #tp-wrap table.tp-tab td { padding: 7px 10px; border-bottom: 1px solid #f1f5f9; font-size: 13px; }
    #tp-wrap select, #tp-wrap .tp-btn { padding: 5px 10px; border: 1px solid #cbd5e1; border-radius: 6px; background: #fff; font-size: 13px; cursor: pointer; }
    #tp-wrap .tp-btn-pri { background: #2563eb; border-color: #2563eb; color: #fff; }
    #tp-wrap .tp-btn:disabled { opacity: .6; cursor: default; }
    </style>
    <div id="tp-wrap" class="wrap">
        <h1>🔐 Perfis de Acesso</h1>
        <p style="color:#64748b;max-width:760px">Negócio × perfil × tela × permissão. Usuário <strong>sem perfil</strong> mantém o comportamento atual;
        administradores sempre veem tudo. Tela nova entra na matriz automaticamente com "Opera" (ninguém fica trancado).</p>
        <p>
            <label>Negócio:
                <select id="tp-ws">
                    <?php foreach ( $wss as $w ) : ?>
                    <option value="<?php echo esc_attr( $w['id'] ); ?>"><?php echo esc_html( $w['nome'] ); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button class="button tp-btn" id="tp-seed" title="Cria Gestor/Farmacêutica/Atendente/Financeiro com matriz preenchida (idempotente)">⚙ Criar perfis padrão</button>
            <button class="button button-primary tp-btn tp-btn-pri" id="tp-novo">+ Novo perfil</button>
        </p>
        <div id="tp-app">Carregando…</div>
    </div>
    <script>
    (function($){
        var ajaxurl = window.ajaxurl || <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
        var nonce = <?php echo wp_json_encode( $nonce ); ?>, DATA = null;
        function ws(){ return $('#tp-ws').val(); }
        function carregar(){
            $('#tp-app').text('Carregando…');
            $.post(ajaxurl, {action:'tao_crm_perfis_listar', nonce:nonce, workspace_id:ws()}, function(r){
                if(!r.success){ $('#tp-app').text('Erro: '+(r.data||'?')); return; }
                DATA = r.data; render();
            }).fail(function(x){
                $('#tp-app').html('<span style="color:#dc2626">Falha na chamada (HTTP '+x.status+'). '
                    + $('<span>').text(String(x.responseText||'').slice(0,300)).html() + '</span>');
            });
        }
        function permDe(pid, tela){
            var hit = (DATA.permissoes||[]).filter(function(p){ return p.perfil_id===pid && p.tela===tela && (p.recurso==='*'||!p.recurso); })[0];
            return hit ? hit.permissao : 'opera';
        }
        function render(){
            var h = '';
            // ── Atribuição usuário → perfil ──
            h += '<h2>Usuários deste negócio</h2><table class="widefat tp-tab" style="max-width:640px"><thead><tr><th>Usuário</th><th>Perfil</th></tr></thead><tbody>';
            (DATA.usuarios||[]).forEach(function(u){
                var v = (DATA.vinculos||[]).filter(function(x){ return parseInt(x.usuario_id)===u.id; })[0];
                h += '<tr><td>'+u.nome+' <span style="color:#94a3b8">#'+u.id+'</span></td><td><select class="tp-vinc" data-uid="'+u.id+'">'
                   + '<option value="">— sem perfil (comportamento atual) —</option>';
                (DATA.perfis||[]).forEach(function(p){
                    h += '<option value="'+p.id+'"'+((v&&v.perfil_id===p.id)?' selected':'')+'>'+p.nome+'</option>';
                });
                h += '</select></td></tr>';
            });
            h += '</tbody></table>';
            // ── Matriz por perfil ──
            (DATA.perfis||[]).forEach(function(p){
                h += '<h2 style="margin-top:22px">'+p.nome+' '+(p.ativo?'':'<span style="color:#dc2626">(inativo)</span>')
                   + ' <button class="button button-small tp-btn tp-excluir" data-id="'+p.id+'">Excluir</button></h2>';
                h += '<table class="widefat striped tp-tab" style="max-width:760px" data-perfil="'+p.id+'"><thead><tr><th>Tela</th><th style="width:220px">Permissão</th></tr></thead><tbody>';
                Object.keys(DATA.telas||{}).forEach(function(t){
                    var atual = permDe(p.id, t);
                    h += '<tr><td>'+DATA.telas[t]+'</td><td><select class="tp-perm" data-tela="'+t+'">';
                    [['opera','Opera (tudo)'],['leitura','Somente leitura'],['oculto','Oculto']].forEach(function(o){
                        h += '<option value="'+o[0]+'"'+(atual===o[0]?' selected':'')+'>'+o[1]+'</option>';
                    });
                    h += '</select></td></tr>';
                });
                h += '</tbody></table>';
                // ── Alçadas do perfil ──
                h += '<h3 style="font-size:13px;margin:14px 0 6px">Alçadas <span style="color:#94a3b8;font-weight:400">(vazio = sem limite)</span></h3>';
                h += '<table class="widefat tp-tab" style="max-width:760px" data-perfil-alc="'+p.id+'"><tbody>';
                Object.keys(DATA.catalogo_alcadas||{}).forEach(function(rk){
                    var meta = DATA.catalogo_alcadas[rk];
                    var atual = (DATA.alcadas||[]).filter(function(a){ return a.perfil_id===p.id && a.recurso===rk; })[0];
                    h += '<tr><td>'+meta.label+'</td><td style="width:220px;white-space:nowrap">'
                       + (meta.unidade==='R$' ? 'R$ ' : '')
                       + '<input type="number" step="0.01" min="0" class="tp-alc" data-recurso="'+rk+'" style="width:110px;padding:4px 8px;border:1px solid #cbd5e1;border-radius:6px" value="'+(atual!==undefined&&atual!==null?atual.limite:'')+'">'
                       + (meta.unidade==='%' ? ' %' : '')
                       + '</td></tr>';
                });
                h += '</tbody></table>';
                h += '<p><button class="button button-primary tp-btn tp-btn-pri tp-salvar-matriz" data-perfil="'+p.id+'">💾 Salvar matriz de '+p.nome+'</button></p>';
            });
            $('#tp-app').html(h);
        }
        $('#tp-ws').on('change', carregar);
        $('#tp-seed').on('click', function(){
            $.post(ajaxurl, {action:'tao_crm_perfis_seed', nonce:nonce, workspace_id:ws()}, function(r){
                alert(r.success ? ('Perfis padrão criados: '+r.data.criados) : ('Erro: '+r.data)); carregar();
            });
        });
        $('#tp-novo').on('click', function(){
            var n = prompt('Nome do novo perfil:'); if(!n) return;
            $.post(ajaxurl, {action:'tao_crm_perfil_salvar', nonce:nonce, workspace_id:ws(), nome:n}, function(r){
                if(!r.success){ alert('Erro: '+r.data); return; } carregar();
            });
        });
        $(document).on('change', '.tp-vinc', function(){
            $.post(ajaxurl, {action:'tao_crm_perfil_atribuir', nonce:nonce, workspace_id:ws(), usuario_id:$(this).data('uid'), perfil_id:$(this).val()}, function(r){
                if(!r.success) alert('Erro: '+r.data);
            });
        });
        $(document).on('click', '.tp-excluir', function(){
            if(!confirm('Excluir este perfil? Usuários vinculados voltam ao comportamento atual.')) return;
            $.post(ajaxurl, {action:'tao_crm_perfil_excluir', nonce:nonce, id:$(this).data('id')}, function(r){
                if(!r.success){ alert('Erro: '+r.data); return; } carregar();
            });
        });
        $(document).on('click', '.tp-salvar-matriz', function(){
            var pid = $(this).data('perfil'), lista = [], alc = [];
            $('table[data-perfil="'+pid+'"] .tp-perm').each(function(){
                lista.push({ tela: $(this).data('tela'), recurso:'*', permissao: $(this).val() });
            });
            $('table[data-perfil-alc="'+pid+'"] .tp-alc').each(function(){
                var v = $(this).val();
                if (v !== '' && !isNaN(parseFloat(v))) alc.push({ recurso: $(this).data('recurso'), limite: parseFloat(v) });
            });
            var $b = $(this).prop('disabled', true).text('Salvando…');
            $.post(ajaxurl, {action:'tao_crm_permissoes_salvar', nonce:nonce, perfil_id:pid, permissoes: JSON.stringify(lista), alcadas: JSON.stringify(alc)}, function(r){
                $b.prop('disabled', false).text('💾 Salvar matriz');
                r.success ? $b.text('✔ Salvo') : alert('Erro: '+r.data);
            });
        });
        carregar();
    })(jQuery);
    </script>
    <?php
}
