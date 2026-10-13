<?php
/**
 * Dashboard screen.
 *
 * @package FactFusion_AI_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$ff_missing     = FactFusion_DB::missing_tables();
$ff_counts      = $ff_missing ? array() : FactFusion_Facts::status_counts();
$ff_live        = $ff_missing ? 0 : FactFusion_Facts::live_verified_count();
$ff_corrections = $ff_missing ? array() : FactFusion_Corrections::status_counts();
$ff_sources     = $ff_missing ? array( 'total' => 0 ) : FactFusion_Sources::query( array( 'per_page' => 1 ) );
$ff_categories  = $ff_missing ? array() : FactFusion_Categories::all();
$ff_minimum     = FactFusion_Admin::LAUNCH_MINIMUM;
?>
<h1><?php esc_html_e( 'FactFusion AI', 'factfusion-ai' ); ?></h1>
<hr class="wp-header-end">

<?php if ( $ff_missing ) : ?>
	<div class="notice notice-error inline">
		<p>
			<?php
			/* translators: %s: comma-separated table names. */
			printf( esc_html__( 'These database tables are missing: %s', 'factfusion-ai' ), esc_html( implode( ', ', $ff_missing ) ) );
			?>
		</p>
	</div>
<?php endif; ?>

<?php if ( ! get_option( 'permalink_structure' ) ) : ?>
	<div class="notice notice-warning inline">
		<p><?php esc_html_e( 'Permalinks are set to "Plain", so /fact/ addresses will not work. Choose another structure under Settings → Permalinks.', 'factfusion-ai' ); ?></p>
	</div>
<?php endif; ?>

<?php if ( ! $ff_missing ) : ?>
	<div class="factfusion-cards">
		<div class="factfusion-card">
			<h2><?php esc_html_e( 'Launch readiness', 'factfusion-ai' ); ?></h2>
			<p class="factfusion-figure">
				<?php
				/* translators: 1: published and verified facts, 2: facts needed to launch. */
				printf( esc_html__( '%1$d of %2$d', 'factfusion-ai' ), (int) $ff_live, (int) $ff_minimum );
				?>
			</p>
			<p><?php esc_html_e( 'facts are published and verified.', 'factfusion-ai' ); ?></p>
			<progress max="<?php echo esc_attr( $ff_minimum ); ?>" value="<?php echo esc_attr( min( $ff_minimum, $ff_live ) ); ?>"></progress>
			<?php if ( $ff_live < $ff_minimum ) : ?>
				<p class="description"><?php esc_html_e( 'A fact counts once you have checked it against its source, marked it Verified and published it.', 'factfusion-ai' ); ?></p>
			<?php endif; ?>
		</div>

		<div class="factfusion-card">
			<h2><?php esc_html_e( 'Facts', 'factfusion-ai' ); ?></h2>
			<ul>
				<?php foreach ( FactFusion_Facts::status_labels() as $ff_status => $ff_label ) : ?>
					<li>
						<a href="<?php echo esc_url( FactFusion_Admin::url( 'factfusion-facts', array( 'status' => $ff_status ) ) ); ?>"><?php echo esc_html( $ff_label ); ?></a>:
						<strong><?php echo (int) $ff_counts[ $ff_status ]; ?></strong>
					</li>
				<?php endforeach; ?>
			</ul>
			<p><a class="button button-primary" href="<?php echo esc_url( FactFusion_Admin::url( 'factfusion-facts', array( 'view' => 'edit' ) ) ); ?>"><?php esc_html_e( 'Add fact', 'factfusion-ai' ); ?></a></p>
		</div>

		<div class="factfusion-card">
			<h2><?php esc_html_e( 'Library', 'factfusion-ai' ); ?></h2>
			<ul>
				<li><a href="<?php echo esc_url( FactFusion_Admin::url( 'factfusion-categories' ) ); ?>"><?php esc_html_e( 'Categories', 'factfusion-ai' ); ?></a>: <strong><?php echo count( $ff_categories ); ?></strong></li>
				<li><a href="<?php echo esc_url( FactFusion_Admin::url( 'factfusion-sources' ) ); ?>"><?php esc_html_e( 'Sources', 'factfusion-ai' ); ?></a>: <strong><?php echo (int) $ff_sources['total']; ?></strong></li>
				<li><a href="<?php echo esc_url( FactFusion_Admin::url( 'factfusion-corrections', array( 'status' => 'new' ) ) ); ?>"><?php esc_html_e( 'New correction reports', 'factfusion-ai' ); ?></a>: <strong><?php echo (int) $ff_corrections['new']; ?></strong></li>
			</ul>
		</div>
	</div>
