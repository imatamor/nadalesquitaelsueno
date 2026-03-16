<?php
/**
 * Nombre: Helpers del theme
 * Descripcion: Centraliza accesos a opciones y utilidades reutilizables de branding y layout.
 * Uso: Importado por functions.php para que otros modulos usen helpers consistentes.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Nombre: maruri_get_theme_options
 * Descripcion: Obtiene el array de opciones persistidas del theme con un fallback seguro.
 * Uso: $options = maruri_get_theme_options();
 * Retorna: Array asociativo con las opciones del theme.
 */
function maruri_get_theme_options() {
	$options = get_option( 'maruri_theme_options', array() );

	return is_array( $options ) ? $options : array();
}

/**
 * Nombre: maruri_get_theme_option
 * Descripcion: Devuelve una opcion puntual del theme usando un fallback si no existe.
 * Uso: maruri_get_theme_option( 'brand_name', get_bloginfo( 'name' ) )
 * Parametros:
 * - $key: Clave de la opcion.
 * - $default: Valor fallback cuando la opcion no existe.
 * Retorna: Valor almacenado o fallback.
 */
function maruri_get_theme_option( $key, $default = '' ) {
	$options = maruri_get_theme_options();

	return isset( $options[ $key ] ) ? $options[ $key ] : $default;
}

/**
 * Nombre: maruri_get_brand_name
 * Descripcion: Resuelve el nombre de marca mostrado por el theme, priorizando la configuracion del admin.
 * Uso: echo maruri_get_brand_name();
 * Retorna: Nombre de marca listo para imprimirse.
 */
function maruri_get_brand_name() {
	$brand_name = maruri_get_theme_option( 'brand_name', get_bloginfo( 'name' ) );

	return $brand_name ? $brand_name : get_bloginfo( 'name' );
}

/**
 * Nombre: maruri_render_fallback_menu
 * Descripcion: Muestra un enlace basico al inicio cuando no hay menu asignado.
 * Uso: Fallback de wp_nav_menu en el header.
 */
function maruri_render_fallback_menu() {
	echo '<ul id="primary-menu" class="menu"><li class="menu-item"><a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'Inicio', 'maruri' ) . '</a></li></ul>';
}
