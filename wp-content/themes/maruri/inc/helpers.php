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
 * Nombre: maruri_get_contact_email
 * Descripcion: Obtiene el correo de contacto definido para el theme o usa el email del sitio como respaldo.
 * Uso: $email = maruri_get_contact_email();
 * Retorna: Correo listo para imprimirse o cadena vacia.
 */
function maruri_get_contact_email() {
	$email = maruri_get_theme_option( 'contact_email', get_bloginfo( 'admin_email' ) );

	return is_email( $email ) ? $email : '';
}

/**
 * Nombre: maruri_get_footer_copyright
 * Descripcion: Resuelve el texto de copyright del footer usando una opcion editable con fallback seguro.
 * Uso: echo maruri_get_footer_copyright();
 * Retorna: Texto de copyright con el anio actual interpolado.
 */
function maruri_get_footer_copyright() {
	$default_text = sprintf(
		/* translators: %s: current year */
		__( '%s Maruri. Todos los derechos reservados.', 'maruri' ),
		gmdate( 'Y' )
	);
	$text         = maruri_get_theme_option( 'footer_copyright', '' );

	return $text ? $text : $default_text;
}

/**
 * Nombre: maruri_get_sidebar_behavior
 * Descripcion: Devuelve el modo de sidebar configurado desde admin con fallback a automatico.
 * Uso: if ( 'hidden' === maruri_get_sidebar_behavior() ) { ... }
 * Retorna: Valor de comportamiento del sidebar.
 */
function maruri_get_sidebar_behavior() {
	$behavior = maruri_get_theme_option( 'sidebar_behavior', 'auto' );

	return in_array( $behavior, array( 'auto', 'always', 'hidden' ), true ) ? $behavior : 'auto';
}

/**
 * Nombre: maruri_should_display_sidebar
 * Descripcion: Decide si el sidebar debe mostrarse segun widgets activos, contexto y configuracion del admin.
 * Uso: if ( maruri_should_display_sidebar() ) { get_sidebar(); }
 * Retorna: True cuando el sidebar debe renderizarse.
 */
function maruri_should_display_sidebar() {
	if ( ! is_active_sidebar( 'sidebar-1' ) ) {
		return false;
	}

	$behavior = maruri_get_sidebar_behavior();

	if ( 'hidden' === $behavior ) {
		return false;
	}

	if ( 'always' === $behavior ) {
		return true;
	}

	return ! maruri_is_full_width_context();
}

/**
 * Nombre: maruri_get_design_tokens
 * Descripcion: Centraliza tokens visuales configurables del theme con defaults seguros.
 * Uso: $tokens = maruri_get_design_tokens();
 * Retorna: Array con tokens CSS listos para serializar.
 */
function maruri_get_design_tokens() {
	$tokens = array(
		'--maruri-color-background' => maruri_get_theme_option( 'background_color', '#f5f1e8' ),
		'--maruri-color-surface'    => maruri_get_theme_option( 'surface_color', '#fffdf8' ),
		'--maruri-color-text'       => maruri_get_theme_option( 'text_color', '#1e1d1a' ),
		'--maruri-color-accent'     => maruri_get_theme_option( 'accent_color', '#9a3412' ),
	);

	$shell_width = absint( maruri_get_theme_option( 'shell_width', 0 ) );
	if ( $shell_width >= 960 ) {
		$tokens['--maruri-shell'] = $shell_width . 'px';
	}

	$reading_width = absint( maruri_get_theme_option( 'reading_width', 0 ) );
	if ( $reading_width >= 640 ) {
		$tokens['--maruri-shell-reading'] = $reading_width . 'px';
	}

	return array_filter( $tokens );
}

/**
 * Nombre: maruri_get_social_links
 * Descripcion: Parsea enlaces sociales configurados en multilinea para renderizarlos con una estructura uniforme.
 * Uso: foreach ( maruri_get_social_links() as $link ) { ... }
 * Retorna: Array de enlaces con red, url y etiqueta.
 */
function maruri_get_social_links() {
	$raw_links = preg_split( '/\r\n|\r|\n/', (string) maruri_get_theme_option( 'social_links', '' ) );
	$links     = array();

	foreach ( $raw_links as $raw_link ) {
		$raw_link = trim( $raw_link );

		if ( '' === $raw_link ) {
			continue;
		}

		$parts   = array_map( 'trim', explode( '|', $raw_link ) );
		$network = isset( $parts[0] ) ? sanitize_key( $parts[0] ) : '';
		$url     = isset( $parts[1] ) ? esc_url_raw( $parts[1] ) : '';
		$label   = isset( $parts[2] ) && '' !== $parts[2] ? sanitize_text_field( $parts[2] ) : ucfirst( $network );

		if ( '' === $network || '' === $url ) {
			continue;
		}

		$links[] = array(
			'network' => $network,
			'url'     => $url,
			'label'   => $label,
		);
	}

	return $links;
}

