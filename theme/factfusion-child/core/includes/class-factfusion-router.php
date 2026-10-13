<?php
/**
 * Public URLs.
 *
 * @package FactFusion_AI_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Serves facts from the custom tables through the theme's templates:
 * /fact/{slug}/, /fact-of-the-day/, /facts/, /random-fact/ and /embed/.
 * No post type is involved.
 */
final class FactFusion_Router {

	const QUERY_VAR = 'ff_fact';

	const VIEW_VAR = 'ff_view';

	/**
	 * Bump when rewrite rules change so they are rebuilt after an update.
	 */
	const RULES_VERSION = '5';

	/**
	 * The fact being displayed on this request.
	 *
	 * @var object|null
	 */
	private static $fact = null;

	/**
	 * Which plugin page this request is: fact, fotd, browse, or empty.
	 *
	 * @var string
	 */
	private static $view = '';

	/**
	 * Category, page and search for the /facts/ page.
	 *
	 * @var array|null
	 */
	private static $browse = null;

	/**
	 * The editor whose public profile is being displayed.
	 *
	 * @var WP_User|null
	 */
	private static $profile = null;

	/**
	 * Which plugin page this request is: fact, fotd, browse, author, or empty.
	 *
	 * @return string
	 */
	public static function view() {
		return self::$view;
	}

	/**
	 * The editor profile on this request, or null.
	 *
	 * @return WP_User|null
	 */
	public static function profile() {
		return self::$profile;
	}

	/**
	 * Registers hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'add_rewrite_rules' ) );
		add_action( 'init', array( __CLASS__, 'maybe_flush_rules' ), 99 );
		add_action( 'init', array( __CLASS__, 'register_content_sources' ) );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_filter( 'posts_pre_query', array( __CLASS__, 'skip_posts_query' ), 10, 2 );
		add_filter( 'pre_handle_404', array( __CLASS__, 'resolve' ), 10, 2 );
		add_filter( 'index_template_hierarchy', array( __CLASS__, 'template_hierarchy' ) );
		add_filter( 'document_title_parts', array( __CLASS__, 'document_title' ) );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
		add_filter( 'redirect_canonical', array( __CLASS__, 'keep_plugin_url' ) );
		add_action( 'template_redirect', array( __CLASS__, 'template_redirect' ) );
	}

	/**
	 * Adds the plugin's URL rules. Specific rules come before general ones.
	 */
	public static function add_rewrite_rules() {
		$view = 'index.php?' . self::VIEW_VAR . '=';
		add_rewrite_rule( '^fact/([^/]+)/?$', 'index.php?' . self::QUERY_VAR . '=$matches[1]', 'top' );
		add_rewrite_rule( '^fact-of-the-day/?$', $view . 'fotd', 'top' );
		add_rewrite_rule( '^random-fact/?$', $view . 'random', 'top' );
		add_rewrite_rule( '^embed/random/?$', $view . 'embed_random', 'top' );
		add_rewrite_rule( '^embed/fact/([^/]+)/?$', $view . 'embed_fact&' . self::QUERY_VAR . '=$matches[1]', 'top' );
		add_rewrite_rule( '^sitemap-facts\.xml$', $view . 'sitemap', 'top' );
		add_rewrite_rule( '^author/([^/]+)/?$', $view . 'author&ff_author=$matches[1]', 'top' );
		add_rewrite_rule( '^facts/?$', $view . 'browse', 'top' );
		add_rewrite_rule( '^facts/page/([0-9]+)/?$', $view . 'browse&ff_page=$matches[1]', 'top' );
		add_rewrite_rule( '^facts/([^/]+)/page/([0-9]+)/?$', $view . 'browse&ff_cat=$matches[1]&ff_page=$matches[2]', 'top' );
		add_rewrite_rule( '^facts/([^/]+)/?$', $view . 'browse&ff_cat=$matches[1]', 'top' );
	}

	/**
	 * Rebuilds WordPress's stored rules once after the plugin's rules change.
	 */
	public static function maybe_flush_rules() {
		if ( get_option( 'factfusion_rules_version' ) !== self::RULES_VERSION ) {
			flush_rewrite_rules();
			update_option( 'factfusion_rules_version', self::RULES_VERSION );
		}
	}

