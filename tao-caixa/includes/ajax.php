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

// ── Taxas (MDR) ──────────────────────────────────────────────────────────────

add_action( 'wp_ajax_tao_caixa_save_taxa', function() {
    $cid = tao_caixa_ajax_guard();

    $id    = sanitize_text_field( $_POST['id'] ?? '' );
    $forma = sanitize_text_field( $_POST['forma_pagamento_id'] ?? '' );
    $band  = trim( sanitize_text_field( $_POST['bandeira'] ?? '' ) );
    if ( ! $forma ) wp_send_json_error( 'Selecione a forma de pagamento' );

    $payload = [
        'forma_pagamento_id'     => $forma,
        'bandeira'               => $band !== '' ? $band : null,
        'parcelas'               => max( 1, (int) ( $_POST['parcelas'] ?? 1 ) ),
        'taxa_pct'               => round( (float) str_replace( ',', '.', $_POST['taxa_pct'] ?? 0 ), 3 ),
        'prazo_recebimento_dias' => max( 0, (int) ( $_POST['prazo_recebimento_dias'] ?? 1 ) ),
        'ativo'                  => ( ( $_POST['ativo'] ?? '1' ) === '1' ),
    ];

    if ( $id ) {
        $r = tao_caixa_api( "/caixa_taxas?id=eq.$id&cliente_id=eq.$cid", 'PATCH', $payload );
    } else {
        $payload['cliente_id'] = $cid;
        $r = tao_caixa_api( '/caixa_taxas', 'POST', $payload );
    }
    if ( ! $r['ok'] ) wp_send_json_error( 'Falha ao salvar: ' . ( $r['raw'] ?? '' ) );
    wp_send_json_success( $r['data'][0] ?? [] );
} );

add_action( 'wp_ajax_tao_caixa_delete_taxa', function() {
    $cid = tao_caixa_ajax_guard();
    $id  = sanitize_text_field( $_POST['id'] ?? '' );
    if ( ! $id ) wp_send_json_error( 'ID inválido' );
    $r = tao_caixa_api( "/caixa_taxas?id=eq.$id&cliente_id=eq.$cid", 'DELETE' );
    if ( ! $r['ok'] ) wp_send_json_error( 'Falha ao excluir: ' . ( $r['raw'] ?? '' ) );
    wp_send_json_success();
} );

// ── Formas de Pagamento ──────────────────────────────────────────────────────

add_action( 'wp_ajax_tao_caixa_save_forma', function() {
    $cid = tao_caixa_ajax_guard();

    $id    = sanitize_text_field( $_POST['id'] ?? '' );
    $nome  = trim( sanitize_text_field( $_POST['nome'] ?? '' ) );
    if ( $nome === '' ) wp_send_json_error( 'Informe o nome da forma de pagamento' );

    $tipos_ok = [ 'dinheiro', 'pix', 'debito', 'credito', 'boleto', 'link', 'outro' ];
    $tipo = sanitize_text_field( $_POST['tipo'] ?? 'dinheiro' );
    if ( ! in_array( $tipo, $tipos_ok, true ) ) $tipo = 'outro';
    $adq = sanitize_text_field( $_POST['adquirente_id'] ?? '' );

    $canais_ok = [ 'maquina', 'link', 'pix', 'dinheiro', 'boleto', 'manual', 'outro' ];
    $canal = sanitize_text_field( $_POST['canal'] ?? '' );
    if ( $canal !== '' && ! in_array( $canal, $canais_ok, true ) ) $canal = 'outro';

    $payload = [
        'nome'                   => $nome,
        'tipo'                   => $tipo,
        'canal'                  => $canal !== '' ? $canal : null,
        'adquirente_id'          => $adq ?: null,
        'prazo_recebimento_dias' => max( 0, (int) ( $_POST['prazo_recebimento_dias'] ?? 0 ) ),
        'taxa_pct'               => round( (float) str_replace( ',', '.', $_POST['taxa_pct'] ?? 0 ), 3 ),
        'conta_no_dinheiro'      => ( ( $_POST['conta_no_dinheiro'] ?? '0' ) === '1' ),
        'ordem'                  => max( 0, (int) ( $_POST['ordem'] ?? 0 ) ),
        'ativo'                  => ( ( $_POST['ativo'] ?? '1' ) === '1' ),
    ];

    if ( $id ) {
        $r = tao_caixa_api( "/caixa_formas_pagamento?id=eq.$id&cliente_id=eq.$cid", 'PATCH', $payload );
    } else {
        $payload['cliente_id'] = $cid;
        $r = tao_caixa_api( '/caixa_formas_pagamento', 'POST', $payload );
    }
    if ( ! $r['ok'] ) wp_send_json_error( 'Falha ao salvar: ' . ( $r['raw'] ?? '' ) );
    wp_send_json_success( $r['data'][0] ?? [] );
} );

add_action( 'wp_ajax_tao_caixa_delete_forma', function() {
    $cid = tao_caixa_ajax_guard();
    $id  = sanitize_text_field( $_POST['id'] ?? '' );
    if ( ! $id ) wp_send_json_error( 'ID inválido' );
    $r = tao_caixa_api( "/caixa_formas_pagamento?id=eq.$id&cliente_id=eq.$cid", 'DELETE' );
    if ( ! $r['ok'] ) wp_send_json_error( 'Falha ao excluir: ' . ( $r['raw'] ?? '' ) );
    wp_send_json_success();
} );
