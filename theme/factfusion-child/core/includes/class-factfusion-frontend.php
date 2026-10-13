<?php
/**
 * Public output: generator, Fact of the Day, fact page parts, browse page and embeds.
 *
 * @package FactFusion_AI_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders everything a visitor sees. Every fact comes from the database.
 */
final class FactFusion_Frontend {

	const BROWSE_PER_PAGE = 20;

	/**
	 * Whether the front-end script is needed on this page.
	 *
	 * @var bool
	 */
	private static $needs_script = false;

	/**
	 * Registers hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_shortcodes' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		add_action( 'wp_footer', array( __CLASS__, 'print_script' ), 5 );
		add_action( 'wp_head', array( __CLASS__, 'mark_script_support' ), 0 );
		add_action( 'factfusion_facts_changed', array( __CLASS__, 'flush_caches' ) );
	}

	/**
	 * Marks the page as script-capable before anything is drawn. Controls that
	 * need the script are laid out from the start and simply become visible,
	 * so nothing on the page moves when the script arrives.
	 */
	public static function mark_script_support() {
		wp_print_inline_script_tag( 'document.documentElement.classList.add("ff-js");' );
	}

	/**
	 * Registers the shortcodes used by the theme templates.
	 */
	public static function register_shortcodes() {
		add_shortcode( 'factfusion_generator', array( __CLASS__, 'shortcode_generator' ) );
		add_shortcode( 'factfusion_fotd', array( __CLASS__, 'shortcode_fotd' ) );
		add_shortcode( 'factfusion_fact_actions', array( __CLASS__, 'shortcode_fact_actions' ) );
		add_shortcode( 'factfusion_fact_related', array( __CLASS__, 'shortcode_fact_related' ) );
		add_shortcode( 'factfusion_fact_report', array( __CLASS__, 'shortcode_fact_report' ) );
		add_shortcode( 'factfusion_fact_embed', array( __CLASS__, 'shortcode_fact_embed' ) );
		add_shortcode( 'factfusion_browse', array( __CLASS__, 'shortcode_browse' ) );
		add_shortcode( 'factfusion_breadcrumbs', array( __CLASS__, 'shortcode_breadcrumbs' ) );
		add_shortcode( 'factfusion_faq', array( __CLASS__, 'shortcode_faq' ) );
	}

	/**
	 * Asks for the front-end script on this page. Used by shortcodes in other classes.
	 */
	public static function need_script() {
		self::$needs_script = true;
	}

	/**
	 * Visible breadcrumb trail. It uses the same list as the BreadcrumbList data.
	 *
	 * @return string
	 */
	public static function shortcode_breadcrumbs() {
		$trail = FactFusion_SEO::trail();
		if ( count( $trail ) < 2 ) {
			return '';
		}
		$last = count( $trail ) - 1;
		$html = sprintf( '<nav class="ff-breadcrumbs" aria-label="%s"><ol>', esc_attr__( 'Breadcrumb', 'factfusion-ai' ) );
		foreach ( $trail as $position => $crumb ) {
			$html .= $position === $last
				? sprintf( '<li aria-current="page">%s</li>', esc_html( $crumb[0] ) )
				: sprintf( '<li><a href="%s">%s</a></li>', esc_url( $crumb[1] ), esc_html( $crumb[0] ) );
		}
		return $html . '</ol></nav>';
	}

	/**
	 * Homepage questions and answers. The FAQPage data is built from the same list.
	 *
	 * @return string
	 */
	public static function shortcode_faq() {
		$faq = FactFusion_SEO::faq();
		if ( ! $faq ) {
			return '';
		}
		$html = '<div class="ff-faq">';
		foreach ( $faq as $pair ) {
			$html .= sprintf( '<details class="ff-faq__item"><summary>%s</summary><p>%s</p></details>', esc_html( $pair[0] ), esc_html( $pair[1] ) );
		}
		return $html . '</div>';
	}

	/**
	 * An editor's public profile: biography, LinkedIn link and the facts they verified.
	 *
	 * @param WP_User $profile Editor.
	 * @return string
	 */
	private static function profile( $profile ) {
		$bio      = trim( (string) get_user_meta( $profile->ID, 'description', true ) );
		$linkedin = FactFusion_Profiles::linkedin( $profile );
		$result   = FactFusion_Facts::query(
			array(
				'status'    => 'published',
				'author_id' => $profile->ID,
				'per_page'  => self::BROWSE_PER_PAGE,
				'orderby'   => 'fact_number',
				'order'     => 'desc',
			)
		);

		$html = '<div class="ff-profile">';
		if ( '' !== $bio ) {
			$html .= '<div class="ff-profile__bio">' . wpautop( esc_html( $bio ) ) . '</div>';
		}
		if ( '' !== $linkedin ) {
			/* translators: %s: person's name. */
			$html .= sprintf( '<p><a href="%1$s" rel="me noopener">%2$s</a></p>', esc_url( $linkedin ), esc_html( sprintf( __( '%s on LinkedIn', 'factfusion-ai' ), $profile->display_name ) ) );
		}
		$html .= '</div>';

		$html .= '<h2 class="wp-block-heading">' . esc_html__( 'Facts verified', 'factfusion-ai' ) . '</h2>';
		$html .= '<p class="ff-meta">' . esc_html(
			sprintf(
				/* translators: 1: person's name, 2: number of facts. */
				_n( '%1$s has checked %2$d published fact against its source.', '%1$s has checked %2$d published facts against their sources.', $result['total'], 'factfusion-ai' ),
				$profile->display_name,
				$result['total']
			)
		) . '</p>';
		if ( $result['items'] ) {
			$html .= '<ul class="ff-fact-list">';
			foreach ( $result['items'] as $item ) {
				$html .= sprintf( '<li class="ff-card"><a class="ff-fact-list__title" href="%1$s">%2$s</a><p>%3$s</p></li>', esc_url( FactFusion_Facts::url( $item ) ), esc_html( $item->title ), esc_html( $item->claim ) );
			}
			$html .= '</ul>';
			if ( $result['total'] > count( $result['items'] ) ) {
				$html .= sprintf( '<p><a href="%s">%s</a></p>', esc_url( FactFusion_Router::browse_url() ), esc_html__( 'Browse all facts', 'factfusion-ai' ) );
			}
		}
		return $html;
	}

