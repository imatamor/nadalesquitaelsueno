<?php
/**
 * Usa una pagina editable en Elementor como fuente del 404 de campana.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Goodsleep_Elementor_Campaign_404 {
	/**
	 * Pagina fuente activa para el 404.
	 *
	 * @var WP_Post|null
	 */
	protected $active_page = null;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'template_redirect', array( $this, 'prime_campaign_404' ), 1 );
		add_filter( 'template_include', array( $this, 'swap_template' ), 99 );
		add_filter( 'document_title_parts', array( $this, 'filter_document_title_parts' ) );
	}

	/**
	 * Convierte el request 404 en la pagina fuente, manteniendo el codigo HTTP 404.
	 *
	 * @return void
	 */
	public function prime_campaign_404() {
		if ( is_admin() || ! is_404() ) {
			return;
		}

		$page = $this->resolve_campaign_404_page();
		if ( ! $page ) {
			return;
		}

		$this->active_page = $page;

		status_header( 404 );
		nocache_headers();
		$this->prime_page_query_context( $page );
	}

	/**
	 * Reemplaza la plantilla 404 por una plantilla normal de pagina.
	 *
	 * @param string $template Plantilla original.
	 * @return string
	 */
	public function swap_template( $template ) {
		if ( ! $this->active_page instanceof WP_Post ) {
			return $template;
		}

		$page_template = get_page_template();
		if ( $page_template ) {
			return $page_template;
		}

		$singular_template = locate_template(
			array(
				'singular.php',
				'index.php',
			)
		);

		return $singular_template ? $singular_template : $template;
	}

	/**
	 * Ajusta el titulo del documento para usar el de la pagina fuente.
	 *
	 * @param array<string,string> $parts Partes del titulo.
	 * @return array<string,string>
	 */
	public function filter_document_title_parts( $parts ) {
		if ( ! $this->active_page instanceof WP_Post ) {
			return $parts;
		}

		$parts['title'] = get_the_title( $this->active_page );

		return $parts;
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
	 * Fuerza el contexto de una pagina normal para que Elementor la procese nativamente.
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
		$wp_query->max_num_pages     = 1;
		$wp_query->queried_object    = $page;
		$wp_query->queried_object_id = (int) $page->ID;
		$wp_query->is_404            = false;
		$wp_query->is_page           = true;
		$wp_query->is_singular       = true;
		$wp_query->is_home           = false;
		$wp_query->is_archive        = false;
		$wp_query->is_posts_page     = false;
		$wp_query->is_post_type_archive = false;
	}
}
