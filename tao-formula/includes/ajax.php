<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// ── Busca de Ativos (autocomplete) ───────────────────────────────────────────

/**
 * PREÇO COM FATOR (paridade FCerta) — atrás da chave tao_formula_preco_fator (default OFF).
 * Ligada, o preço de venda EFETIVO do ativo passa a ser custo_por_unidade × markup_preco
 * (o FATOR do FCerta, carga 1), como o FCerta precifica. Aplicado no ponto único de
 * leitura do ativo — editor, importação, receita IA e reprocessamento herdam juntos.
 */
function tao_formula_preco_fator_on() {
    static $on = null;
    if ( $on === null ) $on = get_option( 'tao_formula_preco_fator' ) === '1';
    return $on;
}
/** Transforma preco_venda em custo×fator (linha única de ativo ou lista). */
function tao_formula_aplica_fator( $rows ) {
    if ( ! tao_formula_preco_fator_on() || ! is_array( $rows ) || ! $rows ) return $rows;
    $uma  = isset( $rows['id'] );               // linha única?
    $list = $uma ? [ $rows ] : $rows;
    foreach ( $list as &$a ) {
        if ( ! is_array( $a ) ) continue;
        $mk = (float) ( $a['markup_preco'] ?? 0 );
        $cu = (float) ( $a['custo_por_unidade'] ?? 0 );
        if ( $mk > 1 && $cu > 0 ) $a['preco_venda'] = round( $cu * $mk, 6 );
    }
    unset( $a );
    return $uma ? $list[0] : $list;
}

add_action( 'wp_ajax_tao_formula_search_ativos', function() {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( 'Acesso negado', 403 );

    $q          = sanitize_text_field( $_GET['q'] ?? '' );
    $grupo      = sanitize_text_field( $_GET['grupo'] ?? '' );
    $cliente_id = tao_formula_cliente_id();
    if ( ! $cliente_id ) wp_send_json_error( 'Cliente não identificado', 400 );

    $term     = urlencode( $q );
    $motor_on = get_option( 'tao_formula_motor_v2' ) === '1';
    $sel_at   = 'id,codigo_fc,nome,unidade,unidade_padrao,preco_compra,custo_por_unidade,preco_venda,markup_preco,fator_correcao,fator_perda,densidade,diluicao,teor,grupo,concentracao,bloqueado'
              . ( $motor_on ? ',dose_max,uni_dose_max,dose_max_dia,dose_max_unidade,restricao' : '' );
    $base  = "/ativos?cliente_id=eq.$cliente_id&ativo=eq.true" .
             "&select=$sel_at" .
             "&order=nome.asc&limit=25";
    // Busca por nome OU por codigo_fc (permite digitar "10569" ou "cafeina")
    $qs = $base . "&or=(nome.ilike.*{$term}*,codigo_fc.ilike.*{$term}*)";
    if ( in_array( $grupo, [ 'M', 'E' ], true ) ) $qs .= "&grupo=eq.$grupo";

    $r = tao_formula_api( $qs );

    // Fallback: se grupo='E' não trouxe resultado, busca sem filtro de grupo
    if ( $r['ok'] && empty( $r['data'] ) && $grupo === 'E' ) {
        $qs_all = $base . "&or=(nome.ilike.*{$term}*,codigo_fc.ilike.*{$term}*)";
        $r = tao_formula_api( $qs_all );
    }
    if ( $r['ok'] ) $r['data'] = tao_formula_aplica_fator( $r['data'] ?? [] );

    // Motor v2: inclui matches por SINÔNIMO (equivalência sal↔base aplicada pelo nome prescrito)
    if ( $motor_on && $r['ok'] && $grupo === 'M' ) {
        $rs = tao_formula_api(
            "/ativos_sinonimos?cliente_id=eq.$cliente_id&sinonimo=ilike.*{$term}*" .
            "&ativo_id=not.is.null&select=sinonimo,fator_equiv,ativo_id&order=sinonimo.asc&limit=10"
        );
        if ( $rs['ok'] && ! empty( $rs['data'] ) ) {
            $ids = array_values( array_unique( array_column( $rs['data'], 'ativo_id' ) ) );
            $ra  = tao_formula_api(
                "/ativos?cliente_id=eq.$cliente_id&ativo=eq.true&id=in.(" . implode( ',', $ids ) . ")" .
                "&select=$sel_at&limit=" . count( $ids )
            );
            $por_id = [];
            foreach ( tao_formula_aplica_fator( $ra['ok'] ? $ra['data'] : [] ) as $at ) $por_id[ $at['id'] ] = $at;
            $ja_tem = array_column( $r['data'], 'id' );
            foreach ( $rs['data'] as $sin ) {
                $at = $por_id[ $sin['ativo_id'] ] ?? null;
                if ( ! $at ) continue;
                $equiv = (float) ( $sin['fator_equiv'] ?? 1 ) ?: 1;
                // Sem equivalência e o ativo já apareceu pelo nome → não duplica
                if ( $equiv == 1 && in_array( $at['id'], $ja_tem, true ) ) continue;
                $entry                = $at;
                $entry['fator_equiv'] = $equiv;
                $entry['_sinonimo']   = $sin['sinonimo'];
                $r['data'][]          = $entry;
                $ja_tem[]             = $at['id'];
            }
        }
    }

    if ( $r['ok'] ) {
        wp_send_json_success( $r['data'] );
    } else {
        wp_send_json_error( $r['raw'] );
    }
} );

// ── Detalhe de um Ativo ───────────────────────────────────────────────────────

add_action( 'wp_ajax_tao_formula_get_ativo', function() {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( 'Acesso negado', 403 );

    $id         = sanitize_text_field( $_GET['id'] ?? '' );
    $cliente_id = tao_formula_cliente_id();
    if ( ! $id || ! $cliente_id ) wp_send_json_error( 'Parâmetros inválidos', 400 );

    $r = tao_formula_api(
        "/ativos?id=eq.$id&cliente_id=eq.$cliente_id" .
        "&select=id,codigo_fc,nome,grupo,unidade,unidade_padrao,estoque_atual,preco_compra,preco_custo," .
        "custo_por_unidade,preco_venda,fator_correcao,fator_perda,densidade,dcb,dose_min,uni_dose_min," .
        "dose_max,uni_dose_max,categoria,classe_terapeutica,principio_ativo,observacoes,sincronizado_em," .
        "diluicao,teor,concentracao,markup_preco,restricao,ativo,controlado,classe_sngpc,registro_ms," .
        "ft_nome_quimico,ft_formula_molecular,ft_peso_molecular,ft_caracteres,ft_ponto_fusao,ft_solubilidade," .
        "ft_ph,ft_grau_pureza,ft_conservacao,ft_referencias,ft_revisao," .
        "excipiente_id,bloqueado,bloqueado_motivo" .
        "&limit=1"
    );

    if ( $r['ok'] && ! empty( $r['data'] ) ) {
        $ativo = $r['data'][0];
        // nome do excipiente associado (p/ exibir no cadastro)
        if ( ! empty( $ativo['excipiente_id'] ) ) {
            $re = tao_formula_api( "/ativos?id=eq.{$ativo['excipiente_id']}&select=nome&limit=1" );
            $ativo['excipiente_nome'] = ( $re['ok'] && ! empty( $re['data'] ) ) ? $re['data'][0]['nome'] : '';
        }
        wp_send_json_success( $ativo );
    } else {
        wp_send_json_error( $r['ok'] ? 'Não encontrado' : $r['raw'], 404 );
    }
} );

// ── Motor v2: lote FEFO do ativo (laudo real prevalece sobre o nominal) ──────

add_action( 'wp_ajax_tao_formula_lote_fefo', function() {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( 'Acesso negado', 403 );
    if ( get_option( 'tao_formula_motor_v2' ) !== '1' ) wp_send_json_success( null );

    $ativo_id   = sanitize_text_field( $_GET['ativo_id'] ?? '' );
    $cliente_id = tao_formula_cliente_id();
    if ( ! $ativo_id || ! $cliente_id ) wp_send_json_error( 'Parâmetros inválidos', 400 );

    $hoje = gmdate( 'Y-m-d' );
    $r = tao_formula_api(
        "/lab_lotes_mp?cliente_id=eq.$cliente_id&ativo_id=eq.$ativo_id" .
        "&status=not.in.(reprovado,vencido,esgotado)&qtd_atual=gt.0&dt_validade=gte.$hoje" .
        "&select=nr_lote,dt_validade,teor_pct,densidade,fator_diluicao,status" .
        "&order=dt_validade.asc&limit=1"
    );
    wp_send_json_success( ( $r['ok'] && ! empty( $r['data'] ) ) ? $r['data'][0] : null );
} );

// ── Criar / editar ativo (cadastro de produtos) ──────────────────────────────

add_action( 'wp_ajax_tao_formula_salvar_ativo', function() {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( [ 'message' => 'Acesso negado' ], 403 );
    $cliente_id = tao_formula_cliente_id();
    if ( ! $cliente_id ) wp_send_json_error( [ 'message' => 'Cliente não identificado' ] );

    $id   = sanitize_text_field( $_POST['id'] ?? '' );
    $nome = strtoupper( trim( sanitize_text_field( $_POST['nome'] ?? '' ) ) );
    if ( ! $nome ) wp_send_json_error( [ 'message' => 'Informe o nome do produto' ] );

    // Número BR: vírgula decimal → ponto; vazio → null
    $num = function( $k ) {
        $v = trim( (string) ( $_POST[ $k ] ?? '' ) );
        return $v === '' ? null : (float) str_replace( ',', '.', $v );
    };
    $txt = function( $k, $upper = false ) {
        $v = trim( sanitize_text_field( $_POST[ $k ] ?? '' ) );
        if ( $upper ) $v = strtoupper( $v );
        return $v === '' ? null : $v;
    };

    $grupo = strtoupper( sanitize_text_field( $_POST['grupo'] ?? 'M' ) );
    if ( ! in_array( $grupo, [ 'M', 'E' ], true ) ) $grupo = 'M';

    $payload = [
        'nome'              => $nome,
        'grupo'             => $grupo,
        'codigo_fc'         => $txt( 'codigo_fc' ),
        'controlado'        => ( $_POST['controlado'] ?? '' ) === '1',
        'classe_sngpc'      => $txt( 'classe_sngpc', true ),
        'registro_ms'       => $txt( 'registro_ms' ),
        'unidade'           => $txt( 'unidade', true ),
        'unidade_padrao'    => $txt( 'unidade_padrao' ),
        'preco_compra'      => $num( 'preco_compra' ),
        'custo_por_unidade' => $num( 'custo_por_unidade' ),
        'preco_venda'       => $num( 'preco_venda' ),
        'markup_preco'      => $num( 'markup_preco' ),
        'dcb'               => $txt( 'dcb', true ),
        'fator_correcao'    => $num( 'fator_correcao' ),
        'fator_perda'       => $num( 'fator_perda' ),
        'densidade'         => $num( 'densidade' ),
        'diluicao'          => $num( 'diluicao' ),
        'teor'              => $num( 'teor' ),
        'concentracao'      => $num( 'concentracao' ),
        'dose_max'          => $num( 'dose_max' ),
        'uni_dose_max'      => $txt( 'uni_dose_max' ),
        'restricao'         => $txt( 'restricao' ),
        'categoria'         => $txt( 'categoria' ),
        'observacoes'       => $txt( 'observacoes' ),
        // Ficha técnica (RDC 67) — especificação da MP
        'ft_nome_quimico'      => $txt( 'ft_nome_quimico' ),
        'ft_formula_molecular' => $txt( 'ft_formula_molecular' ),
        'ft_peso_molecular'    => $txt( 'ft_peso_molecular' ),
        'ft_caracteres'        => $txt( 'ft_caracteres' ),
        'ft_ponto_fusao'       => $txt( 'ft_ponto_fusao' ),
        'ft_solubilidade'      => $txt( 'ft_solubilidade' ),
        'ft_ph'                => $txt( 'ft_ph' ),
        'ft_grau_pureza'       => $txt( 'ft_grau_pureza' ),
        'ft_conservacao'       => $txt( 'ft_conservacao' ),
        'ft_referencias'       => $txt( 'ft_referencias' ),
        'ft_revisao'           => $txt( 'ft_revisao' ),
        'excipiente_id'        => $txt( 'excipiente_id' ),
        'bloqueado'            => ( $_POST['bloqueado'] ?? '' ) === '1',
        'bloqueado_motivo'     => $txt( 'bloqueado_motivo' ),
    ];

    // Código FC não pode colidir com outro produto do mesmo tenant
    if ( $payload['codigo_fc'] ) {
        $chk = tao_formula_api(
            '/ativos?cliente_id=eq.' . $cliente_id .
            '&codigo_fc=eq.' . rawurlencode( $payload['codigo_fc'] ) .
            ( $id ? '&id=neq.' . $id : '' ) . '&select=id,nome&limit=1'
        );
        if ( $chk['ok'] && ! empty( $chk['data'] ) ) {
            wp_send_json_error( [ 'message' => 'Código ' . $payload['codigo_fc'] . ' já usado por: ' . $chk['data'][0]['nome'] ] );
        }
    }

    $gravar = function( $body ) use ( $id, $cliente_id ) {
        if ( $id ) return tao_formula_api( "/ativos?id=eq.$id&cliente_id=eq.$cliente_id", 'PATCH', $body );
        $body['cliente_id'] = $cliente_id; $body['ativo'] = true;
        return tao_formula_api( '/ativos', 'POST', $body );
    };
    $r = $gravar( $payload );
    // migração pendente → grava sem os campos das colunas ainda inexistentes
    if ( ! $r['ok'] && strpos( (string) ( $r['raw'] ?? '' ), 'column' ) !== false ) {
        unset( $payload['controlado'], $payload['classe_sngpc'], $payload['registro_ms'] );
        foreach ( array_keys( $payload ) as $k ) if ( strpos( $k, 'ft_' ) === 0 ) unset( $payload[ $k ] );
        unset( $payload['excipiente_id'], $payload['bloqueado'], $payload['bloqueado_motivo'] );
        $r = $gravar( $payload );
    }
    if ( ! $r['ok'] ) wp_send_json_error( [ 'message' => 'Erro ao salvar: ' . mb_substr( (string) $r['raw'], 0, 300 ) ] );
    $ativo_id = $r['data'][0]['id'] ?? $id;
    // histórico de preços (se algum preço foi informado)
    if ( $ativo_id && ( $payload['preco_compra'] !== null || $payload['custo_por_unidade'] !== null || $payload['preco_venda'] !== null ) ) {
        tao_formula_registrar_preco_hist( $cliente_id, $ativo_id, [
            'preco_compra'  => $payload['preco_compra'],
            'custo_unidade' => $payload['custo_por_unidade'],
            'preco_venda'   => $payload['preco_venda'],
        ], 'manual', [] );
    }
    wp_send_json_success( [ 'id' => $ativo_id, 'novo' => ! $id ] );
} );

// Helper: registra um ponto no histórico de preços do ativo (silencioso se a tabela não existir)
function tao_formula_registrar_preco_hist( $cliente_id, $ativo_id, $precos, $origem, $extra = [] ) {
    tao_formula_api( '/ativo_precos_hist', 'POST', [
        'cliente_id'    => $cliente_id,
        'ativo_id'      => $ativo_id,
        'dt'            => gmdate( 'Y-m-d' ),
        'preco_compra'  => $precos['preco_compra']  ?? null,
        'custo_unidade' => $precos['custo_unidade'] ?? null,
        'preco_venda'   => $precos['preco_venda']   ?? null,
        'origem'        => $origem,
        'fornecedor_id' => $extra['fornecedor_id'] ?? null,
        'nf_numero'     => $extra['nf_numero'] ?? null,
        'usuario_id'    => get_current_user_id(),
    ] );
}

// Leitura do histórico de preços de um ativo (linha do tempo)
add_action( 'wp_ajax_tao_formula_ativo_precos_hist', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( 'Acesso negado', 403 );
    $cliente_id = tao_formula_cliente_id();
    $ativo_id = sanitize_text_field( $_GET['ativo_id'] ?? '' );
    if ( ! $cliente_id || ! $ativo_id ) wp_send_json_error( [ 'message' => 'Parâmetros inválidos' ] );
    $r = tao_formula_api( "/ativo_precos_hist?cliente_id=eq.$cliente_id&ativo_id=eq.$ativo_id&select=dt,preco_compra,custo_unidade,preco_venda,origem,nf_numero&order=dt.desc,criado_em.desc&limit=60" );
    wp_send_json_success( $r['ok'] ? ( $r['data'] ?? [] ) : [] );
} );

// Hub do produto — aba Estoque & Consumo: lotes, saldo, última compra, consumo mensal (12m).
add_action( 'wp_ajax_tao_formula_ativo_estoque', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( 'Acesso negado', 403 );
    $cliente_id = tao_formula_cliente_id();
    $ativo_id = sanitize_text_field( $_GET['ativo_id'] ?? '' );
    if ( ! $cliente_id || ! $ativo_id ) wp_send_json_error( [ 'message' => 'Parâmetros inválidos' ] );

    // 1) lotes do ativo
    $rl = tao_formula_api( "/lab_lotes_mp?cliente_id=eq.$cliente_id&ativo_id=eq.$ativo_id" .
        "&select=id,nr_lote,dt_validade,qtd_atual,unidade,status,origem,nf_numero,fabricante,teor_pct&order=dt_validade.asc&limit=300" );
    $lotes = $rl['ok'] ? ( $rl['data'] ?? [] ) : [];
    $saldo = 0.0;
    foreach ( $lotes as $l ) {
        if ( ! in_array( $l['status'], [ 'reprovado', 'vencido', 'esgotado' ], true ) ) $saldo += (float) $l['qtd_atual'];
    }

    // 2) consumo mensal (saídas dos últimos 12 meses, agrupado por AAAA-MM)
    $desde = gmdate( 'Y-m-d', strtotime( '-12 months' ) );
    $rm = tao_formula_api( "/estoque_movimentos?cliente_id=eq.$cliente_id&ativo_id=eq.$ativo_id&tipo=eq.saida&criado_em=gte.$desde&select=quantidade,criado_em&limit=5000" );
    $meses = [];
    foreach ( ( $rm['ok'] ? $rm['data'] : [] ) as $m ) {
        $ym = substr( (string) $m['criado_em'], 0, 7 );
        if ( ! $ym ) continue;
        $meses[ $ym ] = ( $meses[ $ym ] ?? 0 ) + abs( (float) $m['quantidade'] );
    }
    // grade completa dos últimos 12 meses (do atual para trás), preenchendo zeros
    $consumo = []; $soma = 0.0; $ncom = 0;
    for ( $i = 0; $i < 12; $i++ ) {
        $ym = gmdate( 'Y-m', strtotime( "-$i months" ) );
        $q  = round( (float) ( $meses[ $ym ] ?? 0 ), 4 );
        $consumo[] = [ 'mes' => $ym, 'qtd' => $q ];
        $soma += $q; if ( $q > 0 ) $ncom++;
    }
    $media = $ncom ? round( $soma / $ncom, 4 ) : 0;

    // 3) última compra
    $rc = tao_formula_api( "/ativo_precos_hist?cliente_id=eq.$cliente_id&ativo_id=eq.$ativo_id&origem=eq.nf&select=dt,preco_compra,custo_unidade,nf_numero,fornecedor_id&order=dt.desc,criado_em.desc&limit=1" );
    $ult = ( $rc['ok'] && ! empty( $rc['data'] ) ) ? $rc['data'][0] : null;
    if ( $ult && ! empty( $ult['fornecedor_id'] ) ) {
        $rf = tao_formula_api( "/fornecedores?id=eq.{$ult['fornecedor_id']}&select=nome&limit=1" );
        $ult['fornecedor'] = ( $rf['ok'] && ! empty( $rf['data'] ) ) ? $rf['data'][0]['nome'] : null;
    }

    wp_send_json_success( [
        'saldo' => round( $saldo, 4 ), 'lotes' => $lotes,
        'consumo_mensal' => $consumo, 'media_mensal' => $media, 'ultima_compra' => $ult,
    ] );
} );

// ── Helper: gerar número de orçamento/OM no padrão TAO Neo YYYYMM-NNNNN-SS ────
// YYYYMM  = ano+mês; NNNNN = nº da REQUISIÇÃO (5 dígitos, um por card/atendimento);
// SS      = sequência da fórmula dentro da requisição (2 dígitos). Ex: 202607-00001-01.
// A UI (orcamentos.php) mostra o segmento do meio como "Nº Requisição". A OM herda
// exatamente este número do orçamento aprovado.
// Só considera números do NOVO formato do mês (like "YYYYMM-*"); os históricos do
// FCerta/formato antigo ("2026070009") ficam como estão e não interferem na contagem.

function tao_formula_gerar_numero( $cliente_id, $card_id = null ) {
    $prefix = date( 'Ym' ); // mês atual, só p/ compor uma requisição NOVA
    // A requisição (NNNNN) é ÚNICA e CONTÍNUA — NÃO reinicia no mês (é a chave de busca da
    // farmácia). Própria do TAO, começando do 00001 (não ancorada no FCerta, p/ diferenciar).
    // Considera todo o novo formato YYYYMM-NNNNN-SS (2 hífens), os mais recentes primeiro.
    $r      = tao_formula_api(
        "/orcamentos?cliente_id=eq.$cliente_id" .
        "&numero_orcamento=like.*-*-*" .
        "&select=numero_orcamento,card_id&order=numero_orcamento.desc&limit=5000"
    );
    $todos = ( $r['ok'] && is_array( $r['data'] ) ) ? $r['data'] : [];

    $max_req  = 0;      // maior nº de requisição já emitido (contínuo, todos os meses)
    $req_base = null;   // "YYYYMM-NNNNN" da requisição já aberta p/ este card

    foreach ( $todos as $row ) {
        $parts = explode( '-', (string) ( $row['numero_orcamento'] ?? '' ) );
        if ( count( $parts ) !== 3 ) continue;                                   // só o formato YYYYMM-NNNNN-SS
        if ( strlen( $parts[0] ) !== 6 || ! ctype_digit( $parts[0] ) || ! ctype_digit( $parts[1] ) ) continue;
        $req = (int) $parts[1];
        if ( $req > $max_req ) $max_req = $req;
        if ( $card_id && ( $row['card_id'] ?? '' ) === $card_id ) $req_base = $parts[0] . '-' . $parts[1];
    }

    // fórmula adicional do MESMO card → mantém a requisição original, avança a sequência SS
    if ( $card_id && $req_base !== null ) {
        $max_suf = 0;
        foreach ( $todos as $row ) {
            $parts = explode( '-', (string) ( $row['numero_orcamento'] ?? '' ) );
            if ( count( $parts ) === 3 && ( $parts[0] . '-' . $parts[1] ) === $req_base ) {
                $s = (int) $parts[2];
                if ( $s > $max_suf ) $max_suf = $s;
            }
        }
        return $req_base . '-' . str_pad( $max_suf + 1, 2, '0', STR_PAD_LEFT );
    }

    // nova requisição → NNNNN contínuo (5 dígitos, cresce além se passar de 99999) com a 1ª fórmula
    return $prefix . '-' . str_pad( $max_req + 1, 5, '0', STR_PAD_LEFT ) . '-01';
}

// ── Helper: linha de descrição da fórmula para WhatsApp ──────────────────────

function tao_formula_build_descricao( $forma_nome, $forma_vol, $forma_unidade, $itens, $qtde_potes = 1 ) {
    $vol_str = $forma_vol ? strtoupper( $forma_vol . $forma_unidade ) : '';
    $header  = 'FORMULA MANIPULADA' . ( $forma_nome ? ' - ' . strtoupper( $forma_nome ) : '' );
    if ( $vol_str ) $header .= ': ' . $vol_str;

    $parts = [];
    foreach ( (array) $itens as $item ) {
        if ( ( $item['tipo'] ?? 'mp' ) !== 'mp' ) continue;
        // Orçamento e RÓTULO mostram a DESCRIÇÃO DA PRESCRIÇÃO (o sinônimo prescrito) — regra do Carlos.
        // Fallback ao nome do produto só quando a prescrição está vazia ou é apenas um código (ex.: excipiente "10577").
        $presc = trim( (string) ( $item['nome_prescricao'] ?? '' ) );
        $usa_presc = ( $presc !== '' && ! ctype_digit( $presc ) );
        $nome = strtoupper( $usa_presc ? $presc : ( $item['nome'] ?? $presc ) );
        if ( ! $nome ) continue;
        if ( ! empty( $item['is_qsp'] ) ) {
            $parts[] = $nome . '@';
        } else {
            $dose      = $item['dose']      ?? '';
            $dose_unit = $item['dose_unit'] ?? '';
            $parts[]   = $nome . ( $dose !== '' && $dose !== null ? ' ' . $dose . ' ' . $dose_unit : '' );
        }
    }
    return $header . ( $parts ? ' | ' . implode( '; ', $parts ) : '' );
}

// ── Dados comuns para salvar / atualizar orçamento ────────────────────────────

function tao_formula_orc_payload( $itens ) {
    $forma_id = sanitize_text_field( $_POST['forma_id'] ?? '' );
    $p = [
        'nome_paciente'       => sanitize_text_field( $_POST['nome_paciente'] ?? '' ),
        'whatsapp'            => sanitize_text_field( $_POST['whatsapp'] ?? '' ),
        'forma_id'            => $forma_id ?: null,
        'forma_nome'          => sanitize_text_field( $_POST['forma_nome'] ?? '' ),
        'forma_vol'           => $_POST['forma_vol'] !== '' ? (float) ( $_POST['forma_vol'] ?? 0 ) : null,
        'forma_unidade'       => sanitize_text_field( $_POST['forma_unidade'] ?? 'g' ),
        'qtde_potes'          => max( 1, (int) ( $_POST['qtde_potes'] ?? 1 ) ),
        'custo_fixo_aplicado' => (float) ( $_POST['custo_fixo']      ?? 0 ),
        'total_insumos'       => (float) ( $_POST['total_insumos']   ?? 0 ),
        'acrescimo_aplicado'  => (float) ( $_POST['acrescimo']       ?? 0 ),  // Acréscimo(R$) — fonte da verdade
        'margem_aplicada'     => (float) ( $_POST['margem_pct']      ?? 0 ),  // % derivado (exibição)
        'desconto_pct'        => (float) ( $_POST['desconto_pct']    ?? 0 ),
        'total_orcamento'     => (float) ( $_POST['total_orcamento'] ?? 0 ),
        'observacoes'         => sanitize_textarea_field( $_POST['observacoes'] ?? '' ),
        'itens'               => $itens,
        'atualizado_em'       => gmdate( 'c' ),
    ];
    // Desconto em R$ = fonte da verdade (reaproveita a coluna desconto_fc).
    if ( isset( $_POST['desconto_val'] ) && $_POST['desconto_val'] !== '' )
        $p['desconto_fc'] = (float) $_POST['desconto_val'];
    // Alçada (etapa 1): desconto acima do limite do perfil → bloqueia com orientação.
    // Compara o MAIOR entre o % informado e o % derivado do desconto em R$.
    if ( function_exists( 'tao_crm_alcada' ) ) {
        $lim = tao_crm_alcada( 'orcamento.desconto_pct' );
        if ( $lim !== null ) {
            $pct  = (float) $p['desconto_pct'];
            $desc = (float) ( $p['desconto_fc'] ?? 0 );
            $tot  = (float) $p['total_orcamento'];
            if ( $desc > 0 && ( $tot + $desc ) > 0 ) $pct = max( $pct, $desc / ( $tot + $desc ) * 100 );
            if ( $pct > $lim + 0.005 ) {
                wp_send_json_error( sprintf( 'Desconto de %s%% acima da sua alçada (máximo %s%%). Solicite a um gestor.',
                    number_format( $pct, 1, ',', '.' ), number_format( $lim, 1, ',', '.' ) ) );
            }
        }
    }
    // Consistência do card: orçamento FC guarda o Final também em valor_final_fc,
    // pra o Kanban (tao_crm_sync_valor_oportunidade lê valor_final_fc) refletir a edição.
    if ( isset( $_POST['valor_final_fc'] ) && $_POST['valor_final_fc'] !== '' )
        $p['valor_final_fc'] = (float) $_POST['valor_final_fc'];
    // Campos opcionais (migration_v2): cliente (contratante) ≠ paciente; prescritor livre; posologia;
    // forma_tipo (tipo cápsula) — o editor sempre enviou, faltava a coluna p/ persistir
    $p['nome_cliente']  = sanitize_text_field( $_POST['nome_cliente'] ?? '' ) ?: null;
    $p['prescritor']    = sanitize_text_field( $_POST['prescritor']   ?? '' ) ?: null;
    $p['prescritor_id'] = sanitize_text_field( $_POST['prescritor_id'] ?? '' ) ?: null;
    $p['posologia']     = sanitize_textarea_field( $_POST['posologia'] ?? '' ) ?: null;
    $p['forma_tipo']    = sanitize_text_field( $_POST['forma_tipo']   ?? '' ) ?: null;
    $p['cid_codigo']    = sanitize_text_field( $_POST['cid_codigo']    ?? '' ) ?: null;
    $p['cid_descricao'] = sanitize_text_field( $_POST['cid_descricao'] ?? '' ) ?: null;
    // Ficha de manipulação: data da prescrição + previsão de retirada (migration_ficha_om_v1)
    $p['dt_prescricao']     = sanitize_text_field( $_POST['dt_prescricao']     ?? '' ) ?: null;
    $p['previsao_retirada'] = sanitize_text_field( $_POST['previsao_retirada'] ?? '' ) ?: null;
    // Controlados 344/98 (migration_orc_controlado_v1): receita + comprador — a OM herda
    $p['tp_receita']       = sanitize_text_field( $_POST['tp_receita']       ?? '' ) ?: null;
    $p['nr_notificacao']   = sanitize_text_field( $_POST['nr_notificacao']   ?? '' ) ?: null;
    $p['comprador_nome']   = sanitize_text_field( $_POST['comprador_nome']   ?? '' ) ?: null;
    $p['comprador_doc_tp'] = sanitize_text_field( $_POST['comprador_doc_tp'] ?? '' ) ?: null;
    $p['comprador_doc_nr'] = sanitize_text_field( $_POST['comprador_doc_nr'] ?? '' ) ?: null;
    return $p;
}

// contato_id da pessoa (base única do CRM) a partir do card do Kanban.
function tao_formula_contato_do_card( $card_id ) {
    if ( ! $card_id ) return null;
    $r = tao_formula_api( "/crm_cards?id=eq.$card_id&select=contato_id&limit=1" );
    return ( $r['ok'] && ! empty( $r['data'] ) ) ? ( $r['data'][0]['contato_id'] ?? null ) : null;
}

// Grava orçamento tolerando migration_v2 pendente: se o Supabase recusar coluna
// desconhecida, remove os campos novos e tenta de novo (não perde o orçamento).
function tao_formula_orc_gravar( $path, $method, $data ) {
    $r = tao_formula_api( $path, $method, $data );
    if ( ! $r['ok'] && strpos( (string) ( $r['raw'] ?? '' ), 'column' ) !== false ) {
        unset( $data['nome_cliente'], $data['prescritor'], $data['prescritor_id'], $data['posologia'], $data['forma_tipo'], $data['contato_id'], $data['cid_codigo'], $data['cid_descricao'], $data['dt_prescricao'], $data['previsao_retirada'] );
        $r = tao_formula_api( $path, $method, $data );
    }
    return $r;
}

// ── Salvar Orçamento Manual ───────────────────────────────────────────────────

add_action( 'wp_ajax_tao_formula_save_orcamento', function() {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( 'Acesso negado', 403 );

    $cliente_id = tao_formula_cliente_id();
    if ( ! $cliente_id ) wp_send_json_error( 'Cliente não identificado', 400 );

    $itens_raw = stripslashes( $_POST['itens'] ?? '[]' );
    $itens     = json_decode( $itens_raw, true );
    if ( ! is_array( $itens ) ) $itens = [];

    $card_id = sanitize_text_field( $_POST['card_id'] ?? '' ) ?: null;
    $numero  = tao_formula_gerar_numero( $cliente_id, $card_id );

    $data = array_merge( tao_formula_orc_payload( $itens ), [
        'cliente_id'       => $cliente_id,
        'tipo_entrada'     => 'texto',
        'card_id'          => $card_id,
        'numero_orcamento' => $numero,
        'status'           => 'pendente_revisao',
    ] );
    // Vincula à pessoa única do CRM (via card, quando houver; senão pelo autocomplete de paciente)
    $ct = tao_formula_contato_do_card( $card_id );
    if ( ! $ct ) $ct = sanitize_text_field( $_POST['contato_id'] ?? '' ) ?: null;
    if ( $ct ) $data['contato_id'] = $ct;

    $r = tao_formula_orc_gravar( '/orcamentos', 'POST', $data );
    if ( $r['ok'] ) {
        $id = $r['data'][0]['id'] ?? null;
        if ( $card_id && $id ) {
            tao_formula_api( '/crm_cards_historico', 'POST', [
                'card_id'    => $card_id,
                'usuario_id' => get_current_user_id(),
                'motivo'     => 'Orçamento fórmula criado: ORC:' . $numero .
                                ' — R$ ' . number_format( $data['total_orcamento'], 2, ',', '.' ),
                'criado_em'  => gmdate( 'c' ),
            ] );
        }
        if ( $card_id && function_exists( 'tao_crm_sync_valor_oportunidade' ) ) tao_crm_sync_valor_oportunidade( $card_id );
        wp_send_json_success( [ 'id' => $id, 'numero' => $numero ] );
    } else {
        wp_send_json_error( $r['raw'] );
    }
} );

// ── Atualizar Orçamento Existente ─────────────────────────────────────────────

add_action( 'wp_ajax_tao_formula_update_orcamento', function() {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( 'Acesso negado', 403 );

    $orc_id     = sanitize_text_field( $_POST['orc_id'] ?? '' );
    $cliente_id = tao_formula_cliente_id();
    if ( ! $orc_id || ! $cliente_id ) wp_send_json_error( 'Parâmetros inválidos', 400 );

    $re = tao_formula_api( "/orcamentos?id=eq.$orc_id&cliente_id=eq.$cliente_id&select=card_id,numero_orcamento,total_orcamento,status&limit=1" );
    if ( ! $re['ok'] || empty( $re['data'] ) ) wp_send_json_error( 'Orçamento não encontrado', 404 );
    $exist = $re['data'][0];

    // Orçamento APROVADO é imutável (RDC 67): para alterar, estorne a aprovação no card.
    if ( in_array( (string) ( $exist['status'] ?? '' ), [ 'aprovado_farma', 'aceito_paciente' ], true ) ) {
        wp_send_json_error( 'Orçamento aprovado não pode ser alterado. Estorne a aprovação (no card) para editar.', 409 );
    }

    $itens_raw = stripslashes( $_POST['itens'] ?? '[]' );
    $itens     = json_decode( $itens_raw, true );
    if ( ! is_array( $itens ) ) $itens = [];

    $data = tao_formula_orc_payload( $itens );
    // Mantém o vínculo com a pessoa única do CRM (via card; senão pelo autocomplete de paciente)
    $ct = tao_formula_contato_do_card( $exist['card_id'] ?? null );
    if ( ! $ct ) $ct = sanitize_text_field( $_POST['contato_id'] ?? '' ) ?: null;
    if ( $ct ) $data['contato_id'] = $ct;

    $r = tao_formula_orc_gravar( "/orcamentos?id=eq.$orc_id&cliente_id=eq.$cliente_id", 'PATCH', $data );
    if ( $r['ok'] ) {
        $card_id = $exist['card_id'] ?? null;
        if ( $card_id ) {
            $user = wp_get_current_user();
            tao_formula_api( '/crm_cards_historico', 'POST', [
                'card_id'    => $card_id,
                'usuario_id' => get_current_user_id(),
                'motivo'     => 'Orçamento ORC:' . ( $exist['numero_orcamento'] ?? $orc_id ) .
                                ' atualizado — R$ ' . number_format( (float) $exist['total_orcamento'], 2, ',', '.' ) .
                                ' → R$ ' . number_format( $data['total_orcamento'], 2, ',', '.' ) .
                                ' (' . $user->display_name . ')',
                'criado_em'  => gmdate( 'c' ),
            ] );
        }
        if ( $card_id && function_exists( 'tao_crm_sync_valor_oportunidade' ) ) tao_crm_sync_valor_oportunidade( $card_id );
        wp_send_json_success( [ 'id' => $orc_id, 'numero' => $exist['numero_orcamento'] ] );
    } else {
        wp_send_json_error( $r['raw'] );
    }
} );

// ── Buscar Orçamento por ID ───────────────────────────────────────────────────

add_action( 'wp_ajax_tao_formula_get_orcamento', function() {
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( 'Acesso negado', 403 );

    $orc_id     = sanitize_text_field( $_GET['orc_id'] ?? '' );
    $cliente_id = tao_formula_cliente_id();
    if ( ! $orc_id || ! $cliente_id ) wp_send_json_error( 'Parâmetros inválidos', 400 );

    $r = tao_formula_api( "/orcamentos?id=eq.$orc_id&cliente_id=eq.$cliente_id&limit=1" );
    if ( $r['ok'] && ! empty( $r['data'] ) ) {
        wp_send_json_success( $r['data'][0] );
    } else {
        wp_send_json_error( 'Não encontrado', 404 );
    }
} );

// ── Orçamentos vinculados a um card ──────────────────────────────────────────

add_action( 'wp_ajax_tao_formula_get_orcamentos_card', function() {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( 'Acesso negado', 403 );

    $card_id    = sanitize_text_field( $_GET['card_id'] ?? '' );
    $cliente_id = tao_formula_cliente_id();
    if ( ! $card_id || ! $cliente_id ) wp_send_json_error( 'Parâmetros inválidos', 400 );

    $r = tao_formula_api(
        "/orcamentos?card_id=eq.$card_id&cliente_id=eq.$cliente_id" .
        "&select=id,numero_orcamento,forma_nome,forma_vol,forma_unidade,qtde_potes," .
        "total_orcamento,status,criado_em,itens,nome_paciente,desconto_pct,margem_aplicada" .
        "&order=criado_em.asc"
    );
    wp_send_json( $r['ok'] ? [ 'success' => true,  'data' => $r['data'] ?? [] ]
                            : [ 'success' => false, 'data' => $r['raw'] ] );
} );

