<?php
/**
 * Image Slider Component
 *
 * @package HW_Onsale\Presentation\Components
 */

namespace HW_Onsale\Presentation\Components;

/**
 * Slider Component Class
 */
class Slider {
	/**
	 * Render image slider
	 *
	 * @param array  $images Images array.
	 * @param string $alt Alt text.
         * @param string $fetchpriority Fetch priority.
         * @param string $permalink Product permalink.
         * @return string
         */
        public static function render( $images, $alt, $fetchpriority = 'auto', $permalink = '' ) {
                if ( empty( $images ) ) {
                        return '';
                }

		ob_start();
                ?>
                <div class="hw-onsale-slider" role="region" aria-label="<?php esc_attr_e( 'Product images', 'hw-onsale' ); ?>" data-modal-trigger="slider">
                        <div class="hw-onsale-slider__track" role="list">
                        <?php foreach ( $images as $index => $image ) :
                                $sources     = isset( $image['sources'] ) && is_array( $image['sources'] ) ? $image['sources'] : array();
                                $sizes       = isset( $image['sizes'] ) ? $image['sizes'] : '(min-width:1200px) 25vw, (min-width:768px) 33vw, 50vw';
                                $placeholder = isset( $image['placeholder'] ) ? $image['placeholder'] : '';
                                $img_alt     = ! empty( $image['alt'] ) ? $image['alt'] : $alt;
                                $is_first    = ( 0 === $index );
                                $loading     = $is_first ? 'eager' : 'lazy';
                                $priority    = $is_first ? $fetchpriority : 'auto';
                                if ( $is_first && function_exists( 'hw_perf_archive_register_lcp_image' ) ) {
                                        hw_perf_archive_register_lcp_image( $image );
                                }
                                ?>
                                <div class="hw-onsale-slider__slide" role="listitem">
                                        <?php if ( $permalink ) : ?>
                                                <a href="<?php echo esc_url( $permalink ); ?>" class="hw-onsale-slider__link">
                                        <?php endif; ?>
                                                <picture>
                                                        <?php if ( ! empty( $sources['avif'] ) ) : ?>
                                                                <source type="image/avif" srcset="<?php echo esc_attr( $sources['avif'] ); ?>" sizes="<?php echo esc_attr( $sizes ); ?>" />
                                                        <?php endif; ?>
                                                        <?php if ( ! empty( $sources['webp'] ) ) : ?>
                                                                <source type="image/webp" srcset="<?php echo esc_attr( $sources['webp'] ); ?>" sizes="<?php echo esc_attr( $sizes ); ?>" />
                                                        <?php endif; ?>
                                                        <img
                                                                src="<?php echo esc_url( $image['src'] ); ?>"
                                                                <?php if ( ! empty( $image['srcset'] ) ) : ?>srcset="<?php echo esc_attr( $image['srcset'] ); ?>"<?php endif; ?>
                                                                sizes="<?php echo esc_attr( $sizes ); ?>"
                                                                alt="<?php echo esc_attr( $img_alt ); ?>"
                                                                width="<?php echo esc_attr( $image['width'] ?? 480 ); ?>"
                                                                height="<?php echo esc_attr( $image['height'] ?? 600 ); ?>"
                                                                loading="<?php echo esc_attr( $loading ); ?>"
                                                                fetchpriority="<?php echo esc_attr( $priority ); ?>"
                                                                decoding="async"
                                                                <?php if ( $placeholder ) : ?>data-placeholder="<?php echo esc_url( $placeholder ); ?>"<?php endif; ?>
                                                                data-hw-img
                                                        />
                                                </picture>
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
		return ob_get_clean();
	}
}
