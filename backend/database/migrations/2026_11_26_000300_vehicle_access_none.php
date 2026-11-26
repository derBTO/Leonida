<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Zugang „Ohne“ für Fahrzeuge, die man ohne Schlüssel/Keyless nutzen kann */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE vehicles DROP CONSTRAINT vehicles_access_check');
        DB::statement("ALTER TABLE vehicles ADD CONSTRAINT vehicles_access_check CHECK (access IN ('none', 'key', 'keyless'))");
    }

    public function down(): void
    {
        DB::statement("UPDATE vehicles SET access = NULL WHERE access = 'none'");
        DB::statement('ALTER TABLE vehicles DROP CONSTRAINT vehicles_access_check');
        DB::statement("ALTER TABLE vehicles ADD CONSTRAINT vehicles_access_check CHECK (access IN ('key', 'keyless'))");
    }
};
