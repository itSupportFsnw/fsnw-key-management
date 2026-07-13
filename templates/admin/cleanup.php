<?php
/**
 * Admin-Ansicht: Aufräum-Werkzeug (Test- und Altdaten endgültig löschen).
 *
 * @package FsnwKeyManagement
 *
 * @var array<string, string>|null       $result     Ergebnis der letzten Aktion (success/error).
 * @var array<int, array<string, mixed>> $apartments Alle Wohnungen.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Schlüssel-Daten aufräumen', 'fsnw-key-management' ); ?></h1>

	<?php if ( null !== $result && isset( $result['success'] ) ) : ?>
		<div class="notice notice-success"><p><?php echo esc_html( $result['success'] ); ?></p></div>
	<?php endif; ?>
	<?php if ( null !== $result && isset( $result['error'] ) ) : ?>
		<div class="notice notice-error"><p><?php echo esc_html( $result['error'] ); ?></p></div>
	<?php endif; ?>

	<div class="notice notice-warning inline">
		<p><strong><?php esc_html_e( 'Achtung:', 'fsnw-key-management' ); ?></strong> <?php esc_html_e( 'Alle Löschungen hier sind endgültig und umgehen die normale Nachweis-Aufbewahrung. Nur für Testdaten und eindeutig veraltete Vorgänge verwenden.', 'fsnw-key-management' ); ?></p>
	</div>

	<h2><?php esc_html_e( 'Wohnung komplett löschen (Testdaten)', 'fsnw-key-management' ); ?></h2>
	<p><?php esc_html_e( 'Löscht die Wohnung inklusive aller Schlüsselbunde, Ausgaben und Historie-Einträge.', 'fsnw-key-management' ); ?></p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Diese Wohnung mit allen Daten endgültig löschen?', 'fsnw-key-management' ) ); ?>');">
		<?php wp_nonce_field( 'fsnw_km_cleanup_apartment' ); ?>
		<input type="hidden" name="action" value="fsnw_km_cleanup_apartment">

		<table class="form-table">
			<tr>
				<th><label for="fsnw-cleanup-apartment"><?php esc_html_e( 'Wohnung', 'fsnw-key-management' ); ?></label></th>
				<td>
					<select id="fsnw-cleanup-apartment" name="apartment_id" required>
						<option value=""><?php esc_html_e( 'Bitte wählen …', 'fsnw-key-management' ); ?></option>
						<?php foreach ( $apartments as $apartment ) : ?>
							<option value="<?php echo esc_attr( (string) $apartment['id'] ); ?>"><?php echo esc_html( $apartment['label'] ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Bestätigung', 'fsnw-key-management' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="confirm" value="1">
						<?php esc_html_e( 'Ja, diese Wohnung mit allen Daten endgültig löschen.', 'fsnw-key-management' ); ?>
					</label>
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'Wohnung löschen', 'fsnw-key-management' ), 'delete' ); ?>
	</form>

	<hr>

	<h2><?php esc_html_e( 'Alte Vorgänge löschen', 'fsnw-key-management' ); ?></h2>
	<p><?php esc_html_e( 'Löscht abgeschlossene Ausgaben (zurückgegeben/abgebrochen) und Historie-Einträge, die älter als die angegebene Anzahl Tage sind. Wohnungen, Schlüsselbunde sowie Verlust- und Einzug-Ausgaben bleiben als Nachweis erhalten.', 'fsnw-key-management' ); ?></p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Alte Vorgänge endgültig löschen?', 'fsnw-key-management' ) ); ?>');">
		<?php wp_nonce_field( 'fsnw_km_cleanup_old' ); ?>
		<input type="hidden" name="action" value="fsnw_km_cleanup_old">

		<table class="form-table">
			<tr>
				<th><label for="fsnw-cleanup-days"><?php esc_html_e( 'Älter als (Tage)', 'fsnw-key-management' ); ?></label></th>
				<td>
					<input type="number" id="fsnw-cleanup-days" name="days" value="365" min="30" step="1" required>
					<p class="description"><?php esc_html_e( 'Mindestens 30 Tage.', 'fsnw-key-management' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Bestätigung', 'fsnw-key-management' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="confirm" value="1">
						<?php esc_html_e( 'Ja, alte Vorgänge endgültig löschen.', 'fsnw-key-management' ); ?>
					</label>
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'Alte Vorgänge löschen', 'fsnw-key-management' ), 'delete' ); ?>
	</form>
</div>
