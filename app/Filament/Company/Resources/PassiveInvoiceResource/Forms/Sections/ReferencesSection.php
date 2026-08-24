<?php

namespace App\Filament\Company\Resources\PassiveInvoiceResource\Forms\Sections;

use App\Filament\Company\Resources\PassiveInvoiceResource;
use App\Filament\Company\Resources\SupplierResource;
use App\Models\Supplier;
use Filament\Forms;
use Filament\Forms\Components\Actions\Action as ActionsAction;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Database\Eloquent\Model;

class ReferencesSection
{
    public static function make(): Forms\Components\Section
    {
        return Section::make('Riferimenti')
            ->collapsible(false)
            ->columns(6)
            ->schema([

                Forms\Components\Select::make('supplier_id')->label('Fornitore')
                    ->hintAction(
                        ActionsAction::make('Nuovo')
                            ->icon('ri-user-2-line')
                            ->form(fn(Form $form) => SupplierResource::modalForm($form))
                            ->modalWidth('7xl')
                            ->modalHeading('')
                            ->action(fn (array $data, Supplier $supplier, Set $set) => PassiveInvoiceResource::saveSupplier($data, $supplier, $set))
                            ->hidden(fn ($livewire) => $livewire instanceof \App\Filament\Company\Resources\PassiveInvoiceResource\Pages\EditPassiveInvoice)
                    )
                    ->required()
                    ->columnSpan(3)
                    ->relationship('supplier', 'denomination')
                    //  ->disabled()
                    ,

                Forms\Components\Select::make('parent_id')
                    ->label('Fattura')
                    ->columnSpan(3)
                    ->relationship('parent', 'denomination')
                    ->getOptionLabelFromRecordUsing(
                        fn (Model $record) => $record?->number
                    )
                    ->visible(fn (Get $get) => !is_null($get('parent_id')))
                    //  ->disabled()
                    ,
            ]);
    }
}
