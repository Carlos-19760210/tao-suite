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
    $url   = "https://generativelanguage.googleapis.com/v1beta/models/$model:generateContent?key=" . rawurlencode( $key );
    // Retry em erros transitórios (connection reset/timeout de rede, 5xx, 429) — o Google
    // às vezes derruba a conexão (cURL 56). Backoff 0s → 2s → 5s.
    $resp = null; $code = 0; $errmsg = ''; $last_body = '';
    foreach ( [ 0, 2, 5 ] as $i => $espera ) {
        if ( $espera ) sleep( $espera );
        $resp = wp_remote_post( $url, [ 'timeout' => 120, 'headers' => [ 'Content-Type' => 'application/json' ], 'body' => wp_json_encode( $body ) ] );
        if ( is_wp_error( $resp ) ) { $errmsg = $resp->get_error_message(); continue; }   // rede caiu → tenta de novo
        $code = wp_remote_retrieve_response_code( $resp );
        $last_body = wp_remote_retrieve_body( $resp );
        if ( $code >= 500 || $code === 429 ) { $errmsg = 'Gemini HTTP ' . $code; continue; } // transitório → tenta de novo
        $errmsg = '';
        break;
    }
    if ( is_wp_error( $resp ) || $errmsg ) return [ 'ok' => false, 'error' => 'IA indisponível no momento (' . ( $errmsg ?: 'rede' ) . '). Tente novamente em instantes ou processe manualmente.' ];
    $out  = json_decode( $last_body, true );
    if ( $code >= 400 ) return [ 'ok' => false, 'error' => 'Gemini HTTP ' . $code . ': ' . substr( $last_body, 0, 200 ) ];

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
    // parse numérico tolerante à vírgula decimal BR ("0,100"→0.1, "1.200,00"→1200)
    $preco = (float) ( tao_cot_to_float( $it['preco'] ?? '' ) ?? 0 );
    if ( $preco <= 0 ) return null;
    $pu   = strtolower( trim( (string) ( $it['preco_unidade'] ?? '' ) ) );
    $pq   = (float) ( tao_cot_to_float( $it['pacote_qtde'] ?? '' ) ?? 0 );
    $fu   = strtolower( trim( (string) ( $it['frac_unidade'] ?? '' ) ) );
    $fmin = (float) ( tao_cot_to_float( $it['frac_min'] ?? '' ) ?? 0 );
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

