<?php
/**
 * Category storage.
 *
 * @package FactFusion_AI_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CRUD for ff_categories.
 */
final class FactFusion_Categories {

	/**
	 * Every category, read once per request.
	 *
	 * @var object[]|null
	 */
	private static $rows = null;

	/**
	 * The 12 launch categories: slug, name, icon, description.
	 */
	const DEFAULTS = array(
		array( 'science', 'Science', '🔬', 'Physics, chemistry and how the natural world works.' ),
		array( 'animal', 'Animal', '🐾', 'Facts about animals and how they live.' ),
		array( 'history', 'History', '🏛️', 'Events, people and inventions from the past.' ),
		array( 'space', 'Space', '🚀', 'Planets, stars and space exploration.' ),
		array( 'weird', 'Weird', '🤯', 'True facts that sound like they should not be.' ),
		array( 'human-body', 'Human Body', '🦴', 'How the human body is built and how it works.' ),
		array( 'geography', 'Geography', '🌍', 'Places, landscapes and the shape of the planet.' ),
		array( 'technology', 'Technology', '💻', 'Computing, engineering and inventions.' ),
		array( 'food', 'Food', '🍎', 'What we eat and where it comes from.' ),
		array( 'psychology', 'Psychology', '🧠', 'How people think, remember and behave.' ),
		array( 'ocean', 'Ocean', '🌊', 'The seas and the life in them.' ),
		array( 'nature', 'Nature', '🌿', 'Plants, weather and the living world.' ),
	);

	/**
	 * Inserts any default category whose slug is missing.
	 */
	public static function seed_defaults() {
		foreach ( self::DEFAULTS as $order => $row ) {
			if ( self::get_by_slug( $row[0] ) ) {
				continue;
			}
			self::save(
				array(
					'slug'        => $row[0],
					'name'        => $row[1],
					'icon'        => $row[2],
					'description' => $row[3],
					'sort_order'  => ( $order + 1 ) * 10,
					'is_active'   => 1,
				)
			);
		}
	}

	/**
	 * All categories ordered for display.
	 *
	 * @param bool $active_only Only active categories.
	 * @return object[]
	 */
	public static function all( $active_only = false ) {
		global $wpdb;
		if ( null === self::$rows ) {
			// One query per request; pages ask for categories many times.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			self::$rows = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY sort_order ASC, name ASC', FactFusion_DB::table( 'categories' ) ) );
		}
		if ( ! $active_only ) {
			return self::$rows;
		}
		return array_values(
			array_filter(
				self::$rows,
				static function ( $row ) {
					return (int) $row->is_active;
				}
			)
		);
	}

	/**
	 * Forgets the categories read on this request, after a change.
	 */
	public static function forget() {
		self::$rows = null;
	}

	/**
	 * One category by ID.
	 *
	 * @param int $id Category ID.
	 * @return object|null
	 */
	public static function get( $id ) {
		foreach ( self::all() as $row ) {
			if ( (int) $row->id === (int) $id ) {
				return $row;
			}
		}
		return null;
	}

	/**
	 * One category by slug.
	 *
	 * @param string $slug Category slug.
	 * @return object|null
	 */
	public static function get_by_slug( $slug ) {
		foreach ( self::all() as $row ) {
			if ( (string) $row->slug === (string) $slug ) {
				return $row;
			}
		}
		return null;
	}

