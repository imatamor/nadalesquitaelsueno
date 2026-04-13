<?php
/**
 * Endpoints REST de Goodsleep Elementor.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Goodsleep_Elementor_REST_Controller {
	/**
	 * @var Goodsleep_Elementor_Speechify_Client
	 */
	protected $speechify;

	/**
	 * @var Goodsleep_Elementor_Story_Generator
	 */
	protected $story_generator;

	/**
	 * Constructor.
	 *
	 * @param Goodsleep_Elementor_Speechify_Client $speechify Cliente Speechify.
	 * @param Goodsleep_Elementor_Story_Generator $story_generator Servicio de generacion.
	 */
	public function __construct( Goodsleep_Elementor_Speechify_Client $speechify, Goodsleep_Elementor_Story_Generator $story_generator ) {
		$this->speechify       = $speechify;
		$this->story_generator = $story_generator;

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

		register_rest_route(
			'goodsleep/v1',
			'/catalog/voices/sync',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'sync_voices' ),
				'permission_callback' => static function () {
					return current_user_can( 'manage_options' );
				},
			)
		);
	}

	/**
	 * Genera una historia usando el servicio reutilizable.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function generate_story( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		$params = is_array( $params ) ? $params : array();

		$rate_limit = goodsleep_assert_generation_rate_limit();
		if ( is_wp_error( $rate_limit ) ) {
			return $rate_limit;
		}

		$result = $this->story_generator->generate_story(
			$params,
			array(
				'enforce_rate_limit' => false,
				'source'             => 'public_form',
			)
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
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
			$audio_id          = (int) get_post_meta( $post->ID, '_goodsleep_story_audio_id', true );
			$audio_url         = wp_get_attachment_url( $audio_id );
			$story_text_meta   = (string) get_post_meta( $post->ID, '_goodsleep_story_text', true );
			$stored_vote_score = (string) get_post_meta( $post->ID, '_goodsleep_vote_score', true );
			$vote_total        = (int) get_post_meta( $post->ID, '_goodsleep_vote_total', true );
			$vote_count        = (int) get_post_meta( $post->ID, '_goodsleep_vote_count', true );
			$favorite_count    = (int) get_post_meta( $post->ID, '_goodsleep_favorite_count', true );
			$story_name        = (string) get_post_meta( $post->ID, '_goodsleep_story_name', true );
			$story_text        = $this->normalize_story_list_text( $story_text_meta ? $story_text_meta : $post->post_content );
			$vote_score        = '' !== $stored_vote_score ? (float) $stored_vote_score : 0.0;

			if ( $vote_count > 0 ) {
				$calculated_vote_score = round( $vote_total / $vote_count, 2 );

				if ( abs( $calculated_vote_score - $vote_score ) > 0.001 ) {
					$vote_score = $calculated_vote_score;
					update_post_meta( $post->ID, '_goodsleep_vote_score', number_format( $vote_score, 2, '.', '' ) );
				}
			}

			if ( ! $audio_url ) {
				continue;
			}

			$stories[] = array(
				'id'             => $post->ID,
				'title'          => $story_name ? $story_name : get_the_title( $post ),
				'text'           => $story_text,
				'audioUrl'       => $audio_url,
				'downloadUrl'    => $audio_url,
				'shareUrl'       => goodsleep_get_story_share_url( $post->ID ),
				'favorite'       => goodsleep_is_favorite_story( $post->ID ),
				'favoriteCount'  => $favorite_count,
				'voteAverage'    => round( $vote_score, 2 ),
				'voteCount'      => $vote_count,
				'moonCount'      => min( 5, max( 0, (int) round( $vote_score ) ) ),
				'userHasVoted'   => goodsleep_has_voted_today( $post->ID ),
				'createdAt'      => get_the_date( DATE_ATOM, $post ),
				'publishedLabel' => get_the_date( 'd/m/Y H:i', $post ),
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
	 * Limpia el texto de la historia para el widget de listado.
	 *
	 * @param string $text Texto almacenado.
	 * @return string
	 */
	protected function normalize_story_list_text( $text ) {
		$text = (string) $text;

		if ( function_exists( 'excerpt_remove_blocks' ) ) {
			$text = excerpt_remove_blocks( $text );
		}

		$text = preg_replace( '/<!--\s+wp:.*?-->/', '', $text );
		$text = preg_replace( '/<!--\s+\/wp:.*?-->/', '', $text );
		$text = wp_strip_all_tags( $text );
		$text = preg_replace( "/[\r\n\t]+/", ' ', $text );
		$text = preg_replace( '/\s{2,}/', ' ', $text );

		return trim( (string) $text );
	}

	/**
	 * Alterna el estado favorito.
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

	/**
	 * Sincroniza voces desde Speechify.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function sync_voices() {
		$voices = $this->speechify->fetch_voices();

		if ( is_wp_error( $voices ) ) {
			return $voices;
		}

		return rest_ensure_response( $voices );
	}
}
