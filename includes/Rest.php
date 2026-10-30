<?php
/**
 * REST: POST /wp-json/cookiekit/v1/consent
 *
 * @package Cookiekit
 */

declare(strict_types=1);

namespace Cookiekit;

use AdminKit\Core\Security\Guard;
use AdminKit\Core\Security\Host;
use AdminKit\Core\Security\RateLimit;

defined( 'ABSPATH' ) || exit;

final class Rest {

	public const ROUTE      = '/cookiekit/v1/consent';
	public const RATE_LIMIT = 10;

	public static function init(): void {
		add_action( 'rest_api_init', array( self::class, 'register' ) );
		add_filter( 'rest_pre_serve_request', array( self::class, 'strip_cors' ), 99, 4 );
	}

	public static function register(): void {
		register_rest_route(
			'cookiekit/v1',
			'/consent',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'handle' ),
				'permission_callback' => Guard::public_route( 'anonymous visitor consent: allow-listed fields only, same-origin, rate limited' ), // Herkese açık, ama yalnız izinli alanlar + hız sınırı.
				'args'                => array(
					'c' => array(
						'required'          => true,
						'validate_callback' => static fn( $v ): bool => is_numeric( $v ) && (int) $v >= 0 && (int) $v <= 7,
						'sanitize_callback' => 'absint',
					),
					'r' => array(
						'required'          => true,
						'validate_callback' => static fn( $v ): bool => is_numeric( $v ) && (int) $v >= 0 && (int) $v <= 1000000,
						'sanitize_callback' => 'absint',
					),
					'p' => array(
						'required'          => false,
						'validate_callback' => static fn( $v ): bool => is_string( $v ) && strlen( $v ) <= 400,
					),
				),
			)
		);
	}

	/** Bu yolda çekirdeğin eklediği CORS başlıklarını kaldırır (CORS yok). */
	public static function strip_cors( $served, $result, $request, $server ) {
		if ( $request instanceof \WP_REST_Request && 0 === strpos( $request->get_route(), self::ROUTE ) ) {
			foreach ( array( 'Access-Control-Allow-Origin', 'Access-Control-Allow-Methods', 'Access-Control-Allow-Credentials', 'Access-Control-Expose-Headers', 'Access-Control-Allow-Headers' ) as $h ) {
				header_remove( $h );
			}
			header( 'Cache-Control: no-store' );
		}
		return $served;
	}

	public static function client_ip(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) ) : '';
		/**
		 * Ters vekil/CDN arkasında gerçek istemci IP'sini döndürmek için.
		 *
		 * @param string $ip REMOTE_ADDR değeri.
		 */
		$ip = (string) apply_filters( 'cookiekit_client_ip', $ip );
		return Host::is_ip( $ip ) ? $ip : '0.0.0.0';
	}

	/** IP başına dakikada RATE_LIMIT istek; aşılırsa true. */
	private static function limited( string $ip ): bool {
		return RateLimit::too_many( Log::ip_digest( $ip ), self::RATE_LIMIT );
	}

	public static function handle( \WP_REST_Request $request ) {
		if ( ! Guard::same_origin() ) {
			return new \WP_Error( 'cookiekit_origin', __( 'Forbidden origin.', 'cookiekit' ), array( 'status' => 403 ) );
		}
		if ( ! Settings::get( 'enabled' ) || ! Settings::get( 'log' ) ) {
			return rest_ensure_response( array( 'logged' => false ) );
		}
		$ip = self::client_ip();
		if ( self::limited( $ip ) ) {
			return new \WP_Error( 'cookiekit_rate', __( 'Too many requests.', 'cookiekit' ), array( 'status' => 429 ) );
		}
		$mask = ( (int) $request['c'] ) | Settings::BIT_NECESSARY;
		$mask &= Settings::full_mask();
		$path = (string) wp_parse_url( (string) $request->get_param( 'p' ), PHP_URL_PATH ); // Sorgu dizesi atılır: kişisel veri taşıyabilir.
		$path = '/' . ltrim( substr( sanitize_text_field( $path ), 0, 190 ), '/' );
		$ua   = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		$ok   = Log::insert( $mask, (int) $request['r'], $ip, $ua, $path );
		return rest_ensure_response( array( 'logged' => $ok ) );
	}
}
