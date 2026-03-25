<?php
/**
 * Cliente Mailjet.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Goodsleep_Elementor_Mailjet_Client {
	/**
	 * Envia el correo transaccional de la historia.
	 *
	 * @param array<string,mixed> $story_data Datos base.
	 * @return true|WP_Error
	 */
	public function send_story_email( $story_data ) {
		$api_key    = goodsleep_get_setting( 'mailjet_api_key', '' );
		$secret_key = goodsleep_get_setting( 'mailjet_secret_key', '' );
		$from_email = goodsleep_normalize_email( goodsleep_get_setting( 'mailjet_from_email', '' ) );
		$from_name  = $this->get_sender_name();

		if ( '' === $api_key || '' === $secret_key || '' === $from_email ) {
			return new WP_Error( 'goodsleep_missing_mailjet_config', __( 'Mailjet no esta configurado.', 'goodsleep-elementor' ) );
		}

		$story_id   = isset( $story_data['story_id'] ) ? absint( $story_data['story_id'] ) : 0;
		$share_url  = goodsleep_get_story_share_url( $story_id );
		$media      = goodsleep_get_story_primary_media( $story_id );
		$media_url  = ! empty( $story_data['video_id'] ) ? wp_get_attachment_url( (int) $story_data['video_id'] ) : (string) $media['url'];
		$media_type = ! empty( $media['type'] ) ? (string) $media['type'] : 'video';
		$html_body  = $this->build_email_template( $story_data, $share_url, $media_url, $media_type );
		$bcc_list   = goodsleep_parse_email_list( goodsleep_get_setting( 'mailjet_monitor_bcc', '' ) );

		$body = array(
			'Messages' => array(
				array(
					'From' => array(
						'Email' => $from_email,
						'Name'  => $from_name,
					),
					'To'   => array(
						array(
							'Email' => $story_data['email'],
						),
					),
					'Subject'  => sprintf( __( 'La historia de %s, esta lista en video', 'goodsleep-elementor' ), $story_data['name'] ),
					'HTMLPart' => $html_body,
				),
			),
		);

		if ( ! empty( $bcc_list ) ) {
			$body['Messages'][0]['Bcc'] = array_map(
				static function ( $email ) {
					return array(
						'Email' => $email,
					);
				},
				$bcc_list
			);
		}

		$reply_to_email = goodsleep_normalize_email( goodsleep_get_setting( 'mailjet_reply_to_email', '' ) );
		if ( $reply_to_email ) {
			$body['Messages'][0]['ReplyTo'] = array(
				'Email' => $reply_to_email,
				'Name'  => $this->get_reply_to_name( $from_name ),
			);
		}

		$response = wp_remote_post(
			'https://api.mailjet.com/v3.1/send',
			array(
				'timeout' => 30,
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( $api_key . ':' . $secret_key ),
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'goodsleep_mailjet_failed', __( 'Mailjet no pudo enviar el correo.', 'goodsleep-elementor' ), wp_remote_retrieve_body( $response ) );
		}

		return true;
	}

	/**
	 * Devuelve un nombre visible mas amable para el remitente.
	 *
	 * @return string
	 */
	protected function get_sender_name() {
		$from_name = trim( (string) goodsleep_get_setting( 'mailjet_from_name', 'Goodsleep' ) );

		if ( '' === $from_name || 'Goodsleep' === $from_name ) {
			return get_bloginfo( 'name' );
		}

		return $from_name;
	}

	/**
	 * Devuelve el nombre visible del reply-to.
	 *
	 * @param string $fallback Nombre fallback.
	 * @return string
	 */
	protected function get_reply_to_name( $fallback ) {
		$reply_to_name = trim( (string) goodsleep_get_setting( 'mailjet_reply_to_name', '' ) );

		return '' !== $reply_to_name ? $reply_to_name : $fallback;
	}

	/**
	 * Construye template HTML base.
	 *
	 * @param array<string,mixed> $story_data Datos.
	 * @param string              $share_url  URL publica.
	 * @param string              $media_url  URL final.
	 * @param string              $media_type Tipo de media.
	 * @return string
	 */
	protected function build_email_template( $story_data, $share_url, $media_url, $media_type = 'video' ) {
		$name       = esc_html( $story_data['name'] );
		$story_id   = isset( $story_data['story_id'] ) ? absint( $story_data['story_id'] ) : 0;
		$combined   = $story_id ? goodsleep_get_story_combined_text( $story_id ) : '';
		$brand_html = $this->get_email_brand_markup();
		$bg_url     = $this->get_email_background_url();

		$combined_html = '';
		if ( '' !== $combined ) {
			$combined_html = '<div style="margin:0 0 24px;padding:20px 22px;background:#ffffff;border-radius:18px;color:#171717;font-size:17px;line-height:1.6;">' . wp_kses_post( wpautop( esc_html( $combined ) ) ) . '</div>';
		}

		$cta_label      = 'video' === $media_type ? 'Ver historia' : 'Escuchar historia';
		$download_label = 'video' === $media_type ? 'video' : 'audio';

		return '
		<div style="background-color:#0b0b10;background-image:url(' . esc_url( $bg_url ) . ');background-repeat:no-repeat;background-size:cover;background-position:bottom center;padding:40px 24px;font-family:Arial,sans-serif;color:#ffffff;">
			<div style="max-width:640px;margin:0 auto;background:rgba(23, 23, 34, 0.6);border-radius:24px;padding:40px;">
				<div style="margin:0 0 20px;">' . $brand_html . '</div>
				<h1 style="margin:0 0 16px;font-size:32px;line-height:1.1;">La historia de ' . $name . ', esta lista en video</h1>
				<p style="margin:0 0 24px;color:#d8d8e5;font-size:16px;line-height:1.6;">Ya puedes verla, descargarla o compartirla.</p>
				' . $combined_html . '
				<p style="margin:0 0 24px;">
					<a href="' . esc_url( $share_url ) . '" style="display:inline-block;background:#ff1b9c;color:#ffffff;text-decoration:none;padding:14px 20px;border-radius:10px;font-weight:bold;">' . esc_html( $cta_label ) . '</a>
				</p>
				<p style="margin:0 0 8px;color:#d8d8e5;font-size:14px;">Descarga directa del ' . esc_html( $download_label ) . ':</p>
				<p style="margin:0 0 24px;"><a href="' . esc_url( $media_url ) . '" style="color:#ffffff;">Aqui</a></p>
				<p style="margin:0;color:#ffffff;font-size:13px;">Si no pediste este correo, puedes ignorarlo.</p>
			</div>
		</div>';
	}

	/**
	 * Devuelve el branding superior del correo usando el logo del sitio.
	 *
	 * @return string
	 */
	protected function get_email_brand_markup() {
		$logo_id  = (int) get_theme_mod( 'custom_logo' );
		$logo_url = $logo_id ? wp_get_attachment_image_url( $logo_id, 'full' ) : '';

		if ( $logo_url ) {
			return '<img src="' . esc_url( $logo_url ) . '" alt="' . esc_attr( get_bloginfo( 'name' ) ) . '" style="display:block;width:auto;max-width:180px;max-height:60px;height:auto;border:0;">';
		}

		return '<p style="margin:0;color:#ff1b9c;font-size:12px;letter-spacing:1px;text-transform:uppercase;">Goodsleep</p>';
	}

	/**
	 * Devuelve la imagen de fondo usada por el correo de historias.
	 *
	 * @return string
	 */
	protected function get_email_background_url() {
		$uploads = wp_get_upload_dir();

		if ( ! empty( $uploads['baseurl'] ) ) {
			return trailingslashit( $uploads['baseurl'] ) . '2026/03/historias_bg.jpg';
		}

		return '';
	}
}
