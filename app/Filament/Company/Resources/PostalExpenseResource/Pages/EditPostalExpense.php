<?php

namespace App\Filament\Company\Resources\PostalExpenseResource\Pages;

use App\Filament\Company\Resources\PostalExpenseResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPostalExpense extends EditRecord
{
    protected static string $resource = PostalExpenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
