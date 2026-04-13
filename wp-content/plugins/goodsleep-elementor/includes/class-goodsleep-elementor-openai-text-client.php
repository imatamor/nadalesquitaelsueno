<?php
/**
 * Cliente de OpenAI para generacion de texto interno.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Goodsleep_Elementor_OpenAI_Text_Client {
	/**
	 * Genera texto libre a partir de un prompt renderizado.
	 *
	 * @param string $prompt Prompt final enviado al modelo.
	 * @param array<string,mixed> $args Configuracion opcional.
	 * @return string|WP_Error
	 */
	public function generate_text( $prompt, $args = array() ) {
		$api_key = trim( (string) goodsleep_get_setting( 'openai_text_api_key', '' ) );
		$model   = ! empty( $args['model'] ) ? sanitize_text_field( (string) $args['model'] ) : sanitize_text_field( (string) goodsleep_get_setting( 'openai_text_model', 'gpt-5-mini' ) );
		$timeout = ! empty( $args['timeout'] ) ? max( 5, absint( $args['timeout'] ) ) : max( 5, absint( goodsleep_get_setting( 'openai_text_timeout', 30 ) ) );
		$temperature = isset( $args['temperature'] ) ? (float) $args['temperature'] : (float) goodsleep_get_setting( 'openai_text_temperature', 0.8 );

		if ( '' === $api_key ) {
			return new WP_Error( 'goodsleep_missing_openai_text_config', __( 'OpenAI de texto no esta configurado.', 'goodsleep-elementor' ) );
		}

		$prompt = trim( (string) $prompt );
		if ( '' === $prompt ) {
			return new WP_Error( 'goodsleep_empty_openai_prompt', __( 'El prompt para OpenAI no puede estar vacio.', 'goodsleep-elementor' ) );
		}

		$instruction = ! empty( $args['instruction'] ) ? (string) $args['instruction'] : 'Genera texto en espanol siguiendo exactamente las instrucciones del prompt del usuario. Devuelve solo el texto final, sin comillas, sin listas y sin explicaciones adicionales.';
		$max_tokens  = ! empty( $args['max_output_tokens'] ) ? max( 32, absint( $args['max_output_tokens'] ) ) : 220;

		$response = wp_remote_post(
			'https://api.openai.com/v1/responses',
			array(
				'timeout' => $timeout,
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'model'             => $model,
						'temperature'       => max( 0, min( 2, $temperature ) ),
						'max_output_tokens' => $max_tokens,
						'input'             => array(
							array(
								'role'    => 'developer',
								'content' => array(
									array(
										'type' => 'input_text',
										'text' => $instruction,
									),
								),
							),
							array(
								'role'    => 'user',
								'content' => array(
									array(
										'type' => 'input_text',
										'text' => $prompt,
									),
								),
							),
						),
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code    = (int) wp_remote_retrieve_response_code( $response );
		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error(
				'goodsleep_openai_text_failed',
				$this->extract_error_message( $decoded ),
				array(
					'status'      => 502,
					'openai_code' => $code,
				)
			);
		}

		$text = $this->extract_output_text( $decoded );
		if ( '' === $text ) {
			return new WP_Error( 'goodsleep_openai_text_empty', __( 'OpenAI no devolvio un texto utilizable.', 'goodsleep-elementor' ) );
		}

		return $this->sanitize_generated_text( $text );
	}

	/**
	 * Genera una historia corta respetando el limite del formulario actual.
	 *
	 * @param string $prompt Prompt final enviado al modelo.
	 * @param array<string,mixed> $args Configuracion opcional.
	 * @return string|WP_Error
	 */
	public function generate_story_text( $prompt, $args = array() ) {
		$args['instruction']       = ! empty( $args['instruction'] ) ? $args['instruction'] : 'Genera una historia breve en espanol. Debe sonar natural y emocional. Devuelve solo la historia final, sin comillas, sin listas, sin encabezados y con un maximo de 500 caracteres.';
		$args['max_output_tokens'] = ! empty( $args['max_output_tokens'] ) ? $args['max_output_tokens'] : 320;

		return $this->generate_text( $prompt, $args );
	}

	/**
	 * Mantiene compatibilidad con el nombre anterior del metodo.
	 *
	 * @param string $prompt Prompt final enviado al modelo.
	 * @param array<string,mixed> $args Configuracion opcional.
	 * @return string|WP_Error
	 */
	public function generate_phrase( $prompt, $args = array() ) {
		$args['instruction']       = ! empty( $args['instruction'] ) ? $args['instruction'] : 'Genera una sola frase final breve en espanol. Devuelve solo la frase, sin comillas, sin listas y sin explicaciones adicionales.';
		$args['max_output_tokens'] = ! empty( $args['max_output_tokens'] ) ? $args['max_output_tokens'] : 120;

		return $this->generate_text( $prompt, $args );
	}

	/**
	 * Extrae texto desde la respuesta de Responses API.
	 *
	 * @param array<string,mixed>|null $payload Respuesta decodificada.
	 * @return string
	 */
	protected function extract_output_text( $payload ) {
		if ( ! is_array( $payload ) ) {
			return '';
		}

		if ( ! empty( $payload['output_text'] ) && is_string( $payload['output_text'] ) ) {
			return trim( $payload['output_text'] );
		}

		if ( empty( $payload['output'] ) || ! is_array( $payload['output'] ) ) {
			return '';
		}

		$chunks = array();

		foreach ( $payload['output'] as $item ) {
			if ( empty( $item['content'] ) || ! is_array( $item['content'] ) ) {
				continue;
			}

			foreach ( $item['content'] as $content_item ) {
				if ( ! empty( $content_item['text'] ) && is_string( $content_item['text'] ) ) {
					$chunks[] = trim( $content_item['text'] );
				}
			}
		}

		return trim( implode( ' ', array_filter( $chunks ) ) );
	}

	/**
	 * Normaliza errores de OpenAI a un mensaje legible.
	 *
	 * @param array<string,mixed>|null $payload Error decodificado.
	 * @return string
	 */
	protected function extract_error_message( $payload ) {
		if ( ! is_array( $payload ) ) {
			return __( 'OpenAI devolvio un error al generar la frase.', 'goodsleep-elementor' );
		}

		if ( ! empty( $payload['error']['message'] ) && is_string( $payload['error']['message'] ) ) {
			return sanitize_text_field( $payload['error']['message'] );
		}

		if ( ! empty( $payload['message'] ) && is_string( $payload['message'] ) ) {
			return sanitize_text_field( $payload['message'] );
		}

		return __( 'OpenAI devolvio un error al generar la frase.', 'goodsleep-elementor' );
	}

	/**
	 * Limpia el texto devuelto por el modelo.
	 *
	 * @param string $text Texto crudo.
	 * @return string
	 */
	protected function sanitize_generated_text( $text ) {
		$text = trim( wp_strip_all_tags( (string) $text ) );
		$text = preg_replace( '/^[\"\']+|[\"\']+$/', '', $text );
		$text = preg_replace( '/\s+/', ' ', $text );

		return sanitize_text_field( trim( (string) $text ) );
	}
}
