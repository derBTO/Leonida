<?php

namespace App\Enums;

/** Bestätigungsstatus eines Pins */
enum PinStatus: string
{
    use EnumHelpers;

    case Unconfirmed = 'unconfirmed';
    case Confirmed = 'confirmed';
    case Gone = 'gone';

    public function label(): string
    {
        return match ($this) {
            self::Unconfirmed => 'Unbestätigt',
            self::Confirmed => 'Bestätigt',
            self::Gone => 'Nicht mehr verfügbar',
        };
    }
}
