<?php
/**
 * Nombre: Bootstrap del theme Maruri
 * Descripcion: Carga los modulos base del theme reusable y centraliza su inicializacion.
 * Uso: WordPress ejecuta este archivo al activar o renderizar el theme.
 * Dependencias: Archivos en inc/ para setup, assets, helpers, compatibilidad y admin.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MARURI_THEME_VERSION', '1.0.0' );
define( 'MARURI_THEME_PATH', get_template_directory() );
define( 'MARURI_THEME_URL', get_template_directory_uri() );

require_once MARURI_THEME_PATH . '/inc/helpers.php';
require_once MARURI_THEME_PATH . '/inc/setup.php';
require_once MARURI_THEME_PATH . '/inc/assets.php';
require_once MARURI_THEME_PATH . '/inc/builder-compat.php';
require_once MARURI_THEME_PATH . '/inc/admin/options-page.php';
