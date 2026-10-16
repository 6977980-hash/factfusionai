<?php
/**
 * Site contact email: shown on the trust pages and in the Organization structured data.
 *
 * - The live About, How We Verify Facts, Contact, Privacy Policy and Terms pages get the
 *   address next to each "contact page" link, once. Other edits made in WordPress stay.
 * - The Organization node in the JSON-LD gets email and contactPoint (inc/seo-head.php).
 */

defined( 'ABSPATH' ) || exit;

const FFC_CONTACT_EMAIL = 'contact@factfusionai.com';

/** Email link HTML. */
function ffc_contact_email_link() {
	return '<a href="mailto:' . FFC_CONTACT_EMAIL . '">' . FFC_CONTACT_EMAIL . '</a>';
}

/**
 * Adds the email to stored page text. Pages that already show it are left alone.
 *
 * @param string $slug    Page slug.
 * @param string $content Page content.
 * @return string
 */
function ffc_contact_email_add( $slug, $content ) {
	if ( false !== strpos( $content, FFC_CONTACT_EMAIL ) ) {
		return $content;
	}
	$link = ffc_contact_email_link();
	if ( 'contact' === $slug ) {
		$new = str_replace(
			'Use the form below and your message will go to the editor.',
			'Use the form below and your message will go to the editor, or email us at ' . $link . '.',
			$content
		);
		if ( $new === $content ) {
			$new = $content . "\n\n<!-- wp:paragraph -->\n<p>You can also email us at " . $link . ".</p>\n<!-- /wp:paragraph -->\n";
		}
		return $new;
	}
	$content = preg_replace( '#(<a href="[^"]*">contact page</a>) with:#', '$1 or by email to ' . $link . ' with:', $content );
	return (string) preg_replace( '#(<a href="[^"]*">contact page</a>)\.#', '$1 or email us at ' . $link . '.', $content );
}

add_action(
	'init',
	static function () {
		if ( get_option( 'ffc_contact_email_v1' ) ) {
			return;
		}
		update_option( 'ffc_contact_email_v1', 1, false );
		foreach ( array( 'contact', 'about', 'how-we-verify-facts', 'privacy-policy', 'terms' ) as $slug ) {
			$page = get_page_by_path( $slug );
			if ( ! $page ) {
				continue;
			}
			$content = ffc_contact_email_add( $slug, $page->post_content );
			if ( $content !== $page->post_content ) {
				wp_update_post(
					wp_slash(
						array(
							'ID'           => $page->ID,
							'post_content' => $content,
						)
					)
				);
			}
		}
	},
	20
);
