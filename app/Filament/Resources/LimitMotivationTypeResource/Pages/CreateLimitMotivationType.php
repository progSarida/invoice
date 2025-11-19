<?php

namespace App\Filament\Resources\LimitMotivationTypeResource\Pages;

use App\Filament\Resources\LimitMotivationTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateLimitMotivationType extends CreateRecord
{
    protected static string $resource = LimitMotivationTypeResource::class;

    public function getTitle(): string
    {
        return "Nuova motivazione art. 26 633/72";
    }
}
