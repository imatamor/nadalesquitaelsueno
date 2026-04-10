<?php
/**
 * Servicio reutilizable para generar historias audio-only.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Goodsleep_Elementor_Story_Generator {
	/**
	 * Cliente Speechify.
	 *
	 * @var Goodsleep_Elementor_Speechify_Client
	 */
	protected $speechify;

	/**
	 * Mezclador de audio.
	 *
	 * @var Goodsleep_Elementor_Audio_Mixer
	 */
	protected $audio_mixer;

	/**
	 * Cliente Mailjet.
	 *
	 * @var Goodsleep_Elementor_Mailjet_Client
	 */
	protected $mailjet;

	/**
	 * Constructor.
	 *
	 * @param Goodsleep_Elementor_Speechify_Client $speechify Cliente Speechify.
	 * @param Goodsleep_Elementor_Audio_Mixer $audio_mixer Mezclador.
	 * @param Goodsleep_Elementor_Mailjet_Client $mailjet Cliente Mailjet.
	 */
	public function __construct( Goodsleep_Elementor_Speechify_Client $speechify, Goodsleep_Elementor_Audio_Mixer $audio_mixer, Goodsleep_Elementor_Mailjet_Client $mailjet ) {
		$this->speechify   = $speechify;
		$this->audio_mixer = $audio_mixer;
		$this->mailjet     = $mailjet;
	}

	/**
	 * Genera una historia usando el flujo productivo audio-only.
	 *
	 * @param array<string,mixed> $params Datos de entrada.
	 * @param array<string,mixed> $options Opciones de ejecucion.
	 * @return array<string,mixed>|WP_Error
	 */
	public function generate_story( $params, $options = array() ) {
		$options = wp_parse_args(
			is_array( $options ) ? $options : array(),
			array(
				'send_email'            => true,
				'enforce_rate_limit'    => true,
				'bypass_terms'          => false,
				'post_status'           => 'publish',
				'post_date'             => '',
				'source'                => 'public_form',
				'external_reference'    => '',
				'batch_id'              => '',
				'import_row_index'      => null,
				'openai_prompt'         => '',
				'email_suppressed_note' => '',
			)
		);

		$name          = goodsleep_sanitize_story_name( isset( $params['name'] ) ? $params['name'] : '' );
		$email         = isset( $params['email'] ) ? sanitize_email( $params['email'] ) : '';
		$text          = isset( $params['story_text'] ) ? sanitize_textarea_field( $params['story_text'] ) : '';
		$emotion       = isset( $params['phrase_emotion'] ) ? goodsleep_sanitize_speechify_emotion( $params['phrase_emotion'] ) : 'cheerful';
		$default_voice = goodsleep_get_default_voice();
		$default_track = goodsleep_get_default_track();
		$voice_id      = isset( $params['voice_id'] ) ? sanitize_text_field( $params['voice_id'] ) : '';
		$voice_label   = isset( $params['voice_label'] ) ? sanitize_text_field( $params['voice_label'] ) : '';
		$track_id      = isset( $params['track_id'] ) ? sanitize_text_field( $params['track_id'] ) : '';
		$track_label   = isset( $params['track_label'] ) ? sanitize_text_field( $params['track_label'] ) : '';
		$accepted      = ! empty( $params['accepted_terms'] ) || ! empty( $options['bypass_terms'] );
		$phrase        = '';

		if ( '' === $voice_id && ! empty( $default_voice['id'] ) ) {
			$voice_id = sanitize_text_field( (string) $default_voice['id'] );
		}

		if ( '' === $voice_label && ! empty( $default_voice['label'] ) ) {
			$voice_label = sanitize_text_field( (string) $default_voice['label'] );
		}

		if ( '' === $track_id && ! empty( $default_track['id'] ) ) {
			$track_id = sanitize_text_field( (string) $default_track['id'] );
		}

		if ( '' === $track_label && ! empty( $default_track['label'] ) ) {
			$track_label = sanitize_text_field( (string) $default_track['label'] );
		}

		if ( ! empty( $params['generated_phrase'] ) ) {
			$phrase = sanitize_text_field( (string) $params['generated_phrase'] );
		} elseif ( isset( $params['phrase_template'] ) ) {
			$phrase = $this->render_phrase_template( $params['phrase_template'], $name );
		}

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
			$track_label = sanitize_text_field( (string) $track['label'] );
		}

		if ( ! empty( $options['enforce_rate_limit'] ) ) {
			$rate_limit = goodsleep_assert_generation_rate_limit();
			if ( is_wp_error( $rate_limit ) ) {
				return $rate_limit;
			}
		}

		$combined_text = trim( $text . "\n" . $phrase );
		$speech_input  = $this->build_speechify_input( $text, $phrase, $emotion );
		$audio_response = $this->speechify->generate_audio(
			array(
				'text'     => $combined_text,
				'ssml'     => $speech_input,
				'voice_id' => $voice_id,
				'model'    => 'simba-multilingual',
				'language' => 'es-ES',
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

		$postarr = array(
			'post_type'    => 'goodsleep_story',
			'post_status'  => sanitize_key( (string) $options['post_status'] ),
			'post_title'   => $this->build_story_post_title( $name, $email, $options ),
			'post_content' => $text,
			'post_excerpt' => wp_trim_words( $text, 30 ),
		);

		if ( ! empty( $options['post_date'] ) ) {
			$postarr['post_date']     = sanitize_text_field( (string) $options['post_date'] );
			$postarr['post_date_gmt'] = get_gmt_from_date( $postarr['post_date'] );
		}

		$post_id = wp_insert_post( $postarr, true );
		if ( is_wp_error( $post_id ) ) {
			if ( ! empty( $mixed_audio['path'] ) && file_exists( $mixed_audio['path'] ) ) {
				@unlink( $mixed_audio['path'] );
			}

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
		update_post_meta( $post_id, '_goodsleep_story_phrase', $phrase );
		update_post_meta( $post_id, '_goodsleep_story_phrase_emotion', $emotion );
		update_post_meta( $post_id, '_goodsleep_story_text', $text );
		update_post_meta( $post_id, '_goodsleep_story_combined', $combined_text );
		update_post_meta( $post_id, '_goodsleep_story_voice_id', $voice_id );
		update_post_meta( $post_id, '_goodsleep_story_voice_label', $voice_label );
		update_post_meta( $post_id, '_goodsleep_story_track_id', $track_id );
		update_post_meta( $post_id, '_goodsleep_story_track_label', $track_label );
		update_post_meta( $post_id, '_goodsleep_story_audio_id', $audio_id );
		update_post_meta( $post_id, '_goodsleep_short_slug', $short_slug );
		update_post_meta( $post_id, '_goodsleep_vote_score', '0.00' );
		update_post_meta( $post_id, '_goodsleep_vote_total', 0 );
		update_post_meta( $post_id, '_goodsleep_vote_count', 0 );
		update_post_meta( $post_id, '_goodsleep_favorite_count', 0 );
		update_post_meta( $post_id, '_goodsleep_story_source', sanitize_key( (string) $options['source'] ) );
		update_post_meta( $post_id, '_goodsleep_story_external_reference', sanitize_text_field( (string) $options['external_reference'] ) );
		update_post_meta( $post_id, '_goodsleep_story_import_batch_id', sanitize_text_field( (string) $options['batch_id'] ) );
		update_post_meta( $post_id, '_goodsleep_story_openai_prompt', sanitize_textarea_field( (string) $options['openai_prompt'] ) );
		update_post_meta( $post_id, '_goodsleep_story_email_suppressed', empty( $options['send_email'] ) ? 1 : 0 );

		if ( null !== $options['import_row_index'] && '' !== (string) $options['import_row_index'] ) {
			update_post_meta( $post_id, '_goodsleep_story_import_row_index', absint( $options['import_row_index'] ) );
		}

		$mail_result = true;
		if ( ! empty( $options['send_email'] ) ) {
			$mail_result = $this->mailjet->send_story_email(
				array(
					'story_id' => $post_id,
					'audio_id' => $audio_id,
					'name'     => $name,
					'email'    => $email,
				)
			);
		}

		return array(
			'storyId'       => $post_id,
			'shareUrl'      => goodsleep_get_story_share_url( $post_id ),
			'audioUrl'      => wp_get_attachment_url( $audio_id ),
			'downloadUrl'   => wp_get_attachment_url( $audio_id ),
			'emailSent'     => ! empty( $options['send_email'] ) && ! is_wp_error( $mail_result ),
			'emailSuppressed' => empty( $options['send_email'] ),
			'postDate'      => get_post_field( 'post_date', $post_id ),
		);
	}

	/**
	 * Construye el input SSML para Speechify.
	 *
	 * @param string $story_text Texto principal.
	 * @param string $phrase_text Frase final.
	 * @param string $emotion Emocion aplicada.
	 * @return string
	 */
	protected function build_speechify_input( $story_text, $phrase_text, $emotion = 'cheerful' ) {
		$story_text  = trim( wp_strip_all_tags( (string) $story_text ) );
		$phrase_text = trim( wp_strip_all_tags( (string) $phrase_text ) );

		if ( '' === $phrase_text ) {
			return sprintf(
				'<speak><prosody rate="-8%%" pitch="-4%%">%1$s</prosody></speak>',
				htmlspecialchars( $story_text, ENT_XML1 | ENT_COMPAT, 'UTF-8' )
			);
		}

		return sprintf(
			'<speak><prosody rate="-8%%" pitch="-4%%">%1$s</prosody><break time="700ms" /><prosody rate="-6%%" pitch="-2%%" contour="(0%%,%3$s) (100%%,%3$s)">%2$s</prosody></speak>',
			htmlspecialchars( $story_text, ENT_XML1 | ENT_COMPAT, 'UTF-8' ),
			htmlspecialchars( $phrase_text, ENT_XML1 | ENT_COMPAT, 'UTF-8' ),
			$this->get_emotion_contour( $emotion )
		);
	}

	/**
	 * Renderiza la frase configurable manteniendo compatibilidad con el flujo publico.
	 *
	 * @param string $template Plantilla configurable.
	 * @param string $name Nombre recibido.
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

	/**
	 * Construye un titulo administrativo distintivo.
	 *
	 * @param string $name Nombre de la historia.
	 * @param string $email Correo relacionado.
	 * @param array<string,mixed> $options Opciones de origen.
	 * @return string
	 */
	protected function build_story_post_title( $name, $email, $options = array() ) {
		$email_user   = $email ? sanitize_text_field( (string) current( explode( '@', $email ) ) ) : '';
		$generated_at = ! empty( $options['post_date'] ) ? sanitize_text_field( (string) $options['post_date'] ) : current_time( 'Y-m-d H:i' );
		$source       = ! empty( $options['source'] ) && 'public_form' !== $options['source'] ? sanitize_text_field( strtoupper( (string) $options['source'] ) ) : '';

		return implode( ' | ', array_filter( array( trim( (string) $name ), $email_user ? '@' . $email_user : '', $generated_at, $source ) ) );
	}

	/**
	 * Convierte emociones a una curva simple de entonacion.
	 *
	 * @param string $emotion Emocion solicitada.
	 * @return string
	 */
	protected function get_emotion_contour( $emotion ) {
		$map = array(
			'cheerful'  => '+8%',
			'energetic' => '+10%',
			'warm'      => '+4%',
			'calm'      => '+1%',
			'relaxed'   => '0%',
			'sad'       => '-4%',
			'fearful'   => '-2%',
		);

		return isset( $map[ $emotion ] ) ? $map[ $emotion ] : '+6%';
	}

	/**
	 * Guarda el audio como adjunto.
	 *
	 * @param int $post_id ID del post.
	 * @param string $name Nombre base.
	 * @param string $audio_url URL remota.
	 * @param string $audio_data Base64.
	 * @param string $audio_format Formato.
	 * @param string $local_file Archivo temporal.
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
}
