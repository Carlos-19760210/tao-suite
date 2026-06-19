<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// ── Busca de Ativos (autocomplete) ───────────────────────────────────────────

add_action( 'wp_ajax_tao_formula_search_ativos', function() {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( 'Acesso negado', 403 );

    $q          = sanitize_text_field( $_GET['q'] ?? '' );
    $grupo      = sanitize_text_field( $_GET['grupo'] ?? '' );
    $cliente_id = tao_formula_cliente_id();
    if ( ! $cliente_id ) wp_send_json_error( 'Cliente não identificado', 400 );

    $term  = urlencode( $q );
    $base  = "/ativos?cliente_id=eq.$cliente_id&ativo=eq.true" .
             "&select=id,codigo_fc,nome,unidade,unidade_padrao,preco_compra,custo_por_unidade,preco_venda,fator_correcao,fator_perda,densidade,diluicao,teor,grupo,concentracao" .
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

    if ( $r['ok'] ) {
        wp_send_json_success( $r['data'] );
    } else {
        wp_send_json_error( $r['raw'] );
    }
} );

// ── Detalhe de um Ativo ───────────────────────────────────────────────────────

add_action( 'wp_ajax_tao_formula_get_ativo', function() {
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( 'Acesso negado', 403 );

    $id         = sanitize_text_field( $_GET['id'] ?? '' );
    $cliente_id = tao_formula_cliente_id();
    if ( ! $id || ! $cliente_id ) wp_send_json_error( 'Parâmetros inválidos', 400 );

    $r = tao_formula_api(
        "/ativos?id=eq.$id&cliente_id=eq.$cliente_id" .
        "&select=id,codigo_fc,nome,grupo,unidade,unidade_padrao,estoque_atual,preco_compra,preco_custo," .
        "custo_por_unidade,preco_venda,fator_correcao,fator_perda,densidade,dcb,dose_min,uni_dose_min," .
        "dose_max,uni_dose_max,categoria,classe_terapeutica,principio_ativo,observacoes,sincronizado_em" .
        "&limit=1"
    );

    if ( $r['ok'] && ! empty( $r['data'] ) ) {
        wp_send_json_success( $r['data'][0] );
    } else {
        wp_send_json_error( $r['ok'] ? 'Não encontrado' : $r['raw'], 404 );
    }
} );

// ── Helper: gerar número de orçamento (YYYYMMSeq / YYYYMMSeq-XX) ─────────────

function tao_formula_gerar_numero( $cliente_id, $card_id = null ) {
    $prefix = date( 'Ym' ); // ex: "202606"
    $r      = tao_formula_api(
        "/orcamentos?cliente_id=eq.$cliente_id" .
        "&numero_orcamento=like.{$prefix}*" .
        "&select=numero_orcamento,card_id&order=numero_orcamento.asc&limit=500"
    );
    $todos = ( $r['ok'] && is_array( $r['data'] ) ) ? $r['data'] : [];

    $max_seq   = 0;
    $card_base = null;

    foreach ( $todos as $row ) {
        $num   = $row['numero_orcamento'] ?? '';
        if ( ! $num ) continue;
        $parts = explode( '-', $num );
        $base  = $parts[0];
        $seq   = (int) substr( $base, strlen( $prefix ) );
        if ( $seq > $max_seq ) $max_seq = $seq;
        if ( $card_id && ( $row['card_id'] ?? '' ) === $card_id && count( $parts ) === 1 ) {
            $card_base = $base;
        }
    }

    if ( $card_id && $card_base ) {
        $max_suf = 0;
        foreach ( $todos as $row ) {
            $parts = explode( '-', $row['numero_orcamento'] ?? '' );
            if ( $parts[0] === $card_base && count( $parts ) === 2 ) {
                $s = (int) $parts[1];
                if ( $s > $max_suf ) $max_suf = $s;
            }
        }
        return $card_base . '-' . str_pad( $max_suf + 1, 2, '0', STR_PAD_LEFT );
    }

    return $prefix . str_pad( $max_seq + 1, 4, '0', STR_PAD_LEFT );
}

// ── Helper: linha de descrição da fórmula para WhatsApp ──────────────────────

