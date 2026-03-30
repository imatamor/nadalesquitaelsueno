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
			'dashicons-video-alt3',
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
		add_settings_section( 'goodsleep_video_section', __( 'Configuracion de video', 'goodsleep-elementor' ), '__return_false', 'goodsleep-elementor' );
		add_settings_section( 'goodsleep_catalog_section', __( 'Tracks y salida publica', 'goodsleep-elementor' ), '__return_false', 'goodsleep-elementor' );

		add_settings_field( 'video_provider', __( 'Proveedor de video', 'goodsleep-elementor' ), array( $this, 'render_select_field' ), 'goodsleep-elementor', 'goodsleep_api_section', array( 'key' => 'video_provider', 'options' => array( 'kling' => __( 'Kling', 'goodsleep-elementor' ), 'openai' => __( 'OpenAI Sora', 'goodsleep-elementor' ) ) ) );
		$this->add_text_field( 'openai_api_key', __( 'OpenAI API Key', 'goodsleep-elementor' ) );
		$this->add_text_field( 'openai_base_url', __( 'OpenAI Base URL', 'goodsleep-elementor' ), 'url' );
		$this->add_text_field( 'openai_video_model', __( 'Modelo de video', 'goodsleep-elementor' ) );
		$this->add_text_field( 'openai_webhook_secret', __( 'OpenAI Webhook Secret', 'goodsleep-elementor' ) );
		$this->add_text_field( 'openai_video_submit_path', __( 'Ruta de creacion de video', 'goodsleep-elementor' ) );
		$this->add_text_field( 'openai_video_remix_path', __( 'Ruta de remix de video', 'goodsleep-elementor' ) );
		$this->add_text_field( 'openai_video_status_path', __( 'Ruta de consulta de video', 'goodsleep-elementor' ) );
		$this->add_text_field( 'kling_access_key', __( 'Kling Access Key', 'goodsleep-elementor' ) );
		$this->add_text_field( 'kling_secret_key', __( 'Kling Secret Key', 'goodsleep-elementor' ) );
		$this->add_text_field( 'kling_base_url', __( 'Kling Base URL', 'goodsleep-elementor' ), 'url' );
		$this->add_text_field( 'kling_video_model', __( 'Kling modelo de video', 'goodsleep-elementor' ) );
		add_settings_field( 'kling_video_mode', __( 'Kling modo', 'goodsleep-elementor' ), array( $this, 'render_select_field' ), 'goodsleep-elementor', 'goodsleep_api_section', array( 'key' => 'kling_video_mode', 'options' => array( 'std' => __( 'Standard', 'goodsleep-elementor' ), 'pro' => __( 'Professional', 'goodsleep-elementor' ) ) ) );
		add_settings_field( 'kling_video_sound', __( 'Kling audio integrado', 'goodsleep-elementor' ), array( $this, 'render_select_field' ), 'goodsleep-elementor', 'goodsleep_api_section', array( 'key' => 'kling_video_sound', 'options' => array( 'on' => __( 'Activado', 'goodsleep-elementor' ), 'off' => __( 'Desactivado', 'goodsleep-elementor' ) ) ) );
		$this->add_textarea_field( 'kling_negative_prompt', __( 'Kling negative prompt', 'goodsleep-elementor' ), 3, 'goodsleep_api_section' );
		$this->add_text_field( 'kling_webhook_secret', __( 'Kling callback secret', 'goodsleep-elementor' ) );
		$this->add_text_field( 'kling_text_submit_path', __( 'Kling ruta text-to-video', 'goodsleep-elementor' ) );
		$this->add_text_field( 'kling_text_status_path', __( 'Kling ruta estado text-to-video', 'goodsleep-elementor' ) );
		$this->add_text_field( 'kling_extend_submit_path', __( 'Kling ruta video extension', 'goodsleep-elementor' ) );
		$this->add_text_field( 'kling_extend_status_path', __( 'Kling ruta estado extension', 'goodsleep-elementor' ) );
		$this->add_text_field( 'mailjet_api_key', __( 'Mailjet API Key', 'goodsleep-elementor' ) );
		$this->add_text_field( 'mailjet_secret_key', __( 'Mailjet Secret Key', 'goodsleep-elementor' ) );
		$this->add_text_field( 'mailjet_from_email', __( 'Mailjet From Email', 'goodsleep-elementor' ), 'email' );
		$this->add_text_field( 'mailjet_from_name', __( 'Mailjet From Name', 'goodsleep-elementor' ) );
		$this->add_text_field( 'mailjet_reply_to_email', __( 'Reply-To Email', 'goodsleep-elementor' ), 'email' );
		$this->add_text_field( 'mailjet_reply_to_name', __( 'Reply-To Name', 'goodsleep-elementor' ) );
		$this->add_textarea_field( 'mailjet_monitor_bcc', __( 'Correos de monitoreo (CCO)', 'goodsleep-elementor' ), 4, 'goodsleep_api_section', __( 'Ingresa uno o varios correos separados por coma o salto de linea.', 'goodsleep-elementor' ) );
		$this->add_text_field( 'terms_url', __( 'URL de terminos global', 'goodsleep-elementor' ), 'url' );
		$this->add_text_field( 'terms_text', __( 'Texto de terminos global', 'goodsleep-elementor' ) );
		$this->add_textarea_field( 'whatsapp_share_text', __( 'Texto para compartir por WhatsApp', 'goodsleep-elementor' ), 3 );

		$this->add_text_field( 'video_resolution', __( 'Resolucion por defecto', 'goodsleep-elementor' ), 'text', 'goodsleep_video_section' );
		$this->add_text_field( 'video_aspect_ratio', __( 'Aspect ratio por defecto', 'goodsleep-elementor' ), 'text', 'goodsleep_video_section' );
		$this->add_text_field( 'video_duration', __( 'Duracion objetivo (segundos)', 'goodsleep-elementor' ), 'number', 'goodsleep_video_section' );
		$this->add_text_field( 'video_poll_interval', __( 'Polling frontend (segundos)', 'goodsleep-elementor' ), 'number', 'goodsleep_video_section' );
		$this->add_text_field( 'video_poll_attempts', __( 'Intentos de polling', 'goodsleep-elementor' ), 'number', 'goodsleep_video_section' );
		$this->add_textarea_field( 'video_prompt_style', __( 'Prompt base de video', 'goodsleep-elementor' ), 6, 'goodsleep_video_section' );
		$this->add_textarea_field( 'video_clip_1_prompt_addition', __( 'Prompt adicional clip 1', 'goodsleep-elementor' ), 4, 'goodsleep_video_section' );
		$this->add_textarea_field( 'video_clip_2_prompt_addition', __( 'Prompt adicional clip 2', 'goodsleep-elementor' ), 4, 'goodsleep_video_section' );
		add_settings_field( 'video_product_reference', __( 'Imagen de referencia del producto', 'goodsleep-elementor' ), array( $this, 'render_media_reference_field' ), 'goodsleep-elementor', 'goodsleep_video_section', array( 'key' => 'video_product_reference' ) );
		add_settings_field( 'video_music_enabled', __( 'Anadir musica de fondo', 'goodsleep-elementor' ), array( $this, 'render_checkbox_field' ), 'goodsleep-elementor', 'goodsleep_video_section', array( 'key' => 'video_music_enabled', 'label' => __( 'Mezclar track musical con el video final cuando exista track seleccionado.', 'goodsleep-elementor' ) ) );
		add_settings_field( 'video_public_only', __( 'Ocultar audio legacy en frontend', 'goodsleep-elementor' ), array( $this, 'render_checkbox_field' ), 'goodsleep-elementor', 'goodsleep_catalog_section', array( 'key' => 'video_public_only', 'label' => __( 'Mostrar solo video en frontend para dejar el audio unicamente como respaldo tecnico.', 'goodsleep-elementor' ) ) );

		add_settings_field( 'tracks_catalog', __( 'Tracks manuales', 'goodsleep-elementor' ), array( $this, 'render_tracks_manager' ), 'goodsleep-elementor', 'goodsleep_catalog_section' );
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

		$sanitized['video_provider']         = isset( $input['video_provider'] ) && in_array( $input['video_provider'], array( 'openai', 'kling' ), true ) ? sanitize_key( $input['video_provider'] ) : 'openai';
		$sanitized['openai_api_key']         = isset( $input['openai_api_key'] ) ? sanitize_text_field( $input['openai_api_key'] ) : '';
		$sanitized['openai_base_url']        = isset( $input['openai_base_url'] ) ? esc_url_raw( $input['openai_base_url'] ) : '';
		$sanitized['openai_video_model']     = isset( $input['openai_video_model'] ) ? sanitize_text_field( $input['openai_video_model'] ) : 'sora-2';
		$sanitized['openai_webhook_secret']  = isset( $input['openai_webhook_secret'] ) ? sanitize_text_field( $input['openai_webhook_secret'] ) : '';
		$sanitized['openai_video_submit_path'] = isset( $input['openai_video_submit_path'] ) ? sanitize_text_field( $input['openai_video_submit_path'] ) : '/videos';
		$sanitized['openai_video_remix_path'] = isset( $input['openai_video_remix_path'] ) ? sanitize_text_field( $input['openai_video_remix_path'] ) : '/videos/%s/remix';
		$sanitized['openai_video_status_path'] = isset( $input['openai_video_status_path'] ) ? sanitize_text_field( $input['openai_video_status_path'] ) : '/videos/%s';
		$sanitized['kling_access_key']       = isset( $input['kling_access_key'] ) ? sanitize_text_field( $input['kling_access_key'] ) : '';
		$sanitized['kling_secret_key']       = isset( $input['kling_secret_key'] ) ? sanitize_text_field( $input['kling_secret_key'] ) : '';
		$sanitized['kling_base_url']         = isset( $input['kling_base_url'] ) ? esc_url_raw( $input['kling_base_url'] ) : 'https://api-singapore.klingai.com';
		$sanitized['kling_video_model']      = isset( $input['kling_video_model'] ) ? sanitize_text_field( $input['kling_video_model'] ) : 'kling-v3';
		$sanitized['kling_video_mode']       = isset( $input['kling_video_mode'] ) && in_array( $input['kling_video_mode'], array( 'std', 'pro' ), true ) ? sanitize_key( $input['kling_video_mode'] ) : 'std';
		$sanitized['kling_video_sound']      = isset( $input['kling_video_sound'] ) && in_array( $input['kling_video_sound'], array( 'on', 'off' ), true ) ? sanitize_key( $input['kling_video_sound'] ) : 'on';
		$sanitized['kling_negative_prompt']  = isset( $input['kling_negative_prompt'] ) ? sanitize_textarea_field( $input['kling_negative_prompt'] ) : '';
		$sanitized['kling_webhook_secret']   = isset( $input['kling_webhook_secret'] ) ? sanitize_text_field( $input['kling_webhook_secret'] ) : '';
		$sanitized['kling_text_submit_path'] = isset( $input['kling_text_submit_path'] ) ? sanitize_text_field( $input['kling_text_submit_path'] ) : '/v1/videos/text2video';
		$sanitized['kling_text_status_path'] = isset( $input['kling_text_status_path'] ) ? sanitize_text_field( $input['kling_text_status_path'] ) : '/v1/videos/text2video/%s';
		$sanitized['kling_extend_submit_path'] = isset( $input['kling_extend_submit_path'] ) ? sanitize_text_field( $input['kling_extend_submit_path'] ) : '/v1/videos/extend';
		$sanitized['kling_extend_status_path'] = isset( $input['kling_extend_status_path'] ) ? sanitize_text_field( $input['kling_extend_status_path'] ) : '/v1/videos/%s';
		$sanitized['video_resolution']       = isset( $input['video_resolution'] ) ? sanitize_text_field( $input['video_resolution'] ) : '720p';
		$sanitized['video_aspect_ratio']     = isset( $input['video_aspect_ratio'] ) ? sanitize_text_field( $input['video_aspect_ratio'] ) : '9:16';
		$sanitized['video_duration']         = isset( $input['video_duration'] ) ? max( 4, min( 15, absint( $input['video_duration'] ) ) ) : 12;
		$sanitized['video_poll_interval']    = isset( $input['video_poll_interval'] ) ? max( 2, absint( $input['video_poll_interval'] ) ) : 5;
		$sanitized['video_poll_attempts']    = isset( $input['video_poll_attempts'] ) ? max( 1, absint( $input['video_poll_attempts'] ) ) : 24;
		$sanitized['video_prompt_style']     = isset( $input['video_prompt_style'] ) ? sanitize_textarea_field( $input['video_prompt_style'] ) : '';
		$sanitized['video_clip_1_prompt_addition'] = isset( $input['video_clip_1_prompt_addition'] ) ? sanitize_textarea_field( $input['video_clip_1_prompt_addition'] ) : '';
		$sanitized['video_clip_2_prompt_addition'] = isset( $input['video_clip_2_prompt_addition'] ) ? sanitize_textarea_field( $input['video_clip_2_prompt_addition'] ) : '';
		$sanitized['video_product_reference'] = $this->sanitize_media_reference( isset( $input['video_product_reference'] ) ? $input['video_product_reference'] : array() );
		$sanitized['video_music_enabled']    = ! empty( $input['video_music_enabled'] ) ? 1 : 0;
		$sanitized['video_public_only']      = ! empty( $input['video_public_only'] ) ? 1 : 0;
		$sanitized['mailjet_api_key']        = isset( $input['mailjet_api_key'] ) ? sanitize_text_field( $input['mailjet_api_key'] ) : '';
		$sanitized['mailjet_secret_key']     = isset( $input['mailjet_secret_key'] ) ? sanitize_text_field( $input['mailjet_secret_key'] ) : '';
		$sanitized['mailjet_from_email']     = isset( $input['mailjet_from_email'] ) ? goodsleep_normalize_email( $input['mailjet_from_email'] ) : '';
		$sanitized['mailjet_from_name']      = isset( $input['mailjet_from_name'] ) ? sanitize_text_field( $input['mailjet_from_name'] ) : '';
		$sanitized['mailjet_reply_to_email'] = isset( $input['mailjet_reply_to_email'] ) ? goodsleep_normalize_email( $input['mailjet_reply_to_email'] ) : '';
		$sanitized['mailjet_reply_to_name']  = isset( $input['mailjet_reply_to_name'] ) ? sanitize_text_field( $input['mailjet_reply_to_name'] ) : '';
		$sanitized['mailjet_monitor_bcc']    = isset( $input['mailjet_monitor_bcc'] ) ? $this->sanitize_email_list_textarea( $input['mailjet_monitor_bcc'] ) : '';
		$sanitized['terms_url']              = isset( $input['terms_url'] ) ? esc_url_raw( $input['terms_url'] ) : '';
		$sanitized['terms_text']             = isset( $input['terms_text'] ) ? sanitize_text_field( $input['terms_text'] ) : '';
		$sanitized['whatsapp_share_text']    = isset( $input['whatsapp_share_text'] ) ? sanitize_textarea_field( $input['whatsapp_share_text'] ) : '';
		$sanitized['track_whitelist']        = isset( $input['track_whitelist'] ) ? array_values( array_filter( array_map( 'sanitize_text_field', (array) $input['track_whitelist'] ) ) ) : array();

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
	 * Sanitiza una referencia de medios del admin.
	 *
	 * @param array<string,mixed> $input Datos del campo.
	 * @return array<string,mixed>
	 */
	protected function sanitize_media_reference( $input ) {
		$input = is_array( $input ) ? $input : array();

		return array(
			'attachment_id' => ! empty( $input['attachment_id'] ) ? absint( $input['attachment_id'] ) : 0,
			'url'           => ! empty( $input['url'] ) ? esc_url_raw( (string) $input['url'] ) : '',
		);
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
		<input class="regular-text" type="<?php echo esc_attr( $type ); ?>" name="goodsleep_elementor_settings[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( (string) $value ); ?>">
		<?php
	}

	/**
	 * Renderiza un select simple.
	 *
	 * @param array<string,mixed> $args Args.
	 * @return void
	 */
	public function render_select_field( $args ) {
		$key     = $args['key'];
		$options = ! empty( $args['options'] ) && is_array( $args['options'] ) ? $args['options'] : array();
		$value   = goodsleep_get_setting( $key, '' );
		?>
		<select name="goodsleep_elementor_settings[<?php echo esc_attr( $key ); ?>]">
			<?php foreach ( $options as $option_value => $option_label ) : ?>
				<option value="<?php echo esc_attr( (string) $option_value ); ?>" <?php selected( (string) $value, (string) $option_value ); ?>><?php echo esc_html( (string) $option_label ); ?></option>
			<?php endforeach; ?>
		</select>
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
	 * Renderiza checkbox.
	 *
	 * @param array<string,string> $args Configuracion del campo.
	 * @return void
	 */
	public function render_checkbox_field( $args ) {
		$key   = $args['key'];
		$value = goodsleep_get_setting( $key, 0 );
		$label = ! empty( $args['label'] ) ? (string) $args['label'] : '';
		?>
		<label>
			<input type="checkbox" name="goodsleep_elementor_settings[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( ! empty( $value ) ); ?>>
			<span><?php echo esc_html( $label ); ?></span>
		</label>
		<?php
	}

	/**
	 * Renderiza un selector de imagen de referencia.
	 *
	 * @param array<string,string> $args Args.
	 * @return void
	 */
	public function render_media_reference_field( $args ) {
		$key   = $args['key'];
		$value = goodsleep_get_setting( $key, array() );
		$value = is_array( $value ) ? $value : array();
		$url   = ! empty( $value['url'] ) ? (string) $value['url'] : '';
		$id    = ! empty( $value['attachment_id'] ) ? (int) $value['attachment_id'] : 0;
		?>
		<div class="goodsleep-admin-media-field" data-goodsleep-media-field>
			<div class="goodsleep-admin-media-field__controls">
				<input type="hidden" name="goodsleep_elementor_settings[<?php echo esc_attr( $key ); ?>][attachment_id]" value="<?php echo esc_attr( $id ); ?>" data-media-id>
				<input type="text" class="regular-text goodsleep-admin-media-field__url" name="goodsleep_elementor_settings[<?php echo esc_attr( $key ); ?>][url]" value="<?php echo esc_attr( $url ); ?>" readonly data-media-url>
				<button type="button" class="button" data-select-media><?php esc_html_e( 'Seleccionar imagen', 'goodsleep-elementor' ); ?></button>
				<button type="button" class="button-link-delete" data-clear-media <?php disabled( '' === $url ); ?>><?php esc_html_e( 'Quitar', 'goodsleep-elementor' ); ?></button>
			</div>
			<p class="description"><?php esc_html_e( 'Usa una imagen real del producto desde la galeria de medios para enviarla como referencia visual al clip final.', 'goodsleep-elementor' ); ?></p>
		</div>
		<?php
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
			__( 'Anade tracks manuales en el gestor de arriba para habilitarlos aqui.', 'goodsleep-elementor' )
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
		echo '<p><button type="button" class="button button-secondary" data-add-track>' . esc_html__( 'Anadir track', 'goodsleep-elementor' ) . '</button></p>';
		echo '<p class="description">' . esc_html__( 'Anade manualmente cada track con nombre y archivo de audio desde la galeria de medios.', 'goodsleep-elementor' ) . '</p>';
		echo '</div>';
	}

	/**
	 * Renderiza una picklist con filtro.
	 *
	 * @param string            $field_name Nombre del campo.
	 * @param array<int,array>  $items      Items.
	 * @param array<int,string> $selected   Seleccionados.
	 * @param string            $empty_text Texto vacio.
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

			echo '<label class="goodsleep-admin-picklist__item" data-search-text="' . esc_attr( trim( $label ) ) . '">';
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
			<p><?php esc_html_e( 'Configura OpenAI Sora, correo, video y tracks manuales de la campana Goodsleep.', 'goodsleep-elementor' ); ?></p>
			<p><strong><?php esc_html_e( 'Backfill de historias legacy:', 'goodsleep-elementor' ); ?></strong> <?php esc_html_e( 'queda preparado por codigo, pero no se ejecuta automaticamente. Debe lanzarse manualmente por WP-CLI cuando el flujo nuevo de video ya este validado.', 'goodsleep-elementor' ); ?></p>
			<form method="post" action="options.php">
				<?php settings_fields( 'goodsleep_elementor_settings_group' ); ?>
				<?php do_settings_sections( 'goodsleep-elementor' ); ?>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
