<?php
defined( 'ABSPATH' ) || exit;

class RMS_Admin {

	public function __construct() {
		add_action( 'admin_menu',            array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_init',            array( $this, 'handle_forms' ) );

		/* AJAX handlers */
		add_action( 'wp_ajax_rms_send_reminder',       array( $this, 'ajax_send_reminder' ) );
		add_action( 'wp_ajax_rms_delete_appointment',  array( $this, 'ajax_delete_appointment' ) );
		add_action( 'wp_ajax_rms_add_procedure',       array( $this, 'ajax_add_procedure' ) );
		add_action( 'wp_ajax_rms_delete_procedure',    array( $this, 'ajax_delete_procedure' ) );
	}

	/* ------------------------------------------------------------------ */
	/* Menu                                                                 */
	/* ------------------------------------------------------------------ */

	public function add_menu() {
		add_menu_page(
			__( 'Sistema de Recordatorios', 'reminder-system' ),
			__( 'Recordatorios', 'reminder-system' ),
			'manage_options',
			'rms-records',
			array( $this, 'page_records' ),
			'dashicons-calendar-alt',
			30
		);

		add_submenu_page(
			'rms-records',
			__( 'Registros de Pacientes', 'reminder-system' ),
			__( 'Registros', 'reminder-system' ),
			'manage_options',
			'rms-records',
			array( $this, 'page_records' )
		);

		add_submenu_page(
			'rms-records',
			__( 'Procedimientos', 'reminder-system' ),
			__( 'Procedimientos', 'reminder-system' ),
			'manage_options',
			'rms-procedures',
			array( $this, 'page_procedures' )
		);

		add_submenu_page(
			'rms-records',
			__( 'Configuración', 'reminder-system' ),
			__( 'Configuración', 'reminder-system' ),
			'manage_options',
			'rms-settings',
			array( $this, 'page_settings' )
		);
	}

	/* ------------------------------------------------------------------ */
	/* Assets                                                               */
	/* ------------------------------------------------------------------ */

	public function enqueue_assets( $hook ) {
		if ( false === strpos( $hook, 'rms-' ) ) {
			return;
		}
		wp_enqueue_style(
			'rms-admin',
			RMS_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			RMS_VERSION
		);
		wp_enqueue_script(
			'rms-admin',
			RMS_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			RMS_VERSION,
			true
		);
		wp_localize_script(
			'rms-admin',
			'rmsAdmin',
			array(
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'nonce'         => wp_create_nonce( 'rms_admin_nonce' ),
				'confirmDelete' => __( '¿Está seguro de que desea eliminar este registro?', 'reminder-system' ),
				'confirmRemind' => __( '¿Enviar recordatorio ahora a este paciente?', 'reminder-system' ),
			)
		);
	}

	/* ------------------------------------------------------------------ */
	/* Form handling (runs on admin_init before page render)               */
	/* ------------------------------------------------------------------ */

	public function handle_forms() {
		if ( ! isset( $_GET['page'] ) || false === strpos( sanitize_text_field( wp_unslash( $_GET['page'] ) ), 'rms-' ) ) {
			return;
		}

		/* Save settings */
		if ( isset( $_POST['rms_save_settings'] ) ) {
			check_admin_referer( 'rms_settings' );
			update_option( 'rms_from_email',     sanitize_email( wp_unslash( $_POST['rms_from_email'] ?? '' ) ) );
			update_option( 'rms_from_name',      sanitize_text_field( wp_unslash( $_POST['rms_from_name'] ?? '' ) ) );
			update_option( 'rms_reminder_hours', sanitize_text_field( wp_unslash( $_POST['rms_reminder_hours'] ?? '24' ) ) );
			wp_safe_redirect(
				add_query_arg( array( 'page' => 'rms-settings', 'saved' => '1' ), admin_url( 'admin.php' ) )
			);
			exit;
		}

		/* Save appointment (edit) */
		if ( isset( $_POST['rms_save_appointment'] ) ) {
			check_admin_referer( 'rms_edit_appointment' );
			$id = absint( $_POST['rms_appointment_id'] ?? 0 );

			$raw_date = sanitize_text_field( wp_unslash( $_POST['appointment_date'] ?? '' ) );
			$parsed   = DateTime::createFromFormat( 'Y-m-d\TH:i', $raw_date );
			if ( ! $parsed ) {
				wp_die( esc_html__( 'Formato de fecha inválido.', 'reminder-system' ) );
			}

			RMS_DB::update(
				$id,
				array(
					'patient_name'     => sanitize_text_field( wp_unslash( $_POST['patient_name'] ?? '' ) ),
					'patient_email'    => sanitize_email( wp_unslash( $_POST['patient_email'] ?? '' ) ),
					'appointment_date' => $parsed->format( 'Y-m-d H:i:s' ),
					'procedure_name'   => sanitize_text_field( wp_unslash( $_POST['procedure_name'] ?? '' ) ),
					'status'           => sanitize_text_field( wp_unslash( $_POST['status'] ?? 'confirmed' ) ),
				)
			);

			wp_safe_redirect(
				add_query_arg( array( 'page' => 'rms-records', 'updated' => '1' ), admin_url( 'admin.php' ) )
			);
			exit;
		}
	}

	/* ------------------------------------------------------------------ */
	/* AJAX                                                                 */
	/* ------------------------------------------------------------------ */

	public function ajax_send_reminder() {
		check_ajax_referer( 'rms_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Sin permisos.' ) );
		}

