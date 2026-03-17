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
		$from_email = goodsleep_get_setting( 'mailjet_from_email', '' );

		if ( '' === $api_key || '' === $secret_key || '' === $from_email ) {
			return new WP_Error( 'goodsleep_missing_mailjet_config', __( 'Mailjet no esta configurado.', 'goodsleep-elementor' ) );
		}

		$share_url = goodsleep_get_story_share_url( $story_data['story_id'] );
		$audio_url = wp_get_attachment_url( $story_data['audio_id'] );
		$html_body = $this->build_email_template( $story_data, $share_url, $audio_url );

		$body = array(
			'Messages' => array(
				array(
					'From' => array(
						'Email' => $from_email,
						'Name'  => goodsleep_get_setting( 'mailjet_from_name', 'Goodsleep' ),
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

		$reply_to_email = goodsleep_get_setting( 'mailjet_reply_to_email', '' );
		if ( $reply_to_email ) {
			$body['Messages'][0]['ReplyTo'] = array(
				'Email' => $reply_to_email,
				'Name'  => goodsleep_get_setting( 'mailjet_reply_to_name', goodsleep_get_setting( 'mailjet_from_name', 'Goodsleep' ) ),
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
	 * Construye template HTML base.
	 *
	 * @param array<string,mixed> $story_data Datos.
	 * @param string              $share_url  URL publica.
	 * @param string              $audio_url  URL audio.
	 * @return string
	 */
	protected function build_email_template( $story_data, $share_url, $audio_url ) {
		$name = esc_html( $story_data['name'] );

		return '
		<div style="background:#0b0b10;padding:40px 24px;font-family:Arial,sans-serif;color:#ffffff;">
			<div style="max-width:640px;margin:0 auto;background:#171722;border-radius:24px;padding:40px;">
				<p style="margin:0 0 12px;color:#ff1b9c;font-size:12px;letter-spacing:1px;text-transform:uppercase;">Goodsleep</p>
				<h1 style="margin:0 0 16px;font-size:32px;line-height:1.1;">Tu historia ya esta lista, ' . $name . '.</h1>
				<p style="margin:0 0 24px;color:#d8d8e5;font-size:16px;line-height:1.6;">Ya puedes escucharla, descargarla o compartirla.</p>
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
