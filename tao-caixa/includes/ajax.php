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
    // Campos do contrato (migration v2) — só entram se o form os enviou (compatível pré-migration)
    if ( isset( $_POST['politica_recebimento'] ) && in_array( $_POST['politica_recebimento'], [ 'antecipado', 'fluxo' ], true ) ) {
        $payload['politica_recebimento'] = $_POST['politica_recebimento'];
    }
    if ( isset( $_POST['antecipacao_modo'] ) && in_array( $_POST['antecipacao_modo'], [ 'pct_fixo', 'pct_mes' ], true ) ) {
        $payload['antecipacao_modo'] = $_POST['antecipacao_modo'];
    }
    if ( isset( $_POST['prazo_antecipado_dias'] ) && $_POST['prazo_antecipado_dias'] !== '' ) {
        $payload['prazo_antecipado_dias'] = max( 0, (int) $_POST['prazo_antecipado_dias'] );
    }

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
    $adq   = sanitize_text_field( $_POST['adquirente_id'] ?? '' );
    $modal = sanitize_text_field( $_POST['modalidade'] ?? '' );
    // Modelo novo = taxa da OPERADORA (adquirente+modalidade); legado = taxa da forma. Um dos dois.
    if ( ! $adq && ! $forma ) wp_send_json_error( 'Selecione a operadora (ou, no modo legado, a forma de pagamento)' );
    if ( $adq && ! in_array( $modal, [ 'debito', 'credito' ], true ) ) wp_send_json_error( 'Informe a modalidade (débito ou crédito)' );

    $pmin = max( 1, (int) ( $_POST['parcela_min'] ?? 1 ) );
    $pmax = max( $pmin, (int) ( $_POST['parcela_max'] ?? $pmin ) );

    $payload = [
        'forma_pagamento_id'     => $forma ?: null,
        'parcela_min'            => $pmin,
        'parcela_max'            => $pmax,
        'taxa_pct'               => round( (float) str_replace( ',', '.', $_POST['taxa_pct'] ?? 0 ), 3 ),
        'prazo_recebimento_dias' => max( 0, (int) ( $_POST['prazo_recebimento_dias'] ?? 1 ) ),
        'ativo'                  => ( ( $_POST['ativo'] ?? '1' ) === '1' ),
    ];
    if ( $adq ) {
        $payload['adquirente_id'] = $adq;
        $payload['modalidade']    = $modal;
        $bandeira = strtoupper( trim( sanitize_text_field( $_POST['bandeira'] ?? '' ) ) );
        $payload['bandeira'] = $bandeira !== '' ? $bandeira : null;   // null = todas (curinga)
    }

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
    $calc = round( (float) $sess['saldo_inicial'] + $cash + tao_caixa_movimentos_da_sessao( $cid, $sid ), 2 );
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

// ── Aportes e sangrias (migration_caixa_movimentos_v1) ─────────────────────────

/** Líquido de aportes − sangrias de uma sessão. 0.0 se a migration não rodou. */
function tao_caixa_movimentos_da_sessao( $cid, $sid ) {
    $r = tao_caixa_api( "/caixa_movimentos?sessao_id=eq.$sid&cliente_id=eq.$cid&select=tipo,valor" );
    $liq = 0.0;
    foreach ( ( $r['ok'] ? ( $r['data'] ?? [] ) : [] ) as $m ) {
        $liq += ( ( $m['tipo'] ?? '' ) === 'sangria' ? -1 : 1 ) * (float) $m['valor'];
    }
    return round( $liq, 2 );
}

