<?php

namespace App\Enums;

/** Benutzerrolle */
enum Role: string
{
    use EnumHelpers;

    case Admin = 'admin';
    case Editor = 'editor';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Editor => 'Editor',
        };
    }
}
