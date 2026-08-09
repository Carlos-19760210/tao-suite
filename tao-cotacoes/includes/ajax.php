<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Guarda comum dos handlers AJAX. Retorna o cliente_id ou encerra com erro.
 */
function tao_cot_ajax_guard() {
    while ( ob_get_level() > 0 ) ob_end_clean();
    nocache_headers();
    check_ajax_referer( 'tao_cot_nonce', 'nonce' );
    if ( ! tao_cot_pode() ) wp_send_json_error( 'Sem permissão para operar cotações', 403 );
    $cid = tao_cot_cliente_id();
    if ( ! $cid ) wp_send_json_error( 'Cliente não identificado', 400 );
    return $cid;
}

// ── Fornecedores ─────────────────────────────────────────────────────────────

add_action( 'wp_ajax_tao_cot_save_fornecedor', function() {
    $cid = tao_cot_ajax_guard();

    $id   = sanitize_text_field( $_POST['id'] ?? '' );
    $nome = trim( sanitize_text_field( $_POST['nome'] ?? '' ) );
    $zap  = preg_replace( '/\D/', '', $_POST['whatsapp'] ?? '' );
    if ( $nome === '' ) wp_send_json_error( 'Informe o nome do fornecedor' );
    if ( strlen( $zap ) < 10 ) wp_send_json_error( 'Informe o WhatsApp com DDD (só números)' );

    $payload = [
        'nome'     => $nome,
        'whatsapp' => $zap,
        'contato'  => sanitize_text_field( $_POST['contato'] ?? '' ),
        'obs'      => sanitize_textarea_field( $_POST['obs'] ?? '' ),
        'ativo'    => ( ( $_POST['ativo'] ?? '1' ) === '1' ),
    ];
    // Dados fiscais/contato (migration_v2) — texto vazio vira null
    $txt = function( $k, $upper = false ) {
        $v = trim( sanitize_text_field( $_POST[ $k ] ?? '' ) );
        if ( $upper ) $v = strtoupper( $v );
        return $v === '' ? null : $v;
    };
    $cnpj   = preg_replace( '/\D/', '', $_POST['cnpj'] ?? '' );
    $extras = [
        'cnpj'            => $cnpj ?: null,
        'razao_social'    => $txt( 'razao_social', true ),
        'nome_fantasia'   => $txt( 'nome_fantasia' ),
        'inscr_estadual'  => $txt( 'inscr_estadual' ),
        'endereco'        => $txt( 'endereco' ),
        'cidade'          => $txt( 'cidade' ),
        'uf'              => $txt( 'uf', true ),
        'cep'             => $txt( 'cep' ),
        'telefone'        => $txt( 'telefone' ),
        'email'           => $txt( 'email' ),
        'prazo_pagamento' => $txt( 'prazo_pagamento' ),
    ];

    $salvar = function( $body ) use ( $id, $cid ) {
        if ( $id ) return tao_cot_api( "/fornecedores?id=eq.$id&cliente_id=eq.$cid", 'PATCH', $body );
        $body['cliente_id'] = $cid;
        return tao_cot_api( '/fornecedores', 'POST', $body );
    };
    $aviso = '';
    $r = $salvar( array_merge( $payload, $extras ) );
    // Migration v2 ainda não rodada → grava os campos base (não perde o cadastro)
    if ( ! $r['ok'] && strpos( (string) ( $r['raw'] ?? '' ), 'column' ) !== false ) {
        $r = $salvar( $payload );
        if ( $r['ok'] && array_filter( $extras ) ) {
            $aviso = 'Campos fiscais NÃO gravados — rode a migration_v2_orc_fornec_hist.sql no Supabase.';
        }
    }
    if ( ! $r['ok'] ) wp_send_json_error( 'Falha ao salvar: ' . ( $r['raw'] ?? '' ) );

    tao_cotacoes_limpar_cache_fornecedores( $cid );
    $out = $r['data'][0] ?? [];
    if ( $aviso ) $out['aviso'] = $aviso;
    wp_send_json_success( $out );
} );