	/**
	 * Allows the plugin's query variables.
	 *
	 * @param string[] $vars Public query variables.
	 * @return string[]
	 */
	public static function query_vars( $vars ) {
		array_push( $vars, self::QUERY_VAR, self::VIEW_VAR, 'ff_cat', 'ff_page', 'ff_author' );
		return $vars;
	}

	/**
	 * The fact on this request, or null.
	 *
	 * @return object|null
	 */
	public static function current_fact() {
		return self::$fact;
	}

	/**
	 * The /facts/ page state, or null on other pages.
	 *
	 * @return array{category:object|null,page:int,search:string}|null
	 */
	public static function browse() {
		return self::$browse;
	}

	/**
	 * URL of a /facts/ page.
	 *
	 * @param object|null $category Category row.
	 * @param int         $page     Page number.
	 * @param string      $search   Search text.
	 * @return string
	 */
	public static function browse_url( $category = null, $page = 1, $search = '' ) {
		$path = 'facts' . ( $category && '' === $search ? '/' . $category->slug : '' ) . ( $page > 1 ? '/page/' . (int) $page : '' );
		$url  = home_url( user_trailingslashit( $path ) );
		return '' !== $search ? add_query_arg( 'q', rawurlencode( $search ), $url ) : $url;
	}

	/**
	 * Whether the current visitor may see this fact.
	 *
	 * @param object $fact Fact row.
	 * @return bool
	 */
	private static function can_view( $fact ) {
		return 'published' === $fact->status || current_user_can( factfusion_capability() );
	}

	/**
	 * Whether a query belongs to one of the plugin's URLs.
	 *
	 * @param WP_Query $query Query.
	 * @return bool
	 */
	private static function is_plugin_query( $query ) {
		return '' !== (string) $query->get( self::QUERY_VAR ) || '' !== (string) $query->get( self::VIEW_VAR );
	}

	/**
	 * Stops WordPress loading blog posts on a plugin URL.
	 *
	 * @param array|null $posts Short-circuit value.
	 * @param WP_Query   $query Query.
	 * @return array|null
	 */
	public static function skip_posts_query( $posts, $query ) {
		return $query->is_main_query() && self::is_plugin_query( $query ) ? array() : $posts;
	}

	/**
	 * Marks the request as not found.
	 *
	 * @param WP_Query $wp_query Main query.
	 * @return bool
	 */
	private static function not_found( $wp_query ) {
		self::$fact    = null;
		self::$view    = '';
		self::$browse  = null;
		self::$profile = null;
		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
		return true;
	}

	/**
	 * Works out which plugin page was requested and whether it exists.
	 *
	 * @param bool     $handled  Whether 404 handling is already done.
	 * @param WP_Query $wp_query Main query.
	 * @return bool
	 */
	public static function resolve( $handled, $wp_query ) {
		if ( ! self::is_plugin_query( $wp_query ) ) {
			return $handled;
		}

		$view = sanitize_key( (string) $wp_query->get( self::VIEW_VAR ) );
		$slug = sanitize_title( (string) $wp_query->get( self::QUERY_VAR ) );

		switch ( $view ) {
			case '':
			case 'embed_fact':
				$fact = '' !== $slug ? FactFusion_Facts::get_by_slug( $slug ) : null;
				if ( ! $fact || ! self::can_view( $fact ) || ( 'embed_fact' === $view && 'published' !== $fact->status ) ) {
					return self::not_found( $wp_query );
				}
				self::$fact = $fact;
				self::$view = '' === $view ? 'fact' : 'embed_fact';
				break;

			case 'fotd':
				$fact = FactFusion_Frontend::fact_of_the_day();
				if ( ! $fact ) {
					return self::not_found( $wp_query );
				}
				self::$fact = $fact;
				self::$view = 'fotd';
				break;

			case 'random':
			case 'embed_random':
			case 'sitemap':
				self::$view = $view;
				break;

			case 'author':
				$profile = FactFusion_Profiles::get_by_slug( sanitize_title( (string) $wp_query->get( 'ff_author' ) ) );
				if ( ! $profile ) {
					return self::not_found( $wp_query );
				}
				self::$profile = $profile;
				self::$view    = 'author';
				break;

			case 'browse':
				$category = null;
				$cat_slug = sanitize_title( (string) $wp_query->get( 'ff_cat' ) );
				if ( '' !== $cat_slug ) {
					$category = FactFusion_Categories::get_by_slug( $cat_slug );
					if ( ! $category || ! $category->is_active ) {
						return self::not_found( $wp_query );
					}
				}
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public search box.
				$search       = isset( $_GET['q'] ) ? mb_substr( trim( sanitize_text_field( wp_unslash( $_GET['q'] ) ) ), 0, 100 ) : '';
				self::$browse = array(
					'category' => $category,
					'page'     => max( 1, absint( $wp_query->get( 'ff_page' ) ) ),
					'search'   => $search,
				);
				self::$view   = 'browse';
				break;

			default:
				return self::not_found( $wp_query );
		}

		$wp_query->is_home = false;
		$wp_query->is_404  = false;
		status_header( 200 );
		return true;
	}

