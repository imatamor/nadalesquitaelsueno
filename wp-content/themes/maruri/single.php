<?php
/**
 * Nombre: Plantilla single
 * Descripcion: Renderiza entradas individuales del blog o de otros tipos de contenido con sidebar opcional.
 * Uso: Plantilla por defecto para vistas individuales.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<main id="primary" class="site-main">
	<div class="maruri-shell maruri-content-grid">
		<section class="maruri-content-area">
			<?php while ( have_posts() ) : ?>
				<?php the_post(); ?>
				<article id="post-<?php the_ID(); ?>" <?php post_class( 'maruri-entry maruri-entry--single' ); ?>>
					<header class="entry-header">
						<?php the_title( '<h1 class="entry-title">', '</h1>' ); ?>
					</header>

					<div class="entry-content">
						<?php the_content(); ?>
						<?php wp_link_pages(); ?>
					</div>
				</article>

				<?php the_post_navigation(); ?>
				<?php comments_template(); ?>
			<?php endwhile; ?>
		</section>

		<?php get_sidebar(); ?>
	</div>
</main>
<?php
get_footer();
