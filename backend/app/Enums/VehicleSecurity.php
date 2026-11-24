<?php

namespace App\Enums;

/** Sicherheit eines Fahrzeugs. NULL = unbekannt, NotSpecified = Spielwert „Nicht angegeben“ */
enum VehicleSecurity: string
{
    use EnumHelpers;

    case NotSpecified = 'not_specified';
    case Basic = 'basic';
    case Modern = 'modern';
    case Advanced = 'advanced';

    public function label(): string
    {
        return match ($this) {
            self::NotSpecified => 'Nicht angegeben',
            self::Basic => 'Basis',
            self::Modern => 'Modern',
            self::Advanced => 'Advanced',
        };
    }
}
