<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function solucoesetao_enqueue() {
    wp_enqueue_style( 'google-fonts',
        'https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Inter:wght@400;500&family=JetBrains+Mono:wght@400&display=swap',
        [], null );
    wp_enqueue_style( 'solucoesetao-style', get_stylesheet_uri(), ['google-fonts'], '3.2' );
}
add_action( 'wp_enqueue_scripts', 'solucoesetao_enqueue' );

function solucoesetao_setup() {
    add_theme_support( 'title-tag' );
    add_theme_support( 'custom-logo' );
    add_theme_support( 'post-thumbnails' );
    register_nav_menus( [ 'primary' => 'Menu Principal' ] );
}
add_action( 'after_setup_theme', 'solucoesetao_setup' );


