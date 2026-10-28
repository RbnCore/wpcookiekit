<?php
/**
 * Plugin Name:       CookieKit — Cookie and Consent Manager
 * Plugin URI:        https://github.com/RbnCore/wpcookiekit
 * Description:       KVKK-compliant cookie banner and preferences panel, Google Consent Mode v2, consent log and a script-blocking API. Connects to GitHub only to check for updates (can be turned off in the settings).
 * Version:           0.1.2
 * Requires at least: 6.4
 * Requires PHP:      8.1
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

define( 'COOKIEKIT_VERSION', '0.1.2' );
define( 'COOKIEKIT_FILE', __FILE__ );
define( 'COOKIEKIT_DIR', plugin_dir_path( __FILE__ ) );
define( 'COOKIEKIT_URL', plugin_dir_url( __FILE__ ) );

require_once COOKIEKIT_DIR . 'includes/RbnKitMenu.php';

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

add_action(
	'plugins_loaded',
	static function (): void {
		load_plugin_textdomain( 'cookiekit', false, dirname( plugin_basename( COOKIEKIT_FILE ) ) . '/languages' );
		\Cookiekit\Log::maybe_upgrade();
		\Cookiekit\Frontend::init();
		\Cookiekit\Rest::init();
		\Cookiekit\Admin::init();
		// Güncelleme kodu yalnız yönetim, zamanlanmış görev ve WP-CLI isteğinde yüklenir; ön yüzde çalışmaz.
		if ( is_admin() || wp_doing_cron() || defined( 'WP_CLI' ) ) {
			\Cookiekit\Updater::init();
		}
	}
);
