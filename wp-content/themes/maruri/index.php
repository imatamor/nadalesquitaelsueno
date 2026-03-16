<?php
/**
 * Nombre: Plantilla principal
 * Descripcion: Renderiza archivos y listados generales cuando no existe una plantilla mas especifica.
 * Uso: Fallback principal del theme.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<main id="primary" class="site-main">
	<div class="maruri-shell maruri-content-grid">
		<section class="maruri-content-area">
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
