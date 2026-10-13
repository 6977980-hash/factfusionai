<?php
/**
 * Search-engine output for the plugin's pages.
 *
 * @package FactFusion_AI_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Titles, descriptions, canonical URLs, robots rules, Open Graph tags,
 * JSON-LD, breadcrumbs and the facts sitemap.
 *
 * Facts are not WordPress posts, so an SEO plugin has nothing to read on
 * these pages. Here the plugin prints the whole set itself and asks Rank
 * Math to stay out, so nothing is printed twice. Ordinary WordPress pages
 * are left to Rank Math (or to WordPress when no SEO plugin is active).
 */
final class FactFusion_SEO {

	/**
	 * A category page with fewer facts than this is too thin to index.
	 */
	const THIN_CATEGORY = 3;

	const DESCRIPTION_LENGTH = 155;

	const TITLE_LENGTH = 60;

	/**
	 * Computed tags for this request.
	 *
	 * @var array|null
	 */
	private static $meta = null;

	/**
	 * Registers hooks.
	 */
	public static function init() {
		add_filter( 'rank_math/frontend/disable_integration', array( __CLASS__, 'disable_rank_math' ) );
		add_filter( 'pre_get_document_title', array( __CLASS__, 'title' ), 99 );
		add_filter( 'wp_robots', array( __CLASS__, 'robots' ), 20 );
		add_action( 'wp_head', array( __CLASS__, 'head' ), 1 );
		add_filter( 'robots_txt', array( __CLASS__, 'robots_txt' ), 20, 2 );
		add_filter( 'rank_math/sitemap/index', array( __CLASS__, 'rank_math_index' ) );
	}

	/**
	 * Which of the plugin's pages this is: home, fact, fotd, browse, author,
	 * article, tool, or empty.
	 *
	 * @return string
	 */
	public static function view() {
		$view = FactFusion_Router::view();
		if ( in_array( $view, array( 'fact', 'fotd', 'browse', 'author' ), true ) ) {
			return $view;
		}
		if ( '' !== $view || ! did_action( 'wp' ) ) {
			return '';
		}
		if ( is_front_page() ) {
			return 'home';
		}
		// A published article or tool page. Drafts and previews are left alone.
		$content = FactFusion_Content::current();
		return $content && 'publish' === $content->post_status && ! is_preview() ? FactFusion_Content::type( $content ) : '';
	}

	/**
	 * Keeps Rank Math's tags off the plugin's pages, where this class prints them.
	 *
	 * @param bool $disabled Whether Rank Math is already disabled for this request.
	 * @return bool
	 */
	public static function disable_rank_math( $disabled ) {
		return $disabled || '' !== self::view();
	}

	/**
	 * The questions and answers shown on the homepage and in its FAQPage data.
	 * The visible text and the structured data always come from this one list.
	 *
	 * @return array<int,array{0:string,1:string}>
	 */
	public static function faq() {
		return (array) apply_filters(
			'factfusion_faq',
			array(
				array(
					__( 'Where do the facts come from?', 'factfusion-ai' ),
					__( 'Every fact is taken from a named source, such as a government science agency or a museum. The source is linked on the fact\'s page, with a tier from A to D that shows what kind of source it is.', 'factfusion-ai' ),
				),
				array(
					__( 'How are the facts verified?', 'factfusion-ai' ),
					__( 'An editor checks each claim against its source before it is published. The fact\'s page shows who verified it, the date it was last verified and a confidence score out of 100.', 'factfusion-ai' ),
				),
				array(
					__( 'Does FactFusion AI use AI to write facts?', 'factfusion-ai' ),
					__( 'No fact comes from AI. AI is used only to help draft explanations, and a fact\'s page says so when it was. The facts themselves come from the linked sources.', 'factfusion-ai' ),
				),
				array(
					__( 'Can I use these facts on my own site?', 'factfusion-ai' ),
					__( 'Yes. Each fact page has an "Embed this fact" box with code you can paste into your site, and there is a free public API. Please keep the link back to FactFusion AI.', 'factfusion-ai' ),
				),
				array(
					__( 'What if a fact is wrong?', 'factfusion-ai' ),
					__( 'Use the "Report a problem with this fact" form on the fact\'s page. Reports go to the editor, who checks the fact again and corrects it if needed.', 'factfusion-ai' ),
				),
			)
		);
	}

