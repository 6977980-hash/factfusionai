<?php
/**
 * SEO audit, phase 4: authority.
 *
 * - Open dataset: every published, verified fact as CSV and JSON under CC BY 4.0, rebuilt when
 *   facts change, with a download page that carries Dataset structured data.
 * - Facts API docs page. The API itself is the core engine's factfusion/v1; this file also
 *   makes sure it never returns a fact that is not verified.
 * - Quiz: "guess the number" questions built from published, verified facts.
 * - Public change log: site-wide changes (data/authority/changelog.php), facts added, updated
 *   and withdrawn, and the outcome of reader reports.
 *
 * The four pages are created once, published, with only a shortcode in them. Text an editor
 * adds around the shortcode is kept; the theme never rewrites these pages.
 */

defined( 'ABSPATH' ) || exit;

const FFC_AUTH_PAGES_VERSION = '1';
const FFC_AUTH_META          = '_ffc_authority';
const FFC_DATASET_DIR        = 'factfusion-data';
const FFC_DATASET_FILE       = 'factfusion-verified-facts';
const FFC_DATASET_LICENSE    = 'https://creativecommons.org/licenses/by/4.0/';

/** The phase 4 pages: slug => definition. */
function ffc_auth_pages() {
	return array(
		'open-data'  => array(
			'title'       => 'Open Data: Download Our Verified Facts',
			'meta_title'  => 'Free Verified Facts Dataset (CSV, JSON, CC BY 4.0)',
			'description' => 'Download every verified FactFusion fact as CSV or JSON, free under CC BY 4.0. Each fact has its claim, source links, source tier and verification date.',
			'shortcode'   => 'ffc_open_data',
		),
		'facts-api'  => array(
			'title'       => 'Facts API',
			'meta_title'  => 'Free Facts API: Verified, Sourced Facts as JSON',
			'description' => 'A free, read-only JSON API for verified facts. No key needed. Random facts, search, categories and the Fact of the Day, each with its sources.',
			'shortcode'   => 'ffc_api_docs',
		),
		'quiz'       => array(
			'title'       => 'Fact Quiz: Guess the Number',
			'meta_title'  => 'Science Fact Quiz: Guess the Number',
			'description' => 'How many moons does Saturn have? How deep is the ocean? A quick quiz built from verified, sourced facts, with the source behind every answer.',
			'shortcode'   => 'ffc_quiz',
		),
		'changelog'  => array(
			'title'       => 'Change Log and Corrections',
			'meta_title'  => 'Change Log and Corrections',
			'description' => 'Every change to FactFusion in the open: facts added, updated or withdrawn, corrections from reader reports, and changes to how we check facts.',
			'shortcode'   => 'ffc_changelog',
		),
	);
}

/**
 * Creates the phase 4 pages that do not exist yet. A page someone else already made with the
 * same address is left alone.
 */
