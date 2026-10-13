<?php
/**
 * Facts screen: list, edit form or CSV import.
 *
 * @package FactFusion_AI_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$ff_view = FactFusion_Admin::arg( 'view' );

if ( 'edit' === $ff_view ) {
	require __DIR__ . '/fact-edit.php';
	return;
}
if ( 'import' === $ff_view ) {
	require __DIR__ . '/facts-import.php';
	return;
}

$ff_status   = FactFusion_Admin::arg( 'status' );
$ff_search   = FactFusion_Admin::arg( 's' );
$ff_category = absint( FactFusion_Admin::arg( 'category_id' ) );
$ff_result   = FactFusion_Facts::query(
	array(
		'status'      => $ff_status,
		'search'      => $ff_search,
		'category_id' => $ff_category,
		'paged'       => absint( FactFusion_Admin::arg( 'paged' ) ),
		'per_page'    => FactFusion_Admin::PER_PAGE,
	)
);
$ff_counts   = FactFusion_Facts::status_counts();
$ff_statuses = FactFusion_Facts::status_labels();
$ff_verify   = FactFusion_Facts::verification_labels();
$ff_sources  = FactFusion_Facts::source_counts();
$ff_cats     = array();
foreach ( FactFusion_Categories::all() as $ff_cat ) {
	$ff_cats[ (int) $ff_cat->id ] = $ff_cat->name;
}
?>
<h1 class="wp-heading-inline"><?php esc_html_e( 'Facts', 'factfusion-ai' ); ?></h1>
<a class="page-title-action" href="<?php echo esc_url( FactFusion_Admin::url( 'factfusion-facts', array( 'view' => 'edit' ) ) ); ?>"><?php esc_html_e( 'Add fact', 'factfusion-ai' ); ?></a>
<a class="page-title-action" href="<?php echo esc_url( FactFusion_Admin::url( 'factfusion-facts', array( 'view' => 'import' ) ) ); ?>"><?php esc_html_e( 'Import CSV', 'factfusion-ai' ); ?></a>
<hr class="wp-header-end">

<ul class="subsubsub">
	<li>
		<a href="<?php echo esc_url( FactFusion_Admin::url( 'factfusion-facts' ) ); ?>"<?php echo '' === $ff_status ? ' class="current" aria-current="page"' : ''; ?>><?php esc_html_e( 'All', 'factfusion-ai' ); ?> <span class="count">(<?php echo (int) $ff_counts['all']; ?>)</span></a>
	</li>
	<?php foreach ( $ff_statuses as $ff_key => $ff_label ) : ?>
		<li>
			| <a href="<?php echo esc_url( FactFusion_Admin::url( 'factfusion-facts', array( 'status' => $ff_key ) ) ); ?>"<?php echo $ff_status === $ff_key ? ' class="current" aria-current="page"' : ''; ?>><?php echo esc_html( $ff_label ); ?> <span class="count">(<?php echo (int) $ff_counts[ $ff_key ]; ?>)</span></a>
		</li>
	<?php endforeach; ?>
</ul>

<form method="get" class="factfusion-filters">
	<input type="hidden" name="page" value="factfusion-facts">
	<?php if ( '' !== $ff_status ) : ?>
		<input type="hidden" name="status" value="<?php echo esc_attr( $ff_status ); ?>">
	<?php endif; ?>
	<label class="screen-reader-text" for="ff-filter-category"><?php esc_html_e( 'Filter by category', 'factfusion-ai' ); ?></label>
	<select id="ff-filter-category" name="category_id">
		<option value="0"><?php esc_html_e( 'All categories', 'factfusion-ai' ); ?></option>
		<?php foreach ( $ff_cats as $ff_id => $ff_name ) : ?>
			<option value="<?php echo (int) $ff_id; ?>"<?php selected( $ff_category, $ff_id ); ?>><?php echo esc_html( $ff_name ); ?></option>
		<?php endforeach; ?>
	</select>
	<label class="screen-reader-text" for="ff-filter-search"><?php esc_html_e( 'Search facts', 'factfusion-ai' ); ?></label>
	<input type="search" id="ff-filter-search" name="s" value="<?php echo esc_attr( $ff_search ); ?>" placeholder="<?php esc_attr_e( 'Search title or claim', 'factfusion-ai' ); ?>">
	<button type="submit" class="button"><?php esc_html_e( 'Filter', 'factfusion-ai' ); ?></button>
</form>

<table class="wp-list-table widefat fixed striped">
	<thead>
		<tr>
			<th scope="col" class="factfusion-col-number">#</th>
			<th scope="col" class="column-primary"><?php esc_html_e( 'Title', 'factfusion-ai' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Category', 'factfusion-ai' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Status', 'factfusion-ai' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Verification', 'factfusion-ai' ); ?></th>
			<th scope="col" class="factfusion-col-number"><?php esc_html_e( 'Sources', 'factfusion-ai' ); ?></th>
			<th scope="col" class="factfusion-col-number"><?php esc_html_e( 'Confidence', 'factfusion-ai' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Updated', 'factfusion-ai' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php if ( ! $ff_result['items'] ) : ?>
			<tr><td colspan="8"><?php esc_html_e( 'No facts found.', 'factfusion-ai' ); ?></td></tr>
		<?php endif; ?>
		<?php foreach ( $ff_result['items'] as $ff_fact ) : ?>
			<?php
			$ff_edit = FactFusion_Admin::url(
				'factfusion-facts',
				array(
					'view' => 'edit',
					'id'   => (int) $ff_fact->id,
				)
			);
			?>
			<tr>
				<td class="factfusion-col-number"><?php echo (int) $ff_fact->fact_number; ?></td>
				<td class="column-primary">
					<strong><a class="row-title" href="<?php echo esc_url( $ff_edit ); ?>"><?php echo esc_html( $ff_fact->title ); ?></a></strong>
					<div class="row-actions">
						<span><a href="<?php echo esc_url( $ff_edit ); ?>"><?php esc_html_e( 'Edit', 'factfusion-ai' ); ?></a> | </span>
						<span><a href="<?php echo esc_url( FactFusion_Facts::url( $ff_fact ) ); ?>"><?php echo 'published' === $ff_fact->status ? esc_html__( 'View', 'factfusion-ai' ) : esc_html__( 'Preview', 'factfusion-ai' ); ?></a> | </span>
						<span class="delete"><a href="<?php echo esc_url( FactFusion_Admin::action_url( 'factfusion-facts', 'delete_fact', $ff_fact->id ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this fact permanently?', 'factfusion-ai' ) ); ?>');"><?php esc_html_e( 'Delete', 'factfusion-ai' ); ?></a></span>
					</div>
				</td>
				<td><?php echo esc_html( $ff_cats[ (int) $ff_fact->category_id ] ?? '—' ); ?></td>
				<td><span class="factfusion-badge factfusion-badge--<?php echo esc_attr( $ff_fact->status ); ?>"><?php echo esc_html( $ff_statuses[ $ff_fact->status ] ?? $ff_fact->status ); ?></span></td>
				<td><span class="factfusion-badge factfusion-badge--<?php echo esc_attr( $ff_fact->verification_status ); ?>"><?php echo esc_html( $ff_verify[ $ff_fact->verification_status ] ?? $ff_fact->verification_status ); ?></span></td>
				<td class="factfusion-col-number"><?php echo (int) ( $ff_sources[ (int) $ff_fact->id ] ?? 0 ); ?></td>
				<td class="factfusion-col-number"><?php echo (int) $ff_fact->confidence_score; ?></td>
				<td><?php echo esc_html( FactFusion_Admin::date( $ff_fact->date_updated ) ); ?></td>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>
<?php
FactFusion_Admin::pagination( $ff_result['total'] );
