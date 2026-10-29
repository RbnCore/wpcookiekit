/*! CookieKit yönetim ekranı davranışları. Bağımlılık yok; JS kapalıyken formlar yine çalışır. */
(function () {
	'use strict';
	var d = document, wrap = d.getElementById('ck');
	if (!wrap) { return; }
	var u = window.RbnUi || {};
	var $ = u.$ || function (s, r) { return (r || d).querySelector(s); };
	var $$ = u.$$ || function (s, r) { return Array.prototype.slice.call((r || d).querySelectorAll(s)); };
	var cfg = {};
	try { cfg = JSON.parse(wrap.getAttribute('data-cfg') || '{}'); } catch (e) { cfg = {}; }
	var I = cfg.i18n || {};
	d.documentElement.classList.add('ck-js');
	wrap.classList.add('ck-js');

	/* Hata özeti varsa odaklan */
	var err = d.getElementById('ck-err');
	if (err) { err.focus(); }

	/* Kaydet barı: kirli izleme */
	var anyDirty = false, submitting = false;
	$$('form[data-savebar]').forEach(function (form) {
		var bar = $('[data-bar]', form), rv = $('[data-revert]', form);
		function serial() { return JSON.stringify($$('input,textarea,select', form).map(function (i) { return i.type === 'checkbox' || i.type === 'radio' ? i.checked : i.value; }).concat($$('[data-src-v][aria-pressed=true]', form).map(function (b) { return b.getAttribute('data-src-v'); }))); }
		var snap = serial();
		function chk() { var dirty = serial() !== snap; bar.classList.toggle('is-dirty', dirty); if (rv) { rv.hidden = !dirty; } anyDirty = dirty; }
		form.addEventListener('input', chk);
		form.addEventListener('change', chk);
		if (rv) { rv.addEventListener('click', function () { form.reset(); resetSrc(); syncColors(); form.dispatchEvent(new Event('change', { bubbles: true })); chk(); }); }
		form.addEventListener('submit', function () { submitting = true; });
		if (err) { bar.classList.add('is-dirty'); if (rv) { rv.hidden = false; } }
	});
	window.addEventListener('beforeunload', function (e) { if (anyDirty && !submitting) { e.preventDefault(); } });

	/* Sayaç */
	$$('[data-count]').forEach(function (el) {
		var max = +el.getAttribute('data-count'), c = el.closest('.ck-c'), out = c && $('.ck-counter', c);
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
	var srcInit = $$('[data-src-v]').map(function (b) { return b.getAttribute('aria-pressed'); });
	function resetSrc() {
		$$('[data-src-v]').forEach(function (b, i) { b.setAttribute('aria-pressed', srcInit[i]); });
		var o = $('[data-src-v=ozel]');
		if (colors && o) { colors.hidden = o.getAttribute('aria-pressed') !== 'true'; }
	}
	function lum(h) {
		var c = [1, 3, 5].map(function (i) { var v = parseInt(h.substr(i, 2), 16) / 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); });
		return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2];
	}
	function contrast() {
		var box = d.getElementById('contrast'), bgE = d.getElementById('c-bg'), fgE = d.getElementById('c-fg');
		if (!box || !bgE || !fgE) { return; }
		var bg = bgE.value || '#ffffff', fg = fgE.value || '#1a1a1a', hexRe = /^#[0-9a-f]{6}$/i, txt = $('p', box);
		if (!hexRe.test(bg) || !hexRe.test(fg)) { box.className = 'rbn-note rbn-note--warn'; txt.textContent = I.nocalc || ''; return; }
		var a = lum(bg), b = lum(fg), r = (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05), pass = r >= 4.5;
		box.className = 'rbn-note ' + (pass ? 'rbn-note--ok' : 'rbn-note--err');
		$('use', box).setAttribute('href', pass ? '#i-check' : '#i-alert');
		txt.textContent = (I.contrast || '') + ': ' + r.toFixed(1).replace('.', ',') + ':1 — ' + (pass ? I.aa_ok : I.aa_fail);
	}
	function syncColors() {
		$$('[data-hex]').forEach(function (t) {
			var p = $('[data-pair="' + t.id + '"]');
			if (p && /^#[0-9a-f]{6}$/i.test(t.value)) { p.value = t.value; }
		});
		contrast();
	}
	if (colors) {
		$$('[data-src-v]').forEach(function (r) {
			r.addEventListener('click', function () {
				$$('[data-src-v]').forEach(function (o) { o.setAttribute('aria-pressed', String(o === r)); });
				var ozel = r.getAttribute('data-src-v') === 'ozel';
				colors.hidden = !ozel;
				if (!ozel) { $$('[data-hex]').forEach(function (i) { i.value = ''; }); }
				else { $$('[data-pair]').forEach(function (p) { var t = d.getElementById(p.getAttribute('data-pair')); if (t && !t.value) { t.value = p.value; } }); }
				contrast();
				pushPreview();
				r.form && r.form.dispatchEvent(new Event('change', { bubbles: true }));
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
			dl.showModal();
			if (dl.id === 'dlg-prev') { pushPreview(); }
		});
	});
	$$('[data-close]').forEach(function (b) { b.addEventListener('click', function () { var dl = b.closest('dialog'); if (dl) { dl.close(); } }); });
	/* Çerez silme onayı */
	$$('[data-rm]').forEach(function (b) {
		b.addEventListener('click', function () {
			var id = b.getAttribute('data-rm') || '';
			var name = b.getAttribute('data-rm-name') || '';
			var title = (I.rm_title || 'Remove the “%s” cookie from the list?').replace('%s', name);
			var msg = I.rm_desc || 'The cookie is removed from the preferences panel and the policy table. The cookie itself on your site is not deleted.';
			var confirmText = I.rm_confirm || 'Remove from list';
			var cancelText = I.cancel || 'Cancel';
			var uConfirm = (window.RbnUi && window.RbnUi.confirm) ? window.RbnUi.confirm : null;
			if (!uConfirm) {
				if (!window.confirm(title)) { return; }
				sendRm(id);
				return;
			}
			uConfirm({
				title: title,
				message: msg,
				confirmText: confirmText,
				cancelText: cancelText,
				tone: 'danger'
			}).then(function (ok) {
				if (ok) { sendRm(id); }
			});
		});
	});
	function sendRm(id) {
		var form = d.createElement('form');
		form.method = 'post';
		form.action = cfg.postUrl || 'admin-post.php';
		var inAct = d.createElement('input'); inAct.type = 'hidden'; inAct.name = 'action'; inAct.value = 'cookiekit_cookie_remove'; form.appendChild(inAct);
		var inId = d.createElement('input'); inId.type = 'hidden'; inId.name = 'id'; inId.value = id; form.appendChild(inId);
		var inN = d.createElement('input'); inN.type = 'hidden'; inN.name = '_wpnonce'; inN.value = (cfg.nonces && cfg.nonces.cookie_remove) || ''; form.appendChild(inN);
		d.body.appendChild(form);
		form.submit();
	}

	/* Onay kayıtlarını temizleme (çift onay: requireText) */
	var btnPurge = d.getElementById('btn-purge-records');
	if (btnPurge) {
		btnPurge.addEventListener('click', function () {
			var title = I.purge_title || 'Delete all consent records?';
			var msg = I.purge_desc || 'This cannot be undone. If you have records you must keep as proof under KVKK, download them as CSV first.';
			var reqText = I.confirm || 'DELETE';
			var confirmText = I.purge_confirm || 'Delete all records';
			var cancelText = I.cancel || 'Cancel';
			var uConfirm = (window.RbnUi && window.RbnUi.confirm) ? window.RbnUi.confirm : null;
			if (!uConfirm) {
				if (!window.confirm(title)) { return; }
				sendPurge(reqText);
				return;
			}
			uConfirm({
				title: title,
				message: msg,
				confirmText: confirmText,
				cancelText: cancelText,
				tone: 'danger',
				requireText: reqText
			}).then(function (ok) {
				if (ok) { sendPurge(reqText); }
			});
		});
	}
	function sendPurge(confirmVal) {
		var form = d.createElement('form');
		form.method = 'post';
		form.action = cfg.postUrl || 'admin-post.php';
		var inAct = d.createElement('input'); inAct.type = 'hidden'; inAct.name = 'action'; inAct.value = 'cookiekit_purge_log'; form.appendChild(inAct);
		var inC = d.createElement('input'); inC.type = 'hidden'; inC.name = 'confirm'; inC.value = confirmVal; form.appendChild(inC);
		var inN = d.createElement('input'); inN.type = 'hidden'; inN.name = '_wpnonce'; inN.value = (cfg.nonces && cfg.nonces.purge_log) || ''; form.appendChild(inN);
		d.body.appendChild(form);
		form.submit();
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
	function pressed(st, g) { var b = $('[data-seg=' + g + '] [aria-pressed=true]', st.root); return b ? b.getAttribute('data-v') : ''; }
	function fit(st) {
		var mob = pressed(st, 'dev') === 'mob', w = mob ? SC.mob : SC.desk, cw = st.stage.clientWidth || 400;
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
		st.frame.contentWindow.postMessage({ onizleme: 'ciz', s: settings(), theme: pressed(st, 'th'), panel: st.panel }, location.origin);
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
			if (s.hasAttribute('data-compact')) { st.stage.classList.add('ck-prev__stage--compact'); }
			if (id === 'dialog') { st.stage.classList.add('ck-prev__stage--tall'); }
			$$('[data-seg] button', s).forEach(function (b) {
				b.addEventListener('click', function () {
					$$('button', b.parentNode).forEach(function (o) { o.setAttribute('aria-pressed', String(o === b)); });
					fit(st); push(st);
				});
			});
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
