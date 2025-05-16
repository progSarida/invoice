<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Contracts\HasDescription;

enum PaymentStatus: string implements HasLabel, HasColor, HasDescription
{
    //
    case WAITING = "waiting";
    CASE PARTIAL = "partial";
    case PAIED = "paied";
    case PARTIAL_CREDIT_NOTE = "partial_credit_note";
    case PAIED_CREDIT_NOTE = "paied_credit_note";
    case FULL_CREDIT_NOTE = "full_credit_note";

    public function getDescription(): string
    {
        return match($this) {
            self::WAITING => 'In attesa di pagamento',
            self::PARTIAL => 'Pagamento parziale',
            self::PAIED => 'Pagamento completo',
            self::PARTIAL_CREDIT_NOTE => 'Storno parziale nota di credito',
            self::PAIED_CREDIT_NOTE => 'Storno parziale nota di credito e pagamento completo',
            self::FULL_CREDIT_NOTE => 'Storno completo nota di credito'
        };
    }

    public function getLabel(): string
    {
        return match($this) {
            self::WAITING => 'In attesa',
            self::PARTIAL => 'Parziale',
            self::PAIED => 'Completo',
            self::PARTIAL_CREDIT_NOTE => 'Storno parziale',
            self::PAIED_CREDIT_NOTE => 'Storno parziale e pagamento completo',
            self::FULL_CREDIT_NOTE => 'Storno completo'
        };
    }

    public function getColor(): string | array | null
    {
        return match($this) {
            self::WAITING => 'danger',
            self::PARTIAL => 'warning',
            self::PAIED => 'success',
            self::PARTIAL_CREDIT_NOTE => 'warning',
            self::PAIED_CREDIT_NOTE => 'success',
            self::FULL_CREDIT_NOTE => 'success'
        };
    }
}
