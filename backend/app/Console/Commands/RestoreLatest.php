<?php

namespace App\Console\Commands;

use App\Services\DatabaseBackup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Lokale Testumgebung: spielt die neueste Server-Sicherung aus data/server-backup ein
 * (von scripts/backup-pull.sh dorthin kopiert), aber nur, wenn sie neuer ist als die zuletzt eingespielte.
 *
 * Ablauf: Datenbank leeren → nur die Migrationen der Sicherung ausführen → Daten einspielen →
 * neuere Migrationen des aktuellen Codes ausführen. So passt jede ältere Sicherung zum neueren Code,
 * und nebenbei werden Restore und Migrationen mit echten Daten geprüft. Nie in production.
 */
class RestoreLatest extends Command
{
    protected $signature = 'leonida:restore-latest
        {--dir= : Ordner mit Sicherungen (Standard: LEONIDA_SERVER_BACKUP_DIR bzw. ../data/server-backup)}
        {--force : Auch einspielen, wenn diese Sicherung schon eingespielt wurde}';

    protected $description = 'Lokal: neueste Server-Sicherung einspielen (nur Entwicklung)';

    public function handle(DatabaseBackup $backup): int
    {
        if (app()->environment('production')) {
            $this->error('Nicht in production – dieser Befehl ersetzt alle Daten.');

            return self::FAILURE;
        }
        $dir = rtrim($this->option('dir') ?: (env('LEONIDA_SERVER_BACKUP_DIR') ?: base_path('../data/server-backup')), '/');
        $files = glob("$dir/leonida-*.json.gz") ?: [];
        sort($files);
        $file = end($files);
        if (! $file) {
            $this->line("Keine Server-Sicherung in $dir – überspringe.");

            return self::SUCCESS;
        }
        $marker = storage_path('app/restored-server-backup.txt');
        $stamp = basename($file).' '.filemtime($file);
        if (! $this->option('force') && is_file($marker) && trim((string) file_get_contents($marker)) === $stamp) {
            $this->line('Server-Sicherung '.basename($file).' ist bereits eingespielt.');

            return self::SUCCESS;
        }

        $gz = gzopen($file, 'rb');
        $head = json_decode((string) gzgets($gz), true);
        gzclose($gz);
        if (($head['format'] ?? null) !== DatabaseBackup::FORMAT) {
            $this->error(basename($file).' ist keine Leonida-Sicherung.');

            return self::FAILURE;
        }
        $paths = [];
        foreach ($head['migrations'] as $name) {
            $path = database_path("migrations/$name.php");
            if (! is_file($path)) {
                $this->error("Die Sicherung ist neuer als dieser Code (Migration $name fehlt). Bitte Code aktualisieren.");

                return self::FAILURE;
            }
            $paths[] = $path;
        }

        $this->info('Spiele '.basename($file).' ein …');
        Artisan::call('db:wipe', ['--force' => true]);
        Artisan::call('migrate', ['--force' => true, '--realpath' => true, '--path' => $paths]);
        $counts = $backup->restore($file);
        $newer = count(glob(database_path('migrations/*.php')) ?: []) - count($paths);
        Artisan::call('migrate', ['--force' => true]);
        file_put_contents($marker, $stamp);

        $this->info(sprintf('Eingespielt: %d Datensätze in %d Tabellen, Stand %s.%s',
            array_sum($counts), count($counts), $head['created_at'] ?? '?',
            $newer > 0 ? " Danach $newer neuere Migration(en) ausgeführt." : ''));
        $this->line('Anmelden mit den Zugangsdaten vom Server. Pins: '.DB::table('pins')->count().', Puzzle: '.DB::table('trails')->count().'.');

        return self::SUCCESS;
    }
}
