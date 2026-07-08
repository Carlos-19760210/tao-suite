<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function tao_formula_supabase_url() {
    if ( function_exists( 'cbpm_supabase_url' ) ) return cbpm_supabase_url();
    return get_option( 'tao_formula_supabase_url', '' );
}

function tao_formula_supabase_key() {
    if ( function_exists( 'cbpm_supabase_key' ) ) return cbpm_supabase_key();
    return get_option( 'tao_formula_supabase_key', '' );
}

function tao_formula_can_access() {
    if ( function_exists( 'cbpm_can_access' ) ) return cbpm_can_access();
    return current_user_can( 'manage_options' );
}

function tao_formula_is_master() {
    if ( function_exists( 'cbpm_is_master' ) ) return cbpm_is_master();
    return current_user_can( 'manage_options' );
}

function tao_formula_cliente_id() {
    if ( function_exists( 'cbpm_current_cliente_id' ) ) {
        $id = cbpm_current_cliente_id();
        if ( $id ) return $id;
    }
    // Fallback para usuario master: usa o cliente_id configurado em Configuracoes
    return get_option( 'tao_formula_default_cliente_id', null ) ?: null;
}

/**
 * Workspace do CRM p/ o tenant atual — base ÚNICA de contatos (crm_contatos).
 * Cache estático por request.
 */
function tao_formula_workspace_id() {
    static $cache = [];
    $cid = tao_formula_cliente_id();
    if ( ! $cid ) return null;
    if ( array_key_exists( $cid, $cache ) ) return $cache[ $cid ];
    $r = tao_formula_api( "/crm_workspaces?cliente_id=eq.$cid&ativo=eq.true&select=id&order=nome.asc&limit=1" );
    return $cache[ $cid ] = ( $r['ok'] && ! empty( $r['data'] ) ) ? $r['data'][0]['id'] : null;
}

/**
 * Normaliza telefone p/ o formato do CRM: 55 + DDD + 9 dígitos.
 * Retorna null se não der p/ montar um número plausível.
 */
function tao_formula_norm_whatsapp( $raw ) {
    $d = preg_replace( '/\D/', '', (string) $raw );
    if ( $d === '' ) return null;
    if ( strlen( $d ) === 13 && substr( $d, 0, 2 ) === '55' ) return $d;      // já completo
    if ( strlen( $d ) === 11 ) return '55' . $d;                              // DDD + 9 dígitos
    if ( strlen( $d ) === 10 ) return '55' . substr( $d, 0, 2 ) . '9' . substr( $d, 2 ); // DDD + 8 → insere 9
    if ( strlen( $d ) === 12 && substr( $d, 0, 2 ) === '55' ) return '55' . substr( $d, 2, 2 ) . '9' . substr( $d, 4 );
    return $d; // formato incomum — grava como veio (não perde o dado)
}

/**
 * URL de uma página do TAO Fórmulas — admin ou frontend conforme contexto.
 */
function tao_formula_url( $section = 'formula-dashboard', $params = [] ) {
    global $cbpm_is_frontend;
    if ( ! empty( $cbpm_is_frontend ) && function_exists( 'cbpm_url' ) ) {
        return cbpm_url( $section, $params );
    }
    $slugs = [
        'formula-dashboard'  => 'tao-formula',
        'formula-orcamentos' => 'tao-formula-orcamentos',
        'formula-novo-orc'   => 'tao-formula-orc-novo',
        'formula-historico'  => 'tao-formula-historico',
        'formula-prescritores' => 'tao-formula-prescritores',
        'formula-estoque-nf'   => 'tao-formula-estoque-nf',
        'formula-estoque-lotes'=> 'tao-formula-estoque-lotes',
        'formula-estoque-repo' => 'tao-formula-estoque-repo',
        'formula-producao'     => 'tao-formula-producao',
        'formula-livro'        => 'tao-formula-livro',
        'formula-formas'     => 'tao-formula-formas',
        'formula-ativos'     => 'tao-formula-ativos',
        'formula-config'     => 'tao-formula-config',
    ];
    $page = $slugs[ $section ] ?? 'tao-formula';
    $url  = admin_url( "admin.php?page=$page" );
    return $params ? add_query_arg( $params, $url ) : $url;
}

/**
 * Realiza chamada REST para o Supabase.
 */
function tao_formula_api( $path, $method = 'GET', $body = null ) {
    $url = rtrim( tao_formula_supabase_url(), '/' ) . '/rest/v1' . $path;
    $key = tao_formula_supabase_key();

    $args = [
        'method'  => $method,
        'timeout' => 15,
        'headers' => [
            'apikey'        => $key,
            'Authorization' => 'Bearer ' . $key,
            'Content-Type'  => 'application/json',
            'Prefer'        => 'return=representation',
        ],
    ];
    if ( $body !== null ) {
        $args['body'] = wp_json_encode( $body );
    }

    $resp = wp_remote_request( $url, $args );
    if ( is_wp_error( $resp ) ) {
        return [ 'ok' => false, 'error' => $resp->get_error_message(), 'data' => [] ];
    }
    $code = wp_remote_retrieve_response_code( $resp );
    $raw  = wp_remote_retrieve_body( $resp );
    $data = json_decode( $raw, true );

    return [
        'ok'   => $code >= 200 && $code < 300,
        'code' => $code,
        'data' => is_array( $data ) ? $data : [],
        'raw'  => $raw,
    ];
}
