<?php

namespace App\Filament\Company\Resources\PassiveInvoiceResource\RelationManagers;

use App\Enums\PiValidationStatus;
use App\Filament\Company\Resources\PassiveInvoiceResource;
use App\Models\PassiveInvoice;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class VariationNotesRelationManager extends RelationManager
{
    protected static string $relationship = 'variationNotes';

    protected static ?string $pluralModelLabel = 'Note di variazione';

    protected static ?string $modelLabel = 'Nota di variazione';

    protected static ?string $title = 'Note di variazione';

    /**
     * Una nota di variazione è essa stessa una fattura passiva, con la propria pagina e
     * il proprio documento: da qui si consulta soltanto, si modifica dove già si modifica.
     */
    public function isReadOnly(): bool
    {
        return true;
    }

    /**
     * Serve solo alla modale di visualizzazione, che ne disabilita i campi.
     */
    public function form(Form $form): Form
    {
        return $form
            ->columns(12)
            ->schema([
                Forms\Components\Placeholder::make('doc_type_description')
                    ->label('Tipo documento')
                    ->content(fn (?PassiveInvoice $record): string => $record?->docType?->description ?? '-')
                    ->columnSpan(4),

                Forms\Components\TextInput::make('number')
                    ->label('Numero')
                    ->extraInputAttributes(['class' => 'text-right'])
                    ->columnSpan(2),

                Forms\Components\DatePicker::make('invoice_date')
                    ->label('Data')
                    ->extraInputAttributes(['class' => 'text-center'])
                    ->columnSpan(3),

                Forms\Components\Placeholder::make('supplier_denomination')
                    ->label('Fornitore')
                    ->content(fn (?PassiveInvoice $record): string => $record?->supplier?->denomination ?? '-')
                    ->columnSpan(3),

                Forms\Components\Textarea::make('description')
                    ->label('Descrizione')
                    ->rows(2)
                    ->columnSpanFull(),

                Forms\Components\TextInput::make('total')
                    ->label('Dovuto')
                    ->extraInputAttributes(['class' => 'text-right'])
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2, ',', '.'))
                    ->suffix('€')
                    ->columnSpan(3),

                Forms\Components\TextInput::make('total_payment')
                    ->label('Pagato')
                    ->extraInputAttributes(['class' => 'text-right'])
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2, ',', '.'))
                    ->suffix('€')
                    ->columnSpan(3),

                Forms\Components\DatePicker::make('payment_deadline')
                    ->label('Scadenza pagamento')
                    ->extraInputAttributes(['class' => 'text-center'])
                    ->columnSpan(3),

                Forms\Components\Placeholder::make('pi_validation_name')
                    ->label('Validazione')
                    ->content(fn (?PassiveInvoice $record): string => $record?->piValidation?->name ?? 'Non validata')
                    ->columnSpan(3),

                // I pulsanti restano attivi anche qui: la modale disabilita i campi del form,
                // non le azioni, che hanno uno stato disabilitato proprio.
                Forms\Components\Actions::make([
                    Forms\Components\Actions\Action::make('view_pdf')
                        ->label('Visualizza pdf')
                        ->icon('heroicon-o-eye')
                        ->url(fn ($record): ?string => $record?->pdf_path ? Storage::temporaryUrl($record->pdf_path, now()->addMinutes(1)) : null)
                        ->openUrlInNewTab()
                        ->visible(fn ($record): bool => (bool) $record?->pdf_path)
                        ->color('primary'),
                    Forms\Components\Actions\Action::make('view_xml')
                        ->label('Visualizza xml')
                        ->icon('heroicon-o-eye')
                        ->url(fn ($record): ?string => $record?->xml_path ? Storage::temporaryUrl($record->xml_path, now()->addMinutes(1)) : null)
                        ->openUrlInNewTab()
                        ->visible(fn ($record): bool => (bool) $record?->xml_path)
                        ->color('primary'),
                ])
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('number')
            ->defaultSort('invoice_date', 'desc')
            ->emptyStateHeading('Nessuna nota di variazione collegata')
            ->columns([
                Tables\Columns\TextColumn::make('docType.description')
                    ->label('Tipo documento')
                    ->limit(30)
                    ->tooltip(fn ($record) => $record?->docType?->description)
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('number')
                    ->label('Numero')
                    ->alignRight()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('invoice_date')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('description')
                    ->label('Descrizione')
                    ->wrap()
                    ->limit(30)
                    ->tooltip(fn ($record) => $record?->description)
                    ->searchable(),
                // In una colonna di importi le note di credito si sottraggono, mentre quelle di
                // debito si sommano come le fatture: è la convenzione della colonna "Dovuto".
                Tables\Columns\TextColumn::make('total')
                    ->label('Totale')
                    ->money('EUR')
                    ->alignRight()
                    ->sortable()
                    ->summarize([
                        Tables\Columns\Summarizers\Summarizer::make()
                            ->label('')
                            ->using(fn (QueryBuilder $query): float => (float) (clone $query)
                                ->reorder()
                                ->selectRaw("COALESCE(SUM(CASE WHEN doc_type = 'TD04'
                                                              THEN -total
                                                              ELSE total END), 0) as amount")
                                ->value('amount'))
                            ->money('EUR', true, 'it_IT'),
                    ]),
                Tables\Columns\IconColumn::make('piValidation.pi_validation_status')
                    ->label('Validazione')
                    ->default(PiValidationStatus::NO_STATUS)
                    ->tooltip(fn ($record): string => $record?->piValidation ? $record->piValidation->name : 'Non validata'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->modalHeading(fn (PassiveInvoice $record): string => trim(
                        ($record->docType?->description ?? 'Nota di variazione')
                        . ' n. ' . $record->number
                        . ($record->invoice_date ? ' del ' . $record->invoice_date->format('d/m/Y') : '')
                    ))
                    ->modalWidth('5xl'),
                // Non apre una modale: la nota si modifica nella sua pagina, dove il form è completo.
                Tables\Actions\Action::make('edit')
                    ->label('Modifica')
                    ->icon('heroicon-m-pencil-square')
                    ->url(fn (PassiveInvoice $record): string => PassiveInvoiceResource::getUrl('edit', ['record' => $record]))
                    ->visible(fn (PassiveInvoice $record): bool => (bool) Auth::user()?->can('update', $record)),
            ]);
    }
}
