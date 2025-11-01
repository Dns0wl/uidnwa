<?php
/**
 * Compute Max Discount Use Case
 *
 * @package HW_Onsale\Application\UseCases
 */

namespace HW_Onsale\Application\UseCases;

use HW_Onsale\Domain\ValueObjects\Discount;

/**
 * Compute Max Discount Use Case
 */
class ComputeMaxDiscount {
	/**
	 * Execute use case for a product
	 *
	 * @param \WC_Product $product WooCommerce product.
	 * @return int Discount percentage.
	 */
	public function execute( $product ) {
		if ( $product->is_type( 'variable' ) ) {
			return $this->compute_variable_discount( $product );
		}

		return $this->compute_simple_discount( $product );
	}

	/**
	 * Compute discount for simple product
	 *
	 * @param \WC_Product $product Product.
	 * @return int
	 */
	private function compute_simple_discount( $product ) {
		$regular = (float) $product->get_regular_price();
		$sale    = (float) $product->get_sale_price();

		if ( ! $sale || ! $regular ) {
			return 0;
		}

		$discount = Discount::from_prices( $regular, $sale );

		return apply_filters( 'hw_onsale_discount_max', $discount->get_rounded(), $product );
	}

	/**
	 * Compute max discount for variable product
	 *
	 * @param \WC_Product_Variable $product Variable product.
	 * @return int
	 */
	private function compute_variable_discount( $product ) {
		$variations = $product->get_available_variations();
		$max_pct    = 0;

		foreach ( $variations as $variation_data ) {
			$variation = wc_get_product( $variation_data['variation_id'] );
			if ( ! $variation ) {
				continue;
			}

			$regular = (float) $variation->get_regular_price();
			$sale    = (float) $variation->get_sale_price();

			if ( ! $sale || ! $regular ) {
				continue;
			}

			$discount = Discount::from_prices( $regular, $sale );
			$pct      = $discount->get_rounded();

			if ( $pct > $max_pct ) {
				$max_pct = $pct;
			}
		}

		return apply_filters( 'hw_onsale_discount_max', $max_pct, $product );
	}
}
