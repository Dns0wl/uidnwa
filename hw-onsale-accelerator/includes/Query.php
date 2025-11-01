<?php
namespace HW_Onsale_Accelerator;

defined( 'ABSPATH' ) || exit;

use WC_Product;
use WC_Product_Query;
use WC_Product_Variable;
use WP_Error;

/**
 * Query layer orchestrating WooCommerce data fetching and caching.
 */
class Query {
        /**
         * Cache handler.
         *
         * @var Cache
         */
        private $cache;

        /**
         * Constructor.
         *
         * @param Cache $cache Cache helper.
         */
        public function __construct( ?Cache $cache = null ) {
                $this->cache = $cache ?: new Cache();
        }

        /**
         * Get a page of products with aggregate information.
         *
         * @param array $params Request params (page, per_page, filters).
         * @return array
         */
        public function get_page( array $params ) {
                $normalized = $this->normalize_params( $params );
                $dataset    = $this->get_dataset( $normalized['filters'] );

                $page     = $normalized['page'];
                $per_page = $normalized['per_page'];
                $offset   = ( $page - 1 ) * $per_page;

                $products    = array_slice( $dataset['products'], $offset, $per_page );
                $total       = $dataset['total'];
                $total_pages = $per_page > 0 ? (int) ceil( $total / $per_page ) : 1;

                return array(
                        'products'    => $products,
                        'total'       => $total,
                        'total_pages' => $total_pages,
                        'page'        => $page,
                        'per_page'    => $per_page,
                        'price_range' => $dataset['price_range'],
                        'dataset_key' => $dataset['cache_key'],
                );
        }

        /**
         * Retrieve cached dataset meta without slicing.
         *
         * @param array $filters Filters without pagination.
         * @return array
         */
        public function get_dataset( array $filters ) {
                $key = $this->cache->build_key( 'dataset', $filters );
                $hit = $this->cache->get( $key );

                if ( false !== $hit && is_array( $hit ) ) {
                        $hit['cache_key'] = $key;
                        return $hit;
                }

                $products   = array();
                $min_price  = null;
                $max_price  = null;
                $query_args = $this->build_query_args( $filters );

                $product_query = new WC_Product_Query( $query_args );
                $ids           = $product_query->get_products();

                if ( empty( $ids ) ) {
                        $dataset = array(
                                'products'    => array(),
                                'total'       => 0,
                                'price_range' => array(
                                        'min' => 0,
                                        'max' => 0,
                                ),
                                'cache_key'   => $key,
                        );
                        $this->cache->set( $key, $dataset );
                        return $dataset;
                }

                $seen = array();

                foreach ( $ids as $id ) {
                        $product = wc_get_product( $id );

                        if ( ! $product instanceof WC_Product ) {
                                continue;
                        }

                        // Normalise to parent product for variations.
                        if ( $product->is_type( 'variation' ) ) {
                                $parent_id = $product->get_parent_id();
                                if ( $parent_id ) {
                                        $seen[ $parent_id ] = true;
                                }
                                continue;
                        }

                        $seen[ $product->get_id() ] = true;
                }

                foreach ( array_keys( $seen ) as $product_id ) {
                        $product = wc_get_product( $product_id );

                        if ( ! $product instanceof WC_Product ) {
                                continue;
                        }

                        if ( ! $product->is_purchasable() ) {
                                continue;
                        }

                        if ( ! $product->is_in_stock() && ! empty( $filters['in_stock'] ) ) {
                                continue;
                        }

                        $price_for_filter = $this->resolve_price_for_filter( $product );

                        if ( null !== $filters['min_price'] && $price_for_filter < $filters['min_price'] ) {
                                continue;
                        }

                        if ( null !== $filters['max_price'] && $price_for_filter > $filters['max_price'] ) {
                                continue;
                        }

                        $prepared = $this->format_product( $product );

                        if ( null === $prepared ) {
                                continue;
                        }

                        $prepared['price_value'] = $price_for_filter;
                        $products[]              = $prepared;

                        if ( null === $min_price || $price_for_filter < $min_price ) {
                                $min_price = $price_for_filter;
                        }

                        if ( null === $max_price || $price_for_filter > $max_price ) {
                                $max_price = $price_for_filter;
                        }
                }

                $products = $this->sort_products( $products, $filters['orderby'] );

                $dataset = array(
                        'products'    => $products,
                        'total'       => count( $products ),
                        'price_range' => array(
                                'min' => null === $min_price ? 0 : (int) floor( $min_price ),
                                'max' => null === $max_price ? 0 : (int) ceil( $max_price ),
                        ),
                        'cache_key'   => $key,
                );

                $this->cache->set( $key, $dataset );

                return $dataset;
        }

