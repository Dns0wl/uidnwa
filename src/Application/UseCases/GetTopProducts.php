<?php
/**
 * Get Top Products Use Case
 *
 * @package HW_Onsale\Application\UseCases
 */

namespace HW_Onsale\Application\UseCases;

use HW_Onsale\Domain\Repositories\EventRepositoryInterface;

/**
 * Get Top Products Use Case
 */
class GetTopProducts {
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
	 * @param int    $limit Limit.
	 * @return array
	 */
	public function execute( $from, $to, $limit = 10 ) {
		return $this->event_repo->get_top_products( $from, $to, $limit );
	}
}
