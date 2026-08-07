<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Webhook Meta Cloud API + processamento assíncrono (Bloco 3).
 * ADITIVO e isolado: não toca no dispatch do Evolution. Escreve nas MESMAS tabelas
 * que o Inbox lê (crm_contatos/crm_cards/crm_mensagens), reusa tao_crm_upsert_contato
 * e o mesmo forward N8N do agente (opcional, option tao_crm_meta_forward_n8n).
 *
 * Rotas (mesmo namespace do dispatch):
 *   GET  /wp-json/tao-crm/v1/meta-webhook  → verificação (hub.challenge)
 *   POST /wp-json/tao-crm/v1/meta-webhook  → eventos (HMAC → fila → 200 → async)
 */

add_action( 'rest_api_init', function () {
    register_rest_route( 'tao-crm/v1', '/meta-webhook', [
        [ 'methods' => 'GET',  'callback' => 'tao_meta_webhook_verify',  'permission_callback' => '__return_true' ],
        [ 'methods' => 'POST', 'callback' => 'tao_meta_webhook_receive', 'permission_callback' => '__return_true' ],
    ] );
} );

// ── GET: verificação do webhook (Meta chama uma vez ao assinar) ────────────────
function tao_meta_webhook_verify( WP_REST_Request $req ) {
    $mode  = $req->get_param( 'hub_mode' )         ?: $req->get_param( 'hub.mode' );
    $token = $req->get_param( 'hub_verify_token' ) ?: $req->get_param( 'hub.verify_token' );
    $chall = $req->get_param( 'hub_challenge' )    ?: $req->get_param( 'hub.challenge' );
    $esperado = get_option( 'tao_crm_meta_verify_token', '' );
    if ( $mode === 'subscribe' && $esperado && hash_equals( $esperado, (string) $token ) ) {
        // challenge em texto puro, 200
        return new WP_REST_Response( $chall, 200, [ 'Content-Type' => 'text/plain' ] );
    }
    return new WP_REST_Response( 'forbidden', 403 );
}

// ── POST: valida HMAC (raw body), enfileira e responde 200 rápido ──────────────
function tao_meta_webhook_receive( WP_REST_Request $req ) {
    $raw = $req->get_body();                                  // corpo BRUTO (WP REST não destrói)
    $sig = $req->get_header( 'x_hub_signature_256' ) ?: $req->get_header( 'X-Hub-Signature-256' );
    $secret = get_option( 'tao_crm_meta_app_secret', '' );

    if ( $secret ) {
        $calc = 'sha256=' . hash_hmac( 'sha256', $raw, $secret );
        if ( ! $sig || ! hash_equals( $calc, (string) $sig ) ) {
            tao_crm_log_error( 'meta_webhook', 'assinatura inválida', [ 'sig' => substr( (string) $sig, 0, 24 ) ] );
            return new WP_REST_Response( 'invalid signature', 403 );
        }
    }
    // (sem secret configurado → ambiente de dev/simulador; segue sem checar)

    $body = json_decode( $raw, true );
    if ( ! is_array( $body ) ) return new WP_REST_Response( 'bad request', 400 );

    tao_meta_enfileirar_eventos( $body );

    // processa já em background (não bloqueia a resposta)
    if ( ! wp_next_scheduled( 'tao_meta_processar_eventos' ) ) {
        wp_schedule_single_event( time(), 'tao_meta_processar_eventos' );
    }
    // dispara também um "spawn" imediato do cron (não espera o tick)
    spawn_cron();

    return new WP_REST_Response( [ 'received' => true ], 200 );
}