        /**
         * Normalize params to predictable structure.
         *
         * @param array $params Raw params.
         * @return array
         */
	private function normalize_params( array $params ) {
		$page     = isset( $params['page'] ) ? max( 1, (int) $params['page'] ) : 1;
		$per_page = isset( $params['per_page'] ) ? max( 1, (int) $params['per_page'] ) : (int) get_option( 'hw_onsale_batch_size', 12 );

		$filters = array(
			'orderby'    => isset( $params['orderby'] ) ? sanitize_text_field( $params['orderby'] ) : 'discount-desc',
			'min_price'  => isset( $params['min_price'] ) && '' !== $params['min_price'] ? max( 0, (int) $params['min_price'] ) : null,
			'max_price'  => isset( $params['max_price'] ) && '' !== $params['max_price'] ? max( 0, (int) $params['max_price'] ) : null,
			'categories' => $this->normalise_categories( isset( $params['categories'] ) ? $params['categories'] : array() ),
			'in_stock'   => ! empty( $params['in_stock'] ) ? 1 : 0,
		);

		$per_page = $this->apply_discount_batch_size( $per_page, $filters['orderby'] );

		return array(
			'page'     => $page,
			'per_page' => $per_page,
			'filters'  => $filters,
		);
	}

	/**
	 * Apply discount sort batch size cap.
	 *
	 * @param int    $per_page Requested per page value.
	 * @param string $orderby  Requested orderby.
	 * @return int
	 */
	private function apply_discount_batch_size( $per_page, $orderby ) {
		$per_page = max( 1, (int) $per_page );

		if ( 'discount-desc' === $orderby ) {
			return 12;
}

		return $per_page;
}

        /**
         * Normalize category input to array of ints.
         *
         * @param mixed $categories Raw categories param.
         * @return array
         */
        private function normalise_categories( $categories ) {
                if ( empty( $categories ) ) {
                        return array();
                }

                if ( is_string( $categories ) ) {
                        $parts = array_map( 'trim', explode( ',', $categories ) );
                } elseif ( is_array( $categories ) ) {
                        $parts = $categories;
                } else {
                        $parts = array();
                }

                $ids = array();
                foreach ( $parts as $part ) {
                        $id = (int) $part;
                        if ( $id > 0 ) {
                                $ids[] = $id;
                        }
                }

                return array_values( array_unique( $ids ) );
        }

        /**
         * Build WC_Product_Query arguments.
         *
         * @param array $filters Filters.
         * @return array
         */
        private function build_query_args( array $filters ) {
                $args = array(
                        'status'    => 'publish',
                        'limit'     => -1,
                        'return'    => 'ids',
                        'paginate'  => false,
                        'on_sale'   => true,
                        'meta_key'  => '',
                        'orderby'   => 'date',
                        'order'     => 'DESC',
                );

                if ( ! empty( $filters['categories'] ) ) {
                        $args['tax_query'] = array(
                                array(
                                        'taxonomy'         => 'product_cat',
                                        'field'            => 'term_id',
                                        'terms'            => $filters['categories'],
                                        'include_children' => true,
                                ),
                        );
                }

                if ( ! empty( $filters['in_stock'] ) ) {
                        $args['stock_status'] = 'instock';
                }

                return apply_filters( 'hw_onsale_acc_query_args', $args, $filters );
        }

        /**
         * Sort prepared products by requested order.
         *
         * @param array  $products Prepared products.
         * @param string $orderby  Order directive.
         * @return array
         */
        private function sort_products( array $products, $orderby ) {
                if ( empty( $products ) ) {
                        return $products;
                }

                switch ( $orderby ) {
                        case 'price-asc':
                                usort(
                                        $products,
                                        static function ( $a, $b ) {
                                                return $a['price_value'] <=> $b['price_value'];
                                        }
                                );
                                break;
                        case 'price-desc':
                                usort(
                                        $products,
                                        static function ( $a, $b ) {
                                                return $b['price_value'] <=> $a['price_value'];
                                        }
                                );
                                break;
                        case 'discount-desc':
                        default:
                                usort(
                                        $products,
                                        static function ( $a, $b ) {
                                                return $b['discount_pct'] <=> $a['discount_pct'];
                                        }
                                );
                                break;
                }

                return $products;
        }

