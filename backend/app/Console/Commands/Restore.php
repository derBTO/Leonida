<?php

namespace App\Console\Commands;

use App\Services\DatabaseBackup;
use Illuminate\Console\Command;

/** Stellt eine PHP-Sicherung (*.json.gz) wieder her. Für *.dump siehe docs/BACKUP.md (pg_restore). */
class Restore extends Command
{
    protected $signature = 'leonida:restore
        {file : Pfad zur Sicherung (leonida-….json.gz)}
        {--check : Nur prüfen und zählen, nichts ändern}
        {--force : Ohne Rückfrage}';

    protected $description = 'Datenbank aus einer Sicherung wiederherstellen (überschreibt alle Daten)';

    public function handle(DatabaseBackup $backup): int
    {
        $file = $this->argument('file');
        if (str_ends_with($file, '.dump')) {
            $this->error('Das ist ein pg_dump-Archiv. Wiederherstellen mit pg_restore, siehe docs/BACKUP.md.');

            return self::FAILURE;
        }
        $check = (bool) $this->option('check');
        if (! $check && ! $this->option('force')
            && ! $this->confirm('Alle aktuellen Daten werden durch die Sicherung ersetzt. Fortfahren?')) {
            return self::FAILURE;
        }
        try {
            $counts = $backup->restore($file, $check);
        } catch (\Throwable $e) {
            $this->error('Wiederherstellung fehlgeschlagen, nichts geändert: '.$e->getMessage());

            return self::FAILURE;
        }
        $this->table(['Tabelle', 'Datensätze'], array_map(fn ($t, $n) => [$t, $n], array_keys($counts), $counts));
        $this->info($check ? 'Prüfung erfolgreich, nichts geändert.' : 'Wiederhergestellt.');

        return self::SUCCESS;
    }
}
