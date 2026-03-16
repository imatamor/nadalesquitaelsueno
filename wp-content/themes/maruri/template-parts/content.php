<?php
/**
 * Nombre: Tarjeta de contenido general
 * Descripcion: Presenta una salida reutilizable para listados y archivos.
 * Uso: Se carga desde index, archive y otras vistas de loop.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<article id="post-<?php the_ID(); ?>" <?php post_class( 'maruri-entry maruri-entry--summary' ); ?>>
	<header class="entry-header">
		<?php the_title( sprintf( '<h2 class="entry-title"><a href="%s" rel="bookmark">', esc_url( get_permalink() ) ), '</a></h2>' ); ?>
	</header>

	<div class="entry-summary">
		<?php the_excerpt(); ?>
	</div>
</article>
