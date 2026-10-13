<?php
/**
 * Articles and tool pages.
 *
 * @package FactFusion_AI_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Phase 2 articles and tool pages. They are ordinary WordPress pages, so
 * the editor can rewrite them in the block editor. The plugin adds what a page
 * cannot do alone: a byline with dates, cited facts pulled from the fact
 * tables, a category generator, embed code and the search-engine data.
 *
 * Every fact shown on these pages comes from the fact tables through a
 * shortcode. No fact is stored in page text as data.
 */
final class FactFusion_Content {

	const TYPE_META = '_factfusion_type';

	const TITLE_META = '_factfusion_meta_title';

	const DESCRIPTION_META = '_factfusion_meta_description';

	const CATEGORY_META = '_factfusion_category';

	/**
	 * The pages: slug => type, title, meta title, meta description and, for a
	 * category tool, the category slug.
	 *
	 * @return array<string,array<string,string>>
	 */
	public static function definitions() {
		return array(
			'random-fact-generator'                => array(
				'type'        => 'tool',
				'title'       => 'Random Fact Generator',
				'meta_title'  => 'Random Fact Generator With Sources',
				'description' => 'Press one button for a random fact. Every fact is checked by a person against a named source, and shows that source, the date verified and a confidence score.',
				'category'    => '',
			),
			'random-animal-fact-generator'         => array(
				'type'        => 'tool',
				'title'       => 'Random Animal Fact Generator',
				'meta_title'  => 'Random Animal Fact Generator With Sources',
				'description' => 'Get a random animal fact in one click. Each one is checked against a source such as NOAA Fisheries or the National Park Service, and links to it.',
				'category'    => 'animal',
			),
			'random-space-fact-generator'          => array(
				'type'        => 'tool',
				'title'       => 'Random Space Fact Generator',
				'meta_title'  => 'Random Space Fact Generator With NASA Sources',
				'description' => 'Get a random space fact in one click. Each fact is checked against a NASA page and links to it, with the date it was last verified.',
				'category'    => 'space',
			),
			'what-are-random-facts'                => array(
				'type'        => 'article',
				'title'       => 'What Are Random Facts? Random vs. Verified',
				'meta_title'  => 'What Are Random Facts? Random vs. Verified',
				'description' => 'A random fact is picked by chance. A verified fact has been checked against a source. Here is the difference, and how to tell which kind you are reading.',
				'category'    => '',
			),
			'how-to-fact-check-ai-generated-facts' => array(
				'type'        => 'article',
				'title'       => 'How to Fact-Check AI-Generated Facts',
				'meta_title'  => 'How to Fact-Check AI-Generated Facts: 7 Steps',
				'description' => 'A step-by-step method for checking a fact written by an AI tool: split the claim, find the primary source, compare the wording, and know when to stop.',
				'category'    => '',
			),
			'best-facts-about-space'               => array(
				'type'        => 'article',
				'title'       => 'Verified Space Facts With NASA Sources',
				'meta_title'  => 'Best Facts About Space, Verified With NASA Sources',
				'description' => 'Space facts that each link to the NASA page they were checked against: the hottest planet, the shortest day, rings 30 feet thick and more.',
				'category'    => '',
			),
			'amazing-ocean-facts'                  => array(
				'type'        => 'article',
				'title'       => 'Ocean Facts Verified by NOAA and Peer-Reviewed Studies',
				'meta_title'  => 'Amazing Ocean Facts Verified by NOAA and Studies',
				'description' => 'Ocean facts with their sources: the largest waterfall is underwater, seawater holds gold, and one shark lives at least 272 years. Checked against NOAA and journals.',
				'category'    => '',
			),
			'historical-myths-debunked'            => array(
				'type'        => 'article',
				'title'       => 'History Myths Debunked With Primary Sources',
				'meta_title'  => 'History Myths Debunked With Primary Sources',
				'description' => 'Seven history myths checked against the records: the July 4 signing, horned Viking helmets, the Liberty Bell crack, Paul Revere\'s ride and more.',
				'category'    => '',
			),
		);
	}

