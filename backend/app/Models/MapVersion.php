<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MapVersion extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['transform' => 'array', 'is_active' => 'boolean'];
    }

    public static function active(): ?self
    {
        return static::query()->where('is_active', true)->first();
    }
}
