<?php

namespace App\Filament\Company\Resources\TemplateResource\Pages;

use App\Filament\Company\Resources\TemplateResource;
use App\Filament\Company\Resources\TemplateResource\Concerns\HandlesTemplateFile;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Storage;

class CreateTemplate extends CreateRecord
{
    use HandlesTemplateFile;

    protected static string $resource = TemplateResource::class;

    public function getTitle(): string
    {
        return "Nuovo modello";
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        unset($data['upload_path'], $data['original_filename']);
        return $data;
    }

    protected function afterCreate(): void
    {
        $this->handleFileUpload($this->record, $this->form->getState());
    }
}