<?php
/**
 * Template Name: Full Width
 * Template Post Type: page
 *
 * Nombre: Plantilla full width
 * Descripcion: Ofrece una pagina sin sidebar y con contenedor amplio para maquetadores o layouts especiales.
 * Uso: Asignar esta plantilla desde el editor de paginas cuando se requiera un lienzo mas limpio.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<main id="primary" class="site-main">
	<div class="maruri-shell maruri-shell--wide">
		<?php while ( have_posts() ) : ?>
			<?php the_post(); ?>
			<article id="post-<?php the_ID(); ?>" <?php post_class( 'maruri-entry maruri-entry--full-width' ); ?>>
				<div class="entry-content">
					<?php the_content(); ?>
					<?php wp_link_pages(); ?>
				</div>
			</article>
		<?php endwhile; ?>
	</div>
</main>
<?php
get_footer();
