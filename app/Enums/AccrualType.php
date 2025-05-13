<?php

namespace App\Enums;


use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

enum AccrualType: string implements HasLabel
{
    //
    case ORDINARY = "ordinary";
    case COERCIVE = "coercive";

    public function getLabel(): string
    {
        return match($this) {
            self::ORDINARY => 'Competenza ordinaria',
            self::COERCIVE => 'Competenza coattiva',
        };
    }
}
