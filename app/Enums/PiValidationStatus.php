<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;

enum PiValidationStatus: string implements HasLabel, HasColor, HasDescription, HasIcon
{
    case OK = 'ok';
    case WAIT = 'wait';
    case BLOCK = 'block';

    public function getDescription(): string
    {
        return match($this) {
            self::OK => '',
            self::WAIT => '',
            self::BLOCK => '',
        };
    }

    public function getLabel(): string
    {
        return match($this) {
            self::OK => 'Procedi',
            self::WAIT => 'Aspetta',
            self::BLOCK => 'Blocca',
        };
    }

    public function getIcon(): string
    {
        return match($this) {
            self::OK => 'fluentui-checkmark-circle-20-o',
            self::WAIT => 'fluentui-clock-20-o',
            self::BLOCK => 'fluentui-dismiss-circle-20-o',
        };
    }

    public function getColor(): string | array | null
    {
        return match($this) {
            self::OK => 'success',
            self::WAIT => 'warning',
            self::BLOCK => 'danger',
        };
    }
}
