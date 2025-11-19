<?php

namespace App\Enums;

use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

enum ShipmentDocType: string implements HasLabel, HasDescription
{
    case SPEDIZIONE = "spedizione";
    case MESSO = "messo";

    public function getDescription(): ?string
    {
        return match($this) {
            self::SPEDIZIONE => 'Fattura passiva',
            self::MESSO => 'Documento',
        };
    }

    public function getLabel(): string
    {
        return match($this) {
            self::SPEDIZIONE=> 'Fattura passiva',
            self::MESSO => 'Documento',
        };
    }

    public function getCode(): string
    {
        return match($this) {
            self::SPEDIZIONE => 'SPD',
            self::MESSO => 'MSS',
        };
    }

}
