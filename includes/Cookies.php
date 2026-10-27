<?php
/**
 * Çerez kataloğu: yalnız belgelenmiş çerezler.
 *
 * Kaynaklar: Google reklam ve ölçüm çerezleri referansı (business.safety.google/adscookies),
 * WordPress çekirdeği (yorum çerezleri, YEAR_IN_SECONDS), CookieKit'nin kendi çerezi.
 *
 * @package Cookiekit
 */

declare(strict_types=1);

namespace Cookiekit;

defined( 'ABSPATH' ) || exit;

final class Cookies {

	/** Yönetim ekranından eklenen özel çerezler (tek yeni option). */
	public const CUSTOM_OPTION = 'cookiekit_custom_cookies';
	public const CUSTOM_MAX    = 40;
	public const CUSTOM_CATS   = array( 'necessary', 'analytics', 'ads' );

	/**
	 * Kategori tanımları (id, ad, açıklama, kilitli mi). $all true ise ayara bakmadan hepsi (önizleme).
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function categories( bool $all = false ): array {
		$cats = array(
			array(
				'id'     => 'necessary',
				'bit'    => Settings::BIT_NECESSARY,
				'name'   => 'Zorunlu çerezler',
				'desc'   => 'Sitenin çalışması ve tercihinizin hatırlanması için gereklidir. Kapatılamaz.',
				'locked' => true,
			),
		);
		if ( $all || Settings::get( 'show_analytics' ) ) {
			$cats[] = array(
				'id'     => 'analytics',
				'bit'    => Settings::BIT_ANALYTICS,
				'name'   => 'Analiz çerezleri',
				'desc'   => 'Google Analytics ile sitenin nasıl kullanıldığını ölçer. Yalnızca izin verirseniz kurulur.',
				'locked' => false,
			);
		}
		if ( $all || Settings::get( 'show_ads' ) ) {
			$cats[] = array(
				'id'     => 'ads',
				'bit'    => Settings::BIT_ADS,
				'name'   => 'Reklam çerezleri',
				'desc'   => 'Google AdSense ve Google reklam ortakları ilginizi çekebilecek reklamları göstermek için kullanır. Yalnızca izin verirseniz kurulur.',
				'locked' => false,
			);
		}
		return $cats;
	}

	/**
	 * Özel çerezleri doğrular: ad ≤64, sağlayıcı ≤80, amaç ≤200, süre ≤40, kategori beyaz liste, en çok 40 kayıt.
	 *
	 * @param mixed $raw Ham değer.
	 * @return array<int,array{id:string,cat:string,name:string,provider:string,purpose:string,duration:string}>
	 */
	public static function sanitize_custom( $raw ): array {
		$out = array();
		foreach ( is_array( $raw ) ? $raw : array() as $row ) {
			if ( ! is_array( $row ) || count( $out ) >= self::CUSTOM_MAX ) {
				continue;
			}
			$item = self::clean_cookie( $row );
			if ( null !== $item ) {
				$out[] = $item;
			}
		}
		return $out;
	}

	/**
	 * Tek özel çerez satırı; geçersizse null.
	 *
	 * @param array<string,mixed> $row Ham satır.
	 * @return array{id:string,cat:string,name:string,provider:string,purpose:string,duration:string}|null
	 */
	public static function clean_cookie( array $row ): ?array {
		$cat  = isset( $row['cat'] ) ? sanitize_key( (string) $row['cat'] ) : '';
		$name = mb_substr( trim( sanitize_text_field( (string) ( $row['name'] ?? '' ) ) ), 0, 64 );
		if ( '' === $name || ! in_array( $cat, self::CUSTOM_CATS, true ) ) {
			return null;
		}
		$provider = mb_substr( sanitize_text_field( (string) ( $row['provider'] ?? '' ) ), 0, 80 );
		$purpose  = mb_substr( sanitize_text_field( (string) ( $row['purpose'] ?? '' ) ), 0, 200 );
		$duration = mb_substr( sanitize_text_field( (string) ( $row['duration'] ?? '' ) ), 0, 40 );
		$id       = isset( $row['id'] ) ? preg_replace( '/[^a-f0-9]/', '', (string) $row['id'] ) : '';
		return array(
			'id'       => '' !== $id ? substr( (string) $id, 0, 12 ) : substr( md5( $cat . '|' . $name . '|' . wp_generate_uuid4() ), 0, 12 ),
			'cat'      => $cat,
			'name'     => $name,
			'provider' => '' !== $provider ? $provider : 'Bu site',
			'purpose'  => $purpose,
			'duration' => $duration,
		);
	}

