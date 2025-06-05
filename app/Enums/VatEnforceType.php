<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum VatEnforceType: string implements HasLabel
{
    case NOW = "now";
    case POSTPONED = "postponed";
    case SPLIT = "split";

    public function getLabel(): ?string
    {
        return match($this) {
            self::NOW => "IVA ad esigibilità immediata",
            self::POSTPONED => "IVA ad esigibilità differita",
            self::SPLIT => "Scissione dei pagamenti"
        };
    }

}
