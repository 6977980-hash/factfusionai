<?php
/**
 * Plugin Name: FactFusion Deploy
 * Description: Deploy source for factfusionai. Hostinger Git deploys this repo into the plugin folder; the plugin copies the bundled theme in /theme into wp-content/themes. All site changes live in the theme, so they stay even if this plugin is deleted.
 * Version:     1.0.0
 * Author:      FactFusion AI
 * Requires PHP: 7.4
 */

defined( 'ABSPATH' ) || exit;

const FFD_THEME_SLUG   = 'factfusion-child';
const FFD_OPT_SIG      = 'ffd_source_signature';
const FFD_OPT_MANIFEST = 'ffd_manifest';
const FFD_OPT_PARENT   = 'ffd_parent_theme';
const FFD_OPT_LAST     = 'ffd_last_sync';

function ffd_source_dir() {
	return __DIR__ . '/theme/' . FFD_THEME_SLUG;
}

function ffd_dest_dir() {
	return trailingslashit( get_theme_root() ) . FFD_THEME_SLUG;
}

/** Relative paths of every file in the bundled theme. */
function ffd_source_files() {
	$src = ffd_source_dir();
	if ( ! is_dir( $src ) ) {
		return array();
	}
	$files = array();
	$it    = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $src, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $it as $file ) {
		if ( $file->isFile() ) {
			$files[] = ltrim( str_replace( '\\', '/', substr( $file->getPathname(), strlen( $src ) ) ), '/' );
		}
	}
	sort( $files );
	return $files;
}

/** Cheap signature (path + size + mtime) so a new deploy is noticed without hashing every file. */
function ffd_source_signature( $files ) {
	$src  = ffd_source_dir();
	$data = '';
	foreach ( $files as $rel ) {
		$path  = $src . '/' . $rel;
		$data .= $rel . '|' . filesize( $path ) . '|' . filemtime( $path ) . "\n";
	}
	return md5( $data );
}

/** Parent theme the child theme points at: saved choice, else the parent of whatever is active. */
function ffd_parent_theme() {
	$parent = get_option( FFD_OPT_PARENT );
	if ( ! $parent && get_stylesheet() !== FFD_THEME_SLUG ) {
		$parent = get_template();
		update_option( FFD_OPT_PARENT, $parent );
	}
	return $parent;
}

/** Copy the bundled theme into wp-content/themes. Returns true when files were written. */
function ffd_sync( $force = false ) {
	$files = ffd_source_files();
	if ( ! $files ) {
		return false;
	}
	$sig = ffd_source_signature( $files );
	if ( ! $force && get_option( FFD_OPT_SIG ) === $sig && is_dir( ffd_dest_dir() ) ) {
		return false;
	}

	// Simple lock so two requests right after a deploy don't copy at the same time.
	if ( ! add_option( 'ffd_sync_lock', time(), '', 'no' ) ) {
		if ( time() - (int) get_option( 'ffd_sync_lock' ) < 60 ) {
			return false;
		}
		update_option( 'ffd_sync_lock', time(), 'no' );
	}

	$src    = ffd_source_dir();
	$dest   = ffd_dest_dir();
	$parent = ffd_parent_theme();

	foreach ( $files as $rel ) {
		$target = $dest . '/' . $rel;
		wp_mkdir_p( dirname( $target ) );
		if ( 'style.css' === $rel && $parent ) {
			$css = file_get_contents( $src . '/' . $rel );
			$css = preg_replace( '/^(\s*Template:).*$/mi', '$1 ' . $parent, $css, 1 );
			file_put_contents( $target, $css );
		} else {
			copy( $src . '/' . $rel, $target );
		}
	}

	// Remove files this plugin wrote earlier that were deleted from the repo. Never touches other files.
	$old = (array) get_option( FFD_OPT_MANIFEST, array() );
	foreach ( array_diff( $old, $files ) as $rel ) {
		$path = $dest . '/' . $rel;
		if ( is_file( $path ) ) {
			unlink( $path );
		}
	}

	update_option( FFD_OPT_MANIFEST, $files, 'no' );
	update_option( FFD_OPT_SIG, $sig );
	update_option( FFD_OPT_LAST, time(), 'no' );
	delete_option( 'ffd_sync_lock' );

	if ( function_exists( 'wp_clean_themes_cache' ) ) {
		wp_clean_themes_cache();
	}
	// New theme code can change every page, so clear the page cache (LiteSpeed on Hostinger).
	do_action( 'litespeed_purge_all' );
	return true;
}

register_activation_hook( __FILE__, function () {
	ffd_sync( true );
} );

// Hostinger only pulls files, so check for a new deploy on each request (stat calls only, no DB write unless changed).
add_action( 'init', 'ffd_sync', 1, 0 );

/* ---------- Admin page: Tools → FactFusion Deploy ---------- */

add_action( 'admin_menu', function () {
	add_management_page( 'FactFusion Deploy', 'FactFusion Deploy', 'manage_options', 'ffd', 'ffd_admin_page' );
} );

add_action( 'admin_post_ffd_sync', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Not allowed' );
	}
	check_admin_referer( 'ffd_sync' );
	if ( ! empty( $_POST['parent'] ) ) {
		update_option( FFD_OPT_PARENT, sanitize_key( wp_unslash( $_POST['parent'] ) ) );
	}
	ffd_sync( true );
	wp_safe_redirect( admin_url( 'tools.php?page=ffd&synced=1' ) );
	exit;
} );

function ffd_admin_page() {
	$theme  = wp_get_theme( FFD_THEME_SLUG );
	$active = get_stylesheet() === FFD_THEME_SLUG;
	$last   = get_option( FFD_OPT_LAST );
	?>
	<div class="wrap">
		<h1>FactFusion Deploy</h1>
		<?php if ( isset( $_GET['synced'] ) ) : ?>
			<div class="notice notice-success"><p>Theme synced.</p></div>
		<?php endif; ?>
		<p>Theme folder: <code><?php echo esc_html( ffd_dest_dir() ); ?></code></p>
		<p>Last sync: <?php echo $last ? esc_html( wp_date( 'Y-m-d H:i:s', $last ) ) : 'never'; ?></p>
		<p>Theme status:
			<?php
			if ( ! $theme->exists() ) {
				echo 'not installed yet';
			} elseif ( $active ) {
				echo '<strong>active</strong>';
			} else {
				echo 'installed, not active. <a href="' . esc_url( admin_url( 'themes.php' ) ) . '">Activate it in Appearance → Themes</a>';
			}
			?>
		</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'ffd_sync' ); ?>
			<input type="hidden" name="action" value="ffd_sync">
			<p><label>Parent theme slug: <input name="parent" value="<?php echo esc_attr( ffd_parent_theme() ); ?>"></label></p>
			<?php submit_button( 'Sync theme now' ); ?>
		</form>
	</div>
	<?php
}
