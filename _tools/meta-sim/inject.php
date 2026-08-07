<?php
/**
 * Injeta um payload realista da Meta no webhook local/produção, com a
 * assinatura HMAC correta (X-Hub-Signature-256) calculada com o app_secret de dev.
 *
 * Uso:
 *   php _tools/meta-sim/inject.php <arquivo.json> [webhook_url] [app_secret]
 *
 * Padrões (sobrescrevíveis por env):
 *   WEBHOOK_URL   (default https://solucoesetao.com.br/wp-json/tao-crm/v1/meta-webhook)
 *   APP_SECRET    (default "" — se o WP estiver sem secret, o webhook aceita em modo dev)
 *
 * Ex.: php _tools/meta-sim/inject.php payloads/msg_texto.json "" ""
 */
$arg = $argv;
$file   = $arg[1] ?? '';
$hook   = $arg[2] ?? ( getenv( 'WEBHOOK_URL' ) ?: 'https://solucoesetao.com.br/wp-json/tao-crm/v1/meta-webhook' );
$secret = $arg[3] ?? ( getenv( 'APP_SECRET' ) ?: '' );

if ( ! $file ) { fwrite( STDERR, "uso: php inject.php <arquivo.json> [webhook_url] [app_secret]\n" ); exit( 1 ); }
if ( $file[0] !== '/' && $file[1] !== ':' ) $file = __DIR__ . '/' . $file;   // relativo à pasta
if ( ! is_file( $file ) ) { fwrite( STDERR, "arquivo não encontrado: $file\n" ); exit( 1 ); }

$json = trim( file_get_contents( $file ) );
json_decode( $json );
if ( json_last_error() !== JSON_ERROR_NONE ) { fwrite( STDERR, "JSON inválido: " . json_last_error_msg() . "\n" ); exit( 1 ); }

$headers = [ 'Content-Type: application/json' ];
if ( $secret !== '' ) $headers[] = 'X-Hub-Signature-256: sha256=' . hash_hmac( 'sha256', $json, $secret );

$ch = curl_init( $hook );
curl_setopt_array( $ch, [
    CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15,
    CURLOPT_HTTPHEADER => $headers, CURLOPT_POSTFIELDS => $json,
] );
$resp = curl_exec( $ch );
$code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
curl_close( $ch );

echo "→ POST $hook\n";
echo "  arquivo: " . basename( $file ) . ( $secret !== '' ? " (assinado)" : " (sem assinatura — modo dev)" ) . "\n";
echo "  HTTP $code\n  resposta: $resp\n";
exit( $code >= 200 && $code < 300 ? 0 : 2 );
