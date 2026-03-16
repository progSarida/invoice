<?php

namespace App\Filament\Company\Resources\NewContractResource\RelationManagers;

use App\Enums\ContractType;
use App\Enums\TaxType;
use App\Models\Client;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ContractDetailsRelationManager extends RelationManager
{
    protected static string $relationship = 'contractDetails';

    protected static ?string $title = 'Dettagli/storico contratto';

    public function form(Form $form): Form
    {
        return $form
            ->columns(6)
            ->schema([
                Forms\Components\Select::make('contract_type')->label('Tipo atto')
                    ->required()
                    ->live(onBlur: true)
                    ->options(ContractType::class)
                    ->searchable()
                    ->preload()
                    ->afterStateUpdated(fn (Get $get, Set $set) => static::updateInvoiceDescription($get, $set))
                    ->columnSpan(2),
                TextInput::make('number')->label('Numero atto')
                    ->required()
                    ->live(onBlur: true)
                    ->extraInputAttributes(['class' => 'text-right'])
                    ->afterStateUpdated(fn (Get $get, Set $set) => static::updateInvoiceDescription($get, $set))
                    ->columnSpan(2),
                DatePicker::make('date')->label('Data atto')
                    ->required()
                    ->live(onBlur: true)
                    ->extraInputAttributes(['class' => 'text-center'])
                    ->afterStateUpdated(fn (Get $get, Set $set) => static::updateInvoiceDescription($get, $set))
                    ->columnSpan(2),
                TextInput::make('description')->label('Descrizione')
                    ->required()
                    ->live(onBlur: true)
                    ->maxLength(255)
                    ->afterStateUpdated(fn (Get $get, Set $set) => static::updateInvoiceDescription($get, $set))
                    ->columnSpan(6),
                TextInput::make('invoice_description')->label('Descrizione da riportare in fattura')
                    ->required()
                    ->maxLength(255)
                    ->columnSpan(6),
                Forms\Components\FileUpload::make('contract_attachment_path')
                    ->label('Atto')
                    ->live()
                    // ->disk('public')
                    ->directory('new_contracts')
                    // ->visibility('public')
                    ->acceptedFileTypes(['application/pdf', 'image/*'])
                    ->afterStateUpdated(function (Set $set, $state) {
                        if (!empty($state)) {
                            $set('contract_attachment_date', now()->toDateString());
                        } else {
                            $set('contract_attachment_date', null);
                        }
                    })
                    ->getUploadedFileNameForStorageUsing(function (UploadedFile $file, Get $get) {
                        $contract = $this->getOwnerRecord();
                        $rawTaxTypes = $contract->getRawOriginal('tax_types');
                        // Decode JSON string to array
                        $taxTypesArray = is_string($rawTaxTypes) ? json_decode($rawTaxTypes, true) : ($rawTaxTypes ?? []);
                        $client = Client::find($contract->client_id)->denomination ?? 'unknown';
                        // Generate tax_types string using labels
                        $taxTypes = !empty($taxTypesArray)
                            ? implode('_', array_map(function ($val) {
                                try {
                                    return TaxType::from($val)->getLabel();
                                } catch (\ValueError $e) {
                                    return 'invalid';
                                }
                            }, $taxTypesArray))
                            : 'unknown';
                        $cig = $contract->cig_code ?? 'unknown';
                        $number = $get('number') ?? 'unknown';
                        $date = $get('date') ?? 'unknown';
                        $extension = $file->getClientOriginalExtension();
                        return sprintf('%s_CONTRATTO_%s_%s_%s_%s.%s', $client, $taxTypes, $cig, $number, $date, $extension);
                    })
                    ->columnSpan(6),
                DatePicker::make('contract_attachment_date')
                    ->extraInputAttributes(['class' => 'text-center'])
                    ->readonly()
                    ->dehydrated()
                    ->label('Data caricamento')
                    ->date()
                    ->visible(fn(Get $get, $record): bool => $record && $record->new_contract_copy_path || $get('contract_attachment_path'))
                    ->columnSpan(2),
                Placeholder::make('')
                    ->content('')
                    ->columnSpan(2),
                Forms\Components\Actions::make([
                    Forms\Components\Actions\Action::make('view_contract_copy')
                        ->label('Visualizza atto')
                        ->icon('heroicon-o-eye')
                        // ->url(fn($record): ?string => $record && $record->contract_attachment_path ? Storage::url($record->contract_attachment_path) : null)
                        ->url(fn($record): ?string => $record->contract_attachment_path ? Storage::temporaryUrl($record->contract_attachment_path,now()->addMinutes(1)) : null)
                        ->openUrlInNewTab()
                        ->visible(fn($record): bool => $record && $record->contract_attachment_path)
                        ->color('primary'),
                ])
                ->columnSpan(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->columns([
                Tables\Columns\TextColumn::make('contract_type')
                    ->label('Tipo')
                    ->searchable()
                    // ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('number')
                    ->label('Numero'),
                Tables\Columns\TextColumn::make('date')
                    ->label('Data')
                    ->date('d/m/Y'),
                Tables\Columns\TextColumn::make('description')
                    ->label('Descrizione')
                    ->limit(60)
                    ->tooltip(fn ($record) => $record->description),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    // ->icon('heroicon-o-plus-circle')
                    ->modalHeading('Crea aggiornamento contratto')
                    ->createAnother(false),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->modalHeading('Visualizza aggiornamento contratto'),
                Tables\Actions\EditAction::make()
                    ->modalHeading('Modifica aggiornamento contratto'),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('date', 'desc');
    }

    protected static function updateInvoiceDescription(Get $get, Set $set): void
    {
        $contractType = $get('contract_type')
                        ? ContractType::tryFrom($get('contract_type'))?->getLabel()
                        : '';
        // $contractType = $contractType ? ContractType::find($contractType)?->name : '';
        // $manageType = $get('manage_type_id') ?? null;
        // $manageType = $manageType ? ManageType::find($manageType)?->name : '';
        // $taxType = $get('tax_type') ?? null;
        // $taxType = $taxType ? TaxType::from($taxType)->getLabel() : '';
        // $year = substr($get('budget_year'), 2);

        if($get('contract_type')){
            $description = $contractType;

            if($get('number')){
                $contractNumber = $get('number') ? $get('number') : '';

                $description .= ' n.ro ' . $contractNumber;

                if($get('date')){
                    $contractDate = $get('date') ? \Illuminate\Support\Carbon::parse($get('date'))->format('d/m/Y') : '';

                    $description .= ' del ' . $contractDate;

                    if($get('description')){
                        $contractDescription = $get('description') ? $get('description') : '';


                        // $description .= ' relativo/a a ' . strtolower($contractDescription);
                        $description .= ' relativo/a a ' . lcfirst($contractDescription);
                    }
                }
            }
        }

        // $description .= $accrualType . ' ';
        // $description .= 'Corrispettivo per ' . strtolower($accrualType) . ' ';

        // $description .= strtolower($manageType) . ' ';

        $contractDescription = $get('invoice_reference');

        $set('invoice_description', trim($description));
    }
}
