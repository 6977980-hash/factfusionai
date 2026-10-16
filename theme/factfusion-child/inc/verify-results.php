<?php
/**
 * Applies source-check results to imported (pending) facts.
 *
 * Each data/verify-*.json file is one checked batch: for every fact, its claim, the source
 * URL, a verdict and the exact supporting quote from the source.
 * - correct:    stays pending, marked "verified-ready" (Today's 5 then picks only these).
 * - overstated: claim and title replaced by the corrected claim, then marked verified-ready.
 * - wrong:      archived.
 * - unverifiable: left as it is.
 * Only pending facts are changed. A batch is applied once; running it again changes nothing.
 *
 * Tools → Apply Fact Checks.
 */

defined( 'ABSPATH' ) || exit;

const FFC_VERIFY_READY   = 'ffc_verify_ready';
const FFC_VERIFY_APPLIED = 'ffc_verify_applied';
const FFC_VERIFY_DRAFTED = 'ffc_unsourced_drafted';

/**
 * One time: pending imported facts without a real source go to Draft, so only sourced facts
 * wait for a source check. Dataset quality B is AI-written with a guessed link, C has no link.
 * Nothing is deleted; the moved ids are kept in the option so they can be brought back.
 */
add_action(
	'admin_init',
	static function () {
		global $wpdb;
		if ( get_option( FFC_VERIFY_DRAFTED ) || ! class_exists( 'FactFusion_DB' ) || ! function_exists( 'ffc_bulk_normalize' ) ) {
			return;
		}
		$unsourced = array();
		foreach ( (array) json_decode( (string) file_get_contents( FFC_BULK_FILE ), true ) as $fact ) {
			if ( 'A' !== ( $fact['q'] ?? '' ) ) {
				$unsourced[ ffc_bulk_normalize( $fact['c'] ) ] = true;
			}
		}
		$table = FactFusion_DB::table( 'facts' );
		$ids   = array();
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
		foreach ( $wpdb->get_results( $wpdb->prepare( 'SELECT id, claim FROM %i WHERE status = %s', $table, 'pending' ) ) as $row ) {
			if ( isset( $unsourced[ ffc_bulk_normalize( $row->claim ) ] ) ) {
				$ids[] = (int) $row->id;
			}
		}
		foreach ( array_chunk( $ids, 500 ) as $chunk ) {
			$wpdb->query( $wpdb->prepare( 'UPDATE %i SET status = %s WHERE status = %s AND id IN (' . implode( ',', $chunk ) . ')', $table, 'draft', 'pending' ) );
		}
		// phpcs:enable
		update_option( FFC_VERIFY_DRAFTED, array( 'date' => gmdate( 'Y-m-d H:i' ), 'ids' => $ids ), false );
	}
);

/** Verified-ready facts: fact id => array( quote, url, batch ). */
function ffc_verify_ready() {
	return (array) get_option( FFC_VERIFY_READY, array() );
}

/** Verified-ready fact ids, or an empty array when no batch has been applied yet. */
function ffc_verify_ready_ids() {
	return array_map( 'intval', array_keys( ffc_verify_ready() ) );
}

/** SQL that keeps only verified-ready facts (column $col); matches nothing before the first batch. */
function ffc_verify_ready_sql( $col = 'f.id' ) {
	$ids = ffc_verify_ready_ids();
	return $ids ? " AND {$col} IN (" . implode( ',', $ids ) . ')' : ' AND 0';
}

/** Batch files, newest name last: batch name => path. */
function ffc_verify_batches() {
	$out = array();
	foreach ( (array) glob( __DIR__ . '/../data/verify-*.json' ) as $file ) {
		$out[ basename( $file, '.json' ) ] = $file;
	}
	ksort( $out );
	return $out;
}

/** Finds the imported fact for a dataset claim (exact text, as the import saved it). */
function ffc_verify_find( $claim ) {
	$fact = FactFusion_Facts::get_by_claim( trim( sanitize_textarea_field( $claim ) ) );
	return $fact ? $fact : FactFusion_Facts::get_by_claim( $claim );
}

