<?php
/**
 * Datenzugriffsschicht für Schlüssel-Ausgaben.
 *
 * @package FsnwKeyManagement\Includes\Repositories
 */

namespace FsnwKeyManagement\Includes\Repositories;

defined( 'ABSPATH' ) || exit;

/**
 * Kapselt Datenbankzugriffe auf die Tabelle wp_fsnw_km_issues.
 */
class IssueRepository {

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
		$this->table = $wpdb->prefix . 'fsnw_km_issues';
	}

	/**
	 * Legt eine neue Ausgabe an und gibt deren ID zurück.
	 *
	 * @param array<string, mixed> $data Spaltenwerte.
	 */
	public function insert( array $data ): int {
		$this->wpdb->insert( $this->table, $data );

		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Aktualisiert eine Ausgabe.
	 *
	 * @param int                  $id   Ausgabe-ID.
	 * @param array<string, mixed> $data Zu ändernde Spaltenwerte.
	 */
	public function update( int $id, array $data ): bool {
		return false !== $this->wpdb->update( $this->table, $data, array( 'id' => $id ) );
	}

	/**
	 * Findet eine Ausgabe anhand ihrer ID.
	 *
	 * @param int $id Ausgabe-ID.
	 */
	public function find( int $id ): ?array {
		$row = $this->wpdb->get_row(
			$this->wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $this->table, $id ),
			ARRAY_A
		);

		return null === $row ? null : $row;
	}

	/**
	 * Listet Ausgaben mit einem bestimmten Status, älteste zuerst.
	 *
	 * @param string $status Ausgabe-Status.
	 * @return array<int, array<string, mixed>>
	 */
	public function list_by_status( string $status ): array {
		return (array) $this->wpdb->get_results(
			$this->wpdb->prepare( 'SELECT * FROM %i WHERE status = %s ORDER BY created_at ASC, id ASC', $this->table, $status ),
			ARRAY_A
		);
	}

	/**
	 * Listet die Ausgabe-Historie eines Bundes, neueste zuerst.
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
	 * Löscht alle Ausgaben der angegebenen Bunde endgültig (nur für das Aufräum-Werkzeug).
	 *
	 * @param int[] $bundle_ids Bund-IDs.
	 * @return int Anzahl gelöschter Ausgaben.
	 */
	public function delete_by_bundles( array $bundle_ids ): int {
		if ( empty( $bundle_ids ) ) {
			return 0;
		}

		$placeholders = implode( ', ', array_fill( 0, count( $bundle_ids ), '%d' ) );

		return (int) $this->wpdb->query(
			$this->wpdb->prepare(
				"DELETE FROM %i WHERE bundle_id IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
				array_merge( array( $this->table ), array_map( 'intval', $bundle_ids ) )
			)
		);
	}

	/**
	 * Löscht abgeschlossene Ausgaben (returned/aborted), die älter als der
	 * Stichtag sind (nur für das Aufräum-Werkzeug).
	 *
	 * @param string $cutoff Stichtag im Format "Y-m-d H:i:s".
	 * @return int Anzahl gelöschter Ausgaben.
	 */
	public function delete_finished_older_than( string $cutoff ): int {
		return (int) $this->wpdb->query(
			$this->wpdb->prepare(
				"DELETE FROM %i WHERE status IN ('returned', 'aborted') AND updated_at < %s",
				$this->table,
				$cutoff
			)
		);
	}

	/**
	 * Findet die aktuell offene Ausgabe eines Bundes (awaiting_signature oder issued), oder null.
	 *
	 * @param int $bundle_id Bund-ID.
	 */
	public function find_open_by_bundle( int $bundle_id ): ?array {
		$row = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM %i WHERE bundle_id = %d AND status IN ('awaiting_signature', 'issued') ORDER BY id DESC LIMIT 1",
				$this->table,
				$bundle_id
			),
			ARRAY_A
		);

		return null === $row ? null : $row;
	}
}
