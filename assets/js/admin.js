/**
 * Graceful Error Pages — Admin settings scripts.
 *
 * Handles color picker initialization, media uploader, tab persistence,
 * and preview iframe. Enqueued only on the plugin's settings page.
 *
 * @package GracefulErrorPages
 */

/* global jQuery, wp, gepAdmin */

(function ($) {
	'use strict';

	/**
	 * Initialize WordPress color pickers on all matching inputs.
	 */
	function initColorPickers() {
		$('.gep-color-picker').wpColorPicker();
	}

	/**
	 * Initialize media uploader buttons.
	 *
	 * Each .gep-media-select button opens the WP media modal and writes
	 * the selected image URL to the input indicated by data-target.
	 * The frame is cached per button to avoid creating multiple instances.
	 */
	function initMediaUploaders() {
		$('.gep-media-select').on('click', function (e) {
			e.preventDefault();

			var button = $(this);
			var targetSelector = button.data('target');
			var targetInput = $(targetSelector);

			var frame = button.data('gepFrame');

			if (!frame) {
				frame = wp.media({
					title: gepAdmin.mediaTitle,
					button: { text: gepAdmin.mediaButton },
					multiple: false,
					library: { type: 'image' }
				});

				frame.on('select', function () {
					var attachment = frame.state().get('selection').first().toJSON();
					targetInput.val(attachment.url).trigger('change');
				});

				button.data('gepFrame', frame);
			}

			frame.open();
		});

		$('.gep-media-remove').on('click', function (e) {
			e.preventDefault();

			var targetSelector = $(this).data('target');
			$(targetSelector).val('').trigger('change');
		});
	}

	/**
	 * Collect current form field values for the preview.
	 *
	 * Reads inputs/selects/textareas from the settings form so the
	 * preview reflects unsaved changes rather than saved options.
	 */
	function collectFormValues() {
		var params = {};
		var $form = $('#gep-settings-form');

		if (!$form.length) {
			return params;
		}

		$form.find('input, select, textarea').each(function () {
			var $el = $(this);
			var name = $el.attr('name');

			if (!name || name.indexOf('gep_') !== 0) {
				return;
			}

			if ($el.is(':radio') && !$el.is(':checked')) {
				return;
			}

			if ($el.is(':checkbox')) {
				params[name] = $el.is(':checked') ? '1' : '0';
				return;
			}

			params[name] = $el.val();
		});

		return params;
	}

	/**
	 * Initialize preview button to open an iframe modal.
	 *
	 * Uses DOM API to build elements safely rather than string concatenation.
	 */
	function initPreview() {
		$('.gep-preview-btn').on('click', function (e) {
			e.preventDefault();

			var triggerBtn = $(this);

			$('.gep-preview-overlay, .gep-preview-modal').remove();
			$(document).off('keydown.gepPreview');

			var formValues = collectFormValues();
			var previewUrl = gepAdmin.ajaxUrl +
				'?action=' + encodeURIComponent(gepAdmin.previewAction) +
				'&_wpnonce=' + encodeURIComponent(gepAdmin.previewNonce);

			$.each(formValues, function (key, value) {
				if (value !== '') {
					previewUrl += '&' + encodeURIComponent(key) + '=' + encodeURIComponent(value);
				}
			});

			var overlay = $('<div class="gep-preview-overlay"></div>');

			var closeBtn = $('<button type="button" class="gep-preview-close"></button>')
				.attr('aria-label', gepAdmin.closeLabel || 'Close')
				.text('×');

			var header = $('<div class="gep-preview-header"></div>').append(closeBtn);

			var iframe = $('<iframe class="gep-preview-iframe"></iframe>')
				.attr('src', previewUrl)
				.attr('title', gepAdmin.previewTitle || 'Preview');

			var modal = $('<div class="gep-preview-modal" role="dialog" aria-modal="true" aria-label="Error page preview"></div>')
				.append(header)
				.append(iframe);

			$('body').append(overlay).append(modal);
			closeBtn.trigger('focus');

			function closePreview() {
				overlay.remove();
				modal.remove();
				$(document).off('keydown.gepPreview');
				triggerBtn.trigger('focus');
			}

			overlay.on('click', closePreview);
			closeBtn.on('click', closePreview);

			var focusable = [closeBtn[0], iframe[0]];

			$(document).on('keydown.gepPreview', function (evt) {
				if (evt.key === 'Escape') {
					closePreview();
					return;
				}

				if (evt.key === 'Tab') {
					var idx = focusable.indexOf(document.activeElement);

					if (evt.shiftKey) {
						idx = idx <= 0 ? focusable.length - 1 : idx - 1;
					} else {
						idx = idx >= focusable.length - 1 ? 0 : idx + 1;
					}

					evt.preventDefault();
					focusable[idx].focus();
				}
			});
		});
	}

	$(document).ready(function () {
		initColorPickers();
		initMediaUploaders();
		initPreview();
	});

})(jQuery);
