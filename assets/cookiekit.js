/*! CookieKit 0.1.2 — çerez bandı ve tercih paneli. Bağımlılık yok. */
(function () {
	'use strict';

	var C = window.cookiekitCfg;
	if (!C) { return; }

	var d = document;
	var root = d.documentElement;
	var T = C.t;
	var BIT = { analytics: 2, ads: 4 };
	var banner = null;
	var modal = null;
	var overlay = null;
	var opener = null;

	function read() {
		var m = d.cookie.match(/(?:^|; )cookiekit_consent=(\d+)\.(\d+)/);
		return m ? { r: +m[1], m: +m[2] } : null;
	}

	function write(mask) {
		d.cookie = 'cookiekit_consent=' + C.r + '.' + mask + '; Max-Age=' + (C.days * 86400) +
			'; Path=/; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
	}

	function el(tag, cls, text, attrs) {
		var n = d.createElement(tag);
		if (cls) { n.className = cls; }
		if (text) { n.textContent = text; }
		if (attrs) { for (var k in attrs) { n.setAttribute(k, attrs[k]); } }
		return n;
	}

	function setHeight() {
		var h = 0;
		if (banner && C.pos !== 'box') {
			h = banner.offsetHeight + (C.pos === 'bottom-center' ? 0 : 16);
		}
		root.style.setProperty('--cookiekit-h', h + 'px');
		root.style.setProperty('--fm-cookie-h', h + 'px'); // fm temasının yukarı çık düğmesi için eşleme
	}

	function gtagUpdate(mask) {
		var a = mask & 2 ? 'granted' : 'denied';
		var ad = mask & 4 ? 'granted' : 'denied';
		if (typeof window.gtag === 'function') {
			window.gtag('consent', 'update', { analytics_storage: a, ad_storage: ad, ad_user_data: ad, ad_personalization: ad });
		}
		(window.dataLayer = window.dataLayer || []).push({ event: 'cookiekit_consent_update', cookiekit_mask: mask });
	}

	/** Onaya bağlı betikleri (type="text/plain" data-cookiekit-category) etkinleştirir. */
	function activate(mask) {
		var list = d.querySelectorAll('script[type="text/plain"][data-cookiekit-category]');
		Array.prototype.forEach.call(list, function (old) {
			var bit = BIT[old.getAttribute('data-cookiekit-category')];
			if (!bit || !(mask & bit) || old.getAttribute('data-cookiekit-done')) { return; }
			var s = d.createElement('script');
			var src = old.getAttribute('data-src');
			if (src) { s.src = src; } else { s.text = old.textContent; }
			old.setAttribute('data-cookiekit-done', '1');
			old.parentNode.insertBefore(s, old.nextSibling);
		});
	}

	function sendLog(mask) {
		if (!C.log || typeof fetch !== 'function') { return; }
		try {
			fetch(C.log, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ c: mask, r: C.r, p: location.pathname }),
				keepalive: true,
				credentials: 'omit'
			}).catch(function () {});
		} catch (e) { /* kayıt başarısızsa kullanıcı akışı bozulmaz */ }
	}

	function closePanel() {
		if (modal) { modal.remove(); overlay.remove(); modal = overlay = null; }
		if (opener && opener.focus && d.contains(opener)) { opener.focus(); }
		opener = null;
	}

	function decide(mask) {
		mask = (mask | 1) & C.f;
		write(mask);
		gtagUpdate(mask);
		activate(mask);
		sendLog(mask);
		var hadPanel = !!modal;
		closePanel();
		if (banner) { banner.remove(); banner = null; }
		setHeight();
		if (hadPanel) { /* odak openera döndü */ }
		d.dispatchEvent(new CustomEvent('cookiekit:consent', { detail: { mask: mask } }));
	}

	function buttons(handlers) {
		var wrap = el('div', 'cookiekit-btns');
		handlers.forEach(function (h) {
			var b = el('button', 'cookiekit-btn', h[0], { type: 'button' });
			b.addEventListener('click', h[1]);
			wrap.appendChild(b);
		});
		return wrap;
	}

	function links(parent) {
		var p = el('p', 'cookiekit-links');
		[['privacy', C.urls.privacy], ['cookie', C.urls.cookie]].forEach(function (u) {
			if (!u[1]) { return; }
			var a = el('a', '', T[u[0]], { href: u[1] });
			p.appendChild(a);
		});
		if (p.firstChild) { parent.appendChild(p); }
	}

	function showBanner() {
		if (banner) { return; }
		banner = el('div', 'cookiekit-b cookiekit-b--' + C.pos, '', {
			role: 'dialog', 'aria-modal': 'false', 'aria-labelledby': 'cookiekit-t', 'aria-describedby': 'cookiekit-d'
		});
		var inner = el('div', 'cookiekit-b__in');
		var txt = el('div', 'cookiekit-b__txt');
		txt.appendChild(el('p', 'cookiekit-title', T.title, { id: 'cookiekit-t' }));
		txt.appendChild(el('p', 'cookiekit-desc', T.desc, { id: 'cookiekit-d' }));
		links(txt);
		inner.appendChild(txt);
		inner.appendChild(buttons([
			[T.reject, function () { decide(1); }],
			[T.prefs, function () { opener = d.activeElement; openPanel(); }],
			[T.accept, function () { decide(C.f); }]
		]));
		banner.appendChild(inner);
		d.body.insertBefore(banner, d.body.firstChild);
		setHeight();
		if (typeof ResizeObserver === 'function') { new ResizeObserver(setHeight).observe(banner); }
	}

	function trap(e) {
		if (e.key === 'Escape') { e.preventDefault(); closePanel(); return; }
		if (e.key !== 'Tab') { return; }
		var f = modal.querySelectorAll('button,[href],input,summary');
		if (!f.length) { return; }
		var first = f[0];
		var last = f[f.length - 1];
		if (e.shiftKey && (d.activeElement === first || d.activeElement === modal)) { e.preventDefault(); last.focus(); }
		else if (!e.shiftKey && d.activeElement === last) { e.preventDefault(); first.focus(); }
	}

	function openPanel() {
		if (modal) { return; }
		var cur = read();
		var have = cur && cur.r === C.r ? cur.m : 1;
		overlay = el('div', 'cookiekit-o');
		modal = el('div', 'cookiekit-m', '', {
			role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': 'cookiekit-mt', tabindex: '-1'
		});
		var head = el('div', 'cookiekit-mh');
		head.appendChild(el('h2', 'cookiekit-mt', T.panel, { id: 'cookiekit-mt' }));
		var x = el('button', 'cookiekit-x', '×', { type: 'button', 'aria-label': T.close });
		x.addEventListener('click', closePanel);
		head.appendChild(x);
		modal.appendChild(head);
		var body = el('div', 'cookiekit-mb');
		body.appendChild(el('p', '', T.desc));
		links(body);
		var boxes = [];
		C.c.forEach(function (cat) {
			var box = el('div', 'cookiekit-cat');
			var lab = el('label', 'cookiekit-cl');
			var id = 'cookiekit-c-' + cat.id;
			var inp = el('input', '', '', { type: 'checkbox', id: id });
			inp.checked = cat.locked || !!(have & cat.bit);
			if (cat.locked) { inp.disabled = true; }
			lab.setAttribute('for', id);
			lab.appendChild(inp);
			lab.appendChild(el('span', 'cookiekit-cn', cat.name + (cat.locked ? ' — ' + T.always : '')));
			box.appendChild(lab);
			box.appendChild(el('p', 'cookiekit-cd', cat.desc));
			if (cat.cookies.length) {
				var det = el('details', 'cookiekit-dt');
				det.appendChild(el('summary', '', T.cname + ' (' + cat.cookies.length + ')'));
				var ul = el('ul', 'cookiekit-cl2');
				cat.cookies.forEach(function (c) {
					var li = el('li');
					li.appendChild(el('strong', '', c[0]));
					li.appendChild(el('span', '', ' · ' + c[1] + ' · ' + c[3]));
					li.appendChild(el('span', 'cookiekit-pu', c[2]));
					ul.appendChild(li);
				});
				det.appendChild(ul);
				box.appendChild(det);
			}
			body.appendChild(box);
			boxes.push([cat, inp]);
		});
		modal.appendChild(body);
		var foot = el('div', 'cookiekit-mf');
		foot.appendChild(buttons([
			[T.reject, function () { decide(1); }],
			[T.save, function () {
				var m = 1;
				boxes.forEach(function (b) { if (b[1].checked) { m |= b[0].bit; } });
				decide(m);
			}],
			[T.accept, function () { decide(C.f); }]
		]));
		modal.appendChild(foot);
		modal.addEventListener('keydown', trap);
		d.body.appendChild(overlay);
		d.body.appendChild(modal);
		modal.focus();
	}

	d.addEventListener('click', function (e) {
		var t = e.target && e.target.closest ? e.target.closest('[data-cookiekit-open]') : null;
		if (t) { e.preventDefault(); opener = t; openPanel(); }
	});

	window.Cookiekit = {
		open: function () { opener = d.activeElement; openPanel(); },
		consent: function () { var c = read(); return c && c.r === C.r ? c.m : null; }
	};

	var c0 = read();
	if (c0 && c0.r === C.r) {
		activate(c0.m & C.f);
	} else {
		showBanner();
	}
}());
