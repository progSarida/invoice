<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum InvoicingCicle: string implements HasLabel
{
    case MONTHLY = 'monthly';
    case BIMONTHLY = 'bimonthly';
    case QUARTERLY = 'quarterly';
    case SEMIANNUALLY = 'semiannually';
    case ANNUALLY = 'annually';

    public function getLabel(): string
    {
        return match($this) {
            self::MONTHLY => 'Mensile',
            self::BIMONTHLY => 'Bimestrale',
            self::QUARTERLY => 'Trimestrale',
            self::SEMIANNUALLY => 'Semestrale',
            self::ANNUALLY => 'Annuale',
        };
    }
}
