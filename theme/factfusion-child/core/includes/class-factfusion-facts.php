<?php
/**
 * Fact storage.
 *
 * @package FactFusion_AI_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CRUD for ff_facts. Facts live only in this table, never in a post type.
 */
final class FactFusion_Facts {

	const STATUSES = array( 'draft', 'pending', 'published', 'archived' );

	const VERIFICATION = array( 'unverified', 'verified', 'disputed', 'outdated' );

	const MIN_EXPLANATION_WORDS = 100;

	const MAX_CLAIM_CHARS = 500;

	const POOL_TRANSIENT = 'factfusion_pool';

	/**
	 * Labels for the editorial statuses.
	 *
	 * @return array<string,string>
	 */
	public static function status_labels() {
		return array(
			'draft'     => __( 'Draft', 'factfusion-ai' ),
			'pending'   => __( 'Pending review', 'factfusion-ai' ),
			'published' => __( 'Published', 'factfusion-ai' ),
			'archived'  => __( 'Archived', 'factfusion-ai' ),
		);
	}

	/**
	 * Labels for the verification statuses.
	 *
	 * @return array<string,string>
	 */
	public static function verification_labels() {
		return array(
			'unverified' => __( 'Unverified', 'factfusion-ai' ),
			'verified'   => __( 'Verified', 'factfusion-ai' ),
			'disputed'   => __( 'Disputed', 'factfusion-ai' ),
			'outdated'   => __( 'Outdated', 'factfusion-ai' ),
		);
	}

	/**
	 * One fact by ID.
	 *
	 * @param int $id Fact ID.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', FactFusion_DB::table( 'facts' ), $id ) );
	}

	/**
	 * One fact by slug.
	 *
	 * @param string $slug Fact slug.
	 * @return object|null
	 */
	public static function get_by_slug( $slug ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE slug = %s', FactFusion_DB::table( 'facts' ), $slug ) );
	}

	/**
	 * Finds a fact with exactly this claim text.
	 *
	 * @param string $claim Claim text.
	 * @return object|null
	 */
	public static function get_by_claim( $claim ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE claim = %s LIMIT 1', FactFusion_DB::table( 'facts' ), $claim ) );
	}

	/**
	 * Paginated fact list.
	 *
	 * @param array $args status, verification_status, category_id, search, paged, per_page, orderby, order.
	 * @return array{items:object[],total:int}
	 */
	public static function query( $args = array() ) {
		global $wpdb;
		$table    = FactFusion_DB::table( 'facts' );
		$where    = array( '1 = %d' );
		$params   = array( 1 );
		$per_page = max( 1, min( 200, (int) ( $args['per_page'] ?? 20 ) ) );
		$paged    = max( 1, (int) ( $args['paged'] ?? 1 ) );

		if ( ! empty( $args['status'] ) && in_array( $args['status'], self::STATUSES, true ) ) {
			$where[]  = 'status = %s';
			$params[] = $args['status'];
		}
		if ( ! empty( $args['verification_status'] ) && in_array( $args['verification_status'], self::VERIFICATION, true ) ) {
			$where[]  = 'verification_status = %s';
			$params[] = $args['verification_status'];
		}
		if ( ! empty( $args['category_id'] ) ) {
			$where[]  = 'category_id = %d';
			$params[] = (int) $args['category_id'];
		}
		if ( ! empty( $args['author_id'] ) ) {
			$where[]  = 'author_id = %d';
			$params[] = (int) $args['author_id'];
		}
		if ( isset( $args['search'] ) && '' !== $args['search'] ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]  = '(title LIKE %s OR claim LIKE %s)';
			$params[] = $like;
			$params[] = $like;
		}

		$sortable = array(
			'fact_number'  => 'fact_number',
			'title'        => 'title',
			'date_updated' => 'date_updated',
			'confidence'   => 'confidence_score',
		);
		$orderby  = $sortable[ $args['orderby'] ?? '' ] ?? 'fact_number';
		$order    = 'asc' === strtolower( (string) ( $args['order'] ?? '' ) ) ? 'ASC' : 'DESC';

