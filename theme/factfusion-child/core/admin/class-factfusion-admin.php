<?php
/**
 * Admin screens.
 *
 * @package FactFusion_AI_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Menu, form handling and shared helpers for the six Phase 1 screens.
 */
final class FactFusion_Admin {

	const LAUNCH_MINIMUM = 20;

	const PER_PAGE = 20;

	/**
	 * Menu slug => view file and menu label.
	 *
	 * @return array<string,array{0:string,1:string}>
	 */
	private static function pages() {
		return array(
			'factfusion'             => array( 'dashboard', __( 'Dashboard', 'factfusion-ai' ) ),
			'factfusion-facts'       => array( 'facts', __( 'Facts', 'factfusion-ai' ) ),
			'factfusion-categories'  => array( 'categories', __( 'Categories', 'factfusion-ai' ) ),
			'factfusion-sources'     => array( 'sources', __( 'Sources', 'factfusion-ai' ) ),
			'factfusion-corrections' => array( 'corrections', __( 'Corrections', 'factfusion-ai' ) ),
			'factfusion-settings'    => array( 'settings', __( 'Settings', 'factfusion-ai' ) ),
		);
	}

	/**
	 * Form actions => HTTP method they must arrive by.
	 */
	const ACTIONS = array(
		'save_fact'         => 'POST',
		'delete_fact'       => 'GET',
		'import_facts'      => 'POST',
		'save_category'     => 'POST',
		'delete_category'   => 'GET',
		'save_source'       => 'POST',
		'delete_source'     => 'GET',
		'save_correction'   => 'POST',
		'delete_correction' => 'GET',
		'save_settings'     => 'POST',
		'repair_tables'     => 'POST',
		'create_pages'      => 'POST',
		'create_content'    => 'POST',
		'fill_categories'   => 'POST',
	);

	/**
	 * Validation messages from the form just submitted.
	 *
	 * @var string[]
	 */
	private static $errors = array();

	/**
	 * Field values from a form that failed validation, to refill it.
	 *
	 * @var array|null
	 */
	private static $posted = null;

	/**
	 * Result of the CSV import just run.
	 *
	 * @var array|null
	 */
	private static $import_result = null;

	/**
	 * Registers hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_action' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	/**
	 * Adds the top-level menu and its six screens.
	 */
	public static function menu() {
		$cap = factfusion_capability();
		add_menu_page( 'FactFusion AI', 'FactFusion AI', $cap, 'factfusion', array( __CLASS__, 'render' ), 'dashicons-lightbulb', 26 );
		foreach ( self::pages() as $slug => $page ) {
			add_submenu_page( 'factfusion', $page[1] . ' ‹ FactFusion AI', $page[1], $cap, $slug, array( __CLASS__, 'render' ) );
		}
	}

	/**
	 * Loads the admin stylesheet on plugin screens only.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public static function assets( $hook ) {
		if ( false === strpos( $hook, 'factfusion' ) ) {
			return;
		}
		wp_enqueue_style( 'factfusion-admin', FACTFUSION_URL . 'assets/admin.css', array(), FACTFUSION_VERSION );

		if ( 'factfusion-facts' === self::arg( 'page' ) && 'edit' === self::arg( 'view' ) ) {
			wp_enqueue_script( 'factfusion-admin', FACTFUSION_URL . 'assets/admin.js', array(), FACTFUSION_VERSION, true );
			wp_add_inline_script(
				'factfusion-admin',
				'window.factfusionAdmin = ' . wp_json_encode(
					array(
						'endpoint' => esc_url_raw( rest_url( 'factfusion/v1/ai/explain' ) ),
						'nonce'    => wp_create_nonce( 'wp_rest' ),
						'text'     => array(
							'working' => __( 'Asking the AI for a draft…', 'factfusion-ai' ),
							'replace' => __( 'Replace the current explanation with an AI draft?', 'factfusion-ai' ),
							'done'    => __( 'Draft inserted. Check every statement against the source before you save.', 'factfusion-ai' ),
							'failed'  => __( 'The draft could not be created.', 'factfusion-ai' ),
						),
					)
				) . ';',
				'before'
			);
		}
	}

	/**
	 * URL of a plugin screen.
	 *
	 * @param string $page Menu slug.
	 * @param array  $args Extra query arguments.
	 * @return string
	 */
	public static function url( $page, $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => $page ), $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * Nonce-protected URL for a GET action such as delete.
	 *
	 * @param string $page   Menu slug.
	 * @param string $action Action name.
	 * @param int    $id     Record ID.
	 * @return string
	 */
	public static function action_url( $page, $action, $id ) {
		return wp_nonce_url(
			self::url(
				$page,
				array(
					'factfusion_action' => $action,
					'id'                => (int) $id,
				)
			),
			'factfusion_' . $action
		);
	}

