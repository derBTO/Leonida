<?php

namespace Tests\Feature;

use App\Models\Vehicle;
use App\Models\Weapon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Hinweise: Ort, Waffen/Fahrzeuge, Schritte; Puzzle: Belohnung, nur Hinweise als Schritte. */
class HintTest extends TestCase
{
    private function pin(array $data): array
    {
        return $this->postJson('/api/pins', $data + ['x' => 1, 'y' => 1])->assertCreated()->json('pin');
    }

    private function weapon(string $name): int
    {
        return Weapon::query()->create(['uuid' => (string) Str::uuid(), 'name' => $name])->id;
    }

    private function vehicle(string $name): int
    {
        return Vehicle::query()->create(['uuid' => (string) Str::uuid(), 'name' => $name])->id;
    }

    public function test_hint_links_place_weapons_vehicles_and_has_steps(): void
    {
        $this->actingAs($this->editor());
        $weapon = $this->weapon('Gil’s Kalinga');
        $vehicle = $this->vehicle('Vitello');
        $place = $this->pin(['kind' => 'poi', 'poi_type' => 'poi', 'title' => 'Insel']);
        $media = $this->post('/api/media', ['file' => $this->jpeg()], ['Accept' => 'application/json'])->assertCreated()->json('media');

        $hint = $this->pin(['kind' => 'hint', 'title' => 'Zettel', 'place_pin_id' => $place['id'], 'weapon_ids' => [$weapon], 'vehicle_ids' => [$vehicle],
            'codes' => [['code' => '4711']], 'steps' => [['text' => 'Als Erstes auf die Insel schwimmen.'], ['text' => 'Hinter dem Haus', 'media_id' => $media['id']], ['media_id' => $media['id']]]]);
        $this->assertSame('Insel', $hint['place']['title']);
        $this->assertSame('Gil’s Kalinga', $hint['weapons'][0]['name']);
        $this->assertSame('Vitello', $hint['vehicles'][0]['name']);
        $this->assertSame(['Als Erstes auf die Insel schwimmen.', 'Hinter dem Haus', null], array_column($hint['steps'], 'text'));
        $this->assertNull($hint['steps'][0]['image']);
        $this->assertSame($media['url'], $hint['steps'][1]['image']['url']);
        $this->assertSame([['id' => $hint['id'], 'title' => 'Zettel']], $this->getJson("/api/pins/{$place['id']}")->json('pin.hints'));

        // Ort über den Hinweis als Fundort bei Waffe und Fahrzeug, nicht der Hinweis selbst
        $this->assertSame(['Insel'], array_column($this->getJson("/api/weapons/$weapon")->json('item.locations'), 'title'));
        $this->assertSame(['Insel'], array_column($this->getJson("/api/vehicles/$vehicle")->json('item.locations'), 'title'));
        $this->assertSame(1, collect($this->getJson('/api/weapons')->json('items'))->firstWhere('id', $weapon)['location_count']);
    }

    public function test_place_already_a_loot_location_is_listed_once_and_hint_without_place_adds_nothing(): void
    {
        $this->actingAs($this->editor());
        $weapon = $this->weapon('Pistole');
        $loot = $this->pin(['title' => 'Versteck', 'loot_types' => ['weapons'], 'weapon_ids' => [$weapon]]);
        $this->pin(['kind' => 'hint', 'title' => 'Ohne Ort', 'weapon_ids' => [$weapon]]);
        $this->assertSame(['Versteck'], array_column($this->getJson("/api/weapons/$weapon")->json('item.locations'), 'title'));
        $this->assertSame($loot['id'], $this->getJson("/api/weapons/$weapon")->json('item.locations.0.id'));
    }

