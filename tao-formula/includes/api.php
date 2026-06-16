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
    if ( function_exists( 'cbpm_current_cliente_id' ) ) return cbpm_current_cliente_id();
    return null;
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
