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
							 loading="<?php echo $index === 0 ? 'eager' : 'lazy'; ?>"
							 decoding="async"
							 fetchpriority="<?php echo esc_attr( $index === 0 ? $fetchpriority : 'auto' ); ?>"
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
		return ob_get_clean();
	}
}
