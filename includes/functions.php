<?php
/**
 * Genel yardımcılar (betik engelleme API'si).
 *
 * @package Cookiekit
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'cookiekit_script' ) ) {
	/**
	 * Onaya bağlı betik etiketi üretir. Etiket tarayıcıda çalışmaz; ilgili kategori
	 * onaylanınca CookieKit betiği etkinleştirir.
	 *
	 * @param string $category       'analytics' veya 'ads'.
	 * @param string $src_or_inline  http(s) adresi ya da satır içi JS kodu.
	 * @return string Hazır <script> etiketi (kaçışlanmış).
	 */
	function cookiekit_script( string $category, string $src_or_inline ): string {
		return \Cookiekit\Frontend::blocked_script( $category, $src_or_inline );
	}
}
