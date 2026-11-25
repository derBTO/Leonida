<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Herkunft eines Pins (NULL = selbst angelegt, 'gtadb' = aus den gtadb.org-Landmarken übernommen).
 * Übernommene Orte haben eine UUID Version 5 (aus der Landmarken-URL), eigene Pins Version 4.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        ALTER TABLE pins ADD COLUMN source varchar(20) NULL CHECK (source IN ('gtadb'));
        UPDATE pins SET source = 'gtadb' WHERE substring(uuid::text, 15, 1) = '5';
        CREATE INDEX pins_source_index ON pins (source);
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('ALTER TABLE pins DROP COLUMN source');
    }
};
