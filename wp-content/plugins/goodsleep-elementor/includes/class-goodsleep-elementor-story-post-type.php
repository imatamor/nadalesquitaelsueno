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
}
