<?php
/**
 * Order Attribution
 *
 * @package HW_Onsale\Infrastructure\Attribution
 */

namespace HW_Onsale\Infrastructure\Attribution;

/**
 * Order Attribution Class
 */
class OrderAttribution {
	/**
	 * Cookie name
	 */
	const COOKIE_NAME = 'hw_onsale_visit';

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'template_redirect', array( $this, 'set_visit_cookie' ) );
		add_action( 'woocommerce_thankyou', array( $this, 'attribute_order' ) );
	}

	/**
	 * Set visit cookie when on /onsale page
	 */
	public function set_visit_cookie() {
		$onsale_page_id = get_option( 'hw_onsale_page_id' );

		if ( ! $onsale_page_id || ! is_page( $onsale_page_id ) ) {
			return;
		}

		$expiry = time() + $this->get_attribution_window();
		setcookie( self::COOKIE_NAME, time(), $expiry, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
	}

	/**
	 * Attribute order if conditions met
	 *
	 * @param int $order_id Order ID.
	 */
	public function attribute_order( $order_id ) {
		if ( ! isset( $_COOKIE[ self::COOKIE_NAME ] ) ) {
			return;
		}

		$visit_time = (int) $_COOKIE[ self::COOKIE_NAME ];
		$now        = time();
		$window     = $this->get_attribution_window();

		// Check if within attribution window.
		if ( ( $now - $visit_time ) > $window ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		// Check if order contains at least one product from private-sale category.
		$has_onsale_product = false;

		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			if ( ! $product ) {
				continue;
			}

			if ( has_term( 'private-sale', 'product_cat', $product->get_id() ) ) {
				$has_onsale_product = true;
				break;
			}
		}

		if ( $has_onsale_product ) {
			$order->update_meta_data( '_hw_onsale_attributed', 'yes' );
			$order->save();
		}
	}

	/**
	 * Get attribution window in seconds
	 *
	 * @return int
	 */
	private function get_attribution_window() {
		$hours = apply_filters( 'hw_onsale_attribution_window_hours', (int) get_option( 'hw_onsale_attribution_window', 24 ) );
		return $hours * HOUR_IN_SECONDS;
	}
}
