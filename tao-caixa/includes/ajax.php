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

    // Aceita venda_ids[] (cupom multi-card) OU venda_id (single)
    $vids = json_decode( wp_unslash( $_POST['venda_ids'] ?? '' ), true );
    if ( ! is_array( $vids ) || ! $vids ) {
        $single = sanitize_text_field( $_POST['venda_id'] ?? '' );
        $vids = $single ? [ $single ] : [];
    }
    $vids = array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $vids ) ) ) );
    $pags = json_decode( wp_unslash( $_POST['pagamentos'] ?? '[]' ), true );
    if ( ! $vids ) wp_send_json_error( 'Nenhuma venda selecionada' );
    if ( ! is_array( $pags ) || ! count( $pags ) ) wp_send_json_error( 'Adicione ao menos uma forma de pagamento' );

    // Vendas (ordena por criação = FIFO na distribuição)
    $rv = tao_caixa_api( "/caixa_vendas?id=in.(" . implode( ',', $vids ) . ")&cliente_id=eq.$cid&select=id,paciente_nome,valor_total,valor_pago,status&order=criado_em.asc" );
    $raw = $rv['ok'] ? ( $rv['data'] ?? [] ) : [];
    if ( ! $raw ) wp_send_json_error( 'Vendas não encontradas' );
    $vendas = []; $saldo_total = 0.0;
    foreach ( $raw as $v ) {
        if ( in_array( $v['status'] ?? '', [ 'quitada', 'cancelada', 'estornada' ], true ) ) continue;
        $s = round( (float) $v['valor_total'] - (float) $v['valor_pago'], 2 );
        if ( $s <= 0 ) continue;
        $v['_saldo'] = $s; $vendas[] = $v; $saldo_total += $s;
    }
    if ( ! $vendas ) wp_send_json_error( 'Nenhuma venda em aberto entre as selecionadas' );
    $saldo_total = round( $saldo_total, 2 );

    // Mapa de formas
    $rf = tao_caixa_api( "/caixa_formas_pagamento?cliente_id=eq.$cid&select=id,nome,tipo,adquirente_id,taxa_pct,prazo_recebimento_dias" );
    $fmap = [];
    foreach ( ( $rf['ok'] ? ( $rf['data'] ?? [] ) : [] ) as $f ) $fmap[ $f['id'] ] = $f;

    // Pagamentos (split)
    $linhas = []; $soma = 0.0;
    foreach ( $pags as $p ) {
        $fid  = sanitize_text_field( $p['forma_pagamento_id'] ?? '' );
        $parc = max( 1, (int) ( $p['parcelas'] ?? 1 ) );
        $val  = round( (float) str_replace( ',', '.', (string) ( $p['valor'] ?? 0 ) ), 2 );
        if ( ! $fid || $val <= 0 ) continue;
        if ( ! isset( $fmap[ $fid ] ) ) wp_send_json_error( 'Forma de pagamento inválida' );
        $forma = $fmap[ $fid ];
        $tx    = tao_caixa_resolver_taxa( $cid, $forma, $parc );
        $vtaxa = round( $val * $tx['taxa_pct'] / 100, 2 );
        $linhas[] = [
            'forma_pagamento_id'  => $fid,
            'adquirente_id'       => $forma['adquirente_id'] ?: null,
            'modalidade'          => $forma['tipo'] ?? null,
            'parcelas'            => $parc,
            'valor_bruto'         => $val,
            'taxa_pct_aplicada'   => $tx['taxa_pct'],
            'valor_taxa'          => $vtaxa,
            'valor_liquido'       => round( $val - $vtaxa, 2 ),
            'data_prevista_receb' => gmdate( 'Y-m-d', time() + $tx['prazo'] * 86400 ),
        ];
        $soma += $val;
    }
    if ( ! count( $linhas ) ) wp_send_json_error( 'Pagamentos inválidos' );
    $soma = round( $soma, 2 );
    if ( $soma > $saldo_total + 0.005 ) {
        wp_send_json_error( 'Total dos pagamentos acima do saldo (R$ ' . number_format( $saldo_total, 2, ',', '.' ) . ')' );
    }

    $uid = get_current_user_id();
    $pagador = ( $vendas[0]['paciente_nome'] ?? '' ) . ( count( $vendas ) > 1 ? ' +' . ( count( $vendas ) - 1 ) : '' );

    // Recibo (cupom)
    $rr = tao_caixa_api( '/caixa_recibos', 'POST', [
        'cliente_id'   => $cid, 'valor_total' => $soma, 'valor_pago' => $soma,
        'status'       => 'quitado', 'pagador_nome' => $pagador,
        'criado_por'   => $uid, 'criado_em' => gmdate( 'c' ),
    ] );
    if ( ! $rr['ok'] || empty( $rr['data'] ) ) wp_send_json_error( 'Falha ao criar recibo: ' . ( $rr['raw'] ?? '' ) );
    $recibo_id = $rr['data'][0]['id'];

    // Pagamentos
    foreach ( $linhas as $ln ) {
        $ln['cliente_id'] = $cid; $ln['recibo_id'] = $recibo_id;
        $ln['criado_por'] = $uid; $ln['criado_em'] = gmdate( 'c' );
        tao_caixa_api( '/caixa_pagamentos', 'POST', $ln );
    }

    // Distribui o valor recebido entre as vendas (FIFO) + baixa cada uma
    $rem = $soma; $quitadas = 0;
    foreach ( $vendas as $v ) {
        if ( $rem <= 0.005 ) break;
        $ap = round( min( $rem, $v['_saldo'] ), 2 );
        if ( $ap <= 0 ) continue;
        tao_caixa_api( '/caixa_recibo_vendas', 'POST', [
            'cliente_id' => $cid, 'recibo_id' => $recibo_id, 'venda_id' => $v['id'],
            'valor_aplicado' => $ap, 'criado_em' => gmdate( 'c' ),
        ] );
        $np = round( (float) $v['valor_pago'] + $ap, 2 );
        $st = $np >= ( (float) $v['valor_total'] - 0.005 ) ? 'quitada' : 'parcial';
        if ( $st === 'quitada' ) $quitadas++;
        tao_caixa_api( "/caixa_vendas?id=eq.{$v['id']}&cliente_id=eq.$cid", 'PATCH', [
            'valor_pago' => $np, 'status' => $st, 'atualizado_em' => gmdate( 'c' ),
        ] );
        $rem = round( $rem - $ap, 2 );
    }

    wp_send_json_success( [ 'recibo_total' => $soma, 'pagamentos' => count( $linhas ), 'vendas' => count( $vendas ), 'quitadas' => $quitadas ] );
} );
