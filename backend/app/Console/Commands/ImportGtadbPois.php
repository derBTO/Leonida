<?php

namespace App\Console\Commands;

use App\Enums\PinKind;
use App\Enums\PoiType;
use App\Models\MapVersion;
use App\Models\Pin;
use App\Services\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

/**
 * Übernimmt markierte Orte (Tags wie #gas, #bus) aus den gtadb.org-Landmarken (CC BY 4.0)
 * als POI-Pins. Jeder Ort bekommt eine feste UUID aus seiner Landmarken-ID; ein erneuter Lauf
 * legt nur neue Orte an und überschreibt keine eigenen Änderungen.
 */
class ImportGtadbPois extends Command
{
    protected $signature = 'leonida:import-gtadb-pois
        {--file= : landmarks.json (Standard: map/current/landmarks.json)}
        {--apply : Wirklich anlegen (ohne: nur Vorschau)}';

    protected $description = 'POIs aus den gtadb.org-Landmarken übernehmen';

    /** gtadb-Tag → POI-Kategorie (erster passender Tag gewinnt) */
    public const TAGS = [
        '#gas' => PoiType::GasStation,
        '#supermarket' => PoiType::Groceries,
        '#liquor' => PoiType::Groceries,
        '#bus' => PoiType::BusStop,
        '#train' => PoiType::TrainStop,
        '#metro' => PoiType::TrainStop,
        '#metromover' => PoiType::TrainStop,
        '#marina' => PoiType::Harbor,
        '#hair' => PoiType::Barber,
        '#airport' => PoiType::Airport,
        '#airbase' => PoiType::Military,
        '#clothes' => PoiType::Clothing,
        '#shoes' => PoiType::Clothing,
        '#guns' => PoiType::AmmuNation,
        '#bar' => PoiType::Bar,
        '#nightclub' => PoiType::Bar,
        '#stripclub' => PoiType::Bar,
        '#racetrack' => PoiType::Race,
        '#modshop' => PoiType::Tuning,
        "#pay'n'spray" => PoiType::PayAndSpray,
        '#tattoos' => PoiType::Tattoo,
        '#safehouse' => PoiType::Apartment,
        '#food' => PoiType::Restaurant,
        '#fastfood' => PoiType::Restaurant,
    ];

    private const SKIP = ['demolished', '//may-not-exist'];

    public function handle(): int
    {
        $file = $this->option('file') ?: rtrim(config('leonida.map_dir'), '/').'/current/landmarks.json';
        $data = json_decode((string) @file_get_contents($file), true);
        if (! is_array($data)) {
            $this->error("Landmarken nicht gefunden oder ungültig: $file");

            return self::FAILURE;
        }

        $rows = [];
        foreach ($data as $id => $lm) {
            $tags = $lm[6] ?? [];
            if (! is_array($tags) || array_intersect($tags, self::SKIP)) {
                continue;
            }
            $type = null;
            foreach ($tags as $tag) {
                if (isset(self::TAGS[$tag])) {
                    $type = self::TAGS[$tag];
                    break;
                }
            }
            [$x, $y] = array_pad((array) ($lm[1] ?? []), 2, null);
            if (! $type || ! is_numeric($x) || ! is_numeric($y)) {
                continue;
            }
            $name = trim((string) $lm[0]);
            if ($name === '' || str_starts_with($name, '?')) {
                $name = $type->label().ltrim(substr($name, 1), ' ');
            }
            $real = trim((string) ($lm[3] ?? ''));
            $rows[] = [
                'uuid' => Uuid::uuid5(Uuid::NAMESPACE_URL, "https://map.gtadb.org/#VI,$id")->toString(),
                'id' => $id,
                'type' => $type,
                'title' => mb_substr($name, 0, 150),
                'x' => round((float) $x, 2),
                'y' => round((float) $y, 2),
                'description' => "Aus gtadb.org übernommen (CC BY 4.0): https://map.gtadb.org/#VI,$id"
                    .($real !== '' && ! str_starts_with($real, '?') ? "\nVorbild: $real" : ''),
            ];
        }

        $existing = Pin::query()->whereIn('uuid', array_column($rows, 'uuid'))->pluck('uuid')->flip();
        $new = array_values(array_filter($rows, fn ($r) => ! isset($existing[$r['uuid']])));
        $counts = [];
        foreach ($new as $r) {
            $counts[$r['type']->label()] = ($counts[$r['type']->label()] ?? 0) + 1;
        }
        ksort($counts);
        $this->table(['Kategorie', 'Neu'], array_map(fn ($k, $v) => [$k, $v], array_keys($counts), $counts));
        $this->info(sprintf('%d Orte gefunden, %d schon vorhanden, %d neu.', count($rows), count($existing), count($new)));
        if (! $this->option('apply')) {
            $this->warn('Nur Vorschau. Mit --apply anlegen.');

            return self::SUCCESS;
        }

        $versionId = MapVersion::active()?->id;
        DB::transaction(function () use ($new, $versionId) {
            foreach ($new as $r) {
                Pin::query()->create([
                    'uuid' => $r['uuid'],
                    'kind' => PinKind::Poi,
                    'poi_type' => $r['type'],
                    'title' => $r['title'],
                    'description' => $r['description'],
                    'x' => $r['x'],
                    'y' => $r['y'],
                    'map_version_id' => $versionId,
                    'status' => 'confirmed',
                    'respawn' => 'unknown',
                ]);
            }
            AuditLogger::log('import', 'pins', null, 'POIs aus gtadb.org', ['created' => count($new)]);
        });
        $this->info(count($new).' Orte angelegt.');

        return self::SUCCESS;
    }
}