function tao_formula_build_descricao( $forma_nome, $forma_vol, $forma_unidade, $itens, $qtde_potes = 1 ) {
    $vol_str = $forma_vol ? strtoupper( $forma_vol . $forma_unidade ) : '';
    $header  = 'FORMULA MANIPULADA' . ( $forma_nome ? ' - ' . strtoupper( $forma_nome ) : '' );
    if ( $vol_str ) $header .= ': ' . $vol_str;

    $parts = [];
    foreach ( (array) $itens as $item ) {
        if ( ( $item['tipo'] ?? 'mp' ) !== 'mp' ) continue;
        $nome = strtoupper( $item['nome'] ?? '' );
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
    return [
        'nome_paciente'       => sanitize_text_field( $_POST['nome_paciente'] ?? '' ),
        'whatsapp'            => sanitize_text_field( $_POST['whatsapp'] ?? '' ),
        'forma_id'            => $forma_id ?: null,
        'forma_nome'          => sanitize_text_field( $_POST['forma_nome'] ?? '' ),
        'forma_vol'           => $_POST['forma_vol'] !== '' ? (float) ( $_POST['forma_vol'] ?? 0 ) : null,
        'forma_unidade'       => sanitize_text_field( $_POST['forma_unidade'] ?? 'g' ),
        'qtde_potes'          => max( 1, (int) ( $_POST['qtde_potes'] ?? 1 ) ),
        'custo_fixo_aplicado' => (float) ( $_POST['custo_fixo']      ?? 0 ),
        'total_insumos'       => (float) ( $_POST['total_insumos']   ?? 0 ),
        'margem_aplicada'     => (float) ( $_POST['margem_pct']      ?? 0 ),
        'desconto_pct'        => (float) ( $_POST['desconto_pct']    ?? 0 ),
        'total_orcamento'     => (float) ( $_POST['total_orcamento'] ?? 0 ),
        'observacoes'         => sanitize_textarea_field( $_POST['observacoes'] ?? '' ),
        'itens'               => $itens,
        'atualizado_em'       => gmdate( 'c' ),
    ];
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

    $r = tao_formula_api( '/orcamentos', 'POST', $data );
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

    $re = tao_formula_api( "/orcamentos?id=eq.$orc_id&cliente_id=eq.$cliente_id&select=card_id,numero_orcamento,total_orcamento&limit=1" );
    if ( ! $re['ok'] || empty( $re['data'] ) ) wp_send_json_error( 'Orçamento não encontrado', 404 );
    $exist = $re['data'][0];

    $itens_raw = stripslashes( $_POST['itens'] ?? '[]' );
    $itens     = json_decode( $itens_raw, true );
    if ( ! is_array( $itens ) ) $itens = [];

    $data = tao_formula_orc_payload( $itens );

    $r = tao_formula_api( "/orcamentos?id=eq.$orc_id&cliente_id=eq.$cliente_id", 'PATCH', $data );
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
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_is_master() ) wp_send_json_error( 'Acesso negado', 403 );

    update_option( 'tao_formula_margem_padrao', (float) ( $_POST['margem_padrao'] ?? 30 ) );

    wp_send_json_success( 'Configurações salvas.' );
} );

// ── Status do orçamento ───────────────────────────────────────────────────────

