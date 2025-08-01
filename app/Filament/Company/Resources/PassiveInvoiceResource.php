<?php

namespace App\Filament\Company\Resources;

use App\Filament\Company\Resources\PassiveInvoiceResource\Pages;
use App\Filament\Company\Resources\PassiveInvoiceResource\RelationManagers;
use App\Models\PassiveInvoice;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Tables\Actions\Action;
use Illuminate\Support\Facades\Storage;

class PassiveInvoiceResource extends Resource
{
    protected static ?string $model = PassiveInvoice::class;

    public static ?string $pluralModelLabel = 'Fatture passive';

    public static ?string $modelLabel = 'Fattura passiva';

    protected static ?string $navigationIcon = 'phosphor-invoice-duotone';

    protected static ?string $navigationGroup = 'Fatturazione passiva';

    public static function getNavigationSort(): ?int
    {
        return 2;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('docType.description')
                    ->label('Tipo documento')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('number')
                    ->label('Numero')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('invoice_date')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('supplier.denomination')
                    ->label('Fornitore')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('description')
                    ->label('Descrizione')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('total')
                    ->label('Totale a doversi')
                    ->money('EUR')
                    ->sortable()
                    ->alignRight(),
                TextColumn::make('payment_deadline')
                    ->label('Scadenza')
                    ->date('d/m/Y')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Action::make('download_pdf')
                    ->label('')
                    ->tooltip('Scarica PDF')
                    ->icon('hugeicons-pdf-01')
                    ->action(function ($record) {
                        $pdfPath = $record->pdf_path;
                        if ($pdfPath && Storage::disk('public')->exists($pdfPath)) {
                            return response()->download(
                                Storage::disk('public')->path($pdfPath),
                                'document_' . $record->number . '.pdf'
                            );
                        }
                        return redirect()->back()->with('error', 'File PDF non trovato.');
                    })
                    ->visible(function ($record) {
                        $visible = !empty($record->pdf_path) && Storage::disk('public')->exists($record->pdf_path);
                        return $visible;
                    }),
                Action::make('download_xml')
                    ->label('')
                    ->tooltip('Scarica XML')
                    ->icon('hugeicons-xml-01')
                    ->action(function ($record) {
                        $xmlPath = $record->xml_path;
                        if ($xmlPath && Storage::disk('public')->exists($xmlPath)) {
                            return response()->download(
                                Storage::disk('public')->path($xmlPath),
                                'document_' . $record->number . '.xml'
                            );
                        }
                        return redirect()->back()->with('error', 'File XML non trovato.');
                    })
                    ->visible(function ($record) {
                        $visible = !empty($record->xml_path) && Storage::disk('public')->exists($record->xml_path);
                        return $visible;
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPassiveInvoices::route('/'),
            'create' => Pages\CreatePassiveInvoice::route('/create'),
            'edit' => Pages\EditPassiveInvoice::route('/{record}/edit'),
        ];
    }
}
