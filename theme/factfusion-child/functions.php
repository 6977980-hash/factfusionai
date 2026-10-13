<?php
/**
 * FactFusion Child theme functions. Put all site fixes and custom code here (or in files under inc/).
 */

defined( 'ABSPATH' ) || exit;

// The parent and child style.css files hold only the theme headers, so they are not loaded:
// two empty stylesheets would only delay the first paint. Real styles live in the parent's
// assets/css/theme.css, which it loads itself.

// Load every PHP file in inc/ so fixes can be split into small files.
foreach ( glob( __DIR__ . '/inc/*.php' ) as $ffc_file ) {
	require_once $ffc_file;
}
