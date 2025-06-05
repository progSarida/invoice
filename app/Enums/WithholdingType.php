<?php

namespace App\Enums;

use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

enum WithholdingType: string implements HasLabel, HasDescription
{
    case PHYSICAL = "physical";
    case LEGAL = "legal";
    case INPS = "inps";
    case ENASARCO = "enasarco";
    case ENPAM = "enpam";
    case OTHER = "other";

    public function getLabel(): ?string
    {
        return match($this) {
            self::PHYSICAL => "Ritenuta d'acconto (persone fisiche)",
            self::LEGAL => "Ritenuta d'acconto (persone giuridiche)",
            self::INPS => "Ritenuta previdenziale (INPS)",
            self::ENASARCO => "Ritenuta previdenziale (ENASARCO)",
            self::ENPAM => "Ritenuta previdenziale (ENPAM)",
            self::OTHER => "Ritenuta previdenziale (altro)"
        };
    }

    public function getDescription(): ?string
    {
        return match($this) {
            self::PHYSICAL => "Ritenuta d'acconto (persone fisiche)",
            self::LEGAL => "Ritenuta d'acconto (persone giuridiche)",
            self::INPS => "Ritenuta previdenziale (INPS)",
            self::ENASARCO => "Ritenuta previdenziale (ENASARCO)",
            self::ENPAM => "Ritenuta previdenziale (ENPAM)",
            self::OTHER => "Ritenuta previdenziale (altro)"
        };
    }

}
