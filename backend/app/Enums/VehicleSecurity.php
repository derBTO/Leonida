<?php

namespace App\Enums;

/** Sicherheit eines Fahrzeugs. NULL = unbekannt, NotSpecified = Spielwert „Nicht angegeben“, angezeigt als „Ohne“ */
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
            self::NotSpecified => 'Ohne',
            self::Basic => 'Basis',
            self::Modern => 'Modern',
            self::Advanced => 'Advanced',
        };
    }

    /** Weitere Schreibweisen für den CSV-Import */
    public function aliases(): array
    {
        return $this === self::NotSpecified ? ['nicht angegeben', 'none'] : [];
    }
}
