<?php

namespace App\Enums;

use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

enum VatCodeType: string implements HasLabel, HasDescription
{
    case VC01 = "vc01";
    case VC02 = "vc02";
    case VC03 = "vc03";
    case VC04 = "vc04";
    case VC05 = "vc05";
    case VC06 = "vc06";
    case VC07 = "vc07";
    case VC08 = "vc08";
    case VC09 = "vc09";
    case VC10 = "vc10";
    case VC11 = "vc11";
    case VC12 = "vc12";
    case VC13 = "vc13";
    case VC14 = "vc14";
    case VC15 = "vc15";
    case VC16 = "vc16";
    case VC17 = "vc17";
    case VC18 = "vc18";
    case VC19 = "vc19";
    case VC20 = "vc20";
    case VC21 = "vc21";
    case VC22 = "vc22";
    case VC23 = "vc23";
    case VC24 = "vc24";
    case VC25 = "vc25";
    case VC26 = "vc26";

    public function getRate(): string
    {
        return match($this) {
            self::VC01 => "22",
            self::VC02 => "10",
            self::VC03 => "5",
            self::VC04 => "4",
            self::VC05 => "0",
            self::VC06 => "0",
            self::VC07 => "0",
            self::VC08 => "0",
            self::VC09 => "0",
            self::VC10 => "0",
            self::VC11 => "0",
            self::VC12 => "0",
            self::VC13 => "0",
            self::VC14 => "0",
            self::VC15 => "0",
            self::VC16 => "0",
            self::VC17 => "0",
            self::VC18 => "0",
            self::VC19 => "0",
            self::VC20 => "0",
            self::VC21 => "0",
            self::VC22 => "0",
            self::VC23 => "0",
            self::VC24 => "0",
            self::VC25 => "0",
            self::VC26 => "0"
        };
    }

    public function getCode(): string
    {
        return match($this) {
            self::VC01 => "",
            self::VC02 => "",
            self::VC03 => "",
            self::VC04 => "",
            self::VC05 => "",
            self::VC06 => "N1",
            self::VC07 => "N1",
            self::VC08 => "N2.1",
            self::VC09 => "N2.2",
            self::VC10 => "N3.1",
            self::VC11 => "N3.1",
            self::VC12 => "N3.2",
            self::VC13 => "N3.3",
            self::VC14 => "N3.4",
            self::VC15 => "N3.5",
            self::VC16 => "N3.6",
            self::VC17 => "N4",
            self::VC18 => "N4",
            self::VC19 => "N5",
            self::VC20 => "N5",
            self::VC21 => "N6.1",
            self::VC22 => "N6.2",
            self::VC23 => "N6.3",
            self::VC24 => "N6.4",
            self::VC25 => "N6.5",
            self::VC26 => "N6.6"
        };
    }

    public function getDescription(): ?string
    {
        return match($this) {
            self::VC01 => "",
            self::VC02 => "",
            self::VC03 => "",
            self::VC04 => "",
            self::VC05 => "Articolo generico",
            self::VC06 => "Escluso Art. 15 DPR 633/72",
            self::VC07 => "Legge 27 novembre 1989, n. 384",
            self::VC08 => "Non soggette ad IVA ai sensi degli artt. Da 7 a 7-septies del DPR 633/72",
            self::VC09 => "Non soggette - altri casi",
            self::VC10 => "Non Imponibile esportazioni art. 8, c. 1, Let. A)DPR 633/72",
            self::VC11 => "Non imponibile assimilate alle esportazioni art. 8-bis DPR 633/72",
            self::VC12 => "Non imponibile cessioni intracomunitarie Art. 41 DL 331/93",
            self::VC13 => "Non imponibile cessioni verso San MArino e Citta del Vaticano Art. 71 DPR 633/72",
            self::VC14 => "Non imponibile aperazioni assimilate alle cessioni all'esportazione",
            self::VC15 => "Non imponibile a seguito dichiarazioni d'intento Art. 8, c. 1, Let C) DPR 633/72",
            self::VC16 => "Non imponibile altre operazioni che non concorrono alla formazione del platfond",
            self::VC17 => "Esente Art. 10 DPR 633/72",
            self::VC18 => "Esente art. 124 c. 2 DL 34/20 (operazioni contenimento Covid)",
            self::VC19 => "E£scluso Art. 74 DPR 633/72",
            self::VC20 => "Regime del margine Art. 36 41/95",
            self::VC21 => "Reverse charge Art. 74 vendita rottami e materiali di recupero DPR 633/72",
            self::VC22 => "Reverse charge cessioni di oro e argento puro Art. 17, c. 5 DPR 633/72",
            self::VC23 => "Reverse charge subappalto nel settore edile Art. 17, c. 6 let. a), DPR 633/72",
            self::VC24 => "Reverse charge cessione di fabbricati Art. 17, c. 6 lett. a-bis) DPR 633/72",
            self::VC25 => "Reverse charge cessioni di telefoni cellulari Art. 17, c. 6 lett. b) DPR 633/72",
            self::VC26 => "Reverse charge cessione di prodotti elettronici Art. 17, c. 6 lett. c) DPR 633/72"
        };
    }

    public function getLabel(): string
    {
        return match($this) {
            self::VC01, self::VC02, self::VC03, self::VC04 => $this->getRate() . "%",
            default => $this->getRate() . "% - " . $this->getCode() . " - " . $this->getDescription()
        };
    }
}
