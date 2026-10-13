<?php
/**
 * AdSense compliance: privacy policy wording, ads.txt and the AdSense site code.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Publisher ID from AdSense (Account → Settings → Account information), e.g. 'pub-1234567890123456'.
 * Leave empty until the account exists: ads.txt and the AdSense script stay off while it is empty.
 */
const FFC_ADSENSE_PUB = '';

const FFC_PRIVACY_OLD = 'We do not use advertising cookies.';
const FFC_PRIVACY_NEW = 'We use Google AdSense to show ads. Google and its partners use cookies to show ads based on your visits to this and other websites. Google\'s use of advertising cookies lets it and its partners serve ads based on your visits to our site and/or other sites on the Internet. You can opt out of personalised advertising at <a href="https://adssettings.google.com">Google Ads Settings</a>, or opt out of some third-party vendors\' cookies at <a href="https://www.aboutads.info/choices/">aboutads.info</a>. Visitors in the EEA, the UK and Switzerland are asked for consent before personalised ads are shown. Read <a href="https://policies.google.com/technologies/partner-sites">how Google uses information from sites that use its services</a>.';

/**
 * One-time update of the live Privacy Policy page. Only the old sentence is swapped,
 * so any other edits made in WordPress are kept. Runs again only if the sentence comes back.
 */
add_action(
	'init',
	static function () {
		if ( get_option( 'ffc_privacy_adsense' ) ) {
			return;
		}
		$page = get_page_by_path( 'privacy-policy' );
		if ( ! $page ) {
			return;
		}
		if ( str_contains( $page->post_content, FFC_PRIVACY_OLD ) ) {
			wp_update_post(
				array(
					'ID'           => $page->ID,
					'post_content' => str_replace( FFC_PRIVACY_OLD, FFC_PRIVACY_NEW, $page->post_content ),
				)
			);
		}
		update_option( 'ffc_privacy_adsense', 1 );
	},
	20
);

// /ads.txt served by WordPress (no file on the server needed).
add_action(
	'parse_request',
	static function () {
		$path = trim( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( 'ads.txt' !== $path || ! FFC_ADSENSE_PUB ) {
			return;
		}
		header( 'Content-Type: text/plain; charset=utf-8' );
		echo 'google.com, ' . esc_html( FFC_ADSENSE_PUB ) . ", DIRECT, f08c47fec0942fa0\n";
		exit;
	},
	0
);

// AdSense site code in <head>: needed for site review, and runs Auto ads after approval.
add_action(
	'wp_head',
	static function () {
		if ( ! FFC_ADSENSE_PUB || is_404() ) {
			return;
		}
		printf(
			'<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-%s" crossorigin="anonymous"></script>' . "\n",
			esc_attr( FFC_ADSENSE_PUB )
		);
	},
	5
);
