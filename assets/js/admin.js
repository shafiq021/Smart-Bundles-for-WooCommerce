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
					var id = String(d.id);
					var exists = $tbody.find('input[name$="[id]"]').filter(function () { return this.value === id; }).length > 0;
					if (!exists) {
						var i = parseInt($next.attr('data-next'), 10) || 0;
						var html = $tpl.html().replace(/__i__/g, i).replace(/__id__/g, id);
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
