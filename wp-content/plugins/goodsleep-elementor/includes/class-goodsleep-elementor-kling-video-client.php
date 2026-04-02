<?php
/**
 * Cliente de video para Kling.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Goodsleep_Elementor_Kling_Video_Client implements Goodsleep_Elementor_Video_Provider_Client_Interface {
	/**
	 * Crea una tarea text-to-video en Kling.
	 *
	 * @param array<string,mixed> $payload Payload normalizado.
	 * @return array<string,mixed>|WP_Error
	 */
	public function create_video_task( $payload ) {
		$config = $this->get_api_config();
		if ( is_wp_error( $config ) ) {
			return $config;
		}

		$prompt       = trim( (string) $payload['prompt'] );
		$multi_shot   = ! empty( $payload['multi_shot'] );
		$multi_prompt = ! empty( $payload['multi_prompt'] ) && is_array( $payload['multi_prompt'] ) ? array_values( array_filter( array_map( 'trim', $payload['multi_prompt'] ) ) ) : array();
		if ( ! $multi_shot && '' === $prompt ) {
			return new WP_Error( 'goodsleep_invalid_kling_payload', __( 'Faltan datos para crear el video en Kling.', 'goodsleep-elementor' ) );
		}

		if ( $multi_shot && empty( $multi_prompt ) ) {
			return new WP_Error( 'goodsleep_invalid_kling_payload', __( 'Faltan datos para crear el video en Kling.', 'goodsleep-elementor' ) );
		}

		$request_body = array(
			'model_name'       => sanitize_text_field( (string) goodsleep_get_setting( 'kling_video_model', 'kling-v3' ) ),
			'negative_prompt'  => trim( (string) goodsleep_get_setting( 'kling_negative_prompt', '' ) ),
			'mode'             => sanitize_text_field( (string) goodsleep_get_setting( 'kling_video_mode', 'std' ) ),
			'sound'            => sanitize_text_field( (string) goodsleep_get_setting( 'kling_video_sound', 'on' ) ),
			'aspect_ratio'     => $this->resolve_aspect_ratio(),
			'duration'         => (string) goodsleep_get_provider_video_duration( isset( $payload['duration'] ) ? (int) $payload['duration'] : null ),
			'callback_url'     => goodsleep_get_video_callback_url( 'kling' ),
			'external_task_id' => ! empty( $payload['external_task_id'] ) ? sanitize_text_field( (string) $payload['external_task_id'] ) : '',
		);

		if ( $multi_shot ) {
			$request_body['multi_shot']  = true;
			$request_body['shot_type']   = ! empty( $payload['shot_type'] ) ? sanitize_text_field( (string) $payload['shot_type'] ) : 'intelligence';
			$request_body['multi_prompt'] = $multi_prompt;
		} else {
			$request_body['prompt'] = $prompt;
		}

		$headers = $this->build_headers( $config );
		if ( is_wp_error( $headers ) ) {
			return $headers;
		}

		$response = wp_remote_post(
			$config['base_url'] . goodsleep_get_setting( 'kling_text_submit_path', '/v1/videos/text2video' ),
			array(
				'timeout' => 60,
				'headers' => $headers,
				'body'    => wp_json_encode( $request_body ),
			)
		);

		$decoded = $this->decode_response( $response, 'goodsleep_kling_video_create_failed', __( 'Kling devolvio un error al crear el video.', 'goodsleep-elementor' ) );
		if ( is_wp_error( $decoded ) ) {
			return $decoded;
		}

		$decoded['_goodsleep_kind'] = 'text_to_video';

		return $decoded;
	}

	/**
	 * Consulta una tarea de Kling.
	 *
	 * @param string              $task_id ID remoto.
	 * @param array<string,mixed> $context Contexto guardado.
	 * @return array<string,mixed>|WP_Error
	 */
	public function get_task( $task_id, $context = array() ) {
		$config = $this->get_api_config();
		if ( is_wp_error( $config ) ) {
			return $config;
		}

		$task_id = sanitize_text_field( (string) $task_id );
		if ( '' === $task_id ) {
			return new WP_Error( 'goodsleep_missing_kling_task_id', __( 'Kling no devolvio un task id valido.', 'goodsleep-elementor' ) );
		}

		$path = 'video_extension' === $this->detect_task_kind( $context ) ? goodsleep_get_setting( 'kling_extend_status_path', '/v1/videos/video-extend/%s' ) : goodsleep_get_setting( 'kling_text_status_path', '/v1/videos/text2video/%s' );
		$url  = $config['base_url'] . sprintf( $path, rawurlencode( $task_id ) );
		$headers = $this->build_headers( $config );
		if ( is_wp_error( $headers ) ) {
			return $headers;
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 30,
				'headers' => $headers,
			)
		);

		$decoded = $this->decode_response( $response, 'goodsleep_kling_video_status_failed', __( 'No se pudo consultar el estado del video en Kling.', 'goodsleep-elementor' ) );
		if ( is_wp_error( $decoded ) ) {
			return $decoded;
		}

		$decoded['_goodsleep_kind'] = $this->detect_task_kind( $context );

		return $decoded;
	}

	/**
	 * Crea una extension de video para el segundo clip.
	 *
	 * @param string              $origin_id      Referencia de origen.
	 * @param array<string,mixed> $payload        Prompt del clip.
	 * @param array<string,mixed> $source_context Tarea completada del clip 1.
	 * @return array<string,mixed>|WP_Error
	 */
	public function remix_video_task( $origin_id, $payload, $source_context = array() ) {
		$config = $this->get_api_config();
		if ( is_wp_error( $config ) ) {
			return $config;
		}

		$prompt = trim( (string) $payload['prompt'] );
		if ( '' === $prompt ) {
			return new WP_Error( 'goodsleep_invalid_kling_extension_payload', __( 'Faltan datos para extender el video en Kling.', 'goodsleep-elementor' ) );
		}

		$video_id = $this->extract_video_id( $source_context );
		$request_body = array(
			'prompt'           => $prompt,
			'callback_url'     => goodsleep_get_video_callback_url( 'kling' ),
			'external_task_id' => ! empty( $payload['external_task_id'] ) ? sanitize_text_field( (string) $payload['external_task_id'] ) : '',
		);

		if ( '' !== $video_id ) {
			$request_body['video_id'] = $video_id;
		} else {
			$request_body['task_id'] = sanitize_text_field( (string) $origin_id );
		}
		$headers = $this->build_headers( $config );
		if ( is_wp_error( $headers ) ) {
			return $headers;
		}

		$response = wp_remote_post(
			$config['base_url'] . goodsleep_get_setting( 'kling_extend_submit_path', '/v1/videos/video-extend' ),
			array(
				'timeout' => 60,
				'headers' => $headers,
				'body'    => wp_json_encode( $request_body ),
			)
		);

		$decoded = $this->decode_response( $response, 'goodsleep_kling_video_extension_failed', __( 'Kling devolvio un error al extender el video.', 'goodsleep-elementor' ) );
		if ( is_wp_error( $decoded ) ) {
			return $decoded;
		}

		$decoded['_goodsleep_kind'] = 'video_extension';

		return $decoded;
	}

	/**
	 * Descarga el MP4 final usando la URL del resultado.
	 *
	 * @param string              $task_id  ID remoto.
	 * @param array<string,mixed> $context  Tarea completada.
	 * @return string|WP_Error
	 */
	public function download_video_content( $task_id, $context = array() ) {
		$video_url = $this->extract_video_url( $context );
		if ( '' === $video_url ) {
			return new WP_Error( 'goodsleep_kling_video_content_missing', __( 'Kling no devolvio una URL utilizable para el video final.', 'goodsleep-elementor' ) );
		}

		if ( ! function_exists( 'wp_tempnam' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$temp_path = wp_tempnam( sanitize_file_name( (string) $task_id . '.mp4' ) );
		if ( ! $temp_path ) {
			return new WP_Error( 'goodsleep_video_temp_failed', __( 'No se pudo preparar la descarga del video final.', 'goodsleep-elementor' ) );
		}

		$response = wp_remote_get(
			$video_url,
			array(
				'timeout'  => 180,
				'stream'   => true,
				'filename' => $temp_path,
				'headers'  => array(
					'Accept' => 'video/mp4',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			@unlink( $temp_path );
			return $response;
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		if ( $status_code >= 400 || ! file_exists( $temp_path ) || 0 === filesize( $temp_path ) ) {
			@unlink( $temp_path );
			return new WP_Error( 'goodsleep_kling_video_content_failed', __( 'Kling no devolvio un archivo MP4 utilizable.', 'goodsleep-elementor' ), array( 'status' => 502, 'provider_status' => $status_code ) );
		}

		return $temp_path;
	}

	/**
	 * Extrae el task id.
	 *
	 * @param array<string,mixed> $payload Payload.
	 * @return string
	 */
	public function extract_task_id( $payload ) {
		foreach ( array( 'task_id', 'id' ) as $candidate ) {
			if ( ! empty( $payload[ $candidate ] ) ) {
				return sanitize_text_field( (string) $payload[ $candidate ] );
			}
		}

		if ( ! empty( $payload['data'] ) && is_array( $payload['data'] ) ) {
			return $this->extract_task_id( $payload['data'] );
		}

		return '';
	}

	/**
	 * Extrae el estado normalizado.
	 *
	 * @param array<string,mixed> $payload Payload.
	 * @return string
	 */
	public function extract_status( $payload ) {
		foreach ( array( 'task_status', 'status', 'state' ) as $candidate ) {
			if ( ! empty( $payload[ $candidate ] ) ) {
				$status = strtolower( sanitize_text_field( (string) $payload[ $candidate ] ) );

				if ( in_array( $status, array( 'succeed', 'success', 'completed' ), true ) ) {
					return 'completed';
				}

				if ( in_array( $status, array( 'submitted', 'not_started', 'queued', 'pending' ), true ) ) {
					return 'pending';
				}

				return $status;
			}
		}

		if ( ! empty( $payload['data'] ) && is_array( $payload['data'] ) ) {
			return $this->extract_status( $payload['data'] );
		}

		return '';
	}

	/**
	 * Extrae la URL final del video.
	 *
	 * @param array<string,mixed> $payload Payload.
	 * @return string
	 */
	public function extract_video_url( $payload ) {
		foreach ( array( 'url', 'video_url', 'download_url' ) as $candidate ) {
			if ( ! empty( $payload[ $candidate ] ) && is_string( $payload[ $candidate ] ) ) {
				return esc_url_raw( (string) $payload[ $candidate ] );
			}
		}

		if ( ! empty( $payload['task_result']['videos'] ) && is_array( $payload['task_result']['videos'] ) ) {
			foreach ( $payload['task_result']['videos'] as $video_item ) {
				if ( is_array( $video_item ) && ! empty( $video_item['url'] ) ) {
					return esc_url_raw( (string) $video_item['url'] );
				}
			}
		}

		if ( ! empty( $payload['data'] ) && is_array( $payload['data'] ) ) {
			return $this->extract_video_url( $payload['data'] );
		}

		return '';
	}

	/**
	 * Extrae el video_id final del clip generado.
	 *
	 * @param array<string,mixed> $payload Payload.
	 * @return string
	 */
	public function extract_video_id( $payload ) {
		foreach ( array( 'video_id', 'videoId' ) as $candidate ) {
			if ( ! empty( $payload[ $candidate ] ) ) {
				return sanitize_text_field( (string) $payload[ $candidate ] );
			}
		}

		if ( ! empty( $payload['task_result']['videos'] ) && is_array( $payload['task_result']['videos'] ) ) {
			foreach ( $payload['task_result']['videos'] as $video_item ) {
				if ( is_array( $video_item ) && ! empty( $video_item['id'] ) ) {
					return sanitize_text_field( (string) $video_item['id'] );
				}
			}
		}

		if ( ! empty( $payload['data'] ) && is_array( $payload['data'] ) ) {
			return $this->extract_video_id( $payload['data'] );
		}

		return '';
	}

	/**
	 * Devuelve configuracion base del API.
	 *
	 * @return array<string,string>|WP_Error
	 */
	protected function get_api_config() {
		$access_key = trim( (string) goodsleep_get_setting( 'kling_access_key', '' ) );
		$secret_key = trim( (string) goodsleep_get_setting( 'kling_secret_key', '' ) );
		$base_url   = untrailingslashit( (string) goodsleep_get_setting( 'kling_base_url', 'https://api-singapore.klingai.com' ) );

		if ( '' === $access_key || '' === $secret_key || '' === $base_url ) {
			return new WP_Error( 'goodsleep_missing_kling_config', __( 'Kling no esta configurado.', 'goodsleep-elementor' ) );
		}

		return array(
			'access_key' => $access_key,
			'secret_key' => $secret_key,
			'base_url'   => $base_url,
		);
	}

	/**
	 * Construye los headers con JWT fresco.
	 *
	 * @param array<string,string> $config Configuracion API.
	 * @return array<string,string>|WP_Error
	 */
	protected function build_headers( $config ) {
		$jwt = $this->generate_jwt( $config['access_key'], $config['secret_key'] );
		if ( is_wp_error( $jwt ) ) {
			return $jwt;
		}

		return array(
			'Authorization' => 'Bearer ' . $jwt,
			'Content-Type'  => 'application/json',
			'Accept'        => 'application/json',
		);
	}

	/**
	 * Genera el JWT que espera Kling.
	 *
	 * @param string $access_key Access key.
	 * @param string $secret_key Secret key.
	 * @return string|WP_Error
	 */
	protected function generate_jwt( $access_key, $secret_key ) {
		$header = array(
			'alg' => 'HS256',
			'typ' => 'JWT',
		);

		$now = time();
		$payload = array(
			'iss' => $access_key,
			'exp' => $now + 1800,
			'nbf' => $now - 5,
			'iat' => $now,
		);

		$header_encoded  = $this->base64_url_encode( wp_json_encode( $header ) );
		$payload_encoded = $this->base64_url_encode( wp_json_encode( $payload ) );

		if ( '' === $header_encoded || '' === $payload_encoded ) {
			return new WP_Error( 'goodsleep_kling_jwt_failed', __( 'No se pudo generar el token JWT para Kling.', 'goodsleep-elementor' ) );
		}

		$signature = hash_hmac( 'sha256', $header_encoded . '.' . $payload_encoded, $secret_key, true );

		return $header_encoded . '.' . $payload_encoded . '.' . $this->base64_url_encode( $signature );
	}

	/**
	 * Codifica base64 URL-safe sin relleno.
	 *
	 * @param string $data Texto.
	 * @return string
	 */
	protected function base64_url_encode( $data ) {
		return rtrim( strtr( base64_encode( (string) $data ), '+/', '-_' ), '=' );
	}

	/**
	 * Decodifica una respuesta HTTP con manejo basico de errores.
	 *
	 * @param array<string,mixed>|WP_Error $response Respuesta.
	 * @param string                       $error_key Codigo.
	 * @param string                       $fallback  Mensaje.
	 * @return array<string,mixed>|WP_Error
	 */
	protected function decode_response( $response, $error_key, $fallback ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		$body        = (string) wp_remote_retrieve_body( $response );
		$decoded     = json_decode( $body, true );

		if ( $status_code >= 400 ) {
			return new WP_Error( $error_key, $this->extract_error_message( $decoded, $fallback, $body ), array( 'status' => 502, 'provider_status' => $status_code ) );
		}

		if ( ! is_array( $decoded ) ) {
			return new WP_Error( $error_key, $this->extract_error_message( null, $fallback, $body ) );
		}

		$code = isset( $decoded['code'] ) ? (int) $decoded['code'] : 0;
		if ( 0 !== $code ) {
			return new WP_Error( $error_key, $this->extract_error_message( $decoded, $fallback, $body ), array( 'status' => 502, 'provider_code' => $code ) );
		}

		return $decoded;
	}

	/**
	 * Extrae mensaje legible.
	 *
	 * @param array<string,mixed>|null $payload  Payload.
	 * @param string                   $fallback Fallback.
	 * @param string                   $raw_body Cuerpo bruto.
	 * @return string
	 */
	protected function extract_error_message( $payload, $fallback, $raw_body = '' ) {
		if ( ! is_array( $payload ) ) {
			$raw_body = trim( wp_strip_all_tags( (string) $raw_body ) );
			if ( '' !== $raw_body ) {
				return function_exists( 'mb_substr' ) ? trim( mb_substr( $raw_body, 0, 240 ) ) : trim( substr( $raw_body, 0, 240 ) );
			}

			return $fallback;
		}

		foreach ( array( 'message', 'error', 'task_status_msg' ) as $key ) {
			if ( ! empty( $payload[ $key ] ) && is_string( $payload[ $key ] ) ) {
				return sanitize_text_field( (string) $payload[ $key ] );
			}
		}

		if ( ! empty( $payload['data'] ) && is_array( $payload['data'] ) ) {
			return $this->extract_error_message( $payload['data'], $fallback, $raw_body );
		}

		$raw_body = trim( wp_strip_all_tags( (string) $raw_body ) );
		if ( '' !== $raw_body ) {
			return function_exists( 'mb_substr' ) ? trim( mb_substr( $raw_body, 0, 240 ) ) : trim( substr( $raw_body, 0, 240 ) );
		}

		return $fallback;
	}

	/**
	 * Resuelve el aspect ratio configurado.
	 *
	 * @return string
	 */
	protected function resolve_aspect_ratio() {
		$aspect_ratio = trim( (string) goodsleep_get_setting( 'video_aspect_ratio', '9:16' ) );

		return in_array( $aspect_ratio, array( '16:9', '9:16', '1:1' ), true ) ? $aspect_ratio : '9:16';
	}

	/**
	 * Detecta si la tarea es text_to_video o video_extension.
	 *
	 * @param array<string,mixed> $context Contexto guardado.
	 * @return string
	 */
	protected function detect_task_kind( $context ) {
		$kind = ! empty( $context['_goodsleep_kind'] ) ? sanitize_key( (string) $context['_goodsleep_kind'] ) : '';

		return in_array( $kind, array( 'text_to_video', 'video_extension' ), true ) ? $kind : 'text_to_video';
	}
}