// Salva a associação item→ativo como sinônimo (aprendizado): próximas importações casam sozinhas.
// Não sobrescreve associação existente (respeita o que já foi ensinado).
function tao_cot_salvar_sinonimo( $cid, $termo, $ativo_id ) {
    $termo = trim( (string) $termo );
    if ( $termo === '' || ! $ativo_id ) return;
    $rs = tao_cot_api( "/ativos_sinonimos?cliente_id=eq.$cid&sinonimo=eq." . rawurlencode( $termo ) . "&select=id&limit=1" );
    if ( ! empty( $rs['ok'] ) && ! empty( $rs['data'] ) ) return;   // já existe
    tao_cot_api( '/ativos_sinonimos', 'POST', [ 'cliente_id' => $cid, 'ativo_id' => $ativo_id, 'sinonimo' => $termo ] );
    delete_transient( 'tao_cot_cat_' . $cid );   // invalida o cache do catálogo p/ o match ver o novo sinônimo
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
    $rit   = tao_cot_api( "/cotacao_itens?cotacao_id=eq.$cotacao_id&order=prioridade.asc,descricao.asc&limit=500" );
    $itens = $rit['ok'] ? $rit['data'] : [];
    $rp    = tao_cot_api( "/cotacao_precos?cotacao_id=eq.$cotacao_id&select=*,fornecedores(nome,pedido_minimo)&order=criado_em.asc&limit=2000" );
    $precos = $rp['ok'] ? $rp['data'] : [];

    $fornecedores = [];                       // fid => nome (só quem tem preço)
    $pedido_min   = [];                       // fid => pedido mínimo (R$)
    $por_item     = [];                       // cotacao_item_id => [fid => melhor preço row]
    $extras       = [];                       // matched a ativo fora da lista
    $diverg       = [];
    foreach ( $precos as $p ) {
        $fid = $p['fornecedor_id'];
        $fornecedores[ $fid ] = $p['fornecedores']['nome'] ?? substr( $fid, 0, 8 );
        $pedido_min[ $fid ]   = (float) ( $p['fornecedores']['pedido_minimo'] ?? 0 );
        if ( ! $p['ativo_id'] ) { $diverg[] = $p; continue; }
        $key = $p['cotacao_item_id'] ?: null;
        if ( ! $key ) { $extras[] = $p; continue; }
        $cur = $por_item[ $key ][ $fid ] ?? null;
        if ( ! $cur || (float) $p['vl_unit'] < (float) $cur['vl_unit'] ) $por_item[ $key ][ $fid ] = $p;
    }
    asort( $fornecedores );

    // ── Frete por fornecedor (rateio proporcional ao VALOR) ──────────────────
    $frete = [];
    $rf = tao_cot_api( "/cotacao_fornecedores?cotacao_id=eq.$cotacao_id&select=fornecedor_id,frete&limit=500" );
    if ( $rf['ok'] ) foreach ( $rf['data'] as $x ) if ( (float) ( $x['frete'] ?? 0 ) > 0 ) $frete[ $x['fornecedor_id'] ] = (float) $x['frete'];
    // valor total por fornecedor = Σ (vl_unit × qtd do item) sobre os itens casados que ele cotou
    $qtd_item = [];
    foreach ( $itens as $it ) $qtd_item[ $it['id'] ] = (float) ( $it['qtd'] ?? 0 );
    $valor_forn = [];
    foreach ( $por_item as $iid => $porfid ) {
        $q = $qtd_item[ $iid ] ?? 0;
        foreach ( $porfid as $fid => $p ) {
            $base = $q > 0 ? $q : (float) ( $p['qtde_min'] ?? 0 );   // sem qtd do item, usa o fracionamento
            $valor_forn[ $fid ] = ( $valor_forn[ $fid ] ?? 0 ) + (float) $p['vl_unit'] * $base;
        }
    }
    // fator uniforme por fornecedor (rateio por valor ⇒ mesmo % em cada item)
    $fator = [];
    foreach ( $fornecedores as $fid => $nome ) {
        $fr = $frete[ $fid ] ?? 0; $tv = $valor_forn[ $fid ] ?? 0;
        $fator[ $fid ] = ( $fr > 0 && $tv > 0 ) ? ( 1 + $fr / $tv ) : 1.0;
    }

    $linhas = [];
    foreach ( $itens as $it ) {
        $cells  = $por_item[ $it['id'] ] ?? [];
        foreach ( $cells as $fid => $p ) $cells[ $fid ]['vl_com_frete'] = round( (float) $p['vl_unit'] * ( $fator[ $fid ] ?? 1 ), 6 );
        // melhor = menor preço COM frete (empate/sem frete cai no vl_unit puro pelo fator=1)
        $melhor = null;
        foreach ( $cells as $fid => $p ) {
            if ( ! $melhor || (float) $p['vl_com_frete'] < (float) $cells[ $melhor ]['vl_com_frete'] ) $melhor = $fid;
        }
        $linhas[] = [ 'item' => $it, 'cells' => $cells, 'melhor_fid' => $melhor ];
    }

    // ── Conferência do farmacêutico: todos os preços com o ativo atual + status ──
    $aids = [];
    foreach ( $precos as $p ) if ( ! empty( $p['ativo_id'] ) ) $aids[ $p['ativo_id'] ] = 1;
    $anomes = [];
    if ( $aids ) {
        $ra = tao_cot_api( '/ativos?id=in.(' . implode( ',', array_keys( $aids ) ) . ')&select=id,nome&limit=1000' );
        if ( $ra['ok'] ) foreach ( $ra['data'] as $a ) $anomes[ $a['id'] ] = $a['nome'];
    }
    $itmap = [];
    foreach ( $itens as $it ) $itmap[ $it['id'] ] = $it['descricao'];
    $conferencia = [];
    foreach ( $precos as $p ) {
        $aid = $p['ativo_id'] ?? null;
        if ( ! $aid )                               $status = 'nao_assoc';   // sem ativo (divergência)
        elseif ( empty( $p['cotacao_item_id'] ) )   $status = 'fora';        // ativo fora dos itens da cotação (possível erro)
        else                                        $status = 'ok';          // casado a um item da cotação
        $conferencia[] = [
            'id'             => $p['id'],
            'fornecedor'     => $fornecedores[ $p['fornecedor_id'] ] ?? '',
            'fornecedor_id'  => $p['fornecedor_id'],
            'item_original'  => $p['item_original'],
            'vl_unit'        => $p['vl_unit'],
            'unid'           => $p['unid'],
            'qtde_min'       => $p['qtde_min'] ?? null,
            'validade'       => $p['validade'] ?? null,
            'ativo_id'       => $aid,
            'ativo_nome'     => $aid ? ( $anomes[ $aid ] ?? '—' ) : null,
            'item_desc'      => ! empty( $p['cotacao_item_id'] ) ? ( $itmap[ $p['cotacao_item_id'] ] ?? '' ) : '',
            'status'         => $status,
        ];
    }
    // ordena: não associados e fora primeiro, depois ok
    usort( $conferencia, function( $a, $b ) {
        $ord = [ 'nao_assoc' => 0, 'fora' => 1, 'ok' => 2 ];
        return ( $ord[ $a['status'] ] <=> $ord[ $b['status'] ] ) ?: strcmp( $a['fornecedor'], $b['fornecedor'] );
    } );

    return [ 'linhas' => $linhas, 'fornecedores' => $fornecedores, 'divergencias' => $diverg, 'extras' => $extras, 'conferencia' => $conferencia, 'frete' => $frete, 'fator' => $fator, 'pedido_minimo' => $pedido_min ];
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

    // ── Caminho DETERMINÍSTICO (sem IA): se veio o texto do PDF e há modelo do fornecedor ──
    $paginas = json_decode( wp_unslash( $_POST['paginas'] ?? '[]' ), true );
    if ( is_array( $paginas ) && $paginas ) {
        $md = tao_cot_modelo_do_fornecedor( $cid, $fid, mb_substr( implode( "\n", array_slice( $paginas, 0, 4 ) ), 0, 4000 ) );
        if ( $md && ! empty( $md['regras'] ) ) {
            $itens = tao_cot_extrair_por_modelo( $paginas, $md['regras'] );
            if ( $itens ) {
                $rp = tao_cot_api( '/cotacao_propostas', 'POST', [
                    'cotacao_id' => $cot_id, 'fornecedor_id' => $fid, 'origem' => 'pdf-modelo',
                    'arquivo_url' => $_POST['midia_url'] ?? null, 'status' => 'pendente',
                ] );
                $prop = ( $rp['ok'] && ! empty( $rp['data'] ) ) ? $rp['data'][0] : null;
                if ( $prop ) {
                    $res = tao_cot_gravar_precos( $cid, $cotacao, $fid, $prop['id'], $itens );
                    tao_cot_api( "/cotacao_propostas?id=eq.{$prop['id']}", 'PATCH', [ 'status' => 'processada', 'processado_em' => gmdate( 'c' ) ] );
                    tao_cot_api( "/cotacao_fornecedores?cotacao_id=eq.$cot_id&fornecedor_id=eq.$fid", 'PATCH', [ 'status' => 'processado' ] );
                    wp_send_json_success( [ 'extraidos' => count( $itens ), 'gravados' => $res['gravados'], 'divergencias' => $res['divergencias'], 'cotacao_id' => $cot_id, 'via' => 'modelo', 'modelo' => $md['nome'] ?? '' ] );
                }
            }
        }
    }

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
        $explicit_ativo = ! empty( $it['ativo_id'] );
        $ativo_id = $explicit_ativo ? sanitize_text_field( $it['ativo_id'] ) : tao_cot_match_ativo( $cid, $it['item'] ?? '' );
        // Aprendizado: associação feita pelo atendente vira sinônimo → próximo import casa sozinho.
        if ( $explicit_ativo && $ativo_id ) tao_cot_salvar_sinonimo( $cid, $it['item'] ?? '', $ativo_id );
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

// ── AJAX: PRÉVIA (extrai por modelo OU IA, mostra o ativo casado, NÃO grava) ──
// O farmacêutico valida/ajusta e só então confirma (via tao_cot_proposta_manual).
add_action( 'wp_ajax_tao_cot_proposta_preview', function() {
    $cid = tao_cot_ajax_guard();
    @set_time_limit( 120 );
    $fid    = sanitize_text_field( $_POST['fornecedor_id'] ?? '' );
    $cot_id = sanitize_text_field( $_POST['cotacao_id'] ?? '' );
    if ( ! $fid || ! $cot_id ) wp_send_json_error( 'Dados inválidos' );

    // marca o retorno: fornecedor participante + cotação entra em "recebendo" (a importar)
    tao_cot_ensure_participante( $cot_id, $fid );
    tao_cot_api( "/cotacao_fornecedores?cotacao_id=eq.$cot_id&fornecedor_id=eq.$fid&status=in.(pendente,enviado)", 'PATCH', [ 'status' => 'respondeu', 'respondeu_em' => gmdate( 'c' ) ] );
    $rcs = tao_cot_api( "/cotacoes?id=eq.$cot_id&cliente_id=eq.$cid&select=status" );
    $st_cot = ( $rcs['ok'] && ! empty( $rcs['data'] ) ) ? ( $rcs['data'][0]['status'] ?? '' ) : '';
    if ( in_array( $st_cot, [ 'rascunho', 'enviada' ], true ) ) {
        tao_cot_api( "/cotacoes?id=eq.$cot_id", 'PATCH', [ 'status' => 'recebendo' ] );
    }

    $itens = []; $via = 'ia';
    // 1) tentar caminho determinístico (texto + modelo do fornecedor)
    $paginas = json_decode( wp_unslash( $_POST['paginas'] ?? '[]' ), true );
    if ( is_array( $paginas ) && $paginas ) {
        $md = tao_cot_modelo_do_fornecedor( $cid, $fid, mb_substr( implode( "\n", array_slice( $paginas, 0, 4 ) ), 0, 4000 ) );
        if ( $md && ! empty( $md['regras'] ) ) {
            $itens = tao_cot_extrair_por_modelo( $paginas, $md['regras'] );
            if ( $itens ) $via = 'modelo';
        }
    }
    // 2) fallback IA (precisa do arquivo)
    if ( ! $itens ) {
        if ( empty( $_FILES['file']['tmp_name'] ) ) wp_send_json_error( 'Sem modelo para este fornecedor — anexe o arquivo (PDF/foto) para a IA ler.' );
        $bin  = file_get_contents( $_FILES['file']['tmp_name'] );
        $mime = mime_content_type( $_FILES['file']['tmp_name'] ) ?: 'application/pdf';
        if ( strlen( $bin ) > 15 * 1024 * 1024 ) wp_send_json_error( 'Arquivo acima de 15 MB' );
        if ( strpos( $mime, 'image/' ) !== 0 && $mime !== 'application/pdf' ) wp_send_json_error( "Tipo não suportado ($mime)" );
        $ex = tao_cot_extrair_arquivo( $bin, $mime );
        if ( empty( $ex['ok'] ) ) wp_send_json_error( 'Extração falhou: ' . ( $ex['error'] ?? '' ) );
        $itens = $ex['itens'];
    }

    // normaliza + casa ativo (sem gravar) para o farmacêutico conferir
    $out = [];
    foreach ( $itens as $it ) {
        $nome = trim( (string) ( $it['item'] ?? '' ) );
        if ( $nome === '' ) continue;
        $norm = tao_cot_normalizar_item( $it );
        list( $vl, $unid ) = $norm ?: [ null, '' ];
        $ativo_id = tao_cot_match_ativo( $cid, $nome );
        $out[] = [
            'item'          => $nome,
            'preco'         => $it['preco'] ?? null,
            'preco_unidade' => $it['preco_unidade'] ?? '',
            'frac_min'      => $it['qtde_min'] ?? ( $it['frac_min'] ?? '' ),
            'validade'      => $it['validade'] ?? '',
            'ativo_id'      => $ativo_id,
            'vl_norm'       => $vl,
            'unid_norm'     => $unid,
        ];
    }
    // nomes dos ativos casados
    $aids = array_values( array_unique( array_filter( array_column( $out, 'ativo_id' ) ) ) );
    $anome = [];
    if ( $aids ) { $ra = tao_cot_api( "/ativos?id=in.(" . implode( ',', $aids ) . ")&select=id,nome" ); foreach ( ( $ra['ok'] ? $ra['data'] : [] ) as $a ) $anome[ $a['id'] ] = $a['nome']; }
    foreach ( $out as &$o ) $o['ativo_nome'] = $o['ativo_id'] ? ( $anome[ $o['ativo_id'] ] ?? '' ) : '';

    wp_send_json_success( [ 'via' => $via, 'itens' => $out, 'total' => count( $out ) ] );
} );

// Verifica se uma cotação pertence ao cliente (scoping p/ edições).
function tao_cot_cotacao_do_cliente( $cot_id, $cid ) {
    if ( ! $cot_id ) return false;
    $r = tao_cot_api( "/cotacoes?id=eq.$cot_id&cliente_id=eq.$cid&select=id&limit=1" );
    return $r['ok'] && ! empty( $r['data'] );
}

// ── AJAX: editar item da cotação (qtde / unidade) a qualquer momento ──────────
add_action( 'wp_ajax_tao_cot_item_editar', function() {
    $cid = tao_cot_ajax_guard();
    $id  = sanitize_text_field( $_POST['id'] ?? '' );
    if ( ! $id ) wp_send_json_error( 'id' );
    $r = tao_cot_api( "/cotacao_itens?id=eq.$id&select=cotacao_id&limit=1" );
    if ( ! $r['ok'] || empty( $r['data'] ) ) wp_send_json_error( 'Item não encontrado' );
    if ( ! tao_cot_cotacao_do_cliente( $r['data'][0]['cotacao_id'], $cid ) ) wp_send_json_error( 'Sem permissão', 403 );
    $patch = [];
    if ( isset( $_POST['qtd'] ) )       $patch['qtd']       = (float) str_replace( ',', '.', preg_replace( '/[^\d,.\-]/', '', (string) $_POST['qtd'] ) );
    if ( isset( $_POST['unidade'] ) )   $patch['unidade']   = strtolower( trim( sanitize_text_field( $_POST['unidade'] ) ) );
    if ( isset( $_POST['descricao'] ) ) $patch['descricao'] = trim( sanitize_text_field( $_POST['descricao'] ) );
    if ( isset( $_POST['codigo_fc'] ) ) $patch['codigo_fc'] = sanitize_text_field( $_POST['codigo_fc'] ) ?: null;
    if ( isset( $_POST['urgente'] ) )   $patch['urgente']   = ( $_POST['urgente'] === '1' || $_POST['urgente'] === 'true' );
    if ( isset( $_POST['ativo_id'] ) )  $patch['ativo_id']  = sanitize_text_field( $_POST['ativo_id'] ) ?: null;
    if ( isset( $_POST['prioridade'] ) ) {
        $pr = max( 0, min( 3, (int) $_POST['prioridade'] ) );
        $patch['prioridade'] = $pr;
        $patch['urgente']    = ( $pr === 0 );   // 0 = Urgente (⭐) mantém compatibilidade
    }
    if ( ! $patch ) wp_send_json_error( 'Nada a alterar' );
    $u = tao_cot_api( "/cotacao_itens?id=eq.$id", 'PATCH', $patch );
    $u['ok'] ? wp_send_json_success( $patch ) : wp_send_json_error( 'Falha ao salvar' );
} );

// ── AJAX: incluir item avulso na cotação ─────────────────────────────────────
add_action( 'wp_ajax_tao_cot_item_add', function() {
    $cid    = tao_cot_ajax_guard();
    $cot_id = sanitize_text_field( $_POST['cotacao_id'] ?? '' );
    $desc   = trim( sanitize_text_field( $_POST['descricao'] ?? '' ) );
    if ( ! $cot_id ) wp_send_json_error( 'cotação' );
    if ( $desc === '' ) wp_send_json_error( 'Informe a descrição do item' );
    if ( ! tao_cot_cotacao_do_cliente( $cot_id, $cid ) ) wp_send_json_error( 'Sem permissão', 403 );
    $pr_add = isset( $_POST['prioridade'] ) ? max( 0, min( 3, (int) $_POST['prioridade'] ) ) : ( ( ( $_POST['urgente'] ?? '' ) === '1' || ( $_POST['urgente'] ?? '' ) === 'true' ) ? 0 : 3 );
    $row = [
        'cotacao_id'     => $cot_id,
        'ativo_id'       => ! empty( $_POST['ativo_id'] ) ? sanitize_text_field( $_POST['ativo_id'] ) : null,
        'codigo_fc'      => sanitize_text_field( $_POST['codigo_fc'] ?? '' ) ?: null,
        'descricao'      => $desc,
        'unidade'        => strtolower( trim( sanitize_text_field( $_POST['unidade'] ?? '' ) ) ),
        'qtd'            => round( (float) str_replace( ',', '.', preg_replace( '/[^\d,.\-]/', '', (string) ( $_POST['qtd'] ?? 0 ) ) ), 2 ),
        'prioridade'     => $pr_add,
        'urgente'        => ( $pr_add === 0 ),
        'ult_preco_pago' => null,
        'origem'         => 'manual',
    ];
    $u = tao_cot_api( '/cotacao_itens', 'POST', [ $row ] );
    $u['ok'] ? wp_send_json_success( is_array( $u['data'] ) ? ( $u['data'][0] ?? [] ) : [] ) : wp_send_json_error( 'Falha ao incluir' );
} );

// ── AJAX: excluir item da cotação (remove também os preços vinculados) ────────
add_action( 'wp_ajax_tao_cot_item_excluir', function() {
    $cid = tao_cot_ajax_guard();
    $id  = sanitize_text_field( $_POST['id'] ?? '' );
    if ( ! $id ) wp_send_json_error( 'id' );
    $r = tao_cot_api( "/cotacao_itens?id=eq.$id&select=cotacao_id&limit=1" );
    if ( ! $r['ok'] || empty( $r['data'] ) ) wp_send_json_error( 'Item não encontrado' );
    if ( ! tao_cot_cotacao_do_cliente( $r['data'][0]['cotacao_id'], $cid ) ) wp_send_json_error( 'Sem permissão', 403 );
    // desvincula os preços deste item (não apaga o histórico do fornecedor)
    tao_cot_api( "/cotacao_precos?cotacao_item_id=eq.$id", 'PATCH', [ 'cotacao_item_id' => null ] );
    $u = tao_cot_api( "/cotacao_itens?id=eq.$id", 'DELETE' );
    $u['ok'] ? wp_send_json_success( true ) : wp_send_json_error( 'Falha ao excluir' );
} );

// ── AJAX: excluir VÁRIOS itens de uma vez ────────────────────────────────────
add_action( 'wp_ajax_tao_cot_itens_excluir', function() {
    $cid = tao_cot_ajax_guard();
    $ids = json_decode( wp_unslash( $_POST['ids'] ?? '[]' ), true );
    if ( ! is_array( $ids ) || ! $ids ) wp_send_json_error( 'Nenhum item selecionado' );
    $ids = array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $ids ) ) ) );
    if ( ! $ids ) wp_send_json_error( 'Nenhum item válido' );
    $in = implode( ',', $ids );
    // confirma que TODOS pertencem a cotações deste cliente
    $r = tao_cot_api( "/cotacao_itens?id=in.($in)&select=id,cotacao_id,cotacoes(cliente_id)&limit=1000" );
    if ( ! $r['ok'] ) wp_send_json_error( 'Falha ao validar itens' );
    $ok_ids = [];
    foreach ( $r['data'] as $row ) if ( ( $row['cotacoes']['cliente_id'] ?? '' ) === $cid ) $ok_ids[] = $row['id'];
    if ( ! $ok_ids ) wp_send_json_error( 'Sem permissão', 403 );
    $inok = implode( ',', $ok_ids );
    // desvincula os preços vinculados (mantém histórico) e exclui os itens
    tao_cot_api( "/cotacao_precos?cotacao_item_id=in.($inok)", 'PATCH', [ 'cotacao_item_id' => null ] );
    $u = tao_cot_api( "/cotacao_itens?id=in.($inok)", 'DELETE' );
    $u['ok'] ? wp_send_json_success( [ 'excluidos' => count( $ok_ids ) ] ) : wp_send_json_error( 'Falha ao excluir' );
} );

