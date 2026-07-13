<?php
/**
 * Geschäftslogik für Schlüssel-Ausgaben (mit Kiosk-Unterschrift).
 *
 * @package FsnwKeyManagement\Includes\Services
 */

namespace FsnwKeyManagement\Includes\Services;

use FsnwKeyManagement\Includes\Repositories\BundleRepository;
use FsnwKeyManagement\Includes\Repositories\IssueRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Lebenszyklus einer Ausgabe:
 * awaiting_signature → issued (Tablet-Unterschrift) → returned | lost;
 * awaiting_signature → aborted (Abbruch).
 * Bei Typ "einzug" geht der Bund mit der Unterschrift dauerhaft weg
 * (Bund-Status handed_over), eine Rückgabe ist nicht vorgesehen.
 */
class IssueService {

	public const TYPE_EINZUG        = 'einzug';
	public const TYPE_VERLUST       = 'verlust';
	public const TYPE_KONTROLLE     = 'kontrolle';
	public const TYPE_AUFSCHLIESSEN = 'aufschliessen';
	public const TYPE_SONSTIGES    = 'sonstiges';

	public const STATUS_AWAITING_SIGNATURE = 'awaiting_signature';
	public const STATUS_ISSUED             = 'issued';
	public const STATUS_RETURNED           = 'returned';
	public const STATUS_LOST               = 'lost';
	public const STATUS_ABORTED            = 'aborted';

	/**
	 * Slug, unter dem dieses Plugin Signatur-Anforderungen an den Kiosk sendet.
	 */
	public const KIOSK_SOURCE = 'fsnw-key-management';

	/**
	 * Voll qualifizierter Klassenname der Kiosk-API (Plugin fsnw-signature-kiosk).
	 * Als String, damit diese Klasse auch ohne aktives Kiosk-Plugin ladbar bleibt.
	 */
	private const KIOSK_API = '\FsnwSignatureKiosk\Includes\Api';

	/**
	 * Repository für Ausgaben.
	 *
	 * @var IssueRepository
	 */
	private IssueRepository $repository;

	/**
	 * Repository für Bunde (Statuswechsel im Ausgabe-Flow).
	 *
	 * @var BundleRepository
	 */
	private BundleRepository $bundle_repository;

	/**
	 * Service für Bunde (dekodierte Lesezugriffe).
	 *
	 * @var BundleService
	 */
	private BundleService $bundle_service;

	/**
	 * Service für Wohnungen.
	 *
	 * @var ApartmentService
	 */
	private ApartmentService $apartment_service;

	/**
	 * Service für die Bestandslogik.
	 *
	 * @var InventoryService
	 */
	private InventoryService $inventory_service;

	/**
	 * Service für die Historie.
	 *
	 * @var LogService
	 */
	private LogService $log_service;

	/**
	 * Konstruktor.
	 */
	public function __construct() {
		$this->repository        = new IssueRepository();
		$this->bundle_repository = new BundleRepository();
		$this->bundle_service    = new BundleService();
		$this->apartment_service = new ApartmentService();
		$this->inventory_service = new InventoryService();
		$this->log_service       = new LogService();
	}

	/**
	 * Anzeige-Labels der Ausgabe-Typen.
	 *
	 * @return array<string, string>
	 */
	public static function type_labels(): array {
		return array(
			self::TYPE_KONTROLLE     => __( 'Wohnungskontrolle (kommt zurück)', 'fsnw-key-management' ),
			self::TYPE_AUFSCHLIESSEN => __( 'Aufschließen – ausgesperrt (kommt zurück)', 'fsnw-key-management' ),
			self::TYPE_EINZUG        => __( 'Einzug (Bund bleibt beim Klienten)', 'fsnw-key-management' ),
			self::TYPE_VERLUST       => __( 'Ausgabe bei Verlust (Ersatz an Klient, alter Bund wird ausgebucht)', 'fsnw-key-management' ),
			self::TYPE_SONSTIGES     => __( 'Sonstiges (kommt zurück)', 'fsnw-key-management' ),
		);
	}

