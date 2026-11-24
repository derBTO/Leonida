<?php

namespace App\Http\Controllers\Api;

use App\Enums\LootType;
use App\Enums\PinStatus;
use App\Enums\RespawnStatus;
use App\Http\Controllers\Controller;
use App\Models\MapVersion;
use App\Models\Pin;
use App\Services\AuditLogger;
use App\Services\ImageService;
use App\Services\PinIndex;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PinController extends Controller
{
    /**
     * Kompakte Liste aller Pins für Karte und Sidebar (ein Request, im Browser gefiltert).
     * Felder: id, t (Titel), x, y, l (Loottypen), s (Status), d (Beschreibung),
     * w (Waffennamen), v (Fahrzeugnamen), c (Codes und Bezeichnungen).
     */
    public function index(Request $request)
    {
        $payload = json_encode(['pins' => PinIndex::all()], JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
        $etag = '"'.md5($payload).'"';
        if ($request->header('If-None-Match') === $etag) {
            return response('', 304)->header('ETag', $etag);
        }

        return response($payload, 200, [
            'Content-Type' => 'application/json',
            'ETag' => $etag,
            'Cache-Control' => 'no-cache',
        ]);
    }

    /** Serverseitige Suche mit denselben Regeln wie im Browser. Liefert passende Pin-IDs. */
    public function search(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'loot' => ['nullable', 'array'],
            'loot.*' => [Rule::in([...LootType::values(), 'none'])],
            'bbox' => ['nullable', 'regex:/^-?\d+(\.\d+)?(,-?\d+(\.\d+)?){3}$/'],
        ]);

        return response()->json(['ids' => PinIndex::search($data['q'] ?? null, $data['loot'] ?? [], $data['bbox'] ?? null)]);
    }

    public function show(Pin $pin): JsonResponse
    {
        return response()->json(['pin' => $pin->toApi()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatePin($request);
        $pin = DB::transaction(function () use ($data) {
            $pin = Pin::query()->create($this->attributes($data) + [
                'uuid' => (string) Str::uuid(),
                'map_version_id' => MapVersion::active()?->id,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);
            $this->syncRelations($pin, $data);
            AuditLogger::log('create', 'pin', $pin->id, $pin->title);

            return $pin;
        });

        return response()->json(['pin' => $pin->fresh()->toApi()], 201);
    }

    public function update(Request $request, Pin $pin): JsonResponse
    {
        $data = $this->validatePin($request);
        DB::transaction(function () use ($pin, $data) {
            $before = $pin->only(['title', 'x', 'y', 'status', 'respawn']);
            $pin->fill($this->attributes($data) + ['updated_by' => Auth::id()]);
            $pin->touch();
            $pin->save();
            $this->syncRelations($pin, $data);
            AuditLogger::log('update', 'pin', $pin->id, $pin->title, AuditLogger::diff(
                array_map(fn ($v) => $v instanceof \BackedEnum ? $v->value : $v, $before),
                array_map(fn ($v) => $v instanceof \BackedEnum ? $v->value : $v, $pin->only(array_keys($before)))
            ));
        });

        return response()->json(['pin' => $pin->fresh()->toApi()]);
    }

    public function move(Request $request, Pin $pin): JsonResponse
    {
        $data = $request->validate([
            'x' => ['required', 'numeric', 'between:-16384,16384'],
            'y' => ['required', 'numeric', 'between:-16384,16384'],
        ]);
        $before = ['x' => $pin->x, 'y' => $pin->y];
        $pin->update([
            'x' => round((float) $data['x'], 2),
            'y' => round((float) $data['y'], 2),
            'map_version_id' => MapVersion::active()?->id ?? $pin->map_version_id,
            'updated_by' => Auth::id(),
        ]);
        AuditLogger::log('move', 'pin', $pin->id, $pin->title, AuditLogger::diff($before, ['x' => $pin->x, 'y' => $pin->y]));

        return response()->json(['pin' => $pin->fresh()->toApi()]);
    }

    public function destroy(Pin $pin, ImageService $images): JsonResponse
    {
        $mediaIds = $pin->images()->pluck('media_id')->all();
        $title = $pin->title;
        $id = $pin->id;
        DB::transaction(fn () => $pin->delete());
        foreach ($mediaIds as $mediaId) {
            $images->deleteIfUnused($mediaId);
        }
        AuditLogger::log('delete', 'pin', $id, $title);

        return response()->json(['deleted' => true]);
    }

    private function validatePin(Request $request): array
    {
        $money = ['nullable', 'integer', 'min:0', 'max:2000000000'];

        return $request->validate([
            'title' => ['required', 'string', 'max:150', 'regex:/\S/u'],
            'description' => ['nullable', 'string', 'max:20000'],
            'x' => ['required', 'numeric', 'between:-16384,16384'],
            'y' => ['required', 'numeric', 'between:-16384,16384'],
            'loot_types' => ['present', 'array'],
            'loot_types.*' => ['distinct', Rule::enum(LootType::class)],
            'weapon_ids' => ['nullable', 'array', 'max:100'],
            'weapon_ids.*' => ['distinct', 'integer', 'exists:weapons,id'],
            'vehicle_ids' => ['nullable', 'array', 'max:100'],
            'vehicle_ids.*' => ['distinct', 'integer', 'exists:vehicles,id'],
            'drugs_value_usd' => $money,
            'jewelry_value_usd' => $money,
            'cash_usd' => $money,
            'codes' => ['nullable', 'array', 'max:50'],
            'codes.*.label' => ['nullable', 'string', 'max:100'],
            'codes.*.code' => ['required', 'string', 'max:100'],
            'codes.*.description' => ['nullable', 'string', 'max:2000'],
            'requirements' => ['nullable', 'string', 'max:5000'],
            'respawn' => ['nullable', Rule::enum(RespawnStatus::class)],
            'status' => ['nullable', Rule::enum(PinStatus::class)],
        ], [
            'title.required' => 'Der Titel fehlt.',
            'title.regex' => 'Der Titel darf nicht leer sein.',
            'codes.*.code.required' => 'Jeder Code braucht einen Wert.',
            'codes.*.code.string' => 'Codes müssen als Text gesendet werden.',
        ]);
    }

    /**
     * Felder, die zu einem nicht gewählten Loottyp gehören, werden geleert. So bleibt der Datensatz
     * konsistent mit dem, was die Oberfläche anzeigt.
     */
    private function attributes(array $data): array
    {
        $types = $data['loot_types'];
        $blank = fn ($v) => is_string($v) && trim($v) === '' ? null : $v;

        return [
            'title' => trim($data['title']),
            'description' => $blank($data['description'] ?? null),
            'x' => round((float) $data['x'], 2),
            'y' => round((float) $data['y'], 2),
            'requirements' => $blank($data['requirements'] ?? null),
            'drugs_value_usd' => in_array('drugs', $types, true) ? ($data['drugs_value_usd'] ?? null) : null,
            'jewelry_value_usd' => in_array('jewelry', $types, true) ? ($data['jewelry_value_usd'] ?? null) : null,
            'cash_usd' => in_array('cash', $types, true) ? ($data['cash_usd'] ?? null) : null,
            'respawn' => $data['respawn'] ?? RespawnStatus::Unknown->value,
            'status' => $data['status'] ?? PinStatus::Unconfirmed->value,
        ];
    }

    private function syncRelations(Pin $pin, array $data): void
    {
        $types = $data['loot_types'];
        $pin->syncLootTypes($types);
        $order = fn (array $ids) => collect($ids)->values()->mapWithKeys(fn ($id, $i) => [$id => ['sort_order' => $i]])->all();
        $pin->weapons()->sync(in_array('weapons', $types, true) ? $order($data['weapon_ids'] ?? []) : []);
        $pin->vehicles()->sync(in_array('vehicles', $types, true) ? $order($data['vehicle_ids'] ?? []) : []);
        $pin->codes()->delete();
        foreach (array_values($data['codes'] ?? []) as $i => $code) {
            $pin->codes()->create([
                'label' => isset($code['label']) && trim($code['label']) !== '' ? trim($code['label']) : null,
                'code' => $code['code'],
                'description' => isset($code['description']) && trim($code['description']) !== '' ? $code['description'] : null,
                'sort_order' => $i,
            ]);
        }
    }
}
