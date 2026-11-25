<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Weapon extends Model
{
    public const STATS = ['damage', 'accuracy', 'handling', 'range'];

    public const LOOKUPS = [
        'weapon_type_id' => 'weapon_type',
        'fire_mode_id' => 'fire_mode',
        'brand_id' => 'weapon_brand',
        'caliber_id' => 'caliber',
    ];

    protected $guarded = ['id', 'weapon_type_cat', 'fire_mode_cat', 'brand_cat', 'caliber_cat'];

    protected function casts(): array
    {
        return array_fill_keys(self::STATS, 'float') + ['magazine_size' => 'integer', 'is_legendary' => 'boolean'];
    }

    public function image(): BelongsTo { return $this->belongsTo(Media::class, 'image_media_id'); }
    public function weaponType(): BelongsTo { return $this->belongsTo(LookupValue::class, 'weapon_type_id'); }
    public function fireMode(): BelongsTo { return $this->belongsTo(LookupValue::class, 'fire_mode_id'); }
    public function brand(): BelongsTo { return $this->belongsTo(LookupValue::class, 'brand_id'); }
    public function caliber(): BelongsTo { return $this->belongsTo(LookupValue::class, 'caliber_id'); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function updatedBy(): BelongsTo { return $this->belongsTo(User::class, 'updated_by'); }

    public function pins(): BelongsToMany
    {
        return $this->belongsToMany(Pin::class, 'pin_weapons')->withPivot('sort_order');
    }

    public const WITH = ['image', 'weaponType', 'fireMode', 'brand', 'caliber'];

    public function toSummary(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'created_by_id' => $this->created_by,
            'is_legendary' => $this->is_legendary,
            'image' => $this->image?->toApi(),
            'weapon_type' => $this->weaponType?->toApi(),
            'stats' => $this->only(self::STATS),
        ];
    }

    public function toApi(bool $withLocations = false): array
    {
        $data = [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'is_legendary' => $this->is_legendary,
            'image' => $this->image?->toApi(),
            'weapon_type' => $this->weaponType?->toApi(),
            'fire_mode' => $this->fireMode?->toApi(),
            'brand' => $this->brand?->toApi(),
            'caliber' => $this->caliber?->toApi(),
            'magazine_size' => $this->magazine_size,
            'stats' => $this->only(self::STATS),
            'notes' => $this->notes,
            'source' => $this->source_name ? ['name' => $this->source_name, 'url' => $this->source_url] : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'created_by' => $this->createdBy?->username,
            'updated_by' => $this->updatedBy?->username,
        ];
        if ($withLocations) {
            $data['locations'] = $this->pins()->orderBy('title')->get(['pins.id', 'title', 'x', 'y', 'status'])
                ->map(fn (Pin $p) => ['id' => $p->id, 'title' => $p->title, 'x' => (float) $p->x, 'y' => (float) $p->y, 'status' => $p->status->value])
                ->all();
        }

        return $data;
    }
}
