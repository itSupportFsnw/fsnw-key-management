<?php
/**
 * Admin-Ansicht: Massen-Import für Wohnungen und Schlüsselbunde.
 *
 * @package FsnwKeyManagement
 *
 * @var array{apartments: int, bundles: int, errors: string[]}|null $result Ergebnis des letzten Imports.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Schlüssel-Import', 'fsnw-key-management' ); ?></h1>

	<?php if ( null !== $result ) : ?>
		<?php if ( $result['apartments'] > 0 ) : ?>
			<div class="notice notice-success">
				<p>
					<?php
					printf(
						/* translators: 1: Anzahl Wohnungen, 2: Anzahl Bunde. */
						esc_html__( 'Import abgeschlossen: %1$d Wohnungen mit %2$d Schlüsselbunden angelegt.', 'fsnw-key-management' ),
						(int) $result['apartments'],
						(int) $result['bundles']
					);
					?>
				</p>
			</div>
		<?php endif; ?>
		<?php if ( ! empty( $result['errors'] ) ) : ?>
			<div class="notice notice-error">
				<p><strong><?php esc_html_e( 'Nicht importierte Zeilen:', 'fsnw-key-management' ); ?></strong></p>
				<ul>
					<?php foreach ( $result['errors'] as $error ) : ?>
						<li><?php echo esc_html( $error ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>
	<?php endif; ?>

	<p><?php esc_html_e( 'Legt pro Zeile eine Wohnung inklusive ihrer Schlüsselbunde an. Die Bunde einer Wohnung sind untereinander identisch; Bunde und Einzelschlüssel werden automatisch durchnummeriert.', 'fsnw-key-management' ); ?></p>

	<h2><?php esc_html_e( 'Format', 'fsnw-key-management' ); ?></h2>
	<p><code>Straße;Hausnummer;PLZ;Ort;Wohneinheit;AnzahlBunde;SchlüsselProBund</code></p>
	<ul style="list-style: disc; margin-left: 20px;">
		<li><?php esc_html_e( 'Pflicht: Straße, Hausnummer, PLZ, Ort.', 'fsnw-key-management' ); ?></li>
		<li><?php esc_html_e( 'Optional: Wohneinheit (leer lassen möglich), Anzahl Bunde (Standard 2), Schlüssel pro Bund (Standard 3).', 'fsnw-key-management' ); ?></li>
	</ul>
	<p>
		<strong><?php esc_html_e( 'Beispiele:', 'fsnw-key-management' ); ?></strong><br>
		<code>Musterstraße;12;44135;Dortmund</code><br>
		<code>Musterstraße;12;44135;Dortmund;WE 3</code><br>
		<code>Beispielweg;7a;44139;Dortmund;2. OG links;3;2</code>
	</p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'fsnw_km_import' ); ?>
		<input type="hidden" name="action" value="fsnw_km_import">

		<textarea name="import_data" rows="15" class="large-text code" placeholder="Musterstraße;12;44135;Dortmund;WE 3;2;3" required></textarea>

		<?php submit_button( __( 'Importieren', 'fsnw-key-management' ) ); ?>
	</form>
</div>
