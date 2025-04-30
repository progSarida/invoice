<?php

namespace App\Enums;

enum AccrualType: string
{
    //
    case ORDINARY = "ordinary";
    case COERCIVE = "coercive";

    public function label(): string
    {
        return match($this) {
            self::ORDINARY => 'Competenza ordinaria',
            self::COERCIVE => 'Competenza coattiva',
        };
    }
}
