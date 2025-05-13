<?php

namespace App\Enums;
use Filament\Support\Contracts\HasLabel;

enum InvoiceSection: string implements HasLabel
{
    //

    case INVOICE = 1;
    case CREDIT_NOTE = 2;

    public function getLabel(): string
    {
        return match($this) {
            self::INVOICE => 'Fattura',
            self::CREDIT_NOTE => 'Nota di credito',
        };
    }

}