add_action( 'wp_ajax_tao_formula_update_orc_status', function() {
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( 'Acesso negado', 403 );

    $id     = sanitize_text_field( $_POST['id'] ?? '' );
    $status = sanitize_text_field( $_POST['status'] ?? '' );
    $allowed = [ 'pendente_revisao', 'aprovado_farma', 'enviado_paciente', 'aceito_paciente', 'rejeitado' ];
    if ( ! $id || ! in_array( $status, $allowed, true ) ) wp_send_json_error( 'Parâmetros inválidos', 400 );

    $cliente_id = tao_formula_cliente_id();
    $data = [
        'status'       => $status,
        'atualizado_em'=> gmdate( 'c' ),
    ];
    if ( $status === 'aprovado_farma' ) {
        $data['farmaceutico_id'] = get_current_user_id();
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
            '&select=id,codigo_fc,nome,unidade_padrao,preco_venda,custo_por_unidade,diluicao,teor&limit=1'
        );
        $at = ( $ra['ok'] && ! empty( $ra['data'] ) ) ? $ra['data'][0] : null;

        // Fallback 2: sinônimo exato (case-insensitive)
        if ( ! $at && $nome_a ) {
            $rs = tao_formula_api(
                '/ativos_sinonimos?cliente_id=eq.' . $cliente_id .
                '&sinonimo=ilike.' . rawurlencode( strtolower( $nome_a ) ) .
                '&select=ativo_id&limit=1'
            );
            if ( $rs['ok'] && ! empty( $rs['data'] ) ) {
                $ra2 = tao_formula_api(
                    '/ativos?id=eq.' . $rs['data'][0]['ativo_id'] .
                    '&cliente_id=eq.' . $cliente_id .
                    '&select=id,codigo_fc,nome,unidade_padrao,preco_venda,custo_por_unidade,diluicao,teor&limit=1'
                );
                $at = ( $ra2['ok'] && ! empty( $ra2['data'] ) ) ? $ra2['data'][0] : null;
            }
        }

        // Fallback 3: sinônimo por substring — "VIT D" encontra "VITAMINA D3", "LACTOB" → "LACTOBACILLUS"
        if ( ! $at && $nome_a && mb_strlen( $nome_a ) >= 3 ) {
            $rs = tao_formula_api(
                '/ativos_sinonimos?cliente_id=eq.' . $cliente_id .
                '&sinonimo=ilike.*' . rawurlencode( $nome_a ) . '*' .
                '&select=ativo_id&limit=1'
            );
            if ( $rs['ok'] && ! empty( $rs['data'] ) ) {
                $ra2 = tao_formula_api(
                    '/ativos?id=eq.' . $rs['data'][0]['ativo_id'] .
                    '&cliente_id=eq.' . $cliente_id .
                    '&select=id,codigo_fc,nome,unidade_padrao,preco_venda,custo_por_unidade,diluicao,teor&limit=1'
                );
                $at = ( $ra2['ok'] && ! empty( $ra2['data'] ) ) ? $ra2['data'][0] : null;
            }
        }

        if ( ! $at ) $nao_encontrados[] = $nome_a;

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
    if ( ! $cliente_id || ! $card_id ) wp_send_json_error( [ 'message' => 'Parâmetros inválidos' ] );

    $ro = tao_formula_api( "/orcamentos?card_id=eq.$card_id&cliente_id=eq.$cliente_id&select=id,volume,itens" );
    if ( ! $ro['ok'] || empty( $ro['data'] ) ) {
        wp_send_json_success( [ 'atualizados' => 0, 'message' => 'Nenhum orçamento encontrado para este card.' ] );
    }

    $sel_at     = 'id,codigo_fc,nome,unidade_padrao,preco_venda,custo_por_unidade,diluicao,teor';
    $total_upd  = 0;

    foreach ( $ro['data'] as $orc ) {
        $itens  = $orc['itens'] ?? [];
        if ( ! is_array( $itens ) ) continue;
        $volume = max( 1, (float) ( $orc['volume'] ?? 30 ) );

        $modified = false;
        foreach ( $itens as &$item ) {
            if ( ! empty( $item['ativo_id'] ) || empty( $item['nome'] ) ) continue;

            $nome = $item['nome'];
            $at   = null;

            // 1) ilike no nome/codigo
            $ra = tao_formula_api( '/ativos?cliente_id=eq.' . $cliente_id . '&or=(nome.ilike.*' . rawurlencode( $nome ) . '*,codigo_fc.ilike.*' . rawurlencode( $nome ) . '*)&select=' . $sel_at . '&limit=1' );
            if ( $ra['ok'] && ! empty( $ra['data'] ) ) $at = $ra['data'][0];

            // 2) Sinônimo exato
            if ( ! $at ) {
                $rs = tao_formula_api( '/ativos_sinonimos?cliente_id=eq.' . $cliente_id . '&sinonimo=ilike.' . rawurlencode( strtolower( $nome ) ) . '&select=ativo_id&limit=1' );
                if ( $rs['ok'] && ! empty( $rs['data'] ) ) {
                    $ra2 = tao_formula_api( '/ativos?id=eq.' . $rs['data'][0]['ativo_id'] . '&cliente_id=eq.' . $cliente_id . '&select=' . $sel_at . '&limit=1' );
                    if ( $ra2['ok'] && ! empty( $ra2['data'] ) ) $at = $ra2['data'][0];
                }
            }

            // 3) Sinônimo substring
            if ( ! $at && mb_strlen( $nome ) >= 3 ) {
                $rs = tao_formula_api( '/ativos_sinonimos?cliente_id=eq.' . $cliente_id . '&sinonimo=ilike.*' . rawurlencode( $nome ) . '*&select=ativo_id&limit=1' );
                if ( $rs['ok'] && ! empty( $rs['data'] ) ) {
                    $ra2 = tao_formula_api( '/ativos?id=eq.' . $rs['data'][0]['ativo_id'] . '&cliente_id=eq.' . $cliente_id . '&select=' . $sel_at . '&limit=1' );
                    if ( $ra2['ok'] && ! empty( $ra2['data'] ) ) $at = $ra2['data'][0];
                }
            }

            if ( ! $at ) continue;

            // Recalcular qtd e subtotal com o ativo encontrado
            $dose      = (float) ( $item['dose']     ?? 0 );
            $dose_unit = $item['dose_unit'] ?? 'mg';
            $is_qsp    = ! empty( $item['is_qsp'] );
            $unid_p    = $at['unidade_padrao'] ?? 'mg';
            $preco     = (float) ( $at['preco_venda'] ?? 0 );
            $dose_g    = $dose_unit === 'g' ? $dose : ( $dose_unit === 'mg' ? $dose / 1000 : ( $dose_unit === '%' ? $dose / 100 * $volume : 0 ) );
            $qtd_tot_g = $is_qsp ? 0 : round( $dose_g * ( $dose_unit === '%' ? 1 : $volume ), 6 );
            $qtd_em_u  = $unid_p === 'g' ? $qtd_tot_g : $qtd_tot_g * 1000;
            $subtotal  = ( $preco > 0 && ! $is_qsp ) ? round( $qtd_em_u * $preco, 4 ) : 0;

            $item['ativo_id']          = $at['id'];
            $item['nome']              = $at['nome'];
            $item['codigo_fc']         = $at['codigo_fc']         ?? '';
            $item['unid_padrao']       = $unid_p;
            $item['preco_venda']       = $preco;
            $item['custo_por_unidade'] = (float) ( $at['custo_por_unidade'] ?? 0 );
            $item['diluicao']          = (float) ( $at['diluicao'] ?? 1 );
            $item['teor']              = (float) ( $at['teor'] ?? 100 );
            $item['qtd_total_g']       = $qtd_tot_g;
            $item['subtotal']          = $subtotal;

            $modified = true;
            $total_upd++;
        }
        unset( $item );

        if ( $modified ) {
            tao_formula_api( '/orcamentos?id=eq.' . $orc['id'] . '&cliente_id=eq.' . $cliente_id, 'PATCH', [ 'itens' => $itens ] );
        }
    }

    wp_send_json_success( [
        'atualizados' => $total_upd,
        'message'     => $total_upd > 0
            ? $total_upd . ' ativo(s) associado(s) com sucesso.'
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
    $r = tao_formula_api( "/orcamentos?id=eq.$orc_id&cliente_id=eq.$cliente_id", 'DELETE' );
    $r['ok'] ? wp_send_json_success() : wp_send_json_error( [ 'message' => 'Erro: ' . $r['raw'] ] );
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
    $r = tao_formula_api( '/ativos_sinonimos', 'POST', [
        'cliente_id' => $cliente_id,
        'ativo_id'   => $ativo_id,
        'sinonimo'   => $sinonimo,
    ] );
    $r['ok'] ? wp_send_json_success() : wp_send_json_error( [ 'message' => 'Erro ao salvar (talvez já exista): ' . $r['raw'] ] );
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
    $offset = max( 0, intval( $_POST['offset']      ?? 0 ) );
    $limit  = 30;

    $url = '/ativos?cliente_id=eq.' . $cliente_id
        . ( $q ? '&or=(nome.ilike.*' . rawurlencode( $q ) . '*,codigo_fc.ilike.*' . rawurlencode( $q ) . '*)' : '' )
        . '&select=id,nome,codigo_fc&order=nome.asc&limit=' . $limit . '&offset=' . $offset;

    $r = tao_formula_api( $url );
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
        'ativos'   => $ativos,
        'has_more' => count( $ativos ) === $limit,
        'offset'   => $offset,
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
