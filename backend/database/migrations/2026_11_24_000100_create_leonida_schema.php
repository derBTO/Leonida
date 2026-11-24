<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Leonida-Datenmodell. Bewusst als SQL geschrieben, weil es PostgreSQL-Funktionen nutzt
 * (Check-Constraints, zusammengesetzte Fremdschlüssel mit konstanter Kategorie-Spalte).
 *
 * Grundregel: unbekannt = NULL. Kein Feld bekommt einen Ersatzwert wie 0.
 */
return new class extends Migration
{
    public function up(): void
    {
        $stat = fn (string $col) => "$col numeric(3,1) NULL CHECK ($col >= 0 AND $col <= 10)";

        DB::unprepared(<<<SQL
        CREATE TABLE map_versions (
            id bigserial PRIMARY KEY,
            version varchar(40) NOT NULL UNIQUE,
            source_commit varchar(40) NULL,
            transform jsonb NULL,
            is_active boolean NOT NULL DEFAULT false,
            created_at timestamp(0) NULL,
            updated_at timestamp(0) NULL
        );
        CREATE UNIQUE INDEX map_versions_one_active ON map_versions (is_active) WHERE is_active;

        CREATE TABLE media (
            id uuid PRIMARY KEY,
            path varchar(255) NOT NULL,
            thumb_path varchar(255) NOT NULL,
            mime varchar(50) NOT NULL,
            width integer NOT NULL,
            height integer NOT NULL,
            bytes integer NOT NULL,
            sha256 char(64) NOT NULL,
            original_name varchar(255) NULL,
            created_by bigint NULL REFERENCES users(id) ON DELETE SET NULL,
            created_at timestamp(0) NULL,
            updated_at timestamp(0) NULL
        );

        CREATE TABLE lookup_values (
            id bigserial PRIMARY KEY,
            category varchar(30) NOT NULL CHECK (category IN (
                'weapon_type', 'fire_mode', 'weapon_brand', 'caliber',
                'vehicle_manufacturer', 'vehicle_class', 'vehicle_style')),
            name varchar(100) NOT NULL CHECK (btrim(name) <> ''),
            name_key varchar(100) NOT NULL,
            created_at timestamp(0) NULL,
            updated_at timestamp(0) NULL,
            UNIQUE (category, name_key),
            UNIQUE (id, category)
        );

        CREATE TABLE weapons (
            id bigserial PRIMARY KEY,
            uuid uuid NOT NULL UNIQUE,
            name varchar(120) NOT NULL CHECK (btrim(name) <> ''),
            image_media_id uuid NULL REFERENCES media(id) ON DELETE SET NULL,
            weapon_type_id bigint NULL,
            weapon_type_cat varchar(30) GENERATED ALWAYS AS ('weapon_type') STORED,
            fire_mode_id bigint NULL,
            fire_mode_cat varchar(30) GENERATED ALWAYS AS ('fire_mode') STORED,
            brand_id bigint NULL,
            brand_cat varchar(30) GENERATED ALWAYS AS ('weapon_brand') STORED,
            caliber_id bigint NULL,
            caliber_cat varchar(30) GENERATED ALWAYS AS ('caliber') STORED,
            magazine_size integer NULL CHECK (magazine_size >= 0),
            {$stat('damage')},
            {$stat('accuracy')},
            {$stat('handling')},
            {$stat('range')},
            notes text NULL,
            created_by bigint NULL REFERENCES users(id) ON DELETE SET NULL,
            updated_by bigint NULL REFERENCES users(id) ON DELETE SET NULL,
            created_at timestamp(0) NULL,
            updated_at timestamp(0) NULL,
            FOREIGN KEY (weapon_type_id, weapon_type_cat) REFERENCES lookup_values(id, category),
            FOREIGN KEY (fire_mode_id, fire_mode_cat) REFERENCES lookup_values(id, category),
            FOREIGN KEY (brand_id, brand_cat) REFERENCES lookup_values(id, category),
            FOREIGN KEY (caliber_id, caliber_cat) REFERENCES lookup_values(id, category)
        );

        CREATE TABLE vehicles (
            id bigserial PRIMARY KEY,
            uuid uuid NOT NULL UNIQUE,
            name varchar(120) NOT NULL CHECK (btrim(name) <> ''),
            image_media_id uuid NULL REFERENCES media(id) ON DELETE SET NULL,
            manufacturer_id bigint NULL,
            manufacturer_cat varchar(30) GENERATED ALWAYS AS ('vehicle_manufacturer') STORED,
            class_id bigint NULL,
            class_cat varchar(30) GENERATED ALWAYS AS ('vehicle_class') STORED,
            style_id bigint NULL,
            style_cat varchar(30) GENERATED ALWAYS AS ('vehicle_style') STORED,
            drivetrain varchar(3) NULL CHECK (drivetrain IN ('FWD', 'RWD', 'AWD')),
            seats integer NULL CHECK (seats >= 0 AND seats <= 100),
            security varchar(20) NULL CHECK (security IN ('not_specified', 'basic', 'modern', 'advanced')),
            tracker varchar(20) NULL CHECK (tracker IN ('none', 'standard', 'advanced', 'military')),
            access varchar(20) NULL CHECK (access IN ('key', 'keyless')),
            registrable boolean NULL,
            {$stat('speed')},
            {$stat('acceleration')},
            {$stat('handling_on_road')},
            {$stat('handling_off_road')},
            {$stat('durability')},
            rating_on_road smallint NULL CHECK (rating_on_road >= 0 AND rating_on_road <= 1000),
            rating_off_road smallint NULL CHECK (rating_off_road >= 0 AND rating_off_road <= 1000),
            notes text NULL,
            created_by bigint NULL REFERENCES users(id) ON DELETE SET NULL,
            updated_by bigint NULL REFERENCES users(id) ON DELETE SET NULL,
            created_at timestamp(0) NULL,
            updated_at timestamp(0) NULL,
            FOREIGN KEY (manufacturer_id, manufacturer_cat) REFERENCES lookup_values(id, category),
            FOREIGN KEY (class_id, class_cat) REFERENCES lookup_values(id, category),
            FOREIGN KEY (style_id, style_cat) REFERENCES lookup_values(id, category)
        );

        CREATE TABLE pins (
            id bigserial PRIMARY KEY,
            uuid uuid NOT NULL UNIQUE,
            title varchar(150) NOT NULL CHECK (btrim(title) <> ''),
            description text NULL,
            x numeric(9,2) NOT NULL CHECK (x BETWEEN -16384 AND 16384),
            y numeric(9,2) NOT NULL CHECK (y BETWEEN -16384 AND 16384),
            map_version_id bigint NULL REFERENCES map_versions(id) ON DELETE SET NULL,
            requirements text NULL,
            drugs_value_usd integer NULL CHECK (drugs_value_usd >= 0),
            jewelry_value_usd integer NULL CHECK (jewelry_value_usd >= 0),
            cash_usd integer NULL CHECK (cash_usd >= 0),
            respawn varchar(20) NOT NULL DEFAULT 'unknown' CHECK (respawn IN ('unknown', 'once', 'recurring')),
            status varchar(20) NOT NULL DEFAULT 'unconfirmed' CHECK (status IN ('unconfirmed', 'confirmed', 'gone')),
            created_by bigint NULL REFERENCES users(id) ON DELETE SET NULL,
            updated_by bigint NULL REFERENCES users(id) ON DELETE SET NULL,
            created_at timestamp(0) NULL,
            updated_at timestamp(0) NULL
        );
        CREATE INDEX pins_xy_index ON pins (x, y);
        CREATE INDEX pins_status_index ON pins (status);

        CREATE TABLE pin_loot_types (
            pin_id bigint NOT NULL REFERENCES pins(id) ON DELETE CASCADE,
            loot_type varchar(20) NOT NULL CHECK (loot_type IN ('vehicles', 'weapons', 'drugs', 'jewelry', 'cash')),
            PRIMARY KEY (pin_id, loot_type)
        );
        CREATE INDEX pin_loot_types_type_index ON pin_loot_types (loot_type);

        CREATE TABLE pin_weapons (
            pin_id bigint NOT NULL REFERENCES pins(id) ON DELETE CASCADE,
            weapon_id bigint NOT NULL REFERENCES weapons(id) ON DELETE CASCADE,
            sort_order integer NOT NULL DEFAULT 0,
            PRIMARY KEY (pin_id, weapon_id)
        );
        CREATE INDEX pin_weapons_weapon_index ON pin_weapons (weapon_id);

        CREATE TABLE pin_vehicles (
            pin_id bigint NOT NULL REFERENCES pins(id) ON DELETE CASCADE,
            vehicle_id bigint NOT NULL REFERENCES vehicles(id) ON DELETE CASCADE,
            sort_order integer NOT NULL DEFAULT 0,
            PRIMARY KEY (pin_id, vehicle_id)
        );
        CREATE INDEX pin_vehicles_vehicle_index ON pin_vehicles (vehicle_id);

        CREATE TABLE pin_codes (
            id bigserial PRIMARY KEY,
            pin_id bigint NOT NULL REFERENCES pins(id) ON DELETE CASCADE,
            label varchar(100) NULL,
            code varchar(100) NOT NULL CHECK (code <> ''),
            description text NULL,
            sort_order integer NOT NULL DEFAULT 0
        );
        CREATE INDEX pin_codes_pin_index ON pin_codes (pin_id);

        CREATE TABLE pin_images (
            id bigserial PRIMARY KEY,
            pin_id bigint NOT NULL REFERENCES pins(id) ON DELETE CASCADE,
            media_id uuid NOT NULL REFERENCES media(id) ON DELETE CASCADE,
            caption text NULL,
            sort_order integer NOT NULL DEFAULT 0,
            created_at timestamp(0) NULL,
            updated_at timestamp(0) NULL
        );
        CREATE INDEX pin_images_pin_index ON pin_images (pin_id, sort_order);

        CREATE TABLE audit_log (
            id bigserial PRIMARY KEY,
            user_id bigint NULL REFERENCES users(id) ON DELETE SET NULL,
            action varchar(30) NOT NULL,
            entity varchar(30) NOT NULL,
            entity_id varchar(40) NULL,
            summary varchar(255) NULL,
            changes jsonb NULL,
            created_at timestamp(0) NOT NULL DEFAULT now()
        );
        CREATE INDEX audit_log_entity_index ON audit_log (entity, entity_id);
        CREATE INDEX audit_log_created_index ON audit_log (created_at DESC);
        SQL);

        // Trigramm-Suche, falls die Erweiterung verfügbar ist. Ohne sie funktioniert die Suche
        // weiter (ILIKE), nur ohne Index.
        try {
            DB::statement('SAVEPOINT trgm');
            DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
            foreach ([
                'pins_title_trgm' => 'pins USING gin (lower(title) gin_trgm_ops)',
                'pins_description_trgm' => 'pins USING gin (lower(description) gin_trgm_ops)',
                'weapons_name_trgm' => 'weapons USING gin (lower(name) gin_trgm_ops)',
                'vehicles_name_trgm' => 'vehicles USING gin (lower(name) gin_trgm_ops)',
                'pin_codes_code_trgm' => 'pin_codes USING gin (lower(code) gin_trgm_ops)',
                'pin_codes_label_trgm' => 'pin_codes USING gin (lower(label) gin_trgm_ops)',
            ] as $name => $definition) {
                DB::statement("CREATE INDEX $name ON $definition");
            }
            DB::statement('RELEASE SAVEPOINT trgm');
        } catch (\Throwable $e) {
            DB::statement('ROLLBACK TO SAVEPOINT trgm');
            logger()->warning('pg_trgm nicht verfügbar, Suche läuft ohne Trigramm-Index: '.$e->getMessage());
            foreach (['pins' => 'title', 'weapons' => 'name', 'vehicles' => 'name'] as $table => $column) {
                DB::statement("CREATE INDEX {$table}_{$column}_lower ON $table (lower($column))");
            }
        }
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS audit_log, pin_images, pin_codes, pin_vehicles, pin_weapons,
            pin_loot_types, pins, vehicles, weapons, lookup_values, media, map_versions CASCADE');
    }
};
