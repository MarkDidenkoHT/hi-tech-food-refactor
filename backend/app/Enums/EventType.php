<?php

namespace App\Enums;

enum EventType: string
{
    case Banquet = 'banquet';
    case Reserve = 'reserve';

    /**
     * The human-readable Russian label for the type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Banquet => 'Банкет',
            self::Reserve => 'Резерв',
        };
    }

    /**
     * The hex color used for the type's dots and chips.
     */
    public function color(): string
    {
        return match ($this) {
            self::Banquet => '#e74c3c',
            self::Reserve => '#27ae60',
        };
    }
}