	/**
	 * Shortens text to a clean length for a description.
	 *
	 * @param string $text Text, may contain markup.
	 * @param int    $max  Maximum characters.
	 * @return string
	 */
	public static function clip( $text, $max = self::DESCRIPTION_LENGTH ) {
		$text = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) $text ) ) );
		if ( mb_strlen( $text ) <= $max ) {
			return $text;
		}
		$cut   = mb_substr( $text, 0, $max - 1 );
		$space = mb_strrpos( $cut, ' ' );
		if ( false !== $space && $space > $max * 0.6 ) {
			$cut = mb_substr( $cut, 0, $space );
		}
		return rtrim( $cut, " ,;:.\u{2013}-" ) . "\u{2026}";
	}

	/**
	 * Adds the site name to a title when the result still fits in a search result.
	 *
	 * @param string $title Page title.
	 * @param bool   $force Add the site name even if the title becomes long.
	 * @return string
	 */
	private static function with_brand( $title, $force = false ) {
		$full = $title . ' | ' . get_bloginfo( 'name' );
		return $force || mb_strlen( $full ) <= self::TITLE_LENGTH ? $full : $title;
	}

	/**
	 * The breadcrumb trail for this page: label and URL, in order.
	 * The visible breadcrumb and the BreadcrumbList data both use it.
	 *
	 * @return array<int,array{0:string,1:string}>
	 */
	public static function trail() {
		$home  = array( __( 'Home', 'factfusion-ai' ), home_url( '/' ) );
		$facts = array( __( 'Facts', 'factfusion-ai' ), FactFusion_Router::browse_url() );

		switch ( self::view() ) {
			case 'fact':
				$fact     = FactFusion_Router::current_fact();
				$category = $fact->category_id ? FactFusion_Categories::get( $fact->category_id ) : null;
				$trail    = array( $home, $facts );
				if ( $category && $category->is_active ) {
					$trail[] = array( $category->name, FactFusion_Router::browse_url( $category ) );
				}
				$trail[] = array( $fact->title, FactFusion_Facts::url( $fact ) );
				return $trail;

			case 'fotd':
				return array( $home, array( __( 'Fact of the Day', 'factfusion-ai' ), home_url( user_trailingslashit( 'fact-of-the-day' ) ) ) );

			case 'browse':
				$browse = FactFusion_Router::browse();
				$trail  = array( $home, $facts );
				if ( '' !== $browse['search'] ) {
					$trail[] = array( __( 'Search results', 'factfusion-ai' ), FactFusion_Router::browse_url( null, 1, $browse['search'] ) );
				} elseif ( $browse['category'] ) {
					$trail[] = array( $browse['category']->name, FactFusion_Router::browse_url( $browse['category'] ) );
				}
				return $trail;

			case 'author':
				$profile = FactFusion_Router::profile();
				return array( $home, array( $profile->display_name, FactFusion_Profiles::url( $profile ) ) );

			case 'article':
			case 'tool':
				$content = FactFusion_Content::current();
				return array( $home, array( get_the_title( $content ), get_permalink( $content ) ) );
		}
		return array();
	}

	/**
	 * Title, description, canonical URL and indexing decision for this page.
	 *
	 * @return array{title:string,description:string,canonical:string,index:bool,type:string}|null
	 */
	public static function meta() {
		if ( null !== self::$meta ) {
			return self::$meta;
		}
		$view = self::view();
		if ( '' === $view ) {
			return null;
		}

		$brand = get_bloginfo( 'name' );
		$meta  = array(
			'title'       => $brand,
			'description' => '',
			'canonical'   => home_url( '/' ),
			'index'       => true,
			'type'        => 'website',
		);

		switch ( $view ) {
			case 'home':
				$meta['title']       = self::with_brand( __( 'Random Fact Generator: Facts With Sources', 'factfusion-ai' ), true );
				$meta['description'] = __( 'Get a random fact in one click. Every fact is checked against a named source and shows its source link, the date it was verified and a confidence score.', 'factfusion-ai' );
				break;

			case 'fact':
				$fact                = FactFusion_Router::current_fact();
				$meta['title']       = self::with_brand( $fact->title );
				$meta['description'] = self::clip( '' !== trim( $fact->short_answer ) ? $fact->short_answer : $fact->claim );
				$meta['canonical']   = FactFusion_Facts::url( $fact );
				$meta['index']       = 'published' === $fact->status && 'verified' === $fact->verification_status && (bool) $fact->is_indexable;
				$meta['type']        = 'article';
				break;

			case 'fotd':
				$fact                = FactFusion_Router::current_fact();
				$meta['title']       = self::with_brand( __( 'Fact of the Day: Daily Random Fact', 'factfusion-ai' ), true );
				/* translators: %s: the fact's claim. */
				$meta['description'] = self::clip( sprintf( __( 'Today\'s fact: %s', 'factfusion-ai' ), $fact->claim ) );
				$meta['canonical']   = home_url( user_trailingslashit( 'fact-of-the-day' ) );
				break;

			case 'browse':
				$browse   = FactFusion_Router::browse();
				$category = $browse['category'];
				$counts   = FactFusion_Facts::published_counts();
				$total    = $category ? (int) ( $counts[ (int) $category->id ] ?? 0 ) : count( FactFusion_Facts::pool() );
				$pages    = max( 1, (int) ceil( $total / FactFusion_Frontend::BROWSE_PER_PAGE ) );
				/* translators: %d: page number. */
				$suffix = $browse['page'] > 1 ? ' ' . sprintf( __( '(Page %d)', 'factfusion-ai' ), $browse['page'] ) : '';

				if ( '' !== $browse['search'] ) {
					/* translators: %s: search text. */
					$meta['title']     = self::with_brand( sprintf( __( 'Facts matching "%s"', 'factfusion-ai' ), $browse['search'] ) );
					$meta['canonical'] = FactFusion_Router::browse_url();
					$meta['index']     = false;
				} elseif ( $category ) {
					/* translators: %s: category name. */
					$title               = '' !== $category->meta_title ? $category->meta_title : sprintf( __( '%s Facts With Sources', 'factfusion-ai' ), $category->name );
					if ( '' !== $suffix ) {
						/* translators: %s: category name. */
						$title = sprintf( __( '%s Facts', 'factfusion-ai' ), $category->name );
					}
					$meta['title']       = self::with_brand( $title . $suffix, true );
					$meta['description'] = '' !== $category->meta_description ? $category->meta_description : self::clip(
						sprintf(
							/* translators: 1: number of facts, 2: category name. */
							_n( '%1$d verified %2$s fact with a link to its source.', '%1$d verified %2$s facts, each with a link to its source.', $total, 'factfusion-ai' ),
							$total,
							$category->name
						) . ' ' . $category->description
					);
					$meta['canonical']   = FactFusion_Router::browse_url( $category, $browse['page'] );
					$meta['index']       = $total >= self::THIN_CATEGORY && $browse['page'] <= $pages;
				} else {
					$meta['title']       = self::with_brand( ( '' !== $suffix ? __( 'All Facts', 'factfusion-ai' ) : __( 'All Facts: Browse Verified Facts by Category', 'factfusion-ai' ) ) . $suffix, true );
					$meta['description'] = sprintf(
						/* translators: 1: number of facts, 2: number of categories. */
						__( 'Browse %1$d verified facts in %2$d categories. Every fact links to the source it was checked against and shows when it was last verified.', 'factfusion-ai' ),
						$total,
						count( $counts )
					);
					$meta['canonical']   = FactFusion_Router::browse_url( null, $browse['page'] );
					$meta['index']       = $browse['page'] <= $pages;
				}
				break;

			case 'author':
				$profile  = FactFusion_Router::profile();
				$role     = FactFusion_Profiles::role( $profile );
				$bio      = (string) get_user_meta( $profile->ID, 'description', true );
				$verified = FactFusion_Facts::query(
					array(
						'status'    => 'published',
						'author_id' => $profile->ID,
						'per_page'  => 1,
					)
				);
				$meta['title']       = self::with_brand( '' !== $role ? $profile->display_name . ', ' . $role : $profile->display_name, true );
				$meta['description'] = '' !== trim( $bio ) ? self::clip( $bio ) : sprintf(
					/* translators: 1: person's name, 2: number of facts, 3: site name. */
					_n( '%1$s has verified %2$d fact on %3$s.', '%1$s has verified %2$d facts on %3$s.', $verified['total'], 'factfusion-ai' ),
					$profile->display_name,
					$verified['total'],
					$brand
				);
				$meta['canonical']   = FactFusion_Profiles::url( $profile );
				$meta['type']        = 'profile';
				break;

			case 'article':
			case 'tool':
				$content             = FactFusion_Content::current();
				$title               = trim( (string) get_post_meta( $content->ID, FactFusion_Content::TITLE_META, true ) );
				$description         = trim( (string) get_post_meta( $content->ID, FactFusion_Content::DESCRIPTION_META, true ) );
				$meta['title']       = self::with_brand( '' !== $title ? $title : get_the_title( $content ) );
				$meta['description'] = self::clip( '' !== $description ? $description : $content->post_content, 160 );
				$meta['canonical']   = get_permalink( $content );
				$meta['type']        = 'article' === $view ? 'article' : 'website';
				break;
		}

		self::$meta = (array) apply_filters( 'factfusion_seo_meta', $meta, $view );
		return self::$meta;
	}

	/**
	 * Supplies the document title on the plugin's pages.
	 *
	 * @param string $title Title chosen so far.
	 * @return string
	 */
	public static function title( $title ) {
		$meta = self::meta();
		// WordPress prints this value as it is, so it is escaped here.
		return $meta ? esc_html( $meta['title'] ) : $title;
	}

	/**
	 * Sets the robots rules on the plugin's pages.
	 *
	 * @param array $robots Robots directives.
	 * @return array
	 */
	public static function robots( $robots ) {
		$meta = self::meta();
		if ( ! $meta ) {
			return $robots;
		}
		if ( ! $meta['index'] ) {
			$robots['noindex'] = true;
			if ( empty( $robots['nofollow'] ) ) {
				$robots['follow'] = true;
			}
			unset( $robots['max-image-preview'], $robots['index'] );
		} elseif ( empty( $robots['noindex'] ) ) {
			$robots['max-snippet'] = '-1';
		}
		return $robots;
	}

	/**
	 * Prints description, canonical, Open Graph, Twitter and JSON-LD tags.
	 */
	public static function head() {
		$meta = self::meta();
		if ( ! $meta ) {
			return;
		}
		// WordPress would print its own canonical on a static front page.
		remove_action( 'wp_head', 'rel_canonical' );

		$brand = get_bloginfo( 'name' );
		echo "\n<!-- FactFusion AI SEO -->\n";
		if ( 'home' === self::view() ) {
			self::print_verification_tags();
		}
		if ( '' !== $meta['description'] ) {
			printf( '<meta name="description" content="%s">' . "\n", esc_attr( $meta['description'] ) );
		}
		if ( $meta['index'] ) {
			printf( '<link rel="canonical" href="%s">' . "\n", esc_url( $meta['canonical'] ) );
		}

		$tags = array(
			'og:locale'    => get_locale(),
			'og:type'      => $meta['type'],
			'og:title'     => $meta['title'],
			'og:url'       => $meta['canonical'],
			'og:site_name' => $brand,
		);
		if ( '' !== $meta['description'] ) {
			$tags['og:description'] = $meta['description'];
		}
		// A wide share image; filter this to use your own. A new file name makes
		// social sites fetch it again; the old og-default.png is kept for old links.
		$image = (string) apply_filters( 'factfusion_share_image', FACTFUSION_URL . 'assets/og-image.png' );
		if ( '' !== $image ) {
			$tags['og:image']        = $image;
			$tags['og:image:width']  = '1200';
			$tags['og:image:height'] = '630';
		}
		if ( 'fact' === self::view() ) {
			$fact                           = FactFusion_Router::current_fact();
			$tags['article:published_time'] = self::iso( $fact->date_created );
			$tags['article:modified_time']  = self::iso( $fact->date_updated );
		}
		if ( 'article' === self::view() ) {
			$content                        = FactFusion_Content::current();
			$tags['article:published_time'] = self::iso( $content->post_date_gmt );
			$tags['article:modified_time']  = self::iso( $content->post_modified_gmt );
		}
		foreach ( $tags as $property => $content ) {
			printf( '<meta property="%s" content="%s">' . "\n", esc_attr( $property ), esc_attr( $content ) );
		}
		echo '<meta name="twitter:card" content="summary_large_image">' . "\n";

		if ( $meta['index'] ) {
			echo '<script type="application/ld+json">' . wp_json_encode( self::schema( $meta ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON with tags hex-encoded.
		}
		echo "<!-- /FactFusion AI SEO -->\n";
	}

	/**
	 * Site-ownership tags for search consoles. Rank Math prints these only on
	 * the homepage, and the homepage is one of the plugin's own pages where
	 * Rank Math is switched off, so the plugin prints the codes saved in
	 * Rank Math > General Settings > Webmaster Tools itself.
	 */
	private static function print_verification_tags() {
		$options = (array) get_option( 'rank-math-options-general', array() );
		$tags    = (array) apply_filters(
			'factfusion_verification_tags',
			array(
				'google_verify'    => 'google-site-verification',
				'bing_verify'      => 'msvalidate.01',
				'baidu_verify'     => 'baidu-site-verification',
				'yandex_verify'    => 'yandex-verification',
				'pinterest_verify' => 'p:domain_verify',
				'norton_verify'    => 'norton-safeweb-site-verification',
			)
		);
		foreach ( $tags as $key => $name ) {
			$code = trim( (string) ( $options[ $key ] ?? '' ) );
			// The field may hold the whole tag or only the code; keep only the code.
			if ( preg_match( '/content=["\']([^"\']+)["\']/', $code, $match ) ) {
				$code = $match[1];
			}
			$code = preg_replace( '/[^A-Za-z0-9_\-.:=+\/]/', '', $code );
			if ( '' !== $code ) {
				printf( '<meta name="%s" content="%s">' . "\n", esc_attr( $name ), esc_attr( $code ) );
			}
		}
	}

	/**
	 * A stored UTC datetime in ISO 8601.
	 *
	 * @param string $datetime UTC datetime.
	 * @return string
	 */
	private static function iso( $datetime ) {
		return gmdate( 'Y-m-d\TH:i:s+00:00', strtotime( $datetime . ' UTC' ) );
	}

	/**
	 * Schema.org Person for an editor.
	 *
	 * @param WP_User $user Editor.
	 * @return array
	 */
	private static function person( $user ) {
		$url    = FactFusion_Profiles::url( $user );
		$person = array(
			'@type' => 'Person',
			'@id'   => ( $url ? $url : home_url( '/' ) ) . '#person-' . (int) $user->ID,
			'name'  => $user->display_name,
		);
		if ( $url ) {
			$person['url'] = $url;
		}
		$role = FactFusion_Profiles::role( $user );
		if ( '' !== $role ) {
			$person['jobTitle'] = $role;
		}
		$linkedin = FactFusion_Profiles::linkedin( $user );
		if ( '' !== $linkedin ) {
			$person['sameAs'] = array( $linkedin );
		}
		$bio = trim( (string) get_user_meta( $user->ID, 'description', true ) );
		if ( '' !== $bio ) {
			$person['description'] = self::clip( $bio, 300 );
		}
		$person['worksFor'] = array( '@id' => home_url( '/' ) . '#organization' );
		return $person;
	}

	/**
	 * The JSON-LD graph for this page. Only data that is visible on the page,
	 * or true of the site, is described. No ClaimReview is ever emitted,
	 * because the site does not publish fact-check reviews of other people's claims.
	 *
	 * @param array $meta Tags for this page.
	 * @return array
	 */
	public static function schema( $meta ) {
		$view     = self::view();
		$home     = home_url( '/' );
		$brand    = get_bloginfo( 'name' );
		$language = get_bloginfo( 'language' );
		$org_id   = $home . '#organization';
		$site_id  = $home . '#website';
		$page_id  = $meta['canonical'] . '#webpage';
		$crumb_id = $meta['canonical'] . '#breadcrumb';

		$organization = array(
			'@type' => 'Organization',
			'@id'   => $org_id,
			'name'  => $brand,
			'url'   => $home,
		);
		$icon         = get_site_icon_url( 512 );
		if ( $icon ) {
			$organization['logo'] = array(
				'@type' => 'ImageObject',
				'url'   => $icon,
			);
		}

		$website = array(
			'@type'           => 'WebSite',
			'@id'             => $site_id,
			'url'             => $home,
			'name'            => $brand,
			'publisher'       => array( '@id' => $org_id ),
			'inLanguage'      => $language,
			'potentialAction' => array(
				'@type'       => 'SearchAction',
				'target'      => array(
					'@type'       => 'EntryPoint',
					'urlTemplate' => FactFusion_Router::browse_url() . '?q={search_term_string}',
				),
				'query-input' => 'required name=search_term_string',
			),
		);
		$tagline = get_bloginfo( 'description' );
		if ( '' !== $tagline ) {
			$website['description'] = $tagline;
		}

		$page = array(
			'@type'       => 'WebPage',
			'@id'         => $page_id,
			'url'         => $meta['canonical'],
			'name'        => $meta['title'],
			'isPartOf'    => array( '@id' => $site_id ),
			'inLanguage'  => $language,
		);
		if ( '' !== $meta['description'] ) {
			$page['description'] = $meta['description'];
		}

		$graph = array( $organization, $website );
		$extra = array();

		$trail = self::trail();
		if ( count( $trail ) > 1 ) {
			$items = array();
			foreach ( $trail as $position => $crumb ) {
				$items[] = array(
					'@type'    => 'ListItem',
					'position' => $position + 1,
					'name'     => $crumb[0],
					'item'     => $crumb[1],
				);
			}
			$page['breadcrumb'] = array( '@id' => $crumb_id );
			$extra[]            = array(
				'@type'           => 'BreadcrumbList',
				'@id'             => $crumb_id,
				'itemListElement' => $items,
			);
		}

		switch ( $view ) {
			case 'home':
				$questions = array();
				foreach ( self::faq() as $pair ) {
					$questions[] = array(
						'@type'          => 'Question',
						'name'           => $pair[0],
						'acceptedAnswer' => array(
							'@type' => 'Answer',
							'text'  => $pair[1],
						),
					);
				}
				if ( $questions ) {
					$extra[] = array(
						'@type'      => 'FAQPage',
						'@id'        => $home . '#faq',
						'isPartOf'   => array( '@id' => $page_id ),
						'mainEntity' => $questions,
					);
				}
				break;

			case 'fact':
				$fact     = FactFusion_Router::current_fact();
				$url      = FactFusion_Facts::url( $fact );
				$category = $fact->category_id ? FactFusion_Categories::get( $fact->category_id ) : null;
				$author   = $fact->author_id ? get_userdata( $fact->author_id ) : false;
				$term_id  = $url . '#term';

				$page['datePublished'] = self::iso( $fact->date_created );
				$page['dateModified']  = self::iso( $fact->date_updated );
				if ( $fact->date_verified ) {
					$page['lastReviewed'] = gmdate( 'Y-m-d', strtotime( $fact->date_verified . ' UTC' ) );
				}

				$citations = array();
				foreach ( FactFusion_Sources::for_fact( $fact->id ) as $source ) {
					$citation = array(
						'@type' => 'CreativeWork',
						'name'  => '' !== $source->title ? $source->title : $source->source_name,
						'url'   => $source->url,
					);
					$citation['publisher'] = array(
						'@type' => 'Organization',
						'name'  => '' !== $source->publisher ? $source->publisher : $source->source_name,
					);
					$citations[]           = $citation;
				}

				$article = array(
					'@type'            => 'Article',
					'@id'              => $url . '#article',
					'headline'         => mb_substr( $fact->title, 0, 110 ),
					'description'      => $meta['description'],
					'datePublished'    => self::iso( $fact->date_created ),
					'dateModified'     => self::iso( $fact->date_updated ),
					'mainEntityOfPage' => array( '@id' => $page_id ),
					'publisher'        => array( '@id' => $org_id ),
					'author'           => array( '@id' => $org_id ),
					'inLanguage'       => $language,
					'about'            => array( '@id' => $term_id ),
				);
				if ( $category ) {
					$article['articleSection'] = $category->name;
				}
				if ( $citations ) {
					$article['citation'] = $citations;
				}
				if ( $author ) {
					$person             = self::person( $author );
					$article['author']  = array( '@id' => $person['@id'] );
					$page['reviewedBy'] = array( '@id' => $person['@id'] );
					$extra[]            = $person;
				}

				// The fact itself, as a defined entry in its category's set of facts.
				$term = array(
					'@type'       => 'DefinedTerm',
					'@id'         => $term_id,
					'name'        => $fact->title,
					'description' => $fact->claim,
					'url'         => $url,
					'termCode'    => 'FF-' . (int) $fact->fact_number,
				);
				if ( $category ) {
					$term['inDefinedTermSet'] = array(
						'@type' => 'DefinedTermSet',
						'@id'   => FactFusion_Router::browse_url( $category ) . '#termset',
						/* translators: %s: category name. */
						'name'  => sprintf( __( '%s Facts', 'factfusion-ai' ), $category->name ),
						'url'   => FactFusion_Router::browse_url( $category ),
					);
				}
				$extra[] = $article;
				$extra[] = $term;
				break;

			case 'fotd':
				$fact          = FactFusion_Router::current_fact();
				$page['about'] = array(
					'@type'       => 'DefinedTerm',
					'name'        => $fact->title,
					'description' => $fact->claim,
					'url'         => FactFusion_Facts::url( $fact ),
				);
				break;

			case 'browse':
				$browse        = FactFusion_Router::browse();
				$page['@type'] = 'CollectionPage';
				$list          = FactFusion_Facts::query(
					array(
						'status'      => 'published',
						'category_id' => $browse['category'] ? (int) $browse['category']->id : 0,
						'paged'       => $browse['page'],
						'per_page'    => FactFusion_Frontend::BROWSE_PER_PAGE,
						'orderby'     => 'fact_number',
						'order'       => 'desc',
					)
				);
				$elements      = array();
				foreach ( $list['items'] as $position => $item ) {
					$elements[] = array(
						'@type'    => 'ListItem',
						'position' => $position + 1,
						'name'     => $item->title,
						'url'      => FactFusion_Facts::url( $item ),
					);
				}
				if ( $elements ) {
					$page['mainEntity'] = array( '@id' => $meta['canonical'] . '#list' );
					$extra[]            = array(
						'@type'           => 'ItemList',
						'@id'             => $meta['canonical'] . '#list',
						'numberOfItems'   => count( $elements ),
						'itemListElement' => $elements,
					);
				}
				// A category landing page shows its questions and answers; describe the same list.
				if ( $browse['category'] && 1 === $browse['page'] && '' === $browse['search'] ) {
					$questions = array();
					foreach ( FactFusion_Categories::faq( $browse['category'] ) as $pair ) {
						$questions[] = array(
							'@type'          => 'Question',
							'name'           => $pair[0],
							'acceptedAnswer' => array(
								'@type' => 'Answer',
								'text'  => $pair[1],
							),
						);
					}
					if ( $questions ) {
						$extra[] = array(
							'@type'      => 'FAQPage',
							'@id'        => $meta['canonical'] . '#faq',
							'isPartOf'   => array( '@id' => $page_id ),
							'mainEntity' => $questions,
						);
					}
				}
				break;

			case 'author':
				$person             = self::person( FactFusion_Router::profile() );
				$page['@type']      = 'ProfilePage';
				$page['mainEntity'] = array( '@id' => $person['@id'] );
				$extra[]            = $person;
				break;

			case 'article':
			case 'tool':
				$content               = FactFusion_Content::current();
				$url                   = $meta['canonical'];
				$author                = get_userdata( $content->post_author );
				$person                = $author && FactFusion_Profiles::url( $author ) ? self::person( $author ) : null;
				$page['datePublished'] = self::iso( $content->post_date_gmt );
				$page['dateModified']  = self::iso( $content->post_modified_gmt );
				if ( $person ) {
					$extra[] = $person;
				}

				if ( 'article' === $view ) {
					$article = array(
						'@type'            => 'Article',
						'@id'              => $url . '#article',
						'headline'         => mb_substr( get_the_title( $content ), 0, 110 ),
						'description'      => $meta['description'],
						'datePublished'    => self::iso( $content->post_date_gmt ),
						'dateModified'     => self::iso( $content->post_modified_gmt ),
						'mainEntityOfPage' => array( '@id' => $page_id ),
						'publisher'        => array( '@id' => $org_id ),
						'author'           => $person ? array( '@id' => $person['@id'] ) : array( '@id' => $org_id ),
						'inLanguage'       => $language,
						'wordCount'        => str_word_count( wp_strip_all_tags( strip_shortcodes( $content->post_content ) ) ),
					);
					// The sources of the facts cited in the article, taken from the fact tables.
					$citations = array();
					if ( preg_match_all( '/\[factfusion_fact\s+slug="([^"]+)"/', (string) $content->post_content, $found ) ) {
						foreach ( array_unique( $found[1] ) as $slug ) {
							$fact = FactFusion_Facts::get_by_slug( sanitize_title( $slug ) );
							if ( ! $fact || 'published' !== $fact->status ) {
								continue;
							}
							foreach ( FactFusion_Sources::for_fact( $fact->id ) as $source ) {
								$citations[ $source->url ] = array(
									'@type' => 'CreativeWork',
									'name'  => '' !== $source->title ? $source->title : $source->source_name,
									'url'   => $source->url,
								);
							}
						}
					}
					if ( $citations ) {
						$article['citation'] = array_values( $citations );
					}
					$extra[] = $article;
				} elseif ( $person ) {
					$page['reviewedBy'] = array( '@id' => $person['@id'] );
				}

				$questions = array();
				foreach ( FactFusion_Content::faq( $content ) as $pair ) {
					$questions[] = array(
						'@type'          => 'Question',
						'name'           => $pair[0],
						'acceptedAnswer' => array(
							'@type' => 'Answer',
							'text'  => $pair[1],
						),
					);
				}
				if ( $questions ) {
					$extra[] = array(
						'@type'      => 'FAQPage',
						'@id'        => $url . '#faq',
						'isPartOf'   => array( '@id' => $page_id ),
						'mainEntity' => $questions,
					);
				}
				break;
		}

		$graph[] = $page;
		return array(
			'@context' => 'https://schema.org',
			'@graph'   => array_merge( $graph, $extra ),
		);
	}

	/**
	 * Every page the plugin wants search engines to know about.
	 *
	 * @return array<int,array{0:string,1:string}> URL and last-modified time.
	 */
	public static function sitemap_urls() {
		$facts  = FactFusion_Facts::indexable();
		$latest = '';
		$newest = array();
		foreach ( $facts as $fact ) {
			$latest                             = max( $latest, (string) $fact->date_updated );
			$newest[ (int) $fact->category_id ] = max( $newest[ (int) $fact->category_id ] ?? '', (string) $fact->date_updated );
		}
		$stamp = '' !== $latest ? self::iso( $latest ) : gmdate( 'Y-m-d\TH:i:s+00:00' );

		$urls   = array(
			array( home_url( '/' ), $stamp ),
			array( FactFusion_Router::browse_url(), $stamp ),
			array( home_url( user_trailingslashit( 'fact-of-the-day' ) ), gmdate( 'Y-m-d\T00:00:00+00:00' ) ),
		);
		$counts = FactFusion_Facts::published_counts();
		foreach ( FactFusion_Categories::all( true ) as $category ) {
			if ( (int) ( $counts[ (int) $category->id ] ?? 0 ) >= self::THIN_CATEGORY ) {
				$urls[] = array( FactFusion_Router::browse_url( $category ), isset( $newest[ (int) $category->id ] ) ? self::iso( $newest[ (int) $category->id ] ) : $stamp );
			}
		}
		foreach ( FactFusion_Profiles::all() as $profile ) {
			$urls[] = array( FactFusion_Profiles::url( $profile ), $stamp );
		}
		foreach ( $facts as $fact ) {
			$urls[] = array( FactFusion_Facts::url( $fact ), self::iso( $fact->date_updated ) );
		}
		return $urls;
	}

	/**
	 * Sends /sitemap-facts.xml and stops.
	 */
	public static function send_sitemap() {
		status_header( 200 );
		header( 'Content-Type: application/xml; charset=UTF-8' );
		header( 'X-Robots-Tag: noindex, follow' );
		echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
		foreach ( self::sitemap_urls() as $entry ) {
			printf( "\t<url><loc>%s</loc><lastmod>%s</lastmod></url>\n", esc_url( $entry[0] ), esc_html( $entry[1] ) );
		}
		echo '</urlset>' . "\n";
		exit;
	}

	/**
	 * URL of the facts sitemap.
	 *
	 * @return string
	 */
	public static function sitemap_url() {
		// Not "facts-sitemap.xml": SEO plugins claim every address ending in "-sitemap.xml".
		return home_url( '/sitemap-facts.xml' );
	}

	/**
	 * Adds the facts sitemap to Rank Math's sitemap index.
	 *
	 * @param string $xml Extra index entries.
	 * @return string
	 */
	public static function rank_math_index( $xml ) {
		return $xml . sprintf( "\t<sitemap>\n\t\t<loc>%s</loc>\n\t\t<lastmod>%s</lastmod>\n\t</sitemap>\n", esc_url( self::sitemap_url() ), esc_html( gmdate( 'Y-m-d\TH:i:s+00:00' ) ) );
	}

	/**
	 * Adds the facts sitemap and the random-redirect rule to robots.txt.
	 *
	 * @param string $output robots.txt so far.
	 * @param bool   $public Whether the site is open to search engines.
	 * @return string
	 */
	public static function robots_txt( $output, $public ) {
		if ( ! $public ) {
			return $output;
		}
		$random  = (string) wp_parse_url( home_url( '/random-fact/' ), PHP_URL_PATH );
		$output  = rtrim( $output ) . "\n\n";
		// /random-fact/ only redirects to a random fact; crawling it is wasted effort.
		$output .= "User-agent: *\nDisallow: {$random}\n\n";
		$output .= 'Sitemap: ' . self::sitemap_url() . "\n";
		return $output;
	}
}
