# Changelog

Alle nennenswerten Änderungen an diesem Projekt werden in dieser Datei dokumentiert.

Das Format orientiert sich an [Keep a Changelog](https://keepachangelog.com/de/1.1.0/), das Projekt folgt [Semantic Versioning](https://semver.org/lang/de/).

## [Unreleased]

## [1.2.0] - 2026-07-13

Changed

- Ausgabe-Seite: Die Bund-Auswahl ist jetzt ein Suchfeld (tippen filtert live
  nach Straße, Ort oder Bund-Bezeichnung; Einzelschlüssel als Zusatzzeile je
  Treffer) statt eines langen Dropdowns — wichtig bei ~200 Wohnungen. Die
  Letzter-Bund-Warnung funktioniert weiterhin.
- Verwaltungs-Seite: Auch die Wohnungs-Auswahl beim Bund-Anlegen ist ein
  Suchfeld. Ohne gültige Auswahl aus der Liste lässt sich das Formular nicht
  absenden (rote Markierung).

Added

- "Duplizieren"-Aktion je Schlüsselbund: legt eine identische Kopie (gleiche
  Einzelschlüssel, Suffix "(Kopie)") als neuen verfügbaren Bund an — für den
  Normalfall, dass die Bunde einer Wohnung exakt gleich sind.

## [1.1.0] - 2026-07-13

Added

- Import-Werkzeug im WP-Admin (Werkzeuge → Schlüssel-Import): eine Zeile pro
  Wohnung (`Straße;Hausnummer;PLZ;Ort;Wohneinheit;AnzahlBunde;SchlüsselProBund`,
  die letzten drei optional mit Standardwerten leer/2/3) legt Wohnung und
  durchnummerierte, identische Bunde in einem Rutsch an; Ergebnis-/Fehlerbericht
  pro Zeile.
- Neue Ausgabe-Typen: "Aufschließen – ausgesperrt" (kommt zurück) und
  "Ausgabe bei Verlust" (Ersatz an Klient, Bund bleibt dort; zusätzlich wird
  automatisch ein dauerhaft vergebener Bund derselben Wohnung als verloren
  ausgebucht — die Bunde sind untereinander identisch).
- "An Klient übergeben"-Aktion in der Draußen-Liste (Verlust-Nachmeldung):
  eine laufende Ausgabe mit Rückkehr wird zur "Ausgabe bei Verlust", der Bund
  bleibt beim Klienten und der alte Bund des Klienten wird ausgebucht.

## [1.0.2] - 2026-07-13

Changed

- Einzelschlüssel-Felder im Bund-Formular vergrößert (44px Höhe, volle Breite)
  und der Entfernen-Button ist jetzt eine deutlich sichtbare 44x44-Schaltfläche
  mit Hover-Zustand statt eines winzigen "x".
- Bearbeiten (Wohnung/Bund) und Historie öffnen jetzt als Popup (Modal mit
  abgedunkeltem Hintergrund) statt die Anlege-Formulare oben vorzubelegen;
  Schließen per X-Button, Abbrechen, Klick auf den Hintergrund oder Escape.
  Die Formular-Karten oben sind dadurch reine Anlegen-Formulare.

## [1.0.1] - 2026-07-13

Changed

- Wohnungs-Formular umgestellt (Nutzerfeedback): statt freier Bezeichnung + Klient
  jetzt strukturierte Adressfelder Straße, Hausnummer, PLZ, Ort und Wohneinheit
  (optional). Die Anzeige-Bezeichnung wird automatisch zusammengesetzt
  ("Straße Nr., PLZ Ort – WE"); neue Spalten per dbDelta (DB-Version 0.2.0),
  das Klient-Feld ist aus allen Oberflächen entfernt.

## [1.0.0] - 2026-07-13

Added

- CI/CD: GitHub-Actions-Workflows für FTP-Deploy (relativer Pfad
  `./fsnw-key-management/`, FTP-Benutzer ist auf den plugins-Ordner eingeschränkt),
  PHP-Lint (8.0–8.3), Build-ZIP, Tag-Release und automatische POT-Generierung
  (`languages/fsnw-key-management.pot` [skip ci]).
- README mit Konzept, Shortcodes und Ausgabe-Ablauf.

## [0.3.0] - 2026-07-13

Added

- Ausgabe-Seite als Frontend-Shortcode `[wp_fsnw_key_dispatch]`: Bund ausgeben
  (Auswahl gruppiert nach Wohnung, nur verfügbare Bunde; Mitarbeiter-Dropdown;
  Typen Wohnungskontrolle/Einzug/Sonstiges; Notiz), Liste "Warten auf
  Unterschrift" mit Abbrechen, Liste "Draußen" mit Rückgabe- und Verloren-Buttons.
- Kiosk-Integration (harte Abhängigkeit auf FSNW Signature Kiosk, source
  `fsnw-key-management`): Ausgabe sendet Signatur-Anforderung (Bund, Wohnung,
  Einzelschlüssel, Klient, Typ) ans Tablet; Abschluss über Hook
  `fsnw_signature_completed` setzt Ausgabe auf "ausgegeben" (bei Einzug geht der
  Bund dauerhaft weg); Abbruch storniert die Kiosk-Anforderung; Bild-Abruf für
  `fsnw_manage_keys` freigeschaltet; Admin-Hinweis bei fehlendem Kiosk-Plugin.
- Warnung "letzter Bund im Schrank": erscheint dynamisch bei Auswahl des letzten
  verfügbaren Bundes einer Wohnung und erfordert eine bewusste Bestätigung
  (Checkbox, client- und serverseitig geprüft).

## [0.2.0] - 2026-07-13

Added

- Verwaltungs-Seite als Frontend-Shortcode `[wp_fsnw_key_manage]` (Zugriff: Login +
  `fsnw_manage_keys`): Wohnungen anlegen/bearbeiten (inkl. aktiv/inaktiv),
  Schlüsselbunde anlegen/bearbeiten mit dynamischer Einzelschlüssel-Liste,
  Inventar-Übersicht je Wohnung mit Zählwerten und Warn-Badges ("Nur noch 1 Bund
  im Schrank", "Neuen Schlüssel anfertigen"), rotes Hinweis-Banner für alle
  betroffenen Wohnungen, Bund-Aktionen Verloren/Ausmustern/In-den-Schrank
  (Reaktivierung, z. B. wiedergefunden oder Ersatz angefertigt) und Historie je Bund.
- Formular-Verarbeitung über admin_post-Handler mit Nonce/Capability-Prüfung und
  kurzlebigen Erfolgs-/Fehlermeldungen (4s-Auto-Ausblenden, URL-Parameter werden
  bereinigt).
- Seiten-Styles im Corporate-Design (`key-management.css`), Vanilla-JS für
  Meldungen und dynamische Schlüssel-Felder.

## [0.1.0] - 2026-07-13

Added

- Plugin-Grundgerüst: Bootstrap, Aktivierung/Deaktivierung, Uninstall (Daten bleiben
  als Nachweis erhalten), Composer-Classmap, WPCS-Konfiguration, Design-Basis
  (tokens.css/base.css aus dem gemeinsamen Corporate-Design).
- Datenbanktabellen (dbDelta): `fsnw_km_apartments` (Wohnungen/Klienten),
  `fsnw_km_bundles` (Schlüsselbunde mit benannten Einzelschlüsseln als JSON-Liste
  und Status available/issued/handed_over/lost/retired), `fsnw_km_issues`
  (Ausgaben mit Typ einzug/kontrolle/sonstiges und Kiosk-Signatur-Verweis),
  `fsnw_km_logs` (Historie).
- Capability `fsnw_manage_keys` (bei Aktivierung an Administrator).
- Services: ApartmentService/BundleService (CRUD + Statuswechsel mit Historie),
  LogService, InventoryService mit der zentralen Bestandslogik: "verfügbar" =
  im Schrank, "vorhanden" = im Schrank + temporär draußen; Warn-Flags
  "letzter verfügbarer Bund" (Ausgabe nicht empfohlen) und "neuen Schlüssel
  anfertigen" (< 2 vorhandene Bunde je Wohnung).
