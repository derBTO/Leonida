<?php

namespace App\Services;

use App\Enums\Drivetrain;
use App\Enums\LookupCategory;
use App\Enums\Tracker;
use App\Enums\VehicleAccess;
use App\Enums\VehicleSecurity;
use App\Models\LookupValue;
use App\Models\Vehicle;
use App\Models\Weapon;
use App\Rules\StatValue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * CSV-Import für Waffen und Fahrzeuge.
 *
 * - Erste Zeile = Spaltennamen (siehe columns()); Trennzeichen „,“, „;“ oder Tab.
 * - Zuordnung über den Namen (Groß-/Kleinschreibung und Leerraum egal).
 * - Leere Zelle = „keine Angabe“: vorhandene Werte bleiben, neue Einträge erhalten NULL.
 * - Vorhandene Werte werden nur mit $overwrite ersetzt; sonst als Abweichung gemeldet.
 * - Listenwerte (Typ, Hersteller …) werden gesucht oder neu angelegt.
 * - Probelauf: alles läuft in einer Transaktion, die am Ende zurückgerollt wird.
 */
class CatalogCsvImport
{
    public const MAX_ROWS = 5000;

    private const MAX_LISTED = 200;

    public function __construct(private LookupService $lookups) {}

    /** Spalten je Katalog: CSV-Spalte => [Typ, DB-Spalte, Zusatz]. */
    public static function columns(string $kind): array
    {
        $common = [
            'name' => ['name', 'name'],
            'notes' => ['text', 'notes'],
            'source_name' => ['source_name', 'source_name'],
            'source_url' => ['url', 'source_url'],
        ];

        return match ($kind) {
            'weapons' => [
                'name' => $common['name'],
                'weapon_type' => ['lookup', 'weapon_type_id', LookupCategory::WeaponType],
                'fire_mode' => ['lookup', 'fire_mode_id', LookupCategory::FireMode],
                'brand' => ['lookup', 'brand_id', LookupCategory::WeaponBrand],
                'caliber' => ['lookup', 'caliber_id', LookupCategory::Caliber],
                'magazine_size' => ['int', 'magazine_size', [0, 10000]],
                'damage' => ['stat', 'damage'],
                'accuracy' => ['stat', 'accuracy'],
                'handling' => ['stat', 'handling'],
                'range' => ['stat', 'range'],
                'is_legendary' => ['bool', 'is_legendary'],
            ] + $common,
            'vehicles' => [
                'name' => $common['name'],
                'manufacturer' => ['lookup', 'manufacturer_id', LookupCategory::VehicleManufacturer],
                'vehicle_class' => ['lookup', 'class_id', LookupCategory::VehicleClass],
                'style' => ['lookup', 'style_id', LookupCategory::VehicleStyle],
                'drivetrain' => ['enum', 'drivetrain', Drivetrain::class],
                'seats' => ['int', 'seats', [0, 100]],
                'security' => ['enum', 'security', VehicleSecurity::class],
                'tracker' => ['enum', 'tracker', Tracker::class],
                'access' => ['enum', 'access', VehicleAccess::class],
                'registrable' => ['bool', 'registrable'],
                'speed' => ['stat', 'speed'],
                'acceleration' => ['stat', 'acceleration'],
                'handling_on_road' => ['stat', 'handling_on_road'],
                'handling_off_road' => ['stat', 'handling_off_road'],
                'durability' => ['stat', 'durability'],
                'rating_on_road' => ['int', 'rating_on_road', [0, 1000]],
                'rating_off_road' => ['int', 'rating_off_road', [0, 1000]],
            ] + $common,
            default => throw ValidationException::withMessages(['kind' => 'Unbekannter Katalog.']),
        };
    }

