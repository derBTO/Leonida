<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Spuren: geordnete Schritte (A → B → C → Loot), je Schritt optional ein Pin.
 * Schritte mit gleicher Nummer (position) gelten als parallel, Reihenfolge innerhalb über sort_order.
 * Fortschritt wird pro Benutzer und Schritt gespeichert.
 * Neue Pin-Art „hint“ (Hinweis) für reine Zwischenstationen.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        ALTER TABLE pins DROP CONSTRAINT IF EXISTS pins_kind_check;
        ALTER TABLE pins ADD CONSTRAINT pins_kind_check CHECK (kind IN ('loot', 'poi', 'hint'));
        CREATE INDEX pins_created_by_index ON pins (created_by);

        CREATE TABLE trails (
            id bigserial PRIMARY KEY,
            uuid uuid NOT NULL UNIQUE,
            name varchar(150) NOT NULL CHECK (btrim(name) <> ''),
            description text NULL,
            created_by bigint NULL REFERENCES users (id) ON DELETE SET NULL,
            updated_by bigint NULL REFERENCES users (id) ON DELETE SET NULL,
            created_at timestamp(0) NULL,
            updated_at timestamp(0) NULL
        );
        CREATE INDEX trails_name_lower_index ON trails (lower(name));

        CREATE TABLE trail_steps (
            id bigserial PRIMARY KEY,
            trail_id bigint NOT NULL REFERENCES trails (id) ON DELETE CASCADE,
            position integer NOT NULL CHECK (position BETWEEN 1 AND 1000),
            sort_order integer NOT NULL DEFAULT 0,
            pin_id bigint NULL REFERENCES pins (id) ON DELETE SET NULL,
            title varchar(200) NOT NULL CHECK (btrim(title) <> ''),
            description text NULL
        );
        CREATE INDEX trail_steps_trail_index ON trail_steps (trail_id, sort_order);
        CREATE INDEX trail_steps_pin_index ON trail_steps (pin_id);

        CREATE TABLE trail_step_progress (
            user_id bigint NOT NULL REFERENCES users (id) ON DELETE CASCADE,
            trail_step_id bigint NOT NULL REFERENCES trail_steps (id) ON DELETE CASCADE,
            done_at timestamp(0) NOT NULL DEFAULT now(),
            PRIMARY KEY (user_id, trail_step_id)
        );
        CREATE INDEX trail_step_progress_step_index ON trail_step_progress (trail_step_id);
        SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
        DROP TABLE IF EXISTS trail_step_progress;
        DROP TABLE IF EXISTS trail_steps;
        DROP TABLE IF EXISTS trails;
        DROP INDEX IF EXISTS pins_created_by_index;
        DELETE FROM pins WHERE kind = 'hint';
        ALTER TABLE pins DROP CONSTRAINT IF EXISTS pins_kind_check;
        ALTER TABLE pins ADD CONSTRAINT pins_kind_check CHECK (kind IN ('loot', 'poi'));
        SQL);
    }
};