add_action( 'wp_ajax_tao_cot_delete_fornecedor', function() {
    $cid = tao_cot_ajax_guard();
    $id  = sanitize_text_field( $_POST['id'] ?? '' );
    if ( ! $id ) wp_send_json_error( 'ID inválido' );

    // Fornecedor já usado em cotação: desativa em vez de excluir (preserva histórico)
    $ru = tao_cot_api( "/cotacao_fornecedores?fornecedor_id=eq.$id&select=id&limit=1" );
    if ( $ru['ok'] && ! empty( $ru['data'] ) ) {
        $r = tao_cot_api( "/fornecedores?id=eq.$id&cliente_id=eq.$cid", 'PATCH', [ 'ativo' => false ] );
        if ( ! $r['ok'] ) wp_send_json_error( 'Falha ao desativar: ' . ( $r['raw'] ?? '' ) );
        tao_cotacoes_limpar_cache_fornecedores( $cid );
        wp_send_json_success( [ 'desativado' => true ] );
    }

    $r = tao_cot_api( "/fornecedores?id=eq.$id&cliente_id=eq.$cid", 'DELETE' );
    if ( ! $r['ok'] ) wp_send_json_error( 'Falha ao excluir: ' . ( $r['raw'] ?? '' ) );
    tao_cotacoes_limpar_cache_fornecedores( $cid );
    wp_send_json_success();
} );

// ── Busca de ativos (combo digitável) ────────────────────────────────────────

add_action( 'wp_ajax_tao_cot_search_ativos', function() {
    $cid = tao_cot_ajax_guard();
    $q   = trim( sanitize_text_field( $_POST['q'] ?? '' ) );
    if ( strlen( $q ) < 2 ) wp_send_json_success( [] );

    $q_enc = rawurlencode( '*' . $q . '*' );
    $r = tao_cot_api( "/ativos?cliente_id=eq.$cid&ativo=eq.true&nome=ilike.$q_enc&select=id,nome,codigo_fc,preco_compra&order=nome.asc&limit=12" );
    wp_send_json_success( $r['ok'] ? $r['data'] : [] );
} );

// Busca de fornecedores (para registrar retorno escolhendo qualquer fornecedor)
add_action( 'wp_ajax_tao_cot_search_fornecedores', function() {
    $cid = tao_cot_ajax_guard();
    $q   = trim( sanitize_text_field( $_POST['q'] ?? '' ) );
    if ( strlen( $q ) < 2 ) wp_send_json_success( [] );
    $q_enc = rawurlencode( '*' . $q . '*' );
    $r = tao_cot_api( "/fornecedores?cliente_id=eq.$cid&ativo=eq.true&nome=ilike.$q_enc&select=id,nome,contato,whatsapp&order=nome.asc&limit=12" );
    wp_send_json_success( $r['ok'] ? $r['data'] : [] );
} );

// ── Parse da planilha de estoque mínimo (via N8N no Cloudfy) ─────────────────

