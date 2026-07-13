<?php
/**
 * Geschäftslogik für Schlüsselbunde.
 *
 * @package FsnwKeyManagement\Includes\Services
 */

namespace FsnwKeyManagement\Includes\Services;

use FsnwKeyManagement\Includes\Repositories\BundleRepository;

defined( 'ABSPATH' ) || exit;

/**
 * CRUD und Statuswechsel für Schlüsselbunde. Ein Bund ist die kleinste
 * ausgebbare Einheit (2-3 benannte Einzelschlüssel, immer als Ganzes).
 */
class BundleService {

	public const STATUS_AVAILABLE   = 'available';
	public const STATUS_ISSUED      = 'issued';
	public const STATUS_HANDED_OVER = 'handed_over';
	public const STATUS_LOST        = 'lost';
	public const STATUS_RETIRED     = 'retired';

	/**
	 * Repository für den Datenbankzugriff.
	 *
	 * @var BundleRepository
	 */
	private BundleRepository $repository;

	/**
	 * Service für die Historie.
	 *
	 * @var LogService
	 */
	private LogService $log_service;

	/**
	 * Konstruktor.
	 *
	 * @param BundleRepository|null $repository  Repository für den Datenbankzugriff.
	 * @param LogService|null       $log_service Service für die Historie.
	 */
	public function __construct( ?BundleRepository $repository = null, ?LogService $log_service = null ) {
		$this->repository  = $repository ?? new BundleRepository();
		$this->log_service = $log_service ?? new LogService();
	}

	/**
	 * Legt einen neuen Bund an.
	 *
	 * @param int      $apartment_id Wohnungs-ID.
	 * @param string   $label        Bezeichnung (z. B. "Bund A" / Nummer im Kasten).
	 * @param string[] $keys         Benannte Einzelschlüssel (min. 1).
	 * @param string   $notes        Optionale Notizen.
	 * @throws \InvalidArgumentException Wenn Label oder Schlüssel fehlen.
	 */
	public function create( int $apartment_id, string $label, array $keys, string $notes = '' ): int {
		$label = sanitize_text_field( $label );
		$keys  = $this->sanitize_keys( $keys );

		if ( '' === $label || empty( $keys ) ) {
			throw new \InvalidArgumentException( esc_html__( 'Bitte eine Bezeichnung und mindestens einen Einzelschlüssel angeben.', 'fsnw-key-management' ) );
		}

		$now = current_time( 'mysql' );

		$bundle_id = $this->repository->insert(
			array(
				'apartment_id' => $apartment_id,
				'label'        => $label,
				'keys_list'    => wp_json_encode( $keys ),
				'status'       => self::STATUS_AVAILABLE,
				'notes'        => sanitize_textarea_field( $notes ),
				'created_at'   => $now,
				'updated_at'   => $now,
			)
		);

		$this->log_service->log( $bundle_id, 'bundle_created', null, array( 'label' => $label ) );

		return $bundle_id;
	}

