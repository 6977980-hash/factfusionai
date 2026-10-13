<?php
/**
 * Plugin settings.
 *
 * @package FactFusion_AI_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads and saves settings. Secrets are never stored here: API keys come
 * from wp-config.php constants or server environment variables only.
 */
final class FactFusion_Settings {

	const OPTION = 'factfusion_settings';

	/**
	 * The lowest anonymous fact-fetch limit the spec allows.
	 */
	const MIN_FACT_FETCH_PER_HOUR = 100;

	/**
	 * Default values.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults() {
		return array(
			'gemini_model'     => 'gemini-3.8-flash',
			'gemini_fallback'  => 'gemini-3.5-flash-lite',
			'rate_fact_hour'   => 100,
			'rate_ai_day'      => 10,
			'rate_ai_burst'    => 3,
			'rate_global_hour' => 5000,
			'fotd_pin'         => 0,
			'ip_header'        => '',
			'ga4_id'           => '',
		);
	}

	/**
	 * Model names that may be selected. No other name is accepted.
	 *
	 * Stable models with a free tier, as listed in Google's Gemini API model
	 * and pricing pages on 2026-10-02. gemini-2.0-flash was shut down on
	 * 2026-06-01 and the 2.5 models are closed to new projects, so neither
	 * is offered here.
	 *
	 * @return string[]
	 */
	public static function allowed_models() {
		return (array) apply_filters(
			'factfusion_allowed_models',
			array( 'gemini-3.8-flash', 'gemini-3.7-flash', 'gemini-3.6-flash', 'gemini-3.5-flash', 'gemini-3.5-flash-lite', 'gemini-3.1-flash-lite' )
		);
	}

	/**
	 * Reads a server-side value from a wp-config.php constant or the environment.
	 *
	 * @param string $name Constant or variable name.
	 * @return string Empty when not set.
	 */
	public static function env( $name ) {
		if ( defined( $name ) ) {
			return (string) constant( $name );
		}
		$value = getenv( $name );
		return false === $value ? '' : (string) $value;
	}

	/**
	 * Whether a server-side secret is configured. Never returns the secret.
	 *
	 * @param string $name Constant or variable name.
	 * @return bool
	 */
	public static function has_secret( $name ) {
		return '' !== self::env( $name );
	}

	/**
	 * All settings, with server-side overrides applied.
	 *
	 * @return array<string,mixed>
	 */
	public static function all() {
		$defaults = self::defaults();
		$settings = wp_parse_args( (array) get_option( self::OPTION, array() ), $defaults );
		$allowed  = self::allowed_models();

		// A stored model that is no longer offered falls back to the current default.
		foreach ( array( 'gemini_model', 'gemini_fallback' ) as $key ) {
			if ( ! in_array( $settings[ $key ], $allowed, true ) ) {
				$settings[ $key ] = $defaults[ $key ];
			}
		}

		$model = self::env( 'GEMINI_MODEL' );
		if ( in_array( $model, $allowed, true ) ) {
			$settings['gemini_model'] = $model;
		}
		$fallback = self::env( 'GEMINI_FALLBACK' );
		if ( in_array( $fallback, $allowed, true ) ) {
			$settings['gemini_fallback'] = $fallback;
		}
		return $settings;
	}

	/**
	 * One setting.
	 *
	 * @param string $key Setting key.
	 * @return mixed
	 */
	public static function get( $key ) {
		$settings = self::all();
		return $settings[ $key ] ?? null;
	}

	/**
	 * Validates and stores settings.
	 *
	 * @param array $data Raw field values.
	 * @return true|WP_Error
	 */
	public static function save( $data ) {
		$defaults = self::defaults();
		$allowed  = self::allowed_models();
		$errors   = new WP_Error();
		$clean    = array();

		foreach ( array( 'gemini_model', 'gemini_fallback' ) as $key ) {
			$value = sanitize_text_field( $data[ $key ] ?? $defaults[ $key ] );
			if ( ! in_array( $value, $allowed, true ) ) {
				$errors->add( 'ff_model', __( 'Choose a model from the list.', 'factfusion-ai' ) );
				$value = $defaults[ $key ];
			}
			$clean[ $key ] = $value;
		}

		$clean['rate_fact_hour']   = absint( $data['rate_fact_hour'] ?? $defaults['rate_fact_hour'] );
		$clean['rate_ai_day']      = max( 1, absint( $data['rate_ai_day'] ?? $defaults['rate_ai_day'] ) );
		$clean['rate_ai_burst']    = max( 1, absint( $data['rate_ai_burst'] ?? $defaults['rate_ai_burst'] ) );
		$clean['rate_global_hour'] = absint( $data['rate_global_hour'] ?? $defaults['rate_global_hour'] );

		if ( $clean['rate_fact_hour'] < self::MIN_FACT_FETCH_PER_HOUR ) {
			/* translators: %d: minimum requests per hour. */
			$errors->add( 'ff_rate', sprintf( __( 'The anonymous fact limit cannot go below %d per hour. Rate limiting is for abuse, not for reading.', 'factfusion-ai' ), self::MIN_FACT_FETCH_PER_HOUR ) );
		}
		if ( $clean['rate_global_hour'] < $clean['rate_fact_hour'] ) {
			$errors->add( 'ff_rate', __( 'The global ceiling cannot be lower than the per-visitor fact limit.', 'factfusion-ai' ) );
		}

		$pin = absint( $data['fotd_pin'] ?? 0 );
		if ( $pin ) {
			$fact = FactFusion_Facts::get( $pin );
			if ( ! $fact || 'published' !== $fact->status ) {
				$errors->add( 'ff_fotd', __( 'Only a published fact can be pinned as Fact of the Day.', 'factfusion-ai' ) );
			}
		}
		$clean['fotd_pin'] = $pin;

		$header             = sanitize_text_field( $data['ip_header'] ?? '' );
		$clean['ip_header'] = isset( FactFusion_Rate_Limiter::PROXY_HEADERS[ $header ] ) ? $header : '';

		$ga4 = strtoupper( trim( sanitize_text_field( $data['ga4_id'] ?? '' ) ) );
		if ( '' !== $ga4 && ! FactFusion_Analytics::is_valid_id( $ga4 ) ) {
			$errors->add( 'ff_ga4', __( 'A GA4 measurement ID looks like G-XXXXXXXXXX. Leave the box empty to keep analytics off.', 'factfusion-ai' ) );
			$ga4 = '';
		}
		$clean['ga4_id'] = $ga4;

		if ( $errors->has_errors() ) {
			return $errors;
		}

		update_option( self::OPTION, $clean, false );
		do_action( 'factfusion_facts_changed' );
		return true;
	}
}
