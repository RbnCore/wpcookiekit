<?php
/**
 * GitHub Releases'tan güncelleme: WordPress'in kendi güncelleme sistemine bilgi verir.
 *
 * - Ağ çağrısı yalnız WP'nin kendi güncelleme kontrolünde (`update_plugins_github.com`) yapılır; yazı/ön yüz çalışmaz.
 * - Belirteç, hesap ya da site bilgisi gönderilmez: yalnız belirteçsiz GET + User-Agent `CookieKit/<sürüm>`.
 * - Paket kurulmadan önce SHA-256 ve Ed25519 imzası doğrulanır; bu kontrol kapatılamaz.
 * - Kapatma anahtarı (`cookiekit_update_off`) açıksa hiç ağ çağrısı yapılmaz.
 *
 * Bağımsız sınıf: başka eklentiye kopyalanırken yalnız sabitler (REPO, PLUGIN, SLUG, ön ek) değişir.
 *
 * @package Cookiekit
 */

declare(strict_types=1);

namespace Cookiekit;

defined( 'ABSPATH' ) || exit;

final class Updater {

	public const REPO         = 'RbnCore/wpcookiekit';
	public const PLUGIN       = 'cookiekit/cookiekit.php';
	public const SLUG         = 'cookiekit';
	public const OPT_OFF      = 'cookiekit_update_off';
	public const OPT_CHECKED  = 'cookiekit_update_checked';
	public const CACHE        = 'cookiekit_update_release';
	public const MAX_ZIP      = 8388608; // 8 MB.
	private const TTL_OK      = 43200;   // 12 saat (GitHub: belirteçsiz 60 istek/saat).
	private const TTL_ERR     = 3600;
	private const PKG_PREFIX  = 'https://github.com/' . self::REPO . '/releases/download/';
	private const NOTES_LIMIT = 2000;

	/** Bu istekte doğrulanıp indirilen paketin sürümü; kaynak seçimi bununla karşılaştırır. */
	private static ?string $verified = null;

	public static function init(): void {
		add_filter( 'update_plugins_github.com', array( self::class, 'offer' ), 10, 3 );
		add_filter( 'plugins_api', array( self::class, 'info' ), 10, 3 );
		add_filter( 'upgrader_pre_download', array( self::class, 'pre_download' ), 10, 3 );
		add_filter( 'upgrader_source_selection', array( self::class, 'source_selection' ), 10, 4 );
		add_action( 'admin_post_cookiekit_update_check', array( self::class, 'action_check' ) );
		add_action( 'admin_post_cookiekit_update_toggle', array( self::class, 'action_toggle' ) );
	}

	public static function enabled(): bool {
		return '1' !== (string) get_option( self::OPT_OFF, '' );
	}

	// ───────────────────────── Saf yardımcılar ─────────────────────────

	/** `0.1.2` ya da `v0.1.2` → `0.1.2`; başka biçim null. */
	public static function normalize_version( string $tag ): ?string {
		return 1 === preg_match( '/^v?(\d{1,4}\.\d{1,4}\.\d{1,4})$/', trim( $tag ), $m ) ? $m[1] : null;
	}

	/** İmzanın bağlandığı ileti: paket adı sürüme, sürüm de özete bağlıdır. */
	public static function signed_message( string $version, string $sha256 ): string {
		return 'cookiekit|' . $version . '|' . strtolower( $sha256 );
	}

