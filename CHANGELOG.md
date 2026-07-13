# Changelog

Alle nennenswerten Änderungen an diesem Projekt werden in dieser Datei dokumentiert.

Das Format orientiert sich an [Keep a Changelog](https://keepachangelog.com/de/1.1.0/), das Projekt folgt [Semantic Versioning](https://semver.org/lang/de/).

## [Unreleased]

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
