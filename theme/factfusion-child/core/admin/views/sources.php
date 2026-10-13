<?php
/**
 * Sources screen.
 *
 * @package FactFusion_AI_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$ff_edit_id  = absint( FactFusion_Admin::arg( 'edit' ) );
$ff_source   = $ff_edit_id ? FactFusion_Sources::get( $ff_edit_id ) : null;
$ff_edit_id  = $ff_source ? $ff_edit_id : 0;
$ff_values   = FactFusion_Admin::form_values(
	$ff_source,
	array(
		'source_name'       => '',
		'url'               => '',
		'tier'              => 'A',
		'title'             => '',
		'author'            => '',
		'publisher'         => '',
		'date_published'    => '',
		'date_accessed'     => gmdate( 'Y-m-d' ),
		'credibility_score' => 0,
		'notes'             => '',
		'is_active'         => 1,
	)
);
$ff_search   = FactFusion_Admin::arg( 's' );
$ff_tier     = FactFusion_Admin::arg( 'tier' );
$ff_result   = FactFusion_Sources::query(
	array(
		'search'   => $ff_search,
		'tier'     => $ff_tier,
		'paged'    => absint( FactFusion_Admin::arg( 'paged' ) ),
		'per_page' => FactFusion_Admin::PER_PAGE,
	)
);
$ff_usage    = FactFusion_Sources::usage_counts();
$ff_tiers    = FactFusion_Sources::tier_labels();
$ff_form_url = FactFusion_Admin::url( 'factfusion-sources', $ff_edit_id ? array( 'edit' => $ff_edit_id ) : array() );
?>
<h1 class="wp-heading-inline"><?php esc_html_e( 'Sources', 'factfusion-ai' ); ?></h1>
<?php if ( $ff_edit_id ) : ?>
	<a class="page-title-action" href="<?php echo esc_url( FactFusion_Admin::url( 'factfusion-sources' ) ); ?>"><?php esc_html_e( 'Add source', 'factfusion-ai' ); ?></a>
<?php endif; ?>
<hr class="wp-header-end">

<form method="get" class="factfusion-filters">
	<input type="hidden" name="page" value="factfusion-sources">
	<label class="screen-reader-text" for="ff-filter-tier"><?php esc_html_e( 'Filter by tier', 'factfusion-ai' ); ?></label>
	<select id="ff-filter-tier" name="tier">
		<option value=""><?php esc_html_e( 'All tiers', 'factfusion-ai' ); ?></option>
		<?php foreach ( FactFusion_Sources::TIERS as $ff_letter ) : ?>
			<option value="<?php echo esc_attr( $ff_letter ); ?>"<?php selected( $ff_tier, $ff_letter ); ?>>
				<?php
				/* translators: %s: tier letter A to D. */
				printf( esc_html__( 'Tier %s', 'factfusion-ai' ), esc_html( $ff_letter ) );
				?>
			</option>
		<?php endforeach; ?>
	</select>
	<label class="screen-reader-text" for="ff-filter-search"><?php esc_html_e( 'Search sources', 'factfusion-ai' ); ?></label>
	<input type="search" id="ff-filter-search" name="s" value="<?php echo esc_attr( $ff_search ); ?>" placeholder="<?php esc_attr_e( 'Search name, domain or title', 'factfusion-ai' ); ?>">
	<button type="submit" class="button"><?php esc_html_e( 'Filter', 'factfusion-ai' ); ?></button>
</form>

