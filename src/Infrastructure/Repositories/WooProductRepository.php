<?php
/**
 * WooCommerce Product Repository
 *
 * @package HW_Onsale\Infrastructure\Repositories
 */

namespace HW_Onsale\Infrastructure\Repositories;

use HW_Onsale\Domain\Entities\ProductCard;
use HW_Onsale\Domain\Repositories\ProductRepositoryInterface;
use HW_Onsale\Application\UseCases\ComputeMaxDiscount;
use HW_Onsale\Infrastructure\Cache\TransientCache;

/**
 * WooCommerce Product Repository
 */
class WooProductRepository implements ProductRepositoryInterface {
	/**
	 * Cache instance
	 *
	 * @var TransientCache
	 */
	private $cache;

	/**
	 * Discount calculator
	 *
	 * @var ComputeMaxDiscount
	 */
	private $discount_calculator;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->cache               = new TransientCache();
		$this->discount_calculator = new ComputeMaxDiscount();
	}

	/**
	 * Get on-sale products
	 *
	 * @param int   $offset Offset.
	 * @param int   $limit Limit.
	 * @param array $filters Filters array.
	 * @return array
	 */
        public function get_onsale_products( $offset = 0, $limit = 12, $filters = array() ) {
                $offset = max( 0, (int) $offset );
                $limit  = max( 0, (int) $limit );

                $sale_product_ids = array_filter( array_map( 'absint', wc_get_product_ids_on_sale() ) );

                if ( empty( $sale_product_ids ) ) {
                        return array();
                }

                $args = array(
                        'post_type'      => 'product',
                        'post_status'    => 'publish',
                        'posts_per_page' => $limit,
                        'offset'         => $offset,
                        'post__in'       => $sale_product_ids,
                        'meta_query'     => array(),
                );

                $tax_query = array();

                // Apply category filter.
                if ( ! empty( $filters['categories'] ) ) {
                        $category_ids = array_filter( array_map( 'absint', explode( ',', $filters['categories'] ) ) );
                        if ( ! empty( $category_ids ) ) {
                                $tax_query[] = array(
                                        'taxonomy' => 'product_cat',
                                        'field'    => 'term_id',
                                        'terms'    => $category_ids,
                                        'operator' => 'IN',
                                );
                        }
                }

                if ( ! empty( $tax_query ) ) {
                        $args['tax_query'] = $tax_query;
                }

                $orderby               = $filters['orderby'] ?? 'discount-desc';
                $should_post_sort_only = ( 'discount-desc' === $orderby );

                // Apply sorting.
                $this->apply_sorting( $args, $filters );

                if ( $should_post_sort_only ) {
                        // We'll handle pagination after sorting by discount, so fetch all matching posts now.
                        $args['posts_per_page'] = -1;
                        $args['offset']         = 0;
                }

		// Apply price filter.
		if ( ! empty( $filters['min_price'] ) || ! empty( $filters['max_price'] ) ) {
			$price_filter = array( 'key' => '_price' );
			
			if ( ! empty( $filters['min_price'] ) && ! empty( $filters['max_price'] ) ) {
				$price_filter['value']   = array( $filters['min_price'], $filters['max_price'] );
				$price_filter['compare'] = 'BETWEEN';
				$price_filter['type']    = 'NUMERIC';
			} elseif ( ! empty( $filters['min_price'] ) ) {
				$price_filter['value']   = $filters['min_price'];
				$price_filter['compare'] = '>=';
				$price_filter['type']    = 'NUMERIC';
			} elseif ( ! empty( $filters['max_price'] ) ) {
				$price_filter['value']   = $filters['max_price'];
				$price_filter['compare'] = '<=';
				$price_filter['type']    = 'NUMERIC';
			}
			
                        $args['meta_query'][] = $price_filter;
                }

                // Apply stock filter.
                if ( ! empty( $filters['in_stock'] ) ) {
                        $args['meta_query'][] = array(
                                'key'     => '_stock_status',
                                'value'   => 'instock',
                                'compare' => '=',
                        );
                }

                if ( empty( $args['meta_query'] ) ) {
                        unset( $args['meta_query'] );
                }

                $args = apply_filters( 'hw_onsale_query_args', $args );

                $query    = new \WP_Query( $args );
		$products = array();

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				$product = wc_get_product( get_the_ID() );

				if ( $product ) {
					$entity = $this->map_to_entity( $product );
					
					// Apply discount filter post-query.
					if ( ! empty( $filters['min_discount'] ) ) {
						if ( $entity->get_discount_pct() >= $filters['min_discount'] ) {
							$products[] = $entity;
						}
					} else {
						$products[] = $entity;
					}
				}
			}
			wp_reset_postdata();
		}

                // Sort by discount if requested
                if ( $should_post_sort_only ) {
                        usort(
                                $products,
                                function ( $a, $b ) {
                                        return $b->get_discount_pct() - $a->get_discount_pct();
                                }
                        );

                        if ( $limit > 0 ) {
                                $products = array_slice( $products, $offset, $limit );
                        } elseif ( $offset > 0 ) {
                                $products = array_slice( $products, $offset );
                        }
                }

                return $products;
        }

	/**
	 * Count total on-sale products
	 *
	 * @param array $filters Filters array.
	 * @return int
	 */
        public function count_onsale_products( $filters = array() ) {
                // Don't cache if filters are applied.
                $should_cache = empty( $filters ) || ! array_filter( $filters );

                if ( $should_cache ) {
                        $cached = $this->cache->get( 'product_count' );
                        if ( false !== $cached ) {
                                return (int) $cached;
                        }
                }

                $sale_product_ids = array_filter( array_map( 'absint', wc_get_product_ids_on_sale() ) );

                if ( empty( $sale_product_ids ) ) {
                        if ( $should_cache ) {
                                $this->cache->set( 'product_count', 0 );
                        }

                        return 0;
                }

                $args = array(
                        'post_type'      => 'product',
                        'post_status'    => 'publish',
                        'posts_per_page' => -1,
                        'fields'         => 'ids',
                        'post__in'       => $sale_product_ids,
                        'meta_query'     => array(),
                );

                $tax_query = array();

                if ( ! empty( $filters['categories'] ) ) {
                        $category_ids = array_filter( array_map( 'absint', explode( ',', $filters['categories'] ) ) );
                        if ( ! empty( $category_ids ) ) {
                                $tax_query[] = array(
                                        'taxonomy' => 'product_cat',
                                        'field'    => 'term_id',
                                        'terms'    => $category_ids,
                                        'operator' => 'IN',
                                );
                        }
                }

                if ( ! empty( $tax_query ) ) {
                        $args['tax_query'] = $tax_query;
                }

		// Apply same filters as get_onsale_products.
		if ( ! empty( $filters['min_price'] ) || ! empty( $filters['max_price'] ) ) {
			$price_filter = array( 'key' => '_price' );
			
			if ( ! empty( $filters['min_price'] ) && ! empty( $filters['max_price'] ) ) {
				$price_filter['value']   = array( $filters['min_price'], $filters['max_price'] );
				$price_filter['compare'] = 'BETWEEN';
				$price_filter['type']    = 'NUMERIC';
			} elseif ( ! empty( $filters['min_price'] ) ) {
				$price_filter['value']   = $filters['min_price'];
				$price_filter['compare'] = '>=';
				$price_filter['type']    = 'NUMERIC';
			} elseif ( ! empty( $filters['max_price'] ) ) {
				$price_filter['value']   = $filters['max_price'];
				$price_filter['compare'] = '<=';
				$price_filter['type']    = 'NUMERIC';
			}
			
			$args['meta_query'][] = $price_filter;
		}

                if ( ! empty( $filters['in_stock'] ) ) {
                        $args['meta_query'][] = array(
                                'key'     => '_stock_status',
                                'value'   => 'instock',
                                'compare' => '=',
                        );
                }

                if ( empty( $args['meta_query'] ) ) {
                        unset( $args['meta_query'] );
                }

                $args = apply_filters( 'hw_onsale_query_args', $args );

		$query = new \WP_Query( $args );
		$count = $query->found_posts;

                if ( $should_cache ) {
                        $this->cache->set( 'product_count', $count );
                }

                return $count;
        }

        /**
         * Get product options for quick add modal
         *
         * @param int $product_id Product ID.
         * @return array|\WP_Error
         */
        public function get_product_options( $product_id ) {
                $product = wc_get_product( $product_id );

                if ( ! $product ) {
                        return new \WP_Error( 'hw_product_not_found', __( 'Product not found.', 'hw-onsale' ), array( 'status' => 404 ) );
                }

                $response = array(
                        'product_id'               => $product_id,
                        'name'                     => $product->get_name(),
                        'base_price_html'          => wp_kses_post( $this->get_enhanced_price_html( $product ) ),
                        'attributes'               => array(),
                        'variations'               => array(),
                        'has_in_stock_variations'  => false,
                );

                if ( ! $product->is_type( 'variable' ) ) {
                        $simple_add_to_cart_url          = $product->add_to_cart_url();
                        $response['simple_add_to_cart_url'] = esc_url_raw( $simple_add_to_cart_url );
                        $response['simple_checkout_url']    = esc_url_raw( $this->convert_cart_url_to_checkout( $simple_add_to_cart_url ) );

                        return $response;
                }

                $default_attributes   = $product->get_default_attributes();
                $variation_attributes = $product->get_variation_attributes();
                $available_variations = $product->get_available_variations();
                $variations           = array();
                $in_stock_values      = array();
                $has_in_stock         = false;

                foreach ( $available_variations as $variation_data ) {
                        $variation = wc_get_product( $variation_data['variation_id'] );
                        if ( ! $variation ) {
                                continue;
                        }

                        $attributes = array();

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

                        // Get variation image payload (src + alt).
                        $image_payload = $this->build_variation_image_payload( $variation_data, $variation );

                        // Get proper add to cart URL
                        $add_to_cart_url = '';
                        $checkout_url    = '';
                        if ( $variation->is_in_stock() ) {
                                $has_in_stock = true;
                                $add_to_cart_url = add_query_arg(
                                        array(
                                                'add-to-cart' => $product_id,
                                                'variation_id' => $variation_data['variation_id'],
                                                'quantity' => 1,
                                        ),
                                        wc_get_cart_url()
                                );

                                // Add variation attributes to URL
                                foreach ( $attributes as $key => $value ) {
                                        $add_to_cart_url = add_query_arg( $key, $value, $add_to_cart_url );
                                }

                                $checkout_url = $this->convert_cart_url_to_checkout( $add_to_cart_url );
                        }

                        $variations[] = array(
                                'id'              => (int) $variation_data['variation_id'],
                                'is_in_stock'     => (bool) $variation->is_in_stock(),
                                'price_html'      => wp_kses_post( $this->get_enhanced_price_html( $variation ) ),
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
                $response['attributes'] = $prepared_attributes;
                $response['variations'] = $variations;

                return $response;
        }

        /**
         * Map WooCommerce product to ProductCard entity
         *
         * @param \WC_Product $product WooCommerce product.
	 * @return ProductCard
	 */
        private function map_to_entity( $product ) {
                $images       = $this->get_product_images( $product );
                $discount_pct = $this->discount_calculator->execute( $product );
                $price_html   = $this->get_enhanced_price_html( $product );
                $materials    = $this->get_genuine_material_children( $product );
                $size         = $this->extract_size_from_description( $product );

                return new ProductCard(
                        $product->get_id(),
                        $product->get_name(),
                        $product->get_permalink(),
                        $images,
                        $price_html,
                        $discount_pct,
                        $product->is_type( 'variable' ),
                        $product->add_to_cart_url(),
                        $product->add_to_cart_text(),
                        $materials,
                        $size
                );
        }

        /**
         * Extract size information from product description.
         *
         * @param \WC_Product $product Product instance.
         * @return string
         */
        private function extract_size_from_description( $product ) {
                if ( ! $product ) {
                        return '';
                }

                $sources = array(
                        $product->get_description(),
                        $product->get_short_description(),
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
         * Parse size string from raw text content.
         *
         * @param string $text Text to parse.
         * @return string
         */
        private function parse_size_from_text( $text ) {
                if ( empty( $text ) ) {
                        return '';
                }

                $scoped_text = $this->isolate_product_description_segment( $text );

                $clean_text = wp_strip_all_tags( $scoped_text );

                if ( '' === $clean_text ) {
                        return '';
                }

                $normalized = preg_replace( '/\s+/u', ' ', $clean_text );

                if ( ! is_string( $normalized ) ) {
                        return '';
                }

                $normalized = trim( $normalized );

                if ( '' === $normalized ) {
                        return '';
                }

                $lowercase = function_exists( 'mb_strtolower' ) ? mb_strtolower( $normalized, 'UTF-8' ) : strtolower( $normalized );
                $size_pos  = strpos( $lowercase, 'size' );

                if ( false === $size_pos ) {
                        return '';
                }

                $snippet = function_exists( 'mb_substr' )
                        ? mb_substr( $normalized, $size_pos, 160, 'UTF-8' )
                        : substr( $normalized, $size_pos, 160 );

                if ( ! is_string( $snippet ) ) {
                        return '';
                }

                $snippet = trim( $snippet );

                if ( '' === $snippet ) {
                        return '';
                }

                if ( ! preg_match( '/^(size[^\.\n]{0,160}cm)/i', $snippet, $matches ) ) {
                        return '';
                }

                $candidate = trim( $matches[1] );

                if ( '' === $candidate ) {
                        return '';
                }

                $measurement = preg_replace( '/^size\s*(?:[:\-–]\s*)?/i', '', $candidate );
                $measurement = preg_replace( '/\s+/u', ' ', $measurement );
                $measurement = rtrim( $measurement, ' ,.;:)' );
                $measurement = trim( $measurement );

                if ( '' === $measurement || false === stripos( $measurement, 'cm' ) ) {
                        return '';
                }

                $measurement = preg_replace( '/\s*cm$/i', ' cm', $measurement );
                $measurement = trim( $measurement );

                if ( '' === $measurement ) {
                        return '';
                }

                $prefix = function_exists( 'mb_substr' )
                        ? mb_substr( $measurement, 0, 4, 'UTF-8' )
                        : substr( $measurement, 0, 4 );

                if ( is_string( $prefix ) && strtolower( $prefix ) === 'size' ) {
                        return $measurement;
                }

                return 'size ' . $measurement;
        }

        /**
         * Extract the product description section used for sizing details.
         *
         * @param string $html Raw HTML content.
         * @return string
         */
        private function isolate_product_description_segment( $html ) {
                if ( empty( $html ) || false === stripos( $html, 'product-desc-content' ) ) {
                        return $html;
                }

                if ( preg_match( '/<div[^>]*class=["\'][^"\']*product-desc-content[^"\']*["\'][^>]*>(.*?)<\/div>/is', $html, $matches ) ) {
                        return $matches[1];
                }

                return $html;
        }

	/**
	 * Get enhanced price HTML for variable products
	 *
	 * @param \WC_Product $product Product.
	 * @return string
	 */
        private function get_enhanced_price_html( $product ) {
                // For simple products, use default price HTML
                if ( ! $product->is_type( 'variable' ) ) {
                        if ( $product->is_on_sale() ) {
                                $regular_price = (float) $product->get_regular_price();
                                $sale_price    = (float) $product->get_sale_price();

                                if ( $regular_price > 0 && $sale_price > 0 && $sale_price < $regular_price ) {
                                        $price_html  = '<span class="price">';
                                        $price_html .= '<del>' . wc_price( $regular_price ) . '</del> ';
                                        $price_html .= '<ins>' . wc_price( $sale_price ) . '</ins>';
                                        $price_html .= '</span>';
                                        return $price_html;
                                }
                        }

                        return $product->get_price_html();
                }

                // For variable products, show highest regular price and lowest sale price
                $variations = $product->get_available_variations();

		if ( empty( $variations ) ) {
			return $product->get_price_html();
		}

		$max_regular_price = 0;
		$min_sale_price = PHP_FLOAT_MAX;
		$has_sale = false;

		foreach ( $variations as $variation_data ) {
			$variation = wc_get_product( $variation_data['variation_id'] );
			
			if ( ! $variation ) {
				continue;
			}

			$regular_price = (float) $variation->get_regular_price();
			$sale_price = (float) $variation->get_sale_price();

			// Track max regular price
			if ( $regular_price > $max_regular_price ) {
				$max_regular_price = $regular_price;
			}

			// Track min sale price
			if ( $sale_price > 0 ) {
				$has_sale = true;
				if ( $sale_price < $min_sale_price ) {
					$min_sale_price = $sale_price;
				}
			} elseif ( $regular_price > 0 && $regular_price < $min_sale_price ) {
				$min_sale_price = $regular_price;
			}
		}

		// Build price HTML
		if ( $has_sale && $max_regular_price > 0 && $min_sale_price < PHP_FLOAT_MAX ) {
			$price_html = '<del>' . wc_price( $max_regular_price ) . '</del> ';
			$price_html .= '<ins>' . wc_price( $min_sale_price ) . '</ins>';
			return $price_html;
		}

                // Fallback to default
                return $product->get_price_html();
        }

        /**
         * Get child categories under Genuine Materials parent
         *
         * @param \WC_Product $product Product instance.
         * @return array
         */
        private function get_genuine_material_children( $product ) {
                $terms = get_the_terms( $product->get_id(), 'product_cat' );

                if ( empty( $terms ) || is_wp_error( $terms ) ) {
                        return array();
                }

                $materials = array();

                $materials_parent_id = 111;

                foreach ( $terms as $term ) {
                        $parent_id = isset( $term->parent ) ? (int) $term->parent : 0;

                        if ( $parent_id !== $materials_parent_id ) {
                                continue;
                        }

                        $link = get_term_link( $term );
                        if ( is_wp_error( $link ) ) {
                                $link = '';
                        }

                        $materials[ $term->term_id ] = array(
                                'id'   => (int) $term->term_id,
                                'name' => $term->name,
                                'slug' => $term->slug,
                                'link' => $link ? esc_url_raw( $link ) : '',
                        );
                }

                return array_values( $materials );
        }

        /**
         * Convert a cart add-to-cart URL to a checkout URL while keeping the same query args.
         *
         * @param string $url Cart URL.
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

        /**
         * Get product images with responsive sizes
         *
         * @param \WC_Product $product Product.
         * @return array
	 */
	private function get_product_images( $product ) {
		$images     = array();
		$image_ids  = array();
		$gallery_ids = $product->get_gallery_image_ids();

		// Add main image.
		if ( $product->get_image_id() ) {
			$image_ids[] = $product->get_image_id();
		}

		// Add gallery images.
		if ( ! empty( $gallery_ids ) ) {
			$image_ids = array_merge( $image_ids, $gallery_ids );
		}

		foreach ( $image_ids as $image_id ) {
			$images[] = $this->get_responsive_image_data( $image_id );
		}

		return $images;
	}

	/**
	 * Get responsive image data
	 *
	 * @param int $image_id Image ID.
	 * @return array
	 */
	private function get_responsive_image_data( $image_id ) {
                $desktop_size = array( 480, 600 );
                $mobile_size  = array( 280, 350 );

		$desktop_src = wp_get_attachment_image_src( $image_id, $desktop_size );
		$mobile_src  = wp_get_attachment_image_src( $image_id, $mobile_size );
		$full_src    = wp_get_attachment_image_src( $image_id, 'full' );

		$alt = get_post_meta( $image_id, '_wp_attachment_image_alt', true );

		return array(
			'desktop' => $desktop_src ? $desktop_src[0] : '',
			'mobile'  => $mobile_src ? $mobile_src[0] : '',
			'full'    => $full_src ? $full_src[0] : '',
			'alt'     => $alt ? $alt : '',
                        'width'   => $desktop_src ? $desktop_src[1] : 480,
                        'height'  => $desktop_src ? $desktop_src[2] : 600,
                );
        }

	/**
	 * Apply sorting to query args
	 *
	 * @param array $args Query args.
	 * @param array $filters Filters array.
	 */
        private function apply_sorting( &$args, $filters ) {
                $orderby = $filters['orderby'] ?? 'discount-desc';

                switch ( $orderby ) {
			case 'price-asc':
				$args['orderby']  = 'meta_value_num';
				$args['meta_key'] = '_price';
				$args['order']    = 'ASC';
				break;

			case 'price-desc':
				$args['orderby']  = 'meta_value_num';
				$args['meta_key'] = '_price';
				$args['order']    = 'DESC';
				break;

			case 'discount-desc':
                        default:
                                // Default: Highest discount first
                                // Note: WooCommerce doesn't store discount as meta, so we'll
                                // fetch products and sort by calculated discount in post-processing
                                $args['orderby'] = 'date';
                                $args['order']   = 'DESC';
                                break;
                }
        }

        /**
         * Normalize attribute slug to include the attribute_ prefix expected by WooCommerce.
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
         * Build normalized variation image payload.
         *
         * @param array       $variation_data Raw variation data from WooCommerce.
         * @param \WC_Product $variation      Variation product instance.
         * @return array
         */
        private function build_variation_image_payload( $variation_data, $variation ) {
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
}