	/**
	 * Registers hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_shortcodes' ) );
	}

	/**
	 * Registers the shortcodes used in these pages and in the page template.
	 */
	public static function register_shortcodes() {
		add_shortcode( 'factfusion_fact', array( __CLASS__, 'shortcode_fact' ) );
		add_shortcode( 'factfusion_byline', array( __CLASS__, 'shortcode_byline' ) );
		add_shortcode( 'factfusion_embed_code', array( __CLASS__, 'shortcode_embed_code' ) );
		add_shortcode( 'factfusion_guides', array( __CLASS__, 'shortcode_guides' ) );
	}

	/**
	 * Type of a page: "article", "tool", or empty for any other page.
	 *
	 * @param int|WP_Post $post Page.
	 * @return string
	 */
	public static function type( $post ) {
		$post = get_post( $post );
		if ( ! $post || 'page' !== $post->post_type ) {
			return '';
		}
		$type = (string) get_post_meta( $post->ID, self::TYPE_META, true );
		return in_array( $type, array( 'article', 'tool' ), true ) ? $type : '';
	}

	/**
	 * The article or tool page being displayed, or null.
	 *
	 * @return WP_Post|null
	 */
	public static function current() {
		if ( ! did_action( 'wp' ) || ! is_page() ) {
			return null;
		}
		$post = get_queried_object();
		return $post instanceof WP_Post && '' !== self::type( $post ) ? $post : null;
	}

