<?php

namespace Tests\Feature;

use App\Models\Vehicle;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    public function test_weapon_with_lookups_and_stats(): void
    {
        $this->actingAs($this->editor());
        $type = $this->postJson('/api/lookups/weapon_type', ['name' => 'Revolver'])->assertCreated()->json('item.id');
        $mode = $this->postJson('/api/lookups/fire_mode', ['name' => 'Double-Action'])->json('item.id');
        $weapon = $this->postJson('/api/weapons', [
            'name' => 'Donnies H&L .38', 'weapon_type_id' => $type, 'fire_mode_id' => $mode, 'magazine_size' => 6,
            'stats' => ['damage' => 2.5, 'accuracy' => 5, 'handling' => '9.0', 'range' => 4.1],
        ])->assertCreated()->json('item');
        $this->assertSame('Revolver', $weapon['weapon_type']['name']);
        // JSON kennt keinen Unterschied zwischen 5 und 5.0
        $this->assertSame(['damage' => 2.5, 'accuracy' => 5, 'handling' => 9, 'range' => 4.1], $weapon['stats']);
    }

    public function test_unknown_values_stay_null_and_zero_stays_zero(): void
    {
        $this->actingAs($this->editor());
        $item = $this->postJson('/api/vehicles', ['name' => 'Unbekannt', 'stats' => ['speed' => 0]])->assertCreated()->json('item');
        $this->assertSame(0, $item['stats']['speed']);
        $this->assertNull($item['stats']['acceleration']);
        $this->assertNull($item['tracker']);
        $this->assertNull($item['security']);
        $this->assertNull($item['registrable']);
        $this->assertNull($item['rating_on_road']);

        $item = $this->postJson('/api/vehicles', ['name' => 'Ohne Tracker', 'tracker' => 'none', 'registrable' => false, 'security' => 'not_specified'])->json('item');
        $this->assertSame('none', $item['tracker']);
        $this->assertFalse($item['registrable']);
        $this->assertSame('not_specified', $item['security']);
    }

    public function test_stat_and_rating_ranges_are_enforced(): void
    {
        $this->actingAs($this->editor());
        $this->postJson('/api/weapons', ['name' => 'X', 'stats' => ['damage' => 10.5]])->assertJsonValidationErrors('stats.damage');
        $this->postJson('/api/weapons', ['name' => 'X', 'stats' => ['damage' => 2.55]])->assertJsonValidationErrors('stats.damage');
        $this->postJson('/api/vehicles', ['name' => 'X', 'rating_on_road' => 1001])->assertJsonValidationErrors('rating_on_road');
        $this->postJson('/api/vehicles', ['name' => 'X', 'rating_off_road' => 12.5])->assertJsonValidationErrors('rating_off_road');
        $this->postJson('/api/vehicles', ['name' => 'X', 'drivetrain' => '4WD'])->assertJsonValidationErrors('drivetrain');
        $this->postJson('/api/vehicles', ['name' => 'X', 'tracker' => 'gps'])->assertJsonValidationErrors('tracker');
        $this->postJson('/api/vehicles', ['name' => 'X', 'access' => 'card'])->assertJsonValidationErrors('access');
    }

    public function test_lookup_of_wrong_category_is_rejected(): void
    {
        $this->actingAs($this->editor());
        $maker = $this->postJson('/api/lookups/vehicle_manufacturer', ['name' => 'Vapid'])->json('item.id');
        $this->postJson('/api/weapons', ['name' => 'X', 'brand_id' => $maker])->assertJsonValidationErrors('brand_id');
    }

    public function test_locations_and_delete_protection(): void
    {
        $this->actingAs($this->editor());
        $vehicle = $this->postJson('/api/vehicles', ['name' => 'Dinka Blista'])->json('item.id');
        $pin = $this->postJson('/api/pins', ['title' => 'Parkhaus', 'x' => 5, 'y' => 6, 'loot_types' => ['vehicles'], 'vehicle_ids' => [$vehicle]])->json('pin.id');
        $this->getJson("/api/vehicles/$vehicle")->assertJsonPath('item.locations.0.id', $pin);
        $this->getJson('/api/vehicles')->assertJsonPath('items.0.location_count', 1);
        $this->deleteJson("/api/vehicles/$vehicle")->assertStatus(409)->assertJsonPath('locations.0.title', 'Parkhaus');
        $this->deleteJson("/api/vehicles/$vehicle?force=1")->assertOk();
        $this->assertSame([], $this->getJson("/api/pins/$pin")->json('pin.vehicles'));
        $this->assertSame(0, Vehicle::query()->count());
    }

    public function test_main_image_upload_and_replace(): void
    {
        $this->actingAs($this->editor());
        $id = $this->postJson('/api/weapons', ['name' => 'Bild-Test'])->json('item.id');
        $first = $this->post("/api/weapons/$id/image", ['file' => $this->jpeg()], ['Accept' => 'application/json'])->assertOk()->json('item.image');
        $this->assertStringEndsWith('.webp', $first['url']);
        $second = $this->post("/api/weapons/$id/image", ['file' => $this->jpeg(30, 30)], ['Accept' => 'application/json'])->json('item.image');
        $this->assertNotSame($first['id'], $second['id']);
        $this->assertFileDoesNotExist(config('leonida.upload_dir').'/'.substr($first['url'], strlen('/media/')));
    }

    public function test_legendary_flag_distinguishes_unknown_and_no(): void
    {
        $this->actingAs($this->editor());
        $unknown = $this->postJson('/api/weapons', ['name' => 'Pistole'])->assertCreated()->json('item');
        $this->assertNull($unknown['is_legendary']);
        $no = $this->postJson('/api/weapons', ['name' => 'Revolver', 'is_legendary' => false])->json('item');
        $this->assertFalse($no['is_legendary']);
        $gold = $this->postJson('/api/weapons', ['name' => 'Goldene Deagle', 'is_legendary' => true])->json('item');
        $this->assertTrue($gold['is_legendary']);
        $this->postJson('/api/weapons', ['name' => 'X', 'is_legendary' => 'vielleicht'])->assertJsonValidationErrors('is_legendary');

        $this->assertSame(['Goldene Deagle'], array_column($this->getJson('/api/weapons?legendary=1')->json('items'), 'name'));
        $this->assertCount(3, $this->getJson('/api/weapons')->json('items'));
        $this->assertTrue($this->getJson('/api/weapons')->json('items.0.is_legendary'));

        $pin = $this->postJson('/api/pins', ['title' => 'Versteck', 'x' => 0, 'y' => 0, 'loot_types' => ['weapons'], 'weapon_ids' => [$gold['id']]])->json('pin.id');
        $this->assertTrue($this->getJson("/api/pins/$pin")->json('pin.weapons.0.is_legendary'));
    }
}
