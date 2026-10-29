=== CookieKit — Cookie and Consent Manager ===
Contributors: rbnbilisim
Tags: cookie consent, kvkk, gdpr, consent mode, cookies
Requires at least: 6.5
Tested up to: 6.8
Requires PHP: 8.1
Requires Plugins: adminkit
Stable tag: 0.1.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Link: https://github.com/RbnCore

KVKK-compliant cookie banner and preferences panel, Google Consent Mode v2, consent log and script blocking. Connects to GitHub only to check for updates (can be turned off in the settings).

== Description ==

* Banner and preferences panel in the site language (Turkish translation included); the Accept / Reject / Preferences buttons have equal prominence (no dark patterns).
* Categories: Essential (locked), Analytics, Advertising. A documented cookie list for each category.
* Google Consent Mode v2: "denied" by default at the top of the page; `consent update` on choice; applied at once for a returning visitor.
* The consent cookie is `cookiekit_consent` (version.bitmask), 180 days; when the "revision" is raised, everyone is asked for consent again.
* Consent log (KVKK proof): the raw IP is not stored (HMAC with a daily rotating salt), it is deleted automatically after 12 months, CSV export.
* Shortcodes: `[cookiekit_tercihler]`, `[cookiekit_cerez_listesi]`; give any element `data-cookiekit-open` to open the panel.
* Script-blocking API: `cookiekit_script( 'analytics', $src_or_code )`.
* Light: JS and CSS together are a few KB, no jQuery, CLS 0; the only external request is the update check (can be turned off in the settings).

== Installation ==

1. Upload the `cookiekit.zip` file with Plugins → Add New → Upload Plugin and activate it.
2. Under RBN Kit → Cookies and Consent (the General, Appearance, Texts, Cookies, Consent log, Integration and Help tabs), edit the categories, links, colours and texts; a 3-step wizard opens on first setup.

== Changelog ==

= 0.1.2 =
* Menu: the screen now sits under the shared "RBN Kit" top-level menu (RBN Kit → Cookies and Consent); the old Settings address redirects to the new one.
* Updates from GitHub: Update / up to date on the Plugins page, WordPress automatic update support.
* The package is verified with a SHA-256 digest and an Ed25519 signature; the check can be turned off in Help → Update.

= 0.1.1 =
* First release.
