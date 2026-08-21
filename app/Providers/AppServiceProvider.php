<?php

namespace App\Providers;

use App\Responses\SsoLogoutResponse;
use Filament\Forms\Components\FileUpload;
use Illuminate\Support\ServiceProvider;
use Filament\Http\Responses\Auth\LogoutResponse;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Number;

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
        // L'helper Number ha una lingua propria, fissata a 'en' dal framework: senza questa riga
        // i conteggi in calce alle tabelle di Filament separano le migliaia con la virgola
        Number::useLocale(Config::get('app.locale'));

        FileUpload::configureUsing(function (FileUpload $component): void {

                $diskName = $component->getDiskName() ?? Config::get('filesystems.default');
                $diskConfig = Config::get("filesystems.disks.{$diskName}");

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