function ffc_auth_create_pages() {
	if ( FFC_AUTH_PAGES_VERSION === get_option( 'ffc_auth_pages' ) || ! class_exists( 'FactFusion_Content' ) ) {
		return;
	}
	if ( ! add_option( 'ffc_auth_pages_lock', time(), '', false ) ) {
		if ( time() - (int) get_option( 'ffc_auth_pages_lock' ) < 300 ) {
			return;
		}
		update_option( 'ffc_auth_pages_lock', time(), false );
	}
	$created = array();
	foreach ( ffc_auth_pages() as $slug => $def ) {
		if ( get_page_by_path( $slug, OBJECT, 'page' ) ) {
			continue;
		}
		$id = wp_insert_post(
			wp_slash(
				array(
					'post_type'      => 'page',
					'post_status'    => 'publish',
					'post_title'     => $def['title'],
					'post_name'      => $slug,
					'post_content'   => '<!-- wp:shortcode -->[' . $def['shortcode'] . ']<!-- /wp:shortcode -->',
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
		update_post_meta( $id, FFC_AUTH_META, $slug );
		update_post_meta( $id, FactFusion_Content::TYPE_META, 'article' );
		update_post_meta( $id, FactFusion_Content::TITLE_META, $def['meta_title'] );
		update_post_meta( $id, FactFusion_Content::DESCRIPTION_META, $def['description'] );
		$created[] = get_permalink( $id );
	}
	update_option( 'ffc_auth_pages', FFC_AUTH_PAGES_VERSION, true );
	delete_option( 'ffc_auth_pages_lock' );
	delete_transient( 'factfusion_footer_links' );
	if ( $created && function_exists( 'ffc_indexnow' ) ) {
		ffc_indexnow( $created );
	}
}
add_action( 'init', 'ffc_auth_create_pages', 32 );

/** Address of one of the phase 4 pages, or '' when it is not live. */
function ffc_auth_url( $slug ) {
	$page = get_page_by_path( $slug, OBJECT, 'page' );
	return $page && 'publish' === $page->post_status ? get_permalink( $page ) : '';
}

/* -------------------------------------------------------------------------
 * Shared queries
 * ---------------------------------------------------------------------- */

/** Whether the facts table has the phase 2 date columns and the phase 3 question column. */
function ffc_auth_columns() {
	return array(
		'dates'    => defined( 'FFC_SEO2_DB_VERSION' ) && FFC_SEO2_DB_VERSION === get_option( 'ffc_seo2_db' ),
		'question' => function_exists( 'ffc_depth_db_ready' ) && ffc_depth_db_ready(),
	);
}

/**
 * Published, verified facts with their category, oldest first.
 *
 * @param string $where Extra SQL condition on the facts table (alias f), already safe.
 * @return object[]
 */
function ffc_auth_verified_facts( $where = '' ) {
	global $wpdb;
	$cols   = ffc_auth_columns();
	$select = 'f.id, f.fact_number, f.title, f.slug, f.claim, f.short_answer, f.explanation, f.confidence_score, f.is_ai_assisted, f.author_id, f.date_created, f.date_updated, f.date_verified, c.slug AS category_slug, c.name AS category_name';
	if ( $cols['dates'] ) {
		$select .= ', f.date_published, f.date_modified';
	}
	if ( $cols['question'] ) {
		$select .= ', f.question';
	}
	// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	return (array) $wpdb->get_results(
		$wpdb->prepare(
			"SELECT {$select} FROM %i f LEFT JOIN %i c ON c.id = f.category_id WHERE f.status = %s AND f.verification_status = %s" . ( $where ? " AND {$where}" : '' ) . ' ORDER BY f.fact_number ASC, f.id ASC',
			FactFusion_DB::table( 'facts' ),
			FactFusion_DB::table( 'categories' ),
			'published',
			'verified'
		)
	);
	// phpcs:enable
}

/**
 * Sources of every fact, best tier first.
 *
 * @return array<int,array<int,array>> Fact ID => list of sources.
 */
function ffc_auth_sources() {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$rows = (array) $wpdb->get_results(
		$wpdb->prepare(
			'SELECT l.fact_id, s.source_name, s.title, s.url, s.tier FROM %i l INNER JOIN %i s ON s.id = l.source_id ORDER BY s.tier ASC, s.source_name ASC',
			FactFusion_DB::table( 'fact_sources' ),
			FactFusion_DB::table( 'sources' )
		)
	);
	$out = array();
	foreach ( $rows as $row ) {
		$out[ (int) $row->fact_id ][] = array(
			'name'  => $row->source_name,
			'title' => $row->title,
			'url'   => $row->url,
			'tier'  => $row->tier,
		);
	}
	return $out;
}

/** A stored UTC datetime as Y-m-d, or null. */
function ffc_auth_day( $datetime ) {
	return $datetime && '0000-00-00 00:00:00' !== $datetime ? gmdate( 'Y-m-d', strtotime( $datetime . ' UTC' ) ) : null;
}

/* -------------------------------------------------------------------------
 * Open dataset
 * ---------------------------------------------------------------------- */

/** Fingerprint of everything the dataset contains. */
function ffc_dataset_signature() {
	global $wpdb;
	$cols = ffc_auth_columns();
	// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$facts = $wpdb->get_row(
		$wpdb->prepare(
			'SELECT COUNT(*) AS n, SUM(id) AS ids, MAX(date_verified) AS verified, MAX(' . ( $cols['dates'] ? 'date_modified' : 'date_updated' ) . ') AS modified FROM %i WHERE status = %s AND verification_status = %s',
			FactFusion_DB::table( 'facts' ),
			'published',
			'verified'
		),
		ARRAY_A
	);
	$links = $wpdb->get_var( $wpdb->prepare( 'SELECT CONCAT(COUNT(*), "-", COALESCE(SUM(source_id), 0)) FROM %i', FactFusion_DB::table( 'fact_sources' ) ) );
	// phpcs:enable
	return md5( wp_json_encode( array( $facts, $links, FFC_AUTH_PAGES_VERSION ) ) );
}

/** Folder and web address of the dataset files. */
function ffc_dataset_location() {
	$uploads = wp_upload_dir( null, false );
	return array(
		'dir' => trailingslashit( $uploads['basedir'] ) . FFC_DATASET_DIR . '/',
		'url' => trailingslashit( set_url_scheme( $uploads['baseurl'] ) ) . FFC_DATASET_DIR . '/',
	);
}

/** Details of the last build: sig, count, generated, csv_bytes, json_bytes, categories. */
function ffc_dataset_info() {
	return (array) get_option( 'ffc_dataset', array() );
}

/**
 * The dataset rows.
 *
 * @return array[]
 */
function ffc_dataset_rows() {
	$sources = ffc_auth_sources();
	$names   = array();
	$rows    = array();
	foreach ( ffc_auth_verified_facts() as $fact ) {
		$verifier = null;
		if ( $fact->author_id ) {
			if ( ! array_key_exists( (int) $fact->author_id, $names ) ) {
				$user                            = get_userdata( (int) $fact->author_id );
				$names[ (int) $fact->author_id ] = $user ? $user->display_name : null;
			}
			$verifier = $names[ (int) $fact->author_id ];
		}
		$published = ffc_auth_day( $fact->date_published ?? null ) ?? ffc_auth_day( $fact->date_created );
		$modified  = ffc_auth_day( $fact->date_modified ?? null ) ?? ffc_auth_day( $fact->date_updated );
		$rows[]    = array(
			'id'             => (int) $fact->id,
			'number'         => (int) $fact->fact_number,
			'title'          => $fact->title,
			'question'       => isset( $fact->question ) ? (string) $fact->question : '',
			'claim'          => $fact->claim,
			'short_answer'   => $fact->short_answer,
			'explanation'    => trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) $fact->explanation ) ) ),
			'category'       => (string) $fact->category_name,
			'category_slug'  => (string) $fact->category_slug,
			'url'            => FactFusion_Facts::url( $fact ),
			'sources'        => $sources[ (int) $fact->id ] ?? array(),
			'confidence'     => (int) $fact->confidence_score,
			'verified_date'  => ffc_auth_day( $fact->date_verified ),
			'verified_by'    => $verifier,
			'date_published' => $published,
			'date_modified'  => max( (string) $published, (string) $modified ),
			'ai_assisted'    => (bool) $fact->is_ai_assisted,
		);
	}
	return $rows;
}