// Explode o payload da Meta em linhas de wa_webhook_eventos (1 por message/status).
function tao_meta_enfileirar_eventos( array $body ) {
    foreach ( ( $body['entry'] ?? [] ) as $entry ) {
        foreach ( ( $entry['changes'] ?? [] ) as $ch ) {
            $val  = $ch['value'] ?? [];
            $pnid = $val['metadata']['phone_number_id'] ?? '';
            $ws   = tao_meta_ws_por_pnid( $pnid );
            foreach ( ( $val['messages'] ?? [] ) as $m ) {
                tao_meta_enfileirar_um( $ws, 'message', $m['id'] ?? null, [ 'value' => $val, 'message' => $m ] );
            }
            foreach ( ( $val['statuses'] ?? [] ) as $s ) {
                $ext = ( $s['id'] ?? '' ) . ':' . ( $s['status'] ?? '' );  // status distinto por transição
                tao_meta_enfileirar_um( $ws, 'status', $ext, [ 'value' => $val, 'status' => $s ] );
            }
        }
    }
}
function tao_meta_enfileirar_um( $ws, $tipo, $external_id, $payload ) {
    // insere; o índice único (external_id) descarta duplicado silenciosamente
    tao_crm_api( '/wa_webhook_eventos', 'POST', [
        'workspace_id' => $ws ?: null,
        'external_id'  => $external_id,
        'tipo'         => $tipo,
        'payload'      => $payload,
    ], [ 'Prefer' => 'resolution=ignore-duplicates,return=minimal' ] );
}

function tao_meta_ws_por_pnid( $pnid ) {
    if ( ! $pnid ) return null;
    $r = tao_crm_api( "/crm_instancias?meta_phone_number_id=eq.$pnid&select=id,workspace_id&limit=1" );
    return ( $r['ok'] && ! empty( $r['data'] ) ) ? ( $r['data'][0]['workspace_id'] ?? null ) : null;
}
function tao_meta_instancia_por_pnid( $pnid ) {
    if ( ! $pnid ) return null;
    $r = tao_crm_api( "/crm_instancias?meta_phone_number_id=eq.$pnid&select=*&limit=1" );
    return ( $r['ok'] && ! empty( $r['data'] ) ) ? $r['data'][0] : null;
}

// ── Processamento assíncrono (WP-cron) ────────────────────────────────────────
add_action( 'tao_meta_processar_eventos', 'tao_meta_processar_pendentes' );

function tao_meta_processar_pendentes() {
    $r = tao_crm_api( "/wa_webhook_eventos?processado_em=is.null&order=recebido_em.asc&limit=100" );
    if ( empty( $r['ok'] ) ) return;
    foreach ( ( $r['data'] ?? [] ) as $ev ) {
        $erro = null;
        try {
            if ( $ev['tipo'] === 'message' )     tao_meta_processar_message( $ev );
            elseif ( $ev['tipo'] === 'status' )  tao_meta_processar_status( $ev );
        } catch ( \Throwable $e ) {
            $erro = substr( $e->getMessage(), 0, 400 );
            tao_crm_log_error( 'meta_webhook', 'processar falhou: ' . $erro, [ 'ev' => $ev['id'] ?? '' ] );
        }
        tao_crm_api( "/wa_webhook_eventos?id=eq.{$ev['id']}", 'PATCH', [ 'processado_em' => gmdate( 'c' ), 'erro' => $erro ] );
    }
}

