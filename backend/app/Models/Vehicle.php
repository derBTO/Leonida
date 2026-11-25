<?php

namespace App\Models;

use App\Enums\Drivetrain;
use App\Enums\Tracker;
use App\Enums\VehicleAccess;
use App\Enums\VehicleSecurity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Vehicle extends Model
{
    public const STATS = ['speed', 'acceleration', 'handling_on_road', 'handling_off_road', 'durability'];

    public const LOOKUPS = [
        'manufacturer_id' => 'vehicle_manufacturer',
        'class_id' => 'vehicle_class',
        'style_id' => 'vehicle_style',
    ];

    protected $guarded = ['id', 'manufacturer_cat', 'class_cat', 'style_cat'];

    protected function casts(): array
    {
        return array_fill_keys(self::STATS, 'float') + [
            'seats' => 'integer',
            'rating_on_road' => 'integer',
            'rating_off_road' => 'integer',
            'registrable' => 'boolean',
            'drivetrain' => Drivetrain::class,
            'security' => VehicleSecurity::class,
            'tracker' => Tracker::class,
            'access' => VehicleAccess::class,
        ];
    }

    public function image(): BelongsTo { return $this->belongsTo(Media::class, 'image_media_id'); }
    public function manufacturer(): BelongsTo { return $this->belongsTo(LookupValue::class, 'manufacturer_id'); }
    public function vehicleClass(): BelongsTo { return $this->belongsTo(LookupValue::class, 'class_id'); }
    public function style(): BelongsTo { return $this->belongsTo(LookupValue::class, 'style_id'); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function updatedBy(): BelongsTo { return $this->belongsTo(User::class, 'updated_by'); }

    public function pins(): BelongsToMany
    {
        return $this->belongsToMany(Pin::class, 'pin_vehicles')->withPivot('sort_order');
    }

    public const WITH = ['image', 'manufacturer', 'vehicleClass', 'style'];

    public function toSummary(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'created_by_id' => $this->created_by,
            'image' => $this->image?->toApi(),
            'manufacturer' => $this->manufacturer?->toApi(),
            'vehicle_class' => $this->vehicleClass?->toApi(),
            'stats' => $this->only(self::STATS),
            'rating_on_road' => $this->rating_on_road,
            'rating_off_road' => $this->rating_off_road,
        ];
    }

    public function toApi(bool $withLocations = false): array
    {
        $data = [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'image' => $this->image?->toApi(),
            'manufacturer' => $this->manufacturer?->toApi(),
            'vehicle_class' => $this->vehicleClass?->toApi(),
            'style' => $this->style?->toApi(),
            'drivetrain' => $this->drivetrain?->value,
            'seats' => $this->seats,
            'security' => $this->security?->value,
            'tracker' => $this->tracker?->value,
            'access' => $this->access?->value,
            'registrable' => $this->registrable,
            'stats' => $this->only(self::STATS),
            'rating_on_road' => $this->rating_on_road,
            'rating_off_road' => $this->rating_off_road,
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
