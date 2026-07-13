<?php
/**
 * Frontend-Controller: Shortcodes, Assets und Formular-Handler.
 *
 * @package FsnwKeyManagement\Frontend
 */

namespace FsnwKeyManagement\Frontend;

use FsnwKeyManagement\Includes\Services\ApartmentService;
use FsnwKeyManagement\Includes\Services\BundleService;
use FsnwKeyManagement\Includes\Services\InventoryService;
use FsnwKeyManagement\Includes\Services\IssueService;
use FsnwKeyManagement\Includes\Services\LogService;
use FsnwKeyManagement\Includes\Support\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * Registriert die beiden Frontend-Seiten (Verwaltung + Ausgabe) und
 * verarbeitet deren Formulare über admin_post-Handler.
 */
class FrontendController {

	/**
	 * Registriert alle Frontend-Hooks.
	 */
	public function init(): void {
		add_action( 'init', array( $this, 'register_shortcodes' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		add_action( 'admin_post_fsnw_km_save_apartment', array( $this, 'handle_save_apartment' ) );
		add_action( 'admin_post_fsnw_km_save_bundle', array( $this, 'handle_save_bundle' ) );
		add_action( 'admin_post_fsnw_km_bundle_status', array( $this, 'handle_bundle_status' ) );
		add_action( 'admin_post_fsnw_km_bundle_clone', array( $this, 'handle_bundle_clone' ) );
		add_action( 'admin_post_fsnw_km_issue_start', array( $this, 'handle_issue_start' ) );
		add_action( 'admin_post_fsnw_km_issue_abort', array( $this, 'handle_issue_abort' ) );
		add_action( 'admin_post_fsnw_km_issue_return', array( $this, 'handle_issue_return' ) );
		add_action( 'admin_post_fsnw_km_issue_lost', array( $this, 'handle_issue_lost' ) );
		add_action( 'admin_post_fsnw_km_issue_handover', array( $this, 'handle_issue_handover' ) );
	}

	/**
	 * Registriert die Shortcodes.
	 */
	public function register_shortcodes(): void {
		add_shortcode( 'wp_fsnw_key_manage', array( $this, 'render_manage_shortcode' ) );
		add_shortcode( 'wp_fsnw_key_dispatch', array( $this, 'render_dispatch_shortcode' ) );
		add_shortcode( 'wp_fsnw_key_list', array( $this, 'render_list_shortcode' ) );
	}

	/**
	 * [wp_fsnw_key_list] - Lese-Übersicht für alle Mitarbeiter: welche Bunde
	 * gibt es je Wohnung und wie viele hängen im Schrank. Zeigt nur die
	 * Bund-Kennung vor dem ersten Minus (der Rest ist für die Übersicht
	 * nicht relevant). Zugriff: Login genügt, keine Verwaltungs-Capability.
	 */
	public function render_list_shortcode(): string {
		if ( ! is_user_logged_in() ) {
			return '<p>' . sprintf(
				/* translators: %s: Login-URL. */
				wp_kses_post( __( 'Bitte <a href="%s">anmelden</a>, um die Schlüsselliste zu sehen.', 'fsnw-key-management' ) ),
				esc_url( wp_login_url( home_url( add_query_arg( null, null ) ) ) )
			) . '</p>';
		}

		$apartment_service = new ApartmentService();
		$bundle_service    = new BundleService();
		$inventory_service = new InventoryService();

		$apartments = $apartment_service->list( true );
		$inventory  = $inventory_service->overview( $apartments );

		$bundles_by_apartment = array();
		foreach ( $apartments as $apartment ) {
			$bundles_by_apartment[ (int) $apartment['id'] ] = $bundle_service->list_by_apartment( (int) $apartment['id'] );
		}

		ob_start();
		include FSNW_KEY_MANAGEMENT_PLUGIN_DIR . 'templates/frontend/list-page.php';

		return (string) ob_get_clean();
	}

	/**
	 * [wp_fsnw_key_dispatch] - Ausgabe-Arbeitsplatz (Ausgabe, Abbruch, Rückgabe, Verlust).
	 */
	public function render_dispatch_shortcode(): string {
		$gate = $this->access_gate();

		if ( null !== $gate ) {
			return $gate;
		}

		$apartment_service = new ApartmentService();
		$bundle_service    = new BundleService();
		$inventory_service = new InventoryService();
		$issue_service     = new IssueService();

		$apartments = $apartment_service->list( true );
		$inventory  = $inventory_service->overview( $apartments );

		// Verfügbare Bunde je (aktiver) Wohnung für das Ausgabe-Formular.
		$available_by_apartment = array();
		foreach ( $apartments as $apartment ) {
			$available = array_filter(
				$bundle_service->list_by_apartment( (int) $apartment['id'] ),
				static function ( array $bundle ): bool {
					return BundleService::STATUS_AVAILABLE === $bundle['status'];
				}
			);

			if ( ! empty( $available ) ) {
				$available_by_apartment[ (int) $apartment['id'] ] = array_values( $available );
			}
		}

		$awaiting = $issue_service->list_enriched( IssueService::STATUS_AWAITING_SIGNATURE );
		$out      = array_values(
			array_filter(
				$issue_service->list_enriched( IssueService::STATUS_ISSUED ),
				static function ( array $issue ): bool {
					// Einzug-Ausgaben (Bund handed_over) sind abgeschlossen und
					// erscheinen nicht mehr in der "Draußen"-Liste.
					return BundleService::STATUS_ISSUED === $issue['bundle_status'];
				}
			)
		);

		$employees = get_users(
			array(
				'orderby' => 'display_name',
				'order'   => 'ASC',
				'fields'  => array( 'ID', 'display_name' ),
			)
		);

		$replacement_needed = array();
		foreach ( $apartments as $apartment ) {
			if ( ! empty( $inventory[ (int) $apartment['id'] ]['needs_replacement'] ) ) {
				$replacement_needed[] = $apartment;
			}
		}

		$type_labels = IssueService::type_labels();
		$current_url = home_url( add_query_arg( null, null ) );

		ob_start();
		include FSNW_KEY_MANAGEMENT_PLUGIN_DIR . 'templates/frontend/dispatch-page.php';

		return (string) ob_get_clean();
	}

	/**
	 * [wp_fsnw_key_manage] - Verwaltungs-Seite (Wohnungen, Bunde, Inventar, Historie).
	 */
	public function render_manage_shortcode(): string {
		$gate = $this->access_gate();

		if ( null !== $gate ) {
			return $gate;
		}

		$apartment_service = new ApartmentService();
		$bundle_service    = new BundleService();
		$inventory_service = new InventoryService();

		$apartments = $apartment_service->list();
		$inventory  = $inventory_service->overview( $apartments );

		$bundles_by_apartment = array();
		foreach ( $apartments as $apartment ) {
			$bundles_by_apartment[ (int) $apartment['id'] ] = $bundle_service->list_by_apartment( (int) $apartment['id'] );
		}

		// Vorbelegung für Bearbeiten-/Historie-Modus (per GET-Parameter angefordert).
		$edit_apartment    = null;
		$edit_bundle       = null;
		$history           = null;
		$history_bundle    = null;
		$history_apartment = null;

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Nur lesende Vorbelegung, keine Zustandsänderung.
		if ( ! empty( $_GET['fsnw_edit_apartment'] ) ) {
			$edit_apartment = $apartment_service->find( absint( $_GET['fsnw_edit_apartment'] ) );
		}

		if ( ! empty( $_GET['fsnw_edit_bundle'] ) ) {
			$edit_bundle = $bundle_service->find( absint( $_GET['fsnw_edit_bundle'] ) );
		}

		if ( ! empty( $_GET['fsnw_history'] ) ) {
			$history_bundle = $bundle_service->find( absint( $_GET['fsnw_history'] ) );
			$history        = null === $history_bundle ? null : ( new LogService() )->list_by_bundle( (int) $history_bundle['id'] );
		}

		if ( ! empty( $_GET['fsnw_apartment_history'] ) ) {
			$history_apartment = $apartment_service->find( absint( $_GET['fsnw_apartment_history'] ) );

			if ( null !== $history_apartment ) {
				$apartment_bundles = $bundles_by_apartment[ (int) $history_apartment['id'] ] ?? array();
				$history           = ( new LogService() )->list_by_bundles( array_map( 'intval', wp_list_pluck( $apartment_bundles, 'id' ) ) );
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$current_url = home_url( add_query_arg( null, null ) );

		ob_start();
		unset( $apartment_bundles );

		include FSNW_KEY_MANAGEMENT_PLUGIN_DIR . 'templates/frontend/manage-page.php';

		return (string) ob_get_clean();
	}

	/**
	 * Lädt CSS/JS nur auf Seiten mit einem der Plugin-Shortcodes.
	 */
	public function enqueue_assets(): void {
		$post = get_post();

		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		if (
			! has_shortcode( $post->post_content, 'wp_fsnw_key_manage' )
			&& ! has_shortcode( $post->post_content, 'wp_fsnw_key_dispatch' )
			&& ! has_shortcode( $post->post_content, 'wp_fsnw_key_list' )
		) {
			return;
		}

		wp_enqueue_style( 'fsnw-key-management-tokens', FSNW_KEY_MANAGEMENT_PLUGIN_URL . 'assets/css/tokens.css', array(), FSNW_KEY_MANAGEMENT_VERSION );
		wp_enqueue_style( 'fsnw-key-management-base', FSNW_KEY_MANAGEMENT_PLUGIN_URL . 'assets/css/base.css', array( 'fsnw-key-management-tokens' ), FSNW_KEY_MANAGEMENT_VERSION );
		wp_enqueue_style( 'fsnw-key-management', FSNW_KEY_MANAGEMENT_PLUGIN_URL . 'assets/css/key-management.css', array( 'fsnw-key-management-tokens', 'fsnw-key-management-base' ), FSNW_KEY_MANAGEMENT_VERSION );
		wp_enqueue_script( 'fsnw-key-management', FSNW_KEY_MANAGEMENT_PLUGIN_URL . 'assets/js/key-management.js', array(), FSNW_KEY_MANAGEMENT_VERSION, true );
		wp_localize_script(
			'fsnw-key-management',
			'fsnwKeyManagement',
			array(
				'watchSignalUrl' => rest_url( 'fsnw-key-management/v1/dispatch/watch-signal' ),
				'nonce'          => wp_create_nonce( 'wp_rest' ),
			)
		);
	}

	/**
	 * Speichert eine Wohnung (anlegen oder bearbeiten).
	 */
	public function handle_save_apartment(): void {
		$this->verify_request( 'fsnw_km_save_apartment' );

		$apartment_id = absint( $_POST['apartment_id'] ?? 0 );
		$address      = array(
			'street'       => sanitize_text_field( wp_unslash( $_POST['street'] ?? '' ) ),
			'house_number' => sanitize_text_field( wp_unslash( $_POST['house_number'] ?? '' ) ),
			'zip'          => sanitize_text_field( wp_unslash( $_POST['zip'] ?? '' ) ),
			'city'         => sanitize_text_field( wp_unslash( $_POST['city'] ?? '' ) ),
			'unit'         => sanitize_text_field( wp_unslash( $_POST['unit'] ?? '' ) ),
		);
		$notes        = sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) );
		$status       = sanitize_key( $_POST['status'] ?? ApartmentService::STATUS_ACTIVE );

		try {
			$service = new ApartmentService();

			if ( $apartment_id > 0 ) {
				$service->update( $apartment_id, $address, $notes, $status );
			} else {
				$service->create( $address, $notes );
			}
		} catch ( \InvalidArgumentException $exception ) {
			$this->redirect_back( array( 'fsnw_error' => $exception->getMessage() ) );
		}

		$this->redirect_back( array( 'fsnw_saved' => '1' ) );
	}

	/**
	 * Speichert einen Schlüsselbund (anlegen oder bearbeiten).
	 */
	public function handle_save_bundle(): void {
		$this->verify_request( 'fsnw_km_save_bundle' );

		$bundle_id    = absint( $_POST['bundle_id'] ?? 0 );
		$apartment_id = absint( $_POST['apartment_id'] ?? 0 );
		$label        = sanitize_text_field( wp_unslash( $_POST['label'] ?? '' ) );
		$notes        = sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) );
		$keys         = array_map( 'sanitize_text_field', wp_unslash( (array) ( $_POST['keys'] ?? array() ) ) );

		try {
			$service = new BundleService();

			if ( $bundle_id > 0 ) {
				$service->update( $bundle_id, $label, $keys, $notes );
			} else {
				if ( null === ( new ApartmentService() )->find( $apartment_id ) ) {
					throw new \InvalidArgumentException( esc_html__( 'Bitte eine Wohnung auswählen.', 'fsnw-key-management' ) );
				}

				$service->create( $apartment_id, $label, $keys, $notes );
			}
		} catch ( \InvalidArgumentException $exception ) {
			$this->redirect_back( array( 'fsnw_error' => $exception->getMessage() ) );
		}

		$this->redirect_back( array( 'fsnw_saved' => '1' ) );
	}

