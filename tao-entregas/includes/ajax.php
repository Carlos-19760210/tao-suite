<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/** Entrega vinculada a um card (ou null se não houver). */
add_action( 'wp_ajax_tao_entregas_get', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_entregas_nonce', 'nonce' );
    if ( ! tao_entregas_can_access() ) wp_send_json_error( [ 'message' => 'Acesso negado' ], 403 );
    $card = sanitize_text_field( $_GET['card_id'] ?? '' );
    if ( ! $card ) wp_send_json_error( [ 'message' => 'Card inválido' ] );
    if ( function_exists( 'tao_crm_check_card_access' ) && ! tao_crm_check_card_access( $card ) ) wp_send_json_error( [ 'message' => 'Acesso negado' ], 403 );
    $r = tao_entregas_api( "/entregas?card_id=eq.$card&select=*&order=criado_em.desc&limit=1" );
    if ( ! $r['ok'] ) wp_send_json_error( [ 'message' => 'Erro: ' . mb_substr( (string) $r['raw'], 0, 160 ) ] );
    wp_send_json_success( ! empty( $r['data'] ) ? $r['data'][0] : null );
} );

/** Formas de pagamento do Caixa (cada uma já carrega o tipo). */
add_action( 'wp_ajax_tao_entregas_formas', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_entregas_nonce', 'nonce' );
    if ( ! tao_entregas_can_access() ) wp_send_json_error( [ 'message' => 'Acesso negado' ], 403 );
    $cid = function_exists( 'cbpm_current_cliente_id' ) ? cbpm_current_cliente_id() : null;
    $f   = $cid ? "cliente_id=eq.$cid&" : '';
    $r = tao_entregas_api( "/caixa_formas_pagamento?{$f}ativo=eq.true&select=id,nome,tipo&order=ordem.asc&limit=100" );
    wp_send_json_success( $r['ok'] ? ( $r['data'] ?? [] ) : [] );
} );

/** Endereços de entrega do cliente: o do cadastro (crm_contatos) + os adicionais. */
function tao_entregas_end_txt( $e ) {
    $p = array_filter( [
        trim( ( $e['logradouro'] ?? '' ) . ' ' . ( $e['numero'] ?? '' ) ),
        $e['complemento'] ?? '', $e['bairro'] ?? '',
        trim( ( $e['cidade'] ?? '' ) . ( ! empty( $e['uf'] ) ? '/' . $e['uf'] : '' ) ),
        $e['cep'] ?? '',
    ] );
    return implode( ', ', $p );
}
add_action( 'wp_ajax_tao_entregas_enderecos', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_entregas_nonce', 'nonce' );
    if ( ! tao_entregas_can_access() ) wp_send_json_error( [ 'message' => 'Acesso negado' ], 403 );
    $contato = sanitize_text_field( $_GET['contato_id'] ?? '' );
    if ( ! $contato ) { wp_send_json_success( [] ); return; }
    if ( function_exists( 'tao_crm_pode_acessar_ws' ) ) {
        $rcw = tao_entregas_api( "/crm_contatos?id=eq.$contato&select=workspace_id&limit=1" );
        $cws = ( ! empty( $rcw['ok'] ) && ! empty( $rcw['data'] ) ) ? ( $rcw['data'][0]['workspace_id'] ?? '' ) : '';
        if ( ! tao_crm_pode_acessar_ws( $cws ) ) wp_send_json_error( [ 'message' => 'Acesso negado' ], 403 );
    }
    $out = [];
    $rc = tao_entregas_api( "/crm_contatos?id=eq.$contato&select=cep,logradouro,numero,bairro,cidade&limit=1" );
    if ( ! empty( $rc['ok'] ) && ! empty( $rc['data'] ) ) {
        $c = $rc['data'][0];
        if ( ! empty( $c['logradouro'] ) || ! empty( $c['cidade'] ) )
            $out[] = [ 'id' => 'cadastro', 'apelido' => 'Cadastro do cliente', 'texto' => tao_entregas_end_txt( $c ) ];
    }
    $re = tao_entregas_api( "/contato_enderecos?contato_id=eq.$contato&select=id,apelido,cep,logradouro,numero,complemento,bairro,cidade,uf&order=criado_em.asc&limit=30" );
    foreach ( ( ! empty( $re['ok'] ) ? $re['data'] : [] ) as $e )
        $out[] = [ 'id' => $e['id'], 'apelido' => $e['apelido'] ?: 'Endereço', 'texto' => tao_entregas_end_txt( $e ) ];
    wp_send_json_success( $out );
} );
add_action( 'wp_ajax_tao_entregas_end_salvar', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_entregas_nonce', 'nonce' );
    if ( ! tao_entregas_can_access() ) wp_send_json_error( [ 'message' => 'Acesso negado' ], 403 );
    $ws      = sanitize_text_field( $_POST['workspace_id'] ?? '' );
    $contato = sanitize_text_field( $_POST['contato_id'] ?? '' );
    if ( ! $ws || ! $contato ) wp_send_json_error( [ 'message' => 'Cliente não identificado' ] );
    if ( function_exists( 'tao_crm_pode_acessar_ws' ) && ! tao_crm_pode_acessar_ws( $ws ) ) wp_send_json_error( [ 'message' => 'Acesso negado' ], 403 );
    $t = function ( $k ) { $v = trim( sanitize_text_field( $_POST[ $k ] ?? '' ) ); return $v === '' ? null : $v; };
    $r = tao_entregas_api( '/contato_enderecos', 'POST', [
        'workspace_id' => $ws, 'contato_id' => $contato, 'apelido' => $t( 'apelido' ) ?: 'Entrega',
        'cep' => $t( 'cep' ), 'logradouro' => $t( 'logradouro' ), 'numero' => $t( 'numero' ),
        'complemento' => $t( 'complemento' ), 'bairro' => $t( 'bairro' ), 'cidade' => $t( 'cidade' ), 'uf' => $t( 'uf' ),
    ] );
    ( ! empty( $r['ok'] ) && ! empty( $r['data'] ) )
        ? wp_send_json_success( [ 'id' => $r['data'][0]['id'] ] )
        : wp_send_json_error( [ 'message' => 'Erro ao salvar: ' . mb_substr( (string) $r['raw'], 0, 160 ) ] );
} );

