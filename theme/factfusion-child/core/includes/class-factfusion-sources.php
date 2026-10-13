<?php
/**
 * Source storage.
 *
 * @package FactFusion_AI_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CRUD for ff_sources and the fact-to-source links.
 */
final class FactFusion_Sources {

	/**
	 * Sources per fact ID, read once per request.
	 *
	 * @var array<int,object[]>
	 */
	private static $by_fact = array();

	const TIERS = array( 'A', 'B', 'C', 'D' );

	/**
	 * Human labels for each tier.
	 *
	 * @return array<string,string>
	 */
	public static function tier_labels() {
		return array(
			'A' => __( 'Tier A: primary, government, peer-reviewed', 'factfusion-ai' ),
			'B' => __( 'Tier B: established reference works', 'factfusion-ai' ),
			'C' => __( 'Tier C: established news and magazines', 'factfusion-ai' ),
			'D' => __( 'Tier D: blogs and social media (avoid)', 'factfusion-ai' ),
		);
	}

	/**
	 * Paginated source list.
	 *
	 * @param array $args search, tier, paged, per_page.
	 * @return array{items:object[],total:int}
	 */
	public static function query( $args = array() ) {
		global $wpdb;
		$table    = FactFusion_DB::table( 'sources' );
		$where    = array( '1 = %d' );
		$params   = array( 1 );
		$per_page = max( 1, (int) ( $args['per_page'] ?? 20 ) );
		$paged    = max( 1, (int) ( $args['paged'] ?? 1 ) );

		if ( ! empty( $args['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]  = '(source_name LIKE %s OR domain LIKE %s OR title LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}
		if ( ! empty( $args['tier'] ) && in_array( $args['tier'], self::TIERS, true ) ) {
			$where[]  = 'tier = %s';
			$params[] = $args['tier'];
		}
		if ( ! empty( $args['active_only'] ) ) {
			$where[]  = 'is_active = %d';
			$params[] = 1;
		}

		$where_sql = implode( ' AND ', $where );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE {$where_sql}", array_merge( array( $table ), $params ) ) );
		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE {$where_sql} ORDER BY source_name ASC, id ASC LIMIT %d OFFSET %d",
				array_merge( array( $table ), $params, array( $per_page, ( $paged - 1 ) * $per_page ) )
			)
		);
		// phpcs:enable

		return array(
			'items' => $items,
			'total' => $total,
		);
	}

	/**
	 * One source by ID.
	 *
	 * @param int $id Source ID.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', FactFusion_DB::table( 'sources' ), $id ) );
	}

	/**
	 * Finds a source by its exact URL.
	 *
	 * @param string $url Source URL.
	 * @return object|null
	 */
	public static function find_by_url( $url ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE url = %s LIMIT 1', FactFusion_DB::table( 'sources' ), esc_url_raw( $url ) ) );
	}

	/**
	 * Sources attached to a fact.
	 *
	 * @param int $fact_id Fact ID.
	 * @return object[]
	 */
	public static function for_fact( $fact_id ) {
		global $wpdb;
		$fact_id = (int) $fact_id;
		// Cards, schema and the share image all ask for the same fact's sources on one request.
		if ( ! isset( self::$by_fact[ $fact_id ] ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			self::$by_fact[ $fact_id ] = (array) $wpdb->get_results(
				$wpdb->prepare(
					'SELECT s.* FROM %i s INNER JOIN %i l ON l.source_id = s.id WHERE l.fact_id = %d ORDER BY s.tier ASC, s.source_name ASC',
					FactFusion_DB::table( 'sources' ),
					FactFusion_DB::table( 'fact_sources' ),
					$fact_id
				)
			);
		}
		return self::$by_fact[ $fact_id ];
	}

	/**
	 * Forgets the sources read on this request, after a change.
	 */
	public static function forget() {
		self::$by_fact = array();
	}

	/**
	 * How many facts cite each source.
	 *
	 * @return array<int,int> Source ID => fact count.
	 */
	public static function usage_counts() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows   = $wpdb->get_results( $wpdb->prepare( 'SELECT source_id, COUNT(*) AS total FROM %i GROUP BY source_id', FactFusion_DB::table( 'fact_sources' ) ) );
		$counts = array();
		foreach ( $rows as $row ) {
			$counts[ (int) $row->source_id ] = (int) $row->total;
		}
		return $counts;
	}

	/**
	 * Returns a Y-m-d date or null.
	 *
	 * @param mixed $value Raw date.
	 * @return string|null
	 */
	public static function clean_date( $value ) {
		$value = trim( (string) $value );
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m ) ) {
			return null;
		}
		return checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ? $value : null;
	}

	/**
	 * Creates or updates a source.
	 *
	 * @param array $data Raw field values.
	 * @param int   $id   Source ID, 0 to create.
	 * @return int|WP_Error Source ID.
	 */
	public static function save( $data, $id = 0 ) {
		global $wpdb;
		$table = FactFusion_DB::table( 'sources' );
		$name  = sanitize_text_field( $data['source_name'] ?? '' );
		$url   = esc_url_raw( trim( (string) ( $data['url'] ?? '' ) ), array( 'http', 'https' ) );
		$host  = $url ? wp_parse_url( $url, PHP_URL_HOST ) : '';
		$tier  = strtoupper( sanitize_text_field( $data['tier'] ?? '' ) );

		if ( '' === $name ) {
			return new WP_Error( 'ff_source_name', __( 'A source needs a name.', 'factfusion-ai' ) );
		}
		if ( '' === $url || ! $host ) {
			return new WP_Error( 'ff_source_url', __( 'A source needs a full http or https URL.', 'factfusion-ai' ) );
		}
		if ( ! in_array( $tier, self::TIERS, true ) ) {
			return new WP_Error( 'ff_source_tier', __( 'Choose a source tier from A to D.', 'factfusion-ai' ) );
		}

		$row = array(
			'source_name'       => $name,
			'domain'            => strtolower( preg_replace( '/^www\./i', '', $host ) ),
			'tier'              => $tier,
			'url'               => $url,
			'title'             => sanitize_text_field( $data['title'] ?? '' ),
			'author'            => sanitize_text_field( $data['author'] ?? '' ),
			'publisher'         => sanitize_text_field( $data['publisher'] ?? '' ),
			'date_published'    => self::clean_date( $data['date_published'] ?? '' ),
			'date_accessed'     => self::clean_date( $data['date_accessed'] ?? '' ),
			'credibility_score' => min( 100, max( 0, (int) ( $data['credibility_score'] ?? 0 ) ) ),
			'notes'             => sanitize_textarea_field( $data['notes'] ?? '' ),
			'is_active'         => empty( $data['is_active'] ) ? 0 : 1,
		);
		$formats = array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d' );

		if ( $id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$ok = $wpdb->update( $table, $row, array( 'id' => (int) $id ), $formats, array( '%d' ) );
			self::forget();
			return false === $ok ? new WP_Error( 'ff_db', __( 'The source could not be saved.', 'factfusion-ai' ) ) : (int) $id;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$ok = $wpdb->insert( $table, $row, $formats );
		self::forget();
		return $ok ? (int) $wpdb->insert_id : new WP_Error( 'ff_db', __( 'The source could not be saved.', 'factfusion-ai' ) );
	}

	/**
	 * Deletes a source no fact cites.
	 *
	 * @param int $id Source ID.
	 * @return true|WP_Error
	 */
	public static function delete( $id ) {
		global $wpdb;
		$counts = self::usage_counts();
		if ( ! empty( $counts[ (int) $id ] ) ) {
			return new WP_Error( 'ff_source_in_use', __( 'That source is cited by at least one fact. Detach it first, or mark it inactive.', 'factfusion-ai' ) );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( FactFusion_DB::table( 'sources' ), array( 'id' => (int) $id ), array( '%d' ) );
		self::forget();
		return true;
	}
}
