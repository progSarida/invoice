<?php

namespace App\Enums;

use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

enum ReinvoiceType: string implements HasLabel, HasDescription
{
    case NONE = "none";
    case FULL = "full";
    case PARTIAL = "partial";
    case COST = "cost";

    public function getDescription(): ?string
    {
        return match($this) {
            self::NONE => 'Non prevista',
            self::FULL=> 'Partita di giro',
            self::PARTIAL => 'Rifatturare se pagate',
            self::COST => 'A costo',
        };
    }

    public function getLabel(): string
    {
        return match($this) {
            self::NONE => 'Non prevista',
            self::FULL=> 'Partita di giro',
            self::PARTIAL => 'Rifatturare se pagate',
            self::COST => 'A costo',
        };
    }

    public function reinvoice(): bool
    {
        return match($this) {
            self::NONE => false,
            self::FULL=> true,
            self::PARTIAL => false,
            self::COST => false,
        };
    }

    public function showReinvoice(): bool
    {
        return match($this) {
            self::NONE => false,
            self::FULL=> true,
            self::PARTIAL => false,
            self::COST => false,
        };
    }
}
