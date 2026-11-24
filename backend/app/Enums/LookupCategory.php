<?php

namespace App\Enums;

/** Kategorie einer dynamischen Auswahlliste */
enum LookupCategory: string
{
    use EnumHelpers;

    case WeaponType = 'weapon_type';
    case FireMode = 'fire_mode';
    case WeaponBrand = 'weapon_brand';
    case Caliber = 'caliber';
    case VehicleManufacturer = 'vehicle_manufacturer';
    case VehicleClass = 'vehicle_class';
    case VehicleStyle = 'vehicle_style';

    public function label(): string
    {
        return match ($this) {
            self::WeaponType => 'Waffentyp',
            self::FireMode => 'Waffenart',
            self::WeaponBrand => 'Waffenmarke',
            self::Caliber => 'Kaliber',
            self::VehicleManufacturer => 'Fahrzeughersteller',
            self::VehicleClass => 'Fahrzeugklasse',
            self::VehicleStyle => 'Fahrzeugstil',
        };
    }
}
