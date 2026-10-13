<?php
/**
 * Review queue: imported pending facts, best sourced first, with a weekly publishing target.
 *
 * FactFusion AI → Review queue. Each row opens the normal fact editor, where the editor
 * drafts the explanation with AI, checks the source, sets Verified and publishes.
 */

defined( 'ABSPATH' ) || exit;

const FFC_WEEKLY_TARGET = 40;

/**
 * Imported facts used to arrive with is_indexable = 0, which would keep them out of
 * search even after publishing. Pending facts are never public, so turning it on is safe.
 */
add_action(
	'admin_init',
	static function () {
		global $wpdb;
		if ( get_option( 'ffc_import_indexable_fixed' ) || ! class_exists( 'FactFusion_DB' ) ) {
			return;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( $wpdb->prepare( 'UPDATE %i SET is_indexable = 1 WHERE status = %s AND source_summary LIKE %s', FactFusion_DB::table( 'facts' ), 'pending', 'Dataset import%' ) );
		update_option( 'ffc_import_indexable_fixed', 1 );
	}
);

/**
 * The import put "Dataset import, quality X" in source_summary. That field is sent to the
 * AI explanation draft, so clear it; quality is still in confidence_score.
 */
add_action(
	'admin_init',
	static function () {
		global $wpdb;
		if ( get_option( 'ffc_import_summary_cleared' ) || ! get_option( 'ffc_import_indexable_fixed' ) || ! class_exists( 'FactFusion_DB' ) ) {
			return;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( $wpdb->prepare( 'UPDATE %i SET source_summary = %s WHERE source_summary LIKE %s', FactFusion_DB::table( 'facts' ), '', 'Dataset import%' ) );
		update_option( 'ffc_import_summary_cleared', 1 );
	},
	20
);

add_action(
	'admin_menu',
	static function () {
		if ( ! class_exists( 'FactFusion_Facts' ) ) {
			return;
		}
		add_submenu_page( 'factfusion', 'Review queue', 'Review queue', factfusion_capability(), 'ffc-review', 'ffc_review_page', 2 );
	},
	20
);

function ffc_review_page() {
	global $wpdb;
	$table    = FactFusion_DB::table( 'facts' );
	$category = absint( $_GET['category_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
	$paged    = max( 1, absint( $_GET['paged'] ?? 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification
	$per_page = 50;

	$week_start = gmdate( 'Y-m-d 00:00:00', strtotime( 'monday this week' ) );
	// phpcs:disable WordPress.DB.DirectDatabaseQuery
	$this_week = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE status = %s AND date_verified >= %s', $table, 'published', $week_start ) );
	$waiting   = $wpdb->get_results( $wpdb->prepare( 'SELECT category_id, confidence_score, COUNT(*) AS n FROM %i WHERE status = %s GROUP BY category_id, confidence_score', $table, 'pending' ) );
	$where     = $category ? $wpdb->prepare( ' AND category_id = %d', $category ) : '';
	$total     = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE status = %s{$where}", $table, 'pending' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$items     = $wpdb->get_results( $wpdb->prepare( "SELECT id, title, claim, category_id, confidence_score, is_ai_assisted FROM %i WHERE status = %s{$where} ORDER BY confidence_score DESC, fact_number ASC LIMIT %d OFFSET %d", $table, 'pending', $per_page, ( $paged - 1 ) * $per_page ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	// phpcs:enable

	$cats = array();
	foreach ( FactFusion_Categories::all() as $cat ) {
		$cats[ (int) $cat->id ] = $cat->name;
	}
	$by_cat = array();
	foreach ( $waiting as $row ) {
		$key                       = (int) $row->category_id;
		$by_cat[ $key ]['total']   = ( $by_cat[ $key ]['total'] ?? 0 ) + (int) $row->n;
		$by_cat[ $key ]['a']       = ( $by_cat[ $key ]['a'] ?? 0 ) + ( (int) $row->confidence_score >= 60 ? (int) $row->n : 0 );
	}
	uasort( $by_cat, static fn( $x, $y ) => $y['a'] <=> $x['a'] );

	$quality = static function ( $score ) {
		if ( $score >= 60 ) {
			return '<span style="color:#008a20;font-weight:600">A</span> source link ready';
		}
		if ( $score >= 30 ) {
			return '<span style="color:#996800;font-weight:600">B</span> check carefully';
		}
		return '<span style="color:#b32d2e;font-weight:600">C</span> find a source';
	};
	$base = admin_url( 'admin.php?page=ffc-review' );
	?>
	<div class="wrap">
		<h1>Review queue</h1>
		<p style="font-size:15px">This week: <strong><?php echo (int) $this_week; ?> / <?php echo (int) FFC_WEEKLY_TARGET; ?></strong> facts published. Waiting: <?php echo (int) array_sum( array_column( $by_cat, 'total' ) ); ?>.</p>
		<p>For each fact: open it, click <em>Draft explanation with AI</em>, write the short answer, open the source and check it says the same thing, add one line of your own, then set <em>Verified</em> and <em>Published</em>. If the source does not support it, set it to <em>Archived</em>.</p>

		<p>
			<a href="<?php echo esc_url( $base ); ?>"<?php echo $category ? '' : ' style="font-weight:600"'; ?>>All</a>
			<?php foreach ( $by_cat as $cat_id => $count ) : ?>
				| <a href="<?php echo esc_url( add_query_arg( 'category_id', $cat_id, $base ) ); ?>"<?php echo $category === $cat_id ? ' style="font-weight:600"' : ''; ?>><?php echo esc_html( $cats[ $cat_id ] ?? 'No category' ); ?> (<?php echo (int) $count['a']; ?> A / <?php echo (int) $count['total']; ?>)</a>
			<?php endforeach; ?>
		</p>

		<table class="widefat striped">
			<thead><tr><th style="width:55%">Claim</th><th>Category</th><th>Quality</th><th></th></tr></thead>
			<tbody>
			<?php foreach ( $items as $fact ) : ?>
				<tr>
					<td><?php echo esc_html( $fact->claim ); ?><?php echo $fact->is_ai_assisted ? ' <em>(AI-written in dataset)</em>' : ''; ?></td>
					<td><?php echo esc_html( $cats[ (int) $fact->category_id ] ?? '—' ); ?></td>
					<td><?php echo wp_kses_post( $quality( (int) $fact->confidence_score ) ); ?></td>
					<td><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=factfusion-facts&view=edit&id=' . (int) $fact->id ) ); ?>">Review</a></td>
				</tr>
			<?php endforeach; ?>
			<?php if ( ! $items ) : ?>
				<tr><td colspan="4">Nothing waiting. Run Tools → Import 10k Facts first.</td></tr>
			<?php endif; ?>
			</tbody>
		</table>
		<?php
		echo '<p>' . paginate_links( // phpcs:ignore WordPress.Security.EscapeOutput
			array(
				'base'    => add_query_arg( 'paged', '%#%' ),
				'format'  => '',
				'current' => $paged,
				'total'   => max( 1, (int) ceil( $total / $per_page ) ),
			)
		) . '</p>';
		?>
	</div>
	<?php
}
