<?php

namespace App\Providers;

use App\Models\Permission;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Filament\Facades\Filament;
use App\Models\User; // Assicurati di importare il tuo modello User

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    // protected $policies = [
    //     // Registra qui tutte le tue policy
    //     // Esempio: SupplierPolicy::class => SupplierPolicy::class,
    // ];

    protected function mapActionToPrefix(string $actionName): string
    {
        // Se il permesso è già in snake_case (es. view_any_supplier), lo lasciamo.
        if (str_contains($actionName, '_')) {
            return $actionName;
        }

        // Converte da camelCase a snake_case (es. viewAny -> view_any)
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $actionName));
    }

    /**
     * Ricava il nome della risorsa e applica la logica del separatore '::' per i nomi composti.
     * @param array $arguments L'array degli argomenti passati al Gate.
     * @return string|null Il nome della risorsa (es. 'supplier' o 'blog::post').
     */
    protected function getResourceNameFromArguments(array $arguments): ?string
    {
        if (empty($arguments)) {
            return null;
        }
        
        $model = $arguments[0];
        $className = null;

        if (is_string($model) && class_exists($model) && is_subclass_of($model, \Illuminate\Database\Eloquent\Model::class)) {
            $className = $model;
        } elseif ($model instanceof \Illuminate\Database\Eloquent\Model) {
            $className = get_class($model);
        } else {
            return null;
        }

        $shortName = (new \ReflectionClass($className))->getShortName();

        $snakeCaseName = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $shortName));
        if (str_contains($snakeCaseName, '_')) {
            return str_replace('_', '::', $snakeCaseName);
        }
        
        // Altrimenti, restituisce il nome in minuscolo (es. Supplier -> supplier)
        return $snakeCaseName;
    }


    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
        Gate::before(function (User $user, string $permission, array $arguments = []) {

            
            $panelId = Filament::getId();
            if ($panelId==="admin" && $user->hasPanelAccessRole()) {
                $tenantRoles = $user->hasPanelAccessRole();
                if($this->checkTenantPermission($user, $permission, $tenantRoles, $arguments))
                    return true;
            }
            else{
                $tenantRoles = $user->getTenantRoles();
                if($this->checkTenantPermission($user, $permission, $tenantRoles, $arguments))
                    return true;
                else
                {
                    $tenantRoles = $user->hasFullCompaniesAccessRole();
                    if($this->checkTenantPermission($user, $permission, $tenantRoles, $arguments))
                        return true;
                }
            }
            
            return false;
        });
    }

    public function checkTenantPermission(User $user, string $permission, $tenantRoles, array $arguments = []){
        //BISOGNA SISTEMARE LA FORMATTAZIONE DEL PERMESSO
            $actionPrefix = $this->mapActionToPrefix($permission);
            $resourceName = $this->getResourceNameFromArguments($arguments);

            if($resourceName=="role"){
                if($user->isSuperAdmin())
                    return $permission;
                else
                    return false;
            } 
            
            $fullPermission = $actionPrefix;

            if ($resourceName)
                $fullPermission = $actionPrefix . '_' . $resourceName;
            
            foreach($tenantRoles as $tenantRole){
                if($tenantRole->hasPermissionTo($fullPermission))
                    return true;
            }

            return false;

    }
}