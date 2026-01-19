<?php

namespace App\Enums;

use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

enum ReversalGroupType: string implements HasLabel, HasDescription
{
    case FULL = "full";
    case PARTIAL = "partial";
    case BOTH = "both";

    public function getDescription(): ?string
    {
        return match($this) {
            self::FULL => 'Totale',
            self::PARTIAL => 'Parziale',
            self::BOTH => 'Entrambi',
        };
    }

    public function getLabel(): string
    {
        return match($this) {
            self::FULL=> 'Totale',
            self::PARTIAL => 'Parziale',
            self::BOTH => 'Entrambi',
        };
    }

    public function getInverse(): string
    {
        return match($this) {
            self::FULL=> 'partial',
            self::PARTIAL => 'full',
        };
    }
}
