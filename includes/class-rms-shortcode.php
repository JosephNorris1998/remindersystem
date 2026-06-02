<?php
defined( 'ABSPATH' ) || exit;

class RMS_Shortcode {

	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_shortcode( 'reminder_form',   array( $this, 'render' ) );

		add_action( 'wp_ajax_rms_submit_form',        array( $this, 'handle_submit' ) );
		add_action( 'wp_ajax_nopriv_rms_submit_form', array( $this, 'handle_submit' ) );
	}

	/* ------------------------------------------------------------------ */
	/* Assets — register only; enqueue inside shortcode for Elementor compat */
	/* ------------------------------------------------------------------ */

	public function register_assets() {
		wp_register_style(
			'flatpickr',
			'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css',
			array(),
			'4.6.13'
		);
		wp_register_script(
			'flatpickr',
			'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.js',
			array(),
			'4.6.13',
			true
		);
		wp_register_style(
			'rms-frontend',
			RMS_PLUGIN_URL . 'assets/css/frontend.css',
			array( 'flatpickr' ),
			RMS_VERSION
		);
		wp_register_script(
			'rms-frontend',
			RMS_PLUGIN_URL . 'assets/js/frontend.js',
			array( 'jquery', 'flatpickr' ),
			RMS_VERSION,
			true
		);
	}

	/* ------------------------------------------------------------------ */
	/* Shortcode render                                                     */
	/* ------------------------------------------------------------------ */

	public function render( $atts ) {
		/* Enqueue here for full Elementor / page-builder compatibility */
		wp_enqueue_style( 'flatpickr' );
		wp_enqueue_script( 'flatpickr' );
		wp_enqueue_style( 'rms-frontend' );
		wp_enqueue_script( 'rms-frontend' );
		wp_localize_script(
			'rms-frontend',
			'rmsFrontend',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'rms_form_nonce' ),
			)
		);

		$procedures = json_decode( get_option( 'rms_procedures', '["Colonoscopia"]' ), true );

		ob_start();
		?>
		<div class="rms-form-wrapper" id="rms-form-wrapper">

			<div class="rms-notice">
				<p>
					<strong>Aviso importante:</strong> Registre la fecha de su cita. Asegúrese que la fecha y hora anotada sea la cita indicada para su procedimiento.
				</p>
			</div>

			<div class="rms-checkbox-row">
				<label class="rms-checkbox-label" for="rms-confirm-checkbox">
					<input type="checkbox" id="rms-confirm-checkbox">
					<span>Completa la información para configurar tus alertas</span>
				</label>
			</div>

			<form id="rms-appointment-form" novalidate>
				<div class="rms-field" id="rms-field-name">
					<label for="rms-patient-name">Nombre completo:</label>
					<input type="text" id="rms-patient-name" name="patient_name"
					       disabled required
					       placeholder="Ingrese su nombre completo">
				</div>

				<div class="rms-field" id="rms-field-email">
					<label for="rms-patient-email">Correo electrónico:</label>
					<input type="email" id="rms-patient-email" name="patient_email"
					       disabled required
					       placeholder="ejemplo@correo.com">
				</div>

				<div class="rms-field" id="rms-field-date">
					<label for="rms-appointment-date">Fecha y hora:</label>
					<input type="text" id="rms-appointment-date" name="appointment_date"
					       disabled required readonly
					       placeholder="DD/MM/AAAA HH:MM">
				</div>

				<div class="rms-field" id="rms-field-procedure">
					<label for="rms-procedure">Procedimiento:</label>
					<select id="rms-procedure" name="procedure_name" disabled required>
						<?php foreach ( $procedures as $proc ) : ?>
							<option value="<?php echo esc_attr( $proc ); ?>"><?php echo esc_html( $proc ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="rms-submit-row" id="rms-submit-row">
					<button type="submit" id="rms-submit-btn" disabled>Haz clic para configurar tus alertas</button>
				</div>

				<div id="rms-form-message" style="display:none;" role="alert"></div>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	/* ------------------------------------------------------------------ */
	/* AJAX form submission                                                 */
	/* ------------------------------------------------------------------ */

	public function handle_submit() {
		check_ajax_referer( 'rms_form_nonce', 'nonce' );

		$name  = sanitize_text_field( wp_unslash( $_POST['patient_name'] ?? '' ) );
		$email = sanitize_email( wp_unslash( $_POST['patient_email'] ?? '' ) );
		$date  = sanitize_text_field( wp_unslash( $_POST['appointment_date'] ?? '' ) );
		$proc  = sanitize_text_field( wp_unslash( $_POST['procedure_name'] ?? '' ) );

		if ( empty( $name ) || empty( $email ) || empty( $date ) || empty( $proc ) ) {
			wp_send_json_error( array( 'message' => 'Por favor complete todos los campos.' ) );
		}

		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => 'El correo electrónico no es válido.' ) );
		}

		/* Parse date from flatpickr: d/m/Y H:i */
		$tz = new DateTimeZone( defined( 'RMS_TIMEZONE' ) ? RMS_TIMEZONE : 'America/Panama' );
		$parsed = DateTime::createFromFormat( 'd/m/Y H:i', $date, $tz );
		if ( ! $parsed ) {
			$parsed = DateTime::createFromFormat( 'Y-m-d H:i', $date, $tz );
		}
		if ( ! $parsed ) {
			wp_send_json_error( array( 'message' => 'Formato de fecha inválido. Seleccione la fecha del calendario.' ) );
		}

		/* Validate it is in the future */
		$now = new DateTime( 'now', $tz );
		if ( $parsed <= $now ) {
			wp_send_json_error( array( 'message' => 'La fecha de la cita debe ser en el futuro.' ) );
		}

		/*
		 * Pre-mark reminders as sent if the appointment is already within their
		 * time window at the moment of registration.  This prevents a reminder
		 * from firing for a window that was never "in the future" for this patient.
		 */
		$seconds_until      = $parsed->getTimestamp() - $now->getTimestamp();
		$reminder_hours     = (float) get_option( 'rms_reminder_hours', 24 );
		$reminder_sent      = ( $seconds_until <= $reminder_hours * 3600 ) ? 1 : 0;
		$reminder_48h_sent  = ( $seconds_until <= 48 * 3600 ) ? 1 : 0;
		$reminder_2h_sent   = ( $seconds_until <= 2 * 3600 ) ? 1 : 0;

		$id = RMS_DB::insert( array(
			'patient_name'      => $name,
			'patient_email'     => $email,
			'appointment_date'  => $parsed->format( 'Y-m-d H:i:s' ),
			'procedure_name'    => $proc,
			'status'            => 'confirmed',
			'reminder_sent'     => $reminder_sent,
			'reminder_48h_sent' => $reminder_48h_sent,
			'reminder_2h_sent'  => $reminder_2h_sent,
			'created_at'        => ( new DateTime( 'now', $tz ) )->format( 'Y-m-d H:i:s' ),
		) );

		if ( ! $id ) {
			wp_send_json_error( array( 'message' => 'Error al guardar la cita. Por favor intente de nuevo.' ) );
		}

		/* Send confirmation email */
		$appointment = RMS_DB::get( $id );
		RMS_Email::send_confirmation( $appointment );

		wp_send_json_success( array(
			'message' => '✅ Su cita ha sido registrada exitosamente. Recibirá un correo de confirmación en breve.',
		) );
	}
}
