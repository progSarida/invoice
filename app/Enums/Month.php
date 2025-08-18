<?php

namespace App\Enums;

use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

enum Month: string implements HasLabel, HasDescription
{
    case JAN = "1";
    case FEB = "2";
    case MAR = "3";
    case APR = "4";
    case MAY = "5";
    case JUN = "6";
    case JUL = "7";
    case AGO = "8";
    case SEP = "9";
    case OCT = "10";
    case NOV = "11";
    case DEC = "12";

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
