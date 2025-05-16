<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Contracts\HasDescription;

enum PaymentType: string implements HasLabel, HasColor, HasDescription
{
    //
    case BANK_TRANSFER = "bank_transfer";
    case CREDIT_CARD = "credit_card";
    case CASH = "cash";

    public function getDescription(): string
    {
        return match($this) {
            self::BANK_TRANSFER => 'Trasferimento bancario',
            self::CREDIT_CARD => 'Carta di credito',
            self::CASH => 'Contanti',
        };
    }

    public function getLabel(): string
    {
        return match($this) {
            self::BANK_TRANSFER => 'Trasferimento bancario',
            self::CREDIT_CARD => 'Carta di credito',
            self::CASH => 'Contanti',
        };
    }

    public function getColor(): string | array | null
    {
        return match($this) {
            self::BANK_TRANSFER => 'info',
            self::CREDIT_CARD => 'danger',
            self::CASH => 'success',
        };
    }
}
