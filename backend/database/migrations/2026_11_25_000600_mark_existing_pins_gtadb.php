<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Einmalig: Alle bis v0.9.2 vorhandenen Pins stammen aus dem gtadb.org-Import (Auskunft BTO, 25.11.2026).
 * Die Erkennung über die UUID in 2026_11_25_000500 hat auf dem Server nicht gegriffen.
 * Neue Pins danach bleiben ohne Herkunft; künftige gtadb-Importe setzen sie selbst.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('pins')->whereNull('source')->update(['source' => 'gtadb']);
    }

    public function down(): void
    {
        // nicht umkehrbar: vorher eigene und übernommene Pins sind danach nicht mehr unterscheidbar
    }
};