add_action( 'wp_ajax_tao_cot_parse_planilha', function() {
    $cid = tao_cot_ajax_guard();

    if ( empty( $_FILES['file']['tmp_name'] ) ) wp_send_json_error( 'Nenhum arquivo recebido' );
    $fname = $_FILES['file']['name'] ?? 'planilha.xls';
    if ( ! preg_match( '/\.xlsx?$/i', $fname ) ) wp_send_json_error( 'Envie a planilha de estoque mínimo no formato .xls/.xlsx' );
    if ( $_FILES['file']['size'] > 5 * 1024 * 1024 ) wp_send_json_error( 'Arquivo acima de 5 MB' );

    $webhook = get_option( 'tao_cotacoes_n8n_parse_url', '' );
    $token   = get_option( 'tao_cotacoes_n8n_token', '' );
    if ( ! $webhook ) wp_send_json_error( 'Webhook de processamento não configurado (tao_cotacoes_n8n_parse_url)' );

    $bin  = file_get_contents( $_FILES['file']['tmp_name'] );
    $resp = wp_remote_post( $webhook, [
        'timeout' => 60,
        'headers' => [
            'Content-Type' => 'application/vnd.ms-excel',
            'X-Tao-Key'    => $token,
            'X-Filename'   => $fname,
        ],
        'body'    => $bin,
    ] );
    if ( is_wp_error( $resp ) ) wp_send_json_error( 'Falha ao processar planilha: ' . $resp->get_error_message() );
    $code = wp_remote_retrieve_response_code( $resp );
    $out  = json_decode( wp_remote_retrieve_body( $resp ), true );
    if ( $code < 200 || $code >= 300 || empty( $out['ok'] ) ) {
        wp_send_json_error( 'Processamento falhou (HTTP ' . $code . '): ' . substr( wp_remote_retrieve_body( $resp ), 0, 300 ) );
    }
    $rows = $out['itens'] ?? [];
    if ( empty( $rows ) ) wp_send_json_error( 'Nenhum item encontrado na planilha' );

    // Matching direto por código FC
    $codigos = array_values( array_unique( array_filter( array_map( fn( $r ) => trim( (string) ( $r['codigo'] ?? '' ) ), $rows ) ) ) );
    $por_codigo = [];
    foreach ( array_chunk( $codigos, 150 ) as $chunk ) {
        $in = implode( ',', array_map( 'rawurlencode', $chunk ) );
        $ra = tao_cot_api( "/ativos?cliente_id=eq.$cid&codigo_fc=in.($in)&select=id,nome,codigo_fc,preco_compra" );
        if ( $ra['ok'] ) foreach ( $ra['data'] as $a ) $por_codigo[ trim( (string) $a['codigo_fc'] ) ] = $a;
    }

    $itens = [];
    $atualizados = 0;
    foreach ( $rows as $r ) {
        $codigo = trim( (string) ( $r['codigo'] ?? '' ) );
        if ( $codigo === '' ) continue;
        $ativo  = $por_codigo[ $codigo ] ?? null;
        $preco_planilha = (float) ( $r['preco_compra'] ?? 0 );

        // Planilha atualiza o benchmark do ativo quando divergir
        if ( $ativo && $preco_planilha > 0 && abs( $preco_planilha - (float) ( $ativo['preco_compra'] ?? 0 ) ) > 0.0001 ) {
            $ru = tao_cot_api( "/ativos?id=eq.{$ativo['id']}", 'PATCH', [ 'preco_compra' => $preco_planilha ] );
            if ( $ru['ok'] ) { $atualizados++; $ativo['preco_compra'] = $preco_planilha; }
        }

        $itens[] = [
            'codigo_fc' => $codigo,
            'ativo_id'  => $ativo['id'] ?? null,
            'descricao' => $ativo['nome'] ?? trim( str_replace( '@', '', (string) ( $r['descricao'] ?? '' ) ) ),
            'unidade'   => strtolower( trim( (string) ( $r['unidade'] ?? '' ) ) ),
            'qtd'       => round( (float) ( $r['qtd_sugerida'] ?? 0 ), 2 ),
            'ult_pago'  => $ativo ? (float) ( $ativo['preco_compra'] ?? 0 ) : $preco_planilha,
            'curva'     => trim( (string) ( $r['curva'] ?? '' ) ),
            'sem_match' => ! $ativo,
        ];
    }

    wp_send_json_success( [ 'itens' => $itens, 'precos_atualizados' => $atualizados ] );
} );

// ── Criar cotação ────────────────────────────────────────────────────────────

