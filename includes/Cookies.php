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
				'name'   => __( 'Essential cookies', 'cookiekit' ),
				'desc'   => __( 'Required for the site to work and to remember your choice. Cannot be turned off.', 'cookiekit' ),
				'locked' => true,
			),
		);
		if ( $all || Settings::get( 'show_analytics' ) ) {
			$cats[] = array(
				'id'     => 'analytics',
				'bit'    => Settings::BIT_ANALYTICS,
				'name'   => __( 'Analytics cookies', 'cookiekit' ),
				'desc'   => __( 'Measures how the site is used with Google Analytics. Only set if you allow it.', 'cookiekit' ),
				'locked' => false,
			);
		}
		if ( $all || Settings::get( 'show_ads' ) ) {
			$cats[] = array(
				'id'     => 'ads',
				'bit'    => Settings::BIT_ADS,
				'name'   => __( 'Advertising cookies', 'cookiekit' ),
				'desc'   => __( 'Used by Google AdSense and Google advertising partners to show ads that may interest you. Only set if you allow it.', 'cookiekit' ),
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
			'provider' => '' !== $provider ? $provider : __( 'This site', 'cookiekit' ),
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
				array( 'cookiekit_consent', __( 'This site (CookieKit)', 'cookiekit' ), __( 'Remembers your cookie choice.', 'cookiekit' ), sprintf( __( '%d days', 'cookiekit' ), $days ) ),
				array( 'comment_author_*, comment_author_email_*, comment_author_url_*', __( 'This site (WordPress)', 'cookiekit' ), __( 'Remembers your name, email and website if you tick “save my details” in the comment form.', 'cookiekit' ), __( '1 year', 'cookiekit' ) ),
			),
			'analytics' => array(
				array( '_ga', 'Google Analytics', __( 'Visitor and site usage measurement (purpose in the Google cookie reference: Analytics).', 'cookiekit' ), __( '2 years', 'cookiekit' ) ),
				array( '_ga_<ID>', 'Google Analytics', __( 'Visitor and site usage measurement (purpose in the Google cookie reference: Analytics).', 'cookiekit' ), __( '2 years', 'cookiekit' ) ),
				array( '_gid', 'Google Analytics', __( 'Visitor and site usage measurement (purpose in the Google cookie reference: Analytics).', 'cookiekit' ), __( '24 hours', 'cookiekit' ) ),
				array( '_gat', 'Google Analytics', __( 'Visitor and site usage measurement (purpose in the Google cookie reference: Analytics).', 'cookiekit' ), __( '1 minute', 'cookiekit' ) ),
			),
			'ads'       => array(
				array( 'IDE', 'Google (doubleclick.net)', __( 'Advertising and security.', 'cookiekit' ), __( '13 months in the EU/EEA and the UK, 24 months elsewhere', 'cookiekit' ) ),
				array( 'test_cookie', 'Google (doubleclick.net)', __( 'Functionality (per the Google cookie reference).', 'cookiekit' ), __( '15 minutes', 'cookiekit' ) ),
				array( '__gads', 'Google', __( 'Advertising and security.', 'cookiekit' ), __( '13 months', 'cookiekit' ) ),
				array( '__gpi', 'Google', __( 'Advertising and security.', 'cookiekit' ), __( '13 months', 'cookiekit' ) ),
				array( '__eoi', 'Google', __( 'Security.', 'cookiekit' ), __( '6 months', 'cookiekit' ) ),
				array( 'DSID', 'Google (doubleclick.net)', __( 'Security, functionality and advertising.', 'cookiekit' ), __( '2 weeks', 'cookiekit' ) ),
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
