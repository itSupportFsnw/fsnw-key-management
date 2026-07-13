<?php
/**
 * Frontend-Template für [wp_fsnw_key_dispatch] - der Ausgabe-Arbeitsplatz.
 *
 * Schlüsselbund ausgeben (mit Kiosk-Tablet-Unterschrift), laufende
 * Anforderungen abbrechen, Rückgaben und Verluste vermerken.
 * Zugriff wird im FrontendController über Login + fsnw_manage_keys geprüft.
 *
 * @package FsnwKeyManagement
 *
 * @var array<int, array<string, mixed>>             $apartments             Aktive Wohnungen.
 * @var array<int, array<string, mixed>>             $inventory              apartment_id => Zählwerte/Warn-Flags.
 * @var array<int, array<int, array<string, mixed>>> $available_by_apartment apartment_id => verfügbare Bunde.
 * @var array<int, array<string, mixed>>             $awaiting               Ausgaben, die auf Unterschrift warten.
 * @var array<int, array<string, mixed>>             $out                    Draußen befindliche Bunde (Rückgabe offen).
 * @var array<int, object>                           $employees              Mitarbeiter (ID, display_name).
 * @var array<int, array<string, mixed>>             $replacement_needed     Wohnungen mit Ersatz-Hinweis.
 * @var array<string, string>                        $type_labels            Ausgabe-Typ-Labels.
 * @var string                                       $current_url
 */

defined( 'ABSPATH' ) || exit;

