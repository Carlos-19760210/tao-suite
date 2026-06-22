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

// ── Fase 2: Sessão de caixa (abrir / fechar / fechamento diário) ───────────────

/** Sessão aberta do cliente (ou null). */
function tao_caixa_sessao_aberta( $cid ) {
    $r = tao_caixa_api( "/caixa_sessoes?cliente_id=eq.$cid&status=eq.aberta&order=aberto_em.desc&limit=1" );
    return ( $r['ok'] && ! empty( $r['data'] ) ) ? $r['data'][0] : null;
}

/** Dinheiro físico recebido numa sessão (formas com conta_no_dinheiro). */
function tao_caixa_dinheiro_da_sessao( $cid, $sid ) {
    $rr = tao_caixa_api( "/caixa_recibos?sessao_id=eq.$sid&cliente_id=eq.$cid&status=neq.estornado&select=id" );
    $rids = array_column( $rr['ok'] ? ( $rr['data'] ?? [] ) : [], 'id' );
    if ( ! $rids ) return 0.0;
    $rf = tao_caixa_api( "/caixa_formas_pagamento?cliente_id=eq.$cid&conta_no_dinheiro=eq.true&select=id" );
    $cash_formas = array_flip( array_column( $rf['ok'] ? ( $rf['data'] ?? [] ) : [], 'id' ) );
    if ( ! $cash_formas ) return 0.0;
    $rp = tao_caixa_api( "/caixa_pagamentos?recibo_id=in.(" . implode( ',', $rids ) . ")&estornado=eq.false&select=forma_pagamento_id,valor_bruto" );
    $cash = 0.0;
    foreach ( ( $rp['ok'] ? ( $rp['data'] ?? [] ) : [] ) as $p ) {
        if ( isset( $cash_formas[ $p['forma_pagamento_id'] ] ) ) $cash += (float) $p['valor_bruto'];
    }
    return round( $cash, 2 );
}

add_action( 'wp_ajax_tao_caixa_abrir_sessao', function() {
    $cid = tao_caixa_ajax_guard();
    if ( tao_caixa_sessao_aberta( $cid ) ) wp_send_json_error( 'Já existe um caixa aberto.' );
    $saldo = round( (float) str_replace( ',', '.', $_POST['saldo_inicial'] ?? 0 ), 2 );
    $obs   = sanitize_textarea_field( wp_unslash( $_POST['observacoes'] ?? '' ) );
    $r = tao_caixa_api( '/caixa_sessoes', 'POST', [
        'cliente_id'   => $cid,
        'operador_id'  => get_current_user_id(),
        'aberto_em'    => gmdate( 'c' ),
        'saldo_inicial'=> $saldo,
        'status'       => 'aberta',
        'observacoes'  => $obs !== '' ? $obs : null,
        'criado_em'    => gmdate( 'c' ),
    ] );
    if ( ! $r['ok'] || empty( $r['data'] ) ) wp_send_json_error( 'Falha ao abrir caixa: ' . ( $r['raw'] ?? '' ) );
    wp_send_json_success( $r['data'][0] );
} );

