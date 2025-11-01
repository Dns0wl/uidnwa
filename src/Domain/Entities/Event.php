<?php
/**
 * Event Entity
 *
 * @package HW_Onsale\Domain\Entities
 */

namespace HW_Onsale\Domain\Entities;

/**
 * Event Entity
 */
class Event {
	/**
	 * Session ID
	 *
	 * @var string
	 */
	private $session_id;

	/**
	 * Event type
	 *
	 * @var string
	 */
	private $event;

	/**
	 * Product ID (nullable)
	 *
	 * @var int|null
	 */
	private $product_id;

	/**
	 * Discount percentage (nullable)
	 *
	 * @var int|null
	 */
	private $discount_pct;

	/**
	 * User agent hash (nullable)
	 *
	 * @var string|null
	 */
	private $user_agent_hash;

	/**
	 * Device type
	 *
	 * @var string|null
	 */
	private $device;

	/**
	 * Referrer
	 *
	 * @var string|null
	 */
	private $ref;

	/**
	 * Extra data (JSON)
	 *
	 * @var string|null
	 */
	private $extra;

	/**
	 * Constructor
	 *
	 * @param string      $session_id Session ID.
	 * @param string      $event Event type.
	 * @param int|null    $product_id Product ID.
	 * @param int|null    $discount_pct Discount percentage.
	 * @param string|null $user_agent_hash User agent hash.
	 * @param string|null $device Device type.
	 * @param string|null $ref Referrer.
	 * @param string|null $extra Extra data.
	 */
	public function __construct(
		$session_id,
		$event,
		$product_id = null,
		$discount_pct = null,
		$user_agent_hash = null,
		$device = null,
		$ref = null,
		$extra = null
	) {
		$this->session_id      = $session_id;
		$this->event           = $event;
		$this->product_id      = $product_id;
		$this->discount_pct    = $discount_pct;
		$this->user_agent_hash = $user_agent_hash;
		$this->device          = $device;
		$this->ref             = $ref;
		$this->extra           = $extra;
	}

	/**
	 * Get session ID
	 *
	 * @return string
	 */
	public function get_session_id() {
		return $this->session_id;
	}

	/**
	 * Get event type
	 *
	 * @return string
	 */
	public function get_event() {
		return $this->event;
	}

	/**
	 * Get product ID
	 *
	 * @return int|null
	 */
	public function get_product_id() {
		return $this->product_id;
	}

	/**
	 * Get discount percentage
	 *
	 * @return int|null
	 */
	public function get_discount_pct() {
		return $this->discount_pct;
	}

	/**
	 * Get user agent hash
	 *
	 * @return string|null
	 */
	public function get_user_agent_hash() {
		return $this->user_agent_hash;
	}

	/**
	 * Get device type
	 *
	 * @return string|null
	 */
	public function get_device() {
		return $this->device;
	}

	/**
	 * Get referrer
	 *
	 * @return string|null
	 */
	public function get_ref() {
		return $this->ref;
	}

	/**
	 * Get extra data
	 *
	 * @return string|null
	 */
	public function get_extra() {
		return $this->extra;
	}
}