	/**
	 * True für Typen, bei denen der Bund dauerhaft weggeht (keine Rückgabe).
	 *
	 * @param string $type Ausgabe-Typ.
	 */
	public static function is_permanent_type( string $type ): bool {
		return in_array( $type, array( self::TYPE_EINZUG, self::TYPE_VERLUST ), true );
	}

	/**
	 * Startet eine Ausgabe: sperrt den Bund, legt die Ausgabe an und sendet
	 * die Signatur-Anforderung an das Kiosk-Tablet.
	 *
	 * @param int    $bundle_id         Bund-ID.
	 * @param int    $issued_to_user_id Empfangender Mitarbeiter.
	 * @param string $type              Ausgabe-Typ (einzug|kontrolle|sonstiges).
	 * @param string $notes             Optionale Notiz.
	 * @param bool   $confirm_last      Bewusste Bestätigung, den letzten verfügbaren Bund auszugeben.
	 * @return int Die Ausgabe-ID.
	 * @throws \InvalidArgumentException Bei fehlendem Kiosk-Plugin, ungültigen Angaben oder unbestätigter Letzter-Bund-Warnung.
	 */
	public function start( int $bundle_id, int $issued_to_user_id, string $type, string $notes = '', bool $confirm_last = false ): int {
		if ( ! class_exists( self::KIOSK_API ) ) {
			throw new \InvalidArgumentException(
				esc_html__( 'Das Plugin "FSNW Signature Kiosk" ist nicht aktiv – Ausgabe nicht möglich.', 'fsnw-key-management' )
			);
		}

		if ( ! array_key_exists( $type, self::type_labels() ) ) {
			throw new \InvalidArgumentException( esc_html__( 'Unbekannter Ausgabe-Typ.', 'fsnw-key-management' ) );
		}

		$recipient = get_userdata( $issued_to_user_id );

		if ( false === $recipient ) {
			throw new \InvalidArgumentException( esc_html__( 'Bitte einen Mitarbeiter auswählen.', 'fsnw-key-management' ) );
		}

		$bundle = $this->bundle_service->find( $bundle_id );

		if ( null === $bundle || BundleService::STATUS_AVAILABLE !== $bundle['status'] ) {
			throw new \InvalidArgumentException( esc_html__( 'Dieser Schlüsselbund ist nicht verfügbar.', 'fsnw-key-management' ) );
		}

		$apartment = $this->apartment_service->find( (int) $bundle['apartment_id'] );

		if ( null === $apartment ) {
			throw new \InvalidArgumentException( esc_html__( 'Zugehörige Wohnung nicht gefunden.', 'fsnw-key-management' ) );
		}

		if ( $this->inventory_service->is_last_available( (int) $apartment['id'] ) && ! $confirm_last ) {
			throw new \InvalidArgumentException(
				esc_html__( 'Das ist der letzte Bund im Schrank – Ausgabe nicht empfohlen. Bitte die Warnung bestätigen, um trotzdem auszugeben.', 'fsnw-key-management' )
			);
		}

		$now = current_time( 'mysql' );

		$issue_id = $this->repository->insert(
			array(
				'bundle_id'         => $bundle_id,
				'type'              => $type,
				'status'            => self::STATUS_AWAITING_SIGNATURE,
				'issued_to_user_id' => $issued_to_user_id,
				'issued_by_user_id' => get_current_user_id(),
				'notes'             => sanitize_textarea_field( $notes ),
				'created_at'        => $now,
				'updated_at'        => $now,
			)
		);

		// Bund sofort sperren, damit er nicht doppelt ausgegeben werden kann.
		$this->bundle_repository->update(
			$bundle_id,
			array(
				'status'     => BundleService::STATUS_ISSUED,
				'updated_at' => $now,
			)
		);

		$items = array( $bundle['label'] . ' – ' . $apartment['label'] );
		foreach ( $bundle['keys_list'] as $single_key ) {
			$items[] = $single_key;
		}

		$meta_lines = array();
		if ( '' !== $apartment['client_name'] ) {
			$meta_lines[] = __( 'Klient:', 'fsnw-key-management' ) . ' ' . $apartment['client_name'];
		}
		$meta_lines[] = __( 'Typ:', 'fsnw-key-management' ) . ' ' . self::type_labels()[ $type ];

		call_user_func(
			array( self::KIOSK_API, 'create_request' ),
			array(
				'source'         => self::KIOSK_SOURCE,
				'reference'      => (string) $issue_id,
				'title'          => __( 'Schlüsselausgabe', 'fsnw-key-management' ),
				'recipient_name' => $recipient->display_name,
				'items'          => $items,
				'meta_lines'     => $meta_lines,
			)
		);

		$this->log_service->log(
			$bundle_id,
			'issue_started',
			$issue_id,
			array(
				'type'         => $type,
				'issued_to'    => $recipient->display_name,
				'confirm_last' => $confirm_last,
			)
		);

		return $issue_id;
	}

