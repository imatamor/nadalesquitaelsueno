<?php
/**
 * Nombre: Plantilla de archivos
 * Descripcion: Renderiza categorias, etiquetas, autores y otros listados archivados.
 * Uso: Plantilla para consultas de archivo.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<main id="primary" class="site-main">
	<div class="maruri-shell maruri-content-grid">
		<section class="maruri-content-area">
			<header class="page-header">
				<?php the_archive_title( '<h1 class="page-title">', '</h1>' ); ?>
				<?php the_archive_description( '<div class="archive-description">', '</div>' ); ?>
			</header>

			<?php if ( have_posts() ) : ?>
				<?php while ( have_posts() ) : ?>
					<?php the_post(); ?>
					<?php get_template_part( 'template-parts/content', get_post_type() ); ?>
				<?php endwhile; ?>

				<?php the_posts_navigation(); ?>
			<?php else : ?>
				<?php get_template_part( 'template-parts/content', 'none' ); ?>
			<?php endif; ?>
		</section>

		<?php get_sidebar(); ?>
	</div>
</main>
<?php
get_footer();
