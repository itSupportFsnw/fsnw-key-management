<?php
/**
 * Aktivierungsroutine: legt Datenbanktabellen an und vergibt Capabilities.
 *
 * @package FsnwKeyManagement\Includes
 */

namespace FsnwKeyManagement\Includes;

use FsnwKeyManagement\Includes\Support\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * Wird über register_activation_hook() ausgeführt.
 */
class Activator {

	/**
	 * Führt die komplette Aktivierungsroutine aus.
	 */
	public static function activate(): void {
		self::create_tables();
		Capabilities::assign_defaults();

		update_option( 'fsnw_key_management_db_version', FSNW_KEY_MANAGEMENT_DB_VERSION );

		flush_rewrite_rules();
	}

	/**
	 * Bringt das Datenbankschema bestehender Installationen auf den aktuellen Stand.
	 * dbDelta() ist idempotent und ergänzt lediglich fehlende Tabellen/Spalten,
	 * ohne bestehende Daten zu verändern.
	 */
	public static function maybe_upgrade(): void {
		if ( get_option( 'fsnw_key_management_db_version' ) === FSNW_KEY_MANAGEMENT_DB_VERSION ) {
			return;
		}

		self::create_tables();

		update_option( 'fsnw_key_management_db_version', FSNW_KEY_MANAGEMENT_DB_VERSION );
	}

	/**
	 * Legt die eigenen Datenbanktabellen per dbDelta() an bzw. aktualisiert sie.
	 */
	private static function create_tables(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		$apartments_table = $wpdb->prefix . 'fsnw_km_apartments';
		$bundles_table    = $wpdb->prefix . 'fsnw_km_bundles';
		$issues_table     = $wpdb->prefix . 'fsnw_km_issues';
		$logs_table       = $wpdb->prefix . 'fsnw_km_logs';

		$sql = "CREATE TABLE {$apartments_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			label VARCHAR(190) NOT NULL,
			client_name VARCHAR(190) NOT NULL DEFAULT '',
			street VARCHAR(190) NOT NULL DEFAULT '',
			house_number VARCHAR(20) NOT NULL DEFAULT '',
			zip VARCHAR(10) NOT NULL DEFAULT '',
			city VARCHAR(190) NOT NULL DEFAULT '',
			unit VARCHAR(100) NOT NULL DEFAULT '',
			notes TEXT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY status (status)
		) {$charset_collate};
		CREATE TABLE {$bundles_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			apartment_id BIGINT UNSIGNED NOT NULL,
			label VARCHAR(190) NOT NULL,
			keys_list LONGTEXT NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'available',
			notes TEXT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY apartment_id (apartment_id),
			KEY status (status)
		) {$charset_collate};
		CREATE TABLE {$issues_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			bundle_id BIGINT UNSIGNED NOT NULL,
			type VARCHAR(20) NOT NULL,
			status VARCHAR(30) NOT NULL DEFAULT 'awaiting_signature',
			issued_to_user_id BIGINT UNSIGNED NOT NULL,
			issued_by_user_id BIGINT UNSIGNED NOT NULL,
			kiosk_signature_id BIGINT UNSIGNED NULL,
			notes TEXT NULL,
			issued_at DATETIME NULL,
			returned_at DATETIME NULL,
			returned_by_user_id BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY bundle_id (bundle_id),
			KEY status (status)
		) {$charset_collate};
		CREATE TABLE {$logs_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			bundle_id BIGINT UNSIGNED NOT NULL,
			issue_id BIGINT UNSIGNED NULL,
			user_id BIGINT UNSIGNED NOT NULL,
			action VARCHAR(60) NOT NULL,
			meta LONGTEXT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY bundle_id (bundle_id),
			KEY action (action)
		) {$charset_collate};";

		dbDelta( $sql );
	}
}
