<?php
namespace HW_Onsale_Accelerator;

defined( 'ABSPATH' ) || exit;

use WP_REST_Response;

/**
 * Main controller bootstrapping hooks for the accelerator.
 */
class Controller {
        /**
         * Singleton instance.
         *
         * @var Controller|null
         */
        private static $instance = null;

        /**
         * Query layer instance.
         *
         * @var Query
         */
        private $query;

        /**
         * Cache helper.
         *
         * @var Cache
         */
        private $cache;

        /**
         * Get singleton instance.
         *
         * @return Controller
         */
        public static function instance() {
                if ( null === self::$instance ) {
                        self::$instance = new self();
                }

                return self::$instance;
        }

        /**
         * Constructor.
         */
        private function __construct() {
                $this->cache = new Cache();
                $this->query = new Query( $this->cache );

                add_filter( 'template_include', array( $this, 'maybe_use_template' ), 100 );
                add_action( 'template_redirect', array( $this, 'prepare_template_context' ), 5 );
                add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ), 20 );
                add_action( 'wp_enqueue_scripts', array( $this, 'dequeue_cart_fragments' ), 100 );
                add_filter( 'script_loader_tag', array( $this, 'defer_plugin_script' ), 10, 3 );
                add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
                add_action( 'send_headers', array( $this, 'maybe_adjust_headers' ) );

                add_action( 'save_post_product', array( $this, 'invalidate_cache' ) );
                add_action( 'deleted_post', array( $this, 'invalidate_cache_on_delete' ), 10, 2 );
                add_action( 'added_post_meta', array( $this, 'maybe_invalidate_on_meta_change' ), 10, 4 );
                add_action( 'updated_post_meta', array( $this, 'maybe_invalidate_on_meta_change' ), 10, 4 );
                add_action( 'deleted_post_meta', array( $this, 'maybe_invalidate_on_meta_change' ), 10, 4 );
                add_action( 'edited_product_cat', array( $this, 'invalidate_cache' ), 10, 0 );
                add_action( 'created_product_cat', array( $this, 'invalidate_cache' ), 10, 0 );
                add_action( 'delete_product_cat', array( $this, 'invalidate_cache' ), 10, 0 );
        }

        /**
         * Prepare template context earlier in template_redirect so global query vars exist.
         *
         * @return void
         */
        public function prepare_template_context() {
                if ( ! $this->is_target_request() ) {
                        return;
                }

		$params    = $this->collect_request_params();
		$page_data = $this->query->get_page( $params );
		$params['per_page'] = $page_data['per_page'];

		set_query_var( 'hw_onsale_acc_initial', array(
			'products'    => $page_data['products'],
			'total'       => $page_data['total'],
			'total_pages' => $page_data['total_pages'],
			'price_range' => $page_data['price_range'],
			'params'      => $params,
                ) );
        }

        /**
         * Swap theme template for our cached template when on /onsale.
         *
         * @param string $template Default template path.
         * @return string
         */
        public function maybe_use_template( $template ) {
                if ( ! $this->is_target_request() ) {
                        return $template;
                }

                $custom = HW_ONSALE_ACCELERATOR_DIR . 'templates/onsale.php';

                if ( file_exists( $custom ) ) {
                        return $custom;
                }

                return $template;
        }

        /**
         * Enqueue frontend assets for /onsale request only.
         *
         * @return void
         */
        public function enqueue_assets() {
                if ( ! $this->is_target_request() ) {
                        return;
                }

		$data = get_query_var( 'hw_onsale_acc_initial' );
		if ( empty( $data ) || ! is_array( $data ) ) {
			$params    = $this->collect_request_params();
			$page_data = $this->query->get_page( $params );
			$params['per_page'] = $page_data['per_page'];

			$data = array(
				'products'    => $page_data['products'],
				'total'       => $page_data['total'],
				'total_pages' => $page_data['total_pages'],
				'price_range' => $page_data['price_range'],
				'params'      => $params,
			);
		}

		$filters = isset( $data['params'] ) && is_array( $data['params'] ) ? $data['params'] : array();
		$orderby = isset( $filters['orderby'] ) ? sanitize_text_field( (string) $filters['orderby'] ) : 'discount-desc';
		$per_page_setting = isset( $filters['per_page'] ) ? $filters['per_page'] : ( isset( $data['per_page'] ) ? $data['per_page'] : (int) get_option( 'hw_onsale_batch_size', 12 ) );
		$batch_size       = $this->get_effective_per_page( $per_page_setting, $orderby );
		$filters['per_page'] = $batch_size;
		$filters['orderby']  = $orderby;
		$data['params']      = $filters;

                wp_enqueue_style(
                        'hw-onsale-accelerator',
                        HW_ONSALE_ACCELERATOR_URL . 'assets/onsale.css',
                        array(),
                        HW_ONSALE_ACCELERATOR_VERSION
                );

                $critical_path = HW_ONSALE_ACCELERATOR_DIR . 'assets/onsale-critical.css';
                if ( file_exists( $critical_path ) ) {
                        $critical_css = trim( file_get_contents( $critical_path ) );
                        if ( ! empty( $critical_css ) ) {
                                wp_add_inline_style( 'hw-onsale-accelerator', $critical_css );
                        }
                }

                wp_enqueue_script(
                        'hw-onsale-accelerator',
                        HW_ONSALE_ACCELERATOR_URL . 'assets/onsale.js',
                        array(),
                        HW_ONSALE_ACCELERATOR_VERSION,
                        true
                );

		$localized = array(
			'restUrl'    => esc_url_raw( rest_url( 'hw-onsale/v1' ) ),
			'nonce'      => wp_create_nonce( 'wp_rest' ),
			'settings'   => array(
				'batchSize'       => (int) $batch_size,
				'trackingEnabled' => get_option( 'hw_onsale_tracking_enabled', '1' ) === '1',
				'badgeThreshold'  => (int) get_option( 'hw_onsale_badge_threshold', 0 ),
				'badgePosition'   => get_option( 'hw_onsale_badge_position', 'top-left' ),
				'badgeStyle'      => get_option( 'hw_onsale_badge_style', 'solid' ),
				'gridColumns'     => array(
					'desktop' => (int) get_option( 'hw_onsale_grid_desktop', 4 ),
					'tablet'  => (int) get_option( 'hw_onsale_grid_tablet', 3 ),
					'mobile'  => (int) get_option( 'hw_onsale_grid_mobile', 2 ),
				),
			),
			'initial'    => array(
				'html'       => $this->render_cards_html( $data['products'] ),
				'total'      => $data['total'],
				'totalPages' => $data['total_pages'],
				'priceRange' => $data['price_range'],
				'filters'    => $data['params'],
			),
			'i18n'       => array(
				'loadMore'    => __( 'Load more', 'hw-onsale' ),
				'loading'     => __( 'Loading…', 'hw-onsale' ),
				'noMore'      => __( 'No more products', 'hw-onsale' ),
				'loadError'   => __( 'Unable to load products.', 'hw-onsale' ),
				'retry'       => __( 'Retry', 'hw-onsale' ),
				'filters'     => __( 'Filters', 'hw-onsale' ),
				'apply'       => __( 'Apply', 'hw-onsale' ),
				'reset'       => __( 'Reset filters', 'hw-onsale' ),
				'choose'      => __( 'Choose an option', 'hw-onsale' ),
			),
		);

                wp_localize_script( 'hw-onsale-accelerator', 'hwOnsaleAcc', $localized );
        }

        /**
         * Register REST routes for list endpoint.
         *
         * @return void
         */
	public function register_rest_routes() {
		$default_per_page = $this->get_effective_per_page( get_option( 'hw_onsale_batch_size', 12 ), 'discount-desc' );

		register_rest_route(
			'hw-onsale/v1',
			'/list',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_list' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'page'       => array(
						'required'          => false,
						'sanitize_callback' => 'absint',
						'default'           => 1,
					),
					'per_page'   => array(
						'required'          => false,
						'sanitize_callback' => 'absint',
						'default'           => (int) $default_per_page,
					),
					'orderby'    => array(
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'min_price'  => array(
						'required'          => false,
						'sanitize_callback' => 'absint',
					),
					'max_price'  => array(
						'required'          => false,
						'sanitize_callback' => 'absint',
					),
					'categories' => array(
						'required'          => false,
						'sanitize_callback' => array( $this, 'sanitize_categories_param' ),
					),
					'in_stock'   => array(
						'required'          => false,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			'hw-onsale/v1',
			'/product/(?P<id>\d+)/options',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_product_options' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'id' => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			'hw-onsale/v1',
			'/event',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_track_event' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public function rest_list( $request ) {
		$params = array(
			'page'       => max( 1, (int) $request->get_param( 'page' ) ),
			'per_page'   => max( 1, (int) $request->get_param( 'per_page' ) ),
			'orderby'    => $request->get_param( 'orderby' ),
			'min_price'  => $request->get_param( 'min_price' ),
			'max_price'  => $request->get_param( 'max_price' ),
			'categories' => $request->get_param( 'categories' ),
			'in_stock'   => $request->get_param( 'in_stock' ),
		);

		$orderby = sanitize_text_field( (string) ( $params['orderby'] ?? '' ) );
		if ( '' === $orderby ) {
			$orderby = 'discount-desc';
		}

		$params['per_page'] = $this->get_effective_per_page( $params['per_page'], $orderby );
		$params['orderby']  = $orderby;

		$cache_key = $this->cache->build_key( 'fragment', $params );
		$cached    = $this->cache->get( $cache_key );

		if ( false !== $cached && isset( $cached['html'] ) ) {
			$response = new WP_REST_Response( $cached['html'] );
			$response->header( 'Content-Type', 'text/html; charset=' . get_option( 'blog_charset' ) );
			$response->header( 'X-Total-Count', (string) ( $cached['total'] ?? 0 ) );
			$response->header( 'X-Total-Pages', (string) ( $cached['total_pages'] ?? 1 ) );
			$response->header( 'X-HW-Onsale-Cache', 'HIT' );
			return $response;
		}

		$page    = $this->query->get_page( $params );
		$start   = ( $page['page'] - 1 ) * $page['per_page'];
		$html    = $this->render_cards_html( $page['products'], $start );
		$payload = array(
			'html'        => $html,
			'total'       => $page['total'],
			'total_pages' => $page['total_pages'],
		);
		$this->cache->set( $cache_key, $payload );

		$response = new WP_REST_Response( $html );
		$response->header( 'Content-Type', 'text/html; charset=' . get_option( 'blog_charset' ) );
		$response->header( 'X-Total-Count', (string) $page['total'] );
		$response->header( 'X-Total-Pages', (string) $page['total_pages'] );
		$response->header( 'X-HW-Onsale-Cache', 'MISS' );

		return $response;
	}

        /**
         * REST callback returning product options payload.
         *
         * @param \WP_REST_Request $request Request.
         * @return \WP_REST_Response|\WP_Error
         */
        public function rest_product_options( $request ) {
                $product_id = (int) $request->get_param( 'id' );
                if ( $product_id <= 0 ) {
                        return new \WP_Error( 'hw_product_invalid', __( 'Invalid product.', 'hw-onsale' ), array( 'status' => 400 ) );
                }

                $cache_key = $this->cache->build_key( 'rest_product_options', array( 'id' => $product_id ) );
                $cached    = $this->cache->get( $cache_key );

                if ( false !== $cached ) {
                        $response = rest_ensure_response( $cached );
                        $response->header( 'X-HW-Onsale-Cache', 'HIT' );
                        return $response;
                }

                $result = $this->query->get_product_options( $product_id );

                if ( is_wp_error( $result ) ) {
                        return $result;
                }

                $this->cache->set( $cache_key, $result );

                $response = rest_ensure_response( $result );
                $response->header( 'X-HW-Onsale-Cache', 'MISS' );

                return $response;
        }

        /**
         * Lightweight tracking endpoint acknowledging events.
         *
         * @param \WP_REST_Request $request Request.
         * @return \WP_REST_Response
         */
        public function rest_track_event( $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
                $response = rest_ensure_response( array( 'success' => true ) );
                $response->set_status( 202 );
                return $response;
        }

        /**
         * Invalidate cache on product or taxonomy change.
         *
         * @return void
         */
        public function invalidate_cache() {
                $this->cache->flush_group();
        }

        /**
         * Invalidate cache when product deleted.
         *
         * @param int     $post_id Post ID.
         * @param WP_Post $post    Post object.
         * @return void
         */
        public function invalidate_cache_on_delete( $post_id, $post ) {
                if ( isset( $post->post_type ) && 'product' === $post->post_type ) {
                        $this->invalidate_cache();
                }
        }

        /**
         * Check meta changes for monitored keys.
         *
         * @param int    $meta_id    Meta ID.
         * @param int    $object_id  Object ID.
         * @param string $meta_key   Meta key.
         * @param mixed  $meta_value Meta value.
         * @return void
         */
        public function maybe_invalidate_on_meta_change( $meta_id, $object_id, $meta_key, $meta_value ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundInExtendedClass
                if ( 'product' !== get_post_type( $object_id ) ) {
                        return;
                }

                $watched = array( '_regular_price', '_sale_price', '_price', '_stock_status', '_manage_stock', '_stock' );

                if ( in_array( $meta_key, $watched, true ) ) {
                        $this->invalidate_cache();
                }
        }

        /**
         * Dequeue cart fragment script for /onsale.
         *
         * @return void
         */
        public function dequeue_cart_fragments() {
                if ( ! $this->is_target_request() ) {
                        return;
                }

                wp_dequeue_script( 'wc-cart-fragments' );
                wp_dequeue_script( 'woocommerce-cart-fragments' );
                wp_deregister_script( 'wc-cart-fragments' );
        }

        /**
         * Add cache headers for anonymous requests.
         *
         * @return void
         */
        public function maybe_adjust_headers() {
                if ( ! $this->is_target_request() ) {
                        return;
                }

                if ( ! $this->should_cache_request() ) {
                        return;
                }

                $cache_control = 'public, max-age=0, s-maxage=600, stale-while-revalidate=86400';
                header_remove( 'Cache-Control' );
                header( 'Cache-Control: ' . $cache_control );
        }

        /**
         * Render HTML cards markup.
         *
         * @param array $products Products.
         * @param int   $start    Offset for fetchpriority logic.
         * @return string
         */
        public function render_cards_html( array $products, $start = 0 ) {
                if ( empty( $products ) ) {
                        return '';
                }

                $html = '';
                foreach ( $products as $index => $product ) {
                        $html .= render_card( $product, $start + $index );
                }

                return $html;
        }

        /**
         * Sanitize categories parameter for REST.
         *
         * @param mixed $value Raw value.
         * @return array
         */
        public function sanitize_categories_param( $value ) {
                if ( is_array( $value ) ) {
                        $parts = $value;
                } else {
                        $parts = explode( ',', (string) $value );
                }

                $ids = array();
                foreach ( $parts as $part ) {
                        $id = (int) trim( $part );
                        if ( $id > 0 ) {
                                $ids[] = $id;
                        }
                }

                return array_values( array_unique( $ids ) );
        }

        /**
         * Defer plugin script only.
         *
         * @param string $tag    Script tag.
         * @param string $handle Handle.
         * @param string $src    Source URL.
         * @return string
         */
        public function defer_plugin_script( $tag, $handle, $src ) {
                if ( 'hw-onsale-accelerator' !== $handle ) {
                        return $tag;
                }

                return sprintf( '<script src="%s" id="%s-js" defer></script>', esc_url( $src ), esc_attr( $handle ) );
        }

        /**
         * Determine if current request is /onsale front-end.
         *
         * @return bool
         */
        private function is_target_request() {
                if ( is_admin() || wp_doing_ajax() || is_feed() || wp_is_json_request() ) {
                        return false;
                }

                global $wp;
                $path = isset( $wp->request ) ? $wp->request : '';

                if ( empty( $path ) ) {
                        $uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
                        $path = trim( parse_url( $uri, PHP_URL_PATH ), '/' );
                }

                if ( 'onsale' === trim( $path, '/' ) ) {
                        return true;
                }

                if ( function_exists( 'is_page' ) && is_page() ) {
                        $object = get_queried_object();
                        if ( $object && isset( $object->post_name ) && 'onsale' === $object->post_name ) {
                                return true;
                        }
                }

                return false;
        }

        /**
         * Determine if anonymous caching is allowed.
         *
         * @return bool
         */
        private function should_cache_request() {
                if ( is_user_logged_in() ) {
                        return false;
                }

                if ( ! empty( $_GET['s'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                        return false;
                }

                if ( empty( $_COOKIE ) || ! is_array( $_COOKIE ) ) { // phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE
                        return true;
                }

                foreach ( array_keys( $_COOKIE ) as $cookie_name ) { // phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE
                        if ( 0 === strpos( $cookie_name, 'woocommerce_cart_hash' ) ||
                                0 === strpos( $cookie_name, 'woocommerce_items_in_cart' ) ||
                                0 === strpos( $cookie_name, 'wp_woocommerce_session_' ) ) {
                                return false;
                        }
                }

                return true;
        }

        /**
         * Collect GET params for filters.
         *
         * @return array
         */
	private function collect_request_params() {
		$default_per_page = (int) get_option( 'hw_onsale_batch_size', 12 );
		$orderby          = isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : 'discount-desc'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$per_page         = $this->get_effective_per_page( $default_per_page, $orderby );

		$params = array(
			'page'       => 1,
			'per_page'   => $per_page,
			'orderby'    => $orderby,
			'min_price'  => isset( $_GET['min_price'] ) ? absint( wp_unslash( $_GET['min_price'] ) ) : null, // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'max_price'  => isset( $_GET['max_price'] ) ? absint( wp_unslash( $_GET['max_price'] ) ) : null, // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'categories' => isset( $_GET['categories'] ) ? $this->sanitize_categories_param( wp_unslash( $_GET['categories'] ) ) : array(), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'in_stock'   => isset( $_GET['in_stock'] ) ? absint( wp_unslash( $_GET['in_stock'] ) ) : 0, // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		);

		return $params;
	}

	/**
	 * Determine effective per page respecting discount sort cap.
	 *
	 * @param int    $per_page Requested per page value.
	 * @param string $orderby  Requested orderby parameter.
	 * @return int
	 */
	private function get_effective_per_page( $per_page, $orderby ) {
		$per_page = max( 1, (int) $per_page );
		$orderby  = sanitize_text_field( (string) $orderby );

		if ( '' === $orderby ) {
			$orderby = 'discount-desc';
		}

		if ( 'discount-desc' === $orderby ) {
			return 12;
		}

		return $per_page;
	}

}