	/** @return array<int,array{id:string,cat:string,name:string,provider:string,purpose:string,duration:string}> */
	public static function custom(): array {
		return self::sanitize_custom( get_option( self::CUSTOM_OPTION, array() ) );
	}

	/**
	 * Yerleşik (kodla gelen) çerezler.
	 *
	 * @return array<string,array<int,array<int,string>>>
	 */
	public static function builtin(): array {
		$days = (int) Settings::get( 'days', 180 );
		$list = array(
			'necessary' => array(
				array( 'cookiekit_consent', 'Bu site (CookieKit)', 'Çerez tercihinizi hatırlar.', $days . ' gün' ),
				array( 'comment_author_*, comment_author_email_*, comment_author_url_*', 'Bu site (WordPress)', 'Yorum formunda “bu bilgileri kaydet” işaretlenirse adınızı, e-postanızı ve site adresinizi hatırlar.', '1 yıl' ),
			),
			'analytics' => array(
				array( '_ga', 'Google Analytics', 'Ziyaretçi ve site kullanımı ölçümü (Google çerez referansında amaç: Analiz).', '2 yıl' ),
				array( '_ga_<ID>', 'Google Analytics', 'Ziyaretçi ve site kullanımı ölçümü (Google çerez referansında amaç: Analiz).', '2 yıl' ),
				array( '_gid', 'Google Analytics', 'Ziyaretçi ve site kullanımı ölçümü (Google çerez referansında amaç: Analiz).', '24 saat' ),
				array( '_gat', 'Google Analytics', 'Ziyaretçi ve site kullanımı ölçümü (Google çerez referansında amaç: Analiz).', '1 dakika' ),
			),
			'ads'       => array(
				array( 'IDE', 'Google (doubleclick.net)', 'Reklam ve güvenlik.', 'AB/AEA ve İngiltere’de 13 ay, diğer yerlerde 24 ay' ),
				array( 'test_cookie', 'Google (doubleclick.net)', 'İşlevsellik (Google çerez referansına göre).', '15 dakika' ),
				array( '__gads', 'Google', 'Reklam ve güvenlik.', '13 ay' ),
				array( '__gpi', 'Google', 'Reklam ve güvenlik.', '13 ay' ),
				array( '__eoi', 'Google', 'Güvenlik.', '6 ay' ),
				array( 'DSID', 'Google (doubleclick.net)', 'Güvenlik, işlevsellik ve reklam.', '2 hafta' ),
			),
		);
		return $list;
	}

	/**
	 * Çerezler: kategori => [ad, sağlayıcı, amaç, süre]. Yerleşik + özel + filtre.
	 *
	 * @return array<string,array<int,array<int,string>>>
	 */
	public static function catalog(): array {
		$list = self::builtin();
		foreach ( self::custom() as $row ) {
			$list[ $row['cat'] ][] = array( $row['name'], $row['provider'], $row['purpose'], $row['duration'] );
		}
		/**
		 * Siteye özel çerezleri ekleme/çıkarma noktası.
		 *
		 * @param array $list kategori => [ [ad, sağlayıcı, amaç, süre], ... ].
		 */
		$list = apply_filters( 'cookiekit_cookies', $list );
		return is_array( $list ) ? $list : array();
	}
}
