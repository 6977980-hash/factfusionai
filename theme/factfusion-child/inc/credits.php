<?php
/**
 * Credits and licences on the "How we verify facts" page: the science-facts-project dataset
 * (MIT) many claims started from, and the licences of the sources we quote (Wikipedia is
 * CC BY-SA). The page text was created once from a starter file, so the section is added here
 * instead of by editing the page; a page that already names the dataset is left alone.
 */

defined( 'ABSPATH' ) || exit;

const FFC_CREDITS_DATASET_URL = 'https://github.com/Royal-lobster/science-facts-project';
const FFC_CREDITS_MIT_URL     = 'https://opensource.org/license/mit';
const FFC_CREDITS_BYSA_URL    = 'https://creativecommons.org/licenses/by-sa/4.0/';

/** The "Credits and licences" section as block HTML. */
function ffc_credits_section() {
	return '<h2 class="wp-block-heading" id="credits">' . esc_html__( 'Credits and licences', 'factfusion-ai' ) . '</h2>'
		. '<p>' . sprintf(
			/* translators: 1: dataset link, 2: licence link. */
			esc_html__( 'Fact ideas: many of our science claims started from the open %1$s dataset by Royal-lobster, used under the %2$s. Each one was checked against its source before it was published, and reworded where the source says less.', 'factfusion-ai' ),
			'<a href="' . esc_url( FFC_CREDITS_DATASET_URL ) . '" rel="noopener">science-facts-project</a>',
			'<a href="' . esc_url( FFC_CREDITS_MIT_URL ) . '" rel="license noopener">MIT License</a>'
		) . '</p>'
		. '<p>' . sprintf(
			/* translators: %s: CC BY-SA licence link. */
			esc_html__( 'Sources: every fact names its source and links to it. Lines we quote from Wikipedia are used under %s, and each quote links to the article it comes from. Other sources belong to their publishers and keep their own licences.', 'factfusion-ai' ),
			'<a href="' . esc_url( FFC_CREDITS_BYSA_URL ) . '" rel="license noopener">CC BY-SA 4.0</a>'
		) . '</p>';
}

add_filter(
	'the_content',
	static function ( $content ) {
		if ( ! is_page( 'how-we-verify-facts' ) || doing_filter( 'get_the_excerpt' ) ) {
			return $content;
		}
		$post = get_post();
		if ( ! $post || (int) $post->ID !== get_queried_object_id() || false !== stripos( $content, 'science-facts-project' ) ) {
			return $content;
		}
		return $content . ffc_credits_section();
	},
	20
);
