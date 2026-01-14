<?php

namespace App\Enums;

use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

enum InvoiceReference: string implements HasLabel, HasDescription
{
    case INVOICE = 'invoice';
    case COLLECTION = 'collection';
    case TICKET = 'ticket';
    case NUMBER = 'number';
    case LEASE = 'lease';

    public function getLabel(): string
    {
        return match($this) {
            self::INVOICE => 'Periodo fattura',
            self::COLLECTION => 'Periodo riscossione',
            self::TICKET => 'Periodo gestione verbali',
            self::NUMBER => 'Numero verbali gestiti',
            self::LEASE => 'Periodo locazione',
        };
    }

    public function getDescription(): ?string
    {
        return match($this) {
            self::INVOICE => 'Corrispettivo per ',
            self::COLLECTION => 'Corrispettivo per ',
            self::TICKET => 'Corrispettivo per ',
            self::NUMBER => 'Corrispettivo per ',
            self::LEASE => 'Corrispettivo per ',
        };
    }
}
