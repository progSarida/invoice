<?php

namespace App\Filament\Company\Resources\TemplateResource\Pages;

use App\Filament\Company\Resources\TemplateResource;
use App\Models\Template;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Url;

class BrowseTemplates extends Page
{
    protected static string $resource = TemplateResource::class;

    protected static ?string $title = 'Gestione Modelli';

    protected static string $view = 'templates.browse-templates';

    // ── Titolo dinamico ───────────────────────────────────────────────────────

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        if ($this->currentSubtype) {
            return $this->currentType->name . ' › ' . $this->currentSubtype->name;
        }
        if ($this->currentType) {
            return 'Gestione Modelli › ' . $this->currentType->name;
        }
        return 'Gestione Modelli';
    }

    // ── Stato navigazione ─────────────────────────────────────────────────────

    #[Url(as: 'type')]
    public ?int $modelTypeId = null;

    #[Url(as: 'subtype')]
    public ?int $modelSubtypeId = null;

    // ── Navigazione ───────────────────────────────────────────────────────────

    public function selectType(int $id): void
    {
        $this->modelTypeId    = $id;
        $this->modelSubtypeId = null;
    }

    public function selectSubtype(int $id): void
    {
        $this->modelSubtypeId = $id;
    }

    public function goBack(int $level = 0): void
    {
        if ($level === 0) {
            $this->modelTypeId    = null;
            $this->modelSubtypeId = null;
        } else {
            $this->modelSubtypeId = null;
        }
    }

    // ── Dati computati ────────────────────────────────────────────────────────

    public function getModelTypesProperty(): \Illuminate\Support\Collection
    {
        return \App\Models\ModelType::orderBy('name')->get();
    }

    public function getModelSubtypesProperty(): \Illuminate\Support\Collection
    {
        if (! $this->modelTypeId) return collect();

        return \App\Models\ModelSubType::where('model_type_id', $this->modelTypeId)
            ->orderBy('name')
            ->get();
    }

    public function getFilesProperty(): \Illuminate\Support\Collection
    {
        if (! $this->modelTypeId || ! $this->modelSubtypeId) return collect();

        return Template::with(['modelType', 'modelSubType'])
            ->where('company_id', Filament::getTenant()->id)
            ->where('model_type_id', $this->modelTypeId)
            ->where('model_subtype_id', $this->modelSubtypeId)
            ->orderByDesc('current')
            ->orderByDesc('upload_date')
            ->get();
    }

    public function getCurrentTypeProperty(): ?\App\Models\ModelType
    {
        if (! $this->modelTypeId) return null;
        return \App\Models\ModelType::find($this->modelTypeId);
    }

    public function getCurrentSubtypeProperty(): ?\App\Models\ModelSubType
    {
        if (! $this->modelSubtypeId) return null;
        return \App\Models\ModelSubType::find($this->modelSubtypeId);
    }

    // ── Azioni sui file (chiamate dalla view) ─────────────────────────────────

    public function downloadFile(int $id): mixed
    {
        $template = Template::findOrFail($id);

        if (! $template->path) {
            Notification::make()->danger()->title('File non trovato')->send();
            return null;
        }

        $disk = Storage::disk(config('filesystems.default'));

        try {
            return redirect($disk->temporaryUrl($template->path, now()->addMinutes(5)));
        } catch (\RuntimeException | \InvalidArgumentException) {
            return redirect($disk->url($template->path));
        }
    }

    public function setCurrent(int $id): void
    {
        $template = Template::findOrFail($id);

        DB::transaction(function () use ($template) {
            Template::where('company_id', Filament::getTenant()->id)
                ->where('model_type_id', $template->model_type_id)
                ->where('model_subtype_id', $template->model_subtype_id)
                ->where('id', '!=', $template->id)
                ->update(['current' => false]);

            $template->update(['current' => true]);
        });

        Notification::make()->success()->title('Modello impostato come "in vigore"')->send();
    }

    // ── Header actions (modali native Filament) ───────────────────────────────

    protected function getHeaderActions(): array
    {
        return [
            // ── UPLOAD ────────────────────────────────────────────────────────
            Action::make('upload')
                ->label('Carica file')
                ->icon('tabler-upload')
                ->modalHeading('Carica nuovo file')
                ->modalDescription('Seleziona il tipo, il sottotipo e il file da caricare.')
                ->modalSubmitActionLabel('Carica')
                ->modalWidth('lg')
                ->form([
                    Select::make('model_type_id')
                        ->label('Tipo modello')
                        ->options(\App\Models\ModelType::orderBy('name')->pluck('name', 'id'))
                        ->default(fn() => $this->modelTypeId)
                        ->required()
                        ->live()
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
                        ->default(fn() => $this->modelSubtypeId)
                        ->required()
                        ->live()
                        ->disabled(fn(callable $get) => ! $get('model_type_id')),

                    FileUpload::make('file')
                        ->label('File')
                        ->required()
                        ->disk('local')          // staging temporaneo
                        ->directory('livewire-tmp')
                        ->preserveFilenames(),
                ])
                ->action(function (array $data): void {
                    $typeId    = $data['model_type_id'];
                    $subtypeId = $data['model_subtype_id'];
                    $tmpPath   = $data['file'];            // path relativo su disco local
                    $filename  = basename($tmpPath);
                    $destPath  = "templates/{$typeId}/{$subtypeId}/{$filename}";

                    $disk    = Storage::disk(config('filesystems.default'));
                    $tmpDisk = Storage::disk('local');

                    if ($tmpDisk->exists($tmpPath)) {
                        $disk->put($destPath, $tmpDisk->get($tmpPath));
                        $tmpDisk->delete($tmpPath);
                    }

                    Template::create([
                        'company_id'       => Filament::getTenant()->id,
                        'model_type_id'    => $typeId,
                        'model_subtype_id' => $subtypeId,
                        'filename'         => $filename,
                        'path'             => $destPath,
                        'upload_date'      => now()->toDateString(),
                        'current'          => false,
                    ]);

                    Notification::make()->success()->title('File caricato con successo')->send();
                }),

            // ── RINOMINA (montata sull'header, aperta via mountAction dalla view) ──
            Action::make('rename')
                ->label('Rinomina')
                ->modalHeading('Rinomina file')
                ->modalSubmitActionLabel('Rinomina')
                ->modalWidth('sm')
                ->visible(false)   // non mostrata nell'header, solo via mountAction
                ->form([
                    TextInput::make('name')
                        ->label('Nuovo nome (senza estensione)')
                        ->required()
                        ->maxLength(255)
                        ->regex('/^[^\/\\\:\*\?\"\<\>\|]+$/')
                        ->validationMessages([
                            'regex' => 'Il nome non può contenere caratteri speciali.',
                        ]),
                ])
                ->action(function (array $data, array $arguments): void {
                    $template  = Template::findOrFail($arguments['id']);
                    $disk      = Storage::disk(config('filesystems.default'));
                    $extension = pathinfo($template->filename, PATHINFO_EXTENSION);
                    $newFilename = $data['name'] . ($extension ? '.' . $extension : '');
                    $oldPath   = $template->path;
                    $newPath   = dirname($oldPath) . '/' . $newFilename;

                    if ($disk->exists($oldPath)) {
                        $disk->move($oldPath, $newPath);
                    }

                    $template->update([
                        'filename' => $newFilename,
                        'path'     => $newPath,
                    ]);

                    Notification::make()->success()->title('File rinominato')->send();
                }),

            // ── ELIMINA ───────────────────────────────────────────────────────
            Action::make('deleteFile')
                ->label('Elimina')
                ->modalHeading('Elimina file')
                ->modalDescription('Questa azione è irreversibile. Il file verrà rimosso dallo storage.')
                ->modalSubmitActionLabel('Elimina')
                ->modalWidth('sm')
                ->color('danger')
                ->visible(false)   // non mostrata nell'header, solo via mountAction
                ->action(function (array $arguments): void {
                    $template = Template::findOrFail($arguments['id']);
                    $disk     = Storage::disk(config('filesystems.default'));

                    if ($template->path && $disk->exists($template->path)) {
                        $disk->delete($template->path);
                    }

                    $template->delete();

                    Notification::make()->success()->title('File eliminato')->send();
                }),
        ];
    }
}