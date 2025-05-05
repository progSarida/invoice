<?php

namespace App\Enums;

use Filament\Support\Colors\Color;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

enum TaxType: string implements HasLabel, HasColor, HasDescription
{
    //
    case CDS = "cds";
    case ICI = "ici";
    case IMU = "imu";
    case LIBERO = "libero";
    case PARK = "park";
    case PUB = "pub";
    case TARI = "tari";
    case TEP = "tep";
    case TOSAP = "tosap";

    public function getDescription(): ?string
    {
        return match($this) {
            self::CDS => 'Codice della Strada',
            self::ICI => 'Imposta Comunale sugli Immobili',
            self::IMU => 'Imposta Municipale Unica',
            self::LIBERO => 'Libera',
            self::PARK => 'Parcheggio',
            self::PUB => 'Imposta sulla Pubblicità',
            self::TARI => 'Tassa sui Rifiuti',
            self::TEP => 'TEP',
            self::TOSAP => 'Tassa per l\'Occupazione del Suolo Pubblico',
        };
    }

    public function getLabel(): string
    {
        return $this->name;

        // return match($this) {
        //     self::CDS => 'CDS',
        //     self::ICI => 'ICI',
        //     self::IMU => 'IMU',
        //     self::FREE => 'LIBERO',
        //     self::PARK => 'PARCHEGGIO',
        //     self::PUB => 'PUBBLICITA\'',
        //     self::TARI => 'TARI',
        //     self::TEP => 'TEP',
        //     self::TOSAP => 'TOSAP',
        // };
    }

    public function getColor(): string | array | null
    {
        return match($this) {
            self::CDS => 'info',
            self::ICI => 'warning',
            self::IMU => 'success',
            self::LIBERO => 'danger',
            self::PARK =>  Color::Blue,
            self::PUB => Color::Cyan,
            self::TARI =>  Color::Orange,
            self::TEP => Color::Amber,
            self::TOSAP => Color::Yellow,
        };
    }
}
