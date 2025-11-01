<?php
/**
 * Database Migrator
 *
 * @package HW_Onsale\Infrastructure\DB
 */

namespace HW_Onsale\Infrastructure\DB;

/**
 * Database Migrator Class
 */
class Migrator {
	/**
	 * Current database version
	 */
	const DB_VERSION = '1.0.0';

	/**
	 * Run migrations
	 */
	public function migrate() {
		$installed_version = get_option( 'hw_onsale_db_version', '0' );

		if ( version_compare( $installed_version, self::DB_VERSION, '<' ) ) {
			$this->create_events_table();
			update_option( 'hw_onsale_db_version', self::DB_VERSION );
		}
	}

	/**
	 * Create events table
	 */
	private function create_events_table() {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'hw_onsale_events';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			session_id VARCHAR(40) NOT NULL,
			event VARCHAR(24) NOT NULL,
			product_id BIGINT(20) UNSIGNED NULL,
			discount_pct TINYINT(3) UNSIGNED NULL,
			user_agent_hash CHAR(32) NULL,
			device VARCHAR(12) NULL,
			ref VARCHAR(255) NULL,
			created_at DATETIME NOT NULL,
			extra LONGTEXT NULL,
			PRIMARY KEY (id),
			KEY created_at (created_at),
			KEY event_created (event, created_at),
			KEY product_created (product_id, created_at),
			KEY session_id (session_id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}
}
