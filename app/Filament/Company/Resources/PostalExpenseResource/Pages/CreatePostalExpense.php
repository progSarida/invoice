<?php

namespace App\Filament\Company\Resources\PostalExpenseResource\Pages;

use App\Filament\Company\Resources\PostalExpenseResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreatePostalExpense extends CreateRecord
{
    protected static string $resource = PostalExpenseResource::class;
}
