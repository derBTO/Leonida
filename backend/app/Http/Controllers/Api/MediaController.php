<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\ImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Bild hochladen, bevor der Eintrag gespeichert ist (Hinweis-Schritte im Editor).
 * Das Bild gehört erst nach dem Speichern zu einem Schritt; nie verwendete Bilder räumt
 * `php artisan leonida:media-cleanup` auf.
 */
class MediaController extends Controller
{
    public function store(Request $request, ImageService $images): JsonResponse
    {
        $request->validate(['file' => ['required', 'file']], ['file.required' => 'Es wurde kein Bild übertragen.']);
        $media = $images->store($request->file('file'));
        AuditLogger::log('create', 'media', null, $media->original_name ?? $media->id);

        return response()->json(['media' => $media->toApi()], 201);
    }
}
