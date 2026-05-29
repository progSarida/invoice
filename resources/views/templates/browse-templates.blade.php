{{--
    browse-templates.blade.php
    File manager a 3 livelli: tipo → sottotipo → file
--}}
<x-filament-panels::page>

    {{-- ══════════════════════════════════════════════════════════════════
         BREADCRUMB
    ══════════════════════════════════════════════════════════════════ --}}
    <nav class="flex items-center gap-1 text-sm mb-6" aria-label="breadcrumb">
        <button
            wire:click="goBack(0)"
            class="flex items-center gap-1 px-2 py-1 rounded-lg transition-colors
                   {{ is_null($this->modelTypeId) ? 'font-semibold text-primary-600 dark:text-primary-400 cursor-default' : 'text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-100' }}"
        >
            <x-heroicon-o-archive-box class="w-4 h-4" />
            <span>Template</span>
        </button>

        @if($this->currentType)
            <x-heroicon-o-chevron-right class="w-3.5 h-3.5 text-gray-400" />
            <button
                wire:click="goBack(1)"
                class="flex items-center gap-1 px-2 py-1 rounded-lg transition-colors
                       {{ is_null($this->modelSubtypeId) ? 'font-semibold text-primary-600 dark:text-primary-400 cursor-default' : 'text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-100' }}"
            >
                <x-heroicon-o-folder-open class="w-4 h-4" />
                <span>{{ $this->currentType->name }}</span>
            </button>
        @endif

        @if($this->currentSubtype)
            <x-heroicon-o-chevron-right class="w-3.5 h-3.5 text-gray-400" />
            <span class="flex items-center gap-1 px-2 py-1 font-semibold text-primary-600 dark:text-primary-400">
                <x-heroicon-o-folder-open class="w-4 h-4" />
                {{ $this->currentSubtype->name }}
            </span>
        @endif
    </nav>

    {{-- ══════════════════════════════════════════════════════════════════
         LIVELLO 1 — TIPI MODELLO
    ══════════════════════════════════════════════════════════════════ --}}
    @if(is_null($this->modelTypeId))
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">
            @forelse($this->modelTypes as $type)
                <button
                    wire:click="selectType({{ $type->id }})"
                    class="group flex flex-col items-center gap-2 p-4 rounded-xl border border-gray-200 dark:border-gray-700
                           bg-white dark:bg-gray-900 hover:border-primary-400 dark:hover:border-primary-500
                           hover:bg-primary-50 dark:hover:bg-primary-950 transition-all duration-150 text-center"
                >
                    <span class="text-3xl group-hover:scale-110 transition-transform duration-150">📁</span>
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-200 leading-tight">
                        {{ $type->name }}
                    </span>
                </button>
            @empty
                <p class="col-span-full text-center text-gray-400 py-12">Nessun tipo modello trovato.</p>
            @endforelse
        </div>
    @endif

    {{-- ══════════════════════════════════════════════════════════════════
         LIVELLO 2 — SOTTOTIPI
    ══════════════════════════════════════════════════════════════════ --}}
    @if(! is_null($this->modelTypeId) && is_null($this->modelSubtypeId))
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">
            @forelse($this->modelSubtypes as $subtype)
                <button
                    wire:click="selectSubtype({{ $subtype->id }})"
                    class="group flex flex-col items-center gap-2 p-4 rounded-xl border border-gray-200 dark:border-gray-700
                           bg-white dark:bg-gray-900 hover:border-primary-400 dark:hover:border-primary-500
                           hover:bg-primary-50 dark:hover:bg-primary-950 transition-all duration-150 text-center"
                >
                    <span class="text-3xl group-hover:scale-110 transition-transform duration-150">📂</span>
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-200 leading-tight">
                        {{ $subtype->name }}
                    </span>
                </button>
            @empty
                <p class="col-span-full text-center text-gray-400 py-12">Nessun sottotipo trovato per questo tipo.</p>
            @endforelse
        </div>
    @endif

    {{-- ══════════════════════════════════════════════════════════════════
         LIVELLO 3 — FILE
    ══════════════════════════════════════════════════════════════════ --}}
    @if(! is_null($this->modelTypeId) && ! is_null($this->modelSubtypeId))

        {{-- Conteggio file --}}
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
            {{ $this->files->count() }} {{ $this->files->count() === 1 ? 'file' : 'file' }}
        </p>

        {{-- Tabella file --}}
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
            @if($this->files->isEmpty())
                <div class="flex flex-col items-center justify-center py-16 text-gray-400">
                    <span class="text-5xl mb-3">📭</span>
                    <p class="text-sm">Nessun file in questa cartella.</p>
                    <button
                        wire:click="mountAction('upload')"
                        class="mt-3 text-sm text-primary-600 dark:text-primary-400 hover:underline"
                    >
                        Carica il primo file →
                    </button>
                </div>
            @else
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                        <tr>
                            <th class="text-left px-4 py-3 font-medium text-gray-600 dark:text-gray-300 w-8"></th>
                            <th class="text-left px-4 py-3 font-medium text-gray-600 dark:text-gray-300">Nome file</th>
                            <th class="text-left px-4 py-3 font-medium text-gray-600 dark:text-gray-300 hidden md:table-cell">Data caricamento</th>
                            <th class="text-center px-4 py-3 font-medium text-gray-600 dark:text-gray-300 w-24">In vigore</th>
                            <th class="text-right px-4 py-3 font-medium text-gray-600 dark:text-gray-300 w-36">Azioni</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach($this->files as $file)
                            <tr class="bg-white dark:bg-gray-900 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">

                                {{-- Icona --}}
                                <td class="px-4 py-3 text-lg">
                                    @php
                                        $ext  = strtolower(pathinfo($file->filename, PATHINFO_EXTENSION));
                                        $icon = match($ext) {
                                            'pdf'        => '📄',
                                            'doc','docx' => '📝',
                                            'xls','xlsx' => '📊',
                                            'ppt','pptx' => '📋',
                                            'zip','rar'  => '🗜️',
                                            default      => '📎',
                                        };
                                    @endphp
                                    {{ $icon }}
                                </td>

                                {{-- Nome --}}
                                <td class="px-4 py-3">
                                    <span class="font-medium text-gray-800 dark:text-gray-100">
                                        {{ $file->filename }}
                                    </span>
                                    @if($file->current)
                                        <span class="ml-2 inline-flex items-center gap-1 text-xs px-2 py-0.5 rounded-full
                                                     bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300 font-medium">
                                            <x-heroicon-s-check-circle class="w-3 h-3" />
                                            in vigore
                                        </span>
                                    @endif
                                </td>

                                {{-- Data --}}
                                <td class="px-4 py-3 text-gray-500 dark:text-gray-400 hidden md:table-cell">
                                    {{ $file->upload_date ? \Carbon\Carbon::parse($file->upload_date)->format('d/m/Y') : '—' }}
                                </td>

                                {{-- Toggle current --}}
                                <td class="px-4 py-3 text-center">
                                    @if(! $file->current)
                                        <button
                                            wire:click="setCurrent({{ $file->id }})"
                                            title="Imposta come in vigore"
                                            class="text-gray-300 dark:text-gray-600 hover:text-green-500 dark:hover:text-green-400 transition-colors"
                                        >
                                            <x-heroicon-o-check-circle class="w-5 h-5" />
                                        </button>
                                    @else
                                        <x-heroicon-s-check-circle class="w-5 h-5 text-green-500 dark:text-green-400 mx-auto" />
                                    @endif
                                </td>

                                {{-- Azioni --}}
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-1">

                                        {{-- Download --}}
                                        <button
                                            wire:click="downloadFile({{ $file->id }})"
                                            title="Scarica"
                                            class="p-1.5 rounded-lg text-gray-400 hover:text-blue-600 dark:hover:text-blue-400
                                                   hover:bg-blue-50 dark:hover:bg-blue-950 transition-colors"
                                        >
                                            <x-heroicon-o-arrow-down-tray class="w-4 h-4" />
                                        </button>

                                        {{-- Rinomina → apre modale nativa Filament --}}
                                        <button
                                            wire:click="mountAction('rename', { id: {{ $file->id }}, name: '{{ addslashes(pathinfo($file->filename, PATHINFO_FILENAME)) }}' })"
                                            title="Rinomina"
                                            class="p-1.5 rounded-lg text-gray-400 hover:text-amber-600 dark:hover:text-amber-400
                                                   hover:bg-amber-50 dark:hover:bg-amber-950 transition-colors"
                                        >
                                            <x-heroicon-o-pencil class="w-4 h-4" />
                                        </button>

                                        {{-- Elimina → apre modale nativa Filament --}}
                                        <button
                                            wire:click="mountAction('deleteFile', { id: {{ $file->id }} })"
                                            title="Elimina"
                                            class="p-1.5 rounded-lg text-gray-400 hover:text-red-600 dark:hover:text-red-400
                                                   hover:bg-red-50 dark:hover:bg-red-950 transition-colors"
                                        >
                                            <x-heroicon-o-trash class="w-4 h-4" />
                                        </button>

                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @endif

    {{-- Necessario per il rendering delle modali Filament --}}
    <x-filament-actions::modals />

</x-filament-panels::page>