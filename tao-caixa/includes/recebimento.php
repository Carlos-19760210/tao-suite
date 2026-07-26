<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Recebimento de NF de entrada (compra) — Fase 1: importar o XML da NFe (mod 55) e
 * exibir a PRÉVIA (fornecedor, itens com lote/validade/NCM, duplicatas/vencimentos).
 * Fase 2 (depois): gerar lote no estoque + título em contas a pagar + entrada SNGPC.
 * Parser namespace-agnóstico (local-name), robusto a prefixos.
 */

function tao_caixa_nfe_parse( $xml ) {
    if ( ! $xml || ! is_string( $xml ) ) return [ 'ok' => false, 'erro' => 'XML vazio.' ];
    $doc = new DOMDocument();
    if ( ! @$doc->loadXML( $xml ) ) return [ 'ok' => false, 'erro' => 'XML inválido / ilegível.' ];
    $xp = new DOMXPath( $doc );
    $ln = function ( $name, $ctx = null ) use ( $xp ) {
        $n = $xp->query( './/*[local-name()="' . $name . '"]', $ctx ?: $xp->query('/')->item(0) );
        return ( $n && $n->length ) ? trim( $n->item(0)->textContent ) : '';
    };
    $node = function ( $name ) use ( $xp ) {
        $n = $xp->query( '//*[local-name()="' . $name . '"]' );
        return ( $n && $n->length ) ? $n->item(0) : null;
    };
    $mod = $ln( 'mod' );
    if ( $mod !== '55' ) return [ 'ok' => false, 'erro' => 'Não é uma NFe modelo 55 (encontrado: ' . ( $mod ?: '—' ) . ').' ];

    $emit = $node( 'emit' ); $dest = $node( 'dest' );
    $emitente = [
        'cnpj'  => $emit ? $ln( 'CNPJ', $emit ) : '',
        'nome'  => $emit ? $ln( 'xNome', $emit ) : '',
    ];
    $nota = [
        'numero' => $ln( 'nNF' ), 'serie' => $ln( 'serie' ),
        'emissao'=> substr( $ln( 'dhEmi' ) ?: $ln( 'dEmi' ), 0, 10 ),
        'tp_nf'  => $ln( 'tpNF' ),   // 0=entrada, 1=saída (do emitente)
        'chave'  => preg_replace( '/\D/', '', $ln( 'chNFe' ) ),
        'dest_cnpj' => $dest ? ( $ln( 'CNPJ', $dest ) ?: $ln( 'CPF', $dest ) ) : '',
    ];

    $itens = [];
    foreach ( $xp->query( '//*[local-name()="det"]' ) as $det ) {
        $prod = $xp->query( './/*[local-name()="prod"]', $det )->item(0);
        if ( ! $prod ) continue;
        // rastro (lote/validade) — pode haver mais de um por item
        $lotes = [];
        foreach ( $xp->query( './/*[local-name()="rastro"]', $det ) as $ras ) {
            $lotes[] = [
                'lote'     => $ln( 'nLote', $ras ),
                'qtd'      => $ln( 'qLote', $ras ),
                'validade' => $ln( 'dVal', $ras ),
            ];
        }
        $itens[] = [
            'cprod'  => $ln( 'cProd', $prod ),
            'ean'    => $ln( 'cEAN', $prod ),
            'desc'   => $ln( 'xProd', $prod ),
            'ncm'    => $ln( 'NCM', $prod ),
            'cest'   => $ln( 'CEST', $prod ),
            'ucom'   => $ln( 'uCom', $prod ),
            'qcom'   => (float) str_replace( ',', '.', $ln( 'qCom', $prod ) ),
            'vun'    => (float) str_replace( ',', '.', $ln( 'vUnCom', $prod ) ),
            'vprod'  => (float) str_replace( ',', '.', $ln( 'vProd', $prod ) ),
            'lotes'  => $lotes,
        ];
    }

    $duplicatas = [];
    foreach ( $xp->query( '//*[local-name()="dup"]' ) as $dup ) {
        $duplicatas[] = [
            'numero' => $ln( 'nDup', $dup ),
            'venc'   => $ln( 'dVenc', $dup ),
            'valor'  => (float) str_replace( ',', '.', $ln( 'vDup', $dup ) ),
        ];
    }

    $tot = $node( 'ICMSTot' );
    return [
        'ok'         => true,
        'emitente'   => $emitente,
        'nota'       => $nota,
        'itens'      => $itens,
        'duplicatas' => $duplicatas,
        'total'      => [
            'produtos' => $tot ? (float) str_replace( ',', '.', $ln( 'vProd', $tot ) ) : 0,
            'nota'     => $tot ? (float) str_replace( ',', '.', $ln( 'vNF', $tot ) ) : 0,
        ],
    ];
}

// AJAX — prévia do XML enviado (não grava nada; Fase 1).
add_action( 'wp_ajax_tao_caixa_receb_preview', function () {
    tao_caixa_ajax_guard();
    $xml = wp_unslash( $_POST['xml'] ?? '' );
    $r   = tao_caixa_nfe_parse( $xml );
    $r['ok'] ? wp_send_json_success( $r ) : wp_send_json_error( $r['erro'] ?? 'Falha ao ler o XML.' );
} );
