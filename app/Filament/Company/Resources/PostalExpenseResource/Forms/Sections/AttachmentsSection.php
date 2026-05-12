<?php

namespace App\Filament\Company\Resources\PostalExpenseResource\Forms\Sections;

use Filament\Forms;
use Filament\Forms\Components\Fieldset;
use Illuminate\Support\Facades\Storage;

class AttachmentsSection
{
    public static function make(): Forms\Components\Section
    {
        return Forms\Components\Section::make('Visualizza Allegati e Dati Lavorazione')
                    ->icon('heroicon-o-paper-clip')
                    ->collapsed()
                    // ->visible(fn($record): bool => $record && ($record->act_attachment_path || $record->notify_attachment_path || $record->reinvoice_attachment_path))
                    ->columnSpanFull()
                    ->schema([
                        Forms\Components\Actions::make([
                            self::viewActAttachmentField(),
                            self::viewNotifyAttachmentField(),
                            self::viewReinvoiceAttachmentField(),
                            self::viewContractAttachmentField(),
                            self::viewPassiveInvoicePdfField(),
                            self::viewReinvoicePdfField(),
                        ])->fullWidth(false),

                        Forms\Components\Placeholder::make('divider')
                            ->label('')
                            ->content(new \Illuminate\Support\HtmlString('<hr class="my-2 border-gray-200 dark:border-white/5">'))
                            ->visible(fn($record) => $record && $record->shipment_insert_user_id),

                        Forms\Components\Grid::make(12)
                            ->schema([
                                self::elaborationDataBaseInfoShipment(),
                                self::elaborationDataNotification(),
                                self::elaborationDataExpense(),
                                self::elaborationDataPayment(),
                                self::elaborationDataReinvoice(),
                            ])
                            ->visible(fn($record) => $record && $record->shipment_insert_user_id),
                    ]);
    }

    private static function viewActAttachmentField(): Forms\Components\Actions\Action
    {
        return Forms\Components\Actions\Action::make('view_act_attachment')
            ->label('Allegato Atto')
            ->icon('heroicon-o-eye')
            // ->url(fn($record): ?string => $record && $record->act_attachment_path ? Storage::url($record->act_attachment_path) : null)
            ->url(fn($record): ?string => $record->act_attachment_path ? Storage::temporaryUrl($record->act_attachment_path,now()->addMinutes(1)) : null)
            ->openUrlInNewTab()
            ->visible(fn($record): bool => $record && $record->act_attachment_path)
            ->color('primary');
    }

    private static function viewNotifyAttachmentField(): Forms\Components\Actions\Action
    {
        return Forms\Components\Actions\Action::make('view_notify_attachment')
            ->label('Allegato Notifica')
            ->icon('heroicon-o-eye')
            // ->url(fn($record): ?string => $record && $record->notify_attachment_path ? Storage::url($record->notify_attachment_path) : null)
            ->url(fn($record): ?string => $record->notify_attachment_path ? Storage::temporaryUrl($record->notify_attachment_path,now()->addMinutes(1)) : null)
            ->openUrlInNewTab()
            ->visible(fn($record): bool => $record && $record->notify_attachment_path)
            ->color('primary');
    }

    private static function viewReinvoiceAttachmentField(): Forms\Components\Actions\Action
    {
        return Forms\Components\Actions\Action::make('view_reinvoice_attachment')
            ->label('Allegato Rifatturazione')
            ->icon('heroicon-o-eye')
            // ->url(fn($record): ?string => $record && $record->reinvoice_attachment_path ? Storage::url($record->reinvoice_attachment_path) : null)
            ->url(fn($record): ?string => $record->reinvoice_attachment_path ? Storage::temporaryUrl($record->reinvoice_attachment_path,now()->addMinutes(1)) : null)
            ->openUrlInNewTab()
            ->visible(fn($record): bool => $record && $record->reinvoice_attachment_path)
            ->color('primary');
    }

    private static function viewContractAttachmentField(): Forms\Components\Actions\Action
    {
        return Forms\Components\Actions\Action::make('view_contract_copy')
            ->label('Contratto in vigore')
            ->icon('tabler-contract')
            // ->url(fn($record): ?string => $record && $record->new_contract_copy_path ? Storage::url($record->new_contract_copy_path) : null)
            ->url(fn($record): ?string => $record->contract?->new_contract_copy_path ? Storage::temporaryUrl($record->contract?->new_contract_copy_path,now()->addMinutes(1)) : null)
            ->openUrlInNewTab()
            ->visible(fn($record): bool => $record && $record->contract?->new_contract_copy_path)
            ->color('primary');
    }

    private static function viewPassiveInvoicePdfField(): Forms\Components\Actions\Action
    {
        return Forms\Components\Actions\Action::make('view_passive_invoice_pdf')
            ->label('Fattura Passiva')
            ->icon('phosphor-invoice-duotone')
            // ->url(fn($record): ?string => $record && $record->passive_invoice_pdf_path ? Storage::url($record->passive_invoice_pdf_path) : null)
            ->url(fn($record): ?string => $record->passiveInvoice?->pdf_path ? Storage::temporaryUrl($record->passiveInvoice?->pdf_path,now()->addMinutes(1)) : null)
            ->openUrlInNewTab()
            ->visible(fn($record): bool => $record?->passiveInvoice?->pdf_path ?? false)
            ->color('primary');
    }

