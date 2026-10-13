<?php
/**
 * Public editor profiles.
 *
 * @package FactFusion_AI_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gives an editor a public page at /author/{slug}/ built from their own
 * WordPress profile. Nothing is shown that the editor did not enter.
 */
final class FactFusion_Profiles {

	const SLUG = 'factfusion_profile_slug';

	const ROLE = 'factfusion_role';

	const LINKEDIN = 'factfusion_linkedin';

	/**
	 * Registers hooks.
	 */
	public static function init() {
		add_action( 'show_user_profile', array( __CLASS__, 'fields' ) );
		add_action( 'edit_user_profile', array( __CLASS__, 'fields' ) );
		add_action( 'personal_options_update', array( __CLASS__, 'save' ) );
		add_action( 'edit_user_profile_update', array( __CLASS__, 'save' ) );
	}

	/**
	 * The editor who owns a profile address.
	 *
	 * @param string $slug Profile slug.
	 * @return WP_User|null
	 */
	public static function get_by_slug( $slug ) {
		if ( '' === $slug ) {
			return null;
		}
		$users = get_users(
			array(
				'meta_key'   => self::SLUG, // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value' => $slug, // phpcs:ignore WordPress.DB.SlowDBQuery
				'number'     => 1,
			)
		);
		return $users ? $users[0] : null;
	}

	/**
	 * Every editor with a public profile.
	 *
	 * @return WP_User[]
	 */
	public static function all() {
		return get_users(
			array(
				'meta_key'     => self::SLUG, // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_compare' => 'EXISTS',
			)
		);
	}

	/**
	 * An editor's profile slug.
	 *
	 * @param WP_User $user User.
	 * @return string Empty when the editor has no public profile.
	 */
	public static function slug( $user ) {
		return (string) get_user_meta( $user->ID, self::SLUG, true );
	}

	/**
	 * URL of an editor's public profile.
	 *
	 * @param WP_User $user User.
	 * @return string Empty when the editor has no public profile.
	 */
	public static function url( $user ) {
		$slug = self::slug( $user );
		return '' === $slug ? '' : home_url( user_trailingslashit( 'author/' . $slug ) );
	}

	/**
	 * An editor's job title.
	 *
	 * @param WP_User $user User.
	 * @return string
	 */
	public static function role( $user ) {
		return (string) get_user_meta( $user->ID, self::ROLE, true );
	}

	/**
	 * An editor's LinkedIn profile URL.
	 *
	 * @param WP_User $user User.
	 * @return string
	 */
	public static function linkedin( $user ) {
		return (string) get_user_meta( $user->ID, self::LINKEDIN, true );
	}

	/**
	 * Prints the profile fields on the WordPress user screen.
	 *
	 * @param WP_User $user User being edited.
	 */
	public static function fields( $user ) {
		if ( ! user_can( $user, factfusion_capability() ) ) {
			return;
		}
		$url = self::url( $user );
		?>
		<h2><?php esc_html_e( 'FactFusion AI public profile', 'factfusion-ai' ); ?></h2>
		<p><?php esc_html_e( 'Shown on your public author page and linked from every fact you verify. Your name comes from "Display name publicly as" and your biography from "Biographical Info" above.', 'factfusion-ai' ); ?></p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="factfusion-profile-slug"><?php esc_html_e( 'Author page address', 'factfusion-ai' ); ?></label></th>
				<td>
					<code><?php echo esc_html( home_url( '/author/' ) ); ?></code>
					<input type="text" id="factfusion-profile-slug" name="factfusion_profile_slug" value="<?php echo esc_attr( self::slug( $user ) ); ?>" class="regular-text" aria-describedby="factfusion-profile-slug-description">
					<p class="description" id="factfusion-profile-slug-description">
						<?php esc_html_e( 'Lowercase words joined by hyphens, for example ali-ahmad-chaudhry. Leave empty to have no public page.', 'factfusion-ai' ); ?>
						<?php if ( $url ) : ?>
							<a href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'View page', 'factfusion-ai' ); ?></a>
						<?php endif; ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="factfusion-role"><?php esc_html_e( 'Role', 'factfusion-ai' ); ?></label></th>
				<td><input type="text" id="factfusion-role" name="factfusion_role" value="<?php echo esc_attr( self::role( $user ) ); ?>" class="regular-text" maxlength="100"></td>
			</tr>
			<tr>
				<th scope="row"><label for="factfusion-linkedin"><?php esc_html_e( 'LinkedIn profile URL', 'factfusion-ai' ); ?></label></th>
				<td><input type="url" id="factfusion-linkedin" name="factfusion_linkedin" value="<?php echo esc_attr( self::linkedin( $user ) ); ?>" class="regular-text"></td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Saves the profile fields. WordPress has already verified the profile nonce.
	 *
	 * @param int $user_id User being saved.
	 */
	public static function save( $user_id ) {
		if ( ! current_user_can( 'edit_user', $user_id ) || ! user_can( $user_id, factfusion_capability() ) ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified by WordPress in user-edit.php.
		if ( isset( $_POST['factfusion_profile_slug'] ) ) {
			$slug  = sanitize_title( wp_unslash( $_POST['factfusion_profile_slug'] ) );
			$owner = self::get_by_slug( $slug );
			if ( '' === $slug ) {
				delete_user_meta( $user_id, self::SLUG );
			} elseif ( ! $owner || (int) $owner->ID === (int) $user_id ) {
				update_user_meta( $user_id, self::SLUG, $slug );
			}
		}
		if ( isset( $_POST['factfusion_role'] ) ) {
			update_user_meta( $user_id, self::ROLE, mb_substr( sanitize_text_field( wp_unslash( $_POST['factfusion_role'] ) ), 0, 100 ) );
		}
		if ( isset( $_POST['factfusion_linkedin'] ) ) {
			$raw  = trim( (string) wp_unslash( $_POST['factfusion_linkedin'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized on the next line.
			$url  = esc_url_raw( $raw, array( 'https' ) );
			$host = $url ? strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) ) : '';
			// Only a real LinkedIn address is accepted, so the page cannot link somewhere unexpected.
			// Anything else is ignored and the stored link is kept; an empty box removes it.
			if ( 'linkedin.com' === $host || '.linkedin.com' === substr( $host, -13 ) ) {
				update_user_meta( $user_id, self::LINKEDIN, $url );
			} elseif ( '' === $raw ) {
				delete_user_meta( $user_id, self::LINKEDIN );
			}
		}
		// phpcs:enable
		do_action( 'factfusion_facts_changed' );
	}
}
