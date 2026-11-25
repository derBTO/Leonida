<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\DatabaseBackup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class BackupTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = storage_path('framework/testing/backups-'.getmypid());
        File::deleteDirectory($this->dir);
        config(['leonida.backup_dir' => $this->dir]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    /** Alle gesicherten Tabellen als vergleichbarer Schnappschuss */
    private function snapshot(): array
    {
        $out = [];
        foreach (app(DatabaseBackup::class)->tables() as $t) {
            $out[$t] = array_map(fn ($r) => (array) $r, DB::select("SELECT * FROM \"$t\" ORDER BY 1"));
        }

        return $out;
    }

    private function seedData(): void
    {
        $this->actingAs($this->admin());
        $type = $this->postJson('/api/lookups/weapon_type', ['name' => 'Pistole'])->json('item.id');
        $weapon = $this->postJson('/api/weapons', ['name' => 'Girardi ES9', 'weapon_type_id' => $type, 'is_legendary' => true, 'stats' => ['damage' => 4.2, 'range' => 0]])->json('item.id');
        $pin = $this->postJson('/api/pins', ['title' => 'Motel „Sunset“', 'description' => "Zeile 1\nZeile 2", 'x' => -1.5, 'y' => 2.25,
            'loot_types' => ['weapons', 'cash'], 'weapon_ids' => [$weapon], 'cash_usd' => 900, 'codes' => [['label' => 'Safe', 'code' => '0042']]])->json('pin.id');
        $this->post("/api/pins/$pin/images", ['file' => $this->jpeg(), 'caption' => 'Tür'], ['Accept' => 'application/json'])->assertCreated();
        $trail = $this->postJson('/api/trails', ['name' => 'Spur', 'steps' => [['title' => 'A', 'pin_id' => $pin], ['title' => 'B', 'parallel' => true]]])->json('item');
        $this->putJson("/api/trails/{$trail['id']}/steps/{$trail['steps'][0]['id']}/progress", ['done' => true])->assertOk();
    }

    public function test_php_backup_restores_everything(): void
    {
        $this->seedData();
        $before = $this->snapshot();
        $this->artisan('leonida:backup', ['--php' => true])->assertSuccessful();
        $file = glob("$this->dir/leonida-*.json.gz")[0];

        // Schaden anrichten
        DB::table('trail_step_progress')->delete();
        DB::table('pins')->delete();
        DB::table('weapons')->update(['name' => 'kaputt']);

        $this->artisan('leonida:restore', ['file' => $file, '--check' => true])->assertSuccessful();
        $this->assertSame(0, DB::table('pins')->count(), '--check ändert nichts');

        $this->artisan('leonida:restore', ['file' => $file, '--force' => true])->assertSuccessful();
        $this->assertEquals($before, $this->snapshot());

        // Sequenzen stehen hinter den wiederhergestellten IDs
        $this->actingAs(User::query()->where('role', 'admin')->first());
        $new = $this->postJson('/api/pins', ['title' => 'Neu', 'x' => 0, 'y' => 0])->assertCreated()->json('pin.id');
        $this->assertGreaterThan(max(array_column($before['pins'], 'id')), $new);
    }

    public function test_restore_refuses_other_migration_state(): void
    {
        $this->seedData();
        $this->artisan('leonida:backup', ['--php' => true])->assertSuccessful();
        $file = glob("$this->dir/leonida-*.json.gz")[0];
        DB::table('migrations')->insert(['migration' => '2099_01_01_000000_zukunft', 'batch' => 99]);
        $this->artisan('leonida:restore', ['file' => $file, '--force' => true])->assertFailed();
        $this->assertSame(1, DB::table('pins')->count());
    }

    public function test_prune_keeps_newest_of_both_formats(): void
    {
        File::ensureDirectoryExists($this->dir);
        foreach (['2026-11-20-033000.dump', '2026-11-21-033000.json.gz', '2026-11-22-033000.json.gz', '2026-11-23-033000.dump'] as $n) {
            touch("$this->dir/leonida-$n");
        }
        $this->assertSame(2, app(DatabaseBackup::class)->prune($this->dir, 2));
        $left = array_map('basename', glob("$this->dir/leonida-*"));
        sort($left);
        $this->assertSame(['leonida-2026-11-22-033000.json.gz', 'leonida-2026-11-23-033000.dump'], $left);
    }

    public function test_pg_dump_is_only_used_when_new_enough(): void
    {
        config(['leonida.pg_dump' => '/nicht/vorhanden/pg_dump']);
        $found = app(DatabaseBackup::class)->findPgDump();
        // im Test-Container passt pg_dump zur Serverversion; ein fehlendes Programm wird ignoriert
        $this->assertNotSame('/nicht/vorhanden/pg_dump', $found);
    }
}
