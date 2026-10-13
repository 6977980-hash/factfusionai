<?php
/**
 * SEO audit, phase 2: fact dates, page-cache purging, old addresses and the Fact of the Day.
 *
 * - Each fact gets a real publish date (set the first time it goes live) and a modified date
 *   that moves only when its text or category changes. Skipping it in Today's 5 or re-running
 *   the AI on a pending fact no longer changes anything search engines see.
 * - Saving a fact clears the page cache only for the pages that show it, and only when a
 *   published fact changed. Edits to pending facts clear nothing.
 * - When a fact's address (slug) changes, the old address 301s to the new one. An archived
 *   fact's address 301s to its category.
 * - The Fact of the Day is fixed for the whole day and kept in a log for the archive.
 */

defined( 'ABSPATH' ) || exit;

const FFC_SEO2_DB_VERSION = '1';

/** SQL for the text that counts as "the content" of a fact. */
const FFC_CONTENT_HASH_SQL = "MD5(CONCAT_WS('|', title, claim, short_answer, explanation, category_id))";

/**
 * Adds date_published, date_modified and content_hash to the facts table and fills them once.
 * The core plugin never drops columns it does not know, so these stay through its upgrades.
 *
 * @return bool Whether the columns are there.
 */
function ffc_facts_columns_ready() {
	if ( FFC_SEO2_DB_VERSION === get_option( 'ffc_seo2_db' ) ) {
		return true;
	}
	if ( ! class_exists( 'FactFusion_DB' ) ) {
		return false;
	}
	if ( ! add_option( 'ffc_seo2_db_lock', time(), '', false ) ) {
		if ( time() - (int) get_option( 'ffc_seo2_db_lock' ) < 300 ) {
			return false;
		}
		update_option( 'ffc_seo2_db_lock', time(), false ); // A run that died part-way.
	}
	global $wpdb;
	$table = FactFusion_DB::table( 'facts' );
	// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$columns = (array) $wpdb->get_col( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $table ), 0 );
	if ( ! $columns ) {
		delete_option( 'ffc_seo2_db_lock' );
		return false;
	}
	if ( ! in_array( 'date_published', $columns, true ) ) {
		$wpdb->query( $wpdb->prepare( "ALTER TABLE %i ADD COLUMN date_published datetime DEFAULT NULL, ADD COLUMN date_modified datetime DEFAULT NULL, ADD COLUMN content_hash char(32) NOT NULL DEFAULT ''", $table ) );
	}
	// Facts that are already live: the best record of when they went live is when they were
	// verified, or when they were created if that came later (hand-made facts).
	$wpdb->query( $wpdb->prepare( 'UPDATE %i SET date_published = GREATEST(date_created, COALESCE(date_verified, date_created)) WHERE status = %s AND date_published IS NULL', $table, 'published' ) );
	$wpdb->query( $wpdb->prepare( 'UPDATE %i SET date_modified = GREATEST(date_published, date_updated), content_hash = ' . FFC_CONTENT_HASH_SQL . " WHERE status = %s AND content_hash = ''", $table, 'published' ) );
	// phpcs:enable
	update_option( 'ffc_seo2_db', FFC_SEO2_DB_VERSION, true );
	delete_option( 'ffc_seo2_db_lock' );
	ffc_facts_sync();
	return true;
}
add_action( 'init', 'ffc_facts_columns_ready', 5 );

/**
 * Publish and modified dates of a fact, as stored UTC datetimes.
 *
 * @param object $fact Fact row.
 * @return array{0:string,1:string}
 */
function ffc_fact_dates( $fact ) {
	$published = ! empty( $fact->date_published ) ? $fact->date_published : $fact->date_created;
	$modified  = ! empty( $fact->date_modified ) ? $fact->date_modified : $fact->date_updated;
	return array( $published, max( $published, $modified ) );
}

/** A stored UTC datetime in ISO 8601. */
function ffc_iso( $datetime ) {
	return gmdate( 'Y-m-d\TH:i:s+00:00', strtotime( $datetime . ' UTC' ) );
}

/**
 * Brings the dates up to date with the facts table and works out which published facts changed
 * since the last call (published, unpublished, edited, moved or renamed).
 *
 * @return string[] Public URLs whose pages show something that changed.
 */
