<?php
/**
 * Plugin Name: HW On Sale
 * Plugin URI: https://example.com/hw-onsale
 * Description: Professional on-sale product page with analytics dashboard, AJAX load-more, and advanced tracking.
 * Version: 1.0.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: HW Team
 * Author URI: https://example.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: hw-onsale
 * Domain Path: /languages
 *
 * @package HW_Onsale
 */

namespace HW_Onsale;

defined( 'ABSPATH' ) || exit;

define( 'HW_ONSALE_VERSION', '1.0.0' );
define( 'HW_ONSALE_PLUGIN_FILE', __FILE__ );
define( 'HW_ONSALE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'HW_ONSALE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * PSR-4 Autoloader
 */
spl_autoload_register(
	function ( $class ) {
		$prefix   = 'HW_Onsale\\';
		$base_dir = __DIR__ . '/src/';

		$len = strlen( $prefix );
		if ( strncmp( $prefix, $class, $len ) !== 0 ) {
			return;
		}

		$relative_class = substr( $class, $len );
		$file           = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

		if ( file_exists( $file ) ) {
			require $file;
		}
	}
);

/**
 * Main Plugin Class
 */
class Plugin {
	/**
	 * Singleton instance
	 *
	 * @var Plugin
	 */
	private static $instance = null;

	/**
	 * Get singleton instance
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor - initialize plugin
	 */
	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'init' ) );
		register_activation_hook( HW_ONSALE_PLUGIN_FILE, array( $this, 'activate' ) );
		register_deactivation_hook( HW_ONSALE_PLUGIN_FILE, array( $this, 'deactivate' ) );
	}

	/**
	 * Initialize plugin
	 */
	public function init() {
		// Check dependencies.
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
			return;
		}

		// Load text domain.
		load_plugin_textdomain( 'hw-onsale', false, dirname( plugin_basename( HW_ONSALE_PLUGIN_FILE ) ) . '/languages' );

		// Initialize components.
		$this->init_rest_api();
		$this->init_shortcodes();
		$this->init_admin();
		$this->init_attribution();
		$this->init_assets();
		$this->init_direct_checkout();
        }

	/**
	 * Initialize REST API controllers
	 */
	private function init_rest_api() {
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/**
	 * Register REST routes
	 */
	public function register_rest_routes() {
		$event_repo   = new Infrastructure\Repositories\WPDBEventRepository();
		$product_repo = new Infrastructure\Repositories\WooProductRepository();

		// Public endpoints.
		$public_controller = new Infrastructure\REST\OnsalePublicController( $product_repo );
		$public_controller->register_routes();

		$ingest_controller = new Infrastructure\REST\EventIngestController( $event_repo );
		$ingest_controller->register_routes();

		// Admin endpoints.
		$analytics_controller = new Infrastructure\REST\AnalyticsAdminController( $event_repo );
		$analytics_controller->register_routes();
	}

	/**
	 * Initialize shortcodes
	 */
	private function init_shortcodes() {
		new Presentation\Shortcodes\OnsaleShortcode();
	}

	/**
	 * Initialize admin dashboard
	 */
	private function init_admin() {
		if ( is_admin() ) {
			new Presentation\Admin\DashboardPage();
		}
	}

	/**
	 * Initialize order attribution
	 */
	private function init_attribution() {
		new Infrastructure\Attribution\OrderAttribution();
	}

	/**
	 * Initialize assets
	 */
	private function init_assets() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Initialize direct checkout handler.
	 */
	private function init_direct_checkout() {
		add_action( 'template_redirect', array( $this, 'handle_direct_checkout' ), 5 );
	}

	/**
	 * Enqueue frontend assets
	 */
	public function enqueue_frontend_assets() {
		global $post;

		// Only enqueue on /onsale page or when shortcode is present.
		$should_enqueue = false;

		if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'hw_onsale' ) ) {
			$should_enqueue = true;
		}

		$onsale_page_id = get_option( 'hw_onsale_page_id' );
		if ( $onsale_page_id && is_page( $onsale_page_id ) ) {
			$should_enqueue = true;
		}

		if ( ! $should_enqueue ) {
			return;
		}

		wp_enqueue_style(
			'hw-onsale',
			HW_ONSALE_PLUGIN_URL . 'assets/css/onsale.css',
			array(),
			HW_ONSALE_VERSION
		);

		// Enqueue theme configuration (optional themes)
		wp_enqueue_style(
			'hw-onsale-theme',
			HW_ONSALE_PLUGIN_URL . 'assets/css/theme-config.css',
			array( 'hw-onsale' ),
			HW_ONSALE_VERSION
		);

		wp_enqueue_script(
			'hw-onsale',
			HW_ONSALE_PLUGIN_URL . 'assets/js/onsale.js',
			array(),
			HW_ONSALE_VERSION,
			true
		);

		wp_localize_script(
			'hw-onsale',
			'hwOnsale',
			array(
				'restUrl'   => rest_url( 'hw-onsale/v1' ),
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'settings'  => $this->get_frontend_settings(),
                                'i18n'      => array(
                                        'loadMore'      => __( 'Load More', 'hw-onsale' ),
                                        'loading'       => __( 'Loading...', 'hw-onsale' ),
                                        'noMore'        => __( 'No more products', 'hw-onsale' ),
                                        'addToCart'     => __( 'Add to cart', 'hw-onsale' ),
                                        'selectOptions' => __( 'Select options', 'hw-onsale' ),
                                        'chooseOption'  => __( 'Choose an option', 'hw-onsale' ),
                                        'outOfStock'    => __( 'This combination is sold out', 'hw-onsale' ),
                                        'unavailable'   => __( 'Combination unavailable', 'hw-onsale' ),
                                        'loadError'     => __( 'Unable to load options.', 'hw-onsale' ),
                                        'close'         => __( 'Close', 'hw-onsale' ),
                                ),
                        )
                );
        }

        /**
         * Handle direct checkout links by clearing the cart and re-adding the requested product only.
         */
        public function handle_direct_checkout() {
                if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
                        return;
                }

                if ( empty( $_GET['hw-direct-checkout'] ) ) {
                        return;
                }

                if ( ! function_exists( 'WC' ) ) {
                        return;
                }

                if ( function_exists( 'wc_load_cart' ) && null === \WC()->cart ) {
                        \wc_load_cart();
                }

                $cart = \WC()->cart;

                if ( ! $cart ) {
                        return;
                }

                $product_id = isset( $_GET['add-to-cart'] ) ? \absint( \wp_unslash( $_GET['add-to-cart'] ) ) : 0;

                if ( ! $product_id ) {
                        return;
                }

                $quantity = isset( $_GET['quantity'] ) ? \wc_stock_amount( \wp_unslash( $_GET['quantity'] ) ) : 1;

                if ( $quantity < 1 ) {
                        $quantity = 1;
                }

                $variation_id = isset( $_GET['variation_id'] ) ? \absint( \wp_unslash( $_GET['variation_id'] ) ) : 0;

                $attributes = array();

                foreach ( $_GET as $key => $value ) {
                        if ( 0 === strpos( $key, 'attribute_' ) ) {
                                $attributes[ $key ] = \wc_clean( \wp_unslash( $value ) );
                        }
                }

                $product = \wc_get_product( $variation_id ? $variation_id : $product_id );

                if ( ! $product || ! $product->is_purchasable() ) {
                        return;
                }

                $cart->empty_cart();

                $added_key = $cart->add_to_cart( $product_id, $quantity, $variation_id, $attributes );

                if ( ! $added_key ) {
                        return;
                }

                if ( function_exists( 'wc_clear_notices' ) ) {
                        \wc_clear_notices( 'success' );
                }

                $remove_keys = array( 'hw-direct-checkout', 'add-to-cart', 'quantity', 'variation_id' );

                foreach ( array_keys( $_GET ) as $query_key ) {
                        if ( 0 === strpos( $query_key, 'attribute_' ) ) {
                                $remove_keys[] = $query_key;
                        }
                }

                $redirect_url = \remove_query_arg( $remove_keys );

                if ( empty( $redirect_url ) ) {
                        $redirect_url = \wc_get_checkout_url();
                }

                \wp_safe_redirect( $redirect_url );
                exit;
        }

	/**
	 * Get frontend settings
	 *
	 * @return array
	 */
	private function get_frontend_settings() {
		$default_batch_size = (int) get_option( 'hw_onsale_batch_size', 12 );
		$batch_size          = max( 1, $default_batch_size );
		$requested_orderby   = 'discount-desc';

		if ( isset( $_GET['orderby'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$requested_orderby = sanitize_text_field( wp_unslash( $_GET['orderby'] ) );
		}

		if ( 'discount-desc' === $requested_orderby ) {
			$batch_size = 12;
		}

		return array(
			'batchSize'       => $batch_size,
                        'trackingEnabled' => get_option( 'hw_onsale_tracking_enabled', '1' ) === '1',
                        'badgeThreshold'  => (int) get_option( 'hw_onsale_badge_threshold', 0 ),
                        'badgePosition'   => get_option( 'hw_onsale_badge_position', 'top-left' ),
                        'badgeStyle'      => get_option( 'hw_onsale_badge_style', 'solid' ),
                        'gridColumns'     => array(
				'desktop' => (int) get_option( 'hw_onsale_grid_desktop', 4 ),
				'tablet'  => (int) get_option( 'hw_onsale_grid_tablet', 3 ),
				'mobile'  => (int) get_option( 'hw_onsale_grid_mobile', 2 ),
			),
		);
	}

	/**
	 * Enqueue admin assets
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( 'woocommerce_page_hw-onsale-dashboard' !== $hook ) {
			return;
		}

		wp_enqueue_media();

		wp_enqueue_style(
			'hw-onsale-admin',
			HW_ONSALE_PLUGIN_URL . 'assets/css/admin-dashboard.css',
			array(),
			HW_ONSALE_VERSION
		);

		wp_enqueue_script(
			'chart-js',
			'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
			array(),
			'4.4.0',
			true
		);

		wp_enqueue_script(
			'hw-onsale-admin',
			HW_ONSALE_PLUGIN_URL . 'assets/js/admin-dashboard.js',
			array( 'chart-js', 'jquery' ),
			HW_ONSALE_VERSION,
			true
		);

		wp_localize_script(
			'hw-onsale-admin',
			'hwOnsaleAdmin',
			array(
				'restUrl' => rest_url( 'hw-onsale/v1' ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'i18n'    => array(
					'exportSuccess' => __( 'Export successful', 'hw-onsale' ),
					'exportError'   => __( 'Export failed', 'hw-onsale' ),
					'loadError'     => __( 'Failed to load analytics', 'hw-onsale' ),
				),
			)
		);
	}

	/**
	 * Activate plugin
	 */
	public function activate() {
		// Check dependencies.
		if ( ! class_exists( 'WooCommerce' ) ) {
			deactivate_plugins( plugin_basename( HW_ONSALE_PLUGIN_FILE ) );
			wp_die( esc_html__( 'This plugin requires WooCommerce to be installed and active.', 'hw-onsale' ) );
		}

		// Run database migrations.
		$migrator = new Infrastructure\DB\Migrator();
		$migrator->migrate();

		// Create /onsale page.
		$this->create_onsale_page();

		// Grant capability to administrators.
		$role = get_role( 'administrator' );
		if ( $role ) {
			$role->add_cap( 'manage_hw_onsale' );
		}

		// Set default options.
		$this->set_default_options();

		// Flush rewrite rules.
		flush_rewrite_rules();
	}

	/**
	 * Create /onsale page
	 */
	private function create_onsale_page() {
		$existing_id = get_option( 'hw_onsale_page_id' );

		if ( $existing_id && get_post( $existing_id ) ) {
			return;
		}

		$page_id = wp_insert_post(
			array(
				'post_title'   => __( 'On Sale', 'hw-onsale' ),
				'post_content' => '[hw_onsale]',
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_name'    => 'onsale',
			)
		);

		if ( $page_id && ! is_wp_error( $page_id ) ) {
			update_option( 'hw_onsale_page_id', $page_id );
		}
	}

	/**
	 * Set default options
	 */
	private function set_default_options() {
		$defaults = array(
			'hw_onsale_batch_size'              => 12,
			'hw_onsale_grid_desktop'            => 4,
			'hw_onsale_grid_tablet'             => 3,
			'hw_onsale_grid_mobile'             => 2,
			'hw_onsale_badge_threshold'         => 0,
			'hw_onsale_badge_position'          => 'top-right',
			'hw_onsale_badge_style'             => 'solid',
			'hw_onsale_card_radius'             => 16,
			'hw_onsale_card_shadow'             => 'medium',
			'hw_onsale_card_gap'                => 24,
			'hw_onsale_tracking_enabled'        => '1',
			'hw_onsale_anonymize_ip'            => '1',
			'hw_onsale_exclude_admins'          => '1',
			'hw_onsale_cache_enabled'           => '0',
			'hw_onsale_cache_ttl'               => 3600,
			'hw_onsale_attribution_window'      => 24,
			'hw_onsale_load_more_label'         => __( 'Load More', 'hw-onsale' ),
			'hw_onsale_banner_show'             => '0',
			'hw_onsale_banner_title'            => '',
			'hw_onsale_banner_subtitle'         => '',
			'hw_onsale_banner_cta_text'         => '',
			'hw_onsale_banner_cta_link'         => '',
			'hw_onsale_banner_overlay_opacity'  => '0.3',
			'hw_onsale_banner_height_desktop'   => '400',
			'hw_onsale_banner_height_mobile'    => '300',
			'hw_onsale_banner_alignment'        => 'center',
			'hw_onsale_hover_add_to_cart'       => '1',
			'hw_onsale_slider_dots'             => '1',
			'hw_onsale_prefetch_next'           => '0',
			'hw_onsale_fetchpriority_first_row' => '1',
		);

		foreach ( $defaults as $key => $value ) {
			if ( false === get_option( $key ) ) {
				add_option( $key, $value );
			}
		}
	}

	/**
	 * Deactivate plugin
	 */
	public function deactivate() {
		// Remove capability.
		$role = get_role( 'administrator' );
		if ( $role ) {
			$role->remove_cap( 'manage_hw_onsale' );
		}

		flush_rewrite_rules();
	}

	/**
	 * WooCommerce missing notice
	 */
	public function woocommerce_missing_notice() {
		?>
		<div class="notice notice-error">
			<p><?php esc_html_e( 'HW On Sale requires WooCommerce to be installed and active.', 'hw-onsale' ); ?></p>
		</div>
		<?php
	}
}

// Bootstrap plugin.
Plugin::instance();
