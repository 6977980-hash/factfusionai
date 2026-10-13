<?php
/**
 * Trust pages and the contact form.
 *
 * @package FactFusion_AI_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates the trust pages as ordinary WordPress pages, so the
 * editor can rewrite them in the block editor, and handles the contact form.
 */
final class FactFusion_Pages {

	const CONTACTS_PER_HOUR = 5;

	/**
	 * The trust pages: slug => title.
	 *
	 * @return array<string,string>
	 */
	public static function definitions() {
		return array(
			'about'               => __( 'About FactFusion AI', 'factfusion-ai' ),
			'how-we-verify-facts' => __( 'How We Verify Facts', 'factfusion-ai' ),
			'contact'             => __( 'Contact', 'factfusion-ai' ),
			'privacy-policy'      => __( 'Privacy Policy', 'factfusion-ai' ),
			'terms'               => __( 'Terms of Use', 'factfusion-ai' ),
		);
	}

	/**
	 * Registers hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_shortcodes' ) );
		add_action( 'admin_post_nopriv_factfusion_contact', array( __CLASS__, 'handle_contact' ) );
		add_action( 'admin_post_factfusion_contact', array( __CLASS__, 'handle_contact' ) );
	}

	/**
	 * Registers the contact form shortcode.
	 */
	public static function register_shortcodes() {
		add_shortcode( 'factfusion_contact_form', array( __CLASS__, 'shortcode_contact_form' ) );
	}

	/**
	 * URL of a trust page if it exists and is published.
	 *
	 * @param string $slug Page slug.
	 * @return string Empty when the page is not live.
	 */
	public static function url( $slug ) {
		$page = get_page_by_path( $slug );
		return $page && 'publish' === $page->post_status ? (string) get_permalink( $page ) : '';
	}

	/**
	 * Which of the trust pages exist, and in what state.
	 *
	 * @return array<string,array{title:string,status:string,id:int}>
	 */
	public static function status() {
		$out = array();
		foreach ( self::definitions() as $slug => $title ) {
			$page         = get_page_by_path( $slug, OBJECT, 'page' );
			$out[ $slug ] = array(
				'title'  => $title,
				'status' => $page ? $page->post_status : '',
				'id'     => $page ? (int) $page->ID : 0,
			);
		}
		return $out;
	}

	/**
	 * Creates any of the trust pages that does not exist. Existing pages,
	 * in any state, are never touched.
	 *
	 * @return string[] Slugs of the pages created.
	 */
	public static function create_missing() {
		$created = array();
		$editor  = null;
		foreach ( FactFusion_Profiles::all() as $profile ) {
			$editor = $profile;
			break;
		}
		$profile_url = $editor ? FactFusion_Profiles::url( $editor ) : '';
		$tokens      = array(
			'{{home_url}}'         => esc_url( home_url( '/' ) ),
			'{{facts_url}}'        => esc_url( FactFusion_Router::browse_url() ),
			'{{fotd_url}}'         => esc_url( home_url( user_trailingslashit( 'fact-of-the-day' ) ) ),
			'{{verify_url}}'       => esc_url( home_url( user_trailingslashit( 'how-we-verify-facts' ) ) ),
			'{{contact_url}}'      => esc_url( home_url( user_trailingslashit( 'contact' ) ) ),
			'{{privacy_url}}'      => esc_url( home_url( user_trailingslashit( 'privacy-policy' ) ) ),
			'{{date}}'             => esc_html( wp_date( get_option( 'date_format' ) ) ),
			'{{profile_sentence}}' => $profile_url ? ' ' . sprintf(
				/* translators: %s: URL of the editor's profile page. */
				__( 'You can read more on <a href="%s">my profile page</a>.', 'factfusion-ai' ),
				esc_url( $profile_url )
			) : '',
		);

		foreach ( self::status() as $slug => $state ) {
			$file = FACTFUSION_DIR . 'data/pages/' . $slug . '.php';
			if ( ! is_readable( $file ) ) {
				continue;
			}
			// WordPress ships an unpublished "Privacy Policy" draft full of
			// "Suggested text". That untouched starter is the one page replaced.
			$replace_id = 0;
			if ( $state['id'] && 'privacy-policy' === $slug && 'draft' === $state['status'] ) {
				$existing = get_post( $state['id'] );
				if ( $existing && false !== strpos( $existing->post_content, 'Suggested text:' ) ) {
					$replace_id = (int) $existing->ID;
				}
			}
			if ( $state['id'] && ! $replace_id ) {
				continue;
			}
			// The starter text lives in guarded PHP files so it cannot be opened directly in a browser.
			ob_start();
			include $file;
			$content = strtr( ltrim( (string) ob_get_clean() ), $tokens );
			if ( 'how-we-verify-facts' === $slug && $profile_url ) {
				// The policy page speaks as "we", so the profile link is worded to match.
				$content = str_replace( $tokens['{{profile_sentence}}'], ' ' . sprintf( '<a href="%s">%s</a>', esc_url( $profile_url ), esc_html__( 'Read his profile.', 'factfusion-ai' ) ), $content );
			}
			$id = wp_insert_post(
				wp_slash(
					array(
						'ID'             => $replace_id,
						'post_type'      => 'page',
						'post_status'    => 'publish',
						'post_title'     => $state['title'],
						'post_name'      => $slug,
						'post_content'   => $content,
						'comment_status' => 'closed',
						'ping_status'    => 'closed',
					)
				),
				true
			);
			if ( is_wp_error( $id ) ) {
				continue;
			}
			$created[] = $slug;
			if ( 'privacy-policy' === $slug && ! get_option( 'wp_page_for_privacy_policy' ) ) {
				update_option( 'wp_page_for_privacy_policy', (int) $id );
			}
		}
		if ( $created ) {
			do_action( 'factfusion_facts_changed' );
		}
		return $created;
	}

