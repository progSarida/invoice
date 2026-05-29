<?php

namespace App\Filament\Company\Resources;

use App\Enums\ModelGroup;
use App\Enums\ModelType;
use App\Filament\Company\Resources\TemplateResource\Pages;
use App\Filament\Company\Resources\TemplateResource\RelationManagers;
use App\Models\Template;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Storage;

class TemplateResource extends Resource
{
    protected static ?string $model = Template::class;

    public static ?string $pluralModelLabel = 'Modelli';

    public static ?string $modelLabel = 'Modello';

    protected static ?string $navigationIcon = 'tabler-template';

    protected static ?string $navigationGroup = 'Generale';

    protected static ?int $navigationSort = 1;

    protected static ?int $navigationGroupSort = 2;


    public static function form(Form $form): Form
    {
        return $form
            ->columns(12)
            ->schema([
                Select::make('model_type_id')
                    ->label('Tipo modello')
                    ->options(\App\Models\ModelType::orderBy('name')->pluck('name', 'id'))
                    ->required()
                    ->live()
                    ->columnSpan(4)
                    ->afterStateUpdated(fn(callable $set) => $set('model_subtype_id', null)),

                Select::make('model_subtype_id')
                    ->label('Sottotipo modello')
                    ->options(function (callable $get) {
                        $typeId = $get('model_type_id');
                        if (! $typeId) return [];
                        return \App\Models\ModelSubType::where('model_type_id', $typeId)
                            ->orderBy('name')
                            ->pluck('name', 'id');
                    })
                    ->required()
                    ->live()
                    ->columnSpan(4)
                    ->disabled(fn(callable $get) => ! $get('model_type_id')),

                Placeholder::make('blank')
                    ->label('')
                    ->columnSpan(1),

                Toggle::make('current')
                    ->label('In uso')
                    ->columnSpan(3),

                TextInput::make('description')
                    ->label('Descrizione')
                    ->required()
                    ->columnSpanFull(),

                Forms\Components\FileUpload::make('upload_path')
                    ->label('File')
                    ->live()
                    ->disk(config('filesystems.default'))
                    ->directory('templates/tmp')
                    ->storeFileNamesIn('original_filename') // <-- salva il nome originale in questo campo
                    ->columnSpanFull()
                    ->extraAttributes(['class' => 'file-upload-with-preview']),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('modelType.name')
                    ->label('Tipo modello')
                    ->searchable(),
                Tables\Columns\TextColumn::make('modelSubType.name')
                    ->label('Sottotipo modello')
                    ->searchable(),
                Tables\Columns\TextColumn::make('description')
                    ->label('Descrizione')
                    ->searchable(),
                Tables\Columns\TextColumn::make('filename')
                    ->label('Nome file')
                    ->searchable(),
                Tables\Columns\TextColumn::make('upload_date')
                    ->label('Data caricamento')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('path')
                    ->label('Percorso')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                // Tables\Columns\IconColumn::make('current')
                //     ->label('In vigore')
                //     ->boolean(),
                Tables\Columns\ToggleColumn::make('current')
                    ->label('In vigore')
                    ->onColor('success')
                    ->onIcon('heroicon-m-check')
                    ->offColor('danger')
                    ->offIcon('heroicon-m-x-mark')
                    ->extraAttributes([
                        'class' => 'opacity-75 hover:opacity-100 transition-opacity',
                    ]),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creato il')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Modificato il')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('model_type_id')
                    ->label('Tipo modello')
                    ->relationship('modelType', 'name')
                    ->searchable()
                    ->preload()
                    ->columnspan(2),
                SelectFilter::make('model_subtype_id')
                    ->label('Sottotipo modello')
                    ->relationship('modelSubType', 'name')
                    ->searchable()
                    ->preload()
                    ->columnspan(2),
                Filter::make('interval_date')
                    ->label('Intervallo di date')
                    ->form([
                        Forms\Components\DatePicker::make('start_date')
                            ->label('Data caricamento da')
                            ->extraInputAttributes(['class' => 'text-center'])
                            ->placeholder('Seleziona data inizio')
                            ->displayFormat('d/m/Y'),
                        Forms\Components\DatePicker::make('end_date')
                            ->label('Data caricamento a')
                            ->extraInputAttributes(['class' => 'text-center'])
                            ->placeholder('Seleziona data fine')
                            ->displayFormat('d/m/Y')
                            ->afterOrEqual('start_date'),
                    ])
                    ->query(function ($query, array $data) {
                        if (!empty($data['start_date'])) {
                            $query->where('upload_date', '>=', $data['start_date']);
                        }
                        if (!empty($data['end_date'])) {
                            $query->where('upload_date', '<=', \Carbon\Carbon::parse($data['end_date'])->endOfDay());
                        }
                        return $query;
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicator = null;

                        if (!empty($data['start_date']) && !empty($data['end_date'])) {
                            $indicator = 'Dal ' . \Carbon\Carbon::parse($data['start_date'])->format('d/m/Y') . ' al ' . \Carbon\Carbon::parse($data['end_date'])->format('d/m/Y');
                        } elseif (!empty($data['start_date'])) {
                            $indicator = 'Dal ' . \Carbon\Carbon::parse($data['start_date'])->format('d/m/Y');
                        } elseif (!empty($data['end_date'])) {
                            $indicator = 'Al ' . \Carbon\Carbon::parse($data['end_date'])->format('d/m/Y');
                        }

                        return $indicator ? [$indicator] : [];
                    }),
            ])
            ->actions([
                // Tables\Actions\ViewAction::make(),
                // Tables\Actions\EditAction::make(),
                Action::make('download_pdf')
                    ->label('')
                    ->tooltip('Scarica file')
                    ->icon('tabler-file-download')
                    ->iconSize('lg')
                    // ->url(fn($record): ?string => $record->attachment_path ? Storage::temporaryUrl($record->attachment_path,now()->addMinutes(1)) : null)
                    ->visible(fn($record) => $record?->path)
                    ->url(function ($record) {
                        if (! $record?->path) return null;

                        $disk = Storage::disk(config('filesystems.default'));

                        try {
                            // Se il driver supporta i link temporanei (es. S3), usa questo
                            return $disk->temporaryUrl($record->path, now()->addMinutes(1));
                        } catch (\RuntimeException | \InvalidArgumentException $e) {
                            // Se sei in locale (driver local/public), usa l'URL standard
                            // Assicurati di aver lanciato 'php artisan storage:link'
                            return $disk->url($record->path);
                        }
                    })
                    ->openUrlInNewTab(),
                Tables\Actions\DeleteAction::make(),
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
            // 'index' => Pages\BrowseTemplates::route('/'),
            'index' => Pages\ListTemplates::route('/'),
            'create' => Pages\CreateTemplate::route('/create'),
            'view' => Pages\ViewTemplate::route('/{record}'),
            'edit' => Pages\EditTemplate::route('/{record}/edit'),
        ];
    }
}
