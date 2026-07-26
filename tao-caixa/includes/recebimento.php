<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Recebimento de NF de entrada (compra).
 *  - Parser NFe mod 55 (namespace-agnóstico).
 *  - Conferência: auto-match de/para (fornecedor+cProd -> ativo, com aprendizado) +
 *    3 valores por unidade: custo (mercado), compra (pago s/ frete) e compra COM FRETE
 *    (= compra + rateio do frete por valor) — base do preço de venda.
 *  - Fase 2b (a construir): "Confirmar" grava lote + contas a pagar + entrada SNGPC.
 */

function tao_caixa_nfe_parse( $xml ) {
    if ( ! $xml || ! is_string( $xml ) ) return [ 'ok' => false, 'erro' => 'XML vazio.' ];
    $doc = new DOMDocument();
    if ( ! @$doc->loadXML( $xml ) ) return [ 'ok' => false, 'erro' => 'XML inválido / ilegível.' ];
    $xp   = new DOMXPath( $doc );
    $root = $xp->query( '/' )->item(0);
    $ln = function ( $name, $ctx = null ) use ( $xp, $root ) {
        $n = $xp->query( './/*[local-name()="' . $name . '"]', $ctx ?: $root );
        return ( $n && $n->length ) ? trim( $n->item(0)->textContent ) : '';
    };
    $node = function ( $name ) use ( $xp ) {
        $n = $xp->query( '//*[local-name()="' . $name . '"]' );
        return ( $n && $n->length ) ? $n->item(0) : null;
    };
    $num = function ( $v ) { return (float) str_replace( ',', '.', $v ); };

    if ( $ln( 'mod' ) !== '55' ) return [ 'ok' => false, 'erro' => 'Não é NFe modelo 55 (encontrado: ' . ( $ln('mod') ?: '—' ) . ').' ];
    $emit = $node( 'emit' ); $dest = $node( 'dest' ); $tot = $node( 'ICMSTot' );

    $itens = [];
    foreach ( $xp->query( '//*[local-name()="det"]' ) as $det ) {
        $prod = $xp->query( './/*[local-name()="prod"]', $det )->item(0);
        if ( ! $prod ) continue;
        $lotes = [];
        foreach ( $xp->query( './/*[local-name()="rastro"]', $det ) as $ras ) {
            $lotes[] = [ 'lote' => $ln( 'nLote', $ras ), 'qtd' => $num( $ln( 'qLote', $ras ) ), 'validade' => $ln( 'dVal', $ras ) ];
        }
        $itens[] = [
            'cprod' => $ln( 'cProd', $prod ), 'ean' => $ln( 'cEAN', $prod ), 'desc' => $ln( 'xProd', $prod ),
            'ncm' => $ln( 'NCM', $prod ), 'cest' => $ln( 'CEST', $prod ), 'ucom' => $ln( 'uCom', $prod ),
            'qcom' => $num( $ln( 'qCom', $prod ) ), 'vun' => $num( $ln( 'vUnCom', $prod ) ),
            'vprod' => $num( $ln( 'vProd', $prod ) ), 'vdesc' => $num( $ln( 'vDesc', $prod ) ),
            'lotes' => $lotes,
        ];
    }
    $duplicatas = [];
    foreach ( $xp->query( '//*[local-name()="dup"]' ) as $dup ) {
        $duplicatas[] = [ 'numero' => $ln( 'nDup', $dup ), 'venc' => $ln( 'dVenc', $dup ), 'valor' => $num( $ln( 'vDup', $dup ) ) ];
    }
    return [
        'ok' => true,
        'emitente'   => [ 'cnpj' => $emit ? preg_replace('/\D/','',$ln('CNPJ',$emit)) : '', 'nome' => $emit ? $ln( 'xNome', $emit ) : '' ],
        'nota'       => [ 'numero' => $ln('nNF'), 'serie' => $ln('serie'), 'emissao' => substr($ln('dhEmi')?:$ln('dEmi'),0,10),
                          'chave' => preg_replace('/\D/','',$ln('chNFe')), 'valor_frete' => $tot ? $num($ln('vFrete',$tot)) : 0 ],
        'itens'      => $itens,
        'duplicatas' => $duplicatas,
        'total'      => [ 'produtos' => $tot ? $num($ln('vProd',$tot)) : 0, 'nota' => $tot ? $num($ln('vNF',$tot)) : 0 ],
    ];
}

