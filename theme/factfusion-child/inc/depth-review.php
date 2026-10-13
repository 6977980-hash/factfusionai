<?php
/**
 * FactFusion AI > Depth check: where an editor reviews the phase 3 additions.
 *
 * - "How we know" notes: read the quotes, open the sources, fix the text if needed, tick, save.
 *   A ticked note shows "Checked against these sources by <name>" instead of the AI label.
 * - Hub and myth pages: open the page, and if it is right, mark it reviewed. Its byline then
 *   names the editor, and the theme never changes its text again.
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'admin_menu',
	static function () {
		if ( ! class_exists( 'FactFusion_DB' ) ) {
			return;
		}
		add_submenu_page( 'factfusion', 'Depth check', 'Depth check', factfusion_capability(), 'ffc-depth', 'ffc_depth_review_page', 2 );
	},
	20
);

/** Facts whose "How we know" note an editor has not checked yet. */
function ffc_depth_unchecked( $limit = 10 ) {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE status = %s AND how_we_know IS NOT NULL AND how_we_know <> '' AND depth_checked_by = 0 ORDER BY category_id, fact_number LIMIT %d", FactFusion_DB::table( 'facts' ), 'published', $limit ) );
}

add_action(
	'admin_post_ffc_depth_save',
	static function () {
		if ( ! current_user_can( factfusion_capability() ) ) {
			wp_die( 'Not allowed.' );
		}
		check_admin_referer( 'ffc_depth_save' );
		global $wpdb;
		$table = FactFusion_DB::table( 'facts' );
		$done  = 0;
		$urls  = array();
		foreach ( (array) ( $_POST['fact'] ?? array() ) as $id => $row ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$fact = FactFusion_Facts::get( (int) $id );
			if ( ! $fact || ! is_array( $row ) ) {
				continue;
			}
			$update = array(
				'question'    => mb_substr( sanitize_text_field( wp_unslash( $row['question'] ?? '' ) ), 0, 255 ),
				'how_we_know' => sanitize_textarea_field( wp_unslash( $row['how_we_know'] ?? '' ) ),
			);
			if ( ! empty( $row['checked'] ) ) {
				$update['depth_checked_by'] = get_current_user_id();
				$update['depth_checked_at'] = FactFusion_DB::now();
				++$done;
			}
			$wpdb->update( $table, $update, array( 'id' => (int) $fact->id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$urls[] = FactFusion_Facts::url( $fact );
		}
		if ( $urls && function_exists( 'ffc_purge' ) ) {
			ffc_purge( $urls );
		}
		wp_safe_redirect( add_query_arg( array( 'page' => 'ffc-depth', 'checked' => $done ), admin_url( 'admin.php' ) ) );
		exit;
	}
);

add_action(
	'admin_post_ffc_depth_page_ok',
	static function () {
		$id = (int) ( $_POST['page_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! current_user_can( factfusion_capability() ) || ! current_user_can( 'edit_page', $id ) ) {
			wp_die( 'Not allowed.' );
		}
		check_admin_referer( 'ffc_depth_page_ok_' . $id );
		delete_post_meta( $id, FFC_DEPTH_DRAFT_META );
		delete_post_meta( $id, FFC_DEPTH_HASH_META );
		wp_update_post( array( 'ID' => $id, 'post_author' => get_current_user_id() ) );
		if ( function_exists( 'ffc_purge' ) ) {
			ffc_purge( array( get_permalink( $id ) ) );
		}
		wp_safe_redirect( add_query_arg( array( 'page' => 'ffc-depth', 'reviewed' => 1 ), admin_url( 'admin.php' ) ) );
		exit;
	}
);

function ffc_depth_review_page() {
	if ( ! function_exists( 'ffc_depth_db_ready' ) || ! ffc_depth_db_ready() ) {
		echo '<div class="wrap"><h1>Depth check</h1><p>Setting up. Reload in a moment.</p></div>';
		return;
	}
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$left  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE status = %s AND how_we_know IS NOT NULL AND how_we_know <> '' AND depth_checked_by = 0", FactFusion_DB::table( 'facts' ), 'published' ) );
	$facts = ffc_depth_unchecked( 10 );
	?>
	<div class="wrap">
		<h1>Depth check</h1>
		<?php if ( isset( $_GET['checked'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
			<div class="notice notice-success"><p><?php echo (int) $_GET['checked']; // phpcs:ignore ?> notes marked as checked.</p></div>
		<?php endif; ?>
		<?php if ( isset( $_GET['reviewed'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
			<div class="notice notice-success"><p>Page marked as reviewed. Your name is now on it.</p></div>
		<?php endif; ?>

		<h2>Pages waiting for your review</h2>
		<p>These topic and myth pages are live with an "AI-assisted, waiting for review" note. Open each one, read it, and if it is right, click "Reviewed". To change the text, edit the page in WordPress first.</p>
		<table class="widefat striped" style="max-width:900px"><tbody>
		<?php
		$any = false;
		foreach ( ffc_depth_pages() as $slug => $def ) {
			$page = get_page_by_path( $slug, OBJECT, 'page' );
			if ( ! $page || ! get_post_meta( $page->ID, FFC_DEPTH_DRAFT_META, true ) ) {
				continue;
			}
			$any = true;
			?>
			<tr>
				<td><strong><?php echo esc_html( get_the_title( $page ) ); ?></strong><br><span style="color:#666"><?php echo esc_html( ucfirst( $def['kind'] ) ); ?></span></td>
				<td><a href="<?php echo esc_url( get_permalink( $page ) ); ?>" target="_blank" rel="noopener">Open page</a> · <a href="<?php echo esc_url( get_edit_post_link( $page->ID ) ); ?>">Edit</a></td>
				<td>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'ffc_depth_page_ok_' . $page->ID ); ?>
						<input type="hidden" name="action" value="ffc_depth_page_ok">
						<input type="hidden" name="page_id" value="<?php echo (int) $page->ID; ?>">
						<button class="button">Reviewed</button>
					</form>
				</td>
			</tr>
			<?php
		}
		if ( ! $any ) {
			echo '<tr><td>All pages are reviewed.</td></tr>';
		}
		?>
		</tbody></table>

		<h2 style="margin-top:2em">"How we know" notes (<?php echo (int) $left; ?> waiting)</h2>
		<p>Open each source link and check that the quote is really on the page and the note says nothing the sources do not. Fix the text if needed, tick the box, and save. Untick ones stay as they are.</p>
		<?php if ( ! $facts ) : ?>
			<p>Nothing waiting.</p>
		<?php else : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'ffc_depth_save' ); ?>
			<input type="hidden" name="action" value="ffc_depth_save">
			<?php
			foreach ( $facts as $fact ) :
				$quotes = ffc_depth_quotes( $fact );
				?>
				<div style="background:#fff;border:1px solid #ccd0d4;padding:12px 16px;margin:12px 0;max-width:900px">
					<h3 style="margin-top:0"><a href="<?php echo esc_url( FactFusion_Facts::url( $fact ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $fact->title ); ?></a></h3>
					<p><label>Question heading<br><input type="text" class="large-text" name="fact[<?php echo (int) $fact->id; ?>][question]" value="<?php echo esc_attr( $fact->question ); ?>"></label></p>
					<p><label>How we know<br><textarea class="large-text" rows="5" name="fact[<?php echo (int) $fact->id; ?>][how_we_know]"><?php echo esc_textarea( $fact->how_we_know ); ?></textarea></label></p>
					<ul>
					<?php foreach ( FactFusion_Sources::for_fact( $fact->id ) as $source ) : ?>
						<li><a href="<?php echo esc_url( $source->url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $source->source_name ); ?></a>
						<?php if ( ! empty( $quotes[ $source->url ] ) ) : ?>
							: <em>"<?php echo esc_html( $quotes[ $source->url ] ); ?>"</em>
						<?php endif; ?></li>
					<?php endforeach; ?>
					</ul>
					<p><label><input type="checkbox" name="fact[<?php echo (int) $fact->id; ?>][checked]" value="1"> I opened the sources and they say this</label></p>
				</div>
			<?php endforeach; ?>
			<p><button class="button button-primary">Save</button></p>
		</form>
		<?php endif; ?>
	</div>
	<?php
}
