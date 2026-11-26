<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Fundorte einer Waffe bzw. eines Fahrzeugs:
 * - Loot-Pins, die den Eintrag direkt führen, und
 * - Orte, die ein Hinweis zusammen mit dem Eintrag verknüpft (Hinweis ohne Ort zählt nicht).
 * Jeder Pin erscheint nur einmal. Dazu die Puzzle, die den Eintrag als Belohnung nennen.
 *
 * Erwartet die Konstanten PIVOT (z. B. 'pin_weapons'), PIVOT_KEY ('weapon_id') und TRAIL_PIVOT ('trail_weapons').
 */
trait HasPinLocations
{
    /** SQL-Teil: (eintrag_id, pin_id) aller Fundorte */
    public static function locationPairsSql(): string
    {
        $pivot = static::PIVOT;
        $key = static::PIVOT_KEY;

        return "SELECT pv.$key AS item_id, p.id AS pin_id FROM $pivot pv JOIN pins p ON p.id = pv.pin_id WHERE p.kind <> 'hint'
            UNION
            SELECT pv.$key, p.place_pin_id FROM $pivot pv JOIN pins p ON p.id = pv.pin_id WHERE p.kind = 'hint' AND p.place_pin_id IS NOT NULL";
    }

    /** @return array<int, int> Anzahl Fundorte je Eintrag */
    public static function locationCounts(): array
    {
        return collect(DB::select('SELECT item_id, count(*) AS n FROM ('.static::locationPairsSql().') l GROUP BY item_id'))
            ->mapWithKeys(fn ($r) => [(int) $r->item_id => (int) $r->n])->all();
    }

    public function locations(): array
    {
        return collect(DB::select('SELECT p.id, p.kind, p.poi_type, p.title, p.x, p.y, p.status FROM pins p
            WHERE p.id IN (SELECT pin_id FROM ('.static::locationPairsSql().') l WHERE l.item_id = ?) ORDER BY lower(p.title)', [$this->id]))
            ->map(fn ($p) => ['id' => (int) $p->id, 'title' => $p->title, 'kind' => $p->kind, 'poi_type' => $p->poi_type,
                'x' => (float) $p->x, 'y' => (float) $p->y, 'status' => $p->status])
            ->all();
    }

    /** Puzzle, die diesen Eintrag als Belohnung nennen */
    public function puzzles(): array
    {
        $pivot = static::TRAIL_PIVOT;
        $key = static::PIVOT_KEY;

        return DB::table($pivot)->join('trails', 'trails.id', '=', "$pivot.trail_id")->where("$pivot.$key", $this->id)
            ->orderByRaw('lower(trails.name)')->get(['trails.id', 'trails.name'])
            ->map(fn ($t) => ['id' => (int) $t->id, 'name' => $t->name])->all();
    }
}
