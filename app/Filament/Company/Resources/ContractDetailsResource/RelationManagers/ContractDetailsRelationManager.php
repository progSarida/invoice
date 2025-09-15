<?php

namespace App\Filament\Company\Resources\ContractDetailsResource\RelationManagers;

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
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Http\UploadedFile;

class ContractDetailsRelationManager extends RelationManager
{
    protected static string $relationship = 'contractDetails';

    protected static ?string $title = 'Storico contratto';

    public function form(Form $form): Form
    {
        return $form
            ->columns(6)
            ->schema([
                TextInput::make('number')
                    ->label('Numero contratto')
                    ->required()
                    ->columnSpan(2),
                Forms\Components\Select::make('contract_type')
                    ->label('Tipo contratto')
                    ->options(ContractType::class)
                    ->required()
                    ->searchable()
                    ->preload()
                    ->columnSpan(2),
                DatePicker::make('date')
                    ->label('Data contratto')
                    ->required()
                    ->columnSpan(2),
                TextInput::make('description')
                    ->label('Descrizione')
                    ->required()
                    ->maxLength(255)
                    ->columnSpan(6),
                Forms\Components\FileUpload::make('contract_attachment_path')
                    ->label('Contratto')
                    ->live()
                    ->disk('public')
                    ->directory('new_contracts')
                    ->visibility('public')
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
                    ->columnSpan(4),
                DatePicker::make('contract_attachment_date')
                    ->readonly()
                    ->dehydrated()
                    ->label('Data caricamento')
                    ->date()
                    ->visible(fn(Get $get, $record): bool => $record && $record->new_contract_copy_path || $get('contract_attachment_path'))
                    ->columnSpan(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->columns([
                Tables\Columns\TextColumn::make('number')
                    ->label('Numero'),
                Tables\Columns\TextColumn::make('contract_type')
                    ->label('Tipo')
                    ->searchable()
                    // ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('date')
                    ->label('Data')
                    ->date('d/m/Y'),
                Tables\Columns\TextColumn::make('description')
                    ->label('Descrizione'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->icon('heroicon-o-plus-circle'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('date', 'desc');
    }
}
