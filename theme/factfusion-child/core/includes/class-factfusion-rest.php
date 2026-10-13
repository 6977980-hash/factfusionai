<?php
/**
 * REST API: /wp-json/factfusion/v1/
 *
 * @package FactFusion_AI_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The ten Phase 1 routes. Public routes return published facts only.
 */
final class FactFusion_REST {

	const ROUTE_NAMESPACE = 'factfusion/v1';

	/**
	 * Registers hooks.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		add_filter( 'rest_pre_serve_request', array( __CLASS__, 'serve_html' ), 10, 4 );
		add_filter( 'rest_post_dispatch', array( __CLASS__, 'cache_headers' ), 10, 3 );
	}

	/**
	 * Registers the routes.
	 */
	public static function register_routes() {
		$public = '__return_true';
		$editor = array( __CLASS__, 'can_edit' );
		$slug   = array(
			'type'              => 'string',
			'default'           => '',
			// REST passes extra arguments that sanitize_title() would misread, so wrap it.
			'sanitize_callback' => static function ( $value ) {
				return sanitize_title( (string) $value );
			},
		);

		register_rest_route(
			self::ROUTE_NAMESPACE,
			'/random',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'random' ),
				'permission_callback' => $public,
				'args'                => array(
					'category' => $slug,
					'exclude'  => array(
						'type'              => 'integer',
						'default'           => 0,
						'sanitize_callback' => 'absint',
						'validate_callback' => 'rest_validate_request_arg',
					),
				),
			)
		);

		register_rest_route(
			self::ROUTE_NAMESPACE,
			'/facts',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'facts' ),
				'permission_callback' => $public,
				'args'                => array(
					'page'     => array(
						'type'              => 'integer',
						'default'           => 1,
						'minimum'           => 1,
						'sanitize_callback' => 'absint',
						'validate_callback' => 'rest_validate_request_arg',
					),
					'per_page' => array(
						'type'              => 'integer',
						'default'           => 20,
						'minimum'           => 1,
						'maximum'           => 50,
						'sanitize_callback' => 'absint',
						'validate_callback' => 'rest_validate_request_arg',
					),
					'category' => $slug,
					'include'  => array(
						'type'    => 'array',
						'default' => array(),
						'items'   => array( 'type' => 'integer' ),
					),
				),
			)
		);

		register_rest_route(
			self::ROUTE_NAMESPACE,
			'/facts/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'fact' ),
				'permission_callback' => $public,
				'args'                => array(
					'id' => array(
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'validate_callback' => 'rest_validate_request_arg',
					),
				),
			)
		);

		register_rest_route(
			self::ROUTE_NAMESPACE,
			'/search',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'search' ),
				'permission_callback' => $public,
				'args'                => array(
					'q'     => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'limit' => array(
						'type'              => 'integer',
						'default'           => 8,
						'minimum'           => 1,
						'maximum'           => 20,
						'sanitize_callback' => 'absint',
						'validate_callback' => 'rest_validate_request_arg',
					),
				),
			)
		);

		register_rest_route(
			self::ROUTE_NAMESPACE,
			'/categories',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'categories' ),
				'permission_callback' => $public,
			)
		);

		register_rest_route(
			self::ROUTE_NAMESPACE,
			'/fact-of-the-day',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'fact_of_the_day' ),
				'permission_callback' => $public,
			)
		);

		register_rest_route(
			self::ROUTE_NAMESPACE,
			'/embed/random',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'embed_random' ),
				'permission_callback' => $public,
				'args'                => array( 'category' => $slug ),
			)
		);

		register_rest_route(
			self::ROUTE_NAMESPACE,
			'/report',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'report' ),
				'permission_callback' => $public,
				'args'                => array(
					'fact_id'              => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
						'validate_callback' => 'rest_validate_request_arg',
					),
					'what_is_wrong'        => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_textarea_field',
					),
					'suggested_correction' => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_textarea_field',
					),
					'supporting_source'    => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_textarea_field',
					),
					'reporter_email'       => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			self::ROUTE_NAMESPACE,
			'/ai/explain',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'ai_explain' ),
				'permission_callback' => $editor,
			)
		);

		register_rest_route(
			self::ROUTE_NAMESPACE,
			'/ai/draft',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'ai_draft' ),
				'permission_callback' => $editor,
				'args'                => array(
					'topic' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'count' => array(
						'type'              => 'integer',
						'default'           => 5,
						'minimum'           => 1,
						'maximum'           => 10,
						'sanitize_callback' => 'absint',
						'validate_callback' => 'rest_validate_request_arg',
					),
				),
			)
		);
	}

	/**
	 * Only signed-in editors with a valid REST nonce may use the AI routes.
	 *
	 * @return bool
	 */
	public static function can_edit() {
		return current_user_can( factfusion_capability() );
	}

	/**
	 * Applies the fact-fetch limit. Editors are not limited.
	 *
	 * @param string $action Limiter action.
	 * @return true|WP_Error
	 */
	private static function limit( $action ) {
		if ( 'ai' !== $action && current_user_can( factfusion_capability() ) ) {
			return true;
		}
		return FactFusion_Rate_Limiter::allow( $action );
	}

	/**
	 * Resolves a category slug to an ID.
	 *
	 * @param string $slug Category slug, may be empty.
	 * @return int|WP_Error 0 for "any category".
	 */
	private static function category_id( $slug ) {
		if ( '' === $slug ) {
			return 0;
		}
		$category = FactFusion_Categories::get_by_slug( $slug );
		if ( ! $category || ! $category->is_active ) {
			return new WP_Error( 'factfusion_no_category', __( 'That category does not exist.', 'factfusion-ai' ), array( 'status' => 404 ) );
		}
		return (int) $category->id;
	}

	/**
	 * GET /random
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public static function random( $request ) {
		$allowed = self::limit( 'fact' );
		if ( is_wp_error( $allowed ) ) {
			return $allowed;
		}
		$category_id = self::category_id( $request['category'] );
		if ( is_wp_error( $category_id ) ) {
			return $category_id;
		}
		$fact = FactFusion_Facts::random_published( $category_id, $request['exclude'] );
		if ( ! $fact ) {
			return new WP_Error( 'factfusion_no_facts', __( 'No facts are available yet.', 'factfusion-ai' ), array( 'status' => 404 ) );
		}
		return FactFusion_Facts::public_data( $fact );
	}

	/**
	 * GET /facts
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function facts( $request ) {
		$allowed = self::limit( 'fact' );
		if ( is_wp_error( $allowed ) ) {
			return $allowed;
		}

		if ( $request['include'] ) {
			$items = FactFusion_Facts::get_published_many( $request['include'] );
			$total = count( $items );
			$pages = 1;
		} else {
			$category_id = self::category_id( $request['category'] );
			if ( is_wp_error( $category_id ) ) {
				return $category_id;
			}
			$result = FactFusion_Facts::query(
				array(
					'status'      => 'published',
					'category_id' => $category_id,
					'paged'       => $request['page'],
					'per_page'    => min( 50, max( 1, (int) $request['per_page'] ) ),
					'orderby'     => 'fact_number',
					'order'       => 'desc',
				)
			);
			$items  = $result['items'];
			$total  = $result['total'];
			$pages  = (int) ceil( $total / min( 50, max( 1, (int) $request['per_page'] ) ) );
		}

		$response = rest_ensure_response(
			array_map(
				static function ( $fact ) {
					return FactFusion_Facts::public_data( $fact, true );
				},
				$items
			)
		);
		$response->header( 'X-WP-Total', (string) $total );
		$response->header( 'X-WP-TotalPages', (string) $pages );
		return $response;
	}

	/**
	 * GET /facts/{id}
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public static function fact( $request ) {
		$allowed = self::limit( 'fact' );
		if ( is_wp_error( $allowed ) ) {
			return $allowed;
		}
		$fact = FactFusion_Facts::get( $request['id'] );
		if ( ! $fact || 'published' !== $fact->status ) {
			return new WP_Error( 'factfusion_not_found', __( 'That fact does not exist.', 'factfusion-ai' ), array( 'status' => 404 ) );
		}
		return FactFusion_Facts::public_data( $fact );
	}

	/**
	 * GET /search
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public static function search( $request ) {
		$search = mb_substr( trim( (string) $request['q'] ), 0, 100 );
		if ( mb_strlen( $search ) < 2 ) {
			return array();
		}
		$allowed = self::limit( 'search' );
		if ( is_wp_error( $allowed ) ) {
			return $allowed;
		}
		$result = FactFusion_Facts::search_published( $search, min( 20, max( 1, (int) $request['limit'] ) ) );
		return array_map(
			static function ( $fact ) {
				return array(
					'id'    => (int) $fact->id,
					'title' => $fact->title,
					'claim' => $fact->claim,
					'url'   => FactFusion_Facts::url( $fact ),
				);
			},
			$result['items']
		);
	}

	/**
	 * GET /categories
	 *
	 * @return array
	 */
	public static function categories() {
		$counts = FactFusion_Facts::published_counts();
		$out    = array();
		foreach ( FactFusion_Categories::all( true ) as $category ) {
			$out[] = array(
				'slug'        => $category->slug,
				'name'        => $category->name,
				'icon'        => $category->icon,
				'description' => $category->description,
				'facts'       => (int) ( $counts[ (int) $category->id ] ?? 0 ),
				'url'         => FactFusion_Router::browse_url( $category ),
			);
		}
		return $out;
	}

	/**
	 * GET /fact-of-the-day
	 *
	 * @return array|WP_Error
	 */
	public static function fact_of_the_day() {
		$fact = FactFusion_Frontend::fact_of_the_day();
		if ( ! $fact ) {
			return new WP_Error( 'factfusion_no_facts', __( 'No facts are available yet.', 'factfusion-ai' ), array( 'status' => 404 ) );
		}
		$data         = FactFusion_Facts::public_data( $fact );
		$data['date'] = wp_date( 'Y-m-d' );
		$data['page'] = home_url( user_trailingslashit( 'fact-of-the-day' ) );
		return $data;
	}

	/**
	 * GET /embed/random: a self-contained HTML fragment.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function embed_random( $request ) {
		$allowed = self::limit( 'fact' );
		if ( is_wp_error( $allowed ) ) {
			return $allowed;
		}
		$category_id = self::category_id( $request['category'] );
		if ( is_wp_error( $category_id ) ) {
			return $category_id;
		}
		return new WP_REST_Response( FactFusion_Frontend::embed_fragment( FactFusion_Facts::random_published( $category_id ) ) );
	}

	/**
	 * Prints the embed fragment as HTML instead of a JSON string.
	 *
	 * @param bool             $served  Whether the request was already served.
	 * @param WP_HTTP_Response $result  Response.
	 * @param WP_REST_Request  $request Request.
	 * @param WP_REST_Server   $server  Server.
	 * @return bool
	 */
	public static function serve_html( $served, $result, $request, $server ) {
		if ( $served || '/' . self::ROUTE_NAMESPACE . '/embed/random' !== $request->get_route() || $result->is_error() || ! is_string( $result->get_data() ) ) {
			return $served;
		}
		$server->send_header( 'Content-Type', 'text/html; charset=' . get_option( 'blog_charset' ) );
		echo $result->get_data(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in embed_fragment().
		return true;
	}

	/**
	 * Marks which answers may be cached. Random and personal answers never are.
	 *
	 * @param WP_HTTP_Response $response Response.
	 * @param WP_REST_Server   $server   Server.
	 * @param WP_REST_Request  $request  Request.
	 * @return WP_HTTP_Response
	 */
	public static function cache_headers( $response, $server, $request ) {
		$route = $request->get_route();
		if ( 0 !== strpos( $route, '/' . self::ROUTE_NAMESPACE . '/' ) ) {
			return $response;
		}
		$cacheable = array( '/categories', '/fact-of-the-day' );
		$path      = substr( $route, strlen( '/' . self::ROUTE_NAMESPACE ) );
		if ( in_array( $path, $cacheable, true ) && ! $response->is_error() ) {
			$response->header( 'Cache-Control', 'public, max-age=300' );
			do_action( 'litespeed_control_set_ttl', 300, 'factfusion: api' );
		} else {
			$response->header( 'Cache-Control', 'no-store, private' );
			$response->header( 'X-LiteSpeed-Cache-Control', 'no-cache' );
			do_action( 'litespeed_control_set_nocache', 'factfusion: api varies per request' );
		}
		if ( 429 === $response->get_status() ) {
			$data = $response->get_data();
			if ( isset( $data['data']['retry_after'] ) ) {
				$response->header( 'Retry-After', (string) (int) $data['data']['retry_after'] );
			}
		}
		$response->header( 'X-Robots-Tag', 'noindex' );
		return $response;
	}

	/**
	 * Checks a Cloudflare Turnstile token when a secret key is configured.
	 *
	 * @param string $token Token from the form.
	 * @return true|WP_Error
	 */
	public static function verify_turnstile( $token ) {
		$secret = FactFusion_Settings::env( 'TURNSTILE_SECRET_KEY' );
		if ( '' === $secret ) {
			return true;
		}
		$failed = new WP_Error( 'factfusion_turnstile', __( 'Please complete the "I am human" check and try again.', 'factfusion-ai' ), array( 'status' => 403 ) );
		if ( '' === $token ) {
			return $failed;
		}
		$response = wp_remote_post(
			'https://challenges.cloudflare.com/turnstile/v0/siteverify',
			array(
				'timeout' => 10,
				'body'    => array(
					'secret'   => $secret,
					'response' => $token,
					'remoteip' => FactFusion_Rate_Limiter::client_ip(),
				),
			)
		);
		$body = is_wp_error( $response ) ? null : json_decode( wp_remote_retrieve_body( $response ), true );
		return is_array( $body ) && ! empty( $body['success'] ) ? true : $failed;
	}

	/**
	 * POST /report: a reader's correction report.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function report( $request ) {
		$fact = FactFusion_Facts::get( $request['fact_id'] );
		if ( ! $fact || 'published' !== $fact->status ) {
			return new WP_Error( 'factfusion_not_found', __( 'That fact does not exist.', 'factfusion-ai' ), array( 'status' => 404 ) );
		}

		$done = array(
			'ok'      => true,
			'message' => __( 'Thank you. Your report has been sent to the editor.', 'factfusion-ai' ),
		);
		$back = ! empty( $request['redirect'] ) && false === strpos( (string) $request->get_header( 'accept' ), 'application/json' );
		$sent = static function () use ( $back, $fact, $done ) {
			if ( $back ) {
				return new WP_REST_Response( null, 303, array( 'Location' => add_query_arg( 'reported', '1', FactFusion_Facts::url( $fact ) ) . '#ff-report' ) );
			}
			return new WP_REST_Response( $done, 201 );
		};

		// A filled honeypot means a bot: pretend it worked and store nothing.
		if ( '' !== trim( (string) $request->get_param( 'website' ) ) ) {
			return $sent();
		}

		$wrong = trim( (string) $request['what_is_wrong'] );
		if ( mb_strlen( $wrong ) < 10 || mb_strlen( $wrong ) > 2000 ) {
			return new WP_Error( 'factfusion_report_text', __( 'Please describe the problem in 10 to 2,000 characters.', 'factfusion-ai' ), array( 'status' => 400 ) );
		}
		$email = trim( (string) $request['reporter_email'] );
		if ( '' !== $email && ! is_email( $email ) ) {
			return new WP_Error( 'factfusion_report_email', __( 'That email address is not valid.', 'factfusion-ai' ), array( 'status' => 400 ) );
		}

		$human = self::verify_turnstile( sanitize_text_field( (string) $request->get_param( 'cf-turnstile-response' ) ) );
		if ( is_wp_error( $human ) ) {
			return $human;
		}
		$allowed = FactFusion_Rate_Limiter::allow( 'report' );
		if ( is_wp_error( $allowed ) ) {
			return $allowed;
		}

		$saved = FactFusion_Corrections::save(
			array(
				'fact_id'              => (int) $fact->id,
				'reporter_email'       => $email,
				'what_is_wrong'        => $wrong,
				'suggested_correction' => mb_substr( (string) $request['suggested_correction'], 0, 2000 ),
				'supporting_source'    => mb_substr( (string) $request['supporting_source'], 0, 500 ),
				'status'               => 'new',
			)
		);
		if ( is_wp_error( $saved ) ) {
			return new WP_Error( 'factfusion_report_failed', __( 'The report could not be saved. Please try again.', 'factfusion-ai' ), array( 'status' => 500 ) );
		}
		return $sent();
	}

	/**
	 * POST /ai/explain: drafts an explanation for the editor to check.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public static function ai_explain( $request ) {
		$allowed = self::limit( 'ai' );
		if ( is_wp_error( $allowed ) ) {
			return $allowed;
		}
		$names = array();
		foreach ( array_slice( array_map( 'absint', (array) $request->get_param( 'source_ids' ) ), 0, 10 ) as $source_id ) {
			$source = FactFusion_Sources::get( $source_id );
			if ( $source ) {
				$names[] = trim( $source->source_name . ( '' !== $source->title ? ': ' . $source->title : '' ) );
			}
		}
		return FactFusion_AI::explain(
			array(
				'title'          => (string) $request->get_param( 'title' ),
				'claim'          => (string) $request->get_param( 'claim' ),
				'short_answer'   => (string) $request->get_param( 'short_answer' ),
				'source_summary' => (string) $request->get_param( 'source_summary' ),
				'source_names'   => $names,
			)
		);
	}

	/**
	 * POST /ai/draft: suggests unsourced candidate claims for research.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public static function ai_draft( $request ) {
		$allowed = self::limit( 'ai' );
		if ( is_wp_error( $allowed ) ) {
			return $allowed;
		}
		return FactFusion_AI::draft( $request['topic'], $request['count'] );
	}
}
