<?php
/**
 * Onay kaydı (KVKK ispat): tablo, ekleme, 12 ay saklama, istatistik.
 * Ham IP saklanmaz; yalnız günlük dönen tuzla HMAC özeti tutulur.
 *
 * @package Cookiekit
 */

declare(strict_types=1);

namespace Cookiekit;

use AdminKit\Helpers\Db;
defined( 'ABSPATH' ) || exit;

final class Log {

	public const DB_VERSION = '1';
	public const CRON_HOOK  = 'cookiekit_purge';
	public const KEEP       = '-12 months';

	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'cookiekit_log';
	}

	public static function activate(): void {
		self::create_table();
		add_option( Settings::OPTION, Settings::defaults() );
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	public static function deactivate(): void {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	public static function maybe_upgrade(): void {
		add_action( self::CRON_HOOK, array( self::class, 'purge' ) );
		if ( get_option( 'cookiekit_db_version' ) !== self::DB_VERSION ) {
			self::create_table();
		}
		if ( is_admin() && ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	private static function create_table(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = self::table();
		$charset = $wpdb->get_charset_collate();
		dbDelta(
			"CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			created_at datetime NOT NULL,
			revision int(10) unsigned NOT NULL DEFAULT 1,
			categories tinyint(3) unsigned NOT NULL DEFAULT 1,
			action char(1) NOT NULL DEFAULT 'c',
			ip_hash char(32) NOT NULL,
			ua_hash char(32) NOT NULL,
			path varchar(190) NOT NULL DEFAULT '/',
			PRIMARY KEY  (id),
			KEY created_at (created_at)
		) {$charset};"
		);
		update_option( 'cookiekit_db_version', self::DB_VERSION, false );
	}

	/** Günlük dönen tuzla özet: ham IP asla saklanmaz. */
	public static function ip_digest( string $ip ): string {
		$salt = hash_hmac( 'sha256', gmdate( 'Y-m-d' ), wp_salt( 'auth' ) );
		return substr( hash_hmac( 'sha256', $ip, $salt ), 0, 32 );
	}

	public static function ua_digest( string $ua ): string {
		return substr( hash( 'sha256', substr( $ua, 0, 120 ) ), 0, 32 );
	}

	/** Karar türü: a=hepsini kabul, r=yalnız zorunlu, c=özel. */
	public static function action_for( int $mask ): string {
		if ( Settings::full_mask() === $mask ) {
			return 'a';
		}
		return Settings::BIT_NECESSARY === $mask ? 'r' : 'c';
	}

	public static function insert( int $mask, int $revision, string $ip, string $ua, string $path ): bool {
		global $wpdb;
		$ok = Db::insert( self::table(),
			array(
				'created_at' => gmdate( 'Y-m-d H:i:s' ),
				'revision'   => $revision,
				'categories' => $mask,
				'action'     => self::action_for( $mask ),
				'ip_hash'    => self::ip_digest( $ip ),
				'ua_hash'    => self::ua_digest( $ua ),
				'path'       => $path,
			),
			array( '%s', '%d', '%d', '%s', '%s', '%s', '%s' ) );
		return false !== $ok;
	}

	/** 12 aydan eski kayıtları siler. */
	public static function purge(): int {
		global $wpdb;
		$limit = gmdate( 'Y-m-d H:i:s', strtotime( self::KEEP ) );
		$n     = Db::query( 'DELETE FROM ' . self::table() . ' WHERE created_at < %s', array( $limit ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- tablo adı sabit.
		return (int) $n;
	}

	/**
	 * Karar sayıları. $days > 0 ise yalnız son $days gün. Tablo okunamazsa ok=false döner.
	 *
	 * @return array{total:int,a:int,r:int,c:int,ok:bool}
	 */
	public static function stats( int $days = 0 ): array {
		global $wpdb;
		$out   = array( 'total' => 0, 'a' => 0, 'r' => 0, 'c' => 0, 'ok' => true );
		$where = '';
		if ( $days > 0 ) {
			$where = $wpdb->prepare( ' WHERE created_at >= %s', gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS ) );
		}
		$prev = $wpdb->suppress_errors( true );
		$rows = Db::get_results( 'SELECT action, COUNT(*) AS n FROM ' . self::table() . $where . ' GROUP BY action', array(), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $where prepare() çıktısı.
		$fail = '' !== (string) $wpdb->last_error;
		$wpdb->suppress_errors( $prev );
		if ( $fail || ! is_array( $rows ) ) {
			$out['ok'] = false;
			return $out;
		}
		foreach ( $rows as $row ) {
			if ( isset( $out[ $row['action'] ] ) ) {
				$out[ $row['action'] ] = (int) $row['n'];
				$out['total']         += (int) $row['n'];
			}
		}
		return $out;
	}

	/** Toplam kayıt sayısı; tablo okunamazsa null. */
	public static function count(): ?int {
		global $wpdb;
		$prev = $wpdb->suppress_errors( true );
		$n    = Db::get_var( 'SELECT COUNT(*) FROM ' . self::table() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- girdi yok.
		$fail = '' !== (string) $wpdb->last_error;
		$wpdb->suppress_errors( $prev );
		return $fail || null === $n ? null : (int) $n;
	}

	/**
	 * Sayfalı son kayıtlar (IP özeti dahil; UA özeti ekrana verilmez).
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function recent( int $limit, int $offset ): array {
		global $wpdb;
		return (array) Db::get_results( 'SELECT id, created_at, revision, action, categories, ip_hash, path FROM ' . self::table() . ' ORDER BY id DESC LIMIT %d OFFSET %d', array( max( 1, $limit ), max( 0, $offset ) ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- tablo adı sabit.
			ARRAY_A );
	}

	/** Tüm onay kayıtlarını siler (tabloyu değil, satırları). Silinen satır sayısını döndürür. */
	public static function delete_all(): int {
		global $wpdb;
		return (int) Db::query( 'DELETE FROM ' . self::table() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- girdi yok.
	}

	/**
	 * CSV için kayıtları döndürür.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function export_rows( int $limit = 100000 ): array {
		global $wpdb;
		return (array) Db::get_results( 'SELECT id, created_at, revision, action, categories, ip_hash, ua_hash, path FROM ' . self::table() . ' ORDER BY id DESC LIMIT %d', array( $limit ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- tablo adı sabit.
			ARRAY_A );
	}
}
