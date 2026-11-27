# Mitmachen

Danke für dein Interesse an Leonida! Fehlermeldungen, Ideen und Pull Requests sind willkommen. Das Projekt ist auf Deutsch, Issues und Pull Requests also gerne auch.

## Fehler melden und Ideen

- **Fehler:** Issue mit der Vorlage „Fehler melden“ – was du gemacht hast, was passiert ist und was du erwartet hättest.
- **Ideen:** Issue mit der Vorlage „Wunsch / Idee“.
- **Sicherheitslücken** bitte nicht öffentlich, siehe [SECURITY.md](SECURITY.md).

## Lokal entwickeln

Alles Nötige steht in [docs/ENTWICKLUNG.md](docs/ENTWICKLUNG.md). Kurzfassung:

```bash
docker compose up        # http://localhost:5173
```

## Vor einem Pull Request

1. **Tests laufen lassen**
   - Backend: `cd backend && php artisan test`
   - Frontend: `cd frontend && npx vue-tsc --noEmit -p tsconfig.app.json && npx vitest run`
   - Browser-Tests: `cd frontend && npx playwright test` (siehe docs/ENTWICKLUNG.md)
   - PHP-Stil: `cd backend && vendor/bin/pint --test`
2. **Neue Funktionen mit Test**, wo es sinnvoll ist (Backend-Feature-Test oder Playwright).
3. **Doku mitziehen:** Bedienung in `docs/ANLEITUNG.md`, API in `docs/API.md`, Eintrag in `CHANGELOG.md`; grundsätzliche Entscheidungen in `docs/ENTSCHEIDUNGEN.md`.
4. **Oberfläche und Doku auf Deutsch**, Code-Bezeichner auf Englisch.

## Daten und Lizenzen

Der Code steht unter der [MIT-Lizenz](LICENSE). Daten Dritter (Kartenkacheln, Landmarken, Ortsnamen) haben eigene Bedingungen, siehe [NOTICE.md](NOTICE.md). Bitte keine Daten oder Bilder einbringen, deren Weitergabe nicht ausdrücklich erlaubt ist, und keine Grafiken oder Logos aus dem Spiel.
