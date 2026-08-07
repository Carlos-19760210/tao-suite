<?php
/**
 * Mock local da Graph API da Meta (endpoint de ENVIO).
 * Emula POST /{version}/{phone_number_id}/messages: valida o shape, devolve um
 * wamid fake e (opcional) emite de volta ao webhook os statuses sent→delivered→read.
 *
 * Rodar:   php -S 127.0.0.1:8089 _tools/meta-sim/mock-graph.php
 * Apontar: no WP, defina a option tao_crm_meta_api_base = http://127.0.0.1:8089
 *
 * Env (opcionais, p/ fechar o ciclo devolvendo statuses ao webhook):
 *   SIM_WEBHOOK_URL   ex.: https://solucoesetao.com.br/wp-json/tao-crm/v1/meta-webhook
 *   SIM_APP_SECRET    mesmo segredo configurado no WP (tao_crm_meta_app_secret)
 *   SIM_PNID          phone_number_id usado (default 123456)
 */
header( 'Content-Type: application/json' );
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path   = parse_url( $_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH );

if ( $method !== 'POST' || ! preg_match( '#/v\d+\.\d+/[^/]+/messages$#', $path ) ) {
    http_response_code( 404 );
    echo json_encode( [ 'error' => [ 'message' => 'rota não emulada', 'path' => $path ] ] );
    exit;
}

$raw  = file_get_contents( 'php://input' );
$body = json_decode( $raw, true );
if ( ! is_array( $body ) || ( $body['messaging_product'] ?? '' ) !== 'whatsapp' || empty( $body['to'] ) || empty( $body['type'] ) ) {
    http_response_code( 400 );
    echo json_encode( [ 'error' => [ 'message' => 'payload inválido', 'code' => 100 ] ] );
    exit;
}

$to    = preg_replace( '/\D/', '', (string) $body['to'] );
$wamid = 'wamid.SIM' . strtoupper( bin2hex( random_bytes( 8 ) ) );

// resposta oficial da Graph
http_response_code( 200 );
echo json_encode( [
    'messaging_product' => 'whatsapp',
    'contacts' => [ [ 'input' => $to, 'wa_id' => $to ] ],
    'messages' => [ [ 'id' => $wamid ] ],
] );

// fecha o ciclo: emite sent→delivered→read ao webhook depois de responder
$hook   = getenv( 'SIM_WEBHOOK_URL' );
$secret = getenv( 'SIM_APP_SECRET' ) ?: '';
$pnid   = getenv( 'SIM_PNID' ) ?: '123456';
if ( $hook ) {
    if ( function_exists( 'fastcgi_finish_request' ) ) fastcgi_finish_request();
    foreach ( [ 'sent', 'delivered', 'read' ] as $i => $st ) {
        usleep( 300000 ); // 0,3s entre transições
        sim_post_status( $hook, $secret, $pnid, $wamid, $to, $st );
    }
}

function sim_post_status( $hook, $secret, $pnid, $wamid, $to, $status ) {
    if ( ! $hook ) return;
    $payload = [ 'object' => 'whatsapp_business_account', 'entry' => [ [ 'id' => 'WABA_SIM', 'changes' => [ [
        'field' => 'messages',
        'value' => [ 'messaging_product' => 'whatsapp', 'metadata' => [ 'phone_number_id' => $pnid ],
            'statuses' => [ [ 'id' => $wamid, 'status' => $status, 'timestamp' => (string) time(), 'recipient_id' => $to ] ] ],
    ] ] ] ] ];
    $json = json_encode( $payload );
    $sig  = 'sha256=' . hash_hmac( 'sha256', $json, $secret );
    $ch = curl_init( $hook );
    curl_setopt_array( $ch, [
        CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8,
        CURLOPT_HTTPHEADER => [ 'Content-Type: application/json', 'X-Hub-Signature-256: ' . $sig ],
        CURLOPT_POSTFIELDS => $json,
    ] );
    curl_exec( $ch );
    curl_close( $ch );
}