add_action( 'wp_ajax_tao_caixa_fechar_sessao', function() {
    $cid  = tao_caixa_ajax_guard();
    $sess = tao_caixa_sessao_aberta( $cid );
    if ( ! $sess ) wp_send_json_error( 'Nenhum caixa aberto.' );
    $sid = $sess['id'];
    $informado = round( (float) str_replace( ',', '.', $_POST['saldo_final_informado'] ?? 0 ), 2 );
    $cash = tao_caixa_dinheiro_da_sessao( $cid, $sid );
    $calc = round( (float) $sess['saldo_inicial'] + $cash, 2 );
    $div  = round( $informado - $calc, 2 );
    $r = tao_caixa_api( "/caixa_sessoes?id=eq.$sid&cliente_id=eq.$cid", 'PATCH', [
        'fechado_em'            => gmdate( 'c' ),
        'saldo_final_informado' => $informado,
        'saldo_final_calculado' => $calc,
        'divergencia'           => $div,
        'status'                => 'fechada',
    ] );
    if ( ! $r['ok'] ) wp_send_json_error( 'Falha ao fechar caixa: ' . ( $r['raw'] ?? '' ) );
    wp_send_json_success( [ 'calculado' => $calc, 'informado' => $informado, 'divergencia' => $div ] );
} );

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
    $rv = tao_caixa_api( "/caixa_vendas?id=in.(" . implode( ',', $vids ) . ")&cliente_id=eq.$cid&select=id,cliente_nome,valor_total,valor_pago,status&order=criado_em.asc" );
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
    $pagador = ( $vendas[0]['cliente_nome'] ?? '' ) . ( count( $vendas ) > 1 ? ' +' . ( count( $vendas ) - 1 ) : '' );

    // Recibo (cupom) — carimba a sessão de caixa aberta (Fase 2), se houver
    $sess_ab = tao_caixa_sessao_aberta( $cid );
    $rr = tao_caixa_api( '/caixa_recibos', 'POST', [
        'cliente_id'   => $cid, 'valor_total' => $soma, 'valor_pago' => $soma,
        'status'       => 'quitado', 'pagador_nome' => $pagador,
        'sessao_id'    => $sess_ab['id'] ?? null,
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

// ── PDV — Estorno auditado (reverte os recibos que pagaram a venda) ────────────

add_action( 'wp_ajax_tao_caixa_estornar_venda', function() {
    $cid = tao_caixa_ajax_guard();
    $venda_id = sanitize_text_field( $_POST['venda_id'] ?? '' );
    $motivo   = sanitize_textarea_field( wp_unslash( $_POST['motivo'] ?? '' ) );
    if ( ! $venda_id ) wp_send_json_error( 'Venda não informada' );
    if ( $motivo === '' ) wp_send_json_error( 'Informe o motivo do estorno' );

    // Recibos que pagaram essa venda
    $rrv = tao_caixa_api( "/caixa_recibo_vendas?venda_id=eq.$venda_id&cliente_id=eq.$cid&select=recibo_id" );
    $recibo_ids = array_values( array_unique( array_column( $rrv['ok'] ? ( $rrv['data'] ?? [] ) : [], 'recibo_id' ) ) );
    if ( ! $recibo_ids ) wp_send_json_error( 'Nenhum recibo encontrado para esta venda' );

    $uid = get_current_user_id();
    $n_recibos = 0; $afetadas = [];
    foreach ( $recibo_ids as $rid ) {
        $rr = tao_caixa_api( "/caixa_recibos?id=eq.$rid&cliente_id=eq.$cid&select=id,status&limit=1" );
        if ( ! $rr['ok'] || empty( $rr['data'] ) ) continue;
        if ( ( $rr['data'][0]['status'] ?? '' ) === 'estornado' ) continue;

        // 1) Marca o recibo estornado (com auditoria). Se o ALTER não rodou, falha AQUI — sem mexer nas vendas.
        $pr = tao_caixa_api( "/caixa_recibos?id=eq.$rid&cliente_id=eq.$cid", 'PATCH', [
            'status'        => 'estornado',
            'estornado_em'  => gmdate( 'c' ),
            'estornado_por' => $uid,
            'estorno_motivo'=> $motivo,
        ] );
        if ( ! $pr['ok'] ) wp_send_json_error( 'Falha ao estornar (rodou o ALTER de estorno?): ' . ( $pr['raw'] ?? '' ) );
        $n_recibos++;

        // 2) Reverte cada venda coberta por esse recibo
        $rv2 = tao_caixa_api( "/caixa_recibo_vendas?recibo_id=eq.$rid&cliente_id=eq.$cid&select=venda_id,valor_aplicado" );
        foreach ( ( $rv2['ok'] ? ( $rv2['data'] ?? [] ) : [] ) as $line ) {
            $vid = $line['venda_id']; $ap = (float) $line['valor_aplicado'];
            $rvd = tao_caixa_api( "/caixa_vendas?id=eq.$vid&cliente_id=eq.$cid&select=valor_total,valor_pago&limit=1" );
            if ( ! $rvd['ok'] || empty( $rvd['data'] ) ) continue;
            $tot = (float) $rvd['data'][0]['valor_total']; $pago = (float) $rvd['data'][0]['valor_pago'];
            $np  = round( max( 0, $pago - $ap ), 2 );
            $st  = $np <= 0.005 ? 'aberta' : ( $np < $tot - 0.005 ? 'parcial' : 'quitada' );
            tao_caixa_api( "/caixa_vendas?id=eq.$vid&cliente_id=eq.$cid", 'PATCH', [
                'valor_pago' => $np, 'status' => $st, 'atualizado_em' => gmdate( 'c' ),
            ] );
            $afetadas[ $vid ] = true;
        }

        // 3) Marca os pagamentos do recibo como estornados
        tao_caixa_api( "/caixa_pagamentos?recibo_id=eq.$rid&cliente_id=eq.$cid", 'PATCH', [ 'estornado' => true ] );
    }

    if ( ! $n_recibos ) wp_send_json_error( 'Nada a estornar (recibos já estornados).' );
    wp_send_json_success( [ 'recibos' => $n_recibos, 'vendas_afetadas' => count( $afetadas ) ] );
} );

// ── PDV — Venda avulsa (balcão): cria card "Consumidor Final" no Pós-vendas + venda ─

add_action( 'wp_ajax_tao_caixa_venda_avulsa', function() {
    $cid = tao_caixa_ajax_guard();

    $cliente_nome = trim( sanitize_text_field( $_POST['cliente_nome'] ?? '' ) );
    if ( $cliente_nome === '' ) $cliente_nome = 'Consumidor Final';
    $whatsapp = sanitize_text_field( $_POST['whatsapp'] ?? '' );
    $itens    = json_decode( wp_unslash( $_POST['itens'] ?? '[]' ), true );
    if ( ! is_array( $itens ) || ! count( $itens ) ) wp_send_json_error( 'Adicione ao menos um item' );

    // Itens + total
    $linhas = []; $total = 0.0;
    foreach ( $itens as $it ) {
        $desc = trim( sanitize_text_field( $it['descricao'] ?? '' ) );
        $qtd  = max( 1, (float) str_replace( ',', '.', (string) ( $it['quantidade'] ?? 1 ) ) );
        $vu   = round( (float) str_replace( ',', '.', (string) ( $it['valor_unitario'] ?? 0 ) ), 2 );
        if ( $desc === '' || $vu <= 0 ) continue;
        $tt = round( $qtd * $vu, 2 );
        $linhas[] = [ 'descricao' => $desc, 'quantidade' => $qtd, 'valor_unitario' => $vu, 'valor_total' => $tt ];
        $total += $tt;
    }
    if ( ! $linhas ) wp_send_json_error( 'Itens inválidos (descrição e valor são obrigatórios)' );
    $total = round( $total, 2 );

    // Workspace do CRM + pipeline de Pós-vendas + 1º estágio
    if ( ! function_exists( 'tao_crm_get_workspace' ) ) wp_send_json_error( 'CRM indisponível para criar o card.' );
    $ws = tao_crm_get_workspace();
    $ws_id = $ws['id'] ?? '';
    if ( ! $ws_id ) wp_send_json_error( 'Workspace do CRM não encontrado.' );
    $pos_pl = get_option( 'tao_crm_pos_vendas_pipeline_' . $ws_id, '' );
    if ( ! $pos_pl ) {
        $rall = tao_caixa_api( "/crm_pipelines?workspace_id=eq.$ws_id&ativo=eq.true&order=ordem.asc&limit=2" );
        $all  = $rall['ok'] ? ( $rall['data'] ?? [] ) : [];
        if ( count( $all ) >= 2 ) $pos_pl = $all[1]['id'];
    }
    if ( ! $pos_pl ) wp_send_json_error( 'Pipeline de Pós-vendas não configurado.' );
    $rst = tao_caixa_api( "/crm_estagios?pipeline_id=eq.$pos_pl&order=ordem.asc&limit=1" );
    $estagio = ( $rst['ok'] && ! empty( $rst['data'] ) ) ? $rst['data'][0]['id'] : '';
    if ( ! $estagio ) wp_send_json_error( 'Pós-vendas sem estágios.' );

    $uid = get_current_user_id();

    // Card "Consumidor Final" (venda de balcão)
    $rc = tao_caixa_api( '/crm_cards', 'POST', [
        'workspace_id'       => $ws_id,
        'pipeline_id'        => $pos_pl,
        'estagio_id'         => $estagio,
        'titulo'             => $cliente_nome,
        'contato_nome'       => $cliente_nome,
        'contato_whatsapp'   => $whatsapp,
        'responsavel_id'     => $uid,
        'status'             => 'aberto',
        'fechado'            => false,
        'valor_oportunidade' => $total,
        'criado_em'          => gmdate( 'c' ),
        'movido_em'          => gmdate( 'c' ),
    ] );
    if ( ! $rc['ok'] || empty( $rc['data'] ) ) wp_send_json_error( 'Falha ao criar card: ' . ( $rc['raw'] ?? '' ) );
    $card_id = $rc['data'][0]['id'];

    // Venda avulsa
    $rv = tao_caixa_api( '/caixa_vendas', 'POST', [
        'cliente_id'    => $cid, 'card_id' => $card_id, 'origem' => 'avulsa',
        'cliente_nome'  => $cliente_nome, 'whatsapp' => $whatsapp,
        'valor_total'   => $total, 'valor_pago' => 0, 'status' => 'aberta',
        'criado_por'    => $uid, 'criado_em' => gmdate( 'c' ), 'atualizado_em' => gmdate( 'c' ),
    ] );
    if ( ! $rv['ok'] || empty( $rv['data'] ) ) wp_send_json_error( 'Falha ao criar venda: ' . ( $rv['raw'] ?? '' ) );
    $venda_id = $rv['data'][0]['id'];

    foreach ( $linhas as $ln ) {
        tao_caixa_api( '/caixa_venda_itens', 'POST', array_merge(
            [ 'cliente_id' => $cid, 'venda_id' => $venda_id, 'criado_em' => gmdate( 'c' ) ], $ln
        ) );
    }

    wp_send_json_success( [ 'venda_id' => $venda_id, 'card_id' => $card_id, 'total' => $total ] );
} );
