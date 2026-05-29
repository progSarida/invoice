<?php

namespace App\Filament\Company\Resources\TemplateResource\Pages;

use App\Filament\Company\Resources\TemplateResource;
use App\Filament\Company\Resources\TemplateResource\Concerns\HandlesTemplateFile;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Storage;

class EditTemplate extends EditRecord
{
    use HandlesTemplateFile;

    protected static string $resource = TemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeUpdate(array $data): array
    {
        unset($data['upload_path'], $data['original_filename']);
        return $data;
    }

    protected function afterSave(): void
    {
        $this->handleFileUpload(
            $this->record,
            $this->form->getState(),
            $this->record->getOriginal('path')
        );
    }
}
