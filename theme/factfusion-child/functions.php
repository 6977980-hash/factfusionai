<?php
/**
 * FactFusion Child theme functions. Put all site fixes and custom code here (or in files under inc/).
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_enqueue_scripts', function () {
	wp_enqueue_style( 'factfusion-parent', get_template_directory_uri() . '/style.css', array(), wp_get_theme( get_template() )->get( 'Version' ) );
	wp_enqueue_style( 'factfusion-child', get_stylesheet_uri(), array( 'factfusion-parent' ), wp_get_theme()->get( 'Version' ) );
} );

// Load every PHP file in inc/ so fixes can be split into small files.
foreach ( glob( __DIR__ . '/inc/*.php' ) as $ffc_file ) {
	require_once $ffc_file;
}
