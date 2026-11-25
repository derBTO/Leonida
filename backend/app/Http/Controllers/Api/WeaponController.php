<?php

namespace App\Http\Controllers\Api;

use App\Models\Weapon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class WeaponController extends CatalogController
{
    protected function modelClass(): string { return Weapon::class; }
    protected function entity(): string { return 'weapon'; }
    protected function pivotTable(): string { return 'pin_weapons'; }
    protected function pivotKey(): string { return 'weapon_id'; }

    /** ?legendary=1 zeigt nur legendäre Waffen. */
    protected function filter(Builder $query, Request $request): void
    {
        if ($request->boolean('legendary')) {
            $query->where('is_legendary', true);
        }
    }

    protected function rules(): array
    {
        return [
            'magazine_size' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'is_legendary' => ['nullable', 'boolean'],
        ];
    }
}
