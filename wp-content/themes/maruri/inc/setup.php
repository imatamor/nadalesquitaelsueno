<?php
/**
 * Nombre: Setup del theme
 * Descripcion: Registra soportes, menus, sidebars y comportamientos base del theme.
 * Uso: Sus hooks se cargan desde functions.php al inicializar WordPress.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Nombre: maruri_setup
 * Descripcion: Declara el soporte base de WordPress que necesita el theme para funcionar de forma independiente.
 * Uso: Hookeada a after_setup_theme.
 */
function maruri_setup() {
	load_theme_textdomain( 'maruri', MARURI_THEME_PATH . '/languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'custom-logo' );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'customize-selective-refresh-widgets' );
	add_theme_support( 'align-wide' );
	add_theme_support( 'responsive-embeds' );

	register_nav_menus(
		array(
			'primary' => __( 'Primary Menu', 'maruri' ),
			'footer'  => __( 'Footer Menu', 'maruri' ),
		)
	);
}
add_action( 'after_setup_theme', 'maruri_setup' );

/**
 * Nombre: maruri_content_width
 * Descripcion: Define un ancho de contenido razonable para medios embebidos y contenido nativo.
 * Uso: Hookeada a after_setup_theme.
 */
function maruri_content_width() {
	$GLOBALS['content_width'] = apply_filters( 'maruri_content_width', 860 );
}
add_action( 'after_setup_theme', 'maruri_content_width', 0 );

/**
 * Nombre: maruri_register_sidebars
 * Descripcion: Registra areas de widgets reutilizables para sidebar y footer.
 * Uso: Hookeada a widgets_init.
 */
function maruri_register_sidebars() {
	register_sidebar(
		array(
			'name'          => __( 'Primary Sidebar', 'maruri' ),
			'id'            => 'sidebar-1',
			'description'   => __( 'Widgets mostrados en archivos y entradas cuando el layout no es full width.', 'maruri' ),
			'before_widget' => '<section id="%1$s" class="widget %2$s">',
			'after_widget'  => '</section>',
			'before_title'  => '<h2 class="widget-title">',
			'after_title'   => '</h2>',
		)
	);

	register_sidebar(
		array(
			'name'          => __( 'Footer', 'maruri' ),
			'id'            => 'footer-1',
			'description'   => __( 'Widgets mostrados en el footer global.', 'maruri' ),
			'before_widget' => '<section id="%1$s" class="widget %2$s">',
			'after_widget'  => '</section>',
			'before_title'  => '<h2 class="widget-title">',
			'after_title'   => '</h2>',
		)
	);
}
add_action( 'widgets_init', 'maruri_register_sidebars' );

/**
 * Nombre: maruri_disable_block_widgets_editor
 * Descripcion: Fuerza la pantalla clasica de widgets para evitar incompatibilidades del editor por bloques en este theme.
 * Uso: Hookeada al filtro use_widgets_block_editor.
 * Retorna: False para desactivar el editor de widgets por bloques.
 */
function maruri_disable_block_widgets_editor() {
	return false;
}
add_filter( 'use_widgets_block_editor', 'maruri_disable_block_widgets_editor' );
