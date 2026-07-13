<?php
/**
 * Admin-Controller: Import-Werkzeug für Wohnungen und Schlüsselbunde.
 *
 * @package FsnwKeyManagement\Admin
 */

namespace FsnwKeyManagement\Admin;

use FsnwKeyManagement\Includes\Services\ApartmentService;
use FsnwKeyManagement\Includes\Services\BundleService;
use FsnwKeyManagement\Includes\Services\CleanupService;
use FsnwKeyManagement\Includes\Support\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * Stellt unter Werkzeuge → Schlüssel-Import einen Massen-Import bereit:
 * eine Zeile pro Wohnung, Wohnung + Schlüsselbunde werden in einem Rutsch
 * angelegt (die Bunde einer Wohnung sind untereinander identisch).
 */
class AdminController {

	/**
	 * Slug der Import-Seite.
	 */
	private const PAGE_SLUG = 'fsnw-km-import';

	/**
	 * Slug der Aufräum-Seite.
	 */
	private const CLEANUP_SLUG = 'fsnw-km-cleanup';

	/**
	 * Transient-Schlüssel (pro Nutzer) für das Import-Ergebnis.
	 */
	private const RESULT_TRANSIENT = 'fsnw_km_import_result_';

	/**
	 * Registriert alle Admin-Hooks.
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_post_fsnw_km_import', array( $this, 'handle_import' ) );
		add_action( 'admin_post_fsnw_km_cleanup_apartment', array( $this, 'handle_cleanup_apartment' ) );
		add_action( 'admin_post_fsnw_km_cleanup_old', array( $this, 'handle_cleanup_old' ) );
	}

	/**
	 * Registriert die Werkzeug-Seiten unter Werkzeuge.
	 */
	public function register_menu(): void {
		add_management_page(
			__( 'Schlüssel-Import', 'fsnw-key-management' ),
			__( 'Schlüssel-Import', 'fsnw-key-management' ),
			Capabilities::MANAGE_KEYS,
			self::PAGE_SLUG,
			array( $this, 'render_import_page' )
		);

		// Endgültiges Löschen bewusst nur für Administratoren.
		add_management_page(
			__( 'Schlüssel-Daten aufräumen', 'fsnw-key-management' ),
			__( 'Schlüssel-Daten aufräumen', 'fsnw-key-management' ),
			'manage_options',
			self::CLEANUP_SLUG,
			array( $this, 'render_cleanup_page' )
		);
	}

	/**
	 * Rendert die Aufräum-Seite (inkl. Ergebnis der letzten Aktion).
	 */
	public function render_cleanup_page(): void {
		$transient_key = self::RESULT_TRANSIENT . 'cleanup_' . get_current_user_id();
		$result        = get_transient( $transient_key );

		if ( false !== $result ) {
			delete_transient( $transient_key );
		} else {
			$result = null;
		}

		$apartments = ( new ApartmentService() )->list();

		include FSNW_KEY_MANAGEMENT_PLUGIN_DIR . 'templates/admin/cleanup.php';
	}

	/**
	 * Löscht eine Wohnung komplett (Testdaten).
	 */
	public function handle_cleanup_apartment(): void {
		$this->verify_cleanup_request( 'fsnw_km_cleanup_apartment' );

		$apartment_id = absint( $_POST['apartment_id'] ?? 0 );

		try {
			$counts = ( new CleanupService() )->delete_apartment( $apartment_id );

			$message = sprintf(
				/* translators: 1: Anzahl Bunde, 2: Anzahl Ausgaben, 3: Anzahl Historie-Einträge. */
				__( 'Wohnung gelöscht (inkl. %1$d Bunde, %2$d Ausgaben, %3$d Historie-Einträge).', 'fsnw-key-management' ),
				$counts['bundles'],
				$counts['issues'],
				$counts['logs']
			);
			$this->finish_cleanup( array( 'success' => $message ) );
		} catch ( \InvalidArgumentException $exception ) {
			$this->finish_cleanup( array( 'error' => $exception->getMessage() ) );
		}
	}

	/**
	 * Löscht Altdaten (abgeschlossene Ausgaben + Historie älter als X Tage).
	 */
	public function handle_cleanup_old(): void {
		$this->verify_cleanup_request( 'fsnw_km_cleanup_old' );

		$days   = absint( $_POST['days'] ?? 365 );
		$counts = ( new CleanupService() )->purge_old( $days );

		$this->finish_cleanup(
			array(
				'success' => sprintf(
					/* translators: 1: Anzahl Ausgaben, 2: Anzahl Historie-Einträge, 3: Anzahl Tage. */
					__( '%1$d abgeschlossene Ausgaben und %2$d Historie-Einträge älter als %3$d Tage gelöscht.', 'fsnw-key-management' ),
					$counts['issues'],
					$counts['logs'],
					max( 30, $days )
				),
			)
		);
	}