add_action( 'wp_ajax_tao_cot_criar_cotacao', function() {
    $cid = tao_cot_ajax_guard();

    $titulo       = trim( sanitize_text_field( $_POST['titulo'] ?? '' ) );
    $instancia_id = sanitize_text_field( $_POST['instancia_id'] ?? '' );
    $enviar       = ( $_POST['enviar'] ?? '0' ) === '1';
    $itens        = json_decode( wp_unslash( $_POST['itens'] ?? '[]' ), true );
    $fornecedores = json_decode( wp_unslash( $_POST['fornecedores'] ?? '[]' ), true );

    if ( empty( $itens ) || ! is_array( $itens ) )               wp_send_json_error( 'A cotação precisa de ao menos 1 item' );
    if ( empty( $fornecedores ) || ! is_array( $fornecedores ) ) wp_send_json_error( 'Selecione ao menos 1 fornecedor' );
    if ( ! $instancia_id )                                       wp_send_json_error( 'Selecione a instância WhatsApp de envio' );
    $tem_urgente = false;
    foreach ( $itens as $it ) if ( ! empty( $it['urgente'] ) ) { $tem_urgente = true; break; }
    if ( ! $tem_urgente ) wp_send_json_error( 'Marque ao menos 1 item urgente (⭐) — são os mandatórios da compra' );

    $rc = tao_cot_api( '/cotacoes', 'POST', [
        'cliente_id'   => $cid,
        'titulo'       => $titulo ?: ( 'Cotação de ' . date_i18n( 'd/m/Y' ) ),
        'status'       => 'rascunho',
        'instancia_id' => $instancia_id,
        'criado_por'   => get_current_user_id(),
    ] );
    if ( ! $rc['ok'] || empty( $rc['data'] ) ) wp_send_json_error( 'Falha ao criar cotação: ' . ( $rc['raw'] ?? '' ) );
    $cot = $rc['data'][0];

    $rows = [];
    foreach ( $itens as $it ) {
        $desc = trim( sanitize_text_field( $it['descricao'] ?? '' ) );
        if ( $desc === '' ) continue;
        $rows[] = [
            'cotacao_id'     => $cot['id'],
            'ativo_id'       => ! empty( $it['ativo_id'] ) ? sanitize_text_field( $it['ativo_id'] ) : null,
            'codigo_fc'      => sanitize_text_field( $it['codigo_fc'] ?? '' ) ?: null,
            'descricao'      => $desc,
            'unidade'        => sanitize_text_field( $it['unidade'] ?? '' ),
            'qtd'            => round( (float) str_replace( ',', '.', (string) ( $it['qtd'] ?? 0 ) ), 2 ),
            'urgente'        => ! empty( $it['urgente'] ),
            'ult_preco_pago' => isset( $it['ult_pago'] ) ? (float) $it['ult_pago'] : null,
            'origem'         => ( $it['origem'] ?? '' ) === 'manual' ? 'manual' : 'planilha',
        ];
    }
    if ( empty( $rows ) ) {
        tao_cot_api( "/cotacoes?id=eq.{$cot['id']}", 'DELETE' );
        wp_send_json_error( 'Nenhum item válido' );
    }
    $ri = tao_cot_api( '/cotacao_itens', 'POST', $rows );
    if ( ! $ri['ok'] ) {
        tao_cot_api( "/cotacoes?id=eq.{$cot['id']}", 'DELETE' );
        wp_send_json_error( 'Falha ao gravar itens: ' . ( $ri['raw'] ?? '' ) );
    }

    $frows = [];
    foreach ( array_unique( array_map( 'sanitize_text_field', $fornecedores ) ) as $fid ) {
        if ( $fid ) $frows[] = [ 'cotacao_id' => $cot['id'], 'fornecedor_id' => $fid ];
    }
    $rf = tao_cot_api( '/cotacao_fornecedores', 'POST', $frows );
    if ( ! $rf['ok'] ) {
        tao_cot_api( "/cotacoes?id=eq.{$cot['id']}", 'DELETE' );
        wp_send_json_error( 'Falha ao vincular fornecedores: ' . ( $rf['raw'] ?? '' ) );
    }

    $envio = null;
    if ( $enviar ) $envio = tao_cot_do_envio( $cot['id'], $cid );

    wp_send_json_success( [ 'id' => $cot['id'], 'numero' => $cot['numero'] ?? null, 'envio' => $envio ] );
} );

// ── Enviar cotação (rascunho ou reenvio de pendentes/erro) ───────────────────

add_action( 'wp_ajax_tao_cot_enviar_cotacao', function() {
    $cid = tao_cot_ajax_guard();
    $id  = sanitize_text_field( $_POST['id'] ?? '' );
    if ( ! $id ) wp_send_json_error( 'ID inválido' );
    $msg_custom = isset( $_POST['msg_custom'] ) ? trim( (string) wp_unslash( $_POST['msg_custom'] ) ) : null;
    $envio = tao_cot_do_envio( $id, $cid, $msg_custom );
    if ( isset( $envio['erro_fatal'] ) ) wp_send_json_error( $envio['erro_fatal'] );
    wp_send_json_success( $envio );
} );

