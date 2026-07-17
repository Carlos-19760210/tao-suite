<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Passo 3 — Venda nascendo do card ganho.
 * Listener do evento disparado pelo tao-crm quando o card cruza pro Pós-vendas.
 * 100% isolado: try/catch em tudo, idempotente (não duplica), nunca quebra o fluxo do CRM.
 */
add_action( 'tao_caixa_card_ganho', 'tao_caixa_criar_venda_do_card', 10, 2 );

function tao_caixa_criar_venda_do_card( $card_id, $workspace_id ) {
    try {
        if ( ! $card_id || ! function_exists( 'tao_caixa_api' ) ) return;

        // cliente_id: workspace → contexto do usuário (cbpm/default) → orçamento do card
        $cliente_id = '';
        if ( $workspace_id ) {
            $rw = tao_caixa_api( "/crm_workspaces?id=eq.$workspace_id&select=cliente_id&limit=1" );
            if ( $rw['ok'] && ! empty( $rw['data'] ) ) $cliente_id = $rw['data'][0]['cliente_id'] ?? '';
        }
        if ( ! $cliente_id && function_exists( 'tao_caixa_cliente_id' ) ) {
            $cliente_id = tao_caixa_cliente_id();
        }
        if ( ! $cliente_id ) {
            $rco = tao_caixa_api( "/orcamentos?card_id=eq.$card_id&select=cliente_id&limit=1" );
            if ( $rco['ok'] && ! empty( $rco['data'] ) ) $cliente_id = $rco['data'][0]['cliente_id'] ?? '';
        }
        if ( ! $cliente_id ) return;

        // Idempotência: se já há venda para este card, não cria outra
        $rex = tao_caixa_api( "/caixa_vendas?card_id=eq.$card_id&select=id&limit=1" );
        if ( $rex['ok'] && ! empty( $rex['data'] ) ) return;

        // Dados do card (cliente / whatsapp)
        $rc   = tao_caixa_api( "/crm_cards?id=eq.$card_id&select=contato_nome,contato_whatsapp&limit=1" );
        $card = ( $rc['ok'] && ! empty( $rc['data'] ) ) ? $rc['data'][0] : [];

        // Itens do negócio (+ orçamentos do módulo Fórmula, quando o cliente tiver) → itens da venda
        $itens = [];
        $total = 0.0;

        $ri = tao_caixa_api( "/crm_card_itens?card_id=eq.$card_id&select=descricao,quantidade,preco_unitario,total&order=ordem.asc" );
        foreach ( ( $ri['ok'] ? ( $ri['data'] ?? [] ) : [] ) as $it ) {
            $vt = round( floatval( $it['total'] ?? 0 ), 2 );
            $itens[] = [
                'descricao'      => $it['descricao'] ?? 'Item',
                'quantidade'     => floatval( $it['quantidade'] ?? 1 ),
                'valor_unitario' => round( floatval( $it['preco_unitario'] ?? 0 ), 2 ),
                'valor_total'    => $vt,
            ];
            $total += $vt;
        }

        $ro = tao_caixa_api( "/orcamentos?card_id=eq.$card_id&select=id,numero_orcamento,forma_nome,total_orcamento,valor_final_fc" );
        foreach ( ( $ro['ok'] ? ( $ro['data'] ?? [] ) : [] ) as $o ) {
            // Régua do card/Kanban: quando o orçamento veio de IMPORTAÇÃO (FCerta), valor_final_fc > 0
            // é o valor que o cliente aprovou — é ELE que vai ao Caixa, não o total_orcamento (que o
            // motor recalcula na aprovação da OM). Orçamento nascido no TAO usa total_orcamento.
            $vfc = round( floatval( $o['valor_final_fc'] ?? 0 ), 2 );
            $vt  = $vfc > 0 ? $vfc : round( floatval( $o['total_orcamento'] ?? 0 ), 2 );
            $itens[] = [
                'orcamento_id'   => $o['id'] ?? null,
                'descricao'      => trim( 'ORC ' . ( $o['numero_orcamento'] ?? '' ) . ' — ' . ( $o['forma_nome'] ?? 'Fórmula' ) ),
                'quantidade'     => 1,
                'valor_unitario' => $vt,
                'valor_total'    => $vt,
            ];
            $total += $vt;
        }

        // Cria a venda (status aberta)
        $rv = tao_caixa_api( '/caixa_vendas', 'POST', [
            'cliente_id'    => $cliente_id,
            'card_id'       => $card_id,
            'origem'        => 'funil',
            'cliente_nome'  => $card['contato_nome'] ?? '',
            'whatsapp'      => $card['contato_whatsapp'] ?? '',
            'valor_total'   => round( $total, 2 ),
            'status'        => 'aberta',
            'criado_em'     => gmdate( 'c' ),
            'atualizado_em' => gmdate( 'c' ),
        ] );
        if ( ! $rv['ok'] || empty( $rv['data'] ) ) return;
        $venda_id = $rv['data'][0]['id'] ?? '';
        if ( ! $venda_id ) return;

        // Itens da venda
        foreach ( $itens as $it ) {
            tao_caixa_api( '/caixa_venda_itens', 'POST', array_merge(
                [ 'cliente_id' => $cliente_id, 'venda_id' => $venda_id, 'criado_em' => gmdate( 'c' ) ],
                $it
            ) );
        }
    } catch ( \Throwable $e ) {
        error_log( '[tao-caixa] criar venda do card ganho falhou: ' . $e->getMessage() );
    }
}

