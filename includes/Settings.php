<?php
/**
 * Ayarlar: TEK şema bildirimi (motor: AdminKit `Core\Settings`). Okuma ve temizleme motordadır; burada yalnız
 * alan adları, türleri, varsayılanları, sınırları ve hangi formun hangi alanı yazabildiği yazılır.
 *
 * Seçenek adı ve saklanan şekil DEĞİŞMEZ: `cookiekit_settings` düz bir dizi (bayraklar 1/0, süre int, metinler
 * string). Köprü/migrasyon kodu yoktur; motor mevcut veriyi bu şemayla okur.
 *
 * @package Cookiekit
 */

declare(strict_types=1);

namespace Cookiekit;

defined( 'ABSPATH' ) || exit;

final class Settings {

	public const OPTION = 'cookiekit_settings';

	/** Motor kayıt kimliği (eklenti metin alanı). */
	public const SLUG = 'cookiekit';

	/** `options.php` ayar grubu. */
	public const GROUP = 'cookiekit_group';

	/** Kategori bitleri: çerez değerindeki bitmask. */
	public const BIT_NECESSARY = 1;
	public const BIT_ANALYTICS = 2;
	public const BIT_ADS       = 4;

	public const POSITIONS = array( 'bottom-left', 'bottom-center', 'bottom-right', 'box' );

	/** Ayar formlarının kimlikleri (gizli `_tab` alanının değerleri). */
	public const FORMS = array( 'genel', 'gorunum', 'metinler', 'kurulum' );

	/**
	 * Şema: alan => tür, varsayılan, sınır ve onu yazan form(lar). Varsayılanlar BURADADIR (tek kaynak).
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function schema(): array {
		self::textdomain();
		$colour_msg = __( 'Enter a valid colour (e.g. #0b5fff); the old value was kept.', 'cookiekit' );
		$link_msg   = __( 'The address must start with “/” (a path on this site) or with this site\'s http(s) address; the old value was kept.', 'cookiekit' );
		return array(
			'enabled'             => array(
				'type'    => 'flag',
				'default' => 1,
				'forms'   => array( 'genel' ),
			),
			'position'            => array(
				'type'    => 'enum',
				'default' => 'bottom-center',
				'values'  => self::POSITIONS,
				'invalid' => 'keep',
				'forms'   => array( 'gorunum' ),
			),
			'show_analytics'      => array(
				'type'    => 'flag',
				'default' => 1,
				'forms'   => array( 'genel' ),
			),
			'show_ads'            => array(
				'type'    => 'flag',
				'default' => 1,
				'forms'   => array( 'genel' ),
			),
			'log'                 => array(
				'type'    => 'flag',
				'default' => 1,
				'forms'   => array( 'genel' ),
			),
			'revision'            => array(
				'type'     => 'int',
				'default'  => 1,
				'internal' => true,
			),
			'wizard_done'         => array(
				'type'     => 'flag',
				'default'  => 0,
				'internal' => true,
			),
			'days'                => array(
				'type'     => 'int',
				'default'  => 180,
				'min'      => 30,
				'max'      => 395,
				'error_id' => 'f-days',
				'message'  => __( 'The duration must be between 30 and 395 days; the value was corrected.', 'cookiekit' ),
				'forms'    => array( 'genel' ),
			),
			'privacy_url'         => array(
				'type'     => 'link',
				'default'  => '/gizlilik-politikasi/',
				'invalid'  => 'keep',
				'error_id' => 't-priv',
				'message'  => $link_msg,
				'forms'    => array( 'metinler', 'kurulum' ),
			),
			'cookie_url'          => array(
				'type'     => 'link',
				'default'  => '/cerez-politikasi/',
				'invalid'  => 'keep',
				'error_id' => 't-cook',
				'message'  => $link_msg,
				'forms'    => array( 'metinler', 'kurulum' ),
			),
			'color_accent'        => array(
				'type'     => 'color',
				'default'  => '',
				'invalid'  => 'keep',
				'error_id' => 'c-ac',
				'message'  => $colour_msg,
				'forms'    => array( 'gorunum' ),
			),
			'color_bg'            => array(
				'type'     => 'color',
				'default'  => '',
				'invalid'  => 'keep',
				'error_id' => 'c-bg',
				'message'  => $colour_msg,
				'forms'    => array( 'gorunum' ),
			),
			'color_text'          => array(
				'type'     => 'color',
				'default'  => '',
				'invalid'  => 'keep',
				'error_id' => 'c-fg',
				'message'  => $colour_msg,
				'forms'    => array( 'gorunum' ),
			),
			'delete_on_uninstall' => array(
				'type'    => 'flag',
				'default' => 0,
				'forms'   => array( 'genel' ),
			),
			'title'               => array(
				'type'     => 'text',
				'default'  => __( 'Your cookie preferences', 'cookiekit' ),
				'max_len'  => 80,
				'empty'    => 'default',
				'forms'    => array( 'metinler' ),
			),
			'desc'                => array(
				'type'     => 'text_multi',
				'default'  => __( 'We use essential cookies to make the site work. Analytics and advertising cookies are only set with your permission. You can change your preferences at any time.', 'cookiekit' ),
				'max_len'  => 500,
				'empty'    => 'default',
				'forms'    => array( 'metinler' ),
			),
			'accept'              => array(
				'type'    => 'text',
				'default' => __( 'Accept', 'cookiekit' ),
				'max_len' => 30,
				'empty'   => 'default',
				'forms'   => array( 'metinler' ),
			),
			'reject'              => array(
				'type'    => 'text',
				'default' => __( 'Reject', 'cookiekit' ),
				'max_len' => 30,
				'empty'   => 'default',
				'forms'   => array( 'metinler' ),
			),
			'prefs'               => array(
				'type'    => 'text',
				'default' => __( 'Preferences', 'cookiekit' ),
				'max_len' => 30,
				'empty'   => 'default',
				'forms'   => array( 'metinler' ),
			),
			'save'                => array(
				'type'    => 'text',
				'default' => __( 'Save my choice', 'cookiekit' ),
				'max_len' => 40,
				'empty'   => 'default',
				'forms'   => array( 'metinler' ),
			),
		);
	}

	/** Motor kaydı: şema + form kümesi + eklentiye özel yan etkiler. */
	public static function register(): void {
		\AdminKit\Core\Settings::register(
			self::SLUG,
			array(
				'option'        => self::OPTION,
				'schema'        => static function (): array {
					return self::schema();
				},
				'forms'         => self::FORMS,
				'form_field'    => '_tab',
				'form_required' => true,
				// Mevcut satırın autoload bayrağına dokunulmaz; yeni kurulumda WordPress karar verir (eskisi gibi).
				'autoload'      => null,
				'on_save'       => static function ( array $before, array $after, array $input, string $form ): array {
					$patch = array( 'wizard_done' => 1 );
					if ( 'genel' === $form ) {
						$patch['revision'] = (int) $before['revision'] + ( empty( $input['bump_revision'] ) ? 0 : 1 );
					}
					if ( 'kurulum' === $form ) {
						$tur = isset( $input['_tur'] ) ? sanitize_key( (string) $input['_tur'] ) : '';
						if ( in_array( $tur, array( 'reklam', 'analiz' ), true ) ) {
							$patch['show_analytics'] = 1;
							$patch['show_ads']       = 'reklam' === $tur ? 1 : 0;
						}
						$patch['enabled'] = 1;
					}
					return $patch;
				},
				'messages'      => static function (): array {
					return array(
						'unknown_form'    => __( 'The form was not recognised; no setting was changed.', 'cookiekit' ),
						'unknown_form_id' => 'ck-tab',
					);
				},
			)
		);
	}

