<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Schritt eines Hinweises: Text und/oder Bild, in fester Reihenfolge. */
class PinStep extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    public function pin(): BelongsTo
    {
        return $this->belongsTo(Pin::class);
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    public function toApi(): array
    {
        return ['id' => $this->id, 'text' => $this->text, 'image' => $this->media?->toApi()];
    }
}
