<?php
/**
 * Plugin Name:       CookieKit — Cookie and Consent Manager
 * Plugin URI:        https://github.com/RbnCore/wpcookiekit
 * Description:       KVKK-compliant cookie banner and preferences panel, Google Consent Mode v2, consent log and a script-blocking API. Connects to GitHub only to check for updates (can be turned off in the settings).
 * Version:           0.1.3
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Requires Plugins:  adminkit
 * Author:            RBN Bilişim
 * Author URI:        https://github.com/RbnCore
 * Update URI:        https://github.com/RbnCore/wpcookiekit
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       cookiekit
 * Domain Path:       /languages
 *
 * @package Cookiekit
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

define( 'COOKIEKIT_VERSION', '0.1.3' );
define( 'COOKIEKIT_FILE', __FILE__ );
define( 'COOKIEKIT_DIR', plugin_dir_path( __FILE__ ) );
define( 'COOKIEKIT_URL', plugin_dir_url( __FILE__ ) );

spl_autoload_register(
	static function ( string $class_name ): void {
		if ( 0 !== strpos( $class_name, 'Cookiekit\\' ) ) {
			return;
		}
		$path = COOKIEKIT_DIR . 'includes/' . str_replace( '\\', '/', substr( $class_name, strlen( 'Cookiekit\\' ) ) ) . '.php';
		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);

require_once COOKIEKIT_DIR . 'includes/functions.php';

register_activation_hook( __FILE__, array( \Cookiekit\Log::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \Cookiekit\Log::class, 'deactivate' ) );

/**
 * Çalışma zamanı kapısı (cekirdek.md §6):
 * AdminKit API >= 4 zorunludur (merkezi güncelleyici + ortak güncelleme kartı). Karşılanmazsa eklenti kancalarını eklemeden güvenle kapanır
 * ve yönetimde uyarı gösterir.
 */
add_action(
	'plugins_loaded',
	static function (): void {
		if ( ! defined( 'ADMINKIT_CORE_API' ) || ADMINKIT_CORE_API < 4 || ! class_exists( 'AdminKit\Core\Core' ) || ! class_exists( 'AdminKit\Core\Settings' ) ) {
			add_action(
				'admin_notices',
				static function (): void {
					if ( ! current_user_can( 'activate_plugins' ) ) {
						return;
					}
					$adminkit_file = 'adminkit/adminkit.php';
					$installed     = file_exists( WP_PLUGIN_DIR . '/' . $adminkit_file );
					if ( $installed ) {
						$url  = wp_nonce_url(
							admin_url( 'plugins.php?action=activate&plugin=' . rawurlencode( $adminkit_file ) ),
							'activate-plugin_' . $adminkit_file
						);
						$link = sprintf(
							'<a href="%s">%s</a>',
							esc_url( $url ),
							esc_html__( 'activate AdminKit', 'cookiekit' )
						);
						$msg  = sprintf(
							/* translators: %s: link to activate AdminKit */
							__( 'CookieKit requires the AdminKit plugin. Please %s to continue.', 'cookiekit' ),
							$link
						);
					} else {
						$url  = 'https://github.com/RbnCore/wpadminkit/releases/latest';
						$link = sprintf(
							'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
							esc_url( $url ),
							esc_html__( 'download and install AdminKit', 'cookiekit' )
						);
						$msg  = sprintf(
							/* translators: %s: link to download AdminKit */
							__( 'CookieKit requires the AdminKit plugin. Please %s to continue.', 'cookiekit' ),
							$link
						);
					}
					printf( '<div class="notice notice-error"><p>%s</p></div>', wp_kses_post( $msg ) );
				}
			);
			return;
		}

		cookiekit_boot();
	},
	5
);

/**
 * Eklenti kancalarını ve çekirdek (Cache) kayıtlarını kurar.
 * Yalnız AdminKit çalışma zamanı kapısından geçildiğinde çalışır.
 */
function cookiekit_boot(): void {
	$register_core = static function (): void {
		\AdminKit\Core\Cache::register_group( 'cookiekit', 'rate', 60 );
	};
	if ( did_action( 'rbnkit_core_ready' ) ) {
		$register_core();
	} else {
		add_action( 'rbnkit_core_ready', $register_core );
	}

	load_plugin_textdomain( 'cookiekit', false, dirname( plugin_basename( COOKIEKIT_FILE ) ) . '/languages' );
	// Ayar şeması AdminKit ayar motoruna kaydolur (tek motor, eklenti yalnız şemayı bildirir).
	\Cookiekit\Settings::register();
	\Cookiekit\Log::maybe_upgrade();
	\Cookiekit\Frontend::init();
	\Cookiekit\Rest::init();
	\Cookiekit\Admin::init();
	// Güncellemeyi AdminKit çekirdeği yapar (tek merkezi Updater); burada yalnız kayıt vardır.
	\AdminKit\Core\Updater::register( 'cookiekit', COOKIEKIT_FILE, COOKIEKIT_VERSION );
}
