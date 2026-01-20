<?php

namespace App\Enums;
use Filament\Support\Contracts\HasLabel;

enum SdiRequestType: string implements HasLabel
{
    case MASS = 'mass';
    case SINGLE = 'single';

    public function getLabel(): string
    {
        return match($this) {
            self::MASS => 'Massiva',
            self::SINGLE => 'Singola',
        };
    }
}
