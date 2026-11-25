<?php

namespace Tests\Feature;

use App\Models\LookupValue;
use App\Models\Pin;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Jede schreibende Route verlangt eine Anmeldung, Admin-Routen die Admin-Rolle. */
class AccessControlTest extends TestCase
{
    public static function writeRoutes(): array
    {
        return [
            ['post', '/api/pins'], ['put', '/api/pins/1'], ['patch', '/api/pins/1/position'], ['delete', '/api/pins/1'],
            ['post', '/api/pins/1/images'], ['put', '/api/pins/1/images/order'], ['patch', '/api/pin-images/1'], ['delete', '/api/pin-images/1'],
            ['post', '/api/weapons'], ['put', '/api/weapons/1'], ['delete', '/api/weapons/1'], ['post', '/api/weapons/1/image'],
            ['post', '/api/vehicles'], ['put', '/api/vehicles/1'], ['delete', '/api/vehicles/1'], ['delete', '/api/vehicles/1/image'],
            ['post', '/api/lookups/caliber'], ['patch', '/api/lookup-values/1'], ['post', '/api/lookup-values/1/merge'],
            ['get', '/api/users'], ['post', '/api/users'], ['get', '/api/admin/export'], ['post', '/api/admin/import'], ['get', '/api/audit'],
        ];
    }

    #[DataProvider('writeRoutes')]
    public function test_guest_gets_401(string $method, string $uri): void
    {
        $this->json($method, $uri, [])->assertStatus(401);
    }

    public function test_editor_cannot_use_admin_routes(): void
    {
        $value = LookupValue::query()->create(['category' => 'caliber', 'name' => '9mm', 'name_key' => '9mm']);
        $this->actingAs($this->editor());
        $this->getJson('/api/users')->assertStatus(403);
        $this->getJson('/api/admin/export')->assertStatus(403);
        $this->postJson("/api/lookup-values/{$value->id}/merge", ['target_id' => $value->id])->assertStatus(403);
        $this->patchJson("/api/lookup-values/{$value->id}", ['name' => 'x'])->assertStatus(403);
    }

    public function test_public_can_read(): void
    {
        Pin::query()->create(['uuid' => fake()->uuid(), 'title' => 'Tankstelle', 'x' => 1, 'y' => 2]);
        $this->getJson('/api/pins')->assertOk()->assertJsonPath('pins.0.t', 'Tankstelle');
        $this->getJson('/api/weapons')->assertOk();
        $this->getJson('/api/meta')->assertOk()->assertJsonPath('enums.loot_type.0.label', 'Fahrzeuge');
    }

    public function test_security_headers(): void
    {
        $this->get('/api/meta')->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_spa_routes_serve_frontend_or_hint(): void
    {
        $response = $this->get('/pin/5');
        $this->assertContains($response->status(), [200, 503]);
        $this->getJson('/api/does-not-exist')->assertStatus(404);
    }
}
