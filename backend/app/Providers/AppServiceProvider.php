<?php

namespace App\Providers;

use App\Models\User;
use Dedoc\Scramble\Scramble;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Produktion: Webverzeichnis liegt getrennt vom Code (siehe docs/INSTALLATION.md)
        if ($dir = config('leonida.public_dir')) {
            $this->app->usePublicPath($dir);
        }
    }

    public function boot(): void
    {
        // API-Dokumentation (/docs/api) nur für angemeldete Benutzer
        Gate::define('viewApiDocs', fn (?User $user) => $user !== null);

        Scramble::configure()->routes(fn ($route) => str_starts_with($route->uri, 'api/') && $route->uri !== 'api/{any}');
    }
}
