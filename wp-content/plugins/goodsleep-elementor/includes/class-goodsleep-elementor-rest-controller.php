<?php
/**
 * Endpoints REST de Goodsleep Elementor.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Goodsleep_Elementor_REST_Controller {
	/**
	 * Servicio de video.
	 *
	 * @var Goodsleep_Elementor_Story_Video_Service
	 */
	protected $video_service;

	/**
	 * Cliente Mailjet.
	 *
	 * @var Goodsleep_Elementor_Mailjet_Client
	 */
	protected $mailjet;

	/**
	 * Constructor.
	 *
	 * @param Goodsleep_Elementor_Story_Video_Service $video_service Servicio principal.
	 * @param Goodsleep_Elementor_Mailjet_Client      $mailjet       Correo.
	 */
	public function __construct( Goodsleep_Elementor_Story_Video_Service $video_service, Goodsleep_Elementor_Mailjet_Client $mailjet ) {
		$this->video_service = $video_service;
		$this->mailjet       = $mailjet;

		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Registra endpoints.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			'goodsleep/v1',
			'/generate-story',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'generate_story' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'goodsleep/v1',
			'/stories/(?P<id>\d+)/status',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_story_status' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'goodsleep/v1',
			'/stories',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list_stories' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'goodsleep/v1',
			'/stories/(?P<id>\d+)/favorite',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'toggle_favorite' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'goodsleep/v1',
			'/stories/(?P<id>\d+)/vote',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'vote_story' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Genera una historia y crea su tarea de video.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function generate_story( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		$params = is_array( $params ) ? $params : array();

		$default_track = goodsleep_get_default_track();
		if ( empty( $params['track_id'] ) && ! empty( $default_track['id'] ) ) {
			$params['track_id'] = (string) $default_track['id'];
		}

		if ( empty( $params['track_label'] ) && ! empty( $default_track['label'] ) ) {
			$params['track_label'] = (string) $default_track['label'];
		}

		$rate_limit = goodsleep_assert_generation_rate_limit();
		if ( is_wp_error( $rate_limit ) ) {
			return $rate_limit;
		}

		$result = $this->video_service->create_generation( $params );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Devuelve el estado de una historia.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_story_status( WP_REST_Request $request ) {
		$story_id = (int) $request['id'];

		if ( $story_id <= 0 || 'goodsleep_story' !== get_post_type( $story_id ) ) {
			return new WP_Error( 'goodsleep_story_not_found', __( 'No se encontro la historia solicitada.', 'goodsleep-elementor' ), array( 'status' => 404 ) );
		}

		$status = (string) get_post_meta( $story_id, '_goodsleep_story_generation_status', true );
		if ( in_array( $status, array( 'processing', 'pending_backfill' ), true ) ) {
			$this->video_service->process_story( $story_id );
		}

		return rest_ensure_response( $this->video_service->build_status_payload( $story_id ) );
	}

	/**
	 * Lista historias publicas.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function list_stories( WP_REST_Request $request ) {
		$page   = max( 1, (int) $request->get_param( 'page' ) );
		$search = sanitize_text_field( (string) $request->get_param( 'search' ) );
		$sort   = sanitize_text_field( (string) $request->get_param( 'sort' ) );

		$args = array(
			'post_type'      => 'goodsleep_story',
			'post_status'    => 'publish',
			'posts_per_page' => 10,
			'paged'          => $page,
			's'              => $search,
		);

		if ( 'rank' === $sort ) {
			$args['meta_key'] = '_goodsleep_vote_score';
			$args['orderby']  = array( 'meta_value_num' => 'DESC', 'date' => 'DESC' );
		} else {
			$args['orderby'] = 'date';
			$args['order']   = 'DESC';
		}

		$query   = new WP_Query( $args );
		$stories = array();

		foreach ( $query->posts as $post ) {
			$media              = goodsleep_get_story_primary_media( $post->ID );
			$stored_vote_score  = (string) get_post_meta( $post->ID, '_goodsleep_vote_score', true );
			$vote_total         = (int) get_post_meta( $post->ID, '_goodsleep_vote_total', true );
			$vote_count         = (int) get_post_meta( $post->ID, '_goodsleep_vote_count', true );
			$favorite_count     = (int) get_post_meta( $post->ID, '_goodsleep_favorite_count', true );
			$story_name         = (string) get_post_meta( $post->ID, '_goodsleep_story_name', true );
			$generation_status  = (string) get_post_meta( $post->ID, '_goodsleep_story_generation_status', true );
			$vote_score         = '' !== $stored_vote_score ? (float) $stored_vote_score : 0.0;

			if ( $vote_count > 0 ) {
				$calculated_vote_score = round( $vote_total / $vote_count, 2 );
				if ( abs( $calculated_vote_score - $vote_score ) > 0.001 ) {
					$vote_score = $calculated_vote_score;
					update_post_meta( $post->ID, '_goodsleep_vote_score', number_format( $vote_score, 2, '.', '' ) );
				}
			}

			if ( empty( $media['url'] ) ) {
				continue;
			}

			$stories[] = array(
				'id'             => $post->ID,
				'title'          => $story_name ? $story_name : get_the_title( $post ),
				'text'           => $post->post_content,
				'mediaType'      => $media['type'],
				'videoUrl'       => 'video' === $media['type'] ? $media['url'] : '',
				'audioUrl'       => 'audio' === $media['type'] ? $media['url'] : '',
				'downloadUrl'    => $media['download_url'],
				'shareUrl'       => goodsleep_get_story_share_url( $post->ID ),
				'favorite'       => goodsleep_is_favorite_story( $post->ID ),
				'favoriteCount'  => $favorite_count,
				'voteAverage'    => round( $vote_score, 2 ),
				'voteCount'      => $vote_count,
				'moonCount'      => min( 5, max( 0, (int) round( $vote_score ) ) ),
				'userHasVoted'   => goodsleep_has_voted_today( $post->ID ),
				'createdAt'      => get_the_date( DATE_ATOM, $post ),
				'publishedLabel' => get_the_date( 'd/m/Y H:i', $post ),
				'status'         => $generation_status ? $generation_status : 'ready',
			);
		}

		if ( 'favorites' === $sort ) {
			usort(
				$stories,
				static function ( $left, $right ) {
					return (int) $right['favorite'] <=> (int) $left['favorite'];
				}
			);
		}

		return rest_ensure_response(
			array(
				'items'    => $stories,
				'maxPages' => (int) $query->max_num_pages,
				'page'     => $page,
			)
		);
	}

	/**
	 * Alterna favoritos.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function toggle_favorite( WP_REST_Request $request ) {
		$story_id   = (int) $request['id'];
		$favorites  = isset( $_COOKIE['goodsleep_favorites'] ) ? explode( ',', sanitize_text_field( wp_unslash( $_COOKIE['goodsleep_favorites'] ) ) ) : array();
		$favorites  = array_filter( array_map( 'absint', $favorites ) );
		$is_current = in_array( $story_id, $favorites, true );
		$count      = (int) get_post_meta( $story_id, '_goodsleep_favorite_count', true );

		if ( $is_current ) {
			$favorites = array_values( array_diff( $favorites, array( $story_id ) ) );
			$count     = max( 0, $count - 1 );
		} else {
			$favorites[] = $story_id;
			$favorites   = array_unique( $favorites );
			$count++;
		}

		goodsleep_store_favorites_cookie( $favorites );
		update_post_meta( $story_id, '_goodsleep_favorite_count', $count );

		return rest_ensure_response(
			array(
				'favorite'      => ! $is_current,
				'favoriteCount' => $count,
			)
		);
	}

	/**
	 * Registra un voto diario.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function vote_story( WP_REST_Request $request ) {
		$story_id = (int) $request['id'];
		$params   = $request->get_json_params();
		$params   = is_array( $params ) ? $params : array();
		$rating   = isset( $params['rating'] ) ? (int) $params['rating'] : 0;

		if ( $rating < 1 || $rating > 5 ) {
			return new WP_Error( 'goodsleep_invalid_vote', __( 'Selecciona una puntuacion valida entre 1 y 5 lunas.', 'goodsleep-elementor' ), array( 'status' => 400 ) );
		}

		if ( goodsleep_has_voted_today( $story_id ) ) {
			return new WP_Error( 'goodsleep_already_voted', __( 'Ya votaste por esta historia hoy.', 'goodsleep-elementor' ), array( 'status' => 409 ) );
		}

		$total = (int) get_post_meta( $story_id, '_goodsleep_vote_total', true );
		$count = (int) get_post_meta( $story_id, '_goodsleep_vote_count', true );

		$total += $rating;
		$count++;
		$score = $count > 0 ? round( $total / $count, 2 ) : 0;

		update_post_meta( $story_id, '_goodsleep_vote_score', number_format( $score, 2, '.', '' ) );
		update_post_meta( $story_id, '_goodsleep_vote_total', $total );
		update_post_meta( $story_id, '_goodsleep_vote_count', $count );
		goodsleep_set_vote_cookie( $story_id );

		return rest_ensure_response(
			array(
				'voteAverage'  => $score,
				'voteCount'    => $count,
				'moonCount'    => min( 5, max( 0, (int) round( $score ) ) ),
				'userHasVoted' => true,
			)
		);
	}
}
