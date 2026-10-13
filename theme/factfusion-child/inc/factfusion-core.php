<?php
/**
 * Runs the FactFusion fact engine from the theme.
 *
 * The code in core/ is the FactFusion AI Core plugin (0.9.0) moved into the
 * theme. Facts live in the site's database (wp_ff_* tables), so they stay
 * when the plugin is deleted. While the plugin is still active it loads
 * first and this file does nothing; once the plugin is deactivated, the
 * theme takes over with the same tables, URLs and shortcodes.
 */

defined( 'ABSPATH' ) || exit;

if ( defined( 'FACTFUSION_VERSION' ) ) {
	return;
}

define( 'FACTFUSION_VERSION', '0.9.0' );
define( 'FACTFUSION_FILE', __FILE__ );
define( 'FACTFUSION_DIR', get_stylesheet_directory() . '/core/' );
define( 'FACTFUSION_URL', get_stylesheet_directory_uri() . '/core/' );

foreach ( array( 'db', 'categories', 'sources', 'facts', 'corrections', 'settings', 'importer', 'rate-limiter', 'ai', 'frontend', 'router', 'rest', 'profiles', 'seo', 'pages', 'content', 'home', 'analytics', 'hardening' ) as $ffc_class ) {
	require_once FACTFUSION_DIR . 'includes/class-factfusion-' . $ffc_class . '.php';
}

if ( ! function_exists( 'factfusion_capability' ) ) {
	/**
	 * Capability required to manage facts.
	 *
	 * @return string
	 */
	function factfusion_capability() {
		return (string) apply_filters( 'factfusion_capability', 'manage_options' );
	}
}

/**
 * Same setup the plugin ran on activation: tables, default categories, URLs.
 * Safe to repeat; existing data is never touched.
 */
function factfusion_child_core_activate() {
	FactFusion_DB::install();
	FactFusion_Categories::seed_defaults();
	FactFusion_Router::add_rewrite_rules();
	flush_rewrite_rules();
	update_option( 'factfusion_rules_version', FactFusion_Router::RULES_VERSION );
}
add_action( 'after_switch_theme', 'factfusion_child_core_activate' );

// plugins_loaded has already fired when a theme loads, so boot straight away.
FactFusion_DB::maybe_upgrade();
FactFusion_Router::init();
FactFusion_Frontend::init();
FactFusion_REST::init();
FactFusion_SEO::init();
FactFusion_Profiles::init();
FactFusion_Pages::init();
FactFusion_Content::init();
FactFusion_Home::init();
FactFusion_Analytics::init();
FactFusion_Hardening::init();

add_action( 'factfusion_daily_cleanup', array( 'FactFusion_Rate_Limiter', 'cleanup' ) );
add_action(
	'init',
	static function () {
		if ( ! wp_next_scheduled( 'factfusion_daily_cleanup' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'factfusion_daily_cleanup' );
		}
	}
);

if ( is_admin() ) {
	require_once FACTFUSION_DIR . 'admin/class-factfusion-admin.php';
	FactFusion_Admin::init();
}
