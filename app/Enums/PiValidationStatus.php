<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;

enum PiValidationStatus: string implements HasLabel, HasColor, HasDescription, HasIcon
{
    case NO_STATUS = 'no_status';
    case OK = 'ok';
    case WAIT = 'wait';
    case BLOCK = 'block';
    case VIEW = 'view';

    public function getDescription(): string
    {
        return match($this) {
            self::NO_STATUS => '',
            self::OK => '',
            self::WAIT => '',
            self::BLOCK => '',
            self::VIEW => '',
        };
    }

    public function getLabel(): string
    {
        return match($this) {
            self::NO_STATUS => 'Da validare',
            self::OK => 'Procedi',
            self::WAIT => 'Aspetta',
            self::BLOCK => 'Blocca',
            self::VIEW => 'Vista',
        };
    }

    public function getIcon(): string
    {
        return match($this) {
            self::NO_STATUS => 'phosphor-empty',
            self::OK => 'fluentui-checkmark-circle-20-o',
            self::WAIT => 'fluentui-clock-20-o',
            self::BLOCK => 'fluentui-dismiss-circle-20-o',
            self::VIEW => 'fluentui-checkmark-circle-20-o',
        };
    }

    public function getColor(): string | array | null
    {
        return match($this) {
            self::NO_STATUS => 'gray',
            self::OK => 'success',
            self::WAIT => 'warning',
            self::BLOCK => 'danger',
            self::VIEW => 'success',
        };
    }

    public static function toArray(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn ($case) => [$case->value => $case->getLabel()])
            ->toArray();
    }
}
