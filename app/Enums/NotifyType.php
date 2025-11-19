<?php

namespace App\Enums;

use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

enum NotifyType: string implements HasLabel, HasDescription
{
    case SPEDIZIONE = "spedizione";
    case MESSO = "messo";

    public function getDescription(): ?string
    {
        return match($this) {
            self::SPEDIZIONE => 'Spedizione',
            self::MESSO => 'Messo',
        };
    }

    public function getLabel(): string
    {
        return match($this) {
            self::SPEDIZIONE=> 'Spedizione',
            self::MESSO => 'Messo',
        };
    }

    public function getCode(): string
    {
        return match($this) {
            self::SPEDIZIONE => 'Spedizione',
            self::MESSO => 'Messo',
        };
    }

    public function isShipment()
    {
        return $this === self::SPEDIZIONE;
    }
}
