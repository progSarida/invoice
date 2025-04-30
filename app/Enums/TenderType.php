<?php

namespace App\Enums;

enum TenderType: string
{
    //
    case PAGATA_AD_AGGIO = "aggio";
    case SERVIZIO = "servizio";
    case PAGATA_A_CANONE = "canone";

    public function label(): string
    {
        return match($this) {
            self::PAGATA_AD_AGGIO => 'Pagamento ad aggio',
            self::SERVIZIO => 'Servizio',
            self::PAGATA_A_CANONE => 'Pagamento a canone',
        };
    }
}