/** Cria ou atualiza a entrega do card. */
add_action( 'wp_ajax_tao_entregas_save', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_entregas_nonce', 'nonce' );
    if ( ! tao_entregas_can_access() ) wp_send_json_error( [ 'message' => 'Acesso negado' ], 403 );

    $id   = sanitize_text_field( $_POST['id'] ?? '' );
    $card = sanitize_text_field( $_POST['card_id'] ?? '' );
    $ws   = sanitize_text_field( $_POST['workspace_id'] ?? '' );
    if ( ! $card || ! $ws ) wp_send_json_error( [ 'message' => 'Parâmetros inválidos' ] );
    if ( function_exists( 'tao_crm_pode_acessar_ws' ) && ! tao_crm_pode_acessar_ws( $ws ) ) wp_send_json_error( [ 'message' => 'Acesso negado' ], 403 );

    $tipos = array_keys( tao_entregas_tipos() );
    $txt   = function ( $k ) { $v = trim( sanitize_text_field( $_POST[ $k ] ?? '' ) ); return $v === '' ? null : $v; };
    $num   = function ( $k ) { $v = str_replace( ',', '.', trim( (string) ( $_POST[ $k ] ?? '' ) ) ); return $v === '' ? null : floatval( $v ); };
    $status = in_array( ( $_POST['status'] ?? '' ), [ 'pendente', 'em_rota', 'entregue', 'nao_entregue' ], true ) ? $_POST['status'] : 'pendente';

    // forma de pagamento: vem do cadastro do Caixa e CARREGA o tipo (automático)
    $forma_id = sanitize_text_field( $_POST['forma_pagamento_id'] ?? '' ) ?: null;
    $forma_nome = $forma_tipo = null;
    if ( $forma_id ) {
        $rf = tao_entregas_api( "/caixa_formas_pagamento?id=eq.$forma_id&select=nome,tipo&limit=1" );
        if ( $rf['ok'] && ! empty( $rf['data'] ) ) { $forma_nome = $rf['data'][0]['nome']; $forma_tipo = $rf['data'][0]['tipo']; }
    }
    $pago = ( $_POST['pago'] ?? '' ) === '1';

    $payload = [
        'tipo'               => in_array( ( $_POST['tipo'] ?? '' ), $tipos, true ) ? $_POST['tipo'] : null,
        'custo'              => $num( 'custo' ),
        'valor_receber'      => $num( 'valor_receber' ),
        'forma_pagamento'    => $forma_nome,
        'forma_pagamento_id' => $forma_id,
        'pago_tipo'          => $forma_tipo,          // tipo automático (credito/…/credito_entrega)
        'pago'               => $pago,
        'entregador'         => $txt( 'entregador' ),
        'endereco'           => $txt( 'endereco' ),
        'rastreio'           => $txt( 'rastreio' ),
        'obs'                => $txt( 'obs' ),
        'status'             => $status,
    ];
    // carimba saída/entrega/pagamento
    if ( $status === 'em_rota' )  $payload['dt_saida']   = gmdate( 'c' );
    if ( $status === 'entregue' ) $payload['dt_entrega'] = gmdate( 'c' );
    if ( $pago )                  $payload['pago_em']    = gmdate( 'c' );

    if ( $id ) {
        $r = tao_entregas_api( "/entregas?id=eq.$id", 'PATCH', $payload );
    } else {
        $payload['workspace_id'] = $ws;
        $payload['card_id']      = $card;
        $payload['contato_id']   = sanitize_text_field( $_POST['contato_id'] ?? '' ) ?: null;
        $payload['origem']       = 'crm';
        $payload['criado_por']   = get_current_user_id();
        $r = tao_entregas_api( '/entregas', 'POST', $payload );
    }
    if ( ! $r['ok'] ) wp_send_json_error( [ 'message' => 'Erro ao salvar: ' . mb_substr( (string) $r['raw'], 0, 200 ) ] );
    $ent_id = $r['data'][0]['id'] ?? $id;

    // Pagamento confirmado (pago + forma) → dá baixa no Caixa. Uma única vez por entrega
    // (idempotência pelo vínculo caixa_pagamento_id). Desacoplado: o Caixa responde ao filtro.
    $baixa = null;
    if ( $pago && $forma_id && $ent_id ) {
        $ja = tao_entregas_api( "/entregas?id=eq.$ent_id&select=caixa_pagamento_id&limit=1" );
        $ja_baixado = ( ! empty( $ja['ok'] ) && ! empty( $ja['data'] ) && ! empty( $ja['data'][0]['caixa_pagamento_id'] ) );
        if ( ! $ja_baixado ) {
            $recibo_id = apply_filters( 'tao_entregas_baixar_no_caixa', null, $card, $ws, $forma_id, $num( 'valor_receber' ) );
            if ( $recibo_id ) {
                tao_entregas_api( "/entregas?id=eq.$ent_id", 'PATCH', [ 'caixa_pagamento_id' => $recibo_id ] );
                $baixa = 'ok';
            }
        }
    }
    wp_send_json_success( [ 'id' => $ent_id, 'baixa_caixa' => $baixa ] );
} );
