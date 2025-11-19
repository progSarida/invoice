<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

enum DocType: string implements HasLabel, HasColor, HasDescription
{
    case TD00 = "td00";
    case TD01 = "td01";
    case TD01A = "td01A";
    case TD02 = "td02";
    case TD03 = "td03";
    case TD04 = "td04";
    case TD05 = "td05";
    case TD06 = "td06";
    case TD08 = "td08";
    case TD16 = "td16";
    case TD17 = "td17";
    case TD18 = "td18";
    case TD19 = "td19";
    case TD20 = "td20";
    case TD21 = "td21";
    case TD22 = "td22";
    case TD23 = "td23";
    case TD24 = "td24";
    case TD25 = "td25";
    case TD26 = "td26";
    case TD26A = "td26A";
    case TD27 = "td27";
    case TD28 = "td28";
    case TD29 = "td29";


    public function getCode(): string
    {
        return match($this) {
            self::TD00 => 'TD00',
            self::TD01 => 'TD01',
            self::TD01A => 'TD01',
            self::TD02 => 'TD02',
            self::TD03 => 'TD03',
            self::TD04 => 'TD04',
            self::TD05 => 'TD05',
            self::TD06 => 'TD06',
            self::TD08 => 'TD08',
            self::TD16 => 'TD16',
            self::TD17 => 'TD17',
            self::TD18 => 'TD18',
            self::TD19 => 'TD19',
            self::TD20 => 'TD20',
            self::TD21 => 'TD21',
            self::TD22 => 'TD22',
            self::TD23 => 'TD23',
            self::TD24 => 'TD24',
            self::TD25 => 'TD25',
            self::TD26 => 'TD26',
            self::TD26A => 'TD26',
            self::TD27 => 'TD27',
            self::TD28 => 'TD28',
            self::TD29 => 'TD29'
        };
    }

    public function getDescription(): string
    {
        return match($this) {
            self::TD00 => "Preavviso di fattura",
            self::TD01 => "Fattura",
            self::TD01A => "Autofattura",
            self::TD02 => "Acconto/anticipo su fattura",
            self::TD03 => "Acconto/anticipo su parcella",
            self::TD04 => "Nota di credito",
            self::TD05 => "Nota di debito",
            self::TD06 => "Parcella",
            self::TD08 => "Nota di credito semplificata",
            self::TD16 => "Integrazione fattura reverse charge interno",
            self::TD17 => "Integrazione/autofattura per acquisto servizi dall'estero",
            self::TD18 => "Integrazione/autofattura per acquisto di beni intracomunitari",
            self::TD19 => "Integrazione/autofattura per acquisto di beni ex art. 17 c. 2 DPR 633/72",
            self::TD20 => "Autofattura per regolarizzazione e integrazione delle fatture in reverse charge",
            self::TD21 => "Autofattura per splafonamento",
            self::TD22 => "Estrazione beni da Deposito IVA",
            self::TD23 => "Estrazione beni da Deposito IVA con versamento dell'IVA",
            self::TD24 => "Fattura differita di cui all'art. 21, comma 4, lett. a",
            self::TD25 => "Fattura differita di cui all'art. 21, comma 4, terzo periodo lett. b",
            self::TD26 => "Cessione beni ammortizzabili",
            self::TD26A => "Autofattura per passaggi interni (ex art. 36 DPR 633/72)",
            self::TD27 => "Fattura per autoconsumo o per cessioni gratuite senza rivalsa",
            self::TD28 => "Acquisti da San Marino con IVA (fattura cartacea)",
            self::TD29 => "Comunicazione per omessa o irregolare fatturazione"
        };
    }

    public function getLabel(): string
    {
        return $this->getCode() . " - " . $this->getDescription();
    }

    public function getColor(): string | array | null
    {
        return match($this) {
            self::TD00 => '',
            self::TD01 => '',
            self::TD01A => '',
            self::TD02 => '',
            self::TD03 => '',
            self::TD04 => '',
            self::TD05 => '',
            self::TD06 => '',
            self::TD08 => '',
            self::TD16 => '',
            self::TD17 => '',
            self::TD18 => '',
            self::TD19 => '',
            self::TD20 => '',
            self::TD21 => '',
            self::TD22 => '',
            self::TD23 => '',
            self::TD24 => '',
            self::TD25 => '',
            self::TD26 => '',
            self::TD26A => '',
            self::TD27 => '',
            self::TD28 => '',
            self::TD29 => ''
        };
    }

    public function getGroup(): string
    {
        return match ($this) {
            self::TD00 => 'Preavvisi di fattura',
            self::TD01, self::TD02, self::TD03, self::TD06, self::TD24, self::TD25, self::TD26 => 'Fatture',
            self::TD04, self::TD05, self::TD08 => 'Nota di varazione',
            self::TD01A, self::TD16, self::TD17, self::TD18, self::TD19, self::TD20, self::TD21, self::TD22, self::TD23, self::TD24, self::TD25, self::TD26A, self::TD27, self::TD28, self::TD29 => 'Autofatture',
        };
    }

    public static function groupedOptions(): array
    {
        $orderedGroups = [
            'Preavvisi di fattura',
            'Fatture',
            'Nota di varazione',
            'Autofatture',
        ];

        $grouped = [];

        $allGrouped = [];
        foreach (self::cases() as $case) {
            $allGrouped[$case->getGroup()][$case->value] = $case->getLabel();
        }

        foreach ($orderedGroups as $group) {
            if (isset($allGrouped[$group])) {
                $grouped[$group] = $allGrouped[$group];
            }
        }

        return $grouped;
    }
}
