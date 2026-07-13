<?php
/**
 * Uninstall-Routine.
 *
 * Wird ausschließlich beim expliziten Löschen des Plugins über den WordPress-Adminbereich
 * ausgeführt. Löscht bewusst KEINE Wohnungs-, Schlüssel- oder Ausgabe-Daten, da diese
 * als Nachweis (wer hatte wann welchen Schlüssel) dauerhaft aufbewahrt werden müssen.
 * Es werden nur Plugin-Optionen entfernt.
 *
 * @package FsnwKeyManagement
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'fsnw_key_management_db_version' );
