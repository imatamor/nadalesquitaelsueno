<?php
/**
 * Comando WP-CLI para preparar o ejecutar backfill de historias.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Goodsleep_Elementor_Backfill_Command {
	/**
	 * Servicio principal.
	 *
	 * @var Goodsleep_Elementor_Story_Video_Service
	 */
	protected $service;

	/**
	 * Constructor.
	 *
	 * @param Goodsleep_Elementor_Story_Video_Service $service Servicio.
	 */
	public function __construct( Goodsleep_Elementor_Story_Video_Service $service ) {
		$this->service = $service;
	}

	/**
	 * Hace dry-run del backfill.
	 *
	 * ## OPTIONS
	 *
	 * [--limit=<number>]
	 * : Numero maximo de historias a listar.
	 *
	 * @param array<int,string>       $args Argumentos posicionales.
	 * @param array<string,string|int> $assoc_args Argumentos nombrados.
	 * @return void
	 */
	public function dry_run( $args, $assoc_args ) {
		$stories = $this->get_target_stories( $assoc_args );

		if ( empty( $stories ) ) {
			WP_CLI::success( 'No hay historias pendientes para backfill.' );
			return;
		}

		foreach ( $stories as $story_id ) {
			$text = goodsleep_get_story_combined_text( $story_id );
			WP_CLI::log( sprintf( '#%1$d | escenas=%2$d | %3$s', $story_id, goodsleep_estimate_scene_count( $text ), wp_trim_words( $text, 20 ) ) );
		}

		WP_CLI::success( sprintf( 'Dry-run completado. Historias detectadas: %d', count( $stories ) ) );
	}

	/**
	 * Encola historias para backfill, pero no las procesa localmente.
	 *
	 * ## OPTIONS
	 *
	 * [--limit=<number>]
	 * : Numero maximo de historias.
	 *
	 * [--story-id=<id>]
	 * : Procesa una historia especifica.
	 *
	 * [--retry-failed]
	 * : Incluye historias fallidas del backfill.
	 *
	 * [--force]
	 * : Fuerza reproceso aunque ya tengan video.
	 *
	 * @param array<int,string>        $args Argumentos posicionales.
	 * @param array<string,string|int> $assoc_args Argumentos nombrados.
	 * @return void
	 */
	public function queue( $args, $assoc_args ) {
		$stories = $this->get_target_stories( $assoc_args );

		if ( empty( $stories ) ) {
			WP_CLI::success( 'No hay historias para encolar en backfill.' );
			return;
		}

		$force = ! empty( $assoc_args['force'] );

		foreach ( $stories as $story_id ) {
			$result = $this->service->queue_story_generation( $story_id, true, $force );

			if ( is_wp_error( $result ) ) {
				WP_CLI::warning( sprintf( 'Historia #%1$d omitida: %2$s', $story_id, $result->get_error_message() ) );
				continue;
			}

			WP_CLI::log( sprintf( 'Historia #%d encolada para backfill.', $story_id ) );
		}

		WP_CLI::success( 'Backfill encolado. No se ejecuta automaticamente fuera de la cola generada.' );
	}

	/**
	 * Obtiene historias objetivo para el backfill.
	 *
	 * @param array<string,string|int> $assoc_args Argumentos.
	 * @return array<int,int>
	 */
	protected function get_target_stories( $assoc_args ) {
		$story_id      = ! empty( $assoc_args['story-id'] ) ? absint( $assoc_args['story-id'] ) : 0;
		$limit         = ! empty( $assoc_args['limit'] ) ? max( 1, absint( $assoc_args['limit'] ) ) : 20;
		$retry_failed  = ! empty( $assoc_args['retry-failed'] );
		$meta_query    = array(
			'relation' => 'OR',
			array(
				'key'     => '_goodsleep_story_video_id',
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => '_goodsleep_story_video_id',
				'value'   => '0',
				'compare' => '=',
			),
		);

		if ( $retry_failed ) {
			$meta_query[] = array(
				'key'     => '_goodsleep_story_generation_status',
				'value'   => 'failed_backfill',
				'compare' => '=',
			);
		}

		$query_args = array(
			'post_type'      => 'goodsleep_story',
			'post_status'    => array( 'publish', 'draft' ),
			'posts_per_page' => $story_id ? 1 : $limit,
			'fields'         => 'ids',
			'orderby'        => 'date',
			'order'          => 'ASC',
			'meta_query'     => $meta_query,
		);

		if ( $story_id ) {
			$query_args['post__in'] = array( $story_id );
		}

		$posts = get_posts( $query_args );

		return array_values( array_map( 'absint', $posts ) );
	}
}
