<?php
/**
 * Ayarlar: varsayılanlar, okuma ve temizleme.
 *
 * @package Cookiekit
 */

declare(strict_types=1);

namespace Cookiekit;

defined( 'ABSPATH' ) || exit;

final class Settings {

	public const OPTION = 'cookiekit_settings';

	/** Kategori bitleri: çerez değerindeki bitmask. */
	public const BIT_NECESSARY = 1;
	public const BIT_ANALYTICS = 2;
	public const BIT_ADS       = 4;

	public const POSITIONS = array( 'bottom-left', 'bottom-center', 'bottom-right', 'box' );

	/**
	 * @return array<string,mixed>
	 */
	public static function defaults(): array {
		// Called on activation, before plugins_loaded has run; make sure the translations are available.
		if ( ! is_textdomain_loaded( 'cookiekit' ) && defined( 'COOKIEKIT_FILE' ) ) {
			load_plugin_textdomain( 'cookiekit', false, dirname( plugin_basename( COOKIEKIT_FILE ) ) . '/languages' );
		}
		return array(
			'enabled'          => 1,
			'position'         => 'bottom-center',
			'show_analytics'   => 1,
			'show_ads'         => 1,
			'log'              => 1,
			'revision'         => 1,
			'days'             => 180,
			'privacy_url'      => '/gizlilik-politikasi/',
			'cookie_url'       => '/cerez-politikasi/',
			'color_accent'     => '',
			'color_bg'         => '',
			'color_text'       => '',
			'delete_on_uninstall' => 0,
			'wizard_done'      => 0,
			'title'            => __( 'Your cookie preferences', 'cookiekit' ),
			'desc'             => __( 'We use essential cookies to make the site work. Analytics and advertising cookies are only set with your permission. You can change your preferences at any time.', 'cookiekit' ),
			'accept'           => __( 'Accept', 'cookiekit' ),
			'reject'           => __( 'Reject', 'cookiekit' ),
			'prefs'            => __( 'Preferences', 'cookiekit' ),
			'save'             => __( 'Save my choice', 'cookiekit' ),
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function all(): array {
		$saved = get_option( self::OPTION, array() );
		return array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
	}

	/**
	 * @param mixed $default Bulunamazsa dönecek değer.
	 * @return mixed
	 */
	public static function get( string $key, $default = null ) {
		$all = self::all();
		return $all[ $key ] ?? $default;
	}

	/** Ziyaretçiye görünen kategorilerin tam bitmask'ı. */
	public static function full_mask(): int {
		$mask = self::BIT_NECESSARY;
		if ( self::get( 'show_analytics' ) ) {
			$mask |= self::BIT_ANALYTICS;
		}
		if ( self::get( 'show_ads' ) ) {
			$mask |= self::BIT_ADS;
		}
		return $mask;
	}
}
