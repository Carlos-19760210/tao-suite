<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Fiscal NFC-e (modelo 65) via gateway Focus NFe.
 * INERTE até configurar o emitente (caixa_emitente_fiscal.ativo=true + focus_token).
 * Adaptador isolado: trocar de gateway = trocar este arquivo, sem tocar no PDV.
 * Requer as migrations migration_nfce_v1.sql (+ migration_fiscal_produtos_v1.sql).
 */

function tao_caixa_fiscal_config() {
    $cid = tao_caixa_cliente_id();
    if ( ! $cid ) return null;
    $r = tao_caixa_api( "/caixa_emitente_fiscal?cliente_id=eq.$cid&limit=1" );
    return ( $r['ok'] && ! empty( $r['data'] ) ) ? $r['data'][0] : null;
}

function tao_caixa_focus_base( $amb ) {
    return ( $amb === 'producao' ) ? 'https://api.focusnfe.com.br' : 'https://homologacao.focusnfe.com.br';
}

// Chamada HTTP ao Focus (Basic auth: token como usuário, senha vazia).
function tao_caixa_focus_req( $cfg, $method, $path, $body = null ) {
    $url  = tao_caixa_focus_base( $cfg['ambiente'] ?? 'homologacao' ) . $path;
    $args = [
        'method'  => $method,
        'timeout' => 30,
        'headers' => [
            'Authorization' => 'Basic ' . base64_encode( ( $cfg['focus_token'] ?? '' ) . ':' ),
            'Content-Type'  => 'application/json',
        ],
    ];
    if ( $body !== null ) $args['body'] = wp_json_encode( $body );
    $resp = wp_remote_request( $url, $args );
    if ( is_wp_error( $resp ) ) return [ 'ok' => false, 'status' => 0, 'erro' => $resp->get_error_message(), 'data' => null ];
    $code = (int) wp_remote_retrieve_response_code( $resp );
    $raw  = wp_remote_retrieve_body( $resp );
    return [ 'ok' => ( $code >= 200 && $code < 300 ), 'status' => $code, 'data' => json_decode( $raw, true ), 'raw' => $raw ];
}

// Formas de pagamento da venda (via recibo) → tPag da NFC-e.
function tao_caixa_nfce_formas( $vid ) {
    $out = [];
    $rrv  = tao_caixa_api( "/caixa_recibo_vendas?venda_id=eq.$vid&select=recibo_id" );
    $rids = array_values( array_unique( array_filter( array_column( ( $rrv['ok'] ? ( $rrv['data'] ?? [] ) : [] ), 'recibo_id' ) ) ) );
    if ( $rids ) {
        $rp = tao_caixa_api( "/caixa_pagamentos?recibo_id=in.(" . implode( ',', $rids ) . ")&estornado=eq.false&select=valor_bruto,modalidade" );
        $map = [ 'dinheiro' => '01', 'credito' => '03', 'debito' => '04', 'pix' => '17', 'boleto' => '15' ];
        foreach ( ( $rp['ok'] ? ( $rp['data'] ?? [] ) : [] ) as $p ) {
            $out[] = [ 'forma_pagamento' => $map[ $p['modalidade'] ?? '' ] ?? '99',
                       'valor_pagamento' => round( (float) ( $p['valor_bruto'] ?? 0 ), 2 ) ];
        }
    }
    return $out ?: [ [ 'forma_pagamento' => '01', 'valor_pagamento' => 0 ] ];
}

