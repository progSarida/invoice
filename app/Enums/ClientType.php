<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ClientType: string implements HasLabel, HasColor
{
    //
    case COMPANY = "company";
    case CITY = "city";
    case CITIES_UNION = "cities_union";
    case CITIES_FEDERATION = "cities_federation";
    case PROVINCE = "province";
    // case REGION = "region";

    public function getLabel(): string
    {
        return match($this) {
            self::COMPANY => 'Committente privato',
            self::CITY => 'Amministrazione Comunale di',
            self::CITIES_UNION => 'Unione di Comuni',
            self::CITIES_FEDERATION => 'Federazione di Comuni',
            self::PROVINCE => 'Provincia di'
            // self::REGION => 'Regione',
        };
    }

    public function getColor(): string
    {
        return match($this) {
            self::COMPANY => 'info',
            self::CITY => 'success',
            self::CITIES_UNION => 'danger',
            self::CITIES_FEDERATION => 'warning',
            self::PROVINCE => 'gray'
            // self::REGION => Color::Yellow,
        };
    }

}