	/**
	 * Prints the hidden fields every form needs.
	 *
	 * @param string $action Action name.
	 * @param int    $id     Record ID.
	 */
	public static function form_fields( $action, $id = 0 ) {
		wp_nonce_field( 'factfusion_' . $action );
		printf( '<input type="hidden" name="factfusion_action" value="%s">', esc_attr( $action ) );
		printf( '<input type="hidden" name="id" value="%d">', (int) $id );
	}

	/**
	 * A string query argument from the current URL.
	 *
	 * @param string $key Argument name.
	 * @return string
	 */
	public static function arg( $key ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list filters.
		return isset( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : '';
	}

	/**
	 * Values to show in a form: the failed submission if there is one, else the stored record.
	 *
	 * @param object|null $record   Stored record.
	 * @param array       $defaults Defaults for a new record.
	 * @return array
	 */
	public static function form_values( $record, $defaults = array() ) {
		if ( is_array( self::$posted ) ) {
			return array_merge( $defaults, self::$posted );
		}
		return $record ? array_merge( $defaults, (array) $record ) : $defaults;
	}

	/**
	 * The CSV import result, if an import just ran.
	 *
	 * @return array|null
	 */
	public static function import_result() {
		return self::$import_result;
	}

	/**
	 * Verifies and dispatches a submitted action.
	 */
	public static function handle_action() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- verified by check_admin_referer() below.
		if ( empty( $_REQUEST['factfusion_action'] ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$action = sanitize_key( wp_unslash( $_REQUEST['factfusion_action'] ) );
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : '';

		if ( ! isset( self::ACTIONS[ $action ] ) || self::ACTIONS[ $action ] !== $method ) {
			return;
		}
		if ( ! current_user_can( factfusion_capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'factfusion-ai' ), 403 );
		}
		check_admin_referer( 'factfusion_' . $action );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$id   = isset( $_REQUEST['id'] ) ? absint( $_REQUEST['id'] ) : 0;
		// Each repository sanitizes every field it stores.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$data = isset( $_POST['ff'] ) && is_array( $_POST['ff'] ) ? wp_unslash( $_POST['ff'] ) : array();

		$handler = 'do_' . $action;
		self::$handler( $id, $data );
	}

	/**
	 * Keeps the form on screen with its values and the validation messages.
	 *
	 * @param WP_Error $error Validation result.
	 * @param array    $data  Submitted values.
	 */
	private static function fail( $error, $data ) {
		self::$errors = $error->get_error_messages();
		self::$posted = $data;
	}

	/**
	 * Redirects after a successful action.
	 *
	 * @param string $page Menu slug.
	 * @param array  $args Query arguments.
	 */
	private static function redirect( $page, $args = array() ) {
		wp_safe_redirect( self::url( $page, $args ) );
		exit;
	}

	/**
	 * Saves a fact.
	 *
	 * @param int   $id   Fact ID.
	 * @param array $data Submitted values.
	 */
	private static function do_save_fact( $id, $data ) {
		$result = FactFusion_Facts::save( $data, $id );
		if ( is_wp_error( $result ) ) {
			self::fail( $result, $data );
			return;
		}
		self::redirect(
			'factfusion-facts',
			array(
				'view'      => 'edit',
				'id'        => $result,
				'ff_notice' => 'saved',
			)
		);
	}

	/**
	 * Deletes a fact.
	 *
	 * @param int $id Fact ID.
	 */
	private static function do_delete_fact( $id ) {
		FactFusion_Facts::delete( $id );
		self::redirect( 'factfusion-facts', array( 'ff_notice' => 'deleted' ) );
	}

	/**
	 * Imports an uploaded CSV file.
	 */
	private static function do_import_facts() {
		// The nonce is verified in handle_action(). tmp_name is a server-generated path, checked with is_uploaded_file().
		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput
		$error = isset( $_FILES['ff_csv']['error'] ) ? (int) $_FILES['ff_csv']['error'] : UPLOAD_ERR_NO_FILE;
		$tmp   = isset( $_FILES['ff_csv']['tmp_name'] ) ? (string) $_FILES['ff_csv']['tmp_name'] : '';
		$name  = isset( $_FILES['ff_csv']['name'] ) ? sanitize_file_name( wp_unslash( $_FILES['ff_csv']['name'] ) ) : '';
		$size  = isset( $_FILES['ff_csv']['size'] ) ? (int) $_FILES['ff_csv']['size'] : 0;
		// phpcs:enable

		if ( UPLOAD_ERR_OK !== $error || '' === $tmp || ! is_uploaded_file( $tmp ) ) {
			self::$errors = array( __( 'Choose a CSV file to import.', 'factfusion-ai' ) );
			return;
		}
		if ( 'csv' !== strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ) ) {
			self::$errors = array( __( 'The file must be a .csv file.', 'factfusion-ai' ) );
			return;
		}
		if ( $size > MB_IN_BYTES ) {
			self::$errors = array( __( 'The file is larger than 1 MB.', 'factfusion-ai' ) );
			return;
		}

		$result = FactFusion_Importer::import_file( $tmp );
		if ( is_wp_error( $result ) ) {
			self::$errors = $result->get_error_messages();
			return;
		}
		self::$import_result = $result;
	}

