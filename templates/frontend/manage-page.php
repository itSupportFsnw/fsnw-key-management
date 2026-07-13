<?php
/**
 * Frontend-Template für [wp_fsnw_key_manage] - Verwaltung der Schlüsselverwaltung.
 *
 * Wohnungen und Schlüsselbunde anlegen (Formular-Karten oben), Inventar-Übersicht
 * mit Bestandswarnungen, Verlust-/Ausmusterungs-Verwaltung. Bearbeiten und
 * Historie öffnen als Popup (Modal) über der Seite.
 * Zugriff wird im FrontendController über Login + fsnw_manage_keys geprüft.
 *
 * @package FsnwKeyManagement
 *
 * @var array<int, array<string, mixed>>             $apartments
 * @var array<int, array<string, mixed>>             $inventory            apartment_id => Zählwerte/Warn-Flags
 * @var array<int, array<int, array<string, mixed>>> $bundles_by_apartment apartment_id => Bunde
 * @var array<string, mixed>|null                    $edit_apartment
 * @var array<string, mixed>|null                    $edit_bundle
 * @var array<int, array<string, mixed>>|null        $history
 * @var array<string, mixed>|null                    $history_bundle
 * @var string                                       $current_url
 */

use FsnwKeyManagement\Includes\Services\BundleService;

defined( 'ABSPATH' ) || exit;

$status_labels = array(
	BundleService::STATUS_AVAILABLE   => __( 'Im Schrank', 'fsnw-key-management' ),
	BundleService::STATUS_ISSUED      => __( 'Ausgegeben', 'fsnw-key-management' ),
	BundleService::STATUS_HANDED_OVER => __( 'Dauerhaft vergeben', 'fsnw-key-management' ),
	BundleService::STATUS_LOST        => __( 'Verloren', 'fsnw-key-management' ),
	BundleService::STATUS_RETIRED     => __( 'Ausgemustert', 'fsnw-key-management' ),
);

$status_badges = array(
	BundleService::STATUS_AVAILABLE   => 'available',
	BundleService::STATUS_ISSUED      => 'booked',
	BundleService::STATUS_HANDED_OVER => 'inactive',
	BundleService::STATUS_LOST        => 'overdue',
	BundleService::STATUS_RETIRED     => 'inactive',
);

$replacement_needed = array();
foreach ( $apartments as $apartment ) {
	if ( ! empty( $inventory[ (int) $apartment['id'] ]['needs_replacement'] ) ) {
		$replacement_needed[] = $apartment;
	}
}

$base_url = remove_query_arg( array( 'fsnw_edit_apartment', 'fsnw_edit_bundle', 'fsnw_history', 'fsnw_saved', 'fsnw_error' ), $current_url );

/**
 * Rendert die Adressfelder des Wohnungs-Formulars (Anlegen und Bearbeiten).
 *
 * @param array<string, mixed>|null $apartment Vorbelegung beim Bearbeiten, sonst null.
 */
$render_apartment_fields = static function ( ?array $apartment ): void {
	?>
	<div class="fsnw-km-field-row">
		<div class="fsnw-km-field-row__wide">
			<label for="fsnw-apartment-street"><?php esc_html_e( 'Straße', 'fsnw-key-management' ); ?></label>
			<input type="text" id="fsnw-apartment-street" name="street" required value="<?php echo esc_attr( $apartment['street'] ?? '' ); ?>">
		</div>
		<div>
			<label for="fsnw-apartment-house-number"><?php esc_html_e( 'Hausnr.', 'fsnw-key-management' ); ?></label>
			<input type="text" id="fsnw-apartment-house-number" name="house_number" required value="<?php echo esc_attr( $apartment['house_number'] ?? '' ); ?>">
		</div>
	</div>

	<div class="fsnw-km-field-row">
		<div>
			<label for="fsnw-apartment-zip"><?php esc_html_e( 'PLZ', 'fsnw-key-management' ); ?></label>
			<input type="text" id="fsnw-apartment-zip" name="zip" required value="<?php echo esc_attr( $apartment['zip'] ?? '' ); ?>">
		</div>
		<div class="fsnw-km-field-row__wide">
			<label for="fsnw-apartment-city"><?php esc_html_e( 'Ort', 'fsnw-key-management' ); ?></label>
			<input type="text" id="fsnw-apartment-city" name="city" required value="<?php echo esc_attr( $apartment['city'] ?? '' ); ?>">
		</div>
	</div>

	<label for="fsnw-apartment-unit"><?php esc_html_e( 'Wohneinheit (optional)', 'fsnw-key-management' ); ?></label>
	<input type="text" id="fsnw-apartment-unit" name="unit" value="<?php echo esc_attr( $apartment['unit'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'z. B. WE 3 / 2. OG links', 'fsnw-key-management' ); ?>">

	<label for="fsnw-apartment-notes"><?php esc_html_e( 'Notizen', 'fsnw-key-management' ); ?></label>
	<textarea id="fsnw-apartment-notes" name="notes" rows="2"><?php echo esc_textarea( $apartment['notes'] ?? '' ); ?></textarea>
	<?php
};

