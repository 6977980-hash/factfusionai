<?php
/**
 * One-time bulk import of the open-source science facts dataset
 * (github.com/Royal-lobster/science-facts-project, MIT) from data/facts-10k.json.
 *
 * Every fact goes in as "pending", "unverified" and not indexable, so no new
 * public pages appear until a person checks and publishes each one.
 * Quality from the dataset is kept in confidence_score: A = 60, B = 30, C = 10.
 *
 * Tools → Import 10k Facts.
 */

defined( 'ABSPATH' ) || exit;

const FFC_BULK_FILE   = __DIR__ . '/../data/facts-10k.json';
const FFC_BULK_OFFSET = 'ffc_bulk_offset';
const FFC_BULK_STATS  = 'ffc_bulk_stats';
const FFC_BULK_BATCH  = 100;

/** Categories the dataset needs beyond the 12 the site started with. */
function ffc_bulk_new_categories() {
	return array(
		array( 'dinosaurs-fossils', 'Dinosaurs & Fossils', '🦖', 'Dinosaurs, fossils and life before humans.' ),
		array( 'tiny-life', 'Tiny Life: Germs & Parasites', '🦠', 'Bacteria, viruses, parasites and the microscopic world.' ),
		array( 'sleep-dreams', 'Sleep & Dreams', '😴', 'Why we sleep, how we dream and what happens at night.' ),
		array( 'language-words', 'Language & Words', '🗣️', 'Where words come from and how languages work.' ),
		array( 'money-economics', 'Money & Economics', '💰', 'Money, markets and the science of choices.' ),
	);
}

function ffc_bulk_seed_categories() {
	foreach ( ffc_bulk_new_categories() as $order => $row ) {
		if ( FactFusion_Categories::get_by_slug( $row[0] ) ) {
			continue;
		}
		FactFusion_Categories::save(
			array(
				'slug'        => $row[0],
				'name'        => $row[1],
				'icon'        => $row[2],
				'description' => $row[3],
				'sort_order'  => 130 + $order * 10,
				'is_active'   => 1,
			)
		);
	}
}

function ffc_bulk_normalize( $text ) {
	return trim( preg_replace( '/[^a-z0-9]+/', ' ', strtolower( (string) $text ) ) );
}

/** Short title from the claim: first ~90 characters, cut at a word. */
function ffc_bulk_title( $claim ) {
	$claim = trim( $claim );
	if ( mb_strlen( $claim ) <= 90 ) {
		return rtrim( $claim, ' .' );
	}
	$cut = mb_substr( $claim, 0, 90 );
	$cut = preg_replace( '/\s+\S*$/u', '', $cut );
	return rtrim( $cut, ' ,;:-—' ) . '…';
}

function ffc_bulk_tier( $url ) {
	$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
	if ( preg_match( '/(nih\.gov|nature\.com|nobelprize\.org|harvard\.edu|nasa\.gov)$/', $host ) ) {
		return 'A';
	}
	if ( preg_match( '/(wikipedia\.org|wikidata\.org|britannica\.com)$/', $host ) ) {
		return 'B';
	}
	return 'C';
}

function ffc_bulk_source_id( $url ) {
	$source = FactFusion_Sources::find_by_url( $url );
	if ( $source ) {
		return (int) $source->id;
	}
	$host = preg_replace( '/^www\./', '', (string) wp_parse_url( $url, PHP_URL_HOST ) );
	$path = (string) wp_parse_url( $url, PHP_URL_PATH );
	$name = str_contains( $host, 'wikipedia.org' ) && str_starts_with( $path, '/wiki/' )
		? 'Wikipedia: ' . str_replace( '_', ' ', rawurldecode( substr( $path, 6 ) ) )
		: $host;
	$id = FactFusion_Sources::save(
		array(
			'source_name'   => mb_substr( $name, 0, 180 ),
			'url'           => $url,
			'tier'          => ffc_bulk_tier( $url ),
			'date_accessed' => gmdate( 'Y-m-d' ),
			'notes'         => 'Imported with the science-facts-project dataset. Not yet checked by an editor.',
			'is_active'     => 1,
		)
	);
	return is_wp_error( $id ) ? 0 : (int) $id;
}

