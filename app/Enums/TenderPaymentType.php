<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

enum TenderPaymentType: string implements HasLabel, HasColor, HasDescription
{
    //
    case AGGIO = "aggio";
    case SERVIZIO = "servizio";
    case CANONE = "canone";

    public function getLabel(): string
    {
        return match($this) {
            self::AGGIO => 'AGGIO',
            self::SERVIZIO => 'SERVIZIO',
            self::CANONE => 'CANONE',
        };
    }

    public function getColor(): string
    {
        return match($this) {
            self::AGGIO => 'danger',
            self::SERVIZIO => 'info',
            self::CANONE => 'success',
        };
    }

    public function getDescription(): string
    {
        return match($this) {
            self::AGGIO => 'Pagamento ad aggio',
            self::SERVIZIO => 'Pagamento a servizio',
            self::CANONE => 'Pagamento a canone',
        };
    }
}
