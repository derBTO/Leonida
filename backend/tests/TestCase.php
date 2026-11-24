<?php

namespace Tests;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\File;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $dir = storage_path('framework/testing/uploads-'.getmypid());
        config(['leonida.upload_dir' => $dir]);
        File::deleteDirectory($dir);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('framework/testing/uploads-'.getmypid()));
        parent::tearDown();
    }

    protected function user(Role $role = Role::Editor, array $attributes = []): User
    {
        static $n = 0;
        $n++;

        return User::query()->create($attributes + [
            'username' => 'user'.$n,
            'password' => 'geheim-passwort-1',
            'role' => $role,
            'is_active' => true,
            'must_change_password' => false,
        ]);
    }

    protected function editor(): User
    {
        return $this->user(Role::Editor);
    }

    protected function admin(): User
    {
        return $this->user(Role::Admin);
    }

    /** Ein echtes kleines JPEG als Upload. */
    protected function jpeg(int $w = 64, int $h = 48, string $name = 'bild.jpg'): \Illuminate\Http\UploadedFile
    {
        $img = imagecreatetruecolor($w, $h);
        imagefilledrectangle($img, 0, 0, $w, $h, imagecolorallocate($img, 200, 40, 120));
        $path = tempnam(sys_get_temp_dir(), 'img');
        imagejpeg($img, $path);

        return new \Illuminate\Http\UploadedFile($path, $name, 'image/jpeg', null, true);
    }
}
