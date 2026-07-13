<?php
/**
 * Bestands- und Warnlogik für den zentralen Schlüsselkasten.
 *
 * @package FsnwKeyManagement\Includes\Services
 */

namespace FsnwKeyManagement\Includes\Services;

use FsnwKeyManagement\Includes\Repositories\BundleRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Zentrale Stelle für alle Bestandsfragen:
 * - "verfügbar" = Bunde im Schrank (status available).
 * - "vorhanden" = verfügbar + temporär draußen (status available|issued);
 *   dauerhaft vergebene (handed_over), verlorene und ausgemusterte Bunde
 *   zählen nicht mehr zum Bestand.
 * Normalzustand je Wohnung: mindestens 2 vorhandene Bunde.
 */
class InventoryService {

	/**
	 * Mindestbestand vorhandener Bunde je Wohnung.
	 */
	public const MIN_BUNDLES_PER_APARTMENT = 2;

	/**
	 * Repository für den Datenbankzugriff.
	 *
	 * @var BundleRepository
	 */
	private BundleRepository $repository;

	/**
	 * Konstruktor.
	 *
	 * @param BundleRepository|null $repository Repository für den Datenbankzugriff.
	 */
	public function __construct( ?BundleRepository $repository = null ) {
		$this->repository = $repository ?? new BundleRepository();
	}

	/**
	 * Anzahl der Bunde einer Wohnung, die im Schrank hängen.
	 *
	 * @param int $apartment_id Wohnungs-ID.
	 */
	public function available_count( int $apartment_id ): int {
		return $this->repository->count_by_status( $apartment_id, array( BundleService::STATUS_AVAILABLE ) );
	}

	/**
	 * Anzahl der vorhandenen Bunde einer Wohnung (im Schrank + temporär draußen).
	 *
	 * @param int $apartment_id Wohnungs-ID.
	 */
	public function existing_count( int $apartment_id ): int {
		return $this->repository->count_by_status(
			$apartment_id,
			array( BundleService::STATUS_AVAILABLE, BundleService::STATUS_ISSUED )
		);
	}

	/**
	 * True, wenn die Ausgabe eines Bundes dieser Wohnung nicht empfohlen ist
	 * (es hängt nur noch genau ein Bund im Schrank).
	 *
	 * @param int $apartment_id Wohnungs-ID.
	 */
	public function is_last_available( int $apartment_id ): bool {
		return 1 === $this->available_count( $apartment_id );
	}

	/**
	 * True, wenn für diese Wohnung ein neuer Schlüssel angefertigt werden muss
	 * (dauerhaft weniger als MIN_BUNDLES_PER_APARTMENT Bunde vorhanden,
	 * z. B. durch Verlust oder Einzug).
	 *
	 * @param int $apartment_id Wohnungs-ID.
	 */
	public function needs_replacement( int $apartment_id ): bool {
		return $this->existing_count( $apartment_id ) < self::MIN_BUNDLES_PER_APARTMENT;
	}

	/**
	 * Liefert die Bestandsübersicht je Wohnung als Map apartment_id → Zählwerte
	 * inkl. der beiden Warn-Flags. Grundlage für Inventarliste und Hinweis-Banner.
	 *
	 * @param array<int, array<string, mixed>> $apartments Wohnungen (aus ApartmentService::list()).
	 * @return array<int, array{available: int, issued: int, existing: int, last_available: bool, needs_replacement: bool}>
	 */
	public function overview( array $apartments ): array {
		$overview = array();

		foreach ( $apartments as $apartment ) {
			$apartment_id = (int) $apartment['id'];
			$available    = $this->available_count( $apartment_id );
			$existing     = $this->existing_count( $apartment_id );

			$overview[ $apartment_id ] = array(
				'available'         => $available,
				'issued'            => $existing - $available,
				'existing'          => $existing,
				'last_available'    => 1 === $available,
				'needs_replacement' => $existing < self::MIN_BUNDLES_PER_APARTMENT,
			);
		}

		return $overview;
	}
}
