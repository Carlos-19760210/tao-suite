<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Modelos de Proposta — "aprendizado de layout" (espelha laudo_modelos do tao-formula).
 * A IA propõe as regras 1x por fornecedor; depois a extração é DETERMINÍSTICA (regex), sem IA.
 * Lógica de parsing/conversão transcrita do consolidar.py (rotina manual validada).
 */

// ── Helpers de parse (portados do consolidar.py) ─────────────────────────────

// "R$ 1.234,56" / "150,00" / "12.5" → 150.0 (float) | null
function tao_cot_to_float( $s ) {
    if ( $s === null || $s === '' ) return null;
    $s = preg_replace( '/[R$\s]/u', '', (string) $s );
    if ( preg_match( '/\d\.\d{3},/', $s ) ) $s = str_replace( [ '.', ',' ], [ '', '.' ], $s ); // 1.234,56
    else                                    $s = str_replace( ',', '.', $s );
    if ( preg_match( '/(\d+\.?\d*)/', $s, $m ) ) return (float) $m[1];
    return null;
}

// data em várias formas → "MM/AAAA" (padrão do comparativo) | ''
function tao_cot_parse_validade( $s ) {
    $s = trim( (string) $s );
    if ( $s === '' ) return '';
    if ( preg_match( '#(\d{2})[/\-](\d{2})[/\-](\d{4})#', $s, $m ) ) return "{$m[2]}/{$m[3]}";
    if ( preg_match( '#(\d{2})[/\-](\d{4})#', $s, $m ) )            return "{$m[1]}/{$m[2]}";
    if ( preg_match( '#(\d{2})[/\-](\d{2})[/\-](\d{2})\b#', $s, $m ) ) {
        $yr = (int) $m[3]; $yr += ( $yr < 50 ) ? 2000 : 1900; return "{$m[2]}/{$yr}";
    }
    if ( preg_match( '/\b(20\d{2})\b/', $s, $m ) ) return "12/{$m[1]}";
    return '';
}

function tao_cot_is_capsula( $nome ) {
    return (bool) preg_match( '/\bcap\b|\bcaps\b|\bcapsula/i', tao_cot_norm_txt( $nome ) );
}

// Compila regex do modelo de forma segura (delimitador # escapado, unicode + case-insensitive).
function tao_cot_rx( $pat ) { return '#' . str_replace( '#', '\#', (string) $pat ) . '#ui'; }

