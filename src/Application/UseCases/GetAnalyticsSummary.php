<?php
/**
 * Get Analytics Summary Use Case
 *
 * @package HW_Onsale\Application\UseCases
 */

namespace HW_Onsale\Application\UseCases;

use HW_Onsale\Domain\Repositories\EventRepositoryInterface;

/**
 * Get Analytics Summary Use Case
 */
class GetAnalyticsSummary {
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
	 * Execute use case
	 *
	 * @param string $from From date.
	 * @param string $to To date.
	 * @return array
	 */
	public function execute( $from, $to ) {
		$summary = $this->event_repo->get_analytics_summary( $from, $to );

		// Calculate CTR.
		$views  = isset( $summary['views'] ) ? (int) $summary['views'] : 0;
		$clicks = isset( $summary['clicks'] ) ? (int) $summary['clicks'] : 0;
		$ctr    = $views > 0 ? ( $clicks / $views ) * 100 : 0;

		$kpis = array(
			'views'        => $views,
			'clicks'       => $clicks,
			'ctr'          => round( $ctr, 2 ),
			'add_to_cart'  => isset( $summary['add_to_cart'] ) ? (int) $summary['add_to_cart'] : 0,
		);

		return apply_filters( 'hw_onsale_admin_kpis', $kpis );
	}
}
