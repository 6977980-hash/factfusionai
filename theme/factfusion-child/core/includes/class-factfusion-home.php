<?php
/**
 * Homepage sections and footer links.
 *
 * @package FactFusion_AI_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The parts of the homepage around the generator: figures, sources, the
 * category grid, the quiz, newly verified facts, how facts are checked and
 * the embed code. Every number, fact and source name comes from the fact
 * tables; nothing on the homepage is typed in as data.
 */
final class FactFusion_Home {

	const STATS_TRANSIENT = 'factfusion_home_stats';

	const FOOTER_TRANSIENT = 'factfusion_footer_links';

	/**
	 * Registers hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_shortcodes' ) );
		add_action( 'factfusion_facts_changed', array( __CLASS__, 'flush' ) );
		add_action( 'save_post_page', array( __CLASS__, 'flush' ) );
		add_action( 'deleted_post', array( __CLASS__, 'flush' ) );
	}

	/**
	 * Registers the shortcodes used by the theme's homepage and footer.
	 */
	public static function register_shortcodes() {
		add_shortcode( 'factfusion_home_stats', array( __CLASS__, 'shortcode_stats' ) );
		add_shortcode( 'factfusion_home_sources', array( __CLASS__, 'shortcode_sources' ) );
		add_shortcode( 'factfusion_home_categories', array( __CLASS__, 'shortcode_categories' ) );
		add_shortcode( 'factfusion_home_quiz', array( __CLASS__, 'shortcode_quiz' ) );
		add_shortcode( 'factfusion_home_latest', array( __CLASS__, 'shortcode_latest' ) );
		add_shortcode( 'factfusion_home_steps', array( __CLASS__, 'shortcode_steps' ) );
		add_shortcode( 'factfusion_home_embed', array( __CLASS__, 'shortcode_embed' ) );
		add_shortcode( 'factfusion_footer_links', array( __CLASS__, 'shortcode_footer_links' ) );
	}

	/**
	 * Forgets the cached figures and footer links.
	 */
	public static function flush() {
		delete_transient( self::STATS_TRANSIENT );
		delete_transient( self::FOOTER_TRANSIENT );
	}

