<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// ─── SUPABASE API ─────────────────────────────────────────────────────────────

function tao_crm_api( $endpoint, $method = 'GET', $body = null, $extra_headers = [] ) {
    if ( function_exists( 'cbpm_api' ) ) {
        return cbpm_api( $endpoint, $method, $body, $extra_headers );
    }
    // Fallback standalone (sem chatbot-platform ativo)
    $url = rtrim( get_option( 'cbpm_supabase_url', '' ), '/' ) . '/rest/v1' . $endpoint;
    $key = get_option( 'cbpm_supabase_key', '' );

    if ( empty( $key ) ) return [ 'ok' => false, 'status' => 0, 'data' => null, 'error' => 'Supabase key não configurada' ];

    $headers = array_merge( [
        'apikey'        => $key,
        'Authorization' => 'Bearer ' . $key,
        'Content-Type'  => 'application/json',
    ], $extra_headers );

    $args = [ 'method' => $method, 'headers' => $headers, 'timeout' => 15 ];
    if ( $body !== null ) $args['body'] = wp_json_encode( $body );

    $response = wp_remote_request( $url, $args );
    if ( is_wp_error( $response ) ) return [ 'ok' => false, 'status' => 0, 'data' => null, 'error' => $response->get_error_message() ];

    $status = wp_remote_retrieve_response_code( $response );
    $data   = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( $status >= 400 ) return [ 'ok' => false, 'status' => $status, 'data' => $data, 'error' => $data['message'] ?? 'Erro HTTP ' . $status ];
    return [ 'ok' => true, 'status' => $status, 'data' => $data, 'error' => '' ];
}

// ─── EVOLUTION API ────────────────────────────────────────────────────────────

function tao_crm_evolution_send( $workspace, $numero, $texto, $blocking = false ) {
    $url  = rtrim( $workspace['evolution_url'] ?? '', '/' );
    $key  = $workspace['evolution_key'] ?? '';
    $inst = $workspace['evolution_instancia'] ?? '';

    if ( ! $url || ! $key || ! $inst ) return [ 'ok' => false, 'error' => 'Evolution não configurada' ];

    $r = wp_remote_post( "$url/message/sendText/$inst", [
        'headers'  => [ 'Content-Type' => 'application/json', 'apikey' => $key ],
        'body'     => wp_json_encode( [ 'number' => $numero, 'text' => $texto ] ),
        'timeout'  => $blocking ? 15 : 3,
        'blocking' => $blocking,
    ] );

    if ( ! $blocking ) return [ 'ok' => true ];
    if ( is_wp_error( $r ) ) return [ 'ok' => false, 'error' => $r->get_error_message() ];
    $code = wp_remote_retrieve_response_code( $r );
    if ( $code >= 400 ) return [ 'ok' => false, 'error' => "HTTP $code" ];
    return [ 'ok' => true ];
}

// Retry com backoff exponencial (2s → 4s → desiste).
// Registra falhas finais em wp_options (tao_evolution_falhas, últimas 50).
function tao_crm_evolution_send_with_retry( $workspace, $numero, $texto, $max_retries = 3 ) {
    $delays = [ 0, 2, 4 ];
    for ( $i = 0; $i < $max_retries; $i++ ) {
        if ( $delays[ $i ] > 0 ) sleep( $delays[ $i ] );
        $r = tao_crm_evolution_send( $workspace, $numero, $texto, true );
        if ( $r['ok'] ) return true;
    }
    // Registrar falha
    $falhas   = (array) get_option( 'tao_evolution_falhas', [] );
    $falhas[] = [
        'ts'    => gmdate( 'c' ),
        'inst'  => $workspace['evolution_instancia'] ?? '',
        'num'   => $numero,
        'erro'  => $r['error'] ?? 'desconhecido',
    ];
    if ( count( $falhas ) > 50 ) $falhas = array_slice( $falhas, -50 );
    update_option( 'tao_evolution_falhas', $falhas, false );
    return false;
}

