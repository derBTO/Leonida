<?php

namespace Tests\Feature;

use App\Models\Pin;
use App\Models\Vehicle;
use App\Models\Weapon;
use Illuminate\Support\Str;
use Tests\TestCase;

class PinTest extends TestCase
{
    private function weapon(string $name): Weapon
    {
        return Weapon::query()->create(['uuid' => Str::uuid(), 'name' => $name]);
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + ['title' => 'Safe im Motel', 'x' => -1200.5, 'y' => 830.25, 'loot_types' => []];
    }

    public function test_only_title_and_position_are_required(): void
    {
        $this->actingAs($this->editor());
        $this->postJson('/api/pins', ['loot_types' => []])->assertStatus(422)->assertJsonValidationErrors(['title', 'x', 'y']);
        $pin = $this->postJson('/api/pins', $this->payload())->assertCreated()->json('pin');
        $this->assertSame('unknown', $pin['respawn']);
        $this->assertSame('unconfirmed', $pin['status']);
        $this->assertNull($pin['cash_usd']);
        $this->assertSame([], $pin['loot_types']);
    }

    public function test_full_pin_roundtrip(): void
    {
        $editor = $this->editor();
        $w1 = $this->weapon('Donnies H&L .38');
        $w2 = $this->weapon('Pump Shotgun');
        $v = Vehicle::query()->create(['uuid' => Str::uuid(), 'name' => 'Vapid Dominator']);
        $this->actingAs($editor);
        $pin = $this->postJson('/api/pins', $this->payload([
            'description' => 'Hinter der Rezeption',
            'loot_types' => ['weapons', 'cash', 'vehicles'],
            'weapon_ids' => [$w2->id, $w1->id],
            'vehicle_ids' => [$v->id],
            'cash_usd' => 2500,
            'codes' => [['label' => 'Safe', 'code' => '0042'], ['code' => '7']],
            'respawn' => 'recurring',
            'status' => 'confirmed',
        ]))->assertCreated()->json('pin');

        $this->assertSame(['cash', 'vehicles', 'weapons'], $pin['loot_types']);
        $this->assertSame(['Pump Shotgun', 'Donnies H&L .38'], array_column($pin['weapons'], 'name'));
        $this->assertSame('0042', $pin['codes'][0]['code']);
        $this->assertSame($editor->username, $pin['created_by']);
        $this->getJson("/api/pins/{$pin['id']}")->assertJsonPath('vehicles.0.name', null)->assertJsonPath('pin.vehicles.0.name', 'Vapid Dominator');
    }

    public function test_fields_of_unselected_loot_types_are_cleared(): void
    {
        $this->actingAs($this->editor());
        $w = $this->weapon('Pistole');
        $id = $this->postJson('/api/pins', $this->payload([
            'loot_types' => ['weapons', 'drugs'], 'weapon_ids' => [$w->id], 'drugs_value_usd' => 900, 'cash_usd' => 50,
        ]))->json('pin.id');
        $pin = $this->getJson("/api/pins/$id")->json('pin');
        $this->assertSame(900, $pin['drugs_value_usd']);
        $this->assertNull($pin['cash_usd']);

        $this->putJson("/api/pins/$id", $this->payload(['loot_types' => ['drugs'], 'weapon_ids' => [$w->id], 'drugs_value_usd' => 900]))->assertOk();
        $this->assertSame([], $this->getJson("/api/pins/$id")->json('pin.weapons'));
    }

    public function test_codes_must_be_strings(): void
    {
        $this->actingAs($this->editor());
        $this->postJson('/api/pins', $this->payload(['codes' => [['code' => 42]]]))
            ->assertStatus(422)->assertJsonValidationErrors('codes.0.code');
    }

