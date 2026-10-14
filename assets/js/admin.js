(function ($) {
	'use strict';

	$(function () {
		var $box = $('.smb-admin');
		if (!$box.length) {
			return;
		}

		/* ---------- pack rows ---------- */

		$box.on('click', '.smb-add', function () {
			var $btn = $(this);
			var i = parseInt($btn.attr('data-next'), 10) || 0;
			var html = $('#smb-row-tpl').html().replace(/__i__/g, i);
			$box.find('.smb-packs tbody').append(html);
			$btn.attr('data-next', i + 1);
		});

		$box.on('click', '.smb-remove', function () {
			$(this).closest('tr').remove();
		});

		/* ---------- pack style: pack cards vs. quantity ranges ---------- */

		var $mode = $box.find('.smb-pack-mode');

		function syncMode() {
			var ranges = $mode.val() === 'ranges';
			$box.find('.smb-mode-ranges').toggle(ranges);
			$box.find('.smb-mode-cards').toggle(!ranges);
		}
		$mode.on('change', syncMode);
		syncMode();

		$box.on('click', '.smb-add-range', function () {
			var $btn = $(this);
			var i = parseInt($btn.attr('data-next'), 10) || 0;
			$box.find('.smb-ranges tbody').append($('#smb-range-tpl').html().replace(/__i__/g, i));
			$btn.attr('data-next', i + 1);
		});

		$box.on('click', '.smb-remove-range', function () {
			$(this).closest('tr').remove();
			$box.find('.smb-range-errors').hide();
		});

		/* Same rules as SMB_Pricing::validate_ranges(): the server has the last word, this just
		 * warns before the page is sent. */
		function rangeText(from, to) {
			if (!to) {
				return from + '+';
			}
			return from === to ? String(from) : from + '\u2013' + to;
		}

		function rangeErrors() {
			var errors = [];
			var ok = [];
			var n = 0;
			var int = /^\d+$/;
			$box.find('.smb-ranges tbody tr').each(function () {
				var $r = $(this);
				var from = $.trim($r.find('.smb-range-from').val());
				var to = $.trim($r.find('.smb-range-to').val());
				var val = $.trim($r.find('.smb-range-value').val());
				if (from === '' && to === '' && val === '') {
					return;
				}
				n++;
				var bad = false;
				if (!int.test(from) || parseInt(from, 10) < 1) {
					errors.push('Range ' + n + ': "From" must be a whole number, 1 or more.');
					bad = true;
				}
				if (to !== '' && (!int.test(to) || (int.test(from) && parseInt(to, 10) < parseInt(from, 10)))) {
					errors.push('Range ' + n + ': "To" must be a whole number that is not lower than "From" (leave it empty for "and up").');
					bad = true;
				}
				if (val === '' || isNaN(Number(val)) || Number(val) < 0 || Number(val) > 100) {
					errors.push('Range ' + n + ': the discount must be a number from 0 to 100.');
					bad = true;
				}
				if (!bad) {
					ok.push({ from: parseInt(from, 10), to: to === '' ? 0 : parseInt(to, 10) });
				}
			});
			ok.sort(function (a, b) { return a.from - b.from; });
			for (var i = 1; i < ok.length; i++) {
				if (ok[i - 1].to === 0 || ok[i].from <= ok[i - 1].to) {
					errors.push('Ranges ' + rangeText(ok[i - 1].from, ok[i - 1].to) + ' and ' + rangeText(ok[i].from, ok[i].to) + ' overlap.');
				}
			}
			if (!errors.length && n === 0) {
				errors.push('Add at least one quantity range, or switch the pack style back to pack cards.');
			}
			return errors;
		}

		$box.closest('form').on('submit', function (e) {
			if ($mode.val() !== 'ranges') {
				return;
			}
			var errors = rangeErrors();
			var $out = $box.find('.smb-range-errors');
			if (!errors.length) {
				$out.hide().empty();
				return;
			}
			e.preventDefault();
			$out.empty().append($('<p>').text(errors.join(' '))).show();
			$out[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
			// WordPress greys the Update button and starts its spinner before this runs; undo that.
			setTimeout(function () {
				$('.spinner').removeClass('is-active');
				$('#publish, #save-post').removeClass('disabled button-disabled').prop('disabled', false);
			}, 0);
		});

		/* ---------- picked products table(s): one per .smb-item-picker ---------- */

		$box.find('.smb-item-picker').each(function () {
			var $picker = $(this);
			var $table = $picker.find('.smb-itemtable');
			var $tbody = $table.find('tbody');
			var $empty = $picker.find('.smb-empty');
			var $next = $picker.find('.smb-items-next');
			var $tpl = $picker.find('.smb-item-tpl');

			function refreshEmpty() {
				var has = $tbody.children().length > 0;
				$table.toggle(has);
				$empty.toggle(!has);
			}

			$tbody.sortable({
				handle: '.smb-col-handle',
				axis: 'y',
				items: '> tr',
				helper: function (e, tr) {
					// keep column widths while dragging
					var $cells = tr.children();
					var $helper = tr.clone();
					$helper.children().each(function (i) {
						$(this).width($cells.eq(i).width());
					});
					return $helper;
				}
			});

			$picker.on('click', '.smb-remove-item', function () {
				$(this).closest('tr').remove();
				refreshEmpty();
			});

			// Pick a product in the search box -> becomes a table row
			$picker.find('.smb-product-search').on('select2:select', function (e) {
				var d = e.params && e.params.data ? e.params.data : null;
				var $sel = $(this);
				if (d && d.id) {
					// Picker values are "productId:variationId" (variation 0 = a simple product, or a
					// variable product whose variation the shopper chooses).
					var bits = String(d.id).split(':');
					var id = String(parseInt(bits[0], 10) || 0);
					var vid = String(parseInt(bits[1], 10) || 0);
					var exists = $tbody.children('tr').filter(function () {
						var $r = $(this);
						return $r.find('input[name$="[id]"]').val() === id && $r.find('input[name$="[variation_id]"]').val() === vid;
					}).length > 0;
					if (id !== '0' && !exists) {
						var i = parseInt($next.attr('data-next'), 10) || 0;
						var html = $tpl.html().replace(/__i__/g, i).replace(/__id__/g, id).replace(/__vid__/g, vid);
						var $row = $(html);
						$row.find('.smb-pname').text(String(d.text || '').replace(/\s*\(#\d+\)\s*$/, ''));
						$tbody.append($row);
						$next.attr('data-next', i + 1);
					}
				}
				// clear the search box so the next product can be picked
				$sel.val(null).trigger('change');
				refreshEmpty();
			});

			refreshEmpty();
		});

		/* ---------- show category / tag pickers only when relevant ---------- */

		function toggle() {
			var v = $box.find('.smb-pick').val();
			$box.find('.smb-if-cats').toggle(v === 'cats');
			$box.find('.smb-if-tags').toggle(v === 'tags');
		}
		$box.on('change', '.smb-pick', toggle);
		toggle();

		/* ---------- pack offers vs. bundle offer: mutually exclusive per product ----------
		 * The server always makes the bundle win (see SMB_Source::config()) regardless of what's
		 * saved here, so this just keeps the admin's "Show pack offers" tick untouched (nothing
		 * to re-check later) and shows a note instead of disabling the field. */

		var $bundleOn = $box.find('.smb-bundle-on');
		var $bundleNote = $box.find('.smb-packs-bundle-note');

		function syncPacksBundle() {
			$bundleNote.toggle($bundleOn.is(':checked'));
		}
		$bundleOn.on('change', syncPacksBundle);
		syncPacksBundle();
	});
})(jQuery);
