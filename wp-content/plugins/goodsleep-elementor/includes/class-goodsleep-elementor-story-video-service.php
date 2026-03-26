<?php
/**
 * Orquesta la generacion de video de historias y el backfill.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Goodsleep_Elementor_Story_Video_Service {
	/**
	 * Cliente de video.
	 *
	 * @var Goodsleep_Elementor_OpenAI_Video_Client
	 */
	protected $provider_client;

	/**
	 * Procesador de video.
	 *
	 * @var Goodsleep_Elementor_Video_Processor
	 */
	protected $video_processor;

	/**
	 * Cliente de correo.
	 *
	 * @var Goodsleep_Elementor_Mailjet_Client
	 */
	protected $mailjet;

	/**
	 * Hook de cron.
	 *
	 * @var string
	 */
	protected $cron_hook = 'goodsleep_process_story_generation';

	/**
	 * Constructor.
	 *
	 * @param Goodsleep_Elementor_OpenAI_Video_Client $provider_client Cliente API.
	 * @param Goodsleep_Elementor_Video_Processor  $video_processor Procesador.
	 * @param Goodsleep_Elementor_Mailjet_Client   $mailjet Correo.
	 */
	public function __construct( Goodsleep_Elementor_OpenAI_Video_Client $provider_client, Goodsleep_Elementor_Video_Processor $video_processor, Goodsleep_Elementor_Mailjet_Client $mailjet ) {
		$this->provider_client = $provider_client;
		$this->video_processor = $video_processor;
		$this->mailjet         = $mailjet;

		add_action( $this->cron_hook, array( $this, 'process_story_event' ), 10, 1 );
	}

	/**
	 * Crea una historia nueva y dispara su tarea de video.
	 *
	 * @param array<string,mixed> $args Datos del formulario.
	 * @return array<string,mixed>|WP_Error
	 */
	public function create_generation( $args ) {
		$name        = goodsleep_sanitize_story_name( isset( $args['name'] ) ? $args['name'] : '' );
		$email       = isset( $args['email'] ) ? sanitize_email( $args['email'] ) : '';
		$text        = isset( $args['story_text'] ) ? sanitize_textarea_field( $args['story_text'] ) : '';
		$phrase      = isset( $args['phrase_template'] ) ? sanitize_text_field( $args['phrase_template'] ) : '';
		$track_id    = isset( $args['track_id'] ) ? sanitize_text_field( $args['track_id'] ) : '';
		$track_label = isset( $args['track_label'] ) ? sanitize_text_field( $args['track_label'] ) : '';
		$accepted    = ! empty( $args['accepted_terms'] );
		$track       = goodsleep_get_track_by_id( $track_id );

		if ( ! $accepted || '' === $name || '' === $email || ! is_email( $email ) || '' === $text || ! $track ) {
			return new WP_Error( 'goodsleep_invalid_submission', __( 'Faltan campos obligatorios del formulario.', 'goodsleep-elementor' ), array( 'status' => 400 ) );
		}

		$rendered_phrase = $this->render_phrase_template( $phrase, $name );
		$combined_text   = trim( $text . "\n" . $rendered_phrase );

		$post_id = wp_insert_post(
			array(
				'post_type'    => 'goodsleep_story',
				'post_status'  => 'draft',
				'post_title'   => $this->build_story_post_title( $name, $email ),
				'post_content' => $text,
				'post_excerpt' => wp_trim_words( $text, 30 ),
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$short_slug = goodsleep_normalize_slug( $name ) . '-' . $post_id;
		update_post_meta( $post_id, '_goodsleep_story_name', $name );
		update_post_meta( $post_id, '_goodsleep_story_email', $email );
		update_post_meta( $post_id, '_goodsleep_story_phrase', $rendered_phrase );
		update_post_meta( $post_id, '_goodsleep_story_text', $text );
		update_post_meta( $post_id, '_goodsleep_story_combined', $combined_text );
		update_post_meta( $post_id, '_goodsleep_story_track_id', $track_id );
		update_post_meta( $post_id, '_goodsleep_story_track_label', $track_label ? $track_label : ( isset( $track['label'] ) ? $track['label'] : '' ) );
		update_post_meta( $post_id, '_goodsleep_short_slug', $short_slug );
		update_post_meta( $post_id, '_goodsleep_vote_score', '0.00' );
		update_post_meta( $post_id, '_goodsleep_vote_total', 0 );
		update_post_meta( $post_id, '_goodsleep_vote_count', 0 );
		update_post_meta( $post_id, '_goodsleep_favorite_count', 0 );

		$queue_result = $this->queue_story_generation( $post_id, false );
		if ( is_wp_error( $queue_result ) ) {
			return $queue_result;
		}

		return $this->build_status_payload( $post_id );
	}

	/**
	 * Encola la generacion o backfill de una historia.
	 *
	 * @param int  $post_id   ID de la historia.
	 * @param bool $backfill  Si es backfill.
	 * @param bool $force     Si se fuerza reproceso.
	 * @return true|WP_Error
	 */
	public function queue_story_generation( $post_id, $backfill = false, $force = false ) {
		$post_id        = (int) $post_id;
		$combined       = goodsleep_get_story_combined_text( $post_id );
		$story_text     = (string) get_post_meta( $post_id, '_goodsleep_story_text', true );
		$closing_phrase = (string) get_post_meta( $post_id, '_goodsleep_story_phrase', true );
		$story_name     = (string) get_post_meta( $post_id, '_goodsleep_story_name', true );
		$track_id       = (string) get_post_meta( $post_id, '_goodsleep_story_track_id', true );
		$track          = goodsleep_get_track_by_id( $track_id );
		$current_type   = goodsleep_get_story_primary_media( $post_id );

		if ( ! $force && 'video' === $current_type['type'] ) {
			return new WP_Error( 'goodsleep_story_has_video', __( 'La historia ya tiene video generado.', 'goodsleep-elementor' ) );
		}

		if ( '' === trim( $combined ) ) {
			return new WP_Error( 'goodsleep_story_without_content', __( 'La historia no tiene contenido narrativo para generar video.', 'goodsleep-elementor' ) );
		}

		$primary_story_text = '' !== trim( $story_text ) ? $story_text : $combined;
		$clip_count         = goodsleep_should_use_two_video_clips( $primary_story_text ) ? 2 : 1;
		$story_segments     = goodsleep_split_story_for_video_clips( $primary_story_text, $clip_count );
		$story_segments     = array_values( array_filter( array_map( 'trim', $story_segments ) ) );
		$clip_count         = max( 1, count( $story_segments ) );
		$scene_count        = goodsleep_estimate_scene_count( $combined );
		$task_ids           = array();
		$task_payloads      = array();
		$clip_prompts       = array();
		$product_reference  = goodsleep_get_video_product_reference();

		foreach ( $story_segments as $clip_index => $story_segment ) {
			$clip_prompts[] = goodsleep_build_video_clip_prompt( $story_segment, $story_name, $closing_phrase, $clip_index, $clip_count );
		}

		$initial_task = $this->provider_client->create_video_task(
			array(
				'model'           => goodsleep_get_setting( 'openai_video_model', 'sora-2' ),
				'prompt'          => $clip_prompts[0],
				'duration'        => (int) goodsleep_get_setting( 'video_duration', 12 ),
				'size'            => $this->resolve_video_size(),
				'input_reference' => 1 === $clip_count ? $product_reference : array(),
			)
		);

		if ( is_wp_error( $initial_task ) ) {
			$status = $backfill ? 'failed_backfill' : 'failed';
			update_post_meta( $post_id, '_goodsleep_story_generation_status', $status );
			update_post_meta( $post_id, '_goodsleep_story_generation_error', $initial_task->get_error_message() );
			return $initial_task;
		}

		$task_id = $this->provider_client->extract_task_id( $initial_task );
		if ( '' === $task_id ) {
			return new WP_Error( 'goodsleep_sora_missing_task', __( 'Sora no devolvio un identificador de video valido.', 'goodsleep-elementor' ) );
		}

		$task_ids[]      = $task_id;
		$task_payloads[] = $initial_task;

		update_post_meta( $post_id, '_goodsleep_story_generation_provider', 'sora-2' );
		update_post_meta( $post_id, '_goodsleep_story_generation_status', $backfill ? 'pending_backfill' : 'processing' );
		update_post_meta( $post_id, '_goodsleep_story_task_id', ! empty( $task_ids[0] ) ? $task_ids[0] : '' );
		update_post_meta( $post_id, '_goodsleep_story_task_ids', wp_slash( wp_json_encode( $task_ids ) ) );
		update_post_meta( $post_id, '_goodsleep_story_task_payload', wp_slash( wp_json_encode( $task_payloads ) ) );
		update_post_meta( $post_id, '_goodsleep_story_clip_prompts', wp_slash( wp_json_encode( $clip_prompts ) ) );
		update_post_meta( $post_id, '_goodsleep_story_prompt', implode( "\n\n--- CLIP ---\n\n", $clip_prompts ) );
		update_post_meta( $post_id, '_goodsleep_story_clip_count', $clip_count );
		update_post_meta( $post_id, '_goodsleep_story_scene_count', $scene_count );
		update_post_meta( $post_id, '_goodsleep_story_generation_error', '' );

		if ( $track ) {
			update_post_meta( $post_id, '_goodsleep_story_track_id', (string) $track['id'] );
			update_post_meta( $post_id, '_goodsleep_story_track_label', (string) $track['label'] );
		}

		$this->schedule_processing( $post_id );

		return true;
	}

	/**
	 * Procesa un story id por cron.
	 *
	 * @param int $post_id ID.
	 * @return void
	 */
	public function process_story_event( $post_id ) {
		$this->process_story( (int) $post_id );
	}

	/**
	 * Procesa el estado remoto y finaliza la historia cuando haya video.
	 *
	 * @param int $post_id ID.
	 * @return array<string,mixed>|WP_Error
	 */
	public function process_story( $post_id ) {
		$post_id  = (int) $post_id;
		$task_ids = $this->get_story_task_ids( $post_id );
		$clip_count = max( 1, (int) get_post_meta( $post_id, '_goodsleep_story_clip_count', true ) );

		if ( empty( $task_ids ) ) {
			return new WP_Error( 'goodsleep_missing_task_id', __( 'La historia no tiene task id para procesar.', 'goodsleep-elementor' ) );
		}

		if ( $clip_count > 1 && count( $task_ids ) < $clip_count ) {
			return $this->process_pending_remix( $post_id, $task_ids );
		}

		$task_payloads  = array();
		$video_sources  = array();
		$is_processing  = false;

		foreach ( $task_ids as $task_id ) {
			$task = $this->provider_client->get_task( $task_id );
			if ( is_wp_error( $task ) ) {
				update_post_meta( $post_id, '_goodsleep_story_generation_error', $task->get_error_message() );
				$this->schedule_processing( $post_id );
				return $task;
			}

			$task_payloads[] = $task;
			$status          = $this->provider_client->extract_status( $task );

			if ( in_array( $status, array( 'queued', 'pending', 'running', 'processing', 'submitted' ), true ) ) {
				$is_processing = true;
				continue;
			}

			if ( in_array( $status, array( 'failed', 'error', 'canceled', 'cancelled' ), true ) ) {
				update_post_meta( $post_id, '_goodsleep_story_generation_status', 'failed' );
				update_post_meta( $post_id, '_goodsleep_story_generation_error', __( 'La tarea de Sora fallo durante la generacion.', 'goodsleep-elementor' ) );
				update_post_meta( $post_id, '_goodsleep_story_task_payload', wp_json_encode( $task_payloads ) );
				return new WP_Error( 'goodsleep_generation_failed', __( 'La tarea de Sora fallo durante la generacion.', 'goodsleep-elementor' ) );
			}

			$video_url  = $this->provider_client->extract_video_url( $task );
			$video_path = '';

			$downloaded_video = $this->provider_client->download_video_content( $task_id );
			if ( ! is_wp_error( $downloaded_video ) ) {
				$video_path = $downloaded_video;
			}

			if ( '' === $video_url && '' === $video_path ) {
				update_post_meta( $post_id, '_goodsleep_story_generation_status', 'failed' );
				update_post_meta( $post_id, '_goodsleep_story_generation_error', __( 'Sora no devolvio una URL de video utilizable.', 'goodsleep-elementor' ) );
				update_post_meta( $post_id, '_goodsleep_story_task_payload', wp_json_encode( $task_payloads ) );
				return new WP_Error( 'goodsleep_video_missing', __( 'Sora no devolvio una URL de video utilizable.', 'goodsleep-elementor' ) );
			}

			$video_sources[] = array(
				'video_url'  => $video_url,
				'video_path' => $video_path,
			);
		}

		update_post_meta( $post_id, '_goodsleep_story_task_payload', wp_json_encode( $task_payloads ) );

		if ( $is_processing ) {
			update_post_meta( $post_id, '_goodsleep_story_generation_status', 'processing' );
			$this->schedule_processing( $post_id );
			return $this->build_status_payload( $post_id );
		}

		$track_id  = (string) get_post_meta( $post_id, '_goodsleep_story_track_id', true );
		$track     = goodsleep_get_track_by_id( $track_id );
		$base_name = goodsleep_normalize_slug( (string) get_post_meta( $post_id, '_goodsleep_story_name', true ) ) . '-' . $post_id;
		$finalized = $this->video_processor->finalize_videos(
			$video_sources,
			$track ? $track : array(),
			$base_name
		);

		if ( is_wp_error( $finalized ) ) {
			update_post_meta( $post_id, '_goodsleep_story_generation_status', 'failed' );
			update_post_meta( $post_id, '_goodsleep_story_generation_error', $finalized->get_error_message() );
			return $finalized;
		}

		$video_id = $this->store_media_attachment( $post_id, (string) get_post_meta( $post_id, '_goodsleep_story_name', true ), $finalized['format'], $finalized['path'], 'video/mp4' );
		if ( is_wp_error( $video_id ) ) {
			return $video_id;
		}

		if ( ! empty( $finalized['path'] ) && file_exists( $finalized['path'] ) ) {
			@unlink( $finalized['path'] );
		}

		update_post_meta( $post_id, '_goodsleep_story_video_id', $video_id );
		update_post_meta( $post_id, '_goodsleep_story_video_url', wp_get_attachment_url( $video_id ) );
		update_post_meta( $post_id, '_goodsleep_story_generation_status', 'ready' );
		update_post_meta( $post_id, '_goodsleep_story_generation_error', '' );
		update_post_meta( $post_id, '_goodsleep_story_generation_completed_at', current_time( 'mysql' ) );

		if ( 'publish' !== get_post_status( $post_id ) ) {
			wp_update_post(
				array(
					'ID'          => $post_id,
					'post_status' => 'publish',
				)
			);
		}

		$email = (string) get_post_meta( $post_id, '_goodsleep_story_email', true );
		if ( $email ) {
			$this->mailjet->send_story_email(
				array(
					'story_id' => $post_id,
					'video_id' => $video_id,
					'name'     => (string) get_post_meta( $post_id, '_goodsleep_story_name', true ),
					'email'    => $email,
				)
			);
		}

		return $this->build_status_payload( $post_id );
	}

	/**
	 * Construye el payload publico de estado.
	 *
	 * @param int $post_id ID.
	 * @return array<string,mixed>
	 */
	public function build_status_payload( $post_id ) {
		$post_id = (int) $post_id;
		$media   = goodsleep_get_story_primary_media( $post_id );
		$clip_count = max( 1, (int) get_post_meta( $post_id, '_goodsleep_story_clip_count', true ) );
		$base_poll_attempts = (int) goodsleep_get_setting( 'video_poll_attempts', 24 );

		return array(
			'storyId'        => $post_id,
			'status'         => (string) get_post_meta( $post_id, '_goodsleep_story_generation_status', true ),
			'shareUrl'       => goodsleep_get_story_share_url( $post_id ),
			'videoUrl'       => 'video' === $media['type'] ? (string) $media['url'] : '',
			'downloadUrl'    => (string) $media['download_url'],
			'mediaType'      => (string) $media['type'],
			'isReady'        => ! empty( $media['is_ready'] ),
			'error'          => (string) get_post_meta( $post_id, '_goodsleep_story_generation_error', true ),
			'pollInterval'   => (int) goodsleep_get_setting( 'video_poll_interval', 5 ),
			'pollAttempts'   => $base_poll_attempts * max( 1, $clip_count * 3 ),
			'publicVideoOnly'=> goodsleep_is_video_public_only(),
		);
	}

	/**
	 * Encola procesamiento por cron.
	 *
	 * @param int $post_id ID.
	 * @return void
	 */
	protected function schedule_processing( $post_id ) {
		$post_id = (int) $post_id;

		if ( ! wp_next_scheduled( $this->cron_hook, array( $post_id ) ) ) {
			wp_schedule_single_event( time() + 30, $this->cron_hook, array( $post_id ) );
		}
	}

	/**
	 * Devuelve los task ids asociados a una historia.
	 *
	 * @param int $post_id ID de la historia.
	 * @return array<int,string>
	 */
	protected function get_story_task_ids( $post_id ) {
		$task_ids = json_decode( (string) get_post_meta( (int) $post_id, '_goodsleep_story_task_ids', true ), true );
		$task_ids = is_array( $task_ids ) ? array_values( array_filter( array_map( 'sanitize_text_field', $task_ids ) ) ) : array();

		if ( ! empty( $task_ids ) ) {
			return $task_ids;
		}

		$legacy_task_id = (string) get_post_meta( (int) $post_id, '_goodsleep_story_task_id', true );

		return '' !== $legacy_task_id ? array( sanitize_text_field( $legacy_task_id ) ) : array();
	}

	/**
	 * Procesa el primer clip y crea el remix cuando corresponda.
	 *
	 * @param int               $post_id  ID historia.
	 * @param array<int,string> $task_ids Tasks existentes.
	 * @return array<string,mixed>|WP_Error
	 */
	protected function process_pending_remix( $post_id, $task_ids ) {
		$first_task_id = ! empty( $task_ids[0] ) ? (string) $task_ids[0] : '';

		if ( '' === $first_task_id ) {
			return new WP_Error( 'goodsleep_missing_task_id', __( 'La historia no tiene task id para procesar.', 'goodsleep-elementor' ) );
		}

		$first_task = $this->provider_client->get_task( $first_task_id );
		if ( is_wp_error( $first_task ) ) {
			update_post_meta( $post_id, '_goodsleep_story_generation_error', $first_task->get_error_message() );
			$this->schedule_processing( $post_id );
			return $first_task;
		}

		$task_payloads = array( $first_task );
		$status        = $this->provider_client->extract_status( $first_task );

		if ( in_array( $status, array( 'queued', 'pending', 'running', 'processing', 'submitted' ), true ) ) {
			update_post_meta( $post_id, '_goodsleep_story_task_payload', wp_slash( wp_json_encode( $task_payloads ) ) );
			update_post_meta( $post_id, '_goodsleep_story_generation_status', 'processing' );
			$this->schedule_processing( $post_id );
			return $this->build_status_payload( $post_id );
		}

		if ( in_array( $status, array( 'failed', 'error', 'canceled', 'cancelled' ), true ) ) {
			update_post_meta( $post_id, '_goodsleep_story_generation_status', 'failed' );
			update_post_meta( $post_id, '_goodsleep_story_generation_error', __( 'La tarea inicial de Sora fallo durante la generacion.', 'goodsleep-elementor' ) );
			update_post_meta( $post_id, '_goodsleep_story_task_payload', wp_slash( wp_json_encode( $task_payloads ) ) );
			return new WP_Error( 'goodsleep_generation_failed', __( 'La tarea inicial de Sora fallo durante la generacion.', 'goodsleep-elementor' ) );
		}

		$clip_prompts = $this->get_story_clip_prompts( $post_id );
		$remix_prompt = ! empty( $clip_prompts[1] ) ? (string) $clip_prompts[1] : '';

		if ( '' === $remix_prompt ) {
			return new WP_Error( 'goodsleep_missing_remix_prompt', __( 'No existe un prompt configurado para el segundo clip.', 'goodsleep-elementor' ) );
		}

		$remix_task = $this->provider_client->remix_video_task(
			$first_task_id,
			array(
				'model'  => goodsleep_get_setting( 'openai_video_model', 'sora-2' ),
				'prompt' => $remix_prompt,
			)
		);

		if ( is_wp_error( $remix_task ) ) {
			update_post_meta( $post_id, '_goodsleep_story_generation_status', 'failed' );
			update_post_meta( $post_id, '_goodsleep_story_generation_error', $remix_task->get_error_message() );
			update_post_meta( $post_id, '_goodsleep_story_task_payload', wp_slash( wp_json_encode( $task_payloads ) ) );
			return $remix_task;
		}

		$remix_task_id = $this->provider_client->extract_task_id( $remix_task );
		if ( '' === $remix_task_id ) {
			return new WP_Error( 'goodsleep_sora_missing_task', __( 'Sora no devolvio un identificador valido para el remix.', 'goodsleep-elementor' ) );
		}

		$task_ids[]      = $remix_task_id;
		$task_payloads[] = $remix_task;

		update_post_meta( $post_id, '_goodsleep_story_task_id', $first_task_id );
		update_post_meta( $post_id, '_goodsleep_story_task_ids', wp_slash( wp_json_encode( $task_ids ) ) );
		update_post_meta( $post_id, '_goodsleep_story_task_payload', wp_slash( wp_json_encode( $task_payloads ) ) );
		update_post_meta( $post_id, '_goodsleep_story_generation_status', 'processing' );
		update_post_meta( $post_id, '_goodsleep_story_generation_error', '' );
		$this->schedule_processing( $post_id );

		return $this->build_status_payload( $post_id );
	}

	/**
	 * Devuelve los prompts configurados para cada clip.
	 *
	 * @param int $post_id ID historia.
	 * @return array<int,string>
	 */
	protected function get_story_clip_prompts( $post_id ) {
		$clip_prompts = json_decode( (string) get_post_meta( (int) $post_id, '_goodsleep_story_clip_prompts', true ), true );
		$clip_prompts = is_array( $clip_prompts ) ? array_values( array_filter( array_map( 'sanitize_text_field', $clip_prompts ) ) ) : array();

		return $clip_prompts;
	}

	/**
	 * Guarda el archivo final como adjunto.
	 *
	 * @param int    $post_id   ID post.
	 * @param string $name      Nombre base.
	 * @param string $format    Extension.
	 * @param string $local_file Archivo local.
	 * @param string $mime_type Mime.
	 * @return int|WP_Error
	 */
	protected function store_media_attachment( $post_id, $name, $format, $local_file, $mime_type ) {
		$upload = wp_upload_dir();

		if ( ! empty( $upload['error'] ) ) {
			return new WP_Error( 'goodsleep_upload_error', $upload['error'] );
		}

		$filename = goodsleep_normalize_slug( $name ) . '-' . $post_id . '.' . sanitize_key( $format );
		$content  = $local_file && file_exists( $local_file ) ? file_get_contents( $local_file ) : '';

		if ( ! $content ) {
			return new WP_Error( 'goodsleep_empty_media', __( 'No se pudo guardar el archivo generado.', 'goodsleep-elementor' ) );
		}

		$written = wp_upload_bits( $filename, null, $content );
		if ( ! empty( $written['error'] ) ) {
			return new WP_Error( 'goodsleep_media_write_failed', $written['error'] );
		}

		$attachment_id = wp_insert_attachment(
			array(
				'post_mime_type' => $mime_type,
				'post_title'     => $name,
				'post_status'    => 'inherit',
			),
			$written['file'],
			$post_id
		);

		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$metadata = wp_generate_attachment_metadata( $attachment_id, $written['file'] );
		wp_update_attachment_metadata( $attachment_id, $metadata );

		return $attachment_id;
	}

	/**
	 * Renderiza la frase final.
	 *
	 * @param string $template Plantilla.
	 * @param string $name Nombre.
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
	 * Construye un titulo administrativo.
	 *
	 * @param string $name  Nombre.
	 * @param string $email Correo.
	 * @return string
	 */
	protected function build_story_post_title( $name, $email ) {
		$email_user = $email ? sanitize_text_field( (string) current( explode( '@', $email ) ) ) : '';
		$generated  = current_time( 'Y-m-d H:i' );

		return implode( ' | ', array_filter( array( trim( (string) $name ), $email_user ? '@' . $email_user : '', $generated ) ) );
	}

	/**
	 * Resuelve el size esperado por OpenAI Sora a partir de la configuracion actual.
	 *
	 * @return string
	 */
	protected function resolve_video_size() {
		$aspect_ratio = (string) goodsleep_get_setting( 'video_aspect_ratio', '9:16' );

		if ( '16:9' === $aspect_ratio ) {
			return '1280x720';
		}

		return '720x1280';
	}

	/**
	 * Busca la historia asociada a un task de OpenAI.
	 *
	 * @param string $task_id ID remoto.
	 * @return int
	 */
	public function find_story_id_by_task_id( $task_id ) {
		$task_id = sanitize_text_field( (string) $task_id );

		if ( '' === $task_id ) {
			return 0;
		}

		$query = new WP_Query(
			array(
				'post_type'      => 'goodsleep_story',
				'post_status'    => array( 'draft', 'publish', 'private' ),
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array(
					'relation' => 'OR',
					array(
						'key'   => '_goodsleep_story_task_id',
						'value' => $task_id,
					),
					array(
						'key'     => '_goodsleep_story_task_ids',
						'value'   => $task_id,
						'compare' => 'LIKE',
					),
				),
			)
		);

		return ! empty( $query->posts[0] ) ? (int) $query->posts[0] : 0;
	}

	/**
	 * Marca una historia como fallida desde un evento externo.
	 *
	 * @param int    $post_id  ID historia.
	 * @param string $message  Mensaje de error.
	 * @return void
	 */
	public function mark_story_failed( $post_id, $message ) {
		$post_id = (int) $post_id;

		if ( $post_id <= 0 ) {
			return;
		}

		update_post_meta( $post_id, '_goodsleep_story_generation_status', 'failed' );
		update_post_meta( $post_id, '_goodsleep_story_generation_error', sanitize_text_field( (string) $message ) );
	}
}
