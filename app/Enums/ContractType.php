<?php

namespace App\Enums;

use Filament\Support\Colors\Color;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ContractType: string implements HasLabel, HasColor
{
    //
    case NESSUNO = "";
    case CONTRATTO = "contratto";
    case DELIBERA_GC = "delibera_gc";
    case DELIBERA_GM = "delibera_gm";
    case DETERMINA = "determina";
    case IMPEGNO = "impegno";
    case CONVENZIONE = "convenzione";
    case DISCIPLINARE = "disciplinare";

    public function getLabel(): string
    {
        return match($this) {
            self::NESSUNO => '',
            self::CONTRATTO => 'Contratto',
            self::DELIBERA_GC => 'Delibera G.C.',
            self::DELIBERA_GM => 'Delibera G.M.',
            self::DETERMINA => 'Determina',
            self::IMPEGNO => 'Impegno',
            self::CONVENZIONE => 'Convenzione',
            self::DISCIPLINARE => 'Disciplinare'
        };
    }

    public function getColor(): string | array | null
    {
        return match($this) {
            self::CONTRATTO => 'info',
            self::DELIBERA_GC => 'warning',
            self::DELIBERA_GM => 'success',
            self::DETERMINA => 'danger',
            self::IMPEGNO =>  Color::Blue,
            self::CONVENZIONE => Color::Cyan,
            self::DISCIPLINARE =>  Color::Orange,
        };
    }
}