<?php endif; ?>

<?php if ( ! $ff_missing ) : ?>
	<?php
	// Internal links: every published fact should be reachable and linked from other facts.
	$ff_published = FactFusion_Facts::query(
		array(
			'status'   => 'published',
			'per_page' => 200,
		)
	);
	$ff_sizes     = FactFusion_Facts::published_counts();
	$ff_active    = array();
	foreach ( $ff_categories as $ff_cat ) {
		if ( $ff_cat->is_active ) {
			$ff_active[ (int) $ff_cat->id ] = $ff_cat->name;
		}
	}
	$ff_issues = array();
	foreach ( $ff_published['items'] as $ff_item ) {
		$ff_reasons = array();
		if ( ! isset( $ff_active[ (int) $ff_item->category_id ] ) ) {
			$ff_reasons[] = __( 'no active category, so no category page lists it', 'factfusion-ai' );
		} elseif ( (int) ( $ff_sizes[ (int) $ff_item->category_id ] ?? 0 ) < 2 ) {
			$ff_reasons[] = __( 'the only fact in its category, so no other fact links to it', 'factfusion-ai' );
		}
		if ( 'verified' !== $ff_item->verification_status || ! $ff_item->is_indexable ) {
			$ff_reasons[] = __( 'hidden from search engines and left out of the sitemap', 'factfusion-ai' );
		}
		if ( $ff_reasons ) {
			$ff_issues[] = array( $ff_item, $ff_reasons );
		}
	}
	$ff_thin = array();
	foreach ( $ff_active as $ff_cat_id => $ff_cat_name ) {
		$ff_size = (int) ( $ff_sizes[ $ff_cat_id ] ?? 0 );
		if ( $ff_size < FactFusion_SEO::THIN_CATEGORY ) {
			$ff_thin[] = $ff_cat_name . ' (' . $ff_size . ')';
		}
	}
	?>
	<h2><?php esc_html_e( 'Internal links and indexing', 'factfusion-ai' ); ?></h2>
	<?php if ( ! $ff_issues ) : ?>
		<p><?php esc_html_e( 'Every published fact is listed on a category page, linked from other facts and included in the sitemap.', 'factfusion-ai' ); ?></p>
	<?php else : ?>
		<p><?php esc_html_e( 'These published facts are hard for readers or search engines to reach:', 'factfusion-ai' ); ?></p>
		<ul class="factfusion-errors">
			<?php foreach ( array_slice( $ff_issues, 0, 20 ) as $ff_issue ) : ?>
				<li>
					<a href="<?php echo esc_url( FactFusion_Admin::url( 'factfusion-facts', array( 'view' => 'edit', 'id' => (int) $ff_issue[0]->id ) ) ); ?>"><?php echo esc_html( $ff_issue[0]->title ); ?></a>:
					<?php echo esc_html( implode( '; ', $ff_issue[1] ) ); ?>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
	<?php if ( $ff_thin ) : ?>
		<p>
			<?php
			printf(
				/* translators: 1: minimum number of facts, 2: list of category names with their fact counts. */
				esc_html__( 'Category pages with fewer than %1$d facts are kept out of search engines until they grow: %2$s.', 'factfusion-ai' ),
				(int) FactFusion_SEO::THIN_CATEGORY,
				esc_html( implode( ', ', $ff_thin ) )
			);
			?>
		</p>
	<?php endif; ?>
	<p>
		<?php esc_html_e( 'Sitemap:', 'factfusion-ai' ); ?>
		<a href="<?php echo esc_url( FactFusion_SEO::sitemap_url() ); ?>"><?php echo esc_html( FactFusion_SEO::sitemap_url() ); ?></a>
		<?php if ( ! get_option( 'blog_public' ) ) : ?>
			<strong><?php esc_html_e( 'Search engines are currently discouraged (Settings → Reading), so nothing will be indexed yet.', 'factfusion-ai' ); ?></strong>
		<?php endif; ?>
	</p>