	/**
	 * Saves a category.
	 *
	 * @param int   $id   Category ID.
	 * @param array $data Submitted values.
	 */
	private static function do_save_category( $id, $data ) {
		$result = FactFusion_Categories::save( $data, $id );
		if ( is_wp_error( $result ) ) {
			self::fail( $result, $data );
			return;
		}
		self::redirect( 'factfusion-categories', array( 'ff_notice' => 'saved' ) );
	}

	/**
	 * Deletes a category.
	 *
	 * @param int $id Category ID.
	 */
	private static function do_delete_category( $id ) {
		$result = FactFusion_Categories::delete( $id );
		if ( is_wp_error( $result ) ) {
			self::$errors = $result->get_error_messages();
			return;
		}
		self::redirect( 'factfusion-categories', array( 'ff_notice' => 'deleted' ) );
	}

	/**
	 * Saves a source.
	 *
	 * @param int   $id   Source ID.
	 * @param array $data Submitted values.
	 */
	private static function do_save_source( $id, $data ) {
		$result = FactFusion_Sources::save( $data, $id );
		if ( is_wp_error( $result ) ) {
			self::fail( $result, $data );
			return;
		}
		self::redirect( 'factfusion-sources', array( 'ff_notice' => 'saved' ) );
	}

	/**
	 * Deletes a source.
	 *
	 * @param int $id Source ID.
	 */
	private static function do_delete_source( $id ) {
		$result = FactFusion_Sources::delete( $id );
		if ( is_wp_error( $result ) ) {
			self::$errors = $result->get_error_messages();
			return;
		}
		self::redirect( 'factfusion-sources', array( 'ff_notice' => 'deleted' ) );
	}

	/**
	 * Saves a correction report.
	 *
	 * @param int   $id   Report ID.
	 * @param array $data Submitted values.
	 */
	private static function do_save_correction( $id, $data ) {
		$result = FactFusion_Corrections::save( $data, $id );
		if ( is_wp_error( $result ) ) {
			self::fail( $result, $data );
			return;
		}
		self::redirect( 'factfusion-corrections', array( 'ff_notice' => 'saved' ) );
	}

	/**
	 * Deletes a correction report.
	 *
	 * @param int $id Report ID.
	 */
	private static function do_delete_correction( $id ) {
		FactFusion_Corrections::delete( $id );
		self::redirect( 'factfusion-corrections', array( 'ff_notice' => 'deleted' ) );
	}

