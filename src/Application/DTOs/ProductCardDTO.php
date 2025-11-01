<?php
/**
 * Product Card DTO
 *
 * @package HW_Onsale\Application\DTOs
 */

namespace HW_Onsale\Application\DTOs;

/**
 * Product Card DTO
 */
class ProductCardDTO {
	/**
	 * Product ID
	 *
	 * @var int
	 */
	public $id;

	/**
	 * Product name
	 *
	 * @var string
	 */
	public $name;

	/**
	 * Product permalink
	 *
	 * @var string
	 */
	public $permalink;

	/**
	 * Images
	 *
	 * @var array
	 */
	public $images;

	/**
	 * Price HTML
	 *
	 * @var string
	 */
	public $price_html;

	/**
	 * Discount percentage
	 *
	 * @var int
	 */
	public $discount_pct;

	/**
	 * Is variable
	 *
	 * @var bool
	 */
	public $is_variable;

	/**
	 * Add to cart URL
	 *
	 * @var string
	 */
	public $add_to_cart_url;

        /**
         * Add to cart text
         *
         * @var string
         */
        public $add_to_cart_text;

        /**
         * Genuine material child categories
         *
         * @var array
         */
        public $materials;

        /**
         * Product size information
         *
         * @var string
         */
        public $size;

	/**
	 * Constructor
	 *
	 * @param int    $id Product ID.
	 * @param string $name Product name.
	 * @param string $permalink Permalink.
	 * @param array  $images Images.
	 * @param string $price_html Price HTML.
	 * @param int    $discount_pct Discount percentage.
	 * @param bool   $is_variable Is variable.
	 * @param string $add_to_cart_url Add to cart URL.
         * @param string $add_to_cart_text Add to cart text.
         * @param array  $materials Genuine material categories.
         * @param string $size Product size information.
         */
        public function __construct(
                $id,
                $name,
                $permalink,
                array $images,
                $price_html,
                $discount_pct,
                $is_variable,
                $add_to_cart_url,
                $add_to_cart_text,
                array $materials = array(),
                $size = ''
        ) {
                $this->id                = $id;
                $this->name              = $name;
                $this->permalink         = $permalink;
                $this->images            = $images;
                $this->price_html        = $price_html;
                $this->discount_pct      = $discount_pct;
                $this->is_variable       = $is_variable;
                $this->add_to_cart_url   = $add_to_cart_url;
                $this->add_to_cart_text  = $add_to_cart_text;
                $this->materials         = $materials;
                $this->size              = $size;
        }

	/**
	 * Convert to array
	 *
	 * @return array
	 */
	public function to_array() {
		return array(
			'id'                => $this->id,
			'name'              => $this->name,
			'permalink'         => $this->permalink,
			'images'            => $this->images,
                        'price_html'        => $this->price_html,
                        'discount_pct'      => $this->discount_pct,
                        'is_variable'       => $this->is_variable,
                        'add_to_cart_url'   => $this->add_to_cart_url,
                        'add_to_cart_text'  => $this->add_to_cart_text,
                        'materials'         => $this->materials,
                        'categories'        => $this->materials,
                        'size'              => $this->size,
                );
        }
}
