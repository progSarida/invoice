<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Contracts\HasDescription;

enum PaymentType: string implements HasLabel, HasColor, HasDescription
{
    // case BANK_TRANSFER = "bank_transfer"; => mp05
    // case CREDIT_CARD = "credit_card"; => mp08
    // case CASH = "cash"; => mp01

    case MP01 = 'mp01';
    case MP02 = 'mp02';
    case MP03 = 'mp03';
    case MP04 = 'mp04';
    case MP05 = 'mp05';
    case MP06 = 'mp06';
    case MP07 = 'mp07';
    case MP08 = 'mp08';
    case MP09 = 'mp09';
    case MP10 = 'mp10';
    case MP11 = 'mp11';
    case MP12 = 'mp12';
    case MP13 = 'mp13';
    case MP14 = 'mp14';
    case MP15 = 'mp15';
    case MP16 = 'mp16';
    case MP17 = 'mp17';
    case MP18 = 'mp18';
    case MP19 = 'mp19';
    case MP20 = 'mp20';
    case MP21 = 'mp21';
    case MP22 = 'mp22';
    case MP23 = 'mp23';

    public function getDescription(): string
    {
        return match($this) {
            // self::BANK_TRANSFER => 'Bonifico bancario',
            // self::CREDIT_CARD => 'Carta di credito',
            // self::CASH => 'Contanti',

            self::MP01 => 'Contanti',
            self::MP02 => 'Assegno',
            self::MP03 => 'Assegno circolare',
            self::MP04 => 'Contanti presso tesoreria',
            self::MP05 => 'Bonifico',
            self::MP06 => 'Vaglia cambiario',
            self::MP07 => 'Bollettino bancario',
            self::MP08 => 'Carta di pagamento',
            self::MP09 => 'RID',
            self::MP10 => 'RID utenze',
            self::MP11 => 'RID veloce',
            self::MP12 => 'RiBA',
            self::MP13 => 'MAV',
            self::MP14 => 'Quietanza erario stato',
            self::MP15 => 'Giroconto su conti di contabilità speciale',
            self::MP16 => 'Domiciliazione bancaria',
            self::MP17 => 'Domiciliazione postale',
            self::MP18 => 'Bollettino di c/c postale',
            self::MP19 => 'SEPA Direct Debit',
            self::MP20 => 'SEPA Direct Debit CORE',
            self::MP21 => 'SEPA Direct Debit B2B',
            self::MP22 => 'Trattenuta su somme già riscosse',
            self::MP23 => 'PagoPA'
        };
    }

    public function getLabel(): string
    {
        return match($this) {
            // self::BANK_TRANSFER => 'Bonifico bancario',
            // self::CREDIT_CARD => 'Carta di credito',
            // self::CASH => 'Contanti',

            self::MP01 => 'Contanti',
            self::MP02 => 'Assegno',
            self::MP03 => 'Assegno circolare',
            self::MP04 => 'Contanti presso tesoreria',
            self::MP05 => 'Bonifico',
            self::MP06 => 'Vaglia cambiario',
            self::MP07 => 'Bollettino bancario',
            self::MP08 => 'Carta di pagamento',
            self::MP09 => 'RID',
            self::MP10 => 'RID utenze',
            self::MP11 => 'RID veloce',
            self::MP12 => 'RiBA',
            self::MP13 => 'MAV',
            self::MP14 => 'Quietanza erario stato',
            self::MP15 => 'Giroconto su conti di contabilità speciale',
            self::MP16 => 'Domiciliazione bancaria',
            self::MP17 => 'Domiciliazione postale',
            self::MP18 => 'Bollettino di c/c postale',
            self::MP19 => 'SEPA Direct Debit',
            self::MP20 => 'SEPA Direct Debit CORE',
            self::MP21 => 'SEPA Direct Debit B2B',
            self::MP22 => 'Trattenuta su somme già riscosse',
            self::MP23 => 'PagoPA'
        };
    }

    public function getColor(): string | array | null
    {
        return match($this) {
            // self::BANK_TRANSFER => 'info',
            // self::CREDIT_CARD => 'danger',
            // self::CASH => 'success',

            self::MP01 => '',
            self::MP02 => '',
            self::MP03 => '',
            self::MP04 => '',
            self::MP05 => '',
            self::MP06 => '',
            self::MP07 => '',
            self::MP08 => '',
            self::MP09 => '',
            self::MP10 => '',
            self::MP11 => '',
            self::MP12 => '',
            self::MP13 => '',
            self::MP14 => '',
            self::MP15 => '',
            self::MP16 => '',
            self::MP17 => '',
            self::MP18 => '',
            self::MP19 => '',
            self::MP20 => '',
            self::MP21 => '',
            self::MP22 => '',
            self::MP23 => '',
        };
    }

    public function getCode(): string | array | null
    {
        return match($this) {
            self::MP01 => 'MP01',
            self::MP02 => 'MP02',
            self::MP03 => 'MP03',
            self::MP04 => 'MP04',
            self::MP05 => 'MP05',
            self::MP06 => 'MP06',
            self::MP07 => 'MP07',
            self::MP08 => 'MP08',
            self::MP09 => 'MP09',
            self::MP10 => 'MP10',
            self::MP11 => 'MP11',
            self::MP12 => 'MP12',
            self::MP13 => 'MP13',
            self::MP14 => 'MP14',
            self::MP15 => 'MP15',
            self::MP16 => 'MP16',
            self::MP17 => 'MP17',
            self::MP18 => 'MP18',
            self::MP19 => 'MP19',
            self::MP20 => 'MP20',
            self::MP21 => 'MP21',
            self::MP22 => 'MP22',
            self::MP23 => 'MP23',
        };
    }

    public function getOrder(): string | array | null
    {
        return match($this) {
            self::MP01 => 2,
            self::MP05 => 1,
            self::MP08 => 3,
            default => 99,
        };
    }
}
