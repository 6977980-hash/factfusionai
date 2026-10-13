<?php
/**
 * SEO audit, phase 3: content depth for search and answer engines.
 *
 * - Each fact page gets a question heading (the question people type) right above its short
 *   answer, and, where written, a "How we know" section with the exact lines quoted from its
 *   sources. "How we know" text is labelled as AI-drafted until an editor ticks it as checked
 *   in FactFusion AI > Depth check.
 * - Space and Ocean facts get a second independent source where one was found.
 * - Topic hub pages (Venus, the Sun, Saturn, the Moon, the deep sea) and myth verdict pages,
 *   created from data/depth/. Each is labelled as AI-drafted until an editor marks it reviewed;
 *   the fact cards on them only ever show published, editor-verified facts.
 * - Category pages link their hubs and myths; fact pages link the hub they belong to.
 * - Policy pages show when they were last updated; health facts carry a not-medical-advice note.
 */

defined( 'ABSPATH' ) || exit;

const FFC_DEPTH_DB_VERSION   = '1';
const FFC_DEPTH_SEED_VERSION = '1';
const FFC_DEPTH_KIND_META    = '_ffc_kind';
const FFC_DEPTH_DRAFT_META   = '_ffc_ai_draft';
const FFC_DEPTH_HASH_META    = '_ffc_src_hash';

/** Categories whose facts are about health, and get the not-medical-advice note. */
const FFC_HEALTH_CATEGORIES = array( 'human-body', 'psychology', 'food' );

/**
 * Adds the phase 3 columns to the facts table: question, how_we_know, source_quotes
 * (JSON: source URL => quoted line) and who checked the "How we know" text, and when.
 * The quotes live on the fact row, not the fact-source link, because saving a fact in the
 * editor rewrites its source links.
 *
 * @return bool Whether the columns are there.
 */
function ffc_depth_db_ready() {
	if ( FFC_DEPTH_DB_VERSION === get_option( 'ffc_depth_db' ) ) {
		return true;
	}
	if ( ! class_exists( 'FactFusion_DB' ) ) {
		return false;
	}
	global $wpdb;
	$table = FactFusion_DB::table( 'facts' );
	// phpcs:disable WordPress.DB.DirectDatabaseQuery
	$columns = (array) $wpdb->get_col( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $table ), 0 );
	if ( ! $columns ) {
		return false;
	}
	$add = array();
	if ( ! in_array( 'question', $columns, true ) ) {
		$add[] = "ADD COLUMN question varchar(255) NOT NULL DEFAULT ''";
	}
	if ( ! in_array( 'how_we_know', $columns, true ) ) {
		$add[] = 'ADD COLUMN how_we_know text NULL';
	}
	if ( ! in_array( 'source_quotes', $columns, true ) ) {
		$add[] = 'ADD COLUMN source_quotes longtext NULL';
	}
	if ( ! in_array( 'depth_checked_by', $columns, true ) ) {
		$add[] = 'ADD COLUMN depth_checked_by bigint(20) unsigned NOT NULL DEFAULT 0, ADD COLUMN depth_checked_at datetime DEFAULT NULL';
	}
	if ( $add ) {
		$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ' . implode( ', ', $add ), $table ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$columns = (array) $wpdb->get_col( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $table ), 0 );
		if ( ! in_array( 'depth_checked_at', $columns, true ) ) {
			return false;
		}
	}
	// phpcs:enable
	update_option( 'ffc_depth_db', FFC_DEPTH_DB_VERSION, true );
	return true;
}

/** Quotes stored on a fact: source URL => quoted line. */
function ffc_depth_quotes( $fact ) {
	$quotes = json_decode( (string) ( $fact->source_quotes ?? '' ), true );
	return is_array( $quotes ) ? $quotes : array();
}

/** The fact a public fact page is showing, or null. */
function ffc_depth_page_fact() {
	if ( ! class_exists( 'FactFusion_Router' ) || 'fact' !== FactFusion_Router::view() ) {
		return null;
	}
	$fact = FactFusion_Router::current_fact();
	return $fact && 'published' === $fact->status ? $fact : null;
}

