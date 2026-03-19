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
		add_settings_section( 'goodsleep_catalog_section', __( 'Catálogos de voces y tracks', 'goodsleep-elementor' ), '__return_false', 'goodsleep-elementor' );

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
		$this->add_textarea_field( 'mailjet_monitor_bcc', __( 'Correos de monitoreo (CCO)', 'goodsleep-elementor' ), 4, 'goodsleep_api_section', __( 'Ingresa uno o varios correos separados por coma o salto de linea.', 'goodsleep-elementor' ) );
		$this->add_text_field( 'terms_url', __( 'URL de términos global', 'goodsleep-elementor' ), 'url' );
		$this->add_text_field( 'terms_text', __( 'Texto de términos global', 'goodsleep-elementor' ) );
		$this->add_textarea_field( 'whatsapp_share_text', __( 'Texto para compartir por WhatsApp', 'goodsleep-elementor' ), 3 );

		add_settings_field( 'tracks_catalog', __( 'Tracks manuales', 'goodsleep-elementor' ), array( $this, 'render_tracks_manager' ), 'goodsleep-elementor', 'goodsleep_catalog_section' );
		add_settings_field( 'voice_whitelist', __( 'Voces habilitadas', 'goodsleep-elementor' ), array( $this, 'render_voice_whitelist' ), 'goodsleep-elementor', 'goodsleep_catalog_section' );
		add_settings_field( 'track_whitelist', __( 'Tracks habilitados', 'goodsleep-elementor' ), array( $this, 'render_track_whitelist' ), 'goodsleep-elementor', 'goodsleep_catalog_section' );
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
	 * @param string $key         Clave.
	 * @param string $label       Etiqueta.
	 * @param int    $rows        Filas.
	 * @param string $section     Seccion.
	 * @param string $description Descripcion.
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
		$sanitized['mailjet_from_email']     = isset( $input['mailjet_from_email'] ) ? goodsleep_normalize_email( $input['mailjet_from_email'] ) : '';
		$sanitized['mailjet_from_name']      = isset( $input['mailjet_from_name'] ) ? sanitize_text_field( $input['mailjet_from_name'] ) : '';
		$sanitized['mailjet_reply_to_email'] = isset( $input['mailjet_reply_to_email'] ) ? goodsleep_normalize_email( $input['mailjet_reply_to_email'] ) : '';
		$sanitized['mailjet_reply_to_name']  = isset( $input['mailjet_reply_to_name'] ) ? sanitize_text_field( $input['mailjet_reply_to_name'] ) : '';
		$sanitized['mailjet_monitor_bcc']    = isset( $input['mailjet_monitor_bcc'] ) ? $this->sanitize_email_list_textarea( $input['mailjet_monitor_bcc'] ) : '';
		$sanitized['terms_url']              = isset( $input['terms_url'] ) ? esc_url_raw( $input['terms_url'] ) : '';
		$sanitized['terms_text']             = isset( $input['terms_text'] ) ? sanitize_text_field( $input['terms_text'] ) : '';
		$sanitized['whatsapp_share_text']      = isset( $input['whatsapp_share_text'] ) ? sanitize_textarea_field( $input['whatsapp_share_text'] ) : '';
		$sanitized['voice_language_whitelist'] = isset( $input['voice_language_whitelist'] ) ? array_values( array_filter( array_map( 'sanitize_text_field', (array) $input['voice_language_whitelist'] ) ) ) : array();
		$sanitized['voice_whitelist']          = isset( $input['voice_whitelist'] ) ? array_values( array_filter( array_map( 'sanitize_text_field', (array) $input['voice_whitelist'] ) ) ) : array();
		$sanitized['track_whitelist']          = isset( $input['track_whitelist'] ) ? array_values( array_filter( array_map( 'sanitize_text_field', (array) $input['track_whitelist'] ) ) ) : array();

		$tracks_catalog = array();
		if ( ! empty( $input['tracks_catalog'] ) && is_array( $input['tracks_catalog'] ) ) {
			foreach ( $input['tracks_catalog'] as $track ) {
				if ( ! is_array( $track ) ) {
					continue;
				}

				$label         = isset( $track['label'] ) ? sanitize_text_field( $track['label'] ) : '';
				$url           = isset( $track['url'] ) ? esc_url_raw( $track['url'] ) : '';
				$attachment_id = isset( $track['attachment_id'] ) ? absint( $track['attachment_id'] ) : 0;
				$id            = isset( $track['id'] ) ? sanitize_text_field( $track['id'] ) : '';

				if ( '' === $label || '' === $url ) {
					continue;
				}

				if ( '' === $id ) {
					$id = $attachment_id ? 'track-' . $attachment_id : 'track-' . count( $tracks_catalog );
				}

				$tracks_catalog[] = array(
					'id'            => $id,
					'label'         => $label,
					'url'           => $url,
					'attachment_id' => $attachment_id,
				);
			}
		}

		$sanitized['tracks_catalog'] = $tracks_catalog;

		return $sanitized;
	}

	/**
	 * Sanitiza una lista de correos separada por coma o salto de linea.
	 *
	 * @param string $value Texto ingresado.
	 * @return string
	 */
	protected function sanitize_email_list_textarea( $value ) {
		$value  = (string) $value;
		$emails = preg_split( '/[\r\n,;]+/', $value );
		$emails = array_filter( array_map( 'goodsleep_normalize_email', (array) $emails ) );
		$emails = array_values( array_unique( $emails ) );

		return implode( "\n", $emails );
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
		$value = goodsleep_get_setting( $key, '' );
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
			__( 'No hay voces cacheadas todavía. Configura Speechify y sincronízalas por API.', 'goodsleep-elementor' ),
			array(
				'show_language_filter' => true,
				'language_field_name'  => 'voice_language_whitelist',
				'selected_languages'   => (array) goodsleep_get_setting( 'voice_language_whitelist', array() ),
			)
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
			__( 'Añade tracks manuales en el gestor de arriba para habilitarlos aquí.', 'goodsleep-elementor' )
		);
	}

	/**
	 * Renderiza el gestor manual de tracks.
	 *
	 * @return void
	 */
	public function render_tracks_manager() {
		$tracks = goodsleep_get_cached_tracks();

		echo '<div class="goodsleep-admin-track-manager" data-goodsleep-track-manager>';
		echo '<div class="goodsleep-admin-track-manager__list" data-track-list>';

		foreach ( $tracks as $index => $track ) {
			$label         = isset( $track['label'] ) ? $track['label'] : '';
			$url           = isset( $track['url'] ) ? $track['url'] : '';
			$id            = isset( $track['id'] ) ? $track['id'] : '';
			$attachment_id = isset( $track['attachment_id'] ) ? (int) $track['attachment_id'] : 0;

			echo '<div class="goodsleep-admin-track-row">';
			echo '<div class="goodsleep-admin-track-row__field">';
			echo '<label>' . esc_html__( 'Nombre del track', 'goodsleep-elementor' ) . '</label>';
			echo '<input type="text" class="regular-text" name="goodsleep_elementor_settings[tracks_catalog][' . esc_attr( $index ) . '][label]" value="' . esc_attr( $label ) . '">';
			echo '<input type="hidden" name="goodsleep_elementor_settings[tracks_catalog][' . esc_attr( $index ) . '][id]" value="' . esc_attr( $id ) . '">';
			echo '<input type="hidden" name="goodsleep_elementor_settings[tracks_catalog][' . esc_attr( $index ) . '][attachment_id]" value="' . esc_attr( $attachment_id ) . '">';
			echo '</div>';
			echo '<div class="goodsleep-admin-track-row__field">';
			echo '<label>' . esc_html__( 'Audio', 'goodsleep-elementor' ) . '</label>';
			echo '<div class="goodsleep-admin-track-row__audio">';
			echo '<input type="text" class="regular-text goodsleep-admin-track-row__audio-url" name="goodsleep_elementor_settings[tracks_catalog][' . esc_attr( $index ) . '][url]" value="' . esc_attr( $url ) . '" readonly>';
			echo '<button type="button" class="button" data-select-track>' . esc_html__( 'Seleccionar audio', 'goodsleep-elementor' ) . '</button>';
			echo '</div>';
			echo '</div>';
			echo '<div class="goodsleep-admin-track-row__remove">';
			echo '<button type="button" class="button-link-delete" data-remove-track>' . esc_html__( 'Eliminar', 'goodsleep-elementor' ) . '</button>';
			echo '</div>';
			echo '</div>';
		}

		echo '</div>';
		echo '<p><button type="button" class="button button-secondary" data-add-track>' . esc_html__( 'Añadir track', 'goodsleep-elementor' ) . '</button></p>';
		echo '<p class="description">' . esc_html__( 'Añade manualmente cada track con nombre y archivo de audio desde la galería de medios.', 'goodsleep-elementor' ) . '</p>';
		echo '</div>';
	}

	/**
	 * Renderiza una picklist con filtro.
	 *
	 * @param string              $field_name Nombre del campo.
	 * @param array<int,array>    $items      Items.
	 * @param array<int,string>   $selected   Seleccionados.
	 * @param string              $empty_text Texto vacio.
	 * @param array<string,mixed> $args       Configuracion de UI.
	 * @return void
	 */
	protected function render_multicheck_list( $field_name, $items, $selected, $empty_text, $args = array() ) {
		if ( empty( $items ) ) {
			echo '<p class="description">' . esc_html( $empty_text ) . '</p>';
			return;
		}

		$show_language_filter = ! empty( $args['show_language_filter'] );
		$languages            = $show_language_filter ? $this->get_picklist_languages( $items ) : array();
		$language_field_name  = ! empty( $args['language_field_name'] ) ? (string) $args['language_field_name'] : '';
		$selected_languages   = ! empty( $args['selected_languages'] ) ? array_map( 'strtolower', (array) $args['selected_languages'] ) : array();

		echo '<div class="goodsleep-admin-picklist">';
		echo '<input type="search" class="goodsleep-admin-picklist__search" placeholder="' . esc_attr__( 'Buscar...', 'goodsleep-elementor' ) . '">';

		if ( $show_language_filter && ! empty( $languages ) ) {
			echo '<div class="goodsleep-admin-picklist__filters">';
			echo '<label class="goodsleep-admin-picklist__filter-label" for="goodsleep-language-filter-' . esc_attr( $field_name ) . '">' . esc_html__( 'Idiomas disponibles', 'goodsleep-elementor' ) . '</label>';
			echo '<div class="goodsleep-admin-picklist__actions">';
			echo '<button type="button" class="button button-secondary" data-picklist-select-all>' . esc_html__( 'Seleccionar todo', 'goodsleep-elementor' ) . '</button>';
			echo '<button type="button" class="button-link" data-picklist-clear>' . esc_html__( 'Limpiar selección', 'goodsleep-elementor' ) . '</button>';
			echo '</div>';
			echo '<select id="goodsleep-language-filter-' . esc_attr( $field_name ) . '" class="goodsleep-admin-picklist__languages" data-picklist-languages multiple size="' . esc_attr( min( count( $languages ), 8 ) ) . '"';

			if ( '' !== $language_field_name ) {
				echo ' name="goodsleep_elementor_settings[' . esc_attr( $language_field_name ) . '][]"';
			}

			echo '>';

			foreach ( $languages as $language ) {
				echo '<option value="' . esc_attr( $language['value'] ) . '" ' . selected( in_array( $language['value'], $selected_languages, true ), true, false ) . '>' . esc_html( $language['label'] ) . '</option>';
			}

			echo '</select>';
			echo '<p class="description">' . esc_html__( 'Si no marcas voces específicas, el frontend usará todas las voces de los idiomas seleccionados. Si no seleccionas idiomas ni voces, se mostrarán todas las voces.', 'goodsleep-elementor' ) . '</p>';
			echo '</div>';
		}

		echo '<div class="goodsleep-admin-picklist__items">';

		foreach ( $items as $item ) {
			$id             = isset( $item['id'] ) ? $item['id'] : '';
			$label          = isset( $item['label'] ) ? $item['label'] : $id;
			$language_value = isset( $item['language'] ) && '' !== $item['language'] ? (string) $item['language'] : ( isset( $item['locale'] ) ? (string) $item['locale'] : '' );
			$language_label = isset( $item['language_label'] ) && '' !== $item['language_label'] ? (string) $item['language_label'] : $language_value;

			if ( ! $id ) {
				continue;
			}

			echo '<label class="goodsleep-admin-picklist__item" data-language="' . esc_attr( strtolower( $language_value ) ) . '" data-search-text="' . esc_attr( trim( $label . ' ' . $language_label . ' ' . $language_value ) ) . '">';
			echo '<input type="checkbox" name="goodsleep_elementor_settings[' . esc_attr( $field_name ) . '][]" value="' . esc_attr( $id ) . '" ' . checked( in_array( $id, $selected, true ), true, false ) . '>';
			echo '<span>' . esc_html( $label ) . '</span>';

			if ( $show_language_filter && '' !== $language_label ) {
				echo '<small class="goodsleep-admin-picklist__meta">' . esc_html( $language_label ) . '</small>';
			}

			echo '</label>';
		}

		echo '</div></div>';
	}

	/**
	 * Devuelve los idiomas disponibles de una picklist.
	 *
	 * @param array<int,array<string,mixed>> $items Items del catalogo.
	 * @return array<int,array<string,string>>
	 */
	protected function get_picklist_languages( $items ) {
		$languages = array();

		foreach ( $items as $item ) {
			$value = '';
			$label = '';

			if ( ! empty( $item['language'] ) && is_string( $item['language'] ) ) {
				$value = strtolower( sanitize_text_field( $item['language'] ) );
			} elseif ( ! empty( $item['locale'] ) && is_string( $item['locale'] ) ) {
				$value = strtolower( sanitize_text_field( $item['locale'] ) );
			}

			if ( ! empty( $item['language_label'] ) && is_string( $item['language_label'] ) ) {
				$label = sanitize_text_field( $item['language_label'] );
			} elseif ( '' !== $value ) {
				$label = strtoupper( $value );
			}

			if ( '' === $value || '' === $label ) {
				continue;
			}

			$languages[ $value ] = array(
				'value' => $value,
				'label' => $label,
			);
		}

		uasort(
			$languages,
			static function ( $left, $right ) {
				return strcasecmp( $left['label'], $right['label'] );
			}
		);

		return array_values( $languages );
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
			<p><?php esc_html_e( 'Configura integraciones, voces y tracks manuales de la campaña Goodsleep.', 'goodsleep-elementor' ); ?></p>
			<form method="post" action="options.php">
				<?php settings_fields( 'goodsleep_elementor_settings_group' ); ?>
				<?php do_settings_sections( 'goodsleep-elementor' ); ?>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
