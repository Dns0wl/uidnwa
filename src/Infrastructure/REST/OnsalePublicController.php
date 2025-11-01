<?php
/**
 * Onsale Public REST Controller
 *
 * @package HW_Onsale\Infrastructure\REST
 */

namespace HW_Onsale\Infrastructure\REST;

use HW_Onsale\Domain\Repositories\ProductRepositoryInterface;
use HW_Onsale\Application\UseCases\GetOnsaleProducts;

/**
 * Onsale Public Controller
 */
class OnsalePublicController {
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
	 * Register routes
	 */
        public function register_routes() {
                register_rest_route(
                        'hw-onsale/v1',
                        '/list',
                        array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_products' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'offset'       => array(
						'required'          => false,
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
					'limit'        => array(
						'required'          => false,
						'default'           => 12,
						'sanitize_callback' => 'absint',
					),
					'orderby'      => array(
						'required'          => false,
						'default'           => 'discount-desc',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'min_price'    => array(
						'required'          => false,
						'sanitize_callback' => 'absint',
					),
					'max_price'    => array(
						'required'          => false,
						'sanitize_callback' => 'absint',
					),
					'min_discount' => array(
						'required'          => false,
						'sanitize_callback' => 'absint',
					),
					'in_stock'     => array(
						'required'          => false,
						'sanitize_callback' => 'absint',
					),
					'categories'   => array(
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
                                ),
                        )
                );

                register_rest_route(
                        'hw-onsale/v1',
                        '/product/(?P<id>\d+)/options',
                        array(
                                'methods'             => 'GET',
                                'callback'            => array( $this, 'get_product_options' ),
                                'permission_callback' => '__return_true',
                                'args'                => array(
                                        'id' => array(
                                                'required'          => true,
                                                'sanitize_callback' => 'absint',
                                        ),
                                ),
                        )
                );
        }

        /**
         * Get products endpoint
         *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_products( $request ) {
		$offset       = $request->get_param( 'offset' );
		$limit        = $request->get_param( 'limit' );
		$orderby      = $request->get_param( 'orderby' );
		$min_price    = $request->get_param( 'min_price' );
		$max_price    = $request->get_param( 'max_price' );
		$min_discount = $request->get_param( 'min_discount' );
		$in_stock     = $request->get_param( 'in_stock' );
		$categories   = $request->get_param( 'categories' );

		$filters = array(
			'orderby'      => $orderby,
			'min_price'    => $min_price,
			'max_price'    => $max_price,
			'min_discount' => $min_discount,
			'in_stock'     => $in_stock,
			'categories'   => $categories,
		);

		$use_case = new GetOnsaleProducts( $this->product_repo );
		$result   = $use_case->execute( $offset, $limit, $filters );

                return rest_ensure_response( $result );
        }

        /**
         * Get product options endpoint
         *
         * @param \WP_REST_Request $request Request object.
         * @return \WP_REST_Response|\WP_Error
         */
        public function get_product_options( $request ) {
                $product_id = absint( $request->get_param( 'id' ) );

                if ( ! $product_id ) {
                        return new \WP_Error( 'hw_product_invalid', __( 'Invalid product.', 'hw-onsale' ), array( 'status' => 400 ) );
                }

                $result = $this->product_repo->get_product_options( $product_id );

                if ( is_wp_error( $result ) ) {
                        return $result;
                }

                return rest_ensure_response( $result );
        }
}