	/**
	 * Wechselt den Status eines Bundes (verloren / ausgemustert / reaktiviert).
	 */
	public function handle_bundle_status(): void {
		$this->verify_request( 'fsnw_km_bundle_status' );

		$bundle_id = absint( $_POST['bundle_id'] ?? 0 );
		$target    = sanitize_key( $_POST['target_status'] ?? '' );

		$actions = array(
			BundleService::STATUS_LOST      => 'bundle_lost',
			BundleService::STATUS_RETIRED   => 'bundle_retired',
			BundleService::STATUS_AVAILABLE => 'bundle_reactivated',
		);

		if ( ! isset( $actions[ $target ] ) ) {
			$this->redirect_back( array( 'fsnw_error' => __( 'Unbekannter Statuswechsel.', 'fsnw-key-management' ) ) );
		}

		try {
			( new BundleService() )->change_status( $bundle_id, $target, $actions[ $target ] );
		} catch ( \InvalidArgumentException $exception ) {
			$this->redirect_back( array( 'fsnw_error' => $exception->getMessage() ) );
		}

		$this->redirect_back( array( 'fsnw_saved' => '1' ) );
	}

	/**
	 * Dupliziert einen Schlüsselbund (identische Kopie als neuer verfügbarer Bund).
	 */
	public function handle_bundle_clone(): void {
		$this->verify_request( 'fsnw_km_bundle_clone' );

		try {
			( new BundleService() )->duplicate( absint( $_POST['bundle_id'] ?? 0 ) );
		} catch ( \InvalidArgumentException $exception ) {
			$this->redirect_back( array( 'fsnw_error' => $exception->getMessage() ) );
		}

		$this->redirect_back( array( 'fsnw_saved' => '1' ) );
	}

