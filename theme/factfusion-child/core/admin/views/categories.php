<?php
/**
 * Categories screen.
 *
 * @package FactFusion_AI_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$ff_edit_id  = absint( FactFusion_Admin::arg( 'edit' ) );
$ff_category = $ff_edit_id ? FactFusion_Categories::get( $ff_edit_id ) : null;
$ff_edit_id  = $ff_category ? $ff_edit_id : 0;
$ff_values   = FactFusion_Admin::form_values(
	$ff_category,
	array(
		'name'             => '',
		'slug'             => '',
		'description'      => '',
		'icon'             => '',
		'sort_order'       => 0,
		'is_active'        => 1,
		'meta_title'       => '',
		'meta_description' => '',
		'intro'            => '',
		'faq'              => '',
		'featured'         => '',
	)
);
$ff_counts   = FactFusion_Categories::fact_counts();
$ff_form_url = FactFusion_Admin::url( 'factfusion-categories', $ff_edit_id ? array( 'edit' => $ff_edit_id ) : array() );
?>
<h1 class="wp-heading-inline"><?php esc_html_e( 'Categories', 'factfusion-ai' ); ?></h1>
<?php if ( $ff_edit_id ) : ?>
	<a class="page-title-action" href="<?php echo esc_url( FactFusion_Admin::url( 'factfusion-categories' ) ); ?>"><?php esc_html_e( 'Add category', 'factfusion-ai' ); ?></a>
<?php endif; ?>
<hr class="wp-header-end">

<table class="wp-list-table widefat fixed striped">
	<thead>
		<tr>
			<th scope="col" class="column-primary"><?php esc_html_e( 'Name', 'factfusion-ai' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Slug', 'factfusion-ai' ); ?></th>
			<th scope="col" class="factfusion-col-number"><?php esc_html_e( 'Facts', 'factfusion-ai' ); ?></th>
			<th scope="col" class="factfusion-col-number"><?php esc_html_e( 'Order', 'factfusion-ai' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Active', 'factfusion-ai' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php foreach ( FactFusion_Categories::all() as $ff_row ) : ?>
			<?php $ff_edit_url = FactFusion_Admin::url( 'factfusion-categories', array( 'edit' => (int) $ff_row->id ) ); ?>
			<tr>
				<td class="column-primary">
					<strong><a class="row-title" href="<?php echo esc_url( $ff_edit_url ); ?>"><?php echo esc_html( trim( $ff_row->icon . ' ' . $ff_row->name ) ); ?></a></strong>
					<div class="row-actions">
						<span><a href="<?php echo esc_url( $ff_edit_url ); ?>"><?php esc_html_e( 'Edit', 'factfusion-ai' ); ?></a> | </span>
						<span class="delete"><a href="<?php echo esc_url( FactFusion_Admin::action_url( 'factfusion-categories', 'delete_category', $ff_row->id ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this category?', 'factfusion-ai' ) ); ?>');"><?php esc_html_e( 'Delete', 'factfusion-ai' ); ?></a></span>
					</div>
				</td>
				<td><code><?php echo esc_html( $ff_row->slug ); ?></code></td>
				<td class="factfusion-col-number"><?php echo (int) ( $ff_counts[ (int) $ff_row->id ] ?? 0 ); ?></td>
				<td class="factfusion-col-number"><?php echo (int) $ff_row->sort_order; ?></td>
				<td><?php echo $ff_row->is_active ? esc_html__( 'Yes', 'factfusion-ai' ) : esc_html__( 'No', 'factfusion-ai' ); ?></td>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>

<h2><?php echo $ff_edit_id ? esc_html__( 'Edit category', 'factfusion-ai' ) : esc_html__( 'Add category', 'factfusion-ai' ); ?></h2>
<form method="post" action="<?php echo esc_url( $ff_form_url ); ?>">
	<?php FactFusion_Admin::form_fields( 'save_category', $ff_edit_id ); ?>
	<table class="form-table" role="presentation">
		<?php
		FactFusion_Admin::row(
			'text',
			'name',
			__( 'Name', 'factfusion-ai' ),
			$ff_values['name'],
			array(
				'required'  => true,
				'maxlength' => 100,
			)
		);
		FactFusion_Admin::row( 'text', 'slug', __( 'Slug', 'factfusion-ai' ), $ff_values['slug'], array( 'description' => __( 'Leave empty to build it from the name.', 'factfusion-ai' ) ) );
		FactFusion_Admin::row( 'textarea', 'description', __( 'Description', 'factfusion-ai' ), $ff_values['description'], array( 'rows' => 3 ) );
		FactFusion_Admin::row(
			'text',
			'icon',
			__( 'Icon', 'factfusion-ai' ),
			$ff_values['icon'],
			array(
				'maxlength'   => 50,
				'description' => __( 'An emoji or short icon name.', 'factfusion-ai' ),
			)
		);
		FactFusion_Admin::row( 'number', 'sort_order', __( 'Sort order', 'factfusion-ai' ), $ff_values['sort_order'], array( 'description' => __( 'Lower numbers come first.', 'factfusion-ai' ) ) );
		FactFusion_Admin::row( 'checkbox', 'is_active', __( 'Active', 'factfusion-ai' ), $ff_values['is_active'], array( 'text' => __( 'Show this category on the site', 'factfusion-ai' ) ) );
		FactFusion_Admin::row( 'text', 'meta_title', __( 'Meta title', 'factfusion-ai' ), $ff_values['meta_title'], array( 'maxlength' => 255 ) );
		FactFusion_Admin::row(
			'textarea',
			'meta_description',
			__( 'Meta description', 'factfusion-ai' ),
			$ff_values['meta_description'],
			array(
				'rows'      => 2,
				'maxlength' => 320,
			)
		);
		FactFusion_Admin::row(
			'textarea',
			'intro',
			__( 'Page introduction', 'factfusion-ai' ),
			$ff_values['intro'],
			array(
				'rows'        => 10,
				'description' => __( 'Shown at the top of the category page, 150 to 200 words. Plain text; leave an empty line between paragraphs.', 'factfusion-ai' ),
			)
		);
		FactFusion_Admin::row(
			'textarea',
			'featured',
			__( 'Facts to start with', 'factfusion-ai' ),
			$ff_values['featured'],
			array(
				'rows'        => 6,
				'description' => __( 'One fact slug per line, 5 to 10 of them, in the order to show. Only published facts from this category appear.', 'factfusion-ai' ),
			)
		);
		FactFusion_Admin::row(
			'textarea',
			'faq',
			__( 'Questions and answers', 'factfusion-ai' ),
			$ff_values['faq'],
			array(
				'rows'        => 12,
				'description' => __( '3 to 5 pairs. Start each question with "Q:" and put its answer on the next line, starting with "A:".', 'factfusion-ai' ),
			)
		);
		?>
	</table>
	<p class="submit"><button type="submit" class="button button-primary"><?php esc_html_e( 'Save category', 'factfusion-ai' ); ?></button></p>
</form>
