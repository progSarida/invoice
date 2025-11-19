<?php

namespace App\Enums;

use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

enum ProductType: string implements HasLabel, HasDescription
{
    case GIU = "giu";
    case RACI = "raci";
    case RACE = "race";
    case PORD = "pord";
    case P4PRO = "p4pro";
    case SMART = "smart";
    case SPASS = "spass";
    case SPINVIO = "spinvio";
    case SPCOA = "spcoa";
    case CAN = "can";
    case CAD = "cad";

    public function getDescription(): ?string
    {
        return match($this) {
            self::GIU => 'Atto giudiziario',
            self::RACI => 'Raccomandata Italia',
            self::RACE => 'Raccomandata Estero',
            self::PORD => 'Posta ordinaria',
            self::P4PRO => 'Posta4PRO',
            self::SMART => 'Raccomandata SMART',
            self::SPASS => 'Spese c/assegno',
            self::SPINVIO => 'Spese invio atti a messi/ufficiali giudiziari',
            self::SPCOA => 'Spese riscossione coattiva',
            self::CAN => 'Certificato di avviso di notifica',
            self::CAD => 'Certificato di avviso di deposito',
        };
    }

    public function getLabel(): string
    {
        return match($this) {
            self::GIU => 'Atto giudiziario',
            self::RACI => 'Racc. Italia',
            self::RACE => 'Racc. Estero',
            self::PORD => 'Posta ordinaria',
            self::P4PRO => 'Posta4PRO',
            self::SMART => 'Racc. SMART',
            self::SPASS => 'Spese c/assegno',
            self::SPINVIO => 'Spese messi/ufficiali giudiziari',
            self::SPCOA => 'Spese coattiva',
            self::CAN => 'Certificato notifica',
            self::CAD => 'Certificato deposito',
        };
    }

    public function getCode(): string
    {
        return match($this) {
            self::GIU => 'GIUDIZ',
            self::RACI => 'RACITA',
            self::RACE => 'RACEST',
            self::PORD => 'PORDIN',
            self::P4PRO => 'P4PRO',
            self::SMART => 'RSMART',
            self::SPASS => 'SPASS',
            self::SPINVIO => 'SPINVIO',
            self::SPCOA => 'SPCOATT',
            self::CAN => 'CAN',
            self::CAD => 'CAD',
        };
    }
}
