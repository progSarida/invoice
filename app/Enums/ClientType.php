<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

// enum ClientType: string implements HasLabel, HasColor, HasDescription
enum ClientType: string implements HasLabel
{
    //
    // case COMPANY = "company";
    // case CITY = "city";
    // case CITIES_UNION = "cities_union";
    // case CITIES_FEDERATION = "cities_federation";
    // case PROVINCE = "province";
    // case REGION = "region";

    case PRIVATE = "private";
    case PUBLIC = "public";

    public function getLabel(): string
    {
        return match($this) {
            // self::COMPANY => 'Soggetto privato',
            // self::CITY => 'Comune',
            // self::CITIES_UNION => 'Unione di Comuni',
            // self::CITIES_FEDERATION => 'Federazione di Comuni',
            // self::PROVINCE => 'Provincia',
            // self::REGION => 'Regione',
            self::PRIVATE => 'Soggetto privato',
            self::PUBLIC => 'Pubblica amministrazione',
        };
    }

    // public function getDescription(): string
    // {
    //     return match($this) {
    //         self::COMPANY => 'Committente privato',
    //         self::CITY => 'Amministrazione Comunale di',
    //         self::CITIES_UNION => 'Unione di Comuni',
    //         self::CITIES_FEDERATION => 'Federazione di Comuni',
    //         self::PROVINCE => 'Provincia di',
    //         self::REGION => 'Regione',
    //     };
    // }

    // public function getColor(): string
    // {
    //     return match($this) {
    //         self::COMPANY => 'info',
    //         self::CITY => 'success',
    //         self::CITIES_UNION => 'danger',
    //         self::CITIES_FEDERATION => 'warning',
    //         self::PROVINCE => 'gray',
    //         self::REGION => Color::Yellow,
    //     };
    // }

    // public function isCompany(){
    //     return $this === self::COMPANY;
    // }

    public function isPrivate(){
        return $this === self::PRIVATE;
    }

    public static function getOptions(): array
    {
        return collect(self::cases())->mapWithKeys(fn($case) => [$case->value => $case->getLabel()])->toArray();
    }

}
