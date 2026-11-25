<?php

namespace App\Services;

use App\Models\LookupValue;
use App\Models\MapVersion;
use App\Models\Media;
use App\Models\Pin;
use App\Models\Trail;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Weapon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use ZipArchive;

/**
 * Export und Import aller eigenen Daten (Pins, Spuren, Waffen, Fahrzeuge, Auswahllisten, Bilder) als ZIP.
 * Der persönliche Fortschritt in Spuren gehört zu den Benutzerkonten und wird nicht exportiert.
 * Benutzerkonten und Passwörter werden nicht exportiert; Autoren stehen als Benutzername drin.
 * Abgleich beim Import über UUIDs, Auswahllisten über den normalisierten Namen.
 */
class DataTransfer
{
    public const FORMAT = 'leonida-export';

    public const VERSION = 1;

    public function export(): string
    {
        $usernames = User::query()->pluck('username', 'id');
        $name = fn ($id) => $id ? ($usernames[$id] ?? null) : null;
        $lookupName = LookupValue::query()->pluck('name', 'id');
        $versions = MapVersion::query()->pluck('version', 'id');

        $catalog = function (string $class) use ($name, $lookupName) {
            return $class::query()->orderBy('id')->get()->map(function (Model $item) use ($class, $name, $lookupName) {
                $row = ['uuid' => $item->uuid, 'name' => $item->name, 'image_media_id' => $item->image_media_id, 'notes' => $item->notes];
                foreach ($class::LOOKUPS as $column => $category) {
                    $row[$column] = $item->$column ? ['category' => $category, 'name' => $lookupName[$item->$column]] : null;
                }
                foreach ($class::STATS as $stat) {
                    $row[$stat] = $item->$stat;
                }
                foreach (array_diff(array_keys($item->getAttributes()), array_keys($row), ['id', 'created_by', 'updated_by', 'created_at', 'updated_at']) as $key) {
                    if (! str_ends_with($key, '_cat')) {
                        $value = $item->$key;
                        $row[$key] = $value instanceof \BackedEnum ? $value->value : $value;
                    }
                }

                return $row + [
                    'created_at' => $item->created_at?->toIso8601String(),
                    'updated_at' => $item->updated_at?->toIso8601String(),
                    'created_by' => $name($item->created_by),
                    'updated_by' => $name($item->updated_by),
                ];
            })->all();
        };

        $pins = Pin::query()->with(['weapons:id,uuid', 'vehicles:id,uuid', 'codes', 'images'])->orderBy('id')->get()->map(fn (Pin $p) => [
            'uuid' => $p->uuid,
            'kind' => $p->kind->value,
            'poi_type' => $p->poi_type?->value,
            'source' => $p->source,
            'title' => $p->title,
            'description' => $p->description,
            'x' => $p->x,
            'y' => $p->y,
            'map_version' => $p->map_version_id ? ($versions[$p->map_version_id] ?? null) : null,
            'loot_types' => $p->lootTypes(),
            'requirements' => $p->requirements,
            'drugs_value_usd' => $p->drugs_value_usd,
            'jewelry_value_usd' => $p->jewelry_value_usd,
            'cash_usd' => $p->cash_usd,
            'respawn' => $p->respawn->value,
            'status' => $p->status->value,
            'weapons' => $p->weapons->pluck('uuid')->all(),
            'vehicles' => $p->vehicles->pluck('uuid')->all(),
            'codes' => $p->codes->map(fn ($c) => $c->only(['label', 'code', 'description']))->all(),
            'images' => $p->images->map(fn ($i) => ['media_id' => $i->media_id, 'caption' => $i->caption])->all(),
            'created_at' => $p->created_at?->toIso8601String(),
            'updated_at' => $p->updated_at?->toIso8601String(),
            'created_by' => $name($p->created_by),
            'updated_by' => $name($p->updated_by),
        ])->all();

        $media = Media::query()->orderBy('created_at')->get();
        $data = [
            'format' => self::FORMAT,
            'version' => self::VERSION,
            'exported_at' => now()->toIso8601String(),
            'lookups' => LookupValue::query()->orderBy('category')->orderBy('name')->get()
                ->map(fn ($v) => ['category' => $v->category->value, 'name' => $v->name])->all(),
            'media' => $media->map(fn (Media $m) => $m->only(['id', 'path', 'thumb_path', 'mime', 'width', 'height', 'bytes', 'sha256', 'original_name']))->all(),
            'weapons' => $catalog(Weapon::class),
            'vehicles' => $catalog(Vehicle::class),
            'pins' => $pins,
            'trails' => Trail::query()->with('steps.pin:id,uuid')->orderBy('id')->get()->map(fn (Trail $t) => [
                'uuid' => $t->uuid,
                'name' => $t->name,
                'description' => $t->description,
                'steps' => $t->steps->map(fn ($s) => ['position' => $s->position, 'title' => $s->title,
                    'description' => $s->description, 'pin' => $s->pin?->uuid])->all(),
                'created_at' => $t->created_at?->toIso8601String(),
                'updated_at' => $t->updated_at?->toIso8601String(),
                'created_by' => $name($t->created_by),
                'updated_by' => $name($t->updated_by),
            ])->all(),
        ];

        $file = tempnam(sys_get_temp_dir(), 'leonida-export-');
        $zip = new ZipArchive;
        if ($zip->open($file, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Export-Datei kann nicht angelegt werden.');
        }
        $zip->addFromString('data.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));
        $root = rtrim(config('leonida.upload_dir'), '/');
        foreach ($media as $m) {
            foreach ([$m->path, $m->thumb_path] as $path) {
                if (is_file("$root/$path")) {
                    $zip->addFile("$root/$path", "media/$path");
                    $zip->setCompressionName("media/$path", ZipArchive::CM_STORE);
                }
            }
        }
        $zip->close();

        return $file;
    }

    /**
     * @param  'merge'|'replace'  $mode
     * @return array<string, array{created: int, updated: int}|int|bool>
     */
    public function import(string $zipPath, string $mode, bool $dryRun): array
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::RDONLY) !== true) {
            throw ValidationException::withMessages(['file' => 'Die Datei ist kein gültiges ZIP-Archiv.']);
        }
        $data = json_decode((string) $zip->getFromName('data.json'), true);
        if (! is_array($data) || ($data['format'] ?? null) !== self::FORMAT) {
            throw ValidationException::withMessages(['file' => 'Das ist kein Leonida-Export (data.json fehlt oder ist ungültig).']);
        }
        if (($data['version'] ?? 0) > self::VERSION) {
            throw ValidationException::withMessages(['file' => 'Der Export stammt aus einer neueren Leonida-Version.']);
        }

