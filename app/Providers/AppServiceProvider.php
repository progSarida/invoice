<?php

namespace App\Providers;

use App\Responses\SsoLogoutResponse;
use Filament\Forms\Components\FileUpload;
use Illuminate\Support\ServiceProvider;
use Filament\Http\Responses\Auth\LogoutResponse;
use Illuminate\Support\Facades\Config;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(LogoutResponse::class, SsoLogoutResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        FileUpload::configureUsing(function (FileUpload $component): void {
                // 1. Recuperiamo il nome del disco impostato per questo specifico componente
                // Se non è impostato, Filament userà il default del sistema.
                $diskName = $component->getDiskName() ?? Config::get('filesystems.default');
                
                // 2. Recuperiamo la configurazione di quel disco
                $diskConfig = Config::get("filesystems.disks.{$diskName}");

                // 3. Applichiamo la visibilità private solo se:
                // - È un driver S3 (o compatibile come R2)
                // - Non ha un URL pubblico configurato (quindi è un bucket privato)
                if (
                    $diskConfig && 
                    ($diskConfig['driver'] ?? '') === 's3' && 
                    empty($diskConfig['url'])
                ) {
                    $component->visibility('private');
                }
            });
    }
}
