<?php
/**
 * Integration mit dem Plugin "FSNW Signature Kiosk" (Ausgabe-Unterschriften).
 *
 * @package FsnwKeyManagement\Includes\Integrations
 */

namespace FsnwKeyManagement\Includes\Integrations;

use FsnwKeyManagement\Includes\Services\IssueService;
use FsnwKeyManagement\Includes\Support\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * Verbindet den Ausgabe-Flow mit dem generischen Signatur-Kiosk:
 * empfängt Abschluss-Hooks, schaltet den Bild-Abruf für die Schlüssel-Rolle
 * frei und warnt im Admin, wenn das Kiosk-Plugin fehlt (harte Abhängigkeit
 * für die Ausgabe).
 */
class SignatureKioskIntegration {

	/**
	 * Registriert alle Integrations-Hooks.
	 */
	public function init(): void {
		add_action( 'fsnw_signature_completed', array( $this, 'handle_completed' ), 10, 4 );
		add_filter( 'fsnw_signature_kiosk_can_view_image', array( $this, 'allow_keys_capability' ), 10, 3 );
		add_action( 'admin_notices', array( $this, 'maybe_render_missing_notice' ) );
	}

	/**
	 * Verarbeitet den Abschluss einer Kiosk-Anforderung dieses Plugins.
	 *
	 * @param int    $request_id   Anforderungs-ID im Kiosk-Plugin (ungenutzt).
	 * @param int    $signature_id Signatur-ID im Kiosk-Plugin.
	 * @param string $source       Slug des anfordernden Plugins.
	 * @param string $reference    Externe Referenz (hier: Ausgabe-ID).
	 */
	public function handle_completed( $request_id, $signature_id, $source, $reference ): void {
		if ( IssueService::KIOSK_SOURCE !== $source ) {
			return;
		}

		( new IssueService() )->handle_kiosk_completion( (int) $reference, (int) $signature_id );
	}

	/**
	 * Schaltet den Abruf der Unterschrift-Bilder dieses Plugins für die
	 * Schlüssel-Capability frei (Kiosk-Standard wäre nur manage_options).
	 *
	 * @param bool   $allowed      Bisheriges Ergebnis der Berechtigungsprüfung.
	 * @param int    $signature_id Signatur-ID im Kiosk-Plugin (ungenutzt).
	 * @param string $source       Slug des anfordernden Plugins.
	 */
	public function allow_keys_capability( $allowed, $signature_id, $source ): bool {
		if ( IssueService::KIOSK_SOURCE === $source && current_user_can( Capabilities::MANAGE_KEYS ) ) {
			return true;
		}

		return (bool) $allowed;
	}

	/**
	 * Zeigt einen Admin-Hinweis, solange das Kiosk-Plugin fehlt.
	 */
	public function maybe_render_missing_notice(): void {
		if ( class_exists( '\FsnwSignatureKiosk\Includes\Api' ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__( 'FSNW Key Management benötigt das Plugin "FSNW Signature Kiosk" für die Ausgabe-Unterschrift. Bitte installieren und aktivieren – bis dahin ist keine Schlüsselausgabe möglich.', 'fsnw-key-management' )
		);
	}
}
