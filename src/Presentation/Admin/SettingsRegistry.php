<?php
/**
 * Settings Registry
 *
 * @package HW_Onsale\Presentation\Admin
 */

namespace HW_Onsale\Presentation\Admin;

/**
 * Settings Registry Class
 */
class SettingsRegistry {
	/**
	 * Register all settings
	 */
	public function register_all() {
		$this->register_banner_settings();
		$this->register_grid_settings();
		$this->register_badge_settings();
		$this->register_performance_settings();
		$this->register_tracking_settings();
	}

	/**
	 * Register banner settings
	 */
	private function register_banner_settings() {
		add_settings_section(
			'hw_onsale_banner_section',
			__( 'Banner / Hero', 'hw-onsale' ),
			null,
			'hw-onsale-settings'
		);

		$this->register_field( 'hw_onsale_banner_show', __( 'Show Banner', 'hw-onsale' ), 'checkbox', 'hw_onsale_banner_section' );
		$this->register_field( 'hw_onsale_banner_image', __( 'Banner Image', 'hw-onsale' ), 'media', 'hw_onsale_banner_section' );
		$this->register_field( 'hw_onsale_banner_title', __( 'Title', 'hw-onsale' ), 'text', 'hw_onsale_banner_section' );
		$this->register_field( 'hw_onsale_banner_subtitle', __( 'Subtitle', 'hw-onsale' ), 'text', 'hw_onsale_banner_section' );
		$this->register_field( 'hw_onsale_banner_cta_text', __( 'CTA Text', 'hw-onsale' ), 'text', 'hw_onsale_banner_section' );
		$this->register_field( 'hw_onsale_banner_cta_link', __( 'CTA Link', 'hw-onsale' ), 'text', 'hw_onsale_banner_section' );
		$this->register_field( 'hw_onsale_banner_overlay_opacity', __( 'Overlay Opacity', 'hw-onsale' ), 'number', 'hw_onsale_banner_section', array( 'min' => 0, 'max' => 0.6, 'step' => 0.1 ) );
		$this->register_field( 'hw_onsale_banner_height_desktop', __( 'Height Desktop (px)', 'hw-onsale' ), 'number', 'hw_onsale_banner_section' );
		$this->register_field( 'hw_onsale_banner_height_mobile', __( 'Height Mobile (px)', 'hw-onsale' ), 'number', 'hw_onsale_banner_section' );
		$this->register_field( 'hw_onsale_banner_alignment', __( 'Alignment', 'hw-onsale' ), 'select', 'hw_onsale_banner_section', array( 'options' => array( 'left' => 'Left', 'center' => 'Center', 'right' => 'Right' ) ) );
	}

	/**
	 * Register grid settings
	 */
	private function register_grid_settings() {
		add_settings_section(
			'hw_onsale_grid_section',
			__( 'Grid & Layout', 'hw-onsale' ),
			null,
			'hw-onsale-settings'
		);

		$this->register_field( 'hw_onsale_grid_desktop', __( 'Columns Desktop', 'hw-onsale' ), 'number', 'hw_onsale_grid_section', array( 'min' => 2, 'max' => 6 ) );
		$this->register_field( 'hw_onsale_grid_tablet', __( 'Columns Tablet', 'hw-onsale' ), 'number', 'hw_onsale_grid_section', array( 'min' => 2, 'max' => 4 ) );
		$this->register_field( 'hw_onsale_grid_mobile', __( 'Columns Mobile', 'hw-onsale' ), 'number', 'hw_onsale_grid_section', array( 'min' => 1, 'max' => 3 ) );
		$this->register_field( 'hw_onsale_card_radius', __( 'Card Radius (px)', 'hw-onsale' ), 'number', 'hw_onsale_grid_section' );
		$this->register_field( 'hw_onsale_card_shadow', __( 'Card Shadow', 'hw-onsale' ), 'select', 'hw_onsale_grid_section', array( 'options' => array( 'none' => 'None', 'small' => 'Small', 'medium' => 'Medium', 'large' => 'Large' ) ) );
		$this->register_field( 'hw_onsale_card_gap', __( 'Card Gap (px)', 'hw-onsale' ), 'number', 'hw_onsale_grid_section' );
		$this->register_field( 'hw_onsale_slider_dots', __( 'Show Slider Dots', 'hw-onsale' ), 'checkbox', 'hw_onsale_grid_section' );
		$this->register_field( 'hw_onsale_hover_add_to_cart', __( 'Hover Add to Cart (Desktop)', 'hw-onsale' ), 'checkbox', 'hw_onsale_grid_section' );
		$this->register_field( 'hw_onsale_batch_size', __( 'Load More Batch Size', 'hw-onsale' ), 'number', 'hw_onsale_grid_section', array( 'min' => 4, 'max' => 48 ) );
		$this->register_field( 'hw_onsale_load_more_label', __( 'Load More Button Label', 'hw-onsale' ), 'text', 'hw_onsale_grid_section' );
	}

