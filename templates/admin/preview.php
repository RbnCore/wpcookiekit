<?php
/**
 * Ayar ekranı canlı önizleme sayfası (iframe içinde, aynı origin). Gerçek cookiekit.css/js yüklenir.
 * Değişkenler: $base (Frontend::preview_config() JSON, JSON_HEX_* ile kaçışlı).
 *
 * @package Cookiekit
 */

defined( 'ABSPATH' ) || exit;

$css = COOKIEKIT_URL . 'assets/cookiekit.css?ver=' . rawurlencode( COOKIEKIT_VERSION );
$js  = COOKIEKIT_URL . 'assets/cookiekit.js?ver=' . rawurlencode( COOKIEKIT_VERSION );
?>
<!doctype html>
<html lang="tr" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex">
<title><?php echo esc_html__( 'Önizleme', 'cookiekit' ); ?></title>
<link rel="stylesheet" href="<?php echo esc_url( $css ); ?>">
<style id="ck-renk"></style>
<style>
	html{background:#fff;color:#1b1b1f}
	html[data-theme=dark]{background:#121216;color:#f2f2f5}
	body{margin:0;padding:1.25rem;font:400 1rem/1.5 system-ui,-apple-system,"Segoe UI",sans-serif}
	.sk{height:.75rem;margin:0 0 .75rem;border-radius:4px;background:rgb(127 127 127/.22)}
	.sk--h{height:1.5rem;width:55%}.sk--w{width:92%}.sk--m{width:78%}.sk--s{width:40%}
	.sk--img{height:7rem;margin:1rem 0}
	[inert]{pointer-events:none}
</style>
</head>
<body>
<div class="sk sk--h"></div><div class="sk sk--w"></div><div class="sk sk--m"></div>
<div class="sk sk--img"></div>
<div class="sk sk--w"></div><div class="sk sk--s"></div><div class="sk sk--w"></div><div class="sk sk--m"></div>
<script>
(function () {
	'use strict';
	var BASE = <?php echo $base; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_json_encode, JSON_HEX_*. ?>;
	var SAVED = <?php echo wp_json_encode( array_intersect_key( \Cookiekit\Settings::all(), array_flip( array( 'color_accent', 'color_bg', 'color_text' ) ) ) ); ?>;
	var JS = <?php echo wp_json_encode( $js, JSON_UNESCAPED_SLASHES ); ?>;
	var d = document, root = d.documentElement;
	function clean() {
		['.cookiekit-b', '.cookiekit-m', '.cookiekit-o'].forEach(function (s) {
			var n = d.querySelector(s); if (n) { n.remove(); }
		});
		var old = d.getElementById('cookiekit-js'); if (old) { old.remove(); }
		root.style.removeProperty('--cookiekit-h');
	}
	function hex(v) { return /^#[0-9a-f]{3}([0-9a-f]{3})?$/i.test(v || ''); }
	function render(msg) {
		var s = msg.s || {}, cfg = JSON.parse(JSON.stringify(BASE)), k;
		clean();
		root.setAttribute('data-theme', msg.theme === 'dark' ? 'dark' : 'light');
		var map = { color_accent: '--cookiekit-ac', color_bg: '--cookiekit-bg', color_text: '--cookiekit-fg' }, v = '';
		for (k in map) { var cv = (k in s) ? s[k] : SAVED[k]; if (hex(cv)) { v += map[k] + ':' + cv + ';'; } }
		d.getElementById('ck-renk').textContent = v ? ':root,:root[data-theme]{' + v + '}' : '';
		if (typeof s.position === 'string' && /^(bottom-left|bottom-center|bottom-right|box)$/.test(s.position)) { cfg.pos = s.position; }
		['title', 'desc', 'accept', 'reject', 'prefs', 'save'].forEach(function (key) {
			if (typeof s[key] === 'string' && s[key] !== '') { cfg.t[key] = s[key]; }
		});
		if (typeof s.privacy_url === 'string') { cfg.urls.privacy = s.privacy_url ? '#' : ''; }
		if (typeof s.cookie_url === 'string') { cfg.urls.cookie = s.cookie_url ? '#' : ''; }
		cfg.c = cfg.c.filter(function (c) {
			if (c.id === 'analytics' && s.show_analytics === 0) { return false; }
			if (c.id === 'ads' && s.show_ads === 0) { return false; }
			return true;
		});
		window.cookiekitCfg = cfg;
		var sc = d.createElement('script');
		sc.id = 'cookiekit-js';
		sc.src = JS + '&t=' + Date.now();
		sc.onload = function () {
			if (msg.panel && window.Cookiekit) { window.Cookiekit.open(); }
			Array.prototype.forEach.call(d.querySelectorAll('.cookiekit-b,.cookiekit-m,.cookiekit-o'), function (n) { n.setAttribute('inert', ''); });
			parent.postMessage({ onizleme: 'hazir' }, location.origin);
		};
		d.body.appendChild(sc);
	}
	window.addEventListener('message', function (e) {
		if (e.origin !== location.origin || e.source !== parent) { return; }
		if (e.data && e.data.onizleme === 'ciz') { render(e.data); }
	});
	parent.postMessage({ onizleme: 'yuklendi' }, location.origin);
}());
</script>
</body>
</html>
