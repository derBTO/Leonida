<?php

namespace Tests\Feature;

use App\Models\Trail;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class TrailTest extends TestCase
{
    private function pin(string $title, array $o = []): int
    {
        return $this->postJson('/api/pins', $o + ['title' => $title, 'x' => 1, 'y' => 2])->assertCreated()->json('pin.id');
    }

    /** A → B (Hinweis) und C parallel → D (Loot) */
    private function chain(): array
    {
        $a = $this->pin('Treffpunkt', ['kind' => 'poi', 'poi_type' => 'gas_station']);
        $b = $this->pin('Zeitung', ['kind' => 'hint']);
        $c = $this->pin('Tor', ['kind' => 'hint', 'codes' => [['code' => '4711']]]);
        $d = $this->pin('Versteck', ['loot_types' => ['cash'], 'cash_usd' => 900]);
        $trail = $this->postJson('/api/trails', ['name' => 'Das Erbe', 'steps' => [
            ['title' => 'Moses treffen', 'pin_id' => $a],
            ['title' => 'Zeitung lesen', 'pin_id' => $b],
            ['title' => 'Code am Tor', 'pin_id' => $c, 'parallel' => true],
            ['title' => 'Auf den Anruf warten'],
            ['title' => 'Loot holen', 'pin_id' => $d],
        ]])->assertCreated()->json('item');

        return [$trail, $a, $b, $c, $d];
    }

    public function test_hint_pin_has_codes_but_no_loot(): void
    {
        $this->actingAs($this->editor());
        $pin = $this->postJson('/api/pins', ['kind' => 'hint', 'title' => 'Zettel', 'x' => 0, 'y' => 0, 'poi_type' => 'bar',
            'loot_types' => ['cash'], 'cash_usd' => 5, 'codes' => [['label' => 'Tor', 'code' => '4711']], 'status' => 'gone'])
            ->assertCreated()->json('pin');
        $this->assertSame('hint', $pin['kind']);
        $this->assertNull($pin['poi_type']);
        $this->assertSame([], $pin['loot_types']);
        $this->assertNull($pin['cash_usd']);
        $this->assertSame('4711', $pin['codes'][0]['code']);
        $this->assertSame('confirmed', $pin['status']);
        $this->assertSame([$pin['id']], $this->getJson('/api/pins/search?kind=hint')->json('ids'));
    }

    public function test_steps_are_numbered_with_parallel_steps(): void
    {
        $this->actingAs($this->editor());
        [$trail, , $b, $c, $d] = $this->chain();
        $this->assertSame([1, 2, 2, 3, 4], array_column($trail['steps'], 'position'));
        $this->assertSame(4, $trail['step_count']);
        $this->assertNull($trail['steps'][3]['pin']);
        $this->assertSame('hint', $trail['steps'][1]['pin']['kind']);

        $pin = $this->getJson("/api/pins/$c")->json('pin');
        $this->assertSame([['trail_id' => $trail['id'], 'trail_name' => 'Das Erbe', 'step_id' => $trail['steps'][2]['id'], 'position' => 2, 'step_count' => 4]], $pin['trails']);
        $this->assertSame(4, $this->getJson("/api/pins/$d")->json('pin.trails.0.position'));
    }

    public function test_progress_is_per_person_and_survives_edits(): void
    {
        $alice = $this->editor();
        $bob = $this->editor();
        $this->actingAs($alice);
        [$trail] = $this->chain();
        $id = $trail['id'];
        $first = $trail['steps'][0]['id'];
        $this->putJson("/api/trails/$id/steps/$first/progress", ['done' => true])->assertOk()->assertJsonPath('item.steps.0.done', true);
        $this->putJson("/api/trails/$id/steps/$first/progress", ['done' => true])->assertOk();
        $this->assertSame(1, $this->getJson('/api/trails')->json('items.0.steps_done'));

        $this->actingAs($bob);
        $this->assertFalse($this->getJson("/api/trails/$id")->json('item.steps.0.done'));
        $this->assertSame(0, $this->getJson('/api/trails')->json('items.0.steps_done'));

        // Bearbeiten: erster Schritt bleibt (gleiche id), Rest wird ersetzt
        $this->actingAs($alice);
        $edited = $this->putJson("/api/trails/$id", ['name' => 'Das Erbe', 'steps' => [
            ['id' => $first, 'title' => 'Moses treffen (nachts)'],
            ['title' => 'Neu'],
        ]])->assertOk()->json('item');
        $this->assertTrue($edited['steps'][0]['done']);
        $this->assertSame(2, $edited['steps_total']);

        $this->deleteJson("/api/trails/$id/progress")->assertOk()->assertJsonPath('item.steps_done', 0);
        $this->putJson("/api/trails/$id/steps/999999/progress", ['done' => true])->assertNotFound();
    }

    public function test_deleting_a_pin_keeps_the_step(): void
    {
        $this->actingAs($this->editor());
        [$trail, , $b] = $this->chain();
        $this->deleteJson("/api/pins/$b")->assertOk();
        $step = $this->getJson("/api/trails/{$trail['id']}")->json('item.steps.1');
        $this->assertSame('Zeitung lesen', $step['title']);
        $this->assertNull($step['pin']);
    }

    public function test_guests_can_read_but_not_write(): void
    {
        $this->actingAs($this->editor());
        [$trail] = $this->chain();
        auth()->logout();
        $this->getJson('/api/trails')->assertOk()->assertJsonCount(1, 'items');
        $this->getJson("/api/trails/{$trail['id']}")->assertOk()->assertJsonPath('item.steps.0.done', false);
        $this->postJson('/api/trails', ['name' => 'X', 'steps' => []])->assertUnauthorized();
        $this->putJson("/api/trails/{$trail['id']}/steps/{$trail['steps'][0]['id']}/progress", ['done' => true])->assertUnauthorized();
    }

    public function test_validation(): void
    {
        $this->actingAs($this->editor());
        $this->postJson('/api/trails', ['name' => ' ', 'steps' => []])->assertJsonValidationErrors('name');
        $this->postJson('/api/trails', ['name' => 'X', 'steps' => [['title' => '']]])->assertJsonValidationErrors('steps.0.title');
        $this->postJson('/api/trails', ['name' => 'X', 'steps' => [['title' => 'a', 'pin_id' => 999999]]])->assertJsonValidationErrors('steps.0.pin_id');
        // Erster Schritt kann nicht parallel sein
        $this->postJson('/api/trails', ['name' => 'X', 'steps' => [['title' => 'a', 'parallel' => true]]])->assertCreated()->assertJsonPath('item.steps.0.position', 1);
    }

    public function test_author_filter(): void
    {
        $alice = $this->editor();
        $bob = $this->editor();
        $this->actingAs($alice);
        $mine = $this->pin('Von Alice');
        \App\Models\Weapon::query()->create(['uuid' => (string) \Illuminate\Support\Str::uuid(), 'name' => 'Alices Waffe', 'created_by' => $alice->id]);
        $this->postJson('/api/trails', ['name' => 'Alices Spur', 'steps' => []])->assertCreated();
        $this->actingAs($bob);
        $this->pin('Von Bob');
        \App\Models\Weapon::query()->create(['uuid' => (string) \Illuminate\Support\Str::uuid(), 'name' => 'Bobs Waffe', 'created_by' => $bob->id]);

        $this->assertSame([$mine], $this->getJson("/api/pins/search?author={$alice->id}")->json('ids'));
        $this->assertSame(['Alices Waffe'], array_column($this->getJson("/api/weapons?author={$alice->id}")->json('items'), 'name'));
        $this->assertSame([], $this->getJson("/api/trails?author={$bob->id}")->json('items'));
        $index = collect($this->getJson('/api/pins')->json('pins'))->pluck('u', 't')->all();
        $this->assertSame(['Von Alice' => $alice->id, 'Von Bob' => $bob->id], $index);
    }

    public function test_export_import_keeps_trails(): void
    {
        $this->actingAs($this->admin());
        $this->chain();
        $response = $this->get('/api/admin/export')->assertOk();
        $zip = tempnam(sys_get_temp_dir(), 'exp').'.zip';
        copy($response->baseResponse->getFile()->getPathname(), $zip);
        $this->post('/api/admin/import', ['file' => new UploadedFile($zip, 'e.zip', 'application/zip', null, true), 'mode' => 'replace'], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('report.trails.created', 1);
        $trail = Trail::query()->sole()->toApi();
        $this->assertSame([1, 2, 2, 3, 4], array_column($trail['steps'], 'position'));
        $this->assertSame('Tor', $trail['steps'][2]['pin']['title']);
        $this->assertNull($trail['steps'][3]['pin']);
    }
}
