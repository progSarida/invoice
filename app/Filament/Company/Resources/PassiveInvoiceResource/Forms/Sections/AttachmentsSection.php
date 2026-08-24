<?php

namespace App\Filament\Company\Resources\PassiveInvoiceResource\Forms\Sections;

use Filament\Forms;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Illuminate\Support\Facades\Storage;

class AttachmentsSection
{
    public static function make(): Forms\Components\Section
    {
        return Section::make('Allegati')
                ->collapsed(false)
                ->columns(6)
                ->schema([
                    Placeholder::make('attachments')
                        ->key('attachments_list')
                        ->label('')
                        ->content(function ($record) {
                            if (!$record || !$record->attachments_path) {
                                return 'Nessun allegato.';
                            }

                            $disk = config('filesystems.default');

                            // Usa allFiles per prendere anche file in sottocartelle
                            $files = Storage::disk($disk)->allFiles($record->attachments_path);

                            if (empty($files)) {
                                return 'Nessuna cartella allegati trovata.';
                            }

                            return new \Illuminate\Support\HtmlString(
                                collect($files)
                                    ->sort()
                                    ->map(function ($file) use ($disk) {
                                        $name = basename($file);

                                        $url = Storage::temporaryUrl($file, now()->addMinutes(5));

                                        return <<<HTML
                                        <div class="flex items-center gap-3 py-1">
                                            <a href="{$url}" target="_blank" download class="text-primary-600 hover:underline font-medium">
                                                {$name}
                                            </a>
                                        </div>
                                        HTML;
                                    })
                                    ->implode('')
                            );
                        })
                        ->extraAttributes(['style' => 'line-height:1.8'])
                        ->columnSpanFull(),
                ]);
    }
}
