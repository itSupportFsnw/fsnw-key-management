<?php
/**
 * Admin-Controller: Import-Werkzeug für Wohnungen und Schlüsselbunde.
 *
 * @package FsnwKeyManagement\Admin
 */

namespace FsnwKeyManagement\Admin;

use FsnwKeyManagement\Includes\Services\ApartmentService;
use FsnwKeyManagement\Includes\Services\BundleService;
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
	 * Transient-Schlüssel (pro Nutzer) für das Import-Ergebnis.
	 */
	private const RESULT_TRANSIENT = 'fsnw_km_import_result_';

	/**
	 * Registriert alle Admin-Hooks.
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_post_fsnw_km_import', array( $this, 'handle_import' ) );
	}

	/**
	 * Registriert die Import-Seite unter Werkzeuge.
	 */
	public function register_menu(): void {
		add_management_page(
			__( 'Schlüssel-Import', 'fsnw-key-management' ),
			__( 'Schlüssel-Import', 'fsnw-key-management' ),
			Capabilities::MANAGE_KEYS,
			self::PAGE_SLUG,
			array( $this, 'render_import_page' )
		);
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
