<?php

namespace App\Http\Controllers\Api;

use App\Enums\Drivetrain;
use App\Enums\LookupCategory;
use App\Enums\LootType;
use App\Enums\PinStatus;
use App\Enums\PoiType;
use App\Enums\RespawnStatus;
use App\Enums\Tracker;
use App\Enums\VehicleAccess;
use App\Enums\VehicleSecurity;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\MapVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MetaController extends Controller
{
    /** Feste Auswahllisten mit deutschen Bezeichnungen, Grenzen und Kartenstand. */
    public function meta(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $request->user()?->toPublicArray(),
            'enums' => [
                'loot_type' => LootType::options(),
                'respawn' => RespawnStatus::options(),
                'pin_status' => PinStatus::options(),
                'poi_type' => PoiType::options(),
                'drivetrain' => Drivetrain::options(),
                'security' => VehicleSecurity::options(),
                'tracker' => Tracker::options(),
                'access' => VehicleAccess::options(),
                'lookup_category' => LookupCategory::options(),
            ],
            'limits' => [
                'upload_max_mb' => config('leonida.images.max_mb'),
                'password_min_length' => config('leonida.password_min_length'),
            ],
            'map' => [
                'manifest_url' => config('leonida.map_url').'/current/manifest.json',
                'tiles_url' => config('leonida.map_url').'/current/tiles',
                'version' => MapVersion::active()?->version,
            ],
        ]);
    }

    /** Letzte Änderungen (nur angemeldet). */
    public function audit(Request $request): JsonResponse
    {
        $request->validate(['entity' => ['nullable', 'string', 'max:30'], 'entity_id' => ['nullable', 'string', 'max:40']]);
        $query = AuditLog::query()->with('user')->orderByDesc('id')->limit(200);
        if ($request->filled('entity')) {
            $query->where('entity', $request->query('entity'));
        }
        if ($request->filled('entity_id')) {
            $query->where('entity_id', $request->query('entity_id'));
        }

        return response()->json(['items' => $query->get()->map(fn (AuditLog $a) => [
            'id' => $a->id,
            'user' => $a->user?->username,
            'action' => $a->action,
            'entity' => $a->entity,
            'entity_id' => $a->entity_id,
            'summary' => $a->summary,
            'changes' => $a->changes,
            'created_at' => $a->created_at?->toIso8601String(),
        ])->all()]);
    }
}
