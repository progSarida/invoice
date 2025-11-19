<?php

namespace App\Enums;

use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

enum ExpenseType: string implements HasLabel, HasDescription
{
    case TIPO1 = "tipo1";
    case TIPO2 = "tipo2";
    case TIPO3 = "tipo3";

    public function getDescription(): ?string
    {
        return match($this) {
            self::TIPO1 => 'Tipo spesa 1',
            self::TIPO2 => 'Tipo spesa 2',
            self::TIPO3 => 'Tipo spesa 3',
        };
    }

    public function getLabel(): string
    {
        return match($this) {
            self::TIPO1 => 'Tipo 1',
            self::TIPO2 => 'Tipo 2',
            self::TIPO3 => 'Tipo 3',
        };
    }

    public function getCode(): string
    {
        return match($this) {
            self::TIPO1 => '01',
            self::TIPO2 => '02',
            self::TIPO3 => '03',
        };
    }

}