function tao_crm_evolution_send_media( $workspace, $numero, $base64, $mimetype, $filename, $caption = '' ) {
    $url  = rtrim( $workspace['evolution_url'] ?? '', '/' );
    $key  = $workspace['evolution_key'] ?? '';
    $inst = $workspace['evolution_instancia'] ?? '';

    if ( ! $url || ! $key || ! $inst ) return [ 'ok' => false, 'error' => 'Evolution não configurada' ];

    // Determina o tipo para a Evolution
    // WhatsApp só aceita OGG/Opus como áudio PTT — outros formatos (mp3, m4a...) vão como documento
    if ( str_starts_with( $mimetype, 'image/' ) )                                      $mediatype = 'image';
    elseif ( str_starts_with( $mimetype, 'video/' ) )                                  $mediatype = 'video';
    elseif ( in_array( $mimetype, [ 'audio/ogg', 'audio/opus', 'audio/webm' ], true ) ) $mediatype = 'audio';
    else                                                                                $mediatype = 'document';

    $body = [
        'number'    => $numero,
        'mediatype' => $mediatype,
        'mimetype'  => $mimetype,
        'media'     => $base64,
        'fileName'  => $filename,
        'caption'   => $caption,
    ];

    $r = wp_remote_post( "$url/message/sendMedia/$inst", [
        'headers' => [ 'Content-Type' => 'application/json', 'apikey' => $key ],
        'body'    => wp_json_encode( $body ),
        'timeout' => 30,
    ] );

    if ( is_wp_error( $r ) ) return [ 'ok' => false, 'error' => $r->get_error_message() ];
    $code = wp_remote_retrieve_response_code( $r );
    if ( $code >= 400 ) return [ 'ok' => false, 'error' => "HTTP $code" ];
    return [ 'ok' => true ];
}

// ─── WORKSPACE ────────────────────────────────────────────────────────────────

// ─── CONTROLE DE ACESSO ───────────────────────────────────────────────────────

/**
 * Retorna true se o usuário atual pode gerenciar o workspace (ver configurações,
 * todos os cards, automações). False = atendente, vê apenas seus próprios cards.
 */
function tao_crm_is_gestor( string $ws_id = '' ): bool {
    if ( current_user_can( 'manage_options' ) ) return true;
    $uid = get_current_user_id();
    if ( ! $uid ) return false;
    $global = (array) get_option( 'tao_crm_gestores_global', [] );
    if ( in_array( $uid, $global, true ) ) return true;
    if ( $ws_id ) {
        $ws_list = (array) get_option( 'tao_crm_gestores_ws_' . $ws_id, [] );
        if ( in_array( $uid, $ws_list, true ) ) return true;
    }
    return false;
}

/**
 * Salva lista de gestores para um workspace.
 */
function tao_crm_set_gestores( string $ws_id, array $user_ids ) {
    $user_ids = array_values( array_map( 'intval', array_filter( $user_ids ) ) );
    update_option( 'tao_crm_gestores_ws_' . $ws_id, $user_ids, false );
}

add_action( 'wp_ajax_tao_crm_save_gestores', 'tao_crm_ajax_save_gestores' );
function tao_crm_ajax_save_gestores() {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Acesso negado' );
    $ws_id    = sanitize_text_field( $_POST['workspace_id'] ?? '' );
    $user_ids = array_map( 'intval', (array) ( $_POST['user_ids'] ?? [] ) );
    if ( ! $ws_id ) wp_send_json_error( 'workspace_id obrigatório' );
    tao_crm_set_gestores( $ws_id, $user_ids );
    wp_send_json_success();
}

