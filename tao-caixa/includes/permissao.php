<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Capability que controla quem pode operar o caixa.
 * Concedida a Admin e Gestor na ativação do plugin.
 */
function tao_caixa_on_activate() {
    foreach ( [ 'administrator', 'cbpm_gestor' ] as $role_name ) {
        $role = get_role( $role_name );
        if ( $role ) $role->add_cap( 'tao_caixa_operar' );
    }
}

/**
 * Garante (idempotente) que o Gestor tenha a permissão, mesmo sem reativar o plugin.
 */
add_action( 'init', function() {
    if ( get_option( 'tao_caixa_cap_v1' ) ) return;
    tao_caixa_on_activate();
    update_option( 'tao_caixa_cap_v1', 1 );
} );

/**
 * Pode operar o caixa? Master/Admin sempre; demais via capability.
 */
function tao_caixa_pode_operar( $user_id = null ) {
    $user = $user_id ? get_user_by( 'id', $user_id ) : wp_get_current_user();
    if ( ! $user || ! $user->ID ) return false;
    if ( user_can( $user, 'manage_options' ) ) return true;
    if ( function_exists( 'cbpm_is_master' ) && ! $user_id && cbpm_is_master() ) return true;
    return user_can( $user, 'tao_caixa_operar' );
}