	/**
	 * Handles the URLs that do not render a theme template: redirects and embeds.
	 */
	public static function template_redirect() {
		switch ( self::$view ) {
			case 'random':
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only avoids repeating a fact.
				$from = isset( $_GET['from'] ) ? absint( $_GET['from'] ) : 0;
				$fact = FactFusion_Facts::random_published( self::requested_category(), $from );
				FactFusion_Frontend::no_cache();
				header( 'X-Robots-Tag: noindex' );
				wp_safe_redirect( $fact ? FactFusion_Facts::url( $fact ) : home_url( '/' ), 302 );
				exit;

			case 'embed_random':
				$allowed = FactFusion_Rate_Limiter::allow( 'fact' );
				FactFusion_Frontend::send_embed_document( is_wp_error( $allowed ) ? null : FactFusion_Facts::random_published( self::requested_category() ), true );
				break;

			case 'embed_fact':
				FactFusion_Frontend::send_embed_document( self::$fact, false );
				break;

			case 'fact':
				self::canonical_redirect( FactFusion_Facts::url( self::$fact ) );
				if ( 'published' === self::$fact->status && ! current_user_can( factfusion_capability() ) ) {
					FactFusion_Facts::bump_hits( self::$fact->id );
				}
				break;

			case 'fotd':
				self::canonical_redirect( home_url( user_trailingslashit( 'fact-of-the-day' ) ) );
				break;

			case 'browse':
				self::canonical_redirect( self::browse_url( self::$browse['category'], self::$browse['page'], self::$browse['search'] ) );
				break;

			case 'author':
				self::canonical_redirect( FactFusion_Profiles::url( self::$profile ) );
				break;

			case 'sitemap':
				FactFusion_SEO::send_sitemap();
				break;
		}
	}

