# Sicherheit

Leonida läuft als öffentlich erreichbare Webanwendung mit Benutzerkonten. Sicherheitslücken bitte **nicht** als öffentliches Issue melden.

## Lücke melden

Bitte über GitHub melden: Reiter **Security → Report a vulnerability** in diesem Repository (private Meldung, nur für die Betreuer sichtbar).

Hilfreich sind:

- betroffener Bereich (z. B. Anmeldung, Rechte, Bild-Upload, API-Pfad),
- Schritte zum Nachvollziehen,
- mögliche Auswirkungen.

Eine erste Rückmeldung gibt es in der Regel innerhalb weniger Tage. Bitte gib uns Zeit, die Lücke zu schließen, bevor du Details veröffentlichst.

## Unterstützte Versionen

Unterstützt wird nur der aktuelle Stand des Branches `main` (siehe [CHANGELOG.md](CHANGELOG.md)).

## Hinweise für eigene Installationen

- Zugangsdaten gehören in `backend/.env` und `deploy.env`; beide sind von Git ausgenommen und dürfen nie committet werden.
- Das Startpasswort des Administrators (`LEONIDA_ADMIN_INITIAL_PASSWORD`) muss beim ersten Login geändert werden.
- Die Passwörter in `docker-compose.yml`, `.env.example` und den Tests sind nur für lokale Entwicklung gedacht.
