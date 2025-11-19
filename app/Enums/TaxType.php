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
    case CUP = "cup";
    case LIBERO = "libero";
    case PARK = "park";
    case PUB = "pub";
    case TARI = "tari";
    case TEP = "tep";
    case TOSAP = "tosap";
    case VOTIVA = "votiva";
    case COATTIVA = "coattiva";
    case EMPTY = "";

    public function getDescription(): ?string
    {
        return match($this) {
            self::CDS => 'Codice della Strada',
            self::ICI => 'Imposta Comunale sugli Immobili',
            self::IMU => 'Imposta Municipale Unica',
            self::CUP => 'Canone unico patrimoniale',
            self::LIBERO => 'Libera',
            self::PARK => 'Parcheggio',
            self::PUB => 'Imposta sulla Pubblicità (In disuso)',
            self::TARI => 'Smaltimento rifiuti solidi urbani',
            self::TEP => 'TEP',
            self::TOSAP => 'Tassa per l\'Occupazione del Suolo Pubblico (In disuso)',
            self::COATTIVA => 'Riscossione coattiva',
            self::VOTIVA => 'Illuminazione votiva',
            self::EMPTY => '',
        };
    }

    public function getLabel(): string
    {
        return match($this) {
            self::CDS => 'CDS',
            self::ICI => 'ICI',
            self::IMU => 'IMU',
            self::CUP => 'CUP',
            self::LIBERO => 'LIBERO',
            self::PARK => 'PARCHEGGIO',
            self::PUB => 'PUBBLICITA\'',
            self::TARI => 'RSU',
            self::TEP => 'TEP',
            self::TOSAP => 'TOSAP',
            self::VOTIVA => 'VOTIVA',
            self::COATTIVA => 'COATTIVA',
            self::EMPTY => '',
        };
    }

    public function getCode(): string
    {
        return match($this) {
            self::CDS => 'CDS',
            self::ICI => 'ICI',
            self::IMU => 'IMU',
            self::CUP => 'CUP',
            self::LIBERO => 'LIB',
            self::PARK => 'PAR',
            self::PUB => 'PUB',
            self::TARI => 'RSU',
            self::TEP => 'TEP',
            self::TOSAP => 'OSP',
            self::VOTIVA => 'VOT',
            self::COATTIVA => 'COA',
            self::EMPTY => '',
        };
    }

    public function getColor(): string | array | null
    {
        // return match($this) {
        //     self::CDS => 'info',
        //     self::ICI => 'warning',
        //     self::IMU => 'success',
        //     self::LIBERO => 'danger',
        //     self::PARK =>  Color::Blue,
        //     self::PUB => Color::Cyan,
        //     self::TARI =>  Color::Orange,
        //     self::TEP => Color::Amber,
        //     self::TOSAP => Color::Yellow,
        // };
        return null;
    }

    public static function multiColor($state): string
    {
        return match ($state) {
            "cds" => 'info',
            "ici" => 'warning',
            "imu" => 'success',
            "cup" => 'primary',
            "libero" => 'danger',
            "park" =>  'info',
            "pub" => 'info',
            "tari" =>  'primary',
            "tep" => 'primary',
            "tosap" => 'warning',
            "votiva" => 'info',
            "coattiva" => 'danger'
        };
    }
}
