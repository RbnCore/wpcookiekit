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
			'title'            => 'Çerez tercihleriniz',
			'desc'             => 'Sitenin çalışması için zorunlu çerezler kullanırız. Analiz ve reklam çerezleri yalnızca sizin izninizle kurulur. Tercihlerinizi istediğiniz zaman değiştirebilirsiniz.',
			'accept'           => 'Kabul et',
			'reject'           => 'Reddet',
			'prefs'            => 'Tercihler',
			'save'             => 'Seçimimi kaydet',
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
