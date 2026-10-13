<?php
/**
 * SEO audit, phase 2: what search engines read in the page head, sitemaps, robots.txt,
 * llms.txt and the RSS feed.
 *
 * - Meta descriptions are whole sentences instead of text cut off with "…", and listing
 *   pages after the first say which page they are.
 * - One title separator ("|") on every page, and no brand name twice (About page).
 * - Fact pages: real publish and modified dates, a share image of their own, the author in
 *   structured data is the site (the editor stays as reviewer), and the DefinedTerm entry,
 *   which was the wrong type for a fact, is gone. Guides get an image in their Article data.
 * - Facts sitemap: lastmod is the fact's modified date; the homepage (already in Rank Math's
 *   page sitemap) is left out, and so are editor pages with no published facts.
 * - robots.txt has one group for every crawler and names the AI search crawlers; llms.txt
 *   describes the site; the RSS feed lists the newest facts and no WordPress version.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Description from whole sentences that fit. Tries each text in turn, then a first sentence
 * slightly over the limit; only when nothing else works is the shortest text cut.
 *
 * @param string[] $texts Candidate texts, best first.
 * @param int      $max   Maximum characters.
 * @return string
 */
function ffc_describe( $texts, $max = 160 ) {
	$clean = array();
	foreach ( (array) $texts as $text ) {
		$text = trim( preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( (string) $text ), ENT_QUOTES, 'UTF-8' ) ) );
		if ( '' === $text ) {
			continue;
		}
		$clean[] = $text;
		$out     = '';
		foreach ( preg_split( '/(?<=[.!?])\s+(?=[\p{Lu}0-9"“(])/u', $text ) as $sentence ) {
			$try = '' === $out ? $sentence : $out . ' ' . $sentence;
			if ( mb_strlen( $try ) > $max ) {
				break;
			}
			$out = $try;
		}
		if ( '' !== $out ) {
			return preg_match( '/[.!?]$/u', $out ) ? $out : $out . '.';
		}
	}
	if ( ! $clean ) {
		return '';
	}
	// A whole sentence a little over the limit reads better than one cut in the middle.
	foreach ( $clean as $text ) {
		$first = preg_split( '/(?<=[.!?])\s+(?=[\p{Lu}0-9"“(])/u', $text )[0];
		if ( mb_strlen( $first ) <= $max + 40 ) {
			return preg_match( '/[.!?]$/u', $first ) ? $first : $first . '.';
		}
	}
	usort(
		$clean,
		static function ( $a, $b ) {
			return mb_strlen( $a ) - mb_strlen( $b );
		}
	);
	$cut = mb_substr( $clean[0], 0, $max - 1 );
	foreach ( array( '; ', ', ', ' – ', ' — ' ) as $mark ) {
		$at = mb_strrpos( $cut, $mark );
		if ( false !== $at && $at > $max * 0.55 ) {
			return rtrim( mb_substr( $cut, 0, $at ), ' ,;:' ) . '.';
		}
	}
	return FactFusion_SEO::clip( $clean[0], $max );
}

/** Published facts in this listing and how many pages it has. */
function ffc_browse_pages( $browse ) {
	if ( '' !== $browse['search'] ) {
		$total = FactFusion_Facts::search_published( $browse['search'], 1 )['total'];
	} else {
		$counts = FactFusion_Facts::published_counts();
		$total  = $browse['category'] ? (int) ( $counts[ (int) $browse['category']->id ] ?? 0 ) : count( FactFusion_Facts::pool() );
	}
	return array( $total, max( 1, (int) ceil( $total / FactFusion_Frontend::BROWSE_PER_PAGE ) ) );
}