// messages: contato → card (meta_cloud) → grava msg in → janela 24h → agente (opcional)
function tao_meta_processar_message( array $ev ) {
    $val  = $ev['payload']['value'] ?? [];
    $m    = $ev['payload']['message'] ?? [];
    $wamid = $m['id'] ?? '';
    if ( ! $wamid ) return;

    // idempotência: mensagem já gravada?
    $jr = tao_crm_api( "/crm_mensagens?wamid=eq.$wamid&select=id&limit=1" );
    if ( ! empty( $jr['ok'] ) && ! empty( $jr['data'] ) ) return;

    $pnid = $val['metadata']['phone_number_id'] ?? '';
    $inst = tao_meta_instancia_por_pnid( $pnid );
    if ( ! $inst || empty( $inst['workspace_id'] ) ) return;   // instância Meta não cadastrada
    $ws = $inst['workspace_id'];

    $from  = tao_crm_e164_digits( $m['from'] ?? ( $val['contacts'][0]['wa_id'] ?? '' ) );
    $nome  = $val['contacts'][0]['profile']['name'] ?? '';
    if ( ! $from ) return;

    $ct = tao_crm_upsert_contato( $ws, $from, $nome );
    $contato_id = $ct['id'] ?? ( is_string( $ct ) ? $ct : '' );

    $card = tao_meta_get_or_create_card( $ws, $inst['id'], $contato_id, $from, $nome );
    if ( ! $card ) return;

    list( $tipo, $conteudo, $midia_url ) = tao_meta_extrair_conteudo( $m );

    $ts = isset( $m['timestamp'] ) ? gmdate( 'c', (int) $m['timestamp'] ) : gmdate( 'c' );
    tao_crm_api( '/crm_mensagens', 'POST', [
        'workspace_id'   => $ws,
        'card_id'        => $card['id'],
        'direcao'        => 'in',
        'tipo'           => $tipo,
        'conteudo'       => $conteudo,
        'midia_url'      => $midia_url,
        'wamid'          => $wamid,
        'provider'       => 'meta_cloud',
        'remetente_nome' => $nome ?: $from,
        'status_entrega' => 'delivered',
        'enviado_em'     => $ts,
    ] );

    // janela de 24h: a partir da ÚLTIMA mensagem recebida
    $expira = gmdate( 'c', time() + 24 * 3600 );
    tao_crm_api( "/crm_cards?id=eq.{$card['id']}", 'PATCH', [
        'ultima_msg_recebida_em' => $ts,
        'janela_expira_em'       => $expira,
        'ultima_mensagem_em'     => $ts,
    ] );
    if ( $contato_id ) tao_crm_api( '/rpc/crm_contato_novo_atendimento', 'POST', [ 'p_id' => $contato_id ] );

    // agente: encaminha ao N8N no MESMO formato do dispatch (opcional, default off)
    if ( get_option( 'tao_crm_meta_forward_n8n', '0' ) === '1' ) {
        tao_meta_forward_n8n( $inst, $card, $from, $nome, $tipo, $conteudo, $wamid, $ts );
    }
    do_action( 'tao_crm_meta_mensagem_recebida', $card['id'], $ws, $m );  // hook interno p/ extensões
}

// statuses: localiza msg por wamid → atualiza status_entrega + timestamp/erro
function tao_meta_processar_status( array $ev ) {
    $s = $ev['payload']['status'] ?? [];
    $wamid = $s['id'] ?? '';
    $st    = strtolower( $s['status'] ?? '' );
    if ( ! $wamid || ! $st ) return;
    $map = [ 'sent' => 'sent', 'delivered' => 'delivered', 'read' => 'read', 'failed' => 'failed' ];
    if ( ! isset( $map[ $st ] ) ) return;
    $patch = [ 'status_entrega' => $map[ $st ] ];
    $now = gmdate( 'c' );
    if ( $st === 'delivered' ) $patch['entregue_em'] = $now;
    if ( $st === 'read' )      $patch['lido_em']      = $now;
    if ( $st === 'failed' ) {
        $patch['falhou_em']    = $now;
        $err = $s['errors'][0] ?? [];
        $patch['erro_codigo']  = (string) ( $err['code'] ?? '' );
        $patch['erro_detalhe'] = substr( (string) ( $err['title'] ?? ( $err['message'] ?? '' ) ), 0, 400 );
    }
    tao_crm_api( "/crm_mensagens?wamid=eq.$wamid", 'PATCH', $patch );
}

