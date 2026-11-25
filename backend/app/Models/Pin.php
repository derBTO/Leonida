<?php

namespace App\Models;

use App\Enums\PinKind;
use App\Enums\PinStatus;
use App\Enums\PoiType;
use App\Enums\RespawnStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Pin extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'x' => 'float',
            'y' => 'float',
            'drugs_value_usd' => 'integer',
            'jewelry_value_usd' => 'integer',
            'cash_usd' => 'integer',
            'respawn' => RespawnStatus::class,
            'kind' => PinKind::class,
            'poi_type' => PoiType::class,
            'status' => PinStatus::class,
        ];
    }

    public function weapons(): BelongsToMany
    {
        return $this->belongsToMany(Weapon::class, 'pin_weapons')->withPivot('sort_order')->orderByPivot('sort_order');
    }

    public function vehicles(): BelongsToMany
    {
        return $this->belongsToMany(Vehicle::class, 'pin_vehicles')->withPivot('sort_order')->orderByPivot('sort_order');
    }

    public function codes(): HasMany { return $this->hasMany(PinCode::class)->orderBy('sort_order')->orderBy('id'); }
    public function images(): HasMany { return $this->hasMany(PinImage::class)->orderBy('sort_order')->orderBy('id'); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function updatedBy(): BelongsTo { return $this->belongsTo(User::class, 'updated_by'); }

    /** @return list<string> */
    public function lootTypes(): array
    {
        return DB::table('pin_loot_types')->where('pin_id', $this->id)->orderBy('loot_type')->pluck('loot_type')->all();
    }

    /** @param list<string> $types */
    public function syncLootTypes(array $types): void
    {
        DB::table('pin_loot_types')->where('pin_id', $this->id)->delete();
        DB::table('pin_loot_types')->insert(array_map(fn ($t) => ['pin_id' => $this->id, 'loot_type' => $t], array_values(array_unique($types))));
    }

    public function toApi(): array
    {
        $this->loadMissing(['weapons.image', 'weapons.weaponType', 'vehicles.image', 'vehicles.manufacturer', 'vehicles.vehicleClass', 'codes', 'images.media', 'createdBy', 'updatedBy']);

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'kind' => $this->kind->value,
            'poi_type' => $this->poi_type?->value,
            'title' => $this->title,
            'description' => $this->description,
            'x' => $this->x,
            'y' => $this->y,
            'loot_types' => $this->lootTypes(),
            'requirements' => $this->requirements,
            'drugs_value_usd' => $this->drugs_value_usd,
            'jewelry_value_usd' => $this->jewelry_value_usd,
            'cash_usd' => $this->cash_usd,
            'respawn' => $this->respawn->value,
            'status' => $this->status->value,
            'weapons' => $this->weapons->map->toSummary()->all(),
            'vehicles' => $this->vehicles->map->toSummary()->all(),
            'codes' => $this->codes->map(fn (PinCode $c) => $c->only(['id', 'label', 'code', 'description']))->all(),
            'images' => $this->images->map->toApi()->all(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'created_by' => $this->createdBy?->username,
            'updated_by' => $this->updatedBy?->username,
        ];
    }
}
