<?php

namespace App\Enums;

use Filament\Support\Colors\Color;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

enum TaxRegimeType: string implements HasLabel, HasDescription
{
    case RF01 = "rf01";
    case RF02 = "rf02";
    case RF04 = "rf04";
    case RF05 = "rf05";
    case RF06 = "rf06";
    case RF07 = "rf07";
    case RF08 = "rf08";
    case RF09 = "rf09";
    case RF10 = "rf10";
    case RF11 = "rf11";
    case RF12 = "rf12";
    case RF13 = "rf13";
    case RF14 = "rf14";
    case RF15 = "rf15";
    case RF16 = "rf16";
    case RF17 = "rf17";
    case RF18 = "rf18";
    case RF19 = "rf19";
    case RF20 = "rf20";

    public function getCode(): string
    {
        return match($this) {
            self::RF01 => "RF01",
            self::RF02 => "RF02",
            self::RF04 => "RF04",
            self::RF05 => "RF05",
            self::RF06 => "RF06",
            self::RF07 => "RF07",
            self::RF08 => "RF08",
            self::RF09 => "RF09",
            self::RF10 => "RF10",
            self::RF11 => "RF11",
            self::RF12 => "RF12",
            self::RF13 => "RF13",
            self::RF14 => "RF14",
            self::RF15 => "RF15",
            self::RF16 => "RF16",
            self::RF17 => "RF17",
            self::RF18 => "RF18",
            self::RF19 => "RF19",
            self::RF20 => "RF20"
        };
    }

    public function getDescription(): ?string
    {
        return match($this) {
            self::RF01 => "Ordinario",
            self::RF02 => "Contribuenti minimi (art. 1, c. 96-117, L. 244/07)",
            self::RF04 => "Agricoltura e attività connesse e pesca (artt. 34 e 34-bis, DPR 633/72)",
            self::RF05 => "Vendita sali e tabacchi (art. 74, c. 1, DPR/633/72)",
            self::RF06 => "Commercio fiammiferi (art. 74, c. 1, DPR/633/72)",
            self::RF07 => "Editoria (art. 74, c. 1, DPR/633/72)",
            self::RF08 => "Gerstione servizi telefonia pubblica (art. 74, c. 1, DPR/633/72)",
            self::RF09 => "Rivendita documenti di trasporto pubblico e di sosta (art. 74, c. 1, DPR/633/72)",
            self::RF10 => "Intrattenimenti, giochi e altre attività di cui all atariffa allegata al DPR 640/72 (art. 74, c. 1, DPR/633/72)",
            self::RF11 => "Agenzie viaggi e turismo (art. 74-ter, DPR/633/72)",
            self::RF12 => "Agriturismo (art. 5, c. 2, L. 413/91)",
            self::RF13 => "Vendite a domicilio (art. 25-bis, c. 6, DPR 600/73)",
            self::RF14 => "Rivendita benmi usati, oggetti d'arte, d'antiquariato o da collezione (art. 36, DL 41/95)",
            self::RF15 => "Agenzie di vendite all'asta di oggetti d'arte, antiquariato o da collezione (art. 40-bis, DL 41/95)",
            self::RF16 => "IVA per cassa P.A. (art. 6, c. 5, DPR 633/72)",
            self::RF17 => "IVA per cassa (art. 32-bis, DL 83/2012)",
            self::RF18 => "Altro",
            self::RF19 => "Regime forfettario (art. 1, c. 54-89, L. 190/2014 e successive modifiche)",
            self::RF20 => "Regime transfrontaliero di Franchigia IVA (Direttiva UE 2020/285)"
        };
    }

    public function getLabel(): string
    {
        return $this->getCode() . " - " . $this->getDescription();
    }

}