/**
 * Rendert die Einzelschlüssel-Liste des Bund-Formulars.
 *
 * @param string[] $keys Vorbelegung (min. ein leeres Feld).
 */
$render_key_fields = static function ( array $keys ): void {
	if ( empty( $keys ) ) {
		$keys = array( '' );
	}
	?>
	<span class="fsnw-km-label"><?php esc_html_e( 'Einzelschlüssel im Bund', 'fsnw-key-management' ); ?></span>
	<div class="fsnw-bundle-keys">
		<?php foreach ( $keys as $single_key ) : ?>
			<div class="fsnw-key-row">
				<input type="text" name="keys[]" value="<?php echo esc_attr( $single_key ); ?>" placeholder="<?php esc_attr_e( 'z. B. Wohnungstür', 'fsnw-key-management' ); ?>">
				<button type="button" class="fsnw-remove-key" aria-label="<?php esc_attr_e( 'Schlüssel entfernen', 'fsnw-key-management' ); ?>">&times;</button>
			</div>
		<?php endforeach; ?>
	</div>
	<button type="button" class="fsnw-btn fsnw-btn--secondary fsnw-btn--small fsnw-add-key"><?php esc_html_e( '+ Schlüssel hinzufügen', 'fsnw-key-management' ); ?></button>
	<?php
};
?>
<div class="fsnw-key-management fsnw-key-management--manage">
	<h1><?php esc_html_e( 'Schlüsselverwaltung', 'fsnw-key-management' ); ?></h1>

	<?php if ( isset( $_GET['fsnw_saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="fsnw-notice fsnw-notice-success"><?php esc_html_e( 'Gespeichert.', 'fsnw-key-management' ); ?></div>
	<?php endif; ?>
	<?php if ( ! empty( $_GET['fsnw_error'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="fsnw-notice fsnw-notice-error"><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['fsnw_error'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?></div>
	<?php endif; ?>

	<?php if ( ! empty( $replacement_needed ) ) : ?>
		<div class="fsnw-replacement-banner" role="alert">
			<strong><?php esc_html_e( 'Neuen Schlüssel anfertigen:', 'fsnw-key-management' ); ?></strong>
			<?php esc_html_e( 'Für folgende Wohnungen sind weniger als 2 Schlüsselbunde vorhanden:', 'fsnw-key-management' ); ?>
			<ul>
				<?php foreach ( $replacement_needed as $apartment ) : ?>
					<li>
						<?php echo esc_html( $apartment['label'] ); ?>
						(<?php echo esc_html( (string) $inventory[ (int) $apartment['id'] ]['existing'] ); ?>
						<?php esc_html_e( 'vorhanden', 'fsnw-key-management' ); ?>)
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<div class="fsnw-km-forms">
		<div class="fsnw-card fsnw-km-form-card fsnw-fade-in">
			<h2><?php esc_html_e( 'Wohnung anlegen', 'fsnw-key-management' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'fsnw_km_save_apartment' ); ?>
				<input type="hidden" name="action" value="fsnw_km_save_apartment">
				<input type="hidden" name="redirect_url" value="<?php echo esc_attr( $base_url ); ?>">
				<input type="hidden" name="apartment_id" value="0">

				<?php $render_apartment_fields( null ); ?>

				<div class="fsnw-km-form-actions">
					<button type="submit" class="fsnw-btn fsnw-btn--primary"><?php esc_html_e( 'Anlegen', 'fsnw-key-management' ); ?></button>
				</div>
			</form>
		</div>

		<div class="fsnw-card fsnw-km-form-card fsnw-fade-in">
			<h2><?php esc_html_e( 'Schlüsselbund anlegen', 'fsnw-key-management' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'fsnw_km_save_bundle' ); ?>
				<input type="hidden" name="action" value="fsnw_km_save_bundle">
				<input type="hidden" name="redirect_url" value="<?php echo esc_attr( $base_url ); ?>">
				<input type="hidden" name="bundle_id" value="0">

				<label for="fsnw-bundle-apartment-search"><?php esc_html_e( 'Wohnung (suchen)', 'fsnw-key-management' ); ?></label>
				<div class="fsnw-combobox">
					<input type="text" id="fsnw-bundle-apartment-search" class="fsnw-combobox__input" autocomplete="off" placeholder="<?php esc_attr_e( 'Straße oder Ort tippen …', 'fsnw-key-management' ); ?>">
					<input type="hidden" name="apartment_id">
					<ul class="fsnw-combobox__list fsnw-hidden">
						<?php foreach ( $apartments as $apartment ) : ?>
							<li data-value="<?php echo esc_attr( (string) $apartment['id'] ); ?>" data-label="<?php echo esc_attr( $apartment['label'] ); ?>">
								<?php echo esc_html( $apartment['label'] ); ?>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>

				<label for="fsnw-bundle-label"><?php esc_html_e( 'Bezeichnung (z. B. Bund A / Haken-Nr.)', 'fsnw-key-management' ); ?></label>
				<input type="text" id="fsnw-bundle-label" name="label" required>

				<?php $render_key_fields( array( '' ) ); ?>

				<label for="fsnw-bundle-notes"><?php esc_html_e( 'Notizen', 'fsnw-key-management' ); ?></label>
				<textarea id="fsnw-bundle-notes" name="notes" rows="2"></textarea>

				<div class="fsnw-km-form-actions">
					<button type="submit" class="fsnw-btn fsnw-btn--primary"><?php esc_html_e( 'Anlegen', 'fsnw-key-management' ); ?></button>
				</div>
			</form>
		</div>
	</div>

	<h2><?php esc_html_e( 'Inventar', 'fsnw-key-management' ); ?></h2>

	<?php if ( empty( $apartments ) ) : ?>
		<div class="fsnw-card"><p><?php esc_html_e( 'Noch keine Wohnungen angelegt.', 'fsnw-key-management' ); ?></p></div>
	<?php endif; ?>

	<?php foreach ( $apartments as $apartment ) : ?>
		<?php
		$apartment_id = (int) $apartment['id'];
		$counts       = $inventory[ $apartment_id ];
		$bundles      = $bundles_by_apartment[ $apartment_id ] ?? array();
		?>
		<div class="fsnw-card fsnw-km-apartment fsnw-fade-in">
			<div class="fsnw-km-apartment__head">
				<div>
					<h3><?php echo esc_html( $apartment['label'] ); ?></h3>
				</div>
				<div class="fsnw-km-apartment__badges">
					<span class="fsnw-badge fsnw-badge--available">
						<?php
						printf(
							/* translators: %d: Anzahl. */
							esc_html__( '%d im Schrank', 'fsnw-key-management' ),
							(int) $counts['available']
						);
						?>
					</span>
					<?php if ( $counts['issued'] > 0 ) : ?>
						<span class="fsnw-badge fsnw-badge--booked">
							<?php
							printf(
								/* translators: %d: Anzahl. */
								esc_html__( '%d draußen', 'fsnw-key-management' ),
								(int) $counts['issued']
							);
							?>
						</span>
					<?php endif; ?>
					<?php if ( $counts['needs_replacement'] ) : ?>
						<span class="fsnw-badge fsnw-badge--overdue"><?php esc_html_e( 'Neuen Schlüssel anfertigen', 'fsnw-key-management' ); ?></span>
					<?php elseif ( $counts['last_available'] ) : ?>
						<span class="fsnw-badge fsnw-badge--underway"><?php esc_html_e( 'Nur noch 1 Bund im Schrank', 'fsnw-key-management' ); ?></span>
					<?php endif; ?>
					<?php if ( 'inactive' === $apartment['status'] ) : ?>
						<span class="fsnw-badge fsnw-badge--inactive"><?php esc_html_e( 'Inaktiv', 'fsnw-key-management' ); ?></span>
					<?php endif; ?>
					<a class="fsnw-btn fsnw-btn--secondary fsnw-btn--small" href="<?php echo esc_url( add_query_arg( 'fsnw_edit_apartment', $apartment_id, $base_url ) ); ?>">
						<?php esc_html_e( 'Bearbeiten', 'fsnw-key-management' ); ?>
					</a>
				</div>
			</div>

			<?php if ( empty( $bundles ) ) : ?>
				<p class="fsnw-km-muted"><?php esc_html_e( 'Noch keine Schlüsselbunde angelegt.', 'fsnw-key-management' ); ?></p>
			<?php else : ?>
				<table class="fsnw-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Bund', 'fsnw-key-management' ); ?></th>
							<th><?php esc_html_e( 'Einzelschlüssel', 'fsnw-key-management' ); ?></th>
							<th><?php esc_html_e( 'Status', 'fsnw-key-management' ); ?></th>
							<th><?php esc_html_e( 'Aktionen', 'fsnw-key-management' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $bundles as $bundle ) : ?>
							<tr>
								<td><?php echo esc_html( $bundle['label'] ); ?></td>
								<td><?php echo esc_html( implode( ', ', $bundle['keys_list'] ) ); ?></td>
								<td>
									<span class="fsnw-badge fsnw-badge--<?php echo esc_attr( $status_badges[ $bundle['status'] ] ?? 'inactive' ); ?>">
										<span class="fsnw-dot fsnw-dot--<?php echo esc_attr( $status_badges[ $bundle['status'] ] ?? 'inactive' ); ?>"></span>
										<?php echo esc_html( $status_labels[ $bundle['status'] ] ?? $bundle['status'] ); ?>
									</span>
								</td>
								<td class="fsnw-km-actions">
									<a class="fsnw-btn fsnw-btn--ghost fsnw-btn--small" href="<?php echo esc_url( add_query_arg( 'fsnw_edit_bundle', $bundle['id'], $base_url ) ); ?>">
										<?php esc_html_e( 'Bearbeiten', 'fsnw-key-management' ); ?>
									</a>
									<a class="fsnw-btn fsnw-btn--ghost fsnw-btn--small" href="<?php echo esc_url( add_query_arg( 'fsnw_history', $bundle['id'], $base_url ) ); ?>">
										<?php esc_html_e( 'Historie', 'fsnw-key-management' ); ?>
									</a>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="fsnw-km-inline-form">
										<?php wp_nonce_field( 'fsnw_km_bundle_clone' ); ?>
										<input type="hidden" name="action" value="fsnw_km_bundle_clone">
										<input type="hidden" name="redirect_url" value="<?php echo esc_attr( $base_url ); ?>">
										<input type="hidden" name="bundle_id" value="<?php echo esc_attr( (string) $bundle['id'] ); ?>">
										<button type="submit" class="fsnw-btn fsnw-btn--ghost fsnw-btn--small"><?php esc_html_e( 'Duplizieren', 'fsnw-key-management' ); ?></button>
									</form>
									<?php if ( BundleService::STATUS_AVAILABLE === $bundle['status'] ) : ?>
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="fsnw-km-inline-form" onsubmit="return confirm('<?php echo esc_js( __( 'Bund wirklich als verloren markieren?', 'fsnw-key-management' ) ); ?>');">
											<?php wp_nonce_field( 'fsnw_km_bundle_status' ); ?>
											<input type="hidden" name="action" value="fsnw_km_bundle_status">
											<input type="hidden" name="redirect_url" value="<?php echo esc_attr( $base_url ); ?>">
											<input type="hidden" name="bundle_id" value="<?php echo esc_attr( (string) $bundle['id'] ); ?>">
											<input type="hidden" name="target_status" value="<?php echo esc_attr( BundleService::STATUS_LOST ); ?>">
											<button type="submit" class="fsnw-btn fsnw-btn--ghost fsnw-btn--small"><?php esc_html_e( 'Verloren', 'fsnw-key-management' ); ?></button>
										</form>
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="fsnw-km-inline-form" onsubmit="return confirm('<?php echo esc_js( __( 'Bund wirklich ausmustern?', 'fsnw-key-management' ) ); ?>');">
											<?php wp_nonce_field( 'fsnw_km_bundle_status' ); ?>
											<input type="hidden" name="action" value="fsnw_km_bundle_status">
											<input type="hidden" name="redirect_url" value="<?php echo esc_attr( $base_url ); ?>">
											<input type="hidden" name="bundle_id" value="<?php echo esc_attr( (string) $bundle['id'] ); ?>">
											<input type="hidden" name="target_status" value="<?php echo esc_attr( BundleService::STATUS_RETIRED ); ?>">
											<button type="submit" class="fsnw-btn fsnw-btn--ghost fsnw-btn--small"><?php esc_html_e( 'Ausmustern', 'fsnw-key-management' ); ?></button>
										</form>
									<?php elseif ( in_array( $bundle['status'], array( BundleService::STATUS_LOST, BundleService::STATUS_RETIRED, BundleService::STATUS_HANDED_OVER ), true ) ) : ?>
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="fsnw-km-inline-form">
											<?php wp_nonce_field( 'fsnw_km_bundle_status' ); ?>
											<input type="hidden" name="action" value="fsnw_km_bundle_status">
											<input type="hidden" name="redirect_url" value="<?php echo esc_attr( $base_url ); ?>">
											<input type="hidden" name="bundle_id" value="<?php echo esc_attr( (string) $bundle['id'] ); ?>">
											<input type="hidden" name="target_status" value="<?php echo esc_attr( BundleService::STATUS_AVAILABLE ); ?>">
											<button type="submit" class="fsnw-btn fsnw-btn--secondary fsnw-btn--small"><?php esc_html_e( 'In den Schrank', 'fsnw-key-management' ); ?></button>
										</form>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
	<?php endforeach; ?>

	<?php if ( null !== $edit_apartment ) : ?>
		<div class="fsnw-km-modal-overlay" data-close-url="<?php echo esc_attr( $base_url ); ?>">
			<div class="fsnw-card fsnw-km-modal" role="dialog" aria-modal="true">
				<div class="fsnw-km-modal__head">
					<h2><?php esc_html_e( 'Wohnung bearbeiten', 'fsnw-key-management' ); ?></h2>
					<a class="fsnw-km-modal__close" href="<?php echo esc_url( $base_url ); ?>" aria-label="<?php esc_attr_e( 'Schließen', 'fsnw-key-management' ); ?>">&times;</a>
				</div>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="fsnw-km-form-card">
					<?php wp_nonce_field( 'fsnw_km_save_apartment' ); ?>
					<input type="hidden" name="action" value="fsnw_km_save_apartment">
					<input type="hidden" name="redirect_url" value="<?php echo esc_attr( $base_url ); ?>">
					<input type="hidden" name="apartment_id" value="<?php echo esc_attr( (string) $edit_apartment['id'] ); ?>">

					<?php $render_apartment_fields( $edit_apartment ); ?>

					<label for="fsnw-apartment-status"><?php esc_html_e( 'Status', 'fsnw-key-management' ); ?></label>
					<select id="fsnw-apartment-status" name="status">
						<option value="active" <?php selected( $edit_apartment['status'], 'active' ); ?>><?php esc_html_e( 'Aktiv', 'fsnw-key-management' ); ?></option>
						<option value="inactive" <?php selected( $edit_apartment['status'], 'inactive' ); ?>><?php esc_html_e( 'Inaktiv', 'fsnw-key-management' ); ?></option>
					</select>

					<div class="fsnw-km-form-actions">
						<button type="submit" class="fsnw-btn fsnw-btn--primary"><?php esc_html_e( 'Speichern', 'fsnw-key-management' ); ?></button>
						<a class="fsnw-btn fsnw-btn--ghost" href="<?php echo esc_url( $base_url ); ?>"><?php esc_html_e( 'Abbrechen', 'fsnw-key-management' ); ?></a>
					</div>
				</form>
			</div>
		</div>
	<?php endif; ?>

	<?php if ( null !== $edit_bundle ) : ?>
		<div class="fsnw-km-modal-overlay" data-close-url="<?php echo esc_attr( $base_url ); ?>">
			<div class="fsnw-card fsnw-km-modal" role="dialog" aria-modal="true">
				<div class="fsnw-km-modal__head">
					<h2><?php esc_html_e( 'Schlüsselbund bearbeiten', 'fsnw-key-management' ); ?></h2>
					<a class="fsnw-km-modal__close" href="<?php echo esc_url( $base_url ); ?>" aria-label="<?php esc_attr_e( 'Schließen', 'fsnw-key-management' ); ?>">&times;</a>
				</div>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="fsnw-km-form-card">
					<?php wp_nonce_field( 'fsnw_km_save_bundle' ); ?>
					<input type="hidden" name="action" value="fsnw_km_save_bundle">
					<input type="hidden" name="redirect_url" value="<?php echo esc_attr( $base_url ); ?>">
					<input type="hidden" name="bundle_id" value="<?php echo esc_attr( (string) $edit_bundle['id'] ); ?>">

					<label for="fsnw-bundle-label-edit"><?php esc_html_e( 'Bezeichnung (z. B. Bund A / Haken-Nr.)', 'fsnw-key-management' ); ?></label>
					<input type="text" id="fsnw-bundle-label-edit" name="label" required value="<?php echo esc_attr( $edit_bundle['label'] ); ?>">

					<?php $render_key_fields( $edit_bundle['keys_list'] ); ?>

					<label for="fsnw-bundle-notes-edit"><?php esc_html_e( 'Notizen', 'fsnw-key-management' ); ?></label>
					<textarea id="fsnw-bundle-notes-edit" name="notes" rows="2"><?php echo esc_textarea( $edit_bundle['notes'] ?? '' ); ?></textarea>

					<div class="fsnw-km-form-actions">
						<button type="submit" class="fsnw-btn fsnw-btn--primary"><?php esc_html_e( 'Speichern', 'fsnw-key-management' ); ?></button>
						<a class="fsnw-btn fsnw-btn--ghost" href="<?php echo esc_url( $base_url ); ?>"><?php esc_html_e( 'Abbrechen', 'fsnw-key-management' ); ?></a>
					</div>
				</form>
			</div>
		</div>
	<?php endif; ?>

	<?php if ( null !== $history_bundle && null !== $history ) : ?>
		<div class="fsnw-km-modal-overlay" data-close-url="<?php echo esc_attr( $base_url ); ?>">
			<div class="fsnw-card fsnw-km-modal fsnw-km-modal--wide" role="dialog" aria-modal="true">
				<div class="fsnw-km-modal__head">
					<h2>
						<?php
						printf(
							/* translators: %s: Bezeichnung des Schlüsselbunds. */
							esc_html__( 'Historie: %s', 'fsnw-key-management' ),
							esc_html( $history_bundle['label'] )
						);
						?>
					</h2>
					<a class="fsnw-km-modal__close" href="<?php echo esc_url( $base_url ); ?>" aria-label="<?php esc_attr_e( 'Schließen', 'fsnw-key-management' ); ?>">&times;</a>
				</div>
				<?php if ( empty( $history ) ) : ?>
					<p><?php esc_html_e( 'Noch keine Einträge.', 'fsnw-key-management' ); ?></p>
				<?php else : ?>
					<table class="fsnw-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Zeitpunkt', 'fsnw-key-management' ); ?></th>
								<th><?php esc_html_e( 'Aktion', 'fsnw-key-management' ); ?></th>
								<th><?php esc_html_e( 'Nutzer', 'fsnw-key-management' ); ?></th>
								<th><?php esc_html_e( 'Details', 'fsnw-key-management' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $history as $entry ) : ?>
								<?php $entry_user = $entry['user_id'] ? get_userdata( (int) $entry['user_id'] ) : null; ?>
								<tr>
									<td><?php echo esc_html( date_i18n( 'd.m.Y H:i', strtotime( $entry['created_at'] ) ) ); ?></td>
									<td><?php echo esc_html( $entry['action'] ); ?></td>
									<td><?php echo esc_html( $entry_user ? $entry_user->display_name : '—' ); ?></td>
									<td>
										<?php if ( empty( $entry['meta'] ) ) : ?>
											&mdash;
										<?php else : ?>
											<code>
												<?php
												foreach ( $entry['meta'] as $meta_key => $meta_value ) {
													echo esc_html( $meta_key . ': ' . ( is_scalar( $meta_value ) ? (string) $meta_value : wp_json_encode( $meta_value ) ) ) . '<br>';
												}
												?>
											</code>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		</div>
	<?php endif; ?>
</div>