add_filter(
	'factfusion_seo_meta',
	static function ( $meta, $view ) {
		switch ( $view ) {
			case 'fact':
				$fact                = FactFusion_Router::current_fact();
				$meta['description'] = ffc_describe( array( $fact->short_answer, $fact->claim ) );
				break;

			case 'fotd':
				$fact                = FactFusion_Router::current_fact();
				$lead                = 'A new fact with its source every day. Today: ';
				$meta['description'] = $lead . ffc_describe( array( $fact->claim, $fact->title ), 160 - mb_strlen( $lead ) );
				break;

			case 'browse':
				$browse = FactFusion_Router::browse();
				if ( '' !== $browse['search'] ) {
					break;
				}
				list( $total, $pages ) = ffc_browse_pages( $browse );
				$category              = $browse['category'];
				$page                  = (int) $browse['page'];
				$suffix                = $page > 1 ? sprintf( ' Page %d of %d.', $page, $pages ) : '';
				if ( $category ) {
					$texts = array(
						'' !== $category->meta_description ? $category->meta_description : '',
						sprintf( '%d verified %s facts, each with a link to its source. %s', $total, $category->name, $category->description ),
					);
				} else {
					$texts = array( $meta['description'] );
				}
				$meta['description'] = ffc_describe( $texts, 160 - mb_strlen( $suffix ) ) . $suffix;
				break;

			case 'author':
				$profile = FactFusion_Router::profile();
				$bio     = (string) get_user_meta( $profile->ID, 'description', true );
				if ( '' !== trim( $bio ) ) {
					$meta['description'] = ffc_describe( array( $bio ) );
				}
				break;

			case 'article':
			case 'tool':
				$content             = FactFusion_Content::current();
				$description         = trim( (string) get_post_meta( $content->ID, FactFusion_Content::DESCRIPTION_META, true ) );
				$meta['description'] = ffc_describe( array( $description, strip_shortcodes( $content->post_content ) ) );
				break;
		}
		return $meta;
	},
	10,
	2
);

/*
 * One title separator. Rank Math (ordinary pages) used " - ", the fact pages " | ".
 * "About FactFusion AI - FactFusion AI" becomes "About FactFusion AI".
 */
function ffc_title( $title ) {
	$brand = get_bloginfo( 'name' );
	if ( '' === $brand || ! is_string( $title ) ) {
		return $title;
	}
	$title  = preg_replace( '/\s+[-–—|]\s+' . preg_quote( $brand, '/' ) . '$/u', ' | ' . $brand, $title );
	$suffix = ' | ' . $brand;
	if ( str_ends_with( $title, $suffix ) ) {
		$head = substr( $title, 0, -strlen( $suffix ) );
		if ( false !== stripos( $head, $brand ) ) {
			$title = $head;
		}
	}
	return $title;
}
add_filter( 'rank_math/frontend/title', 'ffc_title', 99 );
add_filter( 'rank_math/opengraph/facebook/og_title', 'ffc_title', 99 );
add_filter( 'rank_math/opengraph/twitter/title', 'ffc_title', 99 );
add_filter( 'document_title', 'ffc_title', 99 );
add_filter(
	'document_title_separator',
	static function () {
		return '|';
	}
);

/*
 * The core plugin prints the head tags itself. Take its output and correct it.
 */
add_action(
	'init',
	static function () {
		if ( remove_action( 'wp_head', array( 'FactFusion_SEO', 'head' ), 1 ) ) {
			add_action( 'wp_head', 'ffc_seo_head', 1 );
		}
		if ( remove_filter( 'rank_math/sitemap/index', array( 'FactFusion_SEO', 'rank_math_index' ) ) ) {
			add_filter( 'rank_math/sitemap/index', 'ffc_rank_math_index' );
		}
	},
	20
);