	/**
	 * Varsayılanlar şemadan türetilir (etkinleştirme anında motor sınıfına gerek kalmaz).
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults(): array {
		return array_map(
			static function ( array $row ) {
				return $row['default'];
			},
			self::schema()
		);
	}

	/**
	 * Kaydedilmiş ayarlar varsayılanların üzerinde (okuma/temizleme motorda).
	 *
	 * @return array<string,mixed>
	 */
	public static function all(): array {
		self::ensure();
		return \AdminKit\Core\Settings::all( self::SLUG );
	}

	/**
	 * @param mixed $default Bulunamazsa dönecek değer.
	 * @return mixed
	 */
	public static function get( string $key, $default = null ) {
		self::ensure();
		return \AdminKit\Core\Settings::get( self::SLUG, $key, $default );
	}

	/** Şema kaydı yükleme sırası ne olursa olsun bir kez yapılır (kayıt ilk kazanır, ikinci çağrı etkisiz). */
	private static function ensure(): void {
		if ( ! \AdminKit\Core\Settings::registered( self::SLUG ) ) {
			self::register();
		}
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

	/** Çeviri, ayar ekranı çizilmeden önce yüklü olsun (etkinleştirme anında da çağrılır). */
	private static function textdomain(): void {
		if ( ! is_textdomain_loaded( 'cookiekit' ) && defined( 'COOKIEKIT_FILE' ) ) {
			load_plugin_textdomain( 'cookiekit', false, dirname( plugin_basename( COOKIEKIT_FILE ) ) . '/languages' );
		}
	}
}