	/**
	 * Register badge settings
	 */
	private function register_badge_settings() {
		add_settings_section(
			'hw_onsale_badge_section',
			__( 'Discount Badge', 'hw-onsale' ),
			null,
			'hw-onsale-settings'
		);

		$this->register_field( 'hw_onsale_badge_position', __( 'Badge Position', 'hw-onsale' ), 'select', 'hw_onsale_badge_section', array( 'options' => array( 'top-left' => 'Top Left', 'top-center' => 'Top Center', 'top-right' => 'Top Right' ) ) );
		$this->register_field( 'hw_onsale_badge_style', __( 'Badge Style', 'hw-onsale' ), 'select', 'hw_onsale_badge_section', array( 'options' => array( 'solid' => 'Solid', 'outline' => 'Outline' ) ) );
		$this->register_field( 'hw_onsale_badge_threshold', __( 'Hide Below % Threshold', 'hw-onsale' ), 'number', 'hw_onsale_badge_section', array( 'min' => 0, 'max' => 100 ) );
	}

	/**
	 * Register performance settings
	 */
	private function register_performance_settings() {
		add_settings_section(
			'hw_onsale_performance_section',
			__( 'Performance', 'hw-onsale' ),
			null,
			'hw-onsale-settings'
		);

		$this->register_field( 'hw_onsale_cache_enabled', __( 'Enable Transient Cache', 'hw-onsale' ), 'checkbox', 'hw_onsale_performance_section' );
		$this->register_field( 'hw_onsale_cache_ttl', __( 'Cache TTL (seconds)', 'hw-onsale' ), 'number', 'hw_onsale_performance_section' );
		$this->register_field( 'hw_onsale_prefetch_next', __( 'Prefetch Next Batch', 'hw-onsale' ), 'checkbox', 'hw_onsale_performance_section' );
		$this->register_field( 'hw_onsale_fetchpriority_first_row', __( 'High Priority First Row Images', 'hw-onsale' ), 'checkbox', 'hw_onsale_performance_section' );
	}

	/**
	 * Register tracking settings
	 */
	private function register_tracking_settings() {
		add_settings_section(
			'hw_onsale_tracking_section',
			__( 'Tracking & Attribution', 'hw-onsale' ),
			null,
			'hw-onsale-settings'
		);

		$this->register_field( 'hw_onsale_tracking_enabled', __( 'Enable Tracking', 'hw-onsale' ), 'checkbox', 'hw_onsale_tracking_section' );
		$this->register_field( 'hw_onsale_anonymize_ip', __( 'Anonymize IP', 'hw-onsale' ), 'checkbox', 'hw_onsale_tracking_section' );
		$this->register_field( 'hw_onsale_exclude_admins', __( 'Exclude Admins', 'hw-onsale' ), 'checkbox', 'hw_onsale_tracking_section' );
		$this->register_field( 'hw_onsale_attribution_window', __( 'Attribution Window (hours)', 'hw-onsale' ), 'number', 'hw_onsale_tracking_section' );
	}

	/**
	 * Register individual field
	 *
	 * @param string $id Field ID.
	 * @param string $title Field title.
	 * @param string $type Field type.
	 * @param string $section Section ID.
	 * @param array  $args Additional arguments.
	 */
	private function register_field( $id, $title, $type, $section, $args = array() ) {
		register_setting( 'hw_onsale_settings', $id );

		add_settings_field(
			$id,
			$title,
			array( $this, "render_{$type}_field" ),
			'hw-onsale-settings',
			$section,
			array_merge( array( 'id' => $id ), $args )
		);
	}

