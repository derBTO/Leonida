<?php

namespace App\Enums;

/** Zugang zum Fahrzeug */
enum VehicleAccess: string
{
    use EnumHelpers;

    case None = 'none';
    case Key = 'key';
    case Keyless = 'keyless';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Ohne',
            self::Key => 'Schlüssel',
            self::Keyless => 'Keyless',
        };
    }
}
