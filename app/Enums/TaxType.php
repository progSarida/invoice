<?php

namespace App\Enums;

enum TaxType: string
{
    //
    case CDS = "cds";
    case ICI = "ici";
    case IMU = "imu";
    case FREE = "free";
    case PARK = "park";
    case PUB = "pub";
    case TARI = "tari";
    case TEP = "tep";
    case TOSAP = "tosap";

    public function label(): string
    {
        return match($this) {
            self::CDS => 'Codice della Strada',
            self::ICI => 'Imposta Comunale sugli Immobili',
            self::IMU => 'Imposta Municipale Unica',
            self::FREE => 'Libera',
            self::PARK => 'Parcheggio',
            self::PUB => 'Imposta sulla Pubblicità',
            self::TARI => 'Tassa sui Rifiuti',
            self::TEP => 'TEP',
            self::TOSAP => 'Tassa per l\'Occupazione del Suolo Pubblico',
        };
    }
}
