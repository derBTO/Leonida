<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/** Spur: geordnete Schritte von A nach D, jeder Schritt optional an einem Pin. */
class Trail extends Model
{
    protected $guarded = ['id'];

    public function steps(): HasMany
    {
        return $this->hasMany(TrailStep::class)->orderBy('sort_order')->orderBy('id');
    }

    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }

    public function updatedBy(): BelongsTo { return $this->belongsTo(User::class, 'updated_by'); }

    /** @return array<int, true> erledigte Schritt-IDs des Benutzers */
    public static function doneSteps(?int $userId, array $stepIds): array
    {
        if (! $userId || ! $stepIds) {
            return [];
        }

        return DB::table('trail_step_progress')->where('user_id', $userId)->whereIn('trail_step_id', $stepIds)
            ->pluck('trail_step_id')->mapWithKeys(fn ($id) => [(int) $id => true])->all();
    }

    public function stepCount(): int
    {
        return (int) $this->steps->max('position');
    }

    public function toSummary(array $done = []): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'step_count' => $this->stepCount(),
            'steps_total' => $this->steps->count(),
            'steps_done' => $this->steps->filter(fn ($s) => isset($done[$s->id]))->count(),
            'pin_ids' => $this->steps->pluck('pin_id')->filter()->unique()->values()->map(fn ($id) => (int) $id)->all(),
            'created_by_id' => $this->created_by,
            'created_by' => $this->createdBy?->username,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    public function toApi(?int $userId = null): array
    {
        $this->loadMissing(['steps.pin', 'createdBy', 'updatedBy']);
        $done = self::doneSteps($userId, $this->steps->pluck('id')->all());

        return [
            'uuid' => $this->uuid,
            ...$this->toSummary($done),
            'updated_by' => $this->updatedBy?->username,
            'created_at' => $this->created_at?->toIso8601String(),
            'steps' => $this->steps->map(fn (TrailStep $s) => [
                'id' => $s->id,
                'position' => $s->position,
                'title' => $s->title,
                'description' => $s->description,
                'pin' => $s->pin ? [
                    'id' => $s->pin->id, 'title' => $s->pin->title, 'kind' => $s->pin->kind->value,
                    'poi_type' => $s->pin->poi_type?->value, 'x' => $s->pin->x, 'y' => $s->pin->y,
                ] : null,
                'done' => isset($done[$s->id]),
            ])->all(),
        ];
    }
}