	/**
	 * Bricht eine Ausgabe ab, solange noch nicht unterschrieben wurde.
	 *
	 * @param int $issue_id Ausgabe-ID.
	 * @throws \InvalidArgumentException Wenn die Ausgabe nicht existiert oder nicht mehr abbrechbar ist.
	 */
	public function abort( int $issue_id ): void {
		$issue = $this->require_issue( $issue_id, self::STATUS_AWAITING_SIGNATURE );

		if ( class_exists( self::KIOSK_API ) ) {
			call_user_func( array( self::KIOSK_API, 'cancel_by_reference' ), self::KIOSK_SOURCE, (string) $issue_id );
		}

		$this->update_issue_status( $issue, self::STATUS_ABORTED );
		$this->update_bundle_status( (int) $issue['bundle_id'], BundleService::STATUS_AVAILABLE );
		$this->log_service->log( (int) $issue['bundle_id'], 'issue_aborted', $issue_id );
	}

	/**
	 * Vermerkt die Rückgabe eines ausgegebenen Bundes (nur Typen mit Rückgabe).
	 *
	 * @param int $issue_id Ausgabe-ID.
	 * @throws \InvalidArgumentException Wenn die Ausgabe nicht existiert oder keine Rückgabe vorgesehen ist.
	 */
	public function mark_returned( int $issue_id ): void {
		$issue = $this->require_issue( $issue_id, self::STATUS_ISSUED );

		if ( self::is_permanent_type( $issue['type'] ) ) {
			throw new \InvalidArgumentException( esc_html__( 'Bei dieser Ausgabe-Art ist keine Rückgabe vorgesehen (Bund bleibt beim Klienten).', 'fsnw-key-management' ) );
		}

		$this->update_issue_status(
			$issue,
			self::STATUS_RETURNED,
			array(
				'returned_at'         => current_time( 'mysql' ),
				'returned_by_user_id' => get_current_user_id(),
			)
		);
		$this->update_bundle_status( (int) $issue['bundle_id'], BundleService::STATUS_AVAILABLE );
		$this->log_service->log( (int) $issue['bundle_id'], 'issue_returned', $issue_id );
	}

	/**
	 * Meldet einen ausgegebenen Bund als verloren.
	 *
	 * @param int $issue_id Ausgabe-ID.
	 * @throws \InvalidArgumentException Wenn die Ausgabe nicht existiert oder nicht offen ist.
	 */
	public function mark_lost( int $issue_id ): void {
		$issue = $this->require_issue( $issue_id, self::STATUS_ISSUED );

		$this->update_issue_status( $issue, self::STATUS_LOST );
		$this->update_bundle_status( (int) $issue['bundle_id'], BundleService::STATUS_LOST );
		$this->log_service->log( (int) $issue['bundle_id'], 'issue_lost', $issue_id );
	}

