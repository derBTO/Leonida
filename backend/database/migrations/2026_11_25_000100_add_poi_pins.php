<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Orte (POI): Pins mit genau einer POI-Kategorie und ohne Loot-Felder.
 * Die Liste der Kategorien entspricht App\Enums\PoiType.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        ALTER TABLE pins ADD COLUMN kind varchar(10) NOT NULL DEFAULT 'loot' CHECK (kind IN ('loot', 'poi'));
        ALTER TABLE pins ADD COLUMN poi_type varchar(30) NULL CHECK (poi_type IN ('poi', 'photo', 'ammunation', 'bar', 'gas_station', 'groceries', 'clothing', 'tattoo', 'garage', 'tuning', 'barber', 'pay_and_spray', 'workshop', 'airport', 'harbor', 'military', 'restaurant', 'apartment', 'race', 'bus_stop', 'train_stop'));
        ALTER TABLE pins ADD CONSTRAINT pins_poi_type_matches_kind CHECK ((kind = 'poi') = (poi_type IS NOT NULL));
        CREATE INDEX pins_kind_poi_index ON pins (kind, poi_type);
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('ALTER TABLE pins DROP COLUMN poi_type, DROP COLUMN kind');
    }
};
