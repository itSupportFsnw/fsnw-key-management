# Changelog

Alle nennenswerten Änderungen an diesem Projekt werden in dieser Datei dokumentiert.

Das Format orientiert sich an [Keep a Changelog](https://keepachangelog.com/de/1.1.0/), das Projekt folgt [Semantic Versioning](https://semver.org/lang/de/).

## [Unreleased]

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
