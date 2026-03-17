<?php
/**
 * Cliente de Speechify.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Goodsleep_Elementor_Speechify_Client {
	/**
	 * Sincroniza el catalogo de voces desde Speechify.
	 *
	 * @return array<int,array<string,string>>|WP_Error
	 */
	public function fetch_voices() {
		$api_key = goodsleep_get_setting( 'speechify_api_key', '' );
		$url     = untrailingslashit( goodsleep_get_setting( 'speechify_base_url', '' ) ) . goodsleep_get_setting( 'speechify_voices_path', '/v1/voices' );

		if ( '' === $api_key || '' === $url ) {
			return new WP_Error( 'goodsleep_missing_speechify_config', __( 'Speechify no esta configurado.', 'goodsleep-elementor' ) );
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Accept'        => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $decoded ) ) {
			return new WP_Error( 'goodsleep_invalid_voice_catalog', __( 'Speechify devolvio un catalogo de voces invalido.', 'goodsleep-elementor' ) );
		}

		$voices = array();

		foreach ( $decoded as $item ) {
			if ( empty( $item['id'] ) ) {
				continue;
			}

			$voices[] = array(
				'id'    => sanitize_text_field( $item['id'] ),
				'label' => sanitize_text_field( ! empty( $item['display_name'] ) ? $item['display_name'] : $item['id'] ),
			);
		}

		update_option( 'goodsleep_elementor_voice_catalog', $voices, false );

		return $voices;
	}

	/**
	 * Genera audio desde Speechify.
	 *
	 * @param array<string,mixed> $payload Payload normalizado.
	 * @return array<string,mixed>|WP_Error
	 */
	public function generate_audio( $payload ) {
		$api_key = goodsleep_get_setting( 'speechify_api_key', '' );
		$url     = untrailingslashit( goodsleep_get_setting( 'speechify_base_url', '' ) ) . goodsleep_get_setting( 'speechify_audio_path', '/v1/audio/speech' );

		if ( '' === $api_key || '' === $url ) {
			return new WP_Error( 'goodsleep_missing_speechify_config', __( 'Speechify no esta configurado.', 'goodsleep-elementor' ) );
		}

		$request_body = array(
			'input'    => $payload['text'],
			'voice_id' => $payload['voice_id'],
		);

		if ( ! empty( $payload['track_id'] ) ) {
			$request_body['background_track'] = $payload['track_id'];
		}

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 45,
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
					'Accept'        => 'application/json',
				),
				'body'    => wp_json_encode( $request_body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $decoded ) ) {
			return new WP_Error( 'goodsleep_invalid_speechify_response', __( 'Speechify devolvio una respuesta invalida.', 'goodsleep-elementor' ) );
		}

		return $decoded;
	}
}
