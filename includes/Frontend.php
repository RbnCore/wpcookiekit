<?php
/**
 * Ön yüz: Consent Mode v2 varsayılanı (head), varlıklar, kısa kodlar, betik engelleme.
 *
 * Sayfa içeriği ziyaretçiye göre DEĞİŞMEZ (önbellek uyumlu); tüm durum tarayıcıdadır.
 *
 * @package Cookiekit
 */

declare(strict_types=1);

namespace Cookiekit;

defined( 'ABSPATH' ) || exit;

final class Frontend {

	private const CATEGORIES = array( 'analytics', 'ads' );

	public static function init(): void {
		add_action( 'wp_head', array( self::class, 'head_script' ), 0 );
		add_action( 'wp_enqueue_scripts', array( self::class, 'assets' ) );
		add_shortcode( 'cookiekit_tercihler', array( self::class, 'sc_prefs' ) );
		add_shortcode( 'cookiekit_cerez_listesi', array( self::class, 'sc_cookies' ) );
	}

	private static function active(): bool {
		return (bool) Settings::get( 'enabled' ) && ! is_feed() && ! is_robots();
	}

	/**
	 * Yönetim önizlemesi için yapılandırma: ayarlardan bağımsız olarak TÜM kategoriler dahil,
	 * revizyon 0 (mevcut onay çerezi önizlemeyi gizlemesin), günlük kapalı. Kaydedilmemiş form
	 * değerleri önizleme sayfasında bunun üzerine yazılır.
	 */
	public static function preview_config(): array {
		$cfg        = self::config( true );
		$cfg['r']   = 0;
		$cfg['log'] = '';
		return $cfg;
	}

	/** Banner/panel için istemci yapılandırması. */
	private static function config( bool $all_categories = false ): array {
		$cats = array();
		$list = Cookies::catalog();
		foreach ( Cookies::categories( $all_categories ) as $cat ) {
			$cats[] = array(
				'id'      => $cat['id'],
				'bit'     => $cat['bit'],
				'name'    => $cat['name'],
				'desc'    => $cat['desc'],
				'locked'  => (bool) $cat['locked'],
				'cookies' => array_values( $list[ $cat['id'] ] ?? array() ),
			);
		}
		$privacy = (string) Settings::get( 'privacy_url' );
		$cookie  = (string) Settings::get( 'cookie_url' );
		return array(
			'r'    => (int) Settings::get( 'revision' ),
			'f'    => Settings::full_mask(),
			'days' => (int) Settings::get( 'days' ),
			'pos'  => (string) Settings::get( 'position' ),
			'log'  => Settings::get( 'log' ) ? esc_url_raw( rest_url( 'cookiekit/v1/consent' ) ) : '',
			'urls' => array(
				'privacy' => '' !== $privacy ? esc_url_raw( $privacy ) : '',
				'cookie'  => '' !== $cookie ? esc_url_raw( $cookie ) : '',
			),
			't'    => array(
				'title'   => (string) Settings::get( 'title' ),
				'desc'    => (string) Settings::get( 'desc' ),
				'accept'  => (string) Settings::get( 'accept' ),
				'reject'  => (string) Settings::get( 'reject' ),
				'prefs'   => (string) Settings::get( 'prefs' ),
				'save'    => (string) Settings::get( 'save' ),
				'close'   => 'Kapat',
				'panel'   => 'Çerez tercihleri',
				'privacy' => 'Gizlilik Politikası',
				'cookie'  => 'Çerez Politikası',
				'cname'   => 'Çerezler',
				'always'  => 'Her zaman açık',
			),
			'c'    => $cats,
		);
	}