    public function test_validation_of_place_and_steps(): void
    {
        $this->actingAs($this->editor());
        $loot = $this->pin(['title' => 'Loot']);
        $this->postJson('/api/pins', ['kind' => 'hint', 'title' => 'H', 'x' => 0, 'y' => 0, 'place_pin_id' => $loot['id']])
            ->assertJsonValidationErrors(['place_pin_id' => 'Orts-Pin']);
        $this->postJson('/api/pins', ['kind' => 'hint', 'title' => 'H', 'x' => 0, 'y' => 0, 'steps' => [['text' => '  ']]])
            ->assertJsonValidationErrors(['steps.0.text' => 'Text oder ein Bild']);
        // Ort und Schritte gibt es nur bei Hinweisen
        $poi = $this->pin(['kind' => 'poi', 'poi_type' => 'poi', 'title' => 'Ort']);
        $plain = $this->pin(['title' => 'Loot 2', 'place_pin_id' => $poi['id'], 'steps' => [['text' => 'x']]]);
        $this->assertNull($plain['place']);
        $this->assertSame([], $plain['steps']);
    }

    public function test_hints_have_steps_instead_of_gallery_and_removed_step_images_are_deleted(): void
    {
        $this->actingAs($this->editor());
        $loot = $this->pin(['title' => 'Mit Bild']);
        $this->post("/api/pins/{$loot['id']}/images", ['file' => $this->jpeg(), 'caption' => 'Tür'], ['Accept' => 'application/json'])->assertCreated();

        // Umwandeln in einen Hinweis: Galeriebild wird Schritt
        $hint = $this->putJson("/api/pins/{$loot['id']}", ['kind' => 'hint', 'title' => 'Mit Bild', 'x' => 1, 'y' => 1, 'steps' => [['text' => 'Erst']]])
            ->assertOk()->json('pin');
        $this->assertSame([], $hint['images']);
        $this->assertSame(['Erst', 'Tür'], array_column($hint['steps'], 'text'));
        $this->post("/api/pins/{$loot['id']}/images", ['file' => $this->jpeg()], ['Accept' => 'application/json'])->assertStatus(422);

        $mediaId = $hint['steps'][1]['image']['id'];
        $this->putJson("/api/pins/{$loot['id']}", ['kind' => 'hint', 'title' => 'Mit Bild', 'x' => 1, 'y' => 1, 'steps' => [['text' => 'Nur Text']]])->assertOk();
        $this->assertFalse(DB::table('media')->where('id', $mediaId)->exists(), 'Bild des entfernten Schritts gelöscht');
    }

    public function test_puzzle_rewards_and_only_hints_as_steps(): void
    {
        $this->actingAs($this->editor());
        $weapon = $this->weapon('Belohnungswaffe');
        $vehicle = $this->vehicle('Belohnungsauto');
        $hint = $this->pin(['kind' => 'hint', 'title' => 'Hinweis']);
        $loot = $this->pin(['title' => 'Loot']);
        $this->postJson('/api/trails', ['name' => 'X', 'steps' => [['title' => 'a', 'pin_id' => $loot['id']]]])
            ->assertJsonValidationErrors(['steps.0.pin_id' => 'Hinweis']);

        $trail = $this->postJson('/api/trails', ['name' => 'Das Erbe', 'weapon_ids' => [$weapon], 'vehicle_ids' => [$vehicle],
            'steps' => [['title' => 'a', 'pin_id' => $hint['id']]]])->assertCreated()->json('item');
        $this->assertSame(['Belohnungswaffe'], array_column($trail['weapons'], 'name'));
        $this->assertSame(['Belohnungsauto'], array_column($trail['vehicles'], 'name'));
        $this->assertSame(['Belohnungswaffe', 'Belohnungsauto'], $this->getJson('/api/trails')->json('items.0.rewards'));
        $this->assertSame([['id' => $trail['id'], 'name' => 'Das Erbe']], $this->getJson("/api/weapons/$weapon")->json('item.puzzles'));
    }

    public function test_media_cleanup_removes_only_old_unused_images(): void
    {
        $this->actingAs($this->editor());
        $old = $this->post('/api/media', ['file' => $this->jpeg()], ['Accept' => 'application/json'])->json('media.id');
        $used = $this->post('/api/media', ['file' => $this->jpeg()], ['Accept' => 'application/json'])->json('media.id');
        $fresh = $this->post('/api/media', ['file' => $this->jpeg()], ['Accept' => 'application/json'])->json('media.id');
        $this->pin(['kind' => 'hint', 'title' => 'H', 'steps' => [['media_id' => $used]]]);
        DB::table('media')->whereIn('id', [$old, $used])->update(['created_at' => now()->subDays(2)]);
        $this->artisan('leonida:media-cleanup')->assertSuccessful();
        $this->assertSame([$used, $fresh], DB::table('media')->whereIn('id', [$old, $used, $fresh])->orderBy('created_at')->pluck('id')->all());
    }

