<?php
/**
 * Ayar ekranı (RBN Kit → Çerez ve Onay): kayıt, doğrulama, yönetici eylemleri, varlıklar.
 * Görünüm: AdminPage + templates/admin/*.php.
 *
 * @package Cookiekit
 */

declare(strict_types=1);

namespace Cookiekit;

defined( 'ABSPATH' ) || exit;

final class Admin {

	public const SLUG  = 'cookiekit';
	public const TABS  = array( 'genel', 'gorunum', 'metinler', 'cerezler', 'kayitlar', 'entegrasyon', 'yardim' );
	public const NOTES = array( 'cookie_added', 'cookie_removed', 'cookie_invalid', 'cookie_limit', 'purged', 'purge_fail', 'purge_confirm', 'update_checked', 'update_is_off' );

	/** Her form sekmesinin yazabildiği alan kümesi: kalanlar eski değerinden aynen taşınır. */
	private const FORM_TABS = array( 'genel', 'gorunum', 'metinler', 'kurulum' );

	private static string $hook = '';

	/** Eklentinin kendi güvenilir yazımı (wizard_skip): sekme kümesi aranmaz. */
	private static bool $trusted = false;

	public static function init(): void {
		if ( ! is_admin() ) {
			return;
		}
		// Başlıklar çeviri kullanır; `init` öncesi okunursa WP 6.7+ "çok erken yüklendi" uyarısı verir. Yardımcı menüyü öncelik 9'da kurar, kayıt ondan önce.
		add_action( 'admin_menu', array( self::class, 'menu' ), 5 );
		add_action( 'admin_menu', array( self::class, 'late' ), 99 );
		add_action( 'admin_init', array( self::class, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
		add_action( 'admin_post_cookiekit_csv', array( self::class, 'csv' ) );
		add_action( 'admin_post_cookiekit_purge_log', array( self::class, 'purge_log' ) );
		add_action( 'admin_post_cookiekit_cookie_add', array( self::class, 'cookie_add' ) );
		add_action( 'admin_post_cookiekit_cookie_remove', array( self::class, 'cookie_remove' ) );
		add_action( 'admin_post_cookiekit_wizard_skip', array( self::class, 'wizard_skip' ) );
		add_action( 'admin_post_cookiekit_preview', array( self::class, 'preview' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( COOKIEKIT_FILE ), array( self::class, 'links' ) );
	}

	/** Sayfa adresi. @param array<string,string> $args Ek sorgu parametreleri. */
	public static function page_url( array $args = array() ): string {
		return add_query_arg( array_merge( array( 'page' => self::SLUG ), $args ), admin_url( 'admin.php' ) );
	}

	/** @param array<int,string> $links */
	public static function links( array $links ): array {
		array_unshift( $links, '<a href="' . esc_url( self::page_url() ) . '">' . esc_html__( 'Settings', 'cookiekit' ) . '</a>' );
		return $links;
	}

	/** Yan menüde RBN Kit altına kaydolur; `RbnKitMenu` kendi `admin_menu` (öncelik 9) kancasıyla menüyü kurar. */
	public static function menu(): void {
		\RbnKitMenu::add(
			array(
				'slug'       => self::SLUG,
				'title'      => __( 'Cookies and Consent', 'cookiekit' ),
				'menu_title' => __( 'Cookies and Consent', 'cookiekit' ),
				'cap'        => 'manage_options',
				'callback'   => array( AdminPage::class, 'render' ),
				'version'    => COOKIEKIT_VERSION,
			)
		);
	}

	/** Ekran kimliği (rbn-kit_page_cookiekit); menü kurulduktan sonra okunur. */
	public static function late(): void {
		self::legacy_redirect();
		self::$hook = \RbnKitMenu::hook( self::SLUG );
	}

	/** Eski adres (Ayarlar → CookieKit) yer imi kolaylığı için yeni adrese yönlenir; `admin_init` sayfa yetki denetiminden (403) sonra çalıştığından `admin_menu` içinden çağrılır. */
	private static function legacy_redirect(): void {
		global $pagenow;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- yalnız yönlendirme.
		if ( 'options-general.php' === $pagenow && isset( $_GET['page'] ) && self::SLUG === sanitize_key( wp_unslash( $_GET['page'] ) ) ) {
			wp_safe_redirect( self::page_url( array_diff_key( map_deep( wp_unslash( $_GET ), 'sanitize_text_field' ), array( 'page' => 1 ) ) ), 301 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			exit;
		}
	}

	public static function register(): void {
		// Seçenek yoksa update_option sanitize'ı iki kez çağırır; ilk kayıt filtre kurulmadan yapılır.
		if ( false === get_option( Settings::OPTION ) ) {
			add_option( Settings::OPTION, Settings::defaults() );
		}
		register_setting(
			'cookiekit_group',
			Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( self::class, 'sanitize' ),
				'default'           => Settings::defaults(),
			)
		);
	}

	/** Yönetim CSS/JS'i YALNIZ ayar sayfasında yüklenir. */
	public static function enqueue( string $hook ): void {
		if ( '' === self::$hook || $hook !== self::$hook ) {
			return;
		}
		// Görünüm AdminKit kitindendir (rbn-admin-ui; AdminKit kaydeder); eklenti dosyaları yalnız bu ekrana özgü düzeni yazar.
		wp_enqueue_style( 'rbn-admin-ui' );
		wp_enqueue_script( 'rbn-admin-ui' );
		$ver = static function ( string $rel ): string {
			$t = @filemtime( COOKIEKIT_DIR . $rel ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- dosya yoksa sürüme düşer.
			return COOKIEKIT_VERSION . ( $t ? '.' . $t : '' );
		};
		wp_enqueue_style( 'cookiekit-admin', COOKIEKIT_URL . 'assets/cookiekit-admin.css', array( 'rbn-admin-ui' ), $ver( 'assets/cookiekit-admin.css' ) );
		wp_enqueue_script( 'cookiekit-admin', COOKIEKIT_URL . 'assets/cookiekit-admin.js', array( 'rbn-admin-ui' ), $ver( 'assets/cookiekit-admin.js' ), array( 'strategy' => 'defer', 'in_footer' => true ) );
	}

	/**
	 * Yalnız aynı site içi yol ya da aynı alan adlı http(s) adres. Geçersizse null.
	 *
	 * @param mixed $value Ham değer.
	 */
	public static function url_clean( $value ): ?string {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}
		if ( strlen( $value ) > 300 || false !== strpos( $value, '\\' ) || preg_match( '/[\x00-\x1f\x7f]/', $value ) ) {
			return null;
		}
		if ( 0 === strpos( $value, '/' ) ) {
			return 0 === strpos( $value, '//' ) ? null : '/' . ltrim( sanitize_text_field( $value ), '/' );
		}
		$clean = esc_url_raw( $value, array( 'http', 'https' ) );
		$host  = (string) wp_parse_url( $clean, PHP_URL_HOST );
		$mine  = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		return '' !== $clean && '' !== $host && 0 === strcasecmp( $host, $mine ) ? $clean : null;
	}

	private static function fail( string $id, string $message ): void {
		add_settings_error( Settings::OPTION, $id, $message, 'error' );
	}

	/**
	 * Gizli `_tab` hangi alan kümesini içeriyorsa yalnız onları işler; kalanı eski değerinden taşır.
	 *
	 * @param mixed $input Formdan gelen ham değer.
	 * @return array<string,mixed>
	 */
	public static function sanitize( $input ): array {
		$old = Settings::all();
		if ( self::$trusted ) {
			return is_array( $input ) ? $input : $old;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return $old;
		}
		$in  = is_array( $input ) ? $input : array();
		$tab = isset( $in['_tab'] ) ? sanitize_key( (string) $in['_tab'] ) : '';
		if ( ! in_array( $tab, self::FORM_TABS, true ) ) {
			self::fail( 'ck-tab', __( 'The form was not recognised; no setting was changed.', 'cookiekit' ) );
			return $old;
		}
		$def = Settings::defaults();
		$out = $old;
		// Sekme formuna ancak kurulum bittikten (ya da atlandıktan) sonra ulaşılır: kayıt, sihirbazın bittiği anlamına gelir.
		$out['wizard_done'] = 1;

		if ( 'genel' === $tab ) {
			foreach ( array( 'enabled', 'show_analytics', 'show_ads', 'log', 'delete_on_uninstall' ) as $flag ) {
				$out[ $flag ] = empty( $in[ $flag ] ) ? 0 : 1;
			}
			$days_raw    = isset( $in['days'] ) ? trim( (string) $in['days'] ) : (string) $old['days'];
			$days        = absint( $days_raw );
			$out['days'] = min( 395, max( 30, $days ) );
			if ( ! ctype_digit( $days_raw ) || $days !== $out['days'] ) {
				self::fail( 'f-days', __( 'The duration must be between 30 and 395 days; the value was corrected.', 'cookiekit' ) );
			}
			$out['revision'] = (int) $old['revision'] + ( empty( $in['bump_revision'] ) ? 0 : 1 );
		}

		if ( 'gorunum' === $tab ) {
			$pos             = isset( $in['position'] ) ? sanitize_key( (string) $in['position'] ) : '';
			$out['position'] = in_array( $pos, Settings::POSITIONS, true ) ? $pos : $old['position'];
			$colors          = array(
				'color_accent' => 'c-ac',
				'color_bg'     => 'c-bg',
				'color_text'   => 'c-fg',
			);
			foreach ( $colors as $key => $id ) {
				$raw = trim( (string) ( $in[ $key ] ?? '' ) );
				if ( '' === $raw ) {
					$out[ $key ] = '';
					continue;
				}
				$hex = sanitize_hex_color( $raw );
				if ( $hex ) {
					$out[ $key ] = $hex;
				} else {
					self::fail( $id, __( 'Enter a valid colour (e.g. #0b5fff); the old value was kept.', 'cookiekit' ) );
				}
			}
		}

		if ( 'metinler' === $tab ) {
			foreach ( array( 'title' => 80, 'desc' => 500, 'accept' => 30, 'reject' => 30, 'prefs' => 30, 'save' => 40 ) as $key => $max ) {
				$text        = sanitize_text_field( (string) ( $in[ $key ] ?? '' ) );
				$out[ $key ] = '' === $text ? $def[ $key ] : mb_substr( $text, 0, $max );
			}
		}

		if ( 'metinler' === $tab || 'kurulum' === $tab ) {
			foreach ( array( 'privacy_url' => 't-priv', 'cookie_url' => 't-cook' ) as $key => $id ) {
				$clean = self::url_clean( $in[ $key ] ?? '' );
				if ( null === $clean ) {
					self::fail( $id, __( 'The address must start with “/” (a path on this site) or with this site\'s http(s) address; the old value was kept.', 'cookiekit' ) );
				} else {
					$out[ $key ] = $clean;
				}
			}
		}

		if ( 'kurulum' === $tab ) {
			$tur = isset( $in['_tur'] ) ? sanitize_key( (string) $in['_tur'] ) : '';
			if ( in_array( $tur, array( 'reklam', 'analiz' ), true ) ) {
				$out['show_analytics'] = 1;
				$out['show_ads']       = 'reklam' === $tur ? 1 : 0;
			}
			$out['enabled']     = 1;
			$out['wizard_done'] = 1;
		}
		return $out;
	}

	/** Eylem yetkisi + nonce; yetkisizse 403. */
	private static function guard( string $nonce_action ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission for this action.', 'cookiekit' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( $nonce_action );
	}

	private static function back( string $tab, string $note = '' ): void {
		$args = array( 'tab' => $tab );
		if ( '' !== $note && in_array( $note, self::NOTES, true ) ) {
			$args['ck_note'] = $note;
		}
		wp_safe_redirect( self::page_url( $args ) );
		exit;
	}

	public static function wizard_skip(): void {
		self::guard( 'cookiekit_wizard_skip' );
		$all                = Settings::all();
		$all['wizard_done'] = 1;
		self::$trusted = true;
		update_option( Settings::OPTION, $all );
		self::$trusted = false;
		self::back( 'genel' );
	}

	public static function cookie_add(): void {
		self::guard( 'cookiekit_cookie_add' );
		$list = Cookies::custom();
		if ( count( $list ) >= Cookies::CUSTOM_MAX ) {
			self::back( 'cerezler', 'cookie_limit' );
		}
		$row = Cookies::clean_cookie(
			array(
				'cat'      => isset( $_POST['cat'] ) ? wp_unslash( $_POST['cat'] ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- guard() nonce'ı doğruladı; clean_cookie() temizler.
				'name'     => isset( $_POST['name'] ) ? wp_unslash( $_POST['name'] ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				'provider' => isset( $_POST['provider'] ) ? wp_unslash( $_POST['provider'] ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				'purpose'  => isset( $_POST['purpose'] ) ? wp_unslash( $_POST['purpose'] ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				'duration' => isset( $_POST['duration'] ) ? wp_unslash( $_POST['duration'] ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			)
		);
		if ( null === $row ) {
			self::back( 'cerezler', 'cookie_invalid' );
		}
		$list[] = $row;
		update_option( Cookies::CUSTOM_OPTION, $list, false );
		self::back( 'cerezler', 'cookie_added' );
	}

	public static function cookie_remove(): void {
		self::guard( 'cookiekit_cookie_remove' );
		$id   = isset( $_POST['id'] ) ? preg_replace( '/[^a-f0-9]/', '', (string) wp_unslash( $_POST['id'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$left = array();
		foreach ( Cookies::custom() as $row ) {
			if ( $row['id'] !== $id ) {
				$left[] = $row;
			}
		}
		update_option( Cookies::CUSTOM_OPTION, $left, false );
		self::back( 'cerezler', 'cookie_removed' );
	}

	public static function purge_log(): void {
		self::guard( 'cookiekit_purge_log' );
		$confirm = isset( $_POST['confirm'] ) ? trim( (string) wp_unslash( $_POST['confirm'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( mb_strtoupper( __( 'DELETE', 'cookiekit' ), 'UTF-8' ) !== mb_strtoupper( $confirm, 'UTF-8' ) && 'SIL' !== strtoupper( $confirm ) ) {
			self::back( 'kayitlar', 'purge_confirm' );
		}
		Log::delete_all();
		self::back( 'kayitlar', null === Log::count() ? 'purge_fail' : 'purged' );
	}

	/** Canlı önizleme sayfası (iframe): gerçek cookiekit.css/js, günlüksüz, tıklanamaz. */
	public static function preview(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission for this action.', 'cookiekit' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'cookiekit_preview' );
		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );
		header( 'X-Robots-Tag: noindex' );
		$base = wp_json_encode( Frontend::preview_config(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		require COOKIEKIT_DIR . 'templates/admin/preview.php';
		exit;
	}

	public static function csv(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html( __( 'You do not have permission.', 'cookiekit' ) ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'cookiekit_csv' );
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . __( 'cookiekit-consent-log', 'cookiekit' ) . '-' . gmdate( 'Ymd-His' ) . '.csv"' );
		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" );
		fputcsv( $out, array( 'id', _x( 'time_utc', 'CSV column header', 'cookiekit' ), _x( 'revision', 'CSV column header', 'cookiekit' ), _x( 'decision', 'CSV column header', 'cookiekit' ), _x( 'essential', 'CSV column header', 'cookiekit' ), _x( 'analytics', 'CSV column header', 'cookiekit' ), _x( 'advertising', 'CSV column header', 'cookiekit' ), _x( 'ip_digest', 'CSV column header', 'cookiekit' ), _x( 'ua_digest', 'CSV column header', 'cookiekit' ), _x( 'page', 'CSV column header', 'cookiekit' ) ), ';', '"', '\\' );
		$names = array( 'a' => _x( 'accepted', 'CSV decision value', 'cookiekit' ), 'r' => _x( 'rejected', 'CSV decision value', 'cookiekit' ), 'c' => _x( 'custom', 'CSV decision value', 'cookiekit' ) );
		foreach ( Log::export_rows() as $row ) {
			$mask = (int) $row['categories'];
			fputcsv(
				$out,
				array( $row['id'], $row['created_at'], $row['revision'], $names[ $row['action'] ] ?? _x( 'custom', 'CSV decision value', 'cookiekit' ), $mask & 1 ? 1 : 0, $mask & 2 ? 1 : 0, $mask & 4 ? 1 : 0, $row['ip_hash'], $row['ua_hash'], $row['path'] ),
				';',
				'"',
				'\\'
			);
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}
}