        $report = ['mode' => $mode, 'dry_run' => $dryRun];
        $copied = [];

        try {
            DB::beginTransaction();
            if ($mode === 'replace') {
                $report['removed'] = [
                    'trails' => DB::table('trails')->delete(),
                    'pins' => DB::table('pins')->delete(),
                    'weapons' => DB::table('weapons')->delete(),
                    'vehicles' => DB::table('vehicles')->delete(),
                    'lookups' => DB::table('lookup_values')->delete(),
                ];
            }
            $users = User::query()->get()->mapWithKeys(fn ($u) => [mb_strtolower($u->username) => $u->id]);
            $userId = fn ($name) => $name ? ($users[mb_strtolower($name)] ?? null) : null;

            // Auswahllisten
            $lookupIds = [];
            $count = ['created' => 0, 'updated' => 0];
            foreach ($data['lookups'] ?? [] as $row) {
                $key = LookupValue::keyFor($row['name']);
                $existing = LookupValue::query()->where('category', $row['category'])->where('name_key', $key)->first();
                if (! $existing) {
                    $existing = LookupValue::query()->create(['category' => $row['category'], 'name' => LookupValue::cleanName($row['name']), 'name_key' => $key]);
                    $count['created']++;
                }
                $lookupIds[$row['category'].'|'.$key] = $existing->id;
            }
            $report['lookups'] = $count;
            $lookup = function (?array $ref) use (&$lookupIds) {
                if (! $ref) {
                    return null;
                }
                $k = $ref['category'].'|'.LookupValue::keyFor($ref['name']);
                if (! isset($lookupIds[$k])) {
                    $lookupIds[$k] = LookupValue::query()->firstOrCreate(
                        ['category' => $ref['category'], 'name_key' => LookupValue::keyFor($ref['name'])],
                        ['name' => LookupValue::cleanName($ref['name'])]
                    )->id;
                }

                return $lookupIds[$k];
            };

            // Bilder
            $count = ['created' => 0, 'updated' => 0];
            $root = rtrim(config('leonida.upload_dir'), '/');
            foreach ($data['media'] ?? [] as $row) {
                if (Media::query()->whereKey($row['id'])->exists()) {
                    continue;
                }
                foreach (['path', 'thumb_path'] as $field) {
                    $path = $row[$field];
                    if (! preg_match('#^[0-9a-f]{2}/[0-9a-f]{2}/[0-9a-f-]{36}(_t)?\.webp$#', $path)) {
                        throw ValidationException::withMessages(['file' => "Ungültiger Bildpfad im Export: $path"]);
                    }
                    $content = $zip->getFromName("media/$path");
                    if ($content === false || ! @getimagesizefromstring($content)) {
                        throw ValidationException::withMessages(['file' => "Bild fehlt oder ist beschädigt: $path"]);
                    }
                    if (! $dryRun) {
                        @mkdir(dirname("$root/$path"), 0755, true);
                        file_put_contents("$root/$path", $content);
                        $copied[] = "$root/$path";
                    }
                }
                Media::query()->create(array_intersect_key($row, array_flip(['id', 'path', 'thumb_path', 'mime', 'width', 'height', 'bytes', 'sha256', 'original_name'])));
                $count['created']++;
            }
            $report['media'] = $count;

            // Waffen und Fahrzeuge
            $catalogIds = [];
            foreach (['weapons' => Weapon::class, 'vehicles' => Vehicle::class] as $key => $class) {
                $count = ['created' => 0, 'updated' => 0];
                foreach ($data[$key] ?? [] as $row) {
                    $attrs = ['name' => $row['name'], 'notes' => $row['notes'] ?? null, 'image_media_id' => $row['image_media_id'] ?? null,
                        'created_by' => $userId($row['created_by'] ?? null), 'updated_by' => $userId($row['updated_by'] ?? null)];
                    foreach (array_keys($class::LOOKUPS) as $column) {
                        $attrs[$column] = $lookup($row[$column] ?? null);
                    }
                    foreach ($row as $field => $value) {
                        if (! array_key_exists($field, $attrs) && ! in_array($field, ['uuid', 'created_at', 'updated_at', 'created_by', 'updated_by'], true)) {
                            $attrs[$field] = $value;
                        }
                    }
                    $item = $class::query()->where('uuid', $row['uuid'])->first();
                    $item ? $count['updated']++ : $count['created']++;
                    $item ??= new $class(['uuid' => $row['uuid']]);
                    $item->fill($attrs);
                    $item->created_at = $row['created_at'] ?? now();
                    $item->updated_at = $row['updated_at'] ?? now();
                    $item->timestamps = false;
                    $item->save();
                    $catalogIds[$key][$row['uuid']] = $item->id;
                }
                $report[$key] = $count;
            }

            // Pins
            $versions = MapVersion::query()->pluck('id', 'version');
            $count = ['created' => 0, 'updated' => 0];
            $pinIds = [];
            foreach ($data['pins'] ?? [] as $row) {
                $pin = Pin::query()->where('uuid', $row['uuid'])->first();
                $pin ? $count['updated']++ : $count['created']++;
                $pin ??= new Pin(['uuid' => $row['uuid']]);
                $pin->fill([
                    'kind' => $row['kind'] ?? 'loot', 'poi_type' => $row['poi_type'] ?? null, 'source' => $row['source'] ?? null,
                    'title' => $row['title'], 'description' => $row['description'] ?? null, 'x' => $row['x'], 'y' => $row['y'],
                    'map_version_id' => isset($row['map_version']) ? ($versions[$row['map_version']] ?? null) : null,
                    'requirements' => $row['requirements'] ?? null,
                    'drugs_value_usd' => $row['drugs_value_usd'] ?? null, 'jewelry_value_usd' => $row['jewelry_value_usd'] ?? null,
                    'cash_usd' => $row['cash_usd'] ?? null, 'respawn' => $row['respawn'] ?? 'unknown', 'status' => $row['status'] ?? 'unconfirmed',
                    'created_by' => $userId($row['created_by'] ?? null), 'updated_by' => $userId($row['updated_by'] ?? null),
                ]);
                $pin->created_at = $row['created_at'] ?? now();
                $pin->updated_at = $row['updated_at'] ?? now();
                $pin->timestamps = false;
                $pin->save();
                $pinIds[$row['uuid']] = $pin->id;
                $pin->syncLootTypes($row['loot_types'] ?? []);
                $ids = fn (string $key) => collect($row[$key] ?? [])->map(fn ($u) => $catalogIds[$key][$u] ?? null)->filter()->values()
                    ->mapWithKeys(fn ($id, $i) => [$id => ['sort_order' => $i]])->all();
                $pin->weapons()->sync($ids('weapons'));
                $pin->vehicles()->sync($ids('vehicles'));
                $pin->codes()->delete();
                foreach (array_values($row['codes'] ?? []) as $i => $code) {
                    $pin->codes()->create(['label' => $code['label'] ?? null, 'code' => (string) $code['code'], 'description' => $code['description'] ?? null, 'sort_order' => $i]);
                }
                $pin->images()->delete();
                foreach (array_values($row['images'] ?? []) as $i => $image) {
                    $pin->images()->create(['media_id' => $image['media_id'], 'caption' => $image['caption'] ?? null, 'sort_order' => $i]);
                }
            }
            $report['pins'] = $count;

            // Spuren (Schritte werden ersetzt; Fortschritt der ersetzten Schritte entfällt)
            $count = ['created' => 0, 'updated' => 0];
            foreach ($data['trails'] ?? [] as $row) {
                $trail = Trail::query()->where('uuid', $row['uuid'])->first();
                $trail ? $count['updated']++ : $count['created']++;
                $trail ??= new Trail(['uuid' => $row['uuid']]);
                $trail->fill(['name' => $row['name'], 'description' => $row['description'] ?? null,
                    'created_by' => $userId($row['created_by'] ?? null), 'updated_by' => $userId($row['updated_by'] ?? null)]);
                $trail->created_at = $row['created_at'] ?? now();
                $trail->updated_at = $row['updated_at'] ?? now();
                $trail->timestamps = false;
                $trail->save();
                $trail->steps()->delete();
                foreach (array_values($row['steps'] ?? []) as $i => $step) {
                    $pinId = $step['pin'] ?? null;
                    $trail->steps()->create(['position' => max(1, (int) ($step['position'] ?? $i + 1)), 'sort_order' => $i,
                        'title' => $step['title'], 'description' => $step['description'] ?? null,
                        'pin_id' => $pinId ? ($pinIds[$pinId] ?? Pin::query()->where('uuid', $pinId)->value('id')) : null]);
                }
            }
            $report['trails'] = $count;

            if ($dryRun) {
                DB::rollBack();
            } else {
                DB::commit();
                AuditLogger::log('import', 'data', null, "Import ($mode)", $report);
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            foreach ($copied as $file) {
                @unlink($file);
            }
            if ($e instanceof ValidationException) {
                throw $e;
            }
            report($e);
            throw ValidationException::withMessages(['file' => 'Import fehlgeschlagen: '.$e->getMessage()]);
        } finally {
            $zip->close();
        }

        return $report;
    }
}