    /** Leere Vorlage (Kopfzeile + Beispielzeile) zum Herunterladen. */
    public static function template(string $kind): string
    {
        $example = match ($kind) {
            'weapons' => ['name' => 'Beispielpistole', 'weapon_type' => 'Pistole', 'magazine_size' => '12', 'damage' => '4.2',
                'accuracy' => '5.2', 'handling' => '8.4', 'range' => '4.4', 'is_legendary' => 'nein', 'notes' => 'Freitext'],
            default => ['name' => 'Beispielwagen', 'manufacturer' => 'Grotti', 'vehicle_class' => 'Auto', 'drivetrain' => 'RWD',
                'seats' => '2', 'speed' => '6.6', 'acceleration' => '5.9', 'rating_on_road' => '633', 'rating_off_road' => '350'],
        };
        $header = array_keys(self::columns($kind));
        $out = fopen('php://temp', 'w+');
        fputcsv($out, $header, ',', '"', '');
        fputcsv($out, array_map(fn ($c) => $example[$c] ?? '', $header), ',', '"', '');
        rewind($out);

        return (string) stream_get_contents($out);
    }

    /**
     * @param  'weapons'|'vehicles'  $kind
     */
    public function import(string $kind, string $path, bool $dryRun, bool $overwrite = false): array
    {
        $columns = self::columns($kind);
        $rows = $this->readCsv($path, $columns);
        /** @var class-string<Model> $class */
        $class = $kind === 'weapons' ? Weapon::class : Vehicle::class;
        $entity = $kind === 'weapons' ? 'weapon' : 'vehicle';

        $report = ['kind' => $kind, 'dry_run' => $dryRun, 'overwrite' => $overwrite, 'rows' => count($rows),
            'created' => 0, 'updated' => 0, 'unchanged' => 0, 'skipped' => 0,
            'errors' => [], 'conflicts' => [], 'conflict_count' => 0, 'lookups_created' => []];

        // 1. Zeilen prüfen und in Datenbankwerte umwandeln (noch ohne Schreibzugriff).
        $parsed = [];
        $seen = [];
        foreach ($rows as [$line, $cells]) {
            [$values, $messages] = $this->parseRow($cells, $columns);
            $key = LookupValue::keyFor($values['name'] ?? '');
            if ($key !== '' && isset($seen[$key])) {
                $messages[] = "Name kommt in der Datei doppelt vor (zuerst in Zeile {$seen[$key]}).";
            }
            if ($messages) {
                $report['skipped']++;
                if (count($report['errors']) < self::MAX_LISTED) {
                    $report['errors'][] = ['line' => $line, 'name' => $values['name'] ?? null, 'messages' => $messages];
                }

                continue;
            }
            $seen[$key] = $line;
            $parsed[] = [$line, $key, $values];
        }

        // 2. Schreiben (im Probelauf anschließend zurückrollen).
        $existing = [];
        foreach ($class::query()->get() as $item) {
            $existing[LookupValue::keyFor($item->name)] ??= $item;
        }

        DB::beginTransaction();
        try {
            foreach ($parsed as [$line, $key, $values]) {
                $attrs = [];
                foreach ($values as $column => $value) {
                    [$type, $field] = $columns[$column];
                    if ($type === 'lookup') {
                        [$lookup, $created] = $this->lookups->findOrCreate($columns[$column][2], $value);
                        if ($created) {
                            $report['lookups_created'][$columns[$column][2]->value][] = $lookup->name;
                        }
                        $value = $lookup->id;
                    }
                    $attrs[$field] = $value;
                }

                $item = $existing[$key] ?? null;
                if (! $item) {
                    $item = $class::query()->create($attrs + [
                        'uuid' => (string) Str::uuid(),
                        'created_by' => Auth::id(),
                        'updated_by' => Auth::id(),
                    ]);
                    $existing[$key] = $item;
                    $report['created']++;

                    continue;
                }

                $changes = [];
                foreach ($attrs as $field => $value) {
                    if ($field === 'name') {
                        continue;
                    }
                    $current = $item->getRawOriginal($field);
                    if ($current === null) {
                        $changes[$field] = $value;
                    } elseif (! $this->same($current, $value)) {
                        if ($overwrite) {
                            $changes[$field] = $value;
                        } else {
                            $report['conflict_count']++;
                            if (count($report['conflicts']) < self::MAX_LISTED) {
                                $report['conflicts'][] = ['line' => $line, 'name' => $item->name, 'field' => $field,
                                    'current' => $this->display($field, $current), 'csv' => $this->display($field, $value)];
                            }
                        }
                    }
                }
                if (! $changes) {
                    $report['unchanged']++;

                    continue;
                }
                $before = array_intersect_key($item->getRawOriginal(), $changes);
                $item->forceFill($changes + ['updated_by' => Auth::id()])->save();
                AuditLogger::log('update', $entity, $item->id, $item->name.' (CSV-Import)', AuditLogger::diff($before, $changes));
                $report['updated']++;
            }

            foreach ($report['lookups_created'] as &$names) {
                $names = array_values(array_unique($names));
            }
            unset($names);

            if ($dryRun) {
                DB::rollBack();
            } else {
                AuditLogger::log('import', $entity, null, "CSV-Import: {$report['created']} neu, {$report['updated']} ergänzt",
                    array_intersect_key($report, array_flip(['rows', 'created', 'updated', 'unchanged', 'skipped', 'conflict_count', 'overwrite'])));
                DB::commit();
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return $report;
    }

    /** @return list<array{0: int, 1: array<string, string>}> Zeilennummer und Zellen je Spalte */
    private function readCsv(string $path, array $columns): array
    {
        $content = (string) file_get_contents($path);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }
        $firstLine = strtok($content, "\r\n") ?: '';
        $counts = ["\t" => substr_count($firstLine, "\t"), ';' => substr_count($firstLine, ';'), ',' => substr_count($firstLine, ',')];
        arsort($counts);
        $delimiter = array_key_first($counts);

        $handle = fopen('php://temp', 'w+');
        fwrite($handle, $content);
        rewind($handle);
        $header = fgetcsv($handle, null, $delimiter, '"', '');
        if (! $header || $header === [null]) {
            throw ValidationException::withMessages(['file' => 'Die Datei ist leer.']);
        }
        $header = array_map(fn ($h) => mb_strtolower(trim((string) $h)), $header);
        $unknown = array_diff(array_filter($header, fn ($h) => $h !== ''), array_keys($columns));
        if ($unknown) {
            throw ValidationException::withMessages(['file' => 'Unbekannte Spalten: '.implode(', ', $unknown).'. Erlaubt sind: '.implode(', ', array_keys($columns)).'.']);
        }
        if (! in_array('name', $header, true)) {
            throw ValidationException::withMessages(['file' => 'Die Spalte „name“ fehlt.']);
        }
        if (count($header) !== count(array_unique($header))) {
            throw ValidationException::withMessages(['file' => 'Eine Spalte kommt in der Kopfzeile doppelt vor.']);
        }

        $rows = [];
        $line = 1;
        while (($cells = fgetcsv($handle, null, $delimiter, '"', '')) !== false) {
            $line++;
            if ($cells === [null] || implode('', array_map('trim', array_map('strval', $cells))) === '') {
                continue;
            }
            if (count($rows) >= self::MAX_ROWS) {
                throw ValidationException::withMessages(['file' => 'Höchstens '.self::MAX_ROWS.' Zeilen pro Import.']);
            }
            $row = [];
            foreach ($header as $i => $column) {
                if ($column !== '') {
                    $row[$column] = trim((string) ($cells[$i] ?? ''));
                }
            }
            $rows[] = [$line, $row];
        }
        fclose($handle);

        return $rows;
    }

    /** @return array{0: array<string, mixed>, 1: list<string>} Werte (nur nicht leere Zellen) und Fehlermeldungen */
    private function parseRow(array $cells, array $columns): array
    {
        $values = [];
        $messages = [];
        $rules = [];
        foreach ($cells as $column => $raw) {
            if ($raw === '') {
                continue;
            }
            [$type, , $extra] = $columns[$column] + [2 => null];
            $label = $column;
            switch ($type) {
                case 'name':
                    $values[$column] = LookupValue::cleanName($raw);
                    $rules[$column] = ['string', 'max:120'];
                    break;
                case 'text':
                    $values[$column] = $raw;
                    $rules[$column] = ['string', 'max:5000'];
                    break;
                case 'source_name':
                case 'lookup':
                    $values[$column] = LookupValue::cleanName($raw);
                    $rules[$column] = ['string', 'max:100'];
                    break;
                case 'url':
                    $values[$column] = $raw;
                    $rules[$column] = ['url:http,https', 'max:500'];
                    break;
                case 'stat':
                    $values[$column] = $this->number($raw);
                    $rules[$column] = [new StatValue];
                    break;
                case 'int':
                    $number = $this->number($raw);
                    if (! is_numeric($number) || (float) $number != (int) $number) {
                        $messages[] = "$label: ganze Zahl erwartet, „{$raw}“ gefunden.";
                        break;
                    }
                    $values[$column] = (int) $number;
                    $rules[$column] = ['integer', "min:{$extra[0]}", "max:{$extra[1]}"];
                    break;
                case 'bool':
                    $bool = $this->bool($raw);
                    if ($bool === null) {
                        $messages[] = "$label: „ja“ oder „nein“ erwartet, „{$raw}“ gefunden.";
                        break;
                    }
                    $values[$column] = $bool;
                    break;
                case 'enum':
                    $case = $this->enumCase($extra, $raw);
                    if ($case === null) {
                        $messages[] = "$label: unbekannter Wert „{$raw}“ (erlaubt: ".implode(', ', $extra::values()).').';
                        break;
                    }
                    $values[$column] = $case;
                    $rules[$column] = [Rule::enum($extra)];
                    break;
            }
        }
        if (($values['name'] ?? '') === '') {
            $messages[] = 'Der Name fehlt.';
        }
        $validator = Validator::make($values, $rules);
        foreach ($validator->errors()->messages() as $column => $errors) {
            foreach ($errors as $error) {
                $messages[] = "$column: $error";
            }
        }
        if (isset($values['source_url']) && ! isset($values['source_name'])) {
            $values['source_name'] = parse_url($values['source_url'], PHP_URL_HOST) ?: null;
        }
        foreach ($values as $column => $value) {
            if ($columns[$column][0] === 'stat') {
                $values[$column] = round((float) $value, 1);
            }
        }

        return [$values, $messages];
    }

    private function number(string $raw): string
    {
        $raw = str_replace(' ', '', $raw);

        return preg_match('/^-?\d+,\d+$/', $raw) ? str_replace(',', '.', $raw) : $raw;
    }

    private function bool(string $raw): ?bool
    {
        return match (mb_strtolower($raw)) {
            'ja', 'j', 'yes', 'y', 'true', 'wahr', '1', 'x' => true,
            'nein', 'n', 'no', 'false', 'falsch', '0' => false,
            default => null,
        };
    }

    /** @param  class-string<\BackedEnum>  $enum */
    private function enumCase(string $enum, string $raw): ?string
    {
        $needle = mb_strtolower($raw);
        foreach ($enum::cases() as $case) {
            $aliases = method_exists($case, 'aliases') ? $case->aliases() : [];
            if (mb_strtolower($case->value) === $needle || mb_strtolower($case->label()) === $needle || in_array($needle, $aliases, true)) {
                return $case->value;
            }
        }

        return null;
    }

    private function same(mixed $current, mixed $value): bool
    {
        if (is_bool($value)) {
            return (bool) $current === $value && $current !== null;
        }
        if (is_float($value) || is_int($value)) {
            return is_numeric($current) && abs((float) $current - (float) $value) < 1e-9;
        }

        return (string) $current === (string) $value;
    }

    private function display(string $field, mixed $value): string
    {
        if (str_ends_with($field, '_id') && is_numeric($value)) {
            return (string) (LookupValue::query()->find($value)?->name ?? $value);
        }
        if (is_bool($value)) {
            return $value ? 'ja' : 'nein';
        }

        return mb_strimwidth((string) $value, 0, 80, '…');
    }
}