// ── Formas Farmacêuticas ─────────────────────────────────────────────────────

add_action( 'wp_ajax_tao_formula_save_forma', function() {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( 'Acesso negado', 403 );

    $id         = sanitize_text_field( $_POST['id'] ?? '' );
    $cliente_id = tao_formula_cliente_id();
    if ( ! $cliente_id ) wp_send_json_error( 'Cliente não identificado', 400 );

    $tipo_forma      = sanitize_text_field( $_POST['tipo'] ?? 'gel' );
    $tipo_capsula    = sanitize_text_field( $_POST['tipo_capsula'] ?? '' );
    $numero_capsula  = sanitize_text_field( $_POST['numero_capsula'] ?? '' );
    $vol_cap_ul_raw  = $_POST['vol_cap_ul'] ?? '';
    $ftenchcap_raw   = $_POST['ftenchcap'] ?? '';

    $data = [
        'cliente_id'     => $cliente_id,
        'nome'           => sanitize_text_field( $_POST['nome'] ?? '' ),
        'tipo'           => $tipo_forma,
        'volume'         => isset( $_POST['volume'] ) && $_POST['volume'] !== '' ? (float) $_POST['volume'] : null,
        'unidade_volume' => sanitize_text_field( $_POST['unidade_volume'] ?? 'g' ),
        'n_capsulas'     => isset( $_POST['n_capsulas'] ) && $_POST['n_capsulas'] !== '' ? (int) $_POST['n_capsulas'] : null,
        'tipo_capsula'   => ( $tipo_forma === 'cap' && $tipo_capsula !== '' ) ? $tipo_capsula : null,
        'numero_capsula' => ( $tipo_forma === 'cap' && $numero_capsula !== '' ) ? $numero_capsula : null,
        'vol_cap_ul'     => ( $tipo_forma === 'cap' && $vol_cap_ul_raw !== '' ) ? (float) $vol_cap_ul_raw : null,
        'ftenchcap'      => $ftenchcap_raw !== '' ? (float) $ftenchcap_raw : 1.0,
        'custo_fixo'      => isset( $_POST['custo_fixo'] ) && $_POST['custo_fixo'] !== '' ? (float) $_POST['custo_fixo'] : 0,
        'custo_fixo_tipo' => in_array( $_POST['custo_fixo_tipo'] ?? '', [ 'R', 'pct' ] ) ? $_POST['custo_fixo_tipo'] : null,
        'valor_minimo'    => isset( $_POST['valor_minimo'] ) && $_POST['valor_minimo'] !== '' ? (float) $_POST['valor_minimo'] : null,
        'margem_pct'      => (float) ( $_POST['margem_pct'] ?? 30 ),
        'validade_dias'   => isset( $_POST['validade_dias'] ) && $_POST['validade_dias'] !== '' ? (int) $_POST['validade_dias'] : null,
        'modo_preparo'    => trim( (string) wp_unslash( $_POST['modo_preparo'] ?? '' ) ) ?: null,
        'ativo'           => true,
    ];

    if ( empty( $data['nome'] ) ) wp_send_json_error( 'Nome obrigatório', 400 );

    if ( $id ) {
        $r = tao_formula_api( "/formas_farmaceuticas?id=eq.$id&cliente_id=eq.$cliente_id", 'PATCH', $data );
    } else {
        $r = tao_formula_api( '/formas_farmaceuticas', 'POST', $data );
    }

    if ( $r['ok'] ) {
        wp_send_json_success( $r['data'][0] ?? [] );
    } else {
        wp_send_json_error( $r['raw'], 500 );
    }
} );

add_action( 'wp_ajax_tao_formula_delete_forma', function() {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( 'Acesso negado', 403 );

    $id         = sanitize_text_field( $_POST['id'] ?? '' );
    $cliente_id = tao_formula_cliente_id();
    if ( ! $id || ! $cliente_id ) wp_send_json_error( 'Parâmetros inválidos', 400 );

    $r = tao_formula_api( "/formas_farmaceuticas?id=eq.$id&cliente_id=eq.$cliente_id", 'DELETE' );
    if ( $r['ok'] ) {
        wp_send_json_success();
    } else {
        wp_send_json_error( $r['raw'], 500 );
    }
} );

// ── Configurações ─────────────────────────────────────────────────────────────

add_action( 'wp_ajax_tao_formula_save_config', function() {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_is_master() ) wp_send_json_error( 'Acesso negado', 403 );

    update_option( 'tao_formula_margem_padrao', (float) ( $_POST['margem_padrao'] ?? 30 ) );
    // Checkbox: ausente no POST quando desmarcado
    update_option( 'tao_formula_motor_v2', ( $_POST['motor_v2'] ?? '' ) === '1' ? '1' : '0' );

    wp_send_json_success( 'Configurações salvas.' );
} );

// ── Status do orçamento ───────────────────────────────────────────────────────

add_action( 'wp_ajax_tao_formula_update_orc_status', function() {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( 'Acesso negado', 403 );

    $id     = sanitize_text_field( $_POST['id'] ?? '' );
    $status = sanitize_text_field( $_POST['status'] ?? '' );
    $motivo = sanitize_textarea_field( $_POST['motivo'] ?? '' );
    $allowed = [ 'pendente_revisao', 'aprovado_farma', 'enviado_paciente', 'aceito_paciente', 'rejeitado' ];
    if ( ! $id || ! in_array( $status, $allowed, true ) ) wp_send_json_error( 'Parâmetros inválidos', 400 );

    $cliente_id = tao_formula_cliente_id();

    // ── Avaliação farmacêutica (RDC 67) ──────────────────────────────────────
    // Só o farmacêutico responsável (gestor) ou o master pode aprovar/rejeitar.
    $pode_avaliar = tao_formula_is_master()
        || ( function_exists( 'cbpm_is_gestor' ) && cbpm_is_gestor() );
    if ( in_array( $status, [ 'aprovado_farma', 'rejeitado' ], true ) && ! $pode_avaliar ) {
        wp_send_json_error( 'Apenas o farmacêutico responsável pode avaliar o orçamento.', 403 );
    }

    // Estado atual (para impor a sequência exigida pela RDC)
    $cur = tao_formula_api( "/orcamentos?id=eq.$id&cliente_id=eq.$cliente_id&select=status&limit=1" );
    $status_atual = ( $cur['ok'] && ! empty( $cur['data'] ) ) ? ( $cur['data'][0]['status'] ?? '' ) : '';

    // Trava: não envia ao paciente sem avaliação farmacêutica aprovada.
    if ( $status === 'enviado_paciente' && $status_atual !== 'aprovado_farma' ) {
        wp_send_json_error( 'O orçamento precisa ser aprovado pelo farmacêutico antes do envio ao paciente.', 409 );
    }

    $data = [
        'status'       => $status,
        'atualizado_em'=> gmdate( 'c' ),
    ];
    if ( $status === 'aprovado_farma' ) {
        $data['farmaceutico_id'] = get_current_user_id();
        $data['aprovado_em']     = gmdate( 'c' );
        // Por ora, aprovar = enviar: marca o envio no mesmo ato.
        $data['enviado_em']      = gmdate( 'c' );
        $data['motivo_rejeicao'] = null;
    } elseif ( $status === 'rejeitado' ) {
        if ( $motivo === '' ) wp_send_json_error( 'Informe o motivo da rejeição.', 400 );
        $data['farmaceutico_id'] = get_current_user_id();
        $data['motivo_rejeicao'] = $motivo;
    } elseif ( $status === 'enviado_paciente' ) {
        $data['enviado_em'] = gmdate( 'c' );
    }

    $r = tao_formula_api( "/orcamentos?id=eq.$id&cliente_id=eq.$cliente_id", 'PATCH', $data );
    if ( $r['ok'] ) {
        wp_send_json_success();
    } else {
        wp_send_json_error( $r['raw'], 500 );
    }
} );

// ── Regerar chave API IA ─────────────────────────────────────────────────────
add_action( 'wp_ajax_tao_formula_regen_ia_key', function() {
    check_ajax_referer( 'tao_formula_nonce', '_wpnonce' );
    if ( ! tao_formula_is_master() ) wp_send_json_error( 'Acesso negado', 403 );
    $key = bin2hex( random_bytes( 24 ) );
    update_option( 'tao_formula_ia_api_key', $key );
    wp_send_json_success( [ 'key' => $key ] );
} );

// ── Salvar chave OpenAI API ──────────────────────────────────────────────────
add_action( 'wp_ajax_tao_formula_save_openai_key', function() {
    check_ajax_referer( 'tao_formula_nonce', '_wpnonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( [ 'message' => 'Acesso negado' ], 403 );
    $key = sanitize_text_field( $_POST['key'] ?? '' );
    if ( ! $key ) wp_send_json_error( [ 'message' => 'Chave vazia' ] );
    update_option( 'tao_formula_openai_key', $key );
    wp_send_json_success();
} );

// ── Core: cria orçamento a partir de dados estruturados da IA ────────────────
// Usada tanto pelo endpoint N8N quanto pelo upload do card.

function tao_formula_criar_orc_ia_core( $args ) {
    $cliente_id = tao_formula_cliente_id();
    if ( ! $cliente_id ) return [ 'ok' => false, 'message' => 'cliente_id não configurado' ];

    $card_id    = $args['card_id']          ?? null;
    $nome_pac   = $args['nome_paciente']    ?? '';
    $whatsapp   = $args['whatsapp']         ?? '';
    $forma_txt  = $args['forma_farmaceutica'] ?? '';
    $volume     = max( 1, (float) ( $args['volume'] ?? 30 ) );
    $unidade    = $args['unidade']          ?? 'g';
    $obs_ia     = $args['observacoes_ia']   ?? '';
    $ativos_req = $args['ativos']           ?? [];

    // Busca forma farmacêutica — tenta match exato, depois progressivamente mais curto
    $forma_id   = null;
    $forma_nome = $forma_txt;
    if ( $forma_txt ) {
        $words = preg_split( '/\s+/', trim( $forma_txt ) );
        for ( $wi = count( $words ); $wi >= 1 && ! $forma_id; $wi-- ) {
            $term = implode( ' ', array_slice( $words, 0, $wi ) );
            $rf = tao_formula_api(
                '/formas_farmaceuticas?cliente_id=eq.' . $cliente_id .
                '&nome=ilike.*' . rawurlencode( $term ) . '*&select=id,nome&limit=1'
            );
            if ( $rf['ok'] && ! empty( $rf['data'] ) ) {
                $forma_id   = $rf['data'][0]['id'];
                $forma_nome = $rf['data'][0]['nome'];
            }
        }
    }

    // Monta itens
    $itens           = [];
    $nao_encontrados = [];

    foreach ( $ativos_req as $a ) {
        $nome_a    = trim( $a['nome']    ?? '' );
        $dose      = (float) ( $a['dose']    ?? 0 );
        $dose_unit = $a['unidade'] ?? 'mg';
        $is_qsp    = ! empty( $a['qsp'] );

        $ra = tao_formula_api(
            '/ativos?cliente_id=eq.' . $cliente_id .
            '&or=(nome.ilike.*' . rawurlencode( $nome_a ) . '*,codigo_fc.ilike.*' . rawurlencode( $nome_a ) . '*)' .
            '&select=id,codigo_fc,nome,unidade_padrao,preco_venda,custo_por_unidade,markup_preco,diluicao,teor&limit=1'
        );
        $at    = ( $ra['ok'] && ! empty( $ra['data'] ) ) ? $ra['data'][0] : null;
        $equiv = 1.0;   // equivalência sal↔base quando o match vem de sinônimo (motor v2)

        // Fallback 2: sinônimo exato (case-insensitive)
        if ( ! $at && $nome_a ) {
            $rs = tao_formula_api(
                '/ativos_sinonimos?cliente_id=eq.' . $cliente_id .
                '&sinonimo=ilike.' . rawurlencode( strtolower( $nome_a ) ) .
                '&select=ativo_id,fator_equiv&limit=1'
            );
            if ( $rs['ok'] && ! empty( $rs['data'] ) ) {
                $ra2 = tao_formula_api(
                    '/ativos?id=eq.' . $rs['data'][0]['ativo_id'] .
                    '&cliente_id=eq.' . $cliente_id .
                    '&select=id,codigo_fc,nome,unidade_padrao,preco_venda,custo_por_unidade,markup_preco,diluicao,teor&limit=1'
                );
                $at = ( $ra2['ok'] && ! empty( $ra2['data'] ) ) ? $ra2['data'][0] : null;
                if ( $at ) $equiv = (float) ( $rs['data'][0]['fator_equiv'] ?? 1 ) ?: 1.0;
            }
        }

        // Fallback 3: sinônimo por substring — "VIT D" encontra "VITAMINA D3", "LACTOB" → "LACTOBACILLUS"
        if ( ! $at && $nome_a && mb_strlen( $nome_a ) >= 3 ) {
            $rs = tao_formula_api(
                '/ativos_sinonimos?cliente_id=eq.' . $cliente_id .
                '&sinonimo=ilike.*' . rawurlencode( $nome_a ) . '*' .
                '&select=ativo_id,fator_equiv&limit=1'
            );
            if ( $rs['ok'] && ! empty( $rs['data'] ) ) {
                $ra2 = tao_formula_api(
                    '/ativos?id=eq.' . $rs['data'][0]['ativo_id'] .
                    '&cliente_id=eq.' . $cliente_id .
                    '&select=id,codigo_fc,nome,unidade_padrao,preco_venda,custo_por_unidade,markup_preco,diluicao,teor&limit=1'
                );
                $at = ( $ra2['ok'] && ! empty( $ra2['data'] ) ) ? $ra2['data'][0] : null;
                if ( $at ) $equiv = (float) ( $rs['data'][0]['fator_equiv'] ?? 1 ) ?: 1.0;
            }
        }

        if ( ! $at ) $nao_encontrados[] = $nome_a;
        if ( $at ) $at = tao_formula_aplica_fator( $at );

        $preco     = (float) ( $at['preco_venda']      ?? 0 );
        $unid_p    = $at['unidade_padrao'] ?? 'mg';
        $dose_g    = $dose_unit === 'g'  ? $dose
                   : ( $dose_unit === 'mg' ? $dose / 1000
                   : ( $dose_unit === '%'  ? $dose / 100 * $volume : 0 ) );
        $qtd_tot_g = $is_qsp ? 0 : round( $dose_g * ( $dose_unit === '%' ? 1 : $volume ), 6 );
        $qtd_em_u  = $unid_p === 'g' ? $qtd_tot_g : $qtd_tot_g * 1000;
        $subtotal  = ( $preco > 0 && ! $is_qsp ) ? round( $qtd_em_u * $preco, 4 ) : 0;

        $itens[] = [
            'tipo'              => 'mp',
            'ativo_id'          => $at['id']         ?? null,
            'nome'              => $at ? $at['nome']  : $nome_a,
            'codigo_fc'         => $at['codigo_fc']   ?? '',
            'dose'              => $dose,
            'dose_unit'         => $dose_unit,
            'is_qsp'            => $is_qsp,
            'preco_venda'       => $preco,
            'unid_padrao'       => $unid_p,
            'qtd_total_g'       => $qtd_tot_g,
            'subtotal'          => $subtotal,
            'custo_por_unidade' => (float) ( $at['custo_por_unidade'] ?? 0 ),
            'fp'                => 1,
            'diluicao'          => (float) ( $at['diluicao'] ?? 1 ),
            'teor'              => (float) ( $at['teor']     ?? 100 ),
            'equiv'             => $equiv,   // aplicado no recálculo do editor (motor v2)
            'volapa_ul'         => 0,
        ];
    }

    $obs_partes = [ '[RECEITA IA]' ];
    if ( $nao_encontrados ) $obs_partes[] = 'Ativos não identificados: ' . implode( ', ', $nao_encontrados ) . '.';
    $obs_partes[] = $obs_ia ?: 'Revisar preços e quantidades antes de aprovar.';

    $numero  = tao_formula_gerar_numero( $cliente_id, $card_id );
    $payload = [
        'cliente_id'          => $cliente_id,
        'card_id'             => $card_id,
        'numero_orcamento'    => $numero,
        'status'              => 'pendente_revisao',
        'tipo_entrada'        => 'texto',
        'nome_paciente'       => $nome_pac,
        'whatsapp'            => $whatsapp,
        'forma_id'            => $forma_id,
        'forma_nome'          => $forma_nome,
        'forma_vol'           => $volume,
        'forma_unidade'       => $unidade,
        'qtde_potes'          => 1,
        'itens'               => $itens,
        'total_orcamento'     => 0,
        'total_insumos'       => array_sum( array_column( $itens, 'subtotal' ) ),
        'custo_fixo_aplicado' => 0,
        'margem_aplicada'     => 0,
        'desconto_pct'        => 0,
        'observacoes'         => implode( ' ', $obs_partes ),
        'atualizado_em'       => gmdate( 'c' ),
    ];

    $r = tao_formula_api( '/orcamentos', 'POST', $payload );
    if ( ! $r['ok'] ) return [ 'ok' => false, 'message' => 'Erro ao gravar orçamento: ' . mb_substr( $r['raw'] ?? '', 0, 400 ) ];

    $id = $r['data'][0]['id'] ?? null;
    if ( $card_id && $id ) {
        tao_formula_api( '/crm_cards_historico', 'POST', [
            'card_id'    => $card_id,
            'usuario_id' => 0,
            'motivo'     => '[IA] Orçamento por receita: ' . $numero .
                            ( $nao_encontrados ? ' ⚠ ' . implode( ', ', $nao_encontrados ) : '' ),
            'criado_em'  => gmdate( 'c' ),
        ] );
    }

    return [
        'ok'               => true,
        'id'               => $id,
        'numero'           => $numero,
        'nao_encontrados'  => $nao_encontrados,
        'total_preliminar' => $payload['total_insumos'],
    ];
}

// ── Criar Orçamento via IA / N8N (autenticação por api_key) ──────────────────

add_action( 'wp_ajax_nopriv_tao_formula_criar_orcamento_ia', 'tao_formula_handler_criar_orcamento_ia' );
add_action( 'wp_ajax_tao_formula_criar_orcamento_ia',        'tao_formula_handler_criar_orcamento_ia' );

function tao_formula_handler_criar_orcamento_ia() {
    while ( ob_get_level() > 0 ) ob_end_clean();

    $api_key = sanitize_text_field( $_POST['api_key'] ?? '' );
    $stored  = get_option( 'tao_formula_ia_api_key', '' );
    if ( ! $api_key || ! $stored || ! hash_equals( $stored, $api_key ) ) {
        wp_send_json_error( [ 'message' => 'Não autorizado' ], 401 );
    }

    $ativos_raw = stripslashes( $_POST['ativos'] ?? '[]' );
    $ativos_req = json_decode( $ativos_raw, true );
    if ( ! is_array( $ativos_req ) ) wp_send_json_error( [ 'message' => 'ativos inválido (JSON)' ], 400 );

    $result = tao_formula_criar_orc_ia_core( [
        'card_id'            => sanitize_text_field( $_POST['card_id']           ?? '' ) ?: null,
        'nome_paciente'      => sanitize_text_field( $_POST['nome_paciente']     ?? '' ),
        'whatsapp'           => sanitize_text_field( $_POST['whatsapp']          ?? '' ),
        'forma_farmaceutica' => sanitize_text_field( $_POST['forma_farmaceutica'] ?? '' ),
        'volume'             => (float) ( $_POST['volume']  ?? 30 ),
        'unidade'            => sanitize_text_field( $_POST['unidade']  ?? 'g' ),
        'observacoes_ia'     => sanitize_textarea_field( $_POST['observacoes_ia'] ?? '' ),
        'ativos'             => $ativos_req,
    ] );

    if ( $result['ok'] ) {
        wp_send_json_success( $result );
    } else {
        wp_send_json_error( $result );
    }
}

// ── Processar receita via upload do card CRM ──────────────────────────────────

add_action( 'wp_ajax_tao_formula_processar_receita', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( [ 'message' => 'Acesso negado' ], 403 );

    if ( empty( $_FILES['receita_file'] ) || $_FILES['receita_file']['error'] !== UPLOAD_ERR_OK ) {
        wp_send_json_error( [ 'message' => 'Arquivo não recebido ou erro no upload' ], 400 );
    }

    $file = $_FILES['receita_file'];
    $mime = mime_content_type( $file['tmp_name'] );
    $allowed = [ 'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf' ];
    if ( ! in_array( $mime, $allowed, true ) ) {
        wp_send_json_error( [ 'message' => 'Tipo não suportado. Use JPG, PNG ou PDF.' ] );
    }
    if ( $file['size'] > 20 * 1024 * 1024 ) {
        wp_send_json_error( [ 'message' => 'Arquivo muito grande (máx 20 MB)' ] );
    }

    $openai_key = get_option( 'tao_formula_openai_key', '' );
    if ( ! $openai_key ) {
        wp_send_json_error( [ 'message' => 'Chave OpenAI API não configurada (TAO Fórmula → Configurações)' ] );
    }

    $prompt = 'Analise esta prescrição médica brasileira que pode conter MÚLTIPLAS formulações.
Se não for uma receita médica, retorne {"eh_receita":false}.

Extraia CADA formulação separadamente. Formato:
{
  "eh_receita": true,
  "formulacoes": [
    {
      "forma_farmaceutica": "Cápsulas",
      "volume": 60,
      "unidade": "un",
      "ativos": [
        {"nome": "UREIA", "dose": 10, "unidade": "%"},
        {"nome": "ACIDO ASCORBICO", "dose": 500, "unidade": "mg"},
        {"nome": "BASE CREME", "dose": 0, "unidade": "g", "qsp": true}
      ],
      "observacoes": "tomar 1 ao dia"
    }
  ]
}

Regras:
- Uma entrada em "formulacoes" para CADA formulação da prescrição
- dose: % se percentual, mg se miligramas, g se gramas, mcg se microgramas, UI se unidades internacionais
- qsp:true apenas para excipiente/veículo (QSP)
- nome dos ativos em MAIÚSCULAS
- volume: quantidade prescrita (ex: 60 cápsulas → volume:60, unidade:"un")
- Retorne APENAS o JSON válido, sem markdown';

    // Para PDFs: usa Files API (upload) + referencia por file_id para evitar limite de body
    // Para imagens: envia base64 inline (payload pequeno)
    if ( $mime === 'application/pdf' ) {
        $boundary  = 'WPBoundary' . bin2hex( random_bytes( 8 ) );
        $raw_bytes = file_get_contents( $file['tmp_name'] );
        $mp_body   = "--{$boundary}\r\n"
                   . "Content-Disposition: form-data; name=\"purpose\"\r\n\r\nuser_data\r\n"
                   . "--{$boundary}\r\n"
                   . "Content-Disposition: form-data; name=\"file\"; filename=\"receita.pdf\"\r\n"
                   . "Content-Type: application/pdf\r\n\r\n"
                   . $raw_bytes . "\r\n"
                   . "--{$boundary}--\r\n";

        $up = wp_remote_post( 'https://api.openai.com/v1/files', [
            'headers' => [
                'Authorization' => 'Bearer ' . $openai_key,
                'Content-Type'  => "multipart/form-data; boundary={$boundary}",
            ],
            'body'    => $mp_body,
            'timeout' => 60,
        ] );

        if ( is_wp_error( $up ) ) {
            wp_send_json_error( [ 'message' => 'Erro ao enviar PDF: ' . $up->get_error_message() ] );
        }

        $up_data = json_decode( wp_remote_retrieve_body( $up ), true );
        $file_id = $up_data['id'] ?? null;
        if ( ! $file_id ) {
            wp_send_json_error( [ 'message' => 'OpenAI não aceitou o PDF', 'detail' => $up_data ] );
        }

        $content_block = [ 'type' => 'input_file', 'file_id' => $file_id ];
    } else {
        $b64           = base64_encode( file_get_contents( $file['tmp_name'] ) );
        $content_block = [ 'type' => 'input_image', 'image_url' => 'data:' . $mime . ';base64,' . $b64 ];
        $file_id       = null;
    }

    $api_body = [
        'model'             => 'gpt-4o',
        'max_output_tokens' => 2048,
        'input'             => [ [
            'role'    => 'user',
            'content' => [ $content_block, [ 'type' => 'input_text', 'text' => $prompt ] ],
        ] ],
    ];

    $response = wp_remote_post( 'https://api.openai.com/v1/responses', [
        'headers' => [
            'Authorization' => 'Bearer ' . $openai_key,
            'Content-Type'  => 'application/json',
        ],
        'body'    => wp_json_encode( $api_body ),
        'timeout' => 90,
    ] );

    // Remove o arquivo da OpenAI após o uso
    if ( $file_id ) {
        wp_remote_request( 'https://api.openai.com/v1/files/' . $file_id, [
            'method'  => 'DELETE',
            'headers' => [ 'Authorization' => 'Bearer ' . $openai_key ],
            'timeout' => 10,
        ] );
    }

    if ( is_wp_error( $response ) ) {
        wp_send_json_error( [ 'message' => 'Erro ao chamar API GPT: ' . $response->get_error_message() ] );
    }

    $api_data = json_decode( wp_remote_retrieve_body( $response ), true );
    $raw_text = $api_data['output'][0]['content'][0]['text'] ?? '';
    if ( ! $raw_text ) {
        wp_send_json_error( [ 'message' => 'Resposta vazia da IA', 'detail' => $api_data ] );
    }

    // Remove delimitadores markdown
    $json_txt  = trim( preg_replace( '/^```(?:json)?\s*/mi', '', preg_replace( '/^```\s*$/mi', '', trim( $raw_text ) ) ) );
    $extracted = json_decode( $json_txt, true );

    if ( ! is_array( $extracted ) ) {
        wp_send_json_error( [ 'message' => 'A IA não retornou JSON válido', 'raw' => mb_substr( $raw_text, 0, 400 ) ] );
    }
    if ( empty( $extracted['eh_receita'] ) ) {
        wp_send_json_error( [ 'message' => 'O arquivo não parece ser uma receita médica.' ] );
    }

    $formulacoes = $extracted['formulacoes'] ?? [];
    if ( empty( $formulacoes ) ) {
        wp_send_json_error( [ 'message' => 'Nenhuma formulação encontrada na receita.' ] );
    }

    $card_id    = sanitize_text_field( $_POST['card_id']       ?? '' ) ?: null;
    $nome_pac   = sanitize_text_field( $_POST['nome_paciente'] ?? '' );
    $whatsapp   = sanitize_text_field( $_POST['whatsapp']      ?? '' );

    $resultados    = [];
    $todos_nao_enc = [];
    $primeiro_erro = null;

    foreach ( $formulacoes as $f ) {
        $result = tao_formula_criar_orc_ia_core( [
            'card_id'            => $card_id,
            'nome_paciente'      => $nome_pac,
            'whatsapp'           => $whatsapp,
            'forma_farmaceutica' => $f['forma_farmaceutica'] ?? '',
            'volume'             => (float) ( $f['volume']   ?? 1 ),
            'unidade'            => $f['unidade']            ?? 'un',
            'observacoes_ia'     => $f['observacoes']        ?? '',
            'ativos'             => $f['ativos']             ?? [],
        ] );
        if ( $result['ok'] ) {
            $resultados[]  = [ 'numero' => $result['numero'], 'forma' => $f['forma_farmaceutica'] ?? '' ];
            $todos_nao_enc = array_merge( $todos_nao_enc, $result['nao_encontrados'] );
        } elseif ( ! $primeiro_erro ) {
            $primeiro_erro = $result;
        }
    }

    if ( empty( $resultados ) ) {
        wp_send_json_error( $primeiro_erro ?: [ 'message' => 'Não foi possível criar nenhum orçamento.' ] );
    }

    wp_send_json_success( [
        'orcamentos'      => $resultados,
        'nao_encontrados' => array_values( array_unique( $todos_nao_enc ) ),
        'total'           => count( $resultados ),
    ] );
} );

// ── Reprocessar orçamentos do card (re-tenta match com sinônimos atuais) ─────

add_action( 'wp_ajax_tao_formula_reprocessar_orc', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( 'Acesso negado', 403 );

    $cliente_id = tao_formula_cliente_id();
    $card_id    = sanitize_text_field( $_POST['card_id'] ?? '' );
    $orc_id     = sanitize_text_field( $_POST['orc_id'] ?? '' );
    if ( ! $cliente_id || ( ! $card_id && ! $orc_id ) ) wp_send_json_error( [ 'message' => 'Parâmetros inválidos' ] );

    $filtro = $orc_id ? ( 'id=eq.' . $orc_id ) : ( 'card_id=eq.' . $card_id );
    $ro = tao_formula_api( "/orcamentos?$filtro&cliente_id=eq.$cliente_id&select=id,forma_vol,qtde_potes,itens,status" );
    if ( ! $ro['ok'] ) {
        wp_send_json_error( [ 'message' => 'Erro ao buscar orçamentos: ' . ( $ro['raw'] ?? '' ) ] );
        return;
    }
    if ( empty( $ro['data'] ) ) {
        wp_send_json_success( [ 'atualizados' => 0, 'message' => 'Nenhum orçamento encontrado para este card.' ] );
        return;
    }

    $sel_at    = 'id,codigo_fc,nome,unidade_padrao,preco_venda,custo_por_unidade,markup_preco,fator_perda,diluicao,teor';
    $motor_on  = get_option( 'tao_formula_motor_v2' ) === '1';
    $total_upd = 0;

    foreach ( $ro['data'] as $orc ) {
        // Orçamento APROVADO é imutável — reprocessamento pula (estorne para reprocessar).
        if ( in_array( (string) ( $orc['status'] ?? '' ), [ 'aprovado_farma', 'aceito_paciente' ], true ) ) continue;
        $itens = $orc['itens'] ?? [];
        if ( is_string( $itens ) ) $itens = json_decode( $itens, true ) ?: [];
        if ( ! is_array( $itens ) ) continue;

        $forma_vol  = max( 1.0, (float) ( $orc['forma_vol']  ?? 30 ) );
        $qtde_potes = max( 1,   (int)   ( $orc['qtde_potes'] ?? 1  ) );
        $mult       = $forma_vol * $qtde_potes;

        $modified = false;
        foreach ( $itens as &$item ) {
            if ( ! empty( $item['ativo_id'] ) || empty( $item['nome'] ) ) continue;

            // Nome da prescrição (pode estar em nome_prescricao ou em nome)
            $nome_busca = $item['nome_prescricao'] ?? $item['nome'];
            $nome_enc   = rawurlencode( $nome_busca );
            $at         = null;
            $equiv      = 1.0;   // equivalência sal↔base via sinônimo (motor v2)

            // 1) ILIKE direto no nome/codigo_fc
            $ra = tao_formula_api(
                '/ativos?cliente_id=eq.' . $cliente_id .
                '&or=(nome.ilike.*' . $nome_enc . '*,codigo_fc.ilike.*' . $nome_enc . '*)' .
                '&select=' . $sel_at . '&limit=1'
            );
            if ( $ra['ok'] && ! empty( $ra['data'] ) ) $at = $ra['data'][0];

            // 2) Sinônimo — correspondência exata (case-insensitive)
            if ( ! $at ) {
                $rs = tao_formula_api(
                    '/ativos_sinonimos?cliente_id=eq.' . $cliente_id .
                    '&sinonimo=ilike.' . rawurlencode( $nome_busca ) .
                    '&select=ativo_id,fator_equiv&limit=1'
                );
                if ( $rs['ok'] && ! empty( $rs['data'] ) ) {
                    $ra2 = tao_formula_api( '/ativos?id=eq.' . $rs['data'][0]['ativo_id'] . '&cliente_id=eq.' . $cliente_id . '&select=' . $sel_at . '&limit=1' );
                    if ( $ra2['ok'] && ! empty( $ra2['data'] ) ) {
                        $at    = $ra2['data'][0];
                        $equiv = (float) ( $rs['data'][0]['fator_equiv'] ?? 1 ) ?: 1.0;
                    }
                }
            }

            // 3) Sinônimo — substring
            if ( ! $at && mb_strlen( $nome_busca ) >= 3 ) {
                $rs = tao_formula_api(
                    '/ativos_sinonimos?cliente_id=eq.' . $cliente_id .
                    '&sinonimo=ilike.*' . $nome_enc . '*' .
                    '&select=ativo_id,fator_equiv&limit=1'
                );
                if ( $rs['ok'] && ! empty( $rs['data'] ) ) {
                    $ra2 = tao_formula_api( '/ativos?id=eq.' . $rs['data'][0]['ativo_id'] . '&cliente_id=eq.' . $cliente_id . '&select=' . $sel_at . '&limit=1' );
                    if ( $ra2['ok'] && ! empty( $ra2['data'] ) ) {
                        $at    = $ra2['data'][0];
                        $equiv = (float) ( $rs['data'][0]['fator_equiv'] ?? 1 ) ?: 1.0;
                    }
                }
            }

            if ( ! $at ) continue;
            $at = tao_formula_aplica_fator( $at );

            // Recalcular subtotal com fórmula completa (replica calcularLinha do JS)
            $dose      = (float) ( $item['dose']     ?? 0 );
            $dose_unit = strtolower( $item['dose_unit'] ?? 'mg' );
            $is_qsp    = ! empty( $item['is_qsp'] );
            $unid_p    = $at['unidade_padrao'] ?? 'mg';
            $preco     = (float) ( $at['preco_venda']      ?? 0 );
            $fp        = (float) ( $at['fator_perda']      ?? 1 );
            $diluicao  = (float) ( $at['diluicao']         ?? 1 );
            $teor      = (float) ( $at['teor']             ?? 100 );

            $qtd_tot_g = 0.0;
            $subtotal  = 0.0;
            if ( ! $is_qsp && $dose > 0 && $preco > 0 ) {
                switch ( $dose_unit ) {
                    case 'g':   $dose_mg = $dose * 1000; break;
                    case 'mcg': $dose_mg = $dose / 1000; break;
                    default:    $dose_mg = $dose; break;
                }
                $dose_mg_real  = $dose_mg * ( $motor_on ? $equiv : 1 ) * $diluicao / max( 0.001, $teor / 100 );
                $qtd_total_mg  = $dose_mg_real * $fp * $mult;
                $qtd_tot_g     = $qtd_total_mg / 1000;
                $qtd_em_padrao = strtolower( $unid_p ) === 'g' ? $qtd_tot_g : $qtd_total_mg;
                $subtotal      = round( $qtd_em_padrao * $preco, 4 );
            }

            // Preserva nome_prescricao antes de substituir nome pelo canônico
            if ( empty( $item['nome_prescricao'] ) ) {
                $item['nome_prescricao'] = strtoupper( $nome_busca );
            }
            $item['ativo_id']          = $at['id'];
            $item['nome']              = $at['nome'];
            $item['codigo_fc']         = $at['codigo_fc']         ?? '';
            $item['unid_padrao']       = $unid_p;
            $item['preco_venda']       = $preco;
            $item['custo_por_unidade'] = (float) ( $at['custo_por_unidade'] ?? 0 );
            $item['fp']                = $fp;
            $item['diluicao']          = $diluicao;
            $item['teor']              = $teor;
            $item['equiv']             = $equiv;
            $item['qtd_total_g']       = $qtd_tot_g;
            $item['multiplicador']     = $mult;
            $item['subtotal']          = $subtotal;

            $modified = true;
            $total_upd++;
        }
        unset( $item );

        if ( $modified ) {
            // Recalcula totais do orçamento
            $total_insumos = array_reduce( $itens, function( $c, $i ) {
                return $c + ( ( $i['tipo'] ?? 'mp' ) === 'mp' ? (float) ( $i['subtotal'] ?? 0 ) : 0 );
            }, 0.0 );
            $total_emb = array_reduce( $itens, function( $c, $i ) {
                return $c + ( ( $i['tipo'] ?? 'mp' ) === 'emb' ? (float) ( $i['subtotal'] ?? 0 ) : 0 );
            }, 0.0 );

            tao_formula_api( '/orcamentos?id=eq.' . $orc['id'] . '&cliente_id=eq.' . $cliente_id, 'PATCH', [
                'itens'          => $itens,
                'total_insumos'  => round( $total_insumos, 2 ),
                'atualizado_em'  => gmdate( 'c' ),
            ] );
        }
    }

    wp_send_json_success( [
        'atualizados' => $total_upd,
        'message'     => $total_upd > 0
            ? $total_upd . ' ativo(s) associado(s) e recalculado(s) com sucesso.'
            : 'Nenhum ativo pendente encontrado nos orçamentos.',
    ] );
} );

// ── Excluir orçamento ─────────────────────────────────────────────────────────

add_action( 'wp_ajax_tao_formula_excluir_orcamento', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( 'Acesso negado', 403 );
    $cliente_id = tao_formula_cliente_id();
    $orc_id     = sanitize_text_field( $_POST['orc_id'] ?? '' );
    if ( ! $orc_id || ! $cliente_id ) wp_send_json_error( 'Parâmetros inválidos' );
    // Pega o card antes de excluir, p/ re-sincronizar o valor de oportunidade
    $rc = tao_formula_api( "/orcamentos?id=eq.$orc_id&cliente_id=eq.$cliente_id&select=card_id,status&limit=1" );
    $del_card_id = ( $rc['ok'] && ! empty( $rc['data'] ) ) ? ( $rc['data'][0]['card_id'] ?? '' ) : '';
    // Orçamento APROVADO é imutável (RDC 67): para excluir, estorne a aprovação no card.
    $del_status = ( $rc['ok'] && ! empty( $rc['data'] ) ) ? (string) ( $rc['data'][0]['status'] ?? '' ) : '';
    if ( in_array( $del_status, [ 'aprovado_farma', 'aceito_paciente' ], true ) ) {
        wp_send_json_error( [ 'message' => 'Orçamento aprovado não pode ser excluído. Estorne a aprovação (no card) primeiro.' ], 409 );
    }
    $r = tao_formula_api( "/orcamentos?id=eq.$orc_id&cliente_id=eq.$cliente_id", 'DELETE' );
    if ( $r['ok'] ) {
        if ( $del_card_id && function_exists( 'tao_crm_sync_valor_oportunidade' ) ) tao_crm_sync_valor_oportunidade( $del_card_id );
        wp_send_json_success();
    } else {
        wp_send_json_error( [ 'message' => 'Erro: ' . $r['raw'] ] );
    }
} );