	/**
	 * GitHub "latest release" yanıtını doğrular ve sadeleştirir. Eksik/bozuksa null.
	 *
	 * @param mixed $data json_decode çıktısı.
	 * @return array<string,string>|null
	 */
	public static function parse_release( $data ): ?array {
		if ( ! is_array( $data ) || ! empty( $data['draft'] ) || ! empty( $data['prerelease'] ) ) {
			return null;
		}
		$version = self::normalize_version( (string) ( $data['tag_name'] ?? '' ) );
		if ( null === $version || ! isset( $data['assets'] ) || ! is_array( $data['assets'] ) ) {
			return null;
		}
		$want  = array(
			'package' => 'cookiekit-' . $version . '.zip',
			'sha'     => 'cookiekit-' . $version . '.zip.sha256',
			'sig'     => 'cookiekit-' . $version . '.zip.sig',
		);
		$found = array();
		foreach ( $data['assets'] as $asset ) {
			if ( ! is_array( $asset ) || ! isset( $asset['name'], $asset['browser_download_url'] ) ) {
				continue;
			}
			$key = array_search( (string) $asset['name'], $want, true );
			$url = (string) $asset['browser_download_url'];
			if ( false !== $key && $url === self::PKG_PREFIX . rawurlencode( $version ) . '/' . $want[ $key ] ) {
				$found[ $key ] = $url;
			}
		}
		if ( 3 !== count( $found ) ) {
			return null;
		}
		$notes = (string) ( $data['body'] ?? '' );
		$notes = function_exists( 'mb_substr' ) ? mb_substr( $notes, 0, self::NOTES_LIMIT ) : substr( $notes, 0, self::NOTES_LIMIT );
		return array(
			'version' => $version,
			'package' => $found['package'],
			'sha'     => $found['sha'],
			'sig'     => $found['sig'],
			'notes'   => $notes,
			'url'     => 'https://github.com/' . self::REPO . '/releases/tag/' . rawurlencode( (string) $data['tag_name'] ),
		);
	}

	/** Ortak anahtar: 32 baytlık ham değer ya da boş. */
	private static function public_key(): string {
		$file = COOKIEKIT_DIR . 'includes/update-pubkey.php';
		$b64  = is_readable( $file ) ? include $file : '';
		$raw  = is_string( $b64 ) ? base64_decode( $b64, true ) : false;
		return ( is_string( $raw ) && 32 === strlen( $raw ) ) ? $raw : '';
	}

	/**
	 * @param string $key_override Yalnız test için ham ortak anahtar; boşsa gömülü anahtar.
	 * @return true|\WP_Error
	 */
	public static function verify_signature( string $message, string $sig_b64, string $key_override = '' ) {
		if ( ! function_exists( 'sodium_crypto_sign_verify_detached' ) ) {
			return new \WP_Error( 'cookiekit_no_sodium', __( 'Update rejected: signature verification (sodium) is missing on the server. The old version was kept.', 'cookiekit' ) );
		}
		$key = '' !== $key_override ? $key_override : self::public_key();
		$sig = base64_decode( trim( $sig_b64 ), true );
		if ( '' === $key ) {
			return new \WP_Error( 'cookiekit_no_key', __( 'Update rejected: the plugin has no valid signature key. The old version was kept.', 'cookiekit' ) );
		}
		if ( ! is_string( $sig ) || 64 !== strlen( $sig ) || ! sodium_crypto_sign_verify_detached( $sig, $message, $key ) ) {
			return new \WP_Error( 'cookiekit_bad_sig', __( 'Update rejected: the package signature is invalid. The old version was kept.', 'cookiekit' ) );
		}
		return true;
	}

	/**
	 * İndirilen dosya: boyut, SHA-256 ve imza.
	 *
	 * @return true|\WP_Error
	 */
	public static function check_package( string $file, string $version, string $sha_body, string $sig_b64, string $key_override = '' ) {
		$size = is_file( $file ) ? (int) filesize( $file ) : 0;
		if ( $size < 1 || $size > self::MAX_ZIP ) {
			return new \WP_Error( 'cookiekit_bad_size', __( 'Update rejected: the package size is not in the expected range. The old version was kept.', 'cookiekit' ) );
		}
		if ( 1 !== preg_match( '/^\s*([0-9a-fA-F]{64})(?:\s|$)/', $sha_body, $m ) ) {
			return new \WP_Error( 'cookiekit_bad_sha_file', __( 'Update rejected: the digest (SHA-256) file could not be read. The old version was kept.', 'cookiekit' ) );
		}
		$actual = (string) hash_file( 'sha256', $file );
		if ( ! hash_equals( strtolower( $m[1] ), $actual ) ) {
			return new \WP_Error( 'cookiekit_sha_mismatch', __( 'Update rejected: the SHA-256 digest of the package does not match. The old version was kept.', 'cookiekit' ) );
		}
		return self::verify_signature( self::signed_message( $version, $actual ), $sig_b64, $key_override );
	}

