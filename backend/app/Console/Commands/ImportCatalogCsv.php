<?php

namespace App\Console\Commands;

use App\Services\CatalogCsvImport;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

/** Waffen oder Fahrzeuge aus einer CSV-Datei übernehmen (z. B. data/imports/niadb/waffen.csv). */
class ImportCatalogCsv extends Command
{
    protected $signature = 'leonida:catalog-import
        {kind : weapons oder vehicles}
        {file : Pfad zur CSV-Datei}
        {--apply : Wirklich schreiben (ohne: nur Probelauf)}
        {--overwrite : Vorhandene, abweichende Werte überschreiben}';

    protected $description = 'Waffen oder Fahrzeuge aus CSV importieren';

    public function handle(CatalogCsvImport $import): int
    {
        $kind = $this->argument('kind');
        $file = $this->argument('file');
        if (! in_array($kind, ['weapons', 'vehicles'], true)) {
            $this->error('kind muss „weapons“ oder „vehicles“ sein.');

            return self::FAILURE;
        }
        if (! is_file($file)) {
            $this->error("Datei nicht gefunden: $file");

            return self::FAILURE;
        }

        try {
            $r = $import->import($kind, $file, ! $this->option('apply'), (bool) $this->option('overwrite'));
        } catch (ValidationException $e) {
            $this->error(implode(' ', $e->validator->errors()->all()));

            return self::FAILURE;
        }

        $this->info(($r['dry_run'] ? 'Probelauf' : 'Import')." – {$r['rows']} Zeilen: {$r['created']} neu, {$r['updated']} ergänzt, {$r['unchanged']} unverändert, {$r['skipped']} übersprungen.");
        foreach ($r['lookups_created'] as $category => $names) {
            $this->line("Neue Listenwerte ($category): ".implode(', ', $names));
        }
        foreach ($r['errors'] as $e) {
            $this->warn("Zeile {$e['line']} ({$e['name']}): ".implode(' ', $e['messages']));
        }
        if ($r['conflict_count']) {
            $this->warn("{$r['conflict_count']} abweichende Werte nicht überschrieben (--overwrite ersetzt sie):");
            foreach ($r['conflicts'] as $c) {
                $this->line("  Zeile {$c['line']} {$c['name']} · {$c['field']}: „{$c['current']}“ bleibt, CSV: „{$c['csv']}“");
            }
        }
        if ($r['dry_run']) {
            $this->comment('Nichts gespeichert. Mit --apply wirklich importieren.');
        }

        return self::SUCCESS;
    }
}
