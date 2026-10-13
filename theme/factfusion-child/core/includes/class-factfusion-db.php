<?php
/**
 * Schema and table helpers.
 *
 * @package FactFusion_AI_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Owns the seven Phase 1 tables. Table names always use the site's prefix.
 */
final class FactFusion_DB {

	const VERSION = '1.1.0';

	const TABLES = array( 'facts', 'categories', 'sources', 'fact_sources', 'corrections', 'rate_limits', 'ai_cache' );

	/**
	 * Full table name for a short name, e.g. "facts" => "wp_ff_facts".
	 *
	 * @param string $name Short table name.
	 * @return string
	 */
	public static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'ff_' . $name;
	}

	/**
	 * Current UTC time in MySQL format.
	 *
	 * @return string
	 */
	public static function now() {
		return current_time( 'mysql', true );
	}

	/**
	 * Creates or updates all tables.
	 */
	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$facts   = self::table( 'facts' );
		$cats    = self::table( 'categories' );
		$sources = self::table( 'sources' );
		$links   = self::table( 'fact_sources' );
		$corr    = self::table( 'corrections' );
		$rate    = self::table( 'rate_limits' );
		$cache   = self::table( 'ai_cache' );

		$sql = array();

		$sql[] = "CREATE TABLE {$facts} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			fact_number int(10) unsigned NOT NULL DEFAULT 0,
			title varchar(200) NOT NULL DEFAULT '',
			slug varchar(191) NOT NULL DEFAULT '',
			claim varchar(500) NOT NULL DEFAULT '',
			short_answer text NOT NULL,
			explanation longtext NOT NULL,
			category_id bigint(20) unsigned NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'draft',
			verification_status varchar(20) NOT NULL DEFAULT 'unverified',
			confidence_score tinyint(3) unsigned NOT NULL DEFAULT 0,
			source_summary varchar(255) NOT NULL DEFAULT '',
			author_id bigint(20) unsigned NOT NULL DEFAULT 0,
			featured tinyint(1) NOT NULL DEFAULT 0,
			is_indexable tinyint(1) NOT NULL DEFAULT 1,
			is_ai_assisted tinyint(1) NOT NULL DEFAULT 0,
			hit_count bigint(20) unsigned NOT NULL DEFAULT 0,
			date_created datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			date_updated datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			date_verified datetime DEFAULT NULL,
			last_reviewed datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug),
			KEY fact_number (fact_number),
			KEY status_category (status,category_id),
			KEY featured (featured)
		) {$charset};";

		$sql[] = "CREATE TABLE {$cats} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			slug varchar(100) NOT NULL DEFAULT '',
			name varchar(100) NOT NULL DEFAULT '',
			description text NOT NULL,
			icon varchar(50) NOT NULL DEFAULT '',
			sort_order int(11) NOT NULL DEFAULT 0,
			is_active tinyint(1) NOT NULL DEFAULT 1,
			meta_title varchar(255) NOT NULL DEFAULT '',
			meta_description varchar(320) NOT NULL DEFAULT '',
			intro longtext NOT NULL,
			faq longtext NOT NULL,
			featured text NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug)
		) {$charset};";

		$sql[] = "CREATE TABLE {$sources} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			source_name varchar(191) NOT NULL DEFAULT '',
			domain varchar(191) NOT NULL DEFAULT '',
			tier char(1) NOT NULL DEFAULT 'C',
			url text NOT NULL,
			title varchar(500) NOT NULL DEFAULT '',
			author varchar(191) NOT NULL DEFAULT '',
			publisher varchar(191) NOT NULL DEFAULT '',
			date_published date DEFAULT NULL,
			date_accessed date DEFAULT NULL,
			credibility_score tinyint(3) unsigned NOT NULL DEFAULT 0,
			notes text NOT NULL,
			is_active tinyint(1) NOT NULL DEFAULT 1,
			PRIMARY KEY  (id),
			KEY domain (domain),
			KEY tier (tier)
		) {$charset};";

		$sql[] = "CREATE TABLE {$links} (
			fact_id bigint(20) unsigned NOT NULL,
			source_id bigint(20) unsigned NOT NULL,
			PRIMARY KEY  (fact_id,source_id),
			KEY source_id (source_id)
		) {$charset};";

		$sql[] = "CREATE TABLE {$corr} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			fact_id bigint(20) unsigned NOT NULL DEFAULT 0,
			reporter_email varchar(191) NOT NULL DEFAULT '',
			what_is_wrong text NOT NULL,
			suggested_correction text NOT NULL,
			supporting_source text NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'new',
			admin_notes text NOT NULL,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			resolved_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY fact_id (fact_id),
			KEY status (status)
		) {$charset};";

		$sql[] = "CREATE TABLE {$rate} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			ip_hash char(64) NOT NULL DEFAULT '',
			action varchar(40) NOT NULL DEFAULT '',
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY lookup (ip_hash,action,created_at),
			KEY created_at (created_at)
		) {$charset};";

		$sql[] = "CREATE TABLE {$cache} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			cache_key varchar(191) NOT NULL DEFAULT '',
			model varchar(100) NOT NULL DEFAULT '',
			prompt_hash char(64) NOT NULL DEFAULT '',
			response longtext NOT NULL,
			tokens_used int(10) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			expires_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			UNIQUE KEY cache_key (cache_key),
			KEY expires_at (expires_at)
		) {$charset};";

		dbDelta( $sql );
		update_option( 'factfusion_db_version', self::VERSION );
	}

	/**
	 * Runs install() when the stored schema version is behind.
	 */
	public static function maybe_upgrade() {
		if ( get_option( 'factfusion_db_version' ) !== self::VERSION ) {
			self::install();
			FactFusion_Categories::seed_defaults();
		}
	}

	/**
	 * Lists tables that should exist but do not.
	 *
	 * @return string[] Full table names.
	 */
	public static function missing_tables() {
		global $wpdb;
		$missing = array();
		foreach ( self::TABLES as $name ) {
			$table = self::table( $name );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
			if ( $found !== $table ) {
				$missing[] = $table;
			}
		}
		return $missing;
	}
}
