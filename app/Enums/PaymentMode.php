<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Contracts\HasDescription;

enum PaymentMode: string implements HasLabel, HasColor, HasDescription
{
    case TP01 = 'tp01';
    case TP02 = 'tp02';
    case TP03 = 'tp03';

    public function getDescription(): string
    {
        return match($this) {
            self::TP01 => 'Pagamento a rate',
            self::TP02 => 'Pagamento in unica soluzione',
            self::TP03 => 'Anticipo',
        };
    }

    public function getLabel(): string
    {
        return match($this) {
            self::TP01 => 'A rate',
            self::TP02 => 'Unica soluzione',
            self::TP03 => 'Anticipo'
        };
    }

    public function getColor(): string | array | null
    {
        return match($this) {
            self::TP01 => '',
            self::TP02 => '',
            self::TP03 => ''
        };
    }

    public function getCode(): string | array | null
    {
        return match($this) {
            self::TP01 => 'TP01',
            self::TP02 => 'TP02',
            self::TP03 => 'TP03'
        };
    }

    public function getOrder(): string | array | null
    {
        return match($this) {
            self::TP02 => 2,
            self::TP01 => 1,
            self::TP03 => 3
        };
    }
}