// Prévia da mensagem que será enviada (para revisão do farmacêutico antes do envio)
add_action( 'wp_ajax_tao_cot_preview_msg', function() {
    $cid = tao_cot_ajax_guard();
    $id  = sanitize_text_field( $_POST['id'] ?? '' );
    if ( ! $id ) wp_send_json_error( 'ID inválido' );
    $rc = tao_cot_api( "/cotacoes?id=eq.$id&cliente_id=eq.$cid" );
    if ( ! $rc['ok'] || empty( $rc['data'] ) ) wp_send_json_error( 'Cotação não encontrada' );
    $cot = $rc['data'][0];
    $rit = tao_cot_api( "/cotacao_itens?cotacao_id=eq.$id&order=criado_em.asc&limit=500" );
    $itens = $rit['ok'] ? $rit['data'] : [];
    $rn = tao_cot_api( "/clientes?id=eq.$cid&select=nome_negocio" );
    $nome_negocio = ( $rn['ok'] && ! empty( $rn['data'] ) ) ? ( $rn['data'][0]['nome_negocio'] ?? 'nossa farmácia' ) : 'nossa farmácia';
    $msg = tao_cot_montar_msg( $cot, $itens, $nome_negocio, '{fornecedor}' );
    $rf  = tao_cot_api( "/cotacao_fornecedores?cotacao_id=eq.$id&select=status,fornecedores(nome,whatsapp)" );
    $dest = [];
    foreach ( ( $rf['ok'] ? $rf['data'] : [] ) as $p ) {
        $fn = $p['fornecedores']['nome'] ?? '';
        if ( $fn ) $dest[] = [ 'nome' => $fn, 'tem_wa' => ! empty( $p['fornecedores']['whatsapp'] ), 'status' => $p['status'] ?? '' ];
    }
    // rascunho salvo (revisão anterior do farmacêutico) tem prioridade sobre o gerado
    $salvo = trim( (string) ( $cot['msg_texto'] ?? '' ) );
    wp_send_json_success( [ 'msg' => $msg, 'msg_texto' => $salvo, 'fornecedores' => $dest ] );
} );

// Salvar o texto revisado SEM enviar (permite copiar e enviar manualmente depois)
add_action( 'wp_ajax_tao_cot_salvar_msg', function() {
    $cid = tao_cot_ajax_guard();
    $id  = sanitize_text_field( $_POST['id'] ?? '' );
    if ( ! $id ) wp_send_json_error( 'ID inválido' );
    $rc = tao_cot_api( "/cotacoes?id=eq.$id&cliente_id=eq.$cid&select=id" );
    if ( ! $rc['ok'] || empty( $rc['data'] ) ) wp_send_json_error( 'Cotação não encontrada' );
    $txt = trim( (string) wp_unslash( $_POST['msg_texto'] ?? '' ) );
    $u = tao_cot_api( "/cotacoes?id=eq.$id", 'PATCH', [ 'msg_texto' => $txt ] );
    $u['ok'] ? wp_send_json_success( true ) : wp_send_json_error( 'Falha ao salvar o texto' );
} );

/**
 * Dispara a solicitação WhatsApp para os fornecedores pendentes/erro da cotação.
 */
