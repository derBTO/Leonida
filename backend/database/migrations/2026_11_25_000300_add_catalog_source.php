<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Quellenangabe je Waffe/Fahrzeug (z. B. „NiaDB“ mit Link zur Herkunftsseite). */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['weapons', 'vehicles'] as $table) {
            DB::statement("ALTER TABLE $table ADD COLUMN source_name varchar(100) NULL, ADD COLUMN source_url varchar(500) NULL");
            DB::statement("ALTER TABLE $table ADD CONSTRAINT {$table}_source_url_http CHECK (source_url IS NULL OR source_url ~ '^https?://')");
        }
    }

    public function down(): void
    {
        foreach (['weapons', 'vehicles'] as $table) {
            DB::statement("ALTER TABLE $table DROP CONSTRAINT IF EXISTS {$table}_source_url_http, DROP COLUMN IF EXISTS source_name, DROP COLUMN IF EXISTS source_url");
        }
    }
};