	/**
	 * Aktualisiert Bezeichnung, Einzelschlüssel und Notizen eines Bundes.
	 *
	 * @param int      $id    Bund-ID.
	 * @param string   $label Bezeichnung.
	 * @param string[] $keys  Benannte Einzelschlüssel (min. 1).
	 * @param string   $notes Notizen.
	 * @throws \InvalidArgumentException Wenn der Bund nicht existiert oder Angaben fehlen.
	 */
	public function update( int $id, string $label, array $keys, string $notes ): bool {
		if ( null === $this->repository->find( $id ) ) {
			throw new \InvalidArgumentException( esc_html__( 'Schlüsselbund nicht gefunden.', 'fsnw-key-management' ) );
		}

		$label = sanitize_text_field( $label );
		$keys  = $this->sanitize_keys( $keys );

		if ( '' === $label || empty( $keys ) ) {
			throw new \InvalidArgumentException( esc_html__( 'Bitte eine Bezeichnung und mindestens einen Einzelschlüssel angeben.', 'fsnw-key-management' ) );
		}

		return $this->repository->update(
			$id,
			array(
				'label'      => $label,
				'keys_list'  => wp_json_encode( $keys ),
				'notes'      => sanitize_textarea_field( $notes ),
				'updated_at' => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Wechselt den Status eines Bundes und schreibt die Historie.
	 *
	 * Erlaubte manuelle Wechsel (Verwaltungs-Seite):
	 * - available → lost|retired  (Verlust/Inventur bzw. Ausmusterung)
	 * - lost|retired|handed_over → available  (wiedergefunden / Ersatz angefertigt)
	 * Statuswechsel rund um Ausgaben (issued usw.) laufen über den IssueService.
	 *
	 * @param int    $id     Bund-ID.
	 * @param string $status Zielstatus.
	 * @param string $action Historie-Aktion (bundle_lost, bundle_retired, bundle_reactivated).
	 * @throws \InvalidArgumentException Wenn der Bund nicht existiert oder der Wechsel unzulässig ist.
	 */
	public function change_status( int $id, string $status, string $action ): bool {
		$bundle = $this->repository->find( $id );

		if ( null === $bundle ) {
			throw new \InvalidArgumentException( esc_html__( 'Schlüsselbund nicht gefunden.', 'fsnw-key-management' ) );
		}

		$allowed = array(
			self::STATUS_LOST      => array( self::STATUS_AVAILABLE ),
			self::STATUS_RETIRED   => array( self::STATUS_AVAILABLE ),
			self::STATUS_AVAILABLE => array( self::STATUS_LOST, self::STATUS_RETIRED, self::STATUS_HANDED_OVER ),
		);

		if ( ! isset( $allowed[ $status ] ) || ! in_array( $bundle['status'], $allowed[ $status ], true ) ) {
			throw new \InvalidArgumentException( esc_html__( 'Dieser Statuswechsel ist nicht zulässig (Bund ist möglicherweise gerade ausgegeben).', 'fsnw-key-management' ) );
		}

		$result = $this->repository->update(
			$id,
			array(
				'status'     => $status,
				'updated_at' => current_time( 'mysql' ),
			)
		);

		$this->log_service->log( $id, $action, null, array( 'from' => $bundle['status'], 'to' => $status ) );

		return $result;
	}

	/**
	 * Findet einen Bund (keys_list dekodiert) anhand seiner ID.
	 *
	 * @param int $id Bund-ID.
	 */
	public function find( int $id ): ?array {
		$bundle = $this->repository->find( $id );

		return null === $bundle ? null : $this->decode( $bundle );
	}

	/**
	 * Listet alle Bunde einer Wohnung (keys_list dekodiert).
	 *
	 * @param int $apartment_id Wohnungs-ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function list_by_apartment( int $apartment_id ): array {
		return array_map( array( $this, 'decode' ), $this->repository->list_by_apartment( $apartment_id ) );
	}

	/**
	 * Listet alle Bunde (keys_list dekodiert).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function list_all(): array {
		return array_map( array( $this, 'decode' ), $this->repository->list_all() );
	}

	/**
	 * Dekodiert die JSON-Schlüsselliste einer Datenbankzeile.
	 *
	 * @param array<string, mixed> $bundle Rohe Datenbankzeile.
	 */
	private function decode( array $bundle ): array {
		$keys = json_decode( (string) $bundle['keys_list'], true );

		$bundle['id']        = (int) $bundle['id'];
		$bundle['keys_list'] = is_array( $keys ) ? $keys : array();

		return $bundle;
	}

	/**
	 * Bereinigt die Einzelschlüssel-Liste (leere Einträge entfernen).
	 *
	 * @param string[] $keys Eingabewerte.
	 * @return string[]
	 */
	private function sanitize_keys( array $keys ): array {
		return array_values( array_filter( array_map( 'sanitize_text_field', $keys ) ) );
	}
}