	/**
	 * Fact totals per category.
	 *
	 * @return array<int,int> Category ID => fact count.
	 */
	public static function fact_counts() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows   = $wpdb->get_results( $wpdb->prepare( 'SELECT category_id, COUNT(*) AS total FROM %i GROUP BY category_id', FactFusion_DB::table( 'facts' ) ) );
		$counts = array();
		foreach ( $rows as $row ) {
			$counts[ (int) $row->category_id ] = (int) $row->total;
		}
		return $counts;
	}

	/**
	 * Creates or updates a category.
	 *
	 * @param array $data Raw field values.
	 * @param int   $id   Category ID, 0 to create.
	 * @return int|WP_Error Category ID.
	 */
	public static function save( $data, $id = 0 ) {
		global $wpdb;
		$table = FactFusion_DB::table( 'categories' );
		$name  = sanitize_text_field( $data['name'] ?? '' );
		$slug  = sanitize_title( ! empty( $data['slug'] ) ? $data['slug'] : $name );

		if ( '' === $name || '' === $slug ) {
			return new WP_Error( 'ff_category_name', __( 'A category needs a name.', 'factfusion-ai' ) );
		}

		$existing = self::get_by_slug( $slug );
		if ( $existing && (int) $existing->id !== (int) $id ) {
			return new WP_Error( 'ff_category_slug', __( 'Another category already uses that slug.', 'factfusion-ai' ) );
		}

		$row = array(
			'slug'             => $slug,
			'name'             => $name,
			'description'      => sanitize_textarea_field( $data['description'] ?? '' ),
			'icon'             => sanitize_text_field( $data['icon'] ?? '' ),
			'sort_order'       => (int) ( $data['sort_order'] ?? 0 ),
			'is_active'        => empty( $data['is_active'] ) ? 0 : 1,
			'meta_title'       => sanitize_text_field( $data['meta_title'] ?? '' ),
			'meta_description' => sanitize_text_field( $data['meta_description'] ?? '' ),
		);
		$formats = array( '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s' );

		// The category page text. On an update, a field that was not sent is left as it is.
		foreach ( array( 'intro', 'faq', 'featured' ) as $field ) {
			if ( array_key_exists( $field, $data ) || ! $id ) {
				$row[ $field ] = self::clean_text( $data[ $field ] ?? '' );
				$formats[]     = '%s';
			}
		}

		if ( $id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$ok = $wpdb->update( $table, $row, array( 'id' => (int) $id ), $formats, array( '%d' ) );
			self::forget();
			return false === $ok ? new WP_Error( 'ff_db', __( 'The category could not be saved.', 'factfusion-ai' ) ) : (int) $id;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$ok = $wpdb->insert( $table, $row, $formats );
		self::forget();
		return $ok ? (int) $wpdb->insert_id : new WP_Error( 'ff_db', __( 'The category could not be saved.', 'factfusion-ai' ) );
	}

	/**
	 * Plain text with paragraph breaks kept: no markup, normalised line endings.
	 *
	 * @param string $text Raw text.
	 * @return string
	 */
	private static function clean_text( $text ) {
		$text = str_replace( array( "\r\n", "\r" ), "\n", (string) $text );
		return trim( preg_replace( "/\n{3,}/", "\n\n", sanitize_textarea_field( $text ) ) );
	}

	/**
	 * The questions and answers written for a category page. They are stored
	 * as plain text: a line starting "Q:" followed by a line starting "A:".
	 *
	 * @param object $category Category row.
	 * @return array<int,array{0:string,1:string}>
	 */
	public static function faq( $category ) {
		$pairs    = array();
		$question = '';
		foreach ( explode( "\n", (string) ( $category->faq ?? '' ) ) as $line ) {
			$line = trim( $line );
			if ( 0 === stripos( $line, 'Q:' ) ) {
				$question = trim( substr( $line, 2 ) );
			} elseif ( 0 === stripos( $line, 'A:' ) && '' !== $question ) {
				$answer = trim( substr( $line, 2 ) );
				if ( '' !== $answer ) {
					$pairs[] = array( $question, $answer );
				}
				$question = '';
			}
		}
		return $pairs;
	}

	/**
	 * The published facts chosen to lead a category page, in the order given.
	 * Facts that are not published, or belong to another category, are skipped.
	 *
	 * @param object $category Category row.
	 * @return object[]
	 */
	public static function featured_facts( $category ) {
		$facts = array();
		foreach ( preg_split( '/[\s,]+/', (string) ( $category->featured ?? '' ) ) as $slug ) {
			$slug = sanitize_title( $slug );
			if ( '' === $slug || isset( $facts[ $slug ] ) ) {
				continue;
			}
			$fact = FactFusion_Facts::get_by_slug( $slug );
			if ( $fact && 'published' === $fact->status && (int) $fact->category_id === (int) $category->id ) {
				$facts[ $slug ] = $fact;
			}
		}
		return array_values( $facts );
	}

	/**
	 * Starter text for the category pages, from data/category-content.php.
	 *
	 * @return array<string,array{intro:string,faq:array,featured:string[],meta_description:string}>
	 */
	public static function starter_content() {
		$file = FACTFUSION_DIR . 'data/category-content.php';
		return is_readable( $file ) ? (array) include $file : array();
	}

	/**
	 * Fills the intro, questions, featured facts and meta description of each
	 * category from the starter text, but only where the field is still empty.
	 * Nothing the editor has written is ever replaced.
	 *
	 * @return string[] Slugs of the categories that changed.
	 */
	public static function fill_missing_content() {
		$changed = array();
		foreach ( self::starter_content() as $slug => $content ) {
			$category = self::get_by_slug( $slug );
			if ( ! $category ) {
				continue;
			}
			$data = (array) $category;
			$new  = false;
			$faq  = '';
			foreach ( $content['faq'] ?? array() as $pair ) {
				$faq .= 'Q: ' . $pair[0] . "\nA: " . $pair[1] . "\n\n";
			}
			$starter = array(
				'intro'            => $content['intro'] ?? '',
				'faq'              => $faq,
				'featured'         => implode( "\n", $content['featured'] ?? array() ),
				'meta_description' => $content['meta_description'] ?? '',
			);
			foreach ( $starter as $field => $value ) {
				if ( '' === trim( (string) ( $category->$field ?? '' ) ) && '' !== trim( $value ) ) {
					$data[ $field ] = $value;
					$new            = true;
				}
			}
			if ( $new && ! is_wp_error( self::save( $data, (int) $category->id ) ) ) {
				$changed[] = $slug;
			}
		}
		if ( $changed ) {
			do_action( 'factfusion_facts_changed' );
		}
		return $changed;
	}

	/**
	 * Deletes a category that has no facts.
	 *
	 * @param int $id Category ID.
	 * @return true|WP_Error
	 */
	public static function delete( $id ) {
		global $wpdb;
		$counts = self::fact_counts();
		if ( ! empty( $counts[ (int) $id ] ) ) {
			return new WP_Error( 'ff_category_in_use', __( 'That category still has facts. Move them first, or mark the category inactive.', 'factfusion-ai' ) );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( FactFusion_DB::table( 'categories' ), array( 'id' => (int) $id ), array( '%d' ) );
		self::forget();
		return true;
	}
}