// ── AJAX: editar preço processado (vl_unit / unid / qtde_min / validade) ──────
add_action( 'wp_ajax_tao_cot_preco_editar', function() {
    $cid = tao_cot_ajax_guard();
    $id  = sanitize_text_field( $_POST['id'] ?? '' );
    if ( ! $id ) wp_send_json_error( 'id' );
    $r = tao_cot_api( "/cotacao_precos?id=eq.$id&select=cotacao_id,vl_unit,qtde_min&limit=1" );
    if ( ! $r['ok'] || empty( $r['data'] ) ) wp_send_json_error( 'Preço não encontrado' );
    $atual = $r['data'][0];
    if ( ! tao_cot_cotacao_do_cliente( $atual['cotacao_id'], $cid ) ) wp_send_json_error( 'Sem permissão', 403 );
    $patch = [];
    $numf = function( $v ) { return (float) str_replace( ',', '.', preg_replace( '/[^\d,.\-]/', '', (string) $v ) ); };
    if ( isset( $_POST['vl_unit'] ) )  $patch['vl_unit']  = $numf( $_POST['vl_unit'] );
    if ( isset( $_POST['unid'] ) )     $patch['unid']     = strtolower( trim( sanitize_text_field( $_POST['unid'] ) ) );
    if ( isset( $_POST['qtde_min'] ) ) $patch['qtde_min'] = $_POST['qtde_min'] === '' ? null : $numf( $_POST['qtde_min'] );
    if ( isset( $_POST['validade'] ) ) $patch['validade'] = sanitize_text_field( $_POST['validade'] ) ?: null;
    if ( ! $patch ) wp_send_json_error( 'Nada a alterar' );
    // recalcula vl_total = vl_unit × qtde_min quando ambos disponíveis
    $vl  = array_key_exists( 'vl_unit', $patch )  ? $patch['vl_unit']  : (float) $atual['vl_unit'];
    $qm  = array_key_exists( 'qtde_min', $patch ) ? $patch['qtde_min'] : ( $atual['qtde_min'] ?? null );
    $patch['vl_total'] = ( $vl > 0 && $qm > 0 ) ? round( $vl * $qm, 2 ) : null;
    $u = tao_cot_api( "/cotacao_precos?id=eq.$id", 'PATCH', $patch );
    $u['ok'] ? wp_send_json_success( $patch ) : wp_send_json_error( 'Falha ao salvar' );
} );

