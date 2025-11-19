<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum LiquidationType: string implements HasLabel
{
    case YES = "yes";
    case NO = "no";

    public function getLabel(): string
    {
        return match($this) {
            self::YES => 'In liquidazione',
            self::NO => 'Non in liquidazione',
        };
    }

    public static function options(): array
    {
        return [
            self::YES->value => 'In liquidazione',
            self::NO->value => 'Non in liquidazione',
        ];
    }
}
