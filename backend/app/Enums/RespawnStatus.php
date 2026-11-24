<?php

namespace App\Enums;

/** Respawn-Verhalten eines Fundorts */
enum RespawnStatus: string
{
    use EnumHelpers;

    case Unknown = 'unknown';
    case Once = 'once';
    case Recurring = 'recurring';

    public function label(): string
    {
        return match ($this) {
            self::Unknown => 'Unbekannt',
            self::Once => 'Einmalig',
            self::Recurring => 'Wiederkehrend',
        };
    }
}
