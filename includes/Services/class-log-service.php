<?php
/**
 * Geschäftslogik für die Schlüssel-Historie.
 *
 * @package FsnwKeyManagement\Includes\Services
 */

namespace FsnwKeyManagement\Includes\Services;

use FsnwKeyManagement\Includes\Repositories\LogRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Schreibt und liest Historie-Einträge zu Schlüsselbunden und Ausgaben.
 */
class LogService {

	/**
	 * Repository für den Datenbankzugriff.
	 *
	 * @var LogRepository
	 */
	private LogRepository $repository;

	/**
	 * Konstruktor.
	 *
	 * @param LogRepository|null $repository Repository für den Datenbankzugriff.
	 */
	public function __construct( ?LogRepository $repository = null ) {
		$this->repository = $repository ?? new LogRepository();
	}

	/**
	 * Schreibt einen Historie-Eintrag.
	 *
	 * @param int                  $bundle_id Bund-ID.
	 * @param string               $action    Aktions-Schlüssel (z. B. issue_signed).
	 * @param int|null             $issue_id  Zugehörige Ausgabe, falls vorhanden.
	 * @param array<string, mixed> $meta      Zusätzliche Kontextdaten.
	 */
	public function log( int $bundle_id, string $action, ?int $issue_id = null, array $meta = array() ): int {
		return $this->repository->insert(
			array(
				'bundle_id'  => $bundle_id,
				'issue_id'   => $issue_id,
				'user_id'    => get_current_user_id(),
				'action'     => $action,
				'meta'       => empty( $meta ) ? null : wp_json_encode( $meta ),
				'created_at' => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Liefert die Historie eines Bundes (Meta dekodiert), neueste zuerst.
	 *
	 * @param int $bundle_id Bund-ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function list_by_bundle( int $bundle_id ): array {
		return $this->decode_entries( $this->repository->list_by_bundle( $bundle_id ) );
	}

	/**
	 * Liefert die gesammelte Historie mehrerer Bunde (Meta dekodiert), neueste zuerst.
	 *
	 * @param int[] $bundle_ids Bund-IDs.
	 * @return array<int, array<string, mixed>>
	 */
	public function list_by_bundles( array $bundle_ids ): array {
		return $this->decode_entries( $this->repository->list_by_bundles( $bundle_ids ) );
	}

	/**
	 * Dekodiert die Meta-Spalten einer Ergebnisliste.
	 *
	 * @param array<int, array<string, mixed>> $entries Rohe Datenbankzeilen.
	 * @return array<int, array<string, mixed>>
	 */
	private function decode_entries( array $entries ): array {
		foreach ( $entries as &$entry ) {
			$meta          = empty( $entry['meta'] ) ? array() : json_decode( (string) $entry['meta'], true );
			$entry['meta'] = is_array( $meta ) ? $meta : array();
		}

		return $entries;
	}
}
