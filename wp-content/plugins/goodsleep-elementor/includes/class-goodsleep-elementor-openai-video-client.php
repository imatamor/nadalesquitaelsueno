<?php
/**
 * Cliente de video para OpenAI Sora.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Goodsleep_Elementor_OpenAI_Video_Client {
	/**
	 * Crea una tarea de generacion de video.
	 *
	 * @param array<string,mixed> $payload Payload normalizado.
	 * @return array<string,mixed>|WP_Error
	 */
	public function create_video_task( $payload ) {
		$api_key = goodsleep_get_setting( 'openai_api_key', '' );
		$url     = untrailingslashit( goodsleep_get_setting( 'openai_base_url', '' ) ) . goodsleep_get_setting( 'openai_video_submit_path', '/videos' );

		if ( '' === $api_key || '' === $url ) {
			return new WP_Error( 'goodsleep_missing_openai_config', __( 'OpenAI Sora no esta configurado.', 'goodsleep-elementor' ) );
		}

		$model    = ! empty( $payload['model'] ) ? sanitize_text_field( (string) $payload['model'] ) : sanitize_text_field( (string) goodsleep_get_setting( 'openai_video_model', 'sora-2' ) );
		$prompt   = trim( (string) $payload['prompt'] );
		$duration = max( 1, (int) $payload['duration'] );
		$seconds  = (string) $duration;
		$size     = ! empty( $payload['size'] ) ? sanitize_text_field( (string) $payload['size'] ) : '720x1280';
		$input_reference = ! empty( $payload['input_reference'] ) && is_array( $payload['input_reference'] ) ? $payload['input_reference'] : array();

		if ( '' === $model || '' === $prompt ) {
			return new WP_Error( 'goodsleep_invalid_openai_payload', __( 'Faltan datos para crear el video en Sora.', 'goodsleep-elementor' ) );
		}

		$request_body = array(
			'model'   => $model,
			'prompt'  => $prompt,
			'size'    => $size,
			'seconds' => $seconds,
		);

		if ( ! empty( $input_reference['path'] ) ) {
			return $this->create_video_task_with_reference( $url, $api_key, $request_body, $input_reference );
		}

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 60,
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
					'Accept'        => 'application/json',
				),
				'body'    => wp_json_encode( $request_body ),
			)
		);

		return $this->decode_response( $response, 'goodsleep_openai_video_create_failed', __( 'OpenAI devolvio un error al crear el video.', 'goodsleep-elementor' ) );
	}

	/**
	 * Crea una tarea usando multipart cuando existe una imagen de referencia.
	 *
	 * @param string              $url             Endpoint.
	 * @param string              $api_key         API key.
	 * @param array<string,mixed> $request_body    Campos base.
	 * @param array<string,mixed> $input_reference Referencia visual.
	 * @return array<string,mixed>|WP_Error
	 */
	protected function create_video_task_with_reference( $url, $api_key, $request_body, $input_reference ) {
		$file_path = ! empty( $input_reference['path'] ) ? (string) $input_reference['path'] : '';
		$mime_type = ! empty( $input_reference['mime_type'] ) ? (string) $input_reference['mime_type'] : 'image/jpeg';

		if ( '' === $file_path || ! file_exists( $file_path ) ) {
			return new WP_Error( 'goodsleep_invalid_video_reference', __( 'La imagen de referencia del producto no es valida.', 'goodsleep-elementor' ) );
		}

		if ( ! function_exists( 'curl_init' ) || ! class_exists( 'CURLFile' ) ) {
			return new WP_Error( 'goodsleep_missing_curl_reference', __( 'El servidor no puede enviar imagenes de referencia a Sora porque CURL no esta disponible.', 'goodsleep-elementor' ) );
		}

		$curl_file = curl_file_create( $file_path, $mime_type, wp_basename( $file_path ) );
		$multipart = array_merge(
			$request_body,
			array(
				'input_reference' => $curl_file,
			)
		);

		$ch = curl_init( $url );

		curl_setopt_array(
			$ch,
			array(
				CURLOPT_POST           => true,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_TIMEOUT        => 60,
				CURLOPT_HTTPHEADER     => array(
					'Authorization: Bearer ' . $api_key,
					'Accept: application/json',
				),
				CURLOPT_POSTFIELDS     => $multipart,
			)
		);

		$body       = curl_exec( $ch );
		$curl_error = curl_error( $ch );
		$status     = (int) curl_getinfo( $ch, CURLINFO_RESPONSE_CODE );
		curl_close( $ch );

		if ( false === $body ) {
			return new WP_Error( 'goodsleep_openai_video_create_failed', $curl_error ? $curl_error : __( 'No se pudo conectar con OpenAI.', 'goodsleep-elementor' ) );
		}

		$decoded = json_decode( (string) $body, true );

		if ( $status >= 400 ) {
			return new WP_Error( 'goodsleep_openai_video_create_failed', $this->extract_error_message( $decoded, __( 'OpenAI devolvio un error al crear el video.', 'goodsleep-elementor' ) ), array( 'status' => 502, 'provider_status' => $status ) );
		}

		if ( ! is_array( $decoded ) ) {
			return new WP_Error( 'goodsleep_openai_video_create_failed', __( 'OpenAI devolvio una respuesta invalida al crear el video.', 'goodsleep-elementor' ) );
		}

		return $decoded;
	}

	/**
	 * Consulta el estado de un video.
	 *
	 * @param string $task_id ID del video.
	 * @return array<string,mixed>|WP_Error
	 */
	public function get_task( $task_id ) {
		$api_key = goodsleep_get_setting( 'openai_api_key', '' );
		$base    = untrailingslashit( goodsleep_get_setting( 'openai_base_url', '' ) );
		$path    = goodsleep_get_setting( 'openai_video_status_path', '/videos/%s' );
		$url     = $base . sprintf( $path, rawurlencode( (string) $task_id ) );

		if ( '' === $api_key || '' === $url || '' === (string) $task_id ) {
			return new WP_Error( 'goodsleep_missing_openai_config', __( 'OpenAI Sora no esta configurado.', 'goodsleep-elementor' ) );
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 30,
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Accept'        => 'application/json',
				),
			)
		);

		return $this->decode_response( $response, 'goodsleep_openai_video_status_failed', __( 'No se pudo consultar el estado del video en OpenAI.', 'goodsleep-elementor' ) );
	}

	/**
	 * Descarga el MP4 final del video completado.
	 *
	 * @param string $task_id ID del video.
	 * @return string|WP_Error
	 */
	public function download_video_content( $task_id ) {
		$api_key = goodsleep_get_setting( 'openai_api_key', '' );
		$base    = untrailingslashit( goodsleep_get_setting( 'openai_base_url', '' ) );
		$path    = goodsleep_get_setting( 'openai_video_content_path', '/videos/%s/content' );
		$url     = $base . sprintf( $path, rawurlencode( (string) $task_id ) );

		if ( '' === $api_key || '' === $url || '' === (string) $task_id ) {
			return new WP_Error( 'goodsleep_missing_openai_config', __( 'OpenAI Sora no esta configurado.', 'goodsleep-elementor' ) );
		}

		if ( ! function_exists( 'wp_tempnam' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$temp_path = wp_tempnam( sanitize_file_name( (string) $task_id . '.mp4' ) );
		if ( ! $temp_path ) {
			return new WP_Error( 'goodsleep_video_temp_failed', __( 'No se pudo preparar la descarga del video final.', 'goodsleep-elementor' ) );
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout'  => 180,
				'stream'   => true,
				'filename' => $temp_path,
				'headers'  => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Accept'        => 'video/mp4',
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
			return new WP_Error( 'goodsleep_openai_video_content_failed', __( 'OpenAI no devolvio un archivo MP4 utilizable.', 'goodsleep-elementor' ), array( 'status' => 502, 'provider_status' => $status_code ) );
		}

		return $temp_path;
	}

	/**
	 * Extrae el ID del video.
	 *
	 * @param array<string,mixed> $payload Respuesta.
	 * @return string
	 */
	public function extract_task_id( $payload ) {
		foreach ( array( 'id', 'video_id', 'videoId' ) as $candidate ) {
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
	 * @param array<string,mixed> $payload Respuesta.
	 * @return string
	 */
	public function extract_status( $payload ) {
		foreach ( array( 'status', 'state' ) as $candidate ) {
			if ( ! empty( $payload[ $candidate ] ) ) {
				$status = strtolower( sanitize_text_field( (string) $payload[ $candidate ] ) );

				if ( 'in_progress' === $status ) {
					return 'processing';
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
	 * @param array<string,mixed> $payload Respuesta.
	 * @return string
	 */
	public function extract_video_url( $payload ) {
		$keys = array( 'url', 'output_url', 'video_url', 'download_url', 'downloadUrl' );

		foreach ( $keys as $key ) {
			if ( ! empty( $payload[ $key ] ) && is_string( $payload[ $key ] ) ) {
				return esc_url_raw( $payload[ $key ] );
			}
		}

		if ( ! empty( $payload['output'] ) && is_array( $payload['output'] ) ) {
			foreach ( $payload['output'] as $output_item ) {
				if ( is_array( $output_item ) ) {
					$url = $this->extract_video_url( $output_item );
					if ( '' !== $url ) {
						return $url;
					}
				}
			}
		}

		if ( ! empty( $payload['data'] ) && is_array( $payload['data'] ) ) {
			return $this->extract_video_url( $payload['data'] );
		}

		return '';
	}

	/**
	 * Decodifica una respuesta HTTP con manejo basico de errores.
	 *
	 * @param array<string,mixed>|WP_Error $response Respuesta.
	 * @param string                       $error_key Codigo.
	 * @param string                       $fallback  Mensaje por defecto.
	 * @return array<string,mixed>|WP_Error
	 */
	protected function decode_response( $response, $error_key, $fallback ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		$decoded     = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $status_code >= 400 ) {
			return new WP_Error( $error_key, $this->extract_error_message( $decoded, $fallback ), array( 'status' => 502, 'provider_status' => $status_code ) );
		}

		if ( ! is_array( $decoded ) ) {
			return new WP_Error( $error_key, $fallback );
		}

		return $decoded;
	}

	/**
	 * Extrae un mensaje legible.
	 *
	 * @param array<string,mixed>|null $payload Respuesta.
	 * @param string                   $fallback Fallback.
	 * @return string
	 */
	protected function extract_error_message( $payload, $fallback ) {
		if ( ! is_array( $payload ) ) {
			return $fallback;
		}

		foreach ( array( 'message', 'error', 'detail' ) as $key ) {
			if ( ! empty( $payload[ $key ] ) && is_string( $payload[ $key ] ) ) {
				return sanitize_text_field( $payload[ $key ] );
			}
		}

		if ( ! empty( $payload['error'] ) && is_array( $payload['error'] ) ) {
			return $this->extract_error_message( $payload['error'], $fallback );
		}

		if ( ! empty( $payload['data'] ) && is_array( $payload['data'] ) ) {
			return $this->extract_error_message( $payload['data'], $fallback );
		}

		return $fallback;
	}
}
