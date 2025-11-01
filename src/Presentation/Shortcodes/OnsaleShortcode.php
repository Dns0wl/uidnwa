<?php
/**
 * Onsale Shortcode
 *
 * @package HW_Onsale\Presentation\Shortcodes
 */

namespace HW_Onsale\Presentation\Shortcodes;

use HW_Onsale\Presentation\Components\Card;
use HW_Onsale\Presentation\Components\Badge;
use HW_Onsale\Presentation\Components\Slider;

/**
 * Onsale Shortcode Class
 */
class OnsaleShortcode {
	/**
	 * Constructor
	 */
	public function __construct() {
		add_shortcode( 'hw_onsale', array( $this, 'render' ) );
		add_filter( 'template_include', array( $this, 'template_fallback' ), 99 );
	}

	/**
	 * Render shortcode
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render( $atts ) {
		ob_start();
		$this->render_grid();
		return ob_get_clean();
	}

	/**
	 * Render grid
	 */
	private function render_grid() {
		$template = HW_ONSALE_PLUGIN_DIR . 'templates/grid.php';
		if ( file_exists( $template ) ) {
			include $template;
		}
	}

	/**
	 * Template fallback for full-width layout
	 *
	 * @param string $template Template path.
	 * @return string
	 */
	public function template_fallback( $template ) {
		global $post;

		$onsale_page_id = get_option( 'hw_onsale_page_id' );

		if ( ! is_page( $onsale_page_id ) ) {
			return $template;
		}

		// Use plugin template for consistent full-width rendering.
		$plugin_template = HW_ONSALE_PLUGIN_DIR . 'templates/grid.php';
		if ( file_exists( $plugin_template ) ) {
			return $plugin_template;
		}

		return $template;
	}
}
