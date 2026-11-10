<?php
/**
 * Homepage hero: stop the left column from jumping, and tidier category buttons.
 *
 * The parent theme's home.css centres the two hero columns (`align-items: center`). Every new fact
 * has a different length, so the fact card on the right grows or shrinks, and the headline and
 * stats on the left were re-centred each time, which made them jump. Now both columns start at the
 * top, so the left side stays still whatever fact is shown.
 *
 * The category buttons are also smaller and calmer: a soft outline, the selected one in the
 * brand yellow, a visible keyboard focus ring, and on phones one swipeable row that fades at the
 * edge so it is clear there are more.
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'wp_enqueue_scripts',
	static function () {
		if ( ! is_front_page() ) {
			return;
		}
		$css = '@media (min-width:1000px){.ff-hero .ff-hero__grid{align-items:start}.ff-hero .ff-hero__intro{padding-block-start:3.25rem}}'
			// Category buttons.
			. '.ff-generator--hero .ff-chips{gap:.5rem;margin:0 0 1.125rem}'
			. '.ff-generator--hero .ff-chip{min-height:38px;padding:0 .9375rem;border:1px solid rgba(148,163,184,.28);border-radius:999px;background:rgba(255,255,255,.04);color:#CBD5E1;font-size:.8125rem;font-weight:600;letter-spacing:.005em;line-height:1;white-space:nowrap;transition:background-color .15s ease,border-color .15s ease,color .15s ease}'
			. '.ff-generator--hero .ff-chip:hover{border-color:rgba(147,197,253,.6);background:rgba(255,255,255,.08);color:#FFFFFF}'
			. '.ff-generator--hero .ff-chip:focus-visible{outline:2px solid #93C5FD;outline-offset:2px}'
			. '.ff-generator--hero .ff-chip[aria-pressed="true"],.ff-generator--hero .ff-chip[aria-pressed="true"]:hover{border-color:#FBBF24;background:#FBBF24;color:#0B1220}'
			// Phones and tablets: one row that scrolls sideways, fading at the right edge.
			. '@media (max-width:999px){.ff-generator--hero .ff-chips{flex-wrap:nowrap;overflow-x:auto;padding-inline-end:1.5rem;-webkit-mask-image:linear-gradient(to right,#000 calc(100% - 2rem),transparent);mask-image:linear-gradient(to right,#000 calc(100% - 2rem),transparent)}.ff-generator--hero .ff-chip{min-height:40px}}'
			. '@media (prefers-reduced-motion:reduce){.ff-generator--hero .ff-chip{transition:none}}';
		wp_register_style( 'ffc-hero-generator', false, array( 'factfusion-ai-home' ), '1' );
		wp_enqueue_style( 'ffc-hero-generator' );
		wp_add_inline_style( 'ffc-hero-generator', $css );
	},
	20
);