		$where_sql = implode( ' AND ', $where );

		// $orderby and $order come from the allowlists above; every value goes through a placeholder.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE {$where_sql}", array_merge( array( $table ), $params ) ) );
		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE {$where_sql} ORDER BY {$orderby} {$order}, id DESC LIMIT %d OFFSET %d",
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
	 * Fact totals per editorial status, plus "all".
	 *
	 * @return array<string,int>
	 */
	public static function status_counts() {
		global $wpdb;
		$counts = array_fill_keys( self::STATUSES, 0 );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT status, COUNT(*) AS total FROM %i GROUP BY status', FactFusion_DB::table( 'facts' ) ) );
		foreach ( $rows as $row ) {
			if ( isset( $counts[ $row->status ] ) ) {
				$counts[ $row->status ] = (int) $row->total;
			}
		}
		$counts['all'] = array_sum( $counts );
		return $counts;
	}

	/**
	 * Number of facts that are both published and verified.
	 *
	 * @return int
	 */
	public static function live_verified_count() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE status = %s AND verification_status = %s', FactFusion_DB::table( 'facts' ), 'published', 'verified' ) );
	}

	/**
	 * IDs of the sources attached to a fact.
	 *
	 * @param int $fact_id Fact ID.
	 * @return int[]
	 */
	public static function source_ids( $fact_id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( 'SELECT source_id FROM %i WHERE fact_id = %d', FactFusion_DB::table( 'fact_sources' ), $fact_id ) ) );
	}

	/**
	 * Number of sources attached to every fact.
	 *
	 * @return array<int,int> Fact ID => source count.
	 */
	public static function source_counts() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows   = $wpdb->get_results( $wpdb->prepare( 'SELECT fact_id, COUNT(*) AS total FROM %i GROUP BY fact_id', FactFusion_DB::table( 'fact_sources' ) ) );
		$counts = array();
		foreach ( $rows as $row ) {
			$counts[ (int) $row->fact_id ] = (int) $row->total;
		}
		return $counts;
	}

	/**
	 * Word count of an explanation, ignoring markup.
	 *
	 * @param string $explanation Explanation HTML.
	 * @return int
	 */
	public static function word_count( $explanation ) {
		$text = trim( wp_strip_all_tags( (string) $explanation ) );
		return '' === $text ? 0 : count( preg_split( '/\s+/u', $text ) );
	}

	/**
	 * Reasons a fact may not be published yet. Empty means it may.
	 *
	 * @param array $row        Sanitized fact row.
	 * @param int[] $source_ids Attached source IDs.
	 * @return string[]
	 */
	public static function publish_blockers( $row, $source_ids ) {
		$blockers = array();
		if ( empty( $row['category_id'] ) ) {
			$blockers[] = __( 'Assign a category.', 'factfusion-ai' );
		}
		if ( '' === trim( (string) $row['short_answer'] ) ) {
			$blockers[] = __( 'Add a short answer.', 'factfusion-ai' );
		}
		$words = self::word_count( $row['explanation'] );
		if ( $words < self::MIN_EXPLANATION_WORDS ) {
			/* translators: 1: required words, 2: current words. */
			$blockers[] = sprintf( __( 'The explanation needs at least %1$d words. It has %2$d.', 'factfusion-ai' ), self::MIN_EXPLANATION_WORDS, $words );
		}
		$active = 0;
		foreach ( $source_ids as $source_id ) {
			$source = FactFusion_Sources::get( $source_id );
			if ( $source && $source->is_active ) {
				++$active;
			}
		}
		if ( 0 === $active ) {
			$blockers[] = __( 'Attach at least one active source.', 'factfusion-ai' );
		}
		if ( 'verified' !== $row['verification_status'] ) {
			$blockers[] = __( 'Set verification to "Verified" after checking the claim against its source yourself.', 'factfusion-ai' );
		}
		return $blockers;
	}

	/**
	 * Returns a slug no other fact uses.
	 *
	 * @param string $slug Wanted slug.
	 * @param int    $id   Fact being saved.
	 * @return string
	 */
	private static function unique_slug( $slug, $id ) {
		$slug   = substr( $slug, 0, 180 );
		$base   = $slug;
		$suffix = 2;
		while ( true ) {
			$found = self::get_by_slug( $slug );
			if ( ! $found || (int) $found->id === (int) $id ) {
				return $slug;
			}
			$slug = $base . '-' . $suffix;
			++$suffix;
		}
	}

	/**
	 * Creates or updates a fact and its source links.
	 *
	 * @param array $data Raw field values, including source_ids.
	 * @param int   $id   Fact ID, 0 to create.
	 * @return int|WP_Error Fact ID.
	 */
	public static function save( $data, $id = 0 ) {
		global $wpdb;
		$table    = FactFusion_DB::table( 'facts' );
		$existing = $id ? self::get( $id ) : null;
		$errors   = new WP_Error();

		if ( $id && ! $existing ) {
			return new WP_Error( 'ff_fact_missing', __( 'That fact no longer exists.', 'factfusion-ai' ) );
		}

		$title        = sanitize_text_field( $data['title'] ?? '' );
		$claim        = trim( sanitize_textarea_field( $data['claim'] ?? '' ) );
		$status       = sanitize_key( $data['status'] ?? 'draft' );
		$verification = sanitize_key( $data['verification_status'] ?? 'unverified' );
		$category_id  = absint( $data['category_id'] ?? 0 );
		$source_ids   = array_values( array_unique( array_filter( array_map( 'absint', (array) ( $data['source_ids'] ?? array() ) ) ) ) );

		if ( '' === $title ) {
			$errors->add( 'ff_title', __( 'A fact needs a title.', 'factfusion-ai' ) );
		} elseif ( mb_strlen( $title ) > 200 ) {
			$errors->add( 'ff_title', __( 'The title must be 200 characters or fewer.', 'factfusion-ai' ) );
		}
		if ( '' === $claim ) {
			$errors->add( 'ff_claim', __( 'A fact needs a claim.', 'factfusion-ai' ) );
		} elseif ( mb_strlen( $claim ) > self::MAX_CLAIM_CHARS ) {
			/* translators: %d: maximum characters. */
			$errors->add( 'ff_claim', sprintf( __( 'The claim must be %d characters or fewer.', 'factfusion-ai' ), self::MAX_CLAIM_CHARS ) );
		}
		if ( ! in_array( $status, self::STATUSES, true ) ) {
			$errors->add( 'ff_status', __( 'Unknown status.', 'factfusion-ai' ) );
		}
		if ( ! in_array( $verification, self::VERIFICATION, true ) ) {
			$errors->add( 'ff_verification', __( 'Unknown verification status.', 'factfusion-ai' ) );
		}
		if ( $category_id && ! FactFusion_Categories::get( $category_id ) ) {
			$errors->add( 'ff_category', __( 'That category does not exist.', 'factfusion-ai' ) );
		}

		$now = FactFusion_DB::now();
		$row = array(
			'title'               => $title,
			'claim'               => $claim,
			'short_answer'        => trim( sanitize_textarea_field( $data['short_answer'] ?? '' ) ),
			'explanation'         => trim( wp_kses_post( (string) ( $data['explanation'] ?? '' ) ) ),
			'category_id'         => $category_id,
			'status'              => $status,
			'verification_status' => $verification,
			'confidence_score'    => min( 100, max( 0, (int) ( $data['confidence_score'] ?? 0 ) ) ),
			'source_summary'      => sanitize_text_field( $data['source_summary'] ?? '' ),
			'author_id'           => absint( $data['author_id'] ?? ( $existing ? $existing->author_id : get_current_user_id() ) ),
			'featured'            => empty( $data['featured'] ) ? 0 : 1,
			'is_indexable'        => empty( $data['is_indexable'] ) ? 0 : 1,
			'is_ai_assisted'      => empty( $data['is_ai_assisted'] ) ? 0 : 1,
			'date_updated'        => $now,
		);

		if ( 'published' === $status ) {
			foreach ( self::publish_blockers( $row, $source_ids ) as $blocker ) {
				$errors->add( 'ff_publish', $blocker );
			}
		}

		if ( $errors->has_errors() ) {
			return $errors;
		}

		$slug_source = ! empty( $data['slug'] ) ? $data['slug'] : ( $existing ? $existing->slug : $title );
		$row['slug'] = self::unique_slug( sanitize_title( $slug_source ), $id );
		if ( '' === $row['slug'] ) {
			return new WP_Error( 'ff_slug', __( 'A URL slug could not be made from that title. Enter one by hand.', 'factfusion-ai' ) );
		}

		// Verification dates record when a human last checked the fact.
		$was_verified         = $existing && 'verified' === $existing->verification_status;
		$changed              = ! $existing || $existing->verification_status !== $verification;
		$reviewed_now         = ! empty( $data['mark_reviewed'] );
		$row['date_verified'] = $existing ? $existing->date_verified : null;
		$row['last_reviewed'] = $existing ? $existing->last_reviewed : null;

		if ( 'verified' === $verification && ( ! $was_verified || $reviewed_now || empty( $row['date_verified'] ) ) ) {
			$row['date_verified'] = $now;
		}
		if ( $reviewed_now || ( $changed && 'unverified' !== $verification ) ) {
			$row['last_reviewed'] = $now;
		}

		$formats = array( '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s' );

		if ( $id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$ok = $wpdb->update( $table, $row, array( 'id' => (int) $id ), $formats, array( '%d' ) );
			if ( false === $ok ) {
				return new WP_Error( 'ff_db', __( 'The fact could not be saved.', 'factfusion-ai' ) );
			}
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$row['fact_number']  = 1 + (int) $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(fact_number) FROM %i', $table ) );
			$row['date_created'] = $now;
			$formats[]           = '%d';
			$formats[]           = '%s';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$ok = $wpdb->insert( $table, $row, $formats );
			if ( ! $ok ) {
				return new WP_Error( 'ff_db', __( 'The fact could not be saved.', 'factfusion-ai' ) );
			}
			$id = (int) $wpdb->insert_id;
		}

		self::set_sources( $id, $source_ids );
		self::changed();

		return (int) $id;
	}

	/**
	 * Replaces the sources attached to a fact.
	 *
	 * @param int   $fact_id    Fact ID.
	 * @param int[] $source_ids Source IDs.
	 */
	public static function set_sources( $fact_id, $source_ids ) {
		global $wpdb;
		FactFusion_Sources::forget();
		$table = FactFusion_DB::table( 'fact_sources' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( $table, array( 'fact_id' => (int) $fact_id ), array( '%d' ) );
		foreach ( $source_ids as $source_id ) {
			if ( FactFusion_Sources::get( $source_id ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->insert(
					$table,
					array(
						'fact_id'   => (int) $fact_id,
						'source_id' => (int) $source_id,
					),
					array( '%d', '%d' )
				);
			}
		}
	}

	/**
	 * Deletes a fact with its source links and correction reports.
	 *
	 * @param int $id Fact ID.
	 */
	public static function delete( $id ) {
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( FactFusion_DB::table( 'fact_sources' ), array( 'fact_id' => (int) $id ), array( '%d' ) );
		$wpdb->delete( FactFusion_DB::table( 'corrections' ), array( 'fact_id' => (int) $id ), array( '%d' ) );
		$wpdb->delete( FactFusion_DB::table( 'facts' ), array( 'id' => (int) $id ), array( '%d' ) );
		// phpcs:enable
		self::changed();
	}

	/**
	 * Public URL of a fact.
	 *
	 * @param object $fact Fact row.
	 * @return string
	 */
	public static function url( $fact ) {
		return home_url( user_trailingslashit( 'fact/' . $fact->slug ) );
	}

	/**
	 * Clears cached lists after any fact is created, changed or deleted.
	 */
	public static function changed() {
		delete_transient( self::POOL_TRANSIENT );
		do_action( 'factfusion_facts_changed' );
	}

	/**
	 * Published fact IDs with their category, in fact-number order. Cached so
	 * random picks never scan the table.
	 *
	 * @return array<int,int> Fact ID => category ID.
	 */
	public static function pool() {
		global $wpdb;
		$pool = get_transient( self::POOL_TRANSIENT );
		if ( is_array( $pool ) ) {
			return $pool;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT id, category_id FROM %i WHERE status = %s ORDER BY fact_number ASC', FactFusion_DB::table( 'facts' ), 'published' ) );
		$pool = array();
		foreach ( $rows as $row ) {
			$pool[ (int) $row->id ] = (int) $row->category_id;
		}
		set_transient( self::POOL_TRANSIENT, $pool, DAY_IN_SECONDS );
		return $pool;
	}

	/**
	 * Published fact totals per category.
	 *
	 * @return array<int,int> Category ID => published facts.
	 */
	public static function published_counts() {
		return array_count_values( self::pool() );
	}

	/**
	 * One random published fact, straight from the database. No AI involved.
	 *
	 * @param int $category_id Limit to a category, 0 for any.
	 * @param int $exclude_id  Fact to avoid repeating.
	 * @return object|null
	 */
	public static function random_published( $category_id = 0, $exclude_id = 0 ) {
		$pool = self::pool();
		if ( $category_id ) {
			$pool = array_filter(
				$pool,
				static function ( $category ) use ( $category_id ) {
					return (int) $category_id === $category;
				}
			);
		}
		$ids = array_keys( $pool );
		if ( count( $ids ) > 1 ) {
			$ids = array_values( array_diff( $ids, array( (int) $exclude_id ) ) );
		}
		if ( ! $ids ) {
			return null;
		}
		return self::get( $ids[ wp_rand( 0, count( $ids ) - 1 ) ] );
	}

	/**
	 * Published facts for a list of IDs, in the order given.
	 *
	 * @param int[] $ids Fact IDs.
	 * @return object[]
	 */
	public static function get_published_many( $ids ) {
		global $wpdb;
		$ids = array_slice( array_values( array_unique( array_filter( array_map( 'absint', (array) $ids ) ) ) ), 0, 50 );
		if ( ! $ids ) {
			return array();
		}
		$marks = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE status = %s AND id IN ({$marks})", array_merge( array( FactFusion_DB::table( 'facts' ), 'published' ), $ids ) ) );
		// phpcs:enable
		$by_id = array();
		foreach ( $rows as $row ) {
			$by_id[ (int) $row->id ] = $row;
		}
		$ordered = array();
		foreach ( $ids as $id ) {
			if ( isset( $by_id[ $id ] ) ) {
				$ordered[] = $by_id[ $id ];
			}
		}
		return $ordered;
	}

	/**
	 * Published facts that may appear in search engines: verified and not opted out.
	 *
	 * @return object[] Rows with id, slug, title, category_id, date_updated.
	 */
	public static function indexable() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id, slug, title, category_id, date_updated FROM %i WHERE status = %s AND verification_status = %s AND is_indexable = %d ORDER BY fact_number ASC',
				FactFusion_DB::table( 'facts' ),
				'published',
				'verified',
				1
			)
		);
	}

	/**
	 * Other published facts in the same category.
	 *
	 * @param object $fact  Fact row.
	 * @param int    $limit How many.
	 * @return object[]
	 */
	public static function related( $fact, $limit = 3 ) {
		// The next facts in the same category, wrapping round at the end, so
		// every fact in a category is linked from other facts and none is orphaned.
		$ids = array();
		foreach ( self::pool() as $id => $category_id ) {
			if ( (int) $fact->category_id === $category_id ) {
				$ids[] = $id;
			}
		}
		$total = count( $ids );
		$at    = array_search( (int) $fact->id, $ids, true );
		$picks = array();
		if ( false === $at ) {
			$picks = array_slice( $ids, 0, $limit );
		} else {
			for ( $step = 1; $step < $total && count( $picks ) < $limit; $step++ ) {
				$picks[] = $ids[ ( $at + $step ) % $total ];
			}
		}
		return self::get_published_many( $picks );
	}

	/**
	 * Searches published facts. Title matches come first.
	 *
	 * @param string $search Search text.
	 * @param int    $limit  Maximum results.
	 * @param int    $offset Results to skip.
	 * @return array{items:object[],total:int}
	 */
	public static function search_published( $search, $limit = 8, $offset = 0 ) {
		global $wpdb;
		$table = FactFusion_DB::table( 'facts' );
		$like  = '%' . $wpdb->esc_like( $search ) . '%';
		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$total = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE status = %s AND (title LIKE %s OR claim LIKE %s OR short_answer LIKE %s)', $table, 'published', $like, $like, $like ) );
		$items = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE status = %s AND (title LIKE %s OR claim LIKE %s OR short_answer LIKE %s) ORDER BY CASE WHEN title LIKE %s THEN 0 ELSE 1 END, fact_number DESC LIMIT %d OFFSET %d',
				$table,
				'published',
				$like,
				$like,
				$like,
				$like,
				$limit,
				$offset
			)
		);
		// phpcs:enable
		return array(
			'items' => $items,
			'total' => $total,
		);
	}

	/**
	 * Counts one view of a fact.
	 *
	 * @param int $id Fact ID.
	 */
	public static function bump_hits( $id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( $wpdb->prepare( 'UPDATE %i SET hit_count = hit_count + 1 WHERE id = %d', FactFusion_DB::table( 'facts' ), $id ) );
	}

	/**
	 * The fields of a published fact that are safe to show anyone.
	 *
	 * @param object $fact    Fact row.
	 * @param bool   $compact Leave out the explanation.
	 * @return array
	 */
	public static function public_data( $fact, $compact = false ) {
		$category = $fact->category_id ? FactFusion_Categories::get( $fact->category_id ) : null;
		$verified = 'verified' === $fact->verification_status && $fact->date_verified;
		$stamp    = $verified ? strtotime( $fact->date_verified . ' UTC' ) : 0;
		$author   = $verified && $fact->author_id ? get_userdata( $fact->author_id ) : false;
		$sources  = array();
		foreach ( FactFusion_Sources::for_fact( $fact->id ) as $source ) {
			$sources[] = array(
				'name'  => $source->source_name,
				'title' => $source->title,
				'url'   => $source->url,
				'tier'  => $source->tier,
			);
		}

		$data = array(
			'id'            => (int) $fact->id,
			'number'        => (int) $fact->fact_number,
			'title'         => $fact->title,
			'slug'          => $fact->slug,
			'url'           => self::url( $fact ),
			'claim'         => $fact->claim,
			'short_answer'  => $fact->short_answer,
			'category'      => $category ? array(
				'slug' => $category->slug,
				'name' => $category->name,
				'icon' => $category->icon,
			) : null,
			'sources'       => $sources,
			'confidence'    => (int) $fact->confidence_score,
			'verified_date' => $stamp ? gmdate( 'Y-m-d', $stamp ) : null,
			'verified_by'   => $author ? $author->display_name : null,
			'ai_assisted'   => (bool) $fact->is_ai_assisted,
			'embed_url'     => home_url( user_trailingslashit( 'embed/fact/' . $fact->slug ) ),
		);
		if ( ! $compact ) {
			$data['explanation'] = wpautop( wp_kses_post( $fact->explanation ) );
		}
		return $data;
	}
}
