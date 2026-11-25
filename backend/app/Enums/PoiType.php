<?php

namespace App\Enums;

/** Kategorie eines Orts (POI). Farben und Symbole: frontend/src/lib/poiTypes.ts */
enum PoiType: string
{
    use EnumHelpers;

    case Poi = 'poi';
    case Photo = 'photo';
    case AmmuNation = 'ammunation';
    case Bar = 'bar';
    case GasStation = 'gas_station';
    case Groceries = 'groceries';
    case Clothing = 'clothing';
    case Tattoo = 'tattoo';
    case Garage = 'garage';
    case Tuning = 'tuning';
    case Barber = 'barber';
    case PayAndSpray = 'pay_and_spray';
    case Workshop = 'workshop';
    case Airport = 'airport';
    case Harbor = 'harbor';
    case Military = 'military';
    case Restaurant = 'restaurant';
    case Apartment = 'apartment';
    case Race = 'race';
    case BusStop = 'bus_stop';
    case TrainStop = 'train_stop';

    public function label(): string
    {
        return match ($this) {
            self::Poi => 'POI Allgemein',
            self::Photo => 'Photo',
            self::AmmuNation => 'Ammu-Nation',
            self::Bar => 'Bar',
            self::GasStation => 'Tankstelle',
            self::Groceries => 'Lebensmittel',
            self::Clothing => 'Kleidung',
            self::Tattoo => 'Tattoo',
            self::Garage => 'Garage',
            self::Tuning => 'Auto-Tuning',
            self::Barber => 'Frisör',
            self::PayAndSpray => 'Pay & Spray',
            self::Workshop => 'Autowerkstatt',
            self::Airport => 'Flughafen',
            self::Harbor => 'Hafen',
            self::Military => 'Militär',
            self::Restaurant => 'Restaurant',
            self::Apartment => 'Wohnung',
            self::Race => 'Rennen',
            self::BusStop => 'Bushaltestelle',
            self::TrainStop => 'Bahnhaltestelle',
        };
    }
}
