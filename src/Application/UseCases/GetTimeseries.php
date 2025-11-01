<?php
/**
 * Get Timeseries Use Case
 *
 * @package HW_Onsale\Application\UseCases
 */

namespace HW_Onsale\Application\UseCases;

use HW_Onsale\Domain\Repositories\EventRepositoryInterface;

/**
 * Get Timeseries Use Case
 */
class GetTimeseries {
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
		return $this->event_repo->get_timeseries( $from, $to );
	}
}
