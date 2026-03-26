<?php
/**
 * Nombre: Single de historias Goodsleep
 * Descripcion: Renderiza una historia individual reutilizando el lenguaje visual del card de historias.
 * Uso: WordPress la usa para el CPT goodsleep_story.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

wp_enqueue_style( 'goodsleep-elementor-frontend' );
wp_enqueue_script( 'goodsleep-elementor-frontend' );

while ( have_posts() ) :
	the_post();

	$post_id         = get_the_ID();
	$story_name      = (string) get_post_meta( $post_id, '_goodsleep_story_name', true );
	$story_name      = $story_name ? $story_name : get_the_title();
	$media           = function_exists( 'goodsleep_get_story_primary_media' ) ? goodsleep_get_story_primary_media( $post_id ) : array(
		'type'         => '',
		'url'          => '',
		'download_url' => '',
	);
	$share_url       = function_exists( 'goodsleep_get_story_share_url' ) ? goodsleep_get_story_share_url( $post_id ) : get_permalink();
	$published_label = get_the_date( 'd/m/Y H:i' );
	$vote_total      = (int) get_post_meta( $post_id, '_goodsleep_vote_total', true );
	$vote_count      = (int) get_post_meta( $post_id, '_goodsleep_vote_count', true );
	$stored_average  = (float) get_post_meta( $post_id, '_goodsleep_vote_score', true );
	$vote_average    = $vote_count > 0 ? round( $vote_total / $vote_count, 2 ) : $stored_average;
	$moon_count      = $vote_average > 0 ? min( 5, max( 0, (int) round( $vote_average ) ) ) : 0;
	$user_has_voted  = function_exists( 'goodsleep_has_voted_today' ) ? goodsleep_has_voted_today( $post_id ) : false;
	$whatsapp_text   = function_exists( 'goodsleep_get_setting' ) ? (string) goodsleep_get_setting( 'whatsapp_share_text', '' ) : '';
	$whatsapp_text   = $whatsapp_text ? $whatsapp_text : 'Nada le quita el sueno a %s. Mira esta historia: %s';

	try {
		$whatsapp_message = sprintf( $whatsapp_text, $story_name, $share_url );
	} catch ( ValueError $error ) {
		$whatsapp_message = preg_replace( '/%s/', $story_name, $whatsapp_text, 1 );
		$whatsapp_message = preg_replace( '/%s/', $share_url, (string) $whatsapp_message, 1 );
	}

	$whatsapp_url      = 'https://wa.me/?text=' . rawurlencode( (string) $whatsapp_message );
	$rating_summary    = $vote_average > 0 ? number_format( $vote_average, 1, '.', '' ) . '/5' : __( 'Sin votos', 'maruri' );
	$custom_logo       = function_exists( 'get_custom_logo' ) ? get_custom_logo() : '';
	$brand_markup      = $custom_logo ? $custom_logo : '<span class="goodsleep-story-single__brand-text">Goodsleep</span>';
	$download_tooltip  = 'video' === $media['type'] ? __( 'Descargar video', 'maruri' ) : __( 'Descargar audio', 'maruri' );

	status_header( 200 );
	nocache_headers();

	echo '<!doctype html><html ' . get_language_attributes() . '><head><meta charset="' . esc_attr( get_bloginfo( 'charset' ) ) . '"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . esc_html( $story_name . ' | ' . get_bloginfo( 'name' ) ) . '</title>';
	wp_head();
	echo '</head><body class="' . esc_attr( implode( ' ', get_body_class( 'goodsleep-story-share' ) ) ) . '">';
	wp_body_open();
	?>
	<main id="primary" class="site-main goodsleep-story-single">
		<section class="goodsleep-story-single__hero">
			<div class="goodsleep-story-single__overlay"></div>
			<div class="maruri-shell goodsleep-story-single__shell">
				<div class="goodsleep-story-single__intro">
					<div class="goodsleep-story-single__eyebrow"><a href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>"><?php echo wp_kses_post( $brand_markup ); ?></a></div>
					<div class="goodsleep-story-single__heading">
						<p class="goodsleep-story-single__prefix"><?php esc_html_e( 'A', 'maruri' ); ?></p>
						<h1 class="goodsleep-story-single__title"><?php echo esc_html( $story_name ); ?></h1>
					</div>
					<p class="goodsleep-story-single__lead"><?php esc_html_e( 'Nada le quita el sueno', 'maruri' ); ?></p>
				</div>

				<article class="goodsleep-story-card goodsleep-story-single__card" data-story-detail data-story-id="<?php echo esc_attr( $post_id ); ?>">
					<div class="goodsleep-story-card__topline">
						<span class="goodsleep-story-card__title"><?php echo esc_html( $story_name ); ?></span>
						<time class="goodsleep-story-card__date" datetime="<?php echo esc_attr( get_the_date( DATE_ATOM ) ); ?>"><?php echo esc_html( $published_label ); ?></time>
					</div>

					<div class="goodsleep-story-card__text">
						<?php echo wp_kses_post( wpautop( get_the_content() ) ); ?>
					</div>

					<?php if ( 'audio' === $media['type'] && ! empty( $media['url'] ) ) : ?>
						<div class="goodsleep-story-share__player">
							<audio controls preload="metadata" src="<?php echo esc_url( $media['url'] ); ?>"></audio>
						</div>
					<?php endif; ?>

					<div class="goodsleep-story-card__actions">
						<div class="goodsleep-story-card__action-group">
							<?php if ( 'video' === $media['type'] && ! empty( $media['url'] ) ) : ?>
								<button type="button" class="goodsleep-story-card__action-button" data-action="view-video" data-video-url="<?php echo esc_url( $media['url'] ); ?>" data-tooltip="<?php esc_attr_e( 'Ver video', 'maruri' ); ?>" aria-label="<?php esc_attr_e( 'Ver video', 'maruri' ); ?>">
									<span class="goodsleep-story-card__action-icon" aria-hidden="true">
										<svg viewBox="0 0 16 16"><path fill="currentColor" d="M4.2 2.54c0-.78.84-1.27 1.52-.88l6.27 3.63c.68.39.68 1.37 0 1.76L5.72 10.68c-.68.39-1.52-.1-1.52-.88V2.54Z"></path><path fill="none" stroke="currentColor" d="M8 15.25A7.25 7.25 0 1 0 8 .75a7.25 7.25 0 0 0 0 14.5Z"></path></svg>
									</span>
									<span class="goodsleep-story-card__action-label"><?php esc_html_e( 'Ver video', 'maruri' ); ?></span>
								</button>
							<?php endif; ?>

							<?php if ( ! empty( $media['download_url'] ) ) : ?>
								<a class="goodsleep-story-card__action-button" href="<?php echo esc_url( $media['download_url'] ); ?>" download data-tooltip="<?php echo esc_attr( $download_tooltip ); ?>" aria-label="<?php echo esc_attr( $download_tooltip ); ?>">
									<span class="goodsleep-story-card__action-icon" aria-hidden="true">
										<svg viewBox="0 0 16 16"><g fill="currentColor"><path d="M13.02 6 10.2 5.99c-.25 0-.48-.17-.5-.38C9.67 5.37 9.86 5.1 10.13 5.1l2.85.01c.67 0 1.39.55 1.39 1.28v8.23c0 .72-.7 1.28-1.39 1.28H3.04c-.7 0-1.4-.55-1.4-1.28V6.38c0-.73.71-1.28 1.39-1.28l2.63-.01c.25 0 .46.21.47.43 0 .22-.21.46-.47.46H3.12c-.33 0-.63.2-.63.58v7.86c0 .37.28.59.64.59h9.84c.32 0 .54-.27.54-.57v-7.9c0-.25-.18-.54-.47-.54"></path><path d="M7.66.55c0-.28.23-.45.44-.46.18-.01.44.16.44.4v8.49l2.2-2.12c.16-.16.49-.11.62.05.12.14.13.45-.03.6l-2.89 2.83c-.18.18-.5.3-.71.09L4.84 7.54c-.16-.16-.19-.41-.08-.6.08-.16.45-.26.61-.1L7.66 9.1V.55Z"></path></g></svg>
									</span>
									<span class="goodsleep-story-card__action-label"><?php esc_html_e( 'Descargar', 'maruri' ); ?></span>
								</a>
							<?php endif; ?>

							<a class="goodsleep-story-card__action-button" href="<?php echo esc_url( $whatsapp_url ); ?>" target="_blank" rel="noopener noreferrer" data-tooltip="<?php esc_attr_e( 'Compartir historia', 'maruri' ); ?>" aria-label="<?php esc_attr_e( 'Compartir historia', 'maruri' ); ?>">
								<span class="goodsleep-story-card__action-icon" aria-hidden="true">
									<svg viewBox="0 0 16 16"><g fill="currentColor"><path d="M13.02 6 10.2 5.99c-.25 0-.48-.17-.5-.38-.03-.24.16-.51.43-.51l2.85.01c.67 0 1.39.55 1.39 1.28v8.23c0 .72-.7 1.28-1.39 1.28H3.04c-.7 0-1.4-.55-1.4-1.28V6.38c0-.73.71-1.28 1.39-1.28l2.63-.01c.25 0 .46.21.47.43 0 .22-.21.46-.47.46H3.12c-.33 0-.63.2-.63.58v7.86c0 .37.28.59.64.59h9.84c.32 0 .54-.27.54-.57v-7.9c0-.25-.18-.54-.47-.54"></path><path d="M8.48 10.08c0 .28-.23.45-.44.46-.18.01-.44-.16-.44-.4V1.65L5.4 3.77c-.16.16-.49.11-.62-.05-.12-.14-.13-.45.03-.6L7.7.29c.18-.18.5-.3.71-.09l2.89 2.89c.16.16.19.41.08.6-.08.16-.45.26-.61.1L8.48 1.53v8.55Z"></path></g></svg>
								</span>
								<span class="goodsleep-story-card__action-label"><?php esc_html_e( 'Compartir', 'maruri' ); ?></span>
							</a>

							<a class="goodsleep-story-card__action-button" href="<?php echo esc_url( home_url( '/#historias' ) ); ?>" data-tooltip="<?php esc_attr_e( 'Volver a las historias', 'maruri' ); ?>" aria-label="<?php esc_attr_e( 'Volver a las historias', 'maruri' ); ?>">
								<span class="goodsleep-story-card__action-icon" aria-hidden="true">
									<svg viewBox="0 0 16 16"><path fill="none" stroke="currentColor" d="M8.09 14.41c-.36 0-.75-.03-1.12-.09-2.75-.43-4.99-2.55-5.56-5.27C.79 6.13 2.05 3.25 4.62 1.71l.21-.13.87.38-.19.45C4.46 4.73 4.97 7.36 6.79 9.1c1.76 1.69 4.38 2.07 6.67.98l1.29-.61-.65 1.27c-1.19 2.31-3.48 3.67-6.01 3.67"></path></svg>
								</span>
								<span class="goodsleep-story-card__action-label"><?php esc_html_e( 'Historias', 'maruri' ); ?></span>
							</a>
						</div>

						<div class="goodsleep-story-card__rating-wrap">
							<span class="goodsleep-story-card__rating-summary"><?php echo esc_html( $rating_summary ); ?></span>
							<div class="goodsleep-story-card__rating<?php echo $user_has_voted ? ' is-readonly' : ''; ?>" data-rating-group data-readonly="<?php echo $user_has_voted ? 'true' : 'false'; ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Promedio %1$s de 5 basado en %2$s votos.', 'maruri' ), number_format( $vote_average, 1, '.', '' ), (string) $vote_count ) ); ?>">
								<?php for ( $index = 1; $index <= 5; $index++ ) : ?>
									<button type="button" class="goodsleep-story-card__moon<?php echo $index <= $moon_count ? ' is-active' : ''; ?>" data-action="vote" data-rating="<?php echo esc_attr( $index ); ?>" data-tooltip="<?php echo esc_attr( $user_has_voted ? __( 'Ya votaste hoy.', 'maruri' ) : sprintf( __( 'Votar con %d %s.', 'maruri' ), $index, 1 === $index ? __( 'luna', 'maruri' ) : __( 'lunas', 'maruri' ) ) ); ?>"<?php echo $user_has_voted ? ' disabled' : ''; ?>>
										<svg viewBox="0 0 16 16" aria-hidden="true"><path fill="none" stroke="currentColor" d="M8.09 14.41c-.36 0-.75-.03-1.12-.09-2.75-.43-4.99-2.55-5.56-5.27C.79 6.13 2.05 3.25 4.62 1.71l.21-.13.87.38-.19.45C4.46 4.73 4.97 7.36 6.79 9.1c1.76 1.69 4.38 2.07 6.67.98l1.29-.61-.65 1.27c-1.19 2.31-3.48 3.67-6.01 3.67"></path></svg>
									</button>
								<?php endfor; ?>
							</div>
						</div>
					</div>
				</article>
			</div>
		</section>
	</main>
	<?php
endwhile;

wp_footer();
echo '</body></html>';