// ── Salvar sinônimo manualmente ───────────────────────────────────────────────

add_action( 'wp_ajax_tao_formula_salvar_sinonimo', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( 'Acesso negado', 403 );
    $cliente_id = tao_formula_cliente_id();
    $ativo_id   = sanitize_text_field( $_POST['ativo_id'] ?? '' );
    $sinonimo   = strtoupper( trim( sanitize_text_field( $_POST['sinonimo'] ?? '' ) ) );
    if ( ! $ativo_id || ! $sinonimo || ! $cliente_id ) wp_send_json_error( 'Parâmetros inválidos' );
    // UPSERT: se o sinônimo já existe (constraint lower(sinonimo)), re-associa ao ativo informado
    $ex = tao_formula_api( '/ativos_sinonimos?cliente_id=eq.' . $cliente_id . '&sinonimo=ilike.' . rawurlencode( $sinonimo ) . '&select=id&limit=1' );
    if ( $ex['ok'] && ! empty( $ex['data'] ) ) {
        $r = tao_formula_api( "/ativos_sinonimos?id=eq.{$ex['data'][0]['id']}&cliente_id=eq.$cliente_id", 'PATCH', [ 'ativo_id' => $ativo_id ] );
        $r['ok'] ? wp_send_json_success() : wp_send_json_error( [ 'message' => 'Erro ao re-associar: ' . $r['raw'] ] );
        return;
    }
    $r = tao_formula_api( '/ativos_sinonimos', 'POST', [
        'cliente_id' => $cliente_id,
        'ativo_id'   => $ativo_id,
        'sinonimo'   => $sinonimo,
    ] );
    $r['ok'] ? wp_send_json_success() : wp_send_json_error( [ 'message' => 'Erro ao salvar: ' . $r['raw'] ] );
} );

// ── Excluir sinônimo ──────────────────────────────────────────────────────────

add_action( 'wp_ajax_tao_formula_excluir_sinonimo', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( 'Acesso negado', 403 );
    $cliente_id = tao_formula_cliente_id();
    $sin_id     = sanitize_text_field( $_POST['sin_id'] ?? '' );
    if ( ! $sin_id || ! $cliente_id ) wp_send_json_error( 'Parâmetros inválidos' );
    $r = tao_formula_api( "/ativos_sinonimos?id=eq.$sin_id&cliente_id=eq.$cliente_id", 'DELETE' );
    $r['ok'] ? wp_send_json_success() : wp_send_json_error( [ 'message' => 'Erro: ' . $r['raw'] ] );
} );

// ── Buscar ativos (para seletor de sinônimos) ─────────────────────────────────

add_action( 'wp_ajax_tao_formula_buscar_ativos', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( 'Acesso negado', 403 );
    $cliente_id = tao_formula_cliente_id();
    $q          = sanitize_text_field( $_POST['q'] ?? '' );
    if ( ! $q || ! $cliente_id ) { wp_send_json_success( [] ); return; }
    $r = tao_formula_api(
        '/ativos?cliente_id=eq.' . $cliente_id .
        '&or=(nome.ilike.*' . rawurlencode( $q ) . '*,codigo_fc.ilike.*' . rawurlencode( $q ) . '*)' .
        '&select=id,nome,codigo_fc&order=nome.asc&limit=15'
    );
    wp_send_json_success( $r['data'] ?? [] );
} );

// ── Listar ativos paginados (com contagem de sinônimos) ───────────────────────

add_action( 'wp_ajax_tao_formula_listar_ativos_pag', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( 'Acesso negado', 403 );
    $cliente_id = tao_formula_cliente_id();
    if ( ! $cliente_id ) wp_send_json_error( 'Cliente não identificado', 400 );

    $q      = sanitize_text_field( $_POST['q']      ?? '' );
    $size   = in_array( intval( $_POST['size'] ?? 30 ), [ 20, 30, 50 ], true ) ? intval( $_POST['size'] ) : 30;
    $offset = max( 0, intval( $_POST['offset']      ?? 0 ) );

    $url = '/ativos?cliente_id=eq.' . $cliente_id
        . ( $q ? '&or=(nome.ilike.*' . rawurlencode( $q ) . '*,codigo_fc.ilike.*' . rawurlencode( $q ) . '*)' : '' )
        . '&select=id,nome,codigo_fc&order=nome.asc&limit=' . $size . '&offset=' . $offset;

    $r = tao_formula_api( $url, 'GET', null, true );
    if ( ! $r['ok'] ) { wp_send_json_error( 'Erro ao buscar ativos' ); return; }

    $ativos = $r['data'] ?? [];
    $counts = [];

    if ( ! empty( $ativos ) ) {
        $ids_str = implode( ',', array_column( $ativos, 'id' ) );
        $rc = tao_formula_api( "/ativos_sinonimos?cliente_id=eq.$cliente_id&ativo_id=in.($ids_str)&select=ativo_id&limit=3000" );
        if ( $rc['ok'] ) {
            foreach ( $rc['data'] ?? [] as $s ) {
                $counts[ $s['ativo_id'] ] = ( $counts[ $s['ativo_id'] ] ?? 0 ) + 1;
            }
        }
    }

    foreach ( $ativos as &$a ) {
        $a['sinonimos_count'] = $counts[ $a['id'] ] ?? 0;
    }

    wp_send_json_success( [
        'items' => $ativos,
        'total' => (int) $r['total'],
    ] );
} );

// ── Listar sinônimos de um ativo ──────────────────────────────────────────────

add_action( 'wp_ajax_tao_formula_listar_sinonimos', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( 'Acesso negado', 403 );
    $cliente_id = tao_formula_cliente_id();
    $ativo_id   = sanitize_text_field( $_POST['ativo_id'] ?? '' );
    if ( ! $ativo_id || ! $cliente_id ) wp_send_json_error( 'Parâmetros inválidos' );
    $r = tao_formula_api(
        '/ativos_sinonimos?cliente_id=eq.' . $cliente_id .
        '&ativo_id=eq.' . $ativo_id .
        '&select=id,sinonimo&order=sinonimo.asc'
    );
    wp_send_json_success( $r['data'] ?? [] );
} );

// ── Sinônimos: lista geral (centrada no sinônimo) — busca + filtro sem associação ─
add_action( 'wp_ajax_tao_formula_sinonimos_lista', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( 'Acesso negado', 403 );
    $cliente_id = tao_formula_cliente_id();
    if ( ! $cliente_id ) wp_send_json_error( 'Cliente não identificado', 400 );
    $q   = sanitize_text_field( $_POST['q'] ?? '' );
    $sem = ( ( $_POST['sem_ativo'] ?? '' ) == '1' );
    $qs  = '/ativos_sinonimos?cliente_id=eq.' . $cliente_id .
           '&select=id,sinonimo,ativo_id&order=sinonimo.asc&limit=500';
    if ( $q !== '' ) $qs .= '&sinonimo=ilike.*' . rawurlencode( $q ) . '*';
    if ( $sem )      $qs .= '&ativo_id=is.null';
    $r    = tao_formula_api( $qs );
    $rows = $r['data'] ?? [];
    // Junta nome/código do ativo no PHP (sem depender de FK/embed do PostgREST)
    $ids  = array_values( array_unique( array_filter( array_column( $rows, 'ativo_id' ) ) ) );
    $amap = [];
    if ( $ids ) {
        $ra = tao_formula_api( '/ativos?id=in.(' . implode( ',', $ids ) . ')&select=id,nome,codigo_fc' );
        foreach ( ( $ra['data'] ?? [] ) as $a ) $amap[ $a['id'] ] = $a;
    }
    foreach ( $rows as &$row ) { $row['ativos'] = $amap[ $row['ativo_id'] ] ?? null; }
    unset( $row );
    wp_send_json_success( $rows );
} );

// ── Sinônimos: associar / alterar / desassociar o ativo ──────────────────────
add_action( 'wp_ajax_tao_formula_associar_sinonimo', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( 'Acesso negado', 403 );
    $cliente_id = tao_formula_cliente_id();
    $sin_id     = sanitize_text_field( $_POST['sin_id'] ?? '' );
    $ativo_id   = sanitize_text_field( $_POST['ativo_id'] ?? '' );
    if ( ! $sin_id || ! $cliente_id ) wp_send_json_error( 'Parâmetros inválidos' );
    $r = tao_formula_api( "/ativos_sinonimos?id=eq.$sin_id&cliente_id=eq.$cliente_id", 'PATCH', [
        'ativo_id' => $ativo_id !== '' ? $ativo_id : null,
    ] );
    $r['ok'] ? wp_send_json_success() : wp_send_json_error( [ 'message' => 'Erro: ' . $r['raw'] ] );
} );

// ── Sinônimos: criar novo (ativo opcional → pode ficar sem associação) ───────
add_action( 'wp_ajax_tao_formula_criar_sinonimo', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( 'Acesso negado', 403 );
    $cliente_id = tao_formula_cliente_id();
    $sinonimo   = strtoupper( trim( sanitize_text_field( $_POST['sinonimo'] ?? '' ) ) );
    $ativo_id   = sanitize_text_field( $_POST['ativo_id'] ?? '' );
    if ( ! $sinonimo || ! $cliente_id ) wp_send_json_error( 'Informe o sinônimo' );

    // Upsert: se o sinônimo já existe (case-insensitive, ex.: órfão sem ativo), atualiza o ativo
    $ex = tao_formula_api( '/ativos_sinonimos?cliente_id=eq.' . $cliente_id . '&sinonimo=ilike.' . rawurlencode( $sinonimo ) . '&select=id&limit=1' );
    if ( $ex['ok'] && ! empty( $ex['data'] ) ) {
        $sid = $ex['data'][0]['id'];
        $r   = tao_formula_api( "/ativos_sinonimos?id=eq.$sid&cliente_id=eq.$cliente_id", 'PATCH', [
            'ativo_id' => $ativo_id !== '' ? $ativo_id : null,
        ] );
        $r['ok'] ? wp_send_json_success( [ 'id' => $sid ] ) : wp_send_json_error( [ 'message' => 'Erro ao atualizar: ' . $r['raw'] ] );
        return;
    }

    $r = tao_formula_api( '/ativos_sinonimos', 'POST', [
        'cliente_id' => $cliente_id,
        'ativo_id'   => $ativo_id !== '' ? $ativo_id : null,
        'sinonimo'   => $sinonimo,
    ] );
    if ( $r['ok'] ) {
        wp_send_json_success( [ 'id' => $r['data'][0]['id'] ?? '' ] );
    } else {
        wp_send_json_error( [ 'message' => 'Erro ao criar: ' . $r['raw'] ] );
    }
} );

// Conjunto normalizado de TODOS os sinônimos com ativo (paginado — PostgREST limita a 1000/req).
function tao_formula_sinonimos_set( $cliente_id, $norm ) {
    $set = []; $offset = 0;
    do {
        $r    = tao_formula_api( '/ativos_sinonimos?cliente_id=eq.' . $cliente_id . '&ativo_id=not.is.null&select=sinonimo&order=id.asc&limit=1000&offset=' . $offset );
        $rows = $r['data'] ?? [];
        foreach ( $rows as $s ) { $k = $norm( $s['sinonimo'] ); if ( $k !== '' ) $set[ $k ] = true; }
        $offset += 1000;
    } while ( count( $rows ) === 1000 && $offset < 50000 );
    return $set;
}

// ── Sinônimos: termos dos ORÇAMENTOS sem associação a um ativo base ───────────
// Varre os itens (JSON) dos orçamentos e lista os ingredientes (mp) com ativo_id vazio,
// agrupados por nome, ignorando os que já têm sinônimo cadastrado.
add_action( 'wp_ajax_tao_formula_sinonimos_nao_atribuidos', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( 'Acesso negado', 403 );
    $cliente_id = tao_formula_cliente_id();
    if ( ! $cliente_id ) wp_send_json_error( 'Cliente não identificado', 400 );

    // Normaliza p/ comparação: maiúsculas, sem acentos de espaço duplicado/trim
    $norm = function ( $s ) { return mb_strtoupper( trim( preg_replace( '/\s+/u', ' ', (string) $s ) ) ); };
    $r = tao_formula_api( '/orcamentos?cliente_id=eq.' . $cliente_id . '&select=numero_orcamento,itens&order=criado_em.desc&limit=800' );
    $termos = [];
    foreach ( ( $r['data'] ?? [] ) as $o ) {
        $itens = $o['itens'];
        if ( is_string( $itens ) ) $itens = json_decode( $itens, true );
        if ( ! is_array( $itens ) ) continue;
        foreach ( $itens as $it ) {
            if ( ( $it['tipo'] ?? 'mp' ) !== 'mp' ) continue;
            if ( ! empty( $it['is_qsp'] ) ) continue;                     // excipiente/QSP não conta
            $aid = trim( (string) ( $it['ativo_id'] ?? '' ) );
            if ( $aid !== '' ) continue;                                  // já associado
            $nome = trim( (string) ( $it['nome_prescricao'] ?? $it['nome'] ?? '' ) );
            if ( $nome === '' ) continue;
            $key = $norm( $nome );
            if ( ! isset( $termos[ $key ] ) ) $termos[ $key ] = [ 'nome' => $nome, 'count' => 0, 'orcs' => [] ];
            $termos[ $key ]['count']++;
            $num = $o['numero_orcamento'] ?? '';
            if ( $num && count( $termos[ $key ]['orcs'] ) < 3 && ! in_array( $num, $termos[ $key ]['orcs'], true ) ) $termos[ $key ]['orcs'][] = $num;
        }
    }
    // Remove os que já possuem sinônimo cadastrado (associado a um ativo) — paginado
    $jaSin = tao_formula_sinonimos_set( $cliente_id, $norm );
    $out = [];
    foreach ( $termos as $key => $t ) { if ( ! isset( $jaSin[ $key ] ) ) $out[] = $t; }
    usort( $out, function ( $a, $b ) { return $b['count'] - $a['count']; } );
    wp_send_json_success( $out );
} );

// ── Sinônimos: orçamentos reprocessáveis (têm item sem ativo cujo termo já tem sinônimo) ─
add_action( 'wp_ajax_tao_formula_reprocessar_lista', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( 'Acesso negado', 403 );
    $cliente_id = tao_formula_cliente_id();
    if ( ! $cliente_id ) wp_send_json_error( 'Cliente não identificado', 400 );

    $norm = function ( $s ) { return mb_strtoupper( trim( preg_replace( '/\s+/u', ' ', (string) $s ) ) ); };
    // Conjunto de sinônimos já associados a um ativo (paginado — PostgREST limita a 1000/req)
    $sinset = tao_formula_sinonimos_set( $cliente_id, $norm );

    $ro = tao_formula_api( '/orcamentos?cliente_id=eq.' . $cliente_id . '&select=id,numero_orcamento,nome_paciente,card_id,itens&order=criado_em.desc&limit=800' );
    $out = [];
    foreach ( ( $ro['data'] ?? [] ) as $o ) {
        $itens = $o['itens'];
        if ( is_string( $itens ) ) $itens = json_decode( $itens, true );
        if ( ! is_array( $itens ) ) continue;
        $ativos = [];
        foreach ( $itens as $it ) {
            if ( ( $it['tipo'] ?? 'mp' ) !== 'mp' ) continue;
            if ( ! empty( $it['is_qsp'] ) ) continue;
            if ( trim( (string) ( $it['ativo_id'] ?? '' ) ) !== '' ) continue;
            $nome = trim( (string) ( $it['nome_prescricao'] ?? $it['nome'] ?? '' ) );
            if ( $nome === '' ) continue;
            if ( isset( $sinset[ $norm( $nome ) ] ) ) $ativos[] = $nome;
        }
        if ( $ativos ) $out[] = [
            'orc_id'   => $o['id'],
            'numero'   => $o['numero_orcamento'] ?? '',
            'paciente' => $o['nome_paciente'] ?? '',
            'ativos'   => array_values( array_unique( $ativos ) ),
        ];
    }
    wp_send_json_success( $out );
} );

// ── Batch insert no Supabase ignorando duplicatas ────────────────────────────

function tao_formula_batch_insert_sinonimos( $rows ) {
    if ( empty( $rows ) ) return 0;
    $url = rtrim( tao_formula_supabase_url(), '/' ) . '/rest/v1/ativos_sinonimos';
    $key = tao_formula_supabase_key();
    $inseridos = 0;
    foreach ( array_chunk( $rows, 100 ) as $chunk ) {
        $resp = wp_remote_post( $url, [
            'timeout' => 20,
            'headers' => [
                'apikey'        => $key,
                'Authorization' => 'Bearer ' . $key,
                'Content-Type'  => 'application/json',
                'Prefer'        => 'resolution=ignore-duplicates,return=minimal',
            ],
            'body' => wp_json_encode( $chunk ),
        ] );
        $code = is_wp_error( $resp ) ? 0 : wp_remote_retrieve_response_code( $resp );
        if ( $code >= 200 && $code < 300 ) $inseridos += count( $chunk );
    }
    return $inseridos;
}

// ── Gerar sinônimos via GPT-4o (lote com paginação por offset) ───────────────
// JS passa offset crescente; PHP pagina os ativos e verifica quais precisam.

add_action( 'wp_ajax_tao_formula_gerar_sinonimos_lote', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    @set_time_limit( 120 );
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( 'Acesso negado', 403 );

    $cliente_id = tao_formula_cliente_id();
    $openai_key = get_option( 'tao_formula_openai_key', '' );
    if ( ! $cliente_id ) wp_send_json_error( [ 'message' => 'cliente_id não configurado' ] );
    if ( ! $openai_key ) wp_send_json_error( [ 'message' => 'Chave OpenAI não configurada' ] );

    $offset     = max( 0, (int) ( $_POST['offset'] ?? 0 ) );
    $page_size  = 5;

    // Pagina todos os ativos por offset (sem NOT IN gigante)
    $ra = tao_formula_api(
        '/ativos?cliente_id=eq.' . $cliente_id .
        '&select=id,nome,codigo_fc&order=nome.asc' .
        '&limit=' . $page_size . '&offset=' . $offset
    );
    $pagina = $ra['data'] ?? [];

    if ( empty( $pagina ) ) {
        wp_send_json_success( [ 'done' => true, 'processados' => 0, 'inseridos' => 0, 'next_offset' => $offset ] );
    }

    // Filtra só os que ainda não têm sinônimos (5 chamadas leves)
    $sem_sin = [];
    foreach ( $pagina as $at ) {
        $rc = tao_formula_api(
            '/ativos_sinonimos?cliente_id=eq.' . $cliente_id .
            '&ativo_id=eq.' . $at['id'] . '&select=id&limit=1'
        );
        if ( $rc['ok'] && empty( $rc['data'] ) ) $sem_sin[] = $at;
    }

    $inseridos = 0;
    if ( ! empty( $sem_sin ) ) {
        $lista_json = wp_json_encode(
            array_map( fn( $a ) => [ 'id' => $a['id'], 'nome' => $a['nome'], 'codigo_fc' => $a['codigo_fc'] ?? '' ], $sem_sin ),
            JSON_UNESCAPED_UNICODE
        );

        $prompt = 'Você é farmacêutico especialista em farmácia magistral brasileira com domínio profundo da literatura farmacêutica nacional e internacional (USP, Farmacopeia Brasileira, Ph.Eur., Merck Index, CFF).

Para CADA ativo abaixo, gere lista COMPLETA e CRITERIOSA de todos os sinônimos que podem aparecer em prescrições médicas e formulações magistrais brasileiras. Inclua:
1. DCI (Denominação Comum Internacional em português) e INN (inglês)
2. Nome IUPAC e nomes químicos alternativos
3. Nomes e marcas comerciais conhecidos no Brasil
4. Abreviações usadas em prescrições (ex: "Vit D3", "Q10", "NAC", "AA", "Mg")
5. Variações ortográficas: com/sem acento, hífen, espaços
6. Nomes populares em português do Brasil
7. Sais, ésteres, quelatos e formas relacionadas (ex: "Citrato de Mg", "Mg Citrato")
8. Prefixos relevantes: L-, D-, dl-, R-, S-

Formato de resposta — SOMENTE JSON válido, sem markdown, sem texto:
{"ID_EXATO": ["SINONIMO1", "SINONIMO2", ...], "OUTRO_ID": [...]}

Strings em MAIÚSCULAS. Não repita o nome original. Seja exaustivo.

Ativos: ' . $lista_json;

        $api_resp = wp_remote_post( 'https://api.openai.com/v1/chat/completions', [
            'headers' => [ 'Authorization' => 'Bearer ' . $openai_key, 'Content-Type' => 'application/json' ],
            'body'    => wp_json_encode( [ 'model' => 'gpt-4o', 'max_tokens' => 4096, 'messages' => [ [ 'role' => 'user', 'content' => $prompt ] ] ] ),
            'timeout' => 60,
        ] );

        if ( ! is_wp_error( $api_resp ) ) {
            $api_data = json_decode( wp_remote_retrieve_body( $api_resp ), true );
            $raw      = $api_data['choices'][0]['message']['content'] ?? '';
            $json_txt = trim( preg_replace( [ '/^```(?:json)?\s*/mi', '/^```\s*$/mi' ], '', trim( $raw ) ) );
            $resultado = json_decode( $json_txt, true );

            if ( is_array( $resultado ) ) {
                $rows = [];
                foreach ( $resultado as $ativo_id => $sins ) {
                    if ( ! is_array( $sins ) ) continue;
                    foreach ( $sins as $sin ) {
                        $sin = strtoupper( trim( (string) $sin ) );
                        if ( $sin ) $rows[] = [ 'cliente_id' => $cliente_id, 'ativo_id' => $ativo_id, 'sinonimo' => $sin ];
                    }
                }
                $inseridos = tao_formula_batch_insert_sinonimos( $rows );
            }
        }
    }

    $next = $offset + $page_size;
    wp_send_json_success( [
        'done'        => count( $pagina ) < $page_size,
        'processados' => count( $pagina ),
        'sem_sin'     => count( $sem_sin ),
        'inseridos'   => $inseridos,
        'next_offset' => $next,
    ] );
} );

// ── Importar orçamentos a partir de texto (formato ORC:…) ────────────────────

/**
 * Sugere embalagem para a forma importada e retorna item pronto com preço do banco.
 * Retorna array de item 'emb' ou null se não houver sugestão.
 */
function tao_formula_sugerir_embalagem_import( $forma_tipo, $forma_vol, $cliente_id ) {
    // Tabela estática: [tipo → opções ordenadas por volume crescente]
    $table = [
        'creme'   => [
            ['c'=>62921,'n'=>'BISNAGA PLASTICA 30G',           'v'=>30],
            ['c'=>62661,'n'=>'BISNAGA PLASTICA 60G',           'v'=>60],
            ['c'=>62757,'n'=>'BISNAGA PLASTICA 100G',          'v'=>100],
            ['c'=>62732,'n'=>'BISNAGA PLASTICA 200G',          'v'=>200],
        ],
        'gel'     => [
            ['c'=>10607,'n'=>'FRESH ROLLER 15ML',              'v'=>15],
            ['c'=>10754,'n'=>'FRASCO PUMP MEG 30ML',           'v'=>30],
            ['c'=>10603,'n'=>'FRASCO PUMP 50ML',               'v'=>50],
        ],
        'locao'   => [
            ['c'=>66516,'n'=>'FRASCO GOTEJADOR 30ML',          'v'=>30],
            ['c'=>66517,'n'=>'FRASCO GOTEJADOR 60ML',          'v'=>60],
            ['c'=>64700,'n'=>'FRASCO GOTEJADOR 120ML',         'v'=>120],
            ['c'=>11020,'n'=>'FRASCO GOTEJADOR 250ML',         'v'=>250],
        ],
        'shampoo' => [
            ['c'=>10585,'n'=>'FRASCO SHAMPOO/SABONETE 120ML',  'v'=>120],
            ['c'=>24492,'n'=>'FRASCO SHAMPOO/SABONETE 250ML',  'v'=>250],
            ['c'=>10954,'n'=>'FRASCO SHAMPOO 350ML',           'v'=>350],
            ['c'=>12702,'n'=>'FRASCO SHAMPOO 500ML',           'v'=>500],
        ],
        'solucao' => [
            ['c'=>66187,'n'=>'VIDRO 30ML',                     'v'=>30],
            ['c'=>12591,'n'=>'FRASCO PET AMBAR 60ML',          'v'=>60],
            ['c'=>12593,'n'=>'FRASCO PET AMBAR 100ML',         'v'=>100],
            ['c'=>11023,'n'=>'FRASCO PET AMBAR 150ML',         'v'=>150],
            ['c'=>10605,'n'=>'FRASCO PET AMBAR 250ML',         'v'=>250],
            ['c'=>66780,'n'=>'FRASCO PET AMBAR 500ML',         'v'=>500],
        ],
        // cap/duo_cap: vol estimado 0.6 mL/caps (para seleção do pote)
        'cap'     => [
            ['c'=>10593,'n'=>'POTE 35ML',  'v'=>35,  'nc'=>55],
            ['c'=>10594,'n'=>'POTE 60ML',  'v'=>60,  'nc'=>90],
            ['c'=>63601,'n'=>'POTE 110ML', 'v'=>110, 'nc'=>150],
            ['c'=>10592,'n'=>'POTE 160ML', 'v'=>160, 'nc'=>220],
            ['c'=>67848,'n'=>'POTE 250ML', 'v'=>250, 'nc'=>350],
            ['c'=>63244,'n'=>'POTE 320ML', 'v'=>320, 'nc'=>450],
            ['c'=>10596,'n'=>'POTE 500ML', 'v'=>500, 'nc'=>700],
            ['c'=>12856,'n'=>'POTE 750ML', 'v'=>750, 'nc'=>999],
        ],
    ];

    $tipo = strtolower( $forma_tipo ?? '' );
    if ( $tipo === 'duo_cap' ) $tipo = 'cap';
    if ( ! isset( $table[$tipo] ) ) return null;

    $opts     = $table[$tipo];
    $selected = null;
    $vol      = (float)( $forma_vol ?? 0 );

    if ( $tipo === 'cap' ) {
        foreach ( $opts as $o ) {
            if ( ( $o['nc'] ?? 0 ) >= $vol ) { $selected = $o; break; }
        }
    } else {
        foreach ( $opts as $o ) {
            if ( $o['v'] >= $vol ) { $selected = $o; break; }
        }
    }
    if ( ! $selected ) $selected = end( $opts );
    if ( ! $selected ) return null;

    // Busca preço no banco pelo codigo_fc
    $cod = (string) $selected['c'];
    $ra  = tao_formula_api(
        "/ativos?cliente_id=eq.{$cliente_id}&codigo_fc=eq.{$cod}&select=id,preco_venda,custo_por_unidade&limit=1"
    );
    $ativo_id    = '';
    $preco_venda = 0.0;
    $preco_custo = 0.0;
    if ( $ra['ok'] && ! empty( $ra['data'] ) ) {
        $ativo_id    = $ra['data'][0]['id']                 ?? '';
        $preco_venda = (float)( $ra['data'][0]['preco_venda']       ?? 0 );
        $preco_custo = (float)( $ra['data'][0]['custo_por_unidade'] ?? 0 );
    }

    return [
        'tipo'              => 'emb',
        'ativo_id'          => $ativo_id,
        'nome'              => $selected['n'],
        'quantidade'        => 1,
        'custo_por_unidade' => $preco_custo ?: $preco_venda,
        'subtotal'          => round( $preco_venda, 2 ),
    ];
}

/**
 * Parseia descrição de fórmula e busca cada ativo no banco por nome.
 * Descrição esperada: "FORMULA MANIPULADA - FORMA: VOLunit | ATIVO1 DOSE UNIT; ATIVO2 DOSE UNIT"
 * Retorna [ forma_vol, forma_unidade, itens[] ]
 */
function tao_formula_parse_descricao_itens( $descr, $cliente_id ) {
    $forma_vol     = null;
    $forma_unidade = 'g';
    $itens         = [];

    $pipe = strpos( $descr, ' | ' );
    if ( $pipe === false ) return [ $forma_vol, $forma_unidade, $itens ];

    $header = substr( $descr, 0, $pipe );
    $resto  = substr( $descr, $pipe + 3 );

    // Parse vol/unidade do cabeçalho: "...FORMA: 180CAP" ou "...FORMA: 30G"
    if ( preg_match( '/:\s*([\d.,]+)\s*(caps?|cap|g|ml|mcg)\b/i', $header, $hm ) ) {
        $forma_vol = (float) str_replace( ',', '.', $hm[1] );
        $u = strtolower( $hm[2] );
        $forma_unidade = in_array( $u, ['cap','caps'] ) ? 'caps' : ( $u === 'ml' ? 'ml' : 'g' );
    }

    // Ingredientes separados por "; "
    $parts = array_filter( array_map( 'trim', explode( ';', $resto ) ) );
    foreach ( $parts as $part ) {
        // @ marca ativos COM SALDO no FCerta — apenas remove o marcador (NÃO é QSP)
        $part = trim( str_replace( '@', '', $part ) );
        // QSP só quando o texto indica explicitamente (ex.: "QSP", "EXCIPIENTE")
        $is_qsp = (bool) preg_match( '/\b(QSP|EXCIPIENTE)\b/i', $part );

        $nome      = trim( $part );
        $dose      = null;
        $dose_unit = 'mg';
        $unit_explicita = false;

        // Números do FCerta são pt-BR: vírgula = decimal; ponto seguido de 3 dígitos = milhar ("50.000" UI = 50000)
        $num_br = function ( $s ) {
            $s = trim( (string) $s );
            if ( strpos( $s, ',' ) !== false ) return (float) str_replace( ',', '.', str_replace( '.', '', $s ) );
            if ( preg_match( '/^\d{1,3}(\.\d{3})+$/', $s ) ) return (float) str_replace( '.', '', $s );
            return (float) $s;
        };

        // Tenta extrair "NOME DOSE UNIT"
        if ( preg_match( '/^(.+?)\s+([\d.,]+)\s*(mg|mcg|g|UI|UFC|BLH|ml|%)\s*$/i', $part, $im ) ) {
            $nome      = trim( $im[1] );
            $dose      = $num_br( $im[2] );
            $raw_unit  = $im[3];
            $dose_unit = in_array( strtolower($raw_unit), ['ui','ufc','blh'] )
                         ? strtoupper($raw_unit) : strtolower($raw_unit);
            $unit_explicita = true;
        } elseif ( preg_match( '/^(.+?)\s+([\d.,]+)\s*$/', $part, $im ) ) {
            // Dose SEM unidade no texto — a unidade vem do cadastro do ativo (resolvida abaixo)
            $nome = trim( $im[1] );
            $dose = $num_br( $im[2] );
        }
        if ( ! $nome ) continue;

        // Busca ativo pelo nome — ORDEM IMPORTA: exato → sinônimo exato → prefixo → contém.
        // "NAC" caía em *NAC* e casava com aceclofeNACo; substring agora é o ÚLTIMO recurso
        // e só para nomes com 5+ caracteres (sigla curta não faz substring).
        $nome_enc = rawurlencode( $nome );
        $sel_ativo = 'id,nome,codigo_fc,preco_venda,custo_por_unidade,unidade_padrao,fator_perda,diluicao,teor,densidade,concentracao';

        // 1) nome EXATO (case-insensitive)
        $ra = tao_formula_api( "/ativos?cliente_id=eq.{$cliente_id}&nome=ilike.{$nome_enc}&select={$sel_ativo}&limit=1" );

        // 2) sinônimo EXATO cadastrado
        if ( ! ( $ra['ok'] && ! empty( $ra['data'] ) ) ) {
            $rs = tao_formula_api(
                "/ativos_sinonimos?cliente_id=eq.{$cliente_id}&sinonimo=ilike.{$nome_enc}&ativo_id=not.is.null&select=ativo_id&limit=1"
            );
            if ( $rs['ok'] && ! empty( $rs['data'] ) ) {
                $aid = $rs['data'][0]['ativo_id'];
                $ra  = tao_formula_api( "/ativos?id=eq.{$aid}&cliente_id=eq.{$cliente_id}&select={$sel_ativo}&limit=1" );
            }
        }

        // 3) nome por PREFIXO
        if ( ! ( $ra['ok'] && ! empty( $ra['data'] ) ) ) {
            $ra = tao_formula_api( "/ativos?cliente_id=eq.{$cliente_id}&nome=ilike.{$nome_enc}*&select={$sel_ativo}&order=nome.asc&limit=1" );
        }

        // 4) nome CONTÉM — só p/ termos com 5+ caracteres
        if ( ! ( $ra['ok'] && ! empty( $ra['data'] ) ) && mb_strlen( $nome ) >= 5 ) {
            $ra = tao_formula_api( "/ativos?cliente_id=eq.{$cliente_id}&nome=ilike.*{$nome_enc}*&select={$sel_ativo}&order=nome.asc&limit=1" );
        }

        $ativo_id    = '';
        $codigo_fc   = '';
        $preco_venda = 0.0;
        $preco_custo = 0.0;
        $unid_padrao = $dose_unit ?: 'mg';
        $fp          = 1.0;
        $diluicao_at = 1.0;
        $teor_at     = 100.0;
        $densidade_at = 1.0;
        $conc_at      = 0.0;

        $nome_prescricao = strtoupper( $nome ); // sempre preserva o nome da prescrição
        $nome_db         = $nome_prescricao;   // fallback = nome prescrição se não achar no banco

        if ( $ra['ok'] && ! empty( $ra['data'] ) ) {
            $at          = $ra['data'][0];
            $ativo_id    = $at['id']                   ?? '';
            $nome_db     = strtoupper( $at['nome']             ?? $nome );
            $codigo_fc   = (string)( $at['codigo_fc']          ?? '' );
            $preco_venda = (float)(  $at['preco_venda']        ?? 0 );
            $preco_custo = (float)(  $at['custo_por_unidade']  ?? 0 );
            $unid_padrao = $at['unidade_padrao']               ?? $unid_padrao;
            $fp          = (float)(  $at['fator_perda']        ?? 1 );
            $diluicao_at = (float)(  $at['diluicao']           ?? 1 );
            $teor_at     = (float)(  $at['teor']               ?? 100 );
            $densidade_at = (float)( $at['densidade']          ?? 1 ) ?: 1.0;
            $conc_at      = (float)( $at['concentracao']       ?? 0 );

            // Produto de unidade ESPECIAL (UI/UFC/BLH — ex.: Vitamina D): a dose é nessa unidade,
            // salvo se o texto disse explicitamente outra unidade de massa (g/mcg/ml/%).
            // Corrige o caso "VIT D em mg" (dose sem unidade ou mg default → UI do cadastro).
            $upad_up = strtoupper( (string) $unid_padrao );
            if ( $dose !== null && in_array( $upad_up, [ 'UI', 'UFC', 'BLH' ], true )
                 && ( ! $unit_explicita || $dose_unit === 'mg' ) ) {
                $dose_unit = $upad_up;
            }
        }

        $itens[] = [
            'tipo'              => 'mp',
            'ativo_id'          => $ativo_id,
            'nome'              => $nome_db,          // nome canônico do banco (para cálculos)
            'nome_prescricao'   => $nome_prescricao,  // nome original da prescrição (para mensagens)
            'codigo_fc'         => $codigo_fc,
            'is_qsp'            => $is_qsp,
            'dose'              => $dose,
            'dose_unit'         => $dose_unit,
            'multiplicador'     => $forma_vol ?? 1,
            'qtde_potes'        => 1,
            'n_caps_por_dose'   => 1,
            'capsula_tipo'      => null,
            'capsula_numero'    => null,
            'diluicao'          => $diluicao_at,
            'teor'              => $teor_at,
            'fp'                => $fp,
            'densidade'         => $densidade_at,
            'concentracao'      => $conc_at,
            'qtd_total_g'       => 0.0,
            'volapa_ul'         => 0.0,
            'custo_por_unidade' => $preco_custo,
            'preco_venda'       => $preco_venda,
            'unid_padrao'       => $unid_padrao,
            'subtotal'          => 0.0,
        ];
    }

    return [ $forma_vol, $forma_unidade, $itens ];
}

/**
 * Cápsula ideal + custo da cápsula + custo do excipiente (QSP) para import.
 * Port fiel do motor JS (formula-orc.js): VOLAPA por dose → cápsula gelatinosa ideal → custos.
 * Modifica $itens_mp por referência (volapa_ul, subtotal do excipiente, campos de cápsula).
 */
