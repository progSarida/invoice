<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ShareholderType: string implements HasLabel
{
    case ONE = "unico";
    case PLUS = "plus";

    public function getLabel(): string
    {
        return match($this) {
            self::ONE => 'Socio unico',
            self::PLUS => 'Più soci',
        };
    }

    public static function options(): array
    {
        return [
            self::ONE->value => 'Socio unico',
            self::PLUS->value => 'Più soci',
        ];
    }
}