	/**
	 * Prüft Berechtigung, Nonce und Bestätigungs-Checkbox einer Aufräum-Aktion.
	 *
	 * @param string $action Nonce-Action.
	 */
	private function verify_cleanup_request( string $action ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'fsnw-key-management' ) );
		}

		check_admin_referer( $action );

		if ( empty( $_POST['confirm'] ) ) {
			$this->finish_cleanup( array( 'error' => __( 'Bitte die Bestätigungs-Checkbox setzen.', 'fsnw-key-management' ) ) );
		}
	}

	/**
	 * Speichert das Aufräum-Ergebnis und leitet zurück zur Aufräum-Seite.
	 *
	 * @param array<string, string> $result Ergebnis (success oder error).
	 */
	private function finish_cleanup( array $result ): void {
		set_transient( self::RESULT_TRANSIENT . 'cleanup_' . get_current_user_id(), $result, MINUTE_IN_SECONDS );

		wp_safe_redirect( add_query_arg( 'page', self::CLEANUP_SLUG, admin_url( 'tools.php' ) ) );
		exit;
	}

	/**
	 * Rendert die Import-Seite (inkl. Ergebnis des letzten Imports).
	 */
	public function render_import_page(): void {
		$transient_key = self::RESULT_TRANSIENT . get_current_user_id();
		$result        = get_transient( $transient_key );

		if ( false !== $result ) {
			delete_transient( $transient_key );
		} else {
			$result = null;
		}

		include FSNW_KEY_MANAGEMENT_PLUGIN_DIR . 'templates/admin/import.php';
	}

	/**
	 * Verarbeitet den Import.
	 *
	 * Format je Zeile (Semikolon-getrennt):
	 * Straße;Hausnummer;PLZ;Ort[;Wohneinheit[;AnzahlBunde[;SchlüsselProBund]]]
	 * Standardwerte: Wohneinheit leer, 2 Bunde, 3 Schlüssel je Bund.
	 */
	public function handle_import(): void {
		if ( ! current_user_can( Capabilities::MANAGE_KEYS ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'fsnw-key-management' ) );
		}

		check_admin_referer( 'fsnw_km_import' );

		$raw   = sanitize_textarea_field( wp_unslash( $_POST['import_data'] ?? '' ) );
		$lines = preg_split( '/\r\n|\r|\n/', $raw );

		$apartment_service = new ApartmentService();
		$bundle_service    = new BundleService();

		$result = array(
			'apartments' => 0,
			'bundles'    => 0,
			'errors'     => array(),
		);

		foreach ( $lines as $line_number => $line ) {
			$line = trim( $line );

			if ( '' === $line ) {
				continue;
			}

			$columns = array_map( 'trim', explode( ';', $line ) );

			$street       = $columns[0] ?? '';
			$house_number = $columns[1] ?? '';
			$zip          = $columns[2] ?? '';
			$city         = $columns[3] ?? '';
			$unit         = $columns[4] ?? '';
			$bundle_count = max( 1, min( 10, absint( '' === ( $columns[5] ?? '' ) ? 2 : $columns[5] ) ) );
			$keys_count   = max( 1, min( 10, absint( '' === ( $columns[6] ?? '' ) ? 3 : $columns[6] ) ) );

			try {
				$apartment_id = $apartment_service->create(
					array(
						'street'       => $street,
						'house_number' => $house_number,
						'zip'          => $zip,
						'city'         => $city,
						'unit'         => $unit,
					)
				);

				$keys = array();
				for ( $k = 1; $k <= $keys_count; $k++ ) {
					$keys[] = sprintf(
						/* translators: %d: laufende Nummer des Schlüssels im Bund. */
						__( 'Schlüssel %d', 'fsnw-key-management' ),
						$k
					);
				}

				for ( $b = 1; $b <= $bundle_count; $b++ ) {
					$bundle_service->create(
						$apartment_id,
						sprintf(
							/* translators: %d: laufende Nummer des Bundes. */
							__( 'Bund %d', 'fsnw-key-management' ),
							$b
						),
						$keys
					);
					++$result['bundles'];
				}

				++$result['apartments'];
			} catch ( \InvalidArgumentException $exception ) {
				$result['errors'][] = sprintf(
					/* translators: 1: Zeilennummer, 2: Fehlermeldung. */
					__( 'Zeile %1$d: %2$s', 'fsnw-key-management' ),
					$line_number + 1,
					$exception->getMessage()
				);
			}
		}

		set_transient( self::RESULT_TRANSIENT . get_current_user_id(), $result, MINUTE_IN_SECONDS );

		wp_safe_redirect( add_query_arg( 'page', self::PAGE_SLUG, admin_url( 'tools.php' ) ) );
		exit;
	}
}