	/**
	 * Açılmış paketin kök klasörü ve ana dosyası.
	 *
	 * @return true|\WP_Error
	 */
	public static function check_source( string $source, string $version ) {
		$dir  = rtrim( str_replace( '\\', '/', $source ), '/' );
		$main = $dir . '/cookiekit.php';
		if ( self::SLUG !== basename( $dir ) || ! is_file( $main ) ) {
			return new \WP_Error( 'cookiekit_bad_layout', __( 'Update rejected: the root folder of the package is not “cookiekit” or cookiekit.php is missing. The old version was kept.', 'cookiekit' ) );
		}
		$head = get_file_data( $main, array( 'v' => 'Version', 'n' => 'Plugin Name' ) );
		if ( '' === $head['n'] || $head['v'] !== $version ) {
			return new \WP_Error( 'cookiekit_bad_version', __( 'Update rejected: the version inside the package does not match the published version. The old version was kept.', 'cookiekit' ) );
		}
		return true;
	}

	// ───────────────────────── Yayın bilgisi ─────────────────────────

	/**
	 * Son yayın. Kapalıysa ağ çağrısı YAPILMAZ.
	 *
	 * @return array{status:string,release:?array<string,string>,time:int}|null Önbellek kaydı; kapalıysa null.
	 */
	public static function lookup( bool $force = false ): ?array {
		if ( ! self::enabled() ) {
			return null;
		}
		if ( ! $force ) {
			$cached = get_transient( self::CACHE );
			if ( is_array( $cached ) && isset( $cached['status'] ) ) {
				return $cached;
			}
		}
		$res    = wp_remote_get(
			'https://api.github.com/repos/' . self::REPO . '/releases/latest',
			array(
				'timeout'             => 5,
				'redirection'         => 2,
				'limit_response_size' => 262144,
				'user-agent'          => 'CookieKit/' . COOKIEKIT_VERSION,
				'headers'             => array( 'Accept' => 'application/vnd.github+json' ),
			)
		);
		$status = 'error';
		$rel    = null;
		$ttl    = self::TTL_ERR;
		if ( ! is_wp_error( $res ) ) {
			$code = (int) wp_remote_retrieve_response_code( $res );
			if ( 404 === $code ) {
				$status = 'none';
				$ttl    = self::TTL_OK;
			} elseif ( 200 === $code ) {
				$rel = self::parse_release( json_decode( (string) wp_remote_retrieve_body( $res ), true ) );
				if ( null !== $rel ) {
					$status = 'ok';
					$ttl    = self::TTL_OK;
				}
			}
		}
		$record = array(
			'status'  => $status,
			'release' => $rel,
			'time'    => time(),
		);
		set_transient( self::CACHE, $record, $ttl );
		update_option( self::OPT_CHECKED, $record['time'], false );
		return $record;
	}

	/** Yeni sürüm varsa yayın, yoksa null. @return array<string,string>|null */
	public static function newer(): ?array {
		$rec = self::lookup();
		if ( null === $rec || 'ok' !== $rec['status'] || ! is_array( $rec['release'] ) ) {
			return null;
		}
		return version_compare( $rec['release']['version'], COOKIEKIT_VERSION, '>' ) ? $rec['release'] : null;
	}