<table class="wp-list-table widefat fixed striped">
	<thead>
		<tr>
			<th scope="col" class="column-primary"><?php esc_html_e( 'Source', 'factfusion-ai' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Domain', 'factfusion-ai' ); ?></th>
			<th scope="col" class="factfusion-col-number"><?php esc_html_e( 'Tier', 'factfusion-ai' ); ?></th>
			<th scope="col" class="factfusion-col-number"><?php esc_html_e( 'Facts', 'factfusion-ai' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Accessed', 'factfusion-ai' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Active', 'factfusion-ai' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php if ( ! $ff_result['items'] ) : ?>
			<tr><td colspan="6"><?php esc_html_e( 'No sources found.', 'factfusion-ai' ); ?></td></tr>
		<?php endif; ?>
		<?php foreach ( $ff_result['items'] as $ff_row ) : ?>
			<?php $ff_edit_url = FactFusion_Admin::url( 'factfusion-sources', array( 'edit' => (int) $ff_row->id ) ); ?>
			<tr>
				<td class="column-primary">
					<strong><a class="row-title" href="<?php echo esc_url( $ff_edit_url ); ?>"><?php echo esc_html( $ff_row->source_name ); ?></a></strong>
					<?php if ( '' !== $ff_row->title ) : ?>
						<br><?php echo esc_html( $ff_row->title ); ?>
					<?php endif; ?>
					<div class="row-actions">
						<span><a href="<?php echo esc_url( $ff_edit_url ); ?>"><?php esc_html_e( 'Edit', 'factfusion-ai' ); ?></a> | </span>
						<span><a href="<?php echo esc_url( $ff_row->url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open source', 'factfusion-ai' ); ?><span class="screen-reader-text"> <?php esc_html_e( '(opens in a new tab)', 'factfusion-ai' ); ?></span></a> | </span>
						<span class="delete"><a href="<?php echo esc_url( FactFusion_Admin::action_url( 'factfusion-sources', 'delete_source', $ff_row->id ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this source?', 'factfusion-ai' ) ); ?>');"><?php esc_html_e( 'Delete', 'factfusion-ai' ); ?></a></span>
					</div>
				</td>
				<td><?php echo esc_html( $ff_row->domain ); ?></td>
				<td class="factfusion-col-number"><span class="factfusion-badge factfusion-badge--tier"><?php echo esc_html( $ff_row->tier ); ?></span></td>
				<td class="factfusion-col-number"><?php echo (int) ( $ff_usage[ (int) $ff_row->id ] ?? 0 ); ?></td>
				<td><?php echo esc_html( $ff_row->date_accessed ? $ff_row->date_accessed : '—' ); ?></td>
				<td><?php echo $ff_row->is_active ? esc_html__( 'Yes', 'factfusion-ai' ) : esc_html__( 'No', 'factfusion-ai' ); ?></td>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>
<?php FactFusion_Admin::pagination( $ff_result['total'] ); ?>

<h2><?php echo $ff_edit_id ? esc_html__( 'Edit source', 'factfusion-ai' ) : esc_html__( 'Add source', 'factfusion-ai' ); ?></h2>
<form method="post" action="<?php echo esc_url( $ff_form_url ); ?>">
	<?php FactFusion_Admin::form_fields( 'save_source', $ff_edit_id ); ?>
	<table class="form-table" role="presentation">
		<?php
		FactFusion_Admin::row(
			'text',
			'source_name',
			__( 'Source name', 'factfusion-ai' ),
			$ff_values['source_name'],
			array(
				'required'    => true,
				'maxlength'   => 191,
				'description' => __( 'The organisation or publication, for example NASA.', 'factfusion-ai' ),
			)
		);
		FactFusion_Admin::row(
			'url',
			'url',
			__( 'URL', 'factfusion-ai' ),
			$ff_values['url'],
			array(
				'required'    => true,
				'description' => __( 'The exact page that supports the fact, not the site home page.', 'factfusion-ai' ),
			)
		);
		FactFusion_Admin::row( 'select', 'tier', __( 'Tier', 'factfusion-ai' ), $ff_values['tier'], array( 'options' => $ff_tiers ) );
		FactFusion_Admin::row( 'text', 'title', __( 'Page title', 'factfusion-ai' ), $ff_values['title'], array( 'maxlength' => 500 ) );
		FactFusion_Admin::row( 'text', 'author', __( 'Author', 'factfusion-ai' ), $ff_values['author'], array( 'description' => __( 'Only if the page names one. Leave empty otherwise.', 'factfusion-ai' ) ) );
		FactFusion_Admin::row( 'text', 'publisher', __( 'Publisher', 'factfusion-ai' ), $ff_values['publisher'] );
		FactFusion_Admin::row( 'date', 'date_published', __( 'Date published', 'factfusion-ai' ), $ff_values['date_published'], array( 'description' => __( 'Only if the page states one. Leave empty otherwise.', 'factfusion-ai' ) ) );
		FactFusion_Admin::row( 'date', 'date_accessed', __( 'Date accessed', 'factfusion-ai' ), $ff_values['date_accessed'] );
		FactFusion_Admin::row(
			'number',
			'credibility_score',
			__( 'Credibility score', 'factfusion-ai' ),
			$ff_values['credibility_score'],
			array(
				'min' => 0,
				'max' => 100,
			)
		);
		FactFusion_Admin::row( 'textarea', 'notes', __( 'Notes', 'factfusion-ai' ), $ff_values['notes'], array( 'rows' => 3 ) );
		FactFusion_Admin::row( 'checkbox', 'is_active', __( 'Active', 'factfusion-ai' ), $ff_values['is_active'], array( 'text' => __( 'This source may be attached to facts', 'factfusion-ai' ) ) );
		?>
	</table>
	<p class="submit"><button type="submit" class="button button-primary"><?php esc_html_e( 'Save source', 'factfusion-ai' ); ?></button></p>
</form>