	/**
	 * Site figures: verified facts, sources cited, categories with facts, and
	 * the sources cited most often.
	 *
	 * @return array{facts:int,sources:int,categories:int,top:string[]}
	 */
	public static function stats() {
		$stats = get_transient( self::STATS_TRANSIENT );
		if ( is_array( $stats ) ) {
			return $stats;
		}
		global $wpdb;
		$facts = FactFusion_DB::table( 'facts' );
		$links = FactFusion_DB::table( 'fact_sources' );
		$srcs  = FactFusion_DB::table( 'sources' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$sources = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(DISTINCT l.source_id) FROM %i l INNER JOIN %i f ON f.id = l.fact_id WHERE f.status = %s',
				$links,
				$facts,
				'published'
			)
		);
		$top     = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT s.source_name FROM %i l INNER JOIN %i f ON f.id = l.fact_id INNER JOIN %i s ON s.id = l.source_id WHERE f.status = %s AND s.source_name <> %s GROUP BY s.source_name ORDER BY COUNT(*) DESC, s.source_name ASC LIMIT 7',
				$links,
				$facts,
				$srcs,
				'published',
				''
			)
		);
		// phpcs:enable

		$stats = array(
			'facts'      => FactFusion_Facts::live_verified_count(),
			'sources'    => $sources,
			'categories' => count( FactFusion_Facts::published_counts() ),
			'top'        => array_map( 'strval', (array) $top ),
		);
		set_transient( self::STATS_TRANSIENT, $stats, 12 * HOUR_IN_SECONDS );
		return $stats;
	}

	/**
	 * The figures under the homepage headline.
	 *
	 * @return string
	 */
	public static function shortcode_stats() {
		$stats = self::stats();
		if ( ! $stats['facts'] ) {
			return '';
		}
		$items = array(
			array( number_format_i18n( $stats['facts'] ), __( 'verified facts', 'factfusion-ai' ) ),
			array( number_format_i18n( $stats['sources'] ), __( 'sources cited', 'factfusion-ai' ) ),
			array( number_format_i18n( $stats['categories'] ), __( 'subjects', 'factfusion-ai' ) ),
			array( __( 'Free', 'factfusion-ai' ), __( 'no sign-up', 'factfusion-ai' ) ),
		);
		$html = '<ul class="ff-stats">';
		foreach ( $items as $item ) {
			$html .= sprintf( '<li><strong>%1$s</strong> <span>%2$s</span></li>', esc_html( $item[0] ), esc_html( $item[1] ) );
		}
		return $html . '</ul>';
	}

	/**
	 * A strip naming the sources the facts are checked against most often.
	 *
	 * @return string
	 */
	public static function shortcode_sources() {
		$stats = self::stats();
		if ( ! $stats['top'] ) {
			return '';
		}
		$html = sprintf( '<section class="ff-sources" aria-label="%s"><div class="ff-wrap ff-sources__inner">', esc_attr__( 'Sources we check against', 'factfusion-ai' ) );
		$html .= '<p class="ff-sources__label">' . esc_html__( 'Checked against', 'factfusion-ai' ) . '</p><ul class="ff-sources__list">';
		foreach ( $stats['top'] as $name ) {
			$html .= '<li>' . esc_html( $name ) . '</li>';
		}
		$html .= '</ul>';
		$html .= sprintf( '<a class="ff-sources__more" href="%s">%s</a>', esc_url( home_url( user_trailingslashit( 'how-we-verify-facts' ) ) ), esc_html__( 'How we verify facts', 'factfusion-ai' ) );
		return $html . '</div></section>';
	}

	/**
	 * Line icons for the default categories. Others get a plain dot.
	 *
	 * @param string $slug Category slug.
	 * @return string SVG markup.
	 */
	private static function icon( $slug ) {
		$paths = array(
			'space'      => '<path d="M12 2c3 3 4 7 4 11l-4 3-4-3c0-4 1-8 4-11z"/><circle cx="12" cy="9" r="1.6"/><path d="M8 13l-3 4 3 1M16 13l3 4-3 1M10 19l2 3 2-3"/>',
			'animal'     => '<circle cx="6" cy="10" r="2"/><circle cx="10" cy="5.5" r="2"/><circle cx="14.5" cy="5.5" r="2"/><circle cx="18.5" cy="10" r="2"/><path d="M12 11c-3 0-6 4-6 7 0 2 2 3 3.5 2.4 1.5-.6 3.5-.6 5 0C16 21 18 20 18 18c0-3-3-7-6-7z"/>',
			'ocean'      => '<path d="M2 8c2 0 2-2 4-2s2 2 4 2 2-2 4-2 2 2 4 2 2-2 4-2"/><path d="M2 13c2 0 2-2 4-2s2 2 4 2 2-2 4-2 2 2 4 2 2-2 4-2"/><path d="M2 18c2 0 2-2 4-2s2 2 4 2 2-2 4-2 2 2 4 2 2-2 4-2"/>',
			'history'    => '<path d="M2 9l10-6 10 6z"/><path d="M4 21h16M5 9v12M9.5 9v12M14.5 9v12M19 9v12"/>',
			'science'    => '<path d="M9 3h6M10 3v6L4.5 19a1.5 1.5 0 0 0 1.3 2h12.4a1.5 1.5 0 0 0 1.3-2L14 9V3"/><path d="M7 15h10"/>',
			'food'       => '<path d="M12 7c-2-2-8-1-8 5 0 5 3.5 9 5.5 9 1 0 1.5-.7 2.5-.7s1.5.7 2.5.7c2 0 5.5-4 5.5-9 0-6-6-7-8-5z"/><path d="M12 7c0-2 1-4 3-4"/>',
			'nature'     => '<path d="M5 20c0-9 6-15 15-15 0 9-6 15-15 15z"/><path d="M5 20l9-9"/>',
			'human-body' => '<path d="M3 12h4l2-5 4 10 2-5h6"/>',
			'weird'      => '<path d="M12 3l1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9z"/><path d="M19 17l.8 2.2L22 20l-2.2.8L19 23l-.8-2.2L16 20l2.2-.8z"/>',
			'geography'  => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c3 3 3 15 0 18M12 3c-3 3-3 15 0 18"/>',
			'technology' => '<rect x="7" y="7" width="10" height="10" rx="1.5"/><path d="M10 3v4M14 3v4M10 17v4M14 17v4M3 10h4M3 14h4M17 10h4M17 14h4"/>',
			'psychology' => '<path d="M12 5a3 3 0 0 0-5.6-1.5A3 3 0 0 0 3.5 8 3 3 0 0 0 4 13.5 3 3 0 0 0 7 18a3 3 0 0 0 5 1.5z"/><path d="M12 5a3 3 0 0 1 5.6-1.5A3 3 0 0 1 20.5 8a3 3 0 0 1-.5 5.5A3 3 0 0 1 17 18a3 3 0 0 1-5 1.5"/>',
		);
		$path = $paths[ $slug ] ?? '<circle cx="12" cy="12" r="4"/>';
		return '<svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $path . '</svg>';
	}

	/**
	 * Section heading with an optional line under it and a link on the right.
	 *
	 * @param string $id    Heading ID.
	 * @param string $title Heading.
	 * @param string $lede  Line under the heading, or empty.
	 * @param string $link  Link HTML, or empty.
	 * @return string
	 */
	private static function head( $id, $title, $lede = '', $link = '' ) {
		$html  = '<div class="ff-home-head"><div>';
		$html .= sprintf( '<h2 class="wp-block-heading" id="%1$s">%2$s</h2>', esc_attr( $id ), esc_html( $title ) );
		$html .= '' !== $lede ? '<p class="ff-home-head__lede">' . esc_html( $lede ) . '</p>' : '';
		return $html . '</div>' . $link . '</div>';
	}

	/**
	 * The category grid: every category with published facts, with its count.
	 *
	 * @return string
	 */
	public static function shortcode_categories() {
		$counts = FactFusion_Facts::published_counts();
		$tiles  = '';
		foreach ( FactFusion_Categories::all( true ) as $category ) {
			$total = (int) ( $counts[ (int) $category->id ] ?? 0 );
			if ( ! $total ) {
				continue;
			}
			$tiles .= sprintf(
				'<li><a class="ff-tile" href="%1$s">%2$s<span class="ff-tile__name">%3$s</span><span class="ff-tile__count">%4$s</span></a></li>',
				esc_url( FactFusion_Router::browse_url( $category ) ),
				self::icon( $category->slug ),
				esc_html( $category->name ),
				/* translators: %s: number of facts. */
				esc_html( sprintf( _n( '%s fact', '%s facts', $total, 'factfusion-ai' ), number_format_i18n( $total ) ) )
			);
		}
		if ( '' === $tiles ) {
			return '';
		}
		$all  = sprintf(
			'<a class="ff-home-head__link" href="%1$s">%2$s</a>',
			esc_url( FactFusion_Router::browse_url() ),
			/* translators: %s: number of facts. */
			esc_html( sprintf( __( 'Browse all %s facts', 'factfusion-ai' ), number_format_i18n( array_sum( $counts ) ) ) )
		);
		$html = '<section class="ff-home-section" aria-labelledby="ff-subjects">';
		$html .= self::head( 'ff-subjects', __( 'Pick a subject', 'factfusion-ai' ), __( 'Every subject page starts with an introduction, the best facts to begin with and common questions answered.', 'factfusion-ai' ), $all );
		return $html . '<ul class="ff-tiles">' . $tiles . '</ul></section>';
	}

	/**
	 * "Myth or fact?" Each answer is a published fact with its source.
	 *
	 * @return string
	 */
	public static function shortcode_quiz() {
		$file      = FACTFUSION_DIR . 'data/home-quiz.php';
		$questions = is_readable( $file ) ? (array) require $file : array();
		$items     = '';
		$shown     = 0;
		foreach ( $questions as $question ) {
			$fact = FactFusion_Facts::get_by_slug( (string) ( $question['slug'] ?? '' ) );
			if ( ! $fact || 'published' !== $fact->status ) {
				continue;
			}
			$myth      = 'myth' === ( $question['answer'] ?? '' );
			$statement = trim( (string) ( $question['statement'] ?? '' ) );
			if ( '' === $statement ) {
				$statement = $fact->title . '.';
			}
			$sources = FactFusion_Sources::for_fact( $fact->id );
			$items  .= sprintf( '<li class="ff-quiz__item" data-ff-quiz-item data-ff-answer="%s">', $myth ? 'myth' : 'fact' );
			$items  .= '<p class="ff-quiz__statement">&ldquo;' . esc_html( $statement ) . '&rdquo;</p>';
			$items  .= sprintf(
				'<div class="ff-quiz__buttons ff-needs-js"><button type="button" class="ff-quiz__guess" data-ff-guess="myth">%1$s</button><button type="button" class="ff-quiz__guess ff-quiz__guess--fact" data-ff-guess="fact">%2$s</button></div>',
				esc_html__( 'Myth', 'factfusion-ai' ),
				esc_html__( 'Fact', 'factfusion-ai' )
			);
			$items .= '<details class="ff-quiz__answer" data-ff-quiz-answer><summary>' . esc_html__( 'Show the answer', 'factfusion-ai' ) . '</summary>';
			$items .= '<p class="ff-quiz__verdict-line"><strong data-ff-quiz-verdict></strong> <strong>' . esc_html( $myth ? __( 'It is a myth.', 'factfusion-ai' ) : __( 'It is a fact.', 'factfusion-ai' ) ) . '</strong> ' . esc_html( $fact->claim ) . '</p>';
			$items .= '<p class="ff-meta">';
			if ( $sources ) {
				$items .= sprintf( '%1$s: <a href="%2$s">%3$s</a> &middot; ', esc_html__( 'Source', 'factfusion-ai' ), esc_url( $sources[0]->url ), esc_html( $sources[0]->source_name ) );
			}
			$items .= sprintf( '<a href="%1$s">%2$s</a></p></details></li>', esc_url( FactFusion_Facts::url( $fact ) ), esc_html__( 'Read the fact', 'factfusion-ai' ) );
			++$shown;
		}
		if ( ! $shown ) {
			return '';
		}
		FactFusion_Frontend::need_script();

		$html  = sprintf(
			'<section class="ff-quiz" id="myth-or-fact" aria-labelledby="ff-quiz-title" data-ff-quiz data-ff-progress="%1$s" data-ff-right="%2$s" data-ff-wrong="%3$s" data-ff-next="%4$s" data-ff-again="%5$s">',
			/* translators: 1: question number, 2: number of questions, 3: correct answers so far. */
			esc_attr__( 'Question %1$s of %2$s · Score %3$s', 'factfusion-ai' ),
			esc_attr__( 'Right!', 'factfusion-ai' ),
			esc_attr__( 'Not quite.', 'factfusion-ai' ),
			esc_attr__( 'Next question', 'factfusion-ai' ),
			esc_attr__( 'Play again', 'factfusion-ai' )
		);
		$html .= '<div class="ff-quiz__head"><h2 class="wp-block-heading" id="ff-quiz-title">' . esc_html__( 'Myth or fact?', 'factfusion-ai' ) . '</h2>';
		$html .= '<p class="ff-quiz__score ff-needs-js" data-ff-quiz-score aria-live="polite"></p></div>';
		$html .= '<p class="ff-meta ff-quiz__intro">' . esc_html__( 'Decide, then see the verified fact and its source.', 'factfusion-ai' ) . '</p>';
		$html .= '<ol class="ff-quiz__list">' . $items . '</ol>';
		$html .= '<button type="button" class="wp-element-button ff-quiz__next" data-ff-quiz-next hidden>' . esc_html__( 'Next question', 'factfusion-ai' ) . '</button>';
		return $html . '</section>';
	}

	/**
	 * A short list of facts as links, with category and source under each.
	 *
	 * @param object[] $facts Fact rows.
	 * @return string
	 */
	private static function fact_links( $facts ) {
		$names = array();
		foreach ( FactFusion_Categories::all() as $row ) {
			$names[ (int) $row->id ] = $row->name;
		}
		$html = '<ul class="ff-link-list">';
		foreach ( $facts as $fact ) {
			$sources = FactFusion_Sources::for_fact( $fact->id );
			$meta    = array_filter( array( $names[ (int) $fact->category_id ] ?? '', $sources ? $sources[0]->source_name : '' ) );
			$html   .= sprintf(
				'<li><a href="%1$s"><span class="ff-link-list__title">%2$s</span><span class="ff-link-list__meta">%3$s</span></a></li>',
				esc_url( FactFusion_Facts::url( $fact ) ),
				esc_html( $fact->title ),
				esc_html( implode( ' · ', $meta ) )
			);
		}
		return $html . '</ul>';
	}

	/**
	 * The most recently verified facts, and the editor's choices: the first
	 * featured fact of each category, as set under FactFusion AI > Categories.
	 *
	 * @return string
	 */
	public static function shortcode_latest() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$latest = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE status = %s AND verification_status = %s ORDER BY date_verified DESC, fact_number DESC LIMIT %d',
				FactFusion_DB::table( 'facts' ),
				'published',
				'verified',
				5
			)
		);

		$taken = wp_list_pluck( $latest, 'id' );
		$picks = array();
		foreach ( FactFusion_Categories::all( true ) as $category ) {
			foreach ( FactFusion_Categories::featured_facts( $category ) as $fact ) {
				if ( ! in_array( $fact->id, $taken, true ) ) {
					$picks[] = $fact;
					$taken[] = $fact->id;
				}
				break;
			}
			if ( count( $picks ) >= 5 ) {
				break;
			}
		}

		if ( ! $latest && ! $picks ) {
			return '';
		}
		$html = '<div class="ff-home-duo">';
		if ( $latest ) {
			$html .= '<section class="ff-panel" aria-labelledby="ff-latest"><h2 class="wp-block-heading" id="ff-latest">' . esc_html__( 'Newly verified', 'factfusion-ai' ) . '</h2>';
			$html .= '<p class="ff-meta">' . esc_html__( 'The latest facts to pass our checks.', 'factfusion-ai' ) . '</p>' . self::fact_links( $latest ) . '</section>';
		}
		if ( $picks ) {
			$html .= '<section class="ff-panel" aria-labelledby="ff-picks"><h2 class="wp-block-heading" id="ff-picks">' . esc_html__( 'Editor\'s picks', 'factfusion-ai' ) . '</h2>';
			$html .= '<p class="ff-meta">' . esc_html__( 'Good places to start, one from each subject.', 'factfusion-ai' ) . '</p>' . self::fact_links( $picks ) . '</section>';
		}
		return $html . '</div>';
	}

	/**
	 * How every fact is checked, in three steps, with a link to the full method.
	 *
	 * @return string
	 */
	public static function shortcode_steps() {
		$steps = array(
			array( __( 'It starts from a source', 'factfusion-ai' ), __( 'A named source: a science agency, a museum, a peer-reviewed study or a reference work such as Wikipedia. Its tier, from A to D, shows which kind it is. AI never supplies a source.', 'factfusion-ai' ) ),
			array( __( 'An editor checks it', 'factfusion-ai' ), __( 'An editor opens the source and checks that the claim says only what the source says. AI may draft the wording, but nothing is published until the editor approves it.', 'factfusion-ai' ) ),
			array( __( 'You can check it too', 'factfusion-ai' ), __( 'Every fact links to its source and shows its tier from A to D, the date it was last verified and a confidence score.', 'factfusion-ai' ) ),
		);
		$link = sprintf( '<a class="ff-home-head__link" href="%s">%s</a>', esc_url( home_url( user_trailingslashit( 'how-we-verify-facts' ) ) ), esc_html__( 'Read our full method', 'factfusion-ai' ) );
		$html = '<section class="ff-home-section" aria-labelledby="ff-steps">';
		$html .= self::head( 'ff-steps', __( 'Why you can repeat these facts', 'factfusion-ai' ), __( 'Three steps stand behind every fact on this site.', 'factfusion-ai' ), $link );
		$html .= '<ol class="ff-steps">';
		foreach ( $steps as $number => $step ) {
			$html .= sprintf( '<li><span class="ff-steps__number" aria-hidden="true">%1$d</span><h3>%2$s</h3><p>%3$s</p></li>', $number + 1, esc_html( $step[0] ), esc_html( $step[1] ) );
		}
		return $html . '</ol></section>';
	}

	/**
	 * Embed code for the random-fact widget, with a copy button.
	 *
	 * @return string
	 */
	public static function shortcode_embed() {
		if ( ! FactFusion_Facts::pool() ) {
			return '';
		}
		FactFusion_Frontend::need_script();
		$html  = '<section class="ff-home-embed" aria-labelledby="ff-embed-title" data-ff-embed-box><div class="ff-home-embed__text">';
		$html .= '<h2 class="wp-block-heading" id="ff-embed-title">' . esc_html__( 'Put verified facts on your own site', 'factfusion-ai' ) . '</h2>';
		$html .= '<p>' . esc_html__( 'Teachers, bloggers and club pages can show a random verified fact, with its source, on any page. The widget is free and needs no sign-up. Please keep the link under it.', 'factfusion-ai' ) . '</p>';
		$html .= sprintf( '<p><button type="button" class="wp-element-button ff-needs-js" data-ff-embed-copy>%s</button> <span class="ff-status" role="status" data-ff-status></span></p></div>', esc_html__( 'Copy the code', 'factfusion-ai' ) );
		$html .= '<p class="ff-home-embed__code"><label class="screen-reader-text" for="ff-home-embed-code">' . esc_html__( 'Embed code', 'factfusion-ai' ) . '</label>';
		$html .= sprintf( '<textarea id="ff-home-embed-code" class="ff-embed-code" rows="6" readonly data-ff-embed-code>%s</textarea></p>', esc_textarea( FactFusion_Frontend::embed_snippet() ) );
		return $html . '</section>';
	}

	/**
	 * Footer columns: subjects and guides. Shown on every page.
	 *
	 * @return string
	 */
	public static function shortcode_footer_links() {
		$cached = get_transient( self::FOOTER_TRANSIENT );
		if ( is_string( $cached ) ) {
			return $cached;
		}
		$counts   = FactFusion_Facts::published_counts();
		$subjects = array();
		foreach ( FactFusion_Categories::all( true ) as $category ) {
			if ( ! empty( $counts[ (int) $category->id ] ) && count( $subjects ) < 6 ) {
				$subjects[] = sprintf( '<li><a href="%s">%s</a></li>', esc_url( FactFusion_Router::browse_url( $category ) ), esc_html( $category->name ) );
			}
		}
		if ( $subjects ) {
			$subjects[] = sprintf( '<li><a href="%s">%s</a></li>', esc_url( FactFusion_Router::browse_url() ), esc_html__( 'All facts', 'factfusion-ai' ) );
			$subjects[] = sprintf( '<li><a href="%s">%s</a></li>', esc_url( home_url( user_trailingslashit( 'fact-of-the-day' ) ) ), esc_html__( 'Fact of the Day', 'factfusion-ai' ) );
		}
		$guides = array();
		foreach ( FactFusion_Content::status() as $state ) {
			if ( 'publish' === $state['status'] ) {
				$guides[] = sprintf( '<li><a href="%s">%s</a></li>', esc_url( get_permalink( $state['id'] ) ), esc_html( get_the_title( $state['id'] ) ) );
			}
		}

		$html = '';
		if ( $subjects ) {
			$html .= '<div class="ff-footer__col"><p class="ff-footer__heading">' . esc_html__( 'Facts', 'factfusion-ai' ) . '</p><ul>' . implode( '', $subjects ) . '</ul></div>';
		}
		if ( $guides ) {
			$html .= '<div class="ff-footer__col"><p class="ff-footer__heading">' . esc_html__( 'Generators and guides', 'factfusion-ai' ) . '</p><ul>' . implode( '', $guides ) . '</ul></div>';
		}
		set_transient( self::FOOTER_TRANSIENT, $html, 6 * HOUR_IN_SECONDS );
		return $html;
	}
}