add_action( 'wp_ajax_tao_caixa_lancar_movimento', function() {
    $cid  = tao_caixa_ajax_guard();
    $sess = tao_caixa_sessao_aberta( $cid );
    if ( ! $sess ) wp_send_json_error( 'Nenhum caixa aberto — abra o caixa para lançar aporte/sangria.' );
    $tipo = sanitize_text_field( $_POST['tipo'] ?? '' );
    if ( ! in_array( $tipo, [ 'aporte', 'sangria' ], true ) ) wp_send_json_error( 'Tipo inválido.' );
    $valor = round( (float) str_replace( ',', '.', $_POST['valor'] ?? 0 ), 2 );
    if ( $valor <= 0 ) wp_send_json_error( 'Informe um valor maior que zero.' );
    $motivo = sanitize_textarea_field( wp_unslash( $_POST['motivo'] ?? '' ) );
    if ( $tipo === 'sangria' && $motivo === '' ) wp_send_json_error( 'Informe o motivo da sangria.' );
    $sid = $sess['id'];
    if ( $tipo === 'sangria' ) {
        $gaveta = round( (float) $sess['saldo_inicial'] + tao_caixa_dinheiro_da_sessao( $cid, $sid ) + tao_caixa_movimentos_da_sessao( $cid, $sid ), 2 );
        if ( $valor > $gaveta ) wp_send_json_error( 'Sangria maior que o esperado na gaveta (R$ ' . number_format( $gaveta, 2, ',', '.' ) . ').' );
    }
    $r = tao_caixa_api( '/caixa_movimentos', 'POST', [
        'cliente_id'  => $cid,
        'sessao_id'   => $sid,
        'tipo'        => $tipo,
        'valor'       => $valor,
        'motivo'      => $motivo !== '' ? $motivo : null,
        'operador_id' => get_current_user_id(),
        'criado_em'   => gmdate( 'c' ),
    ] );
    if ( ! $r['ok'] || empty( $r['data'] ) ) wp_send_json_error( 'Falha ao lançar: ' . ( $r['raw'] ?? '' ) );
    wp_send_json_success( $r['data'][0] );
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

// ═══ OPERADORA COMO CONTRATO (migration_caixa_operadora_v2) ═════════════════
// Tudo abaixo degrada em silêncio para o modelo antigo enquanto a migration não
// rodar / os cadastros novos não existirem — nada trava o PDV.

/** Config do contrato da operadora (política de recebimento/antecipação). null = sem config (fallback). */
function tao_caixa_adquirente_config( $cid, $adq_id ) {
    static $cache = [];
    if ( ! $adq_id ) return null;
    if ( array_key_exists( $adq_id, $cache ) ) return $cache[ $adq_id ];
    $r = tao_caixa_api( "/caixa_adquirentes?id=eq.$adq_id&cliente_id=eq.$cid&select=id,nome,taxa_antecipacao_pct,politica_recebimento,antecipacao_modo,prazo_antecipado_dias&limit=1" );
    return $cache[ $adq_id ] = ( $r['ok'] && ! empty( $r['data'] ) ) ? $r['data'][0] : null;
}

/** Taxa v2: MDR da tabela da OPERADORA (modalidade × bandeira × faixa); bandeira exata vence o curinga (NULL). */
function tao_caixa_resolver_taxa_v2( $cid, $forma, $parcelas, $bandeira = '' ) {
    $adq_id = $forma['adquirente_id'] ?? '';
    $modal  = in_array( $forma['tipo'] ?? '', [ 'debito', 'credito' ], true ) ? $forma['tipo'] : '';
    if ( $adq_id && $modal ) {
        $q = "/caixa_taxas?cliente_id=eq.$cid&adquirente_id=eq.$adq_id&modalidade=eq.$modal&ativo=eq.true"
           . "&parcela_min=lte.$parcelas&parcela_max=gte.$parcelas";
        $q .= $bandeira !== ''
            ? '&or=(bandeira.ilike.' . rawurlencode( $bandeira ) . ',bandeira.is.null)'
            : '&bandeira=is.null';
        $q .= '&order=bandeira.desc.nullslast,parcela_min.desc&limit=1';
        $rt = tao_caixa_api( $q );
        if ( $rt['ok'] && ! empty( $rt['data'] ) ) {
            return [ 'taxa_pct' => (float) $rt['data'][0]['taxa_pct'], 'prazo' => (int) ( $rt['data'][0]['prazo_recebimento_dias'] ?? 30 ), 'v2' => true ];
        }
    }
    return tao_caixa_resolver_taxa( $cid, $forma, $parcelas ) + [ 'v2' => false ];
}

/**
 * Custo de ANTECIPAÇÃO (só crédito + operadora em política 'antecipado'), sobre a base líquida de MDR.
 * pct_fixo: % único sobre a base. pct_mes: % a.m. × meses antecipados de cada parcela (parcela k = k meses).
 */
function tao_caixa_calc_antecipacao( $adq, $modalidade, $parcelas, $base ) {
    if ( ! $adq || $modalidade !== 'credito' || $base <= 0 ) return 0.0;
    if ( ( $adq['politica_recebimento'] ?? '' ) !== 'antecipado' ) return 0.0;
    $pct = (float) ( $adq['taxa_antecipacao_pct'] ?? 0 );
    if ( $pct <= 0 ) return 0.0;
    if ( ( $adq['antecipacao_modo'] ?? 'pct_fixo' ) === 'pct_mes' ) {
        $n = max( 1, (int) $parcelas ); $vp = $base / $n; $tot = 0.0;
        for ( $k = 1; $k <= $n; $k++ ) $tot += $vp * ( $pct / 100 ) * $k;
        return round( $tot, 2 );
    }
    return round( $base * $pct / 100, 2 );
}

/**
 * Gera os RECEBÍVEIS de um pagamento — espelha como a operadora paga:
 * antecipado → 1 recebível D+prazo_antecipado; fluxo (crédito parcelado) → 1 por parcela a cada 30d;
 * demais → 1 recebível no prazo resolvido. Falha em silêncio se a migration não rodou.
 */
function tao_caixa_gerar_recebiveis( $cid, $pag_id, $adq, $modalidade, $parcelas, $liquido, $prazo_default, $base_ts = null ) {
    if ( ! $pag_id || $liquido <= 0 ) return;
    $base_ts  = $base_ts ?: time();   // data do pagamento (pode ser retroativa) — prazos contam a partir dela
    $parcelas = max( 1, (int) $parcelas );
    $comum = [ 'cliente_id' => $cid, 'pagamento_id' => $pag_id, 'adquirente_id' => $adq['id'] ?? null ];
    $linhas = [];
    if ( $adq && ( $adq['politica_recebimento'] ?? '' ) === 'fluxo' && $modalidade === 'credito' && $parcelas > 1 ) {
        $vp = floor( ( $liquido / $parcelas ) * 100 ) / 100;
        for ( $k = 1; $k <= $parcelas; $k++ ) {
            $linhas[] = $comum + [
                'parcela_n' => $k, 'parcelas_total' => $parcelas,
                'valor_previsto' => $k === $parcelas ? round( $liquido - $vp * ( $parcelas - 1 ), 2 ) : $vp,
                'data_prevista'  => gmdate( 'Y-m-d', $base_ts + $k * 30 * 86400 ),
            ];
        }
    } else {
        $prazo = ( $adq && ( $adq['politica_recebimento'] ?? '' ) === 'antecipado' )
            ? (int) ( $adq['prazo_antecipado_dias'] ?? 1 )
            : max( 0, (int) $prazo_default );
        $linhas[] = $comum + [
            'parcela_n' => 1, 'parcelas_total' => 1,
            'valor_previsto' => round( $liquido, 2 ),
            'data_prevista'  => gmdate( 'Y-m-d', $base_ts + $prazo * 86400 ),
        ];
    }
    tao_caixa_api( '/caixa_recebiveis', 'POST', $linhas );   // tabela ausente → erro silencioso, PDV segue
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
    $rv = tao_caixa_api( "/caixa_vendas?id=in.(" . implode( ',', $vids ) . ")&cliente_id=eq.$cid&select=id,card_id,cliente_nome,valor_total,valor_pago,status&order=criado_em.asc" );
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
        $forma    = $fmap[ $fid ];
        $bandeira = sanitize_text_field( $p['bandeira'] ?? '' );
        $adq      = tao_caixa_adquirente_config( $cid, $forma['adquirente_id'] ?? '' );
        $tx       = tao_caixa_resolver_taxa_v2( $cid, $forma, $parc, $bandeira );
        $vtaxa    = round( $val * $tx['taxa_pct'] / 100, 2 );
        $vant     = tao_caixa_calc_antecipacao( $adq, $forma['tipo'] ?? '', $parc, $val - $vtaxa );
        $prazo    = ( $adq && ( $adq['politica_recebimento'] ?? '' ) === 'antecipado' && in_array( $forma['tipo'] ?? '', [ 'debito', 'credito' ], true ) )
                    ? (int) ( $adq['prazo_antecipado_dias'] ?? 1 ) : (int) $tx['prazo'];
        $ln = [
            'forma_pagamento_id'  => $fid,
            'adquirente_id'       => $forma['adquirente_id'] ?: null,
            'modalidade'          => $forma['tipo'] ?? null,
            'parcelas'            => $parc,
            'valor_bruto'         => $val,
            'taxa_pct_aplicada'   => $tx['taxa_pct'],
            'valor_taxa'          => $vtaxa,
            'valor_liquido'       => round( $val - $vtaxa - $vant, 2 ),
            'data_prevista_receb' => gmdate( 'Y-m-d', time() + $prazo * 86400 ),
        ];
        // Campos da migration v2 — só entram quando há dado (evita 400 antes da migration)
        if ( $vant > 0 )         $ln['valor_antecipacao'] = $vant;
        if ( $bandeira !== '' )  $ln['bandeira'] = $bandeira;
        $ln['_adq'] = $adq; $ln['_prazo_res'] = $prazo;   // uso interno (removidos antes do POST)
        $linhas[] = $ln;
        $soma += $val;
    }
    if ( ! count( $linhas ) ) wp_send_json_error( 'Pagamentos inválidos' );
    $soma = round( $soma, 2 );

    // Campos do recebimento (PDV): CPF, data do pagamento (hoje ou passada), desconto adicional, cupom fiscal
    $cpf_pag  = sanitize_text_field( $_POST['cpf_pagador'] ?? '' );
    $desc_ad  = round( max( 0, (float) str_replace( ',', '.', (string) ( $_POST['desconto_adicional'] ?? 0 ) ) ), 2 );
    // Alçada (etapa 1): desconto adicional acima do limite do perfil → bloqueia
    if ( $desc_ad > 0 && function_exists( 'tao_crm_alcada' ) ) {
        $lim_alc = tao_crm_alcada( 'pdv.desconto_valor' );
        if ( $lim_alc !== null && $desc_ad > $lim_alc + 0.005 ) {
            wp_send_json_error( 'Desconto de R$ ' . number_format( $desc_ad, 2, ',', '.' )
                . ' acima da sua alçada (máximo R$ ' . number_format( $lim_alc, 2, ',', '.' ) . '). Solicite a um gestor.' );
        }
    }
    // CM: ACRÉSCIMO manual do recebimento (Carlos 14/08 — "chamo CM p/ não chamar de acréscimo").
    // Amplia o teto (saldo + CM) e fica registrado no recibo (valor_cm); as vendas continuam
    // sendo baixadas só até o saldo delas (o excedente é o próprio CM, que entra no bruto/caixa).
    $val_cm   = round( max( 0, (float) str_replace( ',', '.', (string) ( $_POST['valor_cm'] ?? 0 ) ) ), 2 );
    $cupom    = ( $_POST['cupom_fiscal'] ?? '0' ) === '1';
    $dt_pag   = sanitize_text_field( $_POST['data_pagamento'] ?? '' );
    if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $dt_pag ) || $dt_pag > wp_date( 'Y-m-d' ) ) $dt_pag = wp_date( 'Y-m-d' );
    $base_ts  = strtotime( $dt_pag . ' 12:00:00' ) ?: time();

    // CM = ACRÉSCIMO manual (Carlos 14/08): o teto do recebimento é saldo + CM. A distribuição
    // FIFO continua limitada ao saldo das vendas — o excedente (CM) fica no recibo (valor_cm).
    if ( $soma + $desc_ad > $saldo_total + $val_cm + 0.005 ) {
        wp_send_json_error( 'Pagamentos + desconto acima do saldo + CM (R$ ' . number_format( $saldo_total + $val_cm, 2, ',', '.' ) . ')' );
    }

    $uid = get_current_user_id();
    $pagador = ( $vendas[0]['cliente_nome'] ?? '' ) . ( count( $vendas ) > 1 ? ' +' . ( count( $vendas ) - 1 ) : '' );

    // Recibo (cupom) — carimba a sessão de caixa aberta (Fase 2), se houver
    $sess_ab = tao_caixa_sessao_aberta( $cid );
    $recibo_base = [
        'cliente_id'   => $cid, 'valor_total' => $soma, 'valor_pago' => $soma,
        'status'       => 'quitado', 'pagador_nome' => $pagador,
        'sessao_id'    => $sess_ab['id'] ?? null,
        'criado_por'   => $uid, 'criado_em' => gmdate( 'c' ),
    ];
    $recibo_v2 = $recibo_base + [
        'cpf_pagador'    => $cpf_pag ?: null,
        'data_pagamento' => $dt_pag,
        'desconto'       => $desc_ad,
        'cupom_fiscal'   => $cupom,
        'valor_cm'       => $val_cm,
    ];
    $rr = tao_caixa_api( '/caixa_recibos', 'POST', $recibo_v2 );
    if ( ! $rr['ok'] && strpos( (string) ( $rr['raw'] ?? '' ), 'valor_cm' ) !== false ) {
        // migration_caixa_recibo_cm_v1 ainda não rodou — sem CM informado, segue sem o campo
        if ( $val_cm > 0 ) wp_send_json_error( 'CM no recebimento requer a migration migration_caixa_recibo_cm_v1.sql. Rode-a ou zere o CM.' );
        unset( $recibo_v2['valor_cm'] );
        $rr = tao_caixa_api( '/caixa_recibos', 'POST', $recibo_v2 );
    }
    if ( ! $rr['ok'] && strpos( (string) ( $rr['raw'] ?? '' ), 'column' ) !== false ) {
        // migration_caixa_recibo_campos_v1 ainda não rodou
        if ( $desc_ad > 0 ) wp_send_json_error( 'Desconto no recebimento requer a migration de recibo (migration_caixa_recibo_campos_v1.sql). Rode-a ou zere o desconto.' );
        $rr = tao_caixa_api( '/caixa_recibos', 'POST', $recibo_base );
    }
    if ( ! $rr['ok'] || empty( $rr['data'] ) ) wp_send_json_error( 'Falha ao criar recibo: ' . ( $rr['raw'] ?? '' ) );
    $recibo_id = $rr['data'][0]['id'];

    // Pagamentos (+ recebíveis por pagamento — como a operadora paga)
    // Data-base = data do pagamento informada (prazos e recebíveis contam a partir dela)
    foreach ( $linhas as $ln ) {
        $adq = $ln['_adq'] ?? null; $prazo_res = $ln['_prazo_res'] ?? 0;
        unset( $ln['_adq'], $ln['_prazo_res'] );
        $ln['cliente_id'] = $cid; $ln['recibo_id'] = $recibo_id;
        $ln['data_prevista_receb'] = gmdate( 'Y-m-d', $base_ts + ( (int) $prazo_res ) * 86400 );
        $ln['criado_por'] = $uid; $ln['criado_em'] = gmdate( 'c' );
        $rp = tao_caixa_api( '/caixa_pagamentos', 'POST', $ln );
        $pid = ( $rp['ok'] && ! empty( $rp['data'] ) ) ? ( $rp['data'][0]['id'] ?? null ) : null;
        if ( $pid ) tao_caixa_gerar_recebiveis( $cid, $pid, $adq, $ln['modalidade'] ?? '', $ln['parcelas'] ?? 1, (float) $ln['valor_liquido'], $prazo_res, $base_ts );
    }

    // Distribui o valor recebido + desconto adicional entre as vendas (FIFO) + baixa cada uma
    // (o desconto abate saldo como se fosse pagamento — fica registrado no recibo)
    $rem = round( $soma + $desc_ad, 2 ); $quitadas = 0;
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
        // Venda quitada → avisa quem escuta (ex.: tao-entregas marca a entrega paga p/ liberar o NPS)
        if ( $st === 'quitada' && ! empty( $v['card_id'] ) ) do_action( 'tao_caixa_venda_paga', $v['card_id'] );
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
        // Estorno cancela os recebíveis dos pagamentos (conciliação não espera mais por eles)
        $rpe = tao_caixa_api( "/caixa_pagamentos?recibo_id=eq.$rid&cliente_id=eq.$cid&select=id" );
        $pids = array_column( $rpe['ok'] ? ( $rpe['data'] ?? [] ) : [], 'id' );
        if ( $pids ) tao_caixa_api( '/caixa_recebiveis?pagamento_id=in.(' . implode( ',', $pids ) . ")&cliente_id=eq.$cid&status=eq.previsto", 'PATCH', [ 'status' => 'cancelado' ] );
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

