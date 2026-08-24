<?php

namespace App\Filament\Company\Resources\PassiveInvoiceResource\Forms\Sections;

use App\Models\DocType;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class DocumentSection
{
    public static function make(): Forms\Components\Section
    {
        return Section::make('')
        ->columns(12)
        ->schema([
            Forms\Components\Select::make('doc_type')
                ->label('Tipo documento')
                ->required()
                ->columnSpan(7)
                ->options(function (Get $get) {
                    $docs = DocType::select('doc_types.name', 'doc_types.description')
                        ->get();
                    return $docs->pluck('description', 'name')->toArray();
                })
                //  ->disabled()
                ,
            Forms\Components\TextInput::make('number')
                ->label('Numero')
                ->required()
                ->extraInputAttributes(['class' => 'text-right'])
                ->columnSpan(3)
                //  ->disabled()
                ,
            Forms\Components\DatePicker::make('invoice_date')
                ->label('Data')
                ->required()
                ->extraInputAttributes(['class' => 'text-center'])
                ->columnSpan(2)
                ->live(onBlur: true)
                ->afterStateUpdated(function ($state, Set $set) {
                    if (!$state) {
                        return;
                    }

                    $currentMonth = now()->month;
                    $date = \Carbon\Carbon::parse($state);
                    $selectedYear = \Carbon\Carbon::parse($state)->year;
                    $currentYear = now()->year;

                    if ($currentMonth !== 1 && $date->year !== $currentYear) {
                        $corrected = $date->copy()->setYear($currentYear);
                        $set('invoice_date', $corrected->format('Y-m-d'));

                        Notification::make()
                            ->title('Anno corretto automaticamente')
                            ->body("Hai inserito una data del {$selectedYear}, ma l'anno corrente è il {$currentYear}.")
                            ->warning()
                            ->send();
                    }
                })
                // ->rules([
                //     fn (): \Closure => function (string $attribute, $value, \Closure $fail) {
                //         if (!$value) {
                //             return;
                //         }

                //         $currentMonth = now()->month;
                //         $selectedYear = \Carbon\Carbon::parse($value)->year;
                //         $currentYear = now()->year;

                //         if ($currentMonth !== 1 && $selectedYear !== $currentYear) {
                //             $fail("La data deve essere dell'anno corrente ({$currentYear}).");
                //         }
                //     },
                // ])
                // ->disabled()
                ,

            Forms\Components\Select::make('parent_id')
                ->label('Fattura stornata')
                ->visible(
                    function (Get $get, $record) {
                        $doc_type = $get('doc_type');
// dd($record, $docTypeId);
                        if (!filled($doc_type)) {
                            return false;
                        }

                        $docType = DocType::with('docGroup')->where('name', $doc_type)->first();

                        return $docType?->docGroup?->name === 'Note di variazione' || $record?->parent_id;
                        // return true;
                    }
                )
                ->live()
                ->relationship(
                    name: 'parent',
                    modifyQueryUsing:
                        function (Builder $query, Get $get){
                            $query->whereHas('docType.docGroup', function ($query) {
                                    $query->whereIn('name', ['Fatture', 'Autofatture']);
                                })
                                ->where('supplier_id',$get('supplier_id'))
                                ->orderBy('number','desc');
                        }
                )
                ->getOptionLabelFromRecordUsing(
                    function (Model $record) {
                        $return = "Fattura n. {$record?->number} del {$record?->invoice_date->format('d/m/Y')} ";
                        return $return;
                    }
                )
                ->columnSpan(12),

            Forms\Components\FileUpload::make('pdf_path')->label('File PDF')
                ->required(fn ($record) => !$record?->pdf_path)
                ->dehydrated(fn ($record) => !$record?->pdf_path)
                // ->disk('public')
                ->directory('passive_invoices/pdf_files')
                // ->visibility('public')
                ->acceptedFileTypes(['application/pdf'])
                ->maxSize(10240)
                ->getUploadedFileNameForStorageUsing(function (UploadedFile $file, Get $get) {
                    $supplierId = $get('supplier_id') ?? 'unknown';
                    $number = $get('number') ?? 'unknown';
                    $invoiceDate = $get('invoice_date') ?? 'unknown';
                    $extension = $file->getClientOriginalExtension();
                    return sprintf('PDF_FAT_PASS_%s_%s_%s.%s', $supplierId, $number, $invoiceDate, $extension);
                })
                ->columnSpan(4),
            Forms\Components\Actions::make([
                Forms\Components\Actions\Action::make('view_pdf')
                    ->label('Visualizza pdf')
                    ->icon('heroicon-o-eye')
                    // ->url(fn($record): ?string => $record && $record->pdf_path ? Storage::url($record->pdf_path) : null)
                    ->url(fn($record): ?string => $record?->pdf_path ? Storage::temporaryUrl($record?->pdf_path,now()->addMinutes(1)) : null)
                    ->openUrlInNewTab()
                    ->visible(fn($record): bool => $record && $record?->pdf_path)
                    ->color('primary'),
            ])
            ->columnSpan(2),

            Forms\Components\FileUpload::make('xml_path')->label('File XML')
                // ->required(fn ($record) => !$record?->xml_path)
                ->dehydrated(fn ($record) => !$record?->xml_path)
                // ->disk('public')
                ->directory('passive_invoices/xml_files')
                // ->visibility('public')
                ->acceptedFileTypes([
                    'application/xml',
                    'text/xml',
                    'application/x-xml'
                ])
                ->maxSize(10240)
                ->getUploadedFileNameForStorageUsing(function (UploadedFile $file, Get $get) {
                    $supplierId = $get('supplier_id') ?? 'unknown';
                    $number = $get('number') ?? 'unknown';
                    $invoiceDate = $get('invoice_date') ?? 'unknown';
                    $extension = $file->getClientOriginalExtension();
                    return sprintf('XML_FAT_PASS_%s_%s_%s.%s', $supplierId, $number, $invoiceDate, $extension);
                })
                ->columnSpan(4),
            Forms\Components\Actions::make([
                Forms\Components\Actions\Action::make('view_xml')
                    ->label('Visualizza xml')
                    ->icon('heroicon-o-eye')
                    // ->url(fn($record): ?string => $record && $record->xml_path ? Storage::url($record->xml_path) : null)
                    ->url(fn($record): ?string => $record?->xml_path ? Storage::temporaryUrl($record?->xml_path,now()->addMinutes(1)) : null)
                    ->openUrlInNewTab()
                    ->visible(fn($record): bool => $record && $record?->xml_path)
                    ->color('primary'),
            ])
            ->columnSpan(2),
        ]);
    }
}
