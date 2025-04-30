<?php

namespace App\Enums;

enum InvoiceType: string
{
    //

    case INVOICE = "invoice";
    case CREDIT_NOTE = "credit_note";
    case INVOICE_NOTICE = "invoice_notice";

    public function label(): string
    {
        return match($this) {
            self::INVOICE => 'Fattura',
            self::CREDIT_NOTE => 'Nota di credito',
            self::INVOICE_NOTICE => 'Preavviso di fattura',
        };
    }

}
