# FSNW Key Management

Schlüsselverwaltung für Klienten-Wohnungen: Inventar der Schlüsselbunde im zentralen
Schlüsselkasten, Ausgabe an Mitarbeiter mit Tablet-Unterschrift, Rückgabe- und
Verlust-Verwaltung.

**Harte Abhängigkeit:** Benötigt das Plugin
[FSNW Signature Kiosk](https://github.com/itSupportFsnw/fsnw-signature-kiosk) für die
Ausgabe-Unterschrift (Integration nach dessen `docs/integration.md`, source
`fsnw-key-management`).

## Konzept

- **Wohnung/Klient** → mehrere **Schlüsselbunde** (Normalfall 2) → je Bund 2–3 benannte
  Einzelschlüssel. Ausgegeben wird immer ein ganzer Bund.
- Bund-Status: `available` (im Schrank) · `issued` (temporär draußen) ·
  `handed_over` (dauerhaft vergeben, Einzug) · `lost` (verloren) · `retired` (ausgemustert).
- Ausgabe-Typen: **Wohnungskontrolle** und **Sonstiges** (Bund kommt zurück, Rückgabe per
  Klick) sowie **Einzug** (Bund geht mit der Unterschrift dauerhaft weg).
- **Warnlogik**: Hängt nur noch 1 Bund einer Wohnung im Schrank, ist die Ausgabe nicht
  empfohlen (bewusste Bestätigung nötig). Sind dauerhaft weniger als 2 Bunde vorhanden
  (Verlust/Einzug), erscheint der Hinweis **"Neuen Schlüssel anfertigen"**.

## Shortcodes (beide: Login + Capability `fsnw_manage_keys`)

| Shortcode | Seite |
| --- | --- |
| `[wp_fsnw_key_dispatch]` | Ausgabe-Arbeitsplatz: ausgeben (Tablet-Unterschrift), abbrechen, Rückgabe, Verlust |
| `[wp_fsnw_key_manage]` | Verwaltung: Wohnungen/Bunde anlegen und bearbeiten, Inventar, Verlust/Ausmustern/Reaktivieren, Historie (je Bund und je Wohnung) |
| `[wp_fsnw_key_list]` | Lese-Übersicht für alle angemeldeten Mitarbeiter (nur Login nötig): Wohnung, Schlüssel-Nr. (Bund-Kennung vor dem ersten Minus), Verfügbarkeit |

## Ablauf einer Ausgabe

1. Mitarbeiter der Ausgabe wählt Bund, Empfänger und Typ → Kiosk-Anforderung wird gesendet,
   der Bund ist ab sofort gesperrt.
2. Empfänger unterschreibt am Tablet → Ausgabe wird "ausgegeben", die Unterschrift liegt im
   Kiosk-Plugin (Verweis `kiosk_signature_id`).
3. Rückgabe/Verlust vermerkt der Ausgabe-Mitarbeiter per Klick; Abbruch vor der Unterschrift
   räumt das Tablet automatisch.

Alle Bewegungen landen in der Historie (`fsnw_km_logs`). Beim Löschen des Plugins bleiben
sämtliche Daten als Nachweis erhalten (nur Optionen werden entfernt).
