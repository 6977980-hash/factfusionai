<?php
/**
 * SEO audit, phase 2: page fixes.
 *
 * - /facts/page/999/ and other listing pages past the end return 404, not an empty page.
 * - WordPress search (?s=) did not search facts. It now goes to the facts search (/facts/?q=).
 * - Fact titles in listings are H3, so a listing page no longer has 20+ H2 headings.
 * - The theme stylesheet is inlined instead of blocking the first paint, and the two empty
 *   stylesheets are no longer loaded.
 * - /fact-of-the-day/ is a hub page: today's fact in short with a link to its own page, and an
 *   archive of earlier facts of the day. It no longer repeats a whole fact page every day.
 */

defined( 'ABSPATH' ) || exit;

/*
 * Listing pages past the last page are not found.
 */
add_filter(
	'pre_handle_404',
	static function ( $handled, $wp_query ) {
		if ( 'browse' !== FactFusion_Router::view() ) {
			return $handled;
		}
		$browse = FactFusion_Router::browse();
		if ( (int) $browse['page'] <= 1 ) {
			return $handled;
		}
		list( , $pages ) = ffc_browse_pages( $browse );
		if ( (int) $browse['page'] > $pages ) {
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
			return true;
		}
		return $handled;
	},
	20,
	2
);

/*
 * WordPress search goes to the facts search, which is the search people expect here.
 */
add_action(
	'template_redirect',
	static function () {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public search.
		if ( is_admin() || ! is_search() || ! isset( $_GET['s'] ) || is_feed() ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$search = mb_substr( trim( sanitize_text_field( wp_unslash( $_GET['s'] ) ) ), 0, 100 );
		wp_safe_redirect( '' === $search ? FactFusion_Router::browse_url() : FactFusion_Router::browse_url( null, 1, $search ), 301 );
		exit;
	},
	1
);

/*
 * Fact titles in listings: H3 under the page's own H2 sections.
 */
add_filter(
	'do_shortcode_tag',
	static function ( $output, $tag ) {
		if ( 'factfusion_browse' === $tag && is_string( $output ) ) {
			$output = preg_replace( '#<h2 class="ff-fact-list__heading">(.*?)</h2>#s', '<h3 class="ff-fact-list__heading">$1</h3>', $output );
		}
		return $output;
	},
	10,
	2
);

/*
 * Stylesheets. theme.css was 131 bytes over WordPress's 20 KB limit for inlining, so it blocked
 * the first paint. Inline it, and the homepage stylesheet, on every page that uses them.
 */
add_filter(
	'styles_inline_size_limit',
	static function () {
		return 60000;
	}
);

add_action(
	'wp_enqueue_scripts',
	static function () {
		$home = get_template_directory() . '/assets/css/home.css';
		if ( wp_style_is( 'factfusion-ai-home', 'enqueued' ) && is_readable( $home ) ) {
			wp_style_add_data( 'factfusion-ai-home', 'path', $home );
		}
	},
	20
);

/*
 * Fact of the Day hub.
 */
add_filter(
	'index_template_hierarchy',
	static function ( $templates ) {
		if ( 'fotd' === FactFusion_Router::view() ) {
			array_unshift( $templates, 'fact-of-the-day.php' );
		}
		return $templates;
	},
	20
);

add_action(
	'init',
	static function () {
		add_shortcode( 'ffc_fotd_hub', 'ffc_fotd_hub' );
	}
);

function ffc_fotd_hub() {
	$fact = ffc_fotd_today();
	if ( ! $fact ) {
		return '<p>' . esc_html__( 'A new verified fact will be featured here every day.', 'factfusion-child' ) . '</p>';
	}
	FactFusion_Frontend::cache_until_midnight();

	$url      = FactFusion_Facts::url( $fact );
	$category = $fact->category_id ? FactFusion_Categories::get( $fact->category_id ) : null;
	$sources  = FactFusion_Sources::for_fact( $fact->id );

	$html  = '<p class="ff-kicker">' . esc_html( sprintf( 'Fact of the Day · %s', wp_date( get_option( 'date_format' ) ) ) ) . '</p>';
	$html .= '<p>' . esc_html__( 'One fact a day, with its source. A new one appears every day at midnight.', 'factfusion-child' ) . '</p>';
	$html .= '<section class="ff-card ff-fotd-hub">';
	if ( $category ) {
		$html .= '<p class="ff-pill">' . esc_html( $category->name ) . '</p>';
	}
	$html .= sprintf( '<h2 class="wp-block-heading">%s</h2>', esc_html( $fact->title ) );
	$html .= '<p class="ff-fact__answer">' . esc_html( '' !== trim( $fact->short_answer ) ? $fact->short_answer : $fact->claim ) . '</p>';
	if ( $sources ) {
		$html .= sprintf(
			'<p class="ff-meta">Source: <a href="%1$s">%2$s</a> <span class="ff-pill ff-tier ff-tier--%3$s">Tier %4$s</span></p>',
			esc_url( $sources[0]->url ),
			esc_html( $sources[0]->source_name ),
			esc_attr( strtolower( $sources[0]->tier ) ),
			esc_html( $sources[0]->tier )
		);
	}
	$html .= sprintf( '<p><a class="wp-element-button" href="%1$s">%2$s</a></p>', esc_url( $url ), esc_html__( 'Read the full fact and how it was checked', 'factfusion-child' ) );
	$html .= '</section>';

	$items = array();
	foreach ( (array) get_option( 'ffc_fotd_log', array() ) as $day => $id ) {
		if ( wp_date( 'Y-m-d' ) === $day || count( $items ) >= 30 ) {
			continue;
		}
		$old = FactFusion_Facts::get( (int) $id );
		if ( $old && 'published' === $old->status ) {
			$items[] = sprintf(
				'<li class="ff-card"><p class="ff-meta"><time datetime="%1$s">%2$s</time></p><h3 class="ff-fact-list__heading"><a class="ff-fact-list__title" href="%3$s">%4$s</a></h3><p>%5$s</p></li>',
				esc_attr( $day ),
				esc_html( wp_date( get_option( 'date_format' ), strtotime( $day . ' 12:00:00' ) ) ),
				esc_url( FactFusion_Facts::url( $old ) ),
				esc_html( $old->title ),
				esc_html( $old->claim )
			);
		}
	}
	if ( $items ) {
		$html .= '<section class="ff-section"><h2 class="wp-block-heading">' . esc_html__( 'Earlier facts of the day', 'factfusion-child' ) . '</h2><ul class="ff-fact-list">' . implode( '', $items ) . '</ul></section>';
	}
	$html .= sprintf(
		'<p><a href="%1$s">%2$s</a> · <a href="%3$s">%4$s</a></p>',
		esc_url( home_url( '/' ) ),
		esc_html__( 'Get a random fact', 'factfusion-child' ),
		esc_url( FactFusion_Router::browse_url() ),
		esc_html__( 'Browse all facts', 'factfusion-child' )
	);
	return $html;
}