/**
 * Applies one batch. Returns a row per fact: claim, verdict, outcome.
 */
function ffc_verify_apply( $batch ) {
	$files = ffc_verify_batches();
	$rows  = isset( $files[ $batch ] ) ? json_decode( (string) file_get_contents( $files[ $batch ] ), true ) : null;
	if ( ! is_array( $rows ) ) {
		return new WP_Error( 'ffc_verify_file', 'That batch file could not be read.' );
	}
	if ( function_exists( 'set_time_limit' ) ) {
		set_time_limit( 300 ); // A 250-fact batch saves up to 250 facts.
	}
	$ready   = ffc_verify_ready();
	$applied = (array) get_option( FFC_VERIFY_APPLIED, array() );
	$report  = array();

	foreach ( $rows as $row ) {
		$verdict = (string) ( $row['verdict'] ?? '' );
		$fact    = ffc_verify_find( (string) $row['claim'] );
		$line    = array( 'claim' => $row['claim'], 'verdict' => $verdict );

		if ( ! $fact ) {
			$report[] = $line + array( 'outcome' => 'Not found on the site (skipped as a duplicate during import?)' );
			continue;
		}
		$line['id'] = (int) $fact->id;
		if ( 'pending' !== $fact->status ) {
			$report[] = $line + array( 'outcome' => 'Not changed: it is already ' . $fact->status );
			continue;
		}

		$result = true;
		if ( 'correct' === $verdict ) {
			$outcome = 'Marked verified-ready';
		} elseif ( 'overstated' === $verdict && '' !== trim( (string) ( $row['corrected_claim'] ?? '' ) ) ) {
			$claim   = trim( (string) $row['corrected_claim'] );
			$result  = ffc_today_update( $fact, array( 'claim' => $claim, 'title' => ffc_bulk_title( $claim ) ) );
			$outcome = 'Claim corrected, marked verified-ready';
		} elseif ( 'wrong' === $verdict ) {
			$result  = ffc_today_update( $fact, array( 'status' => 'archived' ) );
			$outcome = 'Archived';
		} else {
			$report[] = $line + array( 'outcome' => 'Left as it is (source did not confirm it)' );
			continue;
		}

		if ( is_wp_error( $result ) ) {
			$report[] = $line + array( 'outcome' => 'Error: ' . implode( ' ', $result->get_error_messages() ) );
			continue;
		}
		if ( 'wrong' !== $verdict ) {
			$ready[ (int) $fact->id ] = array(
				'quote' => (string) ( $row['quote'] ?? '' ),
				'url'   => (string) ( $row['source_url'] ?? '' ),
				'batch' => $batch,
			);
		}
		$report[] = $line + array( 'outcome' => $outcome );
	}

	update_option( FFC_VERIFY_READY, $ready, false );
	$applied[ $batch ] = array( 'date' => gmdate( 'Y-m-d H:i' ), 'report' => $report );
	update_option( FFC_VERIFY_APPLIED, $applied, false );
	return $report;
}

add_action(
	'admin_menu',
	function () {
		add_management_page( 'Apply Fact Checks', 'Apply Fact Checks', 'manage_options', 'ffc-verify', 'ffc_verify_page' );
	}
);

