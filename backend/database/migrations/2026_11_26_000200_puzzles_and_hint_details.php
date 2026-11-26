<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Puzzle (früher „Spuren“) und ausgebaute Hinweise:
 * - Puzzle-Belohnung: verknüpfte Waffen und Fahrzeuge (trail_weapons, trail_vehicles)
 * - Puzzle-Schritte zeigen nur noch auf Hinweise; bisherige Verknüpfungen zu anderen Pins werden gelöst
 * - Hinweis verknüpft einen Ort (place_pin_id) sowie Waffen/Fahrzeuge (pin_weapons/pin_vehicles)
 * - Hinweis-Schritte (pin_steps): Text und/oder Bild, ersetzen bei Hinweisen die Galerie
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        CREATE TABLE trail_weapons (
            trail_id bigint NOT NULL REFERENCES trails (id) ON DELETE CASCADE,
            weapon_id bigint NOT NULL REFERENCES weapons (id) ON DELETE CASCADE,
            sort_order integer NOT NULL DEFAULT 0,
            PRIMARY KEY (trail_id, weapon_id)
        );
        CREATE INDEX trail_weapons_weapon_index ON trail_weapons (weapon_id);
        CREATE TABLE trail_vehicles (
            trail_id bigint NOT NULL REFERENCES trails (id) ON DELETE CASCADE,
            vehicle_id bigint NOT NULL REFERENCES vehicles (id) ON DELETE CASCADE,
            sort_order integer NOT NULL DEFAULT 0,
            PRIMARY KEY (trail_id, vehicle_id)
        );
        CREATE INDEX trail_vehicles_vehicle_index ON trail_vehicles (vehicle_id);

        UPDATE trail_steps SET pin_id = NULL
            WHERE pin_id IN (SELECT id FROM pins WHERE kind <> 'hint');

        -- Selbstbezug: zurückstellbar, damit Sicherungen Pins in beliebiger Reihenfolge einspielen können
        ALTER TABLE pins ADD COLUMN place_pin_id bigint NULL
            REFERENCES pins (id) ON DELETE SET NULL DEFERRABLE INITIALLY IMMEDIATE;
        ALTER TABLE pins ADD CONSTRAINT pins_place_only_hint CHECK (place_pin_id IS NULL OR kind = 'hint');
        ALTER TABLE pins ADD CONSTRAINT pins_place_not_self CHECK (place_pin_id IS NULL OR place_pin_id <> id);
        CREATE INDEX pins_place_pin_index ON pins (place_pin_id) WHERE place_pin_id IS NOT NULL;

        CREATE TABLE pin_steps (
            id bigserial PRIMARY KEY,
            pin_id bigint NOT NULL REFERENCES pins (id) ON DELETE CASCADE,
            sort_order integer NOT NULL DEFAULT 0,
            text text NULL,
            media_id uuid NULL REFERENCES media (id) ON DELETE SET NULL,
            CHECK (text IS NOT NULL OR media_id IS NOT NULL)
        );
        CREATE INDEX pin_steps_pin_index ON pin_steps (pin_id, sort_order);
        CREATE INDEX pin_steps_media_index ON pin_steps (media_id);

        -- Galeriebilder bestehender Hinweise werden zu Schritten (Bildunterschrift = Text)
        INSERT INTO pin_steps (pin_id, sort_order, text, media_id)
            SELECT i.pin_id, i.sort_order, i.caption, i.media_id
            FROM pin_images i JOIN pins p ON p.id = i.pin_id WHERE p.kind = 'hint';
        DELETE FROM pin_images WHERE pin_id IN (SELECT id FROM pins WHERE kind = 'hint');
        SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
        INSERT INTO pin_images (pin_id, media_id, caption, sort_order)
            SELECT pin_id, media_id, text, sort_order FROM pin_steps WHERE media_id IS NOT NULL;
        DROP TABLE pin_steps;
        ALTER TABLE pins DROP COLUMN place_pin_id;
        DROP TABLE trail_vehicles;
        DROP TABLE trail_weapons;
        SQL);
    }
};
