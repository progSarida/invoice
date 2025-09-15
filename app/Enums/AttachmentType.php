<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum AttachmentType: string implements HasLabel
{
    case BAIL_BILL = "bail_bill";
    case BAIL_RECEIPT = "bail_receipt";
    case CONTRACT = "contract";
    case POSTAL_ACT = "postal_act";
    case POSTAL_NOTIFY = "postal_notify";
    case POSTAL_REINVOICE = "postal_reinvoice";

    public function getLabel(): string
    {
        return match($this) {
            self::BAIL_BILL => 'Polizze cauzioni',
            self::BAIL_RECEIPT => 'Ricevute di pagamento',
            self::CONTRACT => 'Contratti',
            self::POSTAL_ACT => 'Atti notificati',
            self::POSTAL_NOTIFY => 'Notifiche',
            self::POSTAL_REINVOICE => 'Rifatturazioni',
        };
    }
}
