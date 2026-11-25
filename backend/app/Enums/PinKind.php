<?php

namespace App\Enums;

/** Art eines Pins: Fundort mit Loot, Ort (POI) oder Hinweis (Zwischenstation einer Spur) */
enum PinKind: string
{
    use EnumHelpers;

    case Loot = 'loot';
    case Poi = 'poi';
    case Hint = 'hint';

    public function label(): string
    {
        return match ($this) {
            self::Loot => 'Loot',
            self::Poi => 'Ort',
            self::Hint => 'Hinweis',
        };
    }
}
