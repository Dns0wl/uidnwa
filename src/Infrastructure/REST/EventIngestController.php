<?php
/**
 * Event Ingest REST Controller
 *
 * @package HW_Onsale\Infrastructure\REST
 */

namespace HW_Onsale\Infrastructure\REST;

use HW_Onsale\Domain\Repositories\EventRepositoryInterface;
use HW_Onsale\Application\UseCases\TrackEvent;

/**
 * Event Ingest Controller
 */
class EventIngestController {
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
			'/event',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'ingest_event' ),
				'permission_callback' => array( $this, 'verify_nonce' ),
				'args'                => array(
					'session_id'   => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'event'        => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'product_id'   => array(
						'required'          => false,
						'sanitize_callback' => 'absint',
					),
					'discount_pct' => array(
						'required'          => false,
						'sanitize_callback' => 'absint',
					),
					'device'       => array(
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'ref'          => array(
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'extra'        => array(
						'required' => false,
					),
				),
			)
		);
	}

	/**
	 * Verify nonce
	 *
	 * @return bool
	 */
	public function verify_nonce() {
		return wp_verify_nonce( $_REQUEST['_wpnonce'] ?? '', 'wp_rest' );
	}

	/**
	 * Ingest event endpoint
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function ingest_event( $request ) {
		$data = array(
			'session_id'   => $request->get_param( 'session_id' ),
			'event'        => $request->get_param( 'event' ),
			'product_id'   => $request->get_param( 'product_id' ),
			'discount_pct' => $request->get_param( 'discount_pct' ),
			'device'       => $request->get_param( 'device' ),
			'ref'          => $request->get_param( 'ref' ),
			'extra'        => $request->get_param( 'extra' ),
		);

		$use_case = new TrackEvent( $this->event_repo );
		$result   = $use_case->execute( $data );

		return rest_ensure_response(
			array(
				'success' => $result,
			)
		);
	}
}
