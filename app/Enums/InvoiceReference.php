<?php

namespace App\Enums;

use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

enum InvoiceReference: string implements HasLabel, HasDescription
{
    case INVOICE = 'invoice';
    case COLLECTION = 'collection';
    case TICKET = 'ticket';

    public function getLabel(): string
    {
        return match($this) {
            self::INVOICE => 'Periodo fattura',
            self::COLLECTION => 'Periodo riscossione',
            self::TICKET => 'Verbali',
        };
    }

    public function getDescription(): ?string
    {
        return match($this) {
            self::INVOICE => '',
            self::COLLECTION => '',
            self::TICKET => '',
        };
    }
}
