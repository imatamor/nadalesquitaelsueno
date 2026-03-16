<?php
/**
 * Nombre: Sidebar principal
 * Descripcion: Renderiza la barra lateral si el area de widgets correspondiente esta activa.
 * Uso: Se incluye en plantillas que usan layout de dos columnas.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! maruri_should_display_sidebar() ) {
	return;
}
?>
<aside id="secondary" class="widget-area">
	<?php dynamic_sidebar( 'sidebar-1' ); ?>
</aside>