function tao_formula_calc_capsula_import( array &$itens_mp, $forma, $forma_vol, $qtde_potes, $cliente_id ) {
    $out    = [ 'custo_capsula' => 0.0, 'excipiente_subtotal' => 0.0, 'capsula' => null, 'n_per_dose' => 1 ];
    $ftench = (float) ( $forma['ftenchcap'] ?? 1 ) ?: 1.0;
    $forma_vol  = max( 1.0, (float) $forma_vol );
    $qtde_potes = max( 1, (int) $qtde_potes );

    // 1) VOLAPA por dose de cada ativo (não-QSP) — mesma fórmula do JS
    $sum_volapa = 0.0;
    foreach ( $itens_mp as &$item ) {
        if ( ( $item['tipo'] ?? 'mp' ) !== 'mp' || ! empty( $item['is_qsp'] ) ) continue;
        $dose = (float) ( $item['dose'] ?? 0 );
        $unit = $item['dose_unit'] ?? 'mg';
        $dens = (float) ( $item['densidade'] ?? 1 ) ?: 1.0;
        $dil  = (float) ( $item['diluicao'] ?? 1 );
        $teor = (float) ( $item['teor'] ?? 100 );
        $conc = (float) ( $item['concentracao'] ?? 0 );
        $volapa = 0.0;

        if ( $unit === '%' ) {
            $volapa = 0.0;
        } elseif ( in_array( $unit, [ 'UI', 'UFC', 'BLH' ], true ) ) {
            if ( $dens > 0 && $dose > 0 ) {
                $dose_ufc     = ( $unit === 'BLH' ) ? $dose * 1e9 : $dose;
                $conc_efetiva = $conc > 0 ? $conc : 10e9;
                $volapa       = ( $dose_ufc / $conc_efetiva ) * 1000 / $dens;
            }
        } else {
            switch ( strtolower( $unit ) ) {
                case 'g':   $dose_mg = $dose * 1000; break;
                case 'mcg': $dose_mg = $dose / 1000; break;
                case 'ml':  $dose_mg = $dose * $dens * 1000; break;
                default:    $dose_mg = $dose; break;
            }
            $dose_mg_real = $dose_mg * $dil / max( 0.001, $teor / 100 );
            $volapa       = $dens > 0 ? $dose_mg_real / $dens : 0.0;
        }
        $item['volapa_ul'] = round( $volapa, 6 );
        $sum_volapa       += $volapa;
    }
    unset( $item );
    if ( $sum_volapa <= 0 ) return $out;

    // 2) Cápsulas gelatinosas + preço (cdpro_fc → ativo preco_venda)
    $rc = tao_formula_api(
        "/tipos_capsula?cliente_id=eq.{$cliente_id}&ativo=eq.true&tipo=ilike.gelatinosa&select=numero,vol_ul,cdpro_fc&order=vol_ul.asc"
    );
    $caps_raw = $rc['ok'] ? ( $rc['data'] ?? [] ) : [];
    if ( empty( $caps_raw ) ) return $out;

    $cdpros = array_filter( array_map( fn($c) => (string) ( $c['cdpro_fc'] ?? '' ), $caps_raw ) );
    $preco_cap = [];
    if ( $cdpros ) {
        $in = implode( ',', array_unique( $cdpros ) );
        $rp = tao_formula_api( "/ativos?cliente_id=eq.{$cliente_id}&codigo_fc=in.($in)&select=codigo_fc,preco_venda" );
        foreach ( ( $rp['ok'] ? ( $rp['data'] ?? [] ) : [] ) as $a ) {
            $preco_cap[ (string) $a['codigo_fc'] ] = (float) ( $a['preco_venda'] ?? 0 );
        }
    }
    $caps = [];
    foreach ( $caps_raw as $c ) {
        $vol = (float) ( $c['vol_ul'] ?? 0 );
        if ( $vol <= 0 ) continue;
        $caps[] = [ 'numero' => $c['numero'] ?? '', 'vol_ul' => $vol, 'venda_unit' => $preco_cap[ (string) ( $c['cdpro_fc'] ?? '' ) ] ?? 0.0 ];
    }
    if ( empty( $caps ) ) return $out;
    usort( $caps, fn($a, $b) => $a['vol_ul'] <=> $b['vol_ul'] );

    // 3) Cápsula ideal: menor n (1..6) e menor cápsula que cabe
    $sel = null; $n_per = 1;
    for ( $n = 1; $n <= 6 && ! $sel; $n++ ) {
        foreach ( $caps as $c ) {
            if ( $c['vol_ul'] * $n * $ftench >= $sum_volapa ) { $sel = $c; $n_per = $n; break; }
        }
    }
    if ( ! $sel ) {
        $sel   = end( $caps );
        $n_per = max( 1, (int) ceil( $sum_volapa / ( $sel['vol_ul'] * $ftench ) ) );
    }

    // 4) Custo da cápsula
    $total_caps    = $forma_vol * $qtde_potes * $n_per;
    $custo_capsula = $sel['venda_unit'] > 0 ? round( $sel['venda_unit'] * $total_caps, 2 ) : 0.0;

    // 5) Excipiente (QSP): completa o volume disponível
    $avail_per_dose = $sel['vol_ul'] * $n_per * $ftench;
    $qsp_volapa     = max( 0.0, $avail_per_dose - $sum_volapa );
    $excip_subtotal = 0.0;
    foreach ( $itens_mp as &$item ) {
        if ( ( $item['tipo'] ?? 'mp' ) !== 'mp' || empty( $item['is_qsp'] ) ) continue;
        $qsp_dens = (float) ( $item['densidade'] ?? 1 ) ?: 1.0;
        $qsp_mg   = $qsp_volapa * $qsp_dens * $forma_vol * $qtde_potes;
        $qsp_g    = $qsp_mg / 1000;
        $unid     = strtolower( $item['unid_padrao'] ?? 'g' );
        $qtd_em_u = $unid === 'g' ? $qsp_g : $qsp_mg;
        $excip_subtotal = round( $qtd_em_u * (float) ( $item['preco_venda'] ?? 0 ), 4 );
        $item['qtd_total_g']     = $qsp_g;
        $item['volapa_ul']       = round( $qsp_volapa, 6 );
        $item['subtotal']        = $excip_subtotal;
        break;
    }
    unset( $item );

    // Anota a cápsula escolhida em todos os ativos (informativo p/ exibição)
    foreach ( $itens_mp as &$item ) {
        if ( ( $item['tipo'] ?? 'mp' ) === 'mp' ) {
            $item['capsula_tipo']    = 'gelatinosa';
            $item['capsula_numero']  = $sel['numero'];
            $item['n_caps_por_dose'] = $n_per;
        }
    }
    unset( $item );

    $out['custo_capsula']       = $custo_capsula;
    $out['excipiente_subtotal'] = $excip_subtotal;
    $out['capsula']             = $sel;
    $out['n_per_dose']          = $n_per;
    return $out;
}

add_action( 'wp_ajax_tao_formula_importar_orc_texto', function() {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( 'Acesso negado', 403 );

    $cliente_id = tao_formula_cliente_id();
    if ( ! $cliente_id ) wp_send_json_error( 'Cliente não identificado', 400 );

    $card_id   = sanitize_text_field( $_POST['card_id'] ?? '' ) ?: null;
    $orcs_json = wp_unslash( $_POST['orcs'] ?? '' );
    $orcs      = json_decode( $orcs_json, true );
    if ( empty( $orcs ) || ! is_array( $orcs ) ) wp_send_json_error( 'Nenhum orçamento para importar' );

    // Dados do card (nome do paciente e WhatsApp) — busca única para o lote
    $nome_paciente = '';
    $whatsapp_pac  = '';
    if ( $card_id ) {
        $rc = tao_formula_api( "/crm_cards?id=eq.{$card_id}&select=contato_nome,contato_whatsapp&limit=1" );
        if ( $rc['ok'] && ! empty( $rc['data'] ) ) {
            $nome_paciente = $rc['data'][0]['contato_nome']      ?? '';
            $whatsapp_pac  = $rc['data'][0]['contato_whatsapp']  ?? '';
        }
    }

    $criados = 0;
    $erros   = [];

    foreach ( $orcs as $orc ) {
        $numero = sanitize_text_field( $orc['numero'] ?? '' );
        $descr  = sanitize_text_field( $orc['descricao'] ?? '' );
        $valor  = (float) ( $orc['valor'] ?? 0 );                       // valor FINAL (com desconto)
        $valor_bruto = (float) ( $orc['valor_bruto'] ?? $valor );       // valor antes do desconto
        if ( $valor_bruto < $valor ) $valor_bruto = $valor;
        if ( ! $numero || $valor <= 0 ) { $erros[] = "Linha inválida: $numero"; continue; }

        // Verifica duplicata
        $check = tao_formula_api( "/orcamentos?numero_orcamento=eq.{$numero}&cliente_id=eq.{$cliente_id}&select=id&limit=1" );
        if ( $check['ok'] && ! empty( $check['data'] ) ) { $erros[] = "ORC:{$numero} já existe"; continue; }

        // ── 1. Parseia descrição: forma_nome, forma_vol, forma_unidade, itens MP ────
        $forma_nome_raw = '';
        if ( preg_match( '/FORMULA MANIPULADA\s*-\s*([^\s:]+)/i', $descr, $m ) ) {
            $forma_nome_raw = trim( $m[1] );
        }

        list( $forma_vol, $forma_unidade, $itens_mp ) = tao_formula_parse_descricao_itens( $descr, $cliente_id );
        $qtde_potes = 1;

        // ── 2. Busca forma farmacêutica por nome ─────────────────────────────────────
        $forma    = null;
        $forma_id = null;
        if ( $forma_nome_raw ) {
            $enc_f = rawurlencode( $forma_nome_raw );
            $rf = tao_formula_api(
                "/formas_farmaceuticas?cliente_id=eq.{$cliente_id}&nome=ilike.*{$enc_f}*&ativo=eq.true" .
                "&select=id,nome,tipo,custo_fixo,custo_fixo_tipo,margem_pct,valor_minimo,n_capsulas,volume,unidade_volume,ftenchcap&limit=1"
            );
            if ( $rf['ok'] && ! empty( $rf['data'] ) ) {
                $forma    = $rf['data'][0];
                $forma_id = $forma['id'];
            }
        }

        // Fallback forma_vol/unidade da forma cadastrada
        if ( $forma_vol === null && $forma ) {
            $ft = strtolower( $forma['tipo'] ?? 'outro' );
            if ( in_array( $ft, ['cap','duo_cap'] ) ) {
                $forma_vol     = (float)( $forma['n_capsulas'] ?? 30 );
                $forma_unidade = 'caps';
            } else {
                $forma_vol     = (float)( $forma['volume'] ?? 0 ) ?: null;
                $forma_unidade = $forma['unidade_volume'] ?? 'g';
            }
        }

        $forma_nome_final = $forma ? $forma['nome'] : ucfirst( strtolower( $forma_nome_raw ) );
        $mult = ( $forma_vol ?? 1 ) * $qtde_potes;

        // ── 2b. Regras de cápsulas ────────────────────────────────────────────────────
        $forma_tipo = strtolower( $forma['tipo'] ?? '' );
        if ( $forma && in_array( $forma_tipo, ['cap', 'duo_cap'] ) ) {
            // Padrão: cápsulas gelatinosas quando não especificado
            foreach ( $itens_mp as &$item ) {
                if ( $item['tipo'] === 'mp' && empty( $item['capsula_tipo'] ) ) {
                    $item['capsula_tipo'] = 'gelatinosa';
                }
            }
            unset( $item );

            // Excipiente base (QSP): só adiciona se o texto NÃO descreveu um QSP.
            // NÃO marca o último ativo como QSP (ele é um princípio ativo de verdade).
            $has_qsp = false;
            foreach ( $itens_mp as $item ) {
                if ( ( $item['tipo'] ?? 'mp' ) === 'mp' && ! empty( $item['is_qsp'] ) ) { $has_qsp = true; break; }
            }
            if ( ! $has_qsp ) {
                $rexc = tao_formula_api(
                    "/ativos?cliente_id=eq.{$cliente_id}&codigo_fc=eq.10577" .
                    "&select=id,nome,codigo_fc,preco_venda,custo_por_unidade,unidade_padrao,fator_perda,diluicao,teor,densidade&limit=1"
                );
                $exc = ( $rexc['ok'] && ! empty( $rexc['data'] ) ) ? $rexc['data'][0] : null;
                $itens_mp[] = [
                    'tipo'              => 'mp',
                    'ativo_id'          => $exc['id'] ?? '',
                    'nome'              => strtoupper( $exc['nome'] ?? 'EXCIPIENTE BASE' ),
                    'nome_prescricao'   => 'EXCIPIENTE BASE',
                    'codigo_fc'         => '10577',
                    'is_qsp'            => true,
                    'dose'              => null,
                    'dose_unit'         => 'mg',
                    'multiplicador'     => $forma_vol ?? 1,
                    'qtde_potes'        => $qtde_potes,
                    'n_caps_por_dose'   => 1,
                    'capsula_tipo'      => 'gelatinosa',
                    'capsula_numero'    => null,
                    'diluicao'          => (float) ( $exc['diluicao']    ?? 1 ),
                    'teor'              => (float) ( $exc['teor']        ?? 100 ),
                    'fp'                => (float) ( $exc['fator_perda'] ?? 1 ),
                    'densidade'         => (float) ( $exc['densidade']   ?? 1 ) ?: 1.0,
                    'concentracao'      => 0.0,
                    'qtd_total_g'       => 0.0,
                    'volapa_ul'         => 0.0,
                    'custo_por_unidade' => (float) ( $exc['custo_por_unidade'] ?? 0 ),
                    'preco_venda'       => (float) ( $exc['preco_venda']       ?? 0 ),
                    'unid_padrao'       => $exc['unidade_padrao'] ?? 'g',
                    'subtotal'          => 0.0,
                ];
            }

            // Cápsula ideal + custo da cápsula + custo do excipiente (QSP) — port do motor JS
            $cap_calc       = tao_formula_calc_capsula_import( $itens_mp, $forma, $forma_vol, $qtde_potes, $cliente_id );
            $custo_capsula  = $cap_calc['custo_capsula'];
            $excip_subtotal = $cap_calc['excipiente_subtotal'];
        } elseif ( $forma && ! in_array( $forma_tipo, [ 'cap', 'duo_cap', 'envelope' ] ) ) {
            // Formas líquidas/semissólidas (creme, gel, loção, solução, xarope…):
            // o ÚLTIMO ingrediente é o QSP (veículo/base). Garante 1 único QSP = o último.
            $last_idx = null;
            foreach ( $itens_mp as $idx => $it ) {
                if ( ( $it['tipo'] ?? 'mp' ) === 'mp' ) $last_idx = $idx;
            }
            if ( $last_idx !== null ) {
                foreach ( $itens_mp as $idx => &$it ) {
                    if ( ( $it['tipo'] ?? 'mp' ) === 'mp' ) $it['is_qsp'] = ( $idx === $last_idx );
                }
                unset( $it );
            }
        }

        // ── 3. Calcula subtotal de cada MP (replica JS calcularLinha, caso mg/g/mcg) ─
        $custo_capsula  = $custo_capsula  ?? 0.0;
        $excip_subtotal = $excip_subtotal ?? 0.0;
        $motor_on_calc  = get_option( 'tao_formula_motor_v2' ) === '1';
        $total_insumos = 0.0;
        foreach ( $itens_mp as &$item ) {
            if ( $item['tipo'] !== 'mp' || $item['is_qsp'] ) continue;
            $dose      = (float)( $item['dose'] ?? 0 );
            $dose_unit = strtolower( $item['dose_unit'] ?? 'mg' );
            $unid_pad  = strtolower( $item['unid_padrao'] ?? 'mg' );
            $fp        = (float)( $item['fp']       ?? 1 );
            $diluicao  = (float)( $item['diluicao'] ?? 1 );
            $teor      = (float)( $item['teor']     ?? 100 );
            $equiv     = $motor_on_calc ? ( (float)( $item['equiv'] ?? 1 ) ?: 1.0 ) : 1.0;
            $preco     = (float)( $item['preco_venda'] ?? 0 );

            $unit_up = strtoupper( (string) ( $item['dose_unit'] ?? '' ) );
            if ( $dose > 0 && in_array( $unit_up, [ 'UI', 'UFC', 'BLH' ], true ) ) {
                // Especiais (ex.: Vitamina D em UI): massa = dose ÷ concentração (UI|UFC por g) — port do isSpecial do JS
                $conc         = (float) ( $item['concentracao'] ?? 0 );
                $dose_ufc     = $unit_up === 'BLH' ? $dose * 1e9 : $dose;
                $qtd_total_g  = $conc > 0 ? ( $dose_ufc / $conc ) * $mult : 0.0;
                $qtd_total_mg = $qtd_total_g * 1000;
                if     ( $unid_pad === 'g' )                       $qtd_em_padrao = $qtd_total_g;
                elseif ( $unid_pad === 'mg' )                      $qtd_em_padrao = $qtd_total_mg;
                elseif ( strtoupper( $unid_pad ) === $unit_up )    $qtd_em_padrao = $dose * $mult;
                else                                               $qtd_em_padrao = $qtd_total_g;
                $subtotal = round( $qtd_em_padrao * $preco, 4 );
            } elseif ( $dose > 0 && $preco > 0 ) {
                switch ( $dose_unit ) {
                    case 'g':   $dose_mg = $dose * 1000; break;
                    case 'mcg': $dose_mg = $dose / 1000; break;
                    default:    $dose_mg = $dose; break; // mg (e outros)
                }
                $dose_mg_real  = $dose_mg * $equiv * $diluicao / max( 0.001, $teor / 100 );
                $qtd_total_mg  = $dose_mg_real * $fp * $mult;
                $qtd_total_g   = $qtd_total_mg / 1000;
                $qtd_em_padrao = $unid_pad === 'g' ? $qtd_total_g : $qtd_total_mg;
                $subtotal      = round( $qtd_em_padrao * $preco, 4 );
            } else {
                $qtd_total_g   = 0.0;
                $subtotal      = 0.0;
            }
            $item['qtd_total_g']   = $qtd_total_g;
            $item['multiplicador'] = $mult;
            $item['subtotal']      = $subtotal;
            $total_insumos        += $subtotal;
        }
        unset( $item );

        // Excipiente (QSP) entra como insumo
        $total_insumos += $excip_subtotal;

        // ── 4. Sugere embalagem ───────────────────────────────────────────────────────
        $itens_emb = [];
        $total_emb = 0.0;
        if ( $forma ) {
            $emb = tao_formula_sugerir_embalagem_import( $forma['tipo'], $forma_vol, $cliente_id );
            if ( $emb ) {
                $itens_emb[] = $emb;
                $total_emb   = $emb['subtotal'];
            }
        }
        // Valor Calculado = insumos + embalagem (SEM cápsula — cápsula é linha própria)
        $calculado = $total_insumos + $total_emb;

        // ── 5. Custo fixo da forma (base = calculado + cápsulas, igual ao motor JS) ───
        $base_fixo  = $calculado + $custo_capsula;
        $custo_fixo = 0.0;
        if ( $forma ) {
            $cf_tipo = $forma['custo_fixo_tipo'] ?? '';
            $cf_val  = (float)( $forma['custo_fixo'] ?? 0 );
            if ( $cf_tipo === 'pct' ) {
                $custo_fixo = round( $base_fixo * $cf_val / 100, 2 );
            } else {
                // 'R' ou sem regra → usa o valor fixo cadastrado na forma (0 se não houver).
                // A margem NÃO vira custo fixo no import — o markup fica no Acréscimo.
                $custo_fixo = $cf_val;
            }
        }

        // Sub-Total = Valor Calculado + Cápsulas + Custo Fixo
        $subtotal_calc = round( $calculado + $custo_capsula + $custo_fixo, 2 );

        // Valor mínimo da forma
        if ( $forma ) {
            $val_min = (float)( $forma['valor_minimo'] ?? 0 );
            if ( $val_min > 0 && $subtotal_calc < $val_min ) $subtotal_calc = $val_min;
        }

        // ── 6. Opção 2: Desconto = informado; Acréscimo = FINAL − Desconto − Sub-Total ──
        // VALOR FINAL (total_orcamento) = $valor (com desconto), exibido direto do orçamento.
        $desconto_val  = max( 0.0, round( $valor_bruto - $valor, 2 ) );          // desconto informado (bruto − final)
        // Acréscimo ancorado no SEM desconto (bruto): Sub-Total + Acréscimo = valor_bruto.
        // O editor calcula Final = (Sub-Total + Acréscimo) − Desconto — se ancorasse no
        // valor FINAL, o desconto seria aplicado EM DOBRO. Regra: valor manda, % é derivado.
        $acrescimo_val = round( $valor_bruto - $subtotal_calc, 2 );              // Acréscimo(R$) = SEM desconto − Sub-Total
        $sem_desconto  = $subtotal_calc + $acrescimo_val;                        // = valor_bruto
        $acrescimo_pct = $subtotal_calc > 0.005 ? round( $acrescimo_val / $subtotal_calc * 100, 2 ) : 0.0;
        $desconto_pct  = $sem_desconto   > 0.005 ? round( $desconto_val  / $sem_desconto   * 100, 2 ) : 0.0;
        // Evita "numeric field overflow": colunas de % têm precisão limitada.
        // No orçamento importado o acréscimo é re-derivado no editor (FINAL travado), então capar é seguro.
        $acrescimo_pct = max( -999.99, min( 999.99, $acrescimo_pct ) );
        $desconto_pct  = max( 0.0,     min( 99.99,  $desconto_pct ) );
        if ( $subtotal_calc <= 0.005 ) { $custo_fixo = $valor; $calculado = 0.0; }

        $observacoes = "[FC:{$numero}] {$descr}";
        $itens_todos = array_merge( $itens_mp, $itens_emb );

        $r = tao_formula_api( '/orcamentos', 'POST', [
            'cliente_id'          => $cliente_id,
            'card_id'             => $card_id,
            'numero_orcamento'    => $numero,
            'nome_paciente'       => $nome_paciente,
            'whatsapp'            => $whatsapp_pac,
            'forma_id'            => $forma_id,
            'forma_nome'          => $forma_nome_final ?: $descr,
            'forma_vol'           => $forma_vol,
            'forma_unidade'       => $forma_unidade,
            'qtde_potes'          => $qtde_potes,
            'custo_fixo_aplicado' => round( $custo_fixo, 2 ),
            'total_insumos'       => round( $calculado, 2 ),
            'acrescimo_aplicado'  => $acrescimo_val,  // Acréscimo(R$) — fonte da verdade
            'margem_aplicada'     => $acrescimo_pct,  // % derivado (exibição)
            'desconto_pct'        => $desconto_pct,
            'total_orcamento'     => $valor,
            'valor_final_fc'      => $valor,          // FINAL do FC (com desconto) — travado/durável
            'desconto_fc'         => $desconto_val,   // desconto informado do FC (R$) — fixo
            'observacoes'         => $observacoes,
            'itens'               => $itens_todos,
            'status'              => 'pendente_revisao',
            'tipo_entrada'        => 'texto',
            'criado_em'           => gmdate( 'c' ),
            'atualizado_em'       => gmdate( 'c' ),
        ] );

        if ( $r['ok'] ) {
            $criados++;
            if ( $card_id ) {
                $n_mp  = count( $itens_mp );
                $n_emb = count( $itens_emb );
                tao_formula_api( '/crm_cards_historico', 'POST', [
                    'card_id'    => $card_id,
                    'usuario_id' => get_current_user_id(),
                    'motivo'     => 'Orçamento importado: ORC:' . $numero .
                                   ' — R$ ' . number_format( $valor, 2, ',', '.' ) .
                                   " | {$n_mp} MP" . ( $n_emb ? " + {$n_emb} emb." : '' ) .
                                   ( $forma_id ? " | forma: {$forma_nome_final}" : ' | forma não encontrada' ),
                    'criado_em'  => gmdate( 'c' ),
                ] );
            }
        } else {
            $raw_decoded = json_decode( $r['raw'] ?? '', true );
            $detalhe = is_array($raw_decoded)
                ? ( $raw_decoded['message'] ?? $raw_decoded['code'] ?? $r['raw'] )
                : $r['raw'];
            $erros[] = "ORC:{$numero}: " . mb_substr( (string) $detalhe, 0, 300 );
        }
    }

    if ( $card_id && function_exists( 'tao_crm_sync_valor_oportunidade' ) ) tao_crm_sync_valor_oportunidade( $card_id );
    wp_send_json_success( [ 'criados' => $criados, 'erros' => $erros ] );
} );

// ═══════════════════════════════════════════════════════════════════════════
// HISTÓRICO FCerta — consulta de fórmulas por cliente + repetição
// Tabelas: hist_clientes / hist_formulas / hist_formulas_itens
// ═══════════════════════════════════════════════════════════════════════════

// ── Busca de cliente por nome ─────────────────────────────────────────────────

add_action( 'wp_ajax_tao_formula_hist_busca', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( 'Acesso negado', 403 );
    $cliente_id = tao_formula_cliente_id();
    $q          = sanitize_text_field( $_GET['q'] ?? '' );
    if ( ! $cliente_id || mb_strlen( $q ) < 3 ) { wp_send_json_success( [] ); return; }
    $enc = rawurlencode( $q );

    $rc = tao_formula_api(
        "/hist_clientes?cliente_id=eq.$cliente_id&nome=ilike.*{$enc}*" .
        "&select=id,cdcli,nome,contato_id&order=nome.asc&limit=12"
    );
    if ( ! $rc['ok'] ) {
        $msg = strpos( (string) $rc['raw'], 'does not exist' ) !== false
            ? 'Tabelas do histórico ainda não criadas (migration_historico_v1.sql pendente).'
            : 'Erro na busca: ' . mb_substr( (string) $rc['raw'], 0, 200 );
        wp_send_json_error( [ 'message' => $msg ] );
    }
    $clientes = $rc['data'] ?? [];
    $out      = [];

    if ( $clientes ) {
        // Total de fórmulas + última data por cliente (uma query só)
        $ids = implode( ',', array_column( $clientes, 'id' ) );
        $rf  = tao_formula_api(
            "/hist_formulas?cliente_id=eq.$cliente_id&hist_cliente_id=in.($ids)" .
            "&select=hist_cliente_id,dt_cadastro&order=dt_cadastro.desc&limit=5000"
        );
        $agg = [];
        foreach ( ( $rf['ok'] ? $rf['data'] : [] ) as $f ) {
            $h = $f['hist_cliente_id'];
            if ( ! isset( $agg[ $h ] ) ) $agg[ $h ] = [ 'n' => 0, 'ult' => $f['dt_cadastro'] ];
            $agg[ $h ]['n']++;
        }
        foreach ( $clientes as $c ) {
            $out[] = [
                'hist_cliente_id' => $c['id'],
                'contato_id'      => $c['contato_id'] ?? null,
                'cdcli'           => $c['cdcli'],
                'nome'            => $c['nome'],
                'total_formulas'  => $agg[ $c['id'] ]['n']   ?? 0,
                'ultima'          => $agg[ $c['id'] ]['ult'] ?? null,
            ];
        }
    }

    // Fórmulas sem cadastro de cliente (CDCLI vazio no FCerta): busca pelo nome do paciente
    $ra = tao_formula_api(
        "/hist_formulas?cliente_id=eq.$cliente_id&hist_cliente_id=is.null&nome_paciente=ilike.*{$enc}*" .
        "&select=nome_paciente,dt_cadastro&order=dt_cadastro.desc&limit=200"
    );
    $avulsos = [];
    foreach ( ( $ra['ok'] ? $ra['data'] : [] ) as $f ) {
        $n = $f['nome_paciente'];
        if ( ! $n ) continue;
        if ( ! isset( $avulsos[ $n ] ) ) $avulsos[ $n ] = [ 'n' => 0, 'ult' => $f['dt_cadastro'] ];
        $avulsos[ $n ]['n']++;
    }
    foreach ( $avulsos as $nome => $a ) {
        $out[] = [
            'hist_cliente_id' => null,
            'cdcli'           => null,
            'nome'            => $nome,
            'total_formulas'  => $a['n'],
            'ultima'          => $a['ult'],
        ];
    }
    wp_send_json_success( $out );
} );

// ── Fórmulas de um cliente ────────────────────────────────────────────────────

add_action( 'wp_ajax_tao_formula_hist_formulas', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( 'Acesso negado', 403 );
    $cliente_id = tao_formula_cliente_id();
    $hist_id    = sanitize_text_field( $_GET['hist_cliente_id'] ?? '' );
    $nome_pac   = sanitize_text_field( $_GET['nome_paciente']   ?? '' );
    if ( ! $cliente_id || ( ! $hist_id && ! $nome_pac ) ) wp_send_json_error( [ 'message' => 'Parâmetros inválidos' ] );

    $filtro = $hist_id
        ? 'hist_cliente_id=eq.' . $hist_id
        : 'hist_cliente_id=is.null&nome_paciente=eq.' . rawurlencode( $nome_pac );
    $r = tao_formula_api(
        "/hist_formulas?cliente_id=eq.$cliente_id&$filtro" .
        "&select=id,nrrqu,serier,dt_cadastro,volume,univol,qt_potes,posologia,preco_cobrado,ind_repet" .
        "&order=dt_cadastro.desc,nrrqu.desc&limit=200"
    );
    if ( ! $r['ok'] ) wp_send_json_error( [ 'message' => mb_substr( (string) $r['raw'], 0, 200 ) ] );
    $formulas = $r['data'] ?? [];

    // Resumo dos ativos (componentes C não-QSP) por fórmula — uma query p/ a lista toda
    if ( $formulas ) {
        $ids = implode( ',', array_column( $formulas, 'id' ) );
        $ri  = tao_formula_api(
            "/hist_formulas_itens?formula_id=in.($ids)&tpcmp=eq.C" .
            "&select=formula_id,descr,dose,unidade,is_qsp&order=ordem.asc&limit=3000"
        );
        $por_form = [];
        foreach ( ( $ri['ok'] ? $ri['data'] : [] ) as $it ) {
            if ( ! empty( $it['is_qsp'] ) ) continue;
            $nome = rtrim( trim( (string) $it['descr'] ), '@' );
            $dose = (float) ( $it['dose'] ?? 0 );
            $lbl  = $nome . ( $dose > 0 ? ' ' . rtrim( rtrim( number_format( $dose, 2, ',', '' ), '0' ), ',' ) . strtolower( (string) $it['unidade'] ) : '' );
            $por_form[ $it['formula_id'] ][] = $lbl;
        }
        foreach ( $formulas as &$f ) {
            $ativos = $por_form[ $f['id'] ] ?? [];
            $extra  = count( $ativos ) - 4;
            $f['resumo'] = implode( ' + ', array_slice( $ativos, 0, 4 ) ) . ( $extra > 0 ? " +{$extra}" : '' );
        }
        unset( $f );
    }
    wp_send_json_success( $formulas );
} );

// ── Itens de uma fórmula ──────────────────────────────────────────────────────

add_action( 'wp_ajax_tao_formula_hist_itens', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( 'Acesso negado', 403 );
    $cliente_id = tao_formula_cliente_id();
    $fid        = sanitize_text_field( $_GET['formula_id'] ?? '' );
    if ( ! $cliente_id || ! $fid ) wp_send_json_error( [ 'message' => 'Parâmetros inválidos' ] );

    // Confirma que a fórmula pertence ao tenant antes de listar
    $rf = tao_formula_api( "/hist_formulas?id=eq.$fid&cliente_id=eq.$cliente_id&select=id&limit=1" );
    if ( ! $rf['ok'] || empty( $rf['data'] ) ) wp_send_json_error( [ 'message' => 'Fórmula não encontrada' ] );

    $r = tao_formula_api(
        "/hist_formulas_itens?formula_id=eq.$fid" .
        "&select=tpcmp,codigo_fc,descr,dose,unidade,qt_real,is_qsp,ordem&order=ordem.asc&limit=100"
    );
    $r['ok'] ? wp_send_json_success( $r['data'] ?? [] )
             : wp_send_json_error( [ 'message' => mb_substr( (string) $r['raw'], 0, 200 ) ] );
} );

// ── Repetir: cria orçamento novo a partir da fórmula histórica ────────────────

add_action( 'wp_ajax_tao_formula_hist_repetir', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( 'Acesso negado', 403 );
    $cliente_id = tao_formula_cliente_id();
    $fid        = sanitize_text_field( $_POST['formula_id'] ?? '' );
    $card_id    = sanitize_text_field( $_POST['card_id'] ?? '' ) ?: null;   // repetir DENTRO de um card
    if ( ! $cliente_id || ! $fid ) wp_send_json_error( [ 'message' => 'Parâmetros inválidos' ] );

    $rf = tao_formula_api( "/hist_formulas?id=eq.$fid&cliente_id=eq.$cliente_id&limit=1" );
    if ( ! $rf['ok'] || empty( $rf['data'] ) ) wp_send_json_error( [ 'message' => 'Fórmula não encontrada' ] );
    $form = $rf['data'][0];

    $ri = tao_formula_api(
        "/hist_formulas_itens?formula_id=eq.$fid&tpcmp=eq.C" .
        "&select=codigo_fc,descr,dose,unidade,is_qsp,ordem&order=ordem.asc&limit=100"
    );
    $hist_itens = ( $ri['ok'] ? $ri['data'] : [] );
    if ( ! $hist_itens ) wp_send_json_error( [ 'message' => 'Fórmula sem componentes' ] );

    // Associa ativos pelo codigo_fc (vínculo do sync) em uma query
    $cods = array_values( array_unique( array_filter( array_column( $hist_itens, 'codigo_fc' ) ) ) );
    $mapa = [];
    if ( $cods ) {
        $in = implode( ',', array_map( 'rawurlencode', $cods ) );
        $ra = tao_formula_api(
            "/ativos?cliente_id=eq.$cliente_id&codigo_fc=in.($in)" .
            "&select=id,codigo_fc,nome,unidade_padrao,preco_venda,custo_por_unidade,fator_perda,diluicao,teor&limit=" . count( $cods )
        );
        foreach ( ( $ra['ok'] ? $ra['data'] : [] ) as $at ) $mapa[ (string) $at['codigo_fc'] ] = $at;
    }

    $vol      = (float) ( $form['volume'] ?? 0 ) ?: 1;
    $potes    = max( 1, (int) ( $form['qt_potes'] ?? 1 ) );
    $mult     = $vol * $potes;
    $unit_map = [ 'MG' => 'mg', 'G' => 'g', 'MCG' => 'mcg', 'ML' => 'ml', '%' => '%',
                  'UI' => 'UI', 'UFC' => 'UFC', 'BLH' => 'BLH' ];

    $itens           = [];
    $nao_encontrados = [];
    foreach ( $hist_itens as $hi ) {
        $at     = $mapa[ (string) ( $hi['codigo_fc'] ?? '' ) ] ?? null;
        if ( ! $at ) $nao_encontrados[] = $hi['descr'];
        $is_qsp    = ! empty( $hi['is_qsp'] );
        $dose      = (float) ( $hi['dose'] ?? 0 );
        $unida     = strtoupper( trim( (string) ( $hi['unidade'] ?? 'MG' ) ) );
        $dose_unit = $unit_map[ $unida ] ?? 'mg';
        $fp        = (float) ( $at['fator_perda'] ?? 1 ) ?: 1;
        $diluicao  = (float) ( $at['diluicao']    ?? 1 ) ?: 1;
        $teor      = (float) ( $at['teor']        ?? 100 ) ?: 100;
        $preco     = (float) ( $at['preco_venda'] ?? 0 );
        $unid_p    = strtolower( $at['unidade_padrao'] ?? 'mg' );

        // Subtotal preliminar (unidades de massa) — o editor recalcula na revisão
        $qtd_tot_g = 0.0;
        $subtotal  = 0.0;
        if ( ! $is_qsp && $dose > 0 && $preco > 0 && in_array( $dose_unit, [ 'mg', 'g', 'mcg' ], true ) ) {
            $dose_mg = $dose_unit === 'g' ? $dose * 1000 : ( $dose_unit === 'mcg' ? $dose / 1000 : $dose );
            $dose_mg_real = $dose_mg * $diluicao / max( 0.001, $teor / 100 );
            $qtd_total_mg = $dose_mg_real * $fp * $mult;
            $qtd_tot_g    = $qtd_total_mg / 1000;
            $qtd_em_u     = $unid_p === 'g' ? $qtd_tot_g : $qtd_total_mg;
            $subtotal     = round( $qtd_em_u * $preco, 4 );
        }

        $itens[] = [
            'tipo'              => 'mp',
            'ativo_id'          => $at['id'] ?? null,
            'nome'              => $at['nome'] ?? $hi['descr'],
            'nome_prescricao'   => $hi['descr'],
            'codigo_fc'         => $hi['codigo_fc'] ?? '',
            'dose'              => $is_qsp ? null : $dose,
            'dose_unit'         => $dose_unit,
            'is_qsp'            => $is_qsp,
            'multiplicador'     => $mult,
            'qtde_potes'        => $potes,
            'fp'                => $fp,
            'diluicao'          => $diluicao,
            'teor'              => $teor,
            'qtd_total_g'       => $qtd_tot_g,
            'volapa_ul'         => 0,
            'custo_por_unidade' => (float) ( $at['custo_por_unidade'] ?? 0 ),
            'preco_venda'       => $preco,
            'unid_padrao'       => $unid_p,
            'subtotal'          => $subtotal,
        ];
    }

    $nome_pac = $form['nome_paciente'] ?: '';
    $dt_fmt   = $form['dt_cadastro'] ? date( 'd/m/Y', strtotime( $form['dt_cadastro'] ) ) : '?';

    // Cliente (contratante) = cliente do histórico (pode diferir do paciente — ex. mãe/filho)
    $nome_cli = '';
    if ( ! empty( $form['hist_cliente_id'] ) ) {
        $rc = tao_formula_api( "/hist_clientes?id=eq.{$form['hist_cliente_id']}&select=nome&limit=1" );
        $nome_cli = ( $rc['ok'] && ! empty( $rc['data'] ) ) ? ( $rc['data'][0]['nome'] ?? '' ) : '';
    }

    $univol   = strtoupper( trim( (string) ( $form['univol'] ?? '' ) ) );
    $uni_map  = [ 'CAP' => 'caps', 'G' => 'g', 'ML' => 'ml', 'UN' => 'un', 'ENV' => 'env', 'L' => 'L' ];
    $uni_form = $uni_map[ $univol ] ?? '';

    // Forma farmacêutica REAL (repetição exata): CAP→cap, ENV→envelope; demais são ambíguas
    // (ML pode ser solução/loção/floral; G pode ser creme/gel) → atendente confirma
    $forma_id = null; $forma_nome = '';
    $tipo_forma_alvo = [ 'CAP' => 'cap', 'ENV' => 'envelope' ][ $univol ] ?? null;
    if ( $tipo_forma_alvo ) {
        $rf2 = tao_formula_api( "/formas_farmaceuticas?cliente_id=eq.$cliente_id&ativo=eq.true&tipo=eq.$tipo_forma_alvo&select=id,nome&order=nome.asc&limit=1" );
        if ( $rf2['ok'] && ! empty( $rf2['data'] ) ) {
            $forma_id   = $rf2['data'][0]['id'];
            $forma_nome = $rf2['data'][0]['nome'];
        }
    }

    // Tipo da cápsula: TPCAP do FCerta casa por 1ª letra com tipos_capsula do TAO
    // (G=GELATINOSA, E=ENTÉRICA, I=INCOLOR, L=LIPOFILICA, T=TAPIOCA, V=VEGETAL)
    $forma_tipo = ''; $tpcap = strtoupper( trim( (string) ( $form['tpcap'] ?? '' ) ) );
    if ( $tpcap && $forma_id && $univol === 'CAP' ) {
        $rt = tao_formula_api( "/tipos_capsula?cliente_id=eq.$cliente_id&ativo=eq.true&select=tipo&limit=50" );
        foreach ( ( $rt['ok'] ? $rt['data'] : [] ) as $tc ) {
            if ( mb_strtoupper( mb_substr( $tc['tipo'], 0, 1 ) ) === $tpcap ) { $forma_tipo = $tc['tipo']; break; }
        }
    }

    $obs = '[REPETIÇÃO FCerta] Req ' . $form['nrrqu'] . '/' . $form['serier'] . ' de ' . $dt_fmt .
           ' — cobrado na última aprovação: R$ ' . number_format( (float) $form['preco_cobrado'], 2, ',', '.' ) .
           ' (' . $potes . ' un × ' . rtrim( rtrim( number_format( $vol, 2, ',', '' ), '0' ), ',' ) . ' ' . $univol .
           ( $forma_tipo ? ', cápsula ' . $forma_tipo : '' ) . ').';
    if ( ! empty( $form['prescritor'] ) ) $obs .= ' Prescritor: ' . $form['prescritor'] . '.';
    if ( $form['posologia'] )    $obs .= ' Posologia: ' . $form['posologia'] . '.';
    if ( $nao_encontrados )      $obs .= ' ⚠ Sem cadastro atual: ' . implode( ', ', $nao_encontrados ) . '.';
    if ( ! $forma_id )           $obs .= ' Confira a forma farmacêutica.';
    $obs .= ' Preços recalculados pela tabela atual.';

    $numero  = tao_formula_gerar_numero( $cliente_id, $card_id );
    $payload = [
        'cliente_id'          => $cliente_id,
        'card_id'             => $card_id,
        'contato_id'          => $card_id ? tao_formula_contato_do_card( $card_id ) : null,
        'numero_orcamento'    => $numero,
        'status'              => 'pendente_revisao',
        'tipo_entrada'        => 'texto',
        'nome_paciente'       => $nome_pac,
        'nome_cliente'        => ( $nome_cli && $nome_cli !== $nome_pac ) ? $nome_cli : null,
        'prescritor'          => $form['prescritor'] ?? null,
        'posologia'           => $form['posologia']  ?? null,
        'whatsapp'            => '',
        'forma_id'            => $forma_id,
        'forma_nome'          => $forma_nome ?: ( $univol ? "Histórico FCerta ($univol)" : 'Histórico FCerta' ),
        'forma_vol'           => $vol,
        'forma_tipo'          => $forma_tipo ?: null,
        'forma_unidade'       => $uni_form,
        'qtde_potes'          => $potes,
        'itens'               => $itens,
        'total_orcamento'     => array_sum( array_column( $itens, 'subtotal' ) ),
        'total_insumos'       => array_sum( array_column( $itens, 'subtotal' ) ),
        'custo_fixo_aplicado' => 0,
        'margem_aplicada'     => 0,
        'desconto_pct'        => 0,
        'observacoes'         => $obs,
        'atualizado_em'       => gmdate( 'c' ),
    ];

    $r = tao_formula_orc_gravar( '/orcamentos', 'POST', $payload );
    if ( ! $r['ok'] ) wp_send_json_error( [ 'message' => 'Erro ao criar orçamento: ' . mb_substr( (string) $r['raw'], 0, 300 ) ] );

    if ( $card_id && function_exists( 'tao_crm_sync_valor_oportunidade' ) ) tao_crm_sync_valor_oportunidade( $card_id );

    wp_send_json_success( [
        'orc_id'          => $r['data'][0]['id'] ?? null,
        'numero'          => $numero,
        'nao_encontrados' => $nao_encontrados,
    ] );
} );

