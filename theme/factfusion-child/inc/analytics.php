<?php
/**
 * Google Analytics 4 measurement ID for the site.
 *
 * The core engine (FactFusion_Analytics) reads GA4_MEASUREMENT_ID and loads the
 * official gtag.js on every page, but only after a visitor clicks "Accept analytics"
 * in the cookie notice, as the Privacy Policy promises. Signed-in editors are never tracked.
 * A GA4_MEASUREMENT_ID set in wp-config.php still wins over this one.
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'GA4_MEASUREMENT_ID' ) ) {
	define( 'GA4_MEASUREMENT_ID', 'G-8WJ48K5TWQ' );
}
