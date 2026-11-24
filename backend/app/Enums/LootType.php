<?php

namespace App\Enums;

/** Loottyp eines Pins */
enum LootType: string
{
    use EnumHelpers;

    case Vehicles = 'vehicles';
    case Weapons = 'weapons';
    case Drugs = 'drugs';
    case Jewelry = 'jewelry';
    case Cash = 'cash';

    public function label(): string
    {
        return match ($this) {
            self::Vehicles => 'Fahrzeuge',
            self::Weapons => 'Waffen',
            self::Drugs => 'Drogen',
            self::Jewelry => 'Schmuck',
            self::Cash => 'Geld',
        };
    }
}
