<?php

namespace App\Filament\Company\Resources;

use App\Enums\InvoiceType;

use App\Enums\AccrualType;
use App\Enums\TaxType;
use App\Enums\SdiStatus;
use App\Enums\TenderPaymentType;

use App\Filament\Company\Resources\InvoiceResource\Pages;
use App\Filament\Company\Resources\TenderResource;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Tender;
use Closure;
use Filament\Facades\Filament;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Filament\Notifications\Notification;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    public static ?string $pluralModelLabel = 'Fatture';

    public static ?string $modelLabel = 'Fattura';

    protected static ?string $navigationIcon = 'phosphor-invoice-duotone';

    protected static ?string $navigationGroup = 'Fatturazione';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([

                Section::make('')
                    ->columns(4)
                    ->columnSpan(3)
                    ->schema([

                    Forms\Components\Select::make('invoice_type')->label('Tipo')
                        ->required()
                        ->options(InvoiceType::class)->columnSpan(2),

                    Forms\Components\TextInput::make('invoice_uid')->label('Identificativo')
                        ->disabled()->columnSpan(2),

                    Forms\Components\TextInput::make('number')->label('Numero')
                        ->afterStateUpdated( fn(Get $get, Set $set) => InvoiceResource::invoiceNumber($get, $set) )
                        ->live()
                        ->required()
                        ->numeric(),

                    Forms\Components\TextInput::make('section')->label('Sezionario')
                        ->columnSpan(2)
                        ->afterStateUpdated( fn(Get $get, Set $set) => InvoiceResource::invoiceNumber($get, $set) )
                        ->live()
                        ->required()
                        ->numeric(),

                    Forms\Components\TextInput::make('year')->label('Anno')
                        ->afterStateUpdated( fn(Get $get, Set $set) => InvoiceResource::invoiceNumber($get, $set) )
                        ->live()
                        ->required()
                        ->numeric(),

                    Forms\Components\DatePicker::make('invoice_date')->label('Data')
                        ->columnSpan(2)
                        ->required(),

                    Forms\Components\TextInput::make('budget_year')->label('Anno di bilancio')
                        ->numeric()
                        ->required()->columnSpan(2),

                    Forms\Components\TextInput::make('accrual_year')->label('Anno di competenza')
                        ->numeric()
                        ->required()->columnSpan(2),

                    Forms\Components\Select::make('accrual_type')->label('Tipo di competenza')
                        ->required()
                        ->options(AccrualType::class)->columnSpan(2),
                ]),

                Section::make('Destinatario')
                ->collapsible()
                ->columnSpan(2)
                ->schema([

                    Forms\Components\Select::make('client_id')->label('Cliente')
                        ->hintAction(
                            Action::make('Nuovo')
                                ->icon('govicon-user-suit')
                                ->form( fn(Form $form) => ClientResource::modalForm($form) )
                                ->modalHeading('')
                                ->action( fn(array $data, Client $client) => InvoiceResource::saveClient($data, $client) )
                        )
                        ->relationship(name: 'client', titleAttribute: 'denomination')
                        ->getOptionLabelFromRecordUsing(
                            fn (Model $record) => strtoupper("{$record->type->getLabel()}")." - $record->denomination"
                        )
                        ->required()
                        ->afterStateUpdated(function (Get $get, Set $set) {
                            if(empty($get('client_id')) || empty($get('tax_type')))
                            $set('tender_id', null);
                        })
                        ->searchable('denomination')
                        ->live()
                        //->preload()
                        ->columns(1),

                    Forms\Components\Select::make('tax_type')->label('Entrata')
                        ->required()
                        ->options(TaxType::class)
                        ->afterStateUpdated(function (Get $get, Set $set) {
                            if(empty($get('client_id')) || empty($get('tax_type')))
                            $set('tender_id', null);
                        })
                        ->searchable()
                        ->live()
                        //->preload()
                        ->visible(
                            function(Get $get){
                                if(filled ( $get('client_id') )){
                                    if(Client::find($get('client_id'))->type->isCompany())
                                        return false;
                                    else
                                        return true;
                                }
                                else
                                    return false;

                            }
                        ),

                    Forms\Components\Select::make('tender_id')->label('Appalto')
                        ->relationship(
                            name: 'tender',
                            modifyQueryUsing: fn (Builder $query, Get $get) => $query->where('client_id',$get('client_id'))->where('tax_type',$get('tax_type'))
                        )
                        ->getOptionLabelFromRecordUsing(
                            fn (Model $record) => "{$record->office_name} ({$record->office_code})\nTIPO: {$record->type->getLabel()} - CIG: {$record->cig_code}"
                        )
                        ->disabled(fn(Get $get): bool => ! filled($get('client_id')) || ! filled($get('tax_type')))
                        ->required()
                        ->searchable()
                        //->preload()
                        ->visible(
                            function(Get $get){
                                if(filled ( $get('client_id') )){
                                    if(Client::find($get('client_id'))->type->isCompany())
                                        return false;
                                    else
                                        return true;
                                }
                                else
                                    return false;

                            }
                        )

                        ->hintAction(
                            Action::make('Nuovo')
                                ->icon('healthicons-f-construction-worker')
                                ->fillForm(fn (Get $get): array => [
                                    'client_id' => $get('client_id'),
                                    'tax_type' => $get('tax_type'),
                                ])
                                ->form( fn(Form $form) => TenderResource::modalForm($form) )
                                ->modalHeading('')
                                ->action( fn(array $data, Tender $tender) => InvoiceResource::saveTender($data, $tender) )
                        ),

                ]),//FINE SEZIONE DESTINATARIO

                Section::make('Fattura da stornare')
                ->collapsible()
                ->columnSpan(5)
                ->schema([
                    Forms\Components\Select::make('parent_id')->label('Fattura')
                        ->relationship(
                            name: 'invoice',
                            modifyQueryUsing: 
                                fn (Builder $query, Get $get) 
                                => 
                                $query->where('invoice_type',InvoiceType::INVOICE)->with('tender')
                                ->orderBy('year','desc')
                                ->orderBy('section','desc')
                                ->orderBy('number','desc')
                        )
                        ->getOptionLabelFromRecordUsing(
                            fn (Model $record) => "Fattura n. {$record->getInvoiceNumber()} - Entrata: {$record->tax_type->getLabel()}
                            Destinatario: {$record->client->denomination}"
                        )
                        //->preload()
                        ->searchable()
                ]),

                Section::make('Descrizioni')
                ->collapsible()
                ->columnSpan(5)
                ->schema([
                    Forms\Components\Textarea::make('description')->label('Descrizione')
                        ->required()
                        ->columnSpanFull(),
                    Forms\Components\Textarea::make('free_description')->label('Descrizione libera')
                        ->columnSpanFull(),
                ]),



                
                // Forms\Components\TextInput::make('check_validation')
                //     ->maxLength(255),


                
                // Forms\Components\TextInput::make('vat_percentage')
                //     ->required()
                //     ->numeric(),
                // Forms\Components\TextInput::make('vat')
                //     ->required()
                //     ->numeric(),
                // Forms\Components\Toggle::make('is_total_with_vat')
                //     ->required(),
                // Forms\Components\TextInput::make('importo')
                //     ->numeric(),
                // Forms\Components\TextInput::make('spese')
                //     ->numeric(),
                // Forms\Components\TextInput::make('rimborsi')
                //     ->numeric(),
                // Forms\Components\TextInput::make('ordinario')
                //     ->numeric(),
                // Forms\Components\TextInput::make('temporaneo')
                //     ->numeric(),
                // Forms\Components\TextInput::make('affissioni')
                //     ->numeric(),
                // Forms\Components\TextInput::make('bollo')
                //     ->numeric(),
                // Forms\Components\TextInput::make('total')
                //     ->required()
                //     ->numeric(),
                // Forms\Components\TextInput::make('no_vat_total')
                //     ->numeric(),
                // Forms\Components\TextInput::make('bank_account_id')
                //     ->numeric(),
                // Forms\Components\TextInput::make('payment_status')
                //     ->required()
                //     ->maxLength(255)
                //     ->default('waiting'),
                // Forms\Components\TextInput::make('payment_type')
                //     ->maxLength(255),
                // Forms\Components\TextInput::make('payment_days')
                //     ->required()
                //     ->numeric()
                //     ->default(0),
                // Forms\Components\TextInput::make('total_payment')
                //     ->numeric(),
                // Forms\Components\DatePicker::make('last_payment_date'),
                // Forms\Components\TextInput::make('sdi_code')
                //     ->maxLength(255),
                // Forms\Components\TextInput::make('sdi_status')
                //     ->required()
                //     ->maxLength(255)
                //     ->default('da_inviare'),
                // Forms\Components\DatePicker::make('sdi_date'),
                // Forms\Components\TextInput::make('pdf_path')
                //     ->maxLength(255),
                // Forms\Components\TextInput::make('xml_path')
                //     ->maxLength(255),
            ])->columns(5);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('invoice_type')->label('Tipo')
                    ->searchable()->badge()->sortable(),
                Tables\Columns\TextColumn::make('number')->label('Numero')
                    ->formatStateUsing(function ( Invoice $invoice) {
                        return $invoice->getInvoiceNumber();
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query
                            ->orderBy('year', $direction)
                            ->orderBy('section', $direction)
                            ->orderBy('number', $direction);
                    }),
                Tables\Columns\TextColumn::make('invoice_date')->label('Data')
                    ->date()
                    ->sortable(),
                    Tables\Columns\TextColumn::make('client.denomination')->label('Cliente')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('tender.cig_code')->label('CIG')
                    ->numeric()
                    ->sortable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('tender.cup_code')->label('CUP')
                    ->numeric()
                    ->sortable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('tender.rdo_code')->label('RDO')
                    ->numeric()
                    ->sortable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('tax_type')->label('Entrata')
                    ->searchable()->badge()->sortable(),
                Tables\Columns\TextColumn::make('total')->label('Totale a doversi')
                    ->formatStateUsing(function ( Invoice $invoice) {
                        if($invoice->is_total_with_vat)
                            return $invoice->total;
                        else
                            return $invoice->no_vat_total;
                    })
                    ->money('EUR')
                    ->sortable()
                    ->alignRight(),
                Tables\Columns\TextColumn::make('sdi_status')->label('Status')
                    ->searchable()->badge()->sortable(),
                Tables\Columns\TextColumn::make('sdi_date')->label('Data status')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
                SelectFilter::make('invoice_type')->label('Tipo')->options(InvoiceType::class)
                    ->multiple()->searchable()->preload(),
                SelectFilter::make('client_id')->label('Cliente')
                    ->relationship(name: 'client', titleAttribute: 'denomination')
                    ->getOptionLabelFromRecordUsing(
                        fn (Model $record) => strtoupper("{$record->type->getLabel()}")." - $record->denomination"
                    )
                    ->searchable()->preload(),
                SelectFilter::make('tax_type')->label('Entrata')->options(TaxType::class)
                    ->multiple()->searchable()->preload(),
                SelectFilter::make('tender_id')->label('Appalto')
                    ->relationship('tender','office_name')
                    ->getOptionLabelFromRecordUsing(
                        fn (Model $record) => "{$record->office_name} ({$record->office_code})\nTIPO: {$record->type->getLabel()} - CIG: {$record->cig_code}"
                    )
                    ->searchable()->preload(),
                SelectFilter::make('sdi_status')->label('Status')->options(SdiStatus::class)
                    ->multiple()->searchable()->preload(),
                

            ],layout: FiltersLayout::AboveContentCollapsible)->filtersFormColumns(4)
            ->actions([
                Tables\Actions\EditAction::make(),
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
            'index' => Pages\ListInvoices::route('/'),
            'create' => Pages\CreateInvoice::route('/create'),
            'edit' => Pages\EditInvoice::route('/{record}/edit'),
        ];
    }

    public static function saveTender(array $data, Tender $tender): void
    {
        $tender->company_id = Filament::getTenant()->id;
        $tender->client_id = $data['client_id'];
        $tender->tax_type = $data['tax_type'];
        $tender->type = $data['type'];
        $tender->office_name = $data['office_name'];
        $tender->office_code = $data['office_code'];
        $tender->date = $data['date'];
        $tender->cig_code = $data['cig_code'];
        $tender->cup_code = $data['cup_code'];
        $tender->rdo_code = $data['rdo_code'];
        $tender->reference_code = $data['reference_code'];
        $tender->save();
        Notification::make()
            ->title('Appalto salvato con successo')
            ->success()
            ->send();
    }

    public static function saveClient(array $data, Client $client): void
    {
        $client->company_id = Filament::getTenant()->id;
        $client->type = $data['type'];
        $client->denomination = $data['denomination'];
        $client->address = $data['address'];
        $client->city_id = $data['city_id'];
        $client->zip_code = $data['zip_code'];
        $client->tax_code = $data['tax_code'];
        $client->vat_code = $data['vat_code'];
        $client->email = $data['email'];
        $client->ipa_code = $data['ipa_code'];
        $client->save();
        Notification::make()
            ->title('Cliente salvato con successo')
            ->success()
            ->send();
    }

    public static function invoiceNumber(Get $get, Set $set){

        if(empty($get('number')) || empty($get('section')) || empty($get('year')))
            $set('invoice_uid', null);
        else{
            $number = "";
            for($i=strlen($get('number'));$i<3;$i++)
            {
                $number.= "0";
            }
            $number = $number.$get('number');
            $set('invoice_uid', $number."/0".$get('section')."/".$get('year'));
        }

    }
}
