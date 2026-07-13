<?php
/**
 * Capability-Konstanten und -Registrierung.
 *
 * @package FsnwKeyManagement\Includes\Support
 */

namespace FsnwKeyManagement\Includes\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Definiert die Custom Capability des Plugins. Es werden keine eigenen Rollen
 * angelegt - die verwaltende Capability geht bei Aktivierung ausschließlich an
 * die Rolle "administrator" und kann danach über die bestehende WordPress-
 * Rollenverwaltung weiteren Rollen zugewiesen werden.
 */
class Capabilities {

	public const MANAGE_KEYS = 'fsnw_manage_keys';

	/**
	 * Alle Capabilities, die einer Rolle explizit zugewiesen werden können.
	 *
	 * @return string[]
	 */
	public static function assignable(): array {
		return array(
			self::MANAGE_KEYS,
		);
	}

	/**
	 * Vergibt bei Aktivierung alle verwaltenden Capabilities an die Rolle "administrator".
	 */
	public static function assign_defaults(): void {
		$administrator = get_role( 'administrator' );

		if ( null === $administrator ) {
			return;
		}

		foreach ( self::assignable() as $capability ) {
			$administrator->add_cap( $capability );
		}
	}
}
