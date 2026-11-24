<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Legt den ersten Administrator aus LEONIDA_ADMIN_USERNAME / LEONIDA_ADMIN_INITIAL_PASSWORD an.
 * Läuft bei jedem Deployment; existiert der Benutzer schon, passiert nichts.
 */
class BootstrapAdmin extends Command
{
    protected $signature = 'leonida:bootstrap-admin';

    protected $description = 'Ersten Administrator aus der .env anlegen (nur wenn er noch nicht existiert)';

    public function handle(): int
    {
        $username = trim((string) config('leonida.admin_username'));
        if ($username === '') {
            $this->error('LEONIDA_ADMIN_USERNAME ist leer.');

            return self::FAILURE;
        }
        if (User::findByUsername($username)) {
            $this->info("Administrator $username existiert bereits – nichts zu tun.");

            return self::SUCCESS;
        }
        $password = (string) config('leonida.admin_initial_password');
        if (mb_strlen($password) < config('leonida.password_min_length')) {
            $this->error('LEONIDA_ADMIN_INITIAL_PASSWORD fehlt oder ist kürzer als '.config('leonida.password_min_length').' Zeichen.');

            return self::FAILURE;
        }
        User::query()->create([
            'username' => $username,
            'password' => $password,
            'role' => Role::Admin,
            'is_active' => true,
            'must_change_password' => true,
        ]);
        $this->info("Administrator $username angelegt. Das Passwort muss beim ersten Anmelden geändert werden.");
        $this->warn('LEONIDA_ADMIN_INITIAL_PASSWORD kann jetzt aus der .env entfernt werden.');

        return self::SUCCESS;
    }
}