/* -------------------------------------------------------------------------
 * Seeding the questions, "How we know" drafts and second sources.
 * ---------------------------------------------------------------------- */

/**
 * Fills empty question and "How we know" fields from data/depth/, and attaches second sources.
 * Never overwrites what an editor wrote or checked. Runs once per FFC_DEPTH_SEED_VERSION.
 */
function ffc_depth_seed() {
	if ( FFC_DEPTH_SEED_VERSION === get_option( 'ffc_depth_seed' ) || ! ffc_depth_db_ready() ) {
		return;
	}
	if ( ! add_option( 'ffc_depth_seed_lock', time(), '', false ) ) {
		if ( time() - (int) get_option( 'ffc_depth_seed_lock' ) < 300 ) {
			return;
		}
		update_option( 'ffc_depth_seed_lock', time(), false );
	}
	global $wpdb;
	$table   = FactFusion_DB::table( 'facts' );
	$links   = FactFusion_DB::table( 'fact_sources' );
	$now     = FactFusion_DB::now();
	$changed = array();

	$find = static function ( $key ) {
		return 0 === strpos( $key, '#' ) ? FactFusion_Facts::get( (int) substr( $key, 1 ) ) : FactFusion_Facts::get_by_slug( $key );
	};

	// Questions for every category.
	foreach ( (array) include get_stylesheet_directory() . '/data/depth/questions.php' as $key => $question ) {
		$fact = $find( (string) $key );
		if ( $fact && '' === trim( (string) $fact->question ) ) {
			$wpdb->update( $table, array( 'question' => $question ), array( 'id' => (int) $fact->id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$changed[ (int) $fact->id ] = $fact->slug;
		}
	}

	// Space and Ocean: question, "How we know" draft, quoted lines and a second source.
	foreach ( (array) include get_stylesheet_directory() . '/data/depth/facts.php' as $row ) {
		$fact = $find( (string) $row['slug'] );
		if ( ! $fact ) {
			continue;
		}
		$update = array();
		if ( '' === trim( (string) $fact->question ) && ! empty( $row['question'] ) ) {
			$update['question'] = $row['question'];
		}
		if ( '' === trim( (string) $fact->how_we_know ) && ! (int) $fact->depth_checked_by && ! empty( $row['how_we_know'] ) ) {
			$update['how_we_know'] = $row['how_we_know'];
		}
		$quotes = ffc_depth_quotes( $fact );
		$before = $quotes;
		foreach ( array( 'primary', 'second' ) as $which ) {
			$item = $row[ $which ] ?? null;
			if ( empty( $item['url'] ) || empty( $item['quote'] ) ) {
				continue;
			}
			$url = esc_url_raw( $item['url'] );
			if ( 'second' === $which ) {
				$source = FactFusion_Sources::find_by_url( $url );
				$id     = $source ? (int) $source->id : FactFusion_Sources::save(
					array(
						'source_name'   => $item['name'],
						'url'           => $url,
						'tier'          => $item['tier'] ?? 'A',
						'title'         => $item['title'] ?? '',
						'publisher'     => $item['name'],
						'date_accessed' => gmdate( 'Y-m-d' ),
						'notes'         => 'Second source added in SEO phase 3.',
						'is_active'     => 1,
					)
				);
				if ( is_wp_error( $id ) || ! $id ) {
					continue;
				}
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->query( $wpdb->prepare( 'INSERT IGNORE INTO %i (fact_id, source_id) VALUES (%d, %d)', $links, (int) $fact->id, (int) $id ) );
			}
			if ( ! isset( $quotes[ $url ] ) ) {
				$quotes[ $url ] = $item['quote'];
			}
		}
		if ( $quotes !== $before ) {
			$update['source_quotes'] = wp_json_encode( $quotes, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		}
		if ( $update ) {
			// New sourced text on a live page is a real change, so the modified date moves.
			if ( 'published' === $fact->status && ( isset( $update['how_we_know'] ) || isset( $update['source_quotes'] ) ) ) {
				$update['date_modified'] = $now;
			}
			$wpdb->update( $table, $update, array( 'id' => (int) $fact->id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$changed[ (int) $fact->id ] = $fact->slug;
		}
	}

	update_option( 'ffc_depth_seed', FFC_DEPTH_SEED_VERSION, true );
	delete_option( 'ffc_depth_seed_lock' );
	FactFusion_Sources::forget();
	if ( $changed ) {
		$urls = array();
		foreach ( $changed as $slug ) {
			$urls[] = home_url( user_trailingslashit( 'fact/' . $slug ) );
		}
		if ( function_exists( 'ffc_indexnow' ) ) {
			ffc_indexnow( $urls );
		}
		if ( function_exists( 'ffc_purge' ) ) {
			ffc_purge( true );
		}
	}
}
add_action( 'init', 'ffc_depth_seed', 30 );

/* -------------------------------------------------------------------------
 * Hub and myth pages.
 * ---------------------------------------------------------------------- */

/**
 * The phase 3 pages: slug => definition (kind, title, meta_title, description, category,
 * facts it covers, and for myths the verdict).
 *
 * @return array<string,array>
 */
function ffc_depth_pages() {
	static $pages = null;
	if ( null === $pages ) {
		$pages = (array) include get_stylesheet_directory() . '/data/depth/pages.php';
	}
	return $pages;
}

/** The page's starter HTML, with {{home}} replaced by the site address. */
function ffc_depth_page_html( $slug ) {
	$file = get_stylesheet_directory() . '/data/depth/pages/' . $slug . '.php';
	if ( ! is_readable( $file ) ) {
		return '';
	}
	ob_start();
	include $file;
	return strtr( trim( (string) ob_get_clean() ), array( '{{home}}' => untrailingslashit( home_url() ) ) );
}

/**
 * Creates the hub and myth pages that do not exist yet, published, with no author until an
 * editor reviews them. A page the editor has not changed is refreshed when its starter text
 * in the theme changes; once an editor edits or reviews a page, it is never touched again.
 */
function ffc_depth_sync_pages() {
	if ( ! class_exists( 'FactFusion_Content' ) ) {
		return;
	}
	$version = md5( (string) wp_json_encode( ffc_depth_pages() ) . implode( '', array_map( 'md5_file', (array) glob( get_stylesheet_directory() . '/data/depth/pages/*.php' ) ) ) );
	if ( get_option( 'ffc_depth_pages' ) === $version ) {
		return;
	}
	if ( ! add_option( 'ffc_depth_pages_lock', time(), '', false ) ) {
		if ( time() - (int) get_option( 'ffc_depth_pages_lock' ) < 300 ) {
			return;
		}
		update_option( 'ffc_depth_pages_lock', time(), false );
	}
	$kses = has_filter( 'content_save_pre', 'wp_filter_post_kses' );
	if ( $kses ) {
		kses_remove_filters(); // Trusted starter text from the theme, with details blocks and shortcodes.
	}
	$touched = array();
	foreach ( ffc_depth_pages() as $slug => $def ) {
		$html = ffc_depth_page_html( $slug );
		if ( '' === $html ) {
			continue;
		}
		$page = get_page_by_path( $slug, OBJECT, 'page' );
		if ( ! $page ) {
			$id = wp_insert_post(
				wp_slash(
					array(
						'post_type'      => 'page',
						'post_status'    => 'publish',
						'post_title'     => $def['title'],
						'post_name'      => $slug,
						'post_content'   => $html,
						'post_excerpt'   => $def['description'],
						'comment_status' => 'closed',
						'ping_status'    => 'closed',
					)
				),
				true
			);
			if ( is_wp_error( $id ) ) {
				continue;
			}
			update_post_meta( $id, FactFusion_Content::TYPE_META, 'article' );
			update_post_meta( $id, FFC_DEPTH_DRAFT_META, 1 );
			$page      = get_post( $id );
			$touched[] = get_permalink( $page->ID );
		} elseif ( ! get_post_meta( $page->ID, FFC_DEPTH_DRAFT_META, true ) || get_post_meta( $page->ID, FFC_DEPTH_HASH_META, true ) !== md5( $page->post_content ) ) {
			continue; // Reviewed or edited by a person: theirs now.
		} elseif ( $page->post_content !== $html || $page->post_title !== $def['title'] ) {
			wp_update_post( wp_slash( array( 'ID' => $page->ID, 'post_content' => $html, 'post_title' => $def['title'] ) ) );
			$page      = get_post( $page->ID );
			$touched[] = get_permalink( $page->ID );
		}
		// AI-drafted pages carry no person's name until someone reviews them.
		if ( (int) $page->post_author ) {
			global $wpdb;
			$wpdb->update( $wpdb->posts, array( 'post_author' => 0 ), array( 'ID' => $page->ID ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			clean_post_cache( $page->ID );
		}
		update_post_meta( $page->ID, FFC_DEPTH_HASH_META, md5( $page->post_content ) );
		update_post_meta( $page->ID, FFC_DEPTH_KIND_META, $def['kind'] );
		update_post_meta( $page->ID, FactFusion_Content::TITLE_META, $def['meta_title'] );
		update_post_meta( $page->ID, FactFusion_Content::DESCRIPTION_META, $def['description'] );
		update_post_meta( $page->ID, FactFusion_Content::CATEGORY_META, '' );
	}
	if ( $kses ) {
		kses_init_filters();
	}
	update_option( 'ffc_depth_pages', $version, false );
	delete_option( 'ffc_depth_pages_lock' );
	if ( $touched ) {
		if ( function_exists( 'ffc_indexnow' ) ) {
			ffc_indexnow( $touched );
		}
		if ( function_exists( 'ffc_purge' ) ) {
			ffc_purge( true );
		}
	}
}
add_action( 'init', 'ffc_depth_sync_pages', 31 );

/** Published hub and myth pages: slug => array( definition, WP_Post ). */
function ffc_depth_live_pages() {
	static $live = null;
	if ( null === $live ) {
		$live = array();
		foreach ( ffc_depth_pages() as $slug => $def ) {
			$page = get_page_by_path( $slug, OBJECT, 'page' );
			if ( $page && 'publish' === $page->post_status ) {
				$live[ $slug ] = array( $def, $page );
			}
		}
	}
	return $live;
}

/** Whether a page is one of ours that no editor has reviewed yet. */
function ffc_depth_is_ai_draft( $post ) {
	$post = get_post( $post );
	return $post && get_post_meta( $post->ID, FFC_DEPTH_DRAFT_META, true );
}

// Under the title of an unreviewed page, say how it was written instead of naming a person.
add_filter(
	'do_shortcode_tag',
	static function ( $output, $tag ) {
		if ( 'factfusion_byline' !== $tag || ! class_exists( 'FactFusion_Content' ) ) {
			return $output;
		}
		$post = FactFusion_Content::current();
		if ( ! $post || ! ffc_depth_is_ai_draft( $post ) ) {
			return $output;
		}
		$verify = home_url( user_trailingslashit( 'how-we-verify-facts' ) );
		return $output . '<p class="ff-meta ff-ai-disclosure">' . sprintf(
			/* translators: %s: link to the verification page. */
			esc_html__( 'This page was written with AI assistance from the sources it links to, and is waiting for an editor\'s review. The fact cards on it show only facts an editor has checked. %s', 'factfusion-ai' ),
			'<a href="' . esc_url( $verify ) . '">' . esc_html__( 'How we verify facts', 'factfusion-ai' ) . '</a>'
		) . '</p>';
	},
	10,
	2
);

/** [ffc_myths]: the myth pages, with their verdicts. Used on the myths index page. */
add_shortcode(
	'ffc_myths',
	static function () {
		$items = '';
		foreach ( ffc_depth_live_pages() as $def_page ) {
			list( $def, $page ) = $def_page;
			if ( 'myth' !== $def['kind'] ) {
				continue;
			}
			$items .= sprintf(
				'<li class="ff-card ff-myth-card"><span class="ff-verdict ff-verdict--%1$s">%2$s</span> <a class="ff-fact-list__title" href="%3$s">%4$s</a><p>%5$s</p></li>',
				esc_attr( sanitize_title( $def['verdict'] ) ),
				esc_html( $def['verdict'] ),
				esc_url( get_permalink( $page ) ),
				esc_html( get_the_title( $page ) ),
				esc_html( $def['description'] )
			);
		}
		return $items ? '<ul class="ff-fact-list ff-myth-list">' . $items . '</ul>' : '';
	}
);

/** Links to the hubs and myths of a category: [ffc_topics category="space"]. */
function ffc_depth_topics_html( $category_slug, $heading = '' ) {
	$hubs  = array();
	$myths = array();
	foreach ( ffc_depth_live_pages() as $def_page ) {
		list( $def, $page ) = $def_page;
		if ( ! in_array( $category_slug, (array) $def['categories'], true ) ) {
			continue;
		}
		$link = sprintf( '<li><a href="%s">%s</a></li>', esc_url( get_permalink( $page ) ), esc_html( get_the_title( $page ) ) );
		if ( 'hub' === $def['kind'] ) {
			$hubs[] = $link;
		} elseif ( 'myth' === $def['kind'] ) {
			$myths[] = $link;
		}
	}
	if ( ! $hubs && ! $myths ) {
		return '';
	}
	$html = '<section class="ff-section ff-topics">';
	if ( '' !== $heading ) {
		$html .= '<h2 class="wp-block-heading">' . esc_html( $heading ) . '</h2>';
	}
	if ( $hubs ) {
		$html .= '<h3 class="ff-guides__label">' . esc_html__( 'Topics', 'factfusion-ai' ) . '</h3><ul class="ff-guides__list">' . implode( '', $hubs ) . '</ul>';
	}
	if ( $myths ) {
		$html .= '<h3 class="ff-guides__label">' . esc_html__( 'Myths checked', 'factfusion-ai' ) . '</h3><ul class="ff-guides__list">' . implode( '', $myths ) . '</ul>';
		$index = ffc_depth_live_pages()['myths-checked'][1] ?? null;
		if ( $index ) {
			$html .= sprintf( '<p><a href="%s">%s</a></p>', esc_url( get_permalink( $index ) ), esc_html__( 'All myths we checked', 'factfusion-ai' ) );
		}
	}
	return $html . '</section>';
}

add_shortcode(
	'ffc_topics',
	static function ( $atts ) {
		$atts = shortcode_atts( array( 'category' => '', 'heading' => '' ), $atts, 'ffc_topics' );
		return ffc_depth_topics_html( sanitize_title( $atts['category'] ), $atts['heading'] );
	}
);

// Category pages: link the topic hubs and myth pages for that category.
add_filter(
	'factfusion_category_topics',
	static function ( $html, $category ) {
		/* translators: %s: category name. */
		return $html . ffc_depth_topics_html( $category->slug, sprintf( __( 'Explore %s by topic', 'factfusion-ai' ), $category->name ) );
	},
	10,
	2
);

/* -------------------------------------------------------------------------
 * Fact pages: question heading, "How we know", hub links, health note.
 * ---------------------------------------------------------------------- */

// Question heading right above the short answer, so the answer sits directly under the question.
add_filter(
	'render_block',
	static function ( $content, $block ) {
		if ( 'core/paragraph' !== ( $block['blockName'] ?? '' ) || false === strpos( (string) ( $block['attrs']['className'] ?? '' ), 'ff-fact__answer' ) ) {
			return $content;
		}
		$fact = ffc_depth_page_fact();
		$q    = $fact ? trim( (string) ( $fact->question ?? '' ) ) : '';
		return '' === $q ? $content : '<h2 class="wp-block-heading ff-fact__question">' . esc_html( $q ) . '</h2>' . $content;
	},
	10,
	2
);

/** The "How we know" section of a fact, or an empty string. */
function ffc_depth_how_we_know( $fact ) {
	$text = trim( (string) ( $fact->how_we_know ?? '' ) );
	if ( '' === $text ) {
		return '';
	}
	$html   = '<section class="ff-how-we-know" id="how-we-know"><h2 class="wp-block-heading">' . esc_html__( 'How we know', 'factfusion-ai' ) . '</h2>';
	$html  .= wpautop( esc_html( $text ) );
	$quotes = ffc_depth_quotes( $fact );
	$items  = '';
	foreach ( FactFusion_Sources::for_fact( $fact->id ) as $source ) {
		$quote = $quotes[ $source->url ] ?? '';
		if ( '' === $quote ) {
			continue;
		}
		$items .= sprintf(
			'<li><blockquote class="ff-quote"><p>%1$s</p></blockquote><p class="ff-meta">%2$s <a href="%3$s">%4$s</a></p></li>',
			esc_html( '“' . $quote . '”' ),
			esc_html__( 'From', 'factfusion-ai' ),
			esc_url( $source->url ),
			esc_html( $source->source_name )
		);
	}
	if ( $items ) {
		$html .= '<h3>' . esc_html__( 'What the sources say', 'factfusion-ai' ) . '</h3><ul class="ff-quotes">' . $items . '</ul>';
	}
	$checker = (int) ( $fact->depth_checked_by ?? 0 ) ? get_userdata( (int) $fact->depth_checked_by ) : false;
	if ( $checker && ! empty( $fact->depth_checked_at ) ) {
		$stamp = strtotime( $fact->depth_checked_at . ' UTC' );
		$html .= '<p class="ff-meta">' . sprintf(
			/* translators: 1: editor name, 2: date. */
			esc_html__( 'Checked against these sources by %1$s on %2$s.', 'factfusion-ai' ),
			esc_html( $checker->display_name ),
			sprintf( '<time datetime="%1$s">%2$s</time>', esc_attr( gmdate( 'Y-m-d', $stamp ) ), esc_html( wp_date( get_option( 'date_format' ), $stamp ) ) )
		) . '</p>';
	} else {
		$html .= '<p class="ff-meta ff-ai-disclosure">' . esc_html__( 'This section was written with AI assistance from the quoted sources. An editor has not re-checked it yet; the quotes are copied word for word from the linked pages.', 'factfusion-ai' ) . '</p>';
	}
	return $html . '</section>';
}

add_filter(
	'do_shortcode_tag',
	static function ( $output, $tag ) {
		if ( 'factfusion_fact_explanation' !== $tag ) {
			return $output;
		}
		$fact = ffc_depth_page_fact();
		if ( ! $fact ) {
			return $output;
		}
		$category = $fact->category_id ? FactFusion_Categories::get( $fact->category_id ) : null;
		if ( $category && in_array( $category->slug, FFC_HEALTH_CATEGORIES, true ) && 'food' !== $category->slug ) {
			$output .= '<p class="ff-meta ff-health-note">' . esc_html__( 'This is general information from the source named below, not medical advice. For your own health, ask a doctor or pharmacist.', 'factfusion-ai' ) . '</p>';
		} elseif ( $category && 'food' === $category->slug && preg_match( '/caffeine|botulism|food poisoning|bacteria|allergen|safe/i', $fact->title . ' ' . $fact->short_answer ) ) {
			$output .= '<p class="ff-meta ff-health-note">' . esc_html__( 'This is general food-safety information from the source named below, not medical advice.', 'factfusion-ai' ) . '</p>';
		}

		$links = array();
		foreach ( ffc_depth_live_pages() as $def_page ) {
			list( $def, $page ) = $def_page;
			if ( in_array( $fact->slug, (array) $def['facts'], true ) ) {
				$links[] = sprintf(
					'<li>%1$s <a href="%2$s">%3$s</a></li>',
					esc_html( 'myth' === $def['kind'] ? __( 'Myth check:', 'factfusion-ai' ) : __( 'More on this topic:', 'factfusion-ai' ) ),
					esc_url( get_permalink( $page ) ),
					esc_html( get_the_title( $page ) )
				);
			}
		}
		if ( $links ) {
			$output .= '<ul class="ff-meta ff-hub-links">' . implode( '', $links ) . '</ul>';
		}
		return $output . ffc_depth_how_we_know( $fact );
	},
	10,
	2
);

/* -------------------------------------------------------------------------
 * Policy pages: last updated date.
 * ---------------------------------------------------------------------- */

add_filter(
	'the_content',
	static function ( $content ) {
		if ( ! is_page() || ! class_exists( 'FactFusion_Pages' ) ) {
			return $content;
		}
		$post = get_post();
		if ( ! $post || (int) $post->ID !== get_queried_object_id() || doing_filter( 'get_the_excerpt' ) || ! isset( FactFusion_Pages::definitions()[ $post->post_name ] ) ) {
			return $content;
		}
		$stamp = get_post_modified_time( 'U', true, $post );
		return '<p class="ff-meta ff-updated">' . sprintf(
			/* translators: %s: date. */
			esc_html__( 'Last updated: %s', 'factfusion-ai' ),
			sprintf( '<time datetime="%1$s">%2$s</time>', esc_attr( gmdate( 'Y-m-d', $stamp ) ), esc_html( wp_date( get_option( 'date_format' ), $stamp ) ) )
		) . '</p>' . $content;
	},
	5
);

/* -------------------------------------------------------------------------
 * Styles.
 * ---------------------------------------------------------------------- */

add_action(
	'wp_enqueue_scripts',
	static function () {
		$css = '.ff-fact__question{font-size:1.15rem;margin:1.25rem 0 .25rem}'
			. '.ff-how-we-know{margin-top:2rem}'
			. '.ff-quotes{list-style:none;padding:0;margin:.75rem 0;display:grid;gap:.75rem}'
			. '.ff-quote{margin:0;padding:.5rem 0 .5rem 1rem;border-left:3px solid var(--wp--preset--color--primary,#0b6b74);font-style:italic}'
			. '.ff-quote p{margin:0}.ff-quotes .ff-meta{margin:.25rem 0 0}'
			. '.ff-hub-links{list-style:none;padding:0;margin:1rem 0}'
			. '.ff-health-note{margin-top:1rem}'
			. '.ff-verdict{display:inline-block;padding:.15rem .6rem;border-radius:999px;font-weight:700;font-size:.85rem;background:var(--wp--preset--color--soft,#eee)}'
			. '.ff-verdict--false{background:#FDE8E7;color:#9B1C14}.ff-verdict--misleading,.ff-verdict--mostly-false{background:#FDF1DC;color:#8A4B06}.ff-verdict--true{background:#E3F4E8;color:#1D6B38}'
			. '@media (prefers-color-scheme:dark){:root:not([data-theme="light"]) .ff-verdict--false{background:#3A1A18;color:#FF9B92}:root:not([data-theme="light"]) .ff-verdict--misleading,:root:not([data-theme="light"]) .ff-verdict--mostly-false{background:#36260F;color:#F3B46B}:root:not([data-theme="light"]) .ff-verdict--true{background:#15301F;color:#86D7A0}}'
			. '.ff-verdict-box{padding:1rem 1.25rem;border:1px solid var(--wp--preset--color--border,#ddd);border-radius:12px;margin:1rem 0}'
			. '.ff-verdict-box p{margin:.5rem 0 0}.ff-myth-list .ff-verdict{margin-right:.4rem}';
		wp_register_style( 'ffc-depth', false, array(), '1' );
		wp_enqueue_style( 'ffc-depth' );
		wp_add_inline_style( 'ffc-depth', $css );
	},
	20
);