	/**
	 * Startet eine Ausgabe (inkl. Kiosk-Signatur-Anforderung).
	 */
	public function handle_issue_start(): void {
		$this->verify_request( 'fsnw_km_issue_start' );

		try {
			( new IssueService() )->start(
				absint( $_POST['bundle_id'] ?? 0 ),
				absint( $_POST['issued_to_user_id'] ?? 0 ),
				sanitize_key( $_POST['issue_type'] ?? '' ),
				sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) ),
				! empty( $_POST['confirm_last'] )
			);
		} catch ( \InvalidArgumentException $exception ) {
			$this->redirect_back( array( 'fsnw_error' => $exception->getMessage() ) );
		}

		$this->redirect_back( array( 'fsnw_saved' => '1' ) );
	}

	/**
	 * Bricht eine Ausgabe ab, solange noch nicht unterschrieben wurde.
	 */
	public function handle_issue_abort(): void {
		$this->verify_request( 'fsnw_km_issue_abort' );
		$this->run_issue_action( 'abort' );
	}

	/**
	 * Vermerkt die Rückgabe eines Bundes.
	 */
	public function handle_issue_return(): void {
		$this->verify_request( 'fsnw_km_issue_return' );
		$this->run_issue_action( 'mark_returned' );
	}

	/**
	 * Meldet einen ausgegebenen Bund als verloren.
	 */
	public function handle_issue_lost(): void {
		$this->verify_request( 'fsnw_km_issue_lost' );
		$this->run_issue_action( 'mark_lost' );
	}

	/**
	 * Übergibt einen unterwegs befindlichen Bund dauerhaft an den Klienten
	 * (Verlust-Nachmeldung, siehe IssueService::hand_over_to_client()).
	 */
	public function handle_issue_handover(): void {
		$this->verify_request( 'fsnw_km_issue_handover' );
		$this->run_issue_action( 'hand_over_to_client' );
	}

	/**
	 * Führt eine einfache Issue-Aktion (abort/mark_returned/mark_lost) aus.
	 *
	 * @param string $method Methodenname auf dem IssueService.
	 */
	private function run_issue_action( string $method ): void {
		try {
			( new IssueService() )->{$method}( absint( $_POST['issue_id'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce wurde in verify_request() geprüft.
		} catch ( \InvalidArgumentException $exception ) {
			$this->redirect_back( array( 'fsnw_error' => $exception->getMessage() ) );
		}

		$this->redirect_back( array( 'fsnw_saved' => '1' ) );
	}

	/**
	 * Gemeinsames Zugriffs-Gate der Frontend-Seiten: Login + Capability.
	 *
	 * @return string|null Hinweis-Markup bei fehlendem Zugriff, sonst null.
	 */
	private function access_gate(): ?string {
		if ( ! is_user_logged_in() ) {
			return '<p>' . sprintf(
				/* translators: %s: Login-URL. */
				wp_kses_post( __( 'Bitte <a href="%s">anmelden</a>, um die Schlüsselverwaltung zu nutzen.', 'fsnw-key-management' ) ),
				esc_url( wp_login_url( home_url( add_query_arg( null, null ) ) ) )
			) . '</p>';
		}

		if ( ! current_user_can( Capabilities::MANAGE_KEYS ) ) {
			return '<p>' . esc_html__( 'Keine Berechtigung für die Schlüsselverwaltung.', 'fsnw-key-management' ) . '</p>';
		}

		return null;
	}

	/**
	 * Prüft Capability und Nonce eines admin_post-Requests.
	 *
	 * @param string $action Nonce-Action.
	 */
	private function verify_request( string $action ): void {
		if ( ! current_user_can( Capabilities::MANAGE_KEYS ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'fsnw-key-management' ) );
		}

		check_admin_referer( $action );
	}

	/**
	 * Leitet zurück zur aufrufenden Seite (redirect_url aus dem Formular) und
	 * hängt Statusparameter an. Beendet die Ausführung.
	 *
	 * @param array<string, string> $args Statusparameter (fsnw_saved/fsnw_error).
	 */
	private function redirect_back( array $args ): void {
		$redirect = isset( $_POST['redirect_url'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_url'] ) ) : home_url(); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce wurde in verify_request() geprüft.

		// Alte Statusparameter entfernen, damit Meldungen nicht doppelt erscheinen.
		$redirect = remove_query_arg( array( 'fsnw_saved', 'fsnw_error' ), $redirect );

		wp_safe_redirect( add_query_arg( array_map( 'rawurlencode', $args ), $redirect ) );
		exit;
	}
}