        /**
         * Resolve comparable price for filtering.
         *
         * @param WC_Product $product Product.
         * @return float
         */
        private function resolve_price_for_filter( WC_Product $product ) {
                if ( $product->is_type( 'variable' ) ) {
                        $price = (float) $product->get_variation_sale_price( 'min', true );
                        if ( $price <= 0 ) {
                                $price = (float) $product->get_variation_price( 'min', true );
                        }
                        return $price;
                }

                $price = (float) $product->get_sale_price();
                if ( $price <= 0 ) {
                        $price = (float) $product->get_price();
                }

                return $price;
        }

        /**
         * Prepare product payload for rendering and API.
         *
         * @param WC_Product $product Product.
         * @return array|null
         */
        private function format_product( WC_Product $product ) {
                $images = $this->get_product_images( $product );

                if ( empty( $images ) ) {
                        $images[] = array(
                                'desktop' => wc_placeholder_img_src(),
                                'mobile'  => wc_placeholder_img_src(),
                                'alt'     => $product->get_name(),
                                'width'   => 480,
                                'height'  => 600,
                        );
                }

                return array(
                        'id'               => $product->get_id(),
                        'name'             => $product->get_name(),
                        'permalink'        => $product->get_permalink(),
                        'images'           => $images,
                        'price_html'       => $this->get_price_html( $product ),
                        'discount_pct'     => $this->calculate_discount_pct( $product ),
                        'is_variable'      => $product->is_type( 'variable' ),
                        'materials'        => $this->get_material_terms( $product ),
                        'categories'       => $this->get_material_terms( $product ),
                        'size'             => $this->extract_size_from_content( $product ),
                        'add_to_cart_url'  => $product->add_to_cart_url(),
                        'add_to_cart_text' => $product->add_to_cart_text(),
                );
        }

        /**
         * Calculate discount percentage.
         *
         * @param WC_Product $product Product.
         * @return int
         */
        private function calculate_discount_pct( WC_Product $product ) {
                if ( $product->is_type( 'variable' ) ) {
                        $max = 0;
                        if ( $product instanceof WC_Product_Variable ) {
                                foreach ( $product->get_available_variations() as $variation_data ) {
                                        if ( empty( $variation_data['variation_id'] ) ) {
                                                continue;
                                        }

                                        $variation = wc_get_product( $variation_data['variation_id'] );
                                        if ( ! $variation instanceof WC_Product ) {
                                                continue;
                                        }

                                        $regular = (float) $variation->get_regular_price();
                                        $sale    = (float) $variation->get_sale_price();

                                        if ( $sale > 0 && $regular > 0 && $sale < $regular ) {
                                                $pct = round( ( ( $regular - $sale ) / $regular ) * 100 );
                                                if ( $pct > $max ) {
                                                        $max = $pct;
                                                }
                                        }
                                }
                        }

                        return $max;
                }

                $regular = (float) $product->get_regular_price();
                $sale    = (float) $product->get_sale_price();

                if ( $sale > 0 && $regular > 0 && $sale < $regular ) {
                        return (int) round( ( ( $regular - $sale ) / $regular ) * 100 );
                }

                return 0;
        }

        /**
         * Build responsive image data.
         *
         * @param WC_Product $product Product.
         * @return array
         */
        private function get_product_images( WC_Product $product ) {
                $image_ids = array();

                if ( $product->get_image_id() ) {
                        $image_ids[] = $product->get_image_id();
                }

                $gallery_ids = $product->get_gallery_image_ids();
                if ( ! empty( $gallery_ids ) ) {
                        $image_ids = array_merge( $image_ids, $gallery_ids );
                }

                $images = array();

                foreach ( $image_ids as $image_id ) {
                        $images[] = $this->get_responsive_image_data( $image_id );
                }

                return $images;
        }

        /**
         * Convert attachment to responsive metadata.
         *
         * @param int $image_id Attachment ID.
         * @return array
         */
        private function get_responsive_image_data( $image_id ) {
                $desktop_size = array( 480, 600 );
                $mobile_size  = array( 280, 350 );

                $desktop_src = wp_get_attachment_image_src( $image_id, $desktop_size );
                $mobile_src  = wp_get_attachment_image_src( $image_id, $mobile_size );

                $alt = get_post_meta( $image_id, '_wp_attachment_image_alt', true );

                return array(
                        'desktop' => $desktop_src ? $desktop_src[0] : wc_placeholder_img_src(),
                        'mobile'  => $mobile_src ? $mobile_src[0] : wc_placeholder_img_src(),
                        'alt'     => $alt ? $alt : '',
                        'width'   => $desktop_src ? $desktop_src[1] : 480,
                        'height'  => $desktop_src ? $desktop_src[2] : 600,
                );
        }

