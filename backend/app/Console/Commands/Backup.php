<?php

namespace App\Console\Commands;

use App\Services\DatabaseBackup;
use Illuminate\Console\Command;

/**
 * Nächtliches Datenbank-Backup (per Cronjob/Scheduler), alte Sicherungen werden aufgeräumt.
 * Nutzt pg_dump, wenn es zur Serverversion passt, sonst die PHP-Sicherung (siehe DatabaseBackup).
 */
class Backup extends Command
{
    protected $signature = 'leonida:backup
        {--keep=14 : Anzahl aufzubewahrender Sicherungen}
        {--php : pg_dump nicht verwenden, immer die PHP-Sicherung}';

    protected $description = 'Datenbank sichern';

    public function handle(DatabaseBackup $backup): int
    {
        $dir = rtrim(config('leonida.backup_dir'), '/');
        try {
            $result = $backup->create($dir, (bool) $this->option('php'));
        } catch (\Throwable $e) {
            $this->error(now()->format('Y-m-d H:i:s').' Backup fehlgeschlagen: '.$e->getMessage());

            return self::FAILURE;
        }
        $removed = $backup->prune($dir, (int) $this->option('keep'));
        $extra = isset($result['rows']) ? sprintf(', %d Tabellen, %d Datensätze', $result['tables'], $result['rows']) : '';
        $this->info(sprintf('%s Backup %s (%.1f MB, %s%s)%s', now()->format('Y-m-d H:i:s'), $result['file'],
            filesize($result['file']) / 1e6, $result['method'], $extra, $removed ? ", $removed alte gelöscht" : ''));

        return self::SUCCESS;
    }
}
