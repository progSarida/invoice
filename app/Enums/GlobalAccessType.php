<?php

namespace App\Enums;

enum GlobalAccessType: int
{
    case AllPanels = 10000;
    case AdminPanel = 10001;
    case CompanyPanel = 10002;
    
    public function getLabel(): ?string
    {
        return match ($this) {
            self::AllPanels => 'Globale',
            self::AdminPanel => 'Gestione Amministrazione',
            self::CompanyPanel => 'Gestione Aziende',
            default => 'Azienda', // <-- Nuovo
        };
    }
    
    public function getIcon(): ?string
    {
        return match ($this) {
            self::AllPanels => 'heroicon-o-shield-check',
            self::AdminPanel => 'heroicon-o-cog-6-tooth',
            self::CompanyPanel => 'heroicon-o-globe-alt',
            default => 'heroicon-o-building-office-2', // <-- Nuovo
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::AllPanels => 'success',
            self::AdminPanel => 'warning',
            self::CompanyPanel => 'info',
            default => 'gray', // <-- Nuovo
        };
    }

    /**
     * Restituisce un array di tutti gli ID che hanno accesso a TUTTI i tenant (1000 e 1002, ecc.).
     */
    public static function getCompanyAccessIds(): array
    {
        return [
            self::AllPanels->value,
            self::CompanyPanel->value,
        ];
    }

    /**
     * Restituisce un array di tutti gli ID che hanno accesso al Pannello Admin (1000 e 1001, ecc.).
     */
    public static function getPanelAccessIds(): array
    {
        return [
            self::AllPanels->value,
            self::AdminPanel->value,
        ];
    }
}