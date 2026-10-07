(function ($) {
	'use strict';

	var D = window.smbData || {};
	var C = D.currency || { symbol: '', decimals: 2, decimal: '.', thousand: ',', format: '%1$s%2$s' };
	var T = D.i18n || { selected: 'selected', save: 'Save', youSave: 'You save', error: 'Could not add the products.' };

	/* ---------- helpers ---------- */

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

	/* ---------- pack offers ---------- */

	function initPacks($wrap) {
		var $form = findForm($wrap);
		var $cards = $wrap.find('.smb-pack');
		var $qty = $form.find('input.qty, input[name="quantity"]').first();
		var syncing = false;

		if ($wrap.attr('data-hide-qty') === '1') {
			$form.addClass('smb-hide-qty');
		}

		function select($card) {
			$cards.removeClass('is-selected');
			$card.addClass('is-selected').find('.smb-radio').prop('checked', true);
		}

		function setQty(q) {
			if (!$qty.length) {
				return;
			}
			syncing = true;
			$qty.val(q).trigger('change');
			syncing = false;
		}

		$wrap.on('change', '.smb-radio', function () {
			var $card = $(this).closest('.smb-pack');
			select($card);
			setQty(parseInt($card.attr('data-qty'), 10) || 1);
		});

		// Initial state
		var $start = $cards.filter('.is-selected').first();
		if ($start.length) {
			setQty(parseInt($start.attr('data-qty'), 10) || 1);
		}

		// Shopper changes the quantity some other way
		$qty.on('change input', function () {
			if (syncing) {
				return;
			}
			var v = parseInt($(this).val(), 10);
			var $m = $cards.filter(function () { return parseInt($(this).attr('data-qty'), 10) === v; }).first();
			$cards.removeClass('is-selected').find('.smb-radio').prop('checked', false);
			if ($m.length) {
				select($m);
			}
		});

		// Variable products: prices follow the chosen variation
		if ($wrap.attr('data-variable') === '1') {
			var orig = [];
			$cards.each(function (i) {
				orig[i] = {
					price: $(this).find('.smb-price').html(),
					strike: $(this).find('.smb-strike').html(),
					pill: $(this).find('.smb-pill').html(),
					sub: $(this).find('.smb-pack-sub').html(),
					hidden: $(this).find('.smb-pill').hasClass('is-hidden')
				};
			});

			$form.on('found_variation', function (e, v) {
				var cur = parseFloat(v.display_price);
				var reg = parseFloat(v.display_regular_price);
				if (isNaN(cur)) {
					return;
				}
				if (isNaN(reg)) {
					reg = cur;
				}
				$cards.each(function () {
					var $c = $(this);
					var qty = parseInt($c.attr('data-qty'), 10) || 1;
					var type = $c.attr('data-type');
					var val = parseFloat($c.attr('data-value')) || 0;
					var total = type === 'fixed' ? parseFloat($c.attr('data-fixed')) : cur * qty * (100 - val) / 100;
					var regT = reg * qty;
					var save = Math.max(0, regT - total);
					var pct = regT > 0 ? save / regT * 100 : 0;
					var show = save > 0;

					$c.find('.smb-price').html(fmt(total));
					$c.find('.smb-strike').html(fmt(regT)).toggleClass('is-hidden', !show);
					$c.find('.smb-pill').html(T.save + ' ' + fmt(save)).toggleClass('is-hidden', !show);
					$c.find('.smb-pack-sub').text(T.youSave + ' ' + parseFloat(pct.toFixed(2)) + '%').toggleClass('is-hidden', !show);
				});
			});

			$form.on('reset_data hide_variation', function () {
				$cards.each(function (i) {
					var $c = $(this);
					$c.find('.smb-price').html(orig[i].price);
					$c.find('.smb-strike').html(orig[i].strike);
					$c.find('.smb-pill').html(orig[i].pill).toggleClass('is-hidden', orig[i].hidden);
					$c.find('.smb-pack-sub').html(orig[i].sub).toggleClass('is-hidden', orig[i].hidden);
					$c.find('.smb-strike').toggleClass('is-hidden', orig[i].hidden);
				});
			});
		}
	}

	/* ---------- bundle offer ---------- */

	function initBundle($wrap) {
		var $form = findForm($wrap);
		if (!$form.length) {
			return;
		}
		var $card = $wrap.find('.smb-bundle-card');
		var $check = $wrap.find('.smb-bundle-check');
		var $input = $form.find('input[name="smb_bundle"]');
		if (!$input.length) {
			$input = $('<input type="hidden" name="smb_bundle" value="">').appendTo($form);
		}

		function update() {
			var on = $check.is(':checked');
			$card.toggleClass('is-selected', on);
			$input.val(on ? '1' : '');
		}

		$check.on('change', update);

		// Themes that add to cart with AJAX: make sure the toggle travels with the request.
		$(document.body).on('adding_to_cart', function (e, $button, data) {
			if (data && typeof data === 'object' && $check.is(':checked')) {
				data.smb_bundle = '1';
			}
		});

		update();
	}

	/* ---------- bought together (shared bits) ---------- */

	function selectedInfo($wrap) {
		var ids = [];
		var total = 0;
		$wrap.find('.smb-check:checked').each(function () {
			var $i = $(this).closest('.smb-item');
			ids.push($i.attr('data-id'));
			total += parseFloat($i.attr('data-price')) || 0;
		});
		return { ids: ids, total: total };
	}

	function renderSummary($wrap, info) {
		var $sum = $wrap.find('.smb-fbt-summary');
		if (!info.ids.length) {
			$sum.addClass('is-hidden').empty();
			return;
		}
		$sum.removeClass('is-hidden').html(info.ids.length + ' ' + T.selected + ' &middot; +' + fmt(info.total));
	}

	/* ---------- bought together: product page ---------- */

	function initFbtProduct($wrap) {
		var $form = findForm($wrap);
		if (!$form.length) {
			return;
		}

		var $input = $form.find('input[name="smb_addons"]');
		if (!$input.length) {
			$input = $('<input type="hidden" name="smb_addons" value="">').appendTo($form);
		}

		function update() {
			var info = selectedInfo($wrap);
			$input.val(info.ids.join(','));
			renderSummary($wrap, info);
		}

		$wrap.on('change', '.smb-check', function () {
			$(this).closest('.smb-item').toggleClass('is-selected', this.checked);
			update();
		});

		// Themes that add to cart with AJAX: make sure the ticked add-ons travel with the request.
		$(document.body).on('adding_to_cart', function (e, $button, data) {
			if (data && typeof data === 'object') {
				var info = selectedInfo($wrap);
				if (info.ids.length) {
					data.smb_addons = info.ids.join(',');
				}
			}
		});

		update();
	}

	/* ---------- bought together: cart page ---------- */

	/**
	 * Swap just the cart region (table + totals + our own block) in place, instead of a full
	 * page reload: find the ".woocommerce" wrapper that [woocommerce_cart] prints, and replace
	 * its contents with the freshly rendered HTML the server sent back.
	 */
	function swapCartRegion(newHtml) {
		var $old = $('.smb-wrap[data-smb="fbt"][data-context="cart"]').closest('.woocommerce').first();
		if (!$old.length) {
			$old = $('form.woocommerce-cart-form, .cart-collaterals').closest('.woocommerce').first();
		}
		if (!$old.length) {
			return false;
		}
		var $new = $('<div>').html(newHtml);
		var $root = $new.find('.woocommerce').first();
		if (!$root.length) {
			$root = $new; // the response had no extra wrapper around the shortcode output
		}
		$old.html($root.html());
		bootWithin($old);
		$(document.body).trigger('updated_cart_totals').trigger('updated_wc_div').trigger('wc_fragment_refresh');
		return true;
	}

	function applyFragments(fragments) {
		if (!fragments) {
			return;
		}
		$.each(fragments, function (selector, html) {
			try {
				$(selector).replaceWith(html);
			} catch (e) { /* not every fragment selector exists on every page; ignore */ }
		});
	}

	function initFbtCart($wrap) {
		var $btn = $wrap.find('.smb-cart-btn');
		var busy = false;

		function update() {
			var info = selectedInfo($wrap);
			$btn.prop('disabled', !info.ids.length);
			renderSummary($wrap, info);
		}

		$wrap.on('change', '.smb-check', function () {
			$(this).closest('.smb-item').toggleClass('is-selected', this.checked);
			update();
		});

		$btn.on('click', function (e) {
			e.preventDefault();
			if (busy) {
				return;
			}
			var info = selectedInfo($wrap);
			if (!info.ids.length || !D.cartAddUrl) {
				return;
			}
			busy = true;
			$btn.addClass('is-loading');
			$.post(D.cartAddUrl, { ids: info.ids.join(','), nonce: D.nonce })
				.done(function (res) {
					if (res && res.success && res.data) {
						applyFragments(res.data.fragments);
						if (!swapCartRegion(res.data.cart_html || '')) {
							window.location.reload(); // markup we didn't expect: fall back safely
						}
						return;
					}
					window.alert(T.error);
				})
				.fail(function () { window.alert(T.error); })
				.always(function () {
					busy = false;
					$btn.removeClass('is-loading'); // harmless no-op if this node was just replaced
				});
		});

		update();
	}

	/* ---------- boot ---------- */

	function bootWithin($scope) {
		$scope.find('.smb-wrap[data-smb="packs"]').each(function () { initPacks($(this)); });
		$scope.find('.smb-wrap[data-smb="bundle"]').each(function () { initBundle($(this)); });
		$scope.find('.smb-wrap[data-smb="fbt"]').each(function () {
			var $w = $(this);
			if ($w.attr('data-context') === 'cart') {
				initFbtCart($w);
			} else {
				initFbtProduct($w);
			}
		});
	}

	$(function () { bootWithin($(document)); });

})(jQuery);
