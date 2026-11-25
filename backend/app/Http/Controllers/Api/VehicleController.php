<?php

namespace App\Http\Controllers\Api;

use App\Enums\Drivetrain;
use App\Enums\Tracker;
use App\Enums\VehicleAccess;
use App\Enums\VehicleSecurity;
use App\Models\Vehicle;
use Illuminate\Validation\Rule;

class VehicleController extends CatalogController
{
    protected function modelClass(): string
    {
        return Vehicle::class;
    }

    protected function entity(): string
    {
        return 'vehicle';
    }

    protected function pivotTable(): string
    {
        return 'pin_vehicles';
    }

    protected function pivotKey(): string
    {
        return 'vehicle_id';
    }

    protected function rules(): array
    {
        return [
            'drivetrain' => ['nullable', Rule::enum(Drivetrain::class)],
            'seats' => ['nullable', 'integer', 'min:0', 'max:100'],
            'security' => ['nullable', Rule::enum(VehicleSecurity::class)],
            'tracker' => ['nullable', Rule::enum(Tracker::class)],
            'access' => ['nullable', Rule::enum(VehicleAccess::class)],
            'registrable' => ['nullable', 'boolean'],
            'rating_on_road' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'rating_off_road' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ];
    }
}
