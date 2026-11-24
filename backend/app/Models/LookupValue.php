<?php

namespace App\Models;

use App\Enums\LookupCategory;
use Illuminate\Database\Eloquent\Model;

class LookupValue extends Model
{
    protected $fillable = ['category', 'name', 'name_key'];

    protected function casts(): array
    {
        return ['category' => LookupCategory::class];
    }

    /** Normalisierter Schlüssel gegen Dubletten: klein, Leerraum zusammengefasst. */
    public static function keyFor(string $name): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim($name)));
    }

    public static function cleanName(string $name): string
    {
        return preg_replace('/\s+/u', ' ', trim($name));
    }

    public function toApi(): array
    {
        return ['id' => $this->id, 'category' => $this->category->value, 'name' => $this->name];
    }
}
