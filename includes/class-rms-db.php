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
			id                   bigint(20)   NOT NULL AUTO_INCREMENT,
			patient_name         varchar(255) NOT NULL,
			patient_email        varchar(255) NOT NULL,
			appointment_date     datetime     NOT NULL,
			procedure_name       varchar(255) NOT NULL,
			status               varchar(50)  NOT NULL DEFAULT 'confirmed',
			reminder_sent        tinyint(1)   NOT NULL DEFAULT 0,
			reminder_sent_at     datetime              DEFAULT NULL,
			reminder_48h_sent    tinyint(1)   NOT NULL DEFAULT 0,
			reminder_48h_sent_at datetime              DEFAULT NULL,
			created_at           datetime     NOT NULL,
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
	 * Return appointments whose configurable reminder (default 24 h) is due.
	 *
	 * Logic: the reminder moment has already arrived (i.e. we are past the
	 * "target_hours before appointment" threshold) but the appointment is still
	 * in the future.  The reminder_sent flag prevents duplicate sends.
	 *
	 * This approach is resilient to WP-Cron imprecision and to external ping
	 * services (e.g. UptimeRobot Free, every 5 min): even if the cron fires
	 * late, the next run will still find the record and send it.
	 */
	public static function get_pending_reminders() {
		global $wpdb;
		$table          = self::get_table_name();
		$hours          = (float) get_option( 'rms_reminder_hours', 24 );
		$target_seconds = max( 1, (int) round( $hours * 3600 ) );
		$now            = self::get_panama_now();

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				 WHERE reminder_sent = 0
				   AND DATE_SUB(appointment_date, INTERVAL %d SECOND) <= %s
				   AND appointment_date > %s",
				$target_seconds,
				$now,
				$now
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

	public static function get_pending_48h_reminders() {
		global $wpdb;
		$table          = self::get_table_name();
		$target_seconds = 48 * 3600; // Fixed 48-hour target
		$now            = self::get_panama_now();

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				 WHERE reminder_48h_sent = 0
				   AND DATE_SUB(appointment_date, INTERVAL %d SECOND) <= %s
				   AND appointment_date > %s",
				$target_seconds,
				$now,
				$now
			)
		);
	}

	public static function mark_reminder_48h_sent( $id ) {
		return self::update(
			$id,
			array(
				'reminder_48h_sent'    => 1,
				'reminder_48h_sent_at' => self::get_panama_now(),
			)
		);
	}

	/**
	 * Run schema upgrades when the DB version doesn't match the plugin version.
	 * Safe to call on every page load — uses an option flag to avoid redundant work.
	 *
	 * Also performs a one-time, idempotent ALTER TABLE to add the 48h reminder
	 * columns in case the site was installed before those columns existed.
	 */
	public static function maybe_upgrade() {
		global $wpdb;

		if ( get_option( 'rms_db_version' ) !== RMS_VERSION ) {
			self::install();
			update_option( 'rms_db_version', RMS_VERSION );
		}

		// Idempotent safety-net: add 48h columns if they are missing on existing installs.
		$table   = self::get_table_name();
		$columns = $wpdb->get_col( "SHOW COLUMNS FROM `" . esc_sql( $table ) . "`", 0 );

		if ( ! empty( $columns ) && ! in_array( 'reminder_48h_sent', $columns, true ) ) {
			$wpdb->query( "ALTER TABLE `" . esc_sql( $table ) . "` ADD COLUMN reminder_48h_sent tinyint(1) NOT NULL DEFAULT 0" );
		}

		if ( ! empty( $columns ) && ! in_array( 'reminder_48h_sent_at', $columns, true ) ) {
			$wpdb->query( "ALTER TABLE `" . esc_sql( $table ) . "` ADD COLUMN reminder_48h_sent_at datetime DEFAULT NULL" );
		}
	}
}
