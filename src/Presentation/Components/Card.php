<?php
/**
 * Product Card Component
 *
 * @package HW_Onsale\Presentation\Components
 */

namespace HW_Onsale\Presentation\Components;

/**
 * Card Component Class
 */
class Card {
	/**
	 * Render product card
	 *
	 * @param array $product Product data.
	 * @param int   $index Card index.
	 * @return string
	 */
	public static function render( $product, $index = 0 ) {
		$badge_threshold = (int) get_option( 'hw_onsale_badge_threshold', 0 );
		$show_badge      = $product['discount_pct'] > $badge_threshold;
		$fetchpriority   = get_option( 'hw_onsale_fetchpriority_first_row', '1' ) === '1' && $index < 4 ? 'high' : 'auto';

		ob_start();
                $categories = array();

                if ( isset( $product['categories'] ) && is_array( $product['categories'] ) ) {
                        $categories = $product['categories'];
                } elseif ( isset( $product['materials'] ) && is_array( $product['materials'] ) ) {
                        $categories = $product['materials'];
                }

                $categories_attr = ! empty( $categories ) ? wp_json_encode( $categories ) : wp_json_encode( array() );
                $size_attr       = isset( $product['size'] ) ? $product['size'] : '';

                $product_name       = isset( $product['name'] ) ? $product['name'] : '';
                $truncated_name     = wp_trim_words( $product_name, 4, '' );

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
				<?php echo Badge::render( $product['discount_pct'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php endif; ?>

                        <?php echo Slider::render( $product['images'], $product['name'], $fetchpriority, $product['permalink'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

                        <div class="hw-onsale-card__content">
                                <h3 class="hw-onsale-card__title">
                                        <a
                                                href="<?php echo esc_url( $product['permalink'] ); ?>"
                                                data-modal-trigger="title">
                                                <?php echo esc_html( $truncated_name ); ?>
                                        </a>
                                </h3>

                                <div
                                        class="hw-pricewrap"
                                        role="button"
                                        tabindex="0"
                                        data-modal-trigger="price"
                                        aria-label="<?php echo esc_attr( sprintf( __( 'View quick actions for %s', 'hw-onsale' ), $product_name ) ); ?>">
                                        <div class="hw-onsale-card__price">
                                                <?php echo wp_kses_post( $product['price_html'] ); ?>
                                        </div>
                                </div>
                        </div>
                </div>
		<?php
		return ob_get_clean();
	}
}