        /**
         * Generate enhanced price HTML.
         *
         * @param WC_Product $product Product.
         * @return string
         */
        private function get_price_html( WC_Product $product ) {
                if ( $product->is_type( 'variable' ) ) {
                        $variations = $product->get_available_variations();
                        if ( empty( $variations ) ) {
                                return $product->get_price_html();
                        }

                        $max_regular = 0;
                        $min_sale    = PHP_FLOAT_MAX;
                        $has_sale    = false;

                        foreach ( $variations as $variation_data ) {
                                $variation = wc_get_product( $variation_data['variation_id'] );
                                if ( ! $variation instanceof WC_Product ) {
                                        continue;
                                }

                                $regular = (float) $variation->get_regular_price();
                                $sale    = (float) $variation->get_sale_price();

                                if ( $regular > $max_regular ) {
                                        $max_regular = $regular;
                                }

                                if ( $sale > 0 && $sale < $min_sale ) {
                                        $min_sale = $sale;
                                        $has_sale = true;
                                } elseif ( $sale <= 0 && $regular > 0 && $regular < $min_sale ) {
                                        $min_sale = $regular;
                                }
                        }

                        if ( $has_sale && $max_regular > 0 && $min_sale < PHP_FLOAT_MAX ) {
                                return '<del>' . wc_price( $max_regular ) . '</del> <ins>' . wc_price( $min_sale ) . '</ins>';
                        }

                        return $product->get_price_html();
                }

                if ( $product->is_on_sale() ) {
                        $regular = (float) $product->get_regular_price();
                        $sale    = (float) $product->get_sale_price();

                        if ( $sale > 0 && $regular > 0 && $sale < $regular ) {
                                return '<span class="price"><del>' . wc_price( $regular ) . '</del> <ins>' . wc_price( $sale ) . '</ins></span>';
                        }
                }

                return $product->get_price_html();
        }

        /**
         * Collect material child terms (matches original behaviour).
         *
         * @param WC_Product $product Product.
         * @return array
         */
        private function get_material_terms( WC_Product $product ) {
                $terms = get_the_terms( $product->get_id(), 'product_cat' );

                if ( empty( $terms ) || is_wp_error( $terms ) ) {
                        return array();
                }

                $materials_parent_id = 111;
                $materials           = array();

                foreach ( $terms as $term ) {
                        $parent_id = isset( $term->parent ) ? (int) $term->parent : 0;
                        if ( $parent_id !== $materials_parent_id ) {
                                continue;
                        }

                        $link = get_term_link( $term );
                        if ( is_wp_error( $link ) ) {
                                $link = '';
                        }

                        $materials[] = array(
                                'id'   => (int) $term->term_id,
                                'name' => $term->name,
                                'slug' => $term->slug,
                                'link' => $link ? esc_url_raw( $link ) : '',
                        );
                }

                return $materials;
        }

        /**
         * Extract indicative size from description.
         *
         * @param WC_Product $product Product.
         * @return string
         */
        private function extract_size_from_content( WC_Product $product ) {
                $sources = array(
                        $product->get_short_description(),
                        $product->get_description(),
                );

                foreach ( $sources as $content ) {
                        $size = $this->parse_size_from_text( $content );
                        if ( '' !== $size ) {
                                return $size;
                        }
                }

                return '';
        }

