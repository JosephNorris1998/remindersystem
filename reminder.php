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

define( 'RMS_VERSION',    '1.0.0' );
define( 'RMS_PLUGIN_FILE', __FILE__ );
define( 'RMS_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'RMS_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );

require_once RMS_PLUGIN_DIR . 'includes/class-rms-db.php';
require_once RMS_PLUGIN_DIR . 'includes/class-rms-email.php';
require_once RMS_PLUGIN_DIR . 'includes/class-rms-cron.php';
require_once RMS_PLUGIN_DIR . 'includes/class-rms-admin.php';
require_once RMS_PLUGIN_DIR . 'includes/class-rms-shortcode.php';

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
	new RMS_Admin();
	new RMS_Shortcode();
	new RMS_Cron();
}
