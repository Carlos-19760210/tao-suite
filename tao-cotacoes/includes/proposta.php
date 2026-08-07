<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Fase 2 — processamento de propostas de fornecedores.
 * Extração via Gemini + normalização determinística (regras do consolidar.py)
 * + matching contra ativos/sinônimos + comparativo.
 */

// ── Extração via Gemini ───────────────────────────────────────────────────────

function tao_cot_gemini_key()   { return get_option( 'tao_cotacoes_gemini_key', '' ); }
function tao_cot_gemini_model() { return get_option( 'tao_cotacoes_gemini_model', 'gemini-2.5-flash' ); }

function tao_cot_prompt_extracao() {
    return <<<'PROMPT'
Voce recebe o orcamento/tabela de precos de um FORNECEDOR DE INSUMOS FARMACEUTICOS (materias-primas para farmacia de manipulacao).

Extraia TODOS os itens cotados com preco. Retorne SOMENTE um JSON array, sem markdown, onde cada item e:
{
 "item": "nome do insumo como escrito no documento",
 "preco": 123.45,
 "preco_unidade": "kg" | "g" | "mg" | "L" | "ml" | "unidade" | "milheiro" | "pacote",
 "pacote_qtde": 5000,
 "frac_min": 25,
 "frac_unidade": "kg" | "g" | "ml" | "unidade" | "mil",
 "validade": "MM/AAAA",
 "obs": ""
}

REGRAS IMPORTANTES:
- "preco" e o valor numerico na moeda do documento (use ponto decimal). NUNCA invente precos; item sem preco nao entra.
- "preco_unidade" = a que unidade o preco se refere (ex: "R$/kg" -> "kg"; preco por embalagem/pacote -> "pacote" e informe "pacote_qtde" = quantidade contida, na unidade do item).
- "frac_min" + "frac_unidade" = fracionamento/quantidade minima de compra quando informado (ex: "5 MIL" -> 5 + "mil"; "250 g" -> 250 + "g"; "1 KG" -> 1 + "kg").
- "validade" = validade/lote do material quando informado (formato MM/AAAA); senao "".
- Quando a QUANTIDADE cotada acompanha o preco (ex: "1,00 - KG ... R$ 150,00" = R$150 por 1 kg), o preco se refere aquela quantidade: preco_unidade = unidade da quantidade.
- Cuidado com colunas "Valor KG" vs "Valor Unit": para pos use o valor por kg; para capsulas/unidades use o valor unitario e indique pacote quando for pacote.
- Nao inclua frete, impostos, totais, condicoes de pagamento como itens.
- Campos ausentes: use "" para texto e null para numeros.
PROMPT;
}

/**
 * Envia o arquivo (PDF/imagem) ao Gemini e devolve o array de itens extraídos.
 */
function tao_cot_extrair_arquivo( $binario, $mime ) {
    $key = tao_cot_gemini_key();
    if ( ! $key ) return [ 'ok' => false, 'error' => 'Chave Gemini não configurada (tao_cotacoes_gemini_key)' ];

    $body = [
        'contents' => [ [ 'parts' => [
            [ 'inline_data' => [ 'mime_type' => $mime, 'data' => base64_encode( $binario ) ] ],
            [ 'text' => tao_cot_prompt_extracao() ],
        ] ] ],
        'generationConfig' => [ 'response_mime_type' => 'application/json', 'temperature' => 0.1, 'maxOutputTokens' => 16384 ],
    ];
    $model = tao_cot_gemini_model();
    $resp  = wp_remote_post(
        "https://generativelanguage.googleapis.com/v1beta/models/$model:generateContent?key=" . rawurlencode( $key ),
        [ 'timeout' => 120, 'headers' => [ 'Content-Type' => 'application/json' ], 'body' => wp_json_encode( $body ) ]
    );
    if ( is_wp_error( $resp ) ) return [ 'ok' => false, 'error' => $resp->get_error_message() ];
    $code = wp_remote_retrieve_response_code( $resp );
    $out  = json_decode( wp_remote_retrieve_body( $resp ), true );
    if ( $code >= 400 ) return [ 'ok' => false, 'error' => 'Gemini HTTP ' . $code . ': ' . substr( wp_remote_retrieve_body( $resp ), 0, 200 ) ];

    $txt   = $out['candidates'][0]['content']['parts'][0]['text'] ?? '';
    $itens = json_decode( $txt, true );
    if ( ! is_array( $itens ) ) return [ 'ok' => false, 'error' => 'Resposta da IA não é JSON válido: ' . substr( $txt, 0, 150 ) ];
    return [ 'ok' => true, 'itens' => $itens ];
}

