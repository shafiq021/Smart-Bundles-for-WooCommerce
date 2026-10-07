(function ($) {
	'use strict';

	$(function () {
		var $form = $('#smb-form');
		var frame = document.getElementById('smb-preview-frame');
		if (!$form.length || !frame) {
			return;
		}
		var S = window.smbSettings || {};
		var ready = false;

		function doc() {
			try {
				return frame.contentDocument;
			} catch (e) {
				return null;
			}
		}

		/* ---------- build the CSS from every control and push it into the preview ---------- */

		function buildCss() {
			var decl = '';
			$form.find('[data-var]').each(function () {
				var $el = $(this);
				var v = $el.val();
				if (v === null || v === '') {
					return;
				}
				if ($el.attr('data-type') === 'size') {
					if (isNaN(parseFloat(v))) {
						return;
					}
					v = parseFloat(v) + ($el.attr('data-unit') || 'px');
				}
				decl += $el.attr('data-var') + ':' + v + ';';
			});
			return '.smb-wrap{' + decl + '}';
		}

		function apply() {
			var d = doc();
			if (!d || !ready) {
				return;
			}
			var s = d.getElementById('smb-live');
			if (s) {
				s.textContent = buildCss();
			}
		}

		var timer = null;
		function schedule() {
			if (timer) {
				window.cancelAnimationFrame(timer);
			}
			timer = window.requestAnimationFrame(function () {
				timer = null;
				apply();
			});
		}

		$(frame).on('load', function () {
			ready = true;
			applyDemo();
			apply();
		});
		// srcdoc may already be parsed by the time this script runs
		if (doc() && doc().getElementById('smb-live') && doc().readyState === 'complete') {
			ready = true;
		}

		/* ---------- colour pickers ---------- */

		$form.find('.smb-color').each(function () {
			var $i = $(this);
			var def = $i.attr('data-default') || '';
			$i.wpColorPicker({
				defaultColor: def,
				change: function (e, ui) {
					$i.val(ui.color.toString());
					schedule();
				},
				clear: function () {
					$i.wpColorPicker('color', def);
					$i.val(def);
					schedule();
				}
			});
		});

		/* ---------- sliders + number boxes ---------- */

		$form.on('input', '.smb-range', function () {
			$(this).siblings('.smb-num').val(this.value);
			schedule();
		});
		$form.on('input change', '.smb-num', function () {
			$(this).siblings('.smb-range').val(this.value);
			schedule();
		});

		/* ---------- selects (+ Google font loading) ---------- */

		function loadFont($opt) {
			var fam = $opt.attr('data-google');
			var d = doc();
			if (!fam || !d) {
				return;
			}
			var id = 'smb-font-' + fam.replace(/\W/g, '');
			if (d.getElementById(id)) {
				return;
			}
			var l = d.createElement('link');
			l.rel = 'stylesheet';
			l.id = id;
			l.href = (S.fontBase || 'https://fonts.googleapis.com/css2?') + 'family=' + fam.replace(/ /g, '+') + ':wght@' + $opt.attr('data-wght') + '&display=swap';
			d.head.appendChild(l);
		}

		$form.on('change', 'select[data-var]', function () {
			loadFont($(this).find('option:selected'));
			schedule();
		});

		/* ---------- live text (titles, button text, extra CSS) ---------- */

		function setTitle(d, demo, v) {
			var $wrap = $(d).find('[data-demo="' + demo + '"] .smb-wrap');
			var $t = $wrap.find('> .smb-title');
			if (!v) {
				$t.remove();
				return;
			}
			if (!$t.length) {
				$t = $('<div class="smb-title"><span></span></div>', d).prependTo($wrap);
			}
			$t.find('span').text(v);
		}

		var live = {
			packs_title: function (d, v) { setTitle(d, 'packs', v); },
			fbt_title: function (d, v) { setTitle(d, 'fbt', v); },
			cart_title: function (d, v) { setTitle(d, 'cart', v); },
			cart_btn_text: function (d, v) { $(d).find('.smb-cart-btn').text(v); },
			bundle_title: function (d, v) { setTitle(d, 'bundle', v); },
			bundle_name_default: function (d, v) { $(d).find('[data-demo="bundle"] .smb-bundle-name').text(v); },
			custom_css: function (d, v) {
				var s = d.getElementById('smb-custom');
				if (s) {
					s.textContent = v;
				}
			}
		};

		$form.on('input', '[data-live]', function () {
			var d = doc();
			var fn = live[$(this).attr('data-live')];
			if (d && ready && fn) {
				fn(d, $(this).val());
			}
		});

		/* ---------- tabs ---------- */

		function showDemo(which) {
			$('.smb-seg[data-group="demo"] button').removeClass('is-active').filter('[data-demo="' + which + '"]').addClass('is-active');
			applyDemo();
		}

		function applyDemo() {
			var d = doc();
			if (!d || !d.body) {
				return;
			}
			var which = $('.smb-seg[data-group="demo"] .is-active').attr('data-demo') || 'all';
			$(d).find('section[data-demo]').each(function () {
				this.hidden = which !== 'all' && this.getAttribute('data-demo') !== which;
			});
			d.body.classList.toggle('is-single', which !== 'all');
		}

		$form.on('click', '.smb-tab', function () {
			var $t = $(this);
			$('.smb-tab').removeClass('is-active');
			$t.addClass('is-active');
			$('.smb-panel').removeClass('is-active').filter('[data-panel="' + $t.attr('data-tab') + '"]').addClass('is-active');
			showDemo($t.attr('data-demo') || 'all');
		});

		/* ---------- preview toolbar ---------- */

		$('.smb-seg[data-group="demo"]').on('click', 'button', function () {
			showDemo($(this).attr('data-demo'));
		});

		$('.smb-seg[data-group="device"]').on('click', 'button', function () {
			var $b = $(this);
			$b.siblings().removeClass('is-active');
			$b.addClass('is-active');
			$(frame).css('width', $b.attr('data-w'));
		});

		/* ---------- reset ---------- */

		$form.on('click', '.smb-reset', function () {
			if (!window.confirm(S.confirm || 'Reset all styles?')) {
				return;
			}
			$form.find('[data-var]').each(function () {
				var $el = $(this);
				var def = $el.attr('data-default');
				if ($el.hasClass('smb-color')) {
					$el.wpColorPicker('color', def);
				}
				$el.val(def);
				if ($el.hasClass('smb-num')) {
					$el.siblings('.smb-range').val(def);
				}
				if ($el.is('select')) {
					loadFont($el.find('option:selected'));
				}
			});
			schedule();
		});
	});
})(jQuery);