// ── AJAX: incluir nova LINHA de preço (retorno do fornecedor) manualmente ────
add_action( 'wp_ajax_tao_cot_preco_add', function() {
    $cid    = tao_cot_ajax_guard();
    $cot_id = sanitize_text_field( $_POST['cotacao_id'] ?? '' );
    $fid    = sanitize_text_field( $_POST['fornecedor_id'] ?? '' );
    if ( ! $cot_id || ! $fid ) wp_send_json_error( 'Selecione a cotação e o fornecedor' );
    if ( ! tao_cot_cotacao_do_cliente( $cot_id, $cid ) ) wp_send_json_error( 'Sem permissão', 403 );
    $it = [
        'item'          => sanitize_text_field( $_POST['item'] ?? '' ),
        'preco'         => str_replace( ',', '.', preg_replace( '/[^\d,.\-]/', '', (string) ( $_POST['preco'] ?? '' ) ) ),
        'preco_unidade' => strtolower( trim( sanitize_text_field( $_POST['preco_unidade'] ?? '' ) ) ),
        'frac_min'      => str_replace( ',', '.', preg_replace( '/[^\d,.\-]/', '', (string) ( $_POST['frac_min'] ?? '' ) ) ),
        'frac_unidade'  => strtolower( trim( sanitize_text_field( $_POST['frac_unidade'] ?? '' ) ) ),
        'validade'      => sanitize_text_field( $_POST['validade'] ?? '' ),
        'ativo_id'      => sanitize_text_field( $_POST['ativo_id'] ?? '' ),
    ];
    if ( $it['item'] === '' && empty( $it['ativo_id'] ) ) wp_send_json_error( 'Informe o item ou o ativo' );
    $norm = tao_cot_normalizar_item( $it );
    if ( ! $norm ) wp_send_json_error( 'Informe um preço válido' );
    list( $vl, $unid, $qtde, $log ) = $norm;

    tao_cot_ensure_participante( $cot_id, $fid );
    $rp = tao_cot_api( '/cotacao_propostas', 'POST', [
        'cotacao_id' => $cot_id, 'fornecedor_id' => $fid, 'origem' => 'manual',
        'status' => 'processada', 'processado_em' => gmdate( 'c' ),
    ] );
    $prop_id = $rp['ok'] && ! empty( $rp['data'] ) ? $rp['data'][0]['id'] : null;

    $explicit = ! empty( $it['ativo_id'] );
    $ativo_id = $explicit ? $it['ativo_id'] : tao_cot_match_ativo( $cid, $it['item'] );
    if ( $explicit && $ativo_id && $it['item'] !== '' ) tao_cot_salvar_sinonimo( $cid, $it['item'], $ativo_id );
    $cit = null; $anome = '';
    if ( $ativo_id ) {
        $ri  = tao_cot_api( "/cotacao_itens?cotacao_id=eq.$cot_id&ativo_id=eq.$ativo_id&select=id&limit=1" );
        $cit = ( $ri['ok'] && ! empty( $ri['data'] ) ) ? $ri['data'][0]['id'] : null;
        if ( $it['item'] === '' ) {
            $ra = tao_cot_api( "/ativos?id=eq.$ativo_id&select=nome&limit=1" );
            if ( $ra['ok'] && ! empty( $ra['data'] ) ) $anome = $ra['data'][0]['nome'];
        }
    }
    $r = tao_cot_api( '/cotacao_precos', 'POST', [
        'cotacao_id' => $cot_id, 'fornecedor_id' => $fid, 'proposta_id' => $prop_id, 'cotacao_item_id' => $cit,
        'ativo_id' => $ativo_id ?: null, 'item_original' => $it['item'] ?: $anome,
        'vl_unit' => $vl, 'unid' => $unid, 'qtde_min' => $qtde ?: null,
        'vl_total' => $qtde > 0 ? round( $vl * $qtde, 2 ) : null,
        'validade' => $it['validade'] ?: null, 'conversao' => $log ?: 'manual',
    ] );
    if ( ! $r['ok'] ) wp_send_json_error( 'Falha ao incluir a linha' );
    if ( $ativo_id && (float) $vl > 0 ) tao_cot_api( '/precos_historico', 'POST', [
        'cliente_id' => $cid, 'ativo_id' => $ativo_id, 'fornecedor_id' => $fid, 'cotacao_id' => $cot_id, 'preco' => $vl, 'unid' => $unid ] );
    wp_send_json_success( true );
} );

