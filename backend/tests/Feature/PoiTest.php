<?php

namespace Tests\Feature;

use App\Models\Pin;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class PoiTest extends TestCase
{
    private function poi(array $o = []): array
    {
        return $o + ['kind' => 'poi', 'poi_type' => 'gas_station', 'title' => 'Tankstelle Hafen', 'x' => 10, 'y' => 20];
    }

    public function test_poi_has_only_name_description_images(): void
    {
        $this->actingAs($this->editor());
        $pin = $this->postJson('/api/pins', $this->poi([
            'description' => '24/7', 'loot_types' => ['cash'], 'cash_usd' => 500,
            'codes' => [['code' => '1234']], 'requirements' => 'nachts', 'status' => 'gone',
        ]))->assertCreated()->json('pin');
        $this->assertSame('poi', $pin['kind']);
        $this->assertSame('gas_station', $pin['poi_type']);
        $this->assertSame('24/7', $pin['description']);
        $this->assertSame([], $pin['loot_types']);
        $this->assertNull($pin['cash_usd']);
        $this->assertSame([], $pin['codes']);
        $this->assertNull($pin['requirements']);
        $this->assertSame('confirmed', $pin['status']);
    }

    public function test_poi_needs_valid_category(): void
    {
        $this->actingAs($this->editor());
        $this->postJson('/api/pins', $this->poi(['poi_type' => null]))->assertJsonValidationErrors('poi_type');
        $this->postJson('/api/pins', $this->poi(['poi_type' => 'casino']))->assertJsonValidationErrors('poi_type');
    }

    public function test_loot_pin_ignores_poi_type_and_can_switch_kind(): void
    {
        $this->actingAs($this->editor());
        $id = $this->postJson('/api/pins', ['title' => 'Safe', 'x' => 0, 'y' => 0, 'loot_types' => ['cash'], 'poi_type' => 'bar'])
            ->assertCreated()->assertJsonPath('pin.kind', 'loot')->assertJsonPath('pin.poi_type', null)->json('pin.id');
        $this->putJson("/api/pins/$id", $this->poi(['poi_type' => 'bar']))->assertOk()
            ->assertJsonPath('pin.kind', 'poi')->assertJsonPath('pin.loot_types', []);
    }

    public function test_filters_separate_loot_and_poi(): void
    {
        $this->actingAs($this->editor());
        $loot = $this->postJson('/api/pins', ['title' => 'Safe', 'x' => 0, 'y' => 0, 'loot_types' => ['cash']])->json('pin.id');
        $gas = $this->postJson('/api/pins', $this->poi())->json('pin.id');
        $bar = $this->postJson('/api/pins', $this->poi(['poi_type' => 'bar', 'title' => 'Bar']))->json('pin.id');

        $this->assertSame([$loot], $this->getJson('/api/pins/search?kind=loot')->json('ids'));
        $this->assertEqualsCanonicalizing([$gas, $bar], $this->getJson('/api/pins/search?kind=poi')->json('ids'));
        $this->assertSame([$bar], $this->getJson('/api/pins/search?kind=poi&poi[]=bar')->json('ids'));
        // Ohne Art zählt nur die Auswahl: nur Loottypen → keine Orte, nur Kategorien → kein Loot, beides → Vereinigung
        $this->assertSame([$loot], $this->getJson('/api/pins/search?loot[]=cash')->json('ids'));
        $this->assertSame([$bar], $this->getJson('/api/pins/search?poi[]=bar')->json('ids'));
        $this->assertEqualsCanonicalizing([$loot, $bar], $this->getJson('/api/pins/search?poi[]=bar&loot[]=cash')->json('ids'));
        $index = collect($this->getJson('/api/pins')->json('pins'))->keyBy('id');
        $this->assertSame('poi', $index[$bar]['k']);
        $this->assertSame('bar', $index[$bar]['p']);
        $this->assertSame('loot', $index[$loot]['k']);
    }

    public function test_import_from_gtadb_landmarks_is_idempotent(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'lm');
        file_put_contents($file, json_encode([
            'L1' => ['Arrow Gas Station, Belville', [100.5, 200.25], [1, 1], 'Shell, Miami', [0, 0], [1, 1], ['retail', '#gas'], 'x', []],
            'L2' => ['?, Vice Beach', [5, 6], [1, 1], '?', [0, 0], [1, 1], ['#bus'], 'x', []],
            'L3' => ['Abgerissen', [1, 1], [1, 1], '', [0, 0], [1, 1], ['#gas', 'demolished'], 'x', []],
            'L4' => ['Ohne Tag', [1, 1], [1, 1], '', [0, 0], [1, 1], ['hotel'], 'x', []],
        ]));
        $this->artisan('leonida:import-gtadb-pois', ['--file' => $file])->assertSuccessful();
        $this->assertSame(0, Pin::query()->count(), 'Vorschau legt nichts an');
        $this->artisan('leonida:import-gtadb-pois', ['--file' => $file, '--apply' => true])->assertSuccessful();
        $this->assertSame(2, Pin::query()->count());
        $gas = Pin::query()->where('title', 'Arrow Gas Station, Belville')->first();
        $this->assertSame('gas_station', $gas->poi_type->value);
        $this->assertStringContainsString('CC BY 4.0', $gas->description);
        $this->assertStringContainsString('Vorbild: Shell, Miami', $gas->description);
        $this->assertSame('Bushaltestelle, Vice Beach', Pin::query()->where('poi_type', 'bus_stop')->value('title'));
        $this->assertSame('gtadb', $gas->source);
        $this->assertSame(['gtadb', 'gtadb'], array_column($this->getJson('/api/pins')->json('pins'), 'o'));
        $this->artisan('leonida:import-gtadb-pois', ['--file' => $file, '--apply' => true])->assertSuccessful();
        $this->assertSame(2, Pin::query()->count());
    }

    public function test_export_import_keeps_poi(): void
    {
        $this->actingAs($this->admin());
        $this->postJson('/api/pins', $this->poi(['poi_type' => 'harbor']))->assertCreated();
        $zip = tempnam(sys_get_temp_dir(), 'e').'.zip';
        copy($this->get('/api/admin/export')->baseResponse->getFile()->getPathname(), $zip);
        $this->post('/api/admin/import', ['file' => new UploadedFile($zip, 'e.zip', null, null, true), 'mode' => 'replace'], ['Accept' => 'application/json'])->assertOk();
        $this->assertSame('harbor', Pin::query()->first()->poi_type->value);
    }

    public function test_own_pins_have_no_source_and_authors_only_for_logged_in(): void
    {
        $this->assertSame([], $this->getJson('/api/meta')->json('authors'));
        $editor = $this->editor();
        $this->actingAs($editor);
        $this->postJson('/api/pins', $this->poi())->assertCreated()->assertJsonPath('pin.source', null);
        $this->assertContains(['id' => $editor->id, 'username' => $editor->username], $this->getJson('/api/meta')->json('authors'));
    }
}
