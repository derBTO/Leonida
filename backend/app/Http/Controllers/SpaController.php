<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

/** Liefert die gebaute Vue-App für alle Seiten-URLs (z. B. /pin/12, /waffen). */
class SpaController extends Controller
{
    public function __invoke(): Response
    {
        $index = public_path('spa/index.html');
        if (! is_file($index)) {
            return response(
                '<!doctype html><meta charset="utf-8"><title>Leonida</title><p>Frontend noch nicht gebaut: '
                .'<code>cd frontend && npm run build</code></p>',
                503
            );
        }

        return response(file_get_contents($index), 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            'Cache-Control' => 'no-cache',
        ]);
    }
}