// ── Normalização (regras do consolidar.py) ────────────────────────────────────

function tao_cot_norm_txt( $s ) {
    $s = remove_accents( strtolower( trim( (string) $s ) ) );
    $s = preg_replace( '/[^a-z0-9%]+/', ' ', $s );
    return trim( preg_replace( '/\s+/', ' ', $s ) );
}

function tao_cot_eh_capsula( $nome ) {
    $n = tao_cot_norm_txt( $nome );
    return (bool) preg_match( '/\bcaps?\b|\bcapsula/', $n );
}

/**
 * Normaliza um item extraído para R$/g, R$/ml ou R$/milheiro.
 * Retorna [vl_unit, unid, qtde_min_norm, conversao_log] ou null se não normalizável.
 */
function tao_cot_normalizar_item( $it ) {
    $preco = (float) ( $it['preco'] ?? 0 );
    if ( $preco <= 0 ) return null;
    $pu   = strtolower( trim( (string) ( $it['preco_unidade'] ?? '' ) ) );
    $pq   = (float) ( $it['pacote_qtde'] ?? 0 );
    $fu   = strtolower( trim( (string) ( $it['frac_unidade'] ?? '' ) ) );
    $fmin = (float) ( $it['frac_min'] ?? 0 );
    $caps = tao_cot_eh_capsula( $it['item'] ?? '' );
    $log  = '';

    if ( $caps ) {
        // Cápsula: SEMPRE R$/milheiro (regra do Carlos: cápsula = pacote de 1000)
        if ( $pu === 'pacote' && $pq > 0 )      { $vl = $preco / $pq * 1000; $log = "pacote $pq un -> milheiro"; }
        elseif ( $pu === 'milheiro' )           { $vl = $preco; }
        elseif ( $pu === 'unidade' )            { $vl = $preco * 1000; $log = 'unidade -> milheiro'; }
        else                                    { $vl = $preco; $log = ( $pu ?: '?' ) . ' tratado como milheiro'; }
        $qtde = 0;
        if ( $fmin > 0 ) $qtde = $fu === 'mil' ? $fmin : ( $fu === 'unidade' ? $fmin / 1000 : $fmin );
        elseif ( $pq > 0 ) $qtde = $pq / 1000;
        return [ round( $vl, 4 ), 'milheiro', round( $qtde, 2 ), $log ];
    }

    // massa/volume
    $to_base = function( $q, $u ) {          // devolve [qtd_na_base, base g|ml]
        switch ( $u ) {
            case 'kg': return [ $q * 1000, 'g' ];
            case 'g':  return [ $q, 'g' ];
            case 'mg': return [ $q / 1000, 'g' ];
            case 'l': case 'lt': case 'litro': return [ $q * 1000, 'ml' ];
            case 'ml': return [ $q, 'ml' ];
            default:   return [ $q, 'g' ];
        }
    };

    if ( $pu === 'pacote' && $pq > 0 ) {
        list( $qbase, $base ) = $to_base( $pq, $fu ?: 'g' );
        if ( $qbase <= 0 ) return null;
        $vl  = $preco / $qbase;
        $log = "pacote $pq " . ( $fu ?: 'g' ) . " -> $base";
    } else {
        list( $um, $base ) = $to_base( 1, $pu ?: 'kg' );
        $vl  = $preco / $um;
        if ( $um != 1 ) $log = ( $pu ?: 'kg' ) . " -> $base";
    }
    $qtde = 0;
    if ( $fmin > 0 ) { list( $qtde, ) = $to_base( $fmin, $fu ?: $pu ); }
    elseif ( $pq > 0 ) { list( $qtde, ) = $to_base( $pq, $fu ?: 'g' ); }
    return [ round( $vl, 6 ), $base, round( $qtde, 2 ), $log ];
}

// ── Matching contra ativos + sinônimos ────────────────────────────────────────

