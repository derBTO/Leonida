<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pin;
use App\Models\PinImage;
use App\Services\AuditLogger;
use App\Services\ImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PinImageController extends Controller
{
    public function store(Request $request, Pin $pin, ImageService $images): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file'],
            'caption' => ['nullable', 'string', 'max:2000'],
        ], ['file.required' => 'Es wurde kein Bild übertragen.']);
        if ($pin->images()->count() >= 100) {
            return response()->json(['message' => 'Ein Pin kann höchstens 100 Bilder haben.'], 422);
        }
        $media = $images->store($request->file('file'));
        $image = $pin->images()->create([
            'media_id' => $media->id,
            'caption' => $this->clean($request->input('caption')),
            'sort_order' => (int) $pin->images()->max('sort_order') + 1,
        ]);
        $pin->update(['updated_by' => Auth::id()]);
        AuditLogger::log('create', 'pin_image', $image->id, $pin->title);

        return response()->json(['image' => $image->load('media')->toApi()], 201);
    }

    public function update(Request $request, PinImage $image): JsonResponse
    {
        $data = $request->validate(['caption' => ['nullable', 'string', 'max:2000']]);
        $image->update(['caption' => $this->clean($data['caption'] ?? null)]);
        $image->pin->update(['updated_by' => Auth::id()]);

        return response()->json(['image' => $image->load('media')->toApi()]);
    }

    public function reorder(Request $request, Pin $pin): JsonResponse
    {
        $data = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer', 'distinct']]);
        $existing = $pin->images()->pluck('id')->sort()->values()->all();
        $given = collect($data['ids'])->sort()->values()->all();
        if ($existing !== $given) {
            return response()->json(['message' => 'Die Bildliste ist nicht aktuell. Bitte neu laden.'], 409);
        }
        DB::transaction(function () use ($data, $pin) {
            foreach ($data['ids'] as $i => $id) {
                PinImage::query()->whereKey($id)->where('pin_id', $pin->id)->update(['sort_order' => $i]);
            }
        });

        return response()->json(['images' => $pin->images()->with('media')->get()->map->toApi()->all()]);
    }

    public function destroy(PinImage $image, ImageService $images): JsonResponse
    {
        $mediaId = $image->media_id;
        $pin = $image->pin;
        $image->delete();
        $images->deleteIfUnused($mediaId);
        $pin->update(['updated_by' => Auth::id()]);
        AuditLogger::log('delete', 'pin_image', $image->id, $pin->title);

        return response()->json(['deleted' => true]);
    }

    private function clean(?string $text): ?string
    {
        return $text === null || trim($text) === '' ? null : trim($text);
    }
}