<?php endif; ?>

<h2><?php esc_html_e( 'Trust pages', 'factfusion-ai' ); ?></h2>
<?php
$ff_pages         = FactFusion_Pages::status();
$ff_pages_missing = 0;
?>
<ul>
	<?php foreach ( $ff_pages as $ff_slug => $ff_page ) : ?>
		<li>
			<?php if ( $ff_page['id'] ) : ?>
				<a href="<?php echo esc_url( get_edit_post_link( $ff_page['id'] ) ); ?>"><?php echo esc_html( $ff_page['title'] ); ?></a>:
				<?php echo 'publish' === $ff_page['status'] ? esc_html__( 'published', 'factfusion-ai' ) : esc_html( $ff_page['status'] ); ?>
				<code>/<?php echo esc_html( $ff_slug ); ?>/</code>
			<?php else : ?>
				<?php ++$ff_pages_missing; ?>
				<?php echo esc_html( $ff_page['title'] ); ?>: <strong><?php esc_html_e( 'not created yet', 'factfusion-ai' ); ?></strong>
			<?php endif; ?>
		</li>
	<?php endforeach; ?>
</ul>
<?php if ( $ff_pages_missing ) : ?>
	<form method="post" action="<?php echo esc_url( FactFusion_Admin::url( 'factfusion' ) ); ?>">
		<?php FactFusion_Admin::form_fields( 'create_pages' ); ?>
		<p>
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Create the missing pages', 'factfusion-ai' ); ?></button>
			<span class="description"><?php esc_html_e( 'Creates ordinary WordPress pages with starter text. Pages that already exist are never changed.', 'factfusion-ai' ); ?></span>
		</p>
	</form>
<?php else : ?>
	<p class="description"><?php esc_html_e( 'These are ordinary WordPress pages. Edit them under Pages, in your own words.', 'factfusion-ai' ); ?></p>
<?php endif; ?>

<h2><?php esc_html_e( 'Articles and tool pages', 'factfusion-ai' ); ?></h2>
<?php
$ff_content         = FactFusion_Content::status();
$ff_content_missing = 0;
?>
<ul>
	<?php foreach ( $ff_content as $ff_slug => $ff_page ) : ?>
		<li>
			<?php if ( $ff_page['id'] ) : ?>
				<?php $ff_uncited = FactFusion_Content::missing_facts( get_post( $ff_page['id'] ) ); ?>
				<a href="<?php echo esc_url( get_edit_post_link( $ff_page['id'] ) ); ?>"><?php echo esc_html( $ff_page['title'] ); ?></a>:
				<?php echo 'publish' === $ff_page['status'] ? esc_html__( 'published', 'factfusion-ai' ) : esc_html( $ff_page['status'] ); ?>
				<code>/<?php echo esc_html( $ff_slug ); ?>/</code>
				<?php if ( $ff_uncited ) : ?>
					<strong>
						<?php
						/* translators: %d: number of facts. */
						echo esc_html( sprintf( _n( '%d fact it cites is not published yet.', '%d facts it cites are not published yet.', count( $ff_uncited ), 'factfusion-ai' ), count( $ff_uncited ) ) );
						?>
					</strong>
				<?php endif; ?>
			<?php else : ?>
				<?php ++$ff_content_missing; ?>
				<?php echo esc_html( $ff_page['title'] ); ?>: <strong><?php esc_html_e( 'not created yet', 'factfusion-ai' ); ?></strong>
			<?php endif; ?>
		</li>
	<?php endforeach; ?>
