<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Enums\GlobalAccessType;
use BezhanSalleh\FilamentShield\Traits\HasPanelShield;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Spatie\Permission\Traits\HasRoles;
use Spatie\Permission\Models\Role;

class User extends Authenticatable implements FilamentUser, HasTenants
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasRoles, HasFactory, Notifiable, HasPanelShield;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Helper: Verifica se l'utente ha UN RUOLO che gli dà accesso a TUTTI i tenant (1000, 1002, ecc.).
     */
    public function hasFullCompaniesAccessRole(): Collection
    {
        return $this->companyRoles()
                    ->wherePivotIn('company_id', GlobalAccessType::getCompanyAccessIds())
                    ->get();
    }

    /**
     * Helper: Verifica se l'utente ha UN RUOLO che gli dà accesso al Pannello Admin (1000, 1001, ecc.).
     */
    public function hasPanelAccessRole(): Collection
    {
        return $this->companyRoles()
                    ->wherePivotIn('company_id', GlobalAccessType::getPanelAccessIds())
                    ->get();
    }

    public function hasAdminAccess(): bool
    {
        return $this->companyRoles()
                    ->wherePivotIn('company_id', GlobalAccessType::getPanelAccessIds())
                    ->exists();
    }

    public function hasCompanyAccess(): bool
    {
        if($this->companyRoles()->wherePivotIn('company_id', GlobalAccessType::getCompanyAccessIds())->exists())
            return true;

        if($this->companies()->exists())
            return true;

        return false;
    }

    /**
     * Ottiene l'OGGETTO Role di Spatie specifico dell'utente per il tenant corrente,
     * leggendo la colonna 'company_id' nella tabella model_has_roles.
     */
    public function getTenantRoles(): null|Collection
    {
        $currentTenant = Filament::getTenant();

        if (!$currentTenant)
            return null;

        return $this->companyRoles()
                    ->wherePivot('company_id', $currentTenant->getKey())
                    ->get();
    }

    public function getTenantRole()
    {
        $currentTenant = Filament::getTenant();

        if (!$currentTenant)
            return null;

        return $this->companyRoles()
                    ->wherePivot('company_id', $currentTenant->getKey())
                    ->first();
    }

    // --- METODI RICHIESTI DA FILAMENT (FilamentUser) ---

    public function canAccessPanel(Panel $panel): bool
    {
        $panelId = $panel->getId();

        if($this->isSuperAdmin())
            return true;

        if ($panelId === 'admin')
            return $this->hasAdminAccess();
        else if($panelId === 'company')
            return $this->hasCompanyAccess();

        return false;
    }

    public function loginRedirect(): ?Response
    {
        $destinationPanelId = null;

        if ($this->isSuperAdmin() || $this->hasAdminAccess())
            $destinationPanelId = 'admin';
        if ($this->hasCompanyAccess())
            $destinationPanelId = 'company';

        if (!$destinationPanelId)
            return abort(403, 'Accesso non autorizzato a nessun pannello.');

        return redirect()->to(Filament::getPanel($destinationPanelId)->getUrl());
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'model_has_roles', 'model_id', 'company_id');
    }

    public function getTenants(Panel $panel): array|Collection
    {
        // Se l'utente ha un ruolo in [1000, 1002], restituisce tutti i tenant.
        if ($this->hasCompanyAccess())
            return Company::all();

        // Altrimenti, ritorna solo i tenant a cui l'utente è direttamente associato.
        return $this->companies()->get();
    }

    public function canAccessTenant(Model $tenant): bool
    {
        // Se l'utente ha un ruolo in [1000, 1002], può accedere a qualsiasi tenant.
        if ($this->hasCompanyAccess())
            return true;

        // Utente standard: deve avere una relazione diretta con il tenant.
        return $this->companies->contains($tenant);
    }

    // Metodo helper per verificare se l'utente è manager di una specifica company
    public function isManager(): bool
    {
        return $this->hasRole('manager', Filament::getTenant()->id);
    }

    public function isSuperAdmin()
    {
        return $this->hasRole('super_admin', GlobalAccessType::AllPanels->value);
    }

    public function hasRole($roleName, array|int $company_ids){
        if(is_array($company_ids))
            return $this->companyRoles()->where('name', $roleName)
                ->wherePivotIn( 'company_id', $company_ids )
                ->exists();
        else
            return $this->companyRoles()->where('name', $roleName)
                ->wherePivot( 'company_id', $company_ids )
                ->exists();
    }

    public function companyRoles(): BelongsToMany
    {
        return $this->belongsToMany(
            config('permission.models.role'),
            config('permission.table_names.model_has_roles'),
            config('permission.column_names.model_morph_key'),
            'role_id'
        )
        ->withPivot(['company_id','model_type']);
    }

    public function getGlobalRoleAttribute(){
        return $this->companyRoles()
                   ->wherePivot('company_id', 1000)
                   ->first();
    }
}