$base_url = remove_query_arg( array( 'fsnw_saved', 'fsnw_error' ), $current_url );
?>
<div class="fsnw-key-management fsnw-key-management--dispatch">
	<h1><?php esc_html_e( 'Schlüsselausgabe', 'fsnw-key-management' ); ?></h1>

	<?php if ( isset( $_GET['fsnw_saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="fsnw-notice fsnw-notice-success"><?php esc_html_e( 'Erledigt.', 'fsnw-key-management' ); ?></div>
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
					<li><?php echo esc_html( $apartment['label'] ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<div class="fsnw-card fsnw-km-form-card fsnw-fade-in">
		<h2><?php esc_html_e( 'Schlüsselbund ausgeben', 'fsnw-key-management' ); ?></h2>

		<?php if ( empty( $available_by_apartment ) ) : ?>
			<p class="fsnw-km-muted"><?php esc_html_e( 'Aktuell hängt kein verfügbarer Schlüsselbund im Schrank.', 'fsnw-key-management' ); ?></p>
		<?php else : ?>
			<form id="fsnw-issue-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'fsnw_km_issue_start' ); ?>
				<input type="hidden" name="action" value="fsnw_km_issue_start">
				<input type="hidden" name="redirect_url" value="<?php echo esc_attr( $base_url ); ?>">

				<label for="fsnw-issue-bundle"><?php esc_html_e( 'Schlüsselbund', 'fsnw-key-management' ); ?></label>
				<select id="fsnw-issue-bundle" name="bundle_id" required>
					<option value=""><?php esc_html_e( 'Bitte wählen …', 'fsnw-key-management' ); ?></option>
					<?php foreach ( $apartments as $apartment ) : ?>
						<?php
						$apartment_id = (int) $apartment['id'];

						if ( empty( $available_by_apartment[ $apartment_id ] ) ) {
							continue;
						}

						$is_last = ! empty( $inventory[ $apartment_id ]['last_available'] );
						?>
						<optgroup label="<?php echo esc_attr( $apartment['label'] ); ?>">
							<?php foreach ( $available_by_apartment[ $apartment_id ] as $bundle ) : ?>
								<option value="<?php echo esc_attr( (string) $bundle['id'] ); ?>" data-last="<?php echo esc_attr( $is_last ? '1' : '0' ); ?>">
									<?php echo esc_html( $bundle['label'] . ' (' . implode( ', ', $bundle['keys_list'] ) . ')' ); ?>
								</option>
							<?php endforeach; ?>
						</optgroup>
					<?php endforeach; ?>
				</select>

				<div id="fsnw-last-bundle-warning" class="fsnw-last-bundle-warning fsnw-hidden">
					<?php esc_html_e( 'Achtung: Das ist der letzte Bund dieser Wohnung im Schrank – Ausgabe nicht empfohlen.', 'fsnw-key-management' ); ?>
					<label>
						<input type="checkbox" id="fsnw-last-bundle-confirm" name="confirm_last" value="1">
						<?php esc_html_e( 'Trotzdem ausgeben', 'fsnw-key-management' ); ?>
					</label>
				</div>

				<label for="fsnw-issue-employee"><?php esc_html_e( 'Mitarbeiter (Empfänger)', 'fsnw-key-management' ); ?></label>
				<select id="fsnw-issue-employee" name="issued_to_user_id" required>
					<option value=""><?php esc_html_e( 'Bitte wählen …', 'fsnw-key-management' ); ?></option>
					<?php foreach ( $employees as $employee ) : ?>
						<option value="<?php echo esc_attr( (string) $employee->ID ); ?>"><?php echo esc_html( $employee->display_name ); ?></option>
					<?php endforeach; ?>
				</select>

				<label for="fsnw-issue-type"><?php esc_html_e( 'Ausgabe-Typ', 'fsnw-key-management' ); ?></label>
				<select id="fsnw-issue-type" name="issue_type" required>
					<?php foreach ( $type_labels as $type_key => $type_label ) : ?>
						<option value="<?php echo esc_attr( $type_key ); ?>"><?php echo esc_html( $type_label ); ?></option>
					<?php endforeach; ?>
				</select>

				<label for="fsnw-issue-notes"><?php esc_html_e( 'Notiz (optional)', 'fsnw-key-management' ); ?></label>
				<textarea id="fsnw-issue-notes" name="notes" rows="2"></textarea>

				<div class="fsnw-km-form-actions">
					<button type="submit" class="fsnw-btn fsnw-btn--primary">
						<?php esc_html_e( 'Ausgeben – Unterschrift am Tablet anfordern', 'fsnw-key-management' ); ?>
					</button>
				</div>
			</form>
		<?php endif; ?>
	</div>

	<?php if ( ! empty( $awaiting ) ) : ?>
		<div class="fsnw-card fsnw-km-form-card fsnw-fade-in">
			<h2><?php esc_html_e( 'Warten auf Unterschrift', 'fsnw-key-management' ); ?></h2>
			<table class="fsnw-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Bund', 'fsnw-key-management' ); ?></th>
						<th><?php esc_html_e( 'Wohnung', 'fsnw-key-management' ); ?></th>
						<th><?php esc_html_e( 'Mitarbeiter', 'fsnw-key-management' ); ?></th>
						<th><?php esc_html_e( 'Typ', 'fsnw-key-management' ); ?></th>
						<th><?php esc_html_e( 'Aktion', 'fsnw-key-management' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $awaiting as $issue ) : ?>
						<tr>
							<td><?php echo esc_html( $issue['bundle_label'] ); ?></td>
							<td><?php echo esc_html( $issue['apartment_label'] ); ?></td>
							<td><?php echo esc_html( $issue['recipient_name'] ); ?></td>
							<td><?php echo esc_html( $type_labels[ $issue['type'] ] ?? $issue['type'] ); ?></td>
							<td>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="fsnw-km-inline-form">
									<?php wp_nonce_field( 'fsnw_km_issue_abort' ); ?>
									<input type="hidden" name="action" value="fsnw_km_issue_abort">
									<input type="hidden" name="redirect_url" value="<?php echo esc_attr( $base_url ); ?>">
									<input type="hidden" name="issue_id" value="<?php echo esc_attr( (string) $issue['id'] ); ?>">
									<button type="submit" class="fsnw-btn fsnw-btn--secondary fsnw-btn--small"><?php esc_html_e( 'Abbrechen', 'fsnw-key-management' ); ?></button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>

	<div class="fsnw-card fsnw-km-form-card fsnw-fade-in">
		<h2><?php esc_html_e( 'Draußen (Rückgabe offen)', 'fsnw-key-management' ); ?></h2>

		<?php if ( empty( $out ) ) : ?>
			<p class="fsnw-km-muted"><?php esc_html_e( 'Aktuell sind keine Schlüsselbunde unterwegs.', 'fsnw-key-management' ); ?></p>
		<?php else : ?>
			<table class="fsnw-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Bund', 'fsnw-key-management' ); ?></th>
						<th><?php esc_html_e( 'Wohnung', 'fsnw-key-management' ); ?></th>
						<th><?php esc_html_e( 'Mitarbeiter', 'fsnw-key-management' ); ?></th>
						<th><?php esc_html_e( 'Typ', 'fsnw-key-management' ); ?></th>
						<th><?php esc_html_e( 'Seit', 'fsnw-key-management' ); ?></th>
						<th><?php esc_html_e( 'Aktionen', 'fsnw-key-management' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $out as $issue ) : ?>
						<tr>
							<td><?php echo esc_html( $issue['bundle_label'] ); ?></td>
							<td><?php echo esc_html( $issue['apartment_label'] ); ?></td>
							<td><?php echo esc_html( $issue['recipient_name'] ); ?></td>
							<td><?php echo esc_html( $type_labels[ $issue['type'] ] ?? $issue['type'] ); ?></td>
							<td><?php echo esc_html( empty( $issue['issued_at'] ) ? '—' : date_i18n( 'd.m.Y H:i', strtotime( $issue['issued_at'] ) ) ); ?></td>
							<td class="fsnw-km-actions">
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="fsnw-km-inline-form">
									<?php wp_nonce_field( 'fsnw_km_issue_return' ); ?>
									<input type="hidden" name="action" value="fsnw_km_issue_return">
									<input type="hidden" name="redirect_url" value="<?php echo esc_attr( $base_url ); ?>">
									<input type="hidden" name="issue_id" value="<?php echo esc_attr( (string) $issue['id'] ); ?>">
									<button type="submit" class="fsnw-btn fsnw-btn--primary fsnw-btn--small"><?php esc_html_e( 'Rückgabe', 'fsnw-key-management' ); ?></button>
								</form>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="fsnw-km-inline-form" onsubmit="return confirm('<?php echo esc_js( __( 'Bund dauerhaft an den Klienten übergeben? Der alte Bund des Klienten wird als Verlust ausgebucht.', 'fsnw-key-management' ) ); ?>');">
									<?php wp_nonce_field( 'fsnw_km_issue_handover' ); ?>
									<input type="hidden" name="action" value="fsnw_km_issue_handover">
									<input type="hidden" name="redirect_url" value="<?php echo esc_attr( $base_url ); ?>">
									<input type="hidden" name="issue_id" value="<?php echo esc_attr( (string) $issue['id'] ); ?>">
									<button type="submit" class="fsnw-btn fsnw-btn--secondary fsnw-btn--small"><?php esc_html_e( 'An Klient übergeben', 'fsnw-key-management' ); ?></button>
								</form>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="fsnw-km-inline-form" onsubmit="return confirm('<?php echo esc_js( __( 'Bund wirklich als verloren melden?', 'fsnw-key-management' ) ); ?>');">
									<?php wp_nonce_field( 'fsnw_km_issue_lost' ); ?>
									<input type="hidden" name="action" value="fsnw_km_issue_lost">
									<input type="hidden" name="redirect_url" value="<?php echo esc_attr( $base_url ); ?>">
									<input type="hidden" name="issue_id" value="<?php echo esc_attr( (string) $issue['id'] ); ?>">
									<button type="submit" class="fsnw-btn fsnw-btn--ghost fsnw-btn--small"><?php esc_html_e( 'Verloren', 'fsnw-key-management' ); ?></button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
</div>