// ── AJAX: salvar FRETE da proposta de um fornecedor ──────────────────────────
add_action( 'wp_ajax_tao_cot_frete_salvar', function() {
    $cid    = tao_cot_ajax_guard();
    $cot_id = sanitize_text_field( $_POST['cotacao_id'] ?? '' );
    $fid    = sanitize_text_field( $_POST['fornecedor_id'] ?? '' );
    if ( ! $cot_id || ! $fid ) wp_send_json_error( 'Dados inválidos' );
    if ( ! tao_cot_cotacao_do_cliente( $cot_id, $cid ) ) wp_send_json_error( 'Sem permissão', 403 );
    $frete = (float) str_replace( ',', '.', preg_replace( '/[^\d,.\-]/', '', (string) ( $_POST['frete'] ?? '0' ) ) );
    if ( $frete < 0 ) $frete = 0;
    tao_cot_ensure_participante( $cot_id, $fid );
    $u = tao_cot_api( "/cotacao_fornecedores?cotacao_id=eq.$cot_id&fornecedor_id=eq.$fid", 'PATCH', [ 'frete' => $frete ] );
    $u['ok'] ? wp_send_json_success( [ 'frete' => $frete ] ) : wp_send_json_error( 'Falha ao salvar o frete' );
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

    // sinônimo acumulativo (termo como veio do fornecedor) — associação do farmacêutico é AUTORITATIVA
    $termo = trim( (string) $p['item_original'] );
    if ( $termo !== '' ) {
        $rs = tao_cot_api( "/ativos_sinonimos?cliente_id=eq.$cid&sinonimo=eq." . rawurlencode( $termo ) . "&select=id,ativo_id&limit=1" );
        if ( $rs['ok'] && empty( $rs['data'] ) ) {
            tao_cot_api( '/ativos_sinonimos', 'POST', [ 'cliente_id' => $cid, 'ativo_id' => $ativo_id, 'sinonimo' => $termo ] );
        } elseif ( $rs['ok'] && ! empty( $rs['data'] ) && ( $rs['data'][0]['ativo_id'] ?? '' ) !== $ativo_id ) {
            // corrige um sinônimo antes mapeado para o ativo errado
            tao_cot_api( "/ativos_sinonimos?id=eq.{$rs['data'][0]['id']}", 'PATCH', [ 'ativo_id' => $ativo_id ] );
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
