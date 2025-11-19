<?php

namespace App\Enums;

use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

enum WithholdingType: string implements HasLabel, HasDescription
{
    case RT01 = "rt01";
    case RT02 = "rt02";
    case RT03 = "rt03";
    case RT04 = "rt04";
    case RT05 = "rt05";
    case RT06 = "rt06";

    public function getDefinition(): ?string
    {
        return match($this) {
            self::RT01 => "Ritenuta d'acconto (persone fisiche)",
            self::RT02 => "Ritenuta d'acconto (persone giuridiche)",
            self::RT03 => "Ritenuta previdenziale (INPS)",
            self::RT04 => "Ritenuta previdenziale (ENASARCO)",
            self::RT05 => "Ritenuta previdenziale (ENPAM)",
            self::RT06 => "Ritenuta previdenziale (altro)"
        };
    }

    public function getDescription(): ?string
    {
        return match($this) {
            self::RT01 => "Ritenuta persone fisiche",
            self::RT02 => "Ritenuta persone giuridiche",
            self::RT03 => "Ritenuta INPS",
            self::RT04 => "Ritenuta ENASARCO",
            self::RT05 => "Ritenuta ENPAM",
            self::RT06 => "Altra ritenuta previdenziale"
        };
    }

    public function getCode(): ?string
    {
        return match($this) {
            self::RT01 => "RT01",
            self::RT02 => "RT02",
            self::RT03 => "RT03",
            self::RT04 => "RT04",
            self::RT05 => "RT05",
            self::RT06 => "RT06"
        };
    }

    public function getLabel(): ?string
    {
        return $this->getCode() . " - " . $this->getDefinition();
    }

    public function getPrint(): ?string
    {
        return $this->getCode() . " (" . $this->getDescription() . ")";
    }

}