	/**
	 * Registers the front-end script. It is printed only on pages that use it.
	 */
	public static function register_assets() {
		wp_register_script(
			'factfusion',
			FACTFUSION_URL . 'assets/factfusion.js',
			array(),
			FACTFUSION_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
	}

	/**
	 * Prints the script and its settings when a shortcode asked for it.
	 */
	public static function print_script() {
		if ( ! self::$needs_script ) {
			return;
		}
		$config = array(
			'rest'      => esc_url_raw( rest_url( 'factfusion/v1/' ) ),
			'factsUrl'  => esc_url_raw( home_url( user_trailingslashit( 'facts' ) ) ),
			'turnstile' => FactFusion_Settings::env( 'TURNSTILE_SITE_KEY' ),
			'text'      => array(
				'copied'      => __( 'Copied.', 'factfusion-ai' ),
				'linkCopied'  => __( 'Link copied.', 'factfusion-ai' ),
				'copyFailed'  => __( 'Copying is not available in this browser.', 'factfusion-ai' ),
				'saved'       => __( 'Saved on this device.', 'factfusion-ai' ),
				'removed'     => __( 'Removed from saved facts.', 'factfusion-ai' ),
				'save'        => __( 'Save', 'factfusion-ai' ),
				'savedLabel'  => __( 'Saved', 'factfusion-ai' ),
				'loading'     => __( 'Loading a fact…', 'factfusion-ai' ),
				'failed'      => __( 'That did not work. Please try again.', 'factfusion-ai' ),
				'limited'     => __( 'You have read a lot of facts this hour. Take a short break, or browse all facts.', 'factfusion-ai' ),
				'none'        => __( 'No facts in this category yet.', 'factfusion-ai' ),
				'source'      => __( 'Source', 'factfusion-ai' ),
				/* translators: %s: tier letter A to D. */
				'tier'        => __( 'Tier %s', 'factfusion-ai' ),
				'sending'     => __( 'Sending…', 'factfusion-ai' ),
				'reported'    => __( 'Thank you. Your report has been sent to the editor.', 'factfusion-ai' ),
				'noSaved'     => __( 'You have not saved any facts on this device yet.', 'factfusion-ai' ),
				/* translators: %d: number of search suggestions. */
				'suggestions' => __( '%d suggestions. Use the arrow keys to choose.', 'factfusion-ai' ),
				'noResults'   => __( 'No matching facts.', 'factfusion-ai' ),
			),
		);
		wp_add_inline_script( 'factfusion', 'window.factfusion = ' . wp_json_encode( $config ) . ';', 'before' );
		wp_enqueue_script( 'factfusion' );
	}

	/**
	 * Forgets cached picks when facts or settings change, and asks page caches to refresh.
	 */
	public static function flush_caches() {
		delete_transient( 'factfusion_fotd_' . wp_date( 'Ymd' ) );
		delete_transient( FactFusion_Facts::POOL_TRANSIENT );
		// LiteSpeed Cache listens for this. It does nothing when that plugin is absent.
		do_action( 'litespeed_purge_all' );
	}

	/**
	 * Tells page caches not to store this response (random or personal output).
	 */
	public static function no_cache() {
		do_action( 'litespeed_control_set_nocache', 'factfusion: varies per request' );
		if ( ! headers_sent() ) {
			nocache_headers();
			header( 'X-LiteSpeed-Cache-Control: no-cache' );
		}
	}

	/**
	 * Tells page caches to drop this page at the next local midnight, when the
	 * Fact of the Day changes.
	 */
	public static function cache_until_midnight() {
		$now     = current_datetime();
		$seconds = max( 60, $now->modify( 'tomorrow' )->getTimestamp() - $now->getTimestamp() );
		do_action( 'litespeed_control_set_ttl', $seconds, 'factfusion: fact of the day' );
	}

	/**
	 * Today's fact: the pinned one, or a daily rotation through published facts.
	 *
	 * @return object|null
	 */
	public static function fact_of_the_day() {
		$pin = (int) FactFusion_Settings::get( 'fotd_pin' );
		if ( $pin ) {
			$fact = FactFusion_Facts::get( $pin );
			if ( $fact && 'published' === $fact->status ) {
				return $fact;
			}
		}

		$key  = 'factfusion_fotd_' . wp_date( 'Ymd' );
		$id   = (int) get_transient( $key );
		$fact = $id ? FactFusion_Facts::get( $id ) : null;
		if ( $fact && 'published' === $fact->status ) {
			return $fact;
		}

		$ids = array_keys( FactFusion_Facts::pool() );
		if ( ! $ids ) {
			return null;
		}
		$now = current_datetime();
		$day = (int) floor( ( $now->getTimestamp() + $now->getOffset() ) / DAY_IN_SECONDS );
		$id  = $ids[ $day % count( $ids ) ];
		set_transient( $key, $id, 12 * HOUR_IN_SECONDS );
		return FactFusion_Facts::get( $id );
	}

	/**
	 * The small data set the script needs to copy, share and save a fact.
	 *
	 * @param object $fact Fact row.
	 * @return array
	 */
	private static function card_data( $fact ) {
		$data   = FactFusion_Facts::public_data( $fact, true );
		$source = $data['sources'] ? $data['sources'][0] : null;
		return array(
			'id'            => $data['id'],
			'title'         => $data['title'],
			'claim'         => $data['claim'],
			'url'           => $data['url'],
			'category'      => $data['category'],
			'source'        => $source,
			'verified_date' => $data['verified_date'],
			'confidence'    => $data['confidence'],
		);
	}

	/**
	 * A date such as "Oct 3, 2026". The script writes dates the same way.
	 *
	 * @param string $ymd Date as Y-m-d.
	 * @return string
	 */
	private static function short_date( $ymd ) {
		return wp_date( 'M j, Y', strtotime( $ymd . ' 12:00:00 UTC' ), new DateTimeZone( 'UTC' ) );
	}

	/**
	 * A fact card: category, a verified badge, claim, source, tier, date
	 * verified, confidence and a link to the full page.
	 *
	 * @param object $fact Fact row.
	 * @return string
	 */
	private static function card( $fact ) {
		$data     = self::card_data( $fact );
		$verified = (string) $data['verified_date'];
		$html     = sprintf( '<div class="ff-card ff-fact-card" data-ff-card data-ff-fact="%s" aria-live="polite" aria-atomic="true">', esc_attr( wp_json_encode( $data ) ) );
		$html    .= '<div class="ff-fact-card__top">';
		$html    .= sprintf( '<p class="ff-pill" data-ff-field="category">%s</p>', esc_html( $data['category'] ? $data['category']['name'] : __( 'Facts', 'factfusion-ai' ) ) );
		$html    .= sprintf(
			'<p class="ff-pill ff-pill--verified" data-ff-field="badge"%1$s><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M20 6 9 17l-5-5"/></svg>%2$s</p>',
			'' === $verified ? ' hidden' : '',
			esc_html__( 'Verified by an editor', 'factfusion-ai' )
		);
		$html .= '</div>';
		$html .= sprintf( '<p class="ff-fact__claim" data-ff-field="claim">%s</p>', esc_html( $data['claim'] ) );
		$html .= '<p class="ff-meta ff-fact-card__meta">';
		if ( $data['source'] ) {
			$html .= sprintf(
				'<span>%1$s: <a data-ff-field="source" href="%2$s">%3$s</a> <span class="ff-pill ff-tier" data-ff-field="tier">%4$s</span></span> ',
				esc_html__( 'Source', 'factfusion-ai' ),
				esc_url( $data['source']['url'] ),
				esc_html( $data['source']['name'] ),
				/* translators: %s: tier letter A to D. */
				esc_html( sprintf( __( 'Tier %s', 'factfusion-ai' ), $data['source']['tier'] ) )
			);
		}
		$html .= sprintf(
			'<span data-ff-field="verified"%1$s>%2$s <time datetime="%3$s">%4$s</time></span> ',
			'' === $verified ? ' hidden' : '',
			esc_html__( 'Verified', 'factfusion-ai' ),
			esc_attr( $verified ),
			esc_html( '' === $verified ? '' : self::short_date( $verified ) )
		);
		$html .= sprintf(
			'<span data-ff-field="confidence">%s</span> ',
			/* translators: %d: confidence score out of 100. */
			esc_html( sprintf( __( 'Confidence %d/100', 'factfusion-ai' ), $data['confidence'] ) )
		);
		$html .= sprintf( '<a data-ff-field="link" href="%s">%s</a>', esc_url( $data['url'] ), esc_html__( 'Read the explanation', 'factfusion-ai' ) );
		$html .= '</p></div>';
		return $html;
	}

	/**
	 * Copy, share and save buttons. They appear only when the script runs.
	 *
	 * @return string
	 */
	private static function action_buttons() {
		return sprintf(
			'<button type="button" class="ff-button ff-needs-js" data-ff-copy>%1$s</button><button type="button" class="ff-button ff-needs-js" data-ff-share>%2$s</button><button type="button" class="ff-button ff-needs-js" data-ff-save aria-pressed="false">%3$s</button>',
			esc_html__( 'Copy', 'factfusion-ai' ),
			esc_html__( 'Share', 'factfusion-ai' ),
			esc_html__( 'Save', 'factfusion-ai' )
		);
	}

	/**
	 * URL that redirects to a random published fact.
	 *
	 * @param int    $from     Fact to avoid.
	 * @param string $category Category slug to stay within.
	 * @return string
	 */
	public static function random_url( $from = 0, $category = '' ) {
		$url = home_url( user_trailingslashit( 'random-fact' ) );
		if ( '' !== $category ) {
			$url = add_query_arg( 'category', $category, $url );
		}
		return $from ? add_query_arg( 'from', (int) $from, $url ) : $url;
	}

	/**
	 * The generator: one fact from the database, category chips and actions.
	 * With category="slug" it stays within that category and shows no chips.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public static function shortcode_generator( $atts = array() ) {
		$atts   = shortcode_atts(
			array(
				'category' => '',
				'layout'   => '',
			),
			$atts,
			'factfusion_generator'
		);
		$locked = '' !== $atts['category'] ? FactFusion_Categories::get_by_slug( sanitize_title( $atts['category'] ) ) : null;
		if ( $locked && ! $locked->is_active ) {
			$locked = null;
		}
		$fact = FactFusion_Facts::random_published( $locked ? (int) $locked->id : 0 );
		if ( ! $fact ) {
			return '<div class="ff-card"><p class="ff-meta">' . esc_html__( 'Facts are being verified and will appear here soon.', 'factfusion-ai' ) . '</p></div>';
		}
		self::$needs_script = true;

		// The homepage hero puts the category row above the card, in one
		// scrolling line, so the card stays on the first screen of a phone.
		$hero  = 'hero' === $atts['layout'] && ! $locked;
		$chips = '';
		if ( ! $locked ) {
			$counts = FactFusion_Facts::published_counts();
			$chips  = sprintf( '<div class="ff-chips ff-needs-js" role="group" aria-label="%s" data-ff-chips>', esc_attr__( 'Choose a category', 'factfusion-ai' ) );
			$chips .= sprintf( '<button type="button" class="ff-chip" aria-pressed="true" data-ff-category="">%s</button>', esc_html( $hero ? __( 'All subjects', 'factfusion-ai' ) : __( 'All', 'factfusion-ai' ) ) );
			foreach ( FactFusion_Categories::all( true ) as $category ) {
				if ( empty( $counts[ (int) $category->id ] ) ) {
					continue;
				}
				$chips .= sprintf( '<button type="button" class="ff-chip" aria-pressed="false" data-ff-category="%s">%s</button>', esc_attr( $category->slug ), esc_html( $hero ? $category->name : trim( $category->icon . ' ' . $category->name ) ) );
			}
			$chips .= '</div>';
		}

		$next_icon = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M21 12a9 9 0 1 1-3-6.7"/><path d="M21 4v5h-5"/></svg>';

		// Otherwise the fact comes first so it is on screen at once, even on a phone.
		$html  = sprintf( '<div class="ff-generator%1$s" data-ff-generator data-ff-locked="%2$s">', $hero ? ' ff-generator--hero' : '', esc_attr( $locked ? $locked->slug : '' ) );
		$html .= $hero ? $chips : '';
		$html .= self::card( $fact );
		$html .= '<div class="ff-actions">';
		$html .= sprintf( '<a class="wp-element-button ff-button--primary" data-ff-next href="%1$s" rel="nofollow">%2$s%3$s</a>', esc_url( self::random_url( 0, $locked ? $locked->slug : '' ) ), $hero ? $next_icon : '', esc_html__( 'New fact', 'factfusion-ai' ) );
		$html .= self::action_buttons();
		$html .= '</div>';
		$html .= '<p class="ff-status" role="status" data-ff-status></p>';
		$html .= $hero ? '' : $chips;
		$html .= '<div class="ff-generator__foot">';
		$html .= sprintf( '<p class="ff-meta ff-generator__hint ff-needs-js" data-ff-hint>%s</p>', esc_html__( 'Tip: press the Space bar for a new fact.', 'factfusion-ai' ) );
		if ( $hero ) {
			/* translators: %s: number of facts, filled in by the script. */
			$html .= sprintf( '<p class="ff-meta ff-generator__count ff-needs-js" data-ff-count data-ff-count-text="%s"></p>', esc_attr__( 'Facts read this visit: %s', 'factfusion-ai' ) );
		}
		$html .= '</div></div>';
		return $html;
	}

	/**
	 * Fact of the Day card.
	 *
	 * @return string
	 */
	public static function shortcode_fotd() {
		$fact = self::fact_of_the_day();
		if ( ! $fact ) {
			return '<div class="ff-card"><p class="ff-meta">' . esc_html__( 'A new verified fact will be featured here every day.', 'factfusion-ai' ) . '</p></div>';
		}
		self::$needs_script = true;
		self::cache_until_midnight();

		$html  = '<div class="ff-fotd" data-ff-actions>';
		$html .= self::card( $fact );
		$html .= '<div class="ff-actions">';
		$html .= sprintf( '<a class="ff-button" href="%s">%s</a>', esc_url( home_url( user_trailingslashit( 'fact-of-the-day' ) ) ), esc_html__( 'Open Fact of the Day', 'factfusion-ai' ) );
		$html .= self::action_buttons();
		$html .= '</div><p class="ff-status" role="status" data-ff-status></p></div>';
		return $html;
	}

	/**
	 * Copy, share, save and "Next Random Fact" on a fact page.
	 *
	 * @return string
	 */
	public static function shortcode_fact_actions() {
		$fact = FactFusion_Router::current_fact();
		if ( ! $fact ) {
			return '';
		}
		self::$needs_script = true;

		$html  = sprintf( '<div class="ff-fact-actions" data-ff-actions data-ff-fact="%s">', esc_attr( wp_json_encode( self::card_data( $fact ) ) ) );
		$html .= '<div class="ff-actions">';
		$html .= sprintf( '<a class="wp-element-button ff-button--primary" href="%s" rel="nofollow">%s</a>', esc_url( self::random_url( $fact->id ) ), esc_html__( 'Next Random Fact', 'factfusion-ai' ) );
		$html .= self::action_buttons();
		$html .= '</div><p class="ff-status" role="status" data-ff-status></p></div>';
		return $html;
	}

	/**
	 * Three other facts from the same category.
	 *
	 * @return string
	 */
	public static function shortcode_fact_related() {
		$fact = FactFusion_Router::current_fact();
		if ( ! $fact ) {
			return '';
		}
		$related = FactFusion_Facts::related( $fact, 3 );
		if ( ! $related ) {
			return '';
		}
		$html = '<section class="ff-related"><h2 class="wp-block-heading">' . esc_html__( 'Related facts', 'factfusion-ai' ) . '</h2><ul class="ff-fact-list">';
		foreach ( $related as $item ) {
			$html .= sprintf(
				'<li class="ff-card"><a class="ff-fact-list__title" href="%1$s">%2$s</a><p>%3$s</p></li>',
				esc_url( FactFusion_Facts::url( $item ) ),
				esc_html( $item->title ),
				esc_html( $item->claim )
			);
		}
		$html .= '</ul></section>';
		return $html;
	}

	/**
	 * "Report a problem" form on a fact page.
	 *
	 * @return string
	 */
	public static function shortcode_fact_report() {
		$fact = FactFusion_Router::current_fact();
		if ( ! $fact || 'published' !== $fact->status ) {
			return '';
		}
		self::$needs_script = true;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display flag only.
		$sent = isset( $_GET['reported'] );
		$html = '<details class="ff-report"' . ( $sent ? ' open' : '' ) . '><summary>' . esc_html__( 'Report a problem with this fact', 'factfusion-ai' ) . '</summary>';
		if ( $sent ) {
			$html .= '<p class="ff-status" role="status">' . esc_html__( 'Thank you. Your report has been sent to the editor.', 'factfusion-ai' ) . '</p>';
		}
		$html .= sprintf( '<form class="ff-form" method="post" action="%s" data-ff-report>', esc_url( rest_url( 'factfusion/v1/report' ) ) );
		$html .= sprintf( '<input type="hidden" name="fact_id" value="%d"><input type="hidden" name="redirect" value="1">', (int) $fact->id );
		$html .= sprintf( '<p><label for="ff-report-wrong">%s</label><textarea id="ff-report-wrong" name="what_is_wrong" rows="4" required minlength="10" maxlength="2000"></textarea></p>', esc_html__( 'What is wrong?', 'factfusion-ai' ) );
		$html .= sprintf( '<p><label for="ff-report-fix">%s</label><textarea id="ff-report-fix" name="suggested_correction" rows="3" maxlength="2000"></textarea></p>', esc_html__( 'What should it say instead? (optional)', 'factfusion-ai' ) );
		$html .= sprintf( '<p><label for="ff-report-source">%s</label><input type="text" id="ff-report-source" name="supporting_source" maxlength="500"></p>', esc_html__( 'A source that supports your correction (optional)', 'factfusion-ai' ) );
		$html .= sprintf( '<p><label for="ff-report-email">%1$s</label><input type="email" id="ff-report-email" name="reporter_email" maxlength="191" autocomplete="email" aria-describedby="ff-report-email-note"><span class="ff-meta" id="ff-report-email-note">%2$s</span></p>', esc_html__( 'Your email (optional)', 'factfusion-ai' ), esc_html__( 'Used only if the editor needs to ask you about this report.', 'factfusion-ai' ) );
		// Honeypot: people never see or fill this field.
		$html .= '<p class="ff-form__trap" aria-hidden="true"><label for="ff-report-website">Website</label><input type="text" id="ff-report-website" name="website" tabindex="-1" autocomplete="off"></p>';
		if ( '' !== FactFusion_Settings::env( 'TURNSTILE_SITE_KEY' ) ) {
			$html .= '<div data-ff-turnstile></div>';
		}
		$html .= sprintf( '<p><button type="submit" class="wp-element-button">%s</button></p>', esc_html__( 'Send report', 'factfusion-ai' ) );
		$html .= '<p class="ff-status" role="status" data-ff-status></p></form></details>';
		return $html;
	}

	/**
	 * The HTML a site owner pastes to embed a fact. The visible link sits on
	 * their page, outside the frame, with no rel="nofollow".
	 *
	 * @param object|null $fact     A specific fact, or null for a random one.
	 * @param object|null $category For a random fact: a category row to stay within.
	 * @return string
	 */
	public static function embed_snippet( $fact = null, $category = null ) {
		if ( $fact ) {
			$src   = home_url( user_trailingslashit( 'embed/fact/' . $fact->slug ) );
			$link  = FactFusion_Facts::url( $fact );
			$label = $fact->title . ' – FactFusion AI';
		} elseif ( $category ) {
			$src   = add_query_arg( 'category', $category->slug, home_url( user_trailingslashit( 'embed/random' ) ) );
			$link  = FactFusion_Router::browse_url( $category );
			/* translators: %s: category name. */
			$label = sprintf( __( '%s facts by FactFusion AI', 'factfusion-ai' ), $category->name );
		} else {
			$src   = home_url( user_trailingslashit( 'embed/random' ) );
			$link  = home_url( '/' );
			$label = __( 'Random fact generator by FactFusion AI', 'factfusion-ai' );
		}
		return sprintf(
			'<iframe src="%1$s" title="%2$s" width="100%%" height="240" style="border:0;max-width:560px" loading="lazy"></iframe>' . "\n" . '<p><a href="%3$s">%4$s</a></p>',
			esc_url( $src ),
			esc_attr__( 'Fact from FactFusion AI', 'factfusion-ai' ),
			esc_url( $link ),
			esc_html( $label )
		);
	}

	/**
	 * "Embed this fact" box on a fact page.
	 *
	 * @return string
	 */
	public static function shortcode_fact_embed() {
		$fact = FactFusion_Router::current_fact();
		if ( ! $fact || 'published' !== $fact->status ) {
			return '';
		}
		self::$needs_script = true;

		$html  = '<details class="ff-embed-box"><summary>' . esc_html__( 'Embed this fact', 'factfusion-ai' ) . '</summary>';
		$html .= '<p><label for="ff-embed-code">' . esc_html__( 'Paste this code into your page. It is free to use; please keep the link.', 'factfusion-ai' ) . '</label></p>';
		$html .= sprintf( '<textarea id="ff-embed-code" class="ff-embed-code" rows="5" readonly data-ff-embed-code>%s</textarea>', esc_textarea( self::embed_snippet( $fact ) ) );
		$html .= sprintf( '<p><button type="button" class="ff-button ff-needs-js" data-ff-embed-copy>%s</button> <span class="ff-status" role="status" data-ff-status></span></p>', esc_html__( 'Copy code', 'factfusion-ai' ) );
		$html .= '</details>';
		return $html;
	}

	/**
	 * A fact as a self-contained HTML fragment for other sites, with its own
	 * styles, a source link and an attribution link back to this site.
	 *
	 * @param object|null $fact Fact row, or null when none is available.
	 * @return string
	 */
	public static function embed_fragment( $fact ) {
		$css  = '.factfusion-embed{box-sizing:border-box;margin:0;padding:16px 18px;border:1px solid #E5E7EB;border-radius:12px;background:#FFF;color:#111827;font:16px/1.6 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif}';
		$css .= '.factfusion-embed p{margin:0 0 10px}.factfusion-embed .factfusion-embed__claim{font-size:18px;font-weight:600;line-height:1.5}';
		$css .= '.factfusion-embed .factfusion-embed__meta{margin:0;color:#4B5563;font-size:14px}.factfusion-embed a{color:#2563EB}';
		$css .= '@media (prefers-color-scheme:dark){.factfusion-embed{border-color:#334155;background:#0F172A;color:#F1F5F9}.factfusion-embed .factfusion-embed__meta{color:#94A3B8}.factfusion-embed a{color:#60A5FA}}';

		$html = '<div class="factfusion-embed"><style>' . $css . '</style>';
		if ( ! $fact ) {
			$html .= '<p class="factfusion-embed__meta">' . sprintf(
				/* translators: %s: link to the site. */
				esc_html__( 'No fact is available right now. Visit %s.', 'factfusion-ai' ),
				'<a href="' . esc_url( home_url( '/' ) ) . '" target="_blank" rel="noopener">FactFusion AI</a>'
			) . '</p></div>';
			return $html;
		}

		$sources = FactFusion_Sources::for_fact( $fact->id );
		$html   .= '<p class="factfusion-embed__claim">' . esc_html( $fact->claim ) . '</p>';
		$html   .= '<p class="factfusion-embed__meta">';
		if ( $sources ) {
			$html .= sprintf( '%1$s: <a href="%2$s" target="_blank" rel="noopener">%3$s</a> · ', esc_html__( 'Source', 'factfusion-ai' ), esc_url( $sources[0]->url ), esc_html( $sources[0]->source_name ) );
		}
		$html .= sprintf( '<a href="%1$s" target="_blank" rel="noopener">%2$s</a>', esc_url( FactFusion_Facts::url( $fact ) ), esc_html__( 'Verified by FactFusion AI', 'factfusion-ai' ) );
		$html .= '</p></div>';
		return $html;
	}

	/**
	 * Sends a complete, tiny HTML page for use inside an iframe, then stops.
	 *
	 * @param object|null $fact   Fact row.
	 * @param bool        $random Whether the fact changes on every load.
	 */
	public static function send_embed_document( $fact, $random ) {
		if ( $random ) {
			self::no_cache();
		}
		status_header( 200 );
		header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) );
		header( 'X-Robots-Tag: noindex' );
		$title = $fact ? $fact->title : get_bloginfo( 'name' );
		echo '<!doctype html><html lang="' . esc_attr( get_bloginfo( 'language' ) ) . '"><head><meta charset="' . esc_attr( get_option( 'blog_charset' ) ) . '">';
		echo '<meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex"><meta name="color-scheme" content="light dark">';
		echo '<title>' . esc_html( $title ) . '</title><style>html,body{margin:0;padding:0;background:transparent}</style></head><body>';
		echo self::embed_fragment( $fact ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in embed_fragment().
		echo '</body></html>';
		exit;
	}

	/**
	 * Top of a category landing page: the editor's introduction and the facts
	 * chosen to start with. Both are optional and print nothing when empty.
	 *
	 * @param object $category Category row.
	 * @return string
	 */
	private static function category_lead( $category ) {
		$html  = '';
		$intro = trim( (string) ( $category->intro ?? '' ) );
		if ( '' !== $intro ) {
			$html .= '<div class="ff-category-intro">' . wpautop( esc_html( $intro ) ) . '</div>';
		}
		$featured = FactFusion_Categories::featured_facts( $category );
		if ( $featured ) {
			/* translators: %s: category name. */
			$html .= '<section class="ff-featured"><h2 class="wp-block-heading">' . esc_html( sprintf( __( 'Start with these %s facts', 'factfusion-ai' ), strtolower( $category->name ) ) ) . '</h2><ul class="ff-fact-list">';
			foreach ( $featured as $item ) {
				$sources = FactFusion_Sources::for_fact( $item->id );
				$html   .= sprintf(
					'<li class="ff-card"><a class="ff-fact-list__title" href="%1$s">%2$s</a><p>%3$s</p>%4$s</li>',
					esc_url( FactFusion_Facts::url( $item ) ),
					esc_html( $item->title ),
					esc_html( $item->claim ),
					$sources ? sprintf( '<p class="ff-meta">%1$s: <a href="%2$s">%3$s</a></p>', esc_html__( 'Source', 'factfusion-ai' ), esc_url( $sources[0]->url ), esc_html( $sources[0]->source_name ) ) : ''
				);
			}
			/* translators: %s: category name. */
			$html .= '</ul></section><h2 class="wp-block-heading ff-browse__all">' . esc_html( sprintf( __( 'All %s facts', 'factfusion-ai' ), strtolower( $category->name ) ) ) . '</h2>';
		}
		return $html;
	}

	/**
	 * Bottom of a category landing page: questions and answers, and links to
	 * neighbouring categories so that every category page is linked from others.
	 *
	 * @param object             $category Category row.
	 * @param array<int,int>     $counts   Published facts per category ID.
	 * @return string
	 */
	private static function category_tail( $category, $counts ) {
		$html = '';
		$faq  = FactFusion_Categories::faq( $category );
		if ( $faq ) {
			$html .= '<section class="ff-section"><h2 class="wp-block-heading">' . esc_html__( 'Questions and answers', 'factfusion-ai' ) . '</h2><div class="ff-faq">';
			foreach ( $faq as $pair ) {
				$html .= sprintf( '<details class="ff-faq__item"><summary>%s</summary><p>%s</p></details>', esc_html( $pair[0] ), esc_html( $pair[1] ) );
			}
			$html .= '</div></section>';
		}

		// The next categories in display order, wrapping round, so each one links four others.
		$others = array_values(
			array_filter(
				FactFusion_Categories::all( true ),
				static function ( $row ) use ( $counts ) {
					return ! empty( $counts[ (int) $row->id ] );
				}
			)
		);
		$at     = 0;
		foreach ( $others as $index => $row ) {
			if ( (int) $row->id === (int) $category->id ) {
				$at = $index;
			}
		}
		$links = array();
		for ( $step = 1; $step < count( $others ) && count( $links ) < 4; $step++ ) {
			$row     = $others[ ( $at + $step ) % count( $others ) ];
			$links[] = sprintf( '<li><a href="%s">%s</a></li>', esc_url( FactFusion_Router::browse_url( $row ) ), esc_html( trim( $row->icon . ' ' . sprintf( /* translators: %s: category name. */ __( '%s facts', 'factfusion-ai' ), $row->name ) ) ) );
		}
		if ( $links ) {
			$html .= '<section class="ff-section ff-related-categories"><h2 class="wp-block-heading">' . esc_html__( 'More categories', 'factfusion-ai' ) . '</h2><ul class="ff-guides__list">' . implode( '', $links ) . '</ul>';
			$html .= sprintf( '<p><a href="%s">%s</a></p></section>', esc_url( FactFusion_Router::browse_url() ), esc_html__( 'Browse all facts', 'factfusion-ai' ) );
		}
		return $html;
	}

	/**
	 * The /facts/ page: search, category links, a paged list and saved facts.
	 *
	 * @return string
	 */
	public static function shortcode_browse() {
		if ( 'author' === FactFusion_Router::view() ) {
			return self::profile( FactFusion_Router::profile() );
		}
		$view = FactFusion_Router::browse();
		if ( ! $view ) {
			return '';
		}
		self::$needs_script = true;

		$base     = home_url( user_trailingslashit( 'facts' ) );
		$search   = $view['search'];
		$category = $view['category'];
		$page     = $view['page'];
		$counts   = FactFusion_Facts::published_counts();
		$names    = array();
		foreach ( FactFusion_Categories::all() as $row ) {
			$names[ (int) $row->id ] = $row->name;
		}

		if ( '' !== $search ) {
			$result = FactFusion_Facts::search_published( $search, self::BROWSE_PER_PAGE, ( $page - 1 ) * self::BROWSE_PER_PAGE );
		} else {
			$result = FactFusion_Facts::query(
				array(
					'status'      => 'published',
					'category_id' => $category ? (int) $category->id : 0,
					'paged'       => $page,
					'per_page'    => self::BROWSE_PER_PAGE,
					'orderby'     => 'fact_number',
					'order'       => 'desc',
				)
			);
		}

		// The first page of a category is its landing page: intro and chosen facts first.
		$landing = $category && 1 === $page && '' === $search;
		$html    = $landing ? self::category_lead( $category ) : '';

		$html .= sprintf( '<form class="ff-search" role="search" method="get" action="%s">', esc_url( $base ) );
		$html .= sprintf( '<label class="screen-reader-text" for="ff-q">%s</label>', esc_html__( 'Search facts', 'factfusion-ai' ) );
		$html .= '<div class="ff-combobox">';
		$html .= sprintf( '<input type="search" id="ff-q" name="q" value="%1$s" placeholder="%2$s" maxlength="100" autocomplete="off" data-ff-search>', esc_attr( $search ), esc_attr__( 'Search facts', 'factfusion-ai' ) );
		$html .= '<ul class="ff-suggest" id="ff-suggest" role="listbox" hidden></ul></div>';
		$html .= sprintf( '<button type="submit" class="wp-element-button">%s</button>', esc_html__( 'Search', 'factfusion-ai' ) );
		$html .= '<p class="screen-reader-text" role="status" data-ff-suggest-status></p></form>';

		$html .= sprintf( '<nav class="ff-chips" aria-label="%s">', esc_attr__( 'Categories', 'factfusion-ai' ) );
		$html .= sprintf( '<a class="ff-chip" href="%1$s"%2$s>%3$s</a>', esc_url( $base ), ! $category && '' === $search ? ' aria-current="page"' : '', esc_html__( 'All', 'factfusion-ai' ) );
		foreach ( FactFusion_Categories::all( true ) as $row ) {
			if ( empty( $counts[ (int) $row->id ] ) ) {
				continue;
			}
			$html .= sprintf(
				'<a class="ff-chip" href="%1$s"%2$s>%3$s</a>',
				esc_url( home_url( user_trailingslashit( 'facts/' . $row->slug ) ) ),
				$category && (int) $category->id === (int) $row->id ? ' aria-current="page"' : '',
				esc_html( trim( $row->icon . ' ' . $row->name ) )
			);
		}
		$html .= '</nav>';

		/* translators: %d: number of facts. */
		$html .= '<p class="ff-meta">' . esc_html( sprintf( _n( '%d fact', '%d facts', $result['total'], 'factfusion-ai' ), $result['total'] ) ) . '</p>';

		if ( ! $result['items'] ) {
			$html .= '<p>' . esc_html__( 'No facts matched. Try a different word, or browse a category.', 'factfusion-ai' ) . '</p>';
		} else {
			$html .= '<ul class="ff-fact-list">';
			foreach ( $result['items'] as $item ) {
				$html .= sprintf(
					'<li class="ff-card"><h2 class="ff-fact-list__heading"><a class="ff-fact-list__title" href="%1$s">%2$s</a></h2><p>%3$s</p><p class="ff-meta">%4$s</p></li>',
					esc_url( FactFusion_Facts::url( $item ) ),
					esc_html( $item->title ),
					esc_html( $item->claim ),
					esc_html( $names[ (int) $item->category_id ] ?? '' )
				);
			}
			$html .= '</ul>';
		}

		$pages = (int) ceil( $result['total'] / self::BROWSE_PER_PAGE );
		if ( $pages > 1 ) {
			$html .= sprintf( '<nav class="ff-pagination" aria-label="%s">', esc_attr__( 'Pages', 'factfusion-ai' ) );
			if ( $page > 1 ) {
				$html .= sprintf( '<a class="ff-button" rel="prev" href="%s">%s</a>', esc_url( FactFusion_Router::browse_url( $category, $page - 1, $search ) ), esc_html__( 'Previous', 'factfusion-ai' ) );
			}
			/* translators: 1: current page, 2: total pages. */
			$html .= '<span class="ff-meta">' . esc_html( sprintf( __( 'Page %1$d of %2$d', 'factfusion-ai' ), $page, $pages ) ) . '</span>';
			if ( $page < $pages ) {
				$html .= sprintf( '<a class="ff-button" rel="next" href="%s">%s</a>', esc_url( FactFusion_Router::browse_url( $category, $page + 1, $search ) ), esc_html__( 'Next', 'factfusion-ai' ) );
			}
			$html .= '</nav>';
		}

		if ( $landing ) {
			$html .= self::category_tail( $category, $counts );
		}

		$html .= '<section class="ff-saved" data-ff-saved hidden><h2 class="wp-block-heading">' . esc_html__( 'Your saved facts', 'factfusion-ai' ) . '</h2>';
		$html .= '<p class="ff-meta">' . esc_html__( 'Saved facts are kept in this browser only.', 'factfusion-ai' ) . '</p><ul class="ff-fact-list" data-ff-saved-list></ul></section>';

		return $html;
	}
}
