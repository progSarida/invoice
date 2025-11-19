<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ClientSubType: string implements HasLabel
{
    case EMPTY = '';
    case COMPANY = 'company';
    case MAN = 'man';
    case WOMAN = 'woman';
    case PROFESSIONAL = 'professional';
    case CITY = 'city';
    case UNION = 'union';
    case FEDERATION = 'federation';
    case PROVINCE = 'province';

    public function getLabel(): string
    {
        return match($this) {
            self::EMPTY => '',
            self::COMPANY => 'Azienda',
            self::MAN => 'Uomo',
            self::WOMAN => 'Donna',
            self::PROFESSIONAL => 'Professionista',
            self::CITY => 'Comune',
            self::UNION => 'Unione di comuni',
            self::FEDERATION => 'Federazione di comuni',
            self::PROVINCE => 'Provincia',
        };
    }

    public function getType(): ?string
    {
        return match ($this) {
            self::COMPANY, self::MAN, self::WOMAN, self::PROFESSIONAL, self::EMPTY => ClientType::PRIVATE->value,
            self::CITY, self::UNION, self::FEDERATION, self::PROVINCE => ClientType::PUBLIC->value,
            default => null,
        };
    }

    public static function groupedByType(): array
    {
        return [
            'private' => [
                self::EMPTY,
                self::COMPANY,
                self::MAN,
                self::WOMAN,
                self::PROFESSIONAL
            ],
            'public' => [
                self::CITY,
                self::UNION,
                self::FEDERATION,
                self::PROVINCE,
            ],
        ];
    }

    public static function optionsForType(?string $categoria): array
    {
        if($categoria == '')
            return collect(self::cases())
                ->mapWithKeys(fn($case) => [$case->value => $case->getLabel()])
                ->toArray();
        else
            return collect(self::groupedByType()[$categoria] ?? [])
                ->mapWithKeys(fn($case) => [$case->value => $case->getLabel()])
                ->toArray();
    }

    public function getRecipient(): string
    {
        return match($this) {
            self::EMPTY => 'Spett. le',
            self::COMPANY => 'Spett. le ditta',
            self::MAN => 'Egr. signore',
            self::WOMAN => 'Egr. signora',
            self::PROFESSIONAL => 'Spett. le',
            self::CITY => 'Spett. le Comune di',
            self::UNION => 'Spett. le',
            self::FEDERATION => 'Spett. le',
            self::PROVINCE => 'Spett. le Provincia di',
        };
    }

    public function isCompany(){
        return $this === self::COMPANY;
    }

    public function isPerson(){
        return $this === self::MAN || $this === self::WOMAN;
    }

    public function isProfessional(){
        return $this === self::PROFESSIONAL;
    }
}
