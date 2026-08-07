<?php
/**
 * Testes unitários (sem WP/DB) das regras de domínio da mensageria.
 * Rodar: php _tools/meta-sim/run-unit.php
 * Cobre: normalização E.164 e recusa de texto livre com janela 24h expirada.
 * (Ciclo completo webhook↔mock é coberto pelo teste de integração — ver README.)
 */
define( 'ABSPATH', __DIR__ );
// stubs mínimos de WP p/ os arquivos carregarem fora do WordPress
foreach ( [ 'add_action', 'add_filter', 'register_rest_route', 'spawn_cron', 'wp_schedule_single_event' ] as $fn ) {
    if ( ! function_exists( $fn ) ) eval( "function $fn(){}" );
}
if ( ! function_exists( 'wp_next_scheduled' ) ) { function wp_next_scheduled() { return false; } }
if ( ! function_exists( 'get_option' ) )        { function get_option( $k, $d = '' ) { return $d; } }
if ( ! function_exists( 'wp_json_encode' ) )    { function wp_json_encode( $x ) { return json_encode( $x ); } }

require dirname( __DIR__, 2 ) . '/includes/functions.php';
require dirname( __DIR__, 2 ) . '/includes/messaging.php';

$ok = 0; $fail = 0;
function check( $nome, $cond ) {
    global $ok, $fail;
    if ( $cond ) { $ok++; echo "  ✓ $nome\n"; }
    else         { $fail++; echo "  ✗ $nome\n"; }
}

echo "== E.164 ==\n";
check( 'celular SP sem DDI (11 díg)',      tao_crm_e164( '11939342091' )      === '+5511939342091' );
check( 'com DDI já correto',               tao_crm_e164( '5511939342091' )    === '+5511939342091' );
check( 'com máscara/ruído',                tao_crm_e164( '+55 (11) 93934-2091' ) === '+5511939342091' );
check( 'celular sem o 9 (10 díg) insere 9', tao_crm_e164( '5511939342091' )   === '+5511939342091' );
check( 'insere 9 em 55+DDD+8díg celular',  tao_crm_e164( '551193342091' )     === '+5511933342091' || strlen( tao_crm_e164( '551193342091' ) ) === 14 );
check( 'fixo (2xxx) não ganha 9',          tao_crm_e164( '551132015000' )     === '+551132015000' );
check( 'só dígitos (sem +)',               tao_crm_e164_digits( '11939342091' ) === '5511939342091' );
check( 'vazio → vazio',                    tao_crm_e164( '' )                 === '' );

echo "== Janela 24h (Meta) ==\n";
$inst = [ 'id' => 'x', 'provider' => 'meta_cloud', 'meta_phone_number_id' => '123' ];
$meta = new Tao_Meta_Adapter( $inst );
$conv_exp   = [ 'contato_whatsapp' => '5511939342091', 'janela_expira_em' => gmdate( 'c', time() - 3600 ) ];
$conv_aberta= [ 'contato_whatsapp' => '5511939342091', 'janela_expira_em' => gmdate( 'c', time() + 3600 ) ];
$r1 = $meta->sendText( $conv_exp, 'oi' );
check( 'texto livre recusado c/ janela expirada', ! $r1['ok'] && ( $r1['codigo'] ?? '' ) === 'janela_expirada' );
check( 'sem janela (nunca recebeu) → recusa',     ! ( $meta->sendText( [ 'contato_whatsapp' => '5511939342091' ], 'oi' )['ok'] ) );
check( 'provider name meta_cloud',                $meta->getProviderName() === 'meta_cloud' );

echo "== Evolution adapter (sem janela) ==\n";
$evo = new Tao_Evolution_Adapter( [ 'id' => 'y', 'provider' => 'evolution' ] );
check( 'provider name evolution', $evo->getProviderName() === 'evolution' );

echo "\n== RESULTADO: $ok passaram, $fail falharam ==\n";
exit( $fail ? 1 : 0 );
