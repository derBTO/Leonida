<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

abstract class Controller
{
    /**
     * Bearbeiten und Löschen: Administratoren dürfen alles, alle anderen nur, was sie selbst angelegt haben.
     * Einträge ohne Ersteller (z. B. importierte Orte) gehören damit nur Administratoren.
     */
    protected function ensureOwner(?int $createdBy): void
    {
        $user = Auth::user();
        if ($user?->isAdmin()) {
            return;
        }
        if ($createdBy === null || $createdBy !== $user?->id) {
            abort(403, 'Du darfst nur Einträge bearbeiten oder löschen, die du selbst angelegt hast.');
        }
    }
}
