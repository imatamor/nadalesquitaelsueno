<?php
/**
 * Renderizado de la pagina 404 de campana desde una pagina editable en Elementor.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Goodsleep_Elementor_Campaign_404 {
	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'template_redirect', array( $this, 'render_campaign_404' ), 1 );
	}

	/**
	 * Renderiza una pagina 404 de campana si existe una pagina fuente publicada.
	 *
	 * @return void
	 */
	public function render_campaign_404() {
		if ( is_admin() || ! is_404() ) {
			return;
		}

		$page = $this->resolve_campaign_404_page();
		if ( ! $page ) {
			return;
		}

		status_header( 404 );
		nocache_headers();
		$this->prime_page_query_context( $page );

		$content = $this->get_page_content( $page->ID );
		if ( '' === trim( wp_strip_all_tags( $content ) ) ) {
			return;
		}

		$this->prepare_elementor_runtime( $page->ID );

		echo '<!doctype html><html ' . get_language_attributes() . '><head><meta charset="' . esc_attr( get_bloginfo( 'charset' ) ) . '"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . esc_html( get_the_title( $page ) ) . '</title>';
		wp_head();
		echo '</head><body class="' . esc_attr( implode( ' ', get_body_class( 'goodsleep-campaign-404 elementor-page elementor-page-' . $page->ID ) ) ) . '">';
		wp_body_open();
		echo '<main id="primary" class="goodsleep-campaign-404__main">' . $content . '</main>';
		wp_footer();
		echo '</body></html>';
		exit;
	}

	/**
	 * Busca la pagina fuente del 404 de campana.
	 *
	 * @return WP_Post|null
	 */
	protected function resolve_campaign_404_page() {
		$slugs = apply_filters(
			'goodsleep_campaign_404_slugs',
			array(
				'goodsleep-404',
				'pagina-404-campana',
				'pagina-404',
			)
		);

		foreach ( (array) $slugs as $slug ) {
			$slug = sanitize_title( (string) $slug );

			if ( '' === $slug ) {
				continue;
			}

			$page = get_page_by_path( $slug, OBJECT, 'page' );
			if ( $page instanceof WP_Post && 'publish' === $page->post_status ) {
				return $page;
			}
		}

		return null;
	}

	/**
	 * Devuelve el contenido renderizado de la pagina, priorizando Elementor.
	 *
	 * @param int $page_id ID de la pagina fuente.
	 * @return string
	 */
	protected function get_page_content( $page_id ) {
		$page_id = (int) $page_id;

		if ( class_exists( '\Elementor\Plugin' ) ) {
			$content = \Elementor\Plugin::$instance->frontend->get_builder_content_for_display( $page_id, true );
			if ( is_string( $content ) && '' !== trim( $content ) ) {
				return $content;
			}
		}

		$post = get_post( $page_id );
		if ( ! $post instanceof WP_Post ) {
			return '';
		}

		return apply_filters( 'the_content', $post->post_content );
	}

	/**
	 * Prepara los assets y el contexto basico que Elementor espera en frontend.
	 *
	 * @param int $page_id ID de la pagina fuente.
	 * @return void
	 */
	protected function prepare_elementor_runtime( $page_id ) {
		$page_id = (int) $page_id;

		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			return;
		}

		$plugin = \Elementor\Plugin::$instance;

		if ( isset( $plugin->frontend ) ) {
			$plugin->frontend->enqueue_styles();
			if ( method_exists( $plugin->frontend, 'enqueue_scripts' ) ) {
				$plugin->frontend->enqueue_scripts();
			}
		}

		if ( isset( $plugin->documents ) ) {
			$document = $plugin->documents->get( $page_id );
			if ( $document && method_exists( $document, 'enqueue_styles' ) ) {
				$document->enqueue_styles();
			}
		}
	}

	/**
	 * Fuerza un contexto de pagina para que Elementor cargue assets y documentos
	 * aunque la respuesta HTTP siga siendo 404.
	 *
	 * @param WP_Post $page Pagina fuente del 404.
	 * @return void
	 */
	protected function prime_page_query_context( $page ) {
		global $post, $wp_query;

		$post = $page;
		setup_postdata( $post );

		if ( ! $wp_query instanceof WP_Query ) {
			return;
		}

		$wp_query->post              = $page;
		$wp_query->posts             = array( $page );
		$wp_query->post_count        = 1;
		$wp_query->found_posts       = 1;
		$wp_query->queried_object    = $page;
		$wp_query->queried_object_id = (int) $page->ID;
		$wp_query->is_404            = false;
		$wp_query->is_page           = true;
		$wp_query->is_singular       = true;
		$wp_query->is_home           = false;
		$wp_query->is_archive        = false;
	}
}
