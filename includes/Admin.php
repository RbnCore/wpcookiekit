<?php
/**
 * Ayar ekranı (RBN Kit → Çerez ve Onay): kayıt, doğrulama, yönetici eylemleri, varlıklar.
 * Görünüm: AdminPage + templates/admin/*.php.
 *
 * @package Cookiekit
 */

declare(strict_types=1);

namespace Cookiekit;

use AdminKit\Core\Security\Guard;

defined( 'ABSPATH' ) || exit;

final class Admin {

	public const SLUG  = 'cookiekit';
	public const TABS  = array( 'genel', 'gorunum', 'metinler', 'cerezler', 'kayitlar', 'entegrasyon', 'yardim' );
	public const NOTES = array( 'cookie_added', 'cookie_removed', 'cookie_invalid', 'cookie_limit', 'purged', 'purge_fail', 'purge_confirm' );

	private static string $hook = '';

	public static function init(): void {
		if ( ! is_admin() ) {
			return;
		}
		// Başlıklar çeviri kullanır; `init` öncesi okunursa WP 6.7+ "çok erken yüklendi" uyarısı verir. Yardımcı menüyü öncelik 9'da kurar, kayıt ondan önce.
		add_action( 'admin_menu', array( self::class, 'menu' ), 5 );
		add_action( 'admin_menu', array( self::class, 'late' ), 99 );
		add_action( 'admin_init', array( self::class, 'register' ) );
		add_action( 'admin_init', array( self::class, 'handle_redirect_notices' ) );
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
		return \AdminKit\Helpers\Menu::page_url( self::SLUG, $args );
	}

	/** @param array<int,string> $links */
	public static function links( array $links ): array {
		return \AdminKit\Helpers\Menu::action_link( $links, self::page_url(), __( 'Settings', 'cookiekit' ) );
	}

	/** Yan menüde RBN Kit altına kaydolur; `Menu` yardımcısı RbnKitMenu üzerinden menüyü kurar. */
	public static function menu(): void {
		\AdminKit\Helpers\Menu::add( array( 'slug' => self::SLUG, 'title' => __( 'Cookies and Consent', 'cookiekit' ), 'menu_title' => __( 'Cookies and Consent', 'cookiekit' ), 'cap' => 'manage_options', 'callback' => array( AdminPage::class, 'render' ), 'version' => COOKIEKIT_VERSION ) );
	}

	/** Ekran kimliği (rbn-kit_page_cookiekit); menü kurulduktan sonra okunur. */
	public static function late(): void {
		self::$hook = \AdminKit\Helpers\Menu::hook( self::SLUG );
	}

	public static function register(): void {
		// Şema + form kümesi motorun kaydındadır; burada yalnız `options.php` rotası bağlanır.
		\AdminKit\Core\Settings::register_option( Settings::SLUG, Settings::GROUP );
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

	/** Post-Redirect-Get: URL'den parametreleri temizler ve Toast::flash'a taşır. */
	public static function handle_redirect_notices(): void {
		if ( ! isset( $_GET['page'] ) || self::SLUG !== $_GET['page'] ) {
			return;
		}
		if ( ! empty( $_GET['settings-updated'] ) ) {
			if ( class_exists( '\AdminKit\Helpers\Toast' ) ) {
				\AdminKit\Helpers\Toast::flash( 'success', __( 'Settings saved.', 'cookiekit' ) );
			}
			wp_safe_redirect( remove_query_arg( 'settings-updated' ) );
			exit;
		}
		if ( isset( $_GET['ck_note'] ) ) {
			$note = sanitize_key( (string) wp_unslash( $_GET['ck_note'] ) );
			self::flash_note( $note );
			wp_safe_redirect( remove_query_arg( 'ck_note' ) );
			exit;
		}
	}

	public static function flash_note( string $note ): void {
		$notes = array(
			'cookie_added'   => array( 'success', __( 'The cookie was added to the list.', 'cookiekit' ) ),
			'cookie_removed' => array( 'success', __( 'The cookie was removed from the list.', 'cookiekit' ) ),
			'cookie_invalid' => array( 'error', __( 'The cookie could not be added: a name is required and the category must be valid.', 'cookiekit' ) ),
			'cookie_limit'   => array( 'error', __( 'At most 40 custom cookies can be added.', 'cookiekit' ) ),
			'purged'         => array( 'success', __( 'All consent records were deleted.', 'cookiekit' ) ),
			'purge_fail'     => array( 'error', __( 'Consent records could not be deleted: the table cannot be read.', 'cookiekit' ) ),
			'update_checked' => array( 'success', __( 'The update check was done.', 'cookiekit' ) ),
			'update_is_off'  => array( 'info', __( 'The update check is off; no connection was made.', 'cookiekit' ) ),
			'purge_confirm'  => array( 'error', __( 'Deletion was not confirmed: you must type DELETE in the box. No record was deleted.', 'cookiekit' ) ),
		);
		if ( isset( $notes[ $note ] ) && class_exists( '\AdminKit\Helpers\Toast' ) ) {
			\AdminKit\Helpers\Toast::flash( $notes[ $note ][0], $notes[ $note ][1] );
		}
	}

	private static function back( string $tab, string $note = '' ): void {
		if ( '' !== $note ) {
			self::flash_note( $note );
		}
		wp_safe_redirect( self::page_url( array( 'tab' => $tab ) ) );
		exit;
	}

	public static function wizard_skip(): void {
		Guard::admin_action( 'cookiekit_wizard_skip', 'manage_options' );
		\AdminKit\Core\Settings::set( Settings::SLUG, 'wizard_done', 1 );
		self::back( 'genel' );
	}

	public static function cookie_add(): void {
		Guard::admin_action( 'cookiekit_cookie_add', 'manage_options' );
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
		Guard::admin_action( 'cookiekit_cookie_remove', 'manage_options' );
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
		Guard::admin_action( 'cookiekit_purge_log', 'manage_options' );
		$confirm = isset( $_POST['confirm'] ) ? trim( (string) wp_unslash( $_POST['confirm'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( mb_strtoupper( __( 'DELETE', 'cookiekit' ), 'UTF-8' ) !== mb_strtoupper( $confirm, 'UTF-8' ) && 'SIL' !== strtoupper( $confirm ) ) {
			self::back( 'kayitlar', 'purge_confirm' );
		}
		Log::delete_all();
		self::back( 'kayitlar', null === Log::count() ? 'purge_fail' : 'purged' );
	}

	/** Canlı önizleme sayfası (iframe): gerçek cookiekit.css/js, günlüksüz, tıklanamaz. */
	public static function preview(): void {
		Guard::admin_action( 'cookiekit_preview', 'manage_options' );
		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );
		header( 'X-Robots-Tag: noindex' );
		$base = wp_json_encode( Frontend::preview_config(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		require COOKIEKIT_DIR . 'templates/admin/preview.php';
		exit;
	}

	public static function csv(): void {
		Guard::admin_action( 'cookiekit_csv', 'manage_options' );
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
