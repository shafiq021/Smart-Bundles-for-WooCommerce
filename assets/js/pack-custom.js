/**
 * Smart Bundles: the Custom card of the pack offers (quantity stepper + variation dropdown).
 * Loaded only when a pack offer with a Custom card is printed. It only DISPLAYS prices: the server
 * printed the unit price per variation (see SMB_Custom_Pack::pricing()) and the cart recalculates
 * everything itself, so nothing sent from here is ever used as a price.
 */
(function ($) {
	'use strict';

	var D = window.smbData || {};
	var C = D.currency || { symbol: '', decimals: 2, decimal: '.', thousand: ',', format: '%1$s%2$s' };
	var T = D.i18n || { save: 'Save', youSave: 'You save' };

	/* same formatting as frontend.js, so the numbers look like the other pack cards */
	function fmt(n) {
		var d = parseInt(C.decimals, 10) || 0;
		var f = Math.pow(10, d);
		n = Math.round(n * f) / f;
		var parts = Math.abs(n).toFixed(d).split('.');
		parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, C.thousand);
		var num = parts.length > 1 ? parts[0] + C.decimal + parts[1] : parts[0];
		var out = C.format
			.replace('%1$s', '<span class="woocommerce-Price-currencySymbol">' + C.symbol + '</span>')
			.replace('%2$s', num);
		return '<span class="woocommerce-Price-amount amount"><bdi>' + out + '</bdi></span>';
	}

	function findForm($wrap) {
		var $f = $wrap.closest('.product').find('form.cart').first();
		return $f.length ? $f : $('form.cart').first();
	}

	function init($wrap) {
		var $card = $wrap.find('.smb-pack--custom').first();
		if (!$card.length || $card.data('smbCustomReady')) {
			return;
		}
		$card.data('smbCustomReady', true);

		var $form = findForm($wrap);
		var $qty = $form.find('input.qty, input[name="quantity"]').first();
		var $radio = $card.find('.smb-radio');
		var $sel = $card.find('.smb-custom-var');
		var $num = $card.find('.smb-custom-qty');
		var $minus = $card.find('.smb-custom-minus');
		var $plus = $card.find('.smb-custom-plus');
		var variable = $card.attr('data-variable') === '1';
		var syncing = false;

		// Tells the server this add to cart comes from the Custom card (the server re-checks everything)
		var $flag = $form.find('input[name="smb_pack_custom"]');
		if ($form.length && !$flag.length) {
			$flag = $('<input type="hidden" name="smb_pack_custom" value="">').appendTo($form);
		}

		/* ---------- what is chosen right now ---------- */

		function current() {
			var $s = variable ? $sel.find('option:selected') : $card;
			return {
				regular: parseFloat($s.attr('data-regular')) || 0,
				unit: parseFloat($s.attr('data-unit')) || 0,
				max: parseInt($s.attr('data-max'), 10) || 0 // 0 = no limit
			};
		}

		function clamp(q, max) {
			q = parseInt(q, 10);
			if (isNaN(q) || q < 1) {
				q = 1;
			}
			if (max > 0 && q > max) {
				q = max;
			}
			return q;
		}

		/* ---------- price display ---------- */

		function render(it, q) {
			var total = it.unit * q;
			var regT = it.regular * q;
			var save = Math.max(0, regT - total);
			var pct = regT > 0 ? save / regT * 100 : 0;
			var show = save > 0;

			$card.find('.smb-price').html(fmt(total));
			$card.find('.smb-strike').html(fmt(regT)).toggleClass('is-hidden', !show);
			$card.find('.smb-pill').html(T.save + ' ' + fmt(save)).toggleClass('is-hidden', !show);
			$card.find('.smb-pack-sub').text(T.youSave + ' ' + parseFloat(pct.toFixed(2)) + '%').toggleClass('is-hidden', !show);
		}

		function pushQty(q) {
			if (!$qty.length) {
				return;
			}
			syncing = true;
			$qty.val(q).trigger('change');
			syncing = false;
		}

		function refresh(push) {
			var it = current();
			var q = clamp($num.val(), it.max);
			$num.val(q);
			if (it.max > 0) {
				$num.attr('max', it.max);
			} else {
				$num.removeAttr('max');
			}
			$minus.prop('disabled', q <= 1);
			$plus.prop('disabled', it.max > 0 && q >= it.max);
			if (push) {
				pushQty(q);
			}
			render(it, q);
		}

		/* The native form owns the variation (that is what the Add to cart button submits):
		   copy the chosen combination into its attribute fields. */
		function pushVariation() {
			if (!variable || !$form.length) {
				return;
			}
			var attrs = {};
			try {
				attrs = JSON.parse($sel.find('option:selected').attr('data-attrs') || '{}');
			} catch (e) {
				attrs = {};
			}
			$.each(attrs, function (name, value) {
				var $f = $form.find('select, input').filter(function () { return this.name === name; });
				if ($f.length && $f.val() !== value) {
					$f.val(value).trigger('change');
				}
			});
		}

		/* ---------- selecting the card ---------- */

		function pick() {
			if ($card.hasClass('is-selected')) {
				return;
			}
			$wrap.find('.smb-pack').removeClass('is-selected');
			$card.addClass('is-selected');
			$radio.prop('checked', true);
			$flag.val('1');
			pushVariation();
			refresh(true);
		}

		$radio.on('change', pick);

		// Any other pack card chosen: the add to cart is a normal pack add again
		$wrap.on('change', '.smb-pack:not(.smb-pack--custom) .smb-radio', function () {
			$flag.val('');
		});

		// Touching the controls selects the card (a click on a button inside a label does not)
		$card.find('.smb-custom-controls').on('click focusin', pick);

		/* ---------- stepper + dropdown ---------- */

		$minus.on('click', function () {
			pick();
			$num.val(clamp(($num.val() || 1) - 1, current().max));
			refresh(true);
		});
		$plus.on('click', function () {
			pick();
			$num.val(clamp((parseInt($num.val(), 10) || 1) + 1, current().max));
			refresh(true);
		});
		$num.on('input', function () {
			pick();
			if (this.value !== '') {
				refresh(true);
			}
		});
		$num.on('change blur', function () {
			refresh(true);
		});

		$sel.on('change', function () {
			pick();
			pushVariation();
			refresh(true);
		});

		// Variable products: the native form found a variation (for example the shopper used its own dropdowns)
		$form.on('found_variation', function (e, v) {
			if (!variable || !$card.hasClass('is-selected') || !v || !v.variation_id) {
				return;
			}
			var $o = $sel.find('option[value="' + v.variation_id + '"]');
			if ($o.length && !$o.prop('disabled')) {
				$sel.val(String(v.variation_id));
				refresh(false);
			}
		});

		// The shopper changed the native quantity box (visible when the Quantity box setting is off)
		$qty.on('change input', function () {
			if (syncing || !$card.hasClass('is-selected')) {
				return;
			}
			$num.val(this.value);
			refresh(false);
		});

		// Themes that add to cart with AJAX: make sure the flag travels with the request
		$(document.body).on('adding_to_cart', function (e, $button, data) {
			if (data && typeof data === 'object' && $card.hasClass('is-selected')) {
				data.smb_pack_custom = '1';
			}
		});

		refresh(false);
	}

	$(function () {
		$('.smb-wrap[data-smb="packs"]').each(function () {
			init($(this));
		});
	});

})(jQuery);
