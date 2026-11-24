<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PinImage extends Model
{
    protected $fillable = ['media_id', 'caption', 'sort_order'];

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    public function pin(): BelongsTo
    {
        return $this->belongsTo(Pin::class);
    }

    public function toApi(): array
    {
        return ['id' => $this->id, 'caption' => $this->caption, 'sort_order' => $this->sort_order] + $this->media->toApi();
    }
}
