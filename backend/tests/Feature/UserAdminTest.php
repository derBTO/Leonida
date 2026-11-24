<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class UserAdminTest extends TestCase
{
    public function test_admin_creates_user_who_must_change_password(): void
    {
        $this->actingAs($this->admin());
        $this->postJson('/api/users', ['username' => 'Kumpel', 'password' => 'start-passwort-9', 'role' => 'editor'])
            ->assertCreated()->assertJsonPath('item.must_change_password', true);
        $this->postJson('/api/users', ['username' => 'kumpel', 'password' => 'start-passwort-9', 'role' => 'editor'])
            ->assertJsonValidationErrors('username');
        $this->postJson('/api/users', ['username' => 'böse name', 'password' => 'start-passwort-9', 'role' => 'editor'])
            ->assertJsonValidationErrors('username');
    }

    public function test_last_admin_cannot_be_removed(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $this->patchJson("/api/users/{$admin->id}", ['role' => 'editor'])->assertStatus(422);
        $this->deleteJson("/api/users/{$admin->id}")->assertStatus(422);
        $this->assertTrue(User::query()->find($admin->id)->isAdmin());
    }

    public function test_deactivated_user_is_locked_out(): void
    {
        $editor = $this->editor();
        $this->actingAs($this->admin())->patchJson("/api/users/{$editor->id}", ['is_active' => false])->assertOk();
        $this->actingAs($editor->fresh())->postJson('/api/pins', ['title' => 'X', 'x' => 0, 'y' => 0, 'loot_types' => []])->assertStatus(401);
    }

    public function test_password_reset_forces_change(): void
    {
        $editor = $this->editor();
        $this->actingAs($this->admin())->patchJson("/api/users/{$editor->id}", ['password' => 'zurueckgesetzt-1'])
            ->assertJsonPath('item.must_change_password', true);
    }
}
