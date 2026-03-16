<?php
/**
 * Nombre: Compatibilidad con builders
 * Descripcion: Agrega compatibilidades opcionales y no invasivas para builders sin volverlos una dependencia.
 * Uso: Se carga desde functions.php y actua solo cuando detecta contextos concretos.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Nombre: maruri_is_full_width_context
 * Descripcion: Determina si la vista actual debe usar un layout sin sidebar para contenido nativo o builders.
 * Uso: if ( maruri_is_full_width_context() ) { ... }
 * Retorna: True cuando el contexto requiere full width.
 */
function maruri_is_full_width_context() {
	if ( is_page_template( 'template-full-width.php' ) ) {
		return true;
	}

	if ( function_exists( 'elementor_theme_do_location' ) && is_singular() ) {
		$post_id = get_queried_object_id();

		if ( $post_id && 'builder' === get_post_meta( $post_id, '_elementor_edit_mode', true ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Nombre: maruri_body_classes
 * Descripcion: Agrega clases de contexto para estilos condicionales sin acoplar el markup a un builder.
 * Uso: Hookeada a body_class.
 * Parametros:
 * - $classes: Clases actuales del body.
 * Retorna: Array actualizado de clases.
 */
function maruri_body_classes( $classes ) {
	if ( maruri_is_full_width_context() ) {
		$classes[] = 'maruri-full-width-context';
	}

	if ( did_action( 'elementor/loaded' ) ) {
		$classes[] = 'maruri-elementor-compatible';
	}

	return $classes;
}
add_filter( 'body_class', 'maruri_body_classes' );