// ── Repetir orçamento INTERNO: clona um orçamento de outro card do MESMO cliente
//    para o card atual, como orçamento NOVO pendente de revisão. Copia só o
//    orçamento (composição/forma/preços — o orçamento do TAO é autocontido) e
//    zera o estado (aprovação/OM/envio). Daí segue o fluxo normal: revisar →
//    aprovar → vira OM. NUNCA copia a OM. Pedido Carlos 20/07.
add_action( 'wp_ajax_tao_formula_orc_repetir', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( [ 'message' => 'Acesso negado' ], 403 );
    $cliente_id = tao_formula_cliente_id();
    $src_id     = sanitize_text_field( $_POST['orc_id'] ?? '' );
    $card_id    = sanitize_text_field( $_POST['card_id'] ?? '' ) ?: null;
    if ( ! $cliente_id || ! $src_id || ! $card_id ) wp_send_json_error( [ 'message' => 'Parâmetros inválidos' ] );

    $rs = tao_formula_api( "/orcamentos?id=eq.$src_id&cliente_id=eq.$cliente_id&limit=1" );
    if ( ! $rs['ok'] || empty( $rs['data'] ) ) wp_send_json_error( [ 'message' => 'Orçamento de origem não encontrado' ] );
    $o = $rs['data'][0];

    $itens = is_string( $o['itens'] ?? null ) ? json_decode( $o['itens'], true ) : ( $o['itens'] ?? [] );
    if ( ! is_array( $itens ) ) $itens = [];

    $numero  = tao_formula_gerar_numero( $cliente_id, $card_id );
    $src_num = $o['numero_orcamento'] ?? '?';

    $payload = [
        'cliente_id'             => $cliente_id,
        'card_id'                => $card_id,
        'contato_id'             => tao_formula_contato_do_card( $card_id ),
        'numero_orcamento'       => $numero,
        'status'                 => 'pendente_revisao',
        'tipo_entrada'           => $o['tipo_entrada'] ?? 'texto',
        'nome_paciente'          => $o['nome_paciente'] ?? null,
        'nome_cliente'           => $o['nome_cliente'] ?? null,
        'prescritor'             => $o['prescritor'] ?? null,
        'prescritor_id'          => $o['prescritor_id'] ?? null,
        'posologia'              => $o['posologia'] ?? null,
        'cid_codigo'             => $o['cid_codigo'] ?? null,
        'cid_descricao'          => $o['cid_descricao'] ?? null,
        'tp_receita'             => $o['tp_receita'] ?? null,
        'medicamento_controlado' => $o['medicamento_controlado'] ?? null,
        'whatsapp'               => $o['whatsapp'] ?? '',
        'forma_id'               => $o['forma_id'] ?? null,
        'forma_nome'             => $o['forma_nome'] ?? null,
        'forma_tipo'             => $o['forma_tipo'] ?? null,
        'forma_unidade'          => $o['forma_unidade'] ?? null,
        'forma_vol'              => $o['forma_vol'] ?? null,
        'qtde_potes'             => $o['qtde_potes'] ?? null,
        'qtd_unidades'           => $o['qtd_unidades'] ?? null,
        'itens'                  => $itens,
        'formula_estruturada'    => $o['formula_estruturada'] ?? null,
        'total_orcamento'        => $o['total_orcamento'] ?? 0,
        'total_insumos'          => $o['total_insumos'] ?? 0,
        'custo_total'            => $o['custo_total'] ?? null,
        'custo_fixo_aplicado'    => $o['custo_fixo_aplicado'] ?? 0,
        'margem_aplicada'        => $o['margem_aplicada'] ?? 0,
        'acrescimo_aplicado'     => $o['acrescimo_aplicado'] ?? 0,
        'desconto_pct'           => $o['desconto_pct'] ?? 0,
        'observacoes'            => mb_substr( '[REPETIÇÃO] Do orçamento ' . $src_num . '. ' . (string) ( $o['observacoes'] ?? '' ), 0, 1000 ),
        'atualizado_em'          => gmdate( 'c' ),
    ];

    $r = tao_formula_orc_gravar( '/orcamentos', 'POST', $payload );
    if ( ! $r['ok'] ) wp_send_json_error( [ 'message' => 'Erro ao repetir: ' . mb_substr( (string) ( $r['raw'] ?? '' ), 0, 300 ) ] );

    if ( function_exists( 'tao_crm_sync_valor_oportunidade' ) ) tao_crm_sync_valor_oportunidade( $card_id );

    wp_send_json_success( [ 'orc_id' => $r['data'][0]['id'] ?? null, 'numero' => $numero ] );
} );

// ═══════════════════════════════════════════════════════════════════════════
// PRESCRITORES — CRUD (espelho FC04000) + autocomplete p/ o editor
// ═══════════════════════════════════════════════════════════════════════════

add_action( 'wp_ajax_tao_formula_prescritores_lista', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( [ 'message' => 'Acesso negado' ], 403 );
    $cliente_id = tao_formula_cliente_id();
    if ( ! $cliente_id ) wp_send_json_error( [ 'message' => 'Cliente não identificado' ] );

    $q      = sanitize_text_field( $_GET['q'] ?? '' );
    $size   = in_array( intval( $_GET['size'] ?? 30 ), [ 20, 30, 50 ], true ) ? intval( $_GET['size'] ) : 30;
    $offset = max( 0, intval( $_GET['offset'] ?? 0 ) );
    $filtro = $q ? '&or=(nome.ilike.*' . rawurlencode( $q ) . '*,nr_registro.ilike.*' . rawurlencode( $q ) . '*)' : '';
    $r = tao_formula_api(
        "/prescritores?cliente_id=eq.$cliente_id$filtro" .
        "&select=id,tratamento,nome,tipo_registro,nr_registro,uf_registro,especialidade,celular,telefone,email,endereco,cidade,uf,cep,obs" .
        "&order=nome.asc&limit=$size&offset=$offset", 'GET', null, true
    );
    if ( ! $r['ok'] ) {
        $msg = strpos( (string) $r['raw'], 'does not exist' ) !== false
            ? 'Tabela prescritores ainda não criada (migration_v3_prescritores.sql pendente).'
            : 'Erro: ' . mb_substr( (string) $r['raw'], 0, 200 );
        wp_send_json_error( [ 'message' => $msg ] );
    }
    wp_send_json_success( [ 'items' => $r['data'] ?? [], 'total' => $r['total'] ] );
} );

add_action( 'wp_ajax_tao_formula_salvar_prescritor', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( [ 'message' => 'Acesso negado' ], 403 );
    $cliente_id = tao_formula_cliente_id();
    if ( ! $cliente_id ) wp_send_json_error( [ 'message' => 'Cliente não identificado' ] );

    $id   = sanitize_text_field( $_POST['id'] ?? '' );
    $nome = strtoupper( trim( sanitize_text_field( $_POST['nome'] ?? '' ) ) );
    if ( ! $nome ) wp_send_json_error( [ 'message' => 'Informe o nome do prescritor' ] );

    $txt = function( $k, $upper = false ) {
        $v = trim( sanitize_text_field( $_POST[ $k ] ?? '' ) );
        if ( $upper ) $v = strtoupper( $v );
        return $v === '' ? null : $v;
    };
    $payload = [
        'nome'          => $nome,
        'tratamento'    => $txt( 'tratamento' ),
        'tipo_registro' => $txt( 'tipo_registro', true ),
        'nr_registro'   => $txt( 'nr_registro' ),
        'uf_registro'   => $txt( 'uf_registro', true ),
        'especialidade' => $txt( 'especialidade' ),
        'celular'       => $txt( 'celular' ),
        'telefone'      => $txt( 'telefone' ),
        'email'         => $txt( 'email' ),
        'endereco'      => $txt( 'endereco' ),
        'cidade'        => $txt( 'cidade' ),
        'uf'            => $txt( 'uf', true ),
        'cep'           => $txt( 'cep' ),
        'obs'           => $txt( 'obs' ),
    ];
    if ( $id ) {
        $r = tao_formula_api( "/prescritores?id=eq.$id&cliente_id=eq.$cliente_id", 'PATCH', $payload );
    } else {
        $payload['cliente_id'] = $cliente_id;
        $r = tao_formula_api( '/prescritores', 'POST', $payload );
    }
    $r['ok'] ? wp_send_json_success( [ 'id' => $r['data'][0]['id'] ?? $id ] )
             : wp_send_json_error( [ 'message' => 'Erro ao salvar: ' . mb_substr( (string) $r['raw'], 0, 250 ) ] );
} );

// ── FORNECEDORES — CRUD (dados fiscais + qualificação RDC 67; espelho FC02000) ──
add_action( 'wp_ajax_tao_formula_fornecedores_lista', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( [ 'message' => 'Acesso negado' ], 403 );
    $cliente_id = tao_formula_cliente_id();
    if ( ! $cliente_id ) wp_send_json_error( [ 'message' => 'Cliente não identificado' ] );

    $q      = sanitize_text_field( $_GET['q'] ?? '' );
    $size   = in_array( intval( $_GET['size'] ?? 30 ), [ 20, 30, 50 ], true ) ? intval( $_GET['size'] ) : 30;
    $offset = max( 0, intval( $_GET['offset'] ?? 0 ) );
    $filtro = $q ? '&or=(nome.ilike.*' . rawurlencode( $q ) . '*,razao_social.ilike.*' . rawurlencode( $q )
                 . '*,cnpj.ilike.*' . rawurlencode( $q ) . '*)' : '';
    $r = tao_formula_api(
        "/fornecedores?cliente_id=eq.$cliente_id$filtro" .
        "&select=*&order=nome.asc&limit=$size&offset=$offset", 'GET', null, true
    );
    if ( ! $r['ok'] ) wp_send_json_error( [ 'message' => 'Erro: ' . mb_substr( (string) $r['raw'], 0, 200 ) ] );
    wp_send_json_success( [ 'items' => $r['data'] ?? [], 'total' => (int) $r['total'] ] );
} );

add_action( 'wp_ajax_tao_formula_salvar_fornecedor', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( [ 'message' => 'Acesso negado' ], 403 );
    $cliente_id = tao_formula_cliente_id();
    if ( ! $cliente_id ) wp_send_json_error( [ 'message' => 'Cliente não identificado' ] );

    $id   = sanitize_text_field( $_POST['id'] ?? '' );
    $nome = trim( sanitize_text_field( $_POST['nome'] ?? '' ) );
    if ( ! $nome ) wp_send_json_error( [ 'message' => 'Informe o nome / razão social do fornecedor' ] );

    $txt = function( $k, $upper = false ) {
        $v = trim( sanitize_text_field( $_POST[ $k ] ?? '' ) );
        if ( $upper ) $v = strtoupper( $v );
        return $v === '' ? null : $v;
    };
    $num  = function( $k ) { $v = str_replace( ',', '.', trim( (string) ( $_POST[ $k ] ?? '' ) ) ); return $v === '' ? null : floatval( $v ); };
    $date = function( $k ) { $v = trim( sanitize_text_field( $_POST[ $k ] ?? '' ) ); return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) ? $v : null; };
    $cnpj = preg_replace( '/\D+/', '', (string) ( $_POST['cnpj'] ?? '' ) );

    $payload = [
        'nome'             => $nome,
        'razao_social'     => $txt( 'razao_social' ),
        'nome_fantasia'    => $txt( 'nome_fantasia' ),
        'cnpj'             => $cnpj ?: null,
        'tipo_pessoa'      => $txt( 'tipo_pessoa', true ) ?: 'PJ',
        'tipo'             => $txt( 'tipo' ),
        'inscr_estadual'   => $txt( 'inscr_estadual' ),
        'inscr_municipal'  => $txt( 'inscr_municipal' ),
        'crt'              => $txt( 'crt' ),
        'suframa'          => $txt( 'suframa' ),
        'reg_mapa'         => $txt( 'reg_mapa' ),
        'endereco'         => $txt( 'endereco' ),
        'endereco_nr'      => $txt( 'endereco_nr' ),
        'complemento'      => $txt( 'complemento' ),
        'bairro'           => $txt( 'bairro' ),
        'cidade'           => $txt( 'cidade' ),
        'uf'               => $txt( 'uf', true ),
        'cep'              => $txt( 'cep' ),
        'contato'          => $txt( 'contato' ),
        'telefone'         => $txt( 'telefone' ),
        'telefone2'        => $txt( 'telefone2' ),
        'whatsapp'         => $txt( 'whatsapp' ),
        'email'            => $txt( 'email' ),
        'site'             => $txt( 'site' ),
        'prazo_pagamento'  => $txt( 'prazo_pagamento' ),
        'valor_min_pedido' => $num( 'valor_min_pedido' ),
        'afe'              => $txt( 'afe' ),
        'afe_validade'     => $date( 'afe_validade' ),
        'autoriz_especial' => $txt( 'autoriz_especial' ),
        'ae_validade'      => $date( 'ae_validade' ),
        'licenca_sanitaria'=> $txt( 'licenca_sanitaria' ),
        'licenca_validade' => $date( 'licenca_validade' ),
        'qualificado'      => ! empty( $_POST['qualificado'] ) && $_POST['qualificado'] !== '0',
        'qualif_data'      => $date( 'qualif_data' ),
        'qualif_por'       => $txt( 'qualif_por' ),
        'qualif_obs'       => $txt( 'qualif_obs' ),
        'obs'              => $txt( 'obs' ),
        'ativo'            => ! isset( $_POST['ativo'] ) || $_POST['ativo'] !== '0',
    ];
    if ( $id ) {
        $r = tao_formula_api( "/fornecedores?id=eq.$id&cliente_id=eq.$cliente_id", 'PATCH', $payload );
    } else {
        $payload['cliente_id'] = $cliente_id;
        $r = tao_formula_api( '/fornecedores', 'POST', $payload );
    }
    $r['ok'] ? wp_send_json_success( [ 'id' => $r['data'][0]['id'] ?? $id ] )
             : wp_send_json_error( [ 'message' => 'Erro ao salvar: ' . mb_substr( (string) $r['raw'], 0, 250 ) ] );
} );

// Autocomplete de CID-10 (diagnóstico opcional no orçamento) — por código ou descrição
add_action( 'wp_ajax_tao_formula_cid_busca', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( 'Acesso negado', 403 );
    $q = trim( sanitize_text_field( $_GET['q'] ?? '' ) );
    if ( mb_strlen( $q ) < 2 ) { wp_send_json_success( [] ); return; }
    $enc = rawurlencode( '*' . $q . '*' );
    $r = tao_formula_api( "/cid10?or=(codigo.ilike.$enc,busca.ilike.$enc)&select=codigo,descricao&order=codigo.asc&limit=12" );
    wp_send_json_success( $r['ok'] ? ( $r['data'] ?? [] ) : [] );
} );

// Autocomplete do editor: nome ou nº de registro → 10 primeiros
add_action( 'wp_ajax_tao_formula_prescritores_busca', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( 'Acesso negado', 403 );
    $cliente_id = tao_formula_cliente_id();
    $q          = sanitize_text_field( $_GET['q'] ?? '' );
    if ( ! $cliente_id || mb_strlen( $q ) < 2 ) { wp_send_json_success( [] ); return; }
    $enc = rawurlencode( $q );
    $r = tao_formula_api(
        "/prescritores?cliente_id=eq.$cliente_id&ativo=eq.true" .
        "&or=(nome.ilike.*{$enc}*,nr_registro.ilike.*{$enc}*)" .
        "&select=id,tratamento,nome,tipo_registro,nr_registro,uf_registro,especialidade" .
        "&order=nome.asc&limit=10"
    );
    wp_send_json_success( $r['ok'] ? ( $r['data'] ?? [] ) : [] );
} );

// ═══════════════════════════════════════════════════════════════════════════
// FÓRMULAS PADRÃO — busca + detalhe p/ carregar no editor (lab_formulas_padrao)
// ═══════════════════════════════════════════════════════════════════════════

add_action( 'wp_ajax_tao_formula_fpad_busca', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( 'Acesso negado', 403 );
    $cliente_id = tao_formula_cliente_id();
    $q          = sanitize_text_field( $_GET['q'] ?? '' );
    if ( ! $cliente_id || mb_strlen( $q ) < 2 ) { wp_send_json_success( [] ); return; }
    $r = tao_formula_api(
        "/lab_formulas_padrao?cliente_id=eq.$cliente_id&ativo=eq.true" .
        "&nome=ilike.*" . rawurlencode( $q ) . "*" .
        "&select=id,nome,forma_farmac,volume,unidade,tipo_capsula,tem_qsp&order=nome.asc&limit=15"
    );
    if ( ! $r['ok'] ) {
        $msg = strpos( (string) $r['raw'], 'does not exist' ) !== false
            ? 'Fórmulas padrão ainda não disponíveis (carga pendente).'
            : mb_substr( (string) $r['raw'], 0, 160 );
        wp_send_json_error( [ 'message' => $msg ] );
    }
    wp_send_json_success( $r['data'] ?? [] );
} );

add_action( 'wp_ajax_tao_formula_fpad_detalhe', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( 'Acesso negado', 403 );
    $cliente_id = tao_formula_cliente_id();
    $fid        = sanitize_text_field( $_GET['id'] ?? '' );
    if ( ! $cliente_id || ! $fid ) wp_send_json_error( [ 'message' => 'Parâmetros inválidos' ] );

    $rf = tao_formula_api( "/lab_formulas_padrao?id=eq.$fid&cliente_id=eq.$cliente_id&select=id,nome,forma_farmac,volume,unidade,tipo_capsula,posologia,tem_qsp&limit=1" );
    if ( ! $rf['ok'] || empty( $rf['data'] ) ) wp_send_json_error( [ 'message' => 'Fórmula padrão não encontrada' ] );
    $formula = $rf['data'][0];

    $ri = tao_formula_api(
        "/lab_formulas_padrao_itens?formula_id=eq.$fid" .
        "&select=ativo_id,descricao,qtd,unidade,eh_qsp,ordem&order=ordem.asc&limit=80"
    );
    $itens = ( $ri['ok'] ? $ri['data'] : [] );

    // Enriquece cada item com dados do ativo (preço/unidade/técnicos) p/ o editor calcular
    $ids = array_values( array_unique( array_filter( array_column( $itens, 'ativo_id' ) ) ) );
    $mapa = [];
    if ( $ids ) {
        $motor = get_option( 'tao_formula_motor_v2' ) === '1';
        $sel   = 'id,codigo_fc,nome,unidade_padrao,preco_venda,custo_por_unidade,fator_perda,diluicao,teor,densidade,concentracao'
               . ( $motor ? ',dose_max,uni_dose_max,dose_max_dia,dose_max_unidade,restricao' : '' );
        $ra = tao_formula_api( "/ativos?cliente_id=eq.$cliente_id&id=in.(" . implode( ',', $ids ) . ")&select=$sel&limit=" . count( $ids ) );
        foreach ( ( $ra['ok'] ? $ra['data'] : [] ) as $a ) $mapa[ $a['id'] ] = $a;
    }
    foreach ( $itens as &$it ) {
        $it['ativo'] = $it['ativo_id'] ? ( $mapa[ $it['ativo_id'] ] ?? null ) : null;
    }
    unset( $it );

    wp_send_json_success( [ 'formula' => $formula, 'itens' => $itens ] );
} );

// ═══════════════════════════════════════════════════════════════════════════
// EMPRESA / FILIAL — dados da farmácia (rótulo RDC 67 + fiscal). 1 por tenant.
// ═══════════════════════════════════════════════════════════════════════════

add_action( 'wp_ajax_tao_formula_empresa_get', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( 'Acesso negado', 403 );
    $cliente_id = tao_formula_cliente_id();
    if ( ! $cliente_id ) wp_send_json_error( 'Cliente não identificado', 400 );
    $r = tao_formula_api( "/empresa_config?cliente_id=eq.$cliente_id&limit=1" );
    if ( ! $r['ok'] && strpos( (string) $r['raw'], 'does not exist' ) !== false ) {
        wp_send_json_error( [ 'message' => 'Tabela empresa_config pendente (migration_v4_empresa.sql).' ] );
    }
    wp_send_json_success( ( $r['ok'] && ! empty( $r['data'] ) ) ? $r['data'][0] : null );
} );

add_action( 'wp_ajax_tao_formula_empresa_save', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_is_master() ) wp_send_json_error( [ 'message' => 'Só o administrador pode editar a empresa' ], 403 );
    $cliente_id = tao_formula_cliente_id();
    if ( ! $cliente_id ) wp_send_json_error( [ 'message' => 'Cliente não identificado' ] );

    $txt = function( $k, $upper = false ) {
        $v = trim( sanitize_text_field( $_POST[ $k ] ?? '' ) );
        if ( $upper ) $v = strtoupper( $v );
        return $v === '' ? null : $v;
    };
    $campos = [ 'razao_social', 'nome_fantasia', 'cnpj', 'inscr_estadual', 'inscr_municipal',
        'endereco', 'bairro', 'cidade', 'uf', 'cep', 'telefone', 'email',
        'rt_nome', 'rt_crf', 'rt_uf', 'licenca_afe', 'licenca_cevs', 'licenca_crf_pj', 'autorizacao_esp' ];
    $payload = [ 'atualizado_em' => gmdate( 'c' ) ];
    foreach ( $campos as $c ) $payload[ $c ] = $txt( $c, in_array( $c, [ 'uf', 'rt_uf' ], true ) );
    if ( $payload['cnpj'] ) $payload['cnpj'] = preg_replace( '/\D/', '', $payload['cnpj'] );

    // 1 registro por tenant (PK = cliente_id): existe → PATCH, senão POST
    $chk = tao_formula_api( "/empresa_config?cliente_id=eq.$cliente_id&select=cliente_id&limit=1" );
    if ( $chk['ok'] && ! empty( $chk['data'] ) ) {
        $r = tao_formula_api( "/empresa_config?cliente_id=eq.$cliente_id", 'PATCH', $payload );
    } else {
        $payload['cliente_id'] = $cliente_id;
        $r = tao_formula_api( '/empresa_config', 'POST', $payload );
    }
    $r['ok'] ? wp_send_json_success() : wp_send_json_error( [ 'message' => 'Erro ao salvar: ' . mb_substr( (string) $r['raw'], 0, 250 ) ] );
} );

// ═══════════════════════════════════════════════════════════════════════════
// CLIENTE/PACIENTE — cadastro ÚNICO na base do CRM (crm_contatos).
// A pessoa vive em crm_contatos; hist_clientes só referencia via contato_id.
// ═══════════════════════════════════════════════════════════════════════════

// Lê o contato do CRM (id = crm_contatos.id)
add_action( 'wp_ajax_tao_formula_cliente_get', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( 'Acesso negado', 403 );
    $ws = tao_formula_workspace_id();
    $id = sanitize_text_field( $_GET['id'] ?? '' );
    if ( ! $ws || ! $id ) wp_send_json_error( [ 'message' => 'Parâmetros inválidos' ] );
    $r = tao_formula_api(
        "/crm_contatos?id=eq.$id&workspace_id=eq.$ws" .
        "&select=id,nome,whatsapp,email,data_nascimento,sexo,observacoes,alergias,saude_obesidade,saude_colesterol,saude_pressao,saude_diabetes,consentimento,consent_data,consent_canal,anonimizado,origem&limit=1"
    );
    if ( ! $r['ok'] ) {
        // migration_lgpd pendente → tenta sem os campos novos
        $r = tao_formula_api( "/crm_contatos?id=eq.$id&workspace_id=eq.$ws&select=id,nome,whatsapp,email,data_nascimento,sexo,observacoes,alergias,saude_obesidade,saude_colesterol,saude_pressao,saude_diabetes,origem&limit=1" );
    }
    if ( ! $r['ok'] || empty( $r['data'] ) ) wp_send_json_error( [ 'message' => 'Contato não encontrado' ] );
    // LGPD: registra o acesso a dado sensível de paciente (trilha)
    tao_formula_lgpd_log( tao_formula_cliente_id(), $id, 'visualizou' );
    wp_send_json_success( $r['data'][0] );
} );

// Busca contatos do CRM por nome ou WhatsApp — autocomplete de paciente no orçamento
add_action( 'wp_ajax_tao_formula_cliente_busca', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( 'Acesso negado', 403 );
    $ws = tao_formula_workspace_id();
    $q  = sanitize_text_field( $_GET['q'] ?? '' );
    if ( ! $ws || mb_strlen( $q ) < 2 ) { wp_send_json_success( [] ); return; }
    $enc = rawurlencode( $q );
    // exclui anonimizados (LGPD); se a coluna não existir, refaz sem o filtro
    $base = "/crm_contatos?workspace_id=eq.$ws&or=(nome.ilike.*{$enc}*,whatsapp.ilike.*{$enc}*)"
          . "&select=id,nome,whatsapp&order=nome.asc&limit=10";
    $r = tao_formula_api( $base . '&anonimizado=eq.false' );
    if ( ! $r['ok'] ) $r = tao_formula_api( $base );
    wp_send_json_success( $r['ok'] ? ( $r['data'] ?? [] ) : [] );
} );

// Helper: registra acesso a dado sensível (silencioso se a tabela não existir)
function tao_formula_lgpd_log( $cliente_id, $contato_id, $acao ) {
    if ( ! $cliente_id || ! $contato_id ) return;
    $u = wp_get_current_user();
    tao_formula_api( '/lgpd_acessos', 'POST', [
        'cliente_id' => $cliente_id, 'contato_id' => $contato_id,
        'usuario_id' => get_current_user_id(), 'usuario_nome' => $u ? $u->display_name : null,
        'acao' => $acao,
    ] );
}

// Cria/edita o contato do CRM. Opcional: hist_id p/ vincular ao histórico (contato_id).
add_action( 'wp_ajax_tao_formula_cliente_save', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( [ 'message' => 'Acesso negado' ], 403 );
    $ws         = tao_formula_workspace_id();
    $cliente_id = tao_formula_cliente_id();
    if ( ! $ws ) wp_send_json_error( [ 'message' => 'Workspace do CRM não encontrado' ] );

    $id      = sanitize_text_field( $_POST['id'] ?? '' );       // crm_contatos.id (edição)
    $hist_id = sanitize_text_field( $_POST['hist_id'] ?? '' );  // hist_clientes.id (vincular ao criar)
    $nome    = trim( sanitize_text_field( $_POST['nome'] ?? '' ) );
    if ( ! $nome ) wp_send_json_error( [ 'message' => 'Informe o nome' ] );

    $txt  = function( $k ) { $v = trim( sanitize_text_field( $_POST[ $k ] ?? '' ) ); return $v === '' ? null : $v; };
    $bool = function( $k ) { return ( $_POST[ $k ] ?? '' ) === '1'; };
    $payload = [
        'nome'             => $nome,
        'data_nascimento'  => $txt( 'dt_nascimento' ),
        'email'            => $txt( 'email' ),
        'sexo'             => in_array( ( $_POST['sexo'] ?? '' ), [ 'M', 'F' ], true ) ? $_POST['sexo'] : null,
        'alergias'         => $txt( 'alergias' ),
        'observacoes'      => $txt( 'observacoes' ),
        'saude_obesidade'  => $bool( 'saude_obesidade' ),
        'saude_colesterol' => $bool( 'saude_colesterol' ),
        'saude_pressao'    => $bool( 'saude_pressao' ),
        'saude_diabetes'   => $bool( 'saude_diabetes' ),
        'consentimento'    => $bool( 'consentimento' ),
        'consent_data'     => $txt( 'consent_data' ),
        'consent_canal'    => $txt( 'consent_canal' ),
    ];
    $wpp = tao_formula_norm_whatsapp( $_POST['whatsapp'] ?? '' );
    if ( $wpp ) $payload['whatsapp'] = $wpp;

    if ( $id ) {
        // Edição de contato existente
        $r = tao_formula_api( "/crm_contatos?id=eq.$id&workspace_id=eq.$ws", 'PATCH', $payload );
        $contato_id = $id;
    } else {
        // Novo contato: exige whatsapp (chave do CRM). Se já existir o número, reaproveita.
        if ( ! $wpp ) wp_send_json_error( [ 'message' => 'Informe o WhatsApp (chave única do cadastro).' ] );
        $ex = tao_formula_api( "/crm_contatos?workspace_id=eq.$ws&whatsapp=eq.$wpp&select=id&limit=1" );
        if ( $ex['ok'] && ! empty( $ex['data'] ) ) {
            $contato_id = $ex['data'][0]['id'];
            $r = tao_formula_api( "/crm_contatos?id=eq.$contato_id", 'PATCH', $payload );
        } else {
            $payload['workspace_id'] = $ws;
            $payload['origem']       = 'tao';
            $r = tao_formula_api( '/crm_contatos', 'POST', $payload );
            $contato_id = ( $r['ok'] && ! empty( $r['data'] ) ) ? ( $r['data'][0]['id'] ?? null ) : null;
        }
    }
    if ( ! $r['ok'] ) wp_send_json_error( [ 'message' => 'Erro ao salvar: ' . mb_substr( (string) $r['raw'], 0, 250 ) ] );

    // Vincula o contato ao registro de histórico (quando veio de um paciente FCerta sem contato)
    if ( $hist_id && $contato_id && $cliente_id ) {
        tao_formula_api( "/hist_clientes?id=eq.$hist_id&cliente_id=eq.$cliente_id", 'PATCH', [ 'contato_id' => $contato_id ] );
    }
    tao_formula_lgpd_log( $cliente_id, $contato_id, 'editou' );
    wp_send_json_success( [ 'id' => $contato_id, 'contato_id' => $contato_id ] );
} );

// LGPD — anonimização do paciente (direito ao esquecimento): apaga dados pessoais,
// preserva o registro para integridade das OMs/histórico (rastreabilidade legal).
add_action( 'wp_ajax_tao_formula_contato_anonimizar', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( [ 'message' => 'Acesso negado' ], 403 );
    if ( ! tao_formula_is_master() ) wp_send_json_error( [ 'message' => 'Apenas gestor/master pode anonimizar.' ], 403 );
    $ws         = tao_formula_workspace_id();
    $cliente_id = tao_formula_cliente_id();
    $id = sanitize_text_field( $_POST['id'] ?? '' );
    if ( ! $ws || ! $id ) wp_send_json_error( [ 'message' => 'Parâmetros inválidos' ] );
    $r = tao_formula_api( "/crm_contatos?id=eq.$id&workspace_id=eq.$ws", 'PATCH', [
        'nome' => '[ANONIMIZADO]', 'email' => null, 'data_nascimento' => null, 'sexo' => null,
        'alergias' => null, 'observacoes' => null, 'anonimizado' => true, 'anonimizado_em' => gmdate( 'c' ),
    ] );
    if ( ! $r['ok'] ) wp_send_json_error( [ 'message' => 'Erro: ' . mb_substr( (string) $r['raw'], 0, 200 ) ] );
    tao_formula_lgpd_log( $cliente_id, $id, 'anonimizou' );
    wp_send_json_success();
} );

// ═══════════════════════════════════════════════════════════════════════════
// ESTOQUE — Entrada de NF (Pacote 2 / Fatia 1)
// Upload XML NFe → parse → conferência assistida (de-para) → efetivar.
// ═══════════════════════════════════════════════════════════════════════════

// Parse do XML da NFe: emitente + itens (com grupo K rastreab.) + duplicatas.
function tao_formula_parse_nfe( $xml_raw ) {
    $xml_raw = preg_replace( '/xmlns(:\w+)?="[^"]*"/', '', $xml_raw, 1 ); // solta o namespace raiz
    $x = @simplexml_load_string( $xml_raw );
    if ( ! $x ) return null;
    // localiza infNFe em qualquer envelope (nfeProc/NFe)
    $inf = $x->xpath( '//infNFe' );
    if ( ! $inf ) return null;
    $inf = $inf[0];

    $emit = $inf->emit;
    $en   = $emit->enderEmit;
    $out = [
        'cnpj_emitente' => preg_replace( '/\D/', '', (string) $emit->CNPJ ),
        'razao'         => (string) $emit->xNome,
        'chave_nfe'     => preg_replace( '/\D/', '', (string) ( $inf['Id'] ?? '' ) ),
        'numero'        => (string) $inf->ide->nNF,
        'serie'         => (string) $inf->ide->serie,
        'dt_emissao'    => substr( (string) $inf->ide->dhEmi, 0, 10 ),
        'valor_total'   => (float) $inf->total->ICMSTot->vNF,
        // dados do emitente p/ pré-preencher o cadastro de fornecedor (RDC 67 / casamento da NF)
        'emitente'      => [
            'cnpj'           => preg_replace( '/\D/', '', (string) $emit->CNPJ ),
            'razao_social'   => (string) $emit->xNome,
            'nome_fantasia'  => (string) $emit->xFant,
            'inscr_estadual' => (string) $emit->IE,
            'crt'            => (string) $emit->CRT,
            'endereco'       => (string) $en->xLgr,
            'endereco_nr'    => (string) $en->nro,
            'complemento'    => (string) $en->xCpl,
            'bairro'         => (string) $en->xBairro,
            'cidade'         => (string) $en->xMun,
            'uf'             => (string) $en->UF,
            'cep'            => preg_replace( '/\D/', '', (string) $en->CEP ),
            'telefone'       => (string) $en->fone,
        ],
        'itens'         => [],
        'duplicatas'    => [],
    ];
    foreach ( $inf->det as $det ) {
        $p = $det->prod;
        $item = [
            'cod_fornecedor' => (string) $p->cProd,
            'descr_xml'      => (string) $p->xProd,
            'quantidade'     => (float) $p->qCom,
            'unidade'        => (string) $p->uCom,
            'preco_unit'     => (float) $p->vUnCom,
            'desconto'       => (float) ( $p->vDesc ?? 0 ),
            'lote'           => '', 'dt_fab' => '', 'dt_val' => '',
        ];
        // grupo K (rastreabilidade de medicamento): lote/fab/validade
        if ( isset( $p->rastro ) ) {
            $r = $p->rastro;
            $item['lote']   = (string) $r->nLote;
            $item['dt_fab'] = substr( (string) $r->dFab, 0, 10 );
            $item['dt_val'] = substr( (string) $r->dVal, 0, 10 );
        }
        $out['itens'][] = $item;
    }
    if ( isset( $inf->cobr->dup ) ) {
        foreach ( $inf->cobr->dup as $d ) {
            $out['duplicatas'][] = [
                'numero_dup' => (string) $d->nDup,
                'vencimento' => substr( (string) $d->dVenc, 0, 10 ),
                'valor'      => (float) $d->vDup,
            ];
        }
    }
    return $out;
}

