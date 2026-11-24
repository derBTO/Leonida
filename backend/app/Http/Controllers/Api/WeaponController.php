<?php

namespace App\Http\Controllers\Api;

use App\Models\Weapon;

class WeaponController extends CatalogController
{
    protected function modelClass(): string { return Weapon::class; }
    protected function entity(): string { return 'weapon'; }
    protected function pivotTable(): string { return 'pin_weapons'; }
    protected function pivotKey(): string { return 'weapon_id'; }

    protected function rules(): array
    {
        return ['magazine_size' => ['nullable', 'integer', 'min:0', 'max:10000']];
    }
}