function ffc_facts_sync() {
	if ( FFC_SEO2_DB_VERSION !== get_option( 'ffc_seo2_db' ) ) {
		return array();
	}
	global $wpdb;
	$table = FactFusion_DB::table( 'facts' );
	$now   = FactFusion_DB::now();
	// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT id, slug, category_id, date_published, content_hash, ' . FFC_CONTENT_HASH_SQL . ' AS hash FROM %i WHERE status = %s', $table, 'published' ) );

	$before  = get_option( 'ffc_published_snapshot', null );
	$after   = array();
	$changed = array(); // id => array( slugs, category ids ).
	foreach ( $rows as $row ) {
		$id           = (int) $row->id;
		$after[ $id ] = array( $row->slug, (int) $row->category_id );
		if ( empty( $row->date_published ) ) {
			$wpdb->update( $table, array( 'date_published' => $now, 'date_modified' => $now, 'content_hash' => $row->hash ), array( 'id' => $id ) );
			$changed[ $id ] = array( array( $row->slug ), array( (int) $row->category_id ) );
		} elseif ( $row->content_hash !== $row->hash ) {
			$wpdb->update( $table, array( 'date_modified' => $now, 'content_hash' => $row->hash ), array( 'id' => $id ) );
			$changed[ $id ] = array( array( $row->slug ), array( (int) $row->category_id ) );
		}
	}
	// phpcs:enable

	$urls = array();
	if ( is_array( $before ) ) {
		$old_slugs = (array) get_option( 'ffc_old_slugs', array() );
		foreach ( $before as $id => $was ) {
			$now_row = $after[ $id ] ?? null;
			if ( ! $now_row ) {
				$changed[ $id ] = array( array( $was[0] ), array( $was[1] ) ); // No longer published.
				continue;
			}
			if ( $now_row[0] !== $was[0] ) {
				$old_slugs[ $was[0] ] = (int) $id;
			}
			if ( $now_row[0] !== $was[0] || $now_row[1] !== $was[1] ) {
				$changed[ $id ] = array( array( $was[0], $now_row[0] ), array( $was[1], $now_row[1] ) );
			}
		}
		foreach ( $after as $id => $now_row ) {
			if ( ! isset( $before[ $id ] ) ) {
				$changed[ $id ] = array( array( $now_row[0] ), array( $now_row[1] ) );
			}
		}
		update_option( 'ffc_old_slugs', $old_slugs, false );

		$new_or_edited = array();
		foreach ( $changed as $id => $what ) {
			foreach ( array_unique( $what[0] ) as $slug ) {
				$urls[] = home_url( user_trailingslashit( 'fact/' . $slug ) );
			}
			if ( isset( $after[ $id ] ) ) {
				$new_or_edited[] = home_url( user_trailingslashit( 'fact/' . $after[ $id ][0] ) );
			}
			foreach ( array_unique( $what[1] ) as $category_id ) {
				$urls = array_merge( $urls, ffc_category_urls( (int) $category_id ) );
			}
		}
		if ( $new_or_edited ) {
			ffc_indexnow( $new_or_edited );
		}
	}
	update_option( 'ffc_published_snapshot', $after, false );
	return array_values( array_unique( $urls ) );
}

/**
 * Every page of a category listing.
 *
 * @param int $category_id Category ID.
 * @return string[]
 */
function ffc_category_urls( $category_id ) {
	$category = $category_id ? FactFusion_Categories::get( $category_id ) : null;
	if ( ! $category ) {
		return array();
	}
	$counts = FactFusion_Facts::published_counts();
	$pages  = max( 1, (int) ceil( ( (int) ( $counts[ $category_id ] ?? 0 ) + 1 ) / FactFusion_Frontend::BROWSE_PER_PAGE ) );
	$urls   = array();
	for ( $page = 1; $page <= $pages; $page++ ) {
		$urls[] = FactFusion_Router::browse_url( $category, $page );
	}
	return $urls;
}

/**
 * Clears these pages from the server page cache.
 *
 * @param string[]|true $urls URLs, or true for the whole site.
 */
function ffc_purge( $urls ) {
	if ( true === $urls ) {
		do_action( 'litespeed_purge_all' );
		if ( ! headers_sent() ) {
			header( 'X-LiteSpeed-Purge: *', false );
		}
		return;
	}
	$paths = array();
	foreach ( array_unique( (array) $urls ) as $url ) {
		do_action( 'litespeed_purge_url', $url );
		$paths[] = (string) wp_parse_url( $url, PHP_URL_PATH );
	}
	// Works with Hostinger's server cache even without the LiteSpeed plugin.
	if ( $paths && ! headers_sent() ) {
		header( 'X-LiteSpeed-Purge: ' . implode( ', ', array_unique( $paths ) ), false );
	}
}

/**
 * Pages that list the newest facts, cleared whenever any published fact changes.
 *
 * @return string[]
 */
function ffc_shared_urls() {
	$urls = array(
		home_url( '/' ),
		home_url( '/sitemap-facts.xml' ),
		home_url( '/sitemap_index.xml' ),
		home_url( '/feed/' ),
		home_url( '/llms.txt' ),
		home_url( user_trailingslashit( 'fact-of-the-day' ) ),
	);
	$total = count( FactFusion_Facts::pool() );
	$pages = max( 1, (int) ceil( ( $total + 1 ) / FactFusion_Frontend::BROWSE_PER_PAGE ) );
	for ( $page = 1; $page <= $pages; $page++ ) {
		$urls[] = FactFusion_Router::browse_url( null, $page );
	}
	return $urls;
}