/** Catálogo normalizado do cliente (cache 10 min): nome_norm => ativo_id */
function tao_cot_catalogo_norm( $cid ) {
    $cache = get_transient( 'tao_cot_cat_' . $cid );
    if ( is_array( $cache ) ) return $cache;
    $nomes = []; $sins = [];
    $page = 0;
    do {
        $r = tao_cot_api( "/ativos?cliente_id=eq.$cid&ativo=eq.true&select=id,nome&order=id.asc", 'GET', null,
            [ 'Range-Unit' => 'items', 'Range' => ( $page * 1000 ) . '-' . ( $page * 1000 + 999 ) ] );
        $chunk = $r['ok'] ? $r['data'] : [];
        foreach ( $chunk as $a ) $nomes[ tao_cot_norm_txt( $a['nome'] ) ] = $a['id'];
        $page++;
    } while ( count( $chunk ) === 1000 && $page < 10 );
    $page = 0;
    do {
        $r = tao_cot_api( "/ativos_sinonimos?cliente_id=eq.$cid&select=sinonimo,ativo_id&ativo_id=not.is.null&order=id.asc", 'GET', null,
            [ 'Range-Unit' => 'items', 'Range' => ( $page * 1000 ) . '-' . ( $page * 1000 + 999 ) ] );
        $chunk = $r['ok'] ? $r['data'] : [];
        foreach ( $chunk as $s ) $sins[ tao_cot_norm_txt( $s['sinonimo'] ) ] = $s['ativo_id'];
        $page++;
    } while ( count( $chunk ) === 1000 && $page < 10 );
    $cache = [ 'nomes' => $nomes, 'sins' => $sins ];
    set_transient( 'tao_cot_cat_' . $cid, $cache, 10 * MINUTE_IN_SECONDS );
    return $cache;
}

/** Limpa o nome do item do fornecedor p/ matching (remove tamanhos, marcadores). */
function tao_cot_limpa_item( $nome ) {
    $n = (string) $nome;
    $n = preg_replace( '/\s*[-–]\s*\d+[.,]?\d*\s*(kg|g|mg|ml|l|un|und|unidades?)\b.*/i', '', $n ); // "- 1 KG*", "- 5000 UN"
    $n = str_replace( [ '*', '@' ], '', $n );
    return trim( $n );
}

/** Matching: exato (nome/sinônimo) → fuzzy similar_text ≥ 86%. Retorna ativo_id|null. */
function tao_cot_match_ativo( $cid, $item_original ) {
    $cat = tao_cot_catalogo_norm( $cid );
    $n   = tao_cot_norm_txt( tao_cot_limpa_item( $item_original ) );
    if ( $n === '' ) return null;
    if ( isset( $cat['sins'][ $n ] ) )  return $cat['sins'][ $n ];
    if ( isset( $cat['nomes'][ $n ] ) ) return $cat['nomes'][ $n ];
    // fuzzy leve contra os nomes de ativos
    $best = null; $best_pct = 0;
    foreach ( $cat['nomes'] as $nome_norm => $aid ) {
        // pré-filtro barato: 1º token em comum
        if ( strtok( $nome_norm, ' ' ) !== strtok( $n, ' ' ) ) continue;
        similar_text( $n, $nome_norm, $pct );
        if ( $pct > $best_pct ) { $best_pct = $pct; $best = $aid; }
    }
    return $best_pct >= 86 ? $best : null;
}

// ── Pipeline: processa itens extraídos/digitados de uma proposta ─────────────

/**
 * Garante que o fornecedor exista como participante da cotação.
 * Permite registrar o retorno de um fornecedor mesmo que ele não tenha sido
 * convidado no envio (ou que a cotação não tenha sido enviada pelo módulo).
 */
function tao_cot_ensure_participante( $cot_id, $fornecedor_id ) {
    $r = tao_cot_api( "/cotacao_fornecedores?cotacao_id=eq.$cot_id&fornecedor_id=eq.$fornecedor_id&select=id&limit=1" );
    if ( $r['ok'] && ! empty( $r['data'] ) ) return $r['data'][0]['id'];
    $ins = tao_cot_api( '/cotacao_fornecedores', 'POST', [
        'cotacao_id' => $cot_id, 'fornecedor_id' => $fornecedor_id, 'status' => 'pendente',
    ] );
    return ( $ins['ok'] && ! empty( $ins['data'] ) ) ? $ins['data'][0]['id'] : null;
}

