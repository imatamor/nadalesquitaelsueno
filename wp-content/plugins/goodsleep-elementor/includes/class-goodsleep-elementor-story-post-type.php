<?php
/**
 * Registro del CPT de historias Goodsleep.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Goodsleep_Elementor_Story_Post_Type {
	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register' ) );
		add_action( 'add_meta_boxes', array( $this, 'register_meta_boxes' ) );
		add_action( 'before_delete_post', array( $this, 'delete_attached_story_media' ) );
	}

	/**
	 * Registra CPT y metadatos.
	 *
	 * @return void
	 */
	public function register() {
		register_post_type(
			'goodsleep_story',
			array(
				'labels' => array(
					'name'          => __( 'Historias Goodsleep', 'goodsleep-elementor' ),
					'singular_name' => __( 'Historia Goodsleep', 'goodsleep-elementor' ),
				),
				'public'             => true,
				'show_in_rest'       => true,
				'has_archive'        => false,
				'menu_icon'          => 'dashicons-video-alt3',
				'supports'           => array( 'title', 'editor', 'excerpt' ),
				'rewrite'            => array( 'slug' => 'historia' ),
				'publicly_queryable' => true,
			)
		);

		$meta_schema = array(
			'_goodsleep_story_name'                  => 'string',
			'_goodsleep_story_email'                 => 'string',
			'_goodsleep_story_phrase'                => 'string',
			'_goodsleep_story_phrase_emotion'        => 'string',
			'_goodsleep_story_text'                  => 'string',
			'_goodsleep_story_combined'              => 'string',
			'_goodsleep_story_voice_id'              => 'string',
			'_goodsleep_story_voice_label'           => 'string',
			'_goodsleep_story_track_id'              => 'string',
			'_goodsleep_story_track_label'           => 'string',
			'_goodsleep_story_audio_id'              => 'integer',
			'_goodsleep_story_video_id'              => 'integer',
			'_goodsleep_story_video_url'             => 'string',
			'_goodsleep_story_generation_provider'   => 'string',
			'_goodsleep_story_generation_status'     => 'string',
			'_goodsleep_story_task_id'               => 'string',
			'_goodsleep_story_task_payload'          => 'string',
			'_goodsleep_story_prompt'                => 'string',
			'_goodsleep_story_scene_count'           => 'integer',
			'_goodsleep_story_generation_error'      => 'string',
			'_goodsleep_story_generation_completed_at' => 'string',
			'_goodsleep_story_email_lock'            => 'string',
			'_goodsleep_story_email_sent_at'         => 'string',
			'_goodsleep_short_slug'                  => 'string',
			'_goodsleep_vote_score'                  => 'string',
			'_goodsleep_vote_total'                  => 'integer',
			'_goodsleep_vote_count'                  => 'integer',
			'_goodsleep_favorite_count'              => 'integer',
		);

		foreach ( $meta_schema as $key => $type ) {
			register_post_meta(
				'goodsleep_story',
				$key,
				array(
					'type'              => $type,
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => $this->get_meta_sanitizer( $type ),
					'auth_callback'     => static function () {
						return current_user_can( 'edit_posts' );
					},
				)
			);
		}
	}

	/**
	 * Registra metaboxes del CPT.
	 *
	 * @return void
	 */
	public function register_meta_boxes() {
		add_meta_box(
			'goodsleep-story-details',
			__( 'Datos de la historia', 'goodsleep-elementor' ),
			array( $this, 'render_story_details_meta_box' ),
			'goodsleep_story',
			'side',
			'high'
		);
	}

	/**
	 * Renderiza una ficha lateral con los datos principales.
	 *
	 * @param WP_Post $post Post actual.
	 * @return void
	 */
	public function render_story_details_meta_box( $post ) {
		$post_id     = $post instanceof WP_Post ? (int) $post->ID : 0;
		$name        = (string) get_post_meta( $post_id, '_goodsleep_story_name', true );
		$email       = (string) get_post_meta( $post_id, '_goodsleep_story_email', true );
		$voice_label = (string) get_post_meta( $post_id, '_goodsleep_story_voice_label', true );
		$track_label = (string) get_post_meta( $post_id, '_goodsleep_story_track_label', true );
		$audio_url   = goodsleep_get_story_audio_url( $post_id );
		$video_url   = goodsleep_get_story_video_url( $post_id );
		$share_url   = goodsleep_get_story_share_url( $post_id );
		$status      = (string) get_post_meta( $post_id, '_goodsleep_story_generation_status', true );
		$task_id     = (string) get_post_meta( $post_id, '_goodsleep_story_task_id', true );
		$error       = (string) get_post_meta( $post_id, '_goodsleep_story_generation_error', true );

		echo '<div class="goodsleep-story-meta-box">';
		$this->render_meta_row( __( 'Nombre', 'goodsleep-elementor' ), esc_html( $name ) );
		$this->render_meta_row( __( 'Correo', 'goodsleep-elementor' ), $email ? '<a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>' : '' );
		$this->render_meta_row( __( 'Voz legacy', 'goodsleep-elementor' ), esc_html( $voice_label ) );
		$this->render_meta_row( __( 'Musica', 'goodsleep-elementor' ), esc_html( $track_label ) );
		$this->render_meta_row( __( 'Proveedor de generacion', 'goodsleep-elementor' ), esc_html( (string) get_post_meta( $post_id, '_goodsleep_story_generation_provider', true ) ) );
		$this->render_meta_row( __( 'Estado de generacion', 'goodsleep-elementor' ), esc_html( $status ) );
		$this->render_meta_row( __( 'Task ID', 'goodsleep-elementor' ), esc_html( $task_id ) );
		$this->render_meta_row( __( 'Error', 'goodsleep-elementor' ), esc_html( $error ) );

		if ( $video_url ) {
			$this->render_meta_row(
				__( 'Video', 'goodsleep-elementor' ),
				'<a href="' . esc_url( $video_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Descargar video', 'goodsleep-elementor' ) . '</a>'
			);
			echo '<p><video controls preload="metadata" style="width:100%" src="' . esc_url( $video_url ) . '"></video></p>';
		}

		if ( $audio_url ) {
			$this->render_meta_row(
				__( 'Audio legacy', 'goodsleep-elementor' ),
				'<a href="' . esc_url( $audio_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Descargar audio', 'goodsleep-elementor' ) . '</a>'
			);
			echo '<p><audio controls preload="metadata" style="width:100%"><source src="' . esc_url( $audio_url ) . '"></audio></p>';
		}

		if ( $share_url ) {
			$this->render_meta_row(
				__( 'URL publica', 'goodsleep-elementor' ),
				'<a href="' . esc_url( $share_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Abrir historia', 'goodsleep-elementor' ) . '</a>'
			);
		}

		echo '</div>';
	}

	/**
	 * Renderiza una fila simple de metadato.
	 *
	 * @param string $label Etiqueta visible.
	 * @param string $value Valor ya escapado.
	 * @return void
	 */
	protected function render_meta_row( $label, $value ) {
		if ( '' === trim( wp_strip_all_tags( (string) $value ) ) ) {
			return;
		}

		echo '<p><strong>' . esc_html( $label ) . ':</strong><br>' . wp_kses_post( $value ) . '</p>';
	}

	/**
	 * Borra los medios adjuntos al eliminar una historia.
	 *
	 * @param int $post_id ID del post eliminado.
	 * @return void
	 */
	public function delete_attached_story_media( $post_id ) {
		$post_id = (int) $post_id;

		if ( 'goodsleep_story' !== get_post_type( $post_id ) ) {
			return;
		}

		foreach ( array( goodsleep_get_story_audio_id( $post_id ), goodsleep_get_story_video_id( $post_id ) ) as $attachment_id ) {
			$attachment_id = (int) $attachment_id;
			if ( ! $attachment_id ) {
				continue;
			}

			$attachment = get_post( $attachment_id );
			if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
				continue;
			}

			if ( (int) $attachment->post_parent !== $post_id ) {
				continue;
			}

			wp_delete_attachment( $attachment_id, true );
		}
	}

	/**
	 * Devuelve el sanitizador correcto segun el tipo de meta.
	 *
	 * @param string $type Tipo registrado.
	 * @return callable
	 */
	protected function get_meta_sanitizer( $type ) {
		if ( 'integer' === $type ) {
			return 'absint';
		}

		if ( 'number' === $type ) {
			return 'floatval';
		}

		return 'sanitize_text_field';
	}
}
