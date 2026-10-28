# CookieKit

CookieKit adds a cookie banner and a preferences panel to WordPress for the Turkish data protection law (KVKK), with Google Consent Mode v2, a consent log kept as proof, and a script-blocking API.

## Who it is for

Turkish WordPress publishers and agencies that need a cookie consent banner with real records — which categories were accepted, when, and with which policy revision — without sending visitor data to a third-party consent service.

## Features

- **Banner and preferences panel** in the site language. Accept, Reject and Preferences have equal prominence; there are no dark patterns.
- **Categories**: Essential (always on, locked), Analytics and Advertising. Each category has a documented cookie list, and you can add your own cookies in the settings.
- **Google Consent Mode v2**: `denied` defaults are pushed inline at the top of the page, before other tags; `gtag('consent','update', …)` runs on the visitor's choice, and a returning visitor's stored choice is applied immediately on page load.
- **Consent cookie** `cookiekit_consent` with the format `revision.bitmask`, valid for 180 days by default (adjustable between 30 and 395). Raising the revision asks every visitor for consent again.
- **Consent log as proof**: the raw IP address is never stored — the log keeps an HMAC with a daily rotating salt. Entries are deleted automatically after 12 months, and the log can be exported as CSV.
- **Shortcodes** `[cookiekit_tercihler]` and `[cookiekit_cerez_listesi]`; any element with the `data-cookiekit-open` attribute opens the preferences panel.
- **Script blocking**: `cookiekit_script( 'analytics', $src_or_code )` in PHP, or a `<script type="text/plain" data-cookiekit-category="ads" data-src="…">` tag in HTML. Blocked scripts are activated after consent.
- **Seven admin tabs** under RBN Kit → Cookies and Consent: General, Appearance, Texts, Cookies, Consent log, Integration, Help. First setup opens a three-step wizard (categories → policy pages → preview and enable); a live preview uses the real front-end assets, and nothing is visible on the site until you save.
- **Light by design**: no jQuery, no external assets, and one inline consent script in the page HTML.
- **REST endpoint** `POST /wp-json/cookiekit/v1/consent`, same-origin only, limited to 10 requests per minute per IP (the 11th gets a 429).

## Requirements

- WordPress 6.4 or later
- PHP 8.1 or later
- The PHP `sodium` extension for automatic updates; without it updates are refused and you install the zip by hand

## Installation

1. Download `cookiekit-0.1.2.zip` from [Releases](https://github.com/RbnCore/wpcookiekit/releases) and compare its SHA-256 with the `.zip.sha256` asset.
2. In WordPress go to **Plugins → Add New → Upload Plugin**, choose the zip, install and activate. Activation creates the `{prefix}cookiekit_log` table and schedules the daily cleanup.
3. Open **RBN Kit → Cookies and Consent**, enter your policy links, categories and texts. The three-step wizard opens on first setup.
4. If a page cache plugin is active, clear the cache once. The banner is drawn with JavaScript, so a cached page must contain the new markup.
5. If `DISABLE_WP_CRON` is set, make sure the server calls `wp-cron.php`; otherwise the 12-month log cleanup does not run.

## Updating

Updates come from GitHub and appear on the Plugins page like any WordPress update; automatic updates are supported as well.

- **Source**: only `github.com/RbnCore/wpcookiekit` releases. The request is unauthenticated, times out after 5 seconds, is cached for 12 hours, and sends only `User-Agent: CookieKit/<version>`. If GitHub cannot be reached, no error is shown and the plugin keeps working.
- **Verification is always on**: the package's SHA-256 digest (`.zip.sha256`) and its Ed25519 signature (`.zip.sig`, public key in `includes/update-pubkey.php`) are checked, and the zip must contain `cookiekit/` and `cookiekit.php`. If any check fails, the installation stops and the installed version stays as it was.
- **Turning it off**: RBN Kit → Cookies and Consent → Help → Update → "Turn off the update check". While it is off, no request goes to GitHub. "Check now" skips the cache and asks immediately.
- **Manual update**: upload the new `cookiekit-X.Y.Z.zip` with Plugins → Add New → Upload Plugin and choose *Replace current*. To roll back, install the previous zip the same way; settings and the log table are kept (the schema is additive).

## Privacy and external requests

- On the front end CookieKit makes no external request and loads no third-party asset. The only external request in the whole plugin is the GitHub update check described above, and it can be turned off.
- The consent log stores no raw IP address, only a salted HMAC; the salt rotates daily. Records are deleted after 12 months. CSV export requires `manage_options` and a nonce.
- No site address, user or visitor data is sent to GitHub. Only the user agent string goes out.
- On uninstall the data is kept by default. If "Delete data when the plugin is uninstalled" is ticked, the table and settings are removed.

## Translation

English is the source language; a Turkish (`tr_TR`) translation ships in `languages/`. The banner's default texts are translated, so they follow the site language; texts you save in the settings are shown exactly as written. The consent log's CSV headers are translated too.

## RBN Kit menu

The settings screen is **RBN Kit → Cookies and Consent**, under the shared RBN Kit top-level menu just below Dashboard. The menu order is fixed across RBN plugins (Overview → AdminKit → Cookies and Consent → Content Health).

## Known limits

- CookieKit is **not a TCF-certified CMP**. For personalised advertising in the EEA, the United Kingdom or Switzerland, Google requires a certified CMP; in those regions you need one, or you serve non-personalised ads only.
- Rate-limit counters are transients, so without a persistent object cache they live in the database until they expire.

## Version and license

- Version 0.1.2
- License: GPL-2.0-or-later (see [LICENSE](LICENSE) and [NOTICE](NOTICE))
- Text domain: `cookiekit`

## Development notes

- This repository is the plugin folder itself; release packages are a git archive of the repository root with the `cookiekit/` prefix.
- Structure: `cookiekit.php` holds the header and constants; `includes/` holds the admin screens, the front end (banner, consent mode, shortcodes), the consent log, the REST endpoint and the updater; `assets/` holds the front-end and admin CSS/JS; `templates/admin/` holds admin templates; `languages/` holds the `.pot` and the Turkish translation.
- The updater is a self-contained class: copy it to another plugin by changing the constants (repository, plugin file, slug, option prefix). It offers updates through WordPress's own `update_plugins_github.com` filter and never runs on the front end.
- The signing key is never in the repository or in the package, and release tooling lives in the project tree outside this repository.

## Links

- Repository: https://github.com/RbnCore/wpcookiekit
- Releases: https://github.com/RbnCore/wpcookiekit/releases
- Issues: https://github.com/RbnCore/wpcookiekit/issues
- Author: https://github.com/RbnCore
