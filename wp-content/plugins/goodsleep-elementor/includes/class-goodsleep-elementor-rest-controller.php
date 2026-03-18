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
	 * @var Goodsleep_Elementor_Audio_Mixer
	 */
	protected $audio_mixer;

	/**
	 * @var Goodsleep_Elementor_Mailjet_Client
	 */
	protected $mailjet;

	/**
	 * Constructor.
	 *
	 * @param Goodsleep_Elementor_Speechify_Client $speechify Cliente Speechify.
	 * @param Goodsleep_Elementor_Audio_Mixer      $audio_mixer Mezclador de audio.
	 * @param Goodsleep_Elementor_Mailjet_Client   $mailjet   Cliente Mailjet.
	 */
	public function __construct( Goodsleep_Elementor_Speechify_Client $speechify, Goodsleep_Elementor_Audio_Mixer $audio_mixer, Goodsleep_Elementor_Mailjet_Client $mailjet ) {
		$this->speechify   = $speechify;
		$this->audio_mixer = $audio_mixer;
		$this->mailjet     = $mailjet;

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
	 * Genera una historia y su audio.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function generate_story( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		$params = is_array( $params ) ? $params : array();

		$name        = goodsleep_sanitize_story_name( isset( $params['name'] ) ? $params['name'] : '' );
		$email       = isset( $params['email'] ) ? sanitize_email( $params['email'] ) : '';
		$text        = isset( $params['story_text'] ) ? sanitize_textarea_field( $params['story_text'] ) : '';
		$phrase      = isset( $params['phrase_template'] ) ? sanitize_text_field( $params['phrase_template'] ) : '';
		$emotion     = isset( $params['phrase_emotion'] ) ? goodsleep_sanitize_speechify_emotion( $params['phrase_emotion'] ) : 'cheerful';
		$voice_id    = isset( $params['voice_id'] ) ? sanitize_text_field( $params['voice_id'] ) : '';
		$voice_label = isset( $params['voice_label'] ) ? sanitize_text_field( $params['voice_label'] ) : '';
		$track_id    = isset( $params['track_id'] ) ? sanitize_text_field( $params['track_id'] ) : '';
		$track_label = isset( $params['track_label'] ) ? sanitize_text_field( $params['track_label'] ) : '';
		$accepted    = ! empty( $params['accepted_terms'] );

		if ( ! $accepted || '' === $name || '' === $email || ! is_email( $email ) || '' === $voice_id || '' === $track_id || '' === $text ) {
			return new WP_Error( 'goodsleep_invalid_submission', __( 'Faltan campos obligatorios del formulario.', 'goodsleep-elementor' ), array( 'status' => 400 ) );
		}

		if ( strlen( $text ) > 500 ) {
			return new WP_Error( 'goodsleep_invalid_story_text', __( 'La historia supera el maximo de 500 caracteres.', 'goodsleep-elementor' ), array( 'status' => 400 ) );
		}

		$track = goodsleep_get_track_by_id( $track_id );

		if ( ! $track || empty( $track['url'] ) ) {
			return new WP_Error( 'goodsleep_invalid_track', __( 'Selecciona un track de musica valido.', 'goodsleep-elementor' ), array( 'status' => 400 ) );
		}

		if ( '' === $track_label && ! empty( $track['label'] ) ) {
			$track_label = sanitize_text_field( $track['label'] );
		}

		$rate_limit = goodsleep_assert_generation_rate_limit();
		if ( is_wp_error( $rate_limit ) ) {
			return $rate_limit;
		}

		$rendered_phrase = $this->render_phrase_template( $phrase, $name );
		$combined_text   = trim( $text . "\n" . $rendered_phrase );
		$speech_input    = $this->build_speechify_input( $text, $rendered_phrase, $emotion );

		$audio_response = $this->speechify->generate_audio(
			array(
				'text'     => $combined_text,
				'ssml'     => $speech_input,
				'voice_id' => $voice_id,
			)
		);

		if ( is_wp_error( $audio_response ) ) {
			return $audio_response;
		}

		$audio_url    = ! empty( $audio_response['audio_url'] ) ? esc_url_raw( $audio_response['audio_url'] ) : '';
		$audio_data   = ! empty( $audio_response['audio_data'] ) ? $audio_response['audio_data'] : '';
		$audio_format = ! empty( $audio_response['audio_format'] ) ? sanitize_key( $audio_response['audio_format'] ) : 'mp3';

		if ( '' === $audio_url && '' === $audio_data ) {
			return new WP_Error( 'goodsleep_audio_missing', __( 'Speechify no devolvio un audio utilizable.', 'goodsleep-elementor' ), array( 'status' => 502 ) );
		}

		$mixed_audio = $this->audio_mixer->mix_generated_audio(
			array(
				'audio_url'    => $audio_url,
				'audio_data'   => $audio_data,
				'audio_format' => $audio_format,
			),
			$track,
			goodsleep_normalize_slug( $name ) . '-' . time()
		);

		if ( is_wp_error( $mixed_audio ) ) {
			return $mixed_audio;
		}

		$post_id = wp_insert_post(
			array(
				'post_type'    => 'goodsleep_story',
				'post_status'  => 'publish',
				'post_title'   => $name,
				'post_content' => $text,
				'post_excerpt' => wp_trim_words( $text, 30 ),
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$audio_id = $this->store_audio_attachment( $post_id, $name, '', '', $mixed_audio['format'], $mixed_audio['path'] );

		if ( is_wp_error( $audio_id ) ) {
			if ( ! empty( $mixed_audio['path'] ) && file_exists( $mixed_audio['path'] ) ) {
				@unlink( $mixed_audio['path'] );
			}

			return $audio_id;
		}

		if ( ! empty( $mixed_audio['path'] ) && file_exists( $mixed_audio['path'] ) ) {
			@unlink( $mixed_audio['path'] );
		}

		$short_slug = goodsleep_normalize_slug( $name ) . '-' . $post_id;

		update_post_meta( $post_id, '_goodsleep_story_name', $name );
		update_post_meta( $post_id, '_goodsleep_story_email', $email );
		update_post_meta( $post_id, '_goodsleep_story_phrase', $rendered_phrase );
		update_post_meta( $post_id, '_goodsleep_story_phrase_emotion', $emotion );
		update_post_meta( $post_id, '_goodsleep_story_text', $text );
		update_post_meta( $post_id, '_goodsleep_story_combined', $combined_text );
		update_post_meta( $post_id, '_goodsleep_story_voice_id', $voice_id );
		update_post_meta( $post_id, '_goodsleep_story_voice_label', $voice_label );
		update_post_meta( $post_id, '_goodsleep_story_track_id', $track_id );
		update_post_meta( $post_id, '_goodsleep_story_track_label', $track_label );
		update_post_meta( $post_id, '_goodsleep_story_audio_id', $audio_id );
		update_post_meta( $post_id, '_goodsleep_short_slug', $short_slug );
		update_post_meta( $post_id, '_goodsleep_vote_score', 0 );
		update_post_meta( $post_id, '_goodsleep_favorite_count', 0 );

		$mail_result = $this->mailjet->send_story_email(
			array(
				'story_id' => $post_id,
				'audio_id' => $audio_id,
				'name'     => $name,
				'email'    => $email,
			)
		);

		return rest_ensure_response(
			array(
				'storyId'     => $post_id,
				'shareUrl'    => goodsleep_get_story_share_url( $post_id ),
				'audioUrl'    => wp_get_attachment_url( $audio_id ),
				'downloadUrl' => wp_get_attachment_url( $audio_id ),
				'emailSent'   => ! is_wp_error( $mail_result ),
			)
		);
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
			$audio_id   = (int) get_post_meta( $post->ID, '_goodsleep_story_audio_id', true );
			$audio_url  = wp_get_attachment_url( $audio_id );
			$vote_score = (int) get_post_meta( $post->ID, '_goodsleep_vote_score', true );

			$stories[] = array(
				'id'          => $post->ID,
				'title'       => get_the_title( $post ),
				'text'        => $post->post_content,
				'audioUrl'    => $audio_url,
				'downloadUrl' => $audio_url,
				'shareUrl'    => goodsleep_get_story_share_url( $post->ID ),
				'favorite'    => goodsleep_is_favorite_story( $post->ID ),
				'voteScore'   => $vote_score,
				'moonCount'   => min( 4, max( 0, $vote_score ) ),
				'createdAt'   => get_the_date( DATE_ATOM, $post ),
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

		if ( goodsleep_has_voted_today( $story_id ) ) {
			return new WP_Error( 'goodsleep_already_voted', __( 'Ya votaste por esta historia hoy.', 'goodsleep-elementor' ), array( 'status' => 409 ) );
		}

		$score = (int) get_post_meta( $story_id, '_goodsleep_vote_score', true );
		$score++;

		update_post_meta( $story_id, '_goodsleep_vote_score', $score );
		goodsleep_set_vote_cookie( $story_id );

		return rest_ensure_response(
			array(
				'voteScore' => $score,
				'moonCount' => min( 4, max( 0, $score ) ),
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

	/**
	 * Guarda el audio como adjunto.
	 *
	 * @param int    $post_id    ID del post.
	 * @param string $name       Nombre base.
	 * @param string $audio_url  URL remota.
	 * @param string $audio_data Base64 o contenido plano.
	 * @param string $audio_format Formato de audio.
	 * @param string $local_file   Archivo local preprocesado.
	 * @return int|WP_Error
	 */
	protected function store_audio_attachment( $post_id, $name, $audio_url, $audio_data, $audio_format = 'mp3', $local_file = '' ) {
		$upload = wp_upload_dir();

		if ( ! empty( $upload['error'] ) ) {
			return new WP_Error( 'goodsleep_upload_error', $upload['error'] );
		}

		$extension = in_array( $audio_format, array( 'mp3', 'wav', 'ogg', 'aac', 'pcm' ), true ) ? $audio_format : 'mp3';
		$mime_type = 'audio/mpeg';

		if ( 'wav' === $extension ) {
			$mime_type = 'audio/wav';
		} elseif ( 'ogg' === $extension ) {
			$mime_type = 'audio/ogg';
		} elseif ( 'aac' === $extension ) {
			$mime_type = 'audio/aac';
		}

		$filename = goodsleep_normalize_slug( $name ) . '-' . $post_id . '.' . $extension;
		$content  = '';

		if ( $local_file && file_exists( $local_file ) ) {
			$content = file_get_contents( $local_file );
		} elseif ( $audio_url ) {
			$response = wp_remote_get( $audio_url, array( 'timeout' => 45 ) );

			if ( is_wp_error( $response ) ) {
				return $response;
			}

			$content = wp_remote_retrieve_body( $response );
		} elseif ( $audio_data ) {
			$content = base64_decode( $audio_data );
		}

		if ( ! $content ) {
			return new WP_Error( 'goodsleep_empty_audio', __( 'No se pudo guardar el audio generado.', 'goodsleep-elementor' ) );
		}

		$written = wp_upload_bits( $filename, null, $content );
		if ( ! empty( $written['error'] ) ) {
			return new WP_Error( 'goodsleep_audio_write_failed', $written['error'] );
		}

		$filepath = $written['file'];

		$attachment_id = wp_insert_attachment(
			array(
				'post_mime_type' => $mime_type,
				'post_title'     => $name,
				'post_status'    => 'inherit',
			),
			$filepath,
			$post_id
		);

		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$metadata = wp_generate_attachment_metadata( $attachment_id, $filepath );
		wp_update_attachment_metadata( $attachment_id, $metadata );

		return $attachment_id;
	}

	/**
	 * Construye el input SSML para Speechify.
	 *
	 * @param string $story_text Texto principal de la historia.
	 * @param string $phrase_text Frase final dinamica.
	 * @param string $emotion Emocion aplicada a la frase final.
	 * @return string
	 */
	protected function build_speechify_input( $story_text, $phrase_text, $emotion = 'cheerful' ) {
		$story_text  = trim( wp_strip_all_tags( (string) $story_text ) );
		$phrase_text = trim( wp_strip_all_tags( (string) $phrase_text ) );
		$emotion     = goodsleep_sanitize_speechify_emotion( $emotion );

		if ( '' === $phrase_text ) {
			return $story_text;
		}

		return sprintf(
			'<speak>%1$s<break time="400ms" /><speechify:style emotion="%3$s">%2$s</speechify:style></speak>',
			htmlspecialchars( $story_text, ENT_XML1 | ENT_COMPAT, 'UTF-8' ),
			htmlspecialchars( $phrase_text, ENT_XML1 | ENT_COMPAT, 'UTF-8' ),
			htmlspecialchars( $emotion, ENT_XML1 | ENT_COMPAT, 'UTF-8' )
		);
	}

	/**
	 * Renderiza la frase final sin romper el flujo si la plantilla es invalida.
	 *
	 * @param string $template Plantilla configurable.
	 * @param string $name     Nombre ingresado por el usuario.
	 * @return string
	 */
	protected function render_phrase_template( $template, $name ) {
		$template = (string) $template;
		$name     = (string) $name;

		if ( '' === trim( $template ) ) {
			return '';
		}

		try {
			return sprintf( $template, $name );
		} catch ( ValueError $error ) {
			return str_replace( '%s', $name, $template );
		}
	}
}
