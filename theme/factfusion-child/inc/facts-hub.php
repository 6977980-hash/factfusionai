<?php
/**
 * /facts/ as a real hub page (2026-11 Search Console audit).
 *
 * Search Console listed /facts/ as "Crawled - currently not indexed": the page was a search box,
 * category chips and a list of fact cards, with no text of its own. On the first page of the
 * unfiltered list (no category, no search) this adds a short introduction, and below the list
 * a "Browse by topic" section with every category and its fact count, the topic hubs and the
 * myth pages. Fact text is not touched; counts come from the database at render time.
 */

defined( 'ABSPATH' ) || exit;

/** Whether this is page 1 of /facts/ with no category and no search. */
function ffc_facts_hub_is_main() {
	if ( ! class_exists( 'FactFusion_Router' ) || 'browse' !== FactFusion_Router::view() ) {
		return false;
	}
	$view = FactFusion_Router::browse();
	return $view && ! $view['category'] && '' === (string) $view['search'] && 1 === (int) $view['page'];
}

/** The introduction shown above the search box. */
function ffc_facts_hub_intro() {
	$counts     = FactFusion_Facts::published_counts();
	$total      = array_sum( array_map( 'intval', $counts ) );
	$categories = count( array_filter( $counts ) );
	$verify     = home_url( user_trailingslashit( 'how-we-verify-facts' ) );

	$html  = '<section class="ff-facts-hub__intro">';
	$html .= '<p>' . esc_html(
		sprintf(
			/* translators: 1: number of facts, 2: number of categories. */
			__( 'This is the full FactFusion AI library: %1$d facts in %2$d categories, from space and the ocean to the human body and history. Each fact has its own page with a short answer, a plain-English explanation and a link to the source it was checked against.', 'factfusion-child' ),
			$total,
			$categories
		)
	) . '</p>';
	$html .= '<p>' . sprintf(
		/* translators: %s: link to the verification page. */
		esc_html__( 'Search for a word, pick a category, or scroll the newest facts below. Sources are mostly public agencies and reference works such as NASA, NOAA, the National Park Service and the NIH. %s', 'factfusion-child' ),
		'<a href="' . esc_url( $verify ) . '">' . esc_html__( 'How we check each fact', 'factfusion-child' ) . '</a>'
	) . '</p>';
	return $html . '</section>';
}

/** Categories with counts, topic hubs and myth pages, shown below the list. */
function ffc_facts_hub_topics() {
	$counts = FactFusion_Facts::published_counts();
	$cats   = array();
	foreach ( FactFusion_Categories::all( true ) as $row ) {
		$n = (int) ( $counts[ (int) $row->id ] ?? 0 );
		if ( ! $n ) {
			continue;
		}
		$cats[] = sprintf(
			'<li><a href="%1$s">%2$s</a> <span class="ff-meta">%3$s</span></li>',
			esc_url( FactFusion_Router::browse_url( $row ) ),
			esc_html( sprintf( /* translators: %s: category name. */ __( '%s facts', 'factfusion-child' ), $row->name ) ),
			esc_html( sprintf( _n( '%d fact', '%d facts', $n, 'factfusion-child' ), $n ) )
		);
	}

	$hubs  = array();
	$myths = array();
	if ( function_exists( 'ffc_depth_live_pages' ) ) {
		foreach ( ffc_depth_live_pages() as $def_page ) {
			list( $def, $page ) = $def_page;
			$link = sprintf( '<li><a href="%s">%s</a></li>', esc_url( get_permalink( $page ) ), esc_html( get_the_title( $page ) ) );
			if ( 'hub' === $def['kind'] ) {
				$hubs[] = $link;
			} elseif ( 'myth' === $def['kind'] ) {
				$myths[] = $link;
			}
		}
	}

	if ( ! $cats && ! $hubs && ! $myths ) {
		return '';
	}
	$html = '<section class="ff-section ff-facts-hub__topics"><h2 class="wp-block-heading">' . esc_html__( 'Browse facts by topic', 'factfusion-child' ) . '</h2>';
	if ( $cats ) {
		$html .= '<h3 class="ff-guides__label">' . esc_html__( 'Categories', 'factfusion-child' ) . '</h3><ul class="ff-guides__list">' . implode( '', $cats ) . '</ul>';
	}
	if ( $hubs ) {
		$html .= '<h3 class="ff-guides__label">' . esc_html__( 'Topic guides', 'factfusion-child' ) . '</h3><ul class="ff-guides__list">' . implode( '', $hubs ) . '</ul>';
	}
	if ( $myths ) {
		$html .= '<h3 class="ff-guides__label">' . esc_html__( 'Myths checked against the source', 'factfusion-child' ) . '</h3><ul class="ff-guides__list">' . implode( '', $myths ) . '</ul>';
	}
	return $html . '</section>';
}

add_filter(
	'do_shortcode_tag',
	static function ( $output, $tag ) {
		if ( 'factfusion_browse' !== $tag || ! is_string( $output ) || '' === $output || ! ffc_facts_hub_is_main() ) {
			return $output;
		}
		return ffc_facts_hub_intro() . $output . ffc_facts_hub_topics();
	},
	20,
	2
);

add_action(
	'wp_enqueue_scripts',
	static function () {
		wp_register_style( 'ffc-facts-hub', false, array(), '1' );
		wp_enqueue_style( 'ffc-facts-hub' );
		wp_add_inline_style( 'ffc-facts-hub', '.ff-facts-hub__intro p{margin:0 0 .75rem}.ff-facts-hub__topics{margin-top:2rem}.ff-facts-hub__topics .ff-meta{margin-left:.25rem}' );
	},
	20
);