function tao_cot_gravar_precos( $cid, $cotacao, $fornecedor_id, $proposta_id, $itens_raw ) {
    // mapa ativo_id -> cotacao_item_id (pra vincular ao item pedido)
    $rit = tao_cot_api( "/cotacao_itens?cotacao_id=eq.{$cotacao['id']}&select=id,ativo_id&limit=500" );
    $item_por_ativo = [];
    foreach ( ( $rit['ok'] ? $rit['data'] : [] ) as $ci ) {
        if ( ! empty( $ci['ativo_id'] ) ) $item_por_ativo[ $ci['ativo_id'] ] = $ci['id'];
    }

    $grav = 0; $diverg = 0;
    foreach ( $itens_raw as $it ) {
        $nome = trim( (string) ( $it['item'] ?? '' ) );
        if ( $nome === '' ) continue;
        $norm = tao_cot_normalizar_item( $it );
        if ( ! $norm ) continue;
        list( $vl, $unid, $qtde, $log ) = $norm;
        $ativo_id = tao_cot_match_ativo( $cid, $nome );

        $row = [
            'cotacao_id'      => $cotacao['id'],
            'fornecedor_id'   => $fornecedor_id,
            'proposta_id'     => $proposta_id,
            'cotacao_item_id' => $ativo_id ? ( $item_por_ativo[ $ativo_id ] ?? null ) : null,
            'ativo_id'        => $ativo_id,
            'item_original'   => $nome,
            'vl_unit'         => $vl,
            'unid'            => $unid,
            'qtde_min'        => $qtde ?: null,
            'vl_total'        => $qtde > 0 ? round( $vl * $qtde, 2 ) : null,   // qtde_min já está na unidade do vl_unit
            'validade'        => trim( (string) ( $it['validade'] ?? '' ) ) ?: null,
            'conversao'       => $log ?: null,
        ];

        $r = tao_cot_api( '/cotacao_precos', 'POST', $row );
        if ( $r['ok'] ) {
            $grav++;
            if ( ! $ativo_id ) $diverg++;
            elseif ( $vl > 0 ) {
                tao_cot_api( '/precos_historico', 'POST', [
                    'cliente_id' => $cid, 'ativo_id' => $ativo_id, 'fornecedor_id' => $fornecedor_id,
                    'cotacao_id' => $cotacao['id'], 'preco' => $vl, 'unid' => $unid,
                ] );
            }
        }
    }
    return [ 'gravados' => $grav, 'divergencias' => $diverg ];
}

// ── Dados do comparativo (usado pela tela e pelo export) ─────────────────────

function tao_cot_comparativo_dados( $cid, $cotacao_id ) {
    $rit   = tao_cot_api( "/cotacao_itens?cotacao_id=eq.$cotacao_id&order=urgente.desc,descricao.asc&limit=500" );
    $itens = $rit['ok'] ? $rit['data'] : [];
    $rp    = tao_cot_api( "/cotacao_precos?cotacao_id=eq.$cotacao_id&select=*,fornecedores(nome)&order=criado_em.asc&limit=2000" );
    $precos = $rp['ok'] ? $rp['data'] : [];

    $fornecedores = [];                       // fid => nome (só quem tem preço)
    $por_item     = [];                       // cotacao_item_id => [fid => melhor preço row]
    $extras       = [];                       // matched a ativo fora da lista
    $diverg       = [];
    foreach ( $precos as $p ) {
        $fid = $p['fornecedor_id'];
        $fornecedores[ $fid ] = $p['fornecedores']['nome'] ?? substr( $fid, 0, 8 );
        if ( ! $p['ativo_id'] ) { $diverg[] = $p; continue; }
        $key = $p['cotacao_item_id'] ?: null;
        if ( ! $key ) { $extras[] = $p; continue; }
        $cur = $por_item[ $key ][ $fid ] ?? null;
        if ( ! $cur || (float) $p['vl_unit'] < (float) $cur['vl_unit'] ) $por_item[ $key ][ $fid ] = $p;
    }
    asort( $fornecedores );

    $linhas = [];
    foreach ( $itens as $it ) {
        $cells  = $por_item[ $it['id'] ] ?? [];
        $melhor = null;
        foreach ( $cells as $fid => $p ) {
            if ( ! $melhor || (float) $p['vl_unit'] < (float) $cells[ $melhor ]['vl_unit'] ) $melhor = $fid;
        }
        $linhas[] = [ 'item' => $it, 'cells' => $cells, 'melhor_fid' => $melhor ];
    }
    return [ 'linhas' => $linhas, 'fornecedores' => $fornecedores, 'divergencias' => $diverg, 'extras' => $extras ];
}