/** Imports the next batch. Returns stats for the whole run so far. */
function ffc_bulk_run_batch() {
	global $wpdb;

	$facts  = json_decode( (string) file_get_contents( FFC_BULK_FILE ), true );
	$offset = (int) get_option( FFC_BULK_OFFSET, 0 );
	$stats  = wp_parse_args( (array) get_option( FFC_BULK_STATS, array() ), array( 'imported' => 0, 'duplicate' => 0, 'error' => 0, 'total' => count( $facts ) ) );

	ffc_bulk_seed_categories();
	$cats = array();
	foreach ( FactFusion_Categories::all() as $cat ) {
		$cats[ $cat->slug ] = (int) $cat->id;
	}

	// Everything already in the table, including the hand-written 200, so near-identical claims are skipped.
	$existing = array_flip( array_map( 'ffc_bulk_normalize', $wpdb->get_col( $wpdb->prepare( 'SELECT claim FROM %i', FactFusion_DB::table( 'facts' ) ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

	foreach ( array_slice( $facts, $offset, FFC_BULK_BATCH ) as $fact ) {
		++$offset;
		$key = ffc_bulk_normalize( $fact['c'] );
		if ( isset( $existing[ $key ] ) ) {
			++$stats['duplicate'];
			continue;
		}
		$source_id = $fact['u'] ? ffc_bulk_source_id( $fact['u'] ) : 0;
		$id        = FactFusion_Facts::save(
			array(
				'title'               => ffc_bulk_title( $fact['c'] ),
				'claim'               => $fact['c'],
				'short_answer'        => '',
				'explanation'         => '',
				'category_id'         => $cats[ $fact['k'] ] ?? 0,
				'status'              => 'pending',
				'verification_status' => 'unverified',
				'confidence_score'    => array( 'A' => 60, 'B' => 30, 'C' => 10 )[ $fact['q'] ] ?? 10,
				'source_summary'      => 'Dataset import, quality ' . $fact['q'],
				'is_ai_assisted'      => (int) $fact['g'],
				'is_indexable'        => 0,
				'source_ids'          => $source_id ? array( $source_id ) : array(),
			)
		);
		if ( is_wp_error( $id ) ) {
			++$stats['error'];
		} else {
			++$stats['imported'];
			$existing[ $key ] = true;
		}
	}

	update_option( FFC_BULK_OFFSET, $offset, false );
	update_option( FFC_BULK_STATS, $stats, false );
	$stats['offset'] = $offset;
	return $stats;
}

add_action(
	'admin_menu',
	function () {
		add_management_page( 'Import 10k Facts', 'Import 10k Facts', 'manage_options', 'ffc-bulk', 'ffc_bulk_page' );
	}
);

function ffc_bulk_page() {
	if ( ! class_exists( 'FactFusion_Facts' ) ) {
		echo '<div class="wrap"><h1>Import 10k Facts</h1><p>The FactFusion fact engine is not loaded.</p></div>';
		return;
	}
	$running = isset( $_GET['run'] ) && check_admin_referer( 'ffc_bulk' );
	$stats   = $running ? ffc_bulk_run_batch() : wp_parse_args( (array) get_option( FFC_BULK_STATS, array() ), array( 'imported' => 0, 'duplicate' => 0, 'error' => 0, 'total' => 0 ) );
	$offset  = (int) get_option( FFC_BULK_OFFSET, 0 );
	$total   = $stats['total'] ? $stats['total'] : count( (array) json_decode( (string) file_get_contents( FFC_BULK_FILE ), true ) );
	$done    = $offset >= $total;
	$next    = wp_nonce_url( admin_url( 'tools.php?page=ffc-bulk&run=1' ), 'ffc_bulk' );
	?>
	<div class="wrap">
		<h1>Import 10k Facts</h1>
		<p>Facts are added as <strong>pending</strong> and <strong>not indexable</strong>. Nothing becomes a public page until it is checked and published.</p>
		<p>Progress: <?php echo (int) $offset; ?> / <?php echo (int) $total; ?> &middot;
			imported <?php echo (int) $stats['imported']; ?> &middot;
			skipped as duplicate <?php echo (int) $stats['duplicate']; ?> &middot;
			errors <?php echo (int) $stats['error']; ?></p>
		<?php if ( $done ) : ?>
			<p><strong>Import finished.</strong></p>
		<?php elseif ( $running ) : ?>
			<p>Importing… this page continues by itself. Keep it open.</p>
			<script>setTimeout(function(){ location.href = <?php echo wp_json_encode( $next ); ?>; }, 500);</script>
		<?php else : ?>
			<p><a class="button button-primary" href="<?php echo esc_url( $next ); ?>"><?php echo $offset ? 'Continue import' : 'Start import'; ?></a></p>
		<?php endif; ?>
	</div>
	<?php
}
