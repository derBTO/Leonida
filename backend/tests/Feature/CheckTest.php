<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class CheckTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        File::ensureDirectoryExists(config('leonida.upload_dir'));
    }

    public function test_healthy_installation_passes(): void
    {
        $this->admin();
        $this->artisan('leonida:check')->assertSuccessful();
    }

    public function test_debug_in_production_and_pending_migrations_fail(): void
    {
        $this->admin();
        app()->detectEnvironment(fn () => 'production');
        config(['app.debug' => true]);
        $this->artisan('leonida:check')->expectsOutputToContain('APP_DEBUG=true')->assertFailed();

        config(['app.debug' => false]);
        DB::table('migrations')->where('migration', 'like', '%add_trails')->delete();
        $this->artisan('leonida:check')->expectsOutputToContain('add_trails')->assertFailed();
    }

    public function test_missing_admin_fails(): void
    {
        $this->editor();
        $this->artisan('leonida:check')->expectsOutputToContain('kein aktiver Administrator')->assertFailed();
    }
}
