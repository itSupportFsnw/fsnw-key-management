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
	 * Legt eine neue Wohnung an. Die Anzeige-Bezeichnung (label) wird
	 * automatisch aus der Adresse zusammengesetzt.
	 *
	 * @param array<string, string> $address Adressfelder: street, house_number, zip, city, unit (optional).
	 * @param string                $notes   Optionale Notizen.
	 * @throws \InvalidArgumentException Wenn Pflicht-Adressfelder fehlen.
	 */
	public function create( array $address, string $notes = '' ): int {
		$fields = $this->sanitize_address( $address );
		$now    = current_time( 'mysql' );

		return $this->repository->insert(
			array_merge(
				$fields,
				array(
					'label'      => $this->compose_label( $fields ),
					'notes'      => sanitize_textarea_field( $notes ),
					'status'     => self::STATUS_ACTIVE,
					'created_at' => $now,
					'updated_at' => $now,
				)
			)
		);
	}

	/**
	 * Aktualisiert eine Wohnung.
	 *
	 * @param int                   $id      Wohnungs-ID.
	 * @param array<string, string> $address Adressfelder: street, house_number, zip, city, unit (optional).
	 * @param string                $notes   Notizen.
	 * @param string                $status  active|inactive.
	 * @throws \InvalidArgumentException Wenn die Wohnung nicht existiert oder Pflicht-Adressfelder fehlen.
	 */
	public function update( int $id, array $address, string $notes, string $status ): bool {
		if ( null === $this->repository->find( $id ) ) {
			throw new \InvalidArgumentException( esc_html__( 'Wohnung nicht gefunden.', 'fsnw-key-management' ) );
		}

		$fields = $this->sanitize_address( $address );

		return $this->repository->update(
			$id,
			array_merge(
				$fields,
				array(
					'label'      => $this->compose_label( $fields ),
					'notes'      => sanitize_textarea_field( $notes ),
					'status'     => self::STATUS_INACTIVE === $status ? self::STATUS_INACTIVE : self::STATUS_ACTIVE,
					'updated_at' => current_time( 'mysql' ),
				)
			)
		);
	}

	/**
	 * Bereinigt die Adressfelder und prüft die Pflichtangaben.
	 *
	 * @param array<string, string> $address Rohe Adressfelder.
	 * @return array<string, string>
	 * @throws \InvalidArgumentException Wenn Straße, Hausnummer, PLZ oder Ort fehlen.
	 */
	private function sanitize_address( array $address ): array {
		$fields = array(
			'street'       => sanitize_text_field( $address['street'] ?? '' ),
			'house_number' => sanitize_text_field( $address['house_number'] ?? '' ),
			'zip'          => sanitize_text_field( $address['zip'] ?? '' ),
			'city'         => sanitize_text_field( $address['city'] ?? '' ),
			'unit'         => sanitize_text_field( $address['unit'] ?? '' ),
		);

		if ( '' === $fields['street'] || '' === $fields['house_number'] || '' === $fields['zip'] || '' === $fields['city'] ) {
			throw new \InvalidArgumentException( esc_html__( 'Bitte Straße, Hausnummer, PLZ und Ort angeben.', 'fsnw-key-management' ) );
		}

		return $fields;
	}

	/**
	 * Setzt die Anzeige-Bezeichnung aus der Adresse zusammen,
	 * z. B. "Musterstraße 12, 44135 Dortmund – WE 3".
	 *
	 * @param array<string, string> $fields Bereinigte Adressfelder.
	 */
	private function compose_label( array $fields ): string {
		$label = $fields['street'] . ' ' . $fields['house_number'] . ', ' . $fields['zip'] . ' ' . $fields['city'];

		if ( '' !== $fields['unit'] ) {
			$label .= ' – ' . $fields['unit'];
		}

		return $label;
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
