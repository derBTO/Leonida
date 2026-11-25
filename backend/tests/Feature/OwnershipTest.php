<?php

namespace Tests\Feature;

use Tests\TestCase;

/** Bearbeiter dürfen nur Eigenes ändern/löschen und den Katalog nicht pflegen; Admin darf alles. */
class OwnershipTest extends TestCase
{
    private function pin(): int
    {
        return $this->postJson('/api/pins', ['title' => 'P', 'x' => 1, 'y' => 1])->assertCreated()->json('pin.id');
    }

    public function test_editor_cannot_touch_pins_of_others(): void
    {
        $admin = $this->admin();
        $alice = $this->editor();
        $bob = $this->editor();
        $this->actingAs($admin);
        $adminPin = $this->pin();
        $this->actingAs($alice);
        $alicePin = $this->pin();

        $this->actingAs($bob);
        foreach ([$adminPin, $alicePin] as $id) {
            $this->putJson("/api/pins/$id", ['title' => 'X', 'x' => 1, 'y' => 1])->assertForbidden()
                ->assertJsonPath('message', 'Du darfst nur Einträge bearbeiten oder löschen, die du selbst angelegt hast.');
            $this->patchJson("/api/pins/$id/position", ['x' => 2, 'y' => 2])->assertForbidden();
            $this->deleteJson("/api/pins/$id")->assertForbidden();
            $this->post("/api/pins/$id/images", ['file' => $this->jpeg()], ['Accept' => 'application/json'])->assertForbidden();
            $this->putJson("/api/pins/$id/images/order", ['ids' => []])->assertForbidden();
        }

        $this->actingAs($alice);
        $this->putJson("/api/pins/$alicePin", ['title' => 'Meins', 'x' => 1, 'y' => 1])->assertOk();
        $image = $this->post("/api/pins/$alicePin/images", ['file' => $this->jpeg()], ['Accept' => 'application/json'])->assertCreated()->json('image.id');
        $this->actingAs($bob);
        $this->patchJson("/api/pin-images/$image", ['caption' => 'x'])->assertForbidden();
        $this->deleteJson("/api/pin-images/$image")->assertForbidden();

        $this->actingAs($admin);
        $this->putJson("/api/pins/$alicePin", ['title' => 'Vom Admin', 'x' => 1, 'y' => 1])->assertOk();
        $this->deleteJson("/api/pin-images/$image")->assertOk();
        $this->deleteJson("/api/pins/$alicePin")->assertOk();
    }

    public function test_pins_without_creator_belong_to_admin(): void
    {
        $this->actingAs($this->admin());
        $id = $this->pin();
        \App\Models\Pin::query()->whereKey($id)->update(['created_by' => null]);
        $this->actingAs($this->editor());
        $this->deleteJson("/api/pins/$id")->assertForbidden();
    }

    public function test_editor_cannot_touch_trails_of_others_but_may_track_progress(): void
    {
        $this->actingAs($this->admin());
        $trail = $this->postJson('/api/trails', ['name' => 'Admin-Spur', 'steps' => [['title' => 'A']]])->assertCreated()->json('item');
        $this->actingAs($this->editor());
        $this->putJson("/api/trails/{$trail['id']}", ['name' => 'X', 'steps' => []])->assertForbidden();
        $this->deleteJson("/api/trails/{$trail['id']}")->assertForbidden();
        $this->putJson("/api/trails/{$trail['id']}/steps/{$trail['steps'][0]['id']}/progress", ['done' => true])->assertOk();
        $own = $this->postJson('/api/trails', ['name' => 'Meine', 'steps' => []])->assertCreated()->json('item.id');
        $this->deleteJson("/api/trails/$own")->assertOk();
    }

    public function test_only_admin_maintains_catalog(): void
    {
        $this->actingAs($this->admin());
        $weapon = $this->postJson('/api/weapons', ['name' => 'Pistole'])->assertCreated()->json('item.id');
        $this->actingAs($this->editor());
        $this->postJson('/api/weapons', ['name' => 'X'])->assertForbidden();
        $this->postJson('/api/vehicles', ['name' => 'X'])->assertForbidden();
        $this->putJson("/api/weapons/$weapon", ['name' => 'Y'])->assertForbidden();
        $this->deleteJson("/api/weapons/$weapon")->assertForbidden();
        $this->postJson('/api/lookups/weapon_type', ['name' => 'Neu'])->assertForbidden();
        // vorhandene Waffen dürfen am eigenen Pin verwendet werden
        $this->postJson('/api/pins', ['title' => 'P', 'x' => 1, 'y' => 1, 'loot_types' => ['weapons'], 'weapon_ids' => [$weapon]])->assertCreated();
    }
}
