<?php
/**
 * Geschäftslogik für Wohnungen.
 *
 * @package FsnwKeyManagement\Includes\Services
 */

namespace FsnwKeyManagement\Includes\Services;

use FsnwKeyManagement\Includes\Repositories\ApartmentRepository;

defined( 'ABSPATH' ) || exit;

/**
 * CRUD für Wohnungen (die Gruppierungs-Ebene der Schlüsselbunde).
 */
class ApartmentService {

	public const STATUS_ACTIVE   = 'active';
	public const STATUS_INACTIVE = 'inactive';

	/**
	 * Repository für den Datenbankzugriff.
	 *
	 * @var ApartmentRepository
	 */
	private ApartmentRepository $repository;

	/**
	 * Konstruktor.
	 *
	 * @param ApartmentRepository|null $repository Repository für den Datenbankzugriff.
	 */
	public function __construct( ?ApartmentRepository $repository = null ) {
		$this->repository = $repository ?? new ApartmentRepository();
	}

	/**
	 * Legt eine neue Wohnung an.
	 *
	 * @param string $label       Bezeichnung (z. B. Adresse/Wohnungsnummer).
	 * @param string $client_name Name des Klienten.
	 * @param string $notes       Optionale Notizen.
	 * @throws \InvalidArgumentException Wenn die Bezeichnung fehlt.
	 */
	public function create( string $label, string $client_name = '', string $notes = '' ): int {
		$label = sanitize_text_field( $label );

		if ( '' === $label ) {
			throw new \InvalidArgumentException( esc_html__( 'Bitte eine Bezeichnung für die Wohnung angeben.', 'fsnw-key-management' ) );
		}

		$now = current_time( 'mysql' );

		return $this->repository->insert(
			array(
				'label'       => $label,
				'client_name' => sanitize_text_field( $client_name ),
				'notes'       => sanitize_textarea_field( $notes ),
				'status'      => self::STATUS_ACTIVE,
				'created_at'  => $now,
				'updated_at'  => $now,
			)
		);
	}

	/**
	 * Aktualisiert eine Wohnung.
	 *
	 * @param int    $id          Wohnungs-ID.
	 * @param string $label       Bezeichnung.
	 * @param string $client_name Name des Klienten.
	 * @param string $notes       Notizen.
	 * @param string $status      active|inactive.
	 * @throws \InvalidArgumentException Wenn die Wohnung nicht existiert oder die Bezeichnung fehlt.
	 */
	public function update( int $id, string $label, string $client_name, string $notes, string $status ): bool {
		if ( null === $this->repository->find( $id ) ) {
			throw new \InvalidArgumentException( esc_html__( 'Wohnung nicht gefunden.', 'fsnw-key-management' ) );
		}

		$label = sanitize_text_field( $label );

		if ( '' === $label ) {
			throw new \InvalidArgumentException( esc_html__( 'Bitte eine Bezeichnung für die Wohnung angeben.', 'fsnw-key-management' ) );
		}

		return $this->repository->update(
			$id,
			array(
				'label'       => $label,
				'client_name' => sanitize_text_field( $client_name ),
				'notes'       => sanitize_textarea_field( $notes ),
				'status'      => self::STATUS_INACTIVE === $status ? self::STATUS_INACTIVE : self::STATUS_ACTIVE,
				'updated_at'  => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Findet eine Wohnung anhand ihrer ID.
	 *
	 * @param int $id Wohnungs-ID.
	 */
	public function find( int $id ): ?array {
		return $this->repository->find( $id );
	}

	/**
	 * Listet Wohnungen, optional nur aktive.
	 *
	 * @param bool $only_active Nur aktive Wohnungen liefern.
	 * @return array<int, array<string, mixed>>
	 */
	public function list( bool $only_active = false ): array {
		return $this->repository->list( $only_active ? self::STATUS_ACTIVE : null );
	}
}
