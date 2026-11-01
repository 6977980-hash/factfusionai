<?php
/**
 * Search titles and descriptions for fact pages, written from what people search in Search Console.
 *
 * Only the <title> tag and meta description change. The fact's own title, answer and text in the
 * database stay as they are, so its modified date and the Today's 5 workflow are not affected.
 * A description must say only what the fact already states.
 */

defined( 'ABSPATH' ) || exit;

/** Fact slug => search title, and a description where the fact's short answer is too thin. */
function ffc_seo_fact_overrides() {
	return array(
		'the-sun-always-lights-up-half-of-the-moon' => array(
			'title' => 'How Much of the Moon Is Lit by the Sun? Always Half',
		),
		'people-have-lived-in-space-without-a-break-since-2000' => array(
			'title' => 'How Long Have People Lived on the Space Station?',
		),
		'no-one-died-carving-mount-rushmore' => array(
			'title'       => 'How Many People Died Carving Mount Rushmore? None',
			'description' => 'No one died carving Mount Rushmore.',
		),
		'marss-moon-phobos-will-crash-into-mars-or-break-apart' => array(
			'title' => 'When Will Phobos Crash Into Mars? About 50 Million Years',
		),
		'your-heart-is-about-the-size-of-your-fist' => array(
			'title'       => 'Is Your Heart the Size of Your Fist? Yes, About',
			'description' => 'Yes, about the size of your fist, according to the US National Heart, Lung, and Blood Institute.',
		),
	);
}

add_filter(
	'factfusion_seo_meta',
	static function ( $meta, $view ) {
		if ( 'fact' !== $view ) {
			return $meta;
		}
		$fact      = FactFusion_Router::current_fact();
		$overrides = ffc_seo_fact_overrides();
		if ( isset( $overrides[ $fact->slug ] ) ) {
			$meta = array_merge( $meta, $overrides[ $fact->slug ] );
		}
		return $meta;
	},
	20,
	2
);