	/**
	 * The active category named in "?category=slug", as an ID. Zero means any category.
	 *
	 * @return int
	 */
	private static function requested_category() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only narrows a random pick.
		$slug     = isset( $_GET['category'] ) ? sanitize_title( wp_unslash( $_GET['category'] ) ) : '';
		$category = '' !== $slug ? FactFusion_Categories::get_by_slug( $slug ) : null;
		return $category && $category->is_active ? (int) $category->id : 0;
	}

	/**
	 * Sends every variant of a page's address to its one canonical URL.
	 * Only the path is compared, so tracking parameters are left alone.
	 *
	 * @param string $canonical Canonical URL.
	 */
	private static function canonical_redirect( $canonical ) {
		if ( empty( $_SERVER['REQUEST_URI'] ) ) {
			return;
		}
		$requested = wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH );
		if ( wp_parse_url( $canonical, PHP_URL_PATH ) !== $requested ) {
			wp_safe_redirect( $canonical, 301 );
			exit;
		}
	}

	/**
	 * Sends plugin pages to the matching theme template.
	 *
	 * @param string[] $templates Candidate template files.
	 * @return string[]
	 */
	public static function template_hierarchy( $templates ) {
		if ( 'fact' === self::$view || 'fotd' === self::$view ) {
			array_unshift( $templates, 'single-fact.php' );
		} elseif ( 'browse' === self::$view || 'author' === self::$view ) {
			array_unshift( $templates, 'facts.php' );
		}
		return $templates;
	}

	/**
	 * Heading of the /facts/ page.
	 *
	 * @return string
	 */
	private static function browse_title() {
		if ( '' !== self::$browse['search'] ) {
			/* translators: %s: search text. */
			return sprintf( __( 'Facts matching "%s"', 'factfusion-ai' ), self::$browse['search'] );
		}
		if ( self::$browse['category'] ) {
			/* translators: %s: category name. */
			return sprintf( __( '%s Facts', 'factfusion-ai' ), self::$browse['category']->name );
		}
		return __( 'All Facts', 'factfusion-ai' );
	}

	/**
	 * Sets the document title on plugin pages.
	 *
	 * @param array $parts Title parts.
	 * @return array
	 */
	public static function document_title( $parts ) {
		if ( 'fact' === self::$view ) {
			$parts['title'] = self::$fact->title;
		} elseif ( 'fotd' === self::$view ) {
			/* translators: %s: fact title. */
			$parts['title'] = sprintf( __( 'Fact of the Day: %s', 'factfusion-ai' ), self::$fact->title );
		} elseif ( 'browse' === self::$view ) {
			$parts['title'] = self::browse_title();
			if ( self::$browse['page'] > 1 ) {
				/* translators: %d: page number. */
				$parts['page'] = sprintf( __( 'Page %d', 'factfusion-ai' ), self::$browse['page'] );
			}
		} else {
			return $parts;
		}
		unset( $parts['tagline'] );
		return $parts;
	}

	/**
	 * Adds a body class on plugin pages.
	 *
	 * @param string[] $classes Body classes.
	 * @return string[]
	 */
	public static function body_class( $classes ) {
		if ( self::$fact ) {
			$classes[] = 'ff-fact-page';
		}
		if ( 'browse' === self::$view ) {
			$classes[] = 'ff-browse-page';
		}
		if ( 'author' === self::$view ) {
			$classes[] = 'ff-profile-page';
		}
		return $classes;
	}

	/**
	 * Stops WordPress redirecting a plugin URL to a guessed post.
	 *
	 * @param string|false $redirect Redirect target.
	 * @return string|false
	 */
	public static function keep_plugin_url( $redirect ) {
		return '' !== self::$view || '' !== (string) get_query_var( self::QUERY_VAR ) || '' !== (string) get_query_var( self::VIEW_VAR ) ? false : $redirect;
	}

	/**
	 * Registers the block binding source and shortcodes the theme templates use.
	 */
	public static function register_content_sources() {
		register_block_bindings_source(
			'factfusion/fact',
			array(
				'label'              => __( 'FactFusion fact', 'factfusion-ai' ),
				'get_value_callback' => array( __CLASS__, 'binding_value' ),
			)
		);
		add_shortcode( 'factfusion_fact_explanation', array( __CLASS__, 'shortcode_explanation' ) );
		add_shortcode( 'factfusion_fact_trust', array( __CLASS__, 'shortcode_trust' ) );
	}

	/**
	 * Supplies a value to a bound block in a theme template.
	 *
	 * @param array $args Binding arguments.
	 * @return string|null Null keeps the block's own content.
	 */
	public static function binding_value( $args ) {
		$key = $args['key'] ?? '';

		if ( 'author' === self::$view ) {
			if ( 'browse_title' === $key ) {
				return esc_html( self::$profile->display_name );
			}
			if ( 'browse_intro' === $key ) {
				$role = FactFusion_Profiles::role( self::$profile );
				/* translators: 1: job title, 2: site name. */
				return esc_html( '' !== $role ? sprintf( __( '%1$s, %2$s', 'factfusion-ai' ), $role, get_bloginfo( 'name' ) ) : get_bloginfo( 'name' ) );
			}
			return null;
		}

		if ( 'browse' === self::$view ) {
			if ( 'browse_title' === $key ) {
				return esc_html( self::browse_title() );
			}
			if ( 'browse_intro' === $key ) {
				$category = self::$browse['category'];
				return $category && '' === self::$browse['search'] ? esc_html( $category->description ) : esc_html__( 'Every fact here links to the source it was checked against.', 'factfusion-ai' );
			}
			return null;
		}

		$fact = self::$fact;
		if ( ! $fact ) {
			return null;
		}
		switch ( $key ) {
			case 'title':
			case 'claim':
			case 'short_answer':
				return esc_html( $fact->$key );
			case 'category_name':
				$category = $fact->category_id ? FactFusion_Categories::get( $fact->category_id ) : null;
				if ( ! $category ) {
					return esc_html__( 'Facts', 'factfusion-ai' );
				}
				return $category->is_active
					? sprintf( '<a href="%s">%s</a>', esc_url( self::browse_url( $category ) ), esc_html( $category->name ) )
					: esc_html( $category->name );
			case 'kicker':
				if ( 'fotd' !== self::$view ) {
					return '';
				}
				/* translators: %s: today's date. */
				return esc_html( sprintf( __( 'Fact of the Day · %s', 'factfusion-ai' ), wp_date( get_option( 'date_format' ) ) ) );
		}
		return null;
	}

	/**
	 * Outputs the explanation as paragraphs.
	 *
	 * @return string
	 */
	public static function shortcode_explanation() {
		if ( ! self::$fact ) {
			return '';
		}
		return '<div class="ff-fact__explanation">' . wpautop( wp_kses_post( self::$fact->explanation ) ) . '</div>';
	}

	/**
	 * Outputs the visible trust signals: sources, tier, verified date,
	 * confidence, verifier and the AI disclosure.
	 *
	 * @return string
	 */
	public static function shortcode_trust() {
		$fact = self::$fact;
		if ( ! $fact ) {
			return '';
		}

		$verified = 'verified' === $fact->verification_status;
		$sources  = FactFusion_Sources::for_fact( $fact->id );
		$html     = '<dl class="ff-trust">';

		$html .= '<div><dt>' . esc_html( _n( 'Source', 'Sources', max( 1, count( $sources ) ), 'factfusion-ai' ) ) . '</dt>';
		if ( ! $sources ) {
			$html .= '<dd>' . esc_html__( 'No source attached yet', 'factfusion-ai' ) . '</dd>';
		}
		foreach ( $sources as $source ) {
			$html .= sprintf(
				'<dd><a href="%1$s">%2$s</a> <span class="ff-pill ff-tier ff-tier--%3$s">%4$s</span></dd>',
				esc_url( $source->url ),
				esc_html( $source->source_name ),
				esc_attr( strtolower( $source->tier ) ),
				/* translators: %s: tier letter A to D. */
				esc_html( sprintf( __( 'Tier %s', 'factfusion-ai' ), $source->tier ) )
			);
		}
		$html .= '</div>';

		$html .= '<div><dt>' . esc_html__( 'Last verified', 'factfusion-ai' ) . '</dt><dd>';
		if ( $verified && $fact->date_verified ) {
			$timestamp = strtotime( $fact->date_verified . ' UTC' );
			$html     .= sprintf( '<time datetime="%1$s">%2$s</time>', esc_attr( gmdate( 'Y-m-d', $timestamp ) ), esc_html( wp_date( get_option( 'date_format' ), $timestamp ) ) );
		} else {
			$html .= esc_html__( 'Not yet verified', 'factfusion-ai' );
		}
		$html .= '</dd></div>';

		$html .= '<div><dt>' . esc_html__( 'Confidence', 'factfusion-ai' ) . '</dt><dd>';
		/* translators: %d: confidence score from 0 to 100. */
		$html .= esc_html( sprintf( __( '%d out of 100', 'factfusion-ai' ), (int) $fact->confidence_score ) );
		$html .= '</dd></div>';

		$author = $fact->author_id ? get_userdata( $fact->author_id ) : false;
		if ( $verified && $author ) {
			$profile_url = FactFusion_Profiles::url( $author );
			$name        = esc_html( $author->display_name );
			$html       .= '<div><dt>' . esc_html__( 'Verified by', 'factfusion-ai' ) . '</dt><dd>' . ( $profile_url ? sprintf( '<a href="%s" rel="author">%s</a>', esc_url( $profile_url ), $name ) : $name ) . '</dd></div>';
		}

		$html .= '</dl>';

		$verify_url = FactFusion_Pages::url( 'how-we-verify-facts' );
		if ( $verify_url ) {
			$html .= sprintf( '<p class="ff-meta ff-trust__more"><a href="%s">%s</a></p>', esc_url( $verify_url ), esc_html__( 'How we verify facts', 'factfusion-ai' ) );
		}

		if ( $fact->is_ai_assisted ) {
			$html .= '<p class="ff-ai-disclosure ff-meta">' . esc_html(
				$verified
					? __( 'The explanation on this page was drafted with AI assistance. A human editor checked the fact against its source before publishing.', 'factfusion-ai' )
					: __( 'The explanation on this page was drafted with AI assistance and has not been verified yet.', 'factfusion-ai' )
			) . '</p>';
		}

		return $html;
	}
}
