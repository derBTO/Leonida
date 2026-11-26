<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Easteregg-Kennzeichen: gilt für jede Pin-Art und bestimmt das Kartensymbol. */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        ALTER TABLE pins ADD COLUMN is_easteregg boolean NOT NULL DEFAULT false;
        CREATE INDEX pins_easteregg_index ON pins (is_easteregg) WHERE is_easteregg;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('ALTER TABLE pins DROP COLUMN is_easteregg');
    }
};
