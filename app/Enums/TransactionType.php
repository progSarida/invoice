<?php

namespace App\Enums;

use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

enum TransactionType: string implements HasLabel, HasDescription
{
    case SC = "sc";
    case PR = "pr";
    case AB = "ab";
    case AC = "ac";
    case AD = "ad";
    case TE = "te";
    case VC = "vc";
    case VB = "vb";
    case PS = "ps";

    public function getLabel(): ?string
    {
        return match($this) {
            self::SC => "Sconto",
            self::PR => "Premio",
            self::AB => "Abbuono",
            self::AC => "Spesa Accessoria",
            self::AD => "Reso",
            self::TE => "Tentata vendita",
            self::VC => "Vendita con  conti visione",
            self::VB => "Vendita di beni",
            self::PS => "Prestazione di servizio",
        };
    }

    public function getDescription(): ?string
    {
        return match($this) {
            self::SC => "Sconto",
            self::PR => "Premio",
            self::AB => "Abbuono",
            self::AC => "Spesa Accessoria",
            self::AD => "Reso",
            self::TE => "Tentata vendita",
            self::VC => "Vendita con  conti visione",
            self::VB => "Vendita di beni",
            self::PS => "Prestazione di servizio",
        };
    }

    public function elInvTranslation(): ?string
    {
        return match ($this) {
            self::SC, self::PR, self::AB, self::AD, self::TE => 'SC',
            self::AC, self::VC, self::VB, self::PS => 'MG',
        };
    }
}
