<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use App\Enums\GlobalAccessType;
use App\Models\Company;
use App\Models\Role;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class RolesRelationManager extends RelationManager
{
    protected static string $relationship = 'roles';

    protected static ?string $title = 'Ruoli';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                // Forms\Components\Select::make('role_id')
                //     ->label('Ruolo')
                //     ->options(Role::pluck('name', 'id'))
                //     ->required(),
                
                // // Campo Pivot: Selezionare l'Azienda (Tenant)
                // // Se l'ID è NOT NULL, devi forzare la selezione di un tenant valido o l'ID globale (es. 1)
                // Forms\Components\Select::make('company_id')
                //     ->label('Azienda')
                //     ->options(Company::pluck('name', 'id'))
                //     // Se usi l'ID riservato '1' per il globale, potresti doverlo includere qui.
                //     // ->default(1) // Esempio se '1' è l'ID globale
                //     ->required(), 
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                // Colonna standard per il nome del Ruolo (campo sul modello Role)
                TextColumn::make('name') 
                    ->label('Ruolo'),

                // CHIAVE: Colonna che usa getStateUsing() per recuperare il NOME
                TextColumn::make('company_id') // Usiamo direttamente il nome della pivot per chiarezza
                    ->label('Associazione')
                    ->badge()
                    ->getStateUsing(function (Model $record): string {
                        
                        $companyId = $record->pivot->company_id;
                        
                        // 1. Tenta di risolvere l'ID tramite l'Enum (10000, 10001, 10002)
                        $enumCase = GlobalAccessType::tryFrom($companyId);
                        
                        if ($enumCase) {
                            return $enumCase->getLabel(); // Ottieni l'etichetta Filament (es. "Gestione Aziende")
                        }
                        
                        // 2. Se è un ID di tenant standard, recupera il nome della Company
                        return Company::find($companyId)?->name ?? 'Sconosciuta';
                    })
                    // Colore basato sulla logica dell'Enum (se è un ID speciale) o su un colore di fallback
                    ->color(fn (Model $record): string|array|null => 
                        GlobalAccessType::tryFrom($record->pivot->company_id)?->getColor() ?? 'gray'
                    ),
                ])
                ->filters([
                    //
                ])
                ->headerActions([
                    Tables\Actions\AttachAction::make()->form(fn (): array => [
                        // 1. Seleziona il Ruolo esistente
                        Select::make('recordId')
                            ->label('Ruolo')
                            ->options(Role::pluck('name', 'id'))
                            ->required(),

                        Select::make('company_id')
                                ->label('Tipo di Accesso / Azienda')
                                ->options(
                                    // 🛑 Ottieni solo i casi salvabili (10000, 10001, 10002) e uniscili ai tenant reali 🛑
                                    collect(GlobalAccessType::cases())
                                        // Filtra il caso fittizio (es. 9999 o un valore negativo se lo usi per default)
                                        // Non c'è bisogno di filtrare qui se non hai casi fittizi
                                        ->mapWithKeys(fn ($case) => [$case->value => $case->getLabel()])
                                        ->toArray() 
                                    + Company::pluck('name', 'id')->toArray()
                                )
                                ->required()
                                ->preload()->searchable(),

                        Hidden::make('model_type')->default(function($livewire){ return $livewire->getOwnerRecord()->getMorphClass();})
                    ])
                ])
                ->actions([
                    Tables\Actions\DetachAction::make()->action(function (Model $record, $livewire) {
                        // 2. Ottieni l'ID del tenant/team da distaccare (dal record pivot)
                        // $record qui è il modello Role, e ha accesso alla pivot
                        $teamIdToDetach = $record->pivot->company_id;
                        
                        // 3. Ottieni l'utente (il record padre della Relation Manager)
                        $user = $livewire->getOwnerRecord();
                        
                        // 4. Esegui il distacco forzato con wherePivot
                        // CHIAVE: Filtra il costruttore della relazione per includere SOLO la riga con quel team_id
                        $user->roles()
                            ->wherePivot('company_id', $teamIdToDetach) // Filtra per team_id specifico (anche se NULL)
                            ->detach($record->id); // Distacca solo questo ruolo
                        
                        // Notification::make()->success()->title('Associazione Ruolo rimossa con successo.');
                        // Filament::notify('success', 'Associazione Ruolo rimossa con successo.');
                    }),
                ])
                ->bulkActions([
                    Tables\Actions\BulkActionGroup::make([
                        Tables\Actions\DetachBulkAction::make(),
                    ]),
                ]);
    }
}
