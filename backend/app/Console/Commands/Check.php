<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\MapVersion;
use App\Models\User;
use App\Services\DatabaseBackup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Prüft eine laufende Installation (nach jedem Deploy aufgerufen, auch von Hand nützlich).
 * Ändert nichts. Exit-Code 0 = in Ordnung oder nur Warnungen, 1 = mindestens ein Problem.
 */
class Check extends Command
{
    protected $signature = 'leonida:check {--backup-hours=26 : Ab welchem Alter die letzte Sicherung als veraltet gilt}';

    protected $description = 'Installation prüfen (Konfiguration, Datenbank, Verzeichnisse, Karte, Backup)';

    /** @var list<array{0: string, 1: string, 2: string}> */
    private array $rows = [];

    public function handle(DatabaseBackup $backup): int
    {
        $prod = app()->environment('production');

        // Konfiguration
        $this->add($prod ? 'ok' : 'warn', 'Umgebung', 'APP_ENV='.app()->environment().($prod ? '' : ' (nicht production)'));
        $this->add(config('app.debug') ? ($prod ? 'fail' : 'warn') : 'ok', 'Debug-Modus', config('app.debug') ? 'APP_DEBUG=true – zeigt Fehlerdetails öffentlich' : 'aus');
        $this->add(config('app.key') ? 'ok' : 'fail', 'APP_KEY', config('app.key') ? 'gesetzt' : 'fehlt (php artisan key:generate)');
        $url = (string) config('app.url');
        $this->add(str_starts_with($url, 'https://') || ! $prod ? 'ok' : 'warn', 'APP_URL', $url);
        $this->add(version_compare(PHP_VERSION, '8.3.0', '>=') ? 'ok' : 'fail', 'PHP', PHP_VERSION);
        $missing = array_filter(['pdo_pgsql', 'gd', 'intl', 'zip', 'mbstring', 'fileinfo'], fn ($e) => ! extension_loaded($e));
        $this->add($missing ? 'fail' : 'ok', 'PHP-Erweiterungen', $missing ? 'fehlt: '.implode(', ', $missing) : 'vollständig');

        // Datenbank
        try {
            $version = DB::selectOne('SHOW server_version')->server_version;
            $this->add('ok', 'Datenbank', 'PostgreSQL '.$version);
            $files = array_map(fn ($f) => basename($f, '.php'), glob(database_path('migrations/*.php')) ?: []);
            $ran = DB::table('migrations')->pluck('migration')->all();
            $pending = array_diff($files, $ran);
            $this->add($pending ? 'fail' : 'ok', 'Migrationen', $pending ? 'offen: '.implode(', ', $pending).' (php artisan migrate --force)' : count($ran).' ausgeführt');
            $admins = User::query()->where('role', Role::Admin)->where('is_active', true)->count();
            $this->add($admins ? 'ok' : 'fail', 'Administrator', $admins ? "$admins aktiv" : 'kein aktiver Administrator');
            $map = MapVersion::active();
            $this->add($map ? 'ok' : 'warn', 'Kartenversion', $map ? $map->version : 'keine aktiv (scripts/map-upload.sh)');
            $this->add('ok', 'Datenbestand', sprintf('%d Pins, %d Spuren, %d Waffen, %d Fahrzeuge, %d Benutzer',
                DB::table('pins')->count(), DB::table('trails')->count(), DB::table('weapons')->count(), DB::table('vehicles')->count(), DB::table('users')->count()));
        } catch (\Throwable $e) {
            $this->add('fail', 'Datenbank', 'keine Verbindung: '.$e->getMessage());
        }

        // Verzeichnisse
        foreach ([
            'Bilder' => config('leonida.upload_dir'),
            'Backups' => config('leonida.backup_dir'),
            'Logs' => storage_path('logs'),
            'Cache' => storage_path('framework/cache'),
            'Sitzungen' => storage_path('framework/sessions'),
        ] as $label => $dir) {
            $ok = is_dir($dir) && is_writable($dir);
            $this->add($ok ? 'ok' : ($label === 'Backups' && ! is_dir($dir) ? 'warn' : 'fail'), "Verzeichnis $label", $dir.($ok ? '' : (is_dir($dir) ? ' (nicht beschreibbar)' : ' (fehlt)')));
        }
        $manifest = rtrim((string) config('leonida.map_dir'), '/').'/current/manifest.json';
        $this->add(is_file($manifest) ? 'ok' : 'warn', 'Kartenkacheln', is_file($manifest) ? $manifest : "$manifest fehlt");
        if ($public = config('leonida.public_dir')) {
            $spa = is_file("$public/spa/index.html") && is_file("$public/index.php");
            $this->add($spa ? 'ok' : 'fail', 'Document Root', $public.($spa ? '' : ' (index.php oder spa/index.html fehlt)'));
        }

        // Backup
        $dir = rtrim((string) config('leonida.backup_dir'), '/');
        $files = array_merge(glob("$dir/leonida-*.dump") ?: [], glob("$dir/leonida-*.json.gz") ?: []);
        usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));
        if (! $files) {
            $this->add('warn', 'Letzte Sicherung', 'noch keine (php artisan leonida:backup, Cronjob siehe docs/INSTALLATION.md)');
        } else {
            $hours = (time() - filemtime($files[0])) / 3600;
            $limit = (int) $this->option('backup-hours');
            $this->add($hours <= $limit ? 'ok' : 'warn', 'Letzte Sicherung', sprintf('%s, vor %.1f Std.%s (%d vorhanden)', basename($files[0]), $hours, $hours > $limit ? ' – läuft der Cronjob?' : '', count($files)));
        }
        $log = storage_path('logs/backup.log');
        if (is_file($log)) {
            $lines = array_values(array_filter(array_map('trim', file($log) ?: [])));
            $last = end($lines) ?: '';
            $this->add(str_contains($last, 'fehlgeschlagen') ? 'fail' : 'ok', 'Backup-Protokoll', mb_strimwidth($last, 0, 110, '…'));
        }
        try {
            $pgDump = $backup->findPgDump();
            $this->add('ok', 'Backup-Verfahren', $pgDump ? "pg_dump ($pgDump)" : 'PHP-Sicherung (kein passendes pg_dump)');
        } catch (\Throwable) {
            // Datenbank nicht erreichbar, oben schon gemeldet
        }

        $symbols = ['ok' => '<fg=green>OK</>', 'warn' => '<fg=yellow>Warnung</>', 'fail' => '<fg=red>Problem</>'];
        $this->table(['', 'Prüfung', 'Ergebnis'], array_map(fn ($r) => [$symbols[$r[0]], $r[1], $r[2]], $this->rows));
        $fails = count(array_filter($this->rows, fn ($r) => $r[0] === 'fail'));
        $warns = count(array_filter($this->rows, fn ($r) => $r[0] === 'warn'));
        if ($fails) {
            $this->error("$fails Problem(e), $warns Warnung(en).");

            return self::FAILURE;
        }
        $warns ? $this->warn("Keine Probleme, $warns Warnung(en).") : $this->info('Alles in Ordnung.');

        return self::SUCCESS;
    }

    private function add(string $status, string $label, string $detail): void
    {
        $this->rows[] = [$status, $label, $detail];
    }
}
