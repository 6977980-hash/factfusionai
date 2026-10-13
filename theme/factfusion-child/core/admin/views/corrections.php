<?php
/**
 * Corrections screen.
 *
 * @package FactFusion_AI_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$ff_edit_id    = absint( FactFusion_Admin::arg( 'edit' ) );
$ff_correction = $ff_edit_id ? FactFusion_Corrections::get( $ff_edit_id ) : null;
$ff_edit_id    = $ff_correction ? $ff_edit_id : 0;
$ff_values     = FactFusion_Admin::form_values(
	$ff_correction,
	array(
		'fact_id'              => absint( FactFusion_Admin::arg( 'fact_id' ) ),
		'reporter_email'       => '',
		'what_is_wrong'        => '',
		'suggested_correction' => '',
		'supporting_source'    => '',
		'status'               => 'new',
		'admin_notes'          => '',
	)
);
$ff_status     = FactFusion_Admin::arg( 'status' );
$ff_result     = FactFusion_Corrections::query(
	array(
		'status'   => $ff_status,
		'paged'    => absint( FactFusion_Admin::arg( 'paged' ) ),
		'per_page' => FactFusion_Admin::PER_PAGE,
	)
);
$ff_counts     = FactFusion_Corrections::status_counts();
$ff_statuses   = FactFusion_Corrections::status_labels();
$ff_facts      = FactFusion_Facts::query(
	array(
		'per_page' => 200,
		'orderby'  => 'fact_number',
		'order'    => 'desc',
	)
);
$ff_fact_names = array( 0 => __( '— Choose —', 'factfusion-ai' ) );
foreach ( $ff_facts['items'] as $ff_fact ) {
	$ff_fact_names[ (int) $ff_fact->id ] = '#' . (int) $ff_fact->fact_number . ' ' . $ff_fact->title;
}
$ff_form_url = FactFusion_Admin::url( 'factfusion-corrections', $ff_edit_id ? array( 'edit' => $ff_edit_id ) : array() );
?>
<h1 class="wp-heading-inline"><?php esc_html_e( 'Corrections', 'factfusion-ai' ); ?></h1>
<?php if ( $ff_edit_id ) : ?>
	<a class="page-title-action" href="<?php echo esc_url( FactFusion_Admin::url( 'factfusion-corrections' ) ); ?>"><?php esc_html_e( 'Log a report', 'factfusion-ai' ); ?></a>
<?php endif; ?>
<hr class="wp-header-end">

<ul class="subsubsub">
	<li>
		<a href="<?php echo esc_url( FactFusion_Admin::url( 'factfusion-corrections' ) ); ?>"<?php echo '' === $ff_status ? ' class="current" aria-current="page"' : ''; ?>><?php esc_html_e( 'All', 'factfusion-ai' ); ?> <span class="count">(<?php echo (int) $ff_counts['all']; ?>)</span></a>
	</li>
	<?php foreach ( $ff_statuses as $ff_key => $ff_label ) : ?>
		<li>
			| <a href="<?php echo esc_url( FactFusion_Admin::url( 'factfusion-corrections', array( 'status' => $ff_key ) ) ); ?>"<?php echo $ff_status === $ff_key ? ' class="current" aria-current="page"' : ''; ?>><?php echo esc_html( $ff_label ); ?> <span class="count">(<?php echo (int) $ff_counts[ $ff_key ]; ?>)</span></a>
		</li>
	<?php endforeach; ?>
</ul>

<table class="wp-list-table widefat fixed striped">
	<thead>
		<tr>
			<th scope="col"><?php esc_html_e( 'Received', 'factfusion-ai' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Fact', 'factfusion-ai' ); ?></th>
			<th scope="col" class="column-primary"><?php esc_html_e( 'What is wrong', 'factfusion-ai' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Reporter', 'factfusion-ai' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Status', 'factfusion-ai' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php if ( ! $ff_result['items'] ) : ?>
			<tr><td colspan="5"><?php esc_html_e( 'No correction reports.', 'factfusion-ai' ); ?></td></tr>
		<?php endif; ?>
		<?php foreach ( $ff_result['items'] as $ff_row ) : ?>
			<?php
			$ff_edit_url = FactFusion_Admin::url( 'factfusion-corrections', array( 'edit' => (int) $ff_row->id ) );
			$ff_about    = FactFusion_Facts::get( $ff_row->fact_id );
			?>
			<tr>
				<td><?php echo esc_html( FactFusion_Admin::date( $ff_row->created_at ) ); ?></td>
				<td>
					<?php if ( $ff_about ) : ?>
						<a href="<?php echo esc_url( FactFusion_Admin::url( 'factfusion-facts', array( 'view' => 'edit', 'id' => (int) $ff_about->id ) ) ); ?>"><?php echo esc_html( '#' . (int) $ff_about->fact_number . ' ' . $ff_about->title ); ?></a>
					<?php else : ?>
						—
					<?php endif; ?>
				</td>
				<td class="column-primary">
					<a class="row-title" href="<?php echo esc_url( $ff_edit_url ); ?>"><?php echo esc_html( wp_trim_words( $ff_row->what_is_wrong, 20 ) ); ?></a>
					<div class="row-actions">
						<span><a href="<?php echo esc_url( $ff_edit_url ); ?>"><?php esc_html_e( 'Review', 'factfusion-ai' ); ?></a> | </span>
						<span class="delete"><a href="<?php echo esc_url( FactFusion_Admin::action_url( 'factfusion-corrections', 'delete_correction', $ff_row->id ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this report permanently?', 'factfusion-ai' ) ); ?>');"><?php esc_html_e( 'Delete', 'factfusion-ai' ); ?></a></span>
					</div>
				</td>
				<td><?php echo esc_html( '' !== $ff_row->reporter_email ? $ff_row->reporter_email : '—' ); ?></td>
				<td><span class="factfusion-badge factfusion-badge--<?php echo esc_attr( $ff_row->status ); ?>"><?php echo esc_html( $ff_statuses[ $ff_row->status ] ?? $ff_row->status ); ?></span></td>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>
<?php FactFusion_Admin::pagination( $ff_result['total'] ); ?>

<h2><?php echo $ff_edit_id ? esc_html__( 'Review report', 'factfusion-ai' ) : esc_html__( 'Log a report', 'factfusion-ai' ); ?></h2>
<?php if ( ! $ff_edit_id ) : ?>
	<p class="description"><?php esc_html_e( 'Use this for a correction that reached you by email or another route. The public report form arrives in Milestone 3.', 'factfusion-ai' ); ?></p>
<?php endif; ?>
<form method="post" action="<?php echo esc_url( $ff_form_url ); ?>">
	<?php FactFusion_Admin::form_fields( 'save_correction', $ff_edit_id ); ?>
	<table class="form-table" role="presentation">
		<?php
		FactFusion_Admin::row( 'select', 'fact_id', __( 'Fact', 'factfusion-ai' ), $ff_values['fact_id'], array( 'options' => $ff_fact_names ) );
		FactFusion_Admin::row( 'email', 'reporter_email', __( 'Reporter email', 'factfusion-ai' ), $ff_values['reporter_email'], array( 'description' => __( 'Optional.', 'factfusion-ai' ) ) );
		FactFusion_Admin::row(
			'textarea',
			'what_is_wrong',
			__( 'What is wrong', 'factfusion-ai' ),
			$ff_values['what_is_wrong'],
			array(
				'required' => true,
				'rows'     => 4,
			)
		);
		FactFusion_Admin::row( 'textarea', 'suggested_correction', __( 'Suggested correction', 'factfusion-ai' ), $ff_values['suggested_correction'], array( 'rows' => 3 ) );
		FactFusion_Admin::row( 'textarea', 'supporting_source', __( 'Supporting source', 'factfusion-ai' ), $ff_values['supporting_source'], array( 'rows' => 2 ) );
		FactFusion_Admin::row( 'select', 'status', __( 'Status', 'factfusion-ai' ), $ff_values['status'], array( 'options' => $ff_statuses ) );
		FactFusion_Admin::row( 'textarea', 'admin_notes', __( 'Editor notes', 'factfusion-ai' ), $ff_values['admin_notes'], array( 'rows' => 3 ) );
		?>
	</table>
	<p class="submit"><button type="submit" class="button button-primary"><?php esc_html_e( 'Save report', 'factfusion-ai' ); ?></button></p>
</form>
