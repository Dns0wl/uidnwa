<?php
/**
 * Transient Cache
 *
 * @package HW_Onsale\Infrastructure\Cache
 */

namespace HW_Onsale\Infrastructure\Cache;

/**
 * Transient Cache Class
 */
class TransientCache {
	/**
	 * Get cached value
	 *
	 * @param string $key Cache key.
	 * @return mixed|false
	 */
	public function get( $key ) {
		if ( get_option( 'hw_onsale_cache_enabled', '0' ) !== '1' ) {
			return false;
		}

		return get_transient( 'hw_onsale_' . $key );
	}

	/**
	 * Set cached value
	 *
	 * @param string $key Cache key.
	 * @param mixed  $value Value to cache.
	 * @return bool
	 */
	public function set( $key, $value ) {
		if ( get_option( 'hw_onsale_cache_enabled', '0' ) !== '1' ) {
			return false;
		}

		$ttl = (int) get_option( 'hw_onsale_cache_ttl', 3600 );
		return set_transient( 'hw_onsale_' . $key, $value, $ttl );
	}

	/**
	 * Delete cached value
	 *
	 * @param string $key Cache key.
	 * @return bool
	 */
	public function delete( $key ) {
		return delete_transient( 'hw_onsale_' . $key );
	}

	/**
	 * Flush all cache
	 */
	public function flush_all() {
		global $wpdb;

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( '_transient_hw_onsale_' ) . '%'
			)
		);

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( '_transient_timeout_hw_onsale_' ) . '%'
			)
		);
	}
}