// ── Fase 3: Conciliação e antecipação de recebíveis ───────────────────────────

add_action( 'wp_ajax_tao_caixa_conciliar_pagamento', function() {
    $cid = tao_caixa_ajax_guard();
    $id  = sanitize_text_field( $_POST['id'] ?? '' );
    if ( ! $id ) wp_send_json_error( 'ID inválido' );
    $set = ( $_POST['set'] ?? '1' ) === '1';
    $ref = sanitize_text_field( $_POST['referencia'] ?? '' );
    $r = tao_caixa_api( "/caixa_pagamentos?id=eq.$id&cliente_id=eq.$cid", 'PATCH', [
        'conciliado'         => $set,
        'conciliado_em'      => $set ? gmdate( 'c' ) : null,
        'referencia_externa' => ( $set && $ref !== '' ) ? $ref : null,
    ] );
    if ( ! $r['ok'] ) wp_send_json_error( 'Falha: ' . ( $r['raw'] ?? '' ) );
    wp_send_json_success();
} );

add_action( 'wp_ajax_tao_caixa_antecipar_pagamento', function() {
    $cid = tao_caixa_ajax_guard();
    $id  = sanitize_text_field( $_POST['id'] ?? '' );
    if ( ! $id ) wp_send_json_error( 'ID inválido' );
    $rp = tao_caixa_api( "/caixa_pagamentos?id=eq.$id&cliente_id=eq.$cid&select=id,adquirente_id,valor_liquido,valor_taxa,antecipado&limit=1" );
    if ( ! $rp['ok'] || empty( $rp['data'] ) ) wp_send_json_error( 'Pagamento não encontrado' );
    $p = $rp['data'][0];
    if ( ! empty( $p['antecipado'] ) ) wp_send_json_error( 'Pagamento já antecipado' );

    $apct = 0.0;
    if ( ! empty( $p['adquirente_id'] ) ) {
        $ra = tao_caixa_api( "/caixa_adquirentes?id=eq.{$p['adquirente_id']}&cliente_id=eq.$cid&select=taxa_antecipacao_pct&limit=1" );
        if ( $ra['ok'] && ! empty( $ra['data'] ) ) $apct = (float) ( $ra['data'][0]['taxa_antecipacao_pct'] ?? 0 );
    }
    $liq   = (float) $p['valor_liquido'];
    $custo = round( $liq * $apct / 100, 2 );
    $novo  = round( $liq - $custo, 2 );
    $r = tao_caixa_api( "/caixa_pagamentos?id=eq.$id&cliente_id=eq.$cid", 'PATCH', [
        'antecipado'          => true,
        'taxa_antecip_pct'    => $apct,
        'valor_taxa'          => round( (float) $p['valor_taxa'] + $custo, 2 ),
        'valor_liquido'       => $novo,
        'data_prevista_receb' => gmdate( 'Y-m-d' ),
        'conciliado'          => true,
        'conciliado_em'       => gmdate( 'c' ),
    ] );
    if ( ! $r['ok'] ) wp_send_json_error( 'Falha: ' . ( $r['raw'] ?? '' ) );
    wp_send_json_success( [ 'custo_antecip' => $custo, 'novo_liquido' => $novo, 'taxa' => $apct ] );
} );

