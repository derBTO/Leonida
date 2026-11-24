<?php

return [
    // Erster Administrator, wird von `php artisan leonida:bootstrap-admin` angelegt
    'admin_username' => env('LEONIDA_ADMIN_USERNAME', 'BTO'),
    'admin_initial_password' => env('LEONIDA_ADMIN_INITIAL_PASSWORD'),

    // Webverzeichnis, aus dem der Webserver ausliefert (Produktion: public_html/...).
    // Leer = backend/public (Entwicklung)
    'public_dir' => env('LEONIDA_PUBLIC_DIR'),

    // Ablage außerhalb der Releases. Leer = storage/app/... (nur für Entwicklung)
    'upload_dir' => env('LEONIDA_UPLOAD_DIR') ?: storage_path('app/uploads'),
    'map_dir' => env('LEONIDA_MAP_DIR') ?: base_path('../data/map'),
    'backup_dir' => env('LEONIDA_BACKUP_DIR') ?: storage_path('app/backups'),

    // Öffentliche URL-Präfixe (per Symlink unter public/ eingehängt)
    'media_url' => '/media',
    'map_url' => '/map',

    'images' => [
        'max_mb' => (int) env('LEONIDA_UPLOAD_MAX_MB', 20),
        'max_edge' => 2560,
        'thumb_edge' => 400,
        'quality' => 82,
        'allowed_mimes' => ['image/jpeg', 'image/png', 'image/webp'],
        'max_pixels' => 60_000_000,
    ],

    'password_min_length' => 10,

    // Anmeldeversuche: pro Benutzername+IP und pro IP
    'login_limits' => [
        'per_user_ip' => [5, 60],     // 5 Versuche pro 60 Sekunden
        'per_ip' => [30, 3600],       // 30 Versuche pro Stunde
    ],
];
