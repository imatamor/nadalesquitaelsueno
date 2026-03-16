<?php
/**
 * Nombre: Estado sin resultados
 * Descripcion: Muestra un mensaje consistente cuando no hay contenido para renderizar.
 * Uso: Se carga en loops vacios o resultados de busqueda sin coincidencias.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<section class="no-results not-found">
	<header class="page-header">
		<h1 class="page-title"><?php esc_html_e( 'No encontramos contenido.', 'maruri' ); ?></h1>
	</header>

	<div class="page-content">
		<p><?php esc_html_e( 'Prueba con otra busqueda o revisa las publicaciones mas recientes.', 'maruri' ); ?></p>
		<?php get_search_form(); ?>
	</div>
</section>
