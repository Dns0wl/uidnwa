<?php
/**
 * WPDB Event Repository
 *
 * @package HW_Onsale\Infrastructure\Repositories
 */

namespace HW_Onsale\Infrastructure\Repositories;

use HW_Onsale\Domain\Entities\Event;
use HW_Onsale\Domain\Repositories\EventRepositoryInterface;

/**
 * WPDB Event Repository
 */
class WPDBEventRepository implements EventRepositoryInterface {
	/**
	 * Table name
	 *
	 * @var string
	 */
	private $table_name;

	/**
	 * Constructor
	 */
	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'hw_onsale_events';
	}

	/**
	 * Store event
	 *
	 * @param Event $event Event entity.
	 * @return bool
	 */
	public function store( Event $event ) {
		global $wpdb;

		$result = $wpdb->insert(
			$this->table_name,
			array(
				'session_id'      => $event->get_session_id(),
				'event'           => $event->get_event(),
				'product_id'      => $event->get_product_id(),
				'discount_pct'    => $event->get_discount_pct(),
				'user_agent_hash' => $event->get_user_agent_hash(),
				'device'          => $event->get_device(),
				'ref'             => $event->get_ref(),
				'created_at'      => current_time( 'mysql' ),
				'extra'           => $event->get_extra(),
			),
			array( '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		return false !== $result;
	}

	/**
	 * Get analytics summary
	 *
	 * @param string $from From date.
	 * @param string $to To date.
	 * @return array
	 */
	public function get_analytics_summary( $from, $to ) {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT event, COUNT(*) as count
				FROM {$this->table_name}
				WHERE created_at >= %s AND created_at <= %s
				GROUP BY event",
				$from . ' 00:00:00',
				$to . ' 23:59:59'
			),
			ARRAY_A
		);

		$summary = array(
			'views'        => 0,
			'clicks'       => 0,
			'add_to_cart'  => 0,
		);

		foreach ( $results as $row ) {
			if ( 'view' === $row['event'] ) {
				$summary['views'] = (int) $row['count'];
			} elseif ( 'card_click' === $row['event'] ) {
				$summary['clicks'] = (int) $row['count'];
			} elseif ( 'add_to_cart' === $row['event'] ) {
				$summary['add_to_cart'] = (int) $row['count'];
			}
		}

		return $summary;
	}

	/**
	 * Get time series data
	 *
	 * @param string $from From date.
	 * @param string $to To date.
	 * @return array
	 */
	public function get_timeseries( $from, $to ) {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
					DATE(created_at) as date,
					event,
					COUNT(*) as count
				FROM {$this->table_name}
				WHERE created_at >= %s AND created_at <= %s
				AND event IN ('view', 'card_click', 'add_to_cart')
				GROUP BY DATE(created_at), event
				ORDER BY date ASC",
				$from . ' 00:00:00',
				$to . ' 23:59:59'
			),
			ARRAY_A
		);

		// Organize by date.
		$series = array();
		foreach ( $results as $row ) {
			$date = $row['date'];
			if ( ! isset( $series[ $date ] ) ) {
				$series[ $date ] = array(
					'date'         => $date,
					'views'        => 0,
					'clicks'       => 0,
					'add_to_cart'  => 0,
				);
			}

			if ( 'view' === $row['event'] ) {
				$series[ $date ]['views'] = (int) $row['count'];
			} elseif ( 'card_click' === $row['event'] ) {
				$series[ $date ]['clicks'] = (int) $row['count'];
			} elseif ( 'add_to_cart' === $row['event'] ) {
				$series[ $date ]['add_to_cart'] = (int) $row['count'];
			}
		}

		return array_values( $series );
	}

	/**
	 * Get top products
	 *
	 * @param string $from From date.
	 * @param string $to To date.
	 * @param int    $limit Limit.
	 * @return array
	 */
	public function get_top_products( $from, $to, $limit = 10 ) {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
					product_id,
					COUNT(*) as clicks
				FROM {$this->table_name}
				WHERE created_at >= %s AND created_at <= %s
				AND event = 'card_click'
				AND product_id IS NOT NULL
				GROUP BY product_id
				ORDER BY clicks DESC
				LIMIT %d",
				$from . ' 00:00:00',
				$to . ' 23:59:59',
				$limit
			),
			ARRAY_A
		);

		// Enrich with product names.
		$products = array();
		foreach ( $results as $row ) {
			$product = wc_get_product( $row['product_id'] );
			$products[] = array(
				'product_id' => (int) $row['product_id'],
				'name'       => $product ? $product->get_name() : __( 'Unknown', 'hw-onsale' ),
				'clicks'     => (int) $row['clicks'],
			);
		}

		return $products;
	}

	/**
	 * Get device breakdown
	 *
	 * @param string $from From date.
	 * @param string $to To date.
	 * @return array
	 */
	public function get_device_breakdown( $from, $to ) {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
					device,
					COUNT(*) as count
				FROM {$this->table_name}
				WHERE created_at >= %s AND created_at <= %s
				AND device IS NOT NULL
				GROUP BY device",
				$from . ' 00:00:00',
				$to . ' 23:59:59'
			),
			ARRAY_A
		);

		$breakdown = array();
		foreach ( $results as $row ) {
			$breakdown[] = array(
				'device' => $row['device'],
				'count'  => (int) $row['count'],
			);
		}

		return $breakdown;
	}

	/**
	 * Export events to CSV
	 *
	 * @param string $from From date.
	 * @param string $to To date.
	 * @return array
	 */
	public function export_csv( $from, $to ) {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
					id,
					session_id,
					event,
					product_id,
					discount_pct,
					device,
					ref,
					created_at
				FROM {$this->table_name}
				WHERE created_at >= %s AND created_at <= %s
				ORDER BY created_at DESC",
				$from . ' 00:00:00',
				$to . ' 23:59:59'
			),
			ARRAY_A
		);

		return $results;
	}
}
