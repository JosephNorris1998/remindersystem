<?php
defined( 'ABSPATH' ) || exit;

class RMS_Email {

	public static function get_from_email() {
		return get_option( 'rms_from_email', 'pacificasalud@beforeaftermycare.com' );
	}

	public static function get_from_name() {
		return get_option( 'rms_from_name', 'PacificaSalud' );
	}

	/** Force the From header on every outgoing message. */
	public static function filter_from_email( $email ) {
		return self::get_from_email();
	}

	public static function filter_from_name( $name ) {
		return self::get_from_name();
	}

	private static function send( $to, $subject, $body ) {
		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . self::get_from_name() . ' <' . self::get_from_email() . '>',
		);

		add_filter( 'wp_mail_from',      array( __CLASS__, 'filter_from_email' ) );
		add_filter( 'wp_mail_from_name', array( __CLASS__, 'filter_from_name' ) );

		$sent = wp_mail( $to, $subject, $body, $headers );

		remove_filter( 'wp_mail_from',      array( __CLASS__, 'filter_from_email' ) );
		remove_filter( 'wp_mail_from_name', array( __CLASS__, 'filter_from_name' ) );

		return $sent;
	}

	public static function send_confirmation( $appointment ) {
		$subject = '✅ Confirmación de su cita médica – '
			. date_i18n( 'd/m/Y H:i', strtotime( $appointment->appointment_date ) );

		return self::send(
			$appointment->patient_email,
			$subject,
			self::confirmation_template( $appointment )
		);
	}

	public static function send_reminder( $appointment ) {
		$subject = '⏰ Recordatorio: Su cita médica – '
			. date_i18n( 'd/m/Y H:i', strtotime( $appointment->appointment_date ) );

		return self::send(
			$appointment->patient_email,
			$subject,
			self::reminder_template( $appointment )
		);
	}

	/* ------------------------------------------------------------------ */
	/* Email templates                                                      */
	/* ------------------------------------------------------------------ */

	private static function base_layout( $header_gradient, $header_title, $header_subtitle, $body_html ) {
		$from_name = esc_html( self::get_from_name() );

		return '<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>' . esc_html( strip_tags( $header_title ) ) . '</title>
</head>
<body style="margin:0;padding:0;background:#f0f4f8;font-family:\'Segoe UI\',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f4f8;padding:40px 0;">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">

      <!-- Header -->
      <tr>
        <td style="background:' . $header_gradient . ';border-radius:12px 12px 0 0;padding:40px 30px;text-align:center;">
          <h1 style="color:#fff;margin:0;font-size:26px;font-weight:700;">' . $header_title . '</h1>
          <p style="color:rgba(255,255,255,.85);margin:10px 0 0;font-size:15px;">' . $header_subtitle . '</p>
        </td>
      </tr>

      <!-- Body -->
      <tr>
        <td style="background:#fff;padding:36px 30px;">
          ' . $body_html . '
        </td>
      </tr>

      <!-- Footer -->
      <tr>
        <td style="background:#f8faff;border-top:1px solid #e3eaf5;border-radius:0 0 12px 12px;padding:20px 30px;text-align:center;">
          <p style="color:#888;font-size:13px;margin:0 0 6px;">' . $from_name . '</p>
          <p style="color:#bbb;font-size:12px;margin:0;">Este es un correo automático, por favor no responda a este mensaje.</p>
        </td>
      </tr>

    </table>
  </td></tr>
</table>
</body>
</html>';
	}

	private static function details_card( $appointment, $accent_bg, $accent_border, $row_border ) {
		$name = esc_html( $appointment->patient_name );
		$proc = esc_html( $appointment->procedure_name );
		$date = date_i18n( 'l, d \d\e F \d\e Y', strtotime( $appointment->appointment_date ) );
		$time = date_i18n( 'H:i', strtotime( $appointment->appointment_date ) );

		return '<table width="100%" cellpadding="0" cellspacing="0"
				style="background:' . $accent_bg . ';border:1px solid ' . $accent_border . ';border-radius:10px;margin-bottom:28px;">
			<tr><td style="padding:22px 26px;">
				<table width="100%" cellpadding="0" cellspacing="0">
					<tr><td style="padding:9px 0;border-bottom:1px solid ' . $row_border . ';">
						<span style="color:#888;font-size:12px;text-transform:uppercase;letter-spacing:.5px;">Paciente</span><br>
						<span style="color:#1a1a2e;font-size:15px;font-weight:600;">' . $name . '</span>
					</td></tr>
					<tr><td style="padding:9px 0;border-bottom:1px solid ' . $row_border . ';">
						<span style="color:#888;font-size:12px;text-transform:uppercase;letter-spacing:.5px;">Procedimiento</span><br>
						<span style="color:#1a1a2e;font-size:15px;font-weight:600;">' . $proc . '</span>
					</td></tr>
					<tr><td style="padding:9px 0;border-bottom:1px solid ' . $row_border . ';">
						<span style="color:#888;font-size:12px;text-transform:uppercase;letter-spacing:.5px;">Fecha</span><br>
						<span style="color:#1a1a2e;font-size:15px;font-weight:600;">📅 ' . $date . '</span>
					</td></tr>
					<tr><td style="padding:9px 0;">
						<span style="color:#888;font-size:12px;text-transform:uppercase;letter-spacing:.5px;">Hora</span><br>
						<span style="color:#1a1a2e;font-size:15px;font-weight:600;">🕐 ' . $time . '</span>
					</td></tr>
				</table>
			</td></tr>
		</table>';
	}

	private static function confirmation_template( $appointment ) {
		$name = esc_html( $appointment->patient_name );
		$card = self::details_card( $appointment, '#f8faff', '#e3eaf5', '#e8eef6' );

		$body = '<p style="font-size:17px;color:#333;margin:0 0 20px;">Hola, <strong>' . $name . '</strong> 👋</p>
		<p style="color:#555;font-size:14px;line-height:1.8;margin:0 0 26px;">
			Le confirmamos que su cita médica ha sido registrada correctamente en nuestro sistema. A continuación encontrará los detalles:
		</p>'
		. $card .
		'<div style="background:#e8f5e9;border-left:4px solid #4caf50;border-radius:4px;padding:14px 18px;margin-bottom:24px;">
			<p style="margin:0;color:#2e7d32;font-size:13px;line-height:1.7;">
				<strong>💡 Recuerde:</strong> Por favor llegue <strong>15 minutos antes</strong> de su cita para completar los trámites de admisión.
			</p>
		</div>
		<p style="color:#555;font-size:14px;line-height:1.8;margin:0;">
			Recibirá un <strong>recordatorio</strong> antes de su cita. Si necesita cancelar o reprogramar, comuníquese con nosotros lo antes posible.
		</p>';

		return self::base_layout(
			'linear-gradient(135deg,#1a73e8 0%,#0d47a1 100%)',
			'✅ Cita Confirmada',
			'Su cita ha sido registrada exitosamente',
			$body
		);
	}

	private static function reminder_template( $appointment ) {
		$name  = esc_html( $appointment->patient_name );
		$hours = (float) get_option( 'rms_reminder_hours', 24 );
		$card  = self::details_card( $appointment, '#fff8f0', '#ffe0cc', '#ffe8d6' );

		if ( $hours < 1 ) {
			$time_label = 'en breve';
		} elseif ( $hours < 24 ) {
			$time_label = 'en ' . $hours . ' hora' . ( (int) $hours === 1 ? '' : 's' );
		} else {
			$time_label = 'mañana';
		}

		$body = '<p style="font-size:17px;color:#333;margin:0 0 20px;">Hola, <strong>' . $name . '</strong> 👋</p>
		<p style="color:#555;font-size:14px;line-height:1.8;margin:0 0 26px;">
			Le recordamos que tiene una <strong>cita médica programada ' . $time_label . '</strong>. Por favor asegúrese de estar preparado con anticipación.
		</p>'
		. $card .
		'<div style="background:#fff3cd;border-left:4px solid #ffc107;border-radius:4px;padding:14px 18px;margin-bottom:24px;">
			<p style="margin:0;color:#856404;font-size:13px;line-height:1.7;">
				<strong>⚠️ Importante:</strong> Llegue <strong>15 minutos antes</strong> de su cita. Si no puede asistir, comuníquese con nosotros con anticipación para reagendar.
			</p>
		</div>
		<p style="color:#555;font-size:14px;line-height:1.8;margin:0;">
			¡Le deseamos mucho éxito en su procedimiento! 🌟
		</p>';

		return self::base_layout(
			'linear-gradient(135deg,#ff6b35 0%,#e53935 100%)',
			'⏰ Recordatorio de Cita',
			'Su cita es ' . $time_label,
			$body
		);
	}
}
