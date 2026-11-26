<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Datenbank-Sicherung ohne Abhängigkeit von der pg_dump-Version des Servers.
 *
 * - Gibt es ein pg_dump, das mindestens so neu ist wie der Datenbankserver, entsteht ein Dump im
 *   Custom-Format (*.dump, Wiederherstellen mit pg_restore).
 * - Sonst (z. B. Hetzner: pg_dump 15, Server 17) sichert PHP alle Tabellen als JSON-Zeilen,
 *   gzip-komprimiert (*.json.gz). Das Schema kommt aus den Migrationen; wiederhergestellt wird mit
 *   `php artisan leonida:restore`.
 *
 * Aufbau *.json.gz: erste Zeile Kopf {format, version, created_at, migrations}, dann je Tabelle eine
 * Zeile {table, columns} gefolgt von einer Zeile pro Datensatz (JSON-Array in Spaltenreihenfolge).
 */
class DatabaseBackup
{
    public const FORMAT = 'leonida-backup';

    public const VERSION = 1;

    /** Flüchtige Tabellen, die nicht gesichert werden */
    public const SKIP = ['cache', 'cache_locks', 'sessions', 'jobs', 'job_batches', 'failed_jobs', 'password_reset_tokens'];

    /** @return array{file: string, method: string, tables?: int, rows?: int} */
    public function create(string $dir, bool $forcePhp = false): array
    {
        if (! is_dir($dir) && ! mkdir($dir, 0700, true)) {
            throw new RuntimeException("Backup-Verzeichnis $dir kann nicht angelegt werden.");
        }
        $stamp = now()->format('Y-m-d-His');
        $pgDump = $forcePhp ? null : $this->findPgDump();
        if ($pgDump) {
            $file = "$dir/leonida-$stamp.dump";
            $this->runPgDump($pgDump, $file);
            chmod($file, 0600);

            return ['file' => $file, 'method' => "pg_dump ($pgDump)"];
        }
        $file = "$dir/leonida-$stamp.json.gz";
        $stats = $this->dumpJson($file);
        chmod($file, 0600);

        return ['file' => $file, 'method' => 'PHP (JSON)'] + $stats;
    }

    /** Ältere Sicherungen über $keep hinaus löschen (beide Formate gemeinsam, nach Datum im Namen). */
    public function prune(string $dir, int $keep): int
    {
        $files = array_merge(glob("$dir/leonida-*.dump") ?: [], glob("$dir/leonida-*.json.gz") ?: []);
        usort($files, fn ($a, $b) => strcmp(basename($b), basename($a)));
        $old = array_slice($files, max(0, $keep));
        foreach ($old as $file) {
            unlink($file);
        }

        return count($old);
    }

    /** Hauptversion des Datenbankservers, z. B. 17 */
    public function serverMajor(): int
    {
        return intdiv((int) DB::selectOne('SHOW server_version_num')->server_version_num, 10000);
    }

    /** Pfad eines pg_dump, das mindestens die Serverversion hat, sonst null. */
    public function findPgDump(): ?string
    {
        $candidates = array_filter([config('leonida.pg_dump'), 'pg_dump', ...(glob('/usr/lib/postgresql/*/bin/pg_dump') ?: [])]);
        $server = $this->serverMajor();
        $best = null;
        $bestMajor = 0;
        foreach (array_unique($candidates) as $bin) {
            $p = new Process([$bin, '--version']);
            $p->run();
            if ($p->isSuccessful() && preg_match('/\b(\d+)(?:\.\d+)+/', $p->getOutput(), $m) && (int) $m[1] > $bestMajor) {
                $best = $bin;
                $bestMajor = (int) $m[1];
            }
        }

        return $best !== null && $bestMajor >= $server ? $best : null;
    }

    private function runPgDump(string $bin, string $file): void
    {
        $db = config('database.connections.pgsql');
        $process = new Process(
            [$bin, '--format=custom', '--no-owner', '--host='.$db['host'], '--port='.$db['port'], '--username='.$db['username'], '--file='.$file, $db['database']],
            null, ['PGPASSWORD' => (string) $db['password']], null, 600,
        );
        $process->run();
        if (! $process->isSuccessful()) {
            @unlink($file);
            throw new RuntimeException('pg_dump fehlgeschlagen: '.trim($process->getErrorOutput()));
        }
    }

    /** @return array{tables: int, rows: int} */
    private function dumpJson(string $file): array
    {
        $tmp = "$file.part";
        $gz = gzopen($tmp, 'wb6');
        if (! $gz) {
            throw new RuntimeException("$tmp kann nicht geschrieben werden.");
        }
        $rows = 0;
        $tables = $this->tables();
        try {
            // Ein Snapshot für alle Tabellen, damit die Sicherung in sich stimmig ist
            $outer = DB::transactionLevel() === 0;
            DB::transaction(function () use ($gz, $tables, &$rows, $outer) {
                if ($outer) {
                    DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY');
                }
                $this->writeLine($gz, [
                    'format' => self::FORMAT, 'version' => self::VERSION, 'created_at' => now()->toIso8601String(),
                    'migrations' => $this->migrations(),
                ]);
                foreach ($tables as $table) {
                    $columns = $this->columns($table);
                    $this->writeLine($gz, ['table' => $table, 'columns' => $columns]);
                    $select = implode(', ', array_map(fn ($c) => '"'.$c.'"', $columns));
                    foreach (DB::cursor("SELECT $select FROM \"$table\" ORDER BY 1") as $row) {
                        $this->writeLine($gz, array_values((array) $row));
                        $rows++;
                    }
                }
            });
        } catch (\Throwable $e) {
            gzclose($gz);
            @unlink($tmp);
            throw $e;
        }
        gzclose($gz);
        rename($tmp, $file);

        return ['tables' => count($tables), 'rows' => $rows];
    }

