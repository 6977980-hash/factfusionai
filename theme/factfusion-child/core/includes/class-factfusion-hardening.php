<?php
/**
 * Small security and speed defaults for the public site.
 *
 * @package FactFusion_AI_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Closes the gaps found in the Milestone 5 audit: missing security headers,
 * the login name being discoverable, XML-RPC being open, and a script that
 * swapped emoji for images after the page had drawn.
 */
final class FactFusion_Hardening {

	/**
	 * Registers hooks.
	 */
	public static function init() {
		add_action( 'send_headers', array( __CLASS__, 'headers' ) );
		add_filter( 'rest_endpoints', array( __CLASS__, 'hide_users_endpoint' ) );
		add_action( 'template_redirect', array( __CLASS__, 'block_author_scan' ), 1 );
		add_filter( 'xmlrpc_enabled', '__return_false' );
		add_filter( 'xmlrpc_methods', '__return_empty_array' );
		add_filter( 'wp_headers', array( __CLASS__, 'remove_pingback_header' ) );
		add_filter( 'get_site_icon_url', array( __CLASS__, 'default_icon' ), 10, 2 );

		// Do not advertise the WordPress version or legacy editing endpoints.
		remove_action( 'wp_head', 'wp_generator' );
		remove_action( 'wp_head', 'rsd_link' );
		remove_action( 'wp_head', 'wlwmanifest_link' );

		// The emoji script replaces characters with images after first paint,
		// which costs requests and moves the page. Browsers draw emoji themselves.
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_action( 'wp_enqueue_scripts', 'wp_enqueue_emoji_styles' );
	}

	/**
	 * Sends security headers on public pages.
	 *
	 * @param WP $wp Current request.
	 */
	public static function headers( $wp ) {
		if ( headers_sent() ) {
			return;
		}
		$view = isset( $wp->query_vars[ FactFusion_Router::VIEW_VAR ] ) ? (string) $wp->query_vars[ FactFusion_Router::VIEW_VAR ] : '';

		header( 'X-Content-Type-Options: nosniff' );
		header( 'Referrer-Policy: strict-origin-when-cross-origin' );
		header( 'Permissions-Policy: geolocation=(), microphone=(), camera=()' );
		// The embed widget is meant to be framed by other sites; nothing else is.
		if ( 0 !== strpos( $view, 'embed_' ) ) {
			header( 'X-Frame-Options: SAMEORIGIN' );
		}
		if ( is_ssl() ) {
			header( 'Strict-Transport-Security: max-age=15552000' );
		}
	}

	/**
	 * Stops visitors who are not signed in from listing users, which reveals login names.
	 *
	 * @param array $endpoints REST endpoints.
	 * @return array
	 */
	public static function hide_users_endpoint( $endpoints ) {
		if ( is_user_logged_in() ) {
			return $endpoints;
		}
		foreach ( array_keys( $endpoints ) as $route ) {
			if ( 0 === strpos( $route, '/wp/v2/users' ) ) {
				unset( $endpoints[ $route ] );
			}
		}
		return $endpoints;
	}

	/**
	 * Answers "?author=1" style probes with a 404 instead of a redirect that
	 * spells out the login name. Public profiles live at /author/{slug}/ instead.
	 */
	public static function block_author_scan() {
		global $wp_query;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only check of the URL.
		if ( ( is_author() || isset( $_GET['author'] ) ) && ! is_user_logged_in() ) {
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
		}
	}

	/**
	 * Removes the pingback address from response headers.
	 *
	 * @param array $headers Response headers.
	 * @return array
	 */
	public static function remove_pingback_header( $headers ) {
		unset( $headers['X-Pingback'] );
		return $headers;
	}

	/**
	 * Supplies the bundled icon until a Site Icon is chosen under Settings → General.
	 *
	 * @param string $url  Site icon URL, empty when none is set.
	 * @param int    $size Requested size.
	 * @return string
	 */
	public static function default_icon( $url, $size ) {
		if ( $url ) {
			return $url;
		}
		// New file names, so browsers and search engines do not keep the old icon.
		// The old icon-*.png files stay in the plugin for anyone who linked them.
		return FACTFUSION_URL . ( $size > 192 ? 'assets/logo-512.png' : 'assets/logo-192.png' );
	}
}