	/** @return array{requires:string,requires_php:string,tested:string} */
	private static function local_meta(): array {
		$h      = get_file_data( COOKIEKIT_FILE, array( 'r' => 'Requires at least', 'p' => 'Requires PHP' ) );
		$readme = is_readable( COOKIEKIT_DIR . 'readme.txt' ) ? (string) file_get_contents( COOKIEKIT_DIR . 'readme.txt' ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- yerel dosya.
		$tested = 1 === preg_match( '/^Tested up to:\s*([0-9.]+)/mi', $readme, $m ) ? $m[1] : '';
		return array(
			'requires'     => $h['r'],
			'requires_php' => $h['p'],
			'tested'       => $tested,
		);
	}

	// ───────────────────────── WordPress kancaları ─────────────────────────

	/**
	 * `update_plugins_github.com`: WP'nin kendi güncelleme kontrolü sırasında çağrılır.
	 * Sürüm karşılaştırmasını ve "güncel" listesini WP yapar.
	 *
	 * @param mixed                $update      Önceki değer.
	 * @param array<string,string> $plugin_data Eklenti başlıkları.
	 * @param string               $plugin_file Eklenti dosyası.
	 * @return mixed
	 */
	public static function offer( $update, $plugin_data, $plugin_file ) {
		if ( self::PLUGIN !== $plugin_file || ! self::enabled() ) {
			return $update;
		}
		$rel  = self::newer();
		$meta = self::local_meta();
		return array_merge(
			array(
				'id'           => 'https://github.com/' . self::REPO,
				'slug'         => self::SLUG,
				'version'      => $rel ? $rel['version'] : COOKIEKIT_VERSION,
				'url'          => $rel ? $rel['url'] : 'https://github.com/' . self::REPO,
				'package'      => $rel ? $rel['package'] : '',
				'icons'        => array(),
				'requires'     => $meta['requires'],
				'requires_php' => $meta['requires_php'],
			),
			'' !== $meta['tested'] ? array( 'tested' => $meta['tested'] ) : array()
		);
	}

	/**
	 * "Ayrıntıları görüntüle" penceresi. Ağ çağrısı yapmaz; yalnız önbellekteki yayını kullanır.
	 *
	 * @param mixed $result Önceki sonuç.
	 * @param mixed $action İstek türü.
	 * @param mixed $args   İstek bağımsız değişkenleri.
	 * @return mixed
	 */
	public static function info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || ! is_object( $args ) || self::SLUG !== ( $args->slug ?? '' ) || ! self::enabled() ) {
			return $result;
		}
		$cached = get_transient( self::CACHE );
		$rel    = ( is_array( $cached ) && 'ok' === ( $cached['status'] ?? '' ) && is_array( $cached['release'] ?? null ) ) ? $cached['release'] : null;
		$new    = $rel && version_compare( $rel['version'], COOKIEKIT_VERSION, '>' );
		$meta   = self::local_meta();
		$notes  = $rel ? $rel['notes'] : '';
		return (object) array(
			'name'          => 'CookieKit — ' . __( 'Cookie and Consent Manager', 'cookiekit' ),
			'slug'          => self::SLUG,
			'version'       => $new ? $rel['version'] : COOKIEKIT_VERSION,
			'author'        => '<a href="https://github.com/RbnCore">RBN Bilişim</a>',
			'homepage'      => 'https://github.com/' . self::REPO,
			'requires'      => $meta['requires'],
			'requires_php'  => $meta['requires_php'],
			'tested'        => $meta['tested'],
			'download_link' => $new ? $rel['package'] : '',
			'sections'      => array(
				'description' => esc_html__( 'A KVKK-compliant cookie banner and preferences panel, Google Consent Mode v2, consent log.', 'cookiekit' ),
				'changelog'   => '' !== $notes ? wp_kses_post( nl2br( esc_html( $notes ) ) ) : esc_html__( 'There are no release notes for this version.', 'cookiekit' ),
			),
		);
	}

	/**
	 * Paket indirilir, doğrulanır; geçerliyse geçici dosya yolu döner (WP onu açar).
	 *
	 * @param mixed  $reply   Önceki yanıt.
	 * @param string $package Paket adresi.
	 * @return mixed
	 */
	public static function pre_download( $reply, $package ) {
		if ( false !== $reply || ! is_string( $package ) || 0 !== strpos( $package, self::PKG_PREFIX ) ) {
			return $reply;
		}
		self::$verified = null;
		if ( 1 !== preg_match( '#^' . preg_quote( self::PKG_PREFIX, '#' ) . '(\d{1,4}\.\d{1,4}\.\d{1,4})/cookiekit-\1\.zip$#', $package, $m ) ) {
			return new \WP_Error( 'cookiekit_bad_url', __( 'Update rejected: the package address is not in the expected format. The old version was kept.', 'cookiekit' ) );
		}
		$version = $m[1];
		$sha     = self::fetch_text( $package . '.sha256' );
		$sig     = is_wp_error( $sha ) ? $sha : self::fetch_text( $package . '.sig' );
		if ( is_wp_error( $sig ) ) {
			return $sig;
		}
		if ( ! function_exists( 'download_url' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		$tmp = download_url( $package, 300 );
		if ( is_wp_error( $tmp ) ) {
			return $tmp;
		}
		$ok = self::check_package( $tmp, $version, $sha, $sig );
		if ( is_wp_error( $ok ) ) {
			wp_delete_file( $tmp );
			return $ok;
		}
		self::$verified = $version;
		return $tmp;
	}

	/** Küçük metin varlığı (özet/imza) indirir. @return string|\WP_Error */
	private static function fetch_text( string $url ) {
		$res = wp_safe_remote_get(
			$url,
			array(
				'timeout'             => 15,
				'limit_response_size' => 4096,
				'user-agent'          => 'CookieKit/' . COOKIEKIT_VERSION,
			)
		);
		if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
			return new \WP_Error( 'cookiekit_fetch_fail', __( 'Update stopped: the digest or signature file could not be downloaded. The old version was kept.', 'cookiekit' ) );
		}
		return (string) wp_remote_retrieve_body( $res );
	}

	/**
	 * Açılmış paket: kök klasör ve ana dosya kontrolü. Yalnız CookieKit güncellemesinde çalışır.
	 *
	 * @param mixed                $source        Açılmış klasör.
	 * @param string               $remote_source Açma konumu.
	 * @param mixed                $upgrader      Yükseltici.
	 * @param array<string,mixed>  $hook_extra    Ek bilgi.
	 * @return mixed
	 */
	public static function source_selection( $source, $remote_source = '', $upgrader = null, $hook_extra = array() ) {
		if ( is_wp_error( $source ) || ! is_array( $hook_extra ) || self::PLUGIN !== ( $hook_extra['plugin'] ?? '' ) ) {
			return $source;
		}
		$version        = self::$verified;
		self::$verified = null;
		if ( null === $version ) {
			return new \WP_Error( 'cookiekit_unverified', __( 'Update rejected: the package did not pass the CookieKit verification. The old version was kept.', 'cookiekit' ) );
		}
		$ok = self::check_source( (string) $source, $version );
		return is_wp_error( $ok ) ? $ok : $source;
	}

	// ───────────────────────── Ayar ekranı ─────────────────────────

	public static function action_check(): void {
		self::guard( 'cookiekit_update_check' );
		if ( self::enabled() ) {
			delete_transient( self::CACHE );
			delete_site_transient( 'update_plugins' );
			wp_update_plugins();
			self::redirect( 'update_checked' );
		}
		self::redirect( 'update_is_off' );
	}

	public static function action_toggle(): void {
		self::guard( 'cookiekit_update_toggle' );
		$off = self::enabled(); // Şu an açıksa kapatılacak.
		update_option( self::OPT_OFF, $off ? '1' : '0', false );
		delete_transient( self::CACHE );
		if ( $off ) {
			$cur = get_site_transient( 'update_plugins' );
			if ( is_object( $cur ) ) {
				unset( $cur->response[ self::PLUGIN ], $cur->no_update[ self::PLUGIN ] );
				set_site_transient( 'update_plugins', $cur );
			}
		}
		self::redirect( $off ? 'update_off' : 'update_on' );
	}

	private static function guard( string $nonce_action ): void {
		if ( ! current_user_can( 'update_plugins' ) ) {
			wp_die( esc_html__( 'You do not have permission for this action.', 'cookiekit' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( $nonce_action );
	}

	private static function redirect( string $note ): void {
		wp_safe_redirect( Admin::page_url( array( 'tab' => 'yardim', 'ck_note' => $note ) ) );
		exit;
	}

	/** Yardım sekmesindeki "Güncelleme" kartının gövdesi. */
	public static function render_card(): string {
		$on      = self::enabled();
		$checked = (int) get_option( self::OPT_CHECKED, 0 );
		$cached  = $on ? get_transient( self::CACHE ) : false;
		$state   = '';
		if ( ! $on ) {
			$state = esc_html__( 'The update check is off; GitHub is never contacted.', 'cookiekit' );
		} elseif ( ! is_array( $cached ) ) {
			$state = esc_html__( 'Not checked yet.', 'cookiekit' );
		} elseif ( 'none' === $cached['status'] ) {
			$state = esc_html__( 'There is no published version yet.', 'cookiekit' );
		} elseif ( 'ok' !== $cached['status'] || ! is_array( $cached['release'] ) ) {
			$state = esc_html__( 'Could not check; it will be tried again later. The plugin works normally.', 'cookiekit' );
		} elseif ( version_compare( $cached['release']['version'], COOKIEKIT_VERSION, '>' ) ) {
			/* translators: %s: sürüm numarası */
			$state = sprintf( esc_html__( 'A new version is available: %s. Click “Update” on the Plugins page.', 'cookiekit' ), '<strong>' . esc_html( $cached['release']['version'] ) . '</strong>' );
		} else {
			$state = esc_html__( 'The plugin is up to date.', 'cookiekit' );
		}
		$last = $checked > 0 ? esc_html( wp_date( 'd.m.Y H:i', $checked ) ) : esc_html__( 'never', 'cookiekit' );
		$post = esc_url( admin_url( 'admin-post.php' ) );

		$html  = '<dl class="ck-dl"><dt>' . esc_html__( 'Installed version', 'cookiekit' ) . '</dt><dd>' . esc_html( COOKIEKIT_VERSION ) . '</dd>'
			. '<dt>' . esc_html__( 'Status', 'cookiekit' ) . '</dt><dd>' . $state . '</dd>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $state yukarıda kaçışlı.
			. '<dt>' . esc_html__( 'Last check', 'cookiekit' ) . '</dt><dd>' . $last . '</dd>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			. '<dt>' . esc_html__( 'Automatic updates', 'cookiekit' ) . '</dt><dd>' . esc_html__( 'Turned on or off with the “Enable auto-updates” link on the CookieKit row in the Plugins page.', 'cookiekit' ) . '</dd></dl>'
			. '<p class="ck-help">' . esc_html__( 'The update package is verified with its SHA-256 digest and Ed25519 signature before installation; a package that cannot be verified is not installed. Only a standard GET request is sent; your site address or user information is not sent.', 'cookiekit' ) . '</p>'
			. '<div style="display:flex;gap:var(--ck-s3);flex-wrap:wrap;margin-top:var(--ck-s3)">';
		if ( $on ) {
			$html .= '<form method="post" action="' . $post . '"><input type="hidden" name="action" value="cookiekit_update_check">' . wp_nonce_field( 'cookiekit_update_check', '_wpnonce', true, false ) . '<button type="submit" class="ck-btn">' . esc_html__( 'Check now', 'cookiekit' ) . '</button></form>';
		}
		$html .= '<form method="post" action="' . $post . '"><input type="hidden" name="action" value="cookiekit_update_toggle">' . wp_nonce_field( 'cookiekit_update_toggle', '_wpnonce', true, false ) . '<button type="submit" class="ck-btn">' . esc_html( $on ? __( 'Turn off the update check', 'cookiekit' ) : __( 'Turn on the update check', 'cookiekit' ) ) . '</button></form></div>';
		return $html;
	}
}
