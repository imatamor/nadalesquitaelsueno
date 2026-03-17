<?php
/**
 * Ajustes del plugin Goodsleep Elementor.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Goodsleep_Elementor_Settings {
	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Registra menu del plugin.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_menu_page(
			__( 'Goodsleep Elementor', 'goodsleep-elementor' ),
			__( 'Goodsleep Elementor', 'goodsleep-elementor' ),
			'manage_options',
			'goodsleep-elementor',
			array( $this, 'render_page' ),
			'dashicons-format-audio',
			62
		);
	}

	/**
	 * Registra settings y secciones.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'goodsleep_elementor_settings_group',
			'goodsleep_elementor_settings',
			array( $this, 'sanitize_settings' )
		);

		add_settings_section( 'goodsleep_api_section', __( 'Credenciales e integraciones', 'goodsleep-elementor' ), '__return_false', 'goodsleep-elementor' );
		add_settings_section( 'goodsleep_catalog_section', __( 'Catalogos de voces y tracks', 'goodsleep-elementor' ), '__return_false', 'goodsleep-elementor' );

		$this->add_text_field( 'speechify_api_key', __( 'Speechify API Key', 'goodsleep-elementor' ) );
		$this->add_text_field( 'speechify_base_url', __( 'Speechify Base URL', 'goodsleep-elementor' ) );
		$this->add_text_field( 'speechify_audio_path', __( 'Speechify Audio Path', 'goodsleep-elementor' ) );
		$this->add_text_field( 'speechify_voices_path', __( 'Speechify Voices Path', 'goodsleep-elementor' ) );
		$this->add_text_field( 'mailjet_api_key', __( 'Mailjet API Key', 'goodsleep-elementor' ) );
		$this->add_text_field( 'mailjet_secret_key', __( 'Mailjet Secret Key', 'goodsleep-elementor' ) );
		$this->add_text_field( 'mailjet_from_email', __( 'Mailjet From Email', 'goodsleep-elementor' ), 'email' );
		$this->add_text_field( 'mailjet_from_name', __( 'Mailjet From Name', 'goodsleep-elementor' ) );
		$this->add_text_field( 'mailjet_reply_to_email', __( 'Reply-To Email', 'goodsleep-elementor' ), 'email' );
		$this->add_text_field( 'mailjet_reply_to_name', __( 'Reply-To Name', 'goodsleep-elementor' ) );
		$this->add_text_field( 'terms_url', __( 'Terms URL', 'goodsleep-elementor' ), 'url' );
		$this->add_text_field( 'terms_text', __( 'Terms Text', 'goodsleep-elementor' ) );
		$this->add_textarea_field( 'whatsapp_share_text', __( 'WhatsApp Share Text', 'goodsleep-elementor' ), 3 );
		$this->add_textarea_field(
			'tracks_catalog_json',
			__( 'Catálogo manual de tracks (JSON)', 'goodsleep-elementor' ),
			8,
			'goodsleep_catalog_section',
			__( 'Aquí pegas manualmente un arreglo JSON con los tracks disponibles. Cada track debe tener id y label. Ejemplo: [{"id":"track-01","label":"Piano suave"}].', 'goodsleep-elementor' )
		);

		add_settings_field( 'voice_whitelist', __( 'Allowed Voices', 'goodsleep-elementor' ), array( $this, 'render_voice_whitelist' ), 'goodsleep-elementor', 'goodsleep_catalog_section' );
		add_settings_field( 'track_whitelist', __( 'Allowed Tracks', 'goodsleep-elementor' ), array( $this, 'render_track_whitelist' ), 'goodsleep-elementor', 'goodsleep_catalog_section' );
	}

	/**
	 * Helper para campos de texto.
	 *
	 * @param string $key     Clave.
	 * @param string $label   Etiqueta.
	 * @param string $type    Tipo html.
	 * @param string $section Seccion.
	 * @return void
	 */
	protected function add_text_field( $key, $label, $type = 'text', $section = 'goodsleep_api_section' ) {
		add_settings_field( $key, $label, array( $this, 'render_text_field' ), 'goodsleep-elementor', $section, compact( 'key', 'type' ) );
	}

	/**
	 * Helper para textareas.
	 *
	 * @param string $key     Clave.
	 * @param string $label   Etiqueta.
	 * @param int    $rows    Filas.
	 * @param string $section Seccion.
	 * @return void
	 */
	protected function add_textarea_field( $key, $label, $rows = 6, $section = 'goodsleep_api_section', $description = '' ) {
		add_settings_field( $key, $label, array( $this, 'render_textarea_field' ), 'goodsleep-elementor', $section, compact( 'key', 'rows', 'description' ) );
	}

	/**
	 * Sanitiza settings.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>
	 */
	public function sanitize_settings( $input ) {
		$sanitized = goodsleep_get_settings();
		$input     = is_array( $input ) ? $input : array();

		$sanitized['speechify_api_key']      = isset( $input['speechify_api_key'] ) ? sanitize_text_field( $input['speechify_api_key'] ) : '';
		$sanitized['speechify_base_url']     = isset( $input['speechify_base_url'] ) ? esc_url_raw( $input['speechify_base_url'] ) : '';
		$sanitized['speechify_audio_path']   = isset( $input['speechify_audio_path'] ) ? sanitize_text_field( $input['speechify_audio_path'] ) : '';
		$sanitized['speechify_voices_path']  = isset( $input['speechify_voices_path'] ) ? sanitize_text_field( $input['speechify_voices_path'] ) : '';
		$sanitized['mailjet_api_key']        = isset( $input['mailjet_api_key'] ) ? sanitize_text_field( $input['mailjet_api_key'] ) : '';
		$sanitized['mailjet_secret_key']     = isset( $input['mailjet_secret_key'] ) ? sanitize_text_field( $input['mailjet_secret_key'] ) : '';
		$sanitized['mailjet_from_email']     = isset( $input['mailjet_from_email'] ) ? sanitize_email( $input['mailjet_from_email'] ) : '';
		$sanitized['mailjet_from_name']      = isset( $input['mailjet_from_name'] ) ? sanitize_text_field( $input['mailjet_from_name'] ) : '';
		$sanitized['mailjet_reply_to_email'] = isset( $input['mailjet_reply_to_email'] ) ? sanitize_email( $input['mailjet_reply_to_email'] ) : '';
		$sanitized['mailjet_reply_to_name']  = isset( $input['mailjet_reply_to_name'] ) ? sanitize_text_field( $input['mailjet_reply_to_name'] ) : '';
		$sanitized['terms_url']              = isset( $input['terms_url'] ) ? esc_url_raw( $input['terms_url'] ) : '';
		$sanitized['terms_text']             = isset( $input['terms_text'] ) ? sanitize_text_field( $input['terms_text'] ) : '';
		$sanitized['whatsapp_share_text']    = isset( $input['whatsapp_share_text'] ) ? sanitize_textarea_field( $input['whatsapp_share_text'] ) : '';
		$sanitized['voice_whitelist']        = isset( $input['voice_whitelist'] ) ? array_values( array_filter( array_map( 'sanitize_text_field', (array) $input['voice_whitelist'] ) ) ) : array();
		$sanitized['track_whitelist']        = isset( $input['track_whitelist'] ) ? array_values( array_filter( array_map( 'sanitize_text_field', (array) $input['track_whitelist'] ) ) ) : array();

		$tracks_catalog = array();
		if ( ! empty( $input['tracks_catalog_json'] ) ) {
			$decoded = json_decode( wp_unslash( $input['tracks_catalog_json'] ), true );

			if ( is_array( $decoded ) ) {
				$tracks_catalog = array_values(
					array_filter(
						array_map(
							static function ( $track ) {
								if ( ! is_array( $track ) || empty( $track['id'] ) || empty( $track['label'] ) ) {
									return null;
								}

								return array(
									'id'    => sanitize_text_field( $track['id'] ),
									'label' => sanitize_text_field( $track['label'] ),
								);
							},
							$decoded
						)
					)
				);
			}
		}

		$sanitized['tracks_catalog'] = $tracks_catalog;

		return $sanitized;
	}

	/**
	 * Renderiza text field.
	 *
	 * @param array<string,string> $args Args.
	 * @return void
	 */
	public function render_text_field( $args ) {
		$key   = $args['key'];
		$type  = isset( $args['type'] ) ? $args['type'] : 'text';
		$value = goodsleep_get_setting( $key, '' );
		?>
		<input class="regular-text" type="<?php echo esc_attr( $type ); ?>" name="goodsleep_elementor_settings[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $value ); ?>">
		<?php
	}

	/**
	 * Renderiza textarea.
	 *
	 * @param array<string,mixed> $args Args.
	 * @return void
	 */
	public function render_textarea_field( $args ) {
		$key   = $args['key'];
		$rows  = isset( $args['rows'] ) ? (int) $args['rows'] : 6;
		$value = 'tracks_catalog_json' === $key ? wp_json_encode( goodsleep_get_cached_tracks(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) : goodsleep_get_setting( $key, '' );
		?>
		<textarea class="large-text code" rows="<?php echo esc_attr( $rows ); ?>" name="goodsleep_elementor_settings[<?php echo esc_attr( $key ); ?>]"><?php echo esc_textarea( (string) $value ); ?></textarea>
		<?php if ( ! empty( $args['description'] ) ) : ?>
			<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Renderiza whitelist de voces.
	 *
	 * @return void
	 */
	public function render_voice_whitelist() {
		echo '<p><button type="button" class="button button-secondary" data-goodsleep-sync-voices>' . esc_html__( 'Sincronizar voces desde Speechify', 'goodsleep-elementor' ) . '</button> <span class="description" data-goodsleep-sync-feedback></span></p>';

		$this->render_multicheck_list(
			'voice_whitelist',
			goodsleep_get_cached_voices(),
			(array) goodsleep_get_setting( 'voice_whitelist', array() ),
			__( 'No hay voces cacheadas todavia. Configura Speechify y sincronizalas por API.', 'goodsleep-elementor' )
		);
	}

	/**
	 * Renderiza whitelist de tracks.
	 *
	 * @return void
	 */
	public function render_track_whitelist() {
		$this->render_multicheck_list(
			'track_whitelist',
			goodsleep_get_cached_tracks(),
			(array) goodsleep_get_setting( 'track_whitelist', array() ),
			__( 'Carga tracks desde el JSON del catalogo.', 'goodsleep-elementor' )
		);
	}

	/**
	 * Renderiza una picklist con filtro.
	 *
	 * @param string              $field_name Nombre del campo.
	 * @param array<int,array>    $items      Items.
	 * @param array<int,string>   $selected   Seleccionados.
	 * @param string              $empty_text Texto vacio.
	 * @return void
	 */
	protected function render_multicheck_list( $field_name, $items, $selected, $empty_text ) {
		if ( empty( $items ) ) {
			echo '<p class="description">' . esc_html( $empty_text ) . '</p>';
			return;
		}

		echo '<div class="goodsleep-admin-picklist">';
		echo '<input type="search" class="goodsleep-admin-picklist__search" placeholder="' . esc_attr__( 'Buscar...', 'goodsleep-elementor' ) . '">';
		echo '<div class="goodsleep-admin-picklist__items">';

		foreach ( $items as $item ) {
			$id    = isset( $item['id'] ) ? $item['id'] : '';
			$label = isset( $item['label'] ) ? $item['label'] : $id;

			if ( ! $id ) {
				continue;
			}

			echo '<label class="goodsleep-admin-picklist__item">';
			echo '<input type="checkbox" name="goodsleep_elementor_settings[' . esc_attr( $field_name ) . '][]" value="' . esc_attr( $id ) . '" ' . checked( in_array( $id, $selected, true ), true, false ) . '>';
			echo '<span>' . esc_html( $label ) . '</span>';
			echo '</label>';
		}

		echo '</div></div>';
	}

	/**
	 * Renderiza pagina de ajustes.
	 *
	 * @return void
	 */
	public function render_page() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Goodsleep Elementor', 'goodsleep-elementor' ); ?></h1>
			<p><?php esc_html_e( 'Configura integraciones y catalogos de la campana Goodsleep.', 'goodsleep-elementor' ); ?></p>
			<form method="post" action="options.php">
				<?php settings_fields( 'goodsleep_elementor_settings_group' ); ?>
				<?php do_settings_sections( 'goodsleep-elementor' ); ?>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
