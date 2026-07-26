<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * SNGPC (controlados / ANVISA) — mensagemSNGPC (urn:sngpc-schema).
 *
 * Estratégia SOMBRA (convivência): enquanto a produção/dispensação ainda vive no
 * sistema legado, a farmácia continua gerando o XML lá e o TAO o IMPORTA — parseia,
 * resume, valida a estrutura e guarda o histórico dentro da plataforma. Quando a
 * geração nativa (entradas do Recebimento + saídas da produção quando migrar) bater
 * 100% com o legado por N ciclos, vira a chave.
 *
 * Transmissão fica INERTE atrás de flag (caixa_sngpc_config.ativo=false) — igual NFC-e.
 * Requer a migration migration_sngpc_v1.sql.
 */

function tao_caixa_sngpc_config() {
    $cid = tao_caixa_cliente_id();
    if ( ! $cid ) return null;
    $r = tao_caixa_api( "/caixa_sngpc_config?cliente_id=eq.$cid&limit=1" );
    return ( $r['ok'] && ! empty( $r['data'] ) ) ? $r['data'][0] : null;
}

// Parse namespace-agnóstico do mensagemSNGPC → cabeçalho + contadores por tipo de nó.
function tao_caixa_sngpc_parse( $xml ) {
    if ( ! $xml || ! is_string( $xml ) ) return [ 'ok' => false, 'erro' => 'XML vazio.' ];
    $doc = new DOMDocument();
    if ( ! @$doc->loadXML( $xml ) ) return [ 'ok' => false, 'erro' => 'XML inválido / ilegível.' ];
    $xp = new DOMXPath( $doc );
    $raiz = $xp->query( '/*' )->item( 0 );
    if ( ! $raiz || strtolower( str_replace( 'ns:', '', $raiz->localName ) ) !== 'mensagemsngpc' )
        return [ 'ok' => false, 'erro' => 'Não é um arquivo mensagemSNGPC.' ];

    $g = function ( $name ) use ( $xp ) {
        $n = $xp->query( '//*[local-name()="' . $name . '"]' );
        return ( $n && $n->length ) ? trim( $n->item( 0 )->textContent ) : '';
    };
    $c = function ( $name ) use ( $xp ) {
        $n = $xp->query( '//*[local-name()="' . $name . '"]' );
        return $n ? $n->length : 0;
    };

    return [
        'ok'          => true,
        'cnpj'        => preg_replace( '/\D/', '', $g( 'cnpjEmissor' ) ),
        'transmissor' => preg_replace( '/\D/', '', $g( 'cpfTransmissor' ) ),
        'data_inicio' => $g( 'dataInicio' ),
        'data_fim'    => $g( 'dataFim' ),
        'saidas'      => $c( 'saidaInsumoVendaAoConsumidor' ),
        'perdas'      => $c( 'saidaInsumoPerda' ),
        'entradas'    => $c( 'entradaInsumo' ),
        'medicamentos'=> $c( 'medicamento' ),
    ];
}

// O que o TAO já registra no período (entradas de compra via Recebimento de NF).
// Informativo: base para a geração nativa das ENTRADAS de insumo controlado.
function tao_caixa_sngpc_entradas_nativas( $di, $df ) {
    $cid = tao_caixa_cliente_id();
    $r = tao_caixa_api( "/recebimento_nf?cliente_id=eq.$cid&emissao=gte.$di&emissao=lte.$df&select=id,fornecedor_nome,numero,emissao,valor_total&order=emissao.asc" );
    return $r['ok'] ? ( $r['data'] ?? [] ) : [];
}

// ── AJAX ──────────────────────────────────────────────────────────────────────

// Importar (modo sombra): recebe o XML do sistema atual, valida, resume e grava o fechamento.
add_action( 'wp_ajax_tao_caixa_sngpc_importar', function () {
    tao_caixa_ajax_guard();
    $cid = tao_caixa_cliente_id();
    $uid = get_current_user_id();
    $xml = wp_unslash( $_POST['xml'] ?? '' );
    $p = tao_caixa_sngpc_parse( $xml );
    if ( ! $p['ok'] ) wp_send_json_error( $p['erro'] ?? 'Falha ao ler o XML.' );
    if ( ! $p['data_inicio'] || ! $p['data_fim'] ) wp_send_json_error( 'XML sem período (dataInicio/dataFim).' );

    $cfg = tao_caixa_sngpc_config();
    if ( $cfg && ! empty( $cfg['cnpj_emissor'] ) && $p['cnpj']
         && preg_replace( '/\D/', '', $cfg['cnpj_emissor'] ) !== $p['cnpj'] )
        wp_send_json_error( 'CNPJ do XML (' . $p['cnpj'] . ') difere do emissor configurado.' );

    $r = tao_caixa_api( '/caixa_sngpc_fechamentos', 'POST', [
        'cliente_id' => $cid, 'data_inicio' => $p['data_inicio'], 'data_fim' => $p['data_fim'],
        'origem' => 'importado', 'qtd_saidas' => $p['saidas'], 'qtd_perdas' => $p['perdas'],
        'qtd_entradas' => $p['entradas'], 'qtd_medicamentos' => $p['medicamentos'],
        'status' => 'validado', 'xml' => $xml, 'criado_por' => $uid,
    ], [ 'Prefer' => 'return=representation' ] );
    if ( ! $r['ok'] ) wp_send_json_error( 'Falha ao gravar: ' . ( $r['error'] ?? '' ) );
    wp_send_json_success( [ 'resumo' => $p, 'fechamento' => $r['data'][0] ?? null ] );
} );

// Baixar o XML de um fechamento.
add_action( 'wp_ajax_tao_caixa_sngpc_baixar', function () {
    tao_caixa_ajax_guard();
    $cid = tao_caixa_cliente_id();
    $id  = sanitize_text_field( $_POST['id'] ?? '' );
    $r = tao_caixa_api( "/caixa_sngpc_fechamentos?id=eq.$id&cliente_id=eq.$cid&select=data_inicio,data_fim,xml&limit=1" );
    if ( ! $r['ok'] || empty( $r['data'] ) ) wp_send_json_error( 'Fechamento não encontrado.' );
    $f = $r['data'][0];
    wp_send_json_success( [ 'nome' => 'SNGPC_' . $f['data_inicio'] . '_' . $f['data_fim'] . '.xml', 'xml' => $f['xml'] ] );
} );

// Transmitir — INERTE atrás da flag. Só ativa com config.ativo=true + credenciais + webservice.
add_action( 'wp_ajax_tao_caixa_sngpc_transmitir', function () {
    tao_caixa_ajax_guard();
    $cfg = tao_caixa_sngpc_config();
    if ( ! $cfg || empty( $cfg['ativo'] ) || empty( $cfg['usuario'] ) )
        wp_send_json_error( 'Transmissão SNGPC desligada. Configure usuário/senha e ative a flag para habilitar.' );
    // Webservice de transmissão SNGPC (ANVISA) entra aqui quando a chave virar.
    wp_send_json_error( 'Transmissão automática ainda não habilitada nesta versão — baixe o XML e transmita no portal.' );
} );
