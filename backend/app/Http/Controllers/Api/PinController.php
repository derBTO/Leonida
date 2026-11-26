<?php

namespace App\Http\Controllers\Api;

use App\Enums\LootType;
use App\Enums\PinKind;
use App\Enums\PinStatus;
use App\Enums\PoiType;
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
use Illuminate\Validation\ValidationException;

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
            'kind' => ['nullable', Rule::enum(PinKind::class)],
            'poi' => ['nullable', 'array'],
            'poi.*' => [Rule::enum(PoiType::class)],
            'bbox' => ['nullable', 'regex:/^-?\d+(\.\d+)?(,-?\d+(\.\d+)?){3}$/'],
            'author' => ['nullable', 'integer'],
            'egg' => ['nullable', 'boolean'],
        ]);

        return response()->json(['ids' => PinIndex::search($data['q'] ?? null, $data['loot'] ?? [], $data['bbox'] ?? null, $data['kind'] ?? null, $data['poi'] ?? [], $data['author'] ?? null, isset($data['egg']) ? (bool) $data['egg'] : null)]);
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
        $this->ensureOwner($pin->created_by);
        $data = $this->validatePin($request);
        $this->ensureKindKept($pin, $data['kind'] ?? PinKind::Loot->value);
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
        $this->ensureOwner($pin->created_by);
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
        $this->ensureOwner($pin->created_by);
        $mediaIds = [...$pin->images()->pluck('media_id')->all(), ...$pin->steps()->pluck('media_id')->filter()->all()];
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

        $data = $request->validate([
            'kind' => ['nullable', Rule::enum(PinKind::class)],
            'poi_type' => ['nullable', 'required_if:kind,poi', Rule::enum(PoiType::class)],
            'title' => ['required', 'string', 'max:150', 'regex:/\S/u'],
            'description' => ['nullable', 'string', 'max:20000'],
            'x' => ['required', 'numeric', 'between:-16384,16384'],
            'y' => ['required', 'numeric', 'between:-16384,16384'],
            'loot_types' => ['nullable', 'array'],
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
            'is_easteregg' => ['nullable', 'boolean'],
            // Hinweise: verknüpfter Ort und Schritte (Text und/oder Bild)
            'place_pin_id' => ['nullable', 'integer', Rule::exists('pins', 'id')->where('kind', PinKind::Poi->value)],
            'steps' => ['nullable', 'array', 'max:200'],
            'steps.*.text' => ['nullable', 'string', 'max:10000'],
            'steps.*.media_id' => ['nullable', 'uuid', 'exists:media,id'],
        ], [
            'place_pin_id.exists' => 'Als Ort kann nur ein vorhandener Orts-Pin verknüpft werden.',
            'steps.*.media_id.exists' => 'Ein Bild in den Schritten existiert nicht mehr. Bitte neu hochladen.',
            'title.required' => 'Der Titel fehlt.',
            'title.regex' => 'Der Titel darf nicht leer sein.',
            'codes.*.code.required' => 'Jeder Code braucht einen Wert.',
            'codes.*.code.string' => 'Codes müssen als Text gesendet werden.',
            'poi_type.required_if' => 'Bitte eine Kategorie für den Ort wählen.',
        ]);
        $errors = [];
        foreach (array_values($data['steps'] ?? []) as $i => $step) {
            if (trim((string) ($step['text'] ?? '')) === '' && empty($step['media_id'])) {
                $errors["steps.$i.text"] = 'Jeder Schritt braucht einen Text oder ein Bild.';
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $data;
    }

    /**
     * Felder, die zu einem nicht gewählten Loottyp gehören, werden geleert. So bleibt der Datensatz
     * konsistent mit dem, was die Oberfläche anzeigt.
     */
    private function attributes(array $data): array
    {
        $blank = fn ($v) => is_string($v) && trim($v) === '' ? null : $v;
        $kind = $data['kind'] ?? PinKind::Loot->value;
        if ($kind === PinKind::Poi->value || $kind === PinKind::Hint->value) {
            // Orte und Hinweise haben nur Name, Beschreibung, Bilder und Position (Hinweise zusätzlich Codes)
            return [
                'kind' => $kind,
                'is_easteregg' => (bool) ($data['is_easteregg'] ?? false),
                'poi_type' => $kind === PinKind::Poi->value ? $data['poi_type'] : null,
                'place_pin_id' => $kind === PinKind::Hint->value ? ($data['place_pin_id'] ?? null) : null,
                'title' => trim($data['title']),
                'description' => $blank($data['description'] ?? null),
                'x' => round((float) $data['x'], 2),
                'y' => round((float) $data['y'], 2),
                'requirements' => null,
                'drugs_value_usd' => null,
                'jewelry_value_usd' => null,
                'cash_usd' => null,
                'respawn' => RespawnStatus::Unknown->value,
                'status' => PinStatus::Confirmed->value,
            ];
        }
        $types = $data['loot_types'] ?? [];

        return [
            'kind' => PinKind::Loot->value,
            'is_easteregg' => (bool) ($data['is_easteregg'] ?? false),
            'poi_type' => null,
            'place_pin_id' => null,
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
        $kind = $data['kind'] ?? PinKind::Loot->value;
        $isPoi = $kind === PinKind::Poi->value;
        $types = $kind === PinKind::Loot->value ? ($data['loot_types'] ?? []) : [];
        if ($isPoi) {
            $data['codes'] = [];
        }
        $pin->syncLootTypes($types);
        $order = fn (array $ids) => collect($ids)->values()->mapWithKeys(fn ($id, $i) => [$id => ['sort_order' => $i]])->all();
        $isHint = $kind === PinKind::Hint->value;
        // Loot: Waffen/Fahrzeuge je nach Loottyp; Hinweis: frei verknüpfbar; Ort: keine
        $pin->weapons()->sync($isHint || in_array('weapons', $types, true) ? $order($data['weapon_ids'] ?? []) : []);
        $pin->vehicles()->sync($isHint || in_array('vehicles', $types, true) ? $order($data['vehicle_ids'] ?? []) : []);
        $this->syncSteps($pin, $isHint ? array_values($data['steps'] ?? []) : []);
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

    /**
     * Hinweis-Schritte ersetzen. Galeriebilder eines (ggf. gerade umgewandelten) Hinweises werden als
     * Schritte angehängt; nicht mehr verwendete Bilder werden gelöscht.
     */
    private function syncSteps(Pin $pin, array $steps): void
    {
        $oldMedia = $pin->steps()->pluck('media_id')->filter()->all();
        $pin->steps()->delete();
        $blank = fn ($v) => is_string($v) && trim($v) !== '' ? $v : null;
        foreach ($steps as $i => $step) {
            $pin->steps()->create(['sort_order' => $i, 'text' => $blank($step['text'] ?? null), 'media_id' => $step['media_id'] ?? null]);
        }
        if ($pin->kind === PinKind::Hint) {
            $next = count($steps);
            foreach ($pin->images()->orderBy('sort_order')->get() as $image) {
                $pin->steps()->create(['sort_order' => $next++, 'text' => $image->caption, 'media_id' => $image->media_id]);
            }
            $pin->images()->delete();
        }
        $images = app(ImageService::class);
        foreach ($oldMedia as $mediaId) {
            $images->deleteIfUnused($mediaId);
        }
    }

    /**
     * Hinweise bleiben Hinweise (Puzzle verweisen auf sie), und Orte, auf die Hinweise zeigen, bleiben Orte.
     */
    private function ensureKindKept(Pin $pin, string $kind): void
    {
        if ($pin->kind === PinKind::Hint && $kind !== PinKind::Hint->value) {
            throw \Illuminate\Validation\ValidationException::withMessages(['kind' => 'Ein Hinweis kann nicht in eine andere Art umgewandelt werden.']);
        }
        if ($pin->kind === PinKind::Poi && $kind !== PinKind::Poi->value && Pin::query()->where('place_pin_id', $pin->id)->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['kind' => 'Auf diesen Ort verweisen Hinweise; er bleibt ein Ort.']);
        }
    }
}
