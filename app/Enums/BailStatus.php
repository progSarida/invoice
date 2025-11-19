<?php

namespace App\Enums;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

enum BailStatus: string implements HasLabel
{
    case PAYED = "payed";
    case EXPIRING = "expiring";
    case EXPIRED = "expired";
    case RELEASED = "released";

    public function getLabel(): string
    {
        return match($this) {
            self::PAYED => 'Pagato',
            self::EXPIRING => 'In scadenza',
            self::EXPIRED => 'Scaduta',
            self::RELEASED => 'Svincolato',

        };
    }
}
