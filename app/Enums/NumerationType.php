<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum NumerationType: string implements HasLabel
{
    case ANNUAL = "annual";
    case CONTINUE = "continue";

    public function getLabel(): string
    {
        return match($this) {
            self::ANNUAL => 'Annuale',
            self::CONTINUE => 'Continua'
        };
    }
}
