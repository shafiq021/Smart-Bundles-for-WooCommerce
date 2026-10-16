(function ($) {
	'use strict';

	$(function () {
		var $table = $('.smb-badge-table');
		if (!$table.length) {
			return;
		}
		var $tbody = $table.find('tbody');
		var $empty = $('.smb-badge-empty');
		var $next = $('.smb-badge-next');
		var $search = $('.smb-badge-search');

		function refresh() {
			var has = $tbody.children().length > 0;
			$table.toggle(has);
			$empty.toggle(!has);
		}

		// Picking a product in the search box adds a row (once per product) and clears the box.
		$search.on('select2:select', function (e) {
			var d = e.params && e.params.data ? e.params.data : null;
			if (d && d.id) {
				var id = String(d.id);
				var exists = $tbody.find('input[name$="[id]"]').filter(function () { return this.value === id; }).length > 0;
				if (!exists) {
					var i = parseInt($next.attr('data-next'), 10) || 0;
					var $row = $($('#smb-badge-tpl').html().replace(/__i__/g, i).replace(/__id__/g, id));
					$row.find('.smb-bname').text(d.text || ('#' + id));
					$tbody.append($row);
					$next.attr('data-next', i + 1);
					$row.find('input[type="text"]').trigger('focus');
				}
			}
			$search.val(null).trigger('change');
			refresh();
		});

		$table.on('click', '.smb-badge-remove', function () {
			$(this).closest('tr').remove();
			refresh();
		});

		refresh();
	});
})(jQuery);