/** Writes a file in one go so a download never sees half of it. */
function ffc_dataset_write( $path, $contents ) {
	$tmp = $path . '.tmp' . wp_rand();
	if ( false === file_put_contents( $tmp, $contents ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
		return false;
	}
	return rename( $tmp, $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
}

/**
 * Rebuilds the CSV and JSON files when the facts behind them changed.
 *
 * @param bool $force Rebuild even when nothing changed.
 * @return array Build details (see ffc_dataset_info()).
 */
function ffc_dataset_build( $force = false ) {
	if ( ! class_exists( 'FactFusion_DB' ) ) {
		return ffc_dataset_info();
	}
	$info  = ffc_dataset_info();
	$where = ffc_dataset_location();
	$sig   = ffc_dataset_signature();
	if ( ! $force && ( $info['sig'] ?? '' ) === $sig && is_readable( $where['dir'] . FFC_DATASET_FILE . '.csv' ) && is_readable( $where['dir'] . FFC_DATASET_FILE . '.json' ) ) {
		return $info;
	}
	if ( ! add_option( 'ffc_dataset_lock', time(), '', false ) ) {
		if ( time() - (int) get_option( 'ffc_dataset_lock' ) < 120 ) {
			return $info;
		}
		update_option( 'ffc_dataset_lock', time(), false );
	}
	if ( ! wp_mkdir_p( $where['dir'] ) ) {
		delete_option( 'ffc_dataset_lock' );
		return $info;
	}

	$rows       = ffc_dataset_rows();
	$generated  = gmdate( 'Y-m-d\TH:i:s+00:00' );
	$site       = get_bloginfo( 'name' );
	$categories = array();
	foreach ( $rows as $row ) {
		if ( '' !== $row['category'] ) {
			$categories[ $row['category'] ] = ( $categories[ $row['category'] ] ?? 0 ) + 1;
		}
	}

	$json = wp_json_encode(
		array(
			'name'        => $site . ' verified facts',
			'description' => 'Every fact published on ' . home_url( '/' ) . ' after an editor checked it against its source.',
			'license'     => 'CC BY 4.0',
			'license_url' => FFC_DATASET_LICENSE,
			'attribution' => 'Facts from ' . $site . ' (' . home_url( '/' ) . '), CC BY 4.0',
			'notes'       => 'Our text (titles, claims, answers, explanations) is CC BY 4.0. The linked source pages keep their own licenses.',
			'generated'   => $generated,
			'count'       => count( $rows ),
			'facts'       => $rows,
		),
		JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
	);

	$csv     = fopen( 'php://temp', 'w+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	$columns = array( 'id', 'number', 'title', 'question', 'claim', 'short_answer', 'explanation', 'category', 'category_slug', 'url', 'source_names', 'source_urls', 'source_tiers', 'confidence', 'verified_date', 'verified_by', 'date_published', 'date_modified', 'ai_assisted' );
	fputcsv( $csv, $columns, ',', '"', '' );
	foreach ( $rows as $row ) {
		$line = array();
		foreach ( $columns as $column ) {
			switch ( $column ) {
				case 'source_names':
				case 'source_urls':
				case 'source_tiers':
					$key    = array( 'source_names' => 'name', 'source_urls' => 'url', 'source_tiers' => 'tier' )[ $column ];
					$line[] = implode( ' | ', wp_list_pluck( $row['sources'], $key ) );
					break;
				case 'ai_assisted':
					$line[] = $row['ai_assisted'] ? 'yes' : 'no';
					break;
				default:
					$value = (string) ( $row[ $column ] ?? '' );
					// Spreadsheet apps run cells that start with these as formulas.
					$line[] = preg_match( '/^[=+\-@]/', $value ) ? "'" . $value : $value;
			}
		}
		fputcsv( $csv, $line, ',', '"', '' );
	}
	rewind( $csv );
	$csv_text = stream_get_contents( $csv );
	fclose( $csv ); // phpcs:ignore WordPress.WP.AlternativeFunctions

	$ok = ffc_dataset_write( $where['dir'] . FFC_DATASET_FILE . '.csv', $csv_text )
		&& ffc_dataset_write( $where['dir'] . FFC_DATASET_FILE . '.json', $json );
	if ( $ok ) {
		arsort( $categories );
		$info = array(
			'sig'        => $sig,
			'count'      => count( $rows ),
			'generated'  => $generated,
			'csv_bytes'  => strlen( $csv_text ),
			'json_bytes' => strlen( $json ),
			'categories' => $categories,
		);
		update_option( 'ffc_dataset', $info, false );
		if ( function_exists( 'ffc_purge' ) ) {
			ffc_purge( array_filter( array( ffc_auth_url( 'open-data' ) ) ) );
		}
	}
	delete_option( 'ffc_dataset_lock' );
	return $info;
}

add_action( 'ffc_dataset_build', 'ffc_dataset_build' );

// Rebuild a minute after facts change (so a burst of saves rebuilds once), and once a day.
add_action(
	'factfusion_facts_changed',
	static function () {
		if ( ! wp_next_scheduled( 'ffc_dataset_build' ) ) {
			wp_schedule_single_event( time() + MINUTE_IN_SECONDS, 'ffc_dataset_build' );
		}
		if ( function_exists( 'ffc_purge' ) ) {
			ffc_purge( array_filter( array( ffc_auth_url( 'changelog' ), ffc_auth_url( 'quiz' ) ) ) );
		}
	},
	20
);
add_action(
	'init',
	static function () {
		if ( ! wp_next_scheduled( 'ffc_dataset_daily' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'ffc_dataset_daily' );
		}
	},
	40
);
add_action( 'ffc_dataset_daily', 'ffc_dataset_build' );

/** File size for people: 1.2 MB. */
function ffc_auth_size( $bytes ) {
	return size_format( (int) $bytes, $bytes >= MB_IN_BYTES ? 1 : 0 );
}

/** Download links: format => url. */
function ffc_dataset_urls( $info ) {
	$where = ffc_dataset_location();
	$v     = substr( (string) ( $info['sig'] ?? '' ), 0, 8 );
	return array(
		'csv'  => $where['url'] . FFC_DATASET_FILE . '.csv?v=' . $v,
		'json' => $where['url'] . FFC_DATASET_FILE . '.json?v=' . $v,
	);
}

add_shortcode(
	'ffc_open_data',
	static function () {
		$info = ffc_dataset_info();
		if ( empty( $info['count'] ) || ! is_readable( ffc_dataset_location()['dir'] . FFC_DATASET_FILE . '.csv' ) ) {
			$info = ffc_dataset_build();
		}
		if ( empty( $info['count'] ) ) {
			return '<p>' . esc_html__( 'The dataset is being prepared. Please check back in a few minutes.', 'factfusion-ai' ) . '</p>';
		}
		$urls     = ffc_dataset_urls( $info );
		$site     = get_bloginfo( 'name' );
		$updated  = wp_date( 'j F Y', strtotime( $info['generated'] ) );
		$api      = ffc_auth_url( 'facts-api' );
		$log      = ffc_auth_url( 'changelog' );
		$verify   = class_exists( 'FactFusion_Pages' ) ? FactFusion_Pages::url( 'how-we-verify-facts' ) : '';
		$cats     = array();
		foreach ( (array) $info['categories'] as $name => $n ) {
			$cats[] = esc_html( $name ) . ' (' . (int) $n . ')';
		}
		$credit = sprintf( 'Facts from %s (%s), CC BY 4.0', $site, home_url( '/' ) );

		ob_start();
		?>
<div class="ffc-auth">
<p class="ffc-auth__lead"><?php echo esc_html( sprintf( 'Every fact on %1$s that an editor has checked against its source is free to download and reuse. There are %2$s verified facts in the dataset today, each with its claim, a short answer, an explanation, the sources it was checked against and the date it was verified.', $site, number_format_i18n( (int) $info['count'] ) ) ); ?></p>
<div class="ffc-auth__downloads">
<a class="wp-element-button" href="<?php echo esc_url( $urls['csv'] ); ?>" download><?php echo esc_html( 'Download CSV (' . ffc_auth_size( $info['csv_bytes'] ) . ')' ); ?></a>
<a class="wp-element-button" href="<?php echo esc_url( $urls['json'] ); ?>" download><?php echo esc_html( 'Download JSON (' . ffc_auth_size( $info['json_bytes'] ) . ')' ); ?></a>
</div>
<p class="ff-meta"><?php echo esc_html( 'Last updated ' . $updated . '. The files are rebuilt automatically whenever a fact is added, changed or withdrawn.' ); ?></p>

<h2 class="wp-block-heading">License: CC BY 4.0</h2>
<p>You may copy, share and adapt the facts for any purpose, including commercial use, as long as you credit us with a link. <a href="<?php echo esc_url( FFC_DATASET_LICENSE ); ?>" rel="license">Read the CC BY 4.0 license</a>.</p>
<p>Suggested credit:</p>
<pre class="ffc-auth__code"><?php echo esc_html( $credit ); ?></pre>
<p>The license covers our own text: titles, claims, short answers and explanations. The source pages we link to belong to their publishers and keep their own licenses.</p>

<h2 class="wp-block-heading">What is in the dataset</h2>
<p>Only facts that are published on the site and marked verified. Drafts, facts waiting for review and withdrawn facts are left out. Categories: <?php echo wp_kses_post( implode( ', ', $cats ) ); ?>.</p>
<table class="ffc-auth__table">
<thead><tr><th>Field</th><th>What it means</th></tr></thead>
<tbody>
<tr><td><code>id</code>, <code>number</code></td><td>Our ID and the fact's number on the site.</td></tr>
<tr><td><code>title</code></td><td>The fact as a headline.</td></tr>
<tr><td><code>question</code></td><td>The question the fact answers, where we have written one.</td></tr>
<tr><td><code>claim</code></td><td>The single claim that was checked against the source.</td></tr>
<tr><td><code>short_answer</code>, <code>explanation</code></td><td>A one or two sentence answer, and the longer explanation from the fact page (plain text).</td></tr>
<tr><td><code>category</code>, <code>category_slug</code></td><td>The subject the fact is filed under.</td></tr>
<tr><td><code>url</code></td><td>The fact's page on our site. Please link here when you credit us.</td></tr>
<tr><td><code>sources</code></td><td>Name, title, link and tier of each source. In the CSV these are <code>source_names</code>, <code>source_urls</code> and <code>source_tiers</code>, separated by <code>|</code>.</td></tr>
<tr><td><code>confidence</code></td><td>The editor's confidence score out of 100.</td></tr>
<tr><td><code>verified_date</code>, <code>verified_by</code></td><td>When the fact was last checked against its source, and by whom.</td></tr>
<tr><td><code>date_published</code>, <code>date_modified</code></td><td>When the fact went live, and when its text last changed.</td></tr>
<tr><td><code>ai_assisted</code></td><td>Whether AI helped draft the wording. An editor still checked it against the source.</td></tr>
</tbody>
</table>
<p>Source tiers: A is a government science agency or peer-reviewed research, B is a respected reference work such as an encyclopedia, C and D are other publishers.<?php echo $verify ? ' <a href="' . esc_url( $verify ) . '">How we verify facts</a>.' : ''; ?></p>

<h2 class="wp-block-heading">Other ways to use the facts</h2>
<ul>
<?php if ( $api ) : ?>
<li><a href="<?php echo esc_url( $api ); ?>">Facts API</a>: random facts, search and categories as JSON, free with no key.</li>
<?php endif; ?>
<?php if ( $log ) : ?>
<li><a href="<?php echo esc_url( $log ); ?>">Change log and corrections</a>: what changed in the facts, and when.</li>
<?php endif; ?>
<li>Found a mistake? Use "Report a problem with this fact" on the fact's page and we will check it against the source.</li>
</ul>
</div>
		<?php
		return ffc_auth_css() . ob_get_clean();
	}
);

/* Dataset structured data, for Google Dataset Search. */
add_action(
	'wp_head',
	static function () {
		if ( ! is_page( 'open-data' ) || ! get_post_meta( get_queried_object_id(), FFC_AUTH_META, true ) ) {
			return;
		}
		$info = ffc_dataset_info();
		if ( empty( $info['count'] ) ) {
			return;
		}
		$urls = ffc_dataset_urls( $info );
		$site = get_bloginfo( 'name' );
		$data = array(
			'@context'            => 'https://schema.org',
			'@type'               => 'Dataset',
			'name'                => $site . ' Verified Facts',
			'description'         => sprintf( '%d short facts about space, the ocean, animals, science, history and more. Each fact has one claim, a short answer, an explanation, links to the sources an editor checked it against, the source tier (A to D), a confidence score and the date it was verified. Only published, verified facts are included.', (int) $info['count'] ),
			'url'                 => get_permalink(),
			'license'             => FFC_DATASET_LICENSE,
			'isAccessibleForFree' => true,
			'creator'             => array(
				'@type' => 'Organization',
				'name'  => $site,
				'url'   => home_url( '/' ),
			),
			'dateModified'        => $info['generated'],
			'keywords'            => array_merge( array( 'facts', 'fact checking', 'trivia', 'open data' ), array_keys( (array) $info['categories'] ) ),
			'distribution'        => array(
				array(
					'@type'          => 'DataDownload',
					'encodingFormat' => 'text/csv',
					'contentUrl'     => $urls['csv'],
				),
				array(
					'@type'          => 'DataDownload',
					'encodingFormat' => 'application/json',
					'contentUrl'     => $urls['json'],
				),
			),
		);
		echo '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "</script>\n";
	},
	20
);

/* -------------------------------------------------------------------------
 * Facts API
 * ---------------------------------------------------------------------- */

/*
 * Publishing already requires "Verified", but the API is a public promise, so it checks again:
 * a fact that is not verified is never returned.
 */
add_filter(
	'rest_request_after_callbacks',
	static function ( $response, $handler, $request ) {
		$route = $request->get_route();
		if ( 0 !== strpos( $route, '/factfusion/v1/' ) || is_wp_error( $response ) || ! class_exists( 'FactFusion_Facts' ) ) {
			return $response;
		}
		$path    = substr( $route, strlen( '/factfusion/v1' ) );
		$wrapped = $response instanceof WP_REST_Response;
		$data    = $wrapped ? $response->get_data() : $response;
		if ( ! is_array( $data ) ) {
			return $response;
		}
		$single = in_array( $path, array( '/random', '/fact-of-the-day' ), true ) || preg_match( '#^/facts/\d+$#', $path );
		if ( $single ) {
			if ( array_key_exists( 'verified_date', $data ) && empty( $data['verified_date'] ) ) {
				return new WP_Error( 'factfusion_not_found', __( 'Fact not found.', 'factfusion-ai' ), array( 'status' => 404 ) );
			}
			return $response;
		}
		if ( '/facts' === $path || '/search' === $path ) {
			$data = array_values(
				array_filter(
					$data,
					static function ( $item ) use ( $path ) {
						if ( '/facts' === $path ) {
							return ! empty( $item['verified_date'] );
						}
						$fact = isset( $item['id'] ) ? FactFusion_Facts::get( (int) $item['id'] ) : null;
						return $fact && 'verified' === $fact->verification_status;
					}
				)
			);
			if ( $wrapped ) {
				$response->set_data( $data );
				return $response;
			}
			return $data;
		}
		return $response;
	},
	10,
	3
);

add_shortcode(
	'ffc_api_docs',
	static function () {
		$base    = untrailingslashit( rest_url( 'factfusion/v1' ) );
		$per_ip  = class_exists( 'FactFusion_Settings' ) ? max( FactFusion_Settings::MIN_FACT_FETCH_PER_HOUR, (int) FactFusion_Settings::get( 'rate_fact_hour' ) ) : 100;
		$search  = class_exists( 'FactFusion_Rate_Limiter' ) ? FactFusion_Rate_Limiter::SEARCHES_PER_HOUR : 600;
		$data    = ffc_auth_url( 'open-data' );
		$site    = get_bloginfo( 'name' );
		$example = $base . '/random?category=space';
		$routes  = array(
			array( '/facts', 'page, per_page (1 to 50, default 20), category, include (list of IDs)', 'Verified facts, newest first. The total is in the X-WP-Total and X-WP-TotalPages headers.' ),
			array( '/facts/{id}', '', 'One fact, with its full explanation.' ),
			array( '/random', 'category, exclude (an ID)', 'One random fact, with its explanation.' ),
			array( '/search', 'q (at least 2 characters), limit (1 to 20)', 'Facts that match the words, with ID, title, claim and link.' ),
			array( '/categories', '', 'Every category with its fact count and page.' ),
			array( '/fact-of-the-day', '', 'Today\'s Fact of the Day. The same fact all day.' ),
		);
		ob_start();
		?>
<div class="ffc-auth">
<p class="ffc-auth__lead"><?php echo esc_html( 'The ' . $site . ' Facts API gives you our verified facts as JSON. It is free, read-only and needs no key. Every fact comes with its sources, their tier, the editor\'s confidence score and the date it was verified.' ); ?></p>
<p>Base address:</p>
<pre class="ffc-auth__code"><?php echo esc_html( $base ); ?></pre>
<p>Try it: <a href="<?php echo esc_url( $example ); ?>" rel="nofollow"><?php echo esc_html( $example ); ?></a></p>

<h2 class="wp-block-heading">Endpoints</h2>
<p>All endpoints use GET. Category is a category slug such as <code>space</code>, <code>ocean</code> or <code>animal</code> (see <code>/categories</code>).</p>
<table class="ffc-auth__table">
<thead><tr><th>Endpoint</th><th>Options</th><th>Returns</th></tr></thead>
<tbody>
<?php foreach ( $routes as $r ) : ?>
<tr><td><code><?php echo esc_html( $r[0] ); ?></code></td><td><?php echo esc_html( $r[1] ? $r[1] : 'None' ); ?></td><td><?php echo esc_html( $r[2] ); ?></td></tr>
<?php endforeach; ?>
</tbody>
</table>

<h2 class="wp-block-heading">What a fact looks like</h2>
<pre class="ffc-auth__code">{
  "id": 189,
  "number": 189,
  "title": "A day on Venus is longer than its year",
  "slug": "a-day-on-venus-is-longer-than-its-year",
  "url": "<?php echo esc_html( home_url( '/fact/a-day-on-venus-is-longer-than-its-year/' ) ); ?>",
  "claim": "…",
  "short_answer": "…",
  "category": { "slug": "space", "name": "Space", "icon": "…" },
  "sources": [ { "name": "NASA", "title": "…", "url": "https://…", "tier": "A" } ],
  "confidence": 90,
  "verified_date": "2026-10-13",
  "verified_by": "…",
  "ai_assisted": true,
  "embed_url": "…",
  "explanation": "&lt;p&gt;…&lt;/p&gt;"
}</pre>
<p>Lists leave out <code>explanation</code>. <code>ai_assisted</code> is true when AI helped draft the wording; an editor still checked the fact against its source. Only published, verified facts are ever returned.</p>

<h2 class="wp-block-heading">Example</h2>
<pre class="ffc-auth__code">fetch('<?php echo esc_html( $example ); ?>')
  .then(r =&gt; r.json())
  .then(fact =&gt; console.log(fact.title, fact.sources[0].url));</pre>
<p>The API answers requests from any website (CORS), so you can call it from a browser.</p>

<h2 class="wp-block-heading">Limits and terms</h2>
<ul>
<li><?php echo esc_html( sprintf( 'Up to %1$s fact requests and %2$s searches an hour from one IP address. Over the limit you get HTTP 429 with a Retry-After header.', number_format_i18n( $per_ip ), number_format_i18n( $search ) ) ); ?></li>
<li><?php if ( $data ) : ?>Need every fact? Download the <a href="<?php echo esc_url( $data ); ?>">open dataset</a> instead of paging through the API.<?php else : ?>Need every fact? Page through <code>/facts</code> with <code>per_page=50</code>.<?php endif; ?></li>
<li>The facts are licensed <a href="<?php echo esc_url( FFC_DATASET_LICENSE ); ?>" rel="license">CC BY 4.0</a>. Show the fact's source and link to the fact's <code>url</code> on our site.</li>
<li>The API may change. Breaking changes will get a new version (<code>/factfusion/v2</code>) and a note on our change log.</li>
</ul>
</div>
		<?php
		return ffc_auth_css() . ob_get_clean();
	}
);

/* -------------------------------------------------------------------------
 * Quiz
 * ---------------------------------------------------------------------- */

/**
 * Quiz questions: verified facts whose title has a number in it. The reader picks the right
 * number; the other choices are clearly quiz options, never shown as facts.
 *
 * @return array[]
 */
function ffc_quiz_items() {
	$cached = get_transient( 'ffc_quiz_items' );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	$sources = ffc_auth_sources();
	$items   = array();
	foreach ( ffc_auth_verified_facts( "f.title REGEXP '[0-9]'" ) as $fact ) {
		if ( ! preg_match( '/\d/', $fact->title ) ) {
			continue;
		}
		$source  = $sources[ (int) $fact->id ][0] ?? null;
		$items[] = array(
			't' => $fact->title,
			'a' => wp_strip_all_tags( (string) $fact->short_answer ),
			'u' => FactFusion_Facts::url( $fact ),
			'c' => (string) $fact->category_slug,
			's' => $source ? $source['name'] : '',
		);
	}
	set_transient( 'ffc_quiz_items', $items, HOUR_IN_SECONDS );
	return $items;
}
add_action(
	'factfusion_facts_changed',
	static function () {
		delete_transient( 'ffc_quiz_items' );
	}
);

add_shortcode(
	'ffc_quiz',
	static function () {
		$items = ffc_quiz_items();
		if ( count( $items ) < 5 ) {
			return '<p>' . esc_html__( 'The quiz needs a few more verified facts. Please check back soon.', 'factfusion-ai' ) . '</p>';
		}
		$cats = array();
		foreach ( $items as $item ) {
			$cats[ $item['c'] ] = ( $cats[ $item['c'] ] ?? 0 ) + 1;
		}
		$names = array();
		if ( class_exists( 'FactFusion_Categories' ) ) {
			foreach ( FactFusion_Categories::all( true ) as $category ) {
				$names[ $category->slug ] = $category->name;
			}
		}
		$options = '<option value="">' . esc_html__( 'All subjects', 'factfusion-ai' ) . '</option>';
		foreach ( $cats as $slug => $n ) {
			if ( $n >= 5 && isset( $names[ $slug ] ) ) {
				$options .= '<option value="' . esc_attr( $slug ) . '">' . esc_html( $names[ $slug ] ) . '</option>';
			}
		}
		ob_start();
		?>
<div class="ffc-auth ffc-quiz" data-ffc-quiz>
<p class="ffc-auth__lead">Each question is a real fact with one number hidden. Pick the right number, then see the fact and the source it was checked against. Ten questions a round.</p>
<p><label>Subject: <select data-ffc-quiz-cat><?php echo $options; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above. ?></select></label></p>
<div class="ffc-quiz__card" data-ffc-quiz-card aria-live="polite">
<noscript><p>The quiz needs JavaScript. You can still <a href="<?php echo esc_url( class_exists( 'FactFusion_Router' ) ? FactFusion_Router::browse_url() : home_url( '/' ) ); ?>">browse every fact</a>.</p></noscript>
</div>
<script type="application/json" data-ffc-quiz-data><?php echo wp_json_encode( $items, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE ); ?></script>
<script>
(function () {
	var root = document.querySelector('[data-ffc-quiz]');
	if (!root) { return; }
	var all = JSON.parse(root.querySelector('[data-ffc-quiz-data]').textContent);
	var card = root.querySelector('[data-ffc-quiz-card]');
	var select = root.querySelector('[data-ffc-quiz-cat]');
	var round = [], at = 0, score = 0;
	var NUM = /\d[\d,]*(?:\.\d+)?/;

	function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
	function shuffle(a) { for (var i = a.length - 1; i > 0; i--) { var j = Math.floor(Math.random() * (i + 1)); var t = a[i]; a[i] = a[j]; a[j] = t; } return a; }
	function fmt(n, dec, commas) {
		var s = n.toFixed(dec);
		if (commas) { var p = s.split('.'); p[0] = p[0].replace(/\B(?=(\d{3})+(?!\d))/g, ','); s = p.join('.'); }
		return s;
	}
	function choices(raw, after) {
		var v = parseFloat(raw.replace(/,/g, ''));
		var dec = (raw.split('.')[1] || '').length;
		var commas = raw.indexOf(',') > -1;
		var out = [raw], seen = {}; seen[raw] = 1;
		var year = !dec && !commas && v >= 1000 && v <= new Date().getFullYear();
		var pct = /^\s*(%|percent)/.test(after);
		var tries = year ? [-50, -30, -20, -10, 10, 20, 30] : [0.5, 2, 3, 0.25, 10, 0.1, 4, 1.5];
		shuffle(tries);
		for (var i = 0; i < tries.length && out.length < 3; i++) {
			var n = year ? v + tries[i] : v * tries[i];
			if (year && n > new Date().getFullYear()) { continue; }
			if (pct && n > 100) { continue; }
			if (!dec && n < 1) { continue; }
			var s = fmt(dec ? n : Math.round(n), dec, commas || n >= 10000);
			if (!seen[s] && parseFloat(s.replace(/,/g, '')) > 0) { seen[s] = 1; out.push(s); }
		}
		return out.length === 3 ? shuffle(out) : null;
	}
	function build(item) {
		var m = item.t.match(NUM);
		if (!m || parseFloat(m[0].replace(/,/g, '')) === 0) { return null; }
		var opts = choices(m[0], item.t.slice(m.index + m[0].length));
		if (!opts) { return null; }
		return { item: item, answer: m[0], opts: opts, text: item.t.slice(0, m.index) + '___' + item.t.slice(m.index + m[0].length) };
	}
	function start() {
		var cat = select.value, pool = [];
		shuffle(all.slice()).forEach(function (it) {
			if (pool.length < 10 && (!cat || it.c === cat)) { var q = build(it); if (q) { pool.push(q); } }
		});
		round = pool; at = 0; score = 0; show();
	}
	function show() {
		if (!round.length) {
			card.innerHTML = '<p>No questions for this subject yet. Try another one.</p>';
			return;
		}
		if (at >= round.length) {
			card.innerHTML = '<p class="ffc-quiz__q">You got ' + score + ' out of ' + round.length + '.</p><button type="button" class="wp-element-button" data-again>Play again</button>';
			card.querySelector('[data-again]').onclick = start;
			return;
		}
		var q = round[at];
		var html = '<p class="ff-meta">Question ' + (at + 1) + ' of ' + round.length + ' · Score ' + score + '</p><p class="ffc-quiz__q">' + esc(q.text) + '</p><div class="ffc-quiz__opts">';
		q.opts.forEach(function (o) { html += '<button type="button" class="ffc-quiz__opt" data-o="' + esc(o) + '">' + esc(o) + '</button>'; });
		card.innerHTML = html + '</div><div data-result></div>';
		Array.prototype.forEach.call(card.querySelectorAll('[data-o]'), function (b) {
			b.onclick = function () { answer(q, b.getAttribute('data-o')); };
		});
	}
	function answer(q, picked) {
		var right = picked === q.answer;
		if (right) { score++; }
		Array.prototype.forEach.call(card.querySelectorAll('[data-o]'), function (b) {
			b.disabled = true;
			var o = b.getAttribute('data-o');
			if (o === q.answer) { b.className += ' is-right'; } else if (o === picked) { b.className += ' is-wrong'; }
		});
		card.querySelector('[data-result]').innerHTML =
			'<p class="ffc-quiz__verdict"><strong>' + (right ? 'Right!' : 'Not quite. It is ' + esc(q.answer) + '.') + '</strong> ' + esc(q.item.t) + '.</p>' +
			'<p>' + esc(q.item.a) + '</p>' +
			'<p class="ff-meta"><a href="' + esc(q.item.u) + '">Read the fact</a>' + (q.item.s ? ' · Source: ' + esc(q.item.s) : '') + '</p>' +
			'<button type="button" class="wp-element-button" data-next>' + (at + 1 < round.length ? 'Next question' : 'See your score') + '</button>';
		card.querySelector('[data-next]').onclick = function () { at++; show(); };
	}
	select.onchange = start;
	start();
})();
</script>
<p class="ff-meta">Wrong choices are made up for the quiz. Only the highlighted right answer is a verified fact.</p>
</div>
		<?php
		return ffc_auth_css() . ob_get_clean();
	}
);

/* -------------------------------------------------------------------------
 * Change log
 * ---------------------------------------------------------------------- */

add_shortcode(
	'ffc_changelog',
	static function () {
		global $wpdb;
		$entries = (array) include get_stylesheet_directory() . '/data/authority/changelog.php';
		$cols    = ffc_auth_columns();
		$table   = FactFusion_DB::table( 'facts' );
		$since   = gmdate( 'Y-m-d H:i:s', time() - 90 * DAY_IN_SECONDS );
		$events  = array(); // Y-m-d => kind => list of html.
		$link    = static function ( $fact ) {
			return '<a href="' . esc_url( FactFusion_Facts::url( $fact ) ) . '">' . esc_html( $fact->title ) . '</a>';
		};

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		if ( $cols['dates'] ) {
			$live = $wpdb->get_results( $wpdb->prepare( 'SELECT id, title, slug, date_published, date_modified FROM %i WHERE status = %s AND verification_status = %s AND (date_published >= %s OR date_modified >= %s) ORDER BY date_modified DESC LIMIT 500', $table, 'published', 'verified', $since, $since ) );
			foreach ( $live as $fact ) {
				if ( empty( $fact->date_published ) ) {
					continue;
				}
				if ( $fact->date_published >= $since ) {
					$events[ ffc_auth_day( $fact->date_published ) ]['added'][] = $link( $fact );
				}
				if ( $fact->date_modified && strtotime( $fact->date_modified ) - strtotime( $fact->date_published ) > HOUR_IN_SECONDS && $fact->date_modified >= $since ) {
					$events[ ffc_auth_day( $fact->date_modified ) ]['updated'][] = $link( $fact );
				}
			}
			$gone = $wpdb->get_results( $wpdb->prepare( 'SELECT id, title, date_updated FROM %i WHERE status = %s AND date_published IS NOT NULL AND date_updated >= %s ORDER BY date_updated DESC LIMIT 200', $table, 'archived', $since ) );
			foreach ( $gone as $fact ) {
				$events[ ffc_auth_day( $fact->date_updated ) ]['withdrawn'][] = esc_html( $fact->title );
			}
		}
		$reports = class_exists( 'FactFusion_Corrections' ) ? FactFusion_Corrections::status_counts() : array();
		$fixed   = $wpdb->get_results( $wpdb->prepare( 'SELECT c.resolved_at, f.id, f.title, f.slug, f.status, f.verification_status FROM %i c LEFT JOIN %i f ON f.id = c.fact_id WHERE c.status = %s AND c.resolved_at IS NOT NULL ORDER BY c.resolved_at DESC LIMIT 50', FactFusion_DB::table( 'corrections' ), $table, 'resolved' ) );
		// phpcs:enable
		krsort( $events );

		$labels = array(
			'added'     => array( 'fact added', 'facts added' ),
			'updated'   => array( 'fact updated', 'facts updated' ),
			'withdrawn' => array( 'fact withdrawn', 'facts withdrawn' ),
		);
		ob_start();
		?>
<div class="ffc-auth">
<p class="ffc-auth__lead">Everything that changes on this site is listed here: changes to how we check facts, facts we add, update or withdraw, and what happened to the problems readers report. Spotted a mistake? Use "Report a problem with this fact" on the fact's page.</p>

<h2 class="wp-block-heading">Changes to the site and how we check facts</h2>
<ul class="ffc-log">
<?php foreach ( $entries as $e ) : ?>
<li><time datetime="<?php echo esc_attr( $e['date'] ); ?>"><?php echo esc_html( wp_date( 'j M Y', strtotime( $e['date'] . ' 12:00 UTC' ) ) ); ?></time> <strong><?php echo esc_html( $e['title'] ); ?></strong><br><?php echo esc_html( $e['text'] ); ?></li>
<?php endforeach; ?>
</ul>

<h2 class="wp-block-heading">Reader reports and corrections</h2>
<?php
$total = array_sum( array_map( 'intval', $reports ) );
if ( $total ) :
	?>
<p><?php echo esc_html( sprintf( 'Readers have sent %1$s reports about our facts. %2$s were resolved, %3$s were checked and closed with no change needed, and %4$s are still open.', number_format_i18n( $total ), number_format_i18n( (int) ( $reports['resolved'] ?? 0 ) ), number_format_i18n( (int) ( $reports['rejected'] ?? 0 ) ), number_format_i18n( (int) ( $reports['new'] ?? 0 ) + (int) ( $reports['in_review'] ?? 0 ) ) ) ); ?></p>
<?php else : ?>
<p>No reader reports yet.</p>
<?php endif; ?>
<?php if ( $fixed ) : ?>
<ul class="ffc-log">
	<?php foreach ( $fixed as $r ) : ?>
<li><time datetime="<?php echo esc_attr( ffc_auth_day( $r->resolved_at ) ); ?>"><?php echo esc_html( wp_date( 'j M Y', strtotime( $r->resolved_at . ' UTC' ) ) ); ?></time> Report resolved: <?php echo $r->id && 'published' === $r->status && 'verified' === $r->verification_status ? $link( $r ) : esc_html( $r->title ? $r->title . ' (no longer published)' : 'a fact that has since been removed' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in $link. ?></li>
	<?php endforeach; ?>
</ul>
<?php endif; ?>

<h2 class="wp-block-heading">Fact changes in the last 90 days</h2>
<?php if ( ! $events ) : ?>
<p>No facts were added, updated or withdrawn in the last 90 days.</p>
<?php else : ?>
<ul class="ffc-log">
	<?php
	foreach ( $events as $day => $kinds ) :
		$parts = array();
		foreach ( $labels as $kind => $words ) {
			if ( ! empty( $kinds[ $kind ] ) ) {
				$n       = count( $kinds[ $kind ] );
				$parts[] = $n . ' ' . ( 1 === $n ? $words[0] : $words[1] );
			}
		}
		?>
<li><time datetime="<?php echo esc_attr( $day ); ?>"><?php echo esc_html( wp_date( 'j M Y', strtotime( $day . ' 12:00 UTC' ) ) ); ?></time> <strong><?php echo esc_html( implode( ', ', $parts ) ); ?></strong>
		<?php
		foreach ( $labels as $kind => $words ) :
			if ( empty( $kinds[ $kind ] ) ) {
				continue;
			}
			?>
<details><summary><?php echo esc_html( ucfirst( $words[1] ) ); ?></summary><ul><li><?php echo implode( '</li><li>', $kinds[ $kind ] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above. ?></li></ul></details>
		<?php endforeach; ?>
</li>
	<?php endforeach; ?>
</ul>
<?php endif; ?>
<p class="ff-meta">"Updated" means the fact's text or category changed. Each fact page also shows its own last-updated and last-verified dates.</p>
</div>
		<?php
		return ffc_auth_css() . ob_get_clean();
	}
);

/* -------------------------------------------------------------------------
 * Shared bits: styles, footer and llms.txt links
 * ---------------------------------------------------------------------- */

/** Styles for the phase 4 pages, printed once. */
function ffc_auth_css() {
	static $done = false;
	if ( $done ) {
		return '';
	}
	$done = true;
	return '<style>
.ffc-auth__lead{font-size:1.1em}
.ffc-auth__downloads{display:flex;flex-wrap:wrap;gap:.75rem;margin:1.25rem 0 .5rem}
.ffc-auth__code{white-space:pre-wrap;word-break:break-word;padding:1rem;border-radius:8px;background:rgba(127,127,127,.12);font-size:.9em}
.ffc-auth__table{width:100%;border-collapse:collapse;margin:1rem 0;font-size:.95em}
.ffc-auth__table th,.ffc-auth__table td{text-align:left;vertical-align:top;padding:.5rem;border-bottom:1px solid rgba(127,127,127,.3)}
@media (max-width:600px){.ffc-auth__table{display:block;overflow-x:auto}}
.ffc-log{list-style:none;padding:0}
.ffc-log>li{padding:.75rem 0;border-bottom:1px solid rgba(127,127,127,.25)}
.ffc-log time{display:inline-block;min-width:7.5em;font-weight:600}
.ffc-log details{margin:.4rem 0 0 0}
.ffc-quiz__card{padding:1.25rem;border:1px solid rgba(127,127,127,.3);border-radius:12px;margin:1rem 0}
.ffc-quiz__q{font-size:1.25em;font-weight:600}
.ffc-quiz__opts{display:flex;flex-wrap:wrap;gap:.75rem;margin:1rem 0}
.ffc-quiz__opt{font:inherit;font-size:1.1em;padding:.6rem 1.4rem;border-radius:8px;border:2px solid rgba(127,127,127,.5);background:transparent;color:inherit;cursor:pointer;min-width:5em}
.ffc-quiz__opt:hover:not(:disabled){border-color:currentColor}
.ffc-quiz__opt.is-right{border-color:#1a7f37;background:rgba(26,127,55,.15)}
.ffc-quiz__opt.is-wrong{border-color:#cf222e;background:rgba(207,34,46,.12)}
</style>';
}

/* An "Open data" column in the footer. */
add_filter(
	'do_shortcode_tag',
	static function ( $output, $tag ) {
		if ( 'factfusion_footer_links' !== $tag ) {
			return $output;
		}
		$links = array();
		foreach ( array( 'open-data' => 'Open dataset', 'facts-api' => 'Facts API', 'quiz' => 'Fact quiz', 'changelog' => 'Change log' ) as $slug => $label ) {
			$url = ffc_auth_url( $slug );
			if ( $url ) {
				$links[] = '<li><a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a></li>';
			}
		}
		if ( ! $links ) {
			return $output;
		}
		return $output . '<div class="ff-footer__col"><p class="ff-footer__heading">Open data</p><ul>' . implode( '', $links ) . '</ul></div>';
	},
	10,
	2
);
