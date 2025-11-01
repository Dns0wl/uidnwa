<?php
/**
 * Product Repository Interface
 *
 * @package HW_Onsale\Domain\Repositories
 */

namespace HW_Onsale\Domain\Repositories;

/**
 * Product Repository Interface
 */
interface ProductRepositoryInterface {
	/**
	 * Get on-sale products
	 *
	 * @param int $offset Offset.
	 * @param int $limit Limit.
	 * @return array Array of ProductCard entities.
	 */
        public function get_onsale_products( $offset = 0, $limit = 12, $filters = array() );

        /**
         * Count total on-sale products
         *
         * @return int
         */
        public function count_onsale_products( $filters = array() );

        /**
         * Get product option data for quick add modal
         *
         * @param int $product_id Product ID.
         * @return array|\WP_Error
         */
        public function get_product_options( $product_id );
}