	/**
	 * Contact form. It posts to WordPress and the message is emailed to the
	 * site administrator; no address is shown on the page.
	 *
	 * @return string
	 */
	public static function shortcode_contact_form() {
		$messages = array(
			'sent'    => __( 'Thank you. Your message has been sent to the editor.', 'factfusion-ai' ),
			'fields'  => __( 'Please enter your name, a valid email address and a message of at least 10 characters.', 'factfusion-ai' ),
			'limit'   => __( 'Too many messages from your connection in the last hour. Please try again later.', 'factfusion-ai' ),
			'human'   => __( 'Please complete the "I am human" check and try again.', 'factfusion-ai' ),
			'mail'    => __( 'The message could not be sent. Please try again later.', 'factfusion-ai' ),
		);
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display flag only.
		$state  = isset( $_GET['ff_contact'] ) ? sanitize_key( wp_unslash( $_GET['ff_contact'] ) ) : '';
		$notice = isset( $messages[ $state ] ) ? sprintf( '<p class="ff-status ff-status--%s" role="status">%s</p>', 'sent' === $state ? 'ok' : 'error', esc_html( $messages[ $state ] ) ) : '';

		$html  = '<div class="ff-contact" id="ff-contact">' . $notice;
		$html .= sprintf( '<form class="ff-form" method="post" action="%s">', esc_url( admin_url( 'admin-post.php' ) ) );
		$html .= '<input type="hidden" name="action" value="factfusion_contact">';
		$html .= sprintf( '<p><label for="ff-contact-name">%s</label><input type="text" id="ff-contact-name" name="ff_name" required maxlength="100" autocomplete="name"></p>', esc_html__( 'Your name', 'factfusion-ai' ) );
		$html .= sprintf( '<p><label for="ff-contact-email">%s</label><input type="email" id="ff-contact-email" name="ff_email" required maxlength="191" autocomplete="email"></p>', esc_html__( 'Your email', 'factfusion-ai' ) );
		$html .= sprintf( '<p><label for="ff-contact-message">%s</label><textarea id="ff-contact-message" name="ff_message" rows="6" required minlength="10" maxlength="3000"></textarea></p>', esc_html__( 'Your message', 'factfusion-ai' ) );
		// Honeypot: people never see or fill this field.
		$html .= '<p class="ff-form__trap" aria-hidden="true"><label for="ff-contact-website">Website</label><input type="text" id="ff-contact-website" name="website" tabindex="-1" autocomplete="off"></p>';
		$site_key = FactFusion_Settings::env( 'TURNSTILE_SITE_KEY' );
		if ( '' !== $site_key ) {
			wp_enqueue_script( 'cf-turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js', array(), null, array( 'strategy' => 'defer' ) ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
			$html .= sprintf( '<div class="cf-turnstile" data-sitekey="%s"></div>', esc_attr( $site_key ) );
		}
		$html .= sprintf( '<p><button type="submit" class="wp-element-button">%s</button></p>', esc_html__( 'Send message', 'factfusion-ai' ) );
		$html .= '</form></div>';
		return $html;
	}

	/**
	 * Receives the contact form, emails the administrator and redirects back.
	 */
	public static function handle_contact() {
		$back = self::url( 'contact' );
		$back = $back ? $back : home_url( '/' );
		$done = static function ( $state ) use ( $back ) {
			nocache_headers();
			wp_safe_redirect( add_query_arg( 'ff_contact', $state, $back ) . '#ff-contact', 303 );
			exit;
		};

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- a public contact form; protected by honeypot, rate limit and Turnstile instead of a nonce, which page caching would break.
		if ( ! empty( $_POST['website'] ) ) {
			$done( 'sent' );
		}
		$name    = isset( $_POST['ff_name'] ) ? mb_substr( sanitize_text_field( wp_unslash( $_POST['ff_name'] ) ), 0, 100 ) : '';
		$email   = isset( $_POST['ff_email'] ) ? sanitize_email( wp_unslash( $_POST['ff_email'] ) ) : '';
		$message = isset( $_POST['ff_message'] ) ? trim( sanitize_textarea_field( wp_unslash( $_POST['ff_message'] ) ) ) : '';
		$token   = isset( $_POST['cf-turnstile-response'] ) ? sanitize_text_field( wp_unslash( $_POST['cf-turnstile-response'] ) ) : '';
		// phpcs:enable

		if ( '' === $name || ! is_email( $email ) || mb_strlen( $message ) < 10 || mb_strlen( $message ) > 3000 ) {
			$done( 'fields' );
		}
		if ( is_wp_error( FactFusion_REST::verify_turnstile( $token ) ) ) {
			$done( 'human' );
		}
		if ( is_wp_error( FactFusion_Rate_Limiter::allow( 'contact' ) ) ) {
			$done( 'limit' );
		}

		$site    = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		/* translators: 1: site name, 2: sender's name. */
		$subject = sprintf( __( '[%1$s] Contact form: %2$s', 'factfusion-ai' ), $site, $name );
		$body    = sprintf( "Name: %s\nEmail: %s\n\n%s\n", $name, $email, $message );
		// Only the validated address goes into the header; the name stays in the body.
		$sent = wp_mail( get_option( 'admin_email' ), $subject, $body, array( 'Reply-To: ' . $email ) );

		$done( $sent ? 'sent' : 'mail' );
	}
}
