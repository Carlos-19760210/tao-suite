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
        $nome = strtoupper( $item['nome_prescricao'] ?? $item['nome'] ?? '' );
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

    $ro = tao_formula_api( "/orcamentos?card_id=eq.$card_id&cliente_id=eq.$cliente_id&select=id,forma_vol,qtde_potes,itens" );
    if ( ! $ro['ok'] ) {
        wp_send_json_error( [ 'message' => 'Erro ao buscar orçamentos: ' . ( $ro['raw'] ?? '' ) ] );
        return;
    }
    if ( empty( $ro['data'] ) ) {
        wp_send_json_success( [ 'atualizados' => 0, 'message' => 'Nenhum orçamento encontrado para este card.' ] );
        return;
    }

    $sel_at    = 'id,codigo_fc,nome,unidade_padrao,preco_venda,custo_por_unidade,fator_perda,diluicao,teor';
    $total_upd = 0;

    foreach ( $ro['data'] as $orc ) {
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
                    '&select=ativo_id&limit=1'
                );
                if ( $rs['ok'] && ! empty( $rs['data'] ) ) {
                    $ra2 = tao_formula_api( '/ativos?id=eq.' . $rs['data'][0]['ativo_id'] . '&cliente_id=eq.' . $cliente_id . '&select=' . $sel_at . '&limit=1' );
                    if ( $ra2['ok'] && ! empty( $ra2['data'] ) ) $at = $ra2['data'][0];
                }
            }

            // 3) Sinônimo — substring
            if ( ! $at && mb_strlen( $nome_busca ) >= 3 ) {
                $rs = tao_formula_api(
                    '/ativos_sinonimos?cliente_id=eq.' . $cliente_id .
                    '&sinonimo=ilike.*' . $nome_enc . '*' .
                    '&select=ativo_id&limit=1'
                );
                if ( $rs['ok'] && ! empty( $rs['data'] ) ) {
                    $ra2 = tao_formula_api( '/ativos?id=eq.' . $rs['data'][0]['ativo_id'] . '&cliente_id=eq.' . $cliente_id . '&select=' . $sel_at . '&limit=1' );
                    if ( $ra2['ok'] && ! empty( $ra2['data'] ) ) $at = $ra2['data'][0];
                }
            }

            if ( ! $at ) continue;

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
                $dose_mg_real  = $dose_mg * $diluicao / max( 0.001, $teor / 100 );
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
    $rc = tao_formula_api( "/orcamentos?id=eq.$orc_id&cliente_id=eq.$cliente_id&select=card_id&limit=1" );
    $del_card_id = ( $rc['ok'] && ! empty( $rc['data'] ) ) ? ( $rc['data'][0]['card_id'] ?? '' ) : '';
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
    $r = tao_formula_api( '/ativos_sinonimos', 'POST', [
        'cliente_id' => $cliente_id,
        'ativo_id'   => $ativo_id !== '' ? $ativo_id : null,
        'sinonimo'   => $sinonimo,
    ] );
    $r['ok'] ? wp_send_json_success() : wp_send_json_error( [ 'message' => 'Erro ao criar (talvez já exista): ' . $r['raw'] ] );
} );