</ul>
<?php if ( $ff_content_missing ) : ?>
	<form method="post" action="<?php echo esc_url( FactFusion_Admin::url( 'factfusion' ) ); ?>">
		<?php FactFusion_Admin::form_fields( 'create_content' ); ?>
		<p>
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Create the missing articles and tool pages as drafts', 'factfusion-ai' ); ?></button>
			<span class="description"><?php esc_html_e( 'Nothing is published. Each page is created as a draft for you to read, correct and publish. Pages that already exist are never changed.', 'factfusion-ai' ); ?></span>
		</p>
	</form>
<?php else : ?>
	<p class="description"><?php esc_html_e( 'These are ordinary WordPress pages. Publish a page only after reading it, and only when every fact it cites is published.', 'factfusion-ai' ); ?></p>
<?php endif; ?>

<h2><?php esc_html_e( 'Category pages', 'factfusion-ai' ); ?></h2>
<?php
$ff_cat_rows  = FactFusion_Categories::all( true );
$ff_cat_empty = 0;
?>
<ul>
	<?php foreach ( $ff_cat_rows as $ff_cat ) : ?>
		<?php
		$ff_words    = '' === trim( (string) $ff_cat->intro ) ? 0 : count( preg_split( '/\s+/', trim( $ff_cat->intro ) ) );
		$ff_featured = count( FactFusion_Categories::featured_facts( $ff_cat ) );
		$ff_faq      = count( FactFusion_Categories::faq( $ff_cat ) );
		if ( ! $ff_words || ! $ff_featured || ! $ff_faq ) {
			++$ff_cat_empty;
		}
		?>
		<li>
			<a href="<?php echo esc_url( FactFusion_Admin::url( 'factfusion-categories', array( 'edit' => (int) $ff_cat->id ) ) ); ?>"><?php echo esc_html( $ff_cat->name ); ?></a>:
			<?php
			/* translators: 1: words in the introduction, 2: featured facts, 3: questions. */
			echo esc_html( sprintf( __( 'introduction %1$d words, %2$d facts to start with, %3$d questions', 'factfusion-ai' ), $ff_words, $ff_featured, $ff_faq ) );
			?>
		</li>
	<?php endforeach; ?>
</ul>
<?php if ( $ff_cat_empty && FactFusion_Categories::starter_content() ) : ?>
	<form method="post" action="<?php echo esc_url( FactFusion_Admin::url( 'factfusion' ) ); ?>">
		<?php FactFusion_Admin::form_fields( 'fill_categories' ); ?>
		<p>
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Fill empty category pages with starter text', 'factfusion-ai' ); ?></button>
			<span class="description"><?php esc_html_e( 'Only empty fields are filled. Text you have already written is never replaced.', 'factfusion-ai' ); ?></span>
		</p>
	</form>
<?php endif; ?>

<h2><?php esc_html_e( 'Maintenance', 'factfusion-ai' ); ?></h2>
<form method="post" action="<?php echo esc_url( FactFusion_Admin::url( 'factfusion' ) ); ?>">
	<?php FactFusion_Admin::form_fields( 'repair_tables' ); ?>
	<p>
		<button type="submit" class="button"><?php esc_html_e( 'Rebuild tables and URL rules', 'factfusion-ai' ); ?></button>
		<span class="description"><?php esc_html_e( 'Safe to run at any time. It creates anything missing and never deletes data.', 'factfusion-ai' ); ?></span>
	</p>
</form>