// ── Exportação XLSX: vendas com recebimento em aberto — toda a base (Carlos 14/08) ──

/** XLSX mínimo (ZipArchive + inline strings) — sem dependências externas. */
function tao_caixa_xlsx_stream( $filename, $headers, $rows ) {
	$esc = function ( $s ) { return htmlspecialchars( (string) $s, ENT_XML1 | ENT_COMPAT, 'UTF-8' ); };
	$sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
		. '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
	$all = array_merge( [ $headers ], $rows );
	foreach ( $all as $ri => $row ) {
		$sheet .= '<row r="' . ( $ri + 1 ) . '">';
		foreach ( $row as $cell ) {
			if ( is_int( $cell ) || is_float( $cell ) ) $sheet .= '<c><v>' . $cell . '</v></c>';
			else $sheet .= '<c t="inlineStr"><is><t xml:space="preserve">' . $esc( $cell ) . '</t></is></c>';
		}
		$sheet .= '</row>';
	}
	$sheet .= '</sheetData></worksheet>';
	$files = [
		'[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
			. '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
			. '<Default Extension="xml" ContentType="application/xml"/>'
			. '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
			. '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>',
		'_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
			. '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
		'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
			. '<sheets><sheet name="Pendentes" sheetId="1" r:id="rId1"/></sheets></workbook>',
		'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
			. '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>',
		'xl/worksheets/sheet1.xml' => $sheet,
	];
	$tmp = tempnam( sys_get_temp_dir(), 'taocx' );
	$zip = new ZipArchive();
	$zip->open( $tmp, ZipArchive::OVERWRITE );
	foreach ( $files as $n => $c ) $zip->addFromString( $n, $c );
	$zip->close();
	while ( ob_get_level() > 0 ) ob_end_clean();
	header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
	header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
	header( 'Content-Length: ' . filesize( $tmp ) );
	readfile( $tmp );
	unlink( $tmp );
	exit;
}