// Enriquece cada item: rateio do frete + 3 valores + auto-match de/para.
function tao_caixa_receb_enriquecer( $p ) {
    $cid   = tao_caixa_cliente_id();
    $cnpj  = $p['emitente']['cnpj'] ?? '';
    $frete = (float) ( $p['nota']['valor_frete'] ?? 0 );
    $tprod = (float) ( $p['total']['produtos'] ?? 0 ) ?: 1;

    // de/para salvos (fornecedor+cProd)
    $cprods = array_values( array_unique( array_filter( array_column( $p['itens'], 'cprod' ) ) ) );
    $depara = [];
    if ( $cnpj && $cprods ) {
        $inl = implode( ',', array_map( function ( $c ) { return '"' . str_replace( '"', '', $c ) . '"'; }, $cprods ) );
        $rd = tao_caixa_api( "/recebimento_depara?cliente_id=eq.$cid&fornecedor_cnpj=eq.$cnpj&cprod=in.($inl)&select=cprod,ativo_id,ignorar" );
        foreach ( ( $rd['ok'] ? ( $rd['data'] ?? [] ) : [] ) as $d ) $depara[ $d['cprod'] ] = $d;
    }
    // ativos associados (nome/custo)
    $ativo_ids = array_values( array_unique( array_filter( array_map( function ( $d ) { return $d['ativo_id'] ?? null; }, $depara ) ) ) );
    $ativos = [];
    if ( $ativo_ids ) {
        $ra = tao_caixa_api( "/ativos?id=in.(" . implode( ',', $ativo_ids ) . ")&select=id,nome,preco_custo,unidade_padrao" );
        foreach ( ( $ra['ok'] ? ( $ra['data'] ?? [] ) : [] ) as $a ) $ativos[ $a['id'] ] = $a;
    }

    foreach ( $p['itens'] as &$it ) {
        $q     = (float) ( $it['qcom'] ?? 0 ) ?: 1;
        $vprod = (float) ( $it['vprod'] ?? 0 );
        $vdesc = (float) ( $it['vdesc'] ?? 0 );
        $compra_unit = round( ( $vprod - $vdesc ) / $q, 4 );                 // pago s/ frete (unit)
        $frete_unit  = round( ( ( $vprod / $tprod ) * $frete ) / $q, 4 );    // rateio por valor (unit)
        $it['valor_compra']       = $compra_unit;
        $it['frete_rateado']      = $frete_unit;
        $it['valor_compra_frete'] = round( $compra_unit + $frete_unit, 4 );  // BASE de venda
        // de/para
        $dp = $depara[ $it['cprod'] ] ?? null;
        if ( $dp && ! empty( $dp['ignorar'] ) ) {
            $it['ativo_id'] = null; $it['ativo_nome'] = '(ignorado)'; $it['situacao'] = 'ignorar'; $it['valor_custo'] = 0;
        } elseif ( $dp && ! empty( $dp['ativo_id'] ) ) {
            $a = $ativos[ $dp['ativo_id'] ] ?? null;
            $it['ativo_id']   = $dp['ativo_id'];
            $it['ativo_nome'] = $a['nome'] ?? '(ativo)';
            $it['situacao']   = 'associado';
            $it['valor_custo']= ( $a && (float) ( $a['preco_custo'] ?? 0 ) > 0 ) ? (float) $a['preco_custo'] : $compra_unit; // mercado; s/ ref → = compra
        } else {
            $it['ativo_id'] = null; $it['ativo_nome'] = ''; $it['situacao'] = 'novo'; $it['valor_custo'] = $compra_unit;
        }
    }
    unset( $it );
    return $p;
}

// AJAX — prévia/conferência enriquecida.
add_action( 'wp_ajax_tao_caixa_receb_preview', function () {
    tao_caixa_ajax_guard();
    $r = tao_caixa_nfe_parse( wp_unslash( $_POST['xml'] ?? '' ) );
    if ( ! $r['ok'] ) wp_send_json_error( $r['erro'] ?? 'Falha ao ler o XML.' );
    wp_send_json_success( tao_caixa_receb_enriquecer( $r ) );
} );

