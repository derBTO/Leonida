<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LookupValue;
use App\Rules\StatValue;
use App\Services\AuditLogger;
use App\Services\ImageService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Gemeinsame Logik für Waffen- und Fahrzeugkatalog. */
abstract class CatalogController extends Controller
{
    /** @return class-string<Model> */
    abstract protected function modelClass(): string;

    abstract protected function entity(): string;

    abstract protected function pivotTable(): string;

    abstract protected function pivotKey(): string;

    /** Zusätzliche Validierungsregeln je Katalog. */
    abstract protected function rules(): array;

    protected function query(): Builder
    {
        $class = $this->modelClass();

        return $class::query()->with($class::WITH);
    }

    public function index(Request $request): JsonResponse
    {
        $class = $this->modelClass();
        $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $query = $this->query()->orderByRaw('lower(name)');
        if ($q = trim((string) $request->query('q'))) {
            $query->whereRaw('lower(name) LIKE ?', ['%'.mb_strtolower(addcslashes($q, '\\%_')).'%']);
        }
        foreach (array_keys($class::LOOKUPS) as $column) {
            if ($request->filled($column)) {
                $query->where($column, (int) $request->query($column));
            }
        }
        $counts = DB::table($this->pivotTable())->select($this->pivotKey(), DB::raw('count(*) as n'))
            ->groupBy($this->pivotKey())->pluck('n', $this->pivotKey());

        return response()->json(['items' => $query->get()->map(fn ($item) => $item->toSummary() + [
            'location_count' => (int) ($counts[$item->id] ?? 0),
        ])->all()]);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(['item' => $this->query()->findOrFail($id)->toApi(true)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $class = $this->modelClass();
        $item = $class::query()->create($data + [
            'uuid' => (string) Str::uuid(),
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);
        AuditLogger::log('create', $this->entity(), $item->id, $item->name);

        return response()->json(['item' => $this->query()->find($item->id)->toApi(true)], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $item = $this->query()->findOrFail($id);
        $data = $this->validated($request);
        $before = $item->only(array_keys($data));
        $item->fill($data + ['updated_by' => Auth::id()]);
        $item->touch();
        AuditLogger::log('update', $this->entity(), $item->id, $item->name, AuditLogger::diff(
            array_map(fn ($v) => $v instanceof \BackedEnum ? $v->value : $v, $before),
            array_map(fn ($v) => $v instanceof \BackedEnum ? $v->value : $v, $item->only(array_keys($data)))
        ));

        return response()->json(['item' => $this->query()->find($item->id)->toApi(true)]);
    }

    /** Ohne ?force=1 wird nicht gelöscht, solange Fundorte zugeordnet sind (409 mit Liste). */
    public function destroy(Request $request, int $id, ImageService $images): JsonResponse
    {
        $item = $this->query()->findOrFail($id);
        $locations = $item->toApi(true)['locations'];
        if ($locations && ! $request->boolean('force')) {
            return response()->json([
                'message' => 'Dieser Eintrag ist noch Fundorten zugeordnet.',
                'locations' => $locations,
            ], 409);
        }
        $mediaId = $item->image_media_id;
        DB::transaction(fn () => $item->delete());
        $images->deleteIfUnused($mediaId);
        AuditLogger::log('delete', $this->entity(), $id, $item->name, ['locations' => count($locations)]);

        return response()->json(['deleted' => true]);
    }

    public function uploadImage(Request $request, int $id, ImageService $images): JsonResponse
    {
        $item = $this->query()->findOrFail($id);
        $request->validate(['file' => ['required', 'file']], ['file.required' => 'Es wurde kein Bild übertragen.']);
        $media = $images->store($request->file('file'));
        $old = $item->image_media_id;
        $item->update(['image_media_id' => $media->id, 'updated_by' => Auth::id()]);
        $images->deleteIfUnused($old);
        AuditLogger::log('image', $this->entity(), $item->id, $item->name);

        return response()->json(['item' => $this->query()->find($item->id)->toApi(true)]);
    }

    public function deleteImage(int $id, ImageService $images): JsonResponse
    {
        $item = $this->query()->findOrFail($id);
        $old = $item->image_media_id;
        $item->update(['image_media_id' => null, 'updated_by' => Auth::id()]);
        $images->deleteIfUnused($old);

        return response()->json(['item' => $this->query()->find($item->id)->toApi(true)]);
    }

    protected function validated(Request $request): array
    {
        $class = $this->modelClass();
        $rules = ['name' => ['required', 'string', 'max:120', 'regex:/\S/u'], 'notes' => ['nullable', 'string', 'max:5000']];
        foreach ($class::LOOKUPS as $column => $category) {
            $rules[$column] = ['nullable', 'integer', Rule::exists('lookup_values', 'id')->where('category', $category)];
        }
        foreach ($class::STATS as $stat) {
            $rules["stats.$stat"] = ['nullable', new StatValue];
        }
        $rules['stats'] = ['nullable', 'array'];
        $data = $request->validate($rules + $this->rules(), [
            'name.required' => 'Der Name fehlt.',
            '*.exists' => 'Dieser Listenwert existiert nicht (mehr).',
        ]);

        $out = ['name' => trim($data['name']), 'notes' => isset($data['notes']) && trim($data['notes']) !== '' ? $data['notes'] : null];
        foreach (array_keys($class::LOOKUPS) as $column) {
            $out[$column] = $data[$column] ?? null;
        }
        foreach ($class::STATS as $stat) {
            $value = $data['stats'][$stat] ?? null;
            $out[$stat] = $value === null ? null : round((float) $value, 1);
        }
        foreach (array_keys($this->rules()) as $field) {
            $out[$field] = $data[$field] ?? null;
        }

        return $out;
    }
}