		$id          = absint( $_POST['id'] ?? 0 );
		$appointment = RMS_DB::get( $id );

		if ( ! $appointment ) {
			wp_send_json_error( array( 'message' => 'Registro no encontrado.' ) );
		}

		$sent = RMS_Email::send_reminder( $appointment );
		if ( $sent ) {
			RMS_DB::mark_reminder_sent( $id );
			wp_send_json_success( array( 'message' => 'Recordatorio enviado exitosamente.' ) );
		} else {
			wp_send_json_error( array( 'message' => 'No se pudo enviar el correo. Verifique la configuración de email.' ) );
		}
	}

	public function ajax_delete_appointment() {
		check_ajax_referer( 'rms_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		RMS_DB::delete( absint( $_POST['id'] ?? 0 ) );
		wp_send_json_success();
	}

	public function ajax_add_procedure() {
		check_ajax_referer( 'rms_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		$name = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
		if ( empty( $name ) ) {
			wp_send_json_error( array( 'message' => 'El nombre no puede estar vacío.' ) );
		}

		$procedures = json_decode( get_option( 'rms_procedures', '[]' ), true );
		if ( in_array( $name, $procedures, true ) ) {
			wp_send_json_error( array( 'message' => 'El procedimiento ya existe.' ) );
		}

		$procedures[] = $name;
		update_option( 'rms_procedures', wp_json_encode( $procedures ) );
		wp_send_json_success( array( 'procedures' => $procedures ) );
	}

	public function ajax_delete_procedure() {
		check_ajax_referer( 'rms_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		$name       = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
		$procedures = json_decode( get_option( 'rms_procedures', '[]' ), true );
		$procedures = array_values( array_filter( $procedures, function ( $p ) use ( $name ) {
			return $p !== $name;
		} ) );

		update_option( 'rms_procedures', wp_json_encode( $procedures ) );
		wp_send_json_success( array( 'procedures' => $procedures ) );
	}

	/* ------------------------------------------------------------------ */
	/* Pages                                                                */
	/* ------------------------------------------------------------------ */

	public function page_records() {
		$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : 'list';
		if ( 'edit' === $action ) {
			$this->render_edit();
		} else {
			$this->render_list();
		}
	}

	private function render_list() {
		$search   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$paged    = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
		$per_page = 20;
		$total    = RMS_DB::count( $search );
		$items    = RMS_DB::get_all( array(
			'search'   => $search,
			'page'     => $paged,
			'per_page' => $per_page,
		) );
		?>
		<div class="wrap rms-wrap">
			<h1 class="wp-heading-inline">
				<span class="dashicons dashicons-calendar-alt" style="vertical-align:middle;font-size:26px;line-height:1;margin-right:6px;color:#1a73e8;"></span>
				<?php esc_html_e( 'Registros de Pacientes', 'reminder-system' ); ?>
			</h1>
			<hr class="wp-header-end">

			<?php if ( isset( $_GET['updated'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p>✅ <?php esc_html_e( 'Registro actualizado correctamente.', 'reminder-system' ); ?></p></div>
			<?php endif; ?>

			<form method="get" class="rms-search-form">
				<input type="hidden" name="page" value="rms-records">
				<div class="rms-search-box">
					<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Buscar por paciente, email o procedimiento…', 'reminder-system' ); ?>">
					<?php submit_button( __( 'Buscar', 'reminder-system' ), 'secondary', '', false ); ?>
				</div>
			</form>

			<table class="wp-list-table widefat fixed striped rms-table">
				<thead>
					<tr>
						<th style="width:50px;">ID</th>
						<th>Paciente</th>
						<th>Email</th>
						<th>Fecha y Hora</th>
						<th>Procedimiento</th>
						<th>Estado</th>
						<th>Recordatorio</th>
					<th>Recordatorio 48h</th>
						<th style="width:220px;">Acciones</th>
					</tr>
				</thead>
				<tbody>
				<?php if ( empty( $items ) ) : ?>
					<tr>
						<td colspan="9" style="text-align:center;padding:40px;color:#888;">
							<?php esc_html_e( 'No hay registros aún.', 'reminder-system' ); ?>
						</td>
					</tr>
				<?php else : foreach ( $items as $item ) : ?>
					<tr id="rms-row-<?php echo absint( $item->id ); ?>">
						<td><?php echo absint( $item->id ); ?></td>
						<td><strong><?php echo esc_html( $item->patient_name ); ?></strong></td>
						<td><?php echo esc_html( $item->patient_email ); ?></td>
						<td><?php echo esc_html( date_i18n( 'd/m/Y H:i', strtotime( $item->appointment_date ) ) ); ?></td>
						<td><?php echo esc_html( $item->procedure_name ); ?></td>
						<td>
							<span class="rms-badge rms-badge-<?php echo esc_attr( $item->status ); ?>">
								<?php echo esc_html( ucfirst( $item->status ) ); ?>
							</span>
						</td>
						<td>
							<?php if ( $item->reminder_sent ) : ?>
								<span class="rms-badge rms-badge-sent">✅ Enviado</span>
								<?php if ( $item->reminder_sent_at ) : ?>
									<br><small style="color:#888;"><?php echo esc_html( date_i18n( 'd/m/Y H:i', strtotime( $item->reminder_sent_at ) ) ); ?></small>
								<?php endif; ?>
							<?php else : ?>
								<span class="rms-badge rms-badge-pending">⏳ Pendiente</span>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( $item->reminder_48h_sent ) : ?>
								<span class="rms-badge rms-badge-sent">✅ Enviado</span>
								<?php if ( $item->reminder_48h_sent_at ) : ?>
									<br><small style="color:#888;"><?php echo esc_html( date_i18n( 'd/m/Y H:i', strtotime( $item->reminder_48h_sent_at ) ) ); ?></small>
								<?php endif; ?>
							<?php else : ?>
								<span class="rms-badge rms-badge-pending">⏳ Pendiente</span>
							<?php endif; ?>
						</td>
						<td class="rms-actions">
							<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'rms-records', 'action' => 'edit', 'id' => $item->id ), admin_url( 'admin.php' ) ) ); ?>"
							   class="button button-small">✏️ Editar</a>
							<button class="button button-small rms-btn-remind" data-id="<?php echo absint( $item->id ); ?>">📧 Recordatorio</button>
							<button class="button button-small button-link-delete rms-btn-delete" data-id="<?php echo absint( $item->id ); ?>">🗑️ Eliminar</button>
						</td>
					</tr>
				<?php endforeach; endif; ?>
				</tbody>
			</table>

			<?php
			$total_pages = (int) ceil( $total / $per_page );
			if ( $total_pages > 1 ) {
				echo '<div class="tablenav bottom"><div class="tablenav-pages">';
				echo wp_kses_post( paginate_links( array(
					'base'    => add_query_arg( 'paged', '%#%' ),
					'format'  => '',
					'current' => $paged,
					'total'   => $total_pages,
				) ) );
				echo '</div></div>';
			}
			?>
			<p class="description"><?php printf( esc_html__( 'Total de registros: %d', 'reminder-system' ), $total ); ?></p>
		</div>
		<?php
	}

	private function render_edit() {
		$id          = absint( $_GET['id'] ?? 0 );
		$appointment = RMS_DB::get( $id );

		if ( ! $appointment ) {
			echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html__( 'Registro no encontrado.', 'reminder-system' ) . '</p></div></div>';
			return;
		}

		$procedures = json_decode( get_option( 'rms_procedures', '["Colonoscopia"]' ), true );
		?>
		<div class="wrap rms-wrap">
			<h1>✏️ <?php esc_html_e( 'Editar Registro', 'reminder-system' ); ?></h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=rms-records' ) ); ?>" class="button">← <?php esc_html_e( 'Volver a Registros', 'reminder-system' ); ?></a>
			<br><br>
			<div class="rms-card">
				<form method="post">
					<?php wp_nonce_field( 'rms_edit_appointment' ); ?>
					<input type="hidden" name="rms_appointment_id" value="<?php echo absint( $appointment->id ); ?>">

					<table class="form-table">
						<tr>
							<th><label for="patient_name"><?php esc_html_e( 'Nombre Completo', 'reminder-system' ); ?></label></th>
							<td><input type="text" id="patient_name" name="patient_name" value="<?php echo esc_attr( $appointment->patient_name ); ?>" class="regular-text" required></td>
						</tr>
						<tr>
							<th><label for="patient_email"><?php esc_html_e( 'Correo Electrónico', 'reminder-system' ); ?></label></th>
							<td><input type="email" id="patient_email" name="patient_email" value="<?php echo esc_attr( $appointment->patient_email ); ?>" class="regular-text" required></td>
						</tr>
						<tr>
							<th><label for="appointment_date"><?php esc_html_e( 'Fecha y Hora', 'reminder-system' ); ?></label></th>
							<td>
								<input type="datetime-local" id="appointment_date" name="appointment_date"
								       value="<?php echo esc_attr( date( 'Y-m-d\TH:i', strtotime( $appointment->appointment_date ) ) ); ?>"
								       required>
							</td>
						</tr>
						<tr>
							<th><label for="procedure_name"><?php esc_html_e( 'Procedimiento', 'reminder-system' ); ?></label></th>
							<td>
								<select id="procedure_name" name="procedure_name" class="regular-text">
									<?php foreach ( $procedures as $proc ) : ?>
										<option value="<?php echo esc_attr( $proc ); ?>" <?php selected( $appointment->procedure_name, $proc ); ?>>
											<?php echo esc_html( $proc ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr>
							<th><label for="status"><?php esc_html_e( 'Estado', 'reminder-system' ); ?></label></th>
							<td>
								<select id="status" name="status">
									<option value="confirmed" <?php selected( $appointment->status, 'confirmed' ); ?>><?php esc_html_e( 'Confirmado', 'reminder-system' ); ?></option>
									<option value="pending"   <?php selected( $appointment->status, 'pending' ); ?>><?php esc_html_e( 'Pendiente', 'reminder-system' ); ?></option>
									<option value="cancelled" <?php selected( $appointment->status, 'cancelled' ); ?>><?php esc_html_e( 'Cancelado', 'reminder-system' ); ?></option>
								</select>
							</td>
						</tr>
					</table>

					<?php submit_button( '💾 ' . __( 'Guardar Cambios', 'reminder-system' ), 'primary', 'rms_save_appointment' ); ?>
				</form>
			</div>
		</div>
		<?php
	}

	public function page_procedures() {
		$procedures = json_decode( get_option( 'rms_procedures', '["Colonoscopia"]' ), true );
		?>
		<div class="wrap rms-wrap">
			<h1>🔬 <?php esc_html_e( 'Gestión de Procedimientos', 'reminder-system' ); ?></h1>

			<div class="rms-card">
				<h3><?php esc_html_e( 'Agregar Procedimiento', 'reminder-system' ); ?></h3>
				<div class="rms-add-procedure">
					<input type="text" id="rms-new-procedure" class="regular-text"
					       placeholder="<?php esc_attr_e( 'Nombre del procedimiento', 'reminder-system' ); ?>">
					<button id="rms-btn-add-procedure" class="button button-primary">
						➕ <?php esc_html_e( 'Agregar', 'reminder-system' ); ?>
					</button>
				</div>
				<div id="rms-procedure-message" style="display:none;margin-top:12px;"></div>
			</div>

			<div class="rms-card">
				<h3><?php esc_html_e( 'Procedimientos Registrados', 'reminder-system' ); ?></h3>
				<ul id="rms-procedures-list" class="rms-procedures-list">
					<?php foreach ( $procedures as $proc ) : ?>
						<li data-name="<?php echo esc_attr( $proc ); ?>">
							<span>🔬 <?php echo esc_html( $proc ); ?></span>
							<div>
								<?php if ( 'Colonoscopia' === $proc ) : ?>
									<span class="description"><?php esc_html_e( '(predeterminado)', 'reminder-system' ); ?></span>
								<?php else : ?>
									<button class="button button-small button-link-delete rms-btn-del-procedure"
									        data-name="<?php echo esc_attr( $proc ); ?>">🗑️ <?php esc_html_e( 'Eliminar', 'reminder-system' ); ?></button>
								<?php endif; ?>
							</div>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		</div>
		<?php
	}

	public function page_settings() {
		$from_email     = get_option( 'rms_from_email', 'pacificasalud@beforeaftermycare.com' );
		$from_name      = get_option( 'rms_from_name', 'PacificaSalud' );
		$reminder_hours = get_option( 'rms_reminder_hours', '24' );

		$options = array(
			'0.016667' => __( '1 minuto (prueba)', 'reminder-system' ),
			'1'        => __( '1 hora antes', 'reminder-system' ),
			'2'        => __( '2 horas antes', 'reminder-system' ),
			'6'        => __( '6 horas antes', 'reminder-system' ),
			'12'       => __( '12 horas antes', 'reminder-system' ),
			'24'       => __( '24 horas antes', 'reminder-system' ),
			'48'       => __( '48 horas antes', 'reminder-system' ),
		);
		?>
		<div class="wrap rms-wrap">
			<h1>⚙️ <?php esc_html_e( 'Configuración del Sistema', 'reminder-system' ); ?></h1>

			<?php if ( isset( $_GET['saved'] ) ) : ?>
				<div class="notice notice-success is-dismissible">
					<p>✅ <?php esc_html_e( 'Configuración guardada correctamente.', 'reminder-system' ); ?></p>
				</div>
			<?php endif; ?>

			<form method="post">
				<?php wp_nonce_field( 'rms_settings' ); ?>

				<div class="rms-card">
					<h3>📧 <?php esc_html_e( 'Configuración de Correo', 'reminder-system' ); ?></h3>
					<table class="form-table">
						<tr>
							<th><label for="rms_from_email"><?php esc_html_e( 'Correo Remitente', 'reminder-system' ); ?></label></th>
							<td>
								<input type="email" id="rms_from_email" name="rms_from_email"
								       value="<?php echo esc_attr( $from_email ); ?>" class="regular-text" required>
								<p class="description"><?php esc_html_e( 'Dirección desde la que se enviarán las notificaciones.', 'reminder-system' ); ?></p>
							</td>
						</tr>
						<tr>
							<th><label for="rms_from_name"><?php esc_html_e( 'Nombre Remitente', 'reminder-system' ); ?></label></th>
							<td>
								<input type="text" id="rms_from_name" name="rms_from_name"
								       value="<?php echo esc_attr( $from_name ); ?>" class="regular-text" required>
							</td>
						</tr>
					</table>
				</div>

				<div class="rms-card">
					<h3>⏰ <?php esc_html_e( 'Configuración de Recordatorios', 'reminder-system' ); ?></h3>
					<table class="form-table">
						<tr>
							<th><label for="rms_reminder_hours"><?php esc_html_e( 'Tiempo de Anticipación', 'reminder-system' ); ?></label></th>
							<td>
								<select id="rms_reminder_hours" name="rms_reminder_hours" class="regular-text">
									<?php foreach ( $options as $value => $label ) : ?>
										<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $reminder_hours, (string) $value ); ?>>
											<?php echo esc_html( $label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description">
									<?php esc_html_e( 'Tiempo de anticipación para enviar el recordatorio automático.', 'reminder-system' ); ?><br>
									<em><?php esc_html_e( 'Ejemplo: "24 horas" → cita el 25 a las 9:00am → recordatorio el 24 a las 9:00am.', 'reminder-system' ); ?></em>
								</p>
							</td>
						</tr>
					</table>
				</div>

				<?php submit_button( '💾 ' . __( 'Guardar Configuración', 'reminder-system' ), 'primary', 'rms_save_settings' ); ?>
			</form>
		</div>
		<?php
	}
}