	/**
	 * Saves settings.
	 *
	 * @param int   $id   Unused.
	 * @param array $data Submitted values.
	 */
	private static function do_save_settings( $id, $data ) {
		$result = FactFusion_Settings::save( $data );
		if ( is_wp_error( $result ) ) {
			self::fail( $result, $data );
			return;
		}
		self::redirect( 'factfusion-settings', array( 'ff_notice' => 'saved' ) );
	}

	/**
	 * Recreates missing tables, default categories and the fact URL rule.
	 */
	private static function do_repair_tables() {
		FactFusion_DB::install();
		FactFusion_Categories::seed_defaults();
		flush_rewrite_rules();
		self::redirect( 'factfusion', array( 'ff_notice' => 'repaired' ) );
	}

	/**
	 * Creates any of the trust pages that does not exist yet.
	 */
	private static function do_create_pages() {
		$created = FactFusion_Pages::create_missing();
		self::redirect( 'factfusion', array( 'ff_notice' => $created ? 'pages' : 'nopages' ) );
	}

	/**
	 * Creates any article or tool page that does not exist yet, as a draft.
	 */
	private static function do_create_content() {
		$created = FactFusion_Content::create_missing();
		self::redirect( 'factfusion', array( 'ff_notice' => $created ? 'content' : 'nocontent' ) );
	}

	/**
	 * Fills empty category page text from the starter content.
	 */
	private static function do_fill_categories() {
		$changed = FactFusion_Categories::fill_missing_content();
		self::redirect( 'factfusion', array( 'ff_notice' => $changed ? 'categories' : 'nocategories' ) );
	}

