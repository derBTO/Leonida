<?php

namespace App\Http\Controllers\Api;

use App\Enums\LookupCategory;
use App\Http\Controllers\Controller;
use App\Models\LookupValue;
use App\Services\AuditLogger;
use App\Services\LookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LookupController extends Controller
{
    public function __construct(private LookupService $lookups) {}

    /** Alle Werte einer Liste, alphabetisch. Mit ?usage=1 inkl. Verwendungszahl. */
    public function index(Request $request, string $category): JsonResponse
    {
        $cat = $this->category($category);
        $values = LookupValue::query()->where('category', $cat)->orderByRaw('lower(name)')->get();

        return response()->json(['items' => $values->map(fn (LookupValue $v) => $v->toApi() + (
            $request->boolean('usage') ? ['usage' => $this->lookups->usageCount($v)] : []
        ))->all()]);
    }

    /** Legt einen Wert an oder liefert den vorhandenen (Groß-/Kleinschreibung egal). */
    public function store(Request $request, string $category): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100']]);
        [$value, $created] = $this->lookups->findOrCreate($this->category($category), $data['name']);

        return response()->json(['item' => $value->toApi(), 'created' => $created], $created ? 201 : 200);
    }

    public function update(Request $request, LookupValue $value): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100', 'regex:/\S/u']]);

        return response()->json(['item' => $this->lookups->rename($value, $data['name'])->toApi()]);
    }

    public function merge(Request $request, LookupValue $value): JsonResponse
    {
        $data = $request->validate(['target_id' => ['required', 'integer', 'exists:lookup_values,id']]);
        $target = LookupValue::query()->findOrFail($data['target_id']);
        $moved = $this->lookups->merge($value, $target);

        return response()->json(['item' => $target->toApi(), 'moved' => $moved]);
    }

    public function destroy(LookupValue $value): JsonResponse
    {
        $usage = $this->lookups->usageCount($value);
        if ($usage > 0) {
            return response()->json(['message' => "Der Wert wird noch $usage-mal verwendet. Bitte zusammenführen statt löschen."], 409);
        }
        $value->delete();
        AuditLogger::log('delete', 'lookup', $value->id, $value->name);

        return response()->json(['deleted' => true]);
    }

    private function category(string $category): LookupCategory
    {
        return LookupCategory::tryFrom($category) ?? abort(404, 'Unbekannte Liste.');
    }
}
