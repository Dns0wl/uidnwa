<?php
/**
 * Admin Dashboard Page
 *
 * @package HW_Onsale\Presentation\Admin
 */

namespace HW_Onsale\Presentation\Admin;

/**
 * Dashboard Page Class
 */
class DashboardPage {
	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Add menu page
	 */
	public function add_menu_page() {
		add_submenu_page(
			'woocommerce',
			__( 'On Sale Dashboard', 'hw-onsale' ),
			__( 'On Sale Dashboard', 'hw-onsale' ),
			'manage_hw_onsale',
			'hw-onsale-dashboard',
			array( $this, 'render_dashboard' )
		);
	}

	/**
	 * Register settings
	 */
	public function register_settings() {
		$settings = new SettingsRegistry();
		$settings->register_all();
	}

	/**
	 * Render dashboard
	 */
	public function render_dashboard() {
		$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'overview';
		?>
		<div class="wrap hw-onsale-dashboard">
			<h1><?php esc_html_e( 'On Sale Dashboard', 'hw-onsale' ); ?></h1>

			<nav class="nav-tab-wrapper">
				<a href="?page=hw-onsale-dashboard&tab=overview" 
					class="nav-tab <?php echo 'overview' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Overview', 'hw-onsale' ); ?>
				</a>
				<a href="?page=hw-onsale-dashboard&tab=settings" 
					class="nav-tab <?php echo 'settings' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Appearance & Settings', 'hw-onsale' ); ?>
				</a>
			</nav>

			<div class="hw-onsale-dashboard__content">
				<?php
				if ( 'overview' === $active_tab ) {
					$this->render_overview_tab();
				} else {
					$this->render_settings_tab();
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render overview tab
	 */
	private function render_overview_tab() {
		?>
		<div class="hw-onsale-overview">
			<div class="hw-onsale-overview__filters">
				<label>
					<?php esc_html_e( 'From:', 'hw-onsale' ); ?>
					<input type="date" id="hw-onsale-date-from" value="<?php echo esc_attr( gmdate( 'Y-m-d', strtotime( '-30 days' ) ) ); ?>" />
				</label>
				<label>
					<?php esc_html_e( 'To:', 'hw-onsale' ); ?>
					<input type="date" id="hw-onsale-date-to" value="<?php echo esc_attr( gmdate( 'Y-m-d' ) ); ?>" />
				</label>
				<button type="button" class="button" id="hw-onsale-refresh">
					<?php esc_html_e( 'Refresh', 'hw-onsale' ); ?>
				</button>
				<button type="button" class="button button-primary" id="hw-onsale-export">
					<?php esc_html_e( 'Export CSV', 'hw-onsale' ); ?>
				</button>
			</div>

			<div class="hw-onsale-kpis" id="hw-onsale-kpis">
				<div class="hw-onsale-kpi">
					<div class="hw-onsale-kpi__label"><?php esc_html_e( 'Views', 'hw-onsale' ); ?></div>
					<div class="hw-onsale-kpi__value" data-kpi="views">-</div>
				</div>
				<div class="hw-onsale-kpi">
					<div class="hw-onsale-kpi__label"><?php esc_html_e( 'Clicks', 'hw-onsale' ); ?></div>
					<div class="hw-onsale-kpi__value" data-kpi="clicks">-</div>
				</div>
				<div class="hw-onsale-kpi">
					<div class="hw-onsale-kpi__label"><?php esc_html_e( 'CTR', 'hw-onsale' ); ?></div>
					<div class="hw-onsale-kpi__value" data-kpi="ctr">-</div>
				</div>
				<div class="hw-onsale-kpi">
					<div class="hw-onsale-kpi__label"><?php esc_html_e( 'Add to Cart', 'hw-onsale' ); ?></div>
					<div class="hw-onsale-kpi__value" data-kpi="add_to_cart">-</div>
				</div>
			</div>

			<div class="hw-onsale-charts">
				<div class="hw-onsale-chart">
					<h2><?php esc_html_e( 'Activity Over Time', 'hw-onsale' ); ?></h2>
					<canvas id="hw-onsale-chart-timeseries" aria-label="<?php esc_attr_e( 'Activity time series chart', 'hw-onsale' ); ?>"></canvas>
					<div class="screen-reader-text" id="chart-timeseries-summary"></div>
				</div>

				<div class="hw-onsale-chart">
					<h2><?php esc_html_e( 'Top 10 Products by Clicks', 'hw-onsale' ); ?></h2>
					<canvas id="hw-onsale-chart-top-products" aria-label="<?php esc_attr_e( 'Top products chart', 'hw-onsale' ); ?>"></canvas>
					<div class="screen-reader-text" id="chart-top-products-summary"></div>
				</div>

				<div class="hw-onsale-chart">
					<h2><?php esc_html_e( 'Device Breakdown', 'hw-onsale' ); ?></h2>
					<canvas id="hw-onsale-chart-devices" aria-label="<?php esc_attr_e( 'Device breakdown chart', 'hw-onsale' ); ?>"></canvas>
					<div class="screen-reader-text" id="chart-devices-summary"></div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render settings tab
	 */
	private function render_settings_tab() {
		?>
		<form method="post" action="options.php">
			<?php
			settings_fields( 'hw_onsale_settings' );
			do_settings_sections( 'hw-onsale-settings' );
			submit_button();
			?>
		</form>
		<?php
	}
}