// ── AJAX: processar proposta (arquivo do chat ou upload) ─────────────────────

add_action( 'wp_ajax_tao_cot_proposta_processar', function() {
    $cid = tao_cot_ajax_guard();
    $fid = sanitize_text_field( $_POST['fornecedor_id'] ?? '' );
    $cot_id = sanitize_text_field( $_POST['cotacao_id'] ?? '' );
    if ( ! $fid ) wp_send_json_error( 'Fornecedor inválido' );

    // resolve a cotação: informada, ou a aberta do fornecedor
    if ( ! $cot_id ) {
        $ab = tao_cotacoes_cotacao_aberta_do_fornecedor( $fid );
        $cot_id = $ab['cotacao_id'] ?? '';
    }
    if ( ! $cot_id ) wp_send_json_error( 'Nenhuma cotação aberta para este fornecedor' );
    $rc = tao_cot_api( "/cotacoes?id=eq.$cot_id&cliente_id=eq.$cid" );
    if ( ! $rc['ok'] || empty( $rc['data'] ) ) wp_send_json_error( 'Cotação não encontrada' );
    $cotacao = $rc['data'][0];
    tao_cot_ensure_participante( $cot_id, $fid );

    // origem do arquivo: upload direto OU midia_url (anexo do chat)
    $bin = null; $mime = ''; $origem = 'pdf';
    if ( ! empty( $_FILES['file']['tmp_name'] ) ) {
        $bin  = file_get_contents( $_FILES['file']['tmp_name'] );
        $mime = mime_content_type( $_FILES['file']['tmp_name'] ) ?: 'application/pdf';
    } else {
        $murl = esc_url_raw( $_POST['midia_url'] ?? '' );
        if ( ! $murl ) wp_send_json_error( 'Nenhum arquivo informado' );
        $up = wp_upload_dir();
        if ( strpos( $murl, $up['baseurl'] ) !== 0 ) wp_send_json_error( 'URL de mídia inválida' );
        $path = $up['basedir'] . substr( $murl, strlen( $up['baseurl'] ) );
        if ( ! file_exists( $path ) ) wp_send_json_error( 'Arquivo não encontrado no servidor' );
        $bin  = file_get_contents( $path );
        $mime = mime_content_type( $path ) ?: 'application/pdf';
    }
    if ( strlen( $bin ) > 15 * 1024 * 1024 ) wp_send_json_error( 'Arquivo acima de 15 MB' );
    if ( strpos( $mime, 'image/' ) === 0 ) $origem = 'imagem';
    elseif ( $mime !== 'application/pdf' )  wp_send_json_error( "Tipo não suportado p/ extração ($mime) — use PDF ou imagem, ou digite manualmente" );

    // cria a proposta
    $rp = tao_cot_api( '/cotacao_propostas', 'POST', [
        'cotacao_id' => $cot_id, 'fornecedor_id' => $fid, 'origem' => $origem,
        'arquivo_url' => $_POST['midia_url'] ?? null, 'status' => 'pendente',
    ] );
    if ( ! $rp['ok'] || empty( $rp['data'] ) ) wp_send_json_error( 'Falha ao registrar proposta' );
    $prop = $rp['data'][0];

    $ex = tao_cot_extrair_arquivo( $bin, $mime );
    if ( empty( $ex['ok'] ) ) {
        tao_cot_api( "/cotacao_propostas?id=eq.{$prop['id']}", 'PATCH', [ 'status' => 'erro', 'erro' => substr( $ex['error'] ?? '', 0, 400 ) ] );
        wp_send_json_error( 'Extração falhou: ' . ( $ex['error'] ?? '' ) );
    }
    $res = tao_cot_gravar_precos( $cid, $cotacao, $fid, $prop['id'], $ex['itens'] );

    tao_cot_api( "/cotacao_propostas?id=eq.{$prop['id']}", 'PATCH', [ 'status' => 'processada', 'processado_em' => gmdate( 'c' ) ] );
    tao_cot_api( "/cotacao_fornecedores?cotacao_id=eq.$cot_id&fornecedor_id=eq.$fid", 'PATCH', [ 'status' => 'processado' ] );

    wp_send_json_success( [ 'extraidos' => count( $ex['itens'] ), 'gravados' => $res['gravados'], 'divergencias' => $res['divergencias'], 'cotacao_id' => $cot_id ] );
} );

