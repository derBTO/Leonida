<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Legendär/Unique-Kennzeichen für Waffen. NULL = unbekannt, getrennt von „Nein“. */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE weapons ADD COLUMN is_legendary boolean NULL');
        DB::statement('CREATE INDEX weapons_legendary_index ON weapons (is_legendary) WHERE is_legendary');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE weapons DROP COLUMN is_legendary');
    }
};