        /**
         * Retrieve product options for quick modal.
         *
         * @param int $product_id Product ID.
         * @return array|WP_Error
         */
        public function get_product_options( $product_id ) {
                $product_id = absint( $product_id );
                if ( $product_id <= 0 ) {
                        return new WP_Error( 'hw_product_invalid', __( 'Invalid product.', 'hw-onsale' ), array( 'status' => 400 ) );
                }

                $product = wc_get_product( $product_id );

                if ( ! $product instanceof WC_Product ) {
                        return new WP_Error( 'hw_product_not_found', __( 'Product not found.', 'hw-onsale' ), array( 'status' => 404 ) );
                }

                $cache_key = $this->cache->build_key(
                        'product_options',
                        array(
                                'id'       => $product_id,
                                'modified' => get_post_modified_time( 'U', true, $product_id ),
                        )
                );

                $cached = $this->cache->get( $cache_key );
                if ( false !== $cached ) {
                        return $cached;
                }

                $response = array(
                        'product_id'              => $product_id,
                        'name'                    => $product->get_name(),
                        'base_price_html'         => wp_kses_post( $this->get_price_html( $product ) ),
                        'attributes'              => array(),
                        'variations'              => array(),
                        'has_in_stock_variations' => false,
                );

                if ( ! $product->is_type( 'variable' ) ) {
                        $simple_add_to_cart_url = $product->add_to_cart_url();
                        $response['simple_add_to_cart_url'] = esc_url_raw( $simple_add_to_cart_url );
                        $response['simple_checkout_url']    = esc_url_raw( $this->convert_cart_url_to_checkout( $simple_add_to_cart_url ) );

                        $this->cache->set( $cache_key, $response );
                        return $response;
                }

                $default_attributes   = $product->get_default_attributes();
                $variation_attributes = $product->get_variation_attributes();
                $available_variations = $product->get_available_variations();
                $variations           = array();
                $in_stock_values      = array();
                $has_in_stock         = false;

                foreach ( $available_variations as $variation_data ) {
                        if ( empty( $variation_data['variation_id'] ) ) {
                                continue;
                        }

                        $variation = wc_get_product( $variation_data['variation_id'] );
                        if ( ! $variation instanceof WC_Product ) {
                                continue;
                        }

                        $attributes = array();

                        if ( isset( $variation_data['attributes'] ) && is_array( $variation_data['attributes'] ) ) {
                                foreach ( $variation_data['attributes'] as $key => $value ) {
                                        $normalized_key = $this->normalize_attribute_slug( $key );

                                        if ( '' === $normalized_key ) {
                                                continue;
                                        }

                                        $attributes[ $normalized_key ] = $value;

                                        if ( $variation->is_in_stock() && '' !== $value ) {
                                                if ( ! isset( $in_stock_values[ $normalized_key ] ) ) {
                                                        $in_stock_values[ $normalized_key ] = array();
                                                }

                                                $in_stock_values[ $normalized_key ][ $value ] = true;
                                        }
                                }
                        }

                        $image_payload = $this->build_variation_image_payload( $variation_data, $variation );

                        $add_to_cart_url = '';
                        $checkout_url    = '';

                        if ( $variation->is_in_stock() ) {
                                $has_in_stock     = true;
                                $add_to_cart_url  = add_query_arg(
                                        array(
                                                'add-to-cart'  => $product_id,
                                                'variation_id' => $variation_data['variation_id'],
                                                'quantity'     => 1,
                                        ),
                                        wc_get_cart_url()
                                );

                                foreach ( $attributes as $key => $value ) {
                                        if ( '' === $value ) {
                                                continue;
                                        }
                                        $add_to_cart_url = add_query_arg( $key, $value, $add_to_cart_url );
                                }

                                $checkout_url = $this->convert_cart_url_to_checkout( $add_to_cart_url );
                        }

                        $variations[] = array(
                                'id'              => (int) $variation_data['variation_id'],
                                'is_in_stock'     => (bool) $variation->is_in_stock(),
                                'price_html'      => wp_kses_post( $this->get_price_html( $variation ) ),
                                'attributes'      => $attributes,
                                'add_to_cart_url' => esc_url_raw( $add_to_cart_url ),
                                'checkout_url'    => esc_url_raw( $checkout_url ),
                                'image'           => $image_payload,
                        );
                }

                $prepared_attributes = array();

                foreach ( $variation_attributes as $attribute_key => $options ) {
                        $attribute_slug = $this->normalize_attribute_slug( $attribute_key );

                        if ( '' === $attribute_slug ) {
                                continue;
                        }

                        $taxonomy        = str_replace( 'attribute_', '', $attribute_slug );
                        $attribute_label = wc_attribute_label( $taxonomy );
                        $option_items    = array();

                        foreach ( $options as $option ) {
                                $is_allowed = true;

                                if ( '' !== $option ) {
                                        if ( isset( $in_stock_values[ $attribute_slug ] ) ) {
                                                $is_allowed = isset( $in_stock_values[ $attribute_slug ][ $option ] );
                                        } else {
                                                $is_allowed = false;
                                        }
                                }

                                if ( ! $is_allowed ) {
                                        continue;
                                }

                                $label = $option;

                                if ( taxonomy_exists( $taxonomy ) ) {
                                        $term = get_term_by( 'slug', $option, $taxonomy );
                                        if ( $term && ! is_wp_error( $term ) ) {
                                                $label = $term->name;
                                        }
                                } elseif ( ! empty( $option ) ) {
                                        $label = wc_clean( $option );
                                }

                                $option_items[] = array(
                                        'value' => $option,
                                        'label' => $label,
                                );
                        }

                        if ( empty( $option_items ) ) {
                                continue;
                        }

                        $label_text = trim( wp_strip_all_tags( $attribute_label ) );
                        if ( '' === $label_text ) {
                                $placeholder = __( 'Choose an option', 'hw-onsale' );
                        } else {
                                $placeholder = sprintf( __( 'Choose %s', 'hw-onsale' ), strtolower( $label_text ) );
                        }

                        $default_value = isset( $default_attributes[ $taxonomy ] ) ? $default_attributes[ $taxonomy ] : '';

                        if ( '' !== $default_value ) {
                                $has_default = isset( $in_stock_values[ $attribute_slug ][ $default_value ] );
                                if ( ! $has_default ) {
                                        $default_value = '';
                                }
                        }

                        $prepared_attributes[] = array(
                                'name'        => $attribute_label,
                                'slug'        => $attribute_slug,
                                'options'     => $option_items,
                                'default'     => $default_value,
                                'placeholder' => $placeholder,
                        );
                }

                $response['has_in_stock_variations'] = $has_in_stock;
                $response['attributes']              = $prepared_attributes;
                $response['variations']              = $variations;

                $this->cache->set( $cache_key, $response );

                return $response;
        }

