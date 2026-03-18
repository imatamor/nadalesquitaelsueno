<?php
/**
 * Mezcla locucion y musica usando ffmpeg.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Goodsleep_Elementor_Audio_Mixer {
	/**
	 * Mezcla la locucion generada con un track musical.
	 *
	 * @param array<string,mixed> $voice_source Fuente de audio TTS.
	 * @param array<string,mixed> $track        Track seleccionado.
	 * @param string              $base_name    Nombre base para archivos temporales.
	 * @return array<string,string>|WP_Error
	 */
	public function mix_generated_audio( $voice_source, $track, $base_name ) {
		$this->ensure_wp_file_functions();

		if ( ! $this->can_run_shell_commands() ) {
			return new WP_Error( 'goodsleep_exec_missing', __( 'La ejecucion de comandos no esta disponible en el servidor.', 'goodsleep-elementor' ) );
		}

		if ( ! $this->is_available() ) {
			return new WP_Error( 'goodsleep_ffmpeg_missing', __( 'ffmpeg no esta disponible para mezclar el audio.', 'goodsleep-elementor' ) );
		}

		$voice_format = ! empty( $voice_source['audio_format'] ) ? sanitize_key( $voice_source['audio_format'] ) : 'mp3';
		$voice_path   = $this->create_voice_source_file( $voice_source, $base_name, $voice_format );

		if ( is_wp_error( $voice_path ) ) {
			return $voice_path;
		}

		$track_source = $this->resolve_track_file( $track );
		if ( is_wp_error( $track_source ) ) {
			@unlink( $voice_path );
			return $track_source;
		}

		$track_path   = $track_source['path'];
		$cleanup_track = ! empty( $track_source['cleanup'] );

		$duration = $this->probe_duration( $voice_path );
		if ( is_wp_error( $duration ) ) {
			@unlink( $voice_path );
			if ( $cleanup_track && file_exists( $track_path ) ) {
				@unlink( $track_path );
			}
			return $duration;
		}

		$output_path = $this->create_temp_file_path( $base_name . '-mix.mp3' );
		if ( ! $output_path ) {
			@unlink( $voice_path );
			return new WP_Error( 'goodsleep_mix_temp_failed', __( 'No se pudo crear un archivo temporal para la mezcla.', 'goodsleep-elementor' ) );
		}

		$fade_duration = min( 2, max( 0.1, $duration ) );
		$fade_start    = max( 0, $duration - $fade_duration );
		$mix_command   = sprintf(
			'ffmpeg -y -i %1$s -stream_loop -1 -i %2$s -filter_complex %3$s -map "[mix]" -c:a libmp3lame -b:a 192k %4$s 2>&1',
			escapeshellarg( $voice_path ),
			escapeshellarg( $track_path ),
			escapeshellarg(
				sprintf(
					'[1:a]atrim=0:%1$.3F,afade=t=out:st=%2$.3F:d=%3$.3F,volume=0.18[music];[0:a][music]amix=inputs=2:duration=first:dropout_transition=0[mix]',
					$duration,
					$fade_start,
					$fade_duration
				)
			),
			escapeshellarg( $output_path )
		);

		$command_output = array();
		$command_code   = 0;
		exec( $mix_command, $command_output, $command_code );

		@unlink( $voice_path );
		if ( $cleanup_track && file_exists( $track_path ) ) {
			@unlink( $track_path );
		}

		if ( 0 !== $command_code || ! file_exists( $output_path ) || 0 === filesize( $output_path ) ) {
			@unlink( $output_path );

			return new WP_Error(
				'goodsleep_mix_failed',
				__( 'No se pudo mezclar la locucion con la musica.', 'goodsleep-elementor' ),
				array(
					'output' => implode( "\n", $command_output ),
				)
			);
		}

		return array(
			'path'   => $output_path,
			'format' => 'mp3',
		);
	}

	/**
	 * Determina si ffmpeg esta disponible.
	 *
	 * @return bool
	 */
	public function is_available() {
		$null_device = strtoupper( substr( PHP_OS, 0, 3 ) ) === 'WIN' ? 'NUL' : '/dev/null';
		$command     = 'ffmpeg -version > ' . $null_device . ' 2>&1';
		$exit_code   = 1;

		exec( $command, $output, $exit_code );

		return 0 === (int) $exit_code;
	}

	/**
	 * Determina si el servidor permite ejecutar comandos de shell.
	 *
	 * @return bool
	 */
	protected function can_run_shell_commands() {
		if ( ! function_exists( 'exec' ) ) {
			return false;
		}

		$disabled = (string) ini_get( 'disable_functions' );
		if ( '' === $disabled ) {
			return true;
		}

		$disabled_functions = array_map( 'trim', explode( ',', $disabled ) );

		return ! in_array( 'exec', $disabled_functions, true );
	}

	/**
	 * Resuelve el archivo local del track.
	 *
	 * @param array<string,mixed> $track Track seleccionado.
	 * @return array<string,mixed>|WP_Error
	 */
	protected function resolve_track_file( $track ) {
		$this->ensure_wp_file_functions();

		$attachment_id = ! empty( $track['attachment_id'] ) ? absint( $track['attachment_id'] ) : 0;

		if ( $attachment_id ) {
			$filepath = get_attached_file( $attachment_id );

			if ( $filepath && file_exists( $filepath ) ) {
				return array(
					'path'    => $filepath,
					'cleanup' => false,
				);
			}
		}

		if ( ! empty( $track['url'] ) ) {
			$temp_path = $this->create_temp_file_path( wp_basename( $track['url'] ) );

			if ( ! $temp_path ) {
				return new WP_Error( 'goodsleep_track_temp_failed', __( 'No se pudo preparar el track musical.', 'goodsleep-elementor' ) );
			}

			$response = wp_remote_get(
				esc_url_raw( $track['url'] ),
				array(
					'timeout' => 45,
				)
			);

			if ( is_wp_error( $response ) ) {
				@unlink( $temp_path );
				return $response;
			}

			$content = wp_remote_retrieve_body( $response );
			if ( '' === $content ) {
				@unlink( $temp_path );
				return new WP_Error( 'goodsleep_track_empty', __( 'El track musical seleccionado no tiene un audio valido.', 'goodsleep-elementor' ) );
			}

			if ( false === file_put_contents( $temp_path, $content ) ) {
				@unlink( $temp_path );
				return new WP_Error( 'goodsleep_track_write_failed', __( 'No se pudo copiar el track musical temporal.', 'goodsleep-elementor' ) );
			}

			return array(
				'path'    => $temp_path,
				'cleanup' => true,
			);
		}

		return new WP_Error( 'goodsleep_track_missing', __( 'No se encontro el archivo del track musical.', 'goodsleep-elementor' ) );
	}

	/**
	 * Crea un archivo temporal para la locucion.
	 *
	 * @param array<string,mixed> $voice_source Fuente de audio.
	 * @param string              $base_name    Nombre base.
	 * @param string              $format       Formato detectado.
	 * @return string|WP_Error
	 */
	protected function create_voice_source_file( $voice_source, $base_name, $format ) {
		$this->ensure_wp_file_functions();

		$extension = in_array( $format, array( 'mp3', 'wav', 'ogg', 'aac', 'pcm' ), true ) ? $format : 'mp3';
		$temp_path = $this->create_temp_file_path( $base_name . '-voice.' . $extension );

		if ( ! $temp_path ) {
			return new WP_Error( 'goodsleep_voice_temp_failed', __( 'No se pudo preparar la locucion generada.', 'goodsleep-elementor' ) );
		}

		$content = '';

		if ( ! empty( $voice_source['audio_data'] ) ) {
			$content = base64_decode( $voice_source['audio_data'], true );
		} elseif ( ! empty( $voice_source['audio_url'] ) ) {
			$response = wp_remote_get(
				esc_url_raw( $voice_source['audio_url'] ),
				array(
					'timeout' => 45,
				)
			);

			if ( is_wp_error( $response ) ) {
				@unlink( $temp_path );
				return $response;
			}

			$content = wp_remote_retrieve_body( $response );
		}

		if ( ! $content ) {
			@unlink( $temp_path );
			return new WP_Error( 'goodsleep_voice_empty', __( 'No se pudo obtener la locucion generada.', 'goodsleep-elementor' ) );
		}

		if ( false === file_put_contents( $temp_path, $content ) ) {
			@unlink( $temp_path );
			return new WP_Error( 'goodsleep_voice_write_failed', __( 'No se pudo preparar la locucion temporal.', 'goodsleep-elementor' ) );
		}

		return $temp_path;
	}

	/**
	 * Obtiene la duracion del archivo usando ffprobe.
	 *
	 * @param string $filepath Archivo a inspeccionar.
	 * @return float|WP_Error
	 */
	protected function probe_duration( $filepath ) {
		if ( ! $this->can_run_shell_commands() ) {
			return new WP_Error( 'goodsleep_exec_missing', __( 'La ejecucion de comandos no esta disponible en el servidor.', 'goodsleep-elementor' ) );
		}

		$probe_command = sprintf(
			'ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 %s 2>&1',
			escapeshellarg( $filepath )
		);

		$probe_output = array();
		$probe_code   = 0;
		exec( $probe_command, $probe_output, $probe_code );

		if ( 0 !== $probe_code ) {
			return new WP_Error(
				'goodsleep_probe_failed',
				__( 'No se pudo determinar la duracion de la locucion.', 'goodsleep-elementor' ),
				array(
					'output' => implode( "\n", $probe_output ),
				)
			);
		}

		$duration = isset( $probe_output[0] ) ? (float) trim( $probe_output[0] ) : 0.0;

		if ( $duration <= 0 ) {
			return new WP_Error( 'goodsleep_invalid_duration', __( 'La locucion generada no tiene una duracion valida.', 'goodsleep-elementor' ) );
		}

		return $duration;
	}

	/**
	 * Carga helpers de archivos de WordPress requeridos para wp_tempnam().
	 *
	 * @return void
	 */
	protected function ensure_wp_file_functions() {
		if ( ! function_exists( 'wp_tempnam' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
	}

	/**
	 * Crea un archivo temporal preservando la extension solicitada.
	 *
	 * @param string $filename Nombre base esperado.
	 * @return string
	 */
	protected function create_temp_file_path( $filename ) {
		$this->ensure_wp_file_functions();

		$temp_path = wp_tempnam( sanitize_file_name( $filename ) );
		if ( ! $temp_path ) {
			return '';
		}

		$extension = pathinfo( (string) $filename, PATHINFO_EXTENSION );
		if ( '' === $extension ) {
			return $temp_path;
		}

		$final_path = dirname( $temp_path ) . DIRECTORY_SEPARATOR . wp_unique_filename( dirname( $temp_path ), pathinfo( $temp_path, PATHINFO_FILENAME ) . '.' . $extension );
		@unlink( $temp_path );

		return $final_path;
	}
}
