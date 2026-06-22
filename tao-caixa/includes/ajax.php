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
    if ( ! $forma ) wp_send_json_error( 'Selecione a forma de pagamento' );

    $pmin = max( 1, (int) ( $_POST['parcela_min'] ?? 1 ) );
    $pmax = max( $pmin, (int) ( $_POST['parcela_max'] ?? $pmin ) );

    $payload = [
        'forma_pagamento_id'     => $forma,
        'parcela_min'            => $pmin,
        'parcela_max'            => $pmax,
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

// ── PDV — Receber pagamento de uma venda (Fatia 1: 1 pagamento por recibo) ─────

/**
 * Resolve taxa% e prazo (dias) para uma forma × nº de parcelas.
 * Faixa em caixa_taxas sobrepõe a taxa flat da forma.
 */
function tao_caixa_resolver_taxa( $cid, $forma, $parcelas ) {
    $taxa  = (float) ( $forma['taxa_pct'] ?? 0 );
    $prazo = (int) ( $forma['prazo_recebimento_dias'] ?? 0 );
    $fid   = $forma['id'];
    $rt = tao_caixa_api(
        "/caixa_taxas?forma_pagamento_id=eq.$fid&cliente_id=eq.$cid&ativo=eq.true" .
        "&parcela_min=lte.$parcelas&parcela_max=gte.$parcelas&order=parcela_min.desc&limit=1"
    );
    if ( $rt['ok'] && ! empty( $rt['data'] ) ) {
        $taxa  = (float) $rt['data'][0]['taxa_pct'];
        $prazo = (int) $rt['data'][0]['prazo_recebimento_dias'];
    }
    return [ 'taxa_pct' => $taxa, 'prazo' => $prazo ];
}

add_action( 'wp_ajax_tao_caixa_receber_venda', function() {
    $cid = tao_caixa_ajax_guard();

    $venda_id = sanitize_text_field( $_POST['venda_id'] ?? '' );
    $forma_id = sanitize_text_field( $_POST['forma_pagamento_id'] ?? '' );
    $parcelas = max( 1, (int) ( $_POST['parcelas'] ?? 1 ) );
    $valor    = round( (float) str_replace( ',', '.', $_POST['valor'] ?? 0 ), 2 );
    if ( ! $venda_id || ! $forma_id ) wp_send_json_error( 'Dados incompletos' );
    if ( $valor <= 0 ) wp_send_json_error( 'Informe um valor maior que zero' );

    // Venda
    $rv = tao_caixa_api( "/caixa_vendas?id=eq.$venda_id&cliente_id=eq.$cid&select=id,paciente_nome,valor_total,valor_pago,status&limit=1" );
    if ( ! $rv['ok'] || empty( $rv['data'] ) ) wp_send_json_error( 'Venda não encontrada' );
    $venda = $rv['data'][0];
    if ( in_array( $venda['status'] ?? '', [ 'quitada', 'cancelada', 'estornada' ], true ) ) {
        wp_send_json_error( 'Venda já está ' . $venda['status'] );
    }
    $total  = (float) $venda['valor_total'];
    $pago   = (float) $venda['valor_pago'];
    $aberto = round( $total - $pago, 2 );
    if ( $valor > $aberto + 0.001 ) {
        wp_send_json_error( 'Valor acima do saldo a receber (R$ ' . number_format( $aberto, 2, ',', '.' ) . ')' );
    }

    // Forma + taxa
    $rf = tao_caixa_api( "/caixa_formas_pagamento?id=eq.$forma_id&cliente_id=eq.$cid&select=id,nome,tipo,adquirente_id,taxa_pct,prazo_recebimento_dias&limit=1" );
    if ( ! $rf['ok'] || empty( $rf['data'] ) ) wp_send_json_error( 'Forma de pagamento inválida' );
    $forma = $rf['data'][0];
    $tx    = tao_caixa_resolver_taxa( $cid, $forma, $parcelas );
    $valor_taxa = round( $valor * $tx['taxa_pct'] / 100, 2 );
    $valor_liq  = round( $valor - $valor_taxa, 2 );
    $data_prev  = gmdate( 'Y-m-d', time() + $tx['prazo'] * 86400 );
    $uid = get_current_user_id();

    // Recibo
    $rr = tao_caixa_api( '/caixa_recibos', 'POST', [
        'cliente_id'   => $cid,
        'valor_total'  => $valor,
        'valor_pago'   => $valor,
        'status'       => 'quitado',
        'pagador_nome' => $venda['paciente_nome'] ?? '',
        'criado_por'   => $uid,
        'criado_em'    => gmdate( 'c' ),
    ] );
    if ( ! $rr['ok'] || empty( $rr['data'] ) ) wp_send_json_error( 'Falha ao criar recibo: ' . ( $rr['raw'] ?? '' ) );
    $recibo_id = $rr['data'][0]['id'];

    // Vínculo recibo ↔ venda
    tao_caixa_api( '/caixa_recibo_vendas', 'POST', [
        'cliente_id' => $cid, 'recibo_id' => $recibo_id, 'venda_id' => $venda_id,
        'valor_aplicado' => $valor, 'criado_em' => gmdate( 'c' ),
    ] );
    // Pagamento
    tao_caixa_api( '/caixa_pagamentos', 'POST', [
        'cliente_id'          => $cid,
        'recibo_id'           => $recibo_id,
        'forma_pagamento_id'  => $forma_id,
        'adquirente_id'       => $forma['adquirente_id'] ?: null,
        'modalidade'          => $forma['tipo'] ?? null,
        'parcelas'            => $parcelas,
        'valor_bruto'         => $valor,
        'taxa_pct_aplicada'   => $tx['taxa_pct'],
        'valor_taxa'          => $valor_taxa,
        'valor_liquido'       => $valor_liq,
        'data_prevista_receb' => $data_prev,
        'criado_por'          => $uid,
        'criado_em'           => gmdate( 'c' ),
    ] );

    // Baixa na venda
    $novo_pago   = round( $pago + $valor, 2 );
    $novo_status = $novo_pago >= ( $total - 0.001 ) ? 'quitada' : 'parcial';
    tao_caixa_api( "/caixa_vendas?id=eq.$venda_id&cliente_id=eq.$cid", 'PATCH', [
        'valor_pago' => $novo_pago, 'status' => $novo_status, 'atualizado_em' => gmdate( 'c' ),
    ] );

    wp_send_json_success( [
        'venda_status'  => $novo_status,
        'valor_pago'    => $novo_pago,
        'taxa_pct'      => $tx['taxa_pct'],
        'valor_taxa'    => $valor_taxa,
        'valor_liquido' => $valor_liq,
        'data_prevista' => $data_prev,
    ] );
} );
