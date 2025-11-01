<?php
/**
 * Analytics DTO
 *
 * @package HW_Onsale\Application\DTOs
 */

namespace HW_Onsale\Application\DTOs;

/**
 * Analytics DTO
 */
class AnalyticsDTO {
	/**
	 * KPIs
	 *
	 * @var array
	 */
	public $kpis;

	/**
	 * Time series data
	 *
	 * @var array
	 */
	public $timeseries;

	/**
	 * Top products
	 *
	 * @var array
	 */
	public $top_products;

	/**
	 * Device breakdown
	 *
	 * @var array
	 */
	public $device_breakdown;

	/**
	 * Constructor
	 *
	 * @param array $kpis KPIs.
	 * @param array $timeseries Time series.
	 * @param array $top_products Top products.
	 * @param array $device_breakdown Device breakdown.
	 */
	public function __construct( array $kpis, array $timeseries, array $top_products, array $device_breakdown ) {
		$this->kpis              = $kpis;
		$this->timeseries        = $timeseries;
		$this->top_products      = $top_products;
		$this->device_breakdown  = $device_breakdown;
	}

	/**
	 * Convert to array
	 *
	 * @return array
	 */
	public function to_array() {
		return array(
			'kpis'             => $this->kpis,
			'timeseries'       => $this->timeseries,
			'top_products'     => $this->top_products,
			'device_breakdown' => $this->device_breakdown,
		);
	}
}
