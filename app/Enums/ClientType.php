<?php

namespace App\Enums;

enum ClientType: string
{
    //
    case COMPANY = "company";
    case CITY = "city";
    case CITIES_UNION = "cities_union";
    case CITIES_FEDERATION = "cities_federation";
    case PROVINCE = "province";
    case REGION = "region";

    public function label(): string
    {
        return match($this) {
            self::COMPANY => 'Committente privato',
            self::CITY => 'Amministrazione Comunale di',
            self::CITIES_UNION => 'Unione di Comuni',
            self::CITIES_FEDERATION => 'Federazione di Comuni',
            self::PROVINCE => 'Provincia di',
            self::REGION => 'Regione',
        };
    }
}
