<?php
/**
 * Nombre: Plantilla 404
 * Descripcion: Muestra una pagina de error simple y reusable para contenido no encontrado.
 * Uso: WordPress la usa cuando no existe un recurso solicitado.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<main id="primary" class="site-main">
	<div class="maruri-shell maruri-single-shell maruri-single-shell--narrow">
		<section class="error-404 not-found">
			<header class="page-header">
				<h1 class="page-title"><?php esc_html_e( 'Esa pagina no existe.', 'maruri' ); ?></h1>
			</header>

			<div class="page-content">
				<p><?php esc_html_e( 'Prueba con otra direccion o usa el buscador del sitio.', 'maruri' ); ?></p>
				<?php get_search_form(); ?>
			</div>
		</section>
	</div>
</main>
<?php
get_footer();
