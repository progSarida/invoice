<?php

namespace App\Enums;

use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

enum TimingType: string implements HasLabel, HasDescription
{
    case CONTEXTUAL = "contestuale";
    case DEFERRED = "differita";

    public function getLabel(): ?string
    {
        return match($this) {
            self::CONTEXTUAL => "Contestuale",
            self::DEFERRED => "Differita",
        };
    }

    public function getDescription(): ?string
    {
        return match($this) {
            self::CONTEXTUAL => "Contestuale",
            self::DEFERRED => "Differita",
        };
    }

}
