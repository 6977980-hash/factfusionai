<?php
/**
 * FactFusion AI theme functions.
 *
 * Presentation only. Facts, routing, REST and schema belong to the
 * factfusion-ai-core plugin.
 *
 * @package FactFusion_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'FACTFUSION_THEME_VERSION', '0.9.0' );

/**
 * Keeps raw shortcode tags off the page if the FactFusion AI Core plugin is
 * switched off. The plugin registers the real ones earlier on the same hook.
 */
function factfusion_theme_shortcode_fallbacks() {
	$tags = array( 'factfusion_generator', 'factfusion_fotd', 'factfusion_browse', 'factfusion_fact_actions', 'factfusion_fact_explanation', 'factfusion_fact_trust', 'factfusion_fact_related', 'factfusion_fact_embed', 'factfusion_fact_report', 'factfusion_breadcrumbs', 'factfusion_faq', 'factfusion_cookie_settings', 'factfusion_contact_form', 'factfusion_fact', 'factfusion_byline', 'factfusion_embed_code', 'factfusion_guides', 'factfusion_home_stats', 'factfusion_home_sources', 'factfusion_home_categories', 'factfusion_home_quiz', 'factfusion_home_latest', 'factfusion_home_steps', 'factfusion_home_embed', 'factfusion_footer_links' );
	foreach ( $tags as $tag ) {
		if ( ! shortcode_exists( $tag ) ) {
			add_shortcode( $tag, '__return_empty_string' );
		}
	}
}
add_action( 'init', 'factfusion_theme_shortcode_fallbacks', 99 );

/**
 * Loads the theme stylesheet inside the block editor.
 */
function factfusion_theme_setup() {
	add_editor_style( 'assets/css/theme.css' );
}
add_action( 'after_setup_theme', 'factfusion_theme_setup' );

/**
 * Enqueues front-end assets.
 */
function factfusion_theme_enqueue_assets() {
	wp_enqueue_style(
		'factfusion-ai-theme',
		get_theme_file_uri( 'assets/css/theme.css' ),
		array(),
		FACTFUSION_THEME_VERSION
	);
	// Lets WordPress inline the small stylesheet instead of a render-blocking request.
	wp_style_add_data( 'factfusion-ai-theme', 'path', get_theme_file_path( 'assets/css/theme.css' ) );

	// Homepage sections: only the front page needs these rules.
	if ( is_front_page() ) {
		wp_enqueue_style(
			'factfusion-ai-home',
			get_theme_file_uri( 'assets/css/home.css' ),
			array( 'factfusion-ai-theme' ),
			FACTFUSION_THEME_VERSION
		);
	}

	wp_enqueue_script(
		'factfusion-ai-theme-toggle',
		get_theme_file_uri( 'assets/js/theme-toggle.js' ),
		array(),
		FACTFUSION_THEME_VERSION,
		array(
			'in_footer' => true,
			'strategy'  => 'defer',
		)
	);
}
add_action( 'wp_enqueue_scripts', 'factfusion_theme_enqueue_assets' );

/**
 * Applies a saved light/dark choice before first paint to avoid a flash.
 */
function factfusion_theme_print_color_scheme_bootstrap() {
	wp_print_inline_script_tag(
		'document.documentElement.classList.add("ff-js");(function(){try{var t=localStorage.getItem("factfusion-theme");if("dark"===t||"light"===t){document.documentElement.setAttribute("data-theme",t);}}catch(e){}})();'
	);
}
add_action( 'wp_head', 'factfusion_theme_print_color_scheme_bootstrap', 0 );