function tao_cot_do_envio( $cotacao_id, $cid, $msg_custom = null ) {
    $rc = tao_cot_api( "/cotacoes?id=eq.$cotacao_id&cliente_id=eq.$cid" );
    if ( ! $rc['ok'] || empty( $rc['data'] ) ) return [ 'erro_fatal' => 'Cotação não encontrada' ];
    $cot = $rc['data'][0];
    if ( in_array( $cot['status'], [ 'concluida', 'cancelada' ], true ) ) return [ 'erro_fatal' => 'Cotação já encerrada' ];
    // persiste o texto revisado na cotação (para reuso/cópia)
    if ( $msg_custom !== null && trim( $msg_custom ) !== '' ) tao_cot_api( "/cotacoes?id=eq.$cotacao_id", 'PATCH', [ 'msg_texto' => trim( $msg_custom ) ] );

    $ri = tao_cot_api( "/crm_instancias?id=eq.{$cot['instancia_id']}" );
    if ( ! $ri['ok'] || empty( $ri['data'] ) ) return [ 'erro_fatal' => 'Instância WhatsApp não encontrada' ];
    $instancia = $ri['data'][0];

    $rit = tao_cot_api( "/cotacao_itens?cotacao_id=eq.$cotacao_id&order=criado_em.asc&limit=500" );
    $itens = $rit['ok'] ? $rit['data'] : [];
    if ( empty( $itens ) ) return [ 'erro_fatal' => 'Cotação sem itens' ];

    $rf = tao_cot_api( "/cotacao_fornecedores?cotacao_id=eq.$cotacao_id&status=in.(pendente,erro)&select=id,fornecedor_id,fornecedores(nome,whatsapp,contato,ativo)" );
    $participantes = $rf['ok'] ? $rf['data'] : [];
    if ( empty( $participantes ) ) return [ 'enviados' => 0, 'erros' => 0, 'msg' => 'Nenhum fornecedor pendente de envio' ];

    $rn = tao_cot_api( "/clientes?id=eq.$cid&select=nome_negocio" );
    $nome_negocio = $rn['ok'] && ! empty( $rn['data'] ) ? ( $rn['data'][0]['nome_negocio'] ?? 'nossa farmácia' ) : 'nossa farmácia';

    $enviados = 0; $erros = 0;
    foreach ( $participantes as $i => $p ) {
        $f = $p['fornecedores'] ?? null;
        if ( ! $f || empty( $f['whatsapp'] ) ) {
            tao_cot_api( "/cotacao_fornecedores?id=eq.{$p['id']}", 'PATCH', [ 'status' => 'erro', 'erro' => 'Fornecedor sem WhatsApp' ] );
            $erros++;
            continue;
        }
        $nomef = $f['contato'] ?: $f['nome'];
        $msg = ( $msg_custom !== null && trim( $msg_custom ) !== '' )
            ? str_replace( [ '{fornecedor}', '{contato}' ], $nomef, $msg_custom )   // texto revisado pelo farmacêutico
            : tao_cot_montar_msg( $cot, $itens, $nome_negocio, $nomef );
        $rs  = tao_cot_evolution_send( $instancia, $f['whatsapp'], $msg );
        if ( $rs['ok'] ) {
            tao_cot_api( "/cotacao_fornecedores?id=eq.{$p['id']}", 'PATCH', [
                'status' => 'enviado', 'msg_enviada' => $msg, 'erro' => null, 'enviado_em' => gmdate( 'c' ),
            ] );
            $enviados++;
        } else {
            tao_cot_api( "/cotacao_fornecedores?id=eq.{$p['id']}", 'PATCH', [
                'status' => 'erro', 'erro' => substr( ( $rs['error'] ?? '' ) . ' ' . ( $rs['raw'] ?? '' ), 0, 400 ),
            ] );
            $erros++;
        }
        if ( $i < count( $participantes ) - 1 ) sleep( 2 ); // anti-rajada
    }

    if ( $enviados > 0 && $cot['status'] === 'rascunho' ) {
        tao_cot_api( "/cotacoes?id=eq.$cotacao_id", 'PATCH', [ 'status' => 'enviada', 'enviado_em' => gmdate( 'c' ) ] );
    }
    return [ 'enviados' => $enviados, 'erros' => $erros ];
}

// ── Status / exclusão ────────────────────────────────────────────────────────

add_action( 'wp_ajax_tao_cot_set_status', function() {
    $cid    = tao_cot_ajax_guard();
    $id     = sanitize_text_field( $_POST['id'] ?? '' );
    $status = sanitize_text_field( $_POST['status'] ?? '' );
    if ( ! $id || ! in_array( $status, [ 'concluida', 'cancelada' ], true ) ) wp_send_json_error( 'Status inválido' );

    $payload = [ 'status' => $status ];
    if ( $status === 'concluida' ) $payload['concluido_em'] = gmdate( 'c' );
    $r = tao_cot_api( "/cotacoes?id=eq.$id&cliente_id=eq.$cid", 'PATCH', $payload );
    if ( ! $r['ok'] ) wp_send_json_error( 'Falha: ' . ( $r['raw'] ?? '' ) );
    wp_send_json_success();
} );

// ── Chat com fornecedor (thread própria do módulo, fora do CRM) ──────────────

/**
 * Resolve a instância de envio: a da cotação (se informada) → a da última
 * mensagem da thread → a primeira instância do cliente.
 */
