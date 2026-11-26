# Waffen und Fahrzeuge per CSV importieren

Der CSV-Import füllt oder ergänzt die Waffen- und Fahrzeugdatenbank in einem Schritt. Er steht nur Administratoren zur Verfügung.

## Wo

- **Oberfläche:** Verwaltung → Export & Import → „Waffen & Fahrzeuge aus CSV“. Erst **Probelauf**, dann **Importieren**.
- **Server/Konsole:** im Release-Ordner (`~/leonida/current`)

  ```bash
  php artisan leonida:catalog-import weapons imports/niadb/waffen.csv          # Probelauf
  php artisan leonida:catalog-import weapons imports/niadb/waffen.csv --apply  # schreiben
  php artisan leonida:catalog-import vehicles imports/niadb/fahrzeuge.csv --apply
  ```

## Regeln

- Erste Zeile: Spaltennamen (siehe unten, Reihenfolge egal, nicht benötigte Spalten weglassen). Pflicht ist nur `name`.
- Trennzeichen `,`, `;` oder Tab werden erkannt; UTF-8 (mit oder ohne BOM) oder Windows-1252. Dezimalzahlen mit Punkt oder Komma.
- Zuordnung zu vorhandenen Einträgen über den Namen (Groß-/Kleinschreibung und doppelte Leerzeichen egal).
- **Leere Zelle = keine Angabe.** Bei neuen Einträgen bleibt das Feld leer (NULL, nie 0), bei vorhandenen ändert sich nichts.
- Vorhandene Werte werden **nicht** überschrieben. Weicht die Datei ab, steht das im Bericht. Mit „Überschreiben“ (`--overwrite`) gewinnt die Datei.
- Listenwerte (Typ, Marke, Hersteller, Klasse …) werden über den Namen gesucht und bei Bedarf angelegt. Doppelte lassen sich danach unter Auswahllisten zusammenführen.
- Fehlerhafte Zeilen (Wert außerhalb des Bereichs, unbekannter Auswahlwert, doppelter Name in der Datei …) werden mit Zeilennummer gemeldet und übersprungen; der Rest wird importiert.
- Höchstens 5000 Zeilen und 5 MB pro Datei. Bilder werden nicht per CSV importiert.

## Spalten

| Waffen | Fahrzeuge | Inhalt |
|---|---|---|
| `name` | `name` | Pflicht |
| `weapon_type`, `fire_mode`, `brand`, `caliber` | `manufacturer`, `vehicle_class`, `style` | Listenwert als Text |
| `damage`, `accuracy`, `handling`, `range` | `speed`, `acceleration`, `handling_on_road`, `handling_off_road`, `durability` | 0–10, eine Nachkommastelle |
| `magazine_size` | `seats` | ganze Zahl |
| | `rating_on_road`, `rating_off_road` | ganze Zahl 0–1000 |
| `is_legendary` | `registrable` | ja/nein (auch true/false, 1/0) |
| | `drivetrain` | FWD, RWD, AWD |
| | `security` | not_specified (auch „Ohne“, „Nicht angegeben“), basic, modern, advanced |
| | `tracker` | none, standard, advanced, military |
| | `access` | none (auch „Ohne“), key, keyless |
| `notes` | `notes` | Freitext |
| `source_name`, `source_url` | `source_name`, `source_url` | Quelle, angezeigt als „Daten von …“ mit Link |

Die Vorlage in der Oberfläche enthält alle Spalten und eine Beispielzeile.

## NiaDB-Grundbestand

`imports/niadb/` enthält die am 25.11.2026 von https://meowdb.com/gta6/ übernommenen Daten (153 Waffen, 235 Fahrzeuge). NiaDB erlaubt die Weiterverwendung mit Quellenlink; jeder Eintrag verlinkt auf seine NiaDB-Seite. Die Beschreibungen sind englisch wie im Original.

Aktualisieren: Rohdaten in `imports/niadb/raw/` ersetzen (gleiche Spalten), `python3 scripts/niadb_convert.py` ausführen, dann importieren. Bereits vorhandene Einträge werden nur ergänzt.