/**
 * Nombre: maruri_get_social_icon_svg
 * Descripcion: Devuelve un icono SVG simple para una red conocida, con fallback generico cuando no hay coincidencia.
 * Uso: echo maruri_get_social_icon_svg( 'instagram' );
 * Parametros:
 * - $network: Clave de la red social.
 * Retorna: SVG inline listo para imprimirse.
 */
function maruri_get_social_icon_svg( $network ) {
	$network = sanitize_key( $network );
	$icons   = array(
		'instagram' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5"></rect><circle cx="12" cy="12" r="4.25"></circle><circle cx="17.25" cy="6.75" r="1.25"></circle></svg>',
		'linkedin'  => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6.5 8.25a1.75 1.75 0 1 1 0-3.5 1.75 1.75 0 0 1 0 3.5Zm-1.5 2.25h3v9H5v-9Zm5 0h2.88v1.3h.04c.4-.76 1.38-1.55 2.83-1.55 3.03 0 3.59 1.99 3.59 4.58v4.67h-3v-4.14c0-.99-.02-2.26-1.38-2.26-1.38 0-1.59 1.08-1.59 2.19v4.21h-3v-9Z"></path></svg>',
		'facebook'  => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M13.25 20v-7h2.35l.4-3h-2.75V8.12c0-.87.24-1.46 1.49-1.46H16V4.02c-.23-.03-1.03-.1-1.96-.1-1.94 0-3.29 1.18-3.29 3.36V10H8.5v3h2.25v7h2.5Z"></path></svg>',
		'youtube'   => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21.1 8.2a2.98 2.98 0 0 0-2.1-2.1C17.1 5.5 12 5.5 12 5.5s-5.1 0-7 .6A2.98 2.98 0 0 0 2.9 8.2c-.5 1.9-.5 3.8-.5 3.8s0 1.9.5 3.8a2.98 2.98 0 0 0 2.1 2.1c1.9.6 7 .6 7 .6s5.1 0 7-.6a2.98 2.98 0 0 0 2.1-2.1c.5-1.9.5-3.8.5-3.8s0-1.9-.5-3.8ZM10 15.25v-6.5L15.5 12 10 15.25Z"></path></svg>',
		'tiktok'    => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14.5 4c.42 1.6 1.35 2.82 2.82 3.64.83.47 1.72.72 2.68.76v2.7a8.2 8.2 0 0 1-2.62-.43v4.72a5.4 5.4 0 1 1-5.4-5.39c.2 0 .4.01.6.04v2.76a2.72 2.72 0 1 0 2.92 2.71V4h3Z"></path></svg>',
		'x'         => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18.9 4H21l-4.58 5.23L22 20h-4.4l-3.45-4.8L9.95 20H7.84l4.9-5.6L2 4h4.5l3.11 4.36L13.9 4h5Z"></path></svg>',
	);

	if ( isset( $icons[ $network ] ) ) {
		return $icons[ $network ];
	}

	return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9.5 6h8.5v8.5h-2V9.41l-8.8 8.8-1.4-1.42 8.79-8.79H9.5V6Z"></path><path d="M18 18H6V6h5V4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-5h-2v5Z"></path></svg>';
}

/**
 * Nombre: maruri_render_social_links
 * Descripcion: Imprime una lista accesible de redes sociales configuradas desde el admin.
 * Uso: maruri_render_social_links();
 */
function maruri_render_social_links() {
	$links = maruri_get_social_links();

	if ( empty( $links ) ) {
		return;
	}

	echo '<ul class="maruri-social-links" aria-label="' . esc_attr__( 'Social links', 'maruri' ) . '">';

	foreach ( $links as $link ) {
		echo '<li class="maruri-social-links__item">';
		echo '<a class="maruri-social-links__link maruri-social-links__link--' . esc_attr( $link['network'] ) . '" href="' . esc_url( $link['url'] ) . '" target="_blank" rel="noopener noreferrer">';
		echo '<span class="maruri-social-links__icon">' . maruri_get_social_icon_svg( $link['network'] ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<span class="screen-reader-text">' . esc_html( $link['label'] ) . '</span>';
		echo '</a>';
		echo '</li>';
	}

	echo '</ul>';
}

/**
 * Nombre: maruri_render_fallback_menu
 * Descripcion: Muestra un enlace basico al inicio cuando no hay menu asignado.
 * Uso: Fallback de wp_nav_menu en el header.
 */
function maruri_render_fallback_menu() {
	echo '<ul id="primary-menu" class="menu"><li class="menu-item"><a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'Inicio', 'maruri' ) . '</a></li></ul>';
}