function tao_cot_chat_instancia( $cid, $fornecedor_id, $cotacao_id = '' ) {
    $inst_id = null;
    if ( $cotacao_id ) {
        $rc = tao_cot_api( "/cotacoes?id=eq.$cotacao_id&cliente_id=eq.$cid&select=instancia_id" );
        if ( $rc['ok'] && ! empty( $rc['data'] ) ) $inst_id = $rc['data'][0]['instancia_id'];
    }
    if ( ! $inst_id ) {
        $rm = tao_cot_api( "/fornecedor_mensagens?fornecedor_id=eq.$fornecedor_id&instancia_id=not.is.null&select=instancia_id&order=criado_em.desc&limit=1" );
        if ( $rm['ok'] && ! empty( $rm['data'] ) ) $inst_id = $rm['data'][0]['instancia_id'];
    }
    if ( $inst_id ) {
        $ri = tao_cot_api( "/crm_instancias?id=eq.$inst_id" );
        if ( $ri['ok'] && ! empty( $ri['data'] ) ) return $ri['data'][0];
    }
    $all = tao_cot_instancias( $cid );
    return $all[0] ?? null;
}

add_action( 'wp_ajax_tao_cot_chat_get', function() {
    $cid = tao_cot_ajax_guard();
    $fid = sanitize_text_field( $_POST['fornecedor_id'] ?? '' );
    if ( ! $fid ) wp_send_json_error( 'Fornecedor inválido' );

    $after  = sanitize_text_field( $_POST['after'] ?? '' );
    $filtro = "/fornecedor_mensagens?cliente_id=eq.$cid&fornecedor_id=eq.$fid";
    $q      = $filtro . '&select=id,cotacao_id,direcao,tipo,conteudo,midia_url,midia_mime,criado_em&order=criado_em.asc&limit=300';
    if ( $after ) $q .= '&criado_em=gt.' . rawurlencode( $after );

    $r = tao_cot_api( $q );
    if ( ! $r['ok'] ) wp_send_json_error( 'Falha ao buscar mensagens' );

    // marca recebidas como lidas
    tao_cot_api( $filtro . '&direcao=eq.in&lida=eq.false', 'PATCH', [ 'lida' => true ] );

    wp_send_json_success( $r['data'] );
} );

add_action( 'wp_ajax_tao_cot_chat_send', function() {
    $cid   = tao_cot_ajax_guard();
    $fid   = sanitize_text_field( $_POST['fornecedor_id'] ?? '' );
    $texto = trim( sanitize_textarea_field( $_POST['texto'] ?? '' ) );
    $cot   = sanitize_text_field( $_POST['cotacao_id'] ?? '' );
    if ( ! $fid || $texto === '' ) wp_send_json_error( 'Mensagem vazia' );

    $rf = tao_cot_api( "/fornecedores?id=eq.$fid&cliente_id=eq.$cid&select=id,whatsapp" );
    if ( ! $rf['ok'] || empty( $rf['data'] ) ) wp_send_json_error( 'Fornecedor não encontrado' );
    $forn = $rf['data'][0];

    $instancia = tao_cot_chat_instancia( $cid, $fid, $cot );
    if ( ! $instancia ) wp_send_json_error( 'Nenhuma instância WhatsApp disponível' );

    $rs = tao_cot_evolution_send( $instancia, $forn['whatsapp'], $texto );
    if ( ! $rs['ok'] ) wp_send_json_error( 'Falha no envio: ' . ( $rs['error'] ?? ( 'HTTP ' . ( $rs['code'] ?? '?' ) ) ) );

    $aberta = tao_cotacoes_cotacao_aberta_do_fornecedor( $fid );
    $ri = tao_cot_api( '/fornecedor_mensagens', 'POST', [
        'cliente_id'    => $cid,
        'fornecedor_id' => $fid,
        'cotacao_id'    => $cot ?: ( $aberta['cotacao_id'] ?? null ),
        'instancia_id'  => $instancia['id'] ?? null,
        'direcao'       => 'out',
        'tipo'          => 'text',
        'conteudo'      => $texto,
        'enviado_por'   => get_current_user_id(),
        'lida'          => true,
        'criado_em'     => gmdate( 'c' ),
    ] );
    wp_send_json_success( $ri['data'][0] ?? [] );
} );