    private static function viewReinvoicePdfField(): Forms\Components\Actions\Action
    {
        return Forms\Components\Actions\Action::make('view_reinvoice_pdf')
            ->label('Fattura Rifatturazione')
            ->icon('phosphor-invoice-duotone')
            // ->url(fn($record): ?string => $record && $record->passive_invoice_pdf_path ? Storage::url($record->passive_invoice_pdf_path) : null)
            ->url(fn($record): ?string => $record->reInvoice?->pdf_path ? Storage::temporaryUrl($record->reInvoice?->pdf_path,now()->addMinutes(1)) : null)
            ->openUrlInNewTab()
            ->visible(fn($record): bool => (bool) $record?->reInvoice?->pdf_path ?? false)
            ->color('primary');
    }

    private static function elaborationDataBaseInfoShipment(): Fieldset
    {
        return Fieldset::make('Creazione')
            ->columns(2)
            ->schema([
                Forms\Components\Select::make('shipment_insert_user_id')
                    ->label('')
                    ->disabled()
                    ->visible(fn($record): bool => $record && $record->shipment_insert_user_id)
                    ->relationship('shipmentInsertUser', 'name')
                    ->searchable()
                    ->preload()
                    ->optionsLimit(5)
                    ->columnSpan(1),

                Forms\Components\DatePicker::make('shipment_insert_date')
                    ->label('')
                    ->extraInputAttributes(['class' => 'text-center'])
                    ->disabled()
                    ->visible(fn($record): bool => $record && $record->shipment_insert_date)
                    ->columnSpan(1),
            ])
            ->columnSpan(4);
    }

    private static function elaborationDataNotification(): Fieldset
    {
        return Fieldset::make('Elaborazione notifica')
            ->columns(2)
            ->schema([
            Forms\Components\Select::make('notify_insert_user_id')
                ->label('')
                ->disabled()
                ->visible(fn($record): bool => $record && $record->notify_insert_user_id)
                ->relationship('notifyInsertUser', 'name')
                ->searchable()
                ->preload()
                ->optionsLimit(5),

            Forms\Components\DatePicker::make('notify_insert_date')
                ->label('')
                ->extraInputAttributes(['class' => 'text-center'])
                ->disabled()
                ->visible(fn($record): bool => $record && $record->notify_insert_date),
        ])
        ->columnSpan(4);
    }

    private static function elaborationDataExpense(): Fieldset
    {
        return Fieldset::make('Elaborazione spese')
            ->columns(2)
            ->schema([
                Forms\Components\Select::make('expense_insert_user_id')
                    ->label('')
                    ->disabled()
                    ->visible(fn($record): bool => $record && $record->expense_insert_user_id)
                    ->relationship('expenseInsertUser', 'name')
                    ->searchable()
                    ->preload()
                    ->optionsLimit(5),

                Forms\Components\DatePicker::make('expense_insert_date')
                    ->label('')
                    ->extraInputAttributes(['class' => 'text-center'])
                    ->disabled()
                    ->visible(fn($record): bool => $record && $record->expense_insert_date),
            ])
            ->columnSpan(4);
    }

    private static function elaborationDataPayment(): Fieldset
    {
        return Fieldset::make('Elaborazione pagamento')
            ->columns(2)
            ->schema([
                Forms\Components\Select::make('payment_insert_user_id')
                    ->label('')
                    ->disabled()
                    ->visible(fn($record): bool => $record && $record->payment_insert_user_id)
                    ->relationship('paymentInsertUser', 'name')
                    ->searchable()
                    ->preload()
                    ->optionsLimit(5),

                Forms\Components\DatePicker::make('payment_insert_date')
                    ->label('')
                    ->extraInputAttributes(['class' => 'text-center'])
                    ->disabled()
                    ->visible(fn($record): bool => $record && $record->payment_insert_date),
            ])
            ->columnSpan(4);
    }

    private static function elaborationDataReinvoice(): Fieldset
    {
        return Fieldset::make('Elaborazione rifatturazione')
            ->columns(2)
            ->schema([
                Forms\Components\Select::make('reinvoice_insert_user_id')
                    ->label('')
                    ->disabled()
                    ->visible(fn($record): bool => $record && $record->reinvoice_insert_user_id)
                    ->relationship('reinvoiceInsertUser', 'name')
                    ->searchable()
                    ->preload()
                    ->optionsLimit(5),

                Forms\Components\DatePicker::make('reinvoice_insert_date')
                    ->label('')
                    ->extraInputAttributes(['class' => 'text-center'])
                    ->disabled()
                    ->visible(fn($record): bool => $record && $record->reinvoice_insert_date),
            ])
            ->columnSpan(4);
    }
}