/**
 * Baixa programática do pagamento da venda de UM card (uma forma, quitação total do saldo).
 * Usado quando o pagamento é confirmado fora do PDV (ex.: aba Entrega do card).
 * Idempotente: se a venda já está quitada, não faz nada. Retorna o recibo_id criado (ou null).
 */
function tao_caixa_baixar_card( $cid, $card_id, $forma_id, $valor = null, $parcelas = 1 ) {
    if ( ! $cid || ! $card_id || ! $forma_id || ! function_exists( 'tao_caixa_api' ) ) return null;

    $rv = tao_caixa_api( "/caixa_vendas?card_id=eq.$card_id&cliente_id=eq.$cid&status=in.(aberta,parcial)&select=id,valor_total,valor_pago&order=criado_em.asc&limit=1" );
    if ( ! $rv['ok'] || empty( $rv['data'] ) ) return null;   // sem venda em aberto → nada a baixar
    $v     = $rv['data'][0];
    $saldo = round( (float) $v['valor_total'] - (float) $v['valor_pago'], 2 );
    if ( $saldo <= 0.005 ) return null;
    $val = ( $valor !== null && (float) $valor > 0 ) ? round( (float) $valor, 2 ) : $saldo;
    if ( $val > $saldo ) $val = $saldo;

    $rf = tao_caixa_api( "/caixa_formas_pagamento?id=eq.$forma_id&cliente_id=eq.$cid&select=id,nome,tipo,adquirente_id,taxa_pct,prazo_recebimento_dias&limit=1" );
    if ( ! $rf['ok'] || empty( $rf['data'] ) ) return null;
    $forma    = $rf['data'][0];
    $parcelas = max( 1, (int) $parcelas );
    $adq      = function_exists( 'tao_caixa_adquirente_config' ) ? tao_caixa_adquirente_config( $cid, $forma['adquirente_id'] ?? '' ) : null;
    $tx       = function_exists( 'tao_caixa_resolver_taxa_v2' )
                ? tao_caixa_resolver_taxa_v2( $cid, $forma, $parcelas )
                : ( function_exists( 'tao_caixa_resolver_taxa' ) ? tao_caixa_resolver_taxa( $cid, $forma, $parcelas ) : [ 'taxa_pct' => (float) ( $forma['taxa_pct'] ?? 0 ), 'prazo' => (int) ( $forma['prazo_recebimento_dias'] ?? 0 ) ] );
    $vtaxa    = round( $val * $tx['taxa_pct'] / 100, 2 );
    $vant     = function_exists( 'tao_caixa_calc_antecipacao' ) ? tao_caixa_calc_antecipacao( $adq, $forma['tipo'] ?? '', $parcelas, $val - $vtaxa ) : 0.0;
    $prazo_r  = ( $adq && ( $adq['politica_recebimento'] ?? '' ) === 'antecipado' && in_array( $forma['tipo'] ?? '', [ 'debito', 'credito' ], true ) )
                ? (int) ( $adq['prazo_antecipado_dias'] ?? 1 ) : (int) $tx['prazo'];
    $uid      = get_current_user_id();
    $sess     = function_exists( 'tao_caixa_sessao_aberta' ) ? tao_caixa_sessao_aberta( $cid ) : null;

    $rr = tao_caixa_api( '/caixa_recibos', 'POST', [
        'cliente_id' => $cid, 'valor_total' => $val, 'valor_pago' => $val, 'status' => 'quitado',
        'pagador_nome' => '', 'sessao_id' => $sess['id'] ?? null, 'criado_por' => $uid, 'criado_em' => gmdate( 'c' ),
    ] );
    if ( ! $rr['ok'] || empty( $rr['data'] ) ) return null;
    $recibo_id = $rr['data'][0]['id'];

    $pg = [
        'cliente_id' => $cid, 'recibo_id' => $recibo_id, 'forma_pagamento_id' => $forma_id,
        'adquirente_id' => $forma['adquirente_id'] ?: null, 'modalidade' => $forma['tipo'] ?? null,
        'parcelas' => $parcelas, 'valor_bruto' => $val, 'taxa_pct_aplicada' => $tx['taxa_pct'],
        'valor_taxa' => $vtaxa, 'valor_liquido' => round( $val - $vtaxa - $vant, 2 ),
        'data_prevista_receb' => gmdate( 'Y-m-d', time() + $prazo_r * 86400 ),
        'criado_por' => $uid, 'criado_em' => gmdate( 'c' ),
    ];
    if ( $vant > 0 ) $pg['valor_antecipacao'] = $vant;   // só após a migration (evita 400)
    $rp  = tao_caixa_api( '/caixa_pagamentos', 'POST', $pg );
    $pid = ( $rp['ok'] && ! empty( $rp['data'] ) ) ? ( $rp['data'][0]['id'] ?? null ) : null;
    if ( $pid && function_exists( 'tao_caixa_gerar_recebiveis' ) ) {
        tao_caixa_gerar_recebiveis( $cid, $pid, $adq, $forma['tipo'] ?? '', $parcelas, (float) $pg['valor_liquido'], $prazo_r );
    }

    tao_caixa_api( '/caixa_recibo_vendas', 'POST', [
        'cliente_id' => $cid, 'recibo_id' => $recibo_id, 'venda_id' => $v['id'],
        'valor_aplicado' => $val, 'criado_em' => gmdate( 'c' ),
    ] );
    $np = round( (float) $v['valor_pago'] + $val, 2 );
    $st = $np >= ( (float) $v['valor_total'] - 0.005 ) ? 'quitada' : 'parcial';
    tao_caixa_api( "/caixa_vendas?id=eq.{$v['id']}&cliente_id=eq.$cid", 'PATCH', [
        'valor_pago' => $np, 'status' => $st, 'atualizado_em' => gmdate( 'c' ),
    ] );
    if ( $st === 'quitada' ) do_action( 'tao_caixa_venda_paga', $card_id );

    return $recibo_id;
}

