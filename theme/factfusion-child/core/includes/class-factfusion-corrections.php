<?php
/**
 * Correction report storage.
 *
 * @package FactFusion_AI_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CRUD for ff_corrections.
 */
final class FactFusion_Corrections {

	const STATUSES = array( 'new', 'in_review', 'resolved', 'rejected' );

	/**
	 * Labels for the report statuses.
	 *
	 * @return array<string,string>
	 */
	public static function status_labels() {
		return array(
			'new'       => __( 'New', 'factfusion-ai' ),
			'in_review' => __( 'In review', 'factfusion-ai' ),
			'resolved'  => __( 'Resolved', 'factfusion-ai' ),
			'rejected'  => __( 'Rejected', 'factfusion-ai' ),
		);
	}

	/**
	 * Paginated report list, newest first.
	 *
	 * @param array $args status, paged, per_page.
	 * @return array{items:object[],total:int}
	 */
	public static function query( $args = array() ) {
		global $wpdb;
		$table    = FactFusion_DB::table( 'corrections' );
		$per_page = max( 1, (int) ( $args['per_page'] ?? 20 ) );
		$offset   = ( max( 1, (int) ( $args['paged'] ?? 1 ) ) - 1 ) * $per_page;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		if ( ! empty( $args['status'] ) && in_array( $args['status'], self::STATUSES, true ) ) {
			$total = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE status = %s', $table, $args['status'] ) );
			$items = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE status = %s ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d', $table, $args['status'], $per_page, $offset ) );
		} else {
			$total = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) );
			$items = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d', $table, $per_page, $offset ) );
		}
		// phpcs:enable

		return array(
			'items' => $items,
			'total' => $total,
		);
	}

	/**
	 * Report totals per status.
	 *
	 * @return array<string,int>
	 */
	public static function status_counts() {
		global $wpdb;
		$counts = array_fill_keys( self::STATUSES, 0 );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT status, COUNT(*) AS total FROM %i GROUP BY status', FactFusion_DB::table( 'corrections' ) ) );
		foreach ( $rows as $row ) {
			if ( isset( $counts[ $row->status ] ) ) {
				$counts[ $row->status ] = (int) $row->total;
			}
		}
		$counts['all'] = array_sum( $counts );
		return $counts;
	}

	/**
	 * One report by ID.
	 *
	 * @param int $id Report ID.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', FactFusion_DB::table( 'corrections' ), $id ) );
	}

	/**
	 * Creates or updates a report.
	 *
	 * @param array $data Raw field values.
	 * @param int   $id   Report ID, 0 to create.
	 * @return int|WP_Error Report ID.
	 */
	public static function save( $data, $id = 0 ) {
		global $wpdb;
		$table    = FactFusion_DB::table( 'corrections' );
		$existing = $id ? self::get( $id ) : null;
		$fact_id  = absint( $data['fact_id'] ?? 0 );
		$wrong    = trim( sanitize_textarea_field( $data['what_is_wrong'] ?? '' ) );
		$status   = sanitize_key( $data['status'] ?? 'new' );
		$email    = trim( (string) ( $data['reporter_email'] ?? '' ) );

		if ( $id && ! $existing ) {
			return new WP_Error( 'ff_correction_missing', __( 'That report no longer exists.', 'factfusion-ai' ) );
		}
		if ( ! $fact_id || ! FactFusion_Facts::get( $fact_id ) ) {
			return new WP_Error( 'ff_correction_fact', __( 'Choose the fact this report is about.', 'factfusion-ai' ) );
		}
		if ( '' === $wrong ) {
			return new WP_Error( 'ff_correction_text', __( 'Describe what is wrong.', 'factfusion-ai' ) );
		}
		if ( '' !== $email && ! is_email( $email ) ) {
			return new WP_Error( 'ff_correction_email', __( 'That email address is not valid.', 'factfusion-ai' ) );
		}
		if ( ! in_array( $status, self::STATUSES, true ) ) {
			return new WP_Error( 'ff_correction_status', __( 'Unknown status.', 'factfusion-ai' ) );
		}

		$closed      = in_array( $status, array( 'resolved', 'rejected' ), true );
		$resolved_at = null;
		if ( $closed ) {
			$resolved_at = ( $existing && $existing->resolved_at && $existing->status === $status ) ? $existing->resolved_at : FactFusion_DB::now();
		}

		$row = array(
			'fact_id'              => $fact_id,
			'reporter_email'       => sanitize_email( $email ),
			'what_is_wrong'        => $wrong,
			'suggested_correction' => trim( sanitize_textarea_field( $data['suggested_correction'] ?? '' ) ),
			'supporting_source'    => trim( sanitize_textarea_field( $data['supporting_source'] ?? '' ) ),
			'status'               => $status,
			'admin_notes'          => trim( sanitize_textarea_field( $data['admin_notes'] ?? '' ) ),
			'resolved_at'          => $resolved_at,
		);
		$formats = array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' );

		if ( $id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$ok = $wpdb->update( $table, $row, array( 'id' => (int) $id ), $formats, array( '%d' ) );
			return false === $ok ? new WP_Error( 'ff_db', __( 'The report could not be saved.', 'factfusion-ai' ) ) : (int) $id;
		}

		$row['created_at'] = FactFusion_DB::now();
		$formats[]         = '%s';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$ok = $wpdb->insert( $table, $row, $formats );
		return $ok ? (int) $wpdb->insert_id : new WP_Error( 'ff_db', __( 'The report could not be saved.', 'factfusion-ai' ) );
	}

	/**
	 * Deletes a report.
	 *
	 * @param int $id Report ID.
	 */
	public static function delete( $id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( FactFusion_DB::table( 'corrections' ), array( 'id' => (int) $id ), array( '%d' ) );
	}
}
