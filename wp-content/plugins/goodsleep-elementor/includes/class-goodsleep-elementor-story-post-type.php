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
				'menu_icon'          => 'dashicons-format-audio',
				'supports'           => array( 'title', 'editor', 'excerpt' ),
				'rewrite'            => array( 'slug' => 'historia' ),
				'publicly_queryable' => true,
			)
		);

		$meta_schema = array(
			'_goodsleep_story_name'         => 'string',
			'_goodsleep_story_email'        => 'string',
			'_goodsleep_story_phrase'       => 'string',
			'_goodsleep_story_phrase_emotion' => 'string',
			'_goodsleep_story_text'         => 'string',
			'_goodsleep_story_combined'     => 'string',
			'_goodsleep_story_voice_id'     => 'string',
			'_goodsleep_story_voice_label'  => 'string',
			'_goodsleep_story_track_id'     => 'string',
			'_goodsleep_story_track_label'  => 'string',
			'_goodsleep_story_audio_id'     => 'integer',
			'_goodsleep_short_slug'         => 'string',
			'_goodsleep_vote_score'         => 'integer',
			'_goodsleep_favorite_count'     => 'integer',
		);

		foreach ( $meta_schema as $key => $type ) {
			register_post_meta(
				'goodsleep_story',
				$key,
				array(
					'type'              => $type,
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => 'integer' === $type ? 'absint' : 'sanitize_text_field',
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
	 * Renderiza una ficha lateral con los datos del formulario y el audio.
	 *
	 * @param WP_Post $post Post actual.
	 * @return void
	 */
	public function render_story_details_meta_box( $post ) {
		$post_id     = $post instanceof WP_Post ? (int) $post->ID : 0;
		$name        = (string) get_post_meta( $post_id, '_goodsleep_story_name', true );
		$email       = (string) get_post_meta( $post_id, '_goodsleep_story_email', true );
		$phrase      = (string) get_post_meta( $post_id, '_goodsleep_story_phrase', true );
		$voice_label = (string) get_post_meta( $post_id, '_goodsleep_story_voice_label', true );
		$track_label = (string) get_post_meta( $post_id, '_goodsleep_story_track_label', true );
		$audio_id    = (int) get_post_meta( $post_id, '_goodsleep_story_audio_id', true );
		$audio_url   = $audio_id ? wp_get_attachment_url( $audio_id ) : '';
		$share_url   = goodsleep_get_story_share_url( $post_id );

		echo '<div class="goodsleep-story-meta-box">';
		$this->render_meta_row( __( 'Nombre', 'goodsleep-elementor' ), esc_html( $name ) );
		$this->render_meta_row( __( 'Correo', 'goodsleep-elementor' ), $email ? '<a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>' : '' );
		$this->render_meta_row( __( 'Voz', 'goodsleep-elementor' ), esc_html( $voice_label ) );
		$this->render_meta_row( __( 'Música', 'goodsleep-elementor' ), esc_html( $track_label ) );
		$this->render_meta_row( __( 'Frase final', 'goodsleep-elementor' ), esc_html( $phrase ) );

		if ( $audio_url ) {
			$this->render_meta_row(
				__( 'Audio', 'goodsleep-elementor' ),
				'<a href="' . esc_url( $audio_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Descargar audio', 'goodsleep-elementor' ) . '</a>'
			);

			echo '<p><audio controls preload="metadata" style="width:100%"><source src="' . esc_url( $audio_url ) . '"></audio></p>';
		}

		if ( $share_url ) {
			$this->render_meta_row(
				__( 'URL pública', 'goodsleep-elementor' ),
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
}