// ── Extração DETERMINÍSTICA por MODELO (sem IA) ──────────────────────────────
// $paginas = array de strings (texto por página, como o pdf.js extrai).
// Retorna array de itens: { item, preco, preco_unidade, qtde_min, validade }.
function tao_cot_extrair_por_modelo( array $paginas, array $regras ) {
    $tipo = $regras['tipo'] ?? 'linha';
    $texto = implode( "\n", $paginas );
    $linhas = preg_split( '/\r\n|\r|\n/', $texto );
    // remove ruído de fontes sem mapeamento unicode (ex.: "(cid:1)" em PDFs tipo Sixty)
    $linhas = array_map( function( $l ) { return trim( preg_replace( '/\(cid:\d+\)/', '', (string) $l ) ); }, $linhas );
    $out = [];

    if ( $tipo === 'linha' ) {
        $rx     = tao_cot_rx( $regras['linha_regex'] ?? '' );
        $g      = $regras['grupos'] ?? [ 'item' => 1, 'preco' => 2 ];
        $unfixa = $regras['unidade_fixa'] ?? '';
        $capm   = ! empty( $regras['cap_milheiro'] );
        $pular  = array_map( 'mb_strtolower', $regras['pular'] ?? [] );
        if ( empty( $regras['linha_regex'] ) ) return [];
        foreach ( $linhas as $ln ) {
            $ln = trim( $ln );
            if ( $ln === '' ) continue;
            $low = mb_strtolower( $ln );
            foreach ( $pular as $p ) { if ( $p && mb_strpos( $low, $p ) !== false ) { continue 2; } }
            if ( ! @preg_match( $rx, $ln, $m ) ) continue;
            $nome  = isset( $g['item'] ) ? trim( $m[ $g['item'] ] ?? '' ) : '';
            $preco = isset( $g['preco'] ) ? tao_cot_to_float( $m[ $g['preco'] ] ?? '' ) : null;
            if ( $nome === '' || $preco === null || $preco == 0 ) continue;
            $un = $unfixa;
            if ( isset( $g['unidade'] ) && ! empty( $m[ $g['unidade'] ] ) ) $un = trim( $m[ $g['unidade'] ] );
            if ( $capm && tao_cot_is_capsula( $nome ) ) $un = 'milheiro';
            $out[] = [
                'item'          => $nome,
                'preco'         => $preco,
                'preco_unidade' => $un ?: 'g',
                'qtde_min'      => isset( $g['qtde_min'] ) ? trim( $m[ $g['qtde_min'] ] ?? '' ) : '',
                'frac_unidade'  => '',
                'validade'      => isset( $g['validade'] ) ? tao_cot_parse_validade( $m[ $g['validade'] ] ?? '' ) : '',
            ];
        }
        return $out;
    }

    // tipo 'colunas' — cabeçalho-âncora: acha a linha de cabeçalho e mapeia colunas por palavra-chave.
    // Cada linha de dados é fatiada por 2+ espaços; nome = tudo até a 1ª coluna numérica âncora.
    $col      = $regras['col'] ?? [];
    $header_kw = array_map( 'mb_strtolower', $regras['header_kw'] ?? [] );
    $capm     = ! empty( $regras['cap_milheiro'] );
    $hdr_idx  = -1; $headers = [];
    foreach ( $linhas as $i => $ln ) {
        $low = mb_strtolower( $ln );
        $hit = 0; foreach ( $header_kw as $kw ) { if ( $kw && mb_strpos( $low, $kw ) !== false ) $hit++; }
        if ( $hit >= max( 1, (int) ceil( count( $header_kw ) / 2 ) ) ) { $hdr_idx = $i; $headers = preg_split( '/\s{2,}|\t/', trim( $ln ) ); break; }
    }
    if ( $hdr_idx < 0 ) return [];
    $find = function( $keys ) use ( $headers ) {
        foreach ( (array) $keys as $k ) { $k = mb_strtolower( $k );
            foreach ( $headers as $ci => $h ) { if ( mb_strpos( mb_strtolower( trim( $h ) ), $k ) !== false ) return $ci; } }
        return -1;
    };
    $ci_nome  = $find( $col['nome']     ?? [ 'produto', 'descri' ] );
    $ci_pkg   = $find( $col['preco_kg'] ?? [] );
    $ci_preco = $find( $col['preco']    ?? [ 'preco', 'valor unit' ] );
    $ci_un    = $find( $col['unidade']  ?? [ 'unid', 'medida' ] );
    $ci_qtd   = $find( $col['qtde']     ?? [ 'qtde', 'quant' ] );
    $ci_val   = $find( $col['validade'] ?? [ 'validade', 'valid' ] );
    $use_kg   = $ci_pkg >= 0;
    $ci_p     = $use_kg ? $ci_pkg : $ci_preco;
    if ( $ci_nome < 0 || $ci_p < 0 ) return [];
    for ( $i = $hdr_idx + 1; $i < count( $linhas ); $i++ ) {
        $cells = preg_split( '/\s{2,}|\t/', trim( $linhas[ $i ] ) );
        if ( count( $cells ) < 2 ) continue;
        $nome  = trim( $cells[ $ci_nome ] ?? '' );
        $preco = tao_cot_to_float( $cells[ $ci_p ] ?? '' );
        if ( $nome === '' || $preco === null || $preco == 0 ) continue;
        $un = '';
        if ( $use_kg ) { $um = mb_strtolower( $cells[ $ci_un ] ?? '' ); $un = preg_match( '/\bmil/', $um ) ? 'milheiro' : 'kg'; }
        elseif ( $ci_un >= 0 ) $un = trim( $cells[ $ci_un ] ?? '' );
        if ( $capm && tao_cot_is_capsula( $nome ) ) $un = 'milheiro';
        $out[] = [
            'item'          => $nome,
            'preco'         => $preco,
            'preco_unidade' => $un ?: 'g',
            'qtde_min'      => $ci_qtd >= 0 ? trim( $cells[ $ci_qtd ] ?? '' ) : '',
            'frac_unidade'  => '',
            'validade'      => $ci_val >= 0 ? tao_cot_parse_validade( $cells[ $ci_val ] ?? '' ) : '',
        ];
    }
    return $out;
}

