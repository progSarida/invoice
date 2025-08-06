<?php

namespace App\Enums;

use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

enum PostalDocType: string implements HasLabel, HasDescription
{
    case CONT = "cont";
    case DIFF = "diff";
    case SINGLE = "single";
    case MULTI = "multi";

    public function getDescription(): ?string
    {
        return match($this) {
            self::CONT => 'SMA con pagamento contestuale',
            self::DIFF => 'SMA con pagamento differito',
            self::SINGLE => 'Distinta singola',
            self::MULTI => 'Distinta multipla',
        };
    }

    public function getLabel(): string
    {
        return match($this) {
            self::CONT => 'SMA contestuale',
            self::DIFF => 'SMA differita',
            self::SINGLE => 'Distinta singola',
            self::MULTI => 'Distinta multipla',
        };
    }

    public function getCode(): string
    {
        return match($this) {
            self::CONT => 'SMAPCONT',
            self::DIFF => 'SMAPDIFF',
            self::SINGLE => 'DSINGOLA',
            self::MULTI => 'DMULTIPL',
        };
    }
}
