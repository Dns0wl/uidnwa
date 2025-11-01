<?php
/**
 * ProductCard Entity
 *
 * @package HW_Onsale\Domain\Entities
 */

namespace HW_Onsale\Domain\Entities;

/**
 * Product Card Entity
 */
class ProductCard {
	/**
	 * Product ID
	 *
	 * @var int
	 */
	private $id;

	/**
	 * Product name
	 *
	 * @var string
	 */
	private $name;

	/**
	 * Product permalink
	 *
	 * @var string
	 */
	private $permalink;

	/**
	 * Image URLs
	 *
	 * @var array
	 */
	private $images;

	/**
	 * Price HTML
	 *
	 * @var string
	 */
	private $price_html;

	/**
	 * Discount percentage
	 *
	 * @var float
	 */
	private $discount_pct;

	/**
	 * Is variable product
	 *
	 * @var bool
	 */
	private $is_variable;

	/**
	 * Add to cart URL
	 *
	 * @var string
	 */
	private $add_to_cart_url;

        /**
         * Add to cart text
         *
         * @var string
         */
        private $add_to_cart_text;

        /**
         * Genuine material categories
         *
         * @var array
         */
        private $materials;

        /**
         * Parsed size information
         *
         * @var string
         */
        private $size;

	/**
	 * Constructor
	 *
	 * @param int    $id Product ID.
	 * @param string $name Product name.
	 * @param string $permalink Product permalink.
	 * @param array  $images Image URLs.
	 * @param string $price_html Price HTML.
	 * @param float  $discount_pct Discount percentage.
	 * @param bool   $is_variable Is variable.
	 * @param string $add_to_cart_url Add to cart URL.
         * @param string $add_to_cart_text Add to cart text.
         * @param array  $materials Genuine material categories.
         * @param string $size Product size label.
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
	 * Get ID
	 *
	 * @return int
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * Get name
	 *
	 * @return string
	 */
	public function get_name() {
		return $this->name;
	}

	/**
	 * Get permalink
	 *
	 * @return string
	 */
	public function get_permalink() {
		return $this->permalink;
	}

	/**
	 * Get images
	 *
	 * @return array
	 */
	public function get_images() {
		return $this->images;
	}

	/**
	 * Get price HTML
	 *
	 * @return string
	 */
	public function get_price_html() {
		return $this->price_html;
	}

	/**
	 * Get discount percentage
	 *
	 * @return float
	 */
	public function get_discount_pct() {
		return $this->discount_pct;
	}

	/**
	 * Is variable product
	 *
	 * @return bool
	 */
	public function is_variable() {
		return $this->is_variable;
	}

	/**
	 * Get add to cart URL
	 *
	 * @return string
	 */
	public function get_add_to_cart_url() {
		return $this->add_to_cart_url;
	}

	/**
	 * Get add to cart text
	 *
	 * @return string
	 */
        public function get_add_to_cart_text() {
                return $this->add_to_cart_text;
        }

        /**
         * Get genuine material categories
         *
         * @return array
         */
        public function get_materials() {
                return $this->materials;
        }

        /**
         * Get parsed size information
         *
         * @return string
         */
        public function get_size() {
                return $this->size;
        }
}