function tao_crm_get_workspace( $ws_id = null ) {
    if ( $ws_id ) {
        $r = tao_crm_api( "/crm_workspaces?id=eq.$ws_id&ativo=eq.true&limit=1" );
        return ( $r['ok'] && ! empty( $r['data'] ) ) ? $r['data'][0] : null;
    }

    $cliente_id = function_exists( 'cbpm_current_cliente_id' ) ? cbpm_current_cliente_id() : null;

    if ( $cliente_id ) {
        $r = tao_crm_api( "/crm_workspaces?cliente_id=eq.$cliente_id&ativo=eq.true&limit=1" );
    } else {
        $r = tao_crm_api( "/crm_workspaces?ativo=eq.true&limit=1" );
    }

    return ( $r['ok'] && ! empty( $r['data'] ) ) ? $r['data'][0] : null;
}

function tao_crm_get_workspaces() {
    $r = tao_crm_api( "/crm_workspaces?ativo=eq.true&order=nome.asc" );
    return $r['ok'] ? ( $r['data'] ?? [] ) : [];
}

// ─── URL HELPERS (frontend /robos/ ou wp-admin) ──────────────────────────────

function tao_crm_url( $params = [] ) {
    global $cbpm_is_frontend;
    if ( ! empty( $cbpm_is_frontend ) && function_exists( 'cbpm_url' ) ) {
        return $params ? add_query_arg( $params, cbpm_url( 'crm-kanban' ) ) : cbpm_url( 'crm-kanban' );
    }
    return add_query_arg( array_merge( [ 'page' => 'tao-crm-kanban' ], $params ), admin_url( 'admin.php' ) );
}

function tao_crm_settings_url( $params = [] ) {
    global $cbpm_is_frontend;
    if ( ! empty( $cbpm_is_frontend ) && function_exists( 'cbpm_url' ) ) {
        return $params ? add_query_arg( $params, cbpm_url( 'crm-settings' ) ) : cbpm_url( 'crm-settings' );
    }
    return add_query_arg( array_merge( [ 'page' => 'tao-crm-settings' ], $params ), admin_url( 'admin.php' ) );
}

// ─── HELPERS ─────────────────────────────────────────────────────────────────

function tao_crm_notice( $msg, $type = 'success' ) {
    $class = $type === 'error' ? 'notice-error' : 'notice-success';
    echo "<div class='notice $class is-dismissible'><p>" . esc_html( $msg ) . "</p></div>";
}

function tao_crm_brt( $utc_str, $format = 'd/m H:i' ) {
    if ( ! $utc_str ) return '—';
    return gmdate( $format, strtotime( $utc_str ) - 10800 );
}

/**
 * Limpa o historico do chatbot N8N para um número específico.
 *
 * O N8N Chatbot Generico v6 usa a tabela `historico` do Supabase como contexto de conversa.
 * Quando uma conversa foi para handoff (atendimento humano), o historico retém mensagens que
 * levam o modelo a continuar em modo handoff. Ao fechar/cancelar um card CRM, limpamos o
 * historico para que a próxima interação comece como uma nova conversa.
 */
/**
 * Bloqueia o N8N de responder quando o card entra em handoff humano.
 * Seta leads.status='contatado' e limpa o histórico — mesma lógica inversa do reset.
 */
function tao_crm_lock_chatbot( $phone, $workspace_id ) {
    if ( ! $phone || ! $workspace_id ) return;

    // Busca cliente pelo workspace (via crm_workspaces.cliente_id — mais confiável que instancia_whats)
    $rw = tao_crm_api( "/crm_workspaces?id=eq.$workspace_id&select=cliente_id&limit=1" );
    if ( ! $rw['ok'] || empty( $rw['data'] ) ) return;
    $cliente_id = $rw['data'][0]['cliente_id'] ?? '';
    if ( ! $cliente_id ) return;

    // Seta status 'contatado' → N8N para de responder
    tao_crm_api(
        "/leads?cliente_id=eq.$cliente_id&phone=eq.$phone",
        'PATCH',
        [ 'status' => 'contatado' ]
    );

    // Limpa histórico → N8N confirma bloqueio (condição: contatado + historico vazio)
    tao_crm_api( "/historico?cliente_id=eq.$cliente_id&phone=eq.$phone", 'DELETE' );
}

