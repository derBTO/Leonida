<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CatalogImportController;
use App\Http\Controllers\Api\DataController;
use App\Http\Controllers\Api\LookupController;
use App\Http\Controllers\Api\MetaController;
use App\Http\Controllers\Api\PinController;
use App\Http\Controllers\Api\PinImageController;
use App\Http\Controllers\Api\TrailController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\VehicleController;
use App\Http\Controllers\Api\WeaponController;
use App\Http\Controllers\SpaController;
use Illuminate\Support\Facades\Route;

/*
| Die API liegt im "web"-Middleware-Stack: gleiche Domain, Session-Cookie und CSRF-Schutz
| (Header X-XSRF-TOKEN aus dem Cookie XSRF-TOKEN). Lesen ist öffentlich, Schreiben braucht
| Anmeldung, ein aktives Konto und ein geändertes Initialpasswort.
*/
Route::prefix('api')->group(function () {
    Route::get('meta', [MetaController::class, 'meta']);
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::post('auth/login', [AuthController::class, 'login']);
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::post('auth/password', [AuthController::class, 'changePassword'])->middleware('auth');

    Route::get('pins', [PinController::class, 'index']);
    Route::get('pins/search', [PinController::class, 'search']);
    Route::get('pins/{pin}', [PinController::class, 'show'])->whereNumber('pin');

    foreach (['weapons' => WeaponController::class, 'vehicles' => VehicleController::class] as $path => $controller) {
        Route::get($path, [$controller, 'index']);
        Route::get("$path/{id}", [$controller, 'show'])->whereNumber('id');
    }
    Route::get('lookups/{category}', [LookupController::class, 'index']);
    Route::get('trails', [TrailController::class, 'index']);
    Route::get('trails/{id}', [TrailController::class, 'show'])->whereNumber('id');

    Route::middleware(['auth', 'account.active'])->group(function () {
        Route::get('audit', [MetaController::class, 'audit']);

        Route::post('pins', [PinController::class, 'store']);
        Route::put('pins/{pin}', [PinController::class, 'update'])->whereNumber('pin');
        Route::patch('pins/{pin}/position', [PinController::class, 'move'])->whereNumber('pin');
        Route::delete('pins/{pin}', [PinController::class, 'destroy'])->whereNumber('pin');
        Route::post('pins/{pin}/images', [PinImageController::class, 'store'])->whereNumber('pin');
        Route::put('pins/{pin}/images/order', [PinImageController::class, 'reorder'])->whereNumber('pin');
        Route::patch('pin-images/{image}', [PinImageController::class, 'update'])->whereNumber('image');
        Route::delete('pin-images/{image}', [PinImageController::class, 'destroy'])->whereNumber('image');

        foreach (['weapons' => WeaponController::class, 'vehicles' => VehicleController::class] as $path => $controller) {
            Route::post($path, [$controller, 'store']);
            Route::put("$path/{id}", [$controller, 'update'])->whereNumber('id');
            Route::delete("$path/{id}", [$controller, 'destroy'])->whereNumber('id');
            Route::post("$path/{id}/image", [$controller, 'uploadImage'])->whereNumber('id');
            Route::delete("$path/{id}/image", [$controller, 'deleteImage'])->whereNumber('id');
        }

        Route::post('trails', [TrailController::class, 'store']);
        Route::put('trails/{id}', [TrailController::class, 'update'])->whereNumber('id');
        Route::delete('trails/{id}', [TrailController::class, 'destroy'])->whereNumber('id');
        Route::put('trails/{id}/steps/{step}/progress', [TrailController::class, 'progress'])->whereNumber(['id', 'step']);
        Route::delete('trails/{id}/progress', [TrailController::class, 'resetProgress'])->whereNumber('id');

        Route::post('lookups/{category}', [LookupController::class, 'store']);

        Route::middleware('role:admin')->group(function () {
            Route::patch('lookup-values/{value}', [LookupController::class, 'update'])->whereNumber('value');
            Route::post('lookup-values/{value}/merge', [LookupController::class, 'merge'])->whereNumber('value');
            Route::delete('lookup-values/{value}', [LookupController::class, 'destroy'])->whereNumber('value');

            Route::get('users', [UserController::class, 'index']);
            Route::post('users', [UserController::class, 'store']);
            Route::patch('users/{user}', [UserController::class, 'update'])->whereNumber('user');
            Route::delete('users/{user}', [UserController::class, 'destroy'])->whereNumber('user');

            Route::get('admin/export', [DataController::class, 'export']);
            Route::post('admin/import', [DataController::class, 'import']);
            Route::post('admin/catalog-import/{kind}', [CatalogImportController::class, 'import'])->whereIn('kind', ['weapons', 'vehicles']);
            Route::get('admin/catalog-template/{kind}', [CatalogImportController::class, 'template'])->whereIn('kind', ['weapons', 'vehicles']);
        });
    });

    Route::any('{any}', fn () => response()->json(['message' => 'Unbekannter API-Pfad.'], 404))->where('any', '.*');
});

// Alle übrigen Seiten liefert die Vue-App (z. B. /pin/12, /waffen, /admin).
Route::get('/{any?}', SpaController::class)->where('any', '^(?!api/|docs/|media/|map/|spa/|up$).*$');
