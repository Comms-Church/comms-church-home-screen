/* Church Links — settings page. */
jQuery(function ($) {
	'use strict';

	var A = window.CHSAdmin || { types: {} };
	var $apps = $('#chs-apps');
	var tmpl = $('#tmpl-chs-app-row').html();
	var next = $apps.children().length + 1000;

	function luminance(hex) {
		hex = (hex || '').replace('#', '');
		if (hex.length !== 6) return 0;
		var c = [0, 2, 4].map(function (i) {
			var v = parseInt(hex.substr(i, 2), 16) / 255;
			return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
		});
		return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2];
	}

	function paint($row) {
		var color = $row.find('.chs-color').val();
		$row.find('.chs-swatch').css({ '--c': color, '--g': luminance(color) > 0.45 ? '#1c1e33' : '#ffffff' });
		$row.attr('data-type', $row.find('.chs-type').val());
	}

	// Rename inputs so the saved order matches the on-screen order.
	function reindex() {
		$apps.children('.chs-app').each(function (i) {
			$(this).find('[name]').each(function () {
				this.name = this.name.replace(/\[apps\]\[[^\]]+\]/, '[apps][' + i + ']');
			});
		});
	}

	$apps.sortable({ handle: '.chs-handle', axis: 'y', tolerance: 'pointer', update: reindex });

	$('#chs-add-app').on('click', function () {
		var $row = $(tmpl.replace(/__i__/g, String(next++)));
		$apps.append($row);
		paint($row);
		reindex();
		$row.find('.chs-type').trigger('focus');
	});

	$apps.on('click', '.chs-remove', function () {
		$(this).closest('.chs-app').remove();
		reindex();
	});

	$apps.on('change', '.chs-type', function () {
		var $row = $(this).closest('.chs-app');
		var t = A.types[this.value];
		if (t) {
			$row.find('.chs-label').val(t.label);
			$row.find('.chs-icon').val(t.icon);
			$row.find('.chs-color').val(t.color);
		}
		paint($row);
	});

	$apps.on('input change', '.chs-color', function () {
		paint($(this).closest('.chs-app'));
	});

	$apps.children('.chs-app').each(function () { paint($(this)); });

	// Wallpaper photo.
	var frame;
	$('#chs-wall-choose').on('click', function (e) {
		e.preventDefault();
		if (!frame) {
			frame = wp.media({ title: A.chooseText, button: { text: A.useText }, library: { type: 'image' }, multiple: false });
			frame.on('select', function () {
				var img = frame.state().get('selection').first().toJSON();
				var src = (img.sizes && img.sizes.medium) ? img.sizes.medium.url : img.url;
				$('#chs-wall-id').val(img.id);
				$('#chs-wall-preview').attr('src', src).prop('hidden', false);
				$('input[name$="[wallpaper]"][value="image"]').prop('checked', true);
			});
		}
		frame.open();
	});

	$('#chs-form').on('submit', reindex);

	// QR code for the links page, drawn here so it always matches the current address.
	var $qr = $('#chs-qr');
	if ($qr.length && window.qrcode) {
		var qr = window.qrcode(0, 'M');
		qr.addData($qr.data('url'));
		qr.make();
		var n = qr.getModuleCount(), quiet = 4, size = n + quiet * 2, path = '';
		for (var r = 0; r < n; r++) {
			for (var c = 0; c < n; c++) {
				if (qr.isDark(r, c)) path += 'M' + (c + quiet) + ' ' + (r + quiet) + 'h1v1h-1z';
			}
		}
		var svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' + size + ' ' + size + '" shape-rendering="crispEdges">' +
			'<rect width="100%" height="100%" fill="#fff"/><path d="' + path + '" fill="#000"/></svg>';
		$qr.html(svg);

		var save = function (href, ext) {
			var a = document.createElement('a');
			a.href = href;
			a.download = (A.qrName || 'links-qr') + '.' + ext;
			document.body.appendChild(a);
			a.click();
			a.remove();
		};
		$('[data-qr-download]').on('click', function () {
			var blob = new Blob([svg], { type: 'image/svg+xml' });
			var url = URL.createObjectURL(blob);
			if ($(this).data('qr-download') === 'svg') {
				save(url, 'svg');
				setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
				return;
			}
			// PNG at print size: 1200px wide, sharp edges.
			var img = new Image();
			img.onload = function () {
				var canvas = document.createElement('canvas');
				canvas.width = canvas.height = 1200;
				var ctx = canvas.getContext('2d');
				ctx.imageSmoothingEnabled = false;
				ctx.drawImage(img, 0, 0, 1200, 1200);
				URL.revokeObjectURL(url);
				save(canvas.toDataURL('image/png'), 'png');
			};
			img.src = url;
		});
	}
});