// ── AJAX: proposta digitada manualmente ───────────────────────────────────────

add_action( 'wp_ajax_tao_cot_proposta_manual', function() {
    $cid    = tao_cot_ajax_guard();
    $fid    = sanitize_text_field( $_POST['fornecedor_id'] ?? '' );
    $cot_id = sanitize_text_field( $_POST['cotacao_id'] ?? '' );
    $itens  = json_decode( wp_unslash( $_POST['itens'] ?? '[]' ), true );
    if ( ! $fid || ! $cot_id ) wp_send_json_error( 'Dados inválidos' );
    if ( empty( $itens ) || ! is_array( $itens ) ) wp_send_json_error( 'Informe ao menos 1 item' );

    $rc = tao_cot_api( "/cotacoes?id=eq.$cot_id&cliente_id=eq.$cid" );
    if ( ! $rc['ok'] || empty( $rc['data'] ) ) wp_send_json_error( 'Cotação não encontrada' );
    $cotacao = $rc['data'][0];
    tao_cot_ensure_participante( $cot_id, $fid );

    $rp = tao_cot_api( '/cotacao_propostas', 'POST', [
        'cotacao_id' => $cot_id, 'fornecedor_id' => $fid, 'origem' => 'manual',
        'status' => 'processada', 'processado_em' => gmdate( 'c' ),
    ] );
    $prop_id = $rp['ok'] && ! empty( $rp['data'] ) ? $rp['data'][0]['id'] : null;

    // itens manuais: [{item, preco, preco_unidade(g|ml|milheiro|kg|L), frac_min, validade, ativo_id?}]
    $grav = 0; $diverg = 0;
    $rit = tao_cot_api( "/cotacao_itens?cotacao_id=eq.$cot_id&select=id,ativo_id&limit=500" );
    $item_por_ativo = [];
    foreach ( ( $rit['ok'] ? $rit['data'] : [] ) as $ci ) if ( ! empty( $ci['ativo_id'] ) ) $item_por_ativo[ $ci['ativo_id'] ] = $ci['id'];

    foreach ( $itens as $it ) {
        $norm = tao_cot_normalizar_item( $it );
        if ( ! $norm ) continue;
        list( $vl, $unid, $qtde, $log ) = $norm;
        $ativo_id = ! empty( $it['ativo_id'] ) ? sanitize_text_field( $it['ativo_id'] ) : tao_cot_match_ativo( $cid, $it['item'] ?? '' );
        $r = tao_cot_api( '/cotacao_precos', 'POST', [
            'cotacao_id' => $cot_id, 'fornecedor_id' => $fid, 'proposta_id' => $prop_id,
            'cotacao_item_id' => $ativo_id ? ( $item_por_ativo[ $ativo_id ] ?? null ) : null,
            'ativo_id' => $ativo_id, 'item_original' => sanitize_text_field( $it['item'] ?? '' ),
            'vl_unit' => $vl, 'unid' => $unid, 'qtde_min' => $qtde ?: null,
            'vl_total' => $qtde > 0 ? round( $vl * $qtde * ( $unid === 'milheiro' ? 1 : 1 ), 2 ) : null,
            'validade' => sanitize_text_field( $it['validade'] ?? '' ) ?: null, 'conversao' => $log ?: 'manual',
        ] );
        if ( $r['ok'] ) {
            $grav++;
            if ( ! $ativo_id ) $diverg++;
            else tao_cot_api( '/precos_historico', 'POST', [ 'cliente_id' => $cid, 'ativo_id' => $ativo_id,
                'fornecedor_id' => $fid, 'cotacao_id' => $cot_id, 'preco' => $vl, 'unid' => $unid ] );
        }
    }
    tao_cot_api( "/cotacao_fornecedores?cotacao_id=eq.$cot_id&fornecedor_id=eq.$fid", 'PATCH', [ 'status' => 'processado' ] );
    wp_send_json_success( [ 'gravados' => $grav, 'divergencias' => $diverg ] );
} );

// ── AJAX: resolver divergência (vincula ativo e vira sinônimo) ────────────────