add_action( 'wp_ajax_tao_cot_chat_send_file', function() {
    $cid = tao_cot_ajax_guard();
    $fid = sanitize_text_field( $_POST['fornecedor_id'] ?? '' );
    $cot = sanitize_text_field( $_POST['cotacao_id'] ?? '' );
    if ( ! $fid ) wp_send_json_error( 'Fornecedor inválido' );
    if ( empty( $_FILES['file']['tmp_name'] ) ) wp_send_json_error( 'Nenhum arquivo recebido' );
    if ( $_FILES['file']['size'] > 12 * 1024 * 1024 ) wp_send_json_error( 'Arquivo acima de 12 MB' );

    if ( ! function_exists( 'tao_crm_save_media_file' ) || ! function_exists( 'tao_crm_evolution_send_media' ) ) {
        wp_send_json_error( 'TAO CRM inativo — envio de mídia indisponível' );
    }

    $fname = sanitize_file_name( $_FILES['file']['name'] ?? 'arquivo' );
    $mime  = mime_content_type( $_FILES['file']['tmp_name'] ) ?: 'application/octet-stream';
    $permitidos = [ 'application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'image/gif',
                    'audio/ogg', 'audio/mpeg', 'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' ];
    if ( ! in_array( $mime, $permitidos, true ) ) wp_send_json_error( "Tipo de arquivo não permitido ($mime)" );

    $rf = tao_cot_api( "/fornecedores?id=eq.$fid&cliente_id=eq.$cid&select=id,whatsapp" );
    if ( ! $rf['ok'] || empty( $rf['data'] ) ) wp_send_json_error( 'Fornecedor não encontrado' );
    $forn = $rf['data'][0];

    $instancia = tao_cot_chat_instancia( $cid, $fid, $cot );
    if ( ! $instancia ) wp_send_json_error( 'Nenhuma instância WhatsApp disponível' );

    $midia_url = tao_crm_save_media_file( file_get_contents( $_FILES['file']['tmp_name'] ), $mime, $fname );
    if ( ! $midia_url ) wp_send_json_error( 'Erro ao salvar arquivo no servidor' );

    $rs = tao_crm_evolution_send_media( $instancia, $forn['whatsapp'], $midia_url, $mime, $fname );
    if ( empty( $rs['ok'] ) ) wp_send_json_error( 'Falha no envio: ' . ( $rs['error'] ?? '' ) );

    $tipo = str_starts_with( $mime, 'image/' ) ? 'image' : ( str_starts_with( $mime, 'audio/' ) ? 'audio' : 'document' );
    $aberta = tao_cotacoes_cotacao_aberta_do_fornecedor( $fid );
    $ri = tao_cot_api( '/fornecedor_mensagens', 'POST', [
        'cliente_id'    => $cid,
        'fornecedor_id' => $fid,
        'cotacao_id'    => $cot ?: ( $aberta['cotacao_id'] ?? null ),
        'instancia_id'  => $instancia['id'] ?? null,
        'direcao'       => 'out',
        'tipo'          => $tipo,
        'conteudo'      => $fname,
        'midia_url'     => $midia_url,
        'midia_mime'    => $mime,
        'enviado_por'   => get_current_user_id(),
        'lida'          => true,
        'criado_em'     => gmdate( 'c' ),
    ] );
    wp_send_json_success( $ri['data'][0] ?? [] );
} );

add_action( 'wp_ajax_tao_cot_excluir_cotacao', function() {
    $cid = tao_cot_ajax_guard();
    $id  = sanitize_text_field( $_POST['id'] ?? '' );
    if ( ! $id ) wp_send_json_error( 'ID inválido' );

    $rc = tao_cot_api( "/cotacoes?id=eq.$id&cliente_id=eq.$cid&select=status" );
    if ( ! $rc['ok'] || empty( $rc['data'] ) ) wp_send_json_error( 'Cotação não encontrada' );
    if ( $rc['data'][0]['status'] !== 'rascunho' ) wp_send_json_error( 'Só é possível excluir cotação em rascunho — cancele em vez de excluir' );

    $r = tao_cot_api( "/cotacoes?id=eq.$id&cliente_id=eq.$cid", 'DELETE' );
    if ( ! $r['ok'] ) wp_send_json_error( 'Falha ao excluir: ' . ( $r['raw'] ?? '' ) );
    wp_send_json_success();
} );