// ─── Helper: credenciais Evolution por card ───────────────────────────────────
function tao_crm_get_evo_creds( $card ) {
    if ( ! empty( $card['instancia_id'] ) ) {
        $ri = tao_crm_api( "/crm_instancias?id=eq.{$card['instancia_id']}&select=evolution_url,evolution_key,evolution_instancia" );
        if ( $ri['ok'] && ! empty( $ri['data'] ) ) return $ri['data'][0];
    }
    $rw = tao_crm_api( "/crm_workspaces?id=eq.{$card['workspace_id']}&select=evolution_url,evolution_key,evolution_instancia" );
    return ( $rw['ok'] && ! empty( $rw['data'] ) ) ? $rw['data'][0] : null;
}

// ─── AJAX: salvar instância ───────────────────────────────────────────────────
add_action( 'wp_ajax_tao_crm_save_instancia', 'tao_crm_ajax_save_instancia' );
function tao_crm_ajax_save_instancia() {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Sem permissão.' );
    $edit_id      = sanitize_text_field( $_POST['edit_id'] ?? '' );
    $workspace_id = sanitize_text_field( $_POST['workspace_id'] ?? '' );
    $data = [
        'workspace_id'        => $workspace_id,
        'nome'                => sanitize_text_field( $_POST['nome'] ?? '' ),
        'evolution_url'       => esc_url_raw( $_POST['evolution_url'] ?? '' ),
        'evolution_key'       => sanitize_text_field( $_POST['evolution_key'] ?? '' ),
        'evolution_instancia' => sanitize_text_field( $_POST['evolution_instancia'] ?? '' ),
        'ativo'               => true,
    ];
    if ( ! $data['workspace_id'] || ! $data['nome'] ) wp_send_json_error( 'Dados incompletos.' );
    if ( ! $edit_id ) {
        $rp = tao_crm_api( "/crm_planos?workspace_id=eq.$workspace_id&ativo=eq.true&select=limite_instancias&limit=1" );
        $limite = (int) ( ( $rp['ok'] && ! empty( $rp['data'] ) ) ? ( $rp['data'][0]['limite_instancias'] ?? 1 ) : 1 );
        if ( $limite > 0 ) {
            $rc = tao_crm_api( "/crm_instancias?workspace_id=eq.$workspace_id&ativo=eq.true&select=id" );
            $atual = $rc['ok'] ? count( $rc['data'] ?? [] ) : 0;
            if ( $atual >= $limite ) {
                wp_send_json_error( "Limite de instâncias atingido ($atual/$limite). Faça upgrade do plano para adicionar mais." );
            }
        }
    }
    if ( $edit_id ) {
        $r = tao_crm_api( "/crm_instancias?id=eq.$edit_id", 'PATCH', $data );
    } else {
        $r = tao_crm_api( '/crm_instancias', 'POST', $data, [ 'Prefer' => 'return=representation' ] );
    }
    $r['ok'] ? wp_send_json_success( $r['data'][0] ?? [] ) : wp_send_json_error( $r['error'] );
}

// ─── AJAX: deletar instância ──────────────────────────────────────────────────
add_action( 'wp_ajax_tao_crm_delete_instancia', 'tao_crm_ajax_delete_instancia' );
function tao_crm_ajax_delete_instancia() {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Sem permissão.' );
    $id = sanitize_text_field( $_POST['inst_id'] ?? '' );
    if ( ! $id ) wp_send_json_error( 'ID inválido.' );
    $r = tao_crm_api( "/crm_instancias?id=eq.$id", 'DELETE' );
    $r['ok'] ? wp_send_json_success() : wp_send_json_error( $r['error'] );
}

