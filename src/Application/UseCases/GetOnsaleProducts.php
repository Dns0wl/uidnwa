<?php
/**
 * Get Onsale Products Use Case
 *
 * @package HW_Onsale\Application\UseCases
 */

namespace HW_Onsale\Application\UseCases;

use HW_Onsale\Domain\Repositories\ProductRepositoryInterface;
use HW_Onsale\Application\DTOs\ProductCardDTO;

/**
 * Get Onsale Products Use Case
 */
class GetOnsaleProducts {
	/**
	 * Product repository
	 *
	 * @var ProductRepositoryInterface
	 */
	private $product_repo;

	/**
	 * Constructor
	 *
	 * @param ProductRepositoryInterface $product_repo Product repository.
	 */
	public function __construct( ProductRepositoryInterface $product_repo ) {
		$this->product_repo = $product_repo;
	}

	/**
	 * Execute use case
	 *
	 * @param int   $offset Offset.
	 * @param int   $limit Limit.
	 * @param array $filters Filters array.
	 * @return array
	 */
	public function execute( $offset = 0, $limit = 12, $filters = array() ) {
		$products = $this->product_repo->get_onsale_products( $offset, $limit, $filters );
		$total    = $this->product_repo->count_onsale_products( $filters );

		$cards = array_map(
			function ( $product ) {
				return new ProductCardDTO(
					$product->get_id(),
					$product->get_name(),
					$product->get_permalink(),
					$product->get_images(),
					$product->get_price_html(),
                                        $product->get_discount_pct(),
                                        $product->is_variable(),
                                        $product->get_add_to_cart_url(),
                                        $product->get_add_to_cart_text(),
                                        $product->get_materials(),
                                        $product->get_size()
                                );
                        },
                        $products
                );

		return array(
			'cards' => array_map(
				function ( $card ) {
					return $card->to_array();
				},
				$cards
			),
			'found' => $total,
		);
	}
}
