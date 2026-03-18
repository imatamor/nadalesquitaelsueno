<?php
/**
 * Ruta corta publica para historias Goodsleep.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Goodsleep_Elementor_Share_Router {
	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register' ) );
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_action( 'template_redirect', array( $this, 'render_short_route' ) );
	}

	/**
	 * Registra rewrite rule.
	 *
	 * @return void
	 */
	public function register() {
		add_rewrite_rule( '^h/([^/]+)/?$', 'index.php?goodsleep_short_story=$matches[1]', 'top' );
	}

	/**
	 * Registra query var.
	 *
	 * @param array<int,string> $vars Query vars.
	 * @return array<int,string>
	 */
	public function query_vars( $vars ) {
		$vars[] = 'goodsleep_short_story';

		return $vars;
	}

	/**
	 * Renderiza la historia publica de una ruta corta.
	 *
	 * @return void
	 */
	public function render_short_route() {
		$slug = get_query_var( 'goodsleep_short_story', '' );

		if ( ! $slug ) {
			return;
		}

		$stories = get_posts(
			array(
				'post_type'      => 'goodsleep_story',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'meta_key'       => '_goodsleep_short_slug',
				'meta_value'     => sanitize_text_field( $slug ),
			)
		);

		if ( empty( $stories ) ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
			return;
		}

		$story            = $stories[0];
		$audio_id         = (int) get_post_meta( $story->ID, '_goodsleep_story_audio_id', true );
		$audio_url        = wp_get_attachment_url( $audio_id );
		$share_url        = goodsleep_get_story_share_url( $story->ID );
		$story_name       = (string) get_post_meta( $story->ID, '_goodsleep_story_name', true );
		$story_name       = $story_name ? $story_name : get_the_title( $story );
		$published_label  = get_the_date( 'd/m/Y', $story );
		$vote_total       = (int) get_post_meta( $story->ID, '_goodsleep_vote_total', true );
		$vote_count       = (int) get_post_meta( $story->ID, '_goodsleep_vote_count', true );
		$stored_average   = (float) get_post_meta( $story->ID, '_goodsleep_vote_score', true );
		$vote_average     = $vote_count > 0 ? round( $vote_total / $vote_count, 2 ) : $stored_average;
		$moon_count       = $vote_average > 0 ? min( 5, max( 0, (int) round( $vote_average ) ) ) : 0;
		$user_has_voted   = goodsleep_has_voted_today( $story->ID );
		$rating_summary   = $vote_average > 0 ? number_format( $vote_average, 1, '.', '' ) . '/5' : __( 'Sin votos', 'goodsleep-elementor' );
		$whatsapp_template = (string) goodsleep_get_setting( 'whatsapp_share_text', '' );
		$whatsapp_template = $whatsapp_template ? $whatsapp_template : 'Nada le quita el sueno a %s. Escucha esta historia: %s';
		$whatsapp_message  = $this->render_share_message( $whatsapp_template, $story_name, $share_url );
		$whatsapp_url      = 'https://wa.me/?text=' . rawurlencode( $whatsapp_message );
		$page_title = sprintf(
			/* translators: 1: story title, 2: site name */
			__( '%1$s | %2$s', 'goodsleep-elementor' ),
			$story_name,
			get_bloginfo( 'name' )
		);

		status_header( 200 );
		nocache_headers();
		if ( wp_style_is( 'maruri-style', 'registered' ) ) {
			wp_enqueue_style( 'maruri-style' );
		}
		if ( wp_style_is( 'maruri-base', 'registered' ) ) {
			wp_enqueue_style( 'maruri-base' );
		}
		if ( wp_style_is( 'maruri-goodsleep-landing', 'registered' ) ) {
			wp_enqueue_style( 'maruri-goodsleep-landing' );
		}
		wp_enqueue_style( 'goodsleep-elementor-frontend' );
		wp_enqueue_script( 'goodsleep-elementor-frontend' );

		echo '<!doctype html><html ' . get_language_attributes() . '><head><meta charset="' . esc_attr( get_bloginfo( 'charset' ) ) . '"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . esc_html( $page_title ) . '</title>';
		echo '<meta property="og:title" content="' . esc_attr( $page_title ) . '">';
		echo '<meta property="og:site_name" content="' . esc_attr( get_bloginfo( 'name' ) ) . '">';
		echo '<meta property="og:type" content="article">';
		echo '<meta property="og:url" content="' . esc_url( $share_url ) . '">';
		echo '<meta property="og:description" content="' . esc_attr( wp_trim_words( wp_strip_all_tags( $story->post_content ), 28 ) ) . '">';
		wp_head();
		echo '</head><body class="goodsleep-story-share">';
		wp_body_open();
		echo '<main class="goodsleep-story-single"><section class="goodsleep-story-single__hero"><div class="goodsleep-story-single__overlay"></div><div class="maruri-shell goodsleep-story-single__shell">';
		echo '<div class="goodsleep-story-single__intro">';
		echo '<p class="goodsleep-story-single__eyebrow"><a href="' . esc_url( home_url( '/' ) ) . '">Goodsleep</a></p>';
		echo '<h1 class="goodsleep-story-single__title">' . esc_html( $story_name ) . '</h1>';
		echo '<p class="goodsleep-story-single__lead">' . esc_html__( 'Que nada te quite el sueño', 'goodsleep-elementor' ) . '</p>';
		echo '</div>';
		echo '<article class="goodsleep-story-card goodsleep-story-single__card" data-story-detail data-story-id="' . esc_attr( $story->ID ) . '">';
		echo '<div class="goodsleep-story-card__topline"><span class="goodsleep-story-card__title">' . esc_html( $story_name ) . '</span><time class="goodsleep-story-card__date" datetime="' . esc_attr( get_the_date( DATE_ATOM, $story ) ) . '">' . esc_html( $published_label ) . '</time></div>';
		echo '<div class="goodsleep-story-card__text">' . wp_kses_post( wpautop( $story->post_content ) ) . '</div>';
		if ( $audio_url ) {
			echo '<audio controls preload="metadata" src="' . esc_url( $audio_url ) . '"></audio>';
		}
		echo '<div class="goodsleep-story-card__actions"><div class="goodsleep-story-card__action-group">';
		if ( $audio_url ) {
			echo '<a class="goodsleep-story-card__action-button" href="' . esc_url( $audio_url ) . '" download data-tooltip="' . esc_attr__( 'Descargar audio', 'goodsleep-elementor' ) . '" aria-label="' . esc_attr__( 'Descargar audio', 'goodsleep-elementor' ) . '"><span class="goodsleep-story-card__action-icon" aria-hidden="true"><svg viewBox="0 0 16 16"><g fill="currentColor"><path d="M13.02 6 10.2 5.99c-.25 0-.48-.17-.5-.38C9.67 5.37 9.86 5.1 10.13 5.1l2.85.01c.67 0 1.39.55 1.39 1.28v8.23c0 .72-.7 1.28-1.39 1.28H3.04c-.7 0-1.4-.55-1.4-1.28V6.38c0-.73.71-1.28 1.39-1.28l2.63-.01c.25 0 .46.21.47.43 0 .22-.21.46-.47.46H3.12c-.33 0-.63.2-.63.58v7.86c0 .37.28.59.64.59h9.84c.32 0 .54-.27.54-.57v-7.9c0-.25-.18-.54-.47-.54"></path><path d="M7.66.55c0-.28.23-.45.44-.46.18-.01.44.16.44.4v8.49l2.2-2.12c.16-.16.49-.11.62.05.12.14.13.45-.03.6l-2.89 2.83c-.18.18-.5.3-.71.09L4.84 7.54c-.16-.16-.19-.41-.08-.6.08-.16.45-.26.61-.1L7.66 9.1V.55Z"></path></g></svg></span><span class="goodsleep-story-card__action-label">' . esc_html__( 'Descargar', 'goodsleep-elementor' ) . '</span></a>';
		}
		echo '<a class="goodsleep-story-card__action-button" href="' . esc_url( $whatsapp_url ) . '" target="_blank" rel="noopener noreferrer" data-tooltip="' . esc_attr__( 'Compartir historia', 'goodsleep-elementor' ) . '" aria-label="' . esc_attr__( 'Compartir historia', 'goodsleep-elementor' ) . '"><span class="goodsleep-story-card__action-icon" aria-hidden="true"><svg viewBox="0 0 16 16"><g fill="currentColor"><path d="M13.02 6 10.2 5.99c-.25 0-.48-.17-.5-.38-.03-.24.16-.51.43-.51l2.85.01c.67 0 1.39.55 1.39 1.28v8.23c0 .72-.7 1.28-1.39 1.28H3.04c-.7 0-1.4-.55-1.4-1.28V6.38c0-.73.71-1.28 1.39-1.28l2.63-.01c.25 0 .46.21.47.43 0 .22-.21.46-.47.46H3.12c-.33 0-.63.2-.63.58v7.86c0 .37.28.59.64.59h9.84c.32 0 .54-.27.54-.57v-7.9c0-.25-.18-.54-.47-.54"></path><path d="M8.48 10.08c0 .28-.23.45-.44.46-.18.01-.44-.16-.44-.4V1.65L5.4 3.77c-.16.16-.49.11-.62-.05-.12-.14-.13-.45.03-.6L7.7.29c.18-.18.5-.3.71-.09l2.89 2.89c.16.16.19.41.08.6-.08.16-.45.26-.61.1L8.48 1.53v8.55Z"></path></g></svg></span><span class="goodsleep-story-card__action-label">' . esc_html__( 'Compartir', 'goodsleep-elementor' ) . '</span></a>';
		echo '<a class="goodsleep-story-card__action-button" href="' . esc_url( home_url( '/#historias' ) ) . '" data-tooltip="' . esc_attr__( 'Volver a las historias', 'goodsleep-elementor' ) . '" aria-label="' . esc_attr__( 'Volver a las historias', 'goodsleep-elementor' ) . '"><span class="goodsleep-story-card__action-icon" aria-hidden="true"><svg viewBox="0 0 16 16"><path fill="none" stroke="currentColor" d="M8.09 14.41c-.36 0-.75-.03-1.12-.09-2.75-.43-4.99-2.55-5.56-5.27C.79 6.13 2.05 3.25 4.62 1.71l.21-.13.87.38-.19.45C4.46 4.73 4.97 7.36 6.79 9.1c1.76 1.69 4.38 2.07 6.67.98l1.29-.61-.65 1.27c-1.19 2.31-3.48 3.67-6.01 3.67"></path></svg></span><span class="goodsleep-story-card__action-label">' . esc_html__( 'Historias', 'goodsleep-elementor' ) . '</span></a>';
		echo '</div><div class="goodsleep-story-card__rating-wrap"><span class="goodsleep-story-card__rating-summary">' . esc_html( $rating_summary ) . '</span><div class="goodsleep-story-card__rating' . ( $user_has_voted ? ' is-readonly' : '' ) . '" data-rating-group data-readonly="' . ( $user_has_voted ? 'true' : 'false' ) . '" aria-label="' . esc_attr( sprintf( __( 'Promedio %1$s de 5 basado en %2$s votos.', 'goodsleep-elementor' ), number_format( $vote_average, 1, '.', '' ), (string) $vote_count ) ) . '">';
		for ( $index = 1; $index <= 5; $index++ ) {
			$tooltip = $user_has_voted ? __( 'Ya votaste hoy.', 'goodsleep-elementor' ) : sprintf( _n( 'Votar con %d luna.', 'Votar con %d lunas.', $index, 'goodsleep-elementor' ), $index );
			echo '<button type="button" class="goodsleep-story-card__moon' . ( $index <= $moon_count ? ' is-active' : '' ) . '" data-action="vote" data-rating="' . esc_attr( $index ) . '" data-tooltip="' . esc_attr( $tooltip ) . '"' . ( $user_has_voted ? ' disabled' : '' ) . '><svg viewBox="0 0 16 16" aria-hidden="true"><path fill="none" stroke="currentColor" d="M8.09 14.41c-.36 0-.75-.03-1.12-.09-2.75-.43-4.99-2.55-5.56-5.27C.79 6.13 2.05 3.25 4.62 1.71l.21-.13.87.38-.19.45C4.46 4.73 4.97 7.36 6.79 9.1c1.76 1.69 4.38 2.07 6.67.98l1.29-.61-.65 1.27c-1.19 2.31-3.48 3.67-6.01 3.67"></path></svg></button>';
		}
		echo '</div></div></div></article></div></section></main>';
		wp_footer();
		echo '</body></html>';
		exit;
	}

	/**
	 * Renderiza el texto a compartir sin romper plantillas invalidas.
	 *
	 * @param string $template Plantilla configurable.
	 * @param string $name     Nombre visible.
	 * @param string $share_url URL corta publica.
	 * @return string
	 */
	protected function render_share_message( $template, $name, $share_url ) {
		try {
			return sprintf( (string) $template, (string) $name, (string) $share_url );
		} catch ( ValueError $error ) {
			$message = preg_replace( '/%s/', $name, (string) $template, 1 );
			$message = preg_replace( '/%s/', $share_url, (string) $message, 1 );

			return (string) $message;
		}
	}
}
