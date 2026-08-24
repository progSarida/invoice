<?php

namespace App\Filament\Company\Resources\NewInvoiceResource\Forms\Sections;

use Filament\Forms;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class AttachmentsSection
{
    public static function make(): Forms\Components\Section
    {
        return Section::make('Allegati')
            ->collapsed(fn($record) => $record)
            ->visible(function ($record) {
                if (!$record?->id) return false;
                $files = Storage::disk(config('filesystems.default'))
                    ->files('invoices/attachments/' . $record->id);
                return !empty($files);
            })
            ->headerActions([
                Action::make('download_zip')
                    ->label('Scarica tutti (.zip)')
                    ->icon('heroicon-o-archive-box-arrow-down')
                    ->color('gray')
                    ->action(function ($record) {
                        $disk  = config('filesystems.default');
                        $files = Storage::disk($disk)->files('invoices/attachments/' . $record->id);

                        if (empty($files)) return;

                        $zipPath = sys_get_temp_dir() . '/allegati_fattura_' . $record->id . '_' . time() . '.zip';

                        $zip = new ZipArchive();
                        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

                        foreach ($files as $file) {
                            $stream   = Storage::disk($disk)->readStream($file);
                            $contents = stream_get_contents($stream);
                            fclose($stream);
                            $zip->addFromString(basename($file), $contents);
                        }

                        $zip->close();

                        return response()->download($zipPath, 'allegati_fattura_' . $record->id . '.zip')
                            ->deleteFileAfterSend(true);
                    }),
            ])
            ->schema([
                Placeholder::make('attachments')
                    ->label('')
                    ->content(function ($record) {
                        if (!$record?->id) {
                            return 'Nessun allegato trovato.';
                        }
                        $disk  = config('filesystems.default');
                        $files = Storage::disk($disk)->files('invoices/attachments/' . $record->id);
                        if (empty($files)) {
                            return 'Nessun allegato.';
                        }
                        return new HtmlString(
                            collect($files)->map(function ($file) {
                                $name = basename($file);
                                $url  = Storage::temporaryUrl($file, now()->addMinutes(15));
                                return <<<HTML
                                <div class="flex items-center gap-2 py-1">
                                    <span class="text-gray-400 text-xs">📎</span>
                                    <a href="{$url}" target="_blank" class="text-sm text-blue-600 hover:underline hover:text-blue-800 transition">
                                        {$name}
                                    </a>
                                </div>
                                HTML;
                            })->implode('')
                        );
                    })
                    ->columnSpan('full'),

        // Section::make('Allegati')
        //     ->collapsed(fn($record) => $record)
        //     ->visible(fn($record) => $record)
        //     ->headerActions([
        //         Action::make('downloadAll')
        //             ->label('Scarica tutto (.zip)')
        //             ->icon('heroicon-m-arrow-down-tray')
        //             ->color('gray')
        //             ->size('sm')
        //             ->visible(function ($record) {
        //                 if (!$record) return false;
        //                 $disk  = config('filesystems.default');
        //                 $files = array_merge(
        //                     $record->attachment_path ? Storage::disk($disk)->files($record->attachment_path) : [],
        //                     Storage::disk($disk)->files('invoices/attachments/' . $record->id),
        //                 );
        //                 return count($files) > 1;
        //             })
        //             ->url(fn ($record) => route('attachments.zip', [
        //                 'type' => $record?->getMorphClass(),
        //                 'id'   => $record?->id,
        //             ]))
        //             ->openUrlInNewTab(),
        //     ])
        //     ->schema([
        //         Placeholder::make('attachments')
        //             ->label('')
        //             ->content(function ($record) {
        //                 if (!$record) return 'Nessun allegato trovato.';

        //                 $disk  = config('filesystems.default');

        //                 // File esistenti
        //                 $files = $record->attachment_path
        //                     ? Storage::disk($disk)->files($record->attachment_path)
        //                     : [];

        //                 // File caricati tramite action addFile
        //                 $uploaded = Storage::disk($disk)->files('invoices/attachments/' . $record->id);

        //                 $all = array_merge($files, $uploaded);

        //                 if (empty($all)) return 'Nessun allegato.';

        //                 return new HtmlString(
        //                     collect($all)->map(function ($file) {
        //                         $name = basename($file);
        //                         $url  = Storage::temporaryUrl($file, now()->addMinutes(15));
        //                         return <<<HTML
        //                         <div class="flex items-center gap-2 py-1">
        //                             <span class="text-gray-400 text-xs">📎</span>
        //                             <a href="{$url}" target="_blank" class="text-sm text-blue-600 hover:underline hover:text-blue-800 transition">
        //                                 {$name}
        //                             </a>
        //                         </div>
        //                         HTML;
        //                     })->implode('')
        //                 );
        //             })
        //             ->columnSpan('full'),
            ]);
    }
}
