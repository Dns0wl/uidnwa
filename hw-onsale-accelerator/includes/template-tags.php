<?php
namespace HW_Onsale_Accelerator;

defined( 'ABSPATH' ) || exit;

/**
 * Render discount badge HTML.
 *
 * @param int $discount_pct Discount percentage.
 * @return string
 */
function render_badge( $discount_pct ) {
        $position = get_option( 'hw_onsale_badge_position', 'top-left' );
        $style    = get_option( 'hw_onsale_badge_style', 'solid' );

        ob_start();
        ?>
        <div class="hw-onsale-badge hw-onsale-badge--<?php echo esc_attr( $position ); ?> hw-onsale-badge--<?php echo esc_attr( $style ); ?>"
                role="text"
                aria-label="<?php echo esc_attr( sprintf( __( '%d%% off', 'hw-onsale' ), (int) $discount_pct ) ); ?>">
                -<?php echo esc_html( (int) $discount_pct ); ?>%
        </div>
        <?php
        return trim( ob_get_clean() );
}

/**
 * Render slider HTML block.
 *
 * @param array  $images Image sources.
 * @param string $alt Alt text fallback.
 * @param string $permalink Product link.
 * @return string
 */
function render_slider( array $images, $alt, $permalink ) {
        if ( empty( $images ) ) {
                return '';
        }

        ob_start();
        ?>
        <div class="hw-onsale-slider" role="region" aria-label="<?php esc_attr_e( 'Product images', 'hw-onsale' ); ?>" data-modal-trigger="slider">
                <div class="hw-onsale-slider__track" role="list">
                        <?php foreach ( $images as $index => $image ) : ?>
                                <div class="hw-onsale-slider__slide" role="listitem">
                                        <?php if ( $permalink ) : ?>
                                                <a href="<?php echo esc_url( $permalink ); ?>" class="hw-onsale-slider__link">
                                        <?php endif; ?>
                                                        <img
                                                                src="<?php echo esc_url( $image['desktop'] ); ?>"
                                                                srcset="<?php echo esc_attr( $image['mobile'] . ' 280w, ' . $image['desktop'] . ' 480w' ); ?>"
                                                                sizes="(max-width: 600px) 280px, 480px"
                                                                alt="<?php echo esc_attr( $image['alt'] ? $image['alt'] : $alt ); ?>"
                                                                width="<?php echo esc_attr( $image['width'] ); ?>"
                                                                height="<?php echo esc_attr( $image['height'] ); ?>"
                                                                loading="<?php echo 0 === $index ? 'eager' : 'lazy'; ?>"
                                                                decoding="async"
                                                                fetchpriority="<?php echo esc_attr( 0 === $index ? 'high' : 'auto' ); ?>"
                                                        />
                                        <?php if ( $permalink ) : ?>
                                                </a>
                                        <?php endif; ?>
                                </div>
                        <?php endforeach; ?>
                </div>
                <?php if ( count( $images ) > 1 ) : ?>
                        <?php
                        $total_images = count( $images );
                        $max_dots     = 3;
                        $dot_count    = min( $total_images, $max_dots );
                        ?>
                        <div class="hw-onsale-slider__dots" role="tablist" aria-label="<?php esc_attr_e( 'Image navigation', 'hw-onsale' ); ?>">
                                <?php for ( $i = 0; $i < $dot_count; $i++ ) :
                                        $target_index = $total_images <= $dot_count ? $i : ( 0 === $i ? 0 : ( $i === $dot_count - 1 ? $total_images - 1 : (int) round( ( $total_images - 1 ) / 2 ) ) );
                                        $label        = sprintf( __( 'Image %d', 'hw-onsale' ), $target_index + 1 );
                                        ?>
                                        <button
                                                type="button"
                                                class="hw-onsale-slider__dot <?php echo 0 === $i ? 'is-active' : ''; ?>"
                                                role="tab"
                                                aria-label="<?php echo esc_attr( $label ); ?>"
                                                aria-selected="<?php echo 0 === $i ? 'true' : 'false'; ?>"
                                                data-dot-index="<?php echo esc_attr( $i ); ?>"
                                                data-target-slide="<?php echo esc_attr( $target_index ); ?>">
                                                <span class="hw-sr-only"><?php echo esc_html( sprintf( __( 'Go to image %d', 'hw-onsale' ), $target_index + 1 ) ); ?></span>
                                        </button>
                                <?php endfor; ?>
                        </div>
                <?php endif; ?>
        </div>
        <?php
        return trim( ob_get_clean() );
}

/**
 * Render a product card.
 *
 * @param array $product Product payload.
 * @param int   $index   Position in listing.
 * @return string
 */
function render_card( array $product, $index = 0 ) {
        $badge_threshold = (int) get_option( 'hw_onsale_badge_threshold', 0 );
        $show_badge      = (int) $product['discount_pct'] > $badge_threshold;
        $fetch_priority  = get_option( 'hw_onsale_fetchpriority_first_row', '1' ) === '1' && $index < 4 ? 'high' : 'auto';

        $categories = array();
        if ( isset( $product['categories'] ) && is_array( $product['categories'] ) ) {
                $categories = $product['categories'];
        } elseif ( isset( $product['materials'] ) && is_array( $product['materials'] ) ) {
                $categories = $product['materials'];
        }

        $categories_attr = ! empty( $categories ) ? wp_json_encode( $categories ) : wp_json_encode( array() );
        $size_attr       = isset( $product['size'] ) ? $product['size'] : '';
        $product_name    = isset( $product['name'] ) ? $product['name'] : '';
        $truncated_name  = wp_trim_words( $product_name, 4, '' );

        ob_start();
        ?>
        <div class="hw-onsale-card"
                data-product-id="<?php echo esc_attr( $product['id'] ); ?>"
                data-discount="<?php echo esc_attr( $product['discount_pct'] ); ?>"
                data-variable="<?php echo $product['is_variable'] ? '1' : '0'; ?>"
                data-categories="<?php echo esc_attr( $categories_attr ); ?>"
                data-size="<?php echo esc_attr( $size_attr ); ?>"
                data-product-name="<?php echo esc_attr( $product_name ); ?>"
                data-product-link="<?php echo esc_url( $product['permalink'] ); ?>">
                <?php if ( $show_badge ) : ?>
                        <?php echo render_badge( $product['discount_pct'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <?php endif; ?>
                <?php echo render_slider( $product['images'], $product['name'], $product['permalink'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <div class="hw-onsale-card__content">
                        <h3 class="hw-onsale-card__title">
                                <a href="<?php echo esc_url( $product['permalink'] ); ?>" data-modal-trigger="title">
                                        <?php echo esc_html( $truncated_name ); ?>
                                </a>
                        </h3>
                        <div class="hw-pricewrap" role="button" tabindex="0" data-modal-trigger="price" aria-label="<?php echo esc_attr( sprintf( __( 'View quick actions for %s', 'hw-onsale' ), $product_name ) ); ?>">
                                <div class="hw-onsale-card__price">
                                        <?php echo wp_kses_post( $product['price_html'] ); ?>
                                </div>
                        </div>
                </div>
        </div>
        <?php
        return trim( ob_get_clean() );
}
