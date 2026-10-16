<?php
/**
 * Removes imported facts that can never be published because no source confirms them.
 *
 * Targets only facts whose claim comes from data/facts-10k.json and that are:
 * - in Draft because they have no real source (moved there by verify-results.php),
 * - still pending but not verified-ready (their source did not confirm them),
 * - archived (their source contradicted them).
 * Published and verified-ready facts are never touched.
 *
 * Tools → Clean Up Unverified. Runs only when an admin presses the button.
 */

defined( 'ABSPATH' ) || exit;

/** 'delete' removes the facts for good; 'archive' hides them but keeps them in the database. */
const FFC_CLEANUP_MODE = 'delete';
const FFC_CLEANUP_DONE = 'ffc_cleanup_unverified';

/** Ids to clean up, grouped by current status. */
function ffc_cleanup_targets() {
	global $wpdb;
	$imported = array();
	foreach ( (array) json_decode( (string) file_get_contents( FFC_BULK_FILE ), true ) as $fact ) {
		$imported[ ffc_bulk_normalize( $fact['c'] ) ] = true;
	}
	$ready    = array_flip( ffc_verify_ready_ids() );
	$statuses = 'delete' === FFC_CLEANUP_MODE ? array( 'draft', 'pending', 'archived' ) : array( 'draft', 'pending' );
	$targets  = array_fill_keys( $statuses, array() );
	// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			'SELECT id, claim, status FROM %i WHERE status IN (' . implode( ',', array_fill( 0, count( $statuses ), '%s' ) ) . ')',
			array_merge( array( FactFusion_DB::table( 'facts' ) ), $statuses )
		)
	);
	// phpcs:enable
	foreach ( $rows as $row ) {
		if ( isset( $ready[ (int) $row->id ] ) || ! isset( $imported[ ffc_bulk_normalize( $row->claim ) ] ) ) {
			continue;
		}
		$targets[ $row->status ][] = (int) $row->id;
	}
	return $targets;
}

function ffc_cleanup_run() {
	global $wpdb;
	set_time_limit( 300 );
	$targets = ffc_cleanup_targets();
	$facts   = FactFusion_DB::table( 'facts' );
	// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
	foreach ( $targets as $status => $ids ) {
		foreach ( array_chunk( $ids, 500 ) as $chunk ) {
			$in = implode( ',', $chunk );
			if ( 'delete' === FFC_CLEANUP_MODE ) {
				$wpdb->query( $wpdb->prepare( "DELETE FROM %i WHERE fact_id IN ($in)", FactFusion_DB::table( 'fact_sources' ) ) );
				$wpdb->query( $wpdb->prepare( "DELETE FROM %i WHERE fact_id IN ($in)", FactFusion_DB::table( 'corrections' ) ) );
				$wpdb->query( $wpdb->prepare( "DELETE FROM %i WHERE status = %s AND id IN ($in)", $facts, $status ) );
			} else {
				$wpdb->query( $wpdb->prepare( "UPDATE %i SET status = 'archived' WHERE status = %s AND id IN ($in)", $facts, $status ) );
			}
		}
	}
	// phpcs:enable
	FactFusion_Facts::changed();
	$done = array(
		'date'   => gmdate( 'Y-m-d H:i' ),
		'mode'   => FFC_CLEANUP_MODE,
		'counts' => array_map( 'count', $targets ),
	);
	if ( 'archive' === FFC_CLEANUP_MODE ) {
		$done['ids'] = $targets;
	}
	update_option( FFC_CLEANUP_DONE, $done, false );
	return $done;
}

add_action(
	'admin_menu',
	function () {
		add_management_page( 'Clean Up Unverified', 'Clean Up Unverified', 'manage_options', 'ffc-cleanup', 'ffc_cleanup_page' );
	}
);

function ffc_cleanup_page() {
	echo '<div class="wrap"><h1>Clean Up Unverified Facts</h1>';
	if ( ! class_exists( 'FactFusion_Facts' ) || ! function_exists( 'ffc_verify_ready_ids' ) || ! function_exists( 'ffc_bulk_normalize' ) ) {
		echo '<p>The FactFusion fact engine is not loaded.</p></div>';
		return;
	}
	if ( isset( $_GET['run'] ) && check_admin_referer( 'ffc_cleanup' ) ) {
		$done = ffc_cleanup_run();
		printf(
			'<div class="notice notice-success"><p>Done: %d facts %s.</p></div>',
			(int) array_sum( $done['counts'] ),
			'delete' === $done['mode'] ? 'deleted for good' : 'archived'
		);
	}
	$targets = ffc_cleanup_targets();
	$labels  = array(
		'draft'    => 'In Draft, no real source',
		'pending'  => 'Pending, not confirmed by their source',
		'archived' => 'Archived, wrong according to their source',
	);
	$verb    = 'delete' === FFC_CLEANUP_MODE ? 'Delete for good' : 'Archive';
	echo '<p>Only imported facts that cannot be published are listed. Published facts and checked (ready) facts are not touched.</p>';
	echo '<table class="widefat striped" style="max-width:600px"><tbody>';
	foreach ( $targets as $status => $ids ) {
		printf( '<tr><td>%s</td><td><strong>%d</strong></td></tr>', esc_html( $labels[ $status ] ), count( $ids ) );
	}
	echo '</tbody></table>';
	$total = array_sum( array_map( 'count', $targets ) );
	if ( $total ) {
		$url     = wp_nonce_url( add_query_arg( array( 'page' => 'ffc-cleanup', 'run' => 1 ), admin_url( 'tools.php' ) ), 'ffc_cleanup' );
		$confirm = 'delete' === FFC_CLEANUP_MODE ? 'This deletes the facts for good and cannot be undone. Continue?' : 'Archive these facts?';
		printf(
			'<p><a class="button button-primary" href="%s" onclick="return confirm(\'%s\')">%s %d facts</a></p>',
			esc_url( $url ),
			esc_js( $confirm ),
			esc_html( $verb ),
			(int) $total
		);
	} else {
		echo '<p>Nothing left to clean up.</p>';
	}
	echo '</div>';
}