function ffc_seo_head() {
	ob_start();
	FactFusion_SEO::head();
	$html = (string) ob_get_clean();
	$view = FactFusion_SEO::view();
	$fact = in_array( $view, array( 'fact', 'fotd' ), true ) ? FactFusion_Router::current_fact() : null;
	$img  = '';

	if ( 'fact' === $view && $fact ) {
		list( $published, $modified ) = ffc_fact_dates( $fact );
		$html = preg_replace( '/(<meta property="article:published_time" content=")[^"]*/', '${1}' . ffc_iso( $published ), $html );
		$html = preg_replace( '/(<meta property="article:modified_time" content=")[^"]*/', '${1}' . ffc_iso( $modified ), $html );
		$img  = ffc_og_image_url( $fact );
		if ( '' !== $img ) {
			$html = preg_replace( '/(<meta property="og:image" content=")[^"]*/', '${1}' . esc_url( $img ), $html );
			$html = str_replace( '<meta name="twitter:card" content="summary_large_image">', '<meta name="twitter:card" content="summary_large_image">' . "\n" . '<meta name="twitter:image" content="' . esc_url( $img ) . '">', $html );
		}
	}

	if ( preg_match( '#<script type="application/ld\+json">(.*?)</script>#s', $html, $match ) ) {
		$data = json_decode( $match[1], true );
		if ( is_array( $data ) && isset( $data['@graph'] ) ) {
			$data['@graph'] = ffc_schema_graph( $data['@graph'], $view, $fact, $img );
			$json           = wp_json_encode( $data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
			if ( $json ) {
				$html = str_replace( $match[0], '<script type="application/ld+json">' . $json . '</script>', $html );
			}
		}
	}
	echo $html; // phpcs:ignore WordPress.Security.EscapeOutput -- built and escaped by FactFusion_SEO::head().
}

/**
 * Corrects the JSON-LD graph.
 *
 * @param array       $graph Nodes.
 * @param string      $view  Page type.
 * @param object|null $fact  Fact on fact and Fact of the Day pages.
 * @param string      $img   Fact share image.
 * @return array
 */
function ffc_schema_graph( $graph, $view, $fact, $img ) {
	$org_id = home_url( '/' ) . '#organization';
	$out    = array();
	foreach ( $graph as $node ) {
		$type = $node['@type'] ?? '';
		$id   = (string) ( $node['@id'] ?? '' );
		if ( 'DefinedTerm' === $type ) {
			continue; // A fact is not a dictionary term.
		}
		if ( $fact && 'fact' === $view && ( str_ends_with( $id, '#webpage' ) || 'Article' === $type ) ) {
			list( $published, $modified ) = ffc_fact_dates( $fact );
			$node['datePublished']        = ffc_iso( $published );
			$node['dateModified']         = ffc_iso( $modified );
		}
		if ( 'Article' === $type && 'fact' === $view ) {
			// The site publishes the fact; the editor who checked it is the page's reviewer.
			$node['author'] = array( '@id' => $org_id );
			unset( $node['about'] );
			if ( '' !== $img ) {
				$node['image'] = array(
					'@type'  => 'ImageObject',
					'url'    => $img,
					'width'  => 1200,
					'height' => 630,
				);
			}
		}
		if ( 'Article' === $type && 'article' === $view && empty( $node['image'] ) ) {
			$node['image'] = array(
				'@type'  => 'ImageObject',
				'url'    => (string) apply_filters( 'factfusion_share_image', FACTFUSION_URL . 'assets/og-image.png' ),
				'width'  => 1200,
				'height' => 630,
			);
		}
		if ( 'fotd' === $view && $fact && str_ends_with( $id, '#webpage' ) ) {
			unset( $node['about'] );
			$node['mentions'] = array(
				'@type'    => 'Article',
				'@id'      => FactFusion_Facts::url( $fact ) . '#article',
				'url'      => FactFusion_Facts::url( $fact ),
				'headline' => mb_substr( $fact->title, 0, 110 ),
			);
		}
		$out[] = $node;
	}
	return $out;
}

/*
 * Facts sitemap, replacing the core plugin's.
 */
add_action(
	'template_redirect',
	static function () {
		if ( 'sitemap' === FactFusion_Router::view() ) {
			ffc_send_sitemap();
		}
	},
	5
);

/** Indexable facts with their modified dates. */
function ffc_indexable_facts() {
	global $wpdb;
	$modified = FFC_SEO2_DB_VERSION === get_option( 'ffc_seo2_db' ) ? 'COALESCE(date_modified, date_updated)' : 'date_updated';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	return $wpdb->get_results( $wpdb->prepare( "SELECT id, slug, category_id, author_id, {$modified} AS modified FROM %i WHERE status = %s AND verification_status = %s AND is_indexable = %d ORDER BY fact_number ASC", FactFusion_DB::table( 'facts' ), 'published', 'verified', 1 ) );
}

function ffc_sitemap_urls() {
	$facts     = ffc_indexable_facts();
	$latest    = '';
	$by_cat    = array();
	$by_author = array();
	foreach ( $facts as $fact ) {
		$latest = max( $latest, (string) $fact->modified );
		$by_cat[ (int) $fact->category_id ]  = max( $by_cat[ (int) $fact->category_id ] ?? '', (string) $fact->modified );
		$by_author[ (int) $fact->author_id ] = max( $by_author[ (int) $fact->author_id ] ?? '', (string) $fact->modified );
	}
	$stamp = '' !== $latest ? ffc_iso( $latest ) : gmdate( 'Y-m-d\TH:i:s+00:00' );
	$urls  = array(
		array( FactFusion_Router::browse_url(), $stamp ),
		array( home_url( user_trailingslashit( 'fact-of-the-day' ) ), gmdate( 'Y-m-d\T00:00:00+00:00', strtotime( wp_date( 'Y-m-d' ) . ' UTC' ) ) ),
	);
	$counts = FactFusion_Facts::published_counts();
	foreach ( FactFusion_Categories::all( true ) as $category ) {
		if ( (int) ( $counts[ (int) $category->id ] ?? 0 ) >= FactFusion_SEO::THIN_CATEGORY && isset( $by_cat[ (int) $category->id ] ) ) {
			$urls[] = array( FactFusion_Router::browse_url( $category ), ffc_iso( $by_cat[ (int) $category->id ] ) );
		}
	}
	foreach ( FactFusion_Profiles::all() as $profile ) {
		if ( isset( $by_author[ (int) $profile->ID ] ) ) {
			$urls[] = array( FactFusion_Profiles::url( $profile ), ffc_iso( $by_author[ (int) $profile->ID ] ) );
		}
	}
	foreach ( $facts as $fact ) {
		$urls[] = array( FactFusion_Facts::url( $fact ), ffc_iso( $fact->modified ) );
	}
	return $urls;
}

function ffc_send_sitemap() {
	status_header( 200 );
	header( 'Content-Type: application/xml; charset=UTF-8' );
	header( 'X-Robots-Tag: noindex, follow' );
	echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
	echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
	foreach ( ffc_sitemap_urls() as $entry ) {
		printf( "\t<url><loc>%s</loc><lastmod>%s</lastmod></url>\n", esc_url( $entry[0] ), esc_html( $entry[1] ) );
	}
	echo '</urlset>' . "\n";
	exit;
}

/** The facts sitemap in Rank Math's index, with the date it really last changed. */
function ffc_rank_math_index( $xml ) {
	$latest = '';
	foreach ( ffc_indexable_facts() as $fact ) {
		$latest = max( $latest, (string) $fact->modified );
	}
	$stamp = '' !== $latest ? ffc_iso( $latest ) : gmdate( 'Y-m-d\TH:i:s+00:00' );
	return $xml . sprintf( "\t<sitemap>\n\t\t<loc>%s</loc>\n\t\t<lastmod>%s</lastmod>\n\t</sitemap>\n", esc_url( FactFusion_SEO::sitemap_url() ), esc_html( $stamp ) );
}

/*
 * robots.txt: one group for every crawler, the AI search crawlers named, sitemaps listed once.
 */
add_filter(
	'robots_txt',
	static function ( $output, $public ) {
		if ( ! $public ) {
			return $output;
		}
		$rules    = array();
		$sitemaps = array();
		foreach ( preg_split( '/\r?\n/', (string) $output ) as $line ) {
			$line = trim( $line );
			if ( preg_match( '/^(Allow|Disallow):\s*(\S*)$/i', $line, $m ) && '' !== $m[2] ) {
				$rules[ ucfirst( strtolower( $m[1] ) ) . ': ' . $m[2] ] = true;
			} elseif ( preg_match( '/^Sitemap:\s*(\S+)$/i', $line, $m ) ) {
				$sitemaps[ 'Sitemap: ' . $m[1] ] = true;
			}
		}
		$rules = implode( "\n", array_keys( $rules ) );
		$bots  = array( 'OAI-SearchBot', 'ChatGPT-User', 'GPTBot', 'PerplexityBot', 'Perplexity-User', 'ClaudeBot', 'Claude-SearchBot', 'Claude-User', 'Google-Extended', 'Applebot-Extended' );

		$text  = "User-agent: *\n" . $rules . "\n\n";
		$text .= "# AI search and assistant crawlers are welcome to read and cite our facts.\n";
		$text .= 'User-agent: ' . implode( "\nUser-agent: ", $bots ) . "\n" . $rules . "\n\n";
		$text .= implode( "\n", array_keys( $sitemaps ) ) . "\n";
		return $text;
	},
	99,
	2
);

/*
 * llms.txt: a short plain description of the site for AI tools.
 */
add_action(
	'init',
	static function () {
		if ( '/llms.txt' !== ffc_request_path() || ! class_exists( 'FactFusion_Categories' ) ) {
			return;
		}
		header( 'Content-Type: text/plain; charset=UTF-8' );
		header( 'X-Robots-Tag: noindex' );
		echo ffc_llms_txt(); // phpcs:ignore WordPress.Security.EscapeOutput -- plain text.
		exit;
	},
	1
);

function ffc_llms_txt() {
	$counts = FactFusion_Facts::published_counts();
	$home   = home_url( '/' );
	$lines  = array(
		'# ' . get_bloginfo( 'name' ),
		'',
		'> A random fact generator in which every fact links to a named source, shows the source\'s tier (A to D), the date an editor last checked it against that source, and a confidence score.',
		'',
		'Our first facts come mostly from science agencies such as NASA and NOAA (Tier A). Many newer facts come from reference works such as Wikipedia (Tier B). AI drafts some headlines, short answers and explanations from the source\'s claim; an editor checks them against the source before a fact is published, and each fact page says when AI was used. Each fact page has one claim, a short answer, an explanation, its sources and the date it was last verified.',
		'',
		'## Main pages',
		'',
		'- [Random fact generator](' . $home . '): one random sourced fact per click',
		'- [All facts](' . FactFusion_Router::browse_url() . '): every published fact, by category, with search',
		'- [Fact of the Day](' . home_url( user_trailingslashit( 'fact-of-the-day' ) ) . '): one fact a day and an archive of earlier ones',
	);
	foreach ( array( 'how-we-verify-facts' => 'How we verify facts', 'about' => 'About', 'contact' => 'Contact' ) as $slug => $label ) {
		$url = FactFusion_Pages::url( $slug );
		if ( $url ) {
			$lines[] = '- [' . $label . '](' . $url . ')';
		}
	}
	$lines[] = '';
	$lines[] = '## Facts by category';
	$lines[] = '';
	foreach ( FactFusion_Categories::all( true ) as $category ) {
		$total = (int) ( $counts[ (int) $category->id ] ?? 0 );
		if ( $total ) {
			$lines[] = sprintf( '- [%s facts](%s): %d published facts', $category->name, FactFusion_Router::browse_url( $category ), $total );
		}
	}
	$lines[] = '';
	$lines[] = '## Data';
	$lines[] = '';
	$lines[] = '- [Facts sitemap](' . FactFusion_SEO::sitemap_url() . '): every indexable fact page with its last-modified date';
	$lines[] = '- [Facts API](' . rest_url( 'factfusion/v1/facts' ) . '): published facts as JSON, free to use with a link back';
	$lines[] = '- [RSS feed](' . home_url( '/feed/' ) . '): the newest facts';
	return implode( "\n", $lines ) . "\n";
}

/*
 * RSS: the main feed lists the newest facts. There are no blog posts, so it was empty.
 */
add_filter( 'the_generator', '__return_empty_string' );

add_action(
	'do_feed_rss2',
	static function ( $for_comments ) {
		if ( $for_comments || is_category() || is_tag() || is_author() || is_search() || is_post_type_archive() ) {
			return;
		}
		ffc_facts_feed();
		exit;
	},
	1
);

function ffc_facts_feed() {
	global $wpdb;
	$published = FFC_SEO2_DB_VERSION === get_option( 'ffc_seo2_db' ) ? 'COALESCE(date_published, date_created)' : 'date_created';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$facts = $wpdb->get_results( $wpdb->prepare( "SELECT *, {$published} AS published FROM %i WHERE status = %s AND verification_status = %s ORDER BY published DESC, fact_number DESC LIMIT 30", FactFusion_DB::table( 'facts' ), 'published', 'verified' ) );
	$names = array();
	foreach ( FactFusion_Categories::all() as $row ) {
		$names[ (int) $row->id ] = $row->name;
	}
	$last = $facts ? strtotime( $facts[0]->published . ' UTC' ) : time();

	header( 'Content-Type: application/rss+xml; charset=UTF-8' );
	echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
	echo '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">' . "\n<channel>\n";
	printf( "<title>%s</title>\n", esc_html( get_bloginfo( 'name' ) . ': newest facts' ) );
	printf( "<link>%s</link>\n", esc_url( home_url( '/' ) ) );
	printf( "<atom:link href=\"%s\" rel=\"self\" type=\"application/rss+xml\" />\n", esc_url( home_url( '/feed/' ) ) );
	echo "<description>New facts, each with a link to its source.</description>\n";
	printf( "<language>%s</language>\n", esc_html( get_bloginfo( 'language' ) ) );
	printf( "<lastBuildDate>%s</lastBuildDate>\n", esc_html( gmdate( DATE_RSS, $last ) ) );
	foreach ( $facts as $fact ) {
		$url     = FactFusion_Facts::url( $fact );
		$sources = FactFusion_Sources::for_fact( $fact->id );
		$text    = '' !== trim( $fact->short_answer ) ? $fact->short_answer : $fact->claim;
		if ( $sources ) {
			$text .= ' Source: ' . $sources[0]->source_name . '.';
		}
		echo "<item>\n";
		printf( "<title>%s</title>\n", esc_html( $fact->title ) );
		printf( "<link>%s</link>\n", esc_url( $url ) );
		printf( "<guid isPermaLink=\"true\">%s</guid>\n", esc_url( $url ) );
		printf( "<pubDate>%s</pubDate>\n", esc_html( gmdate( DATE_RSS, strtotime( $fact->published . ' UTC' ) ) ) );
		if ( isset( $names[ (int) $fact->category_id ] ) ) {
			printf( "<category>%s</category>\n", esc_html( $names[ (int) $fact->category_id ] ) );
		}
		printf( "<description>%s</description>\n", esc_html( $text ) );
		echo "</item>\n";
	}
	echo "</channel>\n</rss>\n";
}
