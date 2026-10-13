<?php
/**
 * CSV import.
 *
 * @package FactFusion_AI_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Imports facts from CSV. Imported facts always arrive as "pending" and
 * "unverified": a person must check each one before it can be published.
 */
final class FactFusion_Importer {

	const MAX_ROWS = 50;

	const REQUIRED = array( 'title', 'claim', 'short_answer', 'explanation', 'category', 'source_name', 'source_url', 'source_tier' );

	const OPTIONAL = array( 'confidence_score', 'is_ai_assisted', 'source_summary', 'source_title', 'source_publisher', 'source_author', 'source_date_published', 'source_date_accessed' );

	/**
	 * Imports one CSV file.
	 *
	 * @param string $path Readable file path.
	 * @return array{imported:int,skipped:string[],errors:string[]}|WP_Error
	 */
	public static function import_file( $path ) {
		$result = array(
			'imported' => 0,
			'skipped'  => array(),
			'errors'   => array(),
		);

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$handle = is_readable( $path ) ? fopen( $path, 'r' ) : false;
		if ( ! $handle ) {
			return new WP_Error( 'ff_import_read', __( 'The file could not be read.', 'factfusion-ai' ) );
		}

		$header = fgetcsv( $handle, 0, ',', '"', '' );
		if ( ! $header ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return new WP_Error( 'ff_import_empty', __( 'The file is empty.', 'factfusion-ai' ) );
		}
		$header[0] = preg_replace( '/^\xEF\xBB\xBF/', '', (string) $header[0] );
		$header    = array_map(
			static function ( $column ) {
				return sanitize_key( trim( (string) $column ) );
			},
			$header
		);

		$missing = array_diff( self::REQUIRED, $header );
		if ( $missing ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			/* translators: %s: comma-separated column names. */
			return new WP_Error( 'ff_import_columns', sprintf( __( 'Missing column(s): %s', 'factfusion-ai' ), implode( ', ', $missing ) ) );
		}

		$rows = array();
		// phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition, WordPress.CodeAnalysis.AssignmentInCondition
		while ( false !== ( $cells = fgetcsv( $handle, 0, ',', '"', '' ) ) ) {
			if ( array( null ) === $cells || '' === trim( implode( '', array_map( 'strval', $cells ) ) ) ) {
				continue;
			}
			$rows[] = $cells;
		}
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		if ( count( $rows ) > self::MAX_ROWS ) {
			/* translators: 1: rows in file, 2: maximum rows. */
			return new WP_Error( 'ff_import_batch', sprintf( __( 'The file has %1$d facts. Import at most %2$d at a time so each batch can be checked.', 'factfusion-ai' ), count( $rows ), self::MAX_ROWS ) );
		}

		foreach ( $rows as $index => $cells ) {
			$line = $index + 2;
			$row  = array();
			foreach ( $header as $position => $column ) {
				$row[ $column ] = isset( $cells[ $position ] ) ? trim( (string) $cells[ $position ] ) : '';
			}
			$outcome = self::import_row( $row );
			if ( is_wp_error( $outcome ) ) {
				/* translators: 1: line number, 2: error messages. */
				$message = sprintf( __( 'Line %1$d: %2$s', 'factfusion-ai' ), $line, implode( ' ', $outcome->get_error_messages() ) );
				if ( 'ff_import_duplicate' === $outcome->get_error_code() ) {
					$result['skipped'][] = $message;
				} else {
					$result['errors'][] = $message;
				}
				continue;
			}
			++$result['imported'];
		}

		return $result;
	}

	/**
	 * Imports one row.
	 *
	 * @param array<string,string> $row Column => value.
	 * @return int|WP_Error Fact ID.
	 */
	private static function import_row( $row ) {
		foreach ( self::REQUIRED as $column ) {
			if ( '' === $row[ $column ] ) {
				/* translators: %s: column name. */
				return new WP_Error( 'ff_import_required', sprintf( __( 'Column "%s" is empty.', 'factfusion-ai' ), $column ) );
			}
		}

		$claim = trim( sanitize_textarea_field( $row['claim'] ) );
		if ( FactFusion_Facts::get_by_claim( $claim ) ) {
			return new WP_Error( 'ff_import_duplicate', __( 'A fact with this claim already exists. Skipped.', 'factfusion-ai' ) );
		}

		$category = FactFusion_Categories::get_by_slug( sanitize_title( $row['category'] ) );
		if ( ! $category ) {
			/* translators: %s: category value. */
			return new WP_Error( 'ff_import_category', sprintf( __( 'Unknown category "%s".', 'factfusion-ai' ), $row['category'] ) );
		}

		$source = FactFusion_Sources::find_by_url( $row['source_url'] );
		if ( $source ) {
			$source_id = (int) $source->id;
		} else {
			$source_id = FactFusion_Sources::save(
				array(
					'source_name'    => $row['source_name'],
					'url'            => $row['source_url'],
					'tier'           => $row['source_tier'],
					'title'          => $row['source_title'] ?? '',
					'publisher'      => $row['source_publisher'] ?? '',
					'author'         => $row['source_author'] ?? '',
					'date_published' => $row['source_date_published'] ?? '',
					'date_accessed'  => $row['source_date_accessed'] ?? '',
					'is_active'      => 1,
				)
			);
			if ( is_wp_error( $source_id ) ) {
				return $source_id;
			}
		}

		return FactFusion_Facts::save(
			array(
				'title'               => $row['title'],
				'claim'               => $claim,
				'short_answer'        => $row['short_answer'],
				'explanation'         => $row['explanation'],
				'category_id'         => (int) $category->id,
				'status'              => 'pending',
				'verification_status' => 'unverified',
				'confidence_score'    => $row['confidence_score'] ?? 0,
				'source_summary'      => $row['source_summary'] ?? '',
				'is_ai_assisted'      => in_array( strtolower( $row['is_ai_assisted'] ?? '' ), array( '1', 'yes', 'true' ), true ) ? 1 : 0,
				'is_indexable'        => 1,
				'source_ids'          => array( $source_id ),
			)
		);
	}
}