    /**
     * Stellt eine *.json.gz-Sicherung wieder her: leert alle gesicherten Tabellen und füllt sie neu,
     * alles in einer Transaktion. Die Datenbank muss auf demselben Migrationsstand sein.
     *
     * @return array<string, int> Datensätze je Tabelle
     */
    public function restore(string $file, bool $dryRun = false): array
    {
        $gz = @gzopen($file, 'rb');
        if (! $gz) {
            throw new RuntimeException("$file kann nicht gelesen werden.");
        }
        try {
            $head = json_decode((string) gzgets($gz), true);
            if (($head['format'] ?? null) !== self::FORMAT || ($head['version'] ?? 0) > self::VERSION) {
                throw new RuntimeException('Das ist keine Leonida-Sicherung im JSON-Format.');
            }
            $missing = array_diff($head['migrations'], $this->migrations());
            $extra = array_diff($this->migrations(), $head['migrations']);
            if ($missing || $extra) {
                throw new RuntimeException('Migrationsstand passt nicht zur Sicherung. '
                    .($missing ? 'Fehlt in der Datenbank: '.implode(', ', $missing).'. ' : '')
                    .($extra ? 'Neuer als die Sicherung: '.implode(', ', $extra).'. Bitte die passende Programmversion einspielen.' : ''));
            }

            $counts = [];
            DB::beginTransaction();
            try {
                $tables = $this->tables();
                $data = array_values(array_filter($tables, fn ($t) => $t !== 'migrations'));
                // Selbstbezüge (Hinweis → Ort) erst am Ende der Transaktion prüfen
                DB::statement('SET CONSTRAINTS ALL DEFERRED');
                if ($data) {
                    DB::statement('TRUNCATE '.implode(', ', array_map(fn ($t) => "\"$t\"", $data)).' RESTART IDENTITY CASCADE');
                }
                $table = null;
                $columns = [];
                $batch = [];
                $flush = function () use (&$batch, &$table) {
                    if ($batch && $table !== 'migrations') {
                        DB::table($table)->insert($batch);
                    }
                    $batch = [];
                };
                while (($line = gzgets($gz)) !== false) {
                    $item = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                    if (is_array($item) && array_key_exists('table', $item) && ! array_is_list($item)) {
                        $flush();
                        $table = $item['table'];
                        if (! in_array($table, $tables, true)) {
                            throw new RuntimeException("Unbekannte Tabelle in der Sicherung: $table");
                        }
                        $columns = $item['columns'];
                        $counts[$table] = 0;

                        continue;
                    }
                    $batch[] = array_combine($columns, $item);
                    $counts[$table]++;
                    if (count($batch) >= 500) {
                        $flush();
                    }
                }
                $flush();
                $this->resetSequences($data);
                $dryRun ? DB::rollBack() : DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                throw $e;
            }

            return $counts;
        } finally {
            gzclose($gz);
        }
    }

    /** Tabellen in einer Reihenfolge, in der Fremdschlüssel immer auf bereits gefüllte Tabellen zeigen. */
    public function tables(): array
    {
        $all = collect(DB::select("SELECT table_name FROM information_schema.tables WHERE table_schema = current_schema() AND table_type = 'BASE TABLE'"))
            ->pluck('table_name')->reject(fn ($t) => in_array($t, self::SKIP, true))->sort()->values()->all();
        $deps = [];
        foreach (DB::select("SELECT DISTINCT c.relname AS child, p.relname AS parent FROM pg_constraint k
            JOIN pg_class c ON c.oid = k.conrelid JOIN pg_class p ON p.oid = k.confrelid
            JOIN pg_namespace n ON n.oid = c.relnamespace
            WHERE k.contype = 'f' AND n.nspname = current_schema() AND c.relname <> p.relname") as $r) {
            $deps[$r->child][] = $r->parent;
        }
        $ordered = [];
        $visit = function (string $t, array $path = []) use (&$visit, &$ordered, $deps, $all) {
            if (in_array($t, $ordered, true) || ! in_array($t, $all, true)) {
                return;
            }
            if (in_array($t, $path, true)) {
                throw new RuntimeException('Zyklische Fremdschlüssel: '.implode(' → ', [...$path, $t]));
            }
            foreach ($deps[$t] ?? [] as $parent) {
                $visit($parent, [...$path, $t]);
            }
            $ordered[] = $t;
        };
        foreach ($all as $t) {
            $visit($t);
        }

        return $ordered;
    }

    /** Gespeicherte Spalten: ohne berechnete (GENERATED) Spalten */
    private function columns(string $table): array
    {
        return collect(DB::select("SELECT column_name FROM information_schema.columns
            WHERE table_schema = current_schema() AND table_name = ? AND is_generated = 'NEVER' ORDER BY ordinal_position", [$table]))
            ->pluck('column_name')->all();
    }

    private function migrations(): array
    {
        return DB::table('migrations')->orderBy('migration')->pluck('migration')->all();
    }

    private function resetSequences(array $tables): void
    {
        foreach ($tables as $table) {
            foreach (DB::select('SELECT column_name, pg_get_serial_sequence(?, column_name) AS seq FROM information_schema.columns
                WHERE table_schema = current_schema() AND table_name = ?', ["\"$table\"", $table]) as $c) {
                if ($c->seq) {
                    DB::statement("SELECT setval(?, COALESCE((SELECT max(\"{$c->column_name}\") FROM \"$table\"), 0) + 1, false)", [$c->seq]);
                }
            }
        }
    }

    private function writeLine($gz, array $value): void
    {
        gzwrite($gz, json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR)."\n");
    }
}