// Busca o modelo ativo do fornecedor (id ou CNPJ); desambigua por assinatura no texto.
function tao_cot_modelo_do_fornecedor( $cid, $fornecedor_id, $texto_amostra = '' ) {
    if ( ! $fornecedor_id ) return null;
    $r = tao_cot_api( "/cotacao_modelos?cliente_id=eq.$cid&ativo=eq.true&fornecedor_id=eq.$fornecedor_id&select=*&order=criado_em.desc" );
    $mods = ( $r['ok'] ? ( $r['data'] ?? [] ) : [] );
    if ( ! $mods ) return null;
    if ( count( $mods ) > 1 && $texto_amostra ) {
        foreach ( $mods as $md ) if ( ! empty( $md['assinatura'] ) && mb_stripos( $texto_amostra, $md['assinatura'] ) !== false ) return $md;
    }
    return $mods[0];
}

// Converte os itens extraídos pelo modelo p/ o formato que tao_cot_gravar_precos consome
// e grava (reusa toda a normalização/matching/histórico já existentes).
function tao_cot_gravar_por_modelo( $cid, $cotacao, $fornecedor_id, $proposta_id, array $itens ) {
    // tao_cot_gravar_precos espera itens no formato do prompt Gemini (item, preco, preco_unidade, ...)
    return tao_cot_gravar_precos( $cid, $cotacao, $fornecedor_id, $proposta_id, $itens );
}

// ── IA propõe o modelo (1x por fornecedor) — via Gemini (mesmo do módulo) ─────
function tao_cot_modelo_prompt() {
    return <<<'PROMPT'
Você monta um MOLDE de leitura para uma COTAÇÃO/TABELA DE PREÇOS de fornecedor de insumos farmacêuticos.
Recebe o TEXTO do PDF (páginas separadas por ----PAGINA----). Cada linha de produto tem: nome do insumo, preço, unidade, às vezes qtde/fracionamento e validade.
Responda APENAS JSON, sem markdown:
{"tipo":"linha","assinatura":"","linha_regex":"","grupos":{"item":1,"preco":2,"unidade":3,"qtde_min":4,"validade":5},"unidade_fixa":"","cap_milheiro":false,"pular":["produto","total","validade"]}
REGRAS:
- Prefira "tipo":"linha": construa UMA regex (PCRE) que casa UMA linha de produto e captura em GRUPOS: item, preco, e (se houver) unidade, qtde_min, validade. Ancore em pistas fixas (data dd/mm/aaaa, tokens de unidade KG/G/ML, país de origem) para o nome (que tem espaços) não "vazar".
- "grupos" mapeia o nome do campo para o número do grupo de captura da SUA regex (1-based). Inclua só os que a regex captura.
- "assinatura": um trecho CURTO e EXATO que só aparece NESTE layout (ex.: "Valor KG", cabeçalho característico) — serve p/ reconhecer o fornecedor.
- "unidade_fixa": se a unidade do preço não está na linha, informe kg|g|ml|milheiro; senão "".
- "cap_milheiro": true se linhas de cápsula devem ter preço por milheiro.
- "pular": palavras que, se aparecem na linha, indicam cabeçalho/rodapé/total a IGNORAR.
- NÃO invente valores; o molde só descreve COMO extrair. Use \\ para escapar na regex.
PROMPT;
}