	/**
	 * Verarbeitet den Abschluss der Kiosk-Signatur (Hook fsnw_signature_completed).
	 *
	 * Bewusst ohne Exception bei unpassendem Zustand: Der Hook läuft innerhalb
	 * des REST-Requests des Kiosk-Tablets, die Unterschrift ist zu diesem
	 * Zeitpunkt bereits gespeichert - ein veralteter/unbekannter Abschluss
	 * wird daher still ignoriert statt die Tablet-Antwort zu brechen.
	 *
	 * @param int $issue_id           Ausgabe-ID (reference der Anforderung).
	 * @param int $kiosk_signature_id Signatur-ID im Kiosk-Plugin.
	 */
	public function handle_kiosk_completion( int $issue_id, int $kiosk_signature_id ): void {
		$issue = $this->repository->find( $issue_id );

		if ( null === $issue || self::STATUS_AWAITING_SIGNATURE !== $issue['status'] ) {
			return;
		}

		$this->update_issue_status(
			$issue,
			self::STATUS_ISSUED,
			array(
				'kiosk_signature_id' => $kiosk_signature_id,
				'issued_at'          => current_time( 'mysql' ),
			)
		);

		// Bei Einzug/Verlust geht der Bund dauerhaft weg - er zählt ab jetzt nicht
		// mehr zum Bestand und löst ggf. den Hinweis "Neuen Schlüssel anfertigen" aus.
		if ( self::is_permanent_type( $issue['type'] ) ) {
			$this->update_bundle_status( (int) $issue['bundle_id'], BundleService::STATUS_HANDED_OVER );
		}

		// Bei "Ausgabe bei Verlust" wird zusätzlich der verlorene alte Bund des
		// Klienten ausgebucht (die Bunde sind untereinander identisch).
		if ( self::TYPE_VERLUST === $issue['type'] ) {
			$this->book_out_lost_bundle( (int) $issue['bundle_id'], $issue_id );
		}

		$this->log_service->log(
			(int) $issue['bundle_id'],
			'issue_signed',
			$issue_id,
			array( 'kiosk_signature_id' => $kiosk_signature_id )
		);
	}

	/**
	 * Übergibt einen unterwegs befindlichen Bund dauerhaft an den Klienten
	 * (Verlust-Nachmeldung): Die laufende Ausgabe mit Rückkehr wird zur
	 * "Ausgabe bei Verlust", der Bund bleibt beim Klienten und der verlorene
	 * alte Bund des Klienten wird ausgebucht.
	 *
	 * @param int $issue_id Ausgabe-ID.
	 * @throws \InvalidArgumentException Wenn die Ausgabe nicht offen/unterwegs ist.
	 */
	public function hand_over_to_client( int $issue_id ): void {
		$issue  = $this->require_issue( $issue_id, self::STATUS_ISSUED );
		$bundle = $this->bundle_service->find( (int) $issue['bundle_id'] );

		if ( null === $bundle || BundleService::STATUS_ISSUED !== $bundle['status'] ) {
			throw new \InvalidArgumentException( esc_html__( 'Dieser Bund ist nicht (mehr) unterwegs.', 'fsnw-key-management' ) );
		}

		$original_type = (string) $issue['type'];

		$this->repository->update(
			$issue_id,
			array(
				'type'       => self::TYPE_VERLUST,
				'updated_at' => current_time( 'mysql' ),
			)
		);

		$this->update_bundle_status( (int) $issue['bundle_id'], BundleService::STATUS_HANDED_OVER );
		$this->book_out_lost_bundle( (int) $issue['bundle_id'], $issue_id );

		$this->log_service->log(
			(int) $issue['bundle_id'],
			'issue_handed_over_to_client',
			$issue_id,
			array( 'original_type' => $original_type )
		);
	}

	/**
	 * Bucht den verlorenen alten Bund des Klienten aus: markiert einen anderen,
	 * dauerhaft vergebenen (handed_over) Bund derselben Wohnung als verloren.
	 * Existiert keiner, passiert nichts (der Verlust betrifft dann einen Bund,
	 * der nie im System erfasst wurde).
	 *
	 * @param int $issued_bundle_id Der gerade ausgegebene Bund (bleibt unberührt).
	 * @param int $issue_id         Auslösende Ausgabe (für die Historie).
	 */
	private function book_out_lost_bundle( int $issued_bundle_id, int $issue_id ): void {
		$issued_bundle = $this->bundle_service->find( $issued_bundle_id );

		if ( null === $issued_bundle ) {
			return;
		}

		foreach ( $this->bundle_service->list_by_apartment( (int) $issued_bundle['apartment_id'] ) as $candidate ) {
			if ( (int) $candidate['id'] === $issued_bundle_id || BundleService::STATUS_HANDED_OVER !== $candidate['status'] ) {
				continue;
			}

			$this->update_bundle_status( (int) $candidate['id'], BundleService::STATUS_LOST );
			$this->log_service->log(
				(int) $candidate['id'],
				'bundle_lost',
				$issue_id,
				array( 'auto' => true, 'reason' => 'verlust_ausgebucht' )
			);

			return;
		}
	}

