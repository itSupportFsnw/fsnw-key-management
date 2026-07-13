<?php
/**
 * Datenzugriffsschicht für Schlüsselbunde.
 *
 * @package FsnwKeyManagement\Includes\Repositories
 */

namespace FsnwKeyManagement\Includes\Repositories;

defined( 'ABSPATH' ) || exit;

/**
 * Kapselt Datenbankzugriffe auf die Tabelle wp_fsnw_km_bundles.
 */
class BundleRepository {

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
		$this->table = $wpdb->prefix . 'fsnw_km_bundles';
	}

	/**
	 * Legt einen neuen Bund an und gibt dessen ID zurück.
	 *
	 * @param array<string, mixed> $data Spaltenwerte.
	 */
	public function insert( array $data ): int {
		$this->wpdb->insert( $this->table, $data );

		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Aktualisiert einen Bund.
	 *
	 * @param int                  $id   Bund-ID.
	 * @param array<string, mixed> $data Zu ändernde Spaltenwerte.
	 */
	public function update( int $id, array $data ): bool {
		return false !== $this->wpdb->update( $this->table, $data, array( 'id' => $id ) );
	}

	/**
	 * Findet einen Bund anhand seiner ID.
	 *
	 * @param int $id Bund-ID.
	 */
	public function find( int $id ): ?array {
		$row = $this->wpdb->get_row(
			$this->wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $this->table, $id ),
			ARRAY_A
		);

		return null === $row ? null : $row;
	}

	/**
	 * Listet alle Bunde einer Wohnung, sortiert nach Label.
	 *
	 * @param int $apartment_id Wohnungs-ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function list_by_apartment( int $apartment_id ): array {
		return (array) $this->wpdb->get_results(
			$this->wpdb->prepare( 'SELECT * FROM %i WHERE apartment_id = %d ORDER BY label ASC', $this->table, $apartment_id ),
			ARRAY_A
		);
	}

	/**
	 * Listet alle Bunde, sortiert nach Wohnung und Label.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function list_all(): array {
		return (array) $this->wpdb->get_results(
			$this->wpdb->prepare( 'SELECT * FROM %i ORDER BY apartment_id ASC, label ASC', $this->table ),
			ARRAY_A
		);
	}

	/**
	 * Liefert nur die IDs aller Bunde einer Wohnung.
	 *
	 * @param int $apartment_id Wohnungs-ID.
	 * @return int[]
	 */
	public function ids_by_apartment( int $apartment_id ): array {
		return array_map(
			'intval',
			(array) $this->wpdb->get_col(
				$this->wpdb->prepare( 'SELECT id FROM %i WHERE apartment_id = %d', $this->table, $apartment_id )
			)
		);
	}

	/**
	 * Löscht alle Bunde einer Wohnung endgültig (nur für das Aufräum-Werkzeug).
	 *
	 * @param int $apartment_id Wohnungs-ID.
	 * @return int Anzahl gelöschter Bunde.
	 */
	public function delete_by_apartment( int $apartment_id ): int {
		return (int) $this->wpdb->delete( $this->table, array( 'apartment_id' => $apartment_id ) );
	}

	/**
	 * Zählt Bunde einer Wohnung je Status.
	 *
	 * @param int      $apartment_id Wohnungs-ID.
	 * @param string[] $statuses     Zu zählende Status.
	 */
	public function count_by_status( int $apartment_id, array $statuses ): int {
		if ( empty( $statuses ) ) {
			return 0;
		}

		$placeholders = implode( ', ', array_fill( 0, count( $statuses ), '%s' ) );

		return (int) $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(*) FROM %i WHERE apartment_id = %d AND status IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
				array_merge( array( $this->table, $apartment_id ), $statuses )
			)
		);
	}
}
