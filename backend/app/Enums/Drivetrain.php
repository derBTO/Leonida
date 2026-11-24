<?php

namespace App\Enums;

/** Antriebsart */
enum Drivetrain: string
{
    use EnumHelpers;

    case FWD = 'FWD';
    case RWD = 'RWD';
    case AWD = 'AWD';

    public function label(): string
    {
        return match ($this) {
            self::FWD => 'FWD',
            self::RWD => 'RWD',
            self::AWD => 'AWD',
        };
    }
}
