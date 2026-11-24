<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\DataTransfer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Export und Import aller eigenen Daten, nur für Administratoren. */
class DataController extends Controller
{
    public function export(DataTransfer $transfer): BinaryFileResponse
    {
        @set_time_limit(300);
        $file = $transfer->export();
        AuditLogger::log('export', 'data', null, 'Export');

        return response()->download($file, 'leonida-export-'.now()->format('Y-m-d-His').'.zip', [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend();
    }

    public function import(Request $request, DataTransfer $transfer): JsonResponse
    {
        @set_time_limit(300);
        $data = $request->validate([
            'file' => ['required', 'file'],
            'mode' => ['required', 'in:merge,replace'],
            'dry_run' => ['nullable', 'boolean'],
        ]);

        return response()->json(['report' => $transfer->import(
            $request->file('file')->getRealPath(),
            $data['mode'],
            $request->boolean('dry_run'),
        )]);
    }
}
