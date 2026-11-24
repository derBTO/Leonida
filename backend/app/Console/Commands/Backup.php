<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

/** Nächtliches Datenbank-Backup mit pg_dump (per Cronjob), alte Dumps werden aufgeräumt. */
class Backup extends Command
{
    protected $signature = 'leonida:backup {--keep=14 : Anzahl aufzubewahrender Dumps}';

    protected $description = 'Datenbank mit pg_dump sichern';

    public function handle(): int
    {
        $dir = rtrim(config('leonida.backup_dir'), '/');
        if (! is_dir($dir) && ! mkdir($dir, 0700, true)) {
            $this->error("Backup-Verzeichnis $dir kann nicht angelegt werden.");

            return self::FAILURE;
        }
        $db = config('database.connections.pgsql');
        $file = "$dir/leonida-".now()->format('Y-m-d-His').'.dump';
        $process = new Process(
            ['pg_dump', '--format=custom', '--no-owner', '--host='.$db['host'], '--port='.$db['port'], '--username='.$db['username'], '--file='.$file, $db['database']],
            null,
            ['PGPASSWORD' => (string) $db['password']],
            null,
            600
        );
        $process->run();
        if (! $process->isSuccessful()) {
            $this->error('pg_dump fehlgeschlagen: '.trim($process->getErrorOutput()));
            @unlink($file);

            return self::FAILURE;
        }
        chmod($file, 0600);
        $dumps = glob("$dir/leonida-*.dump") ?: [];
        rsort($dumps);
        foreach (array_slice($dumps, (int) $this->option('keep')) as $old) {
            unlink($old);
        }
        $this->info(sprintf('Backup %s (%.1f MB)', basename($file), filesize($file) / 1e6));

        return self::SUCCESS;
    }
}
