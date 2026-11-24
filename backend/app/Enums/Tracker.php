<?php

namespace App\Enums;

/** Tracker eines Fahrzeugs. NULL = unbekannt, None = „Nicht vorhanden“ */
enum Tracker: string
{
    use EnumHelpers;

    case None = 'none';
    case Standard = 'standard';
    case Advanced = 'advanced';
    case Military = 'military';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Nicht vorhanden',
            self::Standard => 'Standard',
            self::Advanced => 'Advanced',
            self::Military => 'Militär',
        };
    }
}