	/**
	 * Prints success and validation notices.
	 */
	private static function notices() {
		$messages = array(
			'saved'    => __( 'Saved.', 'factfusion-ai' ),
			'deleted'  => __( 'Deleted.', 'factfusion-ai' ),
			'repaired' => __( 'Tables and URL rules were rebuilt.', 'factfusion-ai' ),
			'pages'    => __( 'The missing pages were created. Please read each one and edit it in your own words.', 'factfusion-ai' ),
			'nopages'  => __( 'All the pages already exist. Nothing was changed.', 'factfusion-ai' ),
			'content'   => __( 'The missing articles and tool pages were created as drafts. Read each one, then publish it under Pages.', 'factfusion-ai' ),
			'nocontent' => __( 'All the articles and tool pages already exist. Nothing was changed.', 'factfusion-ai' ),
			'categories'   => __( 'The empty category page fields were filled with starter text. Read each category page and edit it under Categories.', 'factfusion-ai' ),
			'nocategories' => __( 'Every category page already has its text. Nothing was changed.', 'factfusion-ai' ),
		);
		$notice   = self::arg( 'ff_notice' );
		if ( isset( $messages[ $notice ] ) && ! self::$errors ) {
			printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html( $messages[ $notice ] ) );
		}
		if ( self::$errors ) {
			echo '<div class="notice notice-error"><ul class="factfusion-errors">';
			foreach ( self::$errors as $message ) {
				printf( '<li>%s</li>', esc_html( $message ) );
			}
			echo '</ul></div>';
		}
	}

	/**
	 * Renders the current screen.
	 */
	public static function render() {
		if ( ! current_user_can( factfusion_capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to view this page.', 'factfusion-ai' ), 403 );
		}
		$pages = self::pages();
		$slug  = sanitize_key( self::arg( 'page' ) );
		$view  = isset( $pages[ $slug ] ) ? $pages[ $slug ][0] : 'dashboard';

		echo '<div class="wrap factfusion-wrap">';
		ob_start();
		require FACTFUSION_DIR . 'admin/views/' . $view . '.php';
		$body = ob_get_clean();
		// Notices print under the screen's own <h1>, where WordPress expects them.
		$split = strpos( $body, '<hr class="wp-header-end">' );
		if ( false === $split ) {
			self::notices();
			echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the view.
		} else {
			echo substr( $body, 0, $split ) . '<hr class="wp-header-end">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			self::notices();
			echo substr( $body, $split + strlen( '<hr class="wp-header-end">' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</div>';
	}

	/**
	 * Prints one row of a form table.
	 *
	 * @param string $type  text, url, email, number, date, textarea, select or checkbox.
	 * @param string $key   Field key inside the "ff" array.
	 * @param string $label Field label.
	 * @param mixed  $value Current value.
	 * @param array  $args  options, description, required, rows, min, max, maxlength, text.
	 */
	public static function row( $type, $key, $label, $value, $args = array() ) {
		$id   = 'ff-' . str_replace( '_', '-', $key );
		$name = 'ff[' . $key . ']';
		$desc = $id . '-description';
		$attr = '';
		foreach ( array( 'min', 'max', 'maxlength', 'rows', 'step' ) as $name_attr ) {
			if ( isset( $args[ $name_attr ] ) ) {
				$attr .= sprintf( ' %s="%s"', $name_attr, esc_attr( $args[ $name_attr ] ) );
			}
		}
		if ( ! empty( $args['required'] ) ) {
			$attr .= ' required';
		}
		if ( ! empty( $args['description'] ) ) {
			$attr .= sprintf( ' aria-describedby="%s"', esc_attr( $desc ) );
		}

		echo '<tr>';
		if ( 'checkbox' === $type ) {
			printf( '<th scope="row">%s</th><td>', esc_html( $label ) );
			printf(
				'<label for="%1$s"><input type="checkbox" id="%1$s" name="%2$s" value="1"%3$s%4$s> %5$s</label>',
				esc_attr( $id ),
				esc_attr( $name ),
				checked( ! empty( $value ), true, false ),
				$attr, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from esc_attr() above.
				esc_html( $args['text'] ?? $label )
			);
		} else {
			printf( '<th scope="row"><label for="%s">%s</label></th><td>', esc_attr( $id ), esc_html( $label ) );
			if ( 'textarea' === $type ) {
				printf(
					'<textarea id="%1$s" name="%2$s" class="large-text"%3$s>%4$s</textarea>',
					esc_attr( $id ),
					esc_attr( $name ),
					$attr, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					esc_textarea( (string) $value )
				);
			} elseif ( 'select' === $type ) {
				printf( '<select id="%1$s" name="%2$s"%3$s>', esc_attr( $id ), esc_attr( $name ), $attr ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				foreach ( (array) ( $args['options'] ?? array() ) as $option_value => $option_label ) {
					printf( '<option value="%s"%s>%s</option>', esc_attr( $option_value ), selected( (string) $value, (string) $option_value, false ), esc_html( $option_label ) );
				}
				echo '</select>';
			} else {
				printf(
					'<input type="%1$s" id="%2$s" name="%3$s" value="%4$s" class="%5$s"%6$s>',
					esc_attr( $type ),
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( (string) $value ),
					'number' === $type ? 'small-text' : 'regular-text',
					$attr // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				);
			}
		}
		if ( ! empty( $args['description'] ) ) {
			printf( '<p class="description" id="%s">%s</p>', esc_attr( $desc ), esc_html( $args['description'] ) );
		}
		echo '</td></tr>';
	}

	/**
	 * Prints page links for a list.
	 *
	 * @param int $total Total records.
	 */
	public static function pagination( $total ) {
		$pages = (int) ceil( $total / self::PER_PAGE );
		if ( $pages < 2 ) {
			return;
		}
		$links = paginate_links(
			array(
				'base'    => add_query_arg( 'paged', '%#%' ),
				'format'  => '',
				'current' => max( 1, absint( self::arg( 'paged' ) ) ),
				'total'   => $pages,
			)
		);
		printf( '<div class="tablenav bottom"><div class="tablenav-pages">%s</div></div>', wp_kses_post( $links ) );
	}

	/**
	 * A stored UTC datetime in the site's timezone and date format.
	 *
	 * @param string|null $datetime UTC datetime.
	 * @return string
	 */
	public static function date( $datetime ) {
		if ( empty( $datetime ) || 0 === strpos( $datetime, '0000' ) ) {
			return '—';
		}
		return wp_date( get_option( 'date_format' ), strtotime( $datetime . ' UTC' ) );
	}
}