// ── Sinônimos: termos dos ORÇAMENTOS sem associação a um ativo base ───────────
// Varre os itens (JSON) dos orçamentos e lista os ingredientes (mp) com ativo_id vazio,
// agrupados por nome, ignorando os que já têm sinônimo cadastrado.
add_action( 'wp_ajax_tao_formula_sinonimos_nao_atribuidos', function () {
    while ( ob_get_level() > 0 ) ob_end_clean();
    check_ajax_referer( 'tao_formula_nonce', 'nonce' );
    if ( ! tao_formula_can_access() ) wp_send_json_error( 'Acesso negado', 403 );
    $cliente_id = tao_formula_cliente_id();
    if ( ! $cliente_id ) wp_send_json_error( 'Cliente não identificado', 400 );

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
            $key = mb_strtoupper( $nome );
            if ( ! isset( $termos[ $key ] ) ) $termos[ $key ] = [ 'nome' => $nome, 'count' => 0, 'orcs' => [] ];
            $termos[ $key ]['count']++;
            $num = $o['numero_orcamento'] ?? '';
            if ( $num && count( $termos[ $key ]['orcs'] ) < 3 && ! in_array( $num, $termos[ $key ]['orcs'], true ) ) $termos[ $key ]['orcs'][] = $num;
        }
    }
    // Remove os que já possuem sinônimo cadastrado (associado a um ativo)
    $jaSin = [];
    $rs = tao_formula_api( '/ativos_sinonimos?cliente_id=eq.' . $cliente_id . '&ativo_id=not.is.null&select=sinonimo' );
    foreach ( ( $rs['data'] ?? [] ) as $s ) $jaSin[ mb_strtoupper( trim( $s['sinonimo'] ) ) ] = true;
    $out = [];
    foreach ( $termos as $key => $t ) { if ( ! isset( $jaSin[ $key ] ) ) $out[] = $t; }
    usort( $out, function ( $a, $b ) { return $b['count'] - $a['count']; } );
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

        // Tenta extrair "NOME DOSE UNIT"
        if ( preg_match( '/^(.+?)\s+([\d.,]+)\s*(mg|mcg|g|UI|UFC|BLH|ml|%)\s*$/i', $part, $im ) ) {
            $nome      = trim( $im[1] );
            $dose      = (float) str_replace( ',', '.', $im[2] );
            $raw_unit  = $im[3];
            $dose_unit = in_array( strtolower($raw_unit), ['ui','ufc','blh'] )
                         ? strtoupper($raw_unit) : strtolower($raw_unit);
        }
        if ( ! $nome ) continue;

        // Busca ativo pelo nome — wildcards (*) NÃO devem ser encoded (PostgREST usa * como glob)
        $nome_enc = rawurlencode( $nome );
        $sel_ativo = 'id,nome,codigo_fc,preco_venda,custo_por_unidade,unidade_padrao,fator_perda,diluicao,teor,densidade,concentracao';
        $ra  = tao_formula_api(
            "/ativos?cliente_id=eq.{$cliente_id}&nome=ilike.*{$nome_enc}*&select={$sel_ativo}&limit=1"
        );

        // Fallback: se não achou por nome, tenta um sinônimo cadastrado (ativos_sinonimos)
        if ( ! ( $ra['ok'] && ! empty( $ra['data'] ) ) ) {
            $rs = tao_formula_api(
                "/ativos_sinonimos?cliente_id=eq.{$cliente_id}&sinonimo=ilike." . rawurlencode( $nome ) . "&select=ativo_id&limit=1"
            );
            if ( $rs['ok'] && ! empty( $rs['data'] ) ) {
                $aid = $rs['data'][0]['ativo_id'];
                $ra  = tao_formula_api( "/ativos?id=eq.{$aid}&cliente_id=eq.{$cliente_id}&select={$sel_ativo}&limit=1" );
            }
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
        $total_insumos = 0.0;
        foreach ( $itens_mp as &$item ) {
            if ( $item['tipo'] !== 'mp' || $item['is_qsp'] ) continue;
            $dose      = (float)( $item['dose'] ?? 0 );
            $dose_unit = strtolower( $item['dose_unit'] ?? 'mg' );
            $unid_pad  = strtolower( $item['unid_padrao'] ?? 'mg' );
            $fp        = (float)( $item['fp']       ?? 1 );
            $diluicao  = (float)( $item['diluicao'] ?? 1 );
            $teor      = (float)( $item['teor']     ?? 100 );
            $preco     = (float)( $item['preco_venda'] ?? 0 );

            if ( $dose > 0 && $preco > 0 ) {
                switch ( $dose_unit ) {
                    case 'g':   $dose_mg = $dose * 1000; break;
                    case 'mcg': $dose_mg = $dose / 1000; break;
                    default:    $dose_mg = $dose; break; // mg (e outros)
                }
                $dose_mg_real  = $dose_mg * $diluicao / max( 0.001, $teor / 100 );
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
        $acrescimo_val = round( $valor - $subtotal_calc, 2 );                    // Acréscimo = VALOR FINAL − Sub-Total
        $acrescimo_pct = $subtotal_calc > 0.005 ? round( $acrescimo_val / $subtotal_calc * 100, 2 ) : 0.0;
        $desconto_pct  = $subtotal_calc > 0.005 ? round( $desconto_val  / $subtotal_calc * 100, 2 ) : 0.0;
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
            'margem_aplicada'     => $acrescimo_pct,
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
