<?php
/**
 * Fact add/edit form.
 *
 * @package FactFusion_AI_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$ff_id   = absint( FactFusion_Admin::arg( 'id' ) );
$ff_fact = $ff_id ? FactFusion_Facts::get( $ff_id ) : null;

if ( $ff_id && ! $ff_fact ) {
	printf( '<h1>%s</h1><hr class="wp-header-end"><p>%s</p>', esc_html__( 'Fact not found', 'factfusion-ai' ), esc_html__( 'It may have been deleted.', 'factfusion-ai' ) );
	return;
}

$ff_values = FactFusion_Admin::form_values(
	$ff_fact,
	array(
		'title'               => '',
		'slug'                => '',
		'claim'               => '',
		'short_answer'        => '',
		'explanation'         => '',
		'category_id'         => 0,
		'status'              => 'draft',
		'verification_status' => 'unverified',
		'confidence_score'    => 0,
		'source_summary'      => '',
		'author_id'           => get_current_user_id(),
		'featured'            => 0,
		'is_indexable'        => 1,
		'is_ai_assisted'      => 0,
	)
);
if ( ! isset( $ff_values['source_ids'] ) ) {
	$ff_values['source_ids'] = $ff_fact ? FactFusion_Facts::source_ids( $ff_fact->id ) : array();
}
$ff_attached = array_map( 'absint', (array) $ff_values['source_ids'] );

$ff_category_options = array( 0 => __( '— Choose —', 'factfusion-ai' ) );
foreach ( FactFusion_Categories::all() as $ff_cat ) {
	$ff_category_options[ (int) $ff_cat->id ] = $ff_cat->name . ( $ff_cat->is_active ? '' : ' ' . __( '(inactive)', 'factfusion-ai' ) );
}

$ff_all_sources = FactFusion_Sources::query( array( 'per_page' => 1000 ) );
$ff_words       = FactFusion_Facts::word_count( $ff_values['explanation'] );
$ff_form_url    = FactFusion_Admin::url(
	'factfusion-facts',
	array(
		'view' => 'edit',
		'id'   => $ff_id,
	)
);
?>
<h1 class="wp-heading-inline"><?php echo $ff_fact ? esc_html__( 'Edit fact', 'factfusion-ai' ) : esc_html__( 'Add fact', 'factfusion-ai' ); ?></h1>
<a class="page-title-action" href="<?php echo esc_url( FactFusion_Admin::url( 'factfusion-facts' ) ); ?>"><?php esc_html_e( 'Back to facts', 'factfusion-ai' ); ?></a>
<hr class="wp-header-end">

<?php if ( $ff_fact ) : ?>
	<p class="factfusion-meta">
		<?php
		/* translators: %d: fact number. */
		printf( esc_html__( 'Fact #%d', 'factfusion-ai' ), (int) $ff_fact->fact_number );
		?>
		· <?php esc_html_e( 'Created', 'factfusion-ai' ); ?> <?php echo esc_html( FactFusion_Admin::date( $ff_fact->date_created ) ); ?>
		· <?php esc_html_e( 'Last verified', 'factfusion-ai' ); ?> <?php echo esc_html( FactFusion_Admin::date( $ff_fact->date_verified ) ); ?>
		· <?php esc_html_e( 'Last reviewed', 'factfusion-ai' ); ?> <?php echo esc_html( FactFusion_Admin::date( $ff_fact->last_reviewed ) ); ?>
		· <a href="<?php echo esc_url( FactFusion_Facts::url( $ff_fact ) ); ?>"><?php echo 'published' === $ff_fact->status ? esc_html__( 'View page', 'factfusion-ai' ) : esc_html__( 'Preview page', 'factfusion-ai' ); ?></a>
	</p>
<?php endif; ?>