	/**
	 * Liefert einen Fingerabdruck der offenen Ausgaben, damit die Ausgabe-Seite
	 * per Polling erkennen kann, ob sich etwas geändert hat (z. B. Unterschrift
	 * am Tablet), ohne bei jedem Tick die komplette Übersicht zu übertragen.
	 */
	public function get_watch_signal(): string {
		$fingerprint = array();

		foreach ( array( self::STATUS_AWAITING_SIGNATURE, self::STATUS_ISSUED ) as $status ) {
			foreach ( $this->repository->list_by_status( $status ) as $issue ) {
				$fingerprint[] = $issue['id'] . ':' . $issue['status'] . ':' . $issue['updated_at'];
			}
		}

		sort( $fingerprint );

		return md5( implode( '|', $fingerprint ) );
	}

	/**
	 * Listet Ausgaben eines Status, angereichert um Bund-/Wohnungs-/Nutzerdaten.
	 *
	 * @param string $status Ausgabe-Status.
	 * @return array<int, array<string, mixed>>
	 */
	public function list_enriched( string $status ): array {
		$issues = $this->repository->list_by_status( $status );

		foreach ( $issues as &$issue ) {
			$bundle    = $this->bundle_service->find( (int) $issue['bundle_id'] );
			$apartment = null === $bundle ? null : $this->apartment_service->find( (int) $bundle['apartment_id'] );
			$recipient = get_userdata( (int) $issue['issued_to_user_id'] );

			$issue['id']              = (int) $issue['id'];
			$issue['bundle_label']    = null === $bundle ? '' : $bundle['label'];
			$issue['bundle_status']   = null === $bundle ? '' : $bundle['status'];
			$issue['keys_list']       = null === $bundle ? array() : $bundle['keys_list'];
			$issue['apartment_label'] = null === $apartment ? '' : $apartment['label'];
			$issue['client_name']     = null === $apartment ? '' : $apartment['client_name'];
			$issue['recipient_name']  = false === $recipient ? '—' : $recipient->display_name;
		}

		return $issues;
	}

	/**
	 * Lädt eine Ausgabe und stellt den erwarteten Status sicher.
	 *
	 * @param int    $issue_id        Ausgabe-ID.
	 * @param string $expected_status Erwarteter Status.
	 * @return array<string, mixed>
	 * @throws \InvalidArgumentException Wenn die Ausgabe fehlt oder im falschen Status ist.
	 */
	private function require_issue( int $issue_id, string $expected_status ): array {
		$issue = $this->repository->find( $issue_id );

		if ( null === $issue || $expected_status !== $issue['status'] ) {
			throw new \InvalidArgumentException( esc_html__( 'Diese Ausgabe ist nicht (mehr) in einem passenden Zustand.', 'fsnw-key-management' ) );
		}

		return $issue;
	}

	/**
	 * Aktualisiert Status (+ optionale Felder) einer Ausgabe.
	 *
	 * @param array<string, mixed> $issue  Ausgabe-Zeile.
	 * @param string               $status Zielstatus.
	 * @param array<string, mixed> $extra  Zusätzliche Spaltenwerte.
	 */
	private function update_issue_status( array $issue, string $status, array $extra = array() ): void {
		$this->repository->update(
			(int) $issue['id'],
			array_merge(
				$extra,
				array(
					'status'     => $status,
					'updated_at' => current_time( 'mysql' ),
				)
			)
		);
	}

	/**
	 * Aktualisiert den Status eines Bundes (Ausgabe-Flow, ohne die manuellen
	 * Wechsel-Regeln des BundleService).
	 *
	 * @param int    $bundle_id Bund-ID.
	 * @param string $status    Zielstatus.
	 */
	private function update_bundle_status( int $bundle_id, string $status ): void {
		$this->bundle_repository->update(
			$bundle_id,
			array(
				'status'     => $status,
				'updated_at' => current_time( 'mysql' ),
			)
		);
	}
}
