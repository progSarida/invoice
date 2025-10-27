<?php

namespace App\Filament\Company\Resources;

use App\Enums\AttachmentType;
use App\Filament\Company\Resources\AttachmentResource\Pages;
use App\Filament\Company\Resources\AttachmentResource\RelationManagers;
use App\Models\Attachment;
use App\Models\NewContract;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Storage;

class AttachmentResource extends Resource
{
    protected static ?string $model = Attachment::class;

    public static ?string $pluralModelLabel = 'Allegati';

    public static ?string $modelLabel = 'Allegato';

    protected static ?string $navigationIcon = 'tni-attachment-o';

    // protected static ?string $navigationGroup = 'Gestione';

    // protected static ?int $navigationSort = 3;

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
                Tables\Columns\TextColumn::make('attachment_type')
                    ->label('Tipo allegato'),
                Tables\Columns\TextColumn::make('attachment_upload_date')
                    ->label('Data caricamento')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('client.denomination')
                    ->label('Cliente')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('contract')
                    ->label('Contratto')
                    ->sortable()
                    ->searchable()
                    ->getStateUsing(function ($record) {
                        $contract = NewContract::find($record->contract_id);
                        if($contract)
                            return "{$contract?->office_name} ({$contract?->office_code}) - TIPO: {$contract?->payment_type->getLabel()} - CIG: {$contract?->cig_code}";
                        else return '';
                    }),
                Tables\Columns\TextColumn::make('attachment_date')
                    ->label('Data allegato')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('attachment_filename')
                    ->label('Allegato'),
            ])
            ->filters([
                SelectFilter::make('client_id')->label('Cliente')
                    ->relationship(name: 'client', titleAttribute: 'denomination')
                    ->getOptionLabelFromRecordUsing(
                        fn (Model $record) => strtoupper("{$record->subtype->getLabel()}")." - $record->denomination"
                    )
                    ->searchable()
                    ->preload()
                    ->optionsLimit(5),
                SelectFilter::make('notify_type')
                    ->label('Tipo notifica')
                    ->options(AttachmentType::class)
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                // Tables\Actions\EditAction::make(),
                Action::make('download_pdf')
                    ->label('')
                    ->tooltip('Scarica file')
                    ->icon('tabler-file-download')
                    ->iconSize('lg')
                    ->action(function ($record) {
                        $filePath = $record->attachment_path;
                        // dd($filePath);
                        if ($filePath && Storage::disk('public')->exists($filePath)) {
                            $filename = explode("/", $filePath)[1];
                            return response()->download(
                                Storage::disk('public')->path($filePath),
                                // 'document_' . $record->number . '.pdf'
                                $filename
                            );
                        }
                        return redirect()->back()->with('error', 'File PDF non trovato.');
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListAttachments::route('/'),
            // 'create' => Pages\CreateAttachment::route('/create'),
            // 'edit' => Pages\EditAttachment::route('/{record}/edit'),
        ];
    }
}