<form method="post" action="<?php echo esc_url( $ff_form_url ); ?>">
	<?php FactFusion_Admin::form_fields( 'save_fact', $ff_id ); ?>

	<h2><?php esc_html_e( 'The fact', 'factfusion-ai' ); ?></h2>
	<table class="form-table" role="presentation">
		<?php
		FactFusion_Admin::row(
			'text',
			'title',
			__( 'Title', 'factfusion-ai' ),
			$ff_values['title'],
			array(
				'required'  => true,
				'maxlength' => 200,
			)
		);
		FactFusion_Admin::row( 'text', 'slug', __( 'URL slug', 'factfusion-ai' ), $ff_values['slug'], array( 'description' => __( 'Leave empty to build it from the title. The page lives at /fact/slug/.', 'factfusion-ai' ) ) );
		FactFusion_Admin::row(
			'textarea',
			'claim',
			__( 'Claim', 'factfusion-ai' ),
			$ff_values['claim'],
			array(
				'required'    => true,
				'rows'        => 3,
				'maxlength'   => FactFusion_Facts::MAX_CLAIM_CHARS,
				'description' => __( 'One or two sentences, 500 characters at most. State only what the source supports.', 'factfusion-ai' ),
			)
		);
		FactFusion_Admin::row(
			'textarea',
			'short_answer',
			__( 'Short answer', 'factfusion-ai' ),
			$ff_values['short_answer'],
			array(
				'rows'        => 3,
				'description' => __( 'Two or three sentences that answer the question directly.', 'factfusion-ai' ),
			)
		);
		FactFusion_Admin::row(
			'textarea',
			'explanation',
			__( 'Explanation', 'factfusion-ai' ),
			$ff_values['explanation'],
			array(
				'rows'        => 10,
				/* translators: 1: required words, 2: current words. */
				'description' => sprintf( __( 'At least %1$d words are needed to publish. Currently %2$d. Separate paragraphs with a blank line.', 'factfusion-ai' ), FactFusion_Facts::MIN_EXPLANATION_WORDS, $ff_words ),
			)
		);
		?>
		<tr>
			<th scope="row"><?php esc_html_e( 'AI draft', 'factfusion-ai' ); ?></th>
			<td>
				<?php if ( FactFusion_AI::is_configured() ) : ?>
					<button type="button" class="button" data-ff-ai-explain><?php esc_html_e( 'Draft explanation with AI', 'factfusion-ai' ); ?></button>
					<span role="status" data-ff-ai-status></span>
					<p class="description"><?php esc_html_e( 'The AI works only from your claim, short answer and source summary. It cannot read the source, so check every statement yourself before saving.', 'factfusion-ai' ); ?></p>
				<?php else : ?>
					<p class="description"><?php esc_html_e( 'AI drafting is off because no Gemini API key is set on the server. See Settings.', 'factfusion-ai' ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<?php
		FactFusion_Admin::row( 'select', 'category_id', __( 'Category', 'factfusion-ai' ), $ff_values['category_id'], array( 'options' => $ff_category_options ) );
		?>
	</table>

	<h2><?php esc_html_e( 'Sources', 'factfusion-ai' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Attached sources', 'factfusion-ai' ); ?></th>
			<td>
				<fieldset class="factfusion-checklist">
					<legend class="screen-reader-text"><?php esc_html_e( 'Attached sources', 'factfusion-ai' ); ?></legend>
					<?php if ( ! $ff_all_sources['items'] ) : ?>
						<p><?php esc_html_e( 'No sources yet.', 'factfusion-ai' ); ?></p>
					<?php endif; ?>
					<?php foreach ( $ff_all_sources['items'] as $ff_source ) : ?>
						<?php
						$ff_checked = in_array( (int) $ff_source->id, $ff_attached, true );
						if ( ! $ff_source->is_active && ! $ff_checked ) {
							continue;
						}
						?>
						<label>
							<input type="checkbox" name="ff[source_ids][]" value="<?php echo (int) $ff_source->id; ?>"<?php checked( $ff_checked ); ?>>
							<span class="factfusion-badge factfusion-badge--tier"><?php echo esc_html( $ff_source->tier ); ?></span>
							<?php echo esc_html( $ff_source->source_name ); ?>
							<?php if ( '' !== $ff_source->title ) : ?>
								— <?php echo esc_html( $ff_source->title ); ?>
							<?php endif; ?>
							(<a href="<?php echo esc_url( $ff_source->url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $ff_source->domain ); ?><span class="screen-reader-text"> <?php esc_html_e( '(opens in a new tab)', 'factfusion-ai' ); ?></span></a>)
							<?php echo $ff_source->is_active ? '' : esc_html__( '(inactive)', 'factfusion-ai' ); ?>
						</label>
					<?php endforeach; ?>
				</fieldset>
				<p class="description">
					<a href="<?php echo esc_url( FactFusion_Admin::url( 'factfusion-sources' ) ); ?>"><?php esc_html_e( 'Add a new source', 'factfusion-ai' ); ?></a>
					<?php esc_html_e( '(save this fact first so nothing is lost).', 'factfusion-ai' ); ?>
				</p>
			</td>
		</tr>
		<?php
		FactFusion_Admin::row( 'text', 'source_summary', __( 'Source summary', 'factfusion-ai' ), $ff_values['source_summary'], array( 'description' => __( 'Optional. One line on what the source says, in your own words.', 'factfusion-ai' ) ) );
		FactFusion_Admin::row(
			'number',
			'confidence_score',
			__( 'Confidence score', 'factfusion-ai' ),
			$ff_values['confidence_score'],
			array(
				'min'         => 0,
				'max'         => 100,
				'description' => __( 'Your own judgement from 0 to 100, shown on the fact page.', 'factfusion-ai' ),
			)
		);
		?>
	</table>

	<h2><?php esc_html_e( 'Review and publishing', 'factfusion-ai' ); ?></h2>
	<table class="form-table" role="presentation">
		<?php
		FactFusion_Admin::row(
			'select',
			'verification_status',
			__( 'Verification', 'factfusion-ai' ),
			$ff_values['verification_status'],
			array(
				'options'     => FactFusion_Facts::verification_labels(),
				'description' => __( 'Choose "Verified" only after you have read the source yourself and it supports the claim.', 'factfusion-ai' ),
			)
		);
		if ( $ff_fact ) {
			FactFusion_Admin::row( 'checkbox', 'mark_reviewed', __( 'Re-review', 'factfusion-ai' ), 0, array( 'text' => __( 'I re-checked this fact against its source today', 'factfusion-ai' ) ) );
		}
		FactFusion_Admin::row(
			'select',
			'status',
			__( 'Status', 'factfusion-ai' ),
			$ff_values['status'],
			array(
				'options'     => FactFusion_Facts::status_labels(),
				'description' => __( 'Publishing needs a category, a short answer, a full explanation, an active source and "Verified".', 'factfusion-ai' ),
			)
		);
		FactFusion_Admin::row( 'checkbox', 'is_ai_assisted', __( 'AI assistance', 'factfusion-ai' ), $ff_values['is_ai_assisted'], array( 'text' => __( 'The explanation was drafted with AI assistance (shows a disclosure on the page)', 'factfusion-ai' ) ) );
		FactFusion_Admin::row( 'checkbox', 'is_indexable', __( 'Search engines', 'factfusion-ai' ), $ff_values['is_indexable'], array( 'text' => __( 'Allow search engines to index this fact', 'factfusion-ai' ) ) );
		FactFusion_Admin::row( 'checkbox', 'featured', __( 'Featured', 'factfusion-ai' ), $ff_values['featured'], array( 'text' => __( 'Feature this fact', 'factfusion-ai' ) ) );
		?>
		<tr>
			<th scope="row"><label for="ff-author-id"><?php esc_html_e( 'Verified by', 'factfusion-ai' ); ?></label></th>
			<td>
				<?php
				wp_dropdown_users(
					array(
						'name'       => 'ff[author_id]',
						'id'         => 'ff-author-id',
						'selected'   => absint( $ff_values['author_id'] ),
						'capability' => array( 'edit_posts' ),
					)
				);
				?>
				<p class="description"><?php esc_html_e( 'The person who checked the fact. Shown on the fact page once it is verified.', 'factfusion-ai' ); ?></p>
			</td>
		</tr>
	</table>

	<p class="submit">
		<button type="submit" class="button button-primary"><?php esc_html_e( 'Save fact', 'factfusion-ai' ); ?></button>
		<?php if ( $ff_fact ) : ?>
			<a class="factfusion-delete" href="<?php echo esc_url( FactFusion_Admin::action_url( 'factfusion-facts', 'delete_fact', $ff_fact->id ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this fact permanently?', 'factfusion-ai' ) ); ?>');"><?php esc_html_e( 'Delete', 'factfusion-ai' ); ?></a>
		<?php endif; ?>
	</p>
</form>