// Upload do XML + pré-conferência (associação por de-para já aprendido)
add_action( 'wp_ajax_tao_formula_nf_upload', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( [ 'message' => 'Acesso negado' ], 403 );
    $cliente_id = tao_formula_cliente_id();
    if ( ! $cliente_id ) wp_send_json_error( [ 'message' => 'Cliente não identificado' ] );

    if ( empty( $_FILES['xml'] ) || $_FILES['xml']['error'] !== UPLOAD_ERR_OK )
        wp_send_json_error( [ 'message' => 'Envie o arquivo XML da NF-e' ] );
    $raw = file_get_contents( $_FILES['xml']['tmp_name'] );
    $nfe = tao_formula_parse_nfe( $raw );
    if ( ! $nfe ) wp_send_json_error( [ 'message' => 'XML inválido ou não é uma NF-e' ] );

    // NF já importada?
    if ( $nfe['chave_nfe'] ) {
        $dup = tao_formula_api( "/estoque_entradas_nf?cliente_id=eq.$cliente_id&chave_nfe=eq.{$nfe['chave_nfe']}&select=id,status&limit=1" );
        if ( $dup['ok'] && ! empty( $dup['data'] ) )
            wp_send_json_error( [ 'message' => 'Esta NF-e já foi importada (' . $dup['data'][0]['status'] . ').' ] );
    }

    // fornecedor pelo CNPJ do emitente
    $forn = null;
    if ( $nfe['cnpj_emitente'] ) {
        $rf = tao_formula_api( "/fornecedores?cliente_id=eq.$cliente_id&cnpj=eq.{$nfe['cnpj_emitente']}" .
            "&select=id,nome,qualificado,afe,afe_validade,autoriz_especial,ae_validade,licenca_sanitaria,licenca_validade&limit=1" );
        if ( $rf['ok'] && ! empty( $rf['data'] ) ) $forn = $rf['data'][0];
    }

    // alertas de conformidade do fornecedor no recebimento (RDC 67)
    $nfe['forn_alertas'] = [];
    if ( $forn ) {
        $hoje = gmdate( 'Y-m-d' );
        if ( ! empty( $forn['afe_validade'] ) && $forn['afe_validade'] < $hoje )
            $nfe['forn_alertas'][] = [ 'nivel' => 'erro', 'msg' => 'AFE (Autorização de Funcionamento) vencida em ' . $forn['afe_validade'] ];
        if ( ! empty( $forn['ae_validade'] ) && $forn['ae_validade'] < $hoje )
            $nfe['forn_alertas'][] = [ 'nivel' => 'erro', 'msg' => 'Autorização Especial (controlados) vencida em ' . $forn['ae_validade'] ];
        if ( ! empty( $forn['licenca_validade'] ) && $forn['licenca_validade'] < $hoje )
            $nfe['forn_alertas'][] = [ 'nivel' => 'erro', 'msg' => 'Licença/Alvará Sanitário vencido em ' . $forn['licenca_validade'] ];
        if ( empty( $forn['qualificado'] ) )
            $nfe['forn_alertas'][] = [ 'nivel' => 'aviso', 'msg' => 'Fornecedor ainda não qualificado (RDC 67).' ];
        if ( empty( $forn['afe'] ) )
            $nfe['forn_alertas'][] = [ 'nivel' => 'aviso', 'msg' => 'Fornecedor sem número de AFE cadastrado.' ];
    }

    // de-para aprendido p/ este fornecedor
    $depara = [];
    if ( $forn ) {
        $rd = tao_formula_api( "/estoque_forn_depara?cliente_id=eq.$cliente_id&fornecedor_id=eq.{$forn['id']}&select=cod_fornecedor,ativo_id&limit=2000" );
        foreach ( ( $rd['ok'] ? $rd['data'] : [] ) as $d ) $depara[ (string) $d['cod_fornecedor'] ] = $d['ativo_id'];
    }
    // dados dos ativos já mapeados (nome/código p/ exibir)
    $ativo_ids = array_values( array_unique( array_filter( array_values( $depara ) ) ) );
    $ativos = [];
    if ( $ativo_ids ) {
        $ra = tao_formula_api( "/ativos?cliente_id=eq.$cliente_id&id=in.(" . implode( ',', $ativo_ids ) . ")&select=id,codigo_fc,nome,unidade_padrao,preco_compra,custo_por_unidade&limit=" . count( $ativo_ids ) );
        foreach ( ( $ra['ok'] ? $ra['data'] : [] ) as $a ) $ativos[ $a['id'] ] = $a;
    }
    foreach ( $nfe['itens'] as &$it ) {
        $aid = $depara[ $it['cod_fornecedor'] ] ?? null;
        $it['ativo_id'] = $aid;
        $it['ativo']    = $aid ? ( $ativos[ $aid ] ?? null ) : null;
        $it['destino_valor'] = 'compra';  // default (Carlos)
    }
    unset( $it );

    $nfe['fornecedor'] = $forn;
    wp_send_json_success( $nfe );
} );

// Cadastro rápido de fornecedor a partir dos dados do emitente do XML (fluxo da NF).
// Idempotente: se o CNPJ já existir, retorna o existente em vez de duplicar.
add_action( 'wp_ajax_tao_formula_nf_forn_cadastrar', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( [ 'message' => 'Acesso negado' ], 403 );
    $cliente_id = tao_formula_cliente_id();
    if ( ! $cliente_id ) wp_send_json_error( [ 'message' => 'Cliente não identificado' ] );

    $e    = json_decode( stripslashes( $_POST['emitente'] ?? '' ), true );
    $cnpj = preg_replace( '/\D/', '', (string) ( $e['cnpj'] ?? '' ) );
    if ( ! is_array( $e ) || ! $cnpj ) wp_send_json_error( [ 'message' => 'Dados do emitente ausentes' ] );

    // já existe? devolve
    $rf = tao_formula_api( "/fornecedores?cliente_id=eq.$cliente_id&cnpj=eq.$cnpj&select=id,nome&limit=1" );
    if ( $rf['ok'] && ! empty( $rf['data'] ) ) { wp_send_json_success( $rf['data'][0] ); return; }

    $clean = function( $k ) use ( $e ) { $v = trim( (string) ( $e[ $k ] ?? '' ) ); return $v === '' ? null : $v; };
    $payload = [
        'cliente_id'     => $cliente_id,
        'nome'           => $clean( 'nome_fantasia' ) ?: ( $clean( 'razao_social' ) ?: $cnpj ),
        'razao_social'   => $clean( 'razao_social' ),
        'nome_fantasia'  => $clean( 'nome_fantasia' ),
        'cnpj'           => $cnpj,
        'tipo_pessoa'    => 'PJ',
        'inscr_estadual' => $clean( 'inscr_estadual' ),
        'crt'            => $clean( 'crt' ),
        'endereco'       => $clean( 'endereco' ),
        'endereco_nr'    => $clean( 'endereco_nr' ),
        'complemento'    => $clean( 'complemento' ),
        'bairro'         => $clean( 'bairro' ),
        'cidade'         => $clean( 'cidade' ),
        'uf'             => $clean( 'uf' ),
        'cep'            => $clean( 'cep' ),
        'telefone'       => $clean( 'telefone' ),
        'ativo'          => true,
    ];
    $r = tao_formula_api( '/fornecedores', 'POST', $payload );
    ( $r['ok'] && ! empty( $r['data'] ) )
        ? wp_send_json_success( [ 'id' => $r['data'][0]['id'], 'nome' => $payload['nome'] ] )
        : wp_send_json_error( [ 'message' => 'Erro ao cadastrar: ' . mb_substr( (string) $r['raw'], 0, 200 ) ] );
} );

// Efetiva a entrada: grava NF+itens, aprende de-para, cria lotes+movimentos,
// atualiza preço/estoque do ativo e gera contas a pagar.
add_action( 'wp_ajax_tao_formula_nf_efetivar', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( [ 'message' => 'Acesso negado' ], 403 );
    $cliente_id = tao_formula_cliente_id();
    if ( ! $cliente_id ) wp_send_json_error( [ 'message' => 'Cliente não identificado' ] );

    $payload = json_decode( stripslashes( $_POST['payload'] ?? '' ), true );
    if ( ! is_array( $payload ) || empty( $payload['itens'] ) )
        wp_send_json_error( [ 'message' => 'Dados da NF ausentes' ] );

    $itens = $payload['itens'];
    // todos os itens precisam de ativo associado
    foreach ( $itens as $it ) {
        if ( empty( $it['ativo_id'] ) )
            wp_send_json_error( [ 'message' => 'Associe todos os itens a um ativo antes de efetivar.' ] );
    }

    $forn_id = $payload['fornecedor_id'] ?? null;

    // 1. cabeçalho
    $cab = tao_formula_api( '/estoque_entradas_nf', 'POST', [
        'cliente_id'    => $cliente_id,
        'fornecedor_id' => $forn_id,
        'cnpj_emitente' => $payload['cnpj_emitente'] ?? null,
        'chave_nfe'     => $payload['chave_nfe'] ?? null,
        'numero'        => $payload['numero'] ?? null,
        'serie'         => $payload['serie'] ?? null,
        'dt_emissao'    => $payload['dt_emissao'] ?: null,
        'valor_total'   => $payload['valor_total'] ?? null,
        'status'        => 'efetivada',
        'efetivada_em'  => gmdate( 'c' ),
        'criado_por'    => get_current_user_id(),
    ] );
    if ( ! $cab['ok'] || empty( $cab['data'] ) )
        wp_send_json_error( [ 'message' => 'Erro ao gravar a NF: ' . mb_substr( (string) $cab['raw'], 0, 200 ) ] );
    $entrada_id = $cab['data'][0]['id'];

    $lotes_criados = 0; $mov = 0; $depara_novos = 0;
    foreach ( $itens as $it ) {
        $aid = $it['ativo_id'];
        $qtd = (float) ( $it['quantidade'] ?? 0 );

        // item da NF
        tao_formula_api( '/estoque_entradas_nf_itens', 'POST', [
            'entrada_id'     => $entrada_id, 'ativo_id' => $aid,
            'cod_fornecedor' => $it['cod_fornecedor'] ?? null, 'descr_xml' => $it['descr_xml'] ?? null,
            'quantidade'     => $qtd, 'unidade' => $it['unidade'] ?? null,
            'preco_unit'     => (float) ( $it['preco_unit'] ?? 0 ), 'desconto' => (float) ( $it['desconto'] ?? 0 ),
            'lote'           => $it['lote'] ?: null, 'dt_fab' => $it['dt_fab'] ?: null, 'dt_val' => $it['dt_val'] ?: null,
            'teor'           => $it['teor'] ?? null, 'densidade' => $it['densidade'] ?? null, 'diluicao' => $it['diluicao'] ?? null,
            'destino_valor'  => in_array( $it['destino_valor'] ?? 'compra', [ 'custo', 'compra', 'ambos' ], true ) ? $it['destino_valor'] : 'compra',
        ] );

        // aprende o de-para (1x por fornecedor+código)
        if ( $forn_id && ! empty( $it['cod_fornecedor'] ) && empty( $it['ja_depara'] ) ) {
            $rd = tao_formula_api( '/estoque_forn_depara', 'POST', [
                'cliente_id' => $cliente_id, 'fornecedor_id' => $forn_id,
                'cod_fornecedor' => (string) $it['cod_fornecedor'], 'descr_fornecedor' => $it['descr_xml'] ?? null,
                'ativo_id' => $aid,
            ] );
            if ( $rd['ok'] ) $depara_novos++;
        }

        // lote (se a NF trouxe rastreabilidade)
        $lote_id = null;
        if ( ! empty( $it['lote'] ) ) {
            $rl = tao_formula_api( '/lab_lotes_mp', 'POST', [
                'cliente_id' => $cliente_id, 'ativo_id' => $aid, 'nr_lote' => (string) $it['lote'],
                'origem' => 'fornecedor', 'fornecedor_id' => $forn_id,
                'nf_numero' => $payload['numero'] ?? null, 'nf_chave' => $payload['chave_nfe'] ?? null,
                'dt_fabricacao' => $it['dt_fab'] ?: null, 'dt_validade' => $it['dt_val'] ?: gmdate( 'Y-m-d', strtotime( '+2 years' ) ),
                'qtd_inicial' => $qtd, 'qtd_atual' => $qtd, 'unidade' => $it['unidade'] ?? 'g',
                'teor_pct' => $it['teor'] ?? null, 'densidade' => $it['densidade'] ?? null, 'fator_diluicao' => $it['diluicao'] ?? null,
                'status' => 'quarentena',
            ] );
            if ( $rl['ok'] && ! empty( $rl['data'] ) ) { $lote_id = $rl['data'][0]['id'] ?? null; $lotes_criados++; }
        }

        // movimento de entrada (kardex)
        tao_formula_api( '/estoque_movimentos', 'POST', [
            'cliente_id' => $cliente_id, 'ativo_id' => $aid, 'lote_id' => $lote_id,
            'tipo' => 'entrada', 'quantidade' => $qtd, 'origem' => 'nf', 'ref_id' => $entrada_id,
            'usuario_id' => get_current_user_id(),
        ] );
        $mov++;

        // atualiza preço do ativo conforme destino do valor
        $preco = (float) ( $it['preco_unit'] ?? 0 );
        if ( $preco > 0 ) {
            $dv = $it['destino_valor'] ?? 'compra';
            $upd = [];
            if ( $dv === 'compra' || $dv === 'ambos' ) $upd['preco_compra'] = $preco;
            if ( $dv === 'custo'  || $dv === 'ambos' ) $upd['custo_por_unidade'] = $preco;
            if ( $upd ) {
                tao_formula_api( "/ativos?id=eq.$aid&cliente_id=eq.$cliente_id", 'PATCH', $upd );
                tao_formula_registrar_preco_hist( $cliente_id, $aid, [
                    'preco_compra'  => $upd['preco_compra'] ?? null,
                    'custo_unidade' => $upd['custo_por_unidade'] ?? null,
                ], 'nf', [ 'fornecedor_id' => $forn_id, 'nf_numero' => $payload['numero'] ?? null ] );
            }
        }
    }

    // contas a pagar (duplicatas)
    $cp = 0;
    foreach ( ( $payload['duplicatas'] ?? [] ) as $d ) {
        $rc = tao_formula_api( '/contas_pagar', 'POST', [
            'cliente_id' => $cliente_id, 'fornecedor_id' => $forn_id, 'entrada_nf_id' => $entrada_id,
            'numero_dup' => $d['numero_dup'] ?? null, 'vencimento' => $d['vencimento'] ?: null,
            'valor' => (float) ( $d['valor'] ?? 0 ), 'status' => 'aberto',
        ] );
        if ( $rc['ok'] ) $cp++;
    }

    wp_send_json_success( [
        'entrada_id' => $entrada_id, 'itens' => count( $itens ),
        'lotes' => $lotes_criados, 'movimentos' => $mov, 'depara_aprendidos' => $depara_novos, 'contas_pagar' => $cp,
    ] );
} );

// Lista de entradas de NF
add_action( 'wp_ajax_tao_formula_nf_lista', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( 'Acesso negado', 403 );
    $cliente_id = tao_formula_cliente_id();
    if ( ! $cliente_id ) wp_send_json_error( [ 'message' => 'Cliente não identificado' ] );
    $size   = in_array( intval( $_GET['size'] ?? 30 ), [ 20, 30, 50 ], true ) ? intval( $_GET['size'] ) : 30;
    $offset = max( 0, intval( $_GET['offset'] ?? 0 ) );
    $r = tao_formula_api(
        "/estoque_entradas_nf?cliente_id=eq.$cliente_id&select=id,numero,serie,cnpj_emitente,dt_entrada,valor_total,status,fornecedor_id&order=dt_entrada.desc,criado_em.desc&limit=$size&offset=$offset", 'GET', null, true
    );
    wp_send_json_success( [ 'items' => $r['ok'] ? ( $r['data'] ?? [] ) : [], 'total' => $r['ok'] ? (int) $r['total'] : 0 ] );
} );

// ═══════════════════════════════════════════════════════════════════════════
// ESTOQUE Fatia 2 — Lotes (CQ recebimento RDC 67) + saldo + kardex + inventário
// ═══════════════════════════════════════════════════════════════════════════

// Lista de lotes com nome do ativo. Filtros: status, busca (nome/lote).
add_action( 'wp_ajax_tao_formula_estq_lotes', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( 'Acesso negado', 403 );
    $cliente_id = tao_formula_cliente_id();
    if ( ! $cliente_id ) wp_send_json_error( [ 'message' => 'Cliente não identificado' ] );

    $status = sanitize_text_field( $_GET['status'] ?? '' );
    $q      = sanitize_text_field( $_GET['q'] ?? '' );
    $size   = in_array( intval( $_GET['size'] ?? 30 ), [ 20, 30, 50 ], true ) ? intval( $_GET['size'] ) : 30;
    $offset = max( 0, intval( $_GET['offset'] ?? 0 ) );
    $filtro = '';
    if ( in_array( $status, [ 'quarentena', 'aprovado', 'reprovado', 'esgotado', 'vencido' ], true ) ) $filtro .= "&status=eq.$status";
    if ( $q !== '' ) $filtro .= '&nr_lote=ilike.*' . rawurlencode( $q ) . '*';

    $r = tao_formula_api(
        "/lab_lotes_mp?cliente_id=eq.$cliente_id$filtro" .
        "&select=id,ativo_id,nr_lote,origem,dt_validade,qtd_atual,unidade,teor_pct,status,nf_numero,qc_em,laudo_url,nr_laudo,fabricante" .
        "&order=status.asc,dt_validade.asc&limit=200"
    );
    $lotes = $r['ok'] ? ( $r['data'] ?? [] ) : [];
    // nomes dos ativos
    $ids = array_values( array_unique( array_filter( array_column( $lotes, 'ativo_id' ) ) ) );
    $nomes = [];
    if ( $ids ) {
        $ra = tao_formula_api( "/ativos?cliente_id=eq.$cliente_id&id=in.(" . implode( ',', $ids ) . ")&select=id,nome,codigo_fc&limit=" . count( $ids ) );
        foreach ( ( $ra['ok'] ? $ra['data'] : [] ) as $a ) $nomes[ $a['id'] ] = $a;
    }
    foreach ( $lotes as &$l ) { $a = $nomes[ $l['ativo_id'] ] ?? null; $l['ativo_nome'] = $a['nome'] ?? '—'; $l['codigo_fc'] = $a['codigo_fc'] ?? ''; }
    unset( $l );
    // busca por nome do ativo (client não filtra no PostgREST embed): filtra aqui se q não casou lote
    if ( $q !== '' ) {
        $ql = mb_strtolower( $q );
        $lotes = array_values( array_filter( $lotes, fn( $l ) => mb_stripos( $l['nr_lote'] . ' ' . $l['ativo_nome'], $q ) !== false ) );
    }
    $total = count( $lotes );
    $lotes = array_slice( $lotes, $offset, $size );
    wp_send_json_success( [ 'items' => $lotes, 'total' => $total ] );
} );

// CQ de recebimento: aprovar/reprovar lote (RDC 67 — registra quem e quando)
add_action( 'wp_ajax_tao_formula_estq_lote_cq', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( [ 'message' => 'Acesso negado' ], 403 );
    $cliente_id = tao_formula_cliente_id();
    $lote_id = sanitize_text_field( $_POST['lote_id'] ?? '' );
    $acao    = sanitize_text_field( $_POST['acao'] ?? '' ); // aprovar|reprovar
    $result  = sanitize_text_field( $_POST['resultado'] ?? '' );
    if ( ! $cliente_id || ! $lote_id || ! in_array( $acao, [ 'aprovar', 'reprovar' ], true ) )
        wp_send_json_error( [ 'message' => 'Parâmetros inválidos' ] );
    $novo = $acao === 'aprovar' ? 'aprovado' : 'reprovado';
    $r = tao_formula_api( "/lab_lotes_mp?id=eq.$lote_id&cliente_id=eq.$cliente_id", 'PATCH', [
        'status'          => $novo,
        'qc_resultado'    => $result ?: null,
        'qc_aprovado_por' => get_current_user_id(),
        'qc_em'           => gmdate( 'c' ),
    ] );
    $r['ok'] ? wp_send_json_success() : wp_send_json_error( [ 'message' => mb_substr( (string) $r['raw'], 0, 200 ) ] );
} );

// Laudo/certificado de análise por lote (RDC 67): upload do PDF + nº do laudo.
add_action( 'wp_ajax_tao_formula_lote_laudo_upload', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( [ 'message' => 'Acesso negado' ], 403 );
    $cliente_id = tao_formula_cliente_id();
    $lote_id = sanitize_text_field( $_POST['lote_id'] ?? '' );
    if ( ! $cliente_id || ! $lote_id ) wp_send_json_error( [ 'message' => 'Parâmetros inválidos' ] );

    $patch = [ 'nr_laudo' => trim( sanitize_text_field( $_POST['nr_laudo'] ?? '' ) ) ?: null ];

    // upload opcional do PDF
    if ( ! empty( $_FILES['laudo'] ) && $_FILES['laudo']['error'] === UPLOAD_ERR_OK ) {
        $tipo = mime_content_type( $_FILES['laudo']['tmp_name'] );
        if ( ! in_array( $tipo, [ 'application/pdf', 'image/jpeg', 'image/png' ], true ) )
            wp_send_json_error( [ 'message' => 'Envie o laudo em PDF, JPG ou PNG.' ] );
        require_once ABSPATH . 'wp-admin/includes/file.php';
        add_filter( 'upload_dir', 'tao_formula_laudo_dir' );
        $up = wp_handle_upload( $_FILES['laudo'], [ 'test_form' => false ] );
        remove_filter( 'upload_dir', 'tao_formula_laudo_dir' );
        if ( isset( $up['error'] ) ) wp_send_json_error( [ 'message' => 'Falha no upload: ' . $up['error'] ] );
        $patch['laudo_url'] = $up['url'];
    }

    $r = tao_formula_api( "/lab_lotes_mp?id=eq.$lote_id&cliente_id=eq.$cliente_id", 'PATCH', $patch );
    $r['ok'] ? wp_send_json_success( [ 'laudo_url' => $patch['laudo_url'] ?? null, 'nr_laudo' => $patch['nr_laudo'] ] )
             : wp_send_json_error( [ 'message' => mb_substr( (string) $r['raw'], 0, 200 ) ] );
} );
// subdiretório dedicado p/ laudos (organiza os uploads)
function tao_formula_laudo_dir( $dirs ) {
    $dirs['subdir'] = '/laudos-mp' . $dirs['subdir'];
    $dirs['path']   = $dirs['basedir'] . $dirs['subdir'];
    $dirs['url']    = $dirs['baseurl'] . $dirs['subdir'];
    return $dirs;
}

// Inventário: acerta a qtd_atual de um lote e lança movimento de ajuste (com trilha)
add_action( 'wp_ajax_tao_formula_estq_ajuste', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( [ 'message' => 'Acesso negado' ], 403 );
    $cliente_id = tao_formula_cliente_id();
    $lote_id = sanitize_text_field( $_POST['lote_id'] ?? '' );
    $novo    = $_POST['qtd_nova'] ?? '';
    if ( ! $cliente_id || ! $lote_id || $novo === '' ) wp_send_json_error( [ 'message' => 'Parâmetros inválidos' ] );
    $novo = (float) str_replace( ',', '.', $novo );

    $rl = tao_formula_api( "/lab_lotes_mp?id=eq.$lote_id&cliente_id=eq.$cliente_id&select=ativo_id,qtd_atual&limit=1" );
    if ( ! $rl['ok'] || empty( $rl['data'] ) ) wp_send_json_error( [ 'message' => 'Lote não encontrado' ] );
    $lote = $rl['data'][0];
    $delta = round( $novo - (float) $lote['qtd_atual'], 6 );

    tao_formula_api( "/lab_lotes_mp?id=eq.$lote_id&cliente_id=eq.$cliente_id", 'PATCH', [ 'qtd_atual' => $novo ] );
    tao_formula_api( '/estoque_movimentos', 'POST', [
        'cliente_id' => $cliente_id, 'ativo_id' => $lote['ativo_id'], 'lote_id' => $lote_id,
        'tipo' => 'ajuste', 'quantidade' => $delta, 'origem' => 'inventario', 'saldo_apos' => $novo,
        'usuario_id' => get_current_user_id(),
    ] );
    wp_send_json_success( [ 'delta' => $delta ] );
} );

// Kardex: movimentos de um ativo (extrato)
add_action( 'wp_ajax_tao_formula_estq_kardex', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( 'Acesso negado', 403 );
    $cliente_id = tao_formula_cliente_id();
    $ativo_id = sanitize_text_field( $_GET['ativo_id'] ?? '' );
    if ( ! $cliente_id || ! $ativo_id ) wp_send_json_error( [ 'message' => 'Parâmetros inválidos' ] );
    $r = tao_formula_api(
        "/estoque_movimentos?cliente_id=eq.$cliente_id&ativo_id=eq.$ativo_id" .
        "&select=tipo,quantidade,origem,criado_em&order=criado_em.desc&limit=100"
    );
    wp_send_json_success( $r['ok'] ? ( $r['data'] ?? [] ) : [] );
} );

// ═══════════════════════════════════════════════════════════════════════════
// INVENTÁRIO EM MASSA — contagem geral congelada → ajustes em lote (FC1D000)
// ═══════════════════════════════════════════════════════════════════════════

// Abre uma sessão: congela o saldo de todos os lotes vivos como snapshot.
add_action( 'wp_ajax_tao_formula_inv_abrir', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( [ 'message' => 'Acesso negado' ], 403 );
    $cliente_id = tao_formula_cliente_id();
    if ( ! $cliente_id ) wp_send_json_error( [ 'message' => 'Cliente não identificado' ] );
    $descricao = trim( sanitize_text_field( $_POST['descricao'] ?? '' ) ) ?: ( 'Inventário ' . gmdate( 'd/m/Y' ) );
    $status_f  = sanitize_text_field( $_POST['status_lote'] ?? 'aprovado' ); // escopo: aprovado (padrão) ou todos

    // lotes vivos (com saldo) — escopo do inventário
    $filtro = "&qtd_atual=gte.0";
    if ( $status_f === 'aprovado' ) $filtro .= "&status=eq.aprovado";
    else $filtro .= "&status=not.in.(esgotado,reprovado,vencido)";
    $rl = tao_formula_api( "/lab_lotes_mp?cliente_id=eq.$cliente_id$filtro&select=id,ativo_id,nr_lote,qtd_atual,unidade&order=id.asc&limit=5000" );
    $lotes = $rl['ok'] ? ( $rl['data'] ?? [] ) : [];
    if ( ! $lotes ) wp_send_json_error( [ 'message' => 'Nenhum lote no escopo selecionado.' ] );

    // nomes dos ativos
    $ids = array_values( array_unique( array_filter( array_column( $lotes, 'ativo_id' ) ) ) );
    $nomes = [];
    foreach ( array_chunk( $ids, 300 ) as $ch ) {
        $ra = tao_formula_api( "/ativos?id=in.(" . implode( ',', $ch ) . ")&select=id,nome&limit=" . count( $ch ) );
        foreach ( ( $ra['ok'] ? $ra['data'] : [] ) as $a ) $nomes[ $a['id'] ] = $a['nome'];
    }

    $cab = tao_formula_api( '/lab_inventario', 'POST', [
        'cliente_id' => $cliente_id, 'descricao' => $descricao, 'status' => 'aberta',
        'filtro' => $status_f, 'total_itens' => count( $lotes ), 'criado_por' => get_current_user_id(),
    ] );
    if ( ! $cab['ok'] || empty( $cab['data'] ) ) wp_send_json_error( [ 'message' => 'Erro ao abrir: ' . mb_substr( (string) $cab['raw'], 0, 200 ) ] );
    $inv_id = $cab['data'][0]['id'];

    // snapshot dos itens (em lotes de 200 no POST)
    $itens = [];
    foreach ( $lotes as $l ) {
        $itens[] = [
            'inventario_id' => $inv_id, 'lote_id' => $l['id'], 'ativo_id' => $l['ativo_id'],
            'ativo_nome' => $nomes[ $l['ativo_id'] ] ?? '—', 'nr_lote' => $l['nr_lote'],
            'unidade' => $l['unidade'], 'qtd_sistema' => (float) $l['qtd_atual'], 'contado' => false,
        ];
    }
    foreach ( array_chunk( $itens, 200 ) as $ch ) tao_formula_api( '/lab_inventario_itens', 'POST', $ch );
    wp_send_json_success( [ 'id' => $inv_id, 'total' => count( $itens ) ] );
} );

// Lista de sessões de inventário
add_action( 'wp_ajax_tao_formula_inv_lista', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( [ 'message' => 'Acesso negado' ], 403 );
    $cliente_id = tao_formula_cliente_id();
    if ( ! $cliente_id ) wp_send_json_error( [ 'message' => 'Cliente não identificado' ] );
    $size   = in_array( intval( $_GET['size'] ?? 30 ), [ 20, 30, 50 ], true ) ? intval( $_GET['size'] ) : 30;
    $offset = max( 0, intval( $_GET['offset'] ?? 0 ) );
    $r = tao_formula_api( "/lab_inventario?cliente_id=eq.$cliente_id&select=id,descricao,status,total_itens,total_diverg,dt_abertura,dt_fechamento,responsavel&order=dt_abertura.desc&limit=$size&offset=$offset", 'GET', null, true );
    wp_send_json_success( [ 'items' => $r['ok'] ? ( $r['data'] ?? [] ) : [], 'total' => $r['ok'] ? (int) $r['total'] : 0 ] );
} );

// Detalhe da sessão + itens (para a planilha de contagem)
add_action( 'wp_ajax_tao_formula_inv_get', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( 'Acesso negado', 403 );
    $cliente_id = tao_formula_cliente_id();
    $inv_id = sanitize_text_field( $_GET['id'] ?? '' );
    if ( ! $cliente_id || ! $inv_id ) wp_send_json_error( [ 'message' => 'Parâmetros inválidos' ] );
    $rc = tao_formula_api( "/lab_inventario?id=eq.$inv_id&cliente_id=eq.$cliente_id&limit=1" );
    if ( ! $rc['ok'] || empty( $rc['data'] ) ) wp_send_json_error( [ 'message' => 'Inventário não encontrado' ] );
    $ri = tao_formula_api( "/lab_inventario_itens?inventario_id=eq.$inv_id&select=id,ativo_nome,nr_lote,unidade,qtd_sistema,qtd_contada,diferenca,contado&order=ativo_nome.asc&limit=5000" );
    wp_send_json_success( [ 'inv' => $rc['data'][0], 'itens' => $ri['ok'] ? ( $ri['data'] ?? [] ) : [] ] );
} );

// Grava a contagem de um item (qtd real contada)
add_action( 'wp_ajax_tao_formula_inv_contar', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( [ 'message' => 'Acesso negado' ], 403 );
    $item_id = sanitize_text_field( $_POST['item_id'] ?? '' );
    $qtd     = $_POST['qtd_contada'] ?? '';
    if ( ! $item_id ) wp_send_json_error( [ 'message' => 'Item inválido' ] );
    $rl = tao_formula_api( "/lab_inventario_itens?id=eq.$item_id&select=qtd_sistema&limit=1" );
    if ( ! $rl['ok'] || empty( $rl['data'] ) ) wp_send_json_error( [ 'message' => 'Item não encontrado' ] );
    if ( $qtd === '' ) {
        $r = tao_formula_api( "/lab_inventario_itens?id=eq.$item_id", 'PATCH', [ 'qtd_contada' => null, 'diferenca' => null, 'contado' => false ] );
    } else {
        $c = (float) str_replace( ',', '.', $qtd );
        $dif = round( $c - (float) $rl['data'][0]['qtd_sistema'], 6 );
        $r = tao_formula_api( "/lab_inventario_itens?id=eq.$item_id", 'PATCH', [ 'qtd_contada' => $c, 'diferenca' => $dif, 'contado' => true ] );
    }
    $r['ok'] ? wp_send_json_success() : wp_send_json_error( [ 'message' => mb_substr( (string) $r['raw'], 0, 200 ) ] );
} );

// Fecha o inventário: aplica os ajustes (qtd_atual = contada) e lança movimentos.
// Só itens CONTADOS com diferença != 0 geram ajuste. Não contados ficam como estão.
add_action( 'wp_ajax_tao_formula_inv_fechar', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( [ 'message' => 'Acesso negado' ], 403 );
    $cliente_id = tao_formula_cliente_id();
    $inv_id = sanitize_text_field( $_POST['id'] ?? '' );
    if ( ! $cliente_id || ! $inv_id ) wp_send_json_error( [ 'message' => 'Parâmetros inválidos' ] );
    $rc = tao_formula_api( "/lab_inventario?id=eq.$inv_id&cliente_id=eq.$cliente_id&select=status&limit=1" );
    if ( ! $rc['ok'] || empty( $rc['data'] ) ) wp_send_json_error( [ 'message' => 'Inventário não encontrado' ] );
    if ( $rc['data'][0]['status'] !== 'aberta' ) wp_send_json_error( [ 'message' => 'Inventário já encerrado.' ] );

    $ri = tao_formula_api( "/lab_inventario_itens?inventario_id=eq.$inv_id&contado=eq.true&select=lote_id,ativo_id,qtd_contada,diferenca&limit=5000" );
    $itens = $ri['ok'] ? ( $ri['data'] ?? [] ) : [];
    $ajustes = 0;
    foreach ( $itens as $it ) {
        $dif = (float) ( $it['diferenca'] ?? 0 );
        if ( ! $it['lote_id'] || abs( $dif ) < 1e-9 ) continue;
        $novo = (float) $it['qtd_contada'];
        $upd = [ 'qtd_atual' => $novo ]; if ( $novo <= 0 ) $upd['status'] = 'esgotado';
        tao_formula_api( "/lab_lotes_mp?id=eq.{$it['lote_id']}&cliente_id=eq.$cliente_id", 'PATCH', $upd );
        tao_formula_api( '/estoque_movimentos', 'POST', [
            'cliente_id' => $cliente_id, 'ativo_id' => $it['ativo_id'], 'lote_id' => $it['lote_id'],
            'tipo' => 'ajuste', 'quantidade' => $dif, 'origem' => 'inventario', 'ref_id' => $inv_id,
            'saldo_apos' => $novo, 'usuario_id' => get_current_user_id(),
        ] );
        $ajustes++;
    }
    tao_formula_api( "/lab_inventario?id=eq.$inv_id&cliente_id=eq.$cliente_id", 'PATCH', [
        'status' => 'fechada', 'total_diverg' => $ajustes, 'dt_fechamento' => gmdate( 'c' ),
        'responsavel' => wp_get_current_user()->display_name ?: null,
    ] );
    wp_send_json_success( [ 'ajustes' => $ajustes ] );
} );

// Cancela um inventário aberto (não aplica nada)
add_action( 'wp_ajax_tao_formula_inv_cancelar', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( [ 'message' => 'Acesso negado' ], 403 );
    $cliente_id = tao_formula_cliente_id();
    $inv_id = sanitize_text_field( $_POST['id'] ?? '' );
    if ( ! $cliente_id || ! $inv_id ) wp_send_json_error( [ 'message' => 'Parâmetros inválidos' ] );
    $r = tao_formula_api( "/lab_inventario?id=eq.$inv_id&cliente_id=eq.$cliente_id&status=eq.aberta", 'PATCH', [ 'status' => 'cancelada', 'dt_fechamento' => gmdate( 'c' ) ] );
    $r['ok'] ? wp_send_json_success() : wp_send_json_error( [ 'message' => mb_substr( (string) $r['raw'], 0, 200 ) ] );
} );

// ═══════════════════════════════════════════════════════════════════════════
// ESTOQUE Fatia 3 — Mínimo/curva + alerta de reposição → Cotações
// ═══════════════════════════════════════════════════════════════════════════

// Saldo por ativo = soma dos lotes vivos (não reprovado/vencido/esgotado).
function tao_formula_saldo_por_ativo( $cliente_id, $ativo_ids ) {
    $saldo = array_fill_keys( $ativo_ids, 0.0 );
    if ( ! $ativo_ids ) return $saldo;
    $in = implode( ',', $ativo_ids );
    $r = tao_formula_api(
        "/lab_lotes_mp?cliente_id=eq.$cliente_id&ativo_id=in.($in)" .
        "&status=not.in.(reprovado,vencido,esgotado)&select=ativo_id,qtd_atual&limit=5000"
    );
    foreach ( ( $r['ok'] ? $r['data'] : [] ) as $l )
        $saldo[ $l['ativo_id'] ] = ( $saldo[ $l['ativo_id'] ] ?? 0 ) + (float) $l['qtd_atual'];
    return $saldo;
}

// Lista de reposição: ativos com est_min definido + saldo + flag abaixo do mínimo
add_action( 'wp_ajax_tao_formula_estq_reposicao', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( 'Acesso negado', 403 );
    $cliente_id = tao_formula_cliente_id();
    if ( ! $cliente_id ) wp_send_json_error( [ 'message' => 'Cliente não identificado' ] );
    $so_abaixo = ( $_GET['so_abaixo'] ?? '' ) === '1';

    $r = tao_formula_api(
        "/ativos?cliente_id=eq.$cliente_id&est_min=not.is.null&ativo=eq.true" .
        "&select=id,codigo_fc,nome,unidade_padrao,est_min,est_max,curva,preco_compra&order=nome.asc&limit=500"
    );
    $ativos = $r['ok'] ? ( $r['data'] ?? [] ) : [];
    $saldos = tao_formula_saldo_por_ativo( $cliente_id, array_column( $ativos, 'id' ) );
    $out = [];
    foreach ( $ativos as $a ) {
        $saldo  = $saldos[ $a['id'] ] ?? 0;
        $abaixo = $saldo < (float) $a['est_min'];
        if ( $so_abaixo && ! $abaixo ) continue;
        $a['saldo']  = $saldo;
        $a['abaixo'] = $abaixo;
        $a['sugerido'] = $a['est_max'] ? max( 0, (float) $a['est_max'] - $saldo ) : max( 0, (float) $a['est_min'] - $saldo );
        $out[] = $a;
    }
    wp_send_json_success( $out );
} );

