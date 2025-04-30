<?php

namespace App\Enums;

enum ContractType: string
{
    //
    case CONTRATTO = "contratto";
    case DELIBERA_GC = "delibera_gc";
    case DELIBERA_GM = "delibera_gm";
    case DETERMINA = "determina";
    case IMPEGNO = "impegno";
    case CONVENZIONE = "convenzione";
    case DISCIPLINARE = "disciplinare";

    public function label(): string
    {
        return match($this) {
            self::CONTRATTO => 'Contratto',
            self::DELIBERA_GC => 'Delibera G.C.',
            self::DELIBERA_GM => 'Delibera G.M.',
            self::CONTRATTO => 'Contratto',
            self::DETERMINA => 'Determina',
            self::IMPEGNO => 'Impegno',
            self::CONVENZIONE => 'Convenzione',
            self::DISCIPLINARE => 'Disciplinare'
        };
    }
}