function ffc_verify_page() {
	if ( ! class_exists( 'FactFusion_Facts' ) || ! function_exists( 'ffc_today_update' ) ) {
		echo '<div class="wrap"><h1>Apply Fact Checks</h1><p>The FactFusion fact engine is not loaded.</p></div>';
		return;
	}
	$applied = (array) get_option( FFC_VERIFY_APPLIED, array() );
	$batch   = sanitize_key( $_GET['batch'] ?? '' );
	$report  = null;
	if ( $batch && isset( $_GET['apply'] ) && check_admin_referer( 'ffc_verify_' . $batch ) && ! isset( $applied[ $batch ] ) ) {
		$report  = ffc_verify_apply( $batch );
		$applied = (array) get_option( FFC_VERIFY_APPLIED, array() );
	} elseif ( $batch && isset( $applied[ $batch ] ) ) {
		$report = $applied[ $batch ]['report'];
	}
	?>
	<div class="wrap">
		<h1>Apply Fact Checks</h1>
		<p>Each batch below was checked against its source. Applying it changes only <strong>pending</strong> facts:
			correct ones become ready for Today's 5, overstated ones get the corrected claim and become ready, wrong ones are archived.
			Once a batch is applied, Today's 5 picks only from checked facts.</p>
		<?php
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
		$ffc_pending = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE status = %s', FactFusion_DB::table( 'facts' ), 'pending' ) );
		$ffc_ready   = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE status = %s' . ffc_verify_ready_sql( 'id' ), FactFusion_DB::table( 'facts' ), 'pending' ) );
		// phpcs:enable
		$ffc_drafted = (array) get_option( FFC_VERIFY_DRAFTED, array() );
		?>
		<p>Checked and waiting in Today's 5 and the Review queue: <strong><?php echo (int) $ffc_ready; ?></strong> facts.
			Pending facts not yet checked or not confirmed by their source: <strong><?php echo (int) ( $ffc_pending - $ffc_ready ); ?></strong>.
			<?php if ( $ffc_drafted ) : ?>
				Moved to Draft because they have no real source: <strong><?php echo (int) count( (array) ( $ffc_drafted['ids'] ?? array() ) ); ?></strong> (<?php echo esc_html( $ffc_drafted['date'] ?? '' ); ?> UTC, not deleted).
			<?php endif; ?>
		</p>
		<table class="widefat striped" style="max-width:820px">
			<thead><tr><th>Batch</th><th>Correct</th><th>Overstated</th><th>Wrong</th><th>Unconfirmed</th><th></th></tr></thead>
			<tbody>
			<?php foreach ( ffc_verify_batches() as $name => $file ) : ?>
				<?php
				$counts = array_count_values( wp_list_pluck( (array) json_decode( (string) file_get_contents( $file ), true ), 'verdict' ) );
				$url    = add_query_arg( array( 'page' => 'ffc-verify', 'batch' => $name, 'apply' => 1, '_wpnonce' => wp_create_nonce( 'ffc_verify_' . $name ) ), admin_url( 'tools.php' ) );
				?>
				<tr>
					<td><?php echo esc_html( $name ); ?></td>
					<td><?php echo (int) ( $counts['correct'] ?? 0 ); ?></td>
					<td><?php echo (int) ( $counts['overstated'] ?? 0 ); ?></td>
					<td><?php echo (int) ( $counts['wrong'] ?? 0 ); ?></td>
					<td><?php echo (int) ( $counts['unverifiable'] ?? 0 ); ?></td>
					<td>
						<?php if ( isset( $applied[ $name ] ) ) : ?>
							Applied <?php echo esc_html( $applied[ $name ]['date'] ); ?> UTC &middot;
							<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'ffc-verify', 'batch' => $name ), admin_url( 'tools.php' ) ) ); ?>">report</a>
						<?php else : ?>
							<a class="button button-primary" href="<?php echo esc_url( $url ); ?>" onclick="return confirm('Apply this batch to the pending facts?')">Apply</a>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php if ( is_wp_error( $report ) ) : ?>
			<div class="notice notice-error"><p><?php echo esc_html( $report->get_error_message() ); ?></p></div>
		<?php elseif ( $report ) : ?>
			<h2>Report: <?php echo esc_html( $batch ); ?></h2>
			<table class="widefat striped" style="max-width:1100px">
				<thead><tr><th>Fact</th><th>Verdict</th><th>What happened</th></tr></thead>
				<tbody>
				<?php foreach ( $report as $line ) : ?>
					<tr>
						<td><?php echo esc_html( $line['claim'] ); ?></td>
						<td><?php echo esc_html( $line['verdict'] ); ?></td>
						<td><?php echo esc_html( $line['outcome'] ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
	<?php
}
