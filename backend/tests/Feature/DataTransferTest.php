<?php

namespace Tests\Feature;

use App\Models\LookupValue;
use App\Models\Pin;
use App\Models\Weapon;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class DataTransferTest extends TestCase
{
    public function test_export_import_roundtrip_restores_everything(): void
    {
        $this->actingAs($this->admin());
        $cal = $this->postJson('/api/lookups/caliber', ['name' => '.38'])->json('item.id');
        $weapon = $this->postJson('/api/weapons', ['name' => 'Revolver', 'caliber_id' => $cal, 'stats' => ['damage' => 2.5, 'range' => 0]])->json('item.id');
        $this->post("/api/weapons/$weapon/image", ['file' => $this->jpeg()], ['Accept' => 'application/json'])->assertOk();
        $pin = $this->postJson('/api/pins', [
            'title' => 'Motel', 'x' => 1.5, 'y' => -2.25, 'loot_types' => ['weapons', 'cash'], 'weapon_ids' => [$weapon],
            'cash_usd' => 300, 'codes' => [['label' => 'Safe', 'code' => '0007']],
        ])->json('pin.id');
        $this->post("/api/pins/$pin/images", ['file' => $this->jpeg(), 'caption' => 'Tür'], ['Accept' => 'application/json'])->assertCreated();
        $before = $this->getJson("/api/pins/$pin")->json('pin');

        $response = $this->get('/api/admin/export')->assertOk();
        $zip = tempnam(sys_get_temp_dir(), 'exp').'.zip';
        copy($response->baseResponse->getFile()->getPathname(), $zip);

        $upload = fn () => new UploadedFile($zip, 'export.zip', 'application/zip', null, true);
        $dry = $this->post('/api/admin/import', ['file' => $upload(), 'mode' => 'replace', 'dry_run' => 1], ['Accept' => 'application/json'])
            ->assertOk()->json('report');
        $this->assertSame(1, $dry['pins']['created']);
        $this->assertSame(1, Pin::query()->count(), 'Probelauf darf nichts ändern');

        $this->post('/api/admin/import', ['file' => $upload(), 'mode' => 'replace'], ['Accept' => 'application/json'])->assertOk();
        $after = $this->getJson('/api/pins')->json('pins.0');
        $restored = $this->getJson("/api/pins/{$after['id']}")->json('pin');
        foreach (['uuid', 'title', 'x', 'y', 'loot_types', 'cash_usd', 'codes'] as $key) {
            $this->assertEquals($key === 'codes' ? array_map(fn ($c) => array_diff_key($c, ['id' => 1]), $before[$key]) : $before[$key],
                $key === 'codes' ? array_map(fn ($c) => array_diff_key($c, ['id' => 1]), $restored[$key]) : $restored[$key], $key);
        }
        $this->assertSame('Revolver', $restored['weapons'][0]['name']);
        $this->assertSame(['damage' => 2.5, 'accuracy' => null, 'handling' => null, 'range' => 0], $restored['weapons'][0]['stats']);
        $this->assertSame('Tür', $restored['images'][0]['caption']);
        $this->assertSame('.38', Weapon::query()->first()->caliber->name);

        // Zusammenführen ist idempotent
        $this->post('/api/admin/import', ['file' => $upload(), 'mode' => 'merge'], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('report.pins.updated', 1);
        $this->assertSame(1, Pin::query()->count());
        $this->assertSame(1, LookupValue::query()->count());
    }

    public function test_non_export_zip_is_rejected(): void
    {
        $this->actingAs($this->admin());
        $zip = tempnam(sys_get_temp_dir(), 'z').'.zip';
        $archive = new \ZipArchive;
        $archive->open($zip, \ZipArchive::CREATE);
        $archive->addFromString('data.json', '{"format":"anderes"}');
        $archive->close();
        $this->post('/api/admin/import', ['file' => new UploadedFile($zip, 'x.zip', null, null, true), 'mode' => 'merge'], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors('file');
    }
}