	/**
	 * Render text field
	 *
	 * @param array $args Field arguments.
	 */
	public function render_text_field( $args ) {
		$value = get_option( $args['id'], '' );
		?>
		<input type="text" 
			id="<?php echo esc_attr( $args['id'] ); ?>" 
			name="<?php echo esc_attr( $args['id'] ); ?>" 
			value="<?php echo esc_attr( $value ); ?>"
			class="regular-text" />
		<?php
	}

	/**
	 * Render number field
	 *
	 * @param array $args Field arguments.
	 */
	public function render_number_field( $args ) {
		$value = get_option( $args['id'], '' );
		$min   = isset( $args['min'] ) ? $args['min'] : '';
		$max   = isset( $args['max'] ) ? $args['max'] : '';
		$step  = isset( $args['step'] ) ? $args['step'] : '1';
		?>
		<input type="number" 
			id="<?php echo esc_attr( $args['id'] ); ?>" 
			name="<?php echo esc_attr( $args['id'] ); ?>" 
			value="<?php echo esc_attr( $value ); ?>"
			min="<?php echo esc_attr( $min ); ?>"
			max="<?php echo esc_attr( $max ); ?>"
			step="<?php echo esc_attr( $step ); ?>" />
		<?php
	}

	/**
	 * Render checkbox field
	 *
	 * @param array $args Field arguments.
	 */
	public function render_checkbox_field( $args ) {
		$value = get_option( $args['id'], '0' );
		?>
		<label>
			<input type="checkbox" 
				id="<?php echo esc_attr( $args['id'] ); ?>" 
				name="<?php echo esc_attr( $args['id'] ); ?>" 
				value="1"
				<?php checked( '1', $value ); ?> />
			<?php esc_html_e( 'Enable', 'hw-onsale' ); ?>
		</label>
		<?php
	}

	/**
	 * Render select field
	 *
	 * @param array $args Field arguments.
	 */
	public function render_select_field( $args ) {
		$value   = get_option( $args['id'], '' );
		$options = isset( $args['options'] ) ? $args['options'] : array();
		?>
		<select id="<?php echo esc_attr( $args['id'] ); ?>" 
			name="<?php echo esc_attr( $args['id'] ); ?>">
			<?php foreach ( $options as $key => $label ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $value, $key ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Render media field
	 *
	 * @param array $args Field arguments.
	 */
	public function render_media_field( $args ) {
		$value = get_option( $args['id'], '' );
		?>
		<div class="hw-onsale-media-field">
			<input type="hidden" 
				id="<?php echo esc_attr( $args['id'] ); ?>" 
				name="<?php echo esc_attr( $args['id'] ); ?>" 
				value="<?php echo esc_attr( $value ); ?>" />
			<button type="button" class="button hw-onsale-upload-button" data-target="<?php echo esc_attr( $args['id'] ); ?>">
				<?php esc_html_e( 'Select Image', 'hw-onsale' ); ?>
			</button>
			<?php if ( $value ) : ?>
				<div class="hw-onsale-media-preview">
					<img src="<?php echo esc_url( wp_get_attachment_url( $value ) ); ?>" style="max-width: 200px;" />
				</div>
			<?php endif; ?>
		</div>
		<script>
		document.addEventListener('DOMContentLoaded', function() {
			document.querySelectorAll('.hw-onsale-upload-button').forEach(function(button) {
				button.addEventListener('click', function(e) {
					e.preventDefault();
					const targetId = this.dataset.target;
					const frame = wp.media({
						title: '<?php esc_html_e( 'Select Image', 'hw-onsale' ); ?>',
						button: { text: '<?php esc_html_e( 'Use Image', 'hw-onsale' ); ?>' },
						multiple: false
					});
					frame.on('select', function() {
						const attachment = frame.state().get('selection').first().toJSON();
						document.getElementById(targetId).value = attachment.id;
					});
					frame.open();
				});
			});
		});
		</script>
		<?php
	}
}