function tao_cot_modelo_sugerir_ia( array $paginas ) {
    $amostra = mb_substr( implode( "\n----PAGINA----\n", array_slice( $paginas, 0, 4 ) ), 0, 14000 );
    $key = tao_cot_gemini_key();
    if ( ! $key ) return [ 'ok' => false, 'error' => 'Chave Gemini não configurada' ];
    $body = [
        'contents' => [ [ 'parts' => [ [ 'text' => tao_cot_modelo_prompt() . "\n\nTEXTO:\n" . $amostra ] ] ] ],
        'generationConfig' => [ 'response_mime_type' => 'application/json', 'temperature' => 0.1, 'maxOutputTokens' => 2200 ],
    ];
    $model = tao_cot_gemini_model();
    $resp = wp_remote_post(
        "https://generativelanguage.googleapis.com/v1beta/models/$model:generateContent?key=" . rawurlencode( $key ),
        [ 'timeout' => 120, 'headers' => [ 'Content-Type' => 'application/json' ], 'body' => wp_json_encode( $body ) ]
    );
    if ( is_wp_error( $resp ) ) return [ 'ok' => false, 'error' => $resp->get_error_message() ];
    $j = json_decode( wp_remote_retrieve_body( $resp ), true );
    $txt = $j['candidates'][0]['content']['parts'][0]['text'] ?? '';
    if ( preg_match( '/\{.*\}/s', $txt, $m ) ) $txt = $m[0];
    $sug = json_decode( $txt, true );
    if ( ! is_array( $sug ) ) return [ 'ok' => false, 'error' => 'A IA não retornou um molde válido' ];
    return [ 'ok' => true, 'regras' => $sug ];
}

// Extrai só as chaves de regra (sem assinatura) do JSON sugerido/editado.
function tao_cot_modelo_regras_norm( $sug ) {
    $tipo = ( ( $sug['tipo'] ?? 'linha' ) === 'colunas' ) ? 'colunas' : 'linha';
    $r = [ 'tipo' => $tipo, 'cap_milheiro' => ! empty( $sug['cap_milheiro'] ) ];
    if ( $tipo === 'linha' ) {
        $r['linha_regex']  = (string) ( $sug['linha_regex'] ?? '' );
        $r['grupos']       = is_array( $sug['grupos'] ?? null ) ? $sug['grupos'] : [ 'item' => 1, 'preco' => 2 ];
        $r['unidade_fixa'] = (string) ( $sug['unidade_fixa'] ?? '' );
        $r['pular']        = is_array( $sug['pular'] ?? null ) ? array_values( $sug['pular'] ) : [];
    } else {
        $r['header_kw'] = is_array( $sug['header_kw'] ?? null ) ? array_values( $sug['header_kw'] ) : [];
        $r['col']       = is_array( $sug['col'] ?? null ) ? $sug['col'] : [];
    }
    return $r;
}

// ── AJAX ──────────────────────────────────────────────────────────────────────

add_action( 'wp_ajax_tao_cot_modelo_sugerir', function() {
    $cid = tao_cot_ajax_guard();
    @set_time_limit( 120 );
    $paginas = json_decode( wp_unslash( $_POST['paginas'] ?? '[]' ), true );
    if ( ! is_array( $paginas ) || ! $paginas ) wp_send_json_error( 'Sem texto do PDF (digitalizado? use o caminho por IA na importação).' );
    $ia = tao_cot_modelo_sugerir_ia( $paginas );
    if ( empty( $ia['ok'] ) ) wp_send_json_error( $ia['error'] ?? 'Falha na IA' );
    $regras  = tao_cot_modelo_regras_norm( $ia['regras'] );
    $preview = tao_cot_extrair_por_modelo( $paginas, $regras );
    wp_send_json_success( [
        'regras'     => $regras,
        'assinatura' => $ia['regras']['assinatura'] ?? '',
        'preview'    => array_slice( $preview, 0, 40 ),
        'total'      => count( $preview ),
    ] );
} );

