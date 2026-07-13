<?php
/**
 * Datenzugriffsschicht für die Schlüssel-Historie.
 *
 * @package FsnwKeyManagement\Includes\Repositories
 */

namespace FsnwKeyManagement\Includes\Repositories;

defined( 'ABSPATH' ) || exit;

/**
 * Kapselt Datenbankzugriffe auf die Tabelle wp_fsnw_km_logs.
 */
class LogRepository {

	/**
	 * WordPress-Datenbankverbindung.
	 *
	 * @var \wpdb
	 */
	private \wpdb $wpdb;

	/**
	 * Voll qualifizierter Tabellenname inkl. Präfix.
	 *
	 * @var string
	 */
	private string $table;

	/**
	 * Konstruktor.
	 */
	public function __construct() {
		global $wpdb;

		$this->wpdb  = $wpdb;
		$this->table = $wpdb->prefix . 'fsnw_km_logs';
	}

	/**
	 * Legt einen neuen Log-Eintrag an und gibt dessen ID zurück.
	 *
	 * @param array<string, mixed> $data Spaltenwerte.
	 */
	public function insert( array $data ): int {
		$this->wpdb->insert( $this->table, $data );

		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Listet die Historie eines Bundes, neueste zuerst.
	 *
	 * @param int $bundle_id Bund-ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function list_by_bundle( int $bundle_id ): array {
		return (array) $this->wpdb->get_results(
			$this->wpdb->prepare( 'SELECT * FROM %i WHERE bundle_id = %d ORDER BY created_at DESC, id DESC', $this->table, $bundle_id ),
			ARRAY_A
		);
	}

	/**
	 * Listet die gesammelte Historie mehrerer Bunde, neueste zuerst.
	 *
	 * @param int[] $bundle_ids Bund-IDs.
	 * @return array<int, array<string, mixed>>
	 */
	public function list_by_bundles( array $bundle_ids ): array {
		if ( empty( $bundle_ids ) ) {
			return array();
		}

		$placeholders = implode( ', ', array_fill( 0, count( $bundle_ids ), '%d' ) );

		return (array) $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM %i WHERE bundle_id IN ({$placeholders}) ORDER BY created_at DESC, id DESC", // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
				array_merge( array( $this->table ), array_map( 'intval', $bundle_ids ) )
			),
			ARRAY_A
		);
	}
}
