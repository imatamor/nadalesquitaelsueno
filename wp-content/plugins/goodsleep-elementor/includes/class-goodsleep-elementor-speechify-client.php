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

			$language = $this->extract_language_data( $item );

			$voices[] = array(
				'id'             => sanitize_text_field( $item['id'] ),
				'label'          => sanitize_text_field( ! empty( $item['display_name'] ) ? $item['display_name'] : $item['id'] ),
				'language'       => $language['language'],
				'language_label' => $language['language_label'],
				'locale'         => $language['locale'],
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
			'input'        => ! empty( $payload['ssml'] ) ? $payload['ssml'] : $payload['text'],
			'voice_id'     => $payload['voice_id'],
			'audio_format' => 'mp3',
		);

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

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		$decoded     = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $status_code >= 400 ) {
			return new WP_Error(
				'goodsleep_speechify_request_failed',
				$this->extract_error_message( $decoded ),
				array(
					'status'          => 502,
					'speechifyStatus' => $status_code,
				)
			);
		}

		if ( ! is_array( $decoded ) ) {
			return new WP_Error( 'goodsleep_invalid_speechify_response', __( 'Speechify devolvio una respuesta invalida.', 'goodsleep-elementor' ) );
		}

		if ( empty( $decoded['audio_url'] ) && empty( $decoded['audio_data'] ) && empty( $decoded['audio_content'] ) && ( ! empty( $decoded['message'] ) || ! empty( $decoded['error'] ) ) ) {
			return new WP_Error( 'goodsleep_speechify_missing_audio', $this->extract_error_message( $decoded ), array( 'status' => 502 ) );
		}

		return $decoded;
	}

	/**
	 * Extrae idioma y locale de una voz de Speechify.
	 *
	 * @param array<string,mixed> $item Datos crudos de la voz.
	 * @return array<string,string>
	 */
	protected function extract_language_data( $item ) {
		$language       = '';
		$language_label = '';
		$locale         = '';

		$candidates = array(
			'language',
			'language_code',
			'languageCode',
			'lang',
			'locale',
			'locale_code',
			'localeCode',
		);

		foreach ( $candidates as $candidate ) {
			if ( empty( $item[ $candidate ] ) ) {
				continue;
			}

			if ( is_string( $item[ $candidate ] ) ) {
				if ( '' === $locale ) {
					$locale = sanitize_text_field( $item[ $candidate ] );
				}

				if ( '' === $language ) {
					$language = sanitize_text_field( $item[ $candidate ] );
				}
			}

			if ( is_array( $item[ $candidate ] ) ) {
				$nested_value = $this->extract_language_data( $item[ $candidate ] );

				if ( '' === $language && '' !== $nested_value['language'] ) {
					$language = $nested_value['language'];
				}

				if ( '' === $language_label && '' !== $nested_value['language_label'] ) {
					$language_label = $nested_value['language_label'];
				}

				if ( '' === $locale && '' !== $nested_value['locale'] ) {
					$locale = $nested_value['locale'];
				}
			}
		}

		if ( isset( $item['language_name'] ) && is_string( $item['language_name'] ) ) {
			$language_label = sanitize_text_field( $item['language_name'] );
		} elseif ( isset( $item['languageName'] ) && is_string( $item['languageName'] ) ) {
			$language_label = sanitize_text_field( $item['languageName'] );
		} elseif ( isset( $item['name'] ) && is_string( $item['name'] ) && '' === $language_label ) {
			$language_label = sanitize_text_field( $item['name'] );
		}

		if ( '' !== $locale && preg_match( '/^[a-z]{2,3}[-_][A-Z]{2}$/', $locale ) ) {
			$language = strtolower( strtok( $locale, '-_' ) );
		}

		if ( '' !== $language && preg_match( '/^[a-z]{2,3}$/i', $language ) ) {
			$language = strtolower( $language );
		}

		if ( '' === $language_label ) {
			if ( '' !== $locale && ! preg_match( '/^[a-z]{2,3}$/i', $locale ) ) {
				$language_label = $locale;
			} elseif ( '' !== $language ) {
				$language_label = strtoupper( $language );
			}
		}

		if ( '' === $locale ) {
			$locale = $language;
		}

		return array(
			'language'       => sanitize_text_field( $language ),
			'language_label' => sanitize_text_field( $language_label ),
			'locale'         => sanitize_text_field( $locale ),
		);
	}

	/**
	 * Extrae un mensaje de error legible desde Speechify.
	 *
	 * @param array<string,mixed>|null $payload Respuesta de Speechify.
	 * @return string
	 */
	protected function extract_error_message( $payload ) {
		if ( ! is_array( $payload ) ) {
			return __( 'Speechify devolvio un error al generar el audio.', 'goodsleep-elementor' );
		}

		if ( ! empty( $payload['message'] ) && is_string( $payload['message'] ) ) {
			return sanitize_text_field( $payload['message'] );
		}

		if ( ! empty( $payload['error'] ) && is_string( $payload['error'] ) ) {
			return sanitize_text_field( $payload['error'] );
		}

		if ( ! empty( $payload['detail'] ) && is_string( $payload['detail'] ) ) {
			return sanitize_text_field( $payload['detail'] );
		}

		return __( 'Speechify devolvio un error al generar el audio.', 'goodsleep-elementor' );
	}
}
