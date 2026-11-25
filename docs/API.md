# API

Leonida hat eine JSON-API unter `/api`. Die vollständige, automatisch erzeugte Beschreibung (OpenAPI 3) liegt in [`openapi.json`](openapi.json) und ist im laufenden System nach der Anmeldung unter **/docs/api** interaktiv abrufbar.

Neu erzeugen nach Änderungen:

```
cd backend && php artisan scramble:export --path=../docs/openapi.json
```

## Grundregeln

- **Lesen ist öffentlich**, Schreiben braucht eine Anmeldung, ein aktives Konto und ein geändertes Initialpasswort (sonst `403` mit `code: password_change_required`).
- **Authentifizierung** per Session-Cookie (`POST /api/auth/login`). Jede schreibende Anfrage braucht den Header `X-XSRF-TOKEN` mit dem Wert des Cookies `XSRF-TOKEN` (CSRF-Schutz), sonst `419`.
- Antworten sind JSON. Validierungsfehler: `422` mit `errors: { feld: [meldung] }`, deutsche Meldungen.
- **Unbekannt = `null`**. `0` ist ein echter Wert. Gilt für alle Statistiken, Ratings, Beträge und feste Auswahllisten.

## Endpunkte

| Methode | Pfad | Zweck | Recht |
|---|---|---|---|
| GET | `/api/meta` | feste Auswahllisten mit deutschen Bezeichnungen, Grenzen, Kartenstand, aktueller Benutzer | öffentlich |
| GET | `/api/auth/me` | aktueller Benutzer | öffentlich |
| POST | `/api/auth/login` · `/api/auth/logout` | Anmelden / Abmelden | öffentlich |
| POST | `/api/auth/password` | eigenes Passwort ändern | angemeldet |
| GET | `/api/pins` | kompakte Liste aller Pins für die Karte (mit ETag) | öffentlich |
| GET | `/api/pins/search?q=&kind=&loot[]=&poi[]=&bbox=` | IDs passender Pins (gleiche Regeln wie im Browser) | öffentlich |
| GET | `/api/pins/{id}` | Pin mit Waffen, Fahrzeugen, Codes, Bildern | öffentlich |
| POST / PUT / DELETE | `/api/pins` · `/api/pins/{id}` | Pin anlegen / ändern / löschen | Editor |
| PATCH | `/api/pins/{id}/position` | Pin verschieben | Editor |
| POST | `/api/pins/{id}/images` | Bild hochladen (`multipart/form-data`: `file`, `caption`) | Editor |
| PUT | `/api/pins/{id}/images/order` | Reihenfolge (`ids`) | Editor |
| PATCH / DELETE | `/api/pin-images/{id}` | Beschreibung ändern / Bild löschen | Editor |
| GET | `/api/weapons` · `/api/vehicles` | Übersicht mit Anzahl Fundorte, Filter `q`, Lookup-IDs | öffentlich |
| GET | `/api/weapons/{id}` · `/api/vehicles/{id}` | Detail inkl. Fundorte | öffentlich |
| POST / PUT / DELETE | `/api/weapons…` · `/api/vehicles…` | anlegen / ändern / löschen (`?force=1`, wenn Fundorte zugeordnet sind) | Editor |
| POST / DELETE | `/api/weapons/{id}/image` · `/api/vehicles/{id}/image` | Hauptbild setzen / entfernen | Editor |
| GET | `/api/lookups/{kategorie}` | Werte einer Auswahlliste (`?usage=1` mit Verwendungszahl) | öffentlich |
| POST | `/api/lookups/{kategorie}` | Wert anlegen oder vorhandenen liefern (Groß-/Kleinschreibung egal) | Editor |
| PATCH / DELETE | `/api/lookup-values/{id}` | umbenennen / unbenutzten Wert löschen | Admin |
| POST | `/api/lookup-values/{id}/merge` | in `target_id` zusammenführen | Admin |
| GET / POST / PATCH / DELETE | `/api/users…` | Benutzerverwaltung | Admin |
| GET | `/api/admin/export` | ZIP-Export | Admin |
| POST | `/api/admin/import` | ZIP-Import (`file`, `mode=merge|replace`, `dry_run`) | Admin |
| GET | `/api/audit` | letzte 200 Änderungen | angemeldet |

Kategorien der Auswahllisten: `weapon_type`, `fire_mode`, `weapon_brand`, `caliber`, `vehicle_manufacturer`, `vehicle_class`, `vehicle_style`.

## Wertebereiche

| Feld | Erlaubt |
|---|---|
| Waffen- und Fahrzeugwerte (`stats.*`) | 0–10, höchstens eine Nachkommastelle, oder `null` |
| `rating_on_road`, `rating_off_road` | ganze Zahl 0–1000 oder `null` |
| `drugs_value_usd`, `jewelry_value_usd`, `cash_usd` | ganze Zahl ≥ 0 oder `null`; wird geleert, wenn der Loottyp nicht gewählt ist |
| `kind` | `loot` (Standard) oder `poi`. Orte haben nur `title`, `description`, Bilder und Position; Loot-Felder, Codes und Status werden ignoriert |
| `poi_type` | Pflicht bei `kind=poi`: `poi`, `photo`, `ammunation`, `bar`, `gas_station`, `groceries`, `clothing`, `tattoo`, `garage`, `tuning`, `barber`, `pay_and_spray`, `workshop`, `airport`, `harbor`, `military`, `restaurant`, `apartment`, `race`, `bus_stop`, `train_stop` |
| `loot_types` | `vehicles`, `weapons`, `drugs`, `jewelry`, `cash` |
| `respawn` | `unknown`, `once`, `recurring` |
| `status` | `unconfirmed`, `confirmed`, `gone` |
| `drivetrain` | `FWD`, `RWD`, `AWD` |
| `security` | `not_specified`, `basic`, `modern`, `advanced` |
| `tracker` | `none`, `standard`, `advanced`, `military` |
| `access` | `key`, `keyless` |
| `registrable` | `true`, `false`, `null` |
| `codes[].code` | Text (führende Nullen bleiben erhalten), max. 100 Zeichen |
| `x`, `y` | Spielmeter, −16384 bis 16384 |

## Kompakte Pin-Liste

`GET /api/pins` liefert alle Pins in einem Aufruf, damit Karte und Sidebar ohne weitere Anfragen filtern können:

```json
{ "pins": [ { "id": 1, "k": "loot", "p": null, "t": "Safe im Motel", "x": -2056.0, "y": 1000.0, "l": ["cash", "weapons"], "s": "unconfirmed",
              "d": "Beschreibung", "w": ["Donnies H&L .38"], "v": [], "c": ["Safe 0042"] } ] }
```

Mit `If-None-Match` und dem zuletzt erhaltenen `ETag` antwortet der Server `304`, wenn sich nichts geändert hat.
