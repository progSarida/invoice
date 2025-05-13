<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum InvoiceType: string implements HasLabel, HasColor
{
    //

    case INVOICE = "invoice";
    case CREDIT_NOTE = "credit_note";
    case INVOICE_NOTICE = "invoice_notice";

    public function getLabel(): string
    {
        return match($this) {
            self::INVOICE => 'Fattura',
            self::CREDIT_NOTE => 'Nota di credito',
            self::INVOICE_NOTICE => 'Preavviso di fattura',
        };
    }

    public function getColor(): string | array | null
    {
        return match($this) {
            self::INVOICE => 'info',
            self::CREDIT_NOTE => 'danger',
            self::INVOICE_NOTICE => 'warning',
        };
    }

}
