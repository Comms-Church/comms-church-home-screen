/* Church Home Screen — front end behavior. */
(function () {
	'use strict';

	var C = window.CHS || { services: [], weekdays: [], ccModal: false, i18n: {} };
	var $ = function (s) { return document.querySelector(s); };
	var $$ = function (s) { return Array.prototype.slice.call(document.querySelectorAll(s)); };
	var screenEl = $('#screen'), lockEl = $('#lock'), homeEl = $('#home'), sheetEl = $('#sheet'), toastEl = $('#toast');
	var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

	/* ---------- Clock, date and calendar tiles (visitor's own time) ---------- */
	function clock(d) {
		try {
			return new Intl.DateTimeFormat(undefined, { hour: 'numeric', minute: '2-digit' })
				.formatToParts(d)
				.filter(function (p) { return p.type !== 'dayPeriod'; })
				.map(function (p) { return p.value; })
				.join('')
				.trim();
		} catch (e) {
			return (d.getHours() % 12 || 12) + ':' + String(d.getMinutes()).padStart(2, '0');
		}
	}

	function tick() {
		var n = new Date();
		var t = clock(n);
		$('#lockTime').textContent = t;
		$('#statusTime').textContent = t;
		$('#lockDate').textContent = n.toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric' });
		var dow = n.toLocaleDateString(undefined, { weekday: 'short' });
		$$('[data-dow]').forEach(function (e) { e.textContent = dow; });
		$$('[data-day]').forEach(function (e) { e.textContent = n.getDate(); });
		renderService(n);
	}

	/* ---------- Next service ---------- */
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

	var lastSvc = '';
	function renderService(now) {
		var n = nextService(now);
		if (!n) return;
		var i = C.i18n || {};
		var when = n.diff === 0 ? (i.today || 'Today') : n.diff === 1 ? (i.tomorrow || 'Tomorrow') : (C.weekdays[n.s.day] || '');
		var key = when + n.s.time + n.s.label;
		if (key === lastSvc) return;
		lastSvc = key;

		var parts = /^(.*?)(\s*[ap]\.?m\.?)$/i.exec(n.s.time || '');
		var main = parts ? parts[1] : n.s.time;
		var suffix = parts ? parts[2].trim().toUpperCase() : '';
		var whenLower = n.diff <= 1 ? when.toLowerCase() : when;

		$$('[data-svc="when"]').forEach(function (e) { e.textContent = when; });
		$$('[data-svc="time"]').forEach(function (e) { e.textContent = main; });
		$$('[data-svc="sub"]').forEach(function (e) { e.textContent = [suffix, n.s.label].filter(Boolean).join(' · '); });
		$$('[data-svc="note"]').forEach(function (e) {
			e.textContent = (i.note || '%1$s %2$s at %3$s')
				.replace('%1$s', n.s.label || '')
				.replace('%2$s', whenLower)
				.replace('%3$s', n.s.time)
				.trim();
		});
	}

	tick();
	setInterval(tick, 10000);

	/* ---------- Lock and unlock ---------- */
	var locked = true;
	function setP(p) { screenEl.style.setProperty('--p', p.toFixed(3)); }

	function unlock() {
		if (!locked) return;
		locked = false;
		screenEl.classList.remove('dragging');
		screenEl.classList.add('unlocked');
		setP(1);
		homeEl.inert = false;
		lockEl.inert = true;
		$('#statusTime').tabIndex = 0;
		var first = homeEl.querySelector('.app, .widget');
		if (first && document.activeElement && document.activeElement.closest && document.activeElement.closest('#lock')) {
			first.focus({ preventScroll: true });
		}
	}

	function relock() {
		if (locked) return;
		closeSheet(true);
		locked = true;
		screenEl.classList.remove('unlocked');
		setP(0);
		homeEl.inert = true;
		lockEl.inert = false;
		$('#statusTime').tabIndex = -1;
	}

	var drag = null;
	lockEl.addEventListener('pointerdown', function (e) {
		if (!locked) return;
		drag = { y0: e.clientY, y: e.clientY, t: performance.now(), v: 0, dy: 0, h: screenEl.clientHeight, onHint: !!e.target.closest('.unlock-hint') };
		screenEl.classList.add('dragging');
		try { lockEl.setPointerCapture(e.pointerId); } catch (err) {}
	});
	lockEl.addEventListener('pointermove', function (e) {
		if (!drag) return;
		var now = performance.now();
		drag.v = (e.clientY - drag.y) / Math.max(1, now - drag.t);
		drag.y = e.clientY;
		drag.t = now;
		drag.dy = Math.min(0, e.clientY - drag.y0);
		setP(Math.min(1, -drag.dy / drag.h));
	});
	function endDrag() {
		if (!drag) return;
		var d = drag;
		drag = null;
		screenEl.classList.remove('dragging');
		var p = -d.dy / d.h;
		if (p > 0.18 || d.v < -0.5 || (Math.abs(d.dy) < 6 && d.onHint)) unlock();
		else setP(0);
	}
	lockEl.addEventListener('pointerup', endDrag);
	lockEl.addEventListener('pointercancel', endDrag);
	$('#unlockBtn').addEventListener('click', unlock);
	lockEl.addEventListener('wheel', function (e) { if (locked && e.deltaY > 25) unlock(); }, { passive: true });
	$('#statusTime').addEventListener('click', relock);

	/* ---------- App panels ---------- */
	var opener = null, closeTimer = null;

	function openSheet(id, from) {
		var tpl = document.getElementById('sheet-' + id);
		if (!tpl) return;
		clearTimeout(closeTimer);
		var r = from.getBoundingClientRect(), sr = screenEl.getBoundingClientRect();
		sheetEl.style.transformOrigin = (r.left + r.width / 2 - sr.left) + 'px ' + (r.top + r.height / 2 - sr.top) + 'px';
		$('#sheetEyebrow').textContent = tpl.getAttribute('data-eyebrow') || '';
		$('#sheetTitle').textContent = tpl.getAttribute('data-title') || '';
		var body = $('#sheetBody');
		body.innerHTML = '';
		body.appendChild(tpl.content.cloneNode(true));
		body.scrollTop = 0;
		prepareChurchCenter(body);
		opener = from;
		sheetEl.hidden = false;
		homeEl.inert = true;
		requestAnimationFrame(function () {
			requestAnimationFrame(function () {
				sheetEl.classList.add('open');
				screenEl.classList.add('sheet-open');
				$('#doneBtn').focus({ preventScroll: true });
			});
		});
	}

	function closeSheet(instant) {
		if (sheetEl.hidden) return;
		sheetEl.classList.remove('open');
		screenEl.classList.remove('sheet-open');
		if (!locked) homeEl.inert = false;
		if (instant || reduced) sheetEl.hidden = true;
		else closeTimer = setTimeout(function () { sheetEl.hidden = true; }, 460);
		if (opener && !locked) opener.focus({ preventScroll: true });
		opener = null;
	}

	$('#doneBtn').addEventListener('click', function () { closeSheet(); });
	$('#homebar').addEventListener('click', function () {
		if (!sheetEl.hidden) closeSheet();
		else if (locked) unlock();
	});

	/* ---------- Church Center ----------
	   Phones: plain links, which open the Church Center app when it's installed.
	   Computers: Church Center's own pop-up, if the church turned it on. */
	var useModal = !!C.ccModal && window.matchMedia('(min-width: 721px) and (hover: hover)').matches;

	function prepareChurchCenter(root) {
		if (!useModal) return;
		Array.prototype.forEach.call(root.querySelectorAll('a[data-cc]'), function (a) {
			a.setAttribute('data-open-in-church-center-modal', 'true');
			a.removeAttribute('target');
		});
	}

	if (useModal) {
		prepareChurchCenter(document);
		var s = document.createElement('script');
		s.src = 'https://js.churchcenter.com/modal/v1';
		s.async = true;
		document.head.appendChild(s);
	}

	// "Get the App" goes straight to the right store.
	var ua = navigator.userAgent || '';
	var isIOS = /iPhone|iPad|iPod/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
	var isAndroid = /Android/.test(ua);
	$$('a[data-ios]').forEach(function (a) {
		if (isIOS) a.href = a.getAttribute('data-ios');
		else if (isAndroid) a.href = a.getAttribute('data-android');
	});

	/* ---------- Toast and copy ---------- */
	var toastTimer;
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
		toast((C.i18n && C.i18n.selected) || 'Selected');
	}

	function copy(text, btn) {
		try {
			navigator.clipboard.writeText(text).then(
				function () { toast((C.i18n && C.i18n.copied) || 'Copied'); },
				function () { selectValue(btn); }
			);
		} catch (e) {
			selectValue(btn);
		}
	}

	document.addEventListener('click', function (e) {
		var o = e.target.closest('[data-open]');
		if (o) { openSheet(o.getAttribute('data-open'), o); return; }
		var c = e.target.closest('[data-copy]');
		if (c) copy(c.getAttribute('data-copy'), c);
	});

	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape' && !sheetEl.hidden) closeSheet();
		else if (locked && (e.key === 'ArrowUp' || e.key === 'PageUp')) unlock();
	});
})();
