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

	/** Returns a Unix timestamp for the stored Panama-local datetime string. */
	private static function appt_timestamp( $appointment ) {
		$dt = new DateTime( $appointment->appointment_date, new DateTimeZone( RMS_TIMEZONE ) );
		return $dt->getTimestamp();
	}

	public static function send_confirmation( $appointment ) {
		$subject = '✅ Confirmación de su cita médica – '
			. wp_date( 'd/m/Y H:i', self::appt_timestamp( $appointment ), new DateTimeZone( RMS_TIMEZONE ) );

		return self::send(
			$appointment->patient_email,
			$subject,
			self::confirmation_template( $appointment )
		);
	}

	public static function send_reminder( $appointment ) {
		$subject = '⏰ Recordatorio: Su cita médica – '
			. wp_date( 'd/m/Y H:i', self::appt_timestamp( $appointment ), new DateTimeZone( RMS_TIMEZONE ) );

		return self::send(
			$appointment->patient_email,
			$subject,
			self::reminder_template( $appointment )
		);
	}

	public static function send_reminder_48h( $appointment ) {
		$subject = '⏰ Recordatorio (48 horas): Su procedimiento médico – '
			. wp_date( 'd/m/Y H:i', self::appt_timestamp( $appointment ), new DateTimeZone( RMS_TIMEZONE ) );

		return self::send(
			$appointment->patient_email,
			$subject,
			self::reminder_48h_template( $appointment )
		);
	}

	public static function send_reminder_2h( $appointment ) {
		$subject = '⏰ Recordatorio (2 horas): Su procedimiento médico – '
			. wp_date( 'd/m/Y H:i', self::appt_timestamp( $appointment ), new DateTimeZone( RMS_TIMEZONE ) );

		return self::send(
			$appointment->patient_email,
			$subject,
			self::reminder_2h_template( $appointment )
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
		$ts   = self::appt_timestamp( $appointment );
		$tz   = new DateTimeZone( RMS_TIMEZONE );
		$date = wp_date( 'l, d \d\e F \d\e Y', $ts, $tz );
		$time = wp_date( 'H:i', $ts, $tz );

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
		$ts   = self::appt_timestamp( $appointment );
		$tz   = new DateTimeZone( RMS_TIMEZONE );
		$time = wp_date( 'H:i', $ts, $tz );
		$card = self::details_card( $appointment, '#f8faff', '#e3eaf5', '#e8eef6' );

		$body = '<p style="font-size:17px;color:#333;margin:0 0 20px;">Hola, <strong>' . $name . '</strong> 👋</p>
		<p style="color:#555;font-size:14px;line-height:1.8;margin:0 0 26px;">
			Le confirmamos que ha registrado su cita en su guía integral de colonoscopia. A continuación encontrará los detalles:
		</p>'
		. $card .
		'<div style="background:#e8f5e9;border-left:4px solid #4caf50;border-radius:4px;padding:14px 18px;margin-bottom:24px;">
			<p style="margin:0;color:#2e7d32;font-size:13px;line-height:1.7;">
				<strong>💡 Día del procedimiento:</strong> Debe estar a las <strong>' . esc_html( $time ) . '</strong> en el hospital <strong>Punta Pacífica</strong> en el <strong>quinto piso</strong>, departamento de <strong>admisión</strong> (en ayunas).
			</p>
		</div>
		<p style="color:#555;font-size:14px;line-height:1.8;margin:0 0 16px;">
			Visita las indicaciones de preparación en tu guía:
			<a href="https://pacificasalud.beforeaftermycare.com/guia-de-colonoscopia/" style="color:#1a73e8;text-decoration:underline;" target="_blank" rel="noopener noreferrer">Guía de Colonoscopia</a>
		</p>
		<p style="color:#555;font-size:14px;line-height:1.8;margin:0 0 12px;">
			¡Le deseamos mucho éxito en su procedimiento! 🌟
		</p>
		<p style="color:#555;font-size:14px;line-height:1.8;margin:0;">
			Si necesita cancelar o reprogramar debe comunicarse con la secretaria o asistente del Doctor.
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
		$ts    = self::appt_timestamp( $appointment );
		$tz    = new DateTimeZone( RMS_TIMEZONE );
		$time  = wp_date( 'H:i', $ts, $tz );
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
				<strong>⚠️ Día del procedimiento:</strong> Debe estar a las <strong>' . esc_html( $time ) . '</strong> en el hospital <strong>Punta Pacífica</strong> en el <strong>quinto piso</strong>, departamento de <strong>admisión</strong> (en ayunas).
			</p>
		</div>
		<p style="color:#555;font-size:14px;line-height:1.8;margin:0 0 16px;">
			Visita las indicaciones de preparación en tu guía:
			<a href="https://pacificasalud.beforeaftermycare.com/guia-de-colonoscopia/" style="color:#1a73e8;text-decoration:underline;" target="_blank" rel="noopener noreferrer">Guía de Colonoscopia</a>
		</p>
		<p style="color:#555;font-size:14px;line-height:1.8;margin:0 0 12px;">
			¡Le deseamos mucho éxito en su procedimiento! 🌟
		</p>
		<p style="color:#555;font-size:14px;line-height:1.8;margin:0;">
			Si necesita cancelar o reprogramar debe comunicarse con la secretaria o asistente del Doctor.
		</p>';

		return self::base_layout(
			'linear-gradient(135deg,#ff6b35 0%,#e53935 100%)',
			'⏰ Recordatorio de Cita',
			'Su cita es ' . $time_label,
			$body
		);
	}

	private static function reminder_48h_template( $appointment ) {
		$name = esc_html( $appointment->patient_name );
		$ts   = self::appt_timestamp( $appointment );
		$tz   = new DateTimeZone( RMS_TIMEZONE );
		$time = wp_date( 'H:i', $ts, $tz );
		$card = self::details_card( $appointment, '#fff8f0', '#ffe0cc', '#ffe8d6' );

		$body = '<p style="font-size:17px;color:#333;margin:0 0 20px;">Hola, <strong>' . $name . '</strong> 👋</p>
		<p style="color:#555;font-size:14px;line-height:1.8;margin:0 0 26px;">
			Le recordamos que tiene una <strong>cita médica programada en 48 horas</strong>. Por favor asegúrese de estar preparado con anticipación.
		</p>'
		. $card .
		'<div style="background:#fff3cd;border-left:4px solid #ffc107;border-radius:4px;padding:14px 18px;margin-bottom:24px;">
			<p style="margin:0;color:#856404;font-size:13px;line-height:1.7;">
				<strong>⚠️ Día del procedimiento:</strong> Debe estar a las <strong>' . esc_html( $time ) . '</strong> en el hospital <strong>Punta Pacífica</strong> en el <strong>quinto piso</strong>, departamento de <strong>admisión</strong> (en ayunas).
			</p>
		</div>
		<p style="color:#555;font-size:14px;line-height:1.8;margin:0 0 16px;">
			Visita las indicaciones de preparación en tu guía:
			<a href="https://pacificasalud.beforeaftermycare.com/guia-de-colonoscopia/" style="color:#1a73e8;text-decoration:underline;" target="_blank" rel="noopener noreferrer">Guía de Colonoscopia</a>
		</p>
		<p style="color:#555;font-size:14px;line-height:1.8;margin:0 0 12px;">
			¡Le deseamos mucho éxito en su procedimiento! 🌟
		</p>
		<p style="color:#555;font-size:14px;line-height:1.8;margin:0;">
			Si necesita cancelar o reprogramar debe comunicarse con la secretaria o asistente del Doctor.
		</p>';

		return self::base_layout(
			'linear-gradient(135deg,#ff6b35 0%,#e53935 100%)',
			'⏰ Recordatorio de Cita (48 horas)',
			'Su cita es en 48 horas',
			$body
		);
	}

	private static function reminder_2h_template( $appointment ) {
		$name = esc_html( $appointment->patient_name );
		$ts   = self::appt_timestamp( $appointment );
		$tz   = new DateTimeZone( RMS_TIMEZONE );
		$time = wp_date( 'H:i', $ts, $tz );
		$card = self::details_card( $appointment, '#fff8f0', '#ffe0cc', '#ffe8d6' );

		$body = '<p style="font-size:17px;color:#333;margin:0 0 20px;">Hola, <strong>' . $name . '</strong> 👋</p>
		<p style="color:#555;font-size:14px;line-height:1.8;margin:0 0 26px;">
			¡Buenos días! En unas pocas horas tiene su <strong>cita médica programada</strong>. Este es su recordatorio final antes del procedimiento.
		</p>'
		. $card .
		'<div style="background:#fff3cd;border-left:4px solid #ffc107;border-radius:4px;padding:14px 18px;margin-bottom:24px;">
			<p style="margin:0;color:#856404;font-size:13px;line-height:1.7;">
				<strong>⚠️ Día del procedimiento:</strong> Debe estar a las <strong>' . esc_html( $time ) . '</strong> en el hospital <strong>Punta Pacífica</strong> en el <strong>quinto piso</strong>, departamento de <strong>admisión</strong> (en ayunas).
			</p>
		</div>
		<div style="background:#e3f2fd;border-left:4px solid #1a73e8;border-radius:4px;padding:14px 18px;margin-bottom:24px;">
			<p style="margin:0;color:#0d47a1;font-size:13px;line-height:1.7;">
				<strong>📋 Antes de salir:</strong> Le sugerimos revisar las indicaciones de admisión para asegurarse de llegar preparado. Consulte su guía:
				<a href="https://pacificasalud.beforeaftermycare.com/guia-de-colonoscopia/" style="color:#1a73e8;text-decoration:underline;" target="_blank" rel="noopener noreferrer">Guía de Colonoscopia</a>
			</p>
		</div>
		<p style="color:#555;font-size:14px;line-height:1.8;margin:0 0 12px;">
			¡Le deseamos mucho éxito en su procedimiento! 🌟
		</p>
		<p style="color:#555;font-size:14px;line-height:1.8;margin:0;">
			Si necesita cancelar o reprogramar debe comunicarse con la secretaria o asistente del Doctor.
		</p>';

		return self::base_layout(
			'linear-gradient(135deg,#ff6b35 0%,#e53935 100%)',
			'⏰ Recordatorio de Cita (2 horas)',
			'Su cita es en 2 horas',
			$body
		);
	}
}
