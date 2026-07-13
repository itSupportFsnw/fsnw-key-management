<?php
/**
 * Plugin Name:       FSNW Key Management
 * Plugin URI:        https://github.com/itSupportFsnw/fsnw-key-management
 * Description:       Schlüsselverwaltung für Klienten-Wohnungen: Inventar der Schlüsselbunde im zentralen Schlüsselkasten, Ausgabe an Mitarbeiter mit Tablet-Unterschrift (via FSNW Signature Kiosk), Rückgabe- und Verlust-Verwaltung.
 * Version:           0.3.0
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Author:            freestyle Jugendhilfe gGmbh
 * Author URI:        https://www.freestyle-jugendhilfe.de
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       fsnw-key-management
 * Domain Path:       /languages
 *
 * @package FsnwKeyManagement
 */

defined( 'ABSPATH' ) || exit;

define( 'FSNW_KEY_MANAGEMENT_VERSION', '0.3.0' );
define( 'FSNW_KEY_MANAGEMENT_DB_VERSION', '0.1.0' );
define( 'FSNW_KEY_MANAGEMENT_PLUGIN_FILE', __FILE__ );
define( 'FSNW_KEY_MANAGEMENT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FSNW_KEY_MANAGEMENT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

$fsnw_key_management_autoloader = FSNW_KEY_MANAGEMENT_PLUGIN_DIR . 'vendor/autoload.php';

if ( ! file_exists( $fsnw_key_management_autoloader ) ) {
	add_action(
		'admin_notices',
		function () {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html__( 'FSNW Key Management: Composer-Abhängigkeiten fehlen. Bitte "composer install" im Plugin-Verzeichnis ausführen.', 'fsnw-key-management' )
			);
		}
	);
	return;
}

require_once $fsnw_key_management_autoloader;

register_activation_hook( __FILE__, array( \FsnwKeyManagement\Includes\Activator::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \FsnwKeyManagement\Includes\Deactivator::class, 'deactivate' ) );

\FsnwKeyManagement\Includes\Plugin::instance()->run();