    public function test_validation_of_fixed_lists_and_amounts(): void
    {
        $this->actingAs($this->editor());
        $this->postJson('/api/pins', $this->payload(['loot_types' => ['gold']]))->assertJsonValidationErrors('loot_types.0');
        $this->postJson('/api/pins', $this->payload(['status' => 'maybe']))->assertJsonValidationErrors('status');
        $this->postJson('/api/pins', $this->payload(['respawn' => 'daily']))->assertJsonValidationErrors('respawn');
        $this->postJson('/api/pins', $this->payload(['loot_types' => ['cash'], 'cash_usd' => -5]))->assertJsonValidationErrors('cash_usd');
        $this->postJson('/api/pins', $this->payload(['x' => 99999]))->assertJsonValidationErrors('x');
        $this->postJson('/api/pins', $this->payload(['title' => '   ']))->assertJsonValidationErrors('title');
    }

    public function test_move_and_delete(): void
    {
        $this->actingAs($this->editor());
        $id = $this->postJson('/api/pins', $this->payload())->json('pin.id');
        $this->patchJson("/api/pins/$id/position", ['x' => 10.123, 'y' => -20])->assertOk()
            ->assertJsonPath('pin.x', 10.12)->assertJsonPath('pin.y', -20);
        $this->deleteJson("/api/pins/$id")->assertOk();
        $this->getJson("/api/pins/$id")->assertStatus(404)->assertJsonPath('message', 'Eintrag nicht gefunden.');
    }

    public function test_editing_a_weapon_shows_in_linked_pins(): void
    {
        $this->actingAs($this->admin());
        $w = $this->weapon('Alter Name');
        $id = $this->postJson('/api/pins', $this->payload(['loot_types' => ['weapons'], 'weapon_ids' => [$w->id]]))->json('pin.id');
        $this->putJson("/api/weapons/{$w->id}", ['name' => 'Neuer Name'])->assertOk();
        $this->assertSame('Neuer Name', $this->getJson("/api/pins/$id")->json('pin.weapons.0.name'));
        $this->assertSame(['Neuer Name'], $this->getJson('/api/pins')->json('pins.0.w'));
    }

    public function test_search_is_case_insensitive_and_covers_related_fields(): void
    {
        $this->actingAs($this->editor());
        $w = $this->weapon('Pump Shotgun');
        $a = $this->postJson('/api/pins', $this->payload(['title' => 'Motel', 'loot_types' => ['weapons'], 'weapon_ids' => [$w->id]]))->json('pin.id');
        $b = $this->postJson('/api/pins', $this->payload(['title' => 'Bank', 'description' => 'Tresor im KELLER', 'loot_types' => ['cash'], 'x' => 3000]))->json('pin.id');
        $c = $this->postJson('/api/pins', $this->payload(['title' => 'Garage', 'codes' => [['label' => 'Tor', 'code' => '0815']]]))->json('pin.id');

        $this->assertSame([$a], $this->getJson('/api/pins/search?q=SHOTGUN')->json('ids'));
        $this->assertSame([$b], $this->getJson('/api/pins/search?q=keller')->json('ids'));
        $this->assertSame([$c], $this->getJson('/api/pins/search?q=0815')->json('ids'));
        $this->assertSame([$c], $this->getJson('/api/pins/search?q=tor')->json('ids'));
        $this->assertEqualsCanonicalizing([$a, $b], $this->getJson('/api/pins/search?loot[]=weapons&loot[]=cash')->json('ids'));
        $this->assertSame([$c], $this->getJson('/api/pins/search?loot[]=none')->json('ids'));
        $this->assertSame([$b], $this->getJson('/api/pins/search?bbox=2000,0,4000,1000')->json('ids'));
        $this->assertSame([], $this->getJson('/api/pins/search?q=100%25')->json('ids'));
    }

    public function test_index_supports_etag(): void
    {
        Pin::query()->create(['uuid' => Str::uuid(), 'title' => 'A', 'x' => 0, 'y' => 0]);
        $etag = $this->getJson('/api/pins')->assertOk()->headers->get('ETag');
        $this->getJson('/api/pins', ['If-None-Match' => $etag])->assertStatus(304);
    }
}
