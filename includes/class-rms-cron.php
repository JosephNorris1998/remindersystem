<?php
defined( 'ABSPATH' ) || exit;

class RMS_Cron {

	public function __construct() {
		add_filter( 'cron_schedules', array( __CLASS__, 'add_schedules' ) );
		add_action( 'rms_send_reminders', array( $this, 'process_reminders' ) );
	}

	public static function activate() {
		if ( ! wp_next_scheduled( 'rms_send_reminders' ) ) {
			wp_schedule_event( time(), 'rms_every_minute', 'rms_send_reminders' );
		}
	}

	public static function deactivate() {
		$timestamp = wp_next_scheduled( 'rms_send_reminders' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'rms_send_reminders' );
		}
	}

	public static function add_schedules( $schedules ) {
		$schedules['rms_every_minute'] = array(
			'interval' => 60,
			'display'  => __( 'Cada Minuto (RMS)', 'reminder-system' ),
		);
		return $schedules;
	}

	public function process_reminders() {
		// Process the configurable-window reminder (default 24 h).
		$reminder_hours = (float) get_option( 'rms_reminder_hours', 24 );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$tz  = defined( 'RMS_TIMEZONE' ) ? RMS_TIMEZONE : 'America/Panama';
			$now = ( new DateTime( 'now', new DateTimeZone( $tz ) ) )->format( 'Y-m-d H:i:s' );
			error_log( sprintf(
				'[RMS] process_reminders() iniciado. now (Panamá)=%s | objetivo=%.4fh | objetivo_48h=48h',
				$now,
				$reminder_hours
			) );
		}

		$appointments = RMS_DB::get_pending_reminders();

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf(
				'[RMS] Recordatorios %sh pendientes encontrados: %d',
				$reminder_hours,
				count( $appointments )
			) );
		}

		foreach ( $appointments as $appointment ) {
			$sent = RMS_Email::send_reminder( $appointment );
			if ( $sent ) {
				RMS_DB::mark_reminder_sent( $appointment->id );
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( sprintf( '[RMS] Recordatorio enviado: cita ID %d (%s).', $appointment->id, $appointment->patient_email ) );
				}
			} elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( sprintf( '[RMS] Fallo al enviar recordatorio: cita ID %d (%s).', $appointment->id, $appointment->patient_email ) );
			}
		}

		// Process the fixed 48-hour reminder.
		$appointments_48h = RMS_DB::get_pending_48h_reminders();

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf(
				'[RMS] Recordatorios 48h pendientes encontrados: %d',
				count( $appointments_48h )
			) );
		}

		foreach ( $appointments_48h as $appointment ) {
			$sent = RMS_Email::send_reminder_48h( $appointment );
			if ( $sent ) {
				RMS_DB::mark_reminder_48h_sent( $appointment->id );
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( sprintf( '[RMS] Recordatorio 48h enviado: cita ID %d (%s).', $appointment->id, $appointment->patient_email ) );
				}
			} elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( sprintf( '[RMS] Fallo al enviar recordatorio 48h: cita ID %d (%s).', $appointment->id, $appointment->patient_email ) );
			}
		}
	}
}
