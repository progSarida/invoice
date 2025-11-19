<?php

namespace App\Filament\Company\Resources\AttachmentResource\Pages;

use App\Filament\Company\Resources\AttachmentResource;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Facades\Blade;

class ListAttachments extends ListRecords
{
    protected static string $resource = AttachmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Actions\CreateAction::make(),
            Actions\Action::make('print')
                ->icon('heroicon-o-printer')
                ->label('Stampa')
                ->tooltip('Stampa elenco clienti')
                // ->iconButton()                                                                                       // mostro solo icona
                ->color('primary')
                // ->keyBindings(['alt+s'])
                ->action(function ($livewire) {
                    $records = $livewire->getFilteredTableQuery()->get();                                               // recupero risultato della query
                    $filters = $livewire->tableFilters ?? [];                                                           // recupero i filtri
                    $search = $livewire->tableSearch ?? null;
                    // recupero la ricerca

                    return response()
                        ->streamDownload(function () use ($records, $search, $filters) {
                            echo Pdf::loadHTML(
                                Blade::render('pdf.attachments', [
                                    'attachments' => $records,
                                    'search' => $search,
                                    'filters' => $filters,
                                ])
                            )
                                ->setPaper('A4', 'landscape')
                                ->stream();
                        }, 'Allegati.pdf');

                    Notification::make()
                        ->title('Stampa avviata')
                        ->success()
                        ->send();
                }),
        ];
    }

    public function getMaxContentWidth(): MaxWidth|string|null                                  // allarga la tabella a tutta pagina
    {
        return MaxWidth::Full;
    }
}