// Monta o payload NFC-e (Focus) a partir da venda + itens + defaults fiscais.
// Item→fiscal por produto é refinado na homologação (com o contador); aqui usa defaults.
function tao_caixa_nfce_payload( $venda, $cfg ) {
    $vid = $venda['id'];
    $ri  = tao_caixa_api( "/caixa_venda_itens?venda_id=eq.$vid&select=descricao,quantidade,valor_unitario,valor_total" );
    $itens_v = ( $ri['ok'] ? ( $ri['data'] ?? [] ) : [] );
    $regime  = $cfg['regime'] ?? 'simples';
    $items = []; $i = 0;
    foreach ( $itens_v as $it ) {
        $i++;
        $q  = (float) ( $it['quantidade'] ?? 1 ) ?: 1;
        $vt = round( (float) ( $it['valor_total'] ?? 0 ), 2 );
        $vu = round( (float) ( $it['valor_unitario'] ?? ( $q ? $vt / $q : 0 ) ), 2 );
        $item = [
            'numero_item'               => $i,
            'codigo_produto'            => 'IT' . $i,
            'descricao'                 => mb_substr( (string) ( $it['descricao'] ?: 'Item' ), 0, 120 ),
            'cfop'                      => $cfg['cfop_venda'] ?? '5102',
            'unidade_comercial'         => 'UN',
            'quantidade_comercial'      => $q,
            'valor_unitario_comercial'  => $vu,
            'valor_bruto'               => $vt,
            'unidade_tributavel'        => 'UN',
            'quantidade_tributavel'     => $q,
            'valor_unitario_tributavel' => $vu,
            'ncm'                       => $cfg['ncm_manipulado'] ?? '30049099',
            'icms_origem'               => $cfg['origem_padrao'] ?? '0',
            'icms_situacao_tributaria'  => ( $regime === 'simples' ) ? ( $cfg['csosn_padrao'] ?? '102' ) : ( $cfg['cst_padrao'] ?? '102' ),
            'pis_situacao_tributaria'   => '99',
            'cofins_situacao_tributaria'=> '99',
        ];
        $items[] = $item;
    }
    return [
        'cnpj_emitente'      => preg_replace( '/\D/', '', (string) ( $cfg['cnpj'] ?? '' ) ),
        'data_emissao'       => gmdate( 'c' ),
        'presenca_comprador' => '1',
        'modalidade_frete'   => '9',
        'local_destino'      => '1',
        'natureza_operacao'  => 'VENDA AO CONSUMIDOR',
        'items'              => $items,
        'formas_pagamento'   => tao_caixa_nfce_formas( $vid ),
    ];
}

// Aplica o retorno do Focus (autorizado/erro) num patch da venda.
function tao_caixa_nfce_aplicar_retorno( &$patch, $data ) {
    if ( ! is_array( $data ) ) return;
    $st = $data['status'] ?? '';
    if ( $st === 'autorizado' ) {
        $patch['nfce_status']    = 'autorizada';
        $patch['nfce_chave']     = $data['chave_nfe'] ?? ( $data['chave'] ?? null );
        $patch['nfce_protocolo'] = $data['protocolo'] ?? null;
        $patch['nfce_numero']    = isset( $data['numero'] ) ? (int) $data['numero'] : null;
        $patch['nfce_serie']     = $data['serie'] ?? null;
        $patch['nfce_danfe_url'] = $data['caminho_danfe'] ?? null;
        $patch['nfce_xml_url']   = $data['caminho_xml_nota_fiscal'] ?? null;
        $patch['nfce_erro']      = null;
    } elseif ( in_array( $st, [ 'erro_autorizacao', 'denegado' ], true ) ) {
        $patch['nfce_status'] = 'rejeitada';
        $patch['nfce_erro']   = $data['mensagem_sefaz'] ?? ( $data['erros'][0]['mensagem'] ?? 'Rejeitada pela SEFAZ' );
    }
}

function tao_caixa_nfce_emitir( $venda_id ) {
    $cfg = tao_caixa_fiscal_config();
    if ( ! $cfg || empty( $cfg['ativo'] ) || empty( $cfg['focus_token'] ) )
        return [ 'ok' => false, 'erro' => 'Emissão fiscal não configurada (emitente/token/ativo).' ];
    $cid = tao_caixa_cliente_id();
    $rv  = tao_caixa_api( "/caixa_vendas?id=eq.$venda_id&cliente_id=eq.$cid&limit=1" );
    if ( ! $rv['ok'] || empty( $rv['data'] ) ) return [ 'ok' => false, 'erro' => 'Venda não encontrada.' ];
    $venda = $rv['data'][0];
    if ( in_array( $venda['nfce_status'] ?? '', [ 'autorizada', 'processando' ], true ) )
        return [ 'ok' => false, 'erro' => 'Venda já possui NFC-e (' . $venda['nfce_status'] . ').' ];

    $ref     = 'v' . substr( str_replace( '-', '', $venda_id ), 0, 24 );
    $payload = tao_caixa_nfce_payload( $venda, $cfg );
    $r       = tao_caixa_focus_req( $cfg, 'POST', "/v2/nfce?ref=$ref", $payload );

    $patch = [ 'nfce_ref' => $ref, 'nfce_status' => 'processando',
               'nfce_ambiente' => $cfg['ambiente'] ?? 'homologacao', 'nfce_emitido_em' => gmdate( 'c' ) ];
    tao_caixa_nfce_aplicar_retorno( $patch, $r['data'] );          // se já veio resolvido
    if ( ! $r['ok'] && ( $patch['nfce_status'] === 'processando' ) ) {
        $patch['nfce_status'] = 'rejeitada';
        $patch['nfce_erro']   = is_array( $r['data'] ) ? ( $r['data']['mensagem'] ?? $r['raw'] ) : ( $r['erro'] ?? $r['raw'] ?? 'Falha na chamada' );
    }
    tao_caixa_api( "/caixa_vendas?id=eq.$venda_id", 'PATCH', $patch );
    return [ 'ok' => true, 'status' => $patch['nfce_status'], 'ref' => $ref ];
}

