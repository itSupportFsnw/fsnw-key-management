<?php
/**
 * REST-Endpunkt für das Live-Polling der Ausgabe-Seite.
 *
 * @package FsnwKeyManagement\Includes\Rest
 */

namespace FsnwKeyManagement\Includes\Rest;

use FsnwKeyManagement\Includes\Services\IssueService;
use FsnwKeyManagement\Includes\Support\Capabilities;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Liefert den Fingerabdruck der offenen Ausgaben, damit die Ausgabe-Seite
 * sich nach einer Tablet-Unterschrift selbst aktualisiert (gleiches Muster
 * wie das Watch-Signal in wp-fsnw-car-rent).
 */
class DispatchController {

	/**
	 * Registriert die Route im Namespace fsnw-key-management/v1.
	 */
	public function register_routes(): void {
		register_rest_route(
			'fsnw-key-management/v1',
			'/dispatch/watch-signal',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_watch_signal' ),
				'permission_callback' => static function (): bool {
					return current_user_can( Capabilities::MANAGE_KEYS );
				},
			)
		);
	}

	/**
	 * GET /dispatch/watch-signal
	 */
	public function get_watch_signal(): WP_REST_Response {
		nocache_headers();

		return new WP_REST_Response( array( 'signal' => ( new IssueService() )->get_watch_signal() ) );
	}
}
