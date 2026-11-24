<?php

namespace Tests\Feature;

use App\Models\LookupValue;
use App\Models\Weapon;
use Tests\TestCase;

class LookupTest extends TestCase
{
    public function test_duplicates_are_prevented_case_insensitively(): void
    {
        $this->actingAs($this->editor());
        $a = $this->postJson('/api/lookups/weapon_brand', ['name' => 'Vom Feuer'])->assertCreated()->json('item');
        $b = $this->postJson('/api/lookups/weapon_brand', ['name' => '  vom   FEUER '])->assertOk()->json();
        $this->assertSame($a['id'], $b['item']['id']);
        $this->assertFalse($b['created']);
        $this->assertSame(1, LookupValue::query()->count());
        $this->postJson('/api/lookups/caliber', ['name' => 'Vom Feuer'])->assertCreated();
    }

    public function test_unknown_category_is_404(): void
    {
        $this->getJson('/api/lookups/colors')->assertStatus(404);
    }

    public function test_admin_can_merge_and_references_move(): void
    {
        $this->actingAs($this->admin());
        $keep = $this->postJson('/api/lookups/caliber', ['name' => '.38 Special'])->json('item.id');
        $dupe = $this->postJson('/api/lookups/caliber', ['name' => '38 Spezial'])->json('item.id');
        $weapon = $this->postJson('/api/weapons', ['name' => 'Revolver', 'caliber_id' => $dupe])->json('item.id');
        $this->deleteJson("/api/lookup-values/$dupe")->assertStatus(409);
        $this->postJson("/api/lookup-values/$dupe/merge", ['target_id' => $keep])->assertOk()->assertJsonPath('moved', 1);
        $this->assertSame($keep, Weapon::query()->find($weapon)->caliber_id);
        $this->assertNull(LookupValue::query()->find($dupe));
    }

    public function test_merge_across_categories_is_rejected(): void
    {
        $this->actingAs($this->admin());
        $a = $this->postJson('/api/lookups/caliber', ['name' => '9mm'])->json('item.id');
        $b = $this->postJson('/api/lookups/weapon_brand', ['name' => '9mm'])->json('item.id');
        $this->postJson("/api/lookup-values/$a/merge", ['target_id' => $b])->assertStatus(422);
    }

    public function test_rename_cannot_create_duplicate(): void
    {
        $this->actingAs($this->admin());
        $this->postJson('/api/lookups/vehicle_class', ['name' => 'Sport']);
        $b = $this->postJson('/api/lookups/vehicle_class', ['name' => 'Muscle'])->json('item.id');
        $this->patchJson("/api/lookup-values/$b", ['name' => 'SPORT'])->assertStatus(422);
        $this->patchJson("/api/lookup-values/$b", ['name' => 'Muscle Cars'])->assertOk()->assertJsonPath('item.name', 'Muscle Cars');
    }
}
