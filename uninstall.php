<?php
/**
 * Eklenti silinirken çalışır. Ayar ve tablo yalnız "silerken temizle" işaretliyse kaldırılır.
 *
 * @package Cookiekit
 */

declare(strict_types=1);

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

wp_clear_scheduled_hook( 'cookiekit_purge' );

// Güncelleme ve önbellek kaydı: veri silme tercihinden bağımsız, geçici/yardımcı kayıtlardır.
if ( class_exists( '\AdminKit\Core\Cache' ) ) {
	\AdminKit\Core\Cache::purge_plugin( 'cookiekit' );
}
delete_option( 'cookiekit_update_checked' );

$cookiekit_settings = get_option( 'cookiekit_settings', array() );
if ( is_array( $cookiekit_settings ) && ! empty( $cookiekit_settings['delete_on_uninstall'] ) ) {
	global $wpdb;
	$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'cookiekit_log' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared -- tablo adı sabit, girdi yok.
	delete_option( 'cookiekit_settings' );
	delete_option( 'cookiekit_custom_cookies' );
	delete_option( 'cookiekit_db_version' );
}
