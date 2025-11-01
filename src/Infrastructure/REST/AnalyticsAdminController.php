<?php
/**
 * Analytics Admin REST Controller
 *
 * @package HW_Onsale\Infrastructure\REST
 */

namespace HW_Onsale\Infrastructure\REST;

use HW_Onsale\Domain\Repositories\EventRepositoryInterface;
use HW_Onsale\Application\UseCases\GetAnalyticsSummary;
use HW_Onsale\Application\UseCases\GetTimeseries;
use HW_Onsale\Application\UseCases\GetTopProducts;
use HW_Onsale\Application\DTOs\AnalyticsDTO;

/**
 * Analytics Admin Controller
 */
class AnalyticsAdminController {
	/**
	 * Event repository
	 *
	 * @var EventRepositoryInterface
	 */
	private $event_repo;

	/**
	 * Constructor
	 *
	 * @param EventRepositoryInterface $event_repo Event repository.
	 */
	public function __construct( EventRepositoryInterface $event_repo ) {
		$this->event_repo = $event_repo;
	}

	/**
	 * Register routes
	 */
	public function register_routes() {
		register_rest_route(
			'hw-onsale/v1',
			'/analytics',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_analytics' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'from' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'to'   => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			'hw-onsale/v1',
			'/export',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'export_csv' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'from' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'to'   => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Check permission
	 *
	 * @return bool
	 */
	public function check_permission() {
		return current_user_can( 'manage_hw_onsale' );
	}

	/**
	 * Get analytics endpoint
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_analytics( $request ) {
		$from = $request->get_param( 'from' );
		$to   = $request->get_param( 'to' );

		$summary_use_case    = new GetAnalyticsSummary( $this->event_repo );
		$timeseries_use_case = new GetTimeseries( $this->event_repo );
		$top_products_use_case = new GetTopProducts( $this->event_repo );

		$kpis        = $summary_use_case->execute( $from, $to );
		$timeseries  = $timeseries_use_case->execute( $from, $to );
		$top_products = $top_products_use_case->execute( $from, $to, 10 );
		$device_breakdown = $this->event_repo->get_device_breakdown( $from, $to );

		$dto = new AnalyticsDTO( $kpis, $timeseries, $top_products, $device_breakdown );

		return rest_ensure_response( $dto->to_array() );
	}

	/**
	 * Export CSV endpoint
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function export_csv( $request ) {
		$from = $request->get_param( 'from' );
		$to   = $request->get_param( 'to' );

		$data = $this->event_repo->export_csv( $from, $to );

		// Generate CSV.
		ob_start();
		$output = fopen( 'php://output', 'w' );

		// Headers.
		fputcsv( $output, array( 'ID', 'Session ID', 'Event', 'Product ID', 'Discount %', 'Device', 'Referrer', 'Created At' ) );

		// Data rows.
		foreach ( $data as $row ) {
			fputcsv(
				$output,
				array(
					$row['id'],
					$row['session_id'],
					$row['event'],
					$row['product_id'],
					$row['discount_pct'],
					$row['device'],
					$row['ref'],
					$row['created_at'],
				)
			);
		}

		fclose( $output );
		$csv = ob_get_clean();

		return rest_ensure_response(
			array(
				'csv'      => $csv,
				'filename' => 'hw-onsale-export-' . $from . '-to-' . $to . '.csv',
			)
		);
	}
}
