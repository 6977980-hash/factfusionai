<?php
/**
 * Footer layout.
 *
 * The parent theme lays the footer out with `repeat(auto-fit, minmax(13rem, 1fr))`. That fit four
 * blocks (brand, Facts, Generators and guides, About), but phase 4 added an "Open data" column, so
 * the fifth block no longer fit on one row and "About" dropped below the others.
 *
 * Now: on wide screens every block sits in one row, however many columns there are. On tablets the
 * brand takes a full row with the link columns two by two under it; on phones everything stacks.
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'wp_enqueue_scripts',
	static function () {
		$css = '.ff-footer .ff-footer__inner{grid-template-columns:minmax(0,1fr);gap:var(--wp--preset--spacing--40,2rem)}'
			. '.ff-footer .ff-footer__inner>*{min-width:0}'
			. '.ff-footer .ff-footer__col ul a,.ff-footer .wp-block-navigation-item__content{overflow-wrap:anywhere}'
			. '@media (min-width:600px){.ff-footer .ff-footer__inner{grid-template-columns:repeat(2,minmax(0,1fr))}.ff-footer .ff-footer__brand{grid-column:1/-1}}'
			. '@media (min-width:1000px){.ff-footer .ff-footer__inner{grid-template-columns:minmax(0,1.4fr);grid-auto-columns:minmax(0,1fr);grid-auto-flow:column;gap:var(--wp--preset--spacing--40,2rem) var(--wp--preset--spacing--40,2rem)}.ff-footer .ff-footer__brand{grid-column:auto}}';
		wp_register_style( 'ffc-footer', false, array(), '1' );
		wp_enqueue_style( 'ffc-footer' );
		wp_add_inline_style( 'ffc-footer', $css );
	},
	20
);
