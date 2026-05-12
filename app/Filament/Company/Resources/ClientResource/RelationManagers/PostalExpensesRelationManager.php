<?php

namespace App\Filament\Company\Resources\ClientResource\RelationManagers;

use App\Enums\NotifyType;
use App\Enums\ReinvoiceType;
use App\Models\Invoice;
use App\Models\Supplier;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use App\Filament\Company\Resources\PostalExpenseResource\Forms\Sections\AttachmentsSection;
use App\Filament\Company\Resources\PostalExpenseResource\Forms\Sections\BaseInfoSection;
use App\Filament\Company\Resources\PostalExpenseResource\Forms\Sections\ExpenseSection;
use App\Filament\Company\Resources\PostalExpenseResource\Forms\Sections\NoteSection;
use App\Filament\Company\Resources\PostalExpenseResource\Forms\Sections\NotificationSection;
use App\Filament\Company\Resources\PostalExpenseResource\Forms\Sections\PaymentSection;
use App\Filament\Company\Resources\PostalExpenseResource\Forms\Sections\ReinvoiceSection;
use App\Filament\Company\Resources\PostalExpenseResource\Forms\Sections\ShipmentSection;

use function PHPUnit\Framework\isNull;

class PostalExpensesRelationManager extends RelationManager
{
    protected static string $relationship = 'postalExpenses';

    protected static ?string $pluralModelLabel = 'Spese postali';

    protected static ?string $modelLabel = 'Spesa postale';

    protected static ?string $title = 'Spese postali';

    public function form(Form $form): Form
    {
        // 1. Definiamo la subquery per trovare l'ultima data di dettaglio per ogni contratto
        // Lo facciamo fuori dalle closure principali per riutilizzarlo e per chiarezza
        $latestDetailSubquery = \App\Models\ContractDetail::query()
            ->selectRaw('contract_id, MAX(date) as latest_detail_date')
            ->groupBy('contract_id')
            ->toBase();

        return $form->schema([
            BaseInfoSection::make($latestDetailSubquery),
            ShipmentSection::make(),
            NotificationSection::make(),
            ExpenseSection::make(),
            PaymentSection::make(),
            ReinvoiceSection::make(),
            NoteSection::make(),
            AttachmentsSection::make(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            // ->recordTitleAttribute('order_rif')
            ->columns([
                Tables\Columns\TextColumn::make('tax_type')
                    // ->badge()
                    ->label('Entrata'),
                Tables\Columns\TextColumn::make('manage_year')
                    ->label('Anno')
                    ->searchable(),
                Tables\Columns\TextColumn::make('counterpart')
                    ->label('Controparte')
                    ->getStateUsing(function ($record) {
                        $counterpart = "";
                        if($record->supplier_id)
                            $counterpart = Supplier::find($record->supplier_id)->denomination;
                        else
                            $counterpart = $record->supplier_name;
                        return $counterpart;
                    })
                    ->limit(20),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Importo da rimborsare')
                    ->getStateUsing(function ($record) {
                        $sum = ($record->notify_amount ?? 0) +
                            ($record->notify_expense_amount ?? 0) +
                            ($record->mark_expense_amount ?? 0);
                        return $sum;
                    })
                    ->money('EUR'),
                // Tables\Columns\IconColumn::make('reinvoice')
                //     ->label('Rifatturare')
                //     ->boolean(),
                Tables\Columns\TextColumn::make('reinvoice_type')
                    // ->badge()
                    ->label('Tipo rifatturazione'),
                Tables\Columns\IconColumn::make('reinvoiced')
                    ->label('Rifatturato')
                    ->getStateUsing(function ($record) {
                        $reinvoice = Invoice::find($record->reinvoice_id);
                        return !is_null($reinvoice);
                    })
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('notify_type')->label('Tipo notifica')
                    ->options(NotifyType::class),
                Tables\Filters\SelectFilter::make('reinvoice_type')->label('Tipo rifatturazione')
                    ->options(ReinvoiceType::class),
                Tables\Filters\TernaryFilter::make('payed')->label('Pagato'),
                // Tables\Filters\TernaryFilter::make('reinvoice')->label('Rifatturazione'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->modalWidth(MaxWidth::SevenExtraLarge)
                    ->extraAttributes([
                        'style' => 'max-width: min(95vw, 1600px) !important;'
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->modalWidth(MaxWidth::SevenExtraLarge)
                    ->extraAttributes([
                        'style' => 'max-width: min(95vw, 1600px) !important;'
                    ]),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('view_act_attachment')
                        ->label('Allegato Atto')
                        ->icon('heroicon-o-document')
                        ->url(fn($record): ?string => $record->act_attachment_path ? Storage::temporaryUrl($record->act_attachment_path,now()->addMinutes(1)) : null)
                        ->openUrlInNewTab()
                        ->visible(fn($record): bool => (bool)$record->act_attachment_path),             // Nascondo se l'allegato non esiste
                    Tables\Actions\Action::make('view_notify_attachment')
                        ->label('Allegato Notifica')
                        ->icon('heroicon-o-document')
                        ->url(fn($record): ?string => $record->notify_attachment_path ? Storage::temporaryUrl($record->notify_attachment_path,now()->addMinutes(1)) : null)
                        ->openUrlInNewTab()
                        ->visible(fn($record): bool => (bool)$record->notify_attachment_path),          // Nascondo se l'allegato non esiste
                    Tables\Actions\Action::make('view_reinvoice_attachment')
                        ->label('Allegato Rifatturazione')
                        ->icon('heroicon-o-document')
                        ->url(fn($record): ?string => $record->reinvoice_attachment_path ? Storage::temporaryUrl($record->reinvoice_attachment_path,now()->addMinutes(1)) : null)
                        ->openUrlInNewTab()
                        ->visible(fn($record): bool => (bool)$record->reinvoice_attachment_path),       // Nascondo se l'allegato non esiste
                ])
                ->label('Allegati')
                ->icon('heroicon-o-paper-clip')
                ->color('gray')
                ->button(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
