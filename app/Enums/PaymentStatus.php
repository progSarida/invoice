<?php

namespace App\Enums;

enum PaymentStatus: string
{
    //
    case WAITING = "waiting";
    CASE PARTIAL = "partial";
    case PAIED = "paied";
    case PARTIAL_CREDIT_NOTE = "partial_credit_note";
    case PAIED_CREDIT_NOTE = "paied_credit_note";
    case FULL_CREDIT_NOTE = "full_credit_note";

    public function label(): string
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
}
