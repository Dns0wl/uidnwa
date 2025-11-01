<?php
/**
 * Event Repository Interface
 *
 * @package HW_Onsale\Domain\Repositories
 */

namespace HW_Onsale\Domain\Repositories;

use HW_Onsale\Domain\Entities\Event;

/**
 * Event Repository Interface
 */
interface EventRepositoryInterface {
	/**
	 * Store event
	 *
	 * @param Event $event Event entity.
	 * @return bool
	 */
	public function store( Event $event );

	/**
	 * Get analytics summary
	 *
	 * @param string $from From date (Y-m-d).
	 * @param string $to To date (Y-m-d).
	 * @return array
	 */
	public function get_analytics_summary( $from, $to );

	/**
	 * Get time series data
	 *
	 * @param string $from From date (Y-m-d).
	 * @param string $to To date (Y-m-d).
	 * @return array
	 */
	public function get_timeseries( $from, $to );

	/**
	 * Get top products
	 *
	 * @param string $from From date (Y-m-d).
	 * @param string $to To date (Y-m-d).
	 * @param int    $limit Limit.
	 * @return array
	 */
	public function get_top_products( $from, $to, $limit = 10 );

	/**
	 * Get device breakdown
	 *
	 * @param string $from From date (Y-m-d).
	 * @param string $to To date (Y-m-d).
	 * @return array
	 */
	public function get_device_breakdown( $from, $to );

	/**
	 * Export events to CSV
	 *
	 * @param string $from From date (Y-m-d).
	 * @param string $to To date (Y-m-d).
	 * @return array
	 */
	public function export_csv( $from, $to );
}
