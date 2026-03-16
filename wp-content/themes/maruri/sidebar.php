<?php
/**
 * Nombre: Sidebar principal
 * Descripcion: Renderiza la barra lateral si el area de widgets correspondiente esta activa.
 * Uso: Se incluye en plantillas que usan layout de dos columnas.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! is_active_sidebar( 'sidebar-1' ) || maruri_is_full_width_context() ) {
	return;
}
?>
<aside id="secondary" class="widget-area">
	<?php dynamic_sidebar( 'sidebar-1' ); ?>
</aside>