/**
 * Filtro desacoplado: o módulo de Entregas confirma um pagamento e o Caixa dá a baixa.
 * Recebe (null, card_id, workspace_id, forma_pagamento_id, valor) → retorna recibo_id ou o valor original.
 */
add_filter( 'tao_entregas_baixar_no_caixa', function ( $carry, $card_id, $ws, $forma_id, $valor ) {
    try {
        if ( ! empty( $carry ) ) return $carry;              // já baixado por outro
        if ( ! $card_id || ! $forma_id || ! function_exists( 'tao_caixa_api' ) ) return $carry;
        $cid = '';
        if ( $ws ) {
            $rw = tao_caixa_api( "/crm_workspaces?id=eq.$ws&select=cliente_id&limit=1" );
            if ( $rw['ok'] && ! empty( $rw['data'] ) ) $cid = $rw['data'][0]['cliente_id'] ?? '';
        }
        if ( ! $cid && function_exists( 'tao_caixa_cliente_id' ) ) $cid = tao_caixa_cliente_id();
        if ( ! $cid ) return $carry;
        $recibo_id = tao_caixa_baixar_card( $cid, $card_id, $forma_id, $valor );
        return $recibo_id ?: $carry;
    } catch ( \Throwable $e ) {
        error_log( '[tao-caixa] baixa via entrega falhou: ' . $e->getMessage() );
        return $carry;
    }
}, 10, 5 );
