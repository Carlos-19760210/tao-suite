<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Integração com o dispatch do TAO CRM.
 *
 * Fornecedor cadastrado NÃO passa pelo CRM: a mensagem é desviada por completo
 * (sem card, sem Kanban, sem N8N/chatbot, sem crm_mensagens) e gravada na
 * conversa do módulo (`fornecedor_mensagens`). A thread pertence ao FORNECEDOR;
 * quando existe cotação aberta com ele, a mensagem recebe a etiqueta da cotação
 * e o participante é marcado como "respondeu".
 */

function tao_cotacoes_limpar_cache_fornecedores( $cliente_id ) {
    // invalida o cache de todos os workspaces do cliente
    $rw = tao_cot_api( "/crm_workspaces?cliente_id=eq.$cliente_id&select=id" );
    if ( $rw['ok'] ) {
        foreach ( $rw['data'] as $w ) delete_transient( 'tao_cot_fnums_' . $w['id'] );
    }
}

/**
 * Retorna ['id'=>..., 'nome'=>..., 'cliente_id'=>...] se $num for de um
 * fornecedor ativo do cliente dono do workspace; null caso contrário.
 * Cache de 5 min por workspace.
 */
function tao_cotacoes_num_fornecedor( $ws_id, $num ) {
    $cache = get_transient( 'tao_cot_fnums_' . $ws_id );
    if ( ! is_array( $cache ) || ! isset( $cache['nums'] ) ) {
        $cache = [ 'cid' => null, 'nums' => [] ];
        $rw  = tao_cot_api( "/crm_workspaces?id=eq.$ws_id&select=cliente_id" );
        $cid = ( $rw['ok'] && ! empty( $rw['data'] ) ) ? ( $rw['data'][0]['cliente_id'] ?? null ) : null;
        if ( $cid ) {
            $cache['cid'] = $cid;
            $rf = tao_cot_api( "/fornecedores?cliente_id=eq.$cid&ativo=eq.true&select=id,nome,whatsapp&limit=500" );
            if ( $rf['ok'] ) {
                foreach ( $rf['data'] as $f ) {
                    $d = preg_replace( '/\D/', '', $f['whatsapp'] ?? '' );
                    if ( strlen( $d ) >= 10 ) $cache['nums'][ $d ] = [ 'id' => $f['id'], 'nome' => $f['nome'] ];
                }
            }
        }
        set_transient( 'tao_cot_fnums_' . $ws_id, $cache, 5 * MINUTE_IN_SECONDS );
    }
    if ( empty( $cache['nums'] ) ) return null;

    $nd = preg_replace( '/\D/', '', (string) $num );
    if ( strlen( $nd ) < 10 ) return null;

    $hit = null;
    if ( isset( $cache['nums'][ $nd ] ) ) {
        $hit = $cache['nums'][ $nd ];
    } else {
        // tolerância a variações de formato (55 + 9º dígito): DDD + 8 dígitos finais
        foreach ( $cache['nums'] as $d => $f ) {
            if ( substr( $d, -8 ) === substr( $nd, -8 ) ) {
                $p1 = substr( $d, 0, 2 ) === '55' ? substr( $d, 2, 2 ) : substr( $d, 0, 2 );
                $p2 = substr( $nd, 0, 2 ) === '55' ? substr( $nd, 2, 2 ) : substr( $nd, 0, 2 );
                if ( $p1 === $p2 ) { $hit = $f; break; }
            }
        }
    }
    if ( ! $hit ) return null;
    $hit['cliente_id'] = $cache['cid'];
    return $hit;
}

/**
 * Cotação aberta (enviada/recebendo) mais recente que inclui o fornecedor.
 * Retorna ['participante_id'=>..., 'cotacao_id'=>..., 'status_part'=>..., 'status_cot'=>...] ou null.
 */
function tao_cotacoes_cotacao_aberta_do_fornecedor( $fornecedor_id ) {
    $r = tao_cot_api( "/cotacao_fornecedores?fornecedor_id=eq.$fornecedor_id&select=id,status,cotacao_id,cotacoes(status,enviado_em)&order=enviado_em.desc.nullslast&limit=10" );
    if ( ! $r['ok'] ) return null;
    foreach ( $r['data'] as $p ) {
        $st = $p['cotacoes']['status'] ?? '';
        if ( in_array( $st, [ 'enviada', 'recebendo' ], true ) ) {
            return [
                'participante_id' => $p['id'],
                'cotacao_id'      => $p['cotacao_id'],
                'status_part'     => $p['status'],
                'status_cot'      => $st,
            ];
        }
    }
    return null;
}

/**
 * Chamada pelo dispatch do tao-crm para TODA mensagem (in e out) trocada com
 * um número de fornecedor. Grava na thread do módulo e retorna true — o
 * dispatch então dá `continue` (nada de card/N8N/crm_mensagens).
 */
function tao_cotacoes_fornecedor_msg( $ws_id, $num, $inst_id, $from_me, $tipo, $conteudo, $midia_url, $midia_mime, $forn ) {
    $cid = $forn['cliente_id'] ?? null;
    if ( ! $cid ) return true; // fornecedor sem cliente resolvido: desvia mesmo assim

    // Eco do webhook: msg enviada PELO PAINEL volta como from_me — já foi gravada
    // na hora do envio. Ignora se há out recente equivalente (texto igual, ou
    // qualquer mídia out nos últimos 2 min).
    if ( $from_me ) {
        $desde = rawurlencode( gmdate( 'c', time() - 120 ) );
        $chk = tao_cot_api( "/fornecedor_mensagens?fornecedor_id=eq.{$forn['id']}&direcao=eq.out&criado_em=gte.$desde&select=tipo,conteudo&order=criado_em.desc&limit=8" );
        if ( $chk['ok'] ) {
            foreach ( $chk['data'] as $mm ) {
                $eco_txt   = $tipo === 'text' && trim( (string) $mm['conteudo'] ) === trim( (string) $conteudo );
                $eco_midia = $tipo !== 'text' && ( $mm['tipo'] ?? 'text' ) !== 'text';
                if ( $eco_txt || $eco_midia ) return true;
            }
        }
    }

    $aberta = tao_cotacoes_cotacao_aberta_do_fornecedor( $forn['id'] );

    tao_cot_api( '/fornecedor_mensagens', 'POST', [
        'cliente_id'    => $cid,
        'fornecedor_id' => $forn['id'],
        'cotacao_id'    => $aberta['cotacao_id'] ?? null,
        'instancia_id'  => $inst_id,
        'direcao'       => $from_me ? 'out' : 'in',
        'tipo'          => $tipo ?: 'text',
        'conteudo'      => (string) $conteudo,
        'midia_url'     => $midia_url ?: null,
        'midia_mime'    => $midia_mime ?: null,
        'lida'          => (bool) $from_me,
        'criado_em'     => gmdate( 'c' ),
    ] );

    // inbound com cotação aberta: marca "respondeu" e cotação → recebendo
    if ( ! $from_me && $aberta ) {
        if ( $aberta['status_part'] === 'enviado' ) {
            tao_cot_api( "/cotacao_fornecedores?id=eq.{$aberta['participante_id']}", 'PATCH', [
                'status' => 'respondeu', 'respondeu_em' => gmdate( 'c' ),
            ] );
        }
        if ( $aberta['status_cot'] === 'enviada' ) {
            tao_cot_api( "/cotacoes?id=eq.{$aberta['cotacao_id']}", 'PATCH', [ 'status' => 'recebendo' ] );
        }
    }
    return true;
}
