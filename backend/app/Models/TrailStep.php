<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrailStep extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['position' => 'integer', 'sort_order' => 'integer'];
    }

    public function trail(): BelongsTo { return $this->belongsTo(Trail::class); }

    public function pin(): BelongsTo { return $this->belongsTo(Pin::class); }
}
