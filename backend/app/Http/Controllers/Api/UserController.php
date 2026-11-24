<?php

namespace App\Http\Controllers\Api;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/** Benutzerverwaltung, nur für Administratoren. */
class UserController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['items' => User::query()->orderByRaw('lower(username)')->get()->map->toPublicArray()->all()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'min:2', 'max:50', 'regex:/^[A-Za-z0-9_.-]+$/'],
            'password' => ['required', 'string', 'max:200', Password::min(config('leonida.password_min_length'))],
            'role' => ['required', Rule::enum(Role::class)],
        ], ['username.regex' => 'Erlaubt sind Buchstaben, Ziffern, Punkt, Unterstrich und Bindestrich.']);
        if (User::findByUsername($data['username'])) {
            throw ValidationException::withMessages(['username' => 'Diesen Benutzernamen gibt es schon.']);
        }
        $user = User::query()->create($data + ['must_change_password' => true, 'is_active' => true]);
        AuditLogger::log('create', 'user', $user->id, $user->username);

        return response()->json(['item' => $user->toPublicArray()], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'role' => ['sometimes', Rule::enum(Role::class)],
            'is_active' => ['sometimes', 'boolean'],
            'password' => ['sometimes', 'string', 'max:200', Password::min(config('leonida.password_min_length'))],
        ]);
        DB::transaction(function () use ($user, $data, $request) {
            if (isset($data['role'])) {
                $user->role = Role::from($data['role']);
            }
            if (isset($data['is_active'])) {
                $user->is_active = (bool) $data['is_active'];
            }
            if (isset($data['password'])) {
                $user->password = $data['password'];
                $user->must_change_password = true;
            }
            if ($user->is($request->user()) && (! $user->is_active || $user->role !== Role::Admin)) {
                throw ValidationException::withMessages(['role' => 'Du kannst dir die Admin-Rechte nicht selbst entziehen.']);
            }
            $user->save();
            $this->ensureAdminLeft();
        });
        AuditLogger::log('update', 'user', $user->id, $user->username, array_diff_key($data, ['password' => 1]) + (isset($data['password']) ? ['password' => 'zurückgesetzt'] : []));

        return response()->json(['item' => $user->toPublicArray()]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($user->is($request->user())) {
            throw ValidationException::withMessages(['user' => 'Du kannst dein eigenes Konto nicht löschen.']);
        }
        DB::transaction(function () use ($user) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
            $user->delete();
            $this->ensureAdminLeft();
        });
        AuditLogger::log('delete', 'user', $user->id, $user->username);

        return response()->json(['deleted' => true]);
    }

    private function ensureAdminLeft(): void
    {
        if (! User::query()->where('role', Role::Admin)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['role' => 'Es muss mindestens ein aktiver Administrator bleiben.']);
        }
    }
}
