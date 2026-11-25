<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Trail;
use App\Models\TrailStep;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Spuren (A → B → C → Loot). Lesen ist öffentlich, Fortschritt gibt es nur angemeldet und pro Person.
 * Schritte werden beim Speichern als Ganzes übergeben; vorhandene Schritte behalten über ihre id
 * den Fortschritt. „parallel“ = gleiche Nummer wie der vorige Schritt.
 */
class TrailController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate(['q' => ['nullable', 'string', 'max:100'], 'author' => ['nullable', 'integer']]);
        $query = Trail::query()->with(['steps', 'createdBy'])->orderByRaw('lower(name)');
        if ($q = trim((string) $request->query('q'))) {
            $query->whereRaw('lower(name) LIKE ?', ['%'.mb_strtolower(addcslashes($q, '\\%_')).'%']);
        }
        if ($request->filled('author')) {
            $query->where('created_by', (int) $request->query('author'));
        }
        $trails = $query->get();
        $done = Trail::doneSteps(Auth::id(), $trails->flatMap->steps->pluck('id')->all());

        return response()->json(['items' => $trails->map(fn (Trail $t) => $t->toSummary($done))->all()]);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(['item' => Trail::query()->findOrFail($id)->toApi(Auth::id())]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $trail = DB::transaction(function () use ($data) {
            $trail = Trail::query()->create([
                'uuid' => (string) Str::uuid(),
                'name' => $data['name'],
                'description' => $data['description'],
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);
            $this->syncSteps($trail, $data['steps']);
            AuditLogger::log('create', 'trail', $trail->id, $trail->name, ['steps' => count($data['steps'])]);

            return $trail;
        });

        return response()->json(['item' => $trail->fresh()->toApi(Auth::id())], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $trail = Trail::query()->findOrFail($id);
        $data = $this->validated($request);
        DB::transaction(function () use ($trail, $data) {
            $before = ['name' => $trail->name, 'steps' => $trail->steps()->count()];
            $trail->fill(['name' => $data['name'], 'description' => $data['description'], 'updated_by' => Auth::id()]);
            $trail->touch();
            $trail->save();
            $this->syncSteps($trail, $data['steps']);
            AuditLogger::log('update', 'trail', $trail->id, $trail->name, AuditLogger::diff($before, ['name' => $trail->name, 'steps' => count($data['steps'])]));
        });

        return response()->json(['item' => $trail->fresh()->toApi(Auth::id())]);
    }

    public function destroy(int $id): JsonResponse
    {
        $trail = Trail::query()->findOrFail($id);
        $trail->delete();
        AuditLogger::log('delete', 'trail', $id, $trail->name);

        return response()->json(['deleted' => true]);
    }

    /** Schritt für die angemeldete Person als erledigt markieren oder zurücksetzen. */
    public function progress(Request $request, int $id, int $step): JsonResponse
    {
        $data = $request->validate(['done' => ['required', 'boolean']]);
        $trail = Trail::query()->findOrFail($id);
        $stepModel = TrailStep::query()->where('trail_id', $trail->id)->findOrFail($step);
        $key = ['user_id' => Auth::id(), 'trail_step_id' => $stepModel->id];
        if ($data['done']) {
            DB::table('trail_step_progress')->insertOrIgnore($key + ['done_at' => now()]);
        } else {
            DB::table('trail_step_progress')->where($key)->delete();
        }

        return response()->json(['item' => $trail->toApi(Auth::id())]);
    }

    /** Eigenen Fortschritt der Spur löschen. */
    public function resetProgress(int $id): JsonResponse
    {
        $trail = Trail::query()->findOrFail($id);
        DB::table('trail_step_progress')->where('user_id', Auth::id())
            ->whereIn('trail_step_id', $trail->steps()->pluck('id'))->delete();

        return response()->json(['item' => $trail->toApi(Auth::id())]);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150', 'regex:/\S/u'],
            'description' => ['nullable', 'string', 'max:20000'],
            'steps' => ['present', 'array', 'max:200'],
            'steps.*.id' => ['nullable', 'integer'],
            'steps.*.title' => ['required', 'string', 'max:200', 'regex:/\S/u'],
            'steps.*.description' => ['nullable', 'string', 'max:5000'],
            'steps.*.pin_id' => ['nullable', 'integer', 'exists:pins,id'],
            'steps.*.parallel' => ['nullable', 'boolean'],
        ], [
            'name.required' => 'Der Name fehlt.',
            'name.regex' => 'Der Name darf nicht leer sein.',
            'steps.*.title.required' => 'Jeder Schritt braucht eine kurze Beschreibung.',
            'steps.*.title.regex' => 'Jeder Schritt braucht eine kurze Beschreibung.',
            'steps.*.pin_id.exists' => 'Der gewählte Pin existiert nicht (mehr).',
        ]);
        $blank = fn ($v) => is_string($v) && trim($v) !== '' ? $v : null;

        return [
            'name' => trim($data['name']),
            'description' => $blank($data['description'] ?? null),
            'steps' => array_map(fn ($s) => [
                'id' => $s['id'] ?? null,
                'title' => trim($s['title']),
                'description' => $blank($s['description'] ?? null),
                'pin_id' => $s['pin_id'] ?? null,
                'parallel' => (bool) ($s['parallel'] ?? false),
            ], array_values($data['steps'])),
        ];
    }

    private function syncSteps(Trail $trail, array $steps): void
    {
        $existing = $trail->steps()->get()->keyBy('id');
        $keep = [];
        $position = 0;
        foreach ($steps as $i => $s) {
            // Erster Schritt ist nie parallel; parallel = gleiche Nummer wie der vorige
            $position = ($s['parallel'] && $position > 0) ? $position : $position + 1;
            $attrs = ['position' => $position, 'sort_order' => $i, 'title' => $s['title'], 'description' => $s['description'], 'pin_id' => $s['pin_id']];
            $step = $s['id'] ? $existing->get($s['id']) : null;
            if ($step) {
                $step->update($attrs);
            } else {
                $step = $trail->steps()->create($attrs);
            }
            $keep[] = $step->id;
        }
        $trail->steps()->whereNotIn('id', $keep ?: [0])->delete();
    }
}
