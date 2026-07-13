<?php
/**
 * Datenzugriffsschicht für Wohnungen.
 *
 * @package FsnwKeyManagement\Includes\Repositories
 */

namespace FsnwKeyManagement\Includes\Repositories;

defined( 'ABSPATH' ) || exit;

/**
 * Kapselt Datenbankzugriffe auf die Tabelle wp_fsnw_km_apartments.
 */
class ApartmentRepository {

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
		$this->table = $wpdb->prefix . 'fsnw_km_apartments';
	}

	/**
	 * Legt eine neue Wohnung an und gibt deren ID zurück.
	 *
	 * @param array<string, mixed> $data Spaltenwerte.
	 */
	public function insert( array $data ): int {
		$this->wpdb->insert( $this->table, $data );

		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Aktualisiert eine Wohnung.
	 *
	 * @param int                  $id   Wohnungs-ID.
	 * @param array<string, mixed> $data Zu ändernde Spaltenwerte.
	 */
	public function update( int $id, array $data ): bool {
		return false !== $this->wpdb->update( $this->table, $data, array( 'id' => $id ) );
	}

	/**
	 * Findet eine Wohnung anhand ihrer ID.
	 *
	 * @param int $id Wohnungs-ID.
	 */
	public function find( int $id ): ?array {
		$row = $this->wpdb->get_row(
			$this->wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $this->table, $id ),
			ARRAY_A
		);

		return null === $row ? null : $row;
	}

	/**
	 * Löscht eine Wohnung endgültig (nur für das Aufräum-Werkzeug).
	 *
	 * @param int $id Wohnungs-ID.
	 */
	public function delete( int $id ): bool {
		return false !== $this->wpdb->delete( $this->table, array( 'id' => $id ) );
	}

	/**
	 * Listet Wohnungen, optional nach Status gefiltert, alphabetisch nach Label.
	 *
	 * @param string|null $status Optionaler Statusfilter (active|inactive).
	 * @return array<int, array<string, mixed>>
	 */
	public function list( ?string $status = null ): array {
		if ( null === $status ) {
			return (array) $this->wpdb->get_results(
				$this->wpdb->prepare( 'SELECT * FROM %i ORDER BY label ASC', $this->table ),
				ARRAY_A
			);
		}

		return (array) $this->wpdb->get_results(
			$this->wpdb->prepare( 'SELECT * FROM %i WHERE status = %s ORDER BY label ASC', $this->table, $status ),
			ARRAY_A
		);
	}
}
