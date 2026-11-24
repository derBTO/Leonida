<?php

namespace App\Console\Commands;

use App\Models\MapVersion;
use App\Services\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Rechnet alle Pin-Koordinaten mit einer affinen Transformation um, falls gtadb.org das
 * Koordinatensystem ändert. Die Transformation stammt aus dem Landmarken-Vergleich des
 * Karten-Imports (manifest.json → landmarkComparison.transform).
 */
class MigrateCoordinates extends Command
{
    protected $signature = 'leonida:migrate-coords
        {--manifest= : Manifest der neuen Kartenversion (Standard: map/current/manifest.json)}
        {--transform= : Alternativ: a,b,c,d,e,f für x\'=a·x+b·y+c, y\'=d·x+e·y+f}
        {--apply : Wirklich ändern (ohne: nur Vorschau)}';

    protected $description = 'Pin-Koordinaten an ein geändertes Kartensystem anpassen';

    public function handle(): int
    {
        if ($this->option('transform')) {
            $t = array_map('floatval', explode(',', $this->option('transform')));
            $version = null;
        } else {
            $file = $this->option('manifest') ?: rtrim(config('leonida.map_dir'), '/').'/current/manifest.json';
            $manifest = json_decode((string) @file_get_contents($file), true);
            $t = $manifest['landmarkComparison']['transform'] ?? null;
            $version = $manifest['version'] ?? null;
        }
        if (! is_array($t) || count($t) !== 6) {
            $this->error('Keine gültige Transformation gefunden.');

            return self::FAILURE;
        }
        [$a, $b, $c, $d, $e, $f] = $t;
        $pins = DB::table('pins')->get(['id', 'title', 'x', 'y']);
        $moves = $pins->map(function ($p) use ($a, $b, $c, $d, $e, $f) {
            $nx = round($a * $p->x + $b * $p->y + $c, 2);
            $ny = round($d * $p->x + $e * $p->y + $f, 2);

            return ['id' => $p->id, 'title' => $p->title, 'x' => $nx, 'y' => $ny, 'shift' => hypot($nx - $p->x, $ny - $p->y)];
        });
        $this->info(sprintf('%d Pins, Verschiebung max. %.2f m, im Mittel %.2f m', $moves->count(), $moves->max('shift') ?? 0, $moves->avg('shift') ?? 0));
        $this->table(['ID', 'Titel', 'Verschiebung (m)'], $moves->sortByDesc('shift')->take(10)->map(fn ($m) => [$m['id'], $m['title'], round($m['shift'], 2)]));
        if (! $this->option('apply')) {
            $this->warn('Nur Vorschau. Mit --apply übernehmen (vorher Backup!).');

            return self::SUCCESS;
        }
        DB::transaction(function () use ($moves, $version, $t) {
            $versionId = $version ? MapVersion::query()->firstOrCreate(['version' => $version], ['transform' => $t])->id : null;
            foreach ($moves as $m) {
                DB::table('pins')->where('id', $m['id'])->update(array_filter(['x' => $m['x'], 'y' => $m['y'], 'map_version_id' => $versionId], fn ($v) => $v !== null));
            }
            AuditLogger::log('migrate', 'pins', null, 'Koordinaten umgerechnet', ['transform' => $t, 'pins' => $moves->count()]);
        });
        $this->info('Koordinaten umgerechnet.');

        return self::SUCCESS;
    }
}
