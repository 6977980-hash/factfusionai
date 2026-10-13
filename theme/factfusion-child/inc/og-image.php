<?php
/**
 * SEO audit, phase 2: a share image for every fact.
 *
 * Social sites and Google Discover showed the same picture for every page. Each fact now gets
 * its own 1200x630 card with its headline, category and source, drawn once with GD and saved
 * in uploads/factfusion-og/. The file name changes when the text changes, so share previews
 * refresh. Font: Carlito (SIL Open Font License, assets/fonts/OFL.txt).
 */

defined( 'ABSPATH' ) || exit;

const FFC_OG_VERSION = '1';

/**
 * URL of the fact's share image, drawing it first if needed. Empty when it cannot be drawn.
 *
 * @param object $fact Fact row.
 * @return string
 */
function ffc_og_image_url( $fact ) {
	if ( ! function_exists( 'imagettftext' ) || ! function_exists( 'imagepng' ) ) {
		return '';
	}
	$category = $fact->category_id ? FactFusion_Categories::get( $fact->category_id ) : null;
	$sources  = FactFusion_Sources::for_fact( $fact->id );
	$source   = $sources ? $sources[0] : null;
	$parts    = array(
		'title'    => (string) $fact->title,
		'category' => $category ? (string) $category->name : 'Facts',
		'source'   => $source ? sprintf( 'Source: %s · Tier %s', $source->source_name, $source->tier ) : '',
	);
	$upload   = wp_upload_dir( null, false );
	if ( ! empty( $upload['error'] ) ) {
		return '';
	}
	$dir  = $upload['basedir'] . '/factfusion-og';
	$name = $fact->slug . '-' . substr( md5( FFC_OG_VERSION . '|' . implode( '|', $parts ) ), 0, 10 ) . '.png';
	$url  = $upload['baseurl'] . '/factfusion-og/' . $name;
	if ( file_exists( $dir . '/' . $name ) ) {
		return $url;
	}
	if ( ! wp_mkdir_p( $dir ) || ! ffc_og_draw( $dir . '/' . $name, $parts ) ) {
		return '';
	}
	// Remove this fact's older images.
	foreach ( (array) glob( $dir . '/' . $fact->slug . '-*.png' ) as $old ) {
		$base = basename( $old );
		if ( $base !== $name && preg_match( '/^' . preg_quote( $fact->slug, '/' ) . '-[a-f0-9]{10}\.png$/', $base ) ) {
			wp_delete_file( $old );
		}
	}
	return $url;
}

/**
 * Lines of text that fit a width.
 *
 * @return string[]
 */
function ffc_og_wrap( $text, $font, $size, $width ) {
	$lines = array();
	$line  = '';
	foreach ( preg_split( '/\s+/u', trim( $text ) ) as $word ) {
		$try = '' === $line ? $word : $line . ' ' . $word;
		$box = imagettfbbox( $size, 0, $font, $try );
		if ( '' !== $line && $box[2] - $box[0] > $width ) {
			$lines[] = $line;
			$line    = $word;
		} else {
			$line = $try;
		}
	}
	if ( '' !== $line ) {
		$lines[] = $line;
	}
	return $lines;
}

/**
 * Draws the card and saves it as a PNG.
 *
 * @param string $path  File to write.
 * @param array  $parts title, category, source.
 * @return bool
 */
function ffc_og_draw( $path, $parts ) {
	$bold    = get_stylesheet_directory() . '/assets/fonts/Carlito-Bold.ttf';
	$regular = get_stylesheet_directory() . '/assets/fonts/Carlito-Regular.ttf';
	if ( ! is_readable( $bold ) || ! is_readable( $regular ) ) {
		return false;
	}
	$w   = 1200;
	$h   = 630;
	$img = imagecreatetruecolor( $w, $h );
	if ( ! $img ) {
		return false;
	}
	$white   = imagecolorallocate( $img, 255, 255, 255 );
	$text    = imagecolorallocate( $img, 17, 24, 39 );
	$muted   = imagecolorallocate( $img, 75, 85, 99 );
	$primary = imagecolorallocate( $img, 37, 99, 235 );
	$soft    = imagecolorallocate( $img, 219, 234, 254 );
	$border  = imagecolorallocate( $img, 229, 231, 235 );

	imagefilledrectangle( $img, 0, 0, $w, $h, $white );
	imagefilledrectangle( $img, 0, 0, 16, $h, $primary );
	imagefilledrectangle( $img, 16, $h - 96, $w, $h, imagecolorallocate( $img, 243, 244, 246 ) );
	imageline( $img, 16, $h - 96, $w, $h - 96, $border );

	$left = 80;
	imagettftext( $img, 26, 0, $left, 92, $primary, $bold, 'FactFusion AI' );

	// Category pill.
	$label = strtoupper( $parts['category'] );
	$box   = imagettfbbox( 20, 0, $bold, $label );
	$pill  = $box[2] - $box[0] + 36;
	imagefilledrectangle( $img, $left, 122, $left + $pill, 162, $soft );
	imagettftext( $img, 20, 0, $left + 18, 151, $primary, $bold, $label );

	// Headline: as large as fits in four lines.
	$area = $w - $left - 80;
	for ( $size = 64; $size >= 36; $size -= 4 ) {
		$lines = ffc_og_wrap( $parts['title'], $bold, $size, $area );
		if ( count( $lines ) <= 4 ) {
			break;
		}
	}
	$lines = array_slice( $lines, 0, 4 );
	$y     = 214 + $size;
	foreach ( $lines as $line ) {
		imagettftext( $img, $size, 0, $left, (int) $y, $text, $bold, $line );
		$y += $size * 1.3;
	}

	if ( '' !== $parts['source'] ) {
		$source = ffc_og_wrap( $parts['source'], $regular, 26, 760 )[0];
		imagettftext( $img, 26, 0, $left, $h - 38, $muted, $regular, $source );
	}
	$site = 'factfusionai.com';
	$box  = imagettfbbox( 26, 0, $bold, $site );
	imagettftext( $img, 26, 0, $w - 80 - ( $box[2] - $box[0] ), $h - 38, $primary, $bold, $site );

	$ok = imagepng( $img, $path, 9 );
	imagedestroy( $img );
	return (bool) $ok;
}
