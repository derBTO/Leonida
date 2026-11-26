<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Kompakter Pin-Index für die Karte und die serverseitige Suche.
 *
 * Suchregeln (gleich im Browser, siehe frontend/src/lib/pinFilter.ts):
 * - Groß-/Kleinschreibung egal, alle Suchwörter müssen vorkommen (UND).
 * - Durchsucht Titel, Beschreibung, Waffen- und Fahrzeugnamen, Codes und Code-Bezeichnungen.
 * - Loottyp-Filter: Pin muss mindestens einen der gewählten Typen haben (ODER);
 *   „none“ steht für Pins ohne Loottyp. Gilt nur für Loot-Pins.
 * - Art (kind) filtert Loot, Orte oder Hinweise; POI-Kategorien (ODER) gelten nur für Orte.
 * - author: nur Pins dieses Erstellers („Nur meine“).
 * - egg: true = nur Eastereggs, false = ohne Eastereggs.
 */
class PinIndex
{
    public static function all(): array
    {
        $pins = DB::table('pins')->orderBy('id')->get(['id', 'kind', 'poi_type', 'title', 'description', 'x', 'y', 'status', 'created_by', 'source', 'is_easteregg']);
        $loot = self::grouped(DB::table('pin_loot_types')->orderBy('loot_type')->get(['pin_id', 'loot_type as v']));
        $weapons = self::grouped(DB::table('pin_weapons')->join('weapons', 'weapons.id', '=', 'pin_weapons.weapon_id')
            ->orderBy('pin_weapons.sort_order')->get(['pin_id', 'weapons.name as v']));
        $vehicles = self::grouped(DB::table('pin_vehicles')->join('vehicles', 'vehicles.id', '=', 'pin_vehicles.vehicle_id')
            ->orderBy('pin_vehicles.sort_order')->get(['pin_id', 'vehicles.name as v']));
        $codes = self::grouped(DB::table('pin_codes')->orderBy('sort_order')
            ->selectRaw("pin_id, concat_ws(' ', label, code) as v")->get());

        return $pins->map(fn ($p) => [
            'id' => (int) $p->id,
            'k' => $p->kind,
            'p' => $p->poi_type,
            't' => $p->title,
            'x' => (float) $p->x,
            'y' => (float) $p->y,
            'l' => $loot[$p->id] ?? [],
            's' => $p->status,
            'd' => $p->description ?? '',
            'w' => $weapons[$p->id] ?? [],
            'v' => $vehicles[$p->id] ?? [],
            'c' => $codes[$p->id] ?? [],
            'u' => $p->created_by === null ? null : (int) $p->created_by,
            'o' => $p->source,
            'e' => (bool) $p->is_easteregg,
        ])->all();
    }

    /** @return list<int> */
    public static function search(?string $q, array $loot = [], ?string $bbox = null, ?string $kind = null, array $poi = [], ?int $author = null, ?bool $egg = null): array
    {
        $query = DB::table('pins')->select('pins.id')->orderBy('pins.title');
        foreach (self::terms($q) as $term) {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';
            $query->where(function ($w) use ($like) {
                $w->whereRaw('lower(pins.title) LIKE ?', [$like])
                    ->orWhereRaw('lower(pins.description) LIKE ?', [$like])
                    ->orWhereExists(fn ($s) => $s->from('pin_weapons')->join('weapons', 'weapons.id', '=', 'pin_weapons.weapon_id')
                        ->whereColumn('pin_weapons.pin_id', 'pins.id')->whereRaw('lower(weapons.name) LIKE ?', [$like]))
                    ->orWhereExists(fn ($s) => $s->from('pin_vehicles')->join('vehicles', 'vehicles.id', '=', 'pin_vehicles.vehicle_id')
                        ->whereColumn('pin_vehicles.pin_id', 'pins.id')->whereRaw('lower(vehicles.name) LIKE ?', [$like]))
                    ->orWhereExists(fn ($s) => $s->from('pin_codes')->whereColumn('pin_codes.pin_id', 'pins.id')
                        ->where(fn ($c) => $c->whereRaw('lower(pin_codes.code) LIKE ?', [$like])->orWhereRaw('lower(pin_codes.label) LIKE ?', [$like])));
            });
        }
        if ($kind) {
            $query->where('pins.kind', $kind);
        }
        if ($author) {
            $query->where('pins.created_by', $author);
        }
        if ($egg !== null) {
            $query->where('pins.is_easteregg', $egg);
        }
        // Loottyp-Filter gilt nur für Loot-Pins, POI-Filter nur für Orte. Ohne Art („Alles“) zählt
        // nur die Auswahl: nur Kategorien gewählt → keine Loot-Pins, nur Loottypen → keine Orte.
        if (! $kind && $poi && ! $loot) {
            $query->where('pins.kind', '!=', 'loot');
        }
        if (! $kind && $loot && ! $poi) {
            $query->where('pins.kind', '!=', 'poi');
        }
        if ($poi) {
            $query->where(fn ($w) => $w->where('pins.kind', '!=', 'poi')->orWhereIn('pins.poi_type', $poi));
        }
        if ($loot) {
            $types = array_values(array_diff($loot, ['none']));
            $query->where(function ($outer) use ($types, $loot) {
                $outer->where('pins.kind', '!=', 'loot')->orWhere(function ($w) use ($types, $loot) {
                    if ($types) {
                        $w->whereExists(fn ($s) => $s->from('pin_loot_types')->whereColumn('pin_loot_types.pin_id', 'pins.id')->whereIn('loot_type', $types));
                    }
                    if (in_array('none', $loot, true)) {
                        $w->orWhereNotExists(fn ($s) => $s->from('pin_loot_types')->whereColumn('pin_loot_types.pin_id', 'pins.id'));
                    }
                });
            });
        }
        if ($bbox) {
            [$west, $south, $east, $north] = array_map('floatval', explode(',', $bbox));
            $query->whereBetween('pins.x', [min($west, $east), max($west, $east)])
                ->whereBetween('pins.y', [min($south, $north), max($south, $north)]);
        }

        return $query->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /** @return list<string> */
    public static function terms(?string $q): array
    {
        $q = mb_strtolower(trim((string) $q));

        return $q === '' ? [] : array_values(array_filter(preg_split('/\s+/u', $q)));
    }

    private static function grouped($rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $out[$row->pin_id][] = $row->v;
        }

        return $out;
    }
}