/** Tells IndexNow (Bing, Yandex and others) about new or edited fact pages. */
function ffc_indexnow( $urls ) {
	$urls = array_values( array_filter( (array) $urls ) );
	if ( ! $urls || ! function_exists( 'ffc_indexnow_key' ) || FFC_LIVE_HOST !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
		return;
	}
	$key = ffc_indexnow_key();
	wp_remote_post(
		'https://api.indexnow.org/indexnow',
		array(
			'blocking' => false,
			'timeout'  => 5,
			'headers'  => array( 'Content-Type' => 'application/json; charset=utf-8' ),
			'body'     => wp_json_encode(
				array(
					'host'        => FFC_LIVE_HOST,
					'key'         => $key,
					'keyLocation' => home_url( '/' . $key . '.txt' ),
					'urlList'     => array_slice( $urls, 0, 1000 ),
				)
			),
		)
	);
}

/*
 * The core plugin cleared the whole page cache on every save, even for pending facts, so
 * Googlebot kept meeting slow uncached pages while facts were being edited. Replace that.
 */
add_action(
	'init',
	static function () {
		remove_action( 'factfusion_facts_changed', array( 'FactFusion_Frontend', 'flush_caches' ) );
		add_action( 'factfusion_facts_changed', 'ffc_on_facts_changed' );
	}
);

function ffc_on_facts_changed() {
	delete_transient( FactFusion_Facts::POOL_TRANSIENT );
	$urls = ffc_facts_sync();
	// Category, page, profile and settings saves change many pages at once: clear everything.
	if ( ! in_array( 'FactFusion_Facts::changed', (array) wp_debug_backtrace_summary( null, 0, false ), true ) ) {
		ffc_purge( true );
		return;
	}
	if ( $urls ) {
		ffc_purge( array_merge( $urls, ffc_shared_urls() ) );
	}
}

/*
 * Old and archived fact addresses. Runs before the core router marks them as not found.
 */
add_filter(
	'pre_handle_404',
	static function ( $handled, $wp_query ) {
		$slug = sanitize_title( (string) $wp_query->get( FactFusion_Router::QUERY_VAR ) );
		if ( '' === $slug || '' !== (string) $wp_query->get( FactFusion_Router::VIEW_VAR ) ) {
			return $handled;
		}
		$fact = FactFusion_Facts::get_by_slug( $slug );
		if ( $fact ) {
			if ( 'archived' === $fact->status && ! current_user_can( factfusion_capability() ) ) {
				$category = $fact->category_id ? FactFusion_Categories::get( $fact->category_id ) : null;
				wp_safe_redirect( FactFusion_Router::browse_url( $category && $category->is_active ? $category : null ), 301 );
				exit;
			}
			return $handled;
		}
		$old = (array) get_option( 'ffc_old_slugs', array() );
		$now = isset( $old[ $slug ] ) ? FactFusion_Facts::get( (int) $old[ $slug ] ) : null;
		if ( $now && 'published' === $now->status && $now->slug !== $slug ) {
			wp_safe_redirect( FactFusion_Facts::url( $now ), 301 );
			exit;
		}
		return $handled;
	},
	5,
	2
);

/*
 * Fact of the Day: one fact for the whole day. The core plugin picked it again after every
 * save, so it could change in the middle of the day. Today's pick is kept in a log, which
 * also feeds the archive on /fact-of-the-day/.
 */
add_filter(
	'pre_transient_factfusion_fotd_' . wp_date( 'Ymd' ),
	static function ( $value ) {
		$log = (array) get_option( 'ffc_fotd_log', array() );
		$id  = (int) ( $log[ wp_date( 'Y-m-d' ) ] ?? 0 );
		if ( $id ) {
			$fact = FactFusion_Facts::get( $id );
			if ( $fact && 'published' === $fact->status ) {
				return $id;
			}
		}
		return $value;
	}
);

add_action(
	'set_transient_factfusion_fotd_' . wp_date( 'Ymd' ),
	static function ( $value ) {
		ffc_fotd_log( (int) $value );
	}
);

/**
 * Records today's fact in the log, once.
 *
 * @param int $id Fact ID.
 */
function ffc_fotd_log( $id ) {
	$log   = (array) get_option( 'ffc_fotd_log', array() );
	$today = wp_date( 'Y-m-d' );
	$fact  = isset( $log[ $today ] ) ? FactFusion_Facts::get( (int) $log[ $today ] ) : null;
	if ( $id && ( ! $fact || 'published' !== $fact->status ) && (int) ( $log[ $today ] ?? 0 ) !== $id ) {
		$log[ $today ] = $id;
		krsort( $log );
		update_option( 'ffc_fotd_log', array_slice( $log, 0, 120, true ), false );
	}
}

/** Today's fact, fixed for the day. */
function ffc_fotd_today() {
	$fact = FactFusion_Frontend::fact_of_the_day();
	if ( $fact ) {
		ffc_fotd_log( (int) $fact->id );
	}
	return $fact;
}