// Define/edita mínimo, máximo e curva de um ativo
add_action( 'wp_ajax_tao_formula_estq_def_min', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( [ 'message' => 'Acesso negado' ], 403 );
    $cliente_id = tao_formula_cliente_id();
    $ativo_id = sanitize_text_field( $_POST['ativo_id'] ?? '' );
    if ( ! $cliente_id || ! $ativo_id ) wp_send_json_error( [ 'message' => 'Parâmetros inválidos' ] );
    $num = function( $k ) { $v = trim( (string) ( $_POST[ $k ] ?? '' ) ); return $v === '' ? null : (float) str_replace( ',', '.', $v ); };
    $curva = strtoupper( sanitize_text_field( $_POST['curva'] ?? '' ) );
    $r = tao_formula_api( "/ativos?id=eq.$ativo_id&cliente_id=eq.$cliente_id", 'PATCH', [
        'est_min' => $num( 'est_min' ), 'est_max' => $num( 'est_max' ),
        'curva'   => in_array( $curva, [ 'A', 'B', 'C' ], true ) ? $curva : null,
    ] );
    $r['ok'] ? wp_send_json_success() : wp_send_json_error( [ 'message' => mb_substr( (string) $r['raw'], 0, 200 ) ] );
} );

// Gera uma cotação com os ativos selecionados (origem=reposicao)
add_action( 'wp_ajax_tao_formula_estq_gerar_cotacao', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( [ 'message' => 'Acesso negado' ], 403 );
    $cliente_id = tao_formula_cliente_id();
    $itens = json_decode( stripslashes( $_POST['itens'] ?? '' ), true );
    if ( ! $cliente_id || ! is_array( $itens ) || ! $itens )
        wp_send_json_error( [ 'message' => 'Selecione ao menos um item' ] );

    // cabeçalho da cotação
    $rc = tao_formula_api( '/cotacoes', 'POST', [
        'cliente_id' => $cliente_id,
        'titulo'     => 'Reposição de estoque — ' . date_i18n( 'd/m/Y H:i' ),
        'status'     => 'rascunho',
        'criado_por' => get_current_user_id(),
    ] );
    if ( ! $rc['ok'] || empty( $rc['data'] ) )
        wp_send_json_error( [ 'message' => 'Erro ao criar cotação: ' . mb_substr( (string) $rc['raw'], 0, 200 ) ] );
    $cot_id = $rc['data'][0]['id'];

    $linhas = [];
    foreach ( $itens as $it ) {
        $linhas[] = [
            'cotacao_id'    => $cot_id,
            'ativo_id'      => $it['ativo_id'] ?? null,
            'codigo_fc'     => $it['codigo_fc'] ?? null,
            'descricao'     => $it['nome'] ?? '',
            'unidade'       => $it['unidade_padrao'] ?? null,
            'qtd'           => (float) ( $it['sugerido'] ?? 0 ) ?: null,
            'ult_preco_pago'=> isset( $it['preco_compra'] ) ? (float) $it['preco_compra'] : null,
            'origem'        => 'reposicao',
        ];
    }
    tao_formula_api( '/cotacao_itens', 'POST', $linhas );
    wp_send_json_success( [ 'cotacao_id' => $cot_id, 'itens' => count( $linhas ) ] );
} );

// ═══════════════════════════════════════════════════════════════════════════
// PRODUÇÃO — Ordem de Manipulação (Pacote 3 / Fatia A)
// ═══════════════════════════════════════════════════════════════════════════

// 1ª etapa (menor ordem) do kanban
function tao_formula_etapa_inicial( $cliente_id ) {
    $r = tao_formula_api( "/lab_etapas?cliente_id=eq.$cliente_id&ativo=eq.true&order=ordem.asc&select=id&limit=1" );
    return ( $r['ok'] && ! empty( $r['data'] ) ) ? $r['data'][0]['id'] : null;
}

// Gera a OM a partir de um orçamento (copia paciente, prescritor, forma e itens)
add_action( 'wp_ajax_tao_formula_prod_gerar_om', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( [ 'message' => 'Acesso negado' ], 403 );
    $cliente_id = tao_formula_cliente_id();
    $orc_id = sanitize_text_field( $_POST['orc_id'] ?? '' );
    if ( ! $cliente_id || ! $orc_id ) wp_send_json_error( [ 'message' => 'Parâmetros inválidos' ] );

    $res = tao_formula_criar_om( $cliente_id, $orc_id );
    if ( empty( $res['ok'] ) ) wp_send_json_error( [ 'message' => $res['erro'] ?? 'Erro ao gerar OM' ] );
    wp_send_json_success( [ 'ordem_id' => $res['ordem_id'], 'itens' => $res['itens'] ] );
} );

/**
 * Cria a OM a partir de um orçamento. REUSÁVEL: tela Produção (handler acima) e gatilho do
 * ganho (card-ganho.php). Idempotente por orçamento (não cria 2 OMs).
 * Retorna: ['ok'=>bool, 'ordem_id'=>uuid|null, 'itens'=>int, 'ja_existia'=>bool, 'erro'=>string|null].
 */
function tao_formula_criar_om( $cliente_id, $orc_id ) {
    // já tem OM p/ este orçamento? (cancelada não conta — estorno permite gerar de novo)
    $jx = tao_formula_api( "/lab_ordens?cliente_id=eq.$cliente_id&orcamento_id=eq.$orc_id&status=neq.cancelada&select=id,numero&limit=1" );
    if ( $jx['ok'] && ! empty( $jx['data'] ) )
        return [ 'ok' => false, 'ja_existia' => true, 'ordem_id' => $jx['data'][0]['id'], 'itens' => 0, 'erro' => 'Este orçamento já tem OM (nº ' . $jx['data'][0]['numero'] . ').' ];

    $ro = tao_formula_api( "/orcamentos?id=eq.$orc_id&cliente_id=eq.$cliente_id&limit=1" );
    if ( ! $ro['ok'] || empty( $ro['data'] ) ) return [ 'ok' => false, 'ordem_id' => null, 'itens' => 0, 'ja_existia' => false, 'erro' => 'Orçamento não encontrado' ];
    $o = $ro['data'][0];

    // validade padrão por forma farmacêutica — PARÂMETRO CADASTRÁVEL (equivalente ao PRAZOVALxx do FCerta),
    // lido de formas_farmaceuticas.validade_dias. Fallback 120 se a forma ainda não tiver o parâmetro.
    $dias = 120; $modo_preparo = null;
    if ( ! empty( $o['forma_id'] ) ) {
        $rf = tao_formula_api( "/formas_farmaceuticas?id=eq.{$o['forma_id']}&cliente_id=eq.$cliente_id&select=validade_dias,modo_preparo&limit=1" );
        if ( $rf['ok'] && ! empty( $rf['data'] ) ) {
            if ( ! empty( $rf['data'][0]['validade_dias'] ) ) $dias = (int) $rf['data'][0]['validade_dias'];
            $modo_preparo = $rf['data'][0]['modo_preparo'] ?? null;   // herda o modo de preparo padrão da forma
        }
    }

    $payload = [
        'cliente_id'    => $cliente_id,
        'orcamento_id'  => $orc_id,
        'numero'        => $o['numero_orcamento'] ?? null,   // OM = orçamento aprovado → mesmo número (único, ligado ao card)
        'card_id'       => $o['card_id'] ?? null,
        'contato_id'    => $o['contato_id'] ?? null,
        'paciente_nome' => $o['nome_paciente'] ?: 'Paciente',
        'paciente_whats'=> $o['whatsapp'] ?? null,
        'prescritor_id' => $o['prescritor_id'] ?? null,
        'posologia'     => $o['posologia'] ?? null,
        'forma_farmac'  => $o['forma_nome'] ?? null,
        'volume'        => $o['forma_vol'] ?? null,
        'unidade_vol'   => $o['forma_unidade'] ?? null,
        'qtd_unidades'  => $o['qtde_potes'] ?? 1,
        'modo_preparo'      => $modo_preparo,
        'dt_prescricao'     => $o['dt_prescricao'] ?? null,       // herdado do orçamento (ficha)
        'previsao_retirada' => $o['previsao_retirada'] ?? null,   // herdado do orçamento (ficha)
        // Controlados 344/98 — herdados do orçamento (colunas da OM = Pacote 4)
        'tp_receita'        => $o['tp_receita']       ?? null,
        'nr_notificacao'    => $o['nr_notificacao']   ?? null,
        'comprador_nome'    => $o['comprador_nome']   ?? null,
        'comprador_doc_tp'  => $o['comprador_doc_tp'] ?? null,
        'comprador_doc_nr'  => $o['comprador_doc_nr'] ?? null,
        'dt_validade'   => gmdate( 'Y-m-d', strtotime( "+$dias days" ) ),
        'etapa_id'      => tao_formula_etapa_inicial( $cliente_id ),
        'status'        => 'aberta',
        'criado_por'    => get_current_user_id(),
    ];
    $cab = tao_formula_api( '/lab_ordens', 'POST', $payload );
    // migration_ficha_om_v1 pendente? o banco recusa a coluna → remove e recria (não bloqueia a produção).
    if ( ! $cab['ok'] && strpos( (string) ( $cab['raw'] ?? '' ), 'column' ) !== false ) {
        unset( $payload['dt_prescricao'], $payload['previsao_retirada'] );
        $cab = tao_formula_api( '/lab_ordens', 'POST', $payload );
    }
    if ( ! $cab['ok'] && ! empty( $payload['numero'] ) ) {
        // o banco recusou o número informado (trava/trigger na coluna) → cria com o número padrão;
        // NUNCA bloqueia a produção por causa do número.
        unset( $payload['numero'] );
        $cab = tao_formula_api( '/lab_ordens', 'POST', $payload );
    }
    if ( ! $cab['ok'] || empty( $cab['data'] ) )
        return [ 'ok' => false, 'ordem_id' => null, 'itens' => 0, 'ja_existia' => false, 'erro' => 'Erro ao criar OM: ' . mb_substr( (string) $cab['raw'], 0, 200 ) ];
    $ordem_id = $cab['data'][0]['id'];

    // itens (do jsonb do orçamento)
    $itens = $o['itens'] ?? [];
    if ( is_string( $itens ) ) $itens = json_decode( $itens, true ) ?: [];
    $linhas = []; $i = 0;
    foreach ( (array) $itens as $it ) {
        if ( ( $it['tipo'] ?? 'mp' ) !== 'mp' ) continue;
        $linhas[] = [
            'ordem_id'      => $ordem_id,
            'ativo_id'      => $it['ativo_id'] ?? null,
            // descricao = a PRESCRIÇÃO (sinônimo) — usada no rótulo e no livro (o cliente reconhece).
            // A ficha de pesagem usa o ativo origem (nome do produto), resolvido pelo ativo_id.
            'descricao'     => $it['nome_prescricao'] ?? $it['nome'] ?? '—',
            'qtd_prescrita' => isset( $it['dose'] ) ? (float) $it['dose'] : null,
            'unidade'       => $it['dose_unit'] ?? null,
            // Quantidade a PESAR calculada no orçamento (o que o manipulador coloca na balança) + correções aplicadas.
            'qtd_pesar'     => isset( $it['qtd_total_g'] ) ? round( (float) $it['qtd_total_g'], 4 ) : null,
            'unid_pesar'    => 'g',
            'teor_aplic'    => isset( $it['teor'] ) ? (float) $it['teor'] : null,
            'equiv_aplic'   => isset( $it['equiv'] ) ? (float) $it['equiv'] : null,
            'diluicao_aplic'=> isset( $it['diluicao'] ) ? (float) $it['diluicao'] : null,
            'eh_qsp'        => ! empty( $it['is_qsp'] ),
            'ordem'         => $i++,
        ];
    }
    if ( $linhas ) tao_formula_api( '/lab_ordem_itens', 'POST', $linhas );

    return [ 'ok' => true, 'ordem_id' => $ordem_id, 'itens' => count( $linhas ), 'ja_existia' => false, 'erro' => null ];
}

// Kanban: OMs abertas agrupadas por etapa
add_action( 'wp_ajax_tao_formula_prod_kanban', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( 'Acesso negado', 403 );
    $cliente_id = tao_formula_cliente_id();
    if ( ! $cliente_id ) wp_send_json_error( [ 'message' => 'Cliente não identificado' ] );

    $re = tao_formula_api( "/lab_etapas?cliente_id=eq.$cliente_id&ativo=eq.true&order=ordem.asc&select=id,nome,ordem,tipo" );
    $etapas = $re['ok'] ? ( $re['data'] ?? [] ) : [];
    $ro = tao_formula_api(
        "/lab_ordens?cliente_id=eq.$cliente_id&status=eq.aberta" .
        "&select=id,numero,paciente_nome,forma_farmac,volume,unidade_vol,qtd_unidades,dt_validade,etapa_id,controlado&order=criado_em.asc&limit=300"
    );
    wp_send_json_success( [ 'etapas' => $etapas, 'ordens' => $ro['ok'] ? ( $ro['data'] ?? [] ) : [] ] );
} );

// Move a OM para outra etapa (auditoria). Etapa 'final' → conclui + baixa estoque.
add_action( 'wp_ajax_tao_formula_prod_mover', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( [ 'message' => 'Acesso negado' ], 403 );
    $cliente_id = tao_formula_cliente_id();
    $ordem_id = sanitize_text_field( $_POST['ordem_id'] ?? '' );
    $etapa_id = sanitize_text_field( $_POST['etapa_id'] ?? '' );
    if ( ! $cliente_id || ! $ordem_id || ! $etapa_id ) wp_send_json_error( [ 'message' => 'Parâmetros inválidos' ] );

    $ro = tao_formula_api( "/lab_ordens?id=eq.$ordem_id&cliente_id=eq.$cliente_id&select=etapa_id,baixou_estoque,controlado,tp_receita,nr_notificacao,comprador_nome,prescritor_id&limit=1" );
    if ( ! $ro['ok'] || empty( $ro['data'] ) ) wp_send_json_error( [ 'message' => 'OM não encontrada' ] );
    $de = $ro['data'][0]['etapa_id'];

    $re = tao_formula_api( "/lab_etapas?id=eq.$etapa_id&cliente_id=eq.$cliente_id&select=tipo,nome&limit=1" );
    $etapa = ( $re['ok'] && ! empty( $re['data'] ) ) ? $re['data'][0] : [];
    $final = ( $etapa['tipo'] ?? '' ) === 'final';

    // TRAVA Portaria 344/98: não conclui OM de CONTROLADO sem os dados da receita.
    if ( $final && ! empty( $ro['data'][0]['controlado'] ) ) {
        $d = $ro['data'][0]; $faltam = [];
        if ( empty( $d['tp_receita'] ) )     $faltam[] = 'tipo de receita';
        if ( empty( $d['nr_notificacao'] ) ) $faltam[] = 'nº da notificação/receita';
        if ( empty( $d['comprador_nome'] ) ) $faltam[] = 'comprador';
        if ( empty( $d['prescritor_id'] ) )  $faltam[] = 'prescritor';
        if ( $faltam ) wp_send_json_error( [
            'code'    => 'controlado_incompleto',
            'message' => 'Medicamento sujeito a controle especial (Portaria 344/98): informe ' . implode( ', ', $faltam ) . ' na aba "Receita controlada" antes de concluir a OM.',
        ] );
    }

    $patch = [ 'etapa_id' => $etapa_id ];
    if ( $final ) { $patch['status'] = 'concluida'; $patch['concluido_em'] = gmdate( 'c' ); $patch['conferente_id'] = get_current_user_id(); }
    tao_formula_api( "/lab_ordens?id=eq.$ordem_id&cliente_id=eq.$cliente_id", 'PATCH', $patch );
    tao_formula_api( '/lab_ordem_etapas', 'POST', [
        'ordem_id' => $ordem_id, 'de_etapa' => $de, 'para_etapa' => $etapa_id, 'usuario_id' => get_current_user_id(),
    ] );

    // baixa de estoque na conclusão (idempotente)
    $baixa = null; $validade = null;
    if ( $final ) {
        // Validade pela regra do FCerta (VALIDADELOTE): menor entre a validade por forma e a
        // menor validade dos lotes de MP efetivamente pesados. Alerta se o lote reduziu a validade.
        $validade = tao_formula_recalc_validade_om( $cliente_id, $ordem_id );
        if ( empty( $ro['data'][0]['baixou_estoque'] ) ) $baixa = tao_formula_baixar_estoque_om( $cliente_id, $ordem_id );
    }

    wp_send_json_success( [ 'concluida' => $final, 'baixa' => $baixa, 'validade' => $validade ] );
} );

// Salva os dados da receita controlada na OM (Portaria 344/98) — usados na escrituração SNGPC e na trava.
add_action( 'wp_ajax_tao_formula_prod_receita_ctrl', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( [ 'message' => 'Acesso negado' ], 403 );
    $cliente_id = tao_formula_cliente_id();
    $ordem_id = sanitize_text_field( $_POST['ordem_id'] ?? '' );
    if ( ! $cliente_id || ! $ordem_id ) wp_send_json_error( [ 'message' => 'Parâmetros inválidos' ] );
    $txt = function ( $k ) { $v = trim( sanitize_text_field( $_POST[ $k ] ?? '' ) ); return $v === '' ? null : $v; };
    $r = tao_formula_api( "/lab_ordens?id=eq.$ordem_id&cliente_id=eq.$cliente_id", 'PATCH', [
        'tp_receita'       => $txt( 'tp_receita' ),
        'nr_notificacao'   => $txt( 'nr_notificacao' ),
        'comprador_nome'   => $txt( 'comprador_nome' ),
        'comprador_doc_tp' => $txt( 'comprador_doc_tp' ),
        'comprador_doc_nr' => $txt( 'comprador_doc_nr' ),
    ] );
    $r['ok'] ? wp_send_json_success() : wp_send_json_error( [ 'message' => 'Erro ao salvar: ' . mb_substr( (string) $r['raw'], 0, 160 ) ] );
} );

// Salva o modo de preparo / precauções da OM (RDC 67/BPF) — herdado da forma, ajustável na manipulação.
add_action( 'wp_ajax_tao_formula_prod_modo_preparo', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( [ 'message' => 'Acesso negado' ], 403 );
    $cliente_id = tao_formula_cliente_id();
    $ordem_id = sanitize_text_field( $_POST['ordem_id'] ?? '' );
    if ( ! $cliente_id || ! $ordem_id ) wp_send_json_error( [ 'message' => 'Parâmetros inválidos' ] );
    $mp = trim( (string) wp_unslash( $_POST['modo_preparo'] ?? '' ) ) ?: null;
    $r = tao_formula_api( "/lab_ordens?id=eq.$ordem_id&cliente_id=eq.$cliente_id", 'PATCH', [ 'modo_preparo' => $mp ] );
    $r['ok'] ? wp_send_json_success() : wp_send_json_error( [ 'message' => 'Erro ao salvar' ] );
} );

// Recalcula a validade da OM considerando os LOTES usados (regra FCerta "VALIDADELOTE"):
// validade final = MENOR entre a validade por forma farmacêutica (já gravada) e a menor
// validade dos lotes de MP pesados. Atualiza dt_validade e sinaliza se o lote a reduziu.
function tao_formula_recalc_validade_om( $cliente_id, $ordem_id ) {
    $ro = tao_formula_api( "/lab_ordens?id=eq.$ordem_id&cliente_id=eq.$cliente_id&select=dt_validade&limit=1" );
    if ( ! $ro['ok'] || empty( $ro['data'] ) ) return null;
    $base = $ro['data'][0]['dt_validade'] ?? null;   // validade por forma (Y-m-d)

    $ri = tao_formula_api( "/lab_ordem_itens?ordem_id=eq.$ordem_id&lote_mp_id=not.is.null&select=lote_mp_id&limit=200" );
    $lote_ids = array_values( array_unique( array_filter( array_column( $ri['ok'] ? ( $ri['data'] ?? [] ) : [], 'lote_mp_id' ) ) ) );
    if ( ! $lote_ids ) return [ 'validade' => $base, 'reduzida_por_lote' => false ];

    $rl = tao_formula_api( "/lab_lotes_mp?id=in.(" . implode( ',', $lote_ids ) . ")&select=dt_validade" );
    $menor_lote = null;
    foreach ( ( $rl['ok'] ? ( $rl['data'] ?? [] ) : [] ) as $l ) {
        $dv = $l['dt_validade'] ?? null;
        if ( $dv && ( $menor_lote === null || $dv < $menor_lote ) ) $menor_lote = $dv;
    }
    if ( ! $menor_lote ) return [ 'validade' => $base, 'reduzida_por_lote' => false ];

    $final    = ( $base && $base <= $menor_lote ) ? $base : $menor_lote;
    $reduzida = ( $base && $final < $base );
    if ( $reduzida ) {
        tao_formula_api( "/lab_ordens?id=eq.$ordem_id&cliente_id=eq.$cliente_id", 'PATCH', [ 'dt_validade' => $final ] );
    }
    return [ 'validade' => $final, 'reduzida_por_lote' => $reduzida, 'validade_forma' => $base, 'menor_validade_lote' => $menor_lote ];
}

// Baixa de estoque da OM: por item com lote+pesagem, debita o lote e lança kardex
function tao_formula_baixar_estoque_om( $cliente_id, $ordem_id ) {
    $ri = tao_formula_api( "/lab_ordem_itens?ordem_id=eq.$ordem_id&select=ativo_id,lote_mp_id,qtd_pesada&limit=200" );
    $itens = $ri['ok'] ? ( $ri['data'] ?? [] ) : [];
    $baixados = 0;
    foreach ( $itens as $it ) {
        if ( empty( $it['lote_mp_id'] ) || ! ( (float) ( $it['qtd_pesada'] ?? 0 ) > 0 ) ) continue;
        $qtd = (float) $it['qtd_pesada'];
        // debita o lote
        $rl = tao_formula_api( "/lab_lotes_mp?id=eq.{$it['lote_mp_id']}&select=qtd_atual&limit=1" );
        if ( $rl['ok'] && ! empty( $rl['data'] ) ) {
            $novo = max( 0, (float) $rl['data'][0]['qtd_atual'] - $qtd );
            $upd = [ 'qtd_atual' => $novo ];
            if ( $novo <= 0 ) $upd['status'] = 'esgotado';
            tao_formula_api( "/lab_lotes_mp?id=eq.{$it['lote_mp_id']}", 'PATCH', $upd );
        }
        // kardex
        tao_formula_api( '/estoque_movimentos', 'POST', [
            'cliente_id' => $cliente_id, 'ativo_id' => $it['ativo_id'], 'lote_id' => $it['lote_mp_id'],
            'tipo' => 'saida', 'quantidade' => -$qtd, 'origem' => 'om', 'ref_id' => $ordem_id,
            'usuario_id' => get_current_user_id(),
        ] );
        $baixados++;
    }
    tao_formula_api( "/lab_ordens?id=eq.$ordem_id&cliente_id=eq.$cliente_id", 'PATCH', [ 'baixou_estoque' => true ] );
    // Escrituração SNGPC: se a OM é controlada, lança saída dos componentes controlados
    tao_formula_escriturar_om_controlada( $cliente_id, $ordem_id );
    return $baixados;
}

// Gera saída SNGPC dos itens controlados de uma OM controlada (idempotente por ref_id)
function tao_formula_escriturar_om_controlada( $cliente_id, $ordem_id ) {
    $ro = tao_formula_api( "/lab_ordens?id=eq.$ordem_id&cliente_id=eq.$cliente_id&select=controlado,tp_receita,nr_notificacao,comprador_nome,comprador_doc_tp,comprador_doc_nr,prescritor_id,dt_manipulacao&limit=1" );
    if ( ! $ro['ok'] || empty( $ro['data'] ) || empty( $ro['data'][0]['controlado'] ) ) return 0;
    $o = $ro['data'][0];
    // já escriturada?
    $jx = tao_formula_api( "/sngpc_movimentos?cliente_id=eq.$cliente_id&ref_id=eq.$ordem_id&origem=eq.om&select=id&limit=1" );
    if ( $jx['ok'] && ! empty( $jx['data'] ) ) return 0;

    // prescritor (nome/conselho)
    $pn = $pc = $pnr = $puf = null;
    if ( ! empty( $o['prescritor_id'] ) ) {
        $rp = tao_formula_api( "/prescritores?id=eq.{$o['prescritor_id']}&select=nome,tipo_registro,nr_registro,uf_registro&limit=1" );
        if ( $rp['ok'] && ! empty( $rp['data'] ) ) { $p = $rp['data'][0]; $pn = $p['nome']; $pc = $p['tipo_registro']; $pnr = $p['nr_registro']; $puf = $p['uf_registro']; }
    }
    // itens controlados da OM (com lote pesado)
    $ri = tao_formula_api( "/lab_ordem_itens?ordem_id=eq.$ordem_id&select=ativo_id,qtd_pesada,lote_mp_id&limit=200" );
    $ids = array_values( array_unique( array_filter( array_column( $ri['ok'] ? $ri['data'] : [], 'ativo_id' ) ) ) );
    if ( ! $ids ) return 0;
    $ra = tao_formula_api( "/ativos?id=in.(" . implode( ',', $ids ) . ")&controlado=eq.true&select=id,dcb,classe_sngpc,registro_ms,unidade_padrao&limit=" . count( $ids ) );
    $ctrl = [];
    foreach ( ( $ra['ok'] ? $ra['data'] : [] ) as $a ) $ctrl[ $a['id'] ] = $a;

    $lotes_nr = [];
    $n = 0;
    foreach ( ( $ri['ok'] ? $ri['data'] : [] ) as $it ) {
        $a = $ctrl[ $it['ativo_id'] ] ?? null;
        if ( ! $a || ! ( (float) ( $it['qtd_pesada'] ?? 0 ) > 0 ) ) continue;
        $nrlote = null;
        if ( ! empty( $it['lote_mp_id'] ) ) {
            $rl = tao_formula_api( "/lab_lotes_mp?id=eq.{$it['lote_mp_id']}&select=nr_lote&limit=1" );
            $nrlote = ( $rl['ok'] && ! empty( $rl['data'] ) ) ? $rl['data'][0]['nr_lote'] : null;
        }
        tao_formula_api( '/sngpc_movimentos', 'POST', [
            'cliente_id' => $cliente_id, 'tipo' => 'saida', 'ativo_id' => $it['ativo_id'],
            'dcb' => $a['dcb'], 'classe_sngpc' => $a['classe_sngpc'], 'registro_ms' => $a['registro_ms'],
            'nr_lote' => $nrlote, 'quantidade' => (float) $it['qtd_pesada'], 'unidade' => $a['unidade_padrao'] ?? 'g',
            'dt_movimento' => $o['dt_manipulacao'] ?: gmdate( 'Y-m-d' ),
            'prescritor_nome' => $pn, 'prescritor_conselho' => $pc, 'prescritor_nr' => $pnr, 'prescritor_uf' => $puf,
            'tp_receita' => $o['tp_receita'], 'nr_notificacao' => $o['nr_notificacao'],
            'comprador_nome' => $o['comprador_nome'], 'comprador_doc_tp' => $o['comprador_doc_tp'], 'comprador_doc_nr' => $o['comprador_doc_nr'],
            'origem' => 'om', 'ref_id' => $ordem_id, 'criado_por' => get_current_user_id(),
        ] );
        $n++;
    }
    return $n;
}

// Detalhe da OM + itens (p/ pesagem) com lote FEFO sugerido por ativo
add_action( 'wp_ajax_tao_formula_prod_om', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( 'Acesso negado', 403 );
    $cliente_id = tao_formula_cliente_id();
    $ordem_id = sanitize_text_field( $_GET['ordem_id'] ?? '' );
    if ( ! $cliente_id || ! $ordem_id ) wp_send_json_error( [ 'message' => 'Parâmetros inválidos' ] );

    $ro = tao_formula_api( "/lab_ordens?id=eq.$ordem_id&cliente_id=eq.$cliente_id&select=id,numero,paciente_nome,forma_farmac,volume,unidade_vol,qtd_unidades,posologia,dt_validade,status,controlado,tp_receita,nr_notificacao,comprador_nome,comprador_doc_tp,comprador_doc_nr,modo_preparo&limit=1" );
    if ( ! $ro['ok'] || empty( $ro['data'] ) ) wp_send_json_error( [ 'message' => 'OM não encontrada' ] );
    $ri = tao_formula_api( "/lab_ordem_itens?ordem_id=eq.$ordem_id&select=id,ativo_id,descricao,qtd_prescrita,unidade,qtd_pesada,qtd_pesar,unid_pesar,teor_aplic,equiv_aplic,diluicao_aplic,lote_mp_id,eh_qsp,ordem&order=ordem.asc&limit=200" );
    $itens = $ri['ok'] ? ( $ri['data'] ?? [] ) : [];

    // lotes aprovados FEFO + NOME DO ATIVO ORIGEM (produto real, usado na ficha de pesagem) por ativo
    $ids = array_values( array_unique( array_filter( array_column( $itens, 'ativo_id' ) ) ) );
    $lotes = []; $nome_ativo = [];
    if ( $ids ) {
        $hoje = gmdate( 'Y-m-d' );
        $rl = tao_formula_api( "/lab_lotes_mp?cliente_id=eq.$cliente_id&ativo_id=in.(" . implode( ',', $ids ) . ")&status=eq.aprovado&qtd_atual=gt.0&dt_validade=gte.$hoje&select=id,ativo_id,nr_lote,dt_validade,qtd_atual,fabricante&order=dt_validade.asc&limit=500" );
        foreach ( ( $rl['ok'] ? $rl['data'] : [] ) as $l ) $lotes[ $l['ativo_id'] ][] = $l;
        $ra = tao_formula_api( "/ativos?id=in.(" . implode( ',', $ids ) . ")&select=id,nome" );
        foreach ( ( $ra['ok'] ? $ra['data'] : [] ) as $a ) $nome_ativo[ $a['id'] ] = $a['nome'];
    }
    foreach ( $itens as &$it ) {
        $it['lotes']      = $it['ativo_id'] ? ( $lotes[ $it['ativo_id'] ] ?? [] ) : [];
        // Ficha de pesagem usa o ATIVO ORIGEM (produto); fallback para a descrição prescrita se for item livre
        $it['nome_ativo'] = ( $it['ativo_id'] && isset( $nome_ativo[ $it['ativo_id'] ] ) ) ? $nome_ativo[ $it['ativo_id'] ] : $it['descricao'];
    }
    unset( $it );
    wp_send_json_success( [ 'ordem' => $ro['data'][0], 'itens' => $itens ] );
} );

// Salva a pesagem (qtd_pesada + lote) de um item
add_action( 'wp_ajax_tao_formula_prod_pesar', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( [ 'message' => 'Acesso negado' ], 403 );
    $item_id = sanitize_text_field( $_POST['item_id'] ?? '' );
    if ( ! $item_id ) wp_send_json_error( [ 'message' => 'Item inválido' ] );
    $qtd  = $_POST['qtd_pesada'] ?? '';
    $lote = sanitize_text_field( $_POST['lote_mp_id'] ?? '' ) ?: null;
    $r = tao_formula_api( "/lab_ordem_itens?id=eq.$item_id", 'PATCH', [
        'qtd_pesada' => $qtd === '' ? null : (float) str_replace( ',', '.', $qtd ),
        'lote_mp_id' => $lote,
        'pesado_por' => get_current_user_id(),
        'pesado_em'  => gmdate( 'c' ),
    ] );
    $r['ok'] ? wp_send_json_success() : wp_send_json_error( [ 'message' => mb_substr( (string) $r['raw'], 0, 200 ) ] );
} );

// Busca de orçamentos p/ gerar OM (por número/paciente)
add_action( 'wp_ajax_tao_formula_prod_busca_orc', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( 'Acesso negado', 403 );
    $cliente_id = tao_formula_cliente_id();
    $q = sanitize_text_field( $_GET['q'] ?? '' );
    if ( ! $cliente_id || mb_strlen( $q ) < 2 ) { wp_send_json_success( [] ); return; }
    $enc = rawurlencode( $q );
    $r = tao_formula_api(
        "/orcamentos?cliente_id=eq.$cliente_id&or=(numero_orcamento.ilike.*{$enc}*,nome_paciente.ilike.*{$enc}*)" .
        "&select=id,numero_orcamento,nome_paciente,forma_nome,forma_vol,forma_unidade&order=criado_em.desc&limit=10"
    );
    wp_send_json_success( $r['ok'] ? ( $r['data'] ?? [] ) : [] );
} );

// Lista de OMs abertas AGRUPADAS pela fase do card no funil Pós-Vendas (não pelo kanban interno).
// Fonte da verdade da produção = o funil Pós-Vendas (Aguardando Produção → Em Produção → Pronto p/ Entrega).
add_action( 'wp_ajax_tao_formula_prod_lista', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( 'Acesso negado', 403 );
    $cliente_id = tao_formula_cliente_id();
    if ( ! $cliente_id ) wp_send_json_error( [ 'message' => 'Cliente não identificado' ] );

    $ro  = tao_formula_api( "/lab_ordens?cliente_id=eq.$cliente_id&status=neq.concluida&select=id,numero,card_id,paciente_nome,forma_farmac,volume,unidade_vol,dt_validade,controlado,status&order=criado_em.desc&limit=300" );
    $oms = $ro['ok'] ? ( $ro['data'] ?? [] ) : [];

    // fase de cada card + telefone
    $card_ids = array_values( array_unique( array_filter( array_column( $oms, 'card_id' ) ) ) );
    $cards = []; $ws = '';
    if ( $card_ids ) {
        $rc = tao_formula_api( "/crm_cards?id=in.(" . implode( ',', $card_ids ) . ")&select=id,estagio_id,workspace_id,contato_whatsapp" );
        foreach ( ( $rc['ok'] ? ( $rc['data'] ?? [] ) : [] ) as $c ) { $cards[ $c['id'] ] = $c; if ( ! $ws ) $ws = $c['workspace_id'] ?? ''; }
    }
    if ( ! $ws && function_exists( 'tao_formula_workspace_id' ) ) $ws = tao_formula_workspace_id();
    $est = function_exists( 'tao_formula_estagios_producao' ) ? tao_formula_estagios_producao( $ws ) : [ 'aguardando' => null, 'em_producao' => null ];

    $grupos = [ 'aguardando' => [], 'em_producao' => [], 'outros' => [] ];
    foreach ( $oms as $o ) {
        $c = $cards[ $o['card_id'] ?? '' ] ?? null;
        $o['whatsapp'] = $c['contato_whatsapp'] ?? '';
        $eid = $c['estagio_id'] ?? '';
        if ( $eid && $eid === $est['aguardando'] )       $grupos['aguardando'][]  = $o;
        elseif ( $eid && $eid === $est['em_producao'] )  $grupos['em_producao'][] = $o;
        else                                             $grupos['outros'][]     = $o;
    }
    wp_send_json_success( [ 'grupos' => $grupos, 'tem_fase' => (bool) ( $est['aguardando'] && $est['em_producao'] ) ] );
} );

// ═══════════════════════════════════════════════════════════════════════════
// PRODUÇÃO INTERNA — diluições e bases manipuladas (espelho FC18000/FC18100).
// Receita = lab_formulas_padrao; o produzido nasce como lote em lab_lotes_mp.
// ═══════════════════════════════════════════════════════════════════════════

// Lista de ordens de produção interna (com nome do ativo)
add_action( 'wp_ajax_tao_formula_prodint_lista', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( [ 'message' => 'Acesso negado' ], 403 );
    $cliente_id = tao_formula_cliente_id();
    if ( ! $cliente_id ) wp_send_json_error( [ 'message' => 'Cliente não identificado' ] );
    $st = sanitize_text_field( $_GET['status'] ?? '' );
    $f  = $st ? '&status=eq.' . rawurlencode( $st ) : '';
    $size   = in_array( intval( $_GET['size'] ?? 30 ), [ 20, 30, 50 ], true ) ? intval( $_GET['size'] ) : 30;
    $offset = max( 0, intval( $_GET['offset'] ?? 0 ) );
    $r = tao_formula_api( "/lab_producao?cliente_id=eq.$cliente_id$f&select=id,ativo_id,quantidade,unidade,nr_lote,dt_producao,dt_validade,status,responsavel&order=criado_em.desc&limit=$size&offset=$offset", 'GET', null, true );
    $ops = $r['ok'] ? ( $r['data'] ?? [] ) : [];
    $ids = array_values( array_unique( array_filter( array_column( $ops, 'ativo_id' ) ) ) );
    $nomes = [];
    if ( $ids ) {
        $ra = tao_formula_api( "/ativos?id=in.(" . implode( ',', $ids ) . ")&select=id,nome&limit=" . count( $ids ) );
        foreach ( ( $ra['ok'] ? $ra['data'] : [] ) as $a ) $nomes[ $a['id'] ] = $a['nome'];
    }
    foreach ( $ops as &$o ) $o['ativo_nome'] = $nomes[ $o['ativo_id'] ] ?? '—';
    unset( $o );
    wp_send_json_success( [ 'items' => $ops, 'total' => $r['ok'] ? (int) $r['total'] : 0 ] );
} );

// Busca de ativos produzidos internamente (para escolher o que produzir).
// Traz o vínculo de receita (formula_producao_id) quando houver.
add_action( 'wp_ajax_tao_formula_prodint_ativos', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( 'Acesso negado', 403 );
    $cliente_id = tao_formula_cliente_id();
    $q = sanitize_text_field( $_GET['q'] ?? '' );
    if ( ! $cliente_id || mb_strlen( $q ) < 2 ) { wp_send_json_success( [] ); return; }
    $enc = rawurlencode( $q );
    $r = tao_formula_api(
        "/ativos?cliente_id=eq.$cliente_id&nome=ilike.*$enc*&select=id,nome,codigo_fc,unidade_padrao,produzido_interno,formula_producao_id,validade_producao_dias&order=nome.asc&limit=12"
    );
    wp_send_json_success( $r['ok'] ? ( $r['data'] ?? [] ) : [] );
} );

// Busca fórmula padrão (receita) por nome — p/ vincular ao ativo
add_action( 'wp_ajax_tao_formula_prodint_formula_busca', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( 'Acesso negado', 403 );
    $cliente_id = tao_formula_cliente_id();
    $q = sanitize_text_field( $_GET['q'] ?? '' );
    if ( ! $cliente_id || mb_strlen( $q ) < 2 ) { wp_send_json_success( [] ); return; }
    $enc = rawurlencode( $q );
    $r = tao_formula_api( "/lab_formulas_padrao?cliente_id=eq.$cliente_id&nome=ilike.*$enc*&select=id,nome&order=nome.asc&limit=12" );
    wp_send_json_success( $r['ok'] ? ( $r['data'] ?? [] ) : [] );
} );

