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

		$story     = $stories[0];
		$audio_id  = (int) get_post_meta( $story->ID, '_goodsleep_story_audio_id', true );
		$audio_url = wp_get_attachment_url( $audio_id );
		$share_url = goodsleep_get_story_share_url( $story->ID );
		$story_name = (string) get_post_meta( $story->ID, '_goodsleep_story_name', true );
		$story_name = $story_name ? $story_name : get_the_title( $story );
		$page_title = sprintf(
			/* translators: 1: story title, 2: site name */
			__( '%1$s | %2$s', 'goodsleep-elementor' ),
			$story_name,
			get_bloginfo( 'name' )
		);

		status_header( 200 );
		nocache_headers();

		echo '<!doctype html><html ' . get_language_attributes() . '><head><meta charset="' . esc_attr( get_bloginfo( 'charset' ) ) . '"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . esc_html( $page_title ) . '</title>';
		echo '<meta property="og:title" content="' . esc_attr( $page_title ) . '">';
		echo '<meta property="og:site_name" content="' . esc_attr( get_bloginfo( 'name' ) ) . '">';
		echo '<meta property="og:type" content="article">';
		echo '<meta property="og:url" content="' . esc_url( $share_url ) . '">';
		echo '<meta property="og:description" content="' . esc_attr( wp_trim_words( wp_strip_all_tags( $story->post_content ), 28 ) ) . '">';
		wp_head();
		echo '</head><body class="goodsleep-story-share"><main class="goodsleep-story-share__main"><article class="goodsleep-story-share__card">';
		echo '<p class="goodsleep-story-share__eyebrow">Goodsleep</p>';
		echo '<h1>' . esc_html( $story_name ) . '</h1>';
		echo '<div class="goodsleep-story-share__content">' . wp_kses_post( wpautop( $story->post_content ) ) . '</div>';
		if ( $audio_url ) {
			echo '<div class="goodsleep-story-share__player"><audio controls preload="metadata" src="' . esc_url( $audio_url ) . '"></audio></div>';
			echo '<div class="goodsleep-story-share__actions">';
			echo '<a class="goodsleep-story-share__button" href="' . esc_url( $audio_url ) . '" download>' . esc_html__( 'Descargar', 'goodsleep-elementor' ) . '</a>';
			echo '<a class="goodsleep-story-share__button goodsleep-story-share__button--ghost" href="' . esc_url( $share_url ) . '">' . esc_html__( 'Escuchar', 'goodsleep-elementor' ) . '</a>';
			echo '</div>';
		}
		echo '</article></main>';
		wp_footer();
		echo '</body></html>';
		exit;
	}
}
