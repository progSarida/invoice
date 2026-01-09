<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

enum BailType: string implements HasLabel
{
    case INSURANCE = "insurance";
    case BAIL = "bail";

    public function getLabel(): string
    {
        return match($this) {
            self::INSURANCE => 'Assicurazione',
            self::BAIL => 'Cauzione',
        };
    }
}
