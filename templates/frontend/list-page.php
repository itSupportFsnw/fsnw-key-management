<?php
/**
 * Frontend-Template für [wp_fsnw_key_list] - Lese-Übersicht für alle Mitarbeiter.
 *
 * Zeigt je Wohnung die vorhandenen Schlüsselbunde mit ihrer Kennung
 * (nur der Teil vor dem ersten Minus - der Rest ist für die Übersicht
 * nicht relevant) und wie viele davon aktuell im Schrank hängen. Damit
 * können Mitarbeiter der Ausgabe sagen, welchen Bund sie brauchen.
 * Reine Lese-Ansicht, Zugriff: Login genügt.
 *
 * @package FsnwKeyManagement
 *
 * @var array<int, array<string, mixed>>             $apartments
 * @var array<int, array<string, mixed>>             $inventory            apartment_id => Zählwerte
 * @var array<int, array<int, array<string, mixed>>> $bundles_by_apartment apartment_id => Bunde
 */

use FsnwKeyManagement\Includes\Services\BundleService;

defined( 'ABSPATH' ) || exit;
?>
<div class="fsnw-key-management fsnw-key-management--list">
	<h1><?php esc_html_e( 'Schlüsselliste', 'fsnw-key-management' ); ?></h1>
	<p class="fsnw-km-muted"><?php esc_html_e( 'Übersicht aller Wohnungsschlüssel. Sag der Ausgabe einfach die Adresse und die Schlüssel-Nr.', 'fsnw-key-management' ); ?></p>

	<div class="fsnw-card fsnw-km-form-card fsnw-fade-in">
		<label for="fsnw-key-list-search"><?php esc_html_e( 'Suchen', 'fsnw-key-management' ); ?></label>
		<input type="search" id="fsnw-key-list-search" autocomplete="off" placeholder="<?php esc_attr_e( 'Straße, Ort oder Schlüssel-Nr. tippen …', 'fsnw-key-management' ); ?>">
	</div>

	<div class="fsnw-card fsnw-fade-in">
		<table class="fsnw-table" id="fsnw-key-list-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Wohnung', 'fsnw-key-management' ); ?></th>
					<th><?php esc_html_e( 'Schlüssel-Nr.', 'fsnw-key-management' ); ?></th>
					<th><?php esc_html_e( 'Im Schrank', 'fsnw-key-management' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $apartments as $apartment ) : ?>
					<?php
					$apartment_id = (int) $apartment['id'];
					$bundles      = $bundles_by_apartment[ $apartment_id ] ?? array();

					// Kennungen: Bund-Bezeichnung bis zum ersten Minus, dedupliziert.
					// Verlorene/ausgemusterte Bunde zählen nicht zur Übersicht.
					$base_ids = array();
					foreach ( $bundles as $bundle ) {
						if ( in_array( $bundle['status'], array( BundleService::STATUS_LOST, BundleService::STATUS_RETIRED ), true ) ) {
							continue;
						}

						$base_id = trim( explode( '-', (string) $bundle['label'] )[0] );

						if ( '' !== $base_id && ! in_array( $base_id, $base_ids, true ) ) {
							$base_ids[] = $base_id;
						}
					}

					if ( empty( $base_ids ) ) {
						continue;
					}

					$available = (int) $inventory[ $apartment_id ]['available'];
					$existing  = (int) $inventory[ $apartment_id ]['existing'];
					?>
					<tr>
						<td><?php echo esc_html( $apartment['label'] ); ?></td>
						<td><strong><?php echo esc_html( implode( ', ', $base_ids ) ); ?></strong></td>
						<td>
							<span class="fsnw-badge fsnw-badge--<?php echo esc_attr( $available > 0 ? 'available' : 'overdue' ); ?>">
								<?php
								printf(
									/* translators: 1: verfügbare Bunde, 2: vorhandene Bunde. */
									esc_html__( '%1$d von %2$d', 'fsnw-key-management' ),
									$available,
									$existing
								);
								?>
							</span>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<p id="fsnw-key-list-empty" class="fsnw-km-muted fsnw-hidden"><?php esc_html_e( 'Keine Treffer.', 'fsnw-key-management' ); ?></p>
	</div>
</div>
