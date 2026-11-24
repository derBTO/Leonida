<?php

namespace App\Console\Commands;

use App\Models\MapVersion;
use Illuminate\Console\Command;

/** Liest map/current/manifest.json und trägt die aktive Kartenversion in die Datenbank ein. */
class RegisterMapVersion extends Command
{
    protected $signature = 'leonida:map-register';

    protected $description = 'Aktive Kartenversion aus dem Manifest in der Datenbank registrieren';

    public function handle(): int
    {
        $file = rtrim(config('leonida.map_dir'), '/').'/current/manifest.json';
        if (! is_file($file)) {
            $this->warn("Kein Manifest unter $file – Karte noch nicht hochgeladen?");

            return self::SUCCESS;
        }
        $manifest = json_decode((string) file_get_contents($file), true);
        if (! is_array($manifest) || empty($manifest['version'])) {
            $this->error("Manifest $file ist ungültig.");

            return self::FAILURE;
        }
        $version = MapVersion::query()->firstOrCreate(
            ['version' => $manifest['version']],
            ['source_commit' => $manifest['source']['commit'] ?? null, 'transform' => $manifest['landmarkComparison']['transform'] ?? null]
        );
        if (! $version->is_active) {
            MapVersion::query()->where('is_active', true)->update(['is_active' => false]);
            $version->update(['is_active' => true]);
        }
        $this->info("Aktive Kartenversion: {$version->version}");

        return self::SUCCESS;
    }
}
