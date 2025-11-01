<?php
/**
 * Discount Value Object
 *
 * @package HW_Onsale\Domain\ValueObjects
 */

namespace HW_Onsale\Domain\ValueObjects;

/**
 * Discount Value Object
 */
class Discount {
	/**
	 * Percentage value
	 *
	 * @var float
	 */
	private $percentage;

	/**
	 * Constructor
	 *
	 * @param float $percentage Discount percentage (0-100).
	 */
	public function __construct( $percentage ) {
		$this->percentage = max( 0, min( 100, (float) $percentage ) );
	}

	/**
	 * Calculate discount from regular and sale price
	 *
	 * @param float $regular_price Regular price.
	 * @param float $sale_price Sale price.
	 * @return Discount
	 */
	public static function from_prices( $regular_price, $sale_price ) {
		if ( ! $regular_price || ! $sale_price || $sale_price >= $regular_price ) {
			return new self( 0 );
		}

		$percentage = ( ( $regular_price - $sale_price ) / $regular_price ) * 100;
		return new self( $percentage );
	}

	/**
	 * Get percentage value
	 *
	 * @return float
	 */
	public function get_percentage() {
		return $this->percentage;
	}

	/**
	 * Get rounded percentage
	 *
	 * @return int
	 */
	public function get_rounded() {
		return (int) round( $this->percentage );
	}

	/**
	 * Check if discount is zero
	 *
	 * @return bool
	 */
	public function is_zero() {
		return $this->percentage <= 0;
	}

	/**
	 * String representation
	 *
	 * @return string
	 */
	public function __toString() {
		return $this->get_rounded() . '%';
	}
}
