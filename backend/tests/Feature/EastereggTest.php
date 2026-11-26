<?php

namespace Tests\Feature;

use Tests\TestCase;

class EastereggTest extends TestCase
{
    public function test_any_pin_kind_can_be_an_easteregg(): void
    {
        $this->actingAs($this->editor());
        $loot = $this->postJson('/api/pins', ['title' => 'Geisterauto', 'x' => 1, 'y' => 1, 'loot_types' => ['vehicles'], 'is_easteregg' => true])
            ->assertCreated()->assertJsonPath('pin.is_easteregg', true)->json('pin.id');
        $poi = $this->postJson('/api/pins', ['kind' => 'poi', 'poi_type' => 'poi', 'title' => 'UFO', 'x' => 2, 'y' => 2, 'is_easteregg' => true])
            ->assertCreated()->assertJsonPath('pin.is_easteregg', true)->json('pin.id');
        $plain = $this->postJson('/api/pins', ['title' => 'Normal', 'x' => 3, 'y' => 3])->assertCreated()->assertJsonPath('pin.is_easteregg', false)->json('pin.id');

        $index = collect($this->getJson('/api/pins')->json('pins'))->pluck('e', 'id')->all();
        $this->assertSame([$loot => true, $poi => true, $plain => false], $index);
        $this->assertEqualsCanonicalizing([$loot, $poi], $this->getJson('/api/pins/search?egg=1')->json('ids'));
        $this->assertSame([$plain], $this->getJson('/api/pins/search?egg=0')->json('ids'));

        // Kennzeichen lässt sich wieder entfernen
        $this->putJson("/api/pins/$loot", ['title' => 'Geisterauto', 'x' => 1, 'y' => 1])->assertOk()->assertJsonPath('pin.is_easteregg', false);
    }
}