// AJAX — busca de ativo (autocomplete) para associar manualmente.
add_action( 'wp_ajax_tao_caixa_ativo_busca', function () {
    tao_caixa_ajax_guard();
    $cid = tao_caixa_cliente_id();
    $q   = trim( sanitize_text_field( $_POST['q'] ?? '' ) );
    if ( mb_strlen( $q ) < 2 ) wp_send_json_success( [] );
    $enc = rawurlencode( $q );
    $r = tao_caixa_api( "/ativos?cliente_id=eq.$cid&nome=ilike.*{$enc}*&select=id,nome,preco_custo,unidade_padrao&order=nome.asc&limit=10" );
    wp_send_json_success( $r['ok'] ? ( $r['data'] ?? [] ) : [] );
} );

// AJAX — salvar (aprender) a associação de/para.
add_action( 'wp_ajax_tao_caixa_receb_depara_save', function () {
    tao_caixa_ajax_guard();
    $cid  = tao_caixa_cliente_id();
    $cnpj = sanitize_text_field( $_POST['fornecedor_cnpj'] ?? '' );
    $cprod= sanitize_text_field( $_POST['cprod'] ?? '' );
    $ativo= sanitize_text_field( $_POST['ativo_id'] ?? '' );
    $ign  = ( $_POST['ignorar'] ?? '' ) === '1';
    if ( ! $cnpj || ! $cprod ) wp_send_json_error( 'Dados insuficientes.' );
    $payload = [ 'ativo_id' => $ativo ?: null, 'ignorar' => $ign, 'atualizado_em' => gmdate('c') ];
    $ex = tao_caixa_api( "/recebimento_depara?cliente_id=eq.$cid&fornecedor_cnpj=eq.$cnpj&cprod=eq.$cprod&limit=1&select=id" );
    if ( $ex['ok'] && ! empty( $ex['data'] ) ) {
        $r = tao_caixa_api( "/recebimento_depara?cliente_id=eq.$cid&fornecedor_cnpj=eq.$cnpj&cprod=eq.$cprod", 'PATCH', $payload );
    } else {
        $r = tao_caixa_api( '/recebimento_depara', 'POST', $payload + [ 'cliente_id' => $cid, 'fornecedor_cnpj' => $cnpj, 'cprod' => $cprod ] );
    }
    $r['ok'] ? wp_send_json_success() : wp_send_json_error( $r['error'] ?? 'Falha ao salvar.' );
} );

