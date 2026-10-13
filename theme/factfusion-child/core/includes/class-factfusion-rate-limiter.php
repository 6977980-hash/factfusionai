<?php
/**
 * Rate limiting.
 *
 * @package FactFusion_AI_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Counts requests per visitor in ff_rate_limits. Addresses are stored only
 * as salted SHA-256 hashes. The limits exist to stop abuse, not reading.
 */
final class FactFusion_Rate_Limiter {

	const REPORTS_PER_HOUR = 5;

	const SEARCHES_PER_HOUR = 600;

	/**
	 * Request headers an administrator may choose to read the visitor address
	 * from when the site sits behind a proxy or CDN.
	 */
	const PROXY_HEADERS = array(
		'HTTP_CF_CONNECTING_IP' => 'CF-Connecting-IP',
		'HTTP_X_REAL_IP'        => 'X-Real-IP',
		'HTTP_X_FORWARDED_FOR'  => 'X-Forwarded-For',
	);

	/**
	 * The visitor's IP address.
	 *
	 * @return string Empty when it cannot be determined.
	 */
	public static function client_ip() {
		$header = (string) FactFusion_Settings::get( 'ip_header' );
		$ip     = '';
		if ( isset( self::PROXY_HEADERS[ $header ] ) && ! empty( $_SERVER[ $header ] ) ) {
			// Only the first address in a forwarded list is the visitor.
			$parts = explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) ) );
			$ip    = trim( $parts[0] );
		}
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		}
		$ip = (string) apply_filters( 'factfusion_client_ip', $ip );
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}

	/**
	 * Salted SHA-256 hash of the visitor's address.
	 *
	 * @return string
	 */
	public static function ip_hash() {
		return hash( 'sha256', self::client_ip() . '|' . wp_salt( 'auth' ) );
	}

	/**
	 * The windows that apply to an action.
	 *
	 * @param string $action fact, ai, report or search.
	 * @return array{ip:array<int,int>,global:array<int,int>} Seconds => maximum requests.
	 */
	private static function limits( $action ) {
		switch ( $action ) {
			case 'fact':
				return array(
					'ip'     => array( HOUR_IN_SECONDS => max( FactFusion_Settings::MIN_FACT_FETCH_PER_HOUR, (int) FactFusion_Settings::get( 'rate_fact_hour' ) ) ),
					'global' => array( HOUR_IN_SECONDS => (int) FactFusion_Settings::get( 'rate_global_hour' ) ),
				);
			case 'ai':
				return array(
					'ip'     => array(
						MINUTE_IN_SECONDS => (int) FactFusion_Settings::get( 'rate_ai_burst' ),
						DAY_IN_SECONDS    => (int) FactFusion_Settings::get( 'rate_ai_day' ),
					),
					'global' => array(),
				);
			case 'report':
				return array(
					'ip'     => array( HOUR_IN_SECONDS => self::REPORTS_PER_HOUR ),
					'global' => array(),
				);
			case 'contact':
				return array(
					'ip'     => array( HOUR_IN_SECONDS => FactFusion_Pages::CONTACTS_PER_HOUR ),
					'global' => array(),
				);
			case 'search':
				return array(
					'ip'     => array( HOUR_IN_SECONDS => self::SEARCHES_PER_HOUR ),
					'global' => array(),
				);
		}
		return array(
			'ip'     => array(),
			'global' => array(),
		);
	}

	/**
	 * Counts this request and says whether it is allowed.
	 *
	 * @param string $action fact, ai, report or search.
	 * @return true|WP_Error Error carries HTTP status 429.
	 */
	public static function allow( $action ) {
		global $wpdb;
		$table  = FactFusion_DB::table( 'rate_limits' );
		$hash   = self::ip_hash();
		$limits = self::limits( $action );
		$now    = time();

		foreach ( $limits['ip'] as $seconds => $maximum ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$used = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE ip_hash = %s AND action = %s AND created_at > %s', $table, $hash, $action, gmdate( 'Y-m-d H:i:s', $now - $seconds ) ) );
			if ( $used >= $maximum ) {
				return self::too_many( $seconds );
			}
		}
		foreach ( $limits['global'] as $seconds => $maximum ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$used = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE action = %s AND created_at > %s', $table, $action, gmdate( 'Y-m-d H:i:s', $now - $seconds ) ) );
			if ( $used >= $maximum ) {
				return self::too_many( $seconds );
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->insert(
			$table,
			array(
				'ip_hash'    => $hash,
				'action'     => $action,
				'created_at' => gmdate( 'Y-m-d H:i:s', $now ),
			),
			array( '%s', '%s', '%s' )
		);
		return true;
	}

	/**
	 * Builds the "slow down" error.
	 *
	 * @param int $seconds Window that was exceeded.
	 * @return WP_Error
	 */
	private static function too_many( $seconds ) {
		return new WP_Error(
			'factfusion_rate_limited',
			__( 'Too many requests. Please wait a little and try again.', 'factfusion-ai' ),
			array(
				'status'      => 429,
				'retry_after' => (int) $seconds,
			)
		);
	}

	/**
	 * Deletes counters older than two days. Runs daily.
	 */
	public static function cleanup() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE created_at < %s', FactFusion_DB::table( 'rate_limits' ), gmdate( 'Y-m-d H:i:s', time() - 2 * DAY_IN_SECONDS ) ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE expires_at < %s', FactFusion_DB::table( 'ai_cache' ), gmdate( 'Y-m-d H:i:s' ) ) );
	}
}
