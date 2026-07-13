<?php
/**
 * Deaktivierungsroutine.
 *
 * @package FsnwKeyManagement\Includes
 */

namespace FsnwKeyManagement\Includes;

defined( 'ABSPATH' ) || exit;

/**
 * Wird über register_deactivation_hook() ausgeführt.
 *
 * Löscht bewusst keine Daten, Tabellen oder Capabilities - dies geschieht
 * ausschließlich in uninstall.php nach expliziter Löschung durch den Administrator.
 */
class Deactivator {

	/**
	 * Führt die Deaktivierungsroutine aus.
	 */
	public static function deactivate(): void {
		flush_rewrite_rules();
	}
}
