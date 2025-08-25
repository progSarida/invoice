<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

class User extends Authenticatable implements FilamentUser, HasTenants
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

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

    public function canAccessPanel(\Filament\Panel $panel): bool
    {
        if ($panel->getId() === 'admin') { return $this->is_admin; }                        // solo chi è admin può accedere al relativo panel
        if ($panel->getId() === 'company') { return true; }                                 // tutti gli utenti possono accedere al panel 'company'
        return false;
    }

    public function companies()
    {
        return $this->belongsToMany(Company::class)->withPivot('is_manager')->withTimestamps();
    }

    public function getTenants(Panel $panel): array|Collection
    {
        return $this->companies;
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return $this->companies->contains($tenant);
    }

    // Metodo helper per verificare se l'utente è manager di una specifica company
    public function isManagerOf(Company $company): bool
    {
        return $this->companies()->where('company_id', $company->id)->wherePivot('is_manager', true)->exists();
    }
}
