# Lokale Entwicklung

## Mit Docker (empfohlen)

```
scripts/map-import.sh --activate
docker compose up
```

- Frontend mit Hot-Reload: http://localhost:5173
- Backend direkt: http://localhost:8000 (liefert das zuletzt gebaute Frontend)
- Anmeldung: **BTO** / `entwicklung-123`, das Passwort muss beim ersten Login geändert werden
- Beim ersten Start legt der Container `backend/.env` mit Entwicklungswerten an. Bilder landen in `data/uploads`.

## Ohne Docker

Voraussetzungen: PHP 8.3+ mit `pdo_pgsql`, `gd` (mit WebP) oder `imagick`, `intl`, `zip`; Composer; Node.js 20+; PostgreSQL 14+.

```
createdb leonida && createdb leonida_test
cd backend
cp .env.example .env              # DB_*, APP_ENV=local, APP_DEBUG=true, SESSION_SECURE_COOKIE=false,
                                  # LEONIDA_*_DIR leer lassen (dann storage/ bzw. ../data/map)
composer install
php artisan key:generate
php artisan migrate
LEONIDA_ADMIN_INITIAL_PASSWORD=entwicklung-123 php artisan leonida:bootstrap-admin
php artisan leonida:link
php artisan leonida:map-register
php artisan serve                 # Backend auf :8000

cd ../frontend
npm install
npm run dev                       # Frontend auf :5173 mit Proxy auf :8000
```

## Tests

| Bereich | Befehl | Inhalt |
|---|---|---|
| Backend | `cd backend && php artisan test` | 75 Tests: Rechte, Validierung, NULL ≠ 0, Suche, Bilder, Export/Import, Benutzer |
| Frontend | `cd frontend && npm test` | Such- und Filterregeln, Kachel-Geometrie |
| Typprüfung | `cd frontend && npm run typecheck` | TypeScript/Vue |
| Skripte | `python3 -m unittest discover -s tests/scripts` | Karten-Manifest, Koordinatenvergleich |

Die Backend-Tests brauchen eine PostgreSQL-Datenbank `leonida_test` (Zugang in `backend/phpunit.xml`).

## Aufbau

- **API** in `backend/routes/web.php` (Präfix `/api`, Session + CSRF), Controller unter `app/Http/Controllers/Api`
- **Datenmodell** in `backend/database/migrations` (PostgreSQL-SQL mit Check-Constraints)
- **Feste Auswahllisten** als PHP-Enums in `backend/app/Enums` (Bezeichnungen auf Deutsch, über `/api/meta` ans Frontend)
- **Such- und Filterregeln** doppelt und gleich: `backend/app/Services/PinIndex.php` und `frontend/src/lib/pinFilter.ts`
- **Karte**: `frontend/src/map` (Koordinatensystem, Kachelpfade, Marker)
