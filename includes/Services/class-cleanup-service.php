<?php
/**
 * Aufräum-Werkzeug: endgültiges Löschen von Test- und Altdaten.
 *
 * @package FsnwKeyManagement\Includes\Services
 */

namespace FsnwKeyManagement\Includes\Services;

use FsnwKeyManagement\Includes\Repositories\ApartmentRepository;
use FsnwKeyManagement\Includes\Repositories\BundleRepository;
use FsnwKeyManagement\Includes\Repositories\IssueRepository;
use FsnwKeyManagement\Includes\Repositories\LogRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Bewusst nur über das Admin-Werkzeug (manage_options) erreichbar - alle
 * Löschungen sind endgültig und umgehen die Nachweis-Aufbewahrung. Für den
 * Normalbetrieb gilt weiterhin: Daten werden nicht gelöscht.
 */
class CleanupService {

	/**
	 * Löscht eine Wohnung komplett: alle Bunde, Ausgaben und Historie-Einträge
	 * (z. B. Reste aus der Testphase).
	 *
	 * @param int $apartment_id Wohnungs-ID.
	 * @return array{bundles: int, issues: int, logs: int} Anzahl gelöschter Datensätze.
	 * @throws \InvalidArgumentException Wenn die Wohnung nicht existiert.
	 */
	public function delete_apartment( int $apartment_id ): array {
		$apartment_repository = new ApartmentRepository();

		if ( null === $apartment_repository->find( $apartment_id ) ) {
			throw new \InvalidArgumentException( esc_html__( 'Wohnung nicht gefunden.', 'fsnw-key-management' ) );
		}

		$bundle_repository = new BundleRepository();
		$bundle_ids        = $bundle_repository->ids_by_apartment( $apartment_id );

		$result = array(
			'bundles' => 0,
			'issues'  => 0,
			'logs'    => 0,
		);

		$result['issues']  = ( new IssueRepository() )->delete_by_bundles( $bundle_ids );
		$result['logs']    = ( new LogRepository() )->delete_by_bundles( $bundle_ids );
		$result['bundles'] = $bundle_repository->delete_by_apartment( $apartment_id );

		$apartment_repository->delete( $apartment_id );

		return $result;
	}

	/**
	 * Löscht Altdaten: abgeschlossene Ausgaben (zurückgegeben/abgebrochen) und
	 * Historie-Einträge, die älter als die angegebene Anzahl Tage sind.
	 * Wohnungen, Bunde sowie Verlust-/Einzug-Ausgaben bleiben als Nachweis erhalten.
	 *
	 * @param int $days Mindestalter in Tagen (min. 30, um Versehen zu vermeiden).
	 * @return array{issues: int, logs: int} Anzahl gelöschter Datensätze.
	 */
	public function purge_old( int $days ): array {
		$days   = max( 30, $days );
		$cutoff = gmdate( 'Y-m-d H:i:s', strtotime( current_time( 'mysql' ) ) - $days * DAY_IN_SECONDS );

		return array(
			'issues' => ( new IssueRepository() )->delete_finished_older_than( $cutoff ),
			'logs'   => ( new LogRepository() )->delete_older_than( $cutoff ),
		);
	}
}