add_action( 'wp_ajax_tao_caixa_export_pendentes', function() {
	$cid = tao_caixa_ajax_guard();
	$vendas = []; $off = 0;
	do {
		$rv = tao_caixa_api( "/caixa_vendas?cliente_id=eq.$cid&status=in.(aberta,parcial)&select=card_id,cliente_nome,whatsapp,valor_total,valor_pago,status,origem,criado_em&order=criado_em.asc&limit=1000&offset=$off" );
		$page = $rv['ok'] ? ( $rv['data'] ?? [] ) : [];
		$vendas = array_merge( $vendas, $page ); $off += 1000;
	} while ( count( $page ) === 1000 );
	// Nº da Requisição (campo CRM) por card
	$req_map  = [];
	$card_ids = array_values( array_filter( array_unique( array_column( $vendas, 'card_id' ) ) ) );
	if ( $card_ids ) {
		$rcd = tao_caixa_api( "/crm_campos_definicao?chave=eq.numero_requisicao&select=id" );
		$campo_ids = $rcd['ok'] ? array_column( $rcd['data'] ?? [], 'id' ) : [];
		if ( $campo_ids ) {
			foreach ( array_chunk( $card_ids, 100 ) as $chunk ) {
				$rvv = tao_caixa_api( "/crm_cards_valores?card_id=in.(" . implode( ',', $chunk ) . ")&campo_id=in.(" . implode( ',', $campo_ids ) . ")&select=card_id,valor" );
				foreach ( ( $rvv['ok'] ? ( $rvv['data'] ?? [] ) : [] ) as $row )
					if ( ! empty( $row['valor'] ) ) $req_map[ $row['card_id'] ] = $row['valor'];
			}
		}
	}
	$rows = []; $tot = 0.0;
	foreach ( $vendas as $v ) {
		$sal = round( max( 0, (float) ( $v['valor_total'] ?? 0 ) - (float) ( $v['valor_pago'] ?? 0 ) ), 2 );
		if ( $sal <= 0.005 ) continue;
		$tot += $sal;
		$rows[] = [
			! empty( $v['criado_em'] ) ? date_i18n( 'd/m/Y', strtotime( $v['criado_em'] ) ) : '',
			(string) ( $v['cliente_nome'] ?? '' ),
			(string) ( $req_map[ $v['card_id'] ?? '' ] ?? '' ),
			(string) ( $v['whatsapp'] ?? '' ),
			( ( $v['origem'] ?? '' ) === 'avulsa' ) ? 'Avulsa' : 'Funil',
			( ( $v['status'] ?? '' ) === 'parcial' ) ? 'Parcial' : 'A receber',
			round( (float) ( $v['valor_total'] ?? 0 ), 2 ),
			round( (float) ( $v['valor_pago'] ?? 0 ), 2 ),
			$sal,
		];
	}
	$rows[] = [ '', 'TOTAL EM ABERTO', '', '', '', '', '', '', round( $tot, 2 ) ];
	tao_caixa_xlsx_stream(
		'pendentes_recebimento_' . wp_date( 'Y-m-d' ) . '.xlsx',
		[ 'Data', 'Cliente', 'Nº Req.', 'WhatsApp', 'Origem', 'Status', 'Total', 'Pago', 'Em aberto' ],
		$rows
	);
} );
