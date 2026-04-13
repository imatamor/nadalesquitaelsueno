<?php
/**
 * Pantalla interna para importar historias desde SQL.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Goodsleep_Elementor_Internal_Story_Tool {
	/**
	 * Hook de cron para la cola interna.
	 *
	 * @var string
	 */
	protected $cron_hook = 'goodsleep_internal_story_tool_process_queue';

	/**
	 * Opcion donde se guardan los jobs.
	 *
	 * @var string
	 */
	protected $jobs_option_key = 'goodsleep_internal_story_jobs';

	/**
	 * Servicio de generacion de historias.
	 *
	 * @var Goodsleep_Elementor_Story_Generator
	 */
	protected $story_generator;

	/**
	 * Cliente OpenAI de texto.
	 *
	 * @var Goodsleep_Elementor_OpenAI_Text_Client
	 */
	protected $openai_text_client;

	/**
	 * Parser SQL.
	 *
	 * @var Goodsleep_Elementor_SQL_Import_Parser
	 */
	protected $sql_parser;

	/**
	 * Slug de submenu.
	 *
	 * @var string
	 */
	protected $page_slug = 'goodsleep-internal-story-tool';

	/**
	 * Constructor.
	 *
	 * @param Goodsleep_Elementor_Story_Generator   $story_generator Servicio base.
	 * @param Goodsleep_Elementor_OpenAI_Text_Client $openai_text_client Cliente OpenAI.
	 * @param Goodsleep_Elementor_SQL_Import_Parser  $sql_parser Parser SQL.
	 */
	public function __construct( Goodsleep_Elementor_Story_Generator $story_generator, Goodsleep_Elementor_OpenAI_Text_Client $openai_text_client, Goodsleep_Elementor_SQL_Import_Parser $sql_parser ) {
		$this->story_generator    = $story_generator;
		$this->openai_text_client = $openai_text_client;
		$this->sql_parser         = $sql_parser;

		add_action( 'admin_menu', array( $this, 'register_submenu' ) );
		add_action( $this->cron_hook, array( $this, 'process_queue' ) );
	}

	/**
	 * Registra la pantalla interna debajo del plugin.
	 *
	 * @return void
	 */
	public function register_submenu() {
		add_submenu_page(
			'goodsleep-elementor',
			__( 'Generador interno desde SQL', 'goodsleep-elementor' ),
			__( 'Generador interno', 'goodsleep-elementor' ),
			'manage_options',
			$this->page_slug,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Renderiza la herramienta interna y procesa acciones del formulario.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permisos para acceder a esta herramienta.', 'goodsleep-elementor' ) );
		}

		$state = array(
			'notices' => array(),
			'results' => array(),
		);

		if ( 'POST' === strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) && ! empty( $_POST['goodsleep_internal_action'] ) ) {
			$state = $this->handle_postback();
		}

		$dataset_token = ! empty( $state['dataset_token'] ) ? $state['dataset_token'] : ( ! empty( $_REQUEST['dataset_token'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['dataset_token'] ) ) : '' );
		$dataset       = $dataset_token ? $this->load_dataset( $dataset_token ) : array();
		$mapping       = ! empty( $state['mapping'] ) ? $state['mapping'] : $this->get_current_mapping();
		$table_name    = ! empty( $state['table_name'] ) ? $state['table_name'] : $this->resolve_selected_table_name( $dataset, $mapping );

		if ( $table_name && ! empty( $mapping['table_name'] ) && $mapping['table_name'] !== $table_name ) {
			$mapping['table_name'] = $table_name;
		}

		$table = ( $table_name && ! empty( $dataset['tables'][ $table_name ] ) ) ? $dataset['tables'][ $table_name ] : array();
		$jobs  = $this->get_jobs();
		?>
		<div class="wrap goodsleep-internal-tool">
			<h1><?php esc_html_e( 'Generador interno de historias desde SQL', 'goodsleep-elementor' ); ?></h1>
			<p><?php esc_html_e( 'Esta herramienta toma nombre y correo desde el SQL, genera la historia base con OpenAI y luego usa el mismo flujo audio-only actual de Goodsleep sin enviar correos.', 'goodsleep-elementor' ); ?></p>
			<p><strong><?php esc_html_e( 'Ventana fija de fechas:', 'goodsleep-elementor' ); ?></strong> <?php esc_html_e( 'del 1 de febrero al 31 de marzo de 2026.', 'goodsleep-elementor' ); ?></p>

			<?php $this->render_notices( $state['notices'] ); ?>

			<div class="goodsleep-internal-tool__section">
				<h2><?php esc_html_e( '1. Cargar SQL', 'goodsleep-elementor' ); ?></h2>
				<form method="post" enctype="multipart/form-data">
					<?php wp_nonce_field( 'goodsleep_internal_tool', '_goodsleep_internal_nonce' ); ?>
					<input type="hidden" name="goodsleep_internal_action" value="upload_sql">
					<input type="file" name="goodsleep_sql_file" accept=".sql,text/sql,.txt" required>
					<?php submit_button( __( 'Analizar SQL', 'goodsleep-elementor' ), 'secondary', 'submit', false ); ?>
				</form>
			</div>

			<?php if ( ! empty( $dataset['tables'] ) ) : ?>
				<div class="goodsleep-internal-tool__section">
					<h2><?php esc_html_e( '2. Configuracion de mapeo', 'goodsleep-elementor' ); ?></h2>
					<form method="post">
						<?php wp_nonce_field( 'goodsleep_internal_tool', '_goodsleep_internal_nonce' ); ?>
						<input type="hidden" name="goodsleep_internal_action" value="save_mapping">
						<input type="hidden" name="dataset_token" value="<?php echo esc_attr( $dataset_token ); ?>">
						<table class="form-table" role="presentation">
							<tbody>
								<tr>
									<th scope="row"><label for="goodsleep-table-name"><?php esc_html_e( 'Tabla origen', 'goodsleep-elementor' ); ?></label></th>
									<td>
										<select id="goodsleep-table-name" name="mapping[table_name]">
											<?php foreach ( $dataset['tables'] as $candidate_table_name => $candidate_table ) : ?>
												<option value="<?php echo esc_attr( $candidate_table_name ); ?>" <?php selected( $table_name, $candidate_table_name ); ?>>
													<?php echo esc_html( sprintf( '%s (%d filas)', $candidate_table_name, (int) $candidate_table['row_count'] ) ); ?>
												</option>
											<?php endforeach; ?>
										</select>
									</td>
								</tr>
								<?php $this->render_mapping_select_row( 'name_column', __( 'Columna de nombre', 'goodsleep-elementor' ), $table, $mapping ); ?>
								<?php $this->render_mapping_select_row( 'email_column', __( 'Columna de correo electronico', 'goodsleep-elementor' ), $table, $mapping ); ?>
							</tbody>
						</table>
						<p class="description"><?php esc_html_e( 'El nombre se recorta hasta el primer espacio y la historia base se genera automaticamente con OpenAI. Voz, track y frase final usan el flujo normal del plugin.', 'goodsleep-elementor' ); ?></p>
						<?php submit_button( __( 'Guardar mapeo', 'goodsleep-elementor' ), 'secondary', 'submit', false ); ?>
					</form>

					<?php if ( ! empty( $table['sample'] ) ) : ?>
						<h3><?php esc_html_e( 'Vista previa de filas', 'goodsleep-elementor' ); ?></h3>
						<div class="goodsleep-internal-tool__table-preview">
							<table class="widefat striped">
								<thead>
									<tr>
										<?php foreach ( (array) $table['columns'] as $column_name ) : ?>
											<th><?php echo esc_html( $column_name ); ?></th>
										<?php endforeach; ?>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( (array) $table['sample'] as $sample_row ) : ?>
										<tr>
											<?php foreach ( (array) $table['columns'] as $column_name ) : ?>
												<td><?php echo esc_html( $this->truncate_preview_value( isset( $sample_row[ $column_name ] ) ? $sample_row[ $column_name ] : '' ) ); ?></td>
											<?php endforeach; ?>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					<?php endif; ?>
				</div>

				<div class="goodsleep-internal-tool__section">
					<h2><?php esc_html_e( '3. Prueba individual', 'goodsleep-elementor' ); ?></h2>
					<form method="post">
						<?php wp_nonce_field( 'goodsleep_internal_tool', '_goodsleep_internal_nonce' ); ?>
						<input type="hidden" name="goodsleep_internal_action" value="run_test">
						<input type="hidden" name="dataset_token" value="<?php echo esc_attr( $dataset_token ); ?>">
						<input type="hidden" name="mapping_payload" value="<?php echo esc_attr( wp_json_encode( $mapping ) ); ?>">
						<p>
							<label for="goodsleep-test-row-index"><?php esc_html_e( 'Fila a probar', 'goodsleep-elementor' ); ?></label><br>
							<input id="goodsleep-test-row-index" type="number" min="1" max="<?php echo esc_attr( ! empty( $table['row_count'] ) ? (int) $table['row_count'] : 1 ); ?>" name="row_index" value="1">
						</p>
						<p>
							<label for="goodsleep-test-prompt-override"><?php esc_html_e( 'Override de prompt para esta prueba', 'goodsleep-elementor' ); ?></label><br>
							<textarea id="goodsleep-test-prompt-override" class="large-text code" rows="6" name="prompt_override" placeholder="<?php esc_attr_e( 'Si lo dejas vacio, se usa el prompt global de OpenAI texto.', 'goodsleep-elementor' ); ?>"></textarea>
						</p>
						<?php submit_button( __( 'Generar prueba individual', 'goodsleep-elementor' ), 'primary', 'submit', false ); ?>
					</form>
				</div>

				<div class="goodsleep-internal-tool__section">
					<h2><?php esc_html_e( '4. Job para cron', 'goodsleep-elementor' ); ?></h2>
					<form method="post">
						<?php wp_nonce_field( 'goodsleep_internal_tool', '_goodsleep_internal_nonce' ); ?>
						<input type="hidden" name="goodsleep_internal_action" value="create_job">
						<input type="hidden" name="dataset_token" value="<?php echo esc_attr( $dataset_token ); ?>">
						<input type="hidden" name="mapping_payload" value="<?php echo esc_attr( wp_json_encode( $mapping ) ); ?>">
						<p>
							<label for="goodsleep-job-total"><?php esc_html_e( 'Total de historias a generar', 'goodsleep-elementor' ); ?></label><br>
							<input id="goodsleep-job-total" type="number" min="1" name="job_total" value="800">
						</p>
						<p>
							<label for="goodsleep-job-per-run"><?php esc_html_e( 'Historias exitosas por corrida', 'goodsleep-elementor' ); ?></label><br>
							<input id="goodsleep-job-per-run" type="number" min="1" name="job_per_run" value="25">
						</p>
						<p>
							<label for="goodsleep-job-start-row"><?php esc_html_e( 'Empezar desde la fila', 'goodsleep-elementor' ); ?></label><br>
							<input id="goodsleep-job-start-row" type="number" min="1" max="<?php echo esc_attr( ! empty( $table['row_count'] ) ? (int) $table['row_count'] : 1 ); ?>" name="job_start_row" value="1">
						</p>
						<p>
							<label for="goodsleep-job-prompt-override"><?php esc_html_e( 'Override de prompt para este job', 'goodsleep-elementor' ); ?></label><br>
							<textarea id="goodsleep-job-prompt-override" class="large-text code" rows="6" name="prompt_override" placeholder="<?php esc_attr_e( 'Si lo dejas vacio, se usa el prompt global de OpenAI texto.', 'goodsleep-elementor' ); ?>"></textarea>
						</p>
						<p class="description"><?php esc_html_e( 'El job se procesa por cron con lock para evitar solapes. Las filas sin nombre valido se omiten automaticamente y se sigue con la siguiente.', 'goodsleep-elementor' ); ?></p>
						<?php submit_button( __( 'Crear job', 'goodsleep-elementor' ), 'primary', 'submit', false ); ?>
					</form>
				</div>
			<?php endif; ?>

			<?php $this->render_results( $state['results'] ); ?>
			<?php $this->render_jobs_section( $jobs ); ?>
		</div>
		<?php
	}

	/**
	 * Procesa acciones POST de la herramienta.
	 *
	 * @return array<string,mixed>
	 */
	protected function handle_postback() {
		check_admin_referer( 'goodsleep_internal_tool', '_goodsleep_internal_nonce' );

		$action = sanitize_key( (string) wp_unslash( $_POST['goodsleep_internal_action'] ) );

		switch ( $action ) {
			case 'upload_sql':
				return $this->handle_sql_upload();
			case 'save_mapping':
				return $this->handle_mapping_save();
			case 'run_test':
				return $this->handle_test_execution();
			case 'create_job':
				return $this->handle_job_creation();
			default:
				return $this->build_error_state( __( 'Accion interna no reconocida.', 'goodsleep-elementor' ) );
		}
	}

	/**
	 * Procesa la carga del archivo SQL.
	 *
	 * @return array<string,mixed>
	 */
	protected function handle_sql_upload() {
		if ( empty( $_FILES['goodsleep_sql_file']['tmp_name'] ) ) {
			return $this->build_error_state( __( 'Debes seleccionar un archivo SQL.', 'goodsleep-elementor' ) );
		}

		$file_contents = file_get_contents( $_FILES['goodsleep_sql_file']['tmp_name'] );
		if ( false === $file_contents ) {
			return $this->build_error_state( __( 'No se pudo leer el archivo SQL subido.', 'goodsleep-elementor' ) );
		}

		$parsed = $this->sql_parser->parse_dump( $file_contents );
		if ( is_wp_error( $parsed ) ) {
			return $this->build_error_state( $parsed->get_error_message() );
		}

		$token        = 'goodsleep_sql_' . wp_generate_password( 12, false, false );
		$storage_path = $this->store_dataset_on_disk( $token, $parsed );
		if ( is_wp_error( $storage_path ) ) {
			return $this->build_error_state( $storage_path->get_error_message() );
		}

		set_transient(
			$token,
			array(
				'summary'      => $this->build_summary_dataset( $parsed ),
				'storage_path' => $storage_path,
			),
			7 * DAY_IN_SECONDS
		);

		$table_name = $this->select_best_table_name( $parsed );
		$mapping    = $this->merge_mapping_with_table( $this->get_current_mapping(), $table_name, $parsed );
		$this->persist_mapping( $mapping );

		return array(
			'dataset_token' => $token,
			'table_name'    => $table_name,
			'mapping'       => $mapping,
			'notices'       => array(
				array(
					'type' => 'success',
					'text' => __( 'SQL analizado correctamente. Ya puedes revisar el mapeo, probar una fila o crear un job de cron.', 'goodsleep-elementor' ),
				),
			),
			'results'       => array(),
		);
	}

	/**
	 * Guarda el mapeo actual.
	 *
	 * @return array<string,mixed>
	 */
	protected function handle_mapping_save() {
		$dataset_token = ! empty( $_POST['dataset_token'] ) ? sanitize_text_field( wp_unslash( $_POST['dataset_token'] ) ) : '';
		$dataset       = $dataset_token ? $this->load_dataset( $dataset_token ) : array();
		$mapping_input = ! empty( $_POST['mapping'] ) ? (array) wp_unslash( $_POST['mapping'] ) : array();
		$mapping       = $this->sanitize_mapping( $mapping_input );

		if ( empty( $mapping['table_name'] ) || empty( $dataset['tables'][ $mapping['table_name'] ] ) ) {
			return $this->build_error_state( __( 'Selecciona una tabla valida antes de guardar el mapeo.', 'goodsleep-elementor' ), $dataset_token, $mapping );
		}

		$this->persist_mapping( $mapping );

		return array(
			'dataset_token' => $dataset_token,
			'table_name'    => $mapping['table_name'],
			'mapping'       => $mapping,
			'notices'       => array(
				array(
					'type' => 'success',
					'text' => __( 'El mapeo interno quedo guardado.', 'goodsleep-elementor' ),
				),
			),
			'results'       => array(),
		);
	}

	/**
	 * Ejecuta una prueba individual.
	 *
	 * @return array<string,mixed>
	 */
	protected function handle_test_execution() {
		$dataset_token = ! empty( $_POST['dataset_token'] ) ? sanitize_text_field( wp_unslash( $_POST['dataset_token'] ) ) : '';
		$dataset       = $dataset_token ? $this->load_dataset( $dataset_token, true ) : array();
		$mapping       = $this->extract_mapping_from_post();
		$table_name    = ! empty( $mapping['table_name'] ) ? $mapping['table_name'] : $this->resolve_selected_table_name( $dataset, $mapping );
		$table         = ! empty( $dataset['tables'][ $table_name ] ) ? $dataset['tables'][ $table_name ] : array();
		$row_index     = ! empty( $_POST['row_index'] ) ? max( 1, absint( $_POST['row_index'] ) ) : 1;

		if ( empty( $table['rows'][ $row_index - 1 ] ) ) {
			return $this->build_error_state( __( 'La fila solicitada no existe dentro de la tabla seleccionada.', 'goodsleep-elementor' ), $dataset_token, $mapping );
		}

		$this->persist_mapping( $mapping );

		$result = $this->generate_story_from_row(
			$table['rows'][ $row_index - 1 ],
			$mapping,
			array(
				'row_index'       => $row_index,
				'prompt_override' => ! empty( $_POST['prompt_override'] ) ? wp_unslash( $_POST['prompt_override'] ) : '',
				'job_id'          => '',
			)
		);

		$notices = array(
			array(
				'type' => 'success' === $result['status'] ? 'success' : ( 'skipped' === $result['status'] ? 'warning' : 'error' ),
				'text' => 'success' === $result['status'] ? __( 'La prueba individual se genero correctamente.', 'goodsleep-elementor' ) : ( 'skipped' === $result['status'] ? __( 'La fila de prueba fue omitida por datos insuficientes.', 'goodsleep-elementor' ) : $result['message'] ),
			),
		);

		return array(
			'dataset_token' => $dataset_token,
			'table_name'    => $table_name,
			'mapping'       => $mapping,
			'notices'       => $notices,
			'results'       => array(
				'test' => $result,
			),
		);
	}

	/**
	 * Crea un job persistente para procesamiento por cron.
	 *
	 * @return array<string,mixed>
	 */
	protected function handle_job_creation() {
		$dataset_token   = ! empty( $_POST['dataset_token'] ) ? sanitize_text_field( wp_unslash( $_POST['dataset_token'] ) ) : '';
		$dataset         = $dataset_token ? $this->load_dataset( $dataset_token ) : array();
		$mapping         = $this->extract_mapping_from_post();
		$table_name      = ! empty( $mapping['table_name'] ) ? $mapping['table_name'] : $this->resolve_selected_table_name( $dataset, $mapping );
		$table           = ! empty( $dataset['tables'][ $table_name ] ) ? $dataset['tables'][ $table_name ] : array();
		$total           = ! empty( $_POST['job_total'] ) ? max( 1, absint( $_POST['job_total'] ) ) : 1;
		$per_run         = ! empty( $_POST['job_per_run'] ) ? max( 1, absint( $_POST['job_per_run'] ) ) : 1;
		$start_row       = ! empty( $_POST['job_start_row'] ) ? max( 1, absint( $_POST['job_start_row'] ) ) : 1;
		$prompt_override = ! empty( $_POST['prompt_override'] ) ? sanitize_textarea_field( wp_unslash( $_POST['prompt_override'] ) ) : '';

		if ( empty( $table ) ) {
			return $this->build_error_state( __( 'No se encontro la tabla seleccionada para crear el job.', 'goodsleep-elementor' ), $dataset_token, $mapping );
		}

		if ( $start_row > (int) $table['row_count'] ) {
			return $this->build_error_state( __( 'La fila inicial del job supera la cantidad de filas disponibles.', 'goodsleep-elementor' ), $dataset_token, $mapping );
		}

		$this->persist_mapping( $mapping );

		$job_id = 'job-' . gmdate( 'Ymd-His' ) . '-' . wp_generate_password( 6, false, false );
		$job    = array(
			'id'              => $job_id,
			'status'          => 'active',
			'dataset_token'   => $dataset_token,
			'table_name'      => $table_name,
			'mapping'         => $mapping,
			'prompt_override' => $prompt_override,
			'target_total'    => $total,
			'per_run'         => $per_run,
			'row_cursor'      => $start_row - 1,
			'created_at'      => current_time( 'mysql' ),
			'updated_at'      => current_time( 'mysql' ),
			'processed_rows'  => 0,
			'success_count'   => 0,
			'error_count'     => 0,
			'skipped_count'   => 0,
			'last_message'    => __( 'Job creado y pendiente de cron.', 'goodsleep-elementor' ),
			'last_results'    => array(),
		);

		$jobs             = $this->get_jobs();
		$jobs[ $job_id ]  = $job;
		$this->save_jobs( $jobs );
		$this->schedule_next_queue_run();

		return array(
			'dataset_token' => $dataset_token,
			'table_name'    => $table_name,
			'mapping'       => $mapping,
			'notices'       => array(
				array(
					'type' => 'success',
					'text' => sprintf(
						__( 'Job %1$s creado. Objetivo: %2$d historias. Historias por corrida: %3$d.', 'goodsleep-elementor' ),
						$job_id,
						$total,
						$per_run
					),
				),
			),
			'results'       => array(
				'job' => $job,
			),
		);
	}

	/**
	 * Procesa un job activo desde cron.
	 *
	 * @return void
	 */
	public function process_queue() {
		$lock_key = 'goodsleep_internal_story_tool_lock';
		if ( get_transient( $lock_key ) ) {
			return;
		}

		set_transient( $lock_key, 1, 10 * MINUTE_IN_SECONDS );

		$jobs   = $this->get_jobs();
		$job_id = $this->find_next_active_job_id( $jobs );

		if ( '' === $job_id ) {
			delete_transient( $lock_key );
			return;
		}

		$job           = $this->process_single_job_run( $jobs[ $job_id ] );
		$jobs[ $job_id ] = $job;
		$this->save_jobs( $jobs );

		if ( $this->has_active_jobs( $jobs ) ) {
			$this->schedule_next_queue_run();
		}

		delete_transient( $lock_key );
	}

	/**
	 * Ejecuta una corrida de un job.
	 *
	 * @param array<string,mixed> $job Job actual.
	 * @return array<string,mixed>
	 */
	protected function process_single_job_run( $job ) {
		$dataset = $this->load_dataset( (string) $job['dataset_token'], true );
		if ( empty( $dataset['tables'][ $job['table_name'] ]['rows'] ) ) {
			$job['status']       = 'failed';
			$job['updated_at']   = current_time( 'mysql' );
			$job['last_message'] = __( 'No se pudo cargar el dataset del job o ya expiro.', 'goodsleep-elementor' );

			return $job;
		}

		$table            = $dataset['tables'][ $job['table_name'] ];
		$rows             = ! empty( $table['rows'] ) ? array_values( (array) $table['rows'] ) : array();
		$row_count        = count( $rows );
		$row_cursor       = isset( $job['row_cursor'] ) ? max( 0, absint( $job['row_cursor'] ) ) : 0;
		$success_this_run = 0;
		$results          = array();

		while ( $row_cursor < $row_count && $success_this_run < (int) $job['per_run'] && (int) $job['success_count'] < (int) $job['target_total'] ) {
			$result = $this->generate_story_from_row(
				$rows[ $row_cursor ],
				(array) $job['mapping'],
				array(
					'row_index'       => $row_cursor + 1,
					'prompt_override' => (string) $job['prompt_override'],
					'job_id'          => (string) $job['id'],
				)
			);

			$row_cursor++;
			$job['processed_rows'] = (int) $job['processed_rows'] + 1;
			$results[]             = $result;

			if ( 'success' === $result['status'] ) {
				$job['success_count'] = (int) $job['success_count'] + 1;
				$success_this_run++;
			} elseif ( 'skipped' === $result['status'] ) {
				$job['skipped_count'] = (int) $job['skipped_count'] + 1;
			} else {
				$job['error_count'] = (int) $job['error_count'] + 1;
			}
		}

		$job['row_cursor']   = $row_cursor;
		$job['updated_at']   = current_time( 'mysql' );
		$job['last_results'] = array_slice( $results, -10 );

		if ( (int) $job['success_count'] >= (int) $job['target_total'] ) {
			$job['status']       = 'completed';
			$job['last_message'] = __( 'Job completado con el total solicitado.', 'goodsleep-elementor' );
		} elseif ( $row_cursor >= $row_count ) {
			$job['status']       = 'completed';
			$job['last_message'] = __( 'Job completado porque ya no quedan mas filas disponibles.', 'goodsleep-elementor' );
		} else {
			$job['status']       = 'active';
			$job['last_message'] = sprintf(
				__( 'Corrida completada. Historias creadas en esta corrida: %1$d. Total exitoso acumulado: %2$d.', 'goodsleep-elementor' ),
				$success_this_run,
				(int) $job['success_count']
			);
		}

		return $job;
	}

	/**
	 * Genera una historia a partir de una fila del SQL.
	 *
	 * @param array<string,string> $row Fila origen.
	 * @param array<string,string> $mapping Mapeo configurado.
	 * @param array<string,mixed> $context Contexto de ejecucion.
	 * @return array<string,mixed>
	 */
	protected function generate_story_from_row( $row, $mapping, $context = array() ) {
		$mapped_data = $this->map_row_to_story_data( $row, $mapping );

		if ( '' === $mapped_data['name'] ) {
			return array(
				'status'        => 'skipped',
				'row_index'     => (int) $context['row_index'],
				'mapped_data'   => $mapped_data,
				'prompt_text'   => '',
				'story_text'    => '',
				'phrase_text'   => '',
				'combined_text' => '',
				'post_date'     => '',
				'message'       => __( 'La fila se omitio porque no tiene un nombre utilizable.', 'goodsleep-elementor' ),
			);
		}

		if ( '' === $mapped_data['email'] || ! is_email( $mapped_data['email'] ) ) {
			return array(
				'status'        => 'error',
				'row_index'     => (int) $context['row_index'],
				'mapped_data'   => $mapped_data,
				'prompt_text'   => '',
				'story_text'    => '',
				'phrase_text'   => '',
				'combined_text' => '',
				'post_date'     => '',
				'message'       => __( 'La fila no tiene un correo valido.', 'goodsleep-elementor' ),
			);
		}

		$prompt_text = goodsleep_render_template_placeholders(
			$this->resolve_prompt_text( ! empty( $context['prompt_override'] ) ? $context['prompt_override'] : '' ),
			$this->build_prompt_context( $row, $mapped_data )
		);
		$story_text = $this->openai_text_client->generate_story_text( $prompt_text );

		if ( is_wp_error( $story_text ) ) {
			return array(
				'status'        => 'error',
				'row_index'     => (int) $context['row_index'],
				'mapped_data'   => $mapped_data,
				'prompt_text'   => $prompt_text,
				'story_text'    => '',
				'phrase_text'   => $mapped_data['name'],
				'combined_text' => '',
				'post_date'     => '',
				'message'       => $story_text->get_error_message(),
			);
		}

		$story_text = sanitize_textarea_field( (string) $story_text );
		if ( '' === $story_text ) {
			return array(
				'status'        => 'error',
				'row_index'     => (int) $context['row_index'],
				'mapped_data'   => $mapped_data,
				'prompt_text'   => $prompt_text,
				'story_text'    => '',
				'phrase_text'   => $mapped_data['name'],
				'combined_text' => '',
				'post_date'     => '',
				'message'       => __( 'OpenAI no devolvio una historia utilizable.', 'goodsleep-elementor' ),
			);
		}

		if ( strlen( $story_text ) > 500 ) {
			return array(
				'status'        => 'error',
				'row_index'     => (int) $context['row_index'],
				'mapped_data'   => $mapped_data,
				'prompt_text'   => $prompt_text,
				'story_text'    => $story_text,
				'phrase_text'   => $mapped_data['name'],
				'combined_text' => trim( $story_text . "\n" . $mapped_data['name'] ),
				'post_date'     => '',
				'message'       => __( 'La historia generada supera el maximo permitido de 500 caracteres.', 'goodsleep-elementor' ),
			);
		}

		$post_date    = $this->generate_random_story_date_2026();
		$story_result = $this->story_generator->generate_story(
			array(
				'name'            => $mapped_data['name'],
				'email'           => $mapped_data['email'],
				'story_text'      => $story_text,
				'phrase_template' => '%s',
				'accepted_terms'  => true,
			),
			array(
				'send_email'         => false,
				'enforce_rate_limit' => false,
				'bypass_terms'       => true,
				'post_date'          => $post_date,
				'source'             => 'internal_sql_tool',
				'batch_id'           => ! empty( $context['job_id'] ) ? (string) $context['job_id'] : '',
				'import_row_index'   => (int) $context['row_index'],
				'openai_prompt'      => $prompt_text,
			)
		);

		if ( is_wp_error( $story_result ) ) {
			return array(
				'status'        => 'error',
				'row_index'     => (int) $context['row_index'],
				'mapped_data'   => $mapped_data,
				'prompt_text'   => $prompt_text,
				'story_text'    => $story_text,
				'phrase_text'   => $mapped_data['name'],
				'combined_text' => trim( $story_text . "\n" . $mapped_data['name'] ),
				'post_date'     => $post_date,
				'message'       => $story_result->get_error_message(),
			);
		}

		return array(
			'status'        => 'success',
			'row_index'     => (int) $context['row_index'],
			'mapped_data'   => $mapped_data,
			'prompt_text'   => $prompt_text,
			'story_text'    => $story_text,
			'phrase_text'   => $mapped_data['name'],
			'combined_text' => trim( $story_text . "\n" . $mapped_data['name'] ),
			'post_date'     => $post_date,
			'result'        => $story_result,
			'message'       => __( 'Historia creada correctamente.', 'goodsleep-elementor' ),
		);
	}

	/**
	 * Mapea una fila SQL al payload del generador.
	 *
	 * @param array<string,string> $row Fila origen.
	 * @param array<string,string> $mapping Mapeo configurado.
	 * @return array<string,string>
	 */
	protected function map_row_to_story_data( $row, $mapping ) {
		$raw_name = $this->get_row_value( $row, $mapping, 'name_column' );

		return array(
			'name'      => $this->normalize_first_name( $raw_name ),
			'full_name' => sanitize_text_field( (string) $raw_name ),
			'email'     => goodsleep_normalize_email( $this->get_row_value( $row, $mapping, 'email_column' ) ),
		);
	}

	/**
	 * Construye el contexto de placeholders del prompt.
	 *
	 * @param array<string,string> $row Fila cruda.
	 * @param array<string,string> $mapped_data Valores ya mapeados.
	 * @return array<string,string>
	 */
	protected function build_prompt_context( $row, $mapped_data ) {
		$context = array();

		foreach ( $row as $column_name => $value ) {
			$context[ sanitize_key( (string) $column_name ) ] = (string) $value;
		}

		foreach ( $mapped_data as $key => $value ) {
			$context[ sanitize_key( (string) $key ) ] = (string) $value;
		}

		return $context;
	}

	/**
	 * Resuelve prompt global u override puntual.
	 *
	 * @param string $override Prompt puntual.
	 * @return string
	 */
	protected function resolve_prompt_text( $override ) {
		$override = trim( (string) $override );

		return '' !== $override ? $override : (string) goodsleep_get_setting( 'openai_text_prompt', '' );
	}

	/**
	 * Genera una fecha aleatoria dentro de la ventana declarada.
	 *
	 * @return string
	 */
	protected function generate_random_story_date_2026() {
		$start = strtotime( '2026-02-01 00:00:00' );
		$end   = strtotime( '2026-03-31 23:59:59' );
		$stamp = wp_rand( $start, $end );

		return gmdate( 'Y-m-d H:i:s', $stamp - (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) );
	}

	/**
	 * Extrae el primer nombre utilizable.
	 *
	 * @param string $value Nombre completo.
	 * @return string
	 */
	protected function normalize_first_name( $value ) {
		$value = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $value ) ) );
		if ( '' === $value ) {
			return '';
		}

		$parts = explode( ' ', $value );
		$name  = ! empty( $parts[0] ) ? $parts[0] : '';

		return sanitize_text_field( $name );
	}

	/**
	 * Resuelve el valor de una columna mapeada.
	 *
	 * @param array<string,string> $row Fila origen.
	 * @param array<string,string> $mapping Mapeo.
	 * @param string $mapping_key Clave del mapeo.
	 * @return string
	 */
	protected function get_row_value( $row, $mapping, $mapping_key ) {
		$column = ! empty( $mapping[ $mapping_key ] ) ? $mapping[ $mapping_key ] : '';

		return '' !== $column && isset( $row[ $column ] ) ? (string) $row[ $column ] : '';
	}

	/**
	 * Carga el dataset almacenado temporalmente.
	 *
	 * @param string $dataset_token Token transient.
	 * @param bool   $include_rows  Indica si debe cargar las filas completas.
	 * @return array<string,mixed>
	 */
	protected function load_dataset( $dataset_token, $include_rows = false ) {
		$dataset_meta = get_transient( $dataset_token );

		if ( ! is_array( $dataset_meta ) ) {
			return array();
		}

		$summary = ! empty( $dataset_meta['summary'] ) && is_array( $dataset_meta['summary'] ) ? $dataset_meta['summary'] : array();
		if ( ! $include_rows ) {
			return $summary;
		}

		$storage_path = ! empty( $dataset_meta['storage_path'] ) ? (string) $dataset_meta['storage_path'] : '';
		if ( '' === $storage_path || ! file_exists( $storage_path ) || ! is_readable( $storage_path ) ) {
			return array();
		}

		$raw_dataset = file_get_contents( $storage_path );
		if ( false === $raw_dataset ) {
			return array();
		}

		$decoded = json_decode( $raw_dataset, true );

		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Crea una version resumida del dataset para no cargar filas completas en admin.
	 *
	 * @param array<string,mixed> $dataset Dataset completo.
	 * @return array<string,mixed>
	 */
	protected function build_summary_dataset( $dataset ) {
		$summary = array(
			'tables' => array(),
		);

		if ( empty( $dataset['tables'] ) || ! is_array( $dataset['tables'] ) ) {
			return $summary;
		}

		foreach ( $dataset['tables'] as $table_name => $table ) {
			$summary['tables'][ $table_name ] = array(
				'columns'   => ! empty( $table['columns'] ) ? (array) $table['columns'] : array(),
				'row_count' => ! empty( $table['row_count'] ) ? (int) $table['row_count'] : count( ! empty( $table['rows'] ) ? (array) $table['rows'] : array() ),
				'sample'    => ! empty( $table['sample'] ) ? (array) $table['sample'] : array(),
			);
		}

		return $summary;
	}

	/**
	 * Guarda el dataset completo en un archivo temporal.
	 *
	 * @param string              $token   Token de dataset.
	 * @param array<string,mixed> $dataset Dataset completo.
	 * @return string|WP_Error
	 */
	protected function store_dataset_on_disk( $token, $dataset ) {
		$upload_dir = wp_upload_dir();
		if ( ! empty( $upload_dir['error'] ) ) {
			return new WP_Error( 'goodsleep_upload_dir_error', (string) $upload_dir['error'] );
		}

		$directory = trailingslashit( $upload_dir['basedir'] ) . 'goodsleep-internal-tool';
		if ( ! wp_mkdir_p( $directory ) ) {
			return new WP_Error( 'goodsleep_internal_storage_error', __( 'No se pudo crear el directorio temporal para el generador interno.', 'goodsleep-elementor' ) );
		}

		$this->cleanup_dataset_storage( $directory );

		$file_path = trailingslashit( $directory ) . sanitize_file_name( $token ) . '.json';
		$written   = file_put_contents( $file_path, wp_json_encode( $dataset ) );

		if ( false === $written ) {
			return new WP_Error( 'goodsleep_internal_storage_write_error', __( 'No se pudo guardar temporalmente el dataset SQL en disco.', 'goodsleep-elementor' ) );
		}

		return $file_path;
	}

	/**
	 * Elimina datasets temporales antiguos.
	 *
	 * @param string $directory Directorio temporal.
	 * @return void
	 */
	protected function cleanup_dataset_storage( $directory ) {
		$files = glob( trailingslashit( $directory ) . 'goodsleep_sql_*.json' );
		if ( false === $files ) {
			return;
		}

		$expiration = time() - ( 7 * DAY_IN_SECONDS );

		foreach ( $files as $file_path ) {
			if ( ! is_string( $file_path ) || ! file_exists( $file_path ) ) {
				continue;
			}

			$modified_time = filemtime( $file_path );
			if ( false !== $modified_time && $modified_time < $expiration ) {
				wp_delete_file( $file_path );
			}
		}
	}

	/**
	 * Devuelve el mapeo actual almacenado.
	 *
	 * @return array<string,string>
	 */
	protected function get_current_mapping() {
		$mapping = goodsleep_get_setting( 'internal_sql_last_mapping', array() );

		return $this->sanitize_mapping( is_array( $mapping ) ? $mapping : array() );
	}

	/**
	 * Persiste el mapeo dentro de la opcion del plugin.
	 *
	 * @param array<string,string> $mapping Mapeo saneado.
	 * @return void
	 */
	protected function persist_mapping( $mapping ) {
		$settings                              = goodsleep_get_settings();
		$settings['internal_sql_last_mapping'] = $this->sanitize_mapping( $mapping );

		update_option( 'goodsleep_elementor_settings', $settings, false );
	}

	/**
	 * Extrae el mapeo recibido por formulario.
	 *
	 * @return array<string,string>
	 */
	protected function extract_mapping_from_post() {
		if ( ! empty( $_POST['mapping_payload'] ) ) {
			$decoded = json_decode( wp_unslash( $_POST['mapping_payload'] ), true );

			return $this->sanitize_mapping( is_array( $decoded ) ? $decoded : array() );
		}

		if ( ! empty( $_POST['mapping'] ) ) {
			return $this->sanitize_mapping( (array) wp_unslash( $_POST['mapping'] ) );
		}

		return $this->get_current_mapping();
	}

	/**
	 * Sanea el mapeo interno.
	 *
	 * @param array<string,mixed> $mapping Mapeo crudo.
	 * @return array<string,string>
	 */
	protected function sanitize_mapping( $mapping ) {
		$keys = array(
			'table_name',
			'name_column',
			'email_column',
		);
		$result = array();

		foreach ( $keys as $key ) {
			$result[ $key ] = ! empty( $mapping[ $key ] ) ? sanitize_key( (string) $mapping[ $key ] ) : '';
		}

		return $result;
	}

	/**
	 * Selecciona la tabla mas razonable por cantidad de filas.
	 *
	 * @param array<string,mixed> $dataset Dataset SQL.
	 * @return string
	 */
	protected function select_best_table_name( $dataset ) {
		$best_name  = '';
		$best_count = -1;

		if ( empty( $dataset['tables'] ) ) {
			return '';
		}

		foreach ( $dataset['tables'] as $table_name => $table ) {
			$count = ! empty( $table['row_count'] ) ? (int) $table['row_count'] : count( (array) $table['rows'] );
			if ( $count > $best_count ) {
				$best_name  = $table_name;
				$best_count = $count;
			}
		}

		return $best_name;
	}

	/**
	 * Determina la tabla seleccionada o aplica fallback.
	 *
	 * @param array<string,mixed> $dataset Dataset SQL.
	 * @param array<string,string> $mapping Mapeo actual.
	 * @return string
	 */
	protected function resolve_selected_table_name( $dataset, $mapping ) {
		if ( ! empty( $mapping['table_name'] ) && ! empty( $dataset['tables'][ $mapping['table_name'] ] ) ) {
			return $mapping['table_name'];
		}

		return $this->select_best_table_name( $dataset );
	}

	/**
	 * Fusiona mapeo existente con columnas detectadas.
	 *
	 * @param array<string,string> $mapping Mapeo previo.
	 * @param string $table_name Tabla activa.
	 * @param array<string,mixed> $dataset Dataset SQL.
	 * @return array<string,string>
	 */
	protected function merge_mapping_with_table( $mapping, $table_name, $dataset ) {
		$mapping               = $this->sanitize_mapping( $mapping );
		$mapping['table_name'] = $table_name;
		$columns               = ! empty( $dataset['tables'][ $table_name ]['columns'] ) ? (array) $dataset['tables'][ $table_name ]['columns'] : array();

		$mapping_defaults = array(
			'name_column'  => array( 'name', 'nombre', 'first_name', 'fullname', 'full_name' ),
			'email_column' => array( 'email', 'correo', 'mail', 'correo_electronico' ),
		);

		foreach ( $mapping_defaults as $mapping_key => $candidates ) {
			if ( ! empty( $mapping[ $mapping_key ] ) ) {
				continue;
			}

			foreach ( $candidates as $candidate ) {
				if ( in_array( $candidate, $columns, true ) ) {
					$mapping[ $mapping_key ] = $candidate;
					break;
				}
			}
		}

		return $mapping;
	}

	/**
	 * Renderiza una fila select de mapeo.
	 *
	 * @param string $key Clave de mapping.
	 * @param string $label Etiqueta visible.
	 * @param array<string,mixed> $table Tabla actual.
	 * @param array<string,string> $mapping Mapeo seleccionado.
	 * @return void
	 */
	protected function render_mapping_select_row( $key, $label, $table, $mapping ) {
		$columns = ! empty( $table['columns'] ) ? (array) $table['columns'] : array();
		$current = ! empty( $mapping[ $key ] ) ? $mapping[ $key ] : '';
		?>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr( 'goodsleep-' . $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td>
				<select id="<?php echo esc_attr( 'goodsleep-' . $key ); ?>" name="mapping[<?php echo esc_attr( $key ); ?>]">
					<?php foreach ( $columns as $column_name ) : ?>
						<option value="<?php echo esc_attr( $column_name ); ?>" <?php selected( $current, $column_name ); ?>><?php echo esc_html( $column_name ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<?php
	}

	/**
	 * Renderiza notices de exito o error.
	 *
	 * @param array<int,array<string,string>> $notices Notices visibles.
	 * @return void
	 */
	protected function render_notices( $notices ) {
		foreach ( (array) $notices as $notice ) {
			$type = ! empty( $notice['type'] ) ? $notice['type'] : 'info';
			$text = ! empty( $notice['text'] ) ? $notice['text'] : '';

			if ( '' === $text ) {
				continue;
			}

			printf(
				'<div class="notice notice-%1$s"><p>%2$s</p></div>',
				esc_attr( $type ),
				esc_html( $text )
			);
		}
	}

	/**
	 * Renderiza resultados de prueba o creacion de job.
	 *
	 * @param array<string,mixed> $results Resultados acumulados.
	 * @return void
	 */
	protected function render_results( $results ) {
		if ( empty( $results ) ) {
			return;
		}

		if ( ! empty( $results['test'] ) ) {
			echo '<div class="goodsleep-internal-tool__section"><h2>' . esc_html__( 'Resultado de prueba individual', 'goodsleep-elementor' ) . '</h2>';
			$this->render_generation_result_card( $results['test'] );
			echo '</div>';
		}

		if ( ! empty( $results['job'] ) ) {
			echo '<div class="goodsleep-internal-tool__section"><h2>' . esc_html__( 'Job creado', 'goodsleep-elementor' ) . '</h2>';
			echo '<p><strong>' . esc_html__( 'ID del job:', 'goodsleep-elementor' ) . '</strong> ' . esc_html( (string) $results['job']['id'] ) . '</p>';
			echo '<p><strong>' . esc_html__( 'Objetivo:', 'goodsleep-elementor' ) . '</strong> ' . esc_html( (string) $results['job']['target_total'] ) . '</p>';
			echo '<p><strong>' . esc_html__( 'Historias por corrida:', 'goodsleep-elementor' ) . '</strong> ' . esc_html( (string) $results['job']['per_run'] ) . '</p>';
			echo '<p><strong>' . esc_html__( 'Estado:', 'goodsleep-elementor' ) . '</strong> ' . esc_html( (string) $results['job']['status'] ) . '</p>';
			echo '</div>';
		}
	}

	/**
	 * Renderiza la tabla de jobs.
	 *
	 * @param array<string,array<string,mixed>> $jobs Jobs disponibles.
	 * @return void
	 */
	protected function render_jobs_section( $jobs ) {
		echo '<div class="goodsleep-internal-tool__section"><h2>' . esc_html__( 'Jobs de cron', 'goodsleep-elementor' ) . '</h2>';

		if ( empty( $jobs ) ) {
			echo '<p>' . esc_html__( 'Todavia no hay jobs creados.', 'goodsleep-elementor' ) . '</p></div>';
			return;
		}

		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__( 'Job', 'goodsleep-elementor' ) . '</th>';
		echo '<th>' . esc_html__( 'Estado', 'goodsleep-elementor' ) . '</th>';
		echo '<th>' . esc_html__( 'Objetivo', 'goodsleep-elementor' ) . '</th>';
		echo '<th>' . esc_html__( 'Exitosas', 'goodsleep-elementor' ) . '</th>';
		echo '<th>' . esc_html__( 'Errores', 'goodsleep-elementor' ) . '</th>';
		echo '<th>' . esc_html__( 'Omitidas', 'goodsleep-elementor' ) . '</th>';
		echo '<th>' . esc_html__( 'Fila actual', 'goodsleep-elementor' ) . '</th>';
		echo '<th>' . esc_html__( 'Ultimo mensaje', 'goodsleep-elementor' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( array_reverse( $jobs, true ) as $job ) {
			echo '<tr>';
			echo '<td>' . esc_html( (string) $job['id'] ) . '<br><small>' . esc_html( (string) $job['updated_at'] ) . '</small></td>';
			echo '<td>' . esc_html( (string) $job['status'] ) . '</td>';
			echo '<td>' . esc_html( (string) $job['target_total'] ) . ' / ' . esc_html( (string) $job['per_run'] ) . '</td>';
			echo '<td>' . esc_html( (string) $job['success_count'] ) . '</td>';
			echo '<td>' . esc_html( (string) $job['error_count'] ) . '</td>';
			echo '<td>' . esc_html( (string) $job['skipped_count'] ) . '</td>';
			echo '<td>' . esc_html( (string) ( (int) $job['row_cursor'] + 1 ) ) . '</td>';
			echo '<td>' . esc_html( (string) $job['last_message'] ) . '</td>';
			echo '</tr>';

			if ( ! empty( $job['last_results'] ) ) {
				echo '<tr><td colspan="8">';
				echo '<strong>' . esc_html__( 'Ultimos resultados:', 'goodsleep-elementor' ) . '</strong>';
				foreach ( (array) $job['last_results'] as $item ) {
					echo '<div class="goodsleep-internal-tool__result ' . esc_attr( 'success' === $item['status'] ? 'is-success' : ( 'skipped' === $item['status'] ? 'is-warning' : 'is-error' ) ) . '">';
					echo '<strong>' . esc_html( sprintf( __( 'Fila %d', 'goodsleep-elementor' ), (int) $item['row_index'] ) ) . ':</strong> ';
					echo esc_html( (string) $item['message'] );
					echo '</div>';
				}
				echo '</td></tr>';
			}
		}

		echo '</tbody></table>';
		echo '</div>';
	}

	/**
	 * Renderiza la tarjeta individual de una generacion.
	 *
	 * @param array<string,mixed> $item Resultado individual.
	 * @return void
	 */
	protected function render_generation_result_card( $item ) {
		$status_class = 'success' === $item['status'] ? 'is-success' : ( 'skipped' === $item['status'] ? 'is-warning' : 'is-error' );

		echo '<div class="goodsleep-internal-tool__result ' . esc_attr( $status_class ) . '">';
		echo '<h3>' . esc_html( sprintf( __( 'Fila %d', 'goodsleep-elementor' ), (int) $item['row_index'] ) ) . '</h3>';
		echo '<p><strong>' . esc_html__( 'Nombre:', 'goodsleep-elementor' ) . '</strong> ' . esc_html( (string) $item['mapped_data']['name'] ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Email:', 'goodsleep-elementor' ) . '</strong> ' . esc_html( (string) $item['mapped_data']['email'] ) . '</p>';
		if ( ! empty( $item['post_date'] ) ) {
			echo '<p><strong>' . esc_html__( 'Fecha asignada:', 'goodsleep-elementor' ) . '</strong> ' . esc_html( (string) $item['post_date'] ) . '</p>';
		}
		if ( ! empty( $item['prompt_text'] ) ) {
			echo '<p><strong>' . esc_html__( 'Prompt final:', 'goodsleep-elementor' ) . '</strong></p>';
			echo '<pre>' . esc_html( (string) $item['prompt_text'] ) . '</pre>';
		}
		if ( '' !== (string) $item['story_text'] ) {
			echo '<p><strong>' . esc_html__( 'Historia generada:', 'goodsleep-elementor' ) . '</strong></p>';
			echo '<pre>' . esc_html( (string) $item['story_text'] ) . '</pre>';
		}
		if ( '' !== (string) $item['phrase_text'] ) {
			echo '<p><strong>' . esc_html__( 'Frase final:', 'goodsleep-elementor' ) . '</strong> ' . esc_html( (string) $item['phrase_text'] ) . '</p>';
		}
		if ( '' !== (string) $item['combined_text'] ) {
			echo '<p><strong>' . esc_html__( 'Texto combinado:', 'goodsleep-elementor' ) . '</strong></p>';
			echo '<pre>' . esc_html( (string) $item['combined_text'] ) . '</pre>';
		}
		echo '<p><strong>' . esc_html__( 'Resultado:', 'goodsleep-elementor' ) . '</strong> ' . esc_html( (string) $item['message'] ) . '</p>';

		if ( 'success' === $item['status'] && ! empty( $item['result'] ) ) {
			echo '<p><strong>' . esc_html__( 'Historia creada:', 'goodsleep-elementor' ) . '</strong> #' . esc_html( (string) $item['result']['storyId'] ) . '</p>';
			echo '<p><strong>' . esc_html__( 'Correo enviado:', 'goodsleep-elementor' ) . '</strong> ' . esc_html( ! empty( $item['result']['emailSent'] ) ? __( 'Si', 'goodsleep-elementor' ) : __( 'No', 'goodsleep-elementor' ) ) . '</p>';
			echo '<p><strong>' . esc_html__( 'Correo suprimido:', 'goodsleep-elementor' ) . '</strong> ' . esc_html( ! empty( $item['result']['emailSuppressed'] ) ? __( 'Si', 'goodsleep-elementor' ) : __( 'No', 'goodsleep-elementor' ) ) . '</p>';
			echo '<p><a class="button button-secondary" href="' . esc_url( (string) $item['result']['shareUrl'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Abrir historia', 'goodsleep-elementor' ) . '</a></p>';
		}

		echo '</div>';
	}

	/**
	 * Crea un estado de error reutilizable.
	 *
	 * @param string $message Mensaje visible.
	 * @param string $dataset_token Token opcional.
	 * @param array<string,string> $mapping Mapeo opcional.
	 * @return array<string,mixed>
	 */
	protected function build_error_state( $message, $dataset_token = '', $mapping = array() ) {
		return array(
			'dataset_token' => $dataset_token,
			'mapping'       => $mapping,
			'notices'       => array(
				array(
					'type' => 'error',
					'text' => $message,
				),
			),
			'results'       => array(),
		);
	}

	/**
	 * Trunca valores largos para la tabla de preview.
	 *
	 * @param string $value Valor base.
	 * @return string
	 */
	protected function truncate_preview_value( $value ) {
		$value = trim( preg_replace( '/\s+/', ' ', (string) $value ) );

		if ( strlen( $value ) <= 90 ) {
			return $value;
		}

		return substr( $value, 0, 87 ) . '...';
	}

	/**
	 * Devuelve jobs guardados.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	protected function get_jobs() {
		$jobs = get_option( $this->jobs_option_key, array() );

		return is_array( $jobs ) ? $jobs : array();
	}

	/**
	 * Persiste jobs.
	 *
	 * @param array<string,array<string,mixed>> $jobs Jobs saneados.
	 * @return void
	 */
	protected function save_jobs( $jobs ) {
		update_option( $this->jobs_option_key, is_array( $jobs ) ? $jobs : array(), false );
	}

	/**
	 * Busca el siguiente job activo.
	 *
	 * @param array<string,array<string,mixed>> $jobs Jobs existentes.
	 * @return string
	 */
	protected function find_next_active_job_id( $jobs ) {
		foreach ( $jobs as $job_id => $job ) {
			if ( ! empty( $job['status'] ) && 'active' === $job['status'] ) {
				return (string) $job_id;
			}
		}

		return '';
	}

	/**
	 * Devuelve si existen jobs activos.
	 *
	 * @param array<string,array<string,mixed>> $jobs Jobs existentes.
	 * @return bool
	 */
	protected function has_active_jobs( $jobs ) {
		return '' !== $this->find_next_active_job_id( $jobs );
	}

	/**
	 * Programa la siguiente corrida del cron interno si no hay una pendiente.
	 *
	 * @return void
	 */
	protected function schedule_next_queue_run() {
		if ( ! wp_next_scheduled( $this->cron_hook ) ) {
			wp_schedule_single_event( time() + MINUTE_IN_SECONDS, $this->cron_hook );
		}
	}
}