// Extrai (tipo, conteudo, midia_url) de uma mensagem Meta. Não suportado → 'unknown' c/ payload bruto.
function tao_meta_extrair_conteudo( array $m ) {
    $t = $m['type'] ?? 'unknown';
    switch ( $t ) {
        case 'text':        return [ 'text',  $m['text']['body'] ?? '', null ];
        case 'button':      return [ 'text',  $m['button']['text'] ?? '', null ];
        case 'interactive':
            $i = $m['interactive'] ?? [];
            $txt = $i['button_reply']['title'] ?? ( $i['list_reply']['title'] ?? '' );
            return [ 'interactive', $txt, null ];
        case 'image': case 'audio': case 'video': case 'document': case 'sticker':
            $caption = $m[ $t ]['caption'] ?? '';
            $mid     = $m[ $t ]['id'] ?? '';               // download real da mídia = bloco futuro
            return [ $t, $caption ?: '[' . $t . ']', $mid ? ( 'meta-media:' . $mid ) : null ];
        case 'reaction':    return [ 'reaction', $m['reaction']['emoji'] ?? '', null ];
        default:            return [ 'unknown', wp_json_encode( $m ), null ];
    }
}

// get-or-create do card da conversa Meta (espelha a lógica do dispatch, isolado).
function tao_meta_get_or_create_card( $ws, $inst_id, $contato_id, $whatsapp, $nome ) {
    $rc = tao_crm_api( "/crm_cards?workspace_id=eq.$ws&contato_whatsapp=eq.$whatsapp&fechado=eq.false&select=id,provider&order=criado_em.desc&limit=1" );
    if ( ! empty( $rc['ok'] ) && ! empty( $rc['data'] ) ) return $rc['data'][0];

    // pipeline padrão + primeiro estágio
    $rpl = tao_crm_api( "/crm_pipelines?workspace_id=eq.$ws&ativo=eq.true&order=ordem.asc&limit=1" );
    $pl  = ( ! empty( $rpl['ok'] ) && ! empty( $rpl['data'] ) ) ? $rpl['data'][0]['id'] : null;
    $est = null;
    if ( $pl ) {
        $re = tao_crm_api( "/crm_estagios?pipeline_id=eq.$pl&order=ordem.asc&limit=1" );
        $est = ( ! empty( $re['ok'] ) && ! empty( $re['data'] ) ) ? $re['data'][0]['id'] : null;
    }
    $r = tao_crm_api( '/crm_cards', 'POST', [
        'workspace_id'     => $ws,
        'pipeline_id'      => $pl,
        'estagio_id'       => $est,
        'contato_id'       => $contato_id ?: null,
        'contato_nome'     => $nome ?: $whatsapp,
        'contato_whatsapp' => $whatsapp,
        'instancia_id'     => $inst_id,
        'provider'         => 'meta_cloud',
        'status'           => 'aberto',
        'titulo'           => $nome ?: $whatsapp,
    ], [ 'Prefer' => 'return=representation' ] );
    return ( ! empty( $r['ok'] ) && ! empty( $r['data'] ) ) ? $r['data'][0] : null;
}

// Encaminha ao N8N do agente no mesmo formato do dispatch (opcional).
function tao_meta_forward_n8n( $inst, $card, $from, $nome, $tipo, $conteudo, $wamid, $ts ) {
    $url = get_option( 'tao_crm_n8n_url', '' );
    if ( ! $url ) return;
    $ev = [ [
        'event'    => 'messages.upsert',
        'instance' => $inst['evolution_instancia'] ?? ( 'meta:' . ( $inst['meta_phone_number_id'] ?? '' ) ),
        'provider' => 'meta_cloud',
        'data'     => [ 'key' => [ 'remoteJid' => $from . '@s.whatsapp.net', 'fromMe' => false, 'id' => $wamid ],
                        'pushName' => $nome, 'message' => [ 'conversation' => $conteudo ], 'messageType' => $tipo ],
    ] ];
    wp_remote_post( $url, [ 'timeout' => 5, 'blocking' => false,
        'headers' => [ 'Content-Type' => 'application/json' ], 'body' => wp_json_encode( $ev ) ] );
}