add_action( 'wp_ajax_tao_cot_divergencia_resolver', function() {
    $cid      = tao_cot_ajax_guard();
    $preco_id = sanitize_text_field( $_POST['preco_id'] ?? '' );
    $ativo_id = sanitize_text_field( $_POST['ativo_id'] ?? '' );
    if ( ! $preco_id || ! $ativo_id ) wp_send_json_error( 'Dados inválidos' );

    $rp = tao_cot_api( "/cotacao_precos?id=eq.$preco_id&select=*,cotacoes(cliente_id)" );
    if ( ! $rp['ok'] || empty( $rp['data'] ) ) wp_send_json_error( 'Registro não encontrado' );
    $p = $rp['data'][0];
    if ( ( $p['cotacoes']['cliente_id'] ?? '' ) !== $cid ) wp_send_json_error( 'Acesso negado' );

    // vincula ao item da cotação (se o ativo estiver na lista)
    $rit = tao_cot_api( "/cotacao_itens?cotacao_id=eq.{$p['cotacao_id']}&ativo_id=eq.$ativo_id&select=id&limit=1" );
    $cit = ( $rit['ok'] && ! empty( $rit['data'] ) ) ? $rit['data'][0]['id'] : null;

    tao_cot_api( "/cotacao_precos?id=eq.$preco_id", 'PATCH', [ 'ativo_id' => $ativo_id, 'cotacao_item_id' => $cit ] );

    // sinônimo acumulativo (termo como veio do fornecedor)
    $termo = trim( (string) $p['item_original'] );
    if ( $termo !== '' ) {
        $rs = tao_cot_api( "/ativos_sinonimos?cliente_id=eq.$cid&sinonimo=eq." . rawurlencode( $termo ) . "&select=id&limit=1" );
        if ( $rs['ok'] && empty( $rs['data'] ) ) {
            tao_cot_api( '/ativos_sinonimos', 'POST', [ 'cliente_id' => $cid, 'ativo_id' => $ativo_id, 'sinonimo' => $termo ] );
        }
        delete_transient( 'tao_cot_cat_' . $cid );
    }
    // histórico de preço agora que tem ativo
    if ( (float) $p['vl_unit'] > 0 ) {
        tao_cot_api( '/precos_historico', 'POST', [ 'cliente_id' => $cid, 'ativo_id' => $ativo_id,
            'fornecedor_id' => $p['fornecedor_id'], 'cotacao_id' => $p['cotacao_id'],
            'preco' => $p['vl_unit'], 'unid' => $p['unid'] ] );
    }
    wp_send_json_success();
} );

add_action( 'wp_ajax_tao_cot_preco_excluir', function() {
    $cid = tao_cot_ajax_guard();
    $id  = sanitize_text_field( $_POST['id'] ?? '' );
    if ( ! $id ) wp_send_json_error( 'ID inválido' );
    $rp = tao_cot_api( "/cotacao_precos?id=eq.$id&select=id,cotacoes(cliente_id)" );
    if ( ! $rp['ok'] || empty( $rp['data'] ) || ( $rp['data'][0]['cotacoes']['cliente_id'] ?? '' ) !== $cid ) {
        wp_send_json_error( 'Registro não encontrado' );
    }
    tao_cot_api( "/cotacao_precos?id=eq.$id", 'DELETE' );
    wp_send_json_success();
} );

// ── Export XLSX (layout do comparativo atual) ─────────────────────────────────

