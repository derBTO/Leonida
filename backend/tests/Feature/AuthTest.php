<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    public function test_login_and_me(): void
    {
        $this->user(Role::Editor, ['username' => 'Anna']);
        $this->postJson('/api/auth/login', ['username' => 'anna', 'password' => 'geheim-passwort-1'])
            ->assertOk()->assertJsonPath('user.username', 'Anna');
        $this->getJson('/api/auth/me')->assertJsonPath('user.role', 'editor');
    }

    public function test_wrong_password_is_rejected_without_hint(): void
    {
        $this->user(Role::Editor, ['username' => 'Anna']);
        $this->postJson('/api/auth/login', ['username' => 'Anna', 'password' => 'falsch'])
            ->assertStatus(422)->assertJsonPath('errors.username.0', 'Benutzername oder Passwort ist falsch.');
        $this->postJson('/api/auth/login', ['username' => 'Niemand', 'password' => 'falsch'])
            ->assertJsonPath('errors.username.0', 'Benutzername oder Passwort ist falsch.');
    }

    public function test_brute_force_is_throttled(): void
    {
        $this->user(Role::Editor, ['username' => 'Anna']);
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', ['username' => 'Anna', 'password' => 'falsch'])->assertStatus(422);
        }
        $this->postJson('/api/auth/login', ['username' => 'Anna', 'password' => 'geheim-passwort-1'])->assertStatus(429);
    }

    public function test_inactive_user_cannot_login(): void
    {
        $this->user(Role::Editor, ['username' => 'Anna', 'is_active' => false]);
        $this->postJson('/api/auth/login', ['username' => 'Anna', 'password' => 'geheim-passwort-1'])->assertStatus(422);
    }

    public function test_password_change_required_blocks_writes_until_changed(): void
    {
        $user = $this->user(Role::Editor, ['must_change_password' => true]);
        $this->actingAs($user)->postJson('/api/pins', ['title' => 'X', 'x' => 0, 'y' => 0, 'loot_types' => []])
            ->assertStatus(403)->assertJsonPath('code', 'password_change_required');
        $this->postJson('/api/auth/password', [
            'current_password' => 'geheim-passwort-1', 'password' => 'neues-passwort-2', 'password_confirmation' => 'neues-passwort-2',
        ])->assertOk()->assertJsonPath('user.must_change_password', false);
        $this->assertTrue(Hash::check('neues-passwort-2', $user->fresh()->password));
        $this->postJson('/api/pins', ['title' => 'X', 'x' => 0, 'y' => 0, 'loot_types' => []])->assertCreated();
    }

    public function test_short_password_is_rejected(): void
    {
        $this->actingAs($this->editor())->postJson('/api/auth/password', [
            'current_password' => 'geheim-passwort-1', 'password' => 'kurz', 'password_confirmation' => 'kurz',
        ])->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_bootstrap_admin_from_env(): void
    {
        config(['leonida.admin_username' => 'BTO', 'leonida.admin_initial_password' => 'start-passwort-123']);
        $this->artisan('leonida:bootstrap-admin')->assertSuccessful();
        $admin = User::findByUsername('bto');
        $this->assertSame(Role::Admin, $admin->role);
        $this->assertTrue($admin->must_change_password);
        $this->assertNotSame('start-passwort-123', $admin->getRawOriginal('password'));
        $this->artisan('leonida:bootstrap-admin')->assertSuccessful();
        $this->assertSame(1, User::query()->count());
    }

    public function test_passwords_are_hashed(): void
    {
        $user = $this->editor();
        $this->assertStringStartsWith('$', $user->getRawOriginal('password'));
        $this->assertFalse(str_contains($user->getRawOriginal('password'), 'geheim'));
    }
}
