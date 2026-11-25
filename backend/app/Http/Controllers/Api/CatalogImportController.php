<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CatalogCsvImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** CSV-Import für Waffen und Fahrzeuge, nur für Administratoren. */
class CatalogImportController extends Controller
{
    public function import(Request $request, string $kind, CatalogCsvImport $import): JsonResponse
    {
        @set_time_limit(300);
        $request->validate([
            'file' => ['required', 'file', 'max:5120'],
            'dry_run' => ['nullable', 'boolean'],
            'overwrite' => ['nullable', 'boolean'],
        ], ['file.required' => 'Es wurde keine Datei übertragen.', 'file.max' => 'Die Datei ist größer als 5 MB.']);

        return response()->json(['report' => $import->import(
            $kind,
            $request->file('file')->getRealPath(),
            $request->boolean('dry_run'),
            $request->boolean('overwrite'),
        )]);
    }

    public function template(string $kind): Response
    {
        $name = $kind === 'weapons' ? 'waffen' : 'fahrzeuge';

        return response(CatalogCsvImport::template($kind), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"leonida-vorlage-$name.csv\"",
        ]);
    }
}
