<?php

namespace App\Services;

use App\Enums\LookupCategory;
use App\Models\LookupValue;
use App\Models\Vehicle;
use App\Models\Weapon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LookupService
{
    /** Spalten, die auf eine Kategorie verweisen: [Tabelle, Spalte]. */
    public static function references(LookupCategory $category): array
    {
        $refs = [];
        foreach (Weapon::LOOKUPS as $column => $cat) {
            if ($cat === $category->value) {
                $refs[] = ['weapons', $column];
            }
        }
        foreach (Vehicle::LOOKUPS as $column => $cat) {
            if ($cat === $category->value) {
                $refs[] = ['vehicles', $column];
            }
        }

        return $refs;
    }

    /** Liefert den vorhandenen Wert (unabhängig von Groß-/Kleinschreibung) oder legt ihn an. */
    public function findOrCreate(LookupCategory $category, string $name): array
    {
        $clean = LookupValue::cleanName($name);
        if ($clean === '' || mb_strlen($clean) > 100) {
            throw ValidationException::withMessages(['name' => 'Der Name muss 1 bis 100 Zeichen lang sein.']);
        }
        $existing = LookupValue::query()->where('category', $category)->where('name_key', LookupValue::keyFor($clean))->first();
        if ($existing) {
            return [$existing, false];
        }
        $value = LookupValue::query()->create(['category' => $category, 'name' => $clean, 'name_key' => LookupValue::keyFor($clean)]);
        AuditLogger::log('create', 'lookup', $value->id, "{$category->label()}: $clean");

        return [$value, true];
    }

    public function rename(LookupValue $value, string $name): LookupValue
    {
        $clean = LookupValue::cleanName($name);
        $key = LookupValue::keyFor($clean);
        $clash = LookupValue::query()->where('category', $value->category)->where('name_key', $key)->where('id', '!=', $value->id)->first();
        if ($clash) {
            throw ValidationException::withMessages(['name' => "„{$clash->name}“ gibt es bereits. Bitte zusammenführen."]);
        }
        $old = $value->name;
        $value->update(['name' => $clean, 'name_key' => $key]);
        AuditLogger::log('update', 'lookup', $value->id, "$old → $clean");

        return $value;
    }

    /** Biegt alle Verweise von $source auf $target um und löscht $source. */
    public function merge(LookupValue $source, LookupValue $target): int
    {
        if ($source->category !== $target->category) {
            throw ValidationException::withMessages(['target_id' => 'Nur Werte derselben Liste können zusammengeführt werden.']);
        }
        if ($source->id === $target->id) {
            throw ValidationException::withMessages(['target_id' => 'Quelle und Ziel sind identisch.']);
        }

        return DB::transaction(function () use ($source, $target) {
            $moved = 0;
            foreach (self::references($source->category) as [$table, $column]) {
                $moved += DB::table($table)->where($column, $source->id)->update([$column => $target->id]);
            }
            AuditLogger::log('merge', 'lookup', $target->id, "{$source->name} → {$target->name}", ['moved' => $moved]);
            $source->delete();

            return $moved;
        });
    }

    public function usageCount(LookupValue $value): int
    {
        $count = 0;
        foreach (self::references($value->category) as [$table, $column]) {
            $count += DB::table($table)->where($column, $value->id)->count();
        }

        return $count;
    }
}
