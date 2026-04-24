<?php
defined( 'ABSPATH' ) || exit;

class RMS_Cron {

	public function __construct() {
		add_filter( 'cron_schedules', array( $this, 'add_schedules' ) );
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

	public function add_schedules( $schedules ) {
		$schedules['rms_every_minute'] = array(
			'interval' => 60,
			'display'  => __( 'Cada Minuto (RMS)', 'reminder-system' ),
		);
		return $schedules;
	}

	public function process_reminders() {
		$appointments = RMS_DB::get_pending_reminders();
		foreach ( $appointments as $appointment ) {
			$sent = RMS_Email::send_reminder( $appointment );
			if ( $sent ) {
				RMS_DB::mark_reminder_sent( $appointment->id );
			}
		}
	}
}
