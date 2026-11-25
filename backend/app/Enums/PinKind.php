<?php

namespace App\Enums;

/** Art eines Pins: Fundort mit Loot oder Ort (POI) */
enum PinKind: string
{
    use EnumHelpers;

    case Loot = 'loot';
    case Poi = 'poi';

    public function label(): string
    {
        return match ($this) {
            self::Loot => 'Loot',
            self::Poi => 'Ort',
        };
    }
}
