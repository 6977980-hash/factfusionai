<?php
/**
 * Settings screen.
 *
 * @package FactFusion_AI_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$ff_stored = FactFusion_Settings::all();
$ff_values = FactFusion_Admin::form_values( null, $ff_stored );
$ff_models = array_combine( FactFusion_Settings::allowed_models(), FactFusion_Settings::allowed_models() );

$ff_published = FactFusion_Facts::query(
	array(
		'status'   => 'published',
		'per_page' => 200,
		'orderby'  => 'fact_number',
		'order'    => 'asc',
	)
);
$ff_pins      = array( 0 => __( 'Automatic daily rotation', 'factfusion-ai' ) );
foreach ( $ff_published['items'] as $ff_fact ) {
	$ff_pins[ (int) $ff_fact->id ] = '#' . (int) $ff_fact->fact_number . ' ' . $ff_fact->title;
}

$ff_secrets = array(
	'GEMINI_API_KEY'       => __( 'Gemini API key', 'factfusion-ai' ),
	'TURNSTILE_SITE_KEY'   => __( 'Turnstile site key', 'factfusion-ai' ),
	'TURNSTILE_SECRET_KEY' => __( 'Turnstile secret key', 'factfusion-ai' ),
	'GA4_MEASUREMENT_ID'   => __( 'GA4 measurement ID', 'factfusion-ai' ),
);
?>
<h1><?php esc_html_e( 'Settings', 'factfusion-ai' ); ?></h1>
<hr class="wp-header-end">

<h2><?php esc_html_e( 'Server-side keys', 'factfusion-ai' ); ?></h2>
<p><?php esc_html_e( 'Keys are never stored in the database or shown here. Add each one to wp-config.php, above the "stop editing" line.', 'factfusion-ai' ); ?></p>
<table class="widefat striped factfusion-format">
	<thead>
		<tr>
			<th scope="col"><?php esc_html_e( 'Key', 'factfusion-ai' ); ?></th>
			<th scope="col"><?php esc_html_e( 'wp-config.php line', 'factfusion-ai' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Status', 'factfusion-ai' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php foreach ( $ff_secrets as $ff_name => $ff_label ) : ?>
			<tr>
				<td><?php echo esc_html( $ff_label ); ?></td>
				<td><code>define( '<?php echo esc_html( $ff_name ); ?>', '…' );</code></td>
				<td><?php echo FactFusion_Settings::has_secret( $ff_name ) ? esc_html__( 'Set', 'factfusion-ai' ) : esc_html__( 'Not set', 'factfusion-ai' ); ?></td>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>

<form method="post" action="<?php echo esc_url( FactFusion_Admin::url( 'factfusion-settings' ) ); ?>">
	<?php FactFusion_Admin::form_fields( 'save_settings' ); ?>

	<h2><?php esc_html_e( 'AI drafting', 'factfusion-ai' ); ?></h2>
	<p><?php esc_html_e( 'AI drafts headlines, short answers and explanations when an editor asks (Fact editor or Today\'s 5). It never sources or publishes a fact: an editor checks every draft against the source first.', 'factfusion-ai' ); ?></p>
	<table class="form-table" role="presentation">
		<?php
		FactFusion_Admin::row(
			'select',
			'gemini_model',
			__( 'Primary model', 'factfusion-ai' ),
			$ff_values['gemini_model'],
			array(
				'options'     => $ff_models,
				'description' => FactFusion_Settings::has_secret( 'GEMINI_MODEL' ) ? __( 'Currently set by GEMINI_MODEL in wp-config.php, which wins over this choice.', 'factfusion-ai' ) : '',
			)
		);
		FactFusion_Admin::row(
			'select',
			'gemini_fallback',
			__( 'Fallback model', 'factfusion-ai' ),
			$ff_values['gemini_fallback'],
			array(
				'options'     => $ff_models,
				'description' => __( 'Used when the primary model fails. If both fail, the editor sees an error and nothing is drafted.', 'factfusion-ai' ),
			)
		);
		?>
	</table>

	<h2><?php esc_html_e( 'Rate limits', 'factfusion-ai' ); ?></h2>
	<table class="form-table" role="presentation">
		<?php
		FactFusion_Admin::row(
			'number',
			'rate_fact_hour',
			__( 'Facts per visitor per hour', 'factfusion-ai' ),
			$ff_values['rate_fact_hour'],
			array(
				'min'         => FactFusion_Settings::MIN_FACT_FETCH_PER_HOUR,
				/* translators: %d: minimum requests per hour. */
				'description' => sprintf( __( 'Cannot go below %d. A reader may browse dozens of facts in one visit.', 'factfusion-ai' ), FactFusion_Settings::MIN_FACT_FETCH_PER_HOUR ),
			)
		);
		FactFusion_Admin::row( 'number', 'rate_ai_day', __( 'AI requests per visitor per day', 'factfusion-ai' ), $ff_values['rate_ai_day'], array( 'min' => 1 ) );
		FactFusion_Admin::row( 'number', 'rate_ai_burst', __( 'AI requests per visitor per minute', 'factfusion-ai' ), $ff_values['rate_ai_burst'], array( 'min' => 1 ) );
		FactFusion_Admin::row( 'number', 'rate_global_hour', __( 'Site-wide requests per hour', 'factfusion-ai' ), $ff_values['rate_global_hour'], array( 'min' => FactFusion_Settings::MIN_FACT_FETCH_PER_HOUR ) );
		FactFusion_Admin::row(
			'select',
			'ip_header',
			__( 'Visitor address comes from', 'factfusion-ai' ),
			$ff_values['ip_header'],
			array(
				'options'     => array_merge( array( '' => __( 'The server connection (default)', 'factfusion-ai' ) ), FactFusion_Rate_Limiter::PROXY_HEADERS ),
				/* translators: %s: IP address. */
				'description' => sprintf( __( 'The site currently sees you as %s. If that is not your own public IP address, a CDN or proxy sits in front of the site and every visitor would share one limit: choose the header your host uses. Addresses are stored only as hashes.', 'factfusion-ai' ), FactFusion_Rate_Limiter::client_ip() ),
			)
		);
		?>
	</table>

	<h2><?php esc_html_e( 'Analytics', 'factfusion-ai' ); ?></h2>
	<table class="form-table" role="presentation">
		<?php
		$ff_ga4_live = FactFusion_Analytics::id();
		FactFusion_Admin::row(
			'text',
			'ga4_id',
			__( 'GA4 measurement ID', 'factfusion-ai' ),
			$ff_values['ga4_id'],
			array(
				'maxlength'   => 22,
				'description' => ( FactFusion_Settings::has_secret( 'GA4_MEASUREMENT_ID' )
					? __( 'Currently set by GA4_MEASUREMENT_ID in wp-config.php, which wins over this box.', 'factfusion-ai' )
					: __( 'Looks like G-XXXXXXXXXX. Leave empty to keep analytics off.', 'factfusion-ai' ) )
					. ' ' . ( '' !== $ff_ga4_live
						? __( 'Analytics is ON: visitors see a cookie notice, and Google Analytics loads only for those who accept. You are not tracked while signed in.', 'factfusion-ai' )
						: __( 'Analytics is OFF: no cookie notice is shown and nothing is sent to Google.', 'factfusion-ai' ) ),
			)
		);
		?>
	</table>

	<h2><?php esc_html_e( 'Embed widget', 'factfusion-ai' ); ?></h2>
	<p><label for="ff-embed-snippet"><?php esc_html_e( 'Anyone may paste this code into their site to show a random fact with a link back to FactFusion AI. Each fact page offers the same for that one fact.', 'factfusion-ai' ); ?></label></p>
	<textarea id="ff-embed-snippet" class="large-text code" rows="4" readonly onfocus="this.select()"><?php echo esc_textarea( FactFusion_Frontend::embed_snippet() ); ?></textarea>

	<h2><?php esc_html_e( 'Fact of the Day', 'factfusion-ai' ); ?></h2>
	<table class="form-table" role="presentation">
		<?php
		FactFusion_Admin::row(
			'select',
			'fotd_pin',
			__( 'Pinned fact', 'factfusion-ai' ),
			$ff_values['fotd_pin'],
			array(
				'options'     => $ff_pins,
				'description' => __( 'Only published facts can be pinned. Leave on automatic to rotate daily.', 'factfusion-ai' ),
			)
		);
		?>
	</table>

	<p class="submit"><button type="submit" class="button button-primary"><?php esc_html_e( 'Save settings', 'factfusion-ai' ); ?></button></p>
</form>
