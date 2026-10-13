<?php
/**
 * Small site fixes.
 */

defined( 'ABSPATH' ) || exit;

/**
 * The footer links are cached. A copy built before the site moved to its own domain
 * still pointed at the temporary hostingersite.com address. Ignore any cached copy
 * that links to a different host than the site, so it is rebuilt with the right one.
 */
add_filter(
	'transient_factfusion_footer_links',
	static function ( $value ) {
		$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		if ( is_string( $value ) && preg_match_all( '#href="https?://([^/"]+)#', $value, $m ) ) {
			foreach ( $m[1] as $link_host ) {
				if ( strtolower( $link_host ) !== strtolower( $host ) ) {
					return false;
				}
			}
		}
		return $value;
	}
);