// Cria a ordem de produção: escala a receita p/ a quantidade e grava os insumos.
add_action( 'wp_ajax_tao_formula_prodint_nova', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( [ 'message' => 'Acesso negado' ], 403 );
    $cliente_id = tao_formula_cliente_id();
    if ( ! $cliente_id ) wp_send_json_error( [ 'message' => 'Cliente não identificado' ] );

    $ativo_id  = sanitize_text_field( $_POST['ativo_id'] ?? '' );
    $formula_id= sanitize_text_field( $_POST['formula_id'] ?? '' );
    $qtd       = (float) str_replace( ',', '.', (string) ( $_POST['quantidade'] ?? '' ) );
    $vincular  = ! empty( $_POST['vincular_receita'] );  // grava formula_producao_id no ativo
    if ( ! $ativo_id || ! $formula_id || ! ( $qtd > 0 ) )
        wp_send_json_error( [ 'message' => 'Informe o ativo, a receita e a quantidade a produzir.' ] );

    // ativo (unidade + validade)
    $ra = tao_formula_api( "/ativos?id=eq.$ativo_id&cliente_id=eq.$cliente_id&select=id,nome,unidade_padrao,validade_producao_dias&limit=1" );
    if ( ! $ra['ok'] || empty( $ra['data'] ) ) wp_send_json_error( [ 'message' => 'Ativo não encontrado' ] );
    $ativo = $ra['data'][0];
    $unid  = $ativo['unidade_padrao'] ?: 'g';
    $valdias = (int) ( $ativo['validade_producao_dias'] ?: 90 );

    // itens da receita
    $rit = tao_formula_api( "/lab_formulas_padrao_itens?formula_id=eq.$formula_id&select=ativo_id,descricao,qtd,unidade,eh_qsp,ordem&order=ordem.asc&limit=100" );
    $ritens = $rit['ok'] ? ( $rit['data'] ?? [] ) : [];
    if ( ! $ritens ) wp_send_json_error( [ 'message' => 'A receita selecionada não tem itens cadastrados.' ] );
    $soma = 0.0; foreach ( $ritens as $it ) $soma += (float) ( $it['qtd'] ?? 0 );
    if ( ! ( $soma > 0 ) ) wp_send_json_error( [ 'message' => 'A receita tem quantidade total zero.' ] );
    $fator = $qtd / $soma;
    // teor/fator do principal (soma dos itens não-QSP)
    $qtd_principal = 0.0; foreach ( $ritens as $it ) if ( empty( $it['eh_qsp'] ) ) $qtd_principal += (float) ( $it['qtd'] ?? 0 );
    $teor_pct = $qtd_principal > 0 ? round( $qtd_principal / $soma * 100, 4 ) : null;
    $fator_dil= $qtd_principal > 0 ? round( $soma / $qtd_principal, 4 ) : null;

    // numeração do lote: PI-AAAAMM-NNN
    $ym = gmdate( 'Ym' );
    $rc = tao_formula_api( "/lab_producao?cliente_id=eq.$cliente_id&nr_lote=like.PI-$ym-*&select=nr_lote&order=nr_lote.desc&limit=1" );
    $seq = 1;
    if ( $rc['ok'] && ! empty( $rc['data'] ) && preg_match( '/-(\d+)$/', $rc['data'][0]['nr_lote'], $m ) ) $seq = (int) $m[1] + 1;
    $nr_lote = sprintf( 'PI-%s-%03d', $ym, $seq );

    // cabeçalho
    $cab = tao_formula_api( '/lab_producao', 'POST', [
        'cliente_id' => $cliente_id, 'ativo_id' => $ativo_id, 'formula_id' => $formula_id,
        'quantidade' => $qtd, 'unidade' => $unid, 'nr_lote' => $nr_lote,
        'teor_pct' => $teor_pct, 'fator_diluicao' => $fator_dil,
        'dt_producao' => gmdate( 'Y-m-d' ), 'dt_validade' => gmdate( 'Y-m-d', time() + $valdias * 86400 ),
        'status' => 'aberta', 'criado_por' => get_current_user_id(),
    ] );
    if ( ! $cab['ok'] || empty( $cab['data'] ) )
        wp_send_json_error( [ 'message' => 'Erro ao criar ordem: ' . mb_substr( (string) $cab['raw'], 0, 200 ) ] );
    $pid = $cab['data'][0]['id'];

    // itens escalados
    foreach ( $ritens as $it ) {
        tao_formula_api( '/lab_producao_itens', 'POST', [
            'producao_id' => $pid, 'ativo_id' => $it['ativo_id'] ?: null, 'descricao' => $it['descricao'],
            'qtd_teorica' => round( (float) $it['qtd'] * $fator, 4 ), 'unidade' => $it['unidade'] ?: $unid,
            'eh_qsp' => ! empty( $it['eh_qsp'] ), 'ordem' => (int) ( $it['ordem'] ?? 0 ),
        ] );
    }
    if ( $vincular )
        tao_formula_api( "/ativos?id=eq.$ativo_id&cliente_id=eq.$cliente_id", 'PATCH', [ 'produzido_interno' => true, 'formula_producao_id' => $formula_id ] );

    wp_send_json_success( [ 'id' => $pid, 'nr_lote' => $nr_lote ] );
} );

// Detalhe da ordem + itens (p/ pesagem) com lotes FEFO sugeridos
add_action( 'wp_ajax_tao_formula_prodint_get', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( 'Acesso negado', 403 );
    $cliente_id = tao_formula_cliente_id();
    $pid = sanitize_text_field( $_GET['id'] ?? '' );
    if ( ! $cliente_id || ! $pid ) wp_send_json_error( [ 'message' => 'Parâmetros inválidos' ] );

    $rp = tao_formula_api( "/lab_producao?id=eq.$pid&cliente_id=eq.$cliente_id&limit=1" );
    if ( ! $rp['ok'] || empty( $rp['data'] ) ) wp_send_json_error( [ 'message' => 'Ordem não encontrada' ] );
    $op = $rp['data'][0];
    $ra = tao_formula_api( "/ativos?id=eq.{$op['ativo_id']}&select=nome,codigo_fc&limit=1" );
    $op['ativo_nome'] = ( $ra['ok'] && ! empty( $ra['data'] ) ) ? $ra['data'][0]['nome'] : '—';

    $ri = tao_formula_api( "/lab_producao_itens?producao_id=eq.$pid&select=id,ativo_id,descricao,qtd_teorica,qtd_pesada,unidade,lote_mp_id,eh_qsp,ordem&order=ordem.asc&limit=100" );
    $itens = $ri['ok'] ? ( $ri['data'] ?? [] ) : [];
    $ids = array_values( array_unique( array_filter( array_column( $itens, 'ativo_id' ) ) ) );
    $lotes = [];
    if ( $ids ) {
        $hoje = gmdate( 'Y-m-d' );
        $rl = tao_formula_api( "/lab_lotes_mp?cliente_id=eq.$cliente_id&ativo_id=in.(" . implode( ',', $ids ) . ")&status=eq.aprovado&qtd_atual=gt.0&dt_validade=gte.$hoje&select=id,ativo_id,nr_lote,dt_validade,qtd_atual&order=dt_validade.asc&limit=500" );
        foreach ( ( $rl['ok'] ? $rl['data'] : [] ) as $l ) $lotes[ $l['ativo_id'] ][] = $l;
    }
    foreach ( $itens as &$it ) $it['lotes'] = $it['ativo_id'] ? ( $lotes[ $it['ativo_id'] ] ?? [] ) : [];
    unset( $it );
    wp_send_json_success( [ 'op' => $op, 'itens' => $itens ] );
} );

// Salva a pesagem de um insumo (qtd + lote)
add_action( 'wp_ajax_tao_formula_prodint_pesar', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( [ 'message' => 'Acesso negado' ], 403 );
    $item_id = sanitize_text_field( $_POST['item_id'] ?? '' );
    if ( ! $item_id ) wp_send_json_error( [ 'message' => 'Item inválido' ] );
    $qtd  = $_POST['qtd_pesada'] ?? '';
    $lote = sanitize_text_field( $_POST['lote_mp_id'] ?? '' ) ?: null;
    $r = tao_formula_api( "/lab_producao_itens?id=eq.$item_id", 'PATCH', [
        'qtd_pesada' => $qtd === '' ? null : (float) str_replace( ',', '.', $qtd ),
        'lote_mp_id' => $lote, 'pesado_por' => get_current_user_id(), 'pesado_em' => gmdate( 'c' ),
    ] );
    $r['ok'] ? wp_send_json_success() : wp_send_json_error( [ 'message' => mb_substr( (string) $r['raw'], 0, 200 ) ] );
} );

// Conclui: baixa os insumos (kardex) e gera o lote do produzido (lab_lotes_mp).
add_action( 'wp_ajax_tao_formula_prodint_concluir', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( [ 'message' => 'Acesso negado' ], 403 );
    $cliente_id = tao_formula_cliente_id();
    $pid = sanitize_text_field( $_POST['id'] ?? '' );
    if ( ! $cliente_id || ! $pid ) wp_send_json_error( [ 'message' => 'Parâmetros inválidos' ] );

    $rp = tao_formula_api( "/lab_producao?id=eq.$pid&cliente_id=eq.$cliente_id&limit=1" );
    if ( ! $rp['ok'] || empty( $rp['data'] ) ) wp_send_json_error( [ 'message' => 'Ordem não encontrada' ] );
    $op = $rp['data'][0];
    if ( $op['status'] === 'concluida' ) wp_send_json_error( [ 'message' => 'Ordem já concluída.' ] );

    $ri = tao_formula_api( "/lab_producao_itens?producao_id=eq.$pid&select=id,ativo_id,qtd_pesada,lote_mp_id,eh_qsp&limit=100" );
    $itens = $ri['ok'] ? ( $ri['data'] ?? [] ) : [];
    // todo insumo com ativo precisa de lote + qtd pesada
    foreach ( $itens as $it ) {
        if ( ! empty( $it['ativo_id'] ) && ( empty( $it['lote_mp_id'] ) || ! ( (float) ( $it['qtd_pesada'] ?? 0 ) > 0 ) ) )
            wp_send_json_error( [ 'message' => 'Pese todos os insumos (com lote) antes de concluir.' ] );
    }

    // 1) baixa os insumos + kardex saída; captura o lote do principal p/ rastreio
    $lote_puro_id = null;
    foreach ( $itens as $it ) {
        if ( empty( $it['lote_mp_id'] ) || ! ( (float) ( $it['qtd_pesada'] ?? 0 ) > 0 ) ) continue;
        $q = (float) $it['qtd_pesada'];
        $rl = tao_formula_api( "/lab_lotes_mp?id=eq.{$it['lote_mp_id']}&select=qtd_atual&limit=1" );
        if ( $rl['ok'] && ! empty( $rl['data'] ) ) {
            $novo = max( 0, (float) $rl['data'][0]['qtd_atual'] - $q );
            $upd = [ 'qtd_atual' => $novo ]; if ( $novo <= 0 ) $upd['status'] = 'esgotado';
            tao_formula_api( "/lab_lotes_mp?id=eq.{$it['lote_mp_id']}", 'PATCH', $upd );
        }
        tao_formula_api( '/estoque_movimentos', 'POST', [
            'cliente_id' => $cliente_id, 'ativo_id' => $it['ativo_id'], 'lote_id' => $it['lote_mp_id'],
            'tipo' => 'saida', 'quantidade' => -$q, 'origem' => 'producao_interna', 'ref_id' => $pid,
            'usuario_id' => get_current_user_id(),
        ] );
        if ( empty( $it['eh_qsp'] ) && ! $lote_puro_id ) $lote_puro_id = $it['lote_mp_id'];
    }

    // 2) cria o lote do produzido (entra no estoque, aprovado — liberado pelo farmacêutico)
    $rlote = tao_formula_api( '/lab_lotes_mp', 'POST', [
        'cliente_id' => $cliente_id, 'ativo_id' => $op['ativo_id'], 'nr_lote' => $op['nr_lote'],
        'origem' => 'producao_interna', 'lote_puro_id' => $lote_puro_id,
        'dt_fabricacao' => $op['dt_producao'], 'dt_validade' => $op['dt_validade'],
        'qtd_inicial' => (float) $op['quantidade'], 'qtd_atual' => (float) $op['quantidade'], 'unidade' => $op['unidade'],
        'teor_pct' => $op['teor_pct'], 'fator_diluicao' => $op['fator_diluicao'],
        'status' => 'aprovado', 'qc_resultado' => 'aprovado', 'qc_aprovado_por' => get_current_user_id(), 'qc_em' => gmdate( 'c' ),
    ] );
    $lote_gerado_id = ( $rlote['ok'] && ! empty( $rlote['data'] ) ) ? $rlote['data'][0]['id'] : null;
    if ( $lote_gerado_id ) {
        tao_formula_api( '/estoque_movimentos', 'POST', [
            'cliente_id' => $cliente_id, 'ativo_id' => $op['ativo_id'], 'lote_id' => $lote_gerado_id,
            'tipo' => 'entrada', 'quantidade' => (float) $op['quantidade'], 'origem' => 'producao_interna', 'ref_id' => $pid,
            'usuario_id' => get_current_user_id(),
        ] );
    }

    // 3) fecha a ordem
    tao_formula_api( "/lab_producao?id=eq.$pid&cliente_id=eq.$cliente_id", 'PATCH', [
        'status' => 'concluida', 'lote_gerado_id' => $lote_gerado_id, 'concluida_em' => gmdate( 'c' ),
        'responsavel' => wp_get_current_user()->display_name ?: null,
    ] );
    wp_send_json_success( [ 'nr_lote' => $op['nr_lote'], 'lote_id' => $lote_gerado_id ] );
} );

// ═══════════════════════════════════════════════════════════════════════════
// PRODUÇÃO Fatia B (Rótulo RDC 67) + Fatia C (Livro de Receituário)
// ═══════════════════════════════════════════════════════════════════════════

// Gera/retorna o rótulo de uma OM com os dizeres obrigatórios (RDC 67 Anexo I).
add_action( 'wp_ajax_tao_formula_prod_rotulo', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( 'Acesso negado', 403 );
    $cliente_id = tao_formula_cliente_id();
    $ordem_id = sanitize_text_field( $_GET['ordem_id'] ?? '' );
    if ( ! $cliente_id || ! $ordem_id ) wp_send_json_error( [ 'message' => 'Parâmetros inválidos' ] );

    $ro = tao_formula_api( "/lab_ordens?id=eq.$ordem_id&cliente_id=eq.$cliente_id&limit=1" );
    if ( ! $ro['ok'] || empty( $ro['data'] ) ) wp_send_json_error( [ 'message' => 'OM não encontrada' ] );
    $o = $ro['data'][0];

    // composição (itens C não-QSP) + lotes usados
    $ri = tao_formula_api( "/lab_ordem_itens?ordem_id=eq.$ordem_id&select=descricao,qtd_prescrita,unidade,eh_qsp,ordem&order=ordem.asc&limit=200" );
    $comp = [];
    foreach ( ( $ri['ok'] ? $ri['data'] : [] ) as $it ) {
        if ( ! empty( $it['eh_qsp'] ) ) continue;
        $comp[] = $it['descricao'] . ( $it['qtd_prescrita'] !== null ? ' ' . rtrim( rtrim( number_format( (float) $it['qtd_prescrita'], 3, ',', '' ), '0' ), ',' ) . ' ' . $it['unidade'] : '' );
    }

    // prescritor
    $presc = '';
    if ( ! empty( $o['prescritor_id'] ) ) {
        $rp = tao_formula_api( "/prescritores?id=eq.{$o['prescritor_id']}&select=tratamento,nome,tipo_registro,nr_registro,uf_registro&limit=1" );
        if ( $rp['ok'] && ! empty( $rp['data'] ) ) {
            $p = $rp['data'][0];
            $reg = trim( ( $p['tipo_registro'] ?? '' ) . ' ' . ( $p['nr_registro'] ?? '' ) . ( $p['uf_registro'] ? '/' . $p['uf_registro'] : '' ) );
            $presc = trim( ( $p['tratamento'] ? $p['tratamento'] . ' ' : '' ) . $p['nome'] . ( $reg ? ' — ' . $reg : '' ) );
        }
    }

    // empresa + RT (empresa_config)
    $emp = [];
    $rc = tao_formula_api( "/empresa_config?cliente_id=eq.$cliente_id&limit=1" );
    if ( $rc['ok'] && ! empty( $rc['data'] ) ) $emp = $rc['data'][0];
    $farm_end = trim( ( $emp['endereco'] ?? '' ) . ' ' . ( $emp['bairro'] ?? '' ) . ' ' . ( $emp['cidade'] ?? '' ) . ( ! empty( $emp['uf'] ) ? '/' . $emp['uf'] : '' ) );
    $rt = trim( ( $emp['rt_nome'] ?? '' ) . ( ! empty( $emp['rt_crf'] ) ? ' — CRF ' . $emp['rt_crf'] . ( $emp['rt_uf'] ? '/' . $emp['rt_uf'] : '' ) : '' ) );

    // via de uso / advertência
    $uso = strtolower( (string) ( $o['tp_uso'] ?? '' ) );
    $advert = $uso === 'externo' ? 'USO EXTERNO' : ( $uso === 'veterinario' ? 'USO VETERINÁRIO' : 'USO INTERNO/ORAL' );

    $r = [
        'farmacia'    => trim( ( $emp['nome_fantasia'] ?? $emp['razao_social'] ?? 'Farmácia' ) ),
        'cnpj'        => $emp['cnpj'] ?? '',
        'farm_end'    => $farm_end,
        'rt'          => $rt,
        'om'          => $o['numero'],
        'paciente'    => $o['paciente_nome'],
        'prescritor'  => $presc,
        'formula'     => trim( ( $o['forma_farmac'] ?? '' ) . ' ' . ( $o['volume'] ? rtrim( rtrim( number_format( (float) $o['volume'], 2, ',', '' ), '0' ), ',' ) . ' ' . ( $o['unidade_vol'] ?? '' ) : '' ) ),
        'qtd'         => $o['qtd_unidades'],
        'composicao'  => $comp,
        'posologia'   => $o['posologia'] ?: '',
        'advertencia' => $advert,
        'dt_manip'    => $o['dt_manipulacao'] ?: gmdate( 'Y-m-d' ),
        'validade'    => $o['dt_validade'],
        'conservacao' => 'Conservar em temperatura ambiente, ao abrigo de luz e umidade.',
    ];

    // registra o rótulo emitido (auditável)
    $texto = "$r[farmacia] | OM $r[om] | Paciente: $r[paciente] | Validade: $r[validade]";
    tao_formula_api( '/lab_rotulos', 'POST', [
        'ordem_id' => $ordem_id, 'texto' => $texto, 'impresso_em' => gmdate( 'c' ), 'impresso_por' => get_current_user_id(),
    ] );

    if ( empty( $emp ) ) $r['aviso'] = 'Dados da farmácia/RT vazios — preencha em Configurações → Dados da Farmácia (obrigatório no rótulo).';
    wp_send_json_success( $r );
} );

// Livro de Receituário — OMs sequenciais por período (RDC 67 + Lei 5.991 art.42)
add_action( 'wp_ajax_tao_formula_prod_livro', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( 'Acesso negado', 403 );
    $cliente_id = tao_formula_cliente_id();
    if ( ! $cliente_id ) wp_send_json_error( [ 'message' => 'Cliente não identificado' ] );
    $de  = sanitize_text_field( $_GET['de']  ?? gmdate( 'Y-m-01' ) );
    $ate = sanitize_text_field( $_GET['ate'] ?? gmdate( 'Y-m-d' ) );

    $r = tao_formula_api(
        "/lab_ordens?cliente_id=eq.$cliente_id&criado_em=gte.{$de}T00:00:00&criado_em=lte.{$ate}T23:59:59" .
        "&select=numero,criado_em,paciente_nome,forma_farmac,volume,unidade_vol,dt_validade,status,controlado,prescritor_id&order=numero.asc&limit=2000"
    );
    $ordens = $r['ok'] ? ( $r['data'] ?? [] ) : [];
    // nomes dos prescritores
    $pids = array_values( array_unique( array_filter( array_column( $ordens, 'prescritor_id' ) ) ) );
    $presc = [];
    if ( $pids ) {
        $rp = tao_formula_api( "/prescritores?id=in.(" . implode( ',', $pids ) . ")&select=id,nome,tipo_registro,nr_registro&limit=" . count( $pids ) );
        foreach ( ( $rp['ok'] ? $rp['data'] : [] ) as $p ) $presc[ $p['id'] ] = trim( $p['nome'] . ' (' . ( $p['tipo_registro'] ?? '' ) . ' ' . ( $p['nr_registro'] ?? '' ) . ')' );
    }
    foreach ( $ordens as &$o ) $o['prescritor'] = $o['prescritor_id'] ? ( $presc[ $o['prescritor_id'] ] ?? '' ) : '';
    unset( $o );
    wp_send_json_success( [ 'de' => $de, 'ate' => $ate, 'ordens' => $ordens ] );
} );

// ═══════════════════════════════════════════════════════════════════════════
// FINANCEIRO — Contas a Pagar (duplicatas das NFs) + relatório ao contador
// ═══════════════════════════════════════════════════════════════════════════

add_action( 'wp_ajax_tao_formula_cp_lista', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( 'Acesso negado', 403 );
    $cliente_id = tao_formula_cliente_id();
    if ( ! $cliente_id ) wp_send_json_error( [ 'message' => 'Cliente não identificado' ] );
    $status = sanitize_text_field( $_GET['status'] ?? '' );
    $de  = sanitize_text_field( $_GET['de']  ?? '' );
    $ate = sanitize_text_field( $_GET['ate'] ?? '' );
    $size   = in_array( intval( $_GET['size'] ?? 30 ), [ 20, 30, 50 ], true ) ? intval( $_GET['size'] ) : 30;
    $offset = max( 0, intval( $_GET['offset'] ?? 0 ) );
    $f = '';
    if ( in_array( $status, [ 'aberto', 'pago', 'cancelado' ], true ) ) $f .= "&status=eq.$status";
    if ( $de )  $f .= "&vencimento=gte.$de";
    if ( $ate ) $f .= "&vencimento=lte.$ate";

    $r = tao_formula_api(
        "/contas_pagar?cliente_id=eq.$cliente_id$f" .
        "&select=id,fornecedor_id,numero_dup,vencimento,valor,status,dt_pagamento&order=vencimento.asc&limit=500"
    );
    $cp = $r['ok'] ? ( $r['data'] ?? [] ) : [];
    // nomes dos fornecedores
    $fids = array_values( array_unique( array_filter( array_column( $cp, 'fornecedor_id' ) ) ) );
    $nomes = [];
    if ( $fids ) {
        $rf = tao_formula_api( "/fornecedores?id=in.(" . implode( ',', $fids ) . ")&select=id,nome&limit=" . count( $fids ) );
        foreach ( ( $rf['ok'] ? $rf['data'] : [] ) as $x ) $nomes[ $x['id'] ] = $x['nome'];
    }
    $tot_aberto = 0; $tot_pago = 0;
    foreach ( $cp as &$c ) {
        $c['fornecedor'] = $c['fornecedor_id'] ? ( $nomes[ $c['fornecedor_id'] ] ?? '—' ) : '—';
        if ( $c['status'] === 'aberto' ) $tot_aberto += (float) $c['valor'];
        if ( $c['status'] === 'pago' )   $tot_pago   += (float) $c['valor'];
    }
    unset( $c );
    $total   = count( $cp );
    $cp_page = array_slice( $cp, $offset, $size );
    wp_send_json_success( [ 'items' => $cp_page, 'total' => $total, 'total_aberto' => $tot_aberto, 'total_pago' => $tot_pago ] );
} );

add_action( 'wp_ajax_tao_formula_cp_pagar', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( [ 'message' => 'Acesso negado' ], 403 );
    $cliente_id = tao_formula_cliente_id();
    $id = sanitize_text_field( $_POST['id'] ?? '' );
    $acao = sanitize_text_field( $_POST['acao'] ?? 'pagar' ); // pagar|reabrir
    if ( ! $cliente_id || ! $id ) wp_send_json_error( [ 'message' => 'Parâmetros inválidos' ] );
    $patch = $acao === 'reabrir'
        ? [ 'status' => 'aberto', 'dt_pagamento' => null ]
        : [ 'status' => 'pago', 'dt_pagamento' => gmdate( 'Y-m-d' ) ];
    $r = tao_formula_api( "/contas_pagar?id=eq.$id&cliente_id=eq.$cliente_id", 'PATCH', $patch );
    $r['ok'] ? wp_send_json_success() : wp_send_json_error( [ 'message' => mb_substr( (string) $r['raw'], 0, 200 ) ] );
} );

// ═══════════════════════════════════════════════════════════════════════════
// CONTROLADOS / SNGPC (Pacote 4) — livro, lançamento, balanço, XML
// ═══════════════════════════════════════════════════════════════════════════

// Livro de movimentos de controlados (filtro período/tipo)
add_action( 'wp_ajax_tao_formula_sngpc_lista', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( 'Acesso negado', 403 );
    $cliente_id = tao_formula_cliente_id();
    if ( ! $cliente_id ) wp_send_json_error( [ 'message' => 'Cliente não identificado' ] );
    $de   = sanitize_text_field( $_GET['de']   ?? gmdate( 'Y-m-01' ) );
    $ate  = sanitize_text_field( $_GET['ate']  ?? gmdate( 'Y-m-d' ) );
    $tipo = sanitize_text_field( $_GET['tipo'] ?? '' );
    $size   = in_array( intval( $_GET['size'] ?? 30 ), [ 20, 30, 50 ], true ) ? intval( $_GET['size'] ) : 30;
    $offset = max( 0, intval( $_GET['offset'] ?? 0 ) );
    $f = "&dt_movimento=gte.$de&dt_movimento=lte.$ate";
    if ( in_array( $tipo, [ 'entrada', 'saida', 'perda', 'transferencia', 'inventario' ], true ) ) $f .= "&tipo=eq.$tipo";
    $r = tao_formula_api(
        "/sngpc_movimentos?cliente_id=eq.$cliente_id$f" .
        "&select=id,tipo,dcb,classe_sngpc,nr_lote,quantidade,unidade,dt_movimento,prescritor_nome,comprador_nome,nr_notificacao,tp_perda,transmitido,ativo_id" .
        "&order=dt_movimento.desc,criado_em.desc&limit=$size&offset=$offset", 'GET', null, true
    );
    if ( ! $r['ok'] ) {
        $msg = strpos( (string) $r['raw'], 'does not exist' ) !== false
            ? 'Tabela SNGPC ainda não criada (migration_sngpc_v1.sql pendente).' : mb_substr( (string) $r['raw'], 0, 200 );
        wp_send_json_error( [ 'message' => $msg ] );
    }
    $movs = $r['data'] ?? [];
    // nome dos ativos
    $ids = array_values( array_unique( array_filter( array_column( $movs, 'ativo_id' ) ) ) );
    $nomes = [];
    if ( $ids ) {
        $ra = tao_formula_api( "/ativos?id=in.(" . implode( ',', $ids ) . ")&select=id,nome&limit=" . count( $ids ) );
        foreach ( ( $ra['ok'] ? $ra['data'] : [] ) as $a ) $nomes[ $a['id'] ] = $a['nome'];
    }
    foreach ( $movs as &$m ) $m['ativo_nome'] = $m['ativo_id'] ? ( $nomes[ $m['ativo_id'] ] ?? '' ) : '';
    unset( $m );
    wp_send_json_success( [ 'items' => $movs, 'total' => (int) $r['total'] ] );
} );

// Lançamento manual de movimento (entrada/saída/perda/transferência)
add_action( 'wp_ajax_tao_formula_sngpc_lancar', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( [ 'message' => 'Acesso negado' ], 403 );
    $cliente_id = tao_formula_cliente_id();
    $ativo_id = sanitize_text_field( $_POST['ativo_id'] ?? '' );
    $tipo     = sanitize_text_field( $_POST['tipo'] ?? '' );
    $qtd      = $_POST['quantidade'] ?? '';
    if ( ! $cliente_id || ! $ativo_id || ! in_array( $tipo, [ 'entrada', 'saida', 'perda', 'transferencia', 'inventario' ], true ) || $qtd === '' )
        wp_send_json_error( [ 'message' => 'Preencha ativo, tipo e quantidade' ] );

    // puxa dcb/classe/registro do ativo
    $ra = tao_formula_api( "/ativos?id=eq.$ativo_id&cliente_id=eq.$cliente_id&select=dcb,classe_sngpc,registro_ms,unidade_padrao,controlado&limit=1" );
    $a = ( $ra['ok'] && ! empty( $ra['data'] ) ) ? $ra['data'][0] : [];
    if ( empty( $a['controlado'] ) ) wp_send_json_error( [ 'message' => 'Este ativo não está marcado como controlado.' ] );

    $txt = function( $k ) { $v = trim( sanitize_text_field( $_POST[ $k ] ?? '' ) ); return $v === '' ? null : $v; };
    $mov = [
        'cliente_id'   => $cliente_id, 'tipo' => $tipo, 'ativo_id' => $ativo_id,
        'dcb' => $a['dcb'] ?? null, 'classe_sngpc' => $a['classe_sngpc'] ?? null, 'registro_ms' => $a['registro_ms'] ?? null,
        'nr_lote' => $txt( 'nr_lote' ), 'quantidade' => (float) str_replace( ',', '.', $qtd ),
        'unidade' => $a['unidade_padrao'] ?? 'g', 'dt_movimento' => $txt( 'dt_movimento' ) ?: gmdate( 'Y-m-d' ),
        'origem' => 'manual', 'criado_por' => get_current_user_id(),
    ];
    if ( $tipo === 'entrada' ) { $mov['fornecedor_cnpj'] = $txt( 'fornecedor_cnpj' ); $mov['nf_numero'] = $txt( 'nf_numero' ); }
    if ( $tipo === 'saida' ) {
        $mov['prescritor_nome'] = $txt( 'prescritor_nome' ); $mov['prescritor_conselho'] = $txt( 'prescritor_conselho' );
        $mov['prescritor_nr'] = $txt( 'prescritor_nr' ); $mov['prescritor_uf'] = $txt( 'prescritor_uf' );
        $mov['tp_receita'] = $txt( 'tp_receita' ); $mov['nr_notificacao'] = $txt( 'nr_notificacao' );
        $mov['comprador_nome'] = $txt( 'comprador_nome' ); $mov['comprador_doc_tp'] = $txt( 'comprador_doc_tp' );
        $mov['comprador_doc_nr'] = $txt( 'comprador_doc_nr' );
    }
    if ( $tipo === 'perda' ) $mov['tp_perda'] = $txt( 'tp_perda' );
    if ( $tipo === 'transferencia' ) $mov['cnpj_destino'] = $txt( 'cnpj_destino' );

    $r = tao_formula_api( '/sngpc_movimentos', 'POST', $mov );
    $r['ok'] ? wp_send_json_success( [ 'id' => $r['data'][0]['id'] ?? null ] )
             : wp_send_json_error( [ 'message' => mb_substr( (string) $r['raw'], 0, 250 ) ] );
} );

// Balanço BMPO — saldo por substância (entradas − saídas − perdas) no período
add_action( 'wp_ajax_tao_formula_sngpc_balanco', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( 'Acesso negado', 403 );
    $cliente_id = tao_formula_cliente_id();
    $de  = sanitize_text_field( $_GET['de']  ?? gmdate( 'Y-m-01' ) );
    $ate = sanitize_text_field( $_GET['ate'] ?? gmdate( 'Y-m-d' ) );
    if ( ! $cliente_id ) wp_send_json_error( [ 'message' => 'Cliente não identificado' ] );
    $r = tao_formula_api(
        "/sngpc_movimentos?cliente_id=eq.$cliente_id&dt_movimento=gte.$de&dt_movimento=lte.$ate" .
        "&select=ativo_id,dcb,classe_sngpc,tipo,quantidade,unidade&limit=5000"
    );
    $ag = [];
    foreach ( ( $r['ok'] ? $r['data'] : [] ) as $m ) {
        $k = $m['ativo_id'] ?: $m['dcb'];
        if ( ! isset( $ag[ $k ] ) ) $ag[ $k ] = [ 'dcb' => $m['dcb'], 'classe' => $m['classe_sngpc'], 'unidade' => $m['unidade'], 'entrada' => 0, 'saida' => 0, 'perda' => 0 ];
        $q = (float) $m['quantidade'];
        if ( $m['tipo'] === 'entrada' ) $ag[ $k ]['entrada'] += $q;
        elseif ( $m['tipo'] === 'saida' ) $ag[ $k ]['saida'] += $q;
        elseif ( $m['tipo'] === 'perda' ) $ag[ $k ]['perda'] += $q;
    }
    foreach ( $ag as &$x ) $x['saldo'] = $x['entrada'] - $x['saida'] - $x['perda'];
    unset( $x );
    wp_send_json_success( [ 'de' => $de, 'ate' => $ate, 'itens' => array_values( $ag ) ] );
} );

// Gera o XML de transmissão (movimentos não transmitidos do período) e marca como transmitido
add_action( 'wp_ajax_tao_formula_sngpc_xml', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_is_master() ) wp_send_json_error( [ 'message' => 'Só o administrador gera o XML' ], 403 );
    $cliente_id = tao_formula_cliente_id();
    if ( ! $cliente_id ) wp_send_json_error( [ 'message' => 'Cliente não identificado' ] );
    $marcar = ( $_POST['marcar'] ?? '' ) === '1';

    // dados da farmácia (emitente)
    $rc = tao_formula_api( "/empresa_config?cliente_id=eq.$cliente_id&limit=1" );
    $emp = ( $rc['ok'] && ! empty( $rc['data'] ) ) ? $rc['data'][0] : [];
    $cnpj = preg_replace( '/\D/', '', (string) ( $emp['cnpj'] ?? '' ) );

    $rm = tao_formula_api( "/sngpc_movimentos?cliente_id=eq.$cliente_id&transmitido=eq.false&order=dt_movimento.asc&limit=2000" );
    $movs = $rm['ok'] ? ( $rm['data'] ?? [] ) : [];
    if ( ! $movs ) wp_send_json_error( [ 'message' => 'Nenhum movimento pendente de transmissão.' ] );

    // XML no formato do wrapper SNGPC (arquivo_sngpc) — estrutura espelhando o FCerta
    $x  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $x .= '<mensagemSNGPC>' . "\n";
    $x .= "  <cnpj>" . htmlspecialchars( $cnpj ) . "</cnpj>\n";
    $x .= "  <razaoSocial>" . htmlspecialchars( (string) ( $emp['razao_social'] ?? '' ) ) . "</razaoSocial>\n";
    $x .= "  <periodo><inicio>" . $movs[0]['dt_movimento'] . "</inicio><fim>" . end( $movs )['dt_movimento'] . "</fim></periodo>\n";
    $x .= "  <movimentacoes>\n";
    foreach ( $movs as $m ) {
        $x .= "    <mov tipo=\"{$m['tipo']}\">\n";
        $x .= "      <dcb>" . htmlspecialchars( (string) $m['dcb'] ) . "</dcb>\n";
        $x .= "      <registroMS>" . htmlspecialchars( (string) $m['registro_ms'] ) . "</registroMS>\n";
        $x .= "      <lote>" . htmlspecialchars( (string) $m['nr_lote'] ) . "</lote>\n";
        $x .= "      <quantidade>" . $m['quantidade'] . "</quantidade>\n";
        $x .= "      <data>" . $m['dt_movimento'] . "</data>\n";
        if ( $m['tipo'] === 'saida' ) {
            $x .= "      <prescritor nome=\"" . htmlspecialchars( (string) $m['prescritor_nome'] ) . "\" conselho=\"" . htmlspecialchars( (string) $m['prescritor_conselho'] ) . "\" nr=\"" . htmlspecialchars( (string) $m['prescritor_nr'] ) . "\" uf=\"" . htmlspecialchars( (string) $m['prescritor_uf'] ) . "\"/>\n";
            $x .= "      <comprador nome=\"" . htmlspecialchars( (string) $m['comprador_nome'] ) . "\" doc=\"" . htmlspecialchars( (string) $m['comprador_doc_nr'] ) . "\"/>\n";
            $x .= "      <notificacao>" . htmlspecialchars( (string) $m['nr_notificacao'] ) . "</notificacao>\n";
        }
        if ( $m['tipo'] === 'entrada' ) $x .= "      <fornecedor cnpj=\"" . htmlspecialchars( (string) $m['fornecedor_cnpj'] ) . "\" nf=\"" . htmlspecialchars( (string) $m['nf_numero'] ) . "\"/>\n";
        if ( $m['tipo'] === 'perda' ) $x .= "      <tipoPerda>" . htmlspecialchars( (string) $m['tp_perda'] ) . "</tipoPerda>\n";
        $x .= "    </mov>\n";
    }
    $x .= "  </movimentacoes>\n</mensagemSNGPC>\n";

    if ( $marcar ) {
        $ids = array_column( $movs, 'id' );
        tao_formula_api( "/sngpc_movimentos?id=in.(" . implode( ',', $ids ) . ")", 'PATCH', [ 'transmitido' => true, 'dt_transmissao' => gmdate( 'c' ) ] );
    }
    wp_send_json_success( [ 'xml' => $x, 'movimentos' => count( $movs ), 'marcado' => $marcar ] );
} );

// Busca de ativos controlados (para o lançamento)
add_action( 'wp_ajax_tao_formula_sngpc_busca_ativo', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( 'Acesso negado', 403 );
    $cliente_id = tao_formula_cliente_id();
    $q = sanitize_text_field( $_GET['q'] ?? '' );
    if ( ! $cliente_id || mb_strlen( $q ) < 2 ) { wp_send_json_success( [] ); return; }
    $enc = rawurlencode( $q );
    $r = tao_formula_api(
        "/ativos?cliente_id=eq.$cliente_id&controlado=eq.true&nome=ilike.*{$enc}*&select=id,nome,dcb,classe_sngpc,unidade_padrao&order=nome.asc&limit=10"
    );
    wp_send_json_success( $r['ok'] ? ( $r['data'] ?? [] ) : [] );
} );
