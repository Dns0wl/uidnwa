<?php
/**
 * Track Event Use Case
 *
 * @package HW_Onsale\Application\UseCases
 */

namespace HW_Onsale\Application\UseCases;

use HW_Onsale\Domain\Entities\Event;
use HW_Onsale\Domain\Repositories\EventRepositoryInterface;

/**
 * Track Event Use Case
 */
class TrackEvent {
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
	 * @param array $data Event data.
	 * @return bool
	 */
	public function execute( array $data ) {
		// Check if tracking is enabled.
		if ( get_option( 'hw_onsale_tracking_enabled', '1' ) !== '1' ) {
			return false;
		}

		// Exclude admins if configured.
		if ( get_option( 'hw_onsale_exclude_admins', '1' ) === '1' && current_user_can( 'manage_options' ) ) {
			return false;
		}

		// Generate user agent hash if enabled.
		$user_agent_hash = null;
		if ( get_option( 'hw_onsale_anonymize_ip', '1' ) === '1' ) {
			$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
			$user_agent_hash = md5( $user_agent );
		}

		$event = new Event(
			sanitize_text_field( $data['session_id'] ?? '' ),
			sanitize_text_field( $data['event'] ?? '' ),
			isset( $data['product_id'] ) ? absint( $data['product_id'] ) : null,
			isset( $data['discount_pct'] ) ? absint( $data['discount_pct'] ) : null,
			$user_agent_hash,
			sanitize_text_field( $data['device'] ?? '' ),
			sanitize_text_field( $data['ref'] ?? '' ),
			isset( $data['extra'] ) ? wp_json_encode( $data['extra'] ) : null
		);

		$result = $this->event_repo->store( $event );

		if ( $result ) {
			do_action( 'hw_onsale_event_tracked', $event );
		}

		return $result;
	}
}