	/**
	 * The questions and answers written in a page, read from its "details"
	 * blocks. The visible text and the FAQPage data come from the same blocks.
	 *
	 * @param WP_Post $post Page.
	 * @return array<int,array{0:string,1:string}>
	 */
	public static function faq( $post ) {
		$pairs = array();
		if ( preg_match_all( '#<details[^>]*>\s*<summary>(.*?)</summary>(.*?)</details>#s', (string) $post->post_content, $found, PREG_SET_ORDER ) ) {
			foreach ( $found as $match ) {
				$question = trim( wp_strip_all_tags( $match[1] ) );
				$answer   = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( strip_shortcodes( $match[2] ) ) ) );
				if ( '' !== $question && '' !== $answer ) {
					$pairs[] = array( html_entity_decode( $question, ENT_QUOTES, 'UTF-8' ), html_entity_decode( $answer, ENT_QUOTES, 'UTF-8' ) );
				}
			}
		}
		return $pairs;
	}

	/**
	 * Which of the pages exist, and in what state.
	 *
	 * @return array<string,array{title:string,type:string,status:string,id:int}>
	 */
	public static function status() {
		$out = array();
		foreach ( self::definitions() as $slug => $definition ) {
			$page         = get_page_by_path( $slug, OBJECT, 'page' );
			$out[ $slug ] = array(
				'title'  => $definition['title'],
				'type'   => $definition['type'],
				'status' => $page ? $page->post_status : '',
				'id'     => $page ? (int) $page->ID : 0,
			);
		}
		return $out;
	}

	/**
	 * Creates any of the pages that does not exist, as a DRAFT for the editor
	 * to read and publish. Existing pages, in any state, are never touched.
	 *
	 * @return string[] Slugs of the pages created.
	 */
	public static function create_missing() {
		$created = array();
		$editor  = 0;
		foreach ( FactFusion_Profiles::all() as $profile ) {
			$editor = (int) $profile->ID;
			break;
		}
		$tokens = array(
			'{{home_url}}'    => esc_url( home_url( '/' ) ),
			'{{facts_url}}'   => esc_url( FactFusion_Router::browse_url() ),
			'{{fotd_url}}'    => esc_url( home_url( user_trailingslashit( 'fact-of-the-day' ) ) ),
			'{{verify_url}}'  => esc_url( home_url( user_trailingslashit( 'how-we-verify-facts' ) ) ),
			'{{contact_url}}' => esc_url( home_url( user_trailingslashit( 'contact' ) ) ),
			'{{terms_url}}'   => esc_url( home_url( user_trailingslashit( 'terms' ) ) ),
			'{{api_url}}'     => esc_url( rest_url( 'factfusion/v1/random' ) ),
		);
		foreach ( array_keys( self::definitions() ) as $slug ) {
			$tokens[ '{{page:' . $slug . '}}' ] = esc_url( home_url( user_trailingslashit( $slug ) ) );
		}
		foreach ( FactFusion_Categories::all() as $category ) {
			$tokens[ '{{category:' . $category->slug . '}}' ] = esc_url( FactFusion_Router::browse_url( $category ) );
		}

		$definitions = self::definitions();
		foreach ( self::status() as $slug => $state ) {
			$file = FACTFUSION_DIR . 'data/content/' . $slug . '.php';
			if ( $state['id'] || ! is_readable( $file ) ) {
				continue;
			}
			// The starter text lives in guarded PHP files so it cannot be opened directly in a browser.
			ob_start();
			include $file;
			$content = strtr( ltrim( (string) ob_get_clean() ), $tokens );
			$args    = array(
				'post_type'      => 'page',
				'post_status'    => 'draft',
				'post_title'     => $state['title'],
				'post_name'      => $slug,
				'post_content'   => $content,
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
			);
			if ( $editor ) {
				$args['post_author'] = $editor;
			}
			$id = wp_insert_post( wp_slash( $args ), true );
			if ( is_wp_error( $id ) ) {
				continue;
			}
			update_post_meta( $id, self::TYPE_META, $definitions[ $slug ]['type'] );
			update_post_meta( $id, self::TITLE_META, $definitions[ $slug ]['meta_title'] );
			update_post_meta( $id, self::DESCRIPTION_META, $definitions[ $slug ]['description'] );
			update_post_meta( $id, self::CATEGORY_META, $definitions[ $slug ]['category'] );
			$created[] = $slug;
		}
		return $created;
	}

	/**
	 * Slugs of published facts that a page cites but that are not live.
	 * A page should not be published while this list is not empty.
	 *
	 * @param WP_Post $post Page.
	 * @return string[]
	 */
	public static function missing_facts( $post ) {
		$missing = array();
		if ( preg_match_all( '/\[factfusion_fact\s+slug="([^"]+)"/', (string) $post->post_content, $found ) ) {
			foreach ( array_unique( $found[1] ) as $slug ) {
				$fact = FactFusion_Facts::get_by_slug( sanitize_title( $slug ) );
				if ( ! $fact || 'published' !== $fact->status ) {
					$missing[] = $slug;
				}
			}
		}
		return $missing;
	}

	/**
	 * One cited fact inside an article or tool page: the claim, its source
	 * and a link to the fact's own page. Prints nothing for a fact that is
	 * not published, so an unverified fact can never appear this way.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function shortcode_fact( $atts ) {
		$atts = shortcode_atts( array( 'slug' => '' ), $atts, 'factfusion_fact' );
		$fact = '' !== $atts['slug'] ? FactFusion_Facts::get_by_slug( sanitize_title( $atts['slug'] ) ) : null;
		if ( ! $fact || 'published' !== $fact->status ) {
			return current_user_can( factfusion_capability() )
				/* translators: %s: fact slug. */
				? '<p class="ff-meta ff-cite--missing">' . esc_html( sprintf( __( '[Only editors see this: the fact "%s" is not published, so nothing is shown here.]', 'factfusion-ai' ), $atts['slug'] ) ) . '</p>'
				: '';
		}
		$sources = FactFusion_Sources::for_fact( $fact->id );
		$url     = FactFusion_Facts::url( $fact );

		$html  = '<div class="ff-card ff-cite">';
		$html .= sprintf( '<p class="ff-cite__title"><a href="%1$s">%2$s</a></p>', esc_url( $url ), esc_html( $fact->title ) );
		$html .= '<p class="ff-fact__claim">' . esc_html( $fact->claim ) . '</p>';
		$html .= '<p class="ff-meta">';
		if ( $sources ) {
			$html .= sprintf(
				'%1$s: <a href="%2$s">%3$s</a> <span class="ff-pill ff-tier">%4$s</span> ',
				esc_html__( 'Source', 'factfusion-ai' ),
				esc_url( $sources[0]->url ),
				esc_html( $sources[0]->source_name ),
				/* translators: %s: tier letter A to D. */
				esc_html( sprintf( __( 'Tier %s', 'factfusion-ai' ), $sources[0]->tier ) )
			);
		}
		if ( 'verified' === $fact->verification_status && $fact->date_verified ) {
			$stamp = strtotime( $fact->date_verified . ' UTC' );
			$html .= sprintf(
				'<span>%1$s <time datetime="%2$s">%3$s</time></span>',
				esc_html__( 'Verified', 'factfusion-ai' ),
				esc_attr( gmdate( 'Y-m-d', $stamp ) ),
				esc_html( wp_date( get_option( 'date_format' ), $stamp ) )
			);
		}
		$html .= '</p></div>';
		return $html;
	}

	/**
	 * Author and dates under the title of an article or tool page.
	 *
	 * @return string
	 */
	public static function shortcode_byline() {
		$post = self::current();
		if ( ! $post ) {
			return '';
		}
		$author  = get_userdata( $post->post_author );
		$parts   = array();
		$written = 'article' === self::type( $post );
		if ( $author ) {
			$profile = FactFusion_Profiles::url( $author );
			$name    = esc_html( $author->display_name );
			$parts[] = sprintf(
				/* translators: %s: person's name. */
				$written ? esc_html__( 'By %s', 'factfusion-ai' ) : esc_html__( 'Reviewed by %s', 'factfusion-ai' ),
				$profile ? sprintf( '<a href="%s" rel="author">%s</a>', esc_url( $profile ), $name ) : $name
			);
		}
		$published = get_post_time( 'U', true, $post );
		$modified  = get_post_modified_time( 'U', true, $post );
		$format    = get_option( 'date_format' );
		$parts[]   = sprintf(
			'%1$s <time datetime="%2$s">%3$s</time>',
			esc_html__( 'Published', 'factfusion-ai' ),
			esc_attr( gmdate( 'Y-m-d', $published ) ),
			esc_html( wp_date( $format, $published ) )
		);
		$parts[]   = sprintf(
			'%1$s <time datetime="%2$s">%3$s</time>',
			esc_html__( 'Updated', 'factfusion-ai' ),
			esc_attr( gmdate( 'Y-m-d', $modified ) ),
			esc_html( wp_date( $format, $modified ) )
		);
		return '<p class="ff-meta ff-byline">' . implode( ' <span aria-hidden="true">·</span> ', $parts ) . '</p>';
	}

	/**
	 * Embed code for the random-fact widget, with a copy button.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function shortcode_embed_code( $atts ) {
		$atts     = shortcode_atts( array( 'category' => '' ), $atts, 'factfusion_embed_code' );
		$category = '' !== $atts['category'] ? FactFusion_Categories::get_by_slug( sanitize_title( $atts['category'] ) ) : null;
		FactFusion_Frontend::need_script();

		$html  = '<div class="ff-embed-box ff-embed-box--open" data-ff-embed-box>';
		$html .= '<p><label for="ff-embed-code">' . esc_html__( 'Paste this code into your page. It is free to use; please keep the link.', 'factfusion-ai' ) . '</label></p>';
		$html .= sprintf( '<textarea id="ff-embed-code" class="ff-embed-code" rows="5" readonly data-ff-embed-code>%s</textarea>', esc_textarea( FactFusion_Frontend::embed_snippet( null, $category && $category->is_active ? $category : null ) ) );
		$html .= sprintf( '<p><button type="button" class="ff-button ff-needs-js" data-ff-embed-copy>%s</button> <span class="ff-status" role="status" data-ff-status></span></p>', esc_html__( 'Copy code', 'factfusion-ai' ) );
		$html .= '</div>';
		return $html;
	}

	/**
	 * Links to the published articles and tool pages. Shown on the homepage
	 * and under each article and tool page, so every one of them is linked.
	 *
	 * @return string
	 */
	public static function shortcode_guides( $atts = array() ) {
		$atts    = shortcode_atts( array( 'layout' => '' ), $atts, 'factfusion_guides' );
		$current = self::current();
		if ( ! $current && ! ( did_action( 'wp' ) && is_front_page() ) ) {
			return '';
		}
		$groups      = array(
			'tool'    => array(),
			'article' => array(),
		);
		$cards       = array();
		$definitions = self::definitions();
		foreach ( self::status() as $slug => $state ) {
			if ( 'publish' !== $state['status'] || ( $current && (int) $current->ID === $state['id'] ) ) {
				continue;
			}
			$groups[ $state['type'] ][] = sprintf( '<li><a href="%s">%s</a></li>', esc_url( get_permalink( $state['id'] ) ), esc_html( get_the_title( $state['id'] ) ) );
			// The card text is the page's own search description, which the editor can change.
			$about   = trim( (string) get_post_meta( $state['id'], self::DESCRIPTION_META, true ) );
			$cards[] = sprintf(
				'<li><a class="ff-guide-card" href="%1$s"><span class="ff-guide-card__kind">%2$s</span><span class="ff-guide-card__title">%3$s</span><span class="ff-guide-card__text">%4$s</span></a></li>',
				esc_url( get_permalink( $state['id'] ) ),
				esc_html( 'tool' === $state['type'] ? __( 'Generator', 'factfusion-ai' ) : __( 'Guide', 'factfusion-ai' ) ),
				esc_html( get_the_title( $state['id'] ) ),
				esc_html( '' !== $about ? $about : ( $definitions[ $slug ]['description'] ?? '' ) )
			);
		}
		if ( ! $groups['tool'] && ! $groups['article'] ) {
			return '';
		}
		if ( 'cards' === $atts['layout'] && ! $current ) {
			$html  = '<section class="ff-home-section ff-guides" aria-labelledby="ff-guides-title"><div class="ff-home-head"><div>';
			$html .= '<h2 class="wp-block-heading" id="ff-guides-title">' . esc_html__( 'Fact generators and guides', 'factfusion-ai' ) . '</h2>';
			$html .= '<p class="ff-home-head__lede">' . esc_html__( 'Generators for one subject, and guides that show how we know.', 'factfusion-ai' ) . '</p></div></div>';
			return $html . '<ul class="ff-guide-cards">' . implode( '', $cards ) . '</ul></section>';
		}
		$html = '<section class="ff-section ff-guides"><h2 class="wp-block-heading">' . esc_html( $current ? __( 'More from FactFusion AI', 'factfusion-ai' ) : __( 'Fact generators and guides', 'factfusion-ai' ) ) . '</h2>';
		if ( $groups['tool'] ) {
			$html .= '<h3 class="ff-guides__label">' . esc_html__( 'Fact generators', 'factfusion-ai' ) . '</h3><ul class="ff-guides__list">' . implode( '', $groups['tool'] ) . '</ul>';
		}
		if ( $groups['article'] ) {
			$html .= '<h3 class="ff-guides__label">' . esc_html__( 'Guides', 'factfusion-ai' ) . '</h3><ul class="ff-guides__list">' . implode( '', $groups['article'] ) . '</ul>';
		}
		return $html . '</section>';
	}
}