	/** Sayfa başına TEK satır içi betik: Consent Mode v2 varsayılanı + yapılandırma. */
	public static function head_script(): void {
		if ( ! self::active() ) {
			return;
		}
		$cfg = wp_json_encode( self::config(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		if ( false === $cfg ) {
			return;
		}
		// Kullanıcı içeriği yok: $cfg yukarıdaki bayraklarla güvenli kodlanmış JSON'dur.
		echo "<script id=\"cookiekit-cm\">window.dataLayer=window.dataLayer||[];window.gtag=window.gtag||function(){dataLayer.push(arguments)};"
			. "gtag('consent','default',{ad_storage:'denied',analytics_storage:'denied',ad_user_data:'denied',ad_personalization:'denied',wait_for_update:500});"
			. 'window.cookiekitCfg=' . $cfg . ';' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			. "(function(){var m=document.cookie.match(/(?:^|; )cookiekit_consent=(\\d+)\\.(\\d+)/);"
			. "if(m&&+m[1]===cookiekitCfg.r){var k=+m[2]&cookiekitCfg.f,a=k&2?'granted':'denied',d=k&4?'granted':'denied';"
			. "gtag('consent','update',{analytics_storage:a,ad_storage:d,ad_user_data:d,ad_personalization:d})}})();</script>\n";
	}

	public static function assets(): void {
		if ( ! self::active() ) {
			return;
		}
		wp_enqueue_style( 'cookiekit', COOKIEKIT_URL . 'assets/cookiekit.css', array(), COOKIEKIT_VERSION );
		$vars = '';
		foreach ( array( 'color_accent' => '--cookiekit-ac', 'color_bg' => '--cookiekit-bg', 'color_text' => '--cookiekit-fg' ) as $key => $var ) {
			$hex = sanitize_hex_color( (string) Settings::get( $key ) );
			if ( $hex ) {
				$vars .= $var . ':' . $hex . ';';
			}
		}
		if ( '' !== $vars ) {
			wp_add_inline_style( 'cookiekit', ':root,:root[data-theme]{' . $vars . '}' );
		}
		wp_enqueue_script( 'cookiekit', COOKIEKIT_URL . 'assets/cookiekit.js', array(), COOKIEKIT_VERSION, array( 'strategy' => 'defer', 'in_footer' => true ) );
	}

	/** Onaya bağlı betik etiketi (bkz. cookiekit_script()). */
	public static function blocked_script( string $category, string $src_or_inline ): string {
		if ( ! in_array( $category, self::CATEGORIES, true ) ) {
			return '';
		}
		if ( preg_match( '#^https?://#i', $src_or_inline ) ) {
			return '<script type="text/plain" data-cookiekit-category="' . esc_attr( $category ) . '" data-src="' . esc_url( $src_or_inline ) . '"></script>';
		}
		$code = str_ireplace( '</script', '<\\/script', $src_or_inline );
		return '<script type="text/plain" data-cookiekit-category="' . esc_attr( $category ) . '">' . $code . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- çağıran sitenin kendi kodu.
	}

	/** [cookiekit_tercihler label="…"] */
	public static function sc_prefs( $atts ): string {
		$atts = shortcode_atts( array( 'label' => 'Çerez tercihlerini yönet' ), (array) $atts, 'cookiekit_tercihler' );
		return '<button type="button" class="cookiekit-open" data-cookiekit-open>' . esc_html( (string) $atts['label'] ) . '</button>';
	}

	/** [cookiekit_cerez_listesi] */
	public static function sc_cookies(): string {
		$catalog = Cookies::catalog();
		$out     = '';
		foreach ( Cookies::categories() as $cat ) {
			$rows = $catalog[ $cat['id'] ] ?? array();
			if ( ! $rows ) {
				continue;
			}
			$out .= '<h3>' . esc_html( $cat['name'] ) . '</h3><p>' . esc_html( $cat['desc'] ) . '</p>';
			$out .= '<div class="cookiekit-tw" role="region" tabindex="0" aria-label="' . esc_attr( $cat['name'] ) . '"><table class="cookiekit-table"><thead><tr>';
			foreach ( array( 'Çerez', 'Sağlayıcı', 'Amaç', 'Süre' ) as $th ) {
				$out .= '<th scope="col">' . esc_html( $th ) . '</th>';
			}
			$out .= '</tr></thead><tbody>';
			foreach ( $rows as $row ) {
				$out .= '<tr><th scope="row"><code>' . esc_html( (string) $row[0] ) . '</code></th><td>' . esc_html( (string) $row[1] ) . '</td><td>' . esc_html( (string) $row[2] ) . '</td><td>' . esc_html( (string) $row[3] ) . '</td></tr>';
			}
			$out .= '</tbody></table></div>';
		}
		if ( '' !== $out ) {
			wp_enqueue_style( 'cookiekit', COOKIEKIT_URL . 'assets/cookiekit.css', array(), COOKIEKIT_VERSION );
		}
		return $out;
	}
}
