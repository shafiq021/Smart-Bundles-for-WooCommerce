/**
 * Smart Bundles settings: the discount value of the Custom card is shown only when a discount type
 * is chosen, and a value outside the allowed range is flagged right away (the server checks it again).
 */
(function ($) {
	'use strict';

	$(function () {
		var $type = $('.smb-custom-disc-type');
		var $row = $('.smb-custom-disc-row');
		var $val = $('.smb-custom-disc-val');
		var $warn = $('.smb-custom-disc-warn');
		var M = window.smbCustomAdmin || {};

		if (!$type.length || !$val.length) {
			return;
		}

		function check() {
			var type = $type.val();
			var n = parseFloat($val.val());
			var bad = $val.val() !== '' && (isNaN(n) || n < 0 || (type === 'percent' && n > 100));
			$warn.text(bad ? ' ' + (type === 'percent' ? M.percent : M.fixed) : '').toggle(bad);
		}

		function sync() {
			$row.toggle($type.val() !== 'none');
			check();
		}

		$type.on('change', sync);
		$val.on('input change', check);
		sync();
	});
})(jQuery);
