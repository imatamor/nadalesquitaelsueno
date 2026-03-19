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

		$share_url = goodsleep_get_story_share_url( $story_data['story_id'] );
		$audio_url = wp_get_attachment_url( $story_data['audio_id'] );
		$html_body = $this->build_email_template( $story_data, $share_url, $audio_url );
		$bcc_list  = goodsleep_parse_email_list( goodsleep_get_setting( 'mailjet_monitor_bcc', '' ) );

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
							'Name'  => $story_data['name'],
						),
					),
					'Subject'  => sprintf( __( 'Tu historia de Goodsleep ya esta lista, %s', 'goodsleep-elementor' ), $story_data['name'] ),
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
			return 'Goodsleep | Nada les quita el sueño';
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
	 * @param string              $audio_url  URL audio.
	 * @return string
	 */
	protected function build_email_template( $story_data, $share_url, $audio_url ) {
		$name        = esc_html( $story_data['name'] );
		$story_id    = isset( $story_data['story_id'] ) ? absint( $story_data['story_id'] ) : 0;
		$story_text  = $story_id ? (string) get_post_meta( $story_id, '_goodsleep_story_text', true ) : '';
		$story_phrase = $story_id ? (string) get_post_meta( $story_id, '_goodsleep_story_phrase', true ) : '';
		$combined    = $story_id ? (string) get_post_meta( $story_id, '_goodsleep_story_combined', true ) : '';

		if ( '' === $combined ) {
			$combined = trim( $story_text . "\n\n" . $story_phrase );
		}

		$combined_html = '';
		if ( '' !== $combined ) {
			$combined_html = '<div style="margin:0 0 24px;padding:20px 22px;background:#ffffff;border-radius:18px;color:#171717;font-size:17px;line-height:1.6;">' . wp_kses_post( wpautop( esc_html( $combined ) ) ) . '</div>';
		}

		return '
		<div style="background:#0b0b10;padding:40px 24px;font-family:Arial,sans-serif;color:#ffffff;">
			<div style="max-width:640px;margin:0 auto;background:#171722;border-radius:24px;padding:40px;">
				<p style="margin:0 0 12px;color:#ff1b9c;font-size:12px;letter-spacing:1px;text-transform:uppercase;">Goodsleep</p>
				<h1 style="margin:0 0 16px;font-size:32px;line-height:1.1;">Tu historia ya esta lista, ' . $name . '.</h1>
				<p style="margin:0 0 24px;color:#d8d8e5;font-size:16px;line-height:1.6;">Ya puedes escucharla, descargarla o compartirla.</p>
				' . $combined_html . '
				<p style="margin:0 0 24px;">
					<a href="' . esc_url( $share_url ) . '" style="display:inline-block;background:#ff1b9c;color:#ffffff;text-decoration:none;padding:14px 20px;border-radius:999px;font-weight:bold;">Escuchar historia</a>
				</p>
				<p style="margin:0 0 8px;color:#d8d8e5;font-size:14px;">Descarga directa:</p>
				<p style="margin:0 0 24px;"><a href="' . esc_url( $audio_url ) . '" style="color:#ffffff;">' . esc_html( $audio_url ) . '</a></p>
				<p style="margin:0;color:#8a8aa0;font-size:13px;">Si no pediste este correo, puedes ignorarlo.</p>
			</div>
		</div>';
	}
}
