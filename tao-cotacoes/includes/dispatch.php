<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Integração com o dispatch do TAO CRM.
 *
 * Quando um fornecedor cadastrado responde no WhatsApp, o card dele é movido
 * para o estágio de handoff (Aguardando Atendimento) com atendimento_humano=true.
 * O restante do fluxo do dispatch cuida do resto: a mensagem é registrada no
 * card normalmente e o chatbot/N8N fica bloqueado pelo atendimento humano.
 */

function tao_cotacoes_limpar_cache_fornecedores( $cliente_id ) {
    // invalida o cache de todos os workspaces do cliente
    $rw = tao_cot_api( "/crm_workspaces?cliente_id=eq.$cliente_id&select=id" );
    if ( $rw['ok'] ) {
        foreach ( $rw['data'] as $w ) delete_transient( 'tao_cot_fnums_' . $w['id'] );
    }
}

/**
 * Retorna ['id'=>..., 'nome'=>...] se $num for de um fornecedor ativo do
 * cliente dono do workspace; null caso contrário. Cache de 5 min.
 */
function tao_cotacoes_num_fornecedor( $ws_id, $num ) {
    $mapa = get_transient( 'tao_cot_fnums_' . $ws_id );
    if ( ! is_array( $mapa ) ) {
        $mapa = [];
        $rw = tao_cot_api( "/crm_workspaces?id=eq.$ws_id&select=cliente_id" );
        $cid = ( $rw['ok'] && ! empty( $rw['data'] ) ) ? ( $rw['data'][0]['cliente_id'] ?? null ) : null;
        if ( $cid ) {
            $rf = tao_cot_api( "/fornecedores?cliente_id=eq.$cid&ativo=eq.true&select=id,nome,whatsapp&limit=500" );
            if ( $rf['ok'] ) {
                foreach ( $rf['data'] as $f ) {
                    $d = preg_replace( '/\D/', '', $f['whatsapp'] ?? '' );
                    if ( strlen( $d ) >= 10 ) $mapa[ $d ] = [ 'id' => $f['id'], 'nome' => $f['nome'] ];
                }
            }
        }
        set_transient( 'tao_cot_fnums_' . $ws_id, $mapa, 5 * MINUTE_IN_SECONDS );
    }
    if ( empty( $mapa ) ) return null;

    $nd = preg_replace( '/\D/', '', (string) $num );
    if ( strlen( $nd ) < 10 ) return null;
    if ( isset( $mapa[ $nd ] ) ) return $mapa[ $nd ];

    // tolerância a variações de formato (55 + 9º dígito): compara pelos sufixos
    foreach ( $mapa as $d => $f ) {
        if ( substr( $d, -8 ) === substr( $nd, -8 ) ) {
            $p1 = substr( $d, 0, 2 ) === '55' ? substr( $d, 2, 2 ) : substr( $d, 0, 2 );
            $p2 = substr( $nd, 0, 2 ) === '55' ? substr( $nd, 2, 2 ) : substr( $nd, 0, 2 );
            if ( $p1 === $p2 ) return $f; // mesmo DDD + mesmos 8 dígitos finais
        }
    }
    return null;
}

/**
 * Mensagem inbound de fornecedor: garante card aberto em handoff, silenciado
 * para o bot, e marca "respondeu" nas cotações abertas desse fornecedor.
 */
function tao_cotacoes_fornecedor_inbound( $ws_id, $num, $contato_id, $inst_id, $pl_id, $handoff_stage_id, $forn ) {
    $agora = gmdate( 'c' );

    // 1. Card aberto do fornecedor neste workspace
    $rc = tao_cot_api( "/crm_cards?workspace_id=eq.$ws_id&contato_whatsapp=eq.$num&fechado=eq.false&select=id,estagio_id,atendimento_humano&order=criado_em.desc&limit=1" );
    $card = ( $rc['ok'] && ! empty( $rc['data'] ) ) ? $rc['data'][0] : null;

    if ( $card ) {
        if ( empty( $card['atendimento_humano'] ) || ( $handoff_stage_id && $card['estagio_id'] !== $handoff_stage_id ) ) {
            $patch = [ 'atendimento_humano' => true ];
            if ( $handoff_stage_id && $card['estagio_id'] !== $handoff_stage_id ) {
                $patch['estagio_id'] = $handoff_stage_id;
                $patch['movido_em']  = $agora;
                tao_cot_api( '/crm_cards_historico', 'POST', [
                    'card_id'        => $card['id'],
                    'de_estagio_id'  => $card['estagio_id'],
                    'para_estagio_id'=> $handoff_stage_id,
                    'motivo'         => 'Fornecedor de cotação respondeu',
                    'criado_em'      => $agora,
                ] );
            }
            tao_cot_api( "/crm_cards?id=eq.{$card['id']}", 'PATCH', $patch );
        }
        $card_id = $card['id'];
    } elseif ( $pl_id && $handoff_stage_id ) {
        $titulo = 'FORNECEDOR — ' . ( $forn['nome'] ?? $num );
        $rn = tao_cot_api( '/crm_cards', 'POST', [
            'workspace_id'       => $ws_id,
            'pipeline_id'        => $pl_id,
            'estagio_id'         => $handoff_stage_id,
            'instancia_id'       => $inst_id,
            'contato_id'         => $contato_id,
            'titulo'             => $titulo,
            'contato_nome'       => $forn['nome'] ?? $num,
            'contato_whatsapp'   => $num,
            'atendimento_humano' => true,
            'criado_em'          => $agora,
            'movido_em'          => $agora,
        ] );
        $card_id = ( $rn['ok'] && ! empty( $rn['data'] ) ) ? $rn['data'][0]['id'] : null;
    } else {
        $card_id = null;
    }

    if ( function_exists( 'tao_crm_lock_chatbot' ) ) {
        tao_crm_lock_chatbot( preg_replace( '/\D/', '', $num ), $ws_id );
    }

    // 2. Marca "respondeu" nas cotações abertas deste fornecedor (uma vez)
    $fid = $forn['id'] ?? null;
    if ( $fid ) {
        $rq = tao_cot_api( "/cotacao_fornecedores?fornecedor_id=eq.$fid&status=eq.enviado&select=id,cotacao_id,cotacoes(status)" );
        if ( $rq['ok'] ) {
            foreach ( $rq['data'] as $p ) {
                $cot_status = $p['cotacoes']['status'] ?? '';
                if ( ! in_array( $cot_status, [ 'enviada', 'recebendo' ], true ) ) continue;
                tao_cot_api( "/cotacao_fornecedores?id=eq.{$p['id']}", 'PATCH', [
                    'status' => 'respondeu', 'respondeu_em' => $agora, 'card_id' => $card_id,
                ] );
                if ( $cot_status === 'enviada' ) {
                    tao_cot_api( "/cotacoes?id=eq.{$p['cotacao_id']}", 'PATCH', [ 'status' => 'recebendo' ] );
                }
            }
        }
    }
}
