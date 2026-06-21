<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Guarda comum dos handlers AJAX do caixa.
 * Retorna o cliente_id, ou encerra com erro.
 */
function tao_caixa_ajax_guard() {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_caixa_nonce', 'nonce' );
    if ( ! tao_caixa_pode_operar() ) wp_send_json_error( 'Sem permissão para operar o caixa', 403 );
    $cid = tao_caixa_cliente_id();
    if ( ! $cid ) wp_send_json_error( 'Cliente não identificado', 400 );
    return $cid;
}

// ── Adquirentes ──────────────────────────────────────────────────────────────

add_action( 'wp_ajax_tao_caixa_save_adquirente', function() {
    $cid = tao_caixa_ajax_guard();

    $id   = sanitize_text_field( $_POST['id'] ?? '' );
    $nome = trim( sanitize_text_field( $_POST['nome'] ?? '' ) );
    if ( $nome === '' ) wp_send_json_error( 'Informe o nome da operadora' );

    $payload = [
        'nome'                 => $nome,
        'taxa_antecipacao_pct' => round( (float) str_replace( ',', '.', $_POST['taxa_antecipacao_pct'] ?? 0 ), 3 ),
        'ativo'                => ( ( $_POST['ativo'] ?? '1' ) === '1' ),
    ];

    if ( $id ) {
        $r = tao_caixa_api( "/caixa_adquirentes?id=eq.$id&cliente_id=eq.$cid", 'PATCH', $payload );
    } else {
        $payload['cliente_id'] = $cid;
        $r = tao_caixa_api( '/caixa_adquirentes', 'POST', $payload );
    }

    if ( ! $r['ok'] ) wp_send_json_error( 'Falha ao salvar: ' . ( $r['raw'] ?? '' ) );
    wp_send_json_success( $r['data'][0] ?? [] );
} );

add_action( 'wp_ajax_tao_caixa_delete_adquirente', function() {
    $cid = tao_caixa_ajax_guard();
    $id  = sanitize_text_field( $_POST['id'] ?? '' );
    if ( ! $id ) wp_send_json_error( 'ID inválido' );

    $r = tao_caixa_api( "/caixa_adquirentes?id=eq.$id&cliente_id=eq.$cid", 'DELETE' );
    if ( ! $r['ok'] ) wp_send_json_error( 'Falha ao excluir: ' . ( $r['raw'] ?? '' ) );
    wp_send_json_success();
} );