add_action( 'wp_ajax_tao_cot_modelo_testar', function() {
    tao_cot_ajax_guard();
    $paginas = json_decode( wp_unslash( $_POST['paginas'] ?? '[]' ), true );
    $regras  = json_decode( wp_unslash( $_POST['regras'] ?? '{}' ), true );
    if ( ! is_array( $paginas ) || ! is_array( $regras ) ) wp_send_json_error( 'dados' );
    $regras  = tao_cot_modelo_regras_norm( $regras );
    $preview = tao_cot_extrair_por_modelo( $paginas, $regras );
    wp_send_json_success( [ 'preview' => array_slice( $preview, 0, 40 ), 'total' => count( $preview ) ] );
} );

add_action( 'wp_ajax_tao_cot_modelo_salvar', function() {
    $cid    = tao_cot_ajax_guard();
    $fid    = sanitize_text_field( $_POST['fornecedor_id'] ?? '' );
    $nome   = trim( sanitize_text_field( $_POST['nome'] ?? '' ) ) ?: 'Modelo';
    $assin  = sanitize_text_field( $_POST['assinatura'] ?? '' );
    $mid    = sanitize_text_field( $_POST['id'] ?? '' );
    $regras = tao_cot_modelo_regras_norm( json_decode( wp_unslash( $_POST['regras'] ?? '{}' ), true ) ?: [] );
    if ( ! $fid ) wp_send_json_error( 'Selecione o fornecedor' );
    $body = [ 'nome' => $nome, 'ativo' => true, 'tipo' => $regras['tipo'], 'regras' => $regras,
        'assinatura' => $assin ?: null, 'atualizado_em' => gmdate( 'c' ) ];
    if ( $mid ) {
        $r = tao_cot_api( "/cotacao_modelos?id=eq.$mid&cliente_id=eq.$cid", 'PATCH', $body );
    } else {
        $body['cliente_id'] = $cid; $body['fornecedor_id'] = $fid; $body['origem'] = 'ia'; $body['criado_por'] = get_current_user_id();
        $r = tao_cot_api( '/cotacao_modelos', 'POST', $body );
    }
    ( $r['ok'] ) ? wp_send_json_success( $r['data'][0] ?? [] ) : wp_send_json_error( 'Erro ao salvar: ' . mb_substr( (string) ( $r['raw'] ?? '' ), 0, 160 ) );
} );

add_action( 'wp_ajax_tao_cot_modelos_lista', function() {
    $cid = tao_cot_ajax_guard();
    $r = tao_cot_api( "/cotacao_modelos?cliente_id=eq.$cid&select=id,nome,tipo,ativo,fornecedor_id,regras,origem,criado_em&order=criado_em.desc" );
    $mods = $r['ok'] ? ( $r['data'] ?? [] ) : [];
    $fids = array_values( array_unique( array_filter( array_column( $mods, 'fornecedor_id' ) ) ) );
    $fn = [];
    if ( $fids ) { $rf = tao_cot_api( "/fornecedores?id=in.(" . implode( ',', $fids ) . ")&select=id,nome" ); foreach ( ( $rf['ok'] ? $rf['data'] : [] ) as $f ) $fn[ $f['id'] ] = $f['nome']; }
    foreach ( $mods as &$m ) $m['fornecedor_nome'] = $fn[ $m['fornecedor_id'] ?? '' ] ?? '—';
    wp_send_json_success( $mods );
} );

add_action( 'wp_ajax_tao_cot_modelo_excluir', function() {
    $cid = tao_cot_ajax_guard();
    $mid = sanitize_text_field( $_POST['id'] ?? '' );
    if ( ! $mid ) wp_send_json_error( 'id' );
    tao_cot_api( "/cotacao_modelos?id=eq.$mid&cliente_id=eq.$cid", 'DELETE' );
    wp_send_json_success();
} );