    public function test_hint_keeps_its_kind_and_referenced_place_stays_place(): void
    {
        $this->actingAs($this->editor());
        $place = $this->pin(['kind' => 'poi', 'poi_type' => 'bar', 'title' => 'Diner']);
        $hint = $this->pin(['kind' => 'hint', 'title' => 'Zettel', 'place_pin_id' => $place['id']]);
        foreach (['loot', 'poi'] as $kind) {
            $this->putJson("/api/pins/{$hint['id']}", ['kind' => $kind, 'poi_type' => 'bar', 'title' => 'Zettel', 'x' => 1, 'y' => 1])
                ->assertJsonValidationErrors(['kind' => 'Hinweis']);
        }
        $this->putJson("/api/pins/{$place['id']}", ['kind' => 'loot', 'title' => 'Diner', 'x' => 1, 'y' => 1])
            ->assertJsonValidationErrors(['kind' => 'Hinweise']);
        $this->putJson("/api/pins/{$hint['id']}", ['kind' => 'hint', 'title' => 'Zettel neu', 'x' => 1, 'y' => 1])->assertOk();
        // ohne verweisende Hinweise darf ein Ort wieder Loot werden
        $this->putJson("/api/pins/{$place['id']}", ['kind' => 'loot', 'title' => 'Diner', 'x' => 1, 'y' => 1])->assertOk();
    }

    public function test_ammunation_has_weapon_assortment_other_places_not(): void
    {
        $this->actingAs($this->editor());
        $weapon = $this->weapon('Pistole');
        $shop = $this->pin(['kind' => 'poi', 'poi_type' => 'ammunation', 'title' => 'Ammu-Nation Vice City', 'weapon_ids' => [$weapon]]);
        $bar = $this->pin(['kind' => 'poi', 'poi_type' => 'bar', 'title' => 'Bar', 'weapon_ids' => [$weapon]]);
        $this->assertSame('Pistole', $shop['weapons'][0]['name']);
        $this->assertSame([], $bar['weapons']);
        $loc = $this->getJson("/api/weapons/$weapon")->json('item.locations');
        $this->assertSame(['Ammu-Nation Vice City'], array_column($loc, 'title'));
        $this->assertFalse($loc[0]['via_hint']);

        // zusätzlich über einen Hinweis verknüpft: einmal gelistet, gilt als direkt
        $this->pin(['kind' => 'hint', 'title' => 'Tipp', 'place_pin_id' => $shop['id'], 'weapon_ids' => [$weapon]]);
        $loc = $this->getJson("/api/weapons/$weapon")->json('item.locations');
        $this->assertCount(1, $loc);
        $this->assertFalse($loc[0]['via_hint']);
        $this->assertSame(1, collect($this->getJson('/api/weapons')->json('items'))->firstWhere('id', $weapon)['location_count']);

        // Kategorie gewechselt: Sortiment entfällt
        $this->putJson("/api/pins/{$shop['id']}", ['kind' => 'poi', 'poi_type' => 'bar', 'title' => 'Jetzt Bar', 'x' => 1, 'y' => 1, 'weapon_ids' => [$weapon]])->assertOk();
        $this->assertSame([], $this->getJson("/api/pins/{$shop['id']}")->json('pin.weapons'));
        $this->assertTrue($this->getJson("/api/weapons/$weapon")->json('item.locations.0.via_hint'));
    }

    public function test_vehicle_access_and_security_without(): void
    {
        $this->actingAs($this->admin());
        $item = $this->postJson('/api/vehicles', ['name' => 'Fahrrad', 'access' => 'none', 'security' => 'not_specified'])->assertCreated()->json('item');
        $this->assertSame('none', $item['access']);
        $meta = $this->getJson('/api/meta')->json('enums');
        $this->assertContains(['value' => 'none', 'label' => 'Ohne'], $meta['access']);
        $this->assertContains(['value' => 'not_specified', 'label' => 'Ohne'], $meta['security']);
    }
}
