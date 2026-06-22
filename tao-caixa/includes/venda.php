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

        $ro = tao_caixa_api( "/orcamentos?card_id=eq.$card_id&select=id,numero_orcamento,forma_nome,total_orcamento" );
        foreach ( ( $ro['ok'] ? ( $ro['data'] ?? [] ) : [] ) as $o ) {
            $vt = round( floatval( $o['total_orcamento'] ?? 0 ), 2 );
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
