<?php
defined( 'ABSPATH' ) || exit;

class RMS_DB {

	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'rms_appointments';
	}

	public static function install() {
		global $wpdb;

		$table           = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$table} (
			id               bigint(20)   NOT NULL AUTO_INCREMENT,
			patient_name     varchar(255) NOT NULL,
			patient_email    varchar(255) NOT NULL,
			appointment_date datetime     NOT NULL,
			procedure_name   varchar(255) NOT NULL,
			status           varchar(50)  NOT NULL DEFAULT 'confirmed',
			reminder_sent    tinyint(1)   NOT NULL DEFAULT 0,
			reminder_sent_at datetime              DEFAULT NULL,
			created_at       datetime     NOT NULL,
			PRIMARY KEY (id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		if ( ! get_option( 'rms_reminder_hours' ) ) {
			update_option( 'rms_reminder_hours', '24' );
		}
		if ( ! get_option( 'rms_procedures' ) ) {
			update_option( 'rms_procedures', wp_json_encode( array( 'Colonoscopia' ) ) );
		}
		if ( ! get_option( 'rms_from_email' ) ) {
			update_option( 'rms_from_email', 'pacificasalud@beforeaftermycare.com' );
		}
		if ( ! get_option( 'rms_from_name' ) ) {
			update_option( 'rms_from_name', 'PacificaSalud' );
		}
	}

	public static function get_all( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'per_page' => 20,
			'page'     => 1,
			'search'   => '',
		);
		$args  = wp_parse_args( $args, $defaults );
		$table = self::get_table_name();
		$offset = ( absint( $args['page'] ) - 1 ) * absint( $args['per_page'] );

		if ( ! empty( $args['search'] ) ) {
			$like = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table}
					 WHERE patient_name LIKE %s OR patient_email LIKE %s OR procedure_name LIKE %s
					 ORDER BY appointment_date ASC
					 LIMIT %d OFFSET %d",
					$like, $like, $like,
					absint( $args['per_page'] ), $offset
				)
			);
		}

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} ORDER BY appointment_date ASC LIMIT %d OFFSET %d",
				absint( $args['per_page'] ), $offset
			)
		);
	}

	public static function count( $search = '' ) {
		global $wpdb;
		$table = self::get_table_name();

		if ( ! empty( $search ) ) {
			$like = '%' . $wpdb->esc_like( $search ) . '%';
			return (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table}
					 WHERE patient_name LIKE %s OR patient_email LIKE %s OR procedure_name LIKE %s",
					$like, $like, $like
				)
			);
		}

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}

	public static function get( $id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::get_table_name() . ' WHERE id = %d', absint( $id ) )
		);
	}

	public static function insert( $data ) {
		global $wpdb;
		$wpdb->insert( self::get_table_name(), $data );
		return $wpdb->insert_id;
	}

	public static function update( $id, $data ) {
		global $wpdb;
		return $wpdb->update( self::get_table_name(), $data, array( 'id' => absint( $id ) ) );
	}

	public static function delete( $id ) {
		global $wpdb;
		return $wpdb->delete( self::get_table_name(), array( 'id' => absint( $id ) ), array( '%d' ) );
	}

	/**
	 * Return current datetime in America/Panama timezone as a MySQL-formatted string.
	 * Using an explicit timezone makes reminder calculations timezone-independent from
	 * whatever the WP site timezone option is set to.
	 */
	private static function get_panama_now() {
		$tz = defined( 'RMS_TIMEZONE' ) ? RMS_TIMEZONE : 'America/Panama';
		return ( new DateTime( 'now', new DateTimeZone( $tz ) ) )->format( 'Y-m-d H:i:s' );
	}

	/**
	 * Return appointments whose reminder window has arrived and reminder has not been sent.
	 * Uses SECOND precision so fractional hours (e.g. 0.016667 ≈ 1 minute) work correctly.
	 */
	public static function get_pending_reminders() {
		global $wpdb;
		$table   = self::get_table_name();
		$hours   = (float) get_option( 'rms_reminder_hours', 24 );
		$seconds = max( 1, (int) round( $hours * 3600 ) );
		$now     = self::get_panama_now();

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				 WHERE reminder_sent = 0
				   AND appointment_date > %s
				   AND DATE_SUB(appointment_date, INTERVAL %d SECOND) <= %s",
				$now, $seconds, $now
			)
		);
	}

	public static function mark_reminder_sent( $id ) {
		return self::update(
			$id,
			array(
				'reminder_sent'    => 1,
				'reminder_sent_at' => self::get_panama_now(),
			)
		);
	}
}
