<?php
/**
 * Clear the whole server page cache once after every deploy.
 *
 * The deploy plugin only fires the LiteSpeed Cache plugin's purge action, which does
 * nothing when that plugin is not installed. Hostinger's server cache kept serving
 * pages from before the trust and SEO fixes (old homepage FAQ, old robots.txt,
 * old sitemap). The X-LiteSpeed-Purge header clears it without the plugin.
 *
 * @package factfusion-child
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'init',
	static function () {
		$sig = (string) get_option( 'ffd_source_signature', '' ); // Set by the deploy plugin after each theme sync.
		if ( '' === $sig || get_option( 'ffc_purged_sig' ) === $sig || headers_sent() ) {
			return;
		}
		update_option( 'ffc_purged_sig', $sig, true );
		do_action( 'litespeed_purge_all' );
		header( 'X-LiteSpeed-Purge: *', false );
	},
	2
);