// AJAX — CONFIRMAR o recebimento (Fase 2b): grava lote + contas a pagar + base de venda.
// Idempotente por chave. SNGPC entrada dos controlados fica p/ o módulo SNGPC (pendente).
add_action( 'wp_ajax_tao_caixa_receb_confirmar', function () {
    tao_caixa_ajax_guard();
    $cid = tao_caixa_cliente_id();
    $uid = get_current_user_id();
    $dados = json_decode( wp_unslash( $_POST['dados'] ?? '' ), true );
    if ( ! is_array( $dados ) ) wp_send_json_error( 'Dados inválidos.' );
    $nota = $dados['nota'] ?? []; $itens = $dados['itens'] ?? []; $dups = $dados['duplicatas'] ?? [];
    $chave = preg_replace( '/\D/', '', (string) ( $nota['chave'] ?? '' ) );

    foreach ( $itens as $it ) {
        if ( empty( $it['ignorar'] ) && empty( $it['ativo_id'] ) )
            wp_send_json_error( 'Há itens sem ativo associado (associe ou marque como ignorar).' );
    }
    if ( $chave ) {
        $ex = tao_caixa_api( "/recebimento_nf?cliente_id=eq.$cid&chave=eq.$chave&limit=1&select=id" );
        if ( $ex['ok'] && ! empty( $ex['data'] ) ) wp_send_json_error( 'Esta NF já foi processada.' );
    }
    // Fornecedor (cadastro único): busca por CNPJ; cria se não existir.
    $cnpj = preg_replace( '/\D/', '', (string) ( $nota['fornecedor_cnpj'] ?? '' ) );
    $forn_id = null;
    if ( $cnpj ) {
        $rf = tao_caixa_api( "/fornecedores?cnpj=eq.$cnpj&limit=1&select=id" );
        if ( $rf['ok'] && ! empty( $rf['data'] ) ) $forn_id = $rf['data'][0]['id'];
        else {
            $cf = tao_caixa_api( '/fornecedores', 'POST', [ 'cnpj' => $cnpj, 'nome' => $nota['fornecedor_nome'] ?? $cnpj ], [ 'Prefer' => 'return=representation' ] );
            if ( $cf['ok'] && ! empty( $cf['data'] ) ) $forn_id = $cf['data'][0]['id'];
        }
    }
    // Cabeçalho
    $rh = tao_caixa_api( '/recebimento_nf', 'POST', [
        'cliente_id' => $cid, 'fornecedor_cnpj' => $cnpj, 'fornecedor_nome' => $nota['fornecedor_nome'] ?? '',
        'numero' => $nota['numero'] ?? '', 'serie' => $nota['serie'] ?? '', 'chave' => $chave ?: null,
        'emissao' => $nota['emissao'] ?: null, 'valor_produtos' => $nota['valor_produtos'] ?? null,
        'valor_frete' => $nota['valor_frete'] ?? null, 'valor_total' => $nota['valor_total'] ?? null, 'criado_por' => $uid,
    ], [ 'Prefer' => 'return=representation' ] );
    if ( ! $rh['ok'] || empty( $rh['data'] ) ) wp_send_json_error( 'Falha ao criar recebimento: ' . ( $rh['error'] ?? '' ) );
    $rid = $rh['data'][0]['id'];

    $n_lotes = 0; $n_ativos = 0;
    foreach ( $itens as $it ) {
        if ( ! empty( $it['ignorar'] ) || empty( $it['ativo_id'] ) ) continue;
        $ativo = $it['ativo_id'];
        $custo = (float) ( $it['valor_custo'] ?? 0 );
        $compra= (float) ( $it['valor_compra'] ?? 0 );
        $frete = (float) ( $it['frete_rateado'] ?? 0 );
        $cf    = round( $compra + $frete, 4 );
        tao_caixa_api( '/recebimento_nf_itens', 'POST', [
            'recebimento_id' => $rid, 'cliente_id' => $cid, 'ativo_id' => $ativo, 'cprod' => $it['cprod'] ?? '',
            'descricao' => $it['desc'] ?? '', 'ncm' => $it['ncm'] ?? '', 'quantidade' => $it['qcom'] ?? null,
            'unidade' => $it['ucom'] ?? '', 'valor_custo' => $custo, 'valor_compra' => $compra,
            'frete_rateado' => $frete, 'valor_compra_frete' => $cf,
        ] );
        $lotes = ! empty( $it['lotes'] ) ? $it['lotes'] : [ [ 'lote' => '', 'qtd' => $it['qcom'] ?? 0, 'validade' => '' ] ];
        foreach ( $lotes as $l ) {
            tao_caixa_api( '/lab_lotes_mp', 'POST', [
                'cliente_id' => $cid, 'ativo_id' => $ativo, 'nr_lote' => ( $l['lote'] ?? '' ) ?: null,
                'dt_validade' => ( $l['validade'] ?? '' ) ?: null, 'qtd_inicial' => $l['qtd'] ?? null, 'qtd_atual' => $l['qtd'] ?? null,
                'fornecedor_id' => $forn_id, 'nf_chave' => $chave ?: null, 'nf_numero' => $nota['numero'] ?? null,
                'origem' => 'compra', 'unidade' => $it['ucom'] ?? null,
            ] );
            $n_lotes++;
        }
        tao_caixa_api( "/ativos?id=eq.$ativo", 'PATCH', [
            'custo_com_frete' => $cf, 'custo_com_frete_em' => gmdate( 'c' ), 'preco_custo' => $custo, 'preco_compra' => $compra,
        ] );
        $n_ativos++;
    }
    $n_cap = 0;
    foreach ( $dups as $dp ) {
        tao_caixa_api( '/contas_a_pagar', 'POST', [
            'cliente_id' => $cid, 'fornecedor_cnpj' => $cnpj, 'fornecedor_nome' => $nota['fornecedor_nome'] ?? '',
            'recebimento_id' => $rid, 'origem' => 'nf_entrada', 'parcela' => $dp['numero'] ?? '',
            'vencimento' => ( $dp['venc'] ?? '' ) ?: null, 'valor' => $dp['valor'] ?? null, 'status' => 'aberto',
        ] );
        $n_cap++;
    }
    wp_send_json_success( [ 'recebimento_id' => $rid, 'lotes' => $n_lotes, 'ativos' => $n_ativos, 'contas' => $n_cap ] );
} );
