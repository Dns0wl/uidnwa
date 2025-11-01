<?php
/**
 * Discount Badge Component
 *
 * @package HW_Onsale\Presentation\Components
 */

namespace HW_Onsale\Presentation\Components;

/**
 * Badge Component Class
 */
class Badge {
	/**
	 * Render discount badge
	 *
	 * @param int $discount_pct Discount percentage.
	 * @return string
	 */
	public static function render( $discount_pct ) {
		$position = get_option( 'hw_onsale_badge_position', 'top-left' );
		$style    = get_option( 'hw_onsale_badge_style', 'solid' );

		ob_start();
		?>
		<div class="hw-onsale-badge hw-onsale-badge--<?php echo esc_attr( $position ); ?> hw-onsale-badge--<?php echo esc_attr( $style ); ?>"
			role="text"
			aria-label="<?php echo esc_attr( sprintf( __( '%d%% off', 'hw-onsale' ), $discount_pct ) ); ?>">
			-<?php echo esc_html( $discount_pct ); ?>%
		</div>
		<?php
		return ob_get_clean();
	}
}
