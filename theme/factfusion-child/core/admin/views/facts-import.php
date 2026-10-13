<?php
/**
 * CSV import screen.
 *
 * @package FactFusion_AI_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$ff_result = FactFusion_Admin::import_result();
?>
<h1 class="wp-heading-inline"><?php esc_html_e( 'Import facts from CSV', 'factfusion-ai' ); ?></h1>
<a class="page-title-action" href="<?php echo esc_url( FactFusion_Admin::url( 'factfusion-facts' ) ); ?>"><?php esc_html_e( 'Back to facts', 'factfusion-ai' ); ?></a>
<hr class="wp-header-end">

<?php if ( $ff_result ) : ?>
	<div class="notice notice-<?php echo $ff_result['errors'] ? 'warning' : 'success'; ?> inline">
		<p>
			<strong>
				<?php
				/* translators: 1: imported, 2: skipped duplicates, 3: rows with errors. */
				printf( esc_html__( 'Imported %1$d. Skipped %2$d duplicate(s). %3$d row(s) had errors.', 'factfusion-ai' ), (int) $ff_result['imported'], count( $ff_result['skipped'] ), count( $ff_result['errors'] ) );
				?>
			</strong>
		</p>
		<?php if ( $ff_result['errors'] || $ff_result['skipped'] ) : ?>
			<ul class="factfusion-errors">
				<?php foreach ( array_merge( $ff_result['errors'], $ff_result['skipped'] ) as $ff_line ) : ?>
					<li><?php echo esc_html( $ff_line ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
		<?php if ( $ff_result['imported'] ) : ?>
			<p><a href="<?php echo esc_url( FactFusion_Admin::url( 'factfusion-facts', array( 'status' => 'pending' ) ) ); ?>"><?php esc_html_e( 'Review the imported facts', 'factfusion-ai' ); ?></a></p>
		<?php endif; ?>
	</div>
<?php endif; ?>

<p>
	<?php
	/* translators: %d: maximum rows per file. */
	printf( esc_html__( 'Upload up to %d facts at a time. Every imported fact arrives as "Pending review" and "Unverified": nothing is published until you check it against its source.', 'factfusion-ai' ), (int) FactFusion_Importer::MAX_ROWS );
	?>
</p>

<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( FactFusion_Admin::url( 'factfusion-facts', array( 'view' => 'import' ) ) ); ?>">
	<?php FactFusion_Admin::form_fields( 'import_facts' ); ?>
	<p>
		<label for="ff-csv"><?php esc_html_e( 'CSV file', 'factfusion-ai' ); ?></label><br>
		<input type="file" id="ff-csv" name="ff_csv" accept=".csv,text/csv" required>
	</p>
	<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Import', 'factfusion-ai' ); ?></button></p>
</form>

<h2><?php esc_html_e( 'File format', 'factfusion-ai' ); ?></h2>
<p><?php esc_html_e( 'UTF-8, comma separated, first row is the column names. One fact per row, with one source.', 'factfusion-ai' ); ?></p>
<table class="widefat striped factfusion-format">
	<thead>
		<tr><th scope="col"><?php esc_html_e( 'Column', 'factfusion-ai' ); ?></th><th scope="col"><?php esc_html_e( 'Required', 'factfusion-ai' ); ?></th></tr>
	</thead>
	<tbody>
		<?php foreach ( FactFusion_Importer::REQUIRED as $ff_column ) : ?>
			<tr><td><code><?php echo esc_html( $ff_column ); ?></code></td><td><?php esc_html_e( 'Yes', 'factfusion-ai' ); ?></td></tr>
		<?php endforeach; ?>
		<?php foreach ( FactFusion_Importer::OPTIONAL as $ff_column ) : ?>
			<tr><td><code><?php echo esc_html( $ff_column ); ?></code></td><td><?php esc_html_e( 'No', 'factfusion-ai' ); ?></td></tr>
		<?php endforeach; ?>
	</tbody>
</table>
<p class="description"><?php esc_html_e( '"category" is a category slug such as space or human-body. "source_tier" is A, B, C or D. Dates use YYYY-MM-DD. A source with the same URL as an existing one is reused.', 'factfusion-ai' ); ?></p>
