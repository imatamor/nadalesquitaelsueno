<?php
/**
 * Nombre: Assets del theme
 * Descripcion: Registra y encola estilos, scripts y codigo personalizado del theme.
 * Uso: Sus hooks se cargan desde functions.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Nombre: maruri_enqueue_assets
 * Descripcion: Encola los assets publicos del theme y agrega CSS/JS personalizados configurados desde admin.
 * Uso: Hookeada a wp_enqueue_scripts.
 */
function maruri_enqueue_assets() {
	wp_enqueue_style( 'maruri-base', MARURI_THEME_URL . '/assets/css/base.css', array(), MARURI_THEME_VERSION );
	wp_enqueue_script( 'maruri-navigation', MARURI_THEME_URL . '/assets/js/navigation.js', array(), MARURI_THEME_VERSION, true );

	$design_tokens = maruri_get_design_tokens();
	if ( ! empty( $design_tokens ) ) {
		$css = ":root {\n";

		foreach ( $design_tokens as $token_name => $token_value ) {
			$token_name = preg_replace( '/[^a-z0-9\-]/i', '', (string) $token_name );
			$css       .= sprintf( "  %s: %s;\n", $token_name, esc_html( $token_value ) );
		}

		$css .= "}\n";
		wp_add_inline_style( 'maruri-base', $css );
	}

	$custom_css = trim( (string) maruri_get_theme_option( 'custom_css', '' ) );
	if ( '' !== $custom_css ) {
		wp_add_inline_style( 'maruri-base', $custom_css );
	}

	$custom_js = trim( (string) maruri_get_theme_option( 'custom_js', '' ) );
	if ( '' !== $custom_js ) {
		wp_add_inline_script( 'maruri-navigation', $custom_js );
	}
}
add_action( 'wp_enqueue_scripts', 'maruri_enqueue_assets' );

/**
 * Nombre: maruri_print_head_scripts
 * Descripcion: Imprime scripts o etiquetas personalizados en el head si fueron configurados desde admin.
 * Uso: Hookeada a wp_head.
 */
function maruri_print_head_scripts() {
	$script = trim( (string) maruri_get_theme_option( 'head_scripts', '' ) );

	if ( '' !== $script ) {
		echo $script; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
add_action( 'wp_head', 'maruri_print_head_scripts', 99 );

/**
 * Nombre: maruri_print_body_open_scripts
 * Descripcion: Imprime codigo personalizado justo despues de la apertura del body.
 * Uso: Hookeada a wp_body_open.
 */
function maruri_print_body_open_scripts() {
	$script = trim( (string) maruri_get_theme_option( 'body_open_scripts', '' ) );

	if ( '' !== $script ) {
		echo $script; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
add_action( 'wp_body_open', 'maruri_print_body_open_scripts', 99 );

/**
 * Nombre: maruri_print_footer_scripts
 * Descripcion: Imprime codigo personalizado al final del documento.
 * Uso: Hookeada a wp_footer.
 */
function maruri_print_footer_scripts() {
	$script = trim( (string) maruri_get_theme_option( 'footer_scripts', '' ) );

	if ( '' !== $script ) {
		echo $script; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
add_action( 'wp_footer', 'maruri_print_footer_scripts', 99 );
