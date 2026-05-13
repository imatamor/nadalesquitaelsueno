<?php
/**
 * Nombre: Goodsleep_Elementor_OpenAI_Text_Client
 * Descripción: Encapsula la integración con OpenAI para generar texto interno,
 * historias cortas y reescrituras acotadas al límite del formulario.
 * Uso: Instanciarla cuando se necesite generar story_text o frases derivadas desde prompts.
 * Dependencias: Settings del plugin, wp_remote_post y helpers de saneamiento de WordPress.
 * Notas: Mantiene compatibilidad con modelos que no aceptan temperature explícita.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Goodsleep_Elementor_OpenAI_Text_Client {
	/**
	 * Nombre: generate_text
	 * Descripción: Envía un prompt final a Chat Completions y devuelve únicamente
	 * el texto utilizable, con saneamiento y manejo uniforme de errores.
	 * Uso: generate_text( $prompt, $args ) para cualquier generación textual base.
	 *
	 * @param string $prompt Prompt final enviado al modelo.
	 * @param array<string,mixed> $args Configuracion opcional.
	 * @return string|WP_Error
	 */
	public function generate_text( $prompt, $args = array() ) {
		$api_key = trim( (string) goodsleep_get_setting( 'openai_text_api_key', '' ) );
		$model   = ! empty( $args['model'] ) ? sanitize_text_field( (string) $args['model'] ) : sanitize_text_field( (string) goodsleep_get_setting( 'openai_text_model', 'gpt-4o-mini' ) );
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
		$endpoint    = 'https://api.openai.com/v1/chat/completions';
		$payload     = array(
			'model'    => $model,
			'messages' => array(
				array(
					'role'    => 'system',
					'content' => $instruction,
				),
				array(
					'role'    => 'user',
					'content' => $prompt,
				),
			),
		);

		if ( $this->model_supports_temperature( $model, $temperature ) ) {
			$payload['temperature'] = max( 0, min( 2, $temperature ) );
		}

		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout' => $timeout,
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code     = (int) wp_remote_retrieve_response_code( $response );
		$raw_body = wp_remote_retrieve_body( $response );
		$decoded  = json_decode( $raw_body, true );

		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error(
				'goodsleep_openai_text_failed',
				$this->extract_error_message( $decoded, $raw_body, $code ),
				array(
					'status'        => 502,
					'openai_code'   => $code,
					'openai_sample' => $this->build_debug_sample( $decoded, $raw_body ),
				)
			);
		}

		$text = $this->extract_output_text( $decoded );
		if ( '' === $text ) {
			return new WP_Error(
				'goodsleep_openai_text_empty',
				__( 'OpenAI no devolvio un texto utilizable.', 'goodsleep-elementor' ),
				array(
					'openai_sample' => $this->build_debug_sample( $decoded, $raw_body ),
				)
			);
		}

		return $this->sanitize_generated_text( $text );
	}

	/**
	 * Nombre: generate_story_text
	 * Descripción: Genera una historia breve para el flujo interno, reintentando si
	 * el modelo devuelve texto vacío o una historia que supera el límite permitido.
	 * Uso: Se usa desde la herramienta de importación antes de delegar al generador productivo.
	 *
	 * @param string $prompt Prompt final enviado al modelo.
	 * @param array<string,mixed> $args Configuracion opcional.
	 * @return string|WP_Error
	 */
	public function generate_story_text( $prompt, $args = array() ) {
		$attempts    = ! empty( $args['attempts'] ) ? max( 1, absint( $args['attempts'] ) ) : 3;
		$instruction = ! empty( $args['instruction'] ) ? (string) $args['instruction'] : 'Genera una sola historia breve en espanol y devuelve unicamente la historia final. Debe respetar estrictamente un maximo de 500 caracteres, incluyendo espacios. Si la primera version supera el limite, debes acortarla antes de responder. No uses comillas, listas, encabezados, moraleja ni reflexion final.';
		$last_error  = null;

		for ( $attempt = 0; $attempt < $attempts; $attempt++ ) {
			$generated = $this->generate_text(
				$prompt,
				array(
					'instruction' => $instruction,
					'model'       => ! empty( $args['model'] ) ? $args['model'] : '',
					'timeout'     => ! empty( $args['timeout'] ) ? $args['timeout'] : 0,
				)
			);

			if ( is_wp_error( $generated ) ) {
				$last_error = $generated;
				continue;
			}

			$generated = $this->sanitize_generated_text( $generated );
			if ( '' === $generated ) {
				$last_error = new WP_Error(
					'goodsleep_openai_story_empty',
					__( 'OpenAI devolvio una historia vacia.', 'goodsleep-elementor' ),
					array(
						'generated_text' => '',
					)
				);
				continue;
			}

			if ( strlen( $generated ) > 500 ) {
				$shortened = $this->rewrite_story_to_max_length(
					$generated,
					500,
					array(
						'model'   => ! empty( $args['model'] ) ? $args['model'] : '',
						'timeout' => ! empty( $args['timeout'] ) ? $args['timeout'] : 0,
					)
				);

				if ( ! is_wp_error( $shortened ) ) {
					$shortened = $this->sanitize_generated_text( $shortened );

					if ( '' !== $shortened && strlen( $shortened ) <= 500 ) {
						return $shortened;
					}
				}

				$last_error = new WP_Error(
					'goodsleep_openai_story_too_long',
					__( 'OpenAI devolvio una historia que supera los 500 caracteres.', 'goodsleep-elementor' ),
					array(
						'generated_text' => $generated,
					)
				);
				continue;
			}

			return $generated;
		}

		return $last_error ? $last_error : new WP_Error( 'goodsleep_openai_story_failed', __( 'No se pudo generar una historia valida con OpenAI.', 'goodsleep-elementor' ) );
	}

	/**
	 * Nombre: rewrite_story_to_max_length
	 * Descripción: Solicita una segunda pasada de OpenAI para condensar una historia
	 * demasiado larga sin cambiar la idea principal.
	 * Uso: Se invoca automáticamente cuando generate_story_text detecta un exceso de longitud.
	 *
	 * @param string              $story_text Historia base demasiado larga.
	 * @param int                 $max_length Limite maximo.
	 * @param array<string,mixed> $args Configuracion opcional.
	 * @return string|WP_Error
	 */
	protected function rewrite_story_to_max_length( $story_text, $max_length, $args = array() ) {
		$story_text = trim( (string) $story_text );
		$max_length = max( 1, absint( $max_length ) );

		if ( '' === $story_text ) {
			return new WP_Error( 'goodsleep_openai_story_rewrite_empty', __( 'No hay historia base para acortar.', 'goodsleep-elementor' ) );
		}

		$rewrite_prompt = sprintf(
			"Reescribe esta misma historia en espanol manteniendo la idea central, pero dejala en un maximo de %d caracteres contando espacios.\nNo agregues informacion nueva.\nNo uses comillas, listas, titulo, moraleja ni reflexion final.\nDevuelve solo la historia final.\n\nHistoria original:\n%s",
			$max_length,
			$story_text
		);

		return $this->generate_text(
			$rewrite_prompt,
			array(
				'instruction' => sprintf(
					'Acorta textos en espanol sin perder la idea principal. La respuesta debe tener como maximo %d caracteres contando espacios. Devuelve solo el texto final.',
					$max_length
				),
				'model'       => ! empty( $args['model'] ) ? $args['model'] : '',
				'timeout'     => ! empty( $args['timeout'] ) ? $args['timeout'] : 0,
			)
		);
	}

	/**
	 * Mantiene compatibilidad con el nombre anterior del metodo.
	 *
	 * @param string $prompt Prompt final enviado al modelo.
	 * @param array<string,mixed> $args Configuracion opcional.
	 * @return string|WP_Error
	 */
	public function generate_phrase( $prompt, $args = array() ) {
		$args['instruction'] = ! empty( $args['instruction'] ) ? $args['instruction'] : 'Genera una sola frase final breve en espanol. Devuelve solo la frase, sin comillas, sin listas y sin explicaciones adicionales.';

		return $this->generate_text( $prompt, $args );
	}

	/**
	 * Extrae texto desde la respuesta de Chat Completions.
	 *
	 * @param array<string,mixed>|null $payload Respuesta decodificada.
	 * @return string
	 */
	protected function extract_output_text( $payload ) {
		if ( ! is_array( $payload ) ) {
			return '';
		}

		if ( ! empty( $payload['choices'][0]['message']['content'] ) && is_string( $payload['choices'][0]['message']['content'] ) ) {
			return trim( $payload['choices'][0]['message']['content'] );
		}

		return '';
	}

	/**
	 * Normaliza errores de OpenAI a un mensaje legible.
	 *
	 * @param array<string,mixed>|null $payload Error decodificado.
	 * @param string                   $raw_body Body crudo.
	 * @param int                      $code Codigo HTTP.
	 * @return string
	 */
	protected function extract_error_message( $payload, $raw_body, $code ) {
		if ( is_array( $payload ) && ! empty( $payload['error']['message'] ) && is_string( $payload['error']['message'] ) ) {
			return sanitize_text_field( $payload['error']['message'] );
		}

		return sprintf(
			/* translators: 1: response code, 2: response body */
			__( 'La API de OpenAI respondio con error (%1$s): %2$s', 'goodsleep-elementor' ),
			$code,
			sanitize_text_field( substr( wp_strip_all_tags( (string) $raw_body ), 0, 400 ) )
		);
	}

	/**
	 * Construye una muestra acotada de la respuesta para debug.
	 *
	 * @param array<string,mixed>|null $payload Respuesta decodificada.
	 * @param string                   $raw_body Body crudo.
	 * @return string
	 */
	protected function build_debug_sample( $payload, $raw_body ) {
		if ( is_array( $payload ) ) {
			$encoded = wp_json_encode( $payload );
			if ( is_string( $encoded ) && '' !== $encoded ) {
				return substr( $encoded, 0, 1200 );
			}
		}

		return substr( (string) $raw_body, 0, 1200 );
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

	/**
	 * Nombre: model_supports_temperature
	 * Descripción: Decide si conviene incluir temperature en el payload según el modelo
	 * configurado, evitando requests incompatibles con ciertas variantes de GPT-5.
	 * Uso: Se llama antes de construir el payload final hacia OpenAI.
	 *
	 * @param string $model Modelo configurado.
	 * @param float  $temperature Temperatura solicitada.
	 * @return bool
	 */
	protected function model_supports_temperature( $model, $temperature ) {
		$model = strtolower( trim( (string) $model ) );

		if ( '' === $model ) {
			return true;
		}

		if ( 0 === strpos( $model, 'gpt-5' ) ) {
			return abs( (float) $temperature - 1.0 ) < 0.0001;
		}

		return true;
	}
}
