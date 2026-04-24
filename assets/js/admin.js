/* global jQuery, rmsAdmin */
(function ($) {
	'use strict';

	$(document).ready(function () {

		/* ---- Send reminder ---- */
		$(document).on('click', '.rms-btn-remind', function () {
			var $btn = $(this);
			var id   = $btn.data('id');

			if (!window.confirm(rmsAdmin.confirmRemind)) { return; }

			$btn.prop('disabled', true).text('Enviando…');

			$.post(
				rmsAdmin.ajaxUrl,
				{
					action: 'rms_send_reminder',
					nonce:  rmsAdmin.nonce,
					id:     id
				},
				function (response) {
					if (response.success) {
						window.alert('✅ ' + response.data.message);
						window.location.reload();
					} else {
						var msg = (response.data && response.data.message)
							? response.data.message
							: 'Error al enviar el recordatorio.';
						window.alert('❌ ' + msg);
						$btn.prop('disabled', false).text('📧 Recordatorio');
					}
				}
			).fail(function () {
				window.alert('❌ Error de conexión.');
				$btn.prop('disabled', false).text('📧 Recordatorio');
			});
		});

		/* ---- Delete record ---- */
		$(document).on('click', '.rms-btn-delete', function () {
			var $btn = $(this);
			var id   = $btn.data('id');

			if (!window.confirm(rmsAdmin.confirmDelete)) { return; }

			$btn.prop('disabled', true);

			$.post(
				rmsAdmin.ajaxUrl,
				{
					action: 'rms_delete_appointment',
					nonce:  rmsAdmin.nonce,
					id:     id
				},
				function (response) {
					if (response.success) {
						$('#rms-row-' + id).fadeOut(300, function () { $(this).remove(); });
					} else {
						window.alert('Error al eliminar el registro.');
						$btn.prop('disabled', false);
					}
				}
			).fail(function () {
				window.alert('❌ Error de conexión.');
				$btn.prop('disabled', false);
			});
		});

		/* ---- Add procedure ---- */
		$('#rms-btn-add-procedure').on('click', function () {
			addProcedure();
		});

		$('#rms-new-procedure').on('keypress', function (e) {
			if (e.which === 13) {
				e.preventDefault();
				addProcedure();
			}
		});

		function addProcedure() {
			var name = $('#rms-new-procedure').val().trim();
			var $msg = $('#rms-procedure-message');

			$msg.hide().html('');

			if (!name) {
				$msg.html('<div class="notice notice-warning inline"><p>Ingrese el nombre del procedimiento.</p></div>').show();
				return;
			}

			$.post(
				rmsAdmin.ajaxUrl,
				{
					action: 'rms_add_procedure',
					nonce:  rmsAdmin.nonce,
					name:   name
				},
				function (response) {
					if (response.success) {
						window.location.reload();
					} else {
						var errMsg = (response.data && response.data.message)
							? response.data.message
							: 'Error al agregar el procedimiento.';
						$msg.html('<div class="notice notice-error inline"><p>' + errMsg + '</p></div>').show();
					}
				}
			).fail(function () {
				$msg.html('<div class="notice notice-error inline"><p>Error de conexión.</p></div>').show();
			});
		}

		/* ---- Delete procedure ---- */
		$(document).on('click', '.rms-btn-del-procedure', function () {
			var $btn = $(this);
			var name = $btn.data('name');

			if (!window.confirm('¿Eliminar el procedimiento "' + name + '"?')) { return; }

			$btn.prop('disabled', true);

			$.post(
				rmsAdmin.ajaxUrl,
				{
					action: 'rms_delete_procedure',
					nonce:  rmsAdmin.nonce,
					name:   name
				},
				function (response) {
					if (response.success) {
						$btn.closest('li').fadeOut(300, function () { $(this).remove(); });
					} else {
						window.alert('Error al eliminar el procedimiento.');
						$btn.prop('disabled', false);
					}
				}
			).fail(function () {
				window.alert('❌ Error de conexión.');
				$btn.prop('disabled', false);
			});
		});

	});

}(jQuery));
