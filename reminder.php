<?php
/**
 * Plugin Name: Sistema de Recordatorios de Citas
 * Plugin URI:  https://beforeaftermycare.com
 * Description: Sistema de recordatorio de citas médicas. Formulario con shortcode [reminder_form], confirmación por correo y recordatorios automáticos configurables.
 * Version:     1.0.0
 * Author:      PacificaSalud
 * Author URI:  https://beforeaftermycare.com
 * License:     GPL-2.0-or-later
 * Text Domain: reminder-system
 */

defined( 'ABSPATH' ) || exit;

define( 'RMS_VERSION',    '1.1.0' );
define( 'RMS_PLUGIN_FILE', __FILE__ );
define( 'RMS_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'RMS_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'RMS_TIMEZONE',    'America/Panama' );

require_once RMS_PLUGIN_DIR . 'includes/class-rms-db.php';
require_once RMS_PLUGIN_DIR . 'includes/class-rms-email.php';
require_once RMS_PLUGIN_DIR . 'includes/class-rms-cron.php';
require_once RMS_PLUGIN_DIR . 'includes/class-rms-admin.php';
require_once RMS_PLUGIN_DIR . 'includes/class-rms-shortcode.php';

/*
 * Register the custom cron schedule immediately at plugin load time — before
 * register_activation_hook fires — so that wp_schedule_event() can recognise
 * 'rms_every_minute' even during the activation request.
 */
add_filter( 'cron_schedules', array( 'RMS_Cron', 'add_schedules' ) );

register_activation_hook( __FILE__, 'rms_activate' );
register_deactivation_hook( __FILE__, 'rms_deactivate' );

function rms_activate() {
	RMS_DB::install();
	RMS_Cron::activate();
}

function rms_deactivate() {
	RMS_Cron::deactivate();
}

add_action( 'plugins_loaded', 'rms_init' );

function rms_init() {
	RMS_DB::maybe_upgrade();
	new RMS_Admin();
	new RMS_Shortcode();
	new RMS_Cron();
}

/**
 * Auto-repair: ensure the cron event is always scheduled.
 * Runs on every page load so the event is re-created if it was
 * accidentally cleared (e.g. after a plugin update or WP Crontrol delete).
 */
add_action( 'init', 'rms_ensure_cron_scheduled' );

function rms_ensure_cron_scheduled() {
	/* Transient lock: avoid redundant scheduling attempts on concurrent requests. */
	if ( get_transient( 'rms_cron_check' ) ) {
		return;
	}
	set_transient( 'rms_cron_check', 1, 30 );

	if ( ! wp_next_scheduled( 'rms_send_reminders' ) ) {
		wp_schedule_event( time(), 'rms_every_minute', 'rms_send_reminders' );
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[RMS] Evento rms_send_reminders reagendado automáticamente en init.' );
		}
	}
}
