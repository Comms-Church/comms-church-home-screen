/* Church Links — link-list layout. */
(function () {
	'use strict';

	var C = window.CHS || { services: [], weekdays: [], ccModal: false, i18n: {} };
	var i18n = C.i18n || {};
	var $$ = function (s, root) { return Array.prototype.slice.call((root || document).querySelectorAll(s)); };

	/* ---------- Next service and calendar tiles (visitor's own time) ---------- */
	function minutes(t) {
		var m = /^\s*(\d{1,2})(?:[:.](\d{2}))?\s*([ap])?/i.exec(t || '');
		if (!m) return 23 * 60 + 59;
		var h = +m[1], min = +(m[2] || 0), ap = (m[3] || '').toLowerCase();
		if (ap === 'p' && h < 12) h += 12;
		if (ap === 'a' && h === 12) h = 0;
		return h * 60 + min;
	}

	function nextService(now) {
		var nowMin = now.getHours() * 60 + now.getMinutes(), best = null;
		(C.services || []).forEach(function (s) {
			var diff = (s.day - now.getDay() + 7) % 7;
			var start = minutes(s.time);
			// A service stays "today" until 90 minutes after it starts.
			if (diff === 0 && start + 90 < nowMin) diff = 7;
			var score = diff * 1440 + start;
			if (!best || score < best.score) best = { s: s, diff: diff, score: score };
		});
		return best;
	}

	function tick() {
		var now = new Date();
		var dow = now.toLocaleDateString(undefined, { weekday: 'short' });
		$$('[data-dow]').forEach(function (e) { e.textContent = dow; });
		$$('[data-day]').forEach(function (e) { e.textContent = now.getDate(); });
		var n = nextService(now);
		if (!n) return;
		var when = n.diff === 0 ? (i18n.today || 'Today') : n.diff === 1 ? (i18n.tomorrow || 'Tomorrow') : (C.weekdays[n.s.day] || '');
		$$('[data-svc="when"]').forEach(function (e) { e.textContent = when; });
		$$('[data-svc="time"]').forEach(function (e) { e.textContent = n.s.time; });
		$$('[data-svc="sub"]').forEach(function (e) { e.textContent = n.s.label || ''; });
	}
	tick();
	setInterval(tick, 60000);

	/* ---------- Panels: the next-service card opens Visit; a #hash opens its panel ---------- */
	function openPanel(id) {
		var p = document.getElementById('cl-' + id);
		if (!p) return false;
		p.open = true;
		p.scrollIntoView({ behavior: 'smooth', block: 'start' });
		return true;
	}
	$$('[data-panel]').forEach(function (a) {
		a.addEventListener('click', function (e) {
			if (openPanel(a.getAttribute('data-panel'))) e.preventDefault();
		});
	});
	if (/^#cl-/.test(location.hash)) openPanel(location.hash.slice(4));

	/* ---------- Church Center ----------
	   Phones: plain links, which open the Church Center app when it's installed.
	   Computers: Church Center's own pop-up, if the church turned it on. */
	if (C.ccModal && window.matchMedia('(min-width: 721px) and (hover: hover)').matches) {
		$$('a[data-cc]').forEach(function (a) {
			a.setAttribute('data-open-in-church-center-modal', 'true');
			a.removeAttribute('target');
		});
		var sc = document.createElement('script');
		sc.src = 'https://js.churchcenter.com/modal/v1';
		sc.async = true;
		document.head.appendChild(sc);
	}

	// "Get the App" goes straight to the right store.
	var ua = navigator.userAgent || '';
	var isIOS = /iPhone|iPad|iPod/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
	var isAndroid = /Android/.test(ua);
	$$('a[data-ios]').forEach(function (a) {
		if (isIOS) a.href = a.getAttribute('data-ios');
		else if (isAndroid) a.href = a.getAttribute('data-android');
	});

	/* ---------- Desktop: a QR code to carry the page to a phone ---------- */
	var qrBox = document.getElementById('clQr');
	if (qrBox && C.qrLib && window.matchMedia('(min-width: 900px) and (hover: hover)').matches) {
		var lib = document.createElement('script');
		lib.src = C.qrLib;
		lib.onload = function () {
			var qr = window.qrcode(0, 'M');
			qr.addData(C.url);
			qr.make();
			var n = qr.getModuleCount(), q = 2, path = '';
			for (var r = 0; r < n; r++) {
				for (var c = 0; c < n; c++) if (qr.isDark(r, c)) path += 'M' + (c + q) + ' ' + (r + q) + 'h1v1h-1z';
			}
			qrBox.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' + (n + q * 2) + ' ' + (n + q * 2) + '" shape-rendering="crispEdges"><path d="' + path + '" fill="currentColor"/></svg>';
			qrBox.parentNode.hidden = false;
		};
		document.head.appendChild(lib);
	}

	/* ---------- Copy ---------- */
	var toastEl = document.getElementById('toast'), toastTimer;
	function toast(msg) {
		toastEl.textContent = msg;
		toastEl.classList.add('show');
		clearTimeout(toastTimer);
		toastTimer = setTimeout(function () { toastEl.classList.remove('show'); }, 1900);
	}
	function selectValue(btn) {
		var val = btn.parentElement.querySelector('.val');
		if (!val) return;
		var range = document.createRange();
		range.selectNodeContents(val);
		var sel = window.getSelection();
		sel.removeAllRanges();
		sel.addRange(range);
		toast(i18n.selected || 'Selected');
	}
	document.addEventListener('click', function (e) {
		var c = e.target.closest('[data-copy]');
		if (!c) return;
		try {
			navigator.clipboard.writeText(c.getAttribute('data-copy')).then(
				function () { toast(i18n.copied || 'Copied'); },
				function () { selectValue(c); }
			);
		} catch (err) {
			selectValue(c);
		}
	});
})();
