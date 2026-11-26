<?php

namespace App\Console\Commands;

use App\Enums\PinKind;
use App\Enums\Role;
use App\Models\Pin;
use App\Models\User;
use App\Models\Weapon;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;

/**
 * Feste Testdaten für die Browser-Tests (frontend/e2e). Nur außerhalb von production.
 * Erwartet eine frisch migrierte Datenbank (scripts/e2e-server.sh).
 */
class E2eSeed extends Command
{
    protected $signature = 'leonida:e2e-seed';

    protected $description = 'Testdaten für die Browser-Tests anlegen (nicht in production)';

    public const PASSWORD = 'e2e-passwort-123';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('Nicht in production.');

            return self::FAILURE;
        }
        $user = fn (string $name, Role $role) => User::query()->create([
            'username' => $name, 'password' => self::PASSWORD, 'role' => $role, 'is_active' => true, 'must_change_password' => false,
        ]);
        $admin = $user('e2e-admin', Role::Admin);
        $user('e2e-alice', Role::Editor);
        $user('e2e-bob', Role::Editor);

        Pin::query()->create([
            'uuid' => Uuid::uuid5(Uuid::NAMESPACE_URL, 'https://map.gtadb.org/#VI,e2e')->toString(), 'kind' => PinKind::Poi,
            'poi_type' => 'gas_station', 'source' => 'gtadb', 'title' => 'E2E gtadb-Tankstelle', 'x' => -3600, 'y' => 1100,
            'status' => 'confirmed', 'respawn' => 'unknown',
        ]);
        $pin = Pin::query()->create([
            'uuid' => (string) Str::uuid(), 'kind' => PinKind::Loot, 'title' => 'E2E Admin-Versteck', 'x' => -3400, 'y' => 1000,
            'status' => 'confirmed', 'respawn' => 'unknown', 'created_by' => $admin->id, 'updated_by' => $admin->id,
        ]);
        $pin->syncLootTypes(['cash']);
        Weapon::query()->create(['uuid' => (string) Str::uuid(), 'name' => 'E2E Test-Pistole', 'is_legendary' => true, 'created_by' => $admin->id]);
        $this->info('E2E-Testdaten angelegt.');

        return self::SUCCESS;
    }
}
