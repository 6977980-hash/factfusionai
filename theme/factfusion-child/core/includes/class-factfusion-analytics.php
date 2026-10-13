<?php
/**
 * Google Analytics 4, behind consent.
 *
 * @package FactFusion_AI_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads Google Analytics only after a visitor accepts it in the cookie
 * notice. With no measurement ID configured, nothing is printed at all:
 * no notice, no script, no cookies.
 */
final class FactFusion_Analytics {

	/**
	 * Registers hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_shortcodes' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'wp_footer', array( __CLASS__, 'notice' ), 20 );
	}

	/**
	 * Registers the "Cookie settings" shortcode used in the theme footer.
	 */
	public static function register_shortcodes() {
		add_shortcode( 'factfusion_cookie_settings', array( __CLASS__, 'shortcode_settings_link' ) );
	}

	/**
	 * Whether a value looks like a GA4 measurement ID.
	 *
	 * @param string $id Candidate ID.
	 * @return bool
	 */
	public static function is_valid_id( $id ) {
		return (bool) preg_match( '/^G-[A-Z0-9]{4,20}$/', $id );
	}

	/**
	 * The GA4 measurement ID: from wp-config.php if defined, else from Settings.
	 *
	 * @return string Empty when analytics is off.
	 */
	public static function id() {
		$id = FactFusion_Settings::env( 'GA4_MEASUREMENT_ID' );
		if ( '' === $id ) {
			$id = (string) FactFusion_Settings::get( 'ga4_id' );
		}
		return self::is_valid_id( $id ) ? $id : '';
	}

	/**
	 * Whether analytics applies to this request. Editors are never tracked.
	 *
	 * @return bool
	 */
	private static function active() {
		return '' !== self::id() && ! current_user_can( factfusion_capability() );
	}

	/**
	 * Loads the small consent script on every page when analytics is on.
	 */
	public static function enqueue() {
		if ( ! self::active() ) {
			return;
		}
		wp_enqueue_script(
			'factfusion-consent',
			FACTFUSION_URL . 'assets/consent.js',
			array(),
			FACTFUSION_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
		wp_add_inline_script( 'factfusion-consent', 'window.factfusionConsent = ' . wp_json_encode( array( 'id' => self::id() ) ) . ';', 'before' );
	}

	/**
	 * Prints the cookie notice. It stays hidden until the script decides to show it.
	 */
	public static function notice() {
		if ( ! self::active() ) {
			return;
		}
		$privacy = FactFusion_Pages::url( 'privacy-policy' );
		?>
		<div class="ff-consent" data-ff-consent role="region" aria-label="<?php esc_attr_e( 'Cookie notice', 'factfusion-ai' ); ?>" hidden>
			<p>
				<?php esc_html_e( 'We would like to use Google Analytics to see which pages are useful. It sets cookies. Nothing is loaded unless you accept.', 'factfusion-ai' ); ?>
				<?php if ( $privacy ) : ?>
					<a href="<?php echo esc_url( $privacy ); ?>"><?php esc_html_e( 'Privacy policy', 'factfusion-ai' ); ?></a>
				<?php endif; ?>
			</p>
			<div class="ff-consent__actions">
				<button type="button" class="ff-button" data-ff-consent-accept><?php esc_html_e( 'Accept analytics', 'factfusion-ai' ); ?></button>
				<button type="button" class="ff-button" data-ff-consent-decline><?php esc_html_e( 'Decline', 'factfusion-ai' ); ?></button>
			</div>
		</div>
		<?php
	}

	/**
	 * A "Cookie settings" button for the footer, so a choice can be changed later.
	 *
	 * @return string Empty when analytics is off.
	 */
	public static function shortcode_settings_link() {
		if ( ! self::active() ) {
			return '';
		}
		return sprintf( '<button type="button" class="ff-link-button ff-needs-js" data-ff-consent-open>%s</button>', esc_html__( 'Cookie settings', 'factfusion-ai' ) );
	}
}
