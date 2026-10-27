/*! CookieKit yönetim ekranı davranışları. Bağımlılık yok; JS kapalıyken formlar yine çalışır. */
(function () {
	'use strict';
	var d = document, wrap = d.getElementById('ck');
	if (!wrap) { return; }
	var $ = function (s, r) { return (r || d).querySelector(s); };
	var $$ = function (s, r) { return Array.prototype.slice.call((r || d).querySelectorAll(s)); };
	var cfg = {};
	try { cfg = JSON.parse(wrap.getAttribute('data-cfg') || '{}'); } catch (e) { cfg = {}; }
	var I = cfg.i18n || {};
	var dialogs = $$('.ck-dlg');
	d.documentElement.classList.add('ck-js');
	wrap.classList.add('ck-js');

	/* Koyu ton: yönetim ekranı koyuysa (Midnight / koyu mod eklentileri) bileşenler de koyu çizilir. */
	function tone() {
		var dark = false, nodes = [d.getElementById('wpcontent'), d.getElementById('wpwrap'), d.body, d.documentElement];
		for (var i = 0; i < nodes.length; i++) {
			if (!nodes[i]) { continue; }
			var m = /rgba?\((\d+)[ ,]+(\d+)[ ,]+(\d+)(?:[ ,/]+([\d.]+))?/.exec(getComputedStyle(nodes[i]).backgroundColor || '');
			if (m && (m[4] === undefined || +m[4] > 0)) { dark = (0.2126 * +m[1] + 0.7152 * +m[2] + 0.0722 * +m[3]) < 110; break; }
		}
		[wrap].concat(dialogs).forEach(function (n) { if (dark) { n.setAttribute('data-tone', 'koyu'); } else { n.removeAttribute('data-tone'); } });
	}
	tone();

	/* Hata özeti varsa odaklan */
	var err = d.getElementById('ck-err');
	if (err) { err.focus(); }

	/* Kaydet barı: kirli izleme */
	var anyDirty = false, submitting = false;
	$$('form[data-savebar]').forEach(function (form) {
		var bar = $('[data-bar]', form);
		function serial() { return JSON.stringify($$('input,textarea,select', form).map(function (i) { return i.type === 'checkbox' || i.type === 'radio' ? i.checked : i.value; })); }
		var snap = serial();
		function chk() { var dirty = serial() !== snap; bar.classList.toggle('is-dirty', dirty); anyDirty = dirty; }
		form.addEventListener('input', chk);
		form.addEventListener('change', chk);
		var rv = $('[data-revert]', form);
		if (rv) { rv.addEventListener('click', function () { form.reset(); syncColors(); form.dispatchEvent(new Event('change', { bubbles: true })); chk(); }); }
		form.addEventListener('submit', function () { submitting = true; });
		if (err) { bar.classList.add('is-dirty'); }
	});
	window.addEventListener('beforeunload', function (e) { if (anyDirty && !submitting) { e.preventDefault(); } });

	/* Sayaç */
	$$('[data-count]').forEach(function (el) {
		var max = +el.getAttribute('data-count'), c = el.closest('.ck-field__c'), out = c && $('.ck-counter', c);
		if (!out) { return; }
		function upd() { out.textContent = el.value.length + ' / ' + max; }
		el.addEventListener('input', upd);
		upd();
	});

	/* Sayfa listesinden bağlantı doldur */
	$$('[data-fill]').forEach(function (s) {
		s.addEventListener('change', function () {
			var t = d.getElementById(s.getAttribute('data-fill'));
			if (s.value && t) { t.value = s.value; t.dispatchEvent(new Event('input', { bubbles: true })); }
		});
	});

	/* Renk kaynağı + seçici ↔ hex + kontrast */
	var colors = d.getElementById('colors');
	function lum(h) {
		var c = [1, 3, 5].map(function (i) { var v = parseInt(h.substr(i, 2), 16) / 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); });
		return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2];
	}
	function contrast() {
		var box = d.getElementById('contrast'), bgE = d.getElementById('c-bg'), fgE = d.getElementById('c-fg');
		if (!box || !bgE || !fgE) { return; }
		var bg = bgE.value || '#ffffff', fg = fgE.value || '#1a1a1a', hexRe = /^#[0-9a-f]{6}$/i, txt = box.lastChild;
		if (!hexRe.test(bg) || !hexRe.test(fg)) { box.className = 'ck-note ck-note--warn'; txt.textContent = I.nocalc || ''; return; }
		var a = lum(bg), b = lum(fg), r = (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05), pass = r >= 4.5;
		box.className = 'ck-note ' + (pass ? 'ck-note--ok' : 'ck-note--err');
		$('use', box).setAttribute('href', pass ? '#i-check' : '#i-alert');
		txt.textContent = (I.contrast || '') + ': ' + r.toFixed(1).replace('.', ',') + ':1 — ' + (pass ? I.aa_ok : I.aa_fail);
	}
	function syncColors() {
		$$('[data-hex]').forEach(function (t) {
			var p = $('[data-pair="' + t.id + '"]');
			if (p && /^#[0-9a-f]{6}$/i.test(t.value)) { p.value = t.value; }
		});
		var sel = $('[name=renk_kaynak]:checked');
		if (colors && sel) { colors.hidden = sel.value !== 'ozel'; }
		contrast();
	}
	if (colors) {
		$$('[name=renk_kaynak]').forEach(function (r) {
			r.addEventListener('change', function () {
				var ozel = $('[name=renk_kaynak]:checked').value === 'ozel';
				colors.hidden = !ozel;
				if (!ozel) { $$('[data-hex]').forEach(function (i) { i.value = ''; }); }
				else { $$('[data-pair]').forEach(function (p) { var t = d.getElementById(p.getAttribute('data-pair')); if (t && !t.value) { t.value = p.value; } }); }
				contrast();
				pushPreview();
			});
		});
		$$('[data-pair]').forEach(function (p) {
			p.addEventListener('input', function () { var t = d.getElementById(p.getAttribute('data-pair')); if (t) { t.value = p.value; t.dispatchEvent(new Event('input', { bubbles: true })); } });
		});
		$$('[data-hex]').forEach(function (t) { t.addEventListener('input', syncColors); });
		contrast();
	}

	/* Kopyala */
	$$('[data-copy]').forEach(function (b) {
		b.addEventListener('click', function () {
			var src = d.getElementById(b.getAttribute('data-copy')), live = d.getElementById('copy-live'), lab = b.lastChild;
			if (!src) { return; }
			var old = lab.textContent;
			function show(text) {
				lab.textContent = text;
				if (live) { live.textContent = text; }
				setTimeout(function () { lab.textContent = old; if (live) { live.textContent = ''; } }, 1600);
			}
			function done() { show(I.copied || 'OK'); }
			/* Pano erişimi yoksa (http, izin) önce eski yöntem; o da olmazsa metni seçip kullanıcıya bildir. */
			function fail() {
				var ta = d.createElement('textarea'), ok = false;
				ta.value = src.textContent; ta.setAttribute('readonly', ''); ta.style.cssText = 'position:fixed;top:0;left:0;opacity:0';
				d.body.appendChild(ta); ta.select();
				try { ok = d.execCommand('copy'); } catch (e) { ok = false; }
				ta.remove();
				if (ok) { done(); return; }
				var r = d.createRange(); r.selectNodeContents(src);
				var sel = window.getSelection(); sel.removeAllRanges(); sel.addRange(r);
				show(I.copyfail || '');
			}
			if (navigator.clipboard && navigator.clipboard.writeText) { navigator.clipboard.writeText(src.textContent).then(done, fail); } else { fail(); }
		});
	});

	/* Pencereler */
	$$('[data-dialog]').forEach(function (b) {
		b.addEventListener('click', function () {
			var dl = d.getElementById(b.getAttribute('data-dialog'));
			if (!dl || !dl.showModal) { return; }
			tone();
			dl.showModal();
			if (dl.id === 'dlg-prev') { pushPreview(); }
		});
	});
	$$('[data-close]').forEach(function (b) { b.addEventListener('click', function () { var dl = b.closest('dialog'); if (dl) { dl.close(); } }); });
	$$('[data-rm]').forEach(function (b) {
		b.addEventListener('click', function () {
			var dl = d.getElementById('dlg-rmcookie'), id = d.getElementById('rm-id'), h = d.getElementById('dc-h');
			if (!dl || !id) { return; }
			id.value = b.getAttribute('data-rm');
			if (h && I.rm_title) { h.textContent = I.rm_title.replace('%s', b.getAttribute('data-rm-name') || ''); }
			tone();
			dl.showModal();
		});
	});
	var pt = d.getElementById('purge-t'), pok = d.getElementById('purge-ok');
	if (pt && pok) {
		pt.addEventListener('input', function () { pok.disabled = pt.value.trim().toLocaleUpperCase('tr') !== (I.confirm || 'SİL'); });
	}
	$$('[data-do=reset-texts]').forEach(function (b) {
		b.addEventListener('click', function () {
			var def = cfg.defaults || {}, map = { title: 't-title', desc: 't-desc', accept: 't-accept', reject: 't-reject', prefs: 't-prefs', save: 't-save' };
			Object.keys(map).forEach(function (k) {
				var el = d.getElementById(map[k]);
				if (el && typeof def[k] === 'string') { el.value = def[k]; el.dispatchEvent(new Event('input', { bubbles: true })); }
			});
		});
	});

	/* Canlı önizleme */
	var tplEl = d.getElementById('ck-tpl-prev'), slots = {}, SC = { desk: 800, mob: 375 }, ptimer;
	function fit(st) {
		var mob = $('input[value=mob]', st.root).checked, w = mob ? SC.mob : SC.desk, cw = st.stage.clientWidth || 400;
		var scale = Math.min(1, (cw - (mob ? 24 : 0)) / w), h = st.stage.clientHeight / scale;
		st.frame.style.width = w + 'px';
		st.frame.style.height = h + 'px';
		st.frame.style.left = Math.max(0, (cw - w * scale) / 2) + 'px';
		st.frame.style.transform = 'scale(' + scale + ')';
	}
	function val(name) { var e = $('[name="cookiekit_settings[' + name + ']"]'); return e ? e.value : null; }
	function settings() {
		var s = {}, pos = $('[name="cookiekit_settings[position]"]:checked'), tur = $('[name="cookiekit_settings[_tur]"]:checked');
		if (pos) { s.position = pos.value; }
		['color_accent', 'color_bg', 'color_text', 'title', 'desc', 'accept', 'reject', 'prefs', 'save', 'privacy_url', 'cookie_url'].forEach(function (k) {
			var v = val(k);
			if (v !== null) { s[k] = v; }
		});
		if (tur) { s.show_ads = tur.value === 'reklam' ? 1 : 0; }
		return s;
	}
	function push(st) {
		if (!st.ready || !st.frame.contentWindow) { return; }
		st.frame.contentWindow.postMessage({ onizleme: 'ciz', s: settings(), theme: $('input[value=dark]', st.root).checked ? 'dark' : 'light', panel: st.panel }, location.origin);
	}
	function pushPreview() {
		clearTimeout(ptimer);
		ptimer = setTimeout(function () { Object.keys(slots).forEach(function (k) { fit(slots[k]); push(slots[k]); }); }, 120);
	}
	if (tplEl && cfg.preview) {
		$$('[data-prev-slot]').forEach(function (s) {
			var id = s.getAttribute('data-prev-slot'), tmp = d.createElement('div');
			tmp.innerHTML = tplEl.innerHTML.replace(/\{id\}/g, id);
			while (tmp.firstChild) { s.appendChild(tmp.firstChild); }
			var st = { root: s, stage: $('[data-stage]', s), frame: $('iframe', s), ready: false, panel: false };
			if (s.hasAttribute('data-compact')) { st.stage.style.height = '17rem'; }
			if (id === 'dialog') { st.stage.style.height = '32rem'; }
			$$('input', s).forEach(function (i) { i.addEventListener('change', function () { fit(st); push(st); }); });
			$('[data-panel-toggle]', s).addEventListener('click', function (e) {
				st.panel = !st.panel;
				e.currentTarget.setAttribute('aria-pressed', String(st.panel));
				e.currentTarget.textContent = st.panel ? I.panel_off : I.panel_on;
				push(st);
			});
			slots[id] = st;
			if (id !== 'dialog' && id !== 'wiz') { st.frame.src = cfg.preview; }
		});
		window.addEventListener('message', function (e) {
			if (e.origin !== location.origin || !e.data || (e.data.onizleme !== 'yuklendi' && e.data.onizleme !== 'hazir')) { return; }
			Object.keys(slots).forEach(function (k) {
				var st = slots[k];
				if (st.frame.contentWindow === e.source && e.data.onizleme === 'yuklendi') { st.ready = true; fit(st); push(st); }
			});
		});
		d.addEventListener('input', function (e) { if (e.target.closest && e.target.closest('form')) { pushPreview(); } });
		d.addEventListener('change', function (e) { if (e.target.closest && e.target.closest('form')) { pushPreview(); } });
		window.addEventListener('resize', pushPreview);
	}
	function loadSlot(id) { var st = slots[id]; if (st && !st.frame.getAttribute('src')) { st.frame.src = cfg.preview; } }
	var dlgPrev = d.getElementById('dlg-prev');
	if (dlgPrev) { dlgPrev.addEventListener('toggle', function () { if (dlgPrev.open) { loadSlot('dialog'); } }); $$('[data-dialog=dlg-prev]').forEach(function (b) { b.addEventListener('click', function () { loadSlot('dialog'); }); }); }

	/* Sihirbaz */
	var wf = d.getElementById('wiz-form');
	if (wf) {
		var next = d.getElementById('wiz-next'), back = d.getElementById('wiz-back'), fin = d.getElementById('wiz-finish');
		var wiz = function (n) {
			$$('[data-wstep]').forEach(function (s) { s.hidden = +s.getAttribute('data-wstep') !== n; });
			$$('.ck-stepper li').forEach(function (li) {
				var i = +li.getAttribute('data-st');
				li.removeAttribute('aria-current');
				li.classList.toggle('is-done', i < n);
				if (i === n) { li.setAttribute('aria-current', 'step'); }
			});
			d.getElementById('wiz-n').textContent = (I.step || '') + ' ' + n + ' / 3';
			back.hidden = n === 1;
			next.hidden = n === 3;
			fin.hidden = n !== 3;
			wf.dataset.step = n;
			if (n === 3) {
				var t = $('[name="cookiekit_settings[_tur]"]:checked');
				d.getElementById('w-sum1').textContent = t && t.value === 'analiz' ? I.sum_an : I.sum_ads;
				loadSlot('wiz');
				pushPreview();
			}
		};
		function focusStep(n) { var h = $('[data-wstep="' + n + '"] h2'); if (h) { h.setAttribute('tabindex', '-1'); h.focus(); } }
		next.addEventListener('click', function () { var n = +wf.dataset.step; if (n < 3) { wiz(n + 1); focusStep(n + 1); } });
		back.addEventListener('click', function () { var n = +wf.dataset.step; if (n > 1) { wiz(n - 1); focusStep(n - 1); } });
		wf.addEventListener('submit', function () { submitting = true; });
		wiz(1);
	}
}());
