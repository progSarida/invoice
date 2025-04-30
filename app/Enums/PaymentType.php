<?php

namespace App\Enums;

enum PaymentType: string
{
    //
    case BANK_TRANSFER = "bank_transfer";
    case CREDIT_CARD = "credit_card";
    case CASH = "cash";

    public function label(): string
    {
        return match($this) {
            self::BANK_TRANSFER => 'Trasferimento bancario',
            self::CREDIT_CARD => 'Carta di credito',
            self::CASH => 'Contanti',
        };
    }
}