        /**
         * Parse size string from HTML content.
         *
         * @param string $content Raw HTML.
         * @return string
         */
        private function parse_size_from_text( $content ) {
                if ( empty( $content ) ) {
                        return '';
                }

                $content = wp_strip_all_tags( $content );

                if ( preg_match( '/Size\\s*:?\\s*([A-Za-z0-9\-\s]+)/i', $content, $matches ) ) {
                        return trim( $matches[1] );
                }

                return '';
        }

        /**
         * Normalize attribute slug to expected format.
         *
         * @param string $slug Attribute slug.
         * @return string
         */
        private function normalize_attribute_slug( $slug ) {
                $slug = trim( (string) $slug );

                if ( '' === $slug ) {
                        return '';
                }

                if ( 0 === strpos( $slug, 'attribute_' ) ) {
                        $slug = substr( $slug, strlen( 'attribute_' ) );
                }

                $slug = ltrim( $slug, '_' );

                if ( '' === $slug ) {
                        return '';
                }

                return 'attribute_' . $slug;
        }

        /**
         * Build variation image payload for modal.
         *
         * @param array       $variation_data Variation data.
         * @param WC_Product  $variation      Variation product.
         * @return array
         */
        private function build_variation_image_payload( array $variation_data, WC_Product $variation ) {
                $image_src = '';
                $image_alt = '';

                if ( ! empty( $variation_data['image']['src'] ) ) {
                        $image_src = esc_url_raw( $variation_data['image']['src'] );

                        if ( ! empty( $variation_data['image']['alt'] ) ) {
                                $image_alt = sanitize_text_field( $variation_data['image']['alt'] );
                        }
                } elseif ( ! empty( $variation_data['image_id'] ) ) {
                        $image_data = wp_get_attachment_image_src( $variation_data['image_id'], 'woocommerce_single' );
                        if ( $image_data ) {
                                $image_src = esc_url_raw( $image_data[0] );
                        }

                        $alt = get_post_meta( $variation_data['image_id'], '_wp_attachment_image_alt', true );
                        if ( $alt ) {
                                $image_alt = sanitize_text_field( $alt );
                        }
                }

                if ( empty( $image_alt ) ) {
                        $image_alt = wp_strip_all_tags( $variation->get_name() );
                }

                return array(
                        'src' => $image_src,
                        'alt' => $image_alt,
                );
        }

        /**
         * Convert add-to-cart URL into checkout URL with preserved args.
         *
         * @param string $url Add to cart URL.
         * @return string
         */
        private function convert_cart_url_to_checkout( $url ) {
                if ( empty( $url ) ) {
                        return '';
                }

                $parsed = wp_parse_url( $url );

                if ( false === $parsed ) {
                        return '';
                }

                $query_args = array();

                if ( isset( $parsed['query'] ) ) {
                        wp_parse_str( $parsed['query'], $query_args );
                }

                if ( empty( $query_args ) ) {
                        return '';
                }

                unset( $query_args['_wpnonce'] );

                $query_args['hw-direct-checkout'] = 1;

                return add_query_arg( $query_args, wc_get_checkout_url() );
        }
}