function tao_caixa_nfce_consultar( $venda_id ) {
    $cfg = tao_caixa_fiscal_config();
    if ( ! $cfg ) return [ 'ok' => false, 'erro' => 'não configurado' ];
    $cid = tao_caixa_cliente_id();
    $rv  = tao_caixa_api( "/caixa_vendas?id=eq.$venda_id&cliente_id=eq.$cid&select=nfce_ref&limit=1" );
    $ref = ( $rv['ok'] && ! empty( $rv['data'] ) ) ? ( $rv['data'][0]['nfce_ref'] ?? '' ) : '';
    if ( ! $ref ) return [ 'ok' => false, 'erro' => 'venda sem referência fiscal' ];
    $r = tao_caixa_focus_req( $cfg, 'GET', "/v2/nfce/$ref" );
    $patch = [];
    tao_caixa_nfce_aplicar_retorno( $patch, $r['data'] );
    if ( $patch ) tao_caixa_api( "/caixa_vendas?id=eq.$venda_id", 'PATCH', $patch );
    return [ 'ok' => true, 'status' => $patch['nfce_status'] ?? null, 'retorno' => $r['data'] ];
}

function tao_caixa_nfce_cancelar( $venda_id, $motivo ) {
    $cfg = tao_caixa_fiscal_config();
    if ( ! $cfg || empty( $cfg['ativo'] ) ) return [ 'ok' => false, 'erro' => 'não configurado' ];
    if ( mb_strlen( trim( $motivo ) ) < 15 ) return [ 'ok' => false, 'erro' => 'Justificativa exige no mínimo 15 caracteres.' ];
    $cid = tao_caixa_cliente_id();
    $rv  = tao_caixa_api( "/caixa_vendas?id=eq.$venda_id&cliente_id=eq.$cid&select=nfce_ref,nfce_status&limit=1" );
    if ( ! $rv['ok'] || empty( $rv['data'] ) ) return [ 'ok' => false, 'erro' => 'venda não encontrada' ];
    if ( ( $rv['data'][0]['nfce_status'] ?? '' ) !== 'autorizada' ) return [ 'ok' => false, 'erro' => 'só cancela NFC-e autorizada' ];
    $ref = $rv['data'][0]['nfce_ref'] ?? '';
    $r   = tao_caixa_focus_req( $cfg, 'DELETE', "/v2/nfce/$ref", [ 'justificativa' => $motivo ] );
    if ( $r['ok'] ) tao_caixa_api( "/caixa_vendas?id=eq.$venda_id", 'PATCH', [ 'nfce_status' => 'cancelada', 'nfce_cancelado_em' => gmdate( 'c' ) ] );
    return [ 'ok' => $r['ok'], 'retorno' => $r['data'], 'erro' => $r['ok'] ? null : ( is_array( $r['data'] ) ? ( $r['data']['mensagem'] ?? '' ) : $r['raw'] ) ];
}

// ── AJAX ──────────────────────────────────────────────────────────────────────
add_action( 'wp_ajax_tao_caixa_nfce_emitir', function () {
    tao_caixa_ajax_guard();
    $r = tao_caixa_nfce_emitir( sanitize_text_field( $_POST['venda_id'] ?? '' ) );
    $r['ok'] ? wp_send_json_success( $r ) : wp_send_json_error( $r['erro'] ?? 'erro' );
} );
add_action( 'wp_ajax_tao_caixa_nfce_consultar', function () {
    tao_caixa_ajax_guard();
    $r = tao_caixa_nfce_consultar( sanitize_text_field( $_POST['venda_id'] ?? '' ) );
    $r['ok'] ? wp_send_json_success( $r ) : wp_send_json_error( $r['erro'] ?? 'erro' );
} );
add_action( 'wp_ajax_tao_caixa_nfce_cancelar', function () {
    tao_caixa_ajax_guard();
    $r = tao_caixa_nfce_cancelar( sanitize_text_field( $_POST['venda_id'] ?? '' ), sanitize_textarea_field( wp_unslash( $_POST['motivo'] ?? '' ) ) );
    $r['ok'] ? wp_send_json_success( $r ) : wp_send_json_error( $r['erro'] ?? 'erro' );
} );