add_action( 'admin_post_tao_cot_export_xlsx', function() {
    if ( ! tao_cot_pode() ) wp_die( 'Sem permissão' );
    $cid    = tao_cot_cliente_id();
    $cot_id = sanitize_text_field( $_GET['cot'] ?? '' );
    check_admin_referer( 'tao_cot_export_' . $cot_id );
    $rc = tao_cot_api( "/cotacoes?id=eq.$cot_id&cliente_id=eq.$cid" );
    if ( ! $rc['ok'] || empty( $rc['data'] ) ) wp_die( 'Cotação não encontrada' );
    $cot  = $rc['data'][0];
    $comp = tao_cot_comparativo_dados( $cid, $cot_id );

    $col = function( $n ) {           // 0 -> A, 1 -> B...
        $s = '';
        while ( $n >= 0 ) { $s = chr( 65 + $n % 26 ) . $s; $n = intdiv( $n, 26 ) - 1; }
        return $s;
    };
    $esc = fn( $v ) => htmlspecialchars( (string) $v, ENT_XML1 | ENT_COMPAT, 'UTF-8' );
    $sheet_xml = function( $rows ) use ( $col, $esc ) {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        foreach ( $rows as $ri => $row ) {
            $xml .= '<row r="' . ( $ri + 1 ) . '">';
            foreach ( $row as $ci => $v ) {
                $ref = $col( $ci ) . ( $ri + 1 );
                if ( is_numeric( $v ) && $v !== '' && $v !== null ) $xml .= "<c r=\"$ref\"><v>" . $v . '</v></c>';
                else $xml .= "<c r=\"$ref\" t=\"inlineStr\"><is><t xml:space=\"preserve\">" . $esc( $v ) . '</t></is></c>';
            }
            $xml .= '</row>';
        }
        return $xml . '</sheetData></worksheet>';
    };

    // Aba 1: comparativo
    $fids = array_keys( $comp['fornecedores'] );
    $h1 = [ 'ATIVO', 'ULT. VALOR PAGO', 'MELHOR FORN.' ];
    $h2 = [ '', '', '' ];
    foreach ( $fids as $fid ) {
        $h1 = array_merge( $h1, [ $comp['fornecedores'][ $fid ], '', '', '' ] );
        $h2 = array_merge( $h2, [ 'VL UNIT (R$/g|ml|milh.)', 'QTDE MIN', 'VL TOTAL', 'VALIDADE' ] );
    }
    $rows = [ $h1, $h2 ];
    foreach ( $comp['linhas'] as $l ) {
        $r = [ $l['item']['descricao'], $l['item']['ult_preco_pago'] ?? '', $l['melhor_fid'] ? $comp['fornecedores'][ $l['melhor_fid'] ] : '' ];
        foreach ( $fids as $fid ) {
            $p = $l['cells'][ $fid ] ?? null;
            $r = array_merge( $r, $p ? [ $p['vl_unit'], $p['qtde_min'] ?? '', $p['vl_total'] ?? '', $p['validade'] ?? '' ] : [ '', '', '', '' ] );
        }
        $rows[] = $r;
    }
    // Aba 2/3
    $rows_div = [ [ 'Fornecedor', 'Item original', 'Vl unit', 'Unid' ] ];
    foreach ( $comp['divergencias'] as $d ) $rows_div[] = [ $comp['fornecedores'][ $d['fornecedor_id'] ] ?? '', $d['item_original'], $d['vl_unit'], $d['unid'] ];
    $rows_conv = [ [ 'Fornecedor', 'Item', 'Conversao', 'Vl final' ] ];
    $rp2 = tao_cot_api( "/cotacao_precos?cotacao_id=eq.$cot_id&conversao=not.is.null&select=item_original,conversao,vl_unit,fornecedor_id&limit=2000" );
    foreach ( ( $rp2['ok'] ? $rp2['data'] : [] ) as $d ) $rows_conv[] = [ $comp['fornecedores'][ $d['fornecedor_id'] ] ?? '', $d['item_original'], $d['conversao'], $d['vl_unit'] ];

    $tmp = wp_tempnam( 'cotacao-xlsx' );
    $z = new ZipArchive();
    $z->open( $tmp, ZipArchive::OVERWRITE );
    $z->addFromString( '[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/worksheets/sheet3.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>' );
    $z->addFromString( '_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>' );
    $z->addFromString( 'xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Comparativo" sheetId="1" r:id="rId1"/><sheet name="Divergencias" sheetId="2" r:id="rId2"/><sheet name="Conversoes" sheetId="3" r:id="rId3"/></sheets></workbook>' );
    $z->addFromString( 'xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet3.xml"/></Relationships>' );
    $z->addFromString( 'xl/worksheets/sheet1.xml', $sheet_xml( $rows ) );
    $z->addFromString( 'xl/worksheets/sheet2.xml', $sheet_xml( $rows_div ) );
    $z->addFromString( 'xl/worksheets/sheet3.xml', $sheet_xml( $rows_conv ) );
    $z->close();

    while ( ob_get_level() > 0 ) ob_end_clean();
    header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
    header( 'Content-Disposition: attachment; filename="comparativo_cotacao_' . ( $cot['numero'] ?? '' ) . '_' . gmdate( 'Y-m-d' ) . '.xlsx"' );
    header( 'Content-Length: ' . filesize( $tmp ) );
    readfile( $tmp );
    unlink( $tmp );
    exit;
} );
