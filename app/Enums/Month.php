<?php

namespace App\Enums;

use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

enum Month: string implements HasLabel, HasDescription
{
    case JAN = "jan";
    case FEB = "feb";
    case MAR = "mar";
    case APR = "apr";
    case MAY = "may";
    case JUN = "jun";
    case JUL = "jul";
    case AGO = "ago";
    case SEP = "sep";
    case OCT = "oct";
    case NOV = "nov";
    case DEC = "dec";

    public function getDescription(): ?string
    {
        return match($this) {
            self::JAN => 'Gennaio',
            self::FEB => 'Febbraio',
            self::MAR => 'Marzo',
            self::APR => 'Aprile',
            self::MAY => 'Maggio',
            self::JUN => 'Giugno',
            self::JUL => 'Luglio',
            self::AGO => 'Agosto',
            self::SEP => 'Settembre',
            self::OCT => 'Ottobre',
            self::NOV => 'Novembre',
            self::DEC => 'Dicembre',
        };
    }

    public function getLabel(): string
    {
        return match($this) {
            self::JAN => 'Gennaio',
            self::FEB => 'Febbraio',
            self::MAR => 'Marzo',
            self::APR => 'Aprile',
            self::MAY => 'Maggio',
            self::JUN => 'Giugno',
            self::JUL => 'Luglio',
            self::AGO => 'Agosto',
            self::SEP => 'Settembre',
            self::OCT => 'Ottobre',
            self::NOV => 'Novembre',
            self::DEC => 'Dicembre',
        };
    }

    public function getCode(): string
    {
        return match($this) {
            self::JAN => 'GEN',
            self::FEB => 'FEB',
            self::MAR => 'MAR',
            self::APR => 'APR',
            self::MAY => 'MAG',
            self::JUN => 'GIU',
            self::JUL => 'LUG',
            self::AGO => 'AGO',
            self::SEP => 'SET',
            self::OCT => 'OTT',
            self::NOV => 'NOV',
            self::DEC => 'DIC',
        };
    }
}