// ─── AJAX: deletar estágio ────────────────────────────────────────────────────
add_action( 'wp_ajax_tao_crm_delete_estagio', 'tao_crm_ajax_delete_estagio' );
function tao_crm_ajax_delete_estagio() {
    check_ajax_referer( 'tao_crm_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Sem permissão.' );
    $id = sanitize_text_field( $_POST['stage_id'] ?? '' );
    if ( ! $id ) wp_send_json_error( 'ID inválido.' );
    $r = tao_crm_api( "/crm_estagios?id=eq.$id", 'DELETE' );
    if ( $r['ok'] ) {
        wp_send_json_success( 'Estágio excluído.' );
    } else {
        wp_send_json_error( $r['error'] ?? 'Erro ao excluir.' );
    }
}

function tao_crm_reset_chatbot_historico( $phone, $workspace_id ) {
    if ( ! $phone || ! $workspace_id ) return;

    // Usa crm_workspaces.cliente_id — mesma abordagem da lock_chatbot
    $rw = tao_crm_api( "/crm_workspaces?id=eq.$workspace_id&select=cliente_id&limit=1" );
    if ( ! $rw['ok'] || empty( $rw['data'] ) ) return;
    $cliente_id = $rw['data'][0]['cliente_id'] ?? '';
    if ( ! $cliente_id ) return;

    // Deleta o lead completamente — próxima mensagem cria lead novo, N8N responde normalmente
    tao_crm_api( "/leads?cliente_id=eq.$cliente_id&phone=eq.$phone", 'DELETE' );

    // Limpa histórico de conversa
    tao_crm_api( "/historico?cliente_id=eq.$cliente_id&phone=eq.$phone", 'DELETE' );
}

function tao_crm_stage_color( $cor ) {
    return esc_attr( $cor ?: '#6366f1' );
}

// ─── MEDIA: DOWNLOAD EVOLUTION → UPLOADS ─────────────────────────────────────

function tao_crm_save_media_file( $binary, $mimetype, $filename ) {
    $upload  = wp_upload_dir();
    $subdir  = '/tao-crm-media/' . gmdate( 'Y/m' );
    $dir     = $upload['basedir'] . $subdir;
    if ( ! file_exists( $dir ) ) wp_mkdir_p( $dir );

    $ext  = explode( '/', $mimetype )[1] ?? 'bin';
    $ext  = strtok( $ext, ';' ); // remove "; charset=..."
    $base = pathinfo( sanitize_file_name( $filename ), PATHINFO_FILENAME ) ?: 'media';
    $name = wp_unique_filename( $dir, $base . '.' . $ext );

    if ( file_put_contents( $dir . '/' . $name, $binary ) === false ) return null;
    return $upload['baseurl'] . $subdir . '/' . $name;
}

function tao_crm_download_media( $workspace, $key, $message ) {
    $url  = rtrim( $workspace['evolution_url'] ?? '', '/' );
    $akey = $workspace['evolution_key']       ?? '';
    $inst = $workspace['evolution_instancia'] ?? '';
    if ( ! $url || ! $akey || ! $inst ) return null;

    $r = wp_remote_post( "$url/chat/getBase64FromMediaMessage/$inst", [
        'headers' => [ 'Content-Type' => 'application/json', 'apikey' => $akey ],
        'body'    => wp_json_encode( [ 'message' => [ 'key' => $key, 'message' => $message ] ] ),
        'timeout' => 20,
    ] );

    if ( is_wp_error( $r ) || wp_remote_retrieve_response_code( $r ) >= 400 ) return null;
    $data = json_decode( wp_remote_retrieve_body( $r ), true );

    $base64  = $data['base64']   ?? null;
    $mime    = $data['mimetype'] ?? 'application/octet-stream';
    if ( ! $base64 ) return null;

    // Extrai nome do arquivo da mensagem (documentMessage tem fileName)
    $filename = $message['documentMessage']['fileName']
             ?? $message['imageMessage']['fileName']
             ?? ( 'media.' . ( explode( '/', $mime )[1] ?? 'bin' ) );

    return tao_crm_save_media_file( base64_decode( $base64 ), $mime, $filename );
}
