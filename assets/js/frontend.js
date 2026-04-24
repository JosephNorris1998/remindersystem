/* global jQuery, flatpickr, rmsFrontend */
(function ($) {
	'use strict';

	$(document).ready(function () {

		var $checkbox  = $('#rms-confirm-checkbox');
		var $form      = $('#rms-appointment-form');
		var $submitBtn = $('#rms-submit-btn');
		var $message   = $('#rms-form-message');
		var fp         = null;

		/* ---- Init flatpickr ---- */
		if (document.getElementById('rms-appointment-date')) {
			fp = flatpickr('#rms-appointment-date', {
				enableTime:  true,
				dateFormat:  'd/m/Y H:i',
				time_24hr:   true,
				minDate:     'today',
				disableMobile: false,
				locale: {
					firstDayOfWeek: 1,
					weekdays: {
						shorthand: ['Do', 'Lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sa'],
						longhand:  ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado']
					},
					months: {
						shorthand: ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'],
						longhand:  ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre']
					}
				}
			});
		}

		/* ---- Toggle fields on checkbox change ---- */
		$checkbox.on('change', function () {
			var checked = $(this).is(':checked');

			$('#rms-field-name, #rms-field-email, #rms-field-date, #rms-field-procedure')
				.toggleClass('rms-active', checked)
				.find('input, select')
				.prop('disabled', !checked);

			$('#rms-submit-row')
				.toggleClass('rms-active', checked);

			$submitBtn.prop('disabled', !checked);

			/* Sync flatpickr's internal input with disabled state */
			if (fp) {
				if (checked) {
					fp._input.removeAttribute('disabled');
					fp._input.readOnly = true; /* keep readonly so user must use picker */
				} else {
					fp._input.setAttribute('disabled', 'disabled');
				}
			}

			/* Clear message when unchecking */
			if (!checked) {
				$message.hide().removeClass('rms-success rms-error').html('');
			}
		});

		/* ---- Form submit ---- */
		$form.on('submit', function (e) {
			e.preventDefault();

			$message.hide().removeClass('rms-success rms-error').html('');
			$submitBtn.prop('disabled', true).text('Enviando…');

			$.post(
				rmsFrontend.ajaxUrl,
				{
					action:           'rms_submit_form',
					nonce:            rmsFrontend.nonce,
					patient_name:     $('#rms-patient-name').val().trim(),
					patient_email:    $('#rms-patient-email').val().trim(),
					appointment_date: $('#rms-appointment-date').val().trim(),
					procedure_name:   $('#rms-procedure').val()
				},
				function (response) {
					if (response.success) {
						$message.addClass('rms-success').html(response.data.message).show();

						/* Reset form */
						$form[0].reset();
						$checkbox.prop('checked', false).trigger('change');
						if (fp) { fp.clear(); }

						/* Smooth scroll to message */
						$('html, body').animate(
							{ scrollTop: $message.offset().top - 80 },
							350
						);
					} else {
						var errMsg = (response.data && response.data.message)
							? response.data.message
							: 'Error al enviar. Intente de nuevo.';

						$message.addClass('rms-error').html(errMsg).show();
						$submitBtn.prop('disabled', false).text('Registrar cita');
					}
				}
			).fail(function () {
				$message.addClass('rms-error').html('Error de conexión. Por favor intente de nuevo.').show();
				$submitBtn.prop('disabled', false).text('Registrar cita');
			});
		});

	});

}(jQuery));
