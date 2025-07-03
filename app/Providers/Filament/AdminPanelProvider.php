<?php

namespace App\Providers\Filament;

use Filament\Pages;
use Filament\Panel;
use Filament\Widgets;
use Filament\PanelProvider;
use Filament\Facades\Filament;
use Filament\Navigation\MenuItem;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use App\Filament\Resources\CityResource;
use App\Filament\Resources\UserResource;
use Filament\Navigation\NavigationGroup;
use App\Filament\Resources\RegionResource;
use Filament\Http\Middleware\Authenticate;
use App\Filament\Resources\CompanyResource;
use App\Filament\Resources\DocTypeResource;
use App\Filament\Resources\DocGroupResource;
use App\Filament\Resources\ProvinceResource;
use App\Filament\Resources\SectionalResource;
use App\Filament\Resources\ManageTypeResource;
use App\Filament\Resources\AccrualTypeResource;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Filament\Http\Middleware\AuthenticateSession;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use App\Filament\Resources\LimitMotivationTypeResource;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        Filament::serving(function () {
            Filament::registerNavigationGroups([
                NavigationGroup::make()->label('Gestione città'),
                NavigationGroup::make()->label('Parametri'),
            ]);
        });

        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login() // Abilita la pagina di login
            // ->registration() // Opzionale: abilita la registrazione
            ->passwordReset() // Opzionale: abilita il reset della password
            ->emailVerification() // Opzionale: abilita la verifica dell'email
            ->profile() // Opzionale: abilita la pagina del profilo
            ->colors([
                'primary' => Color::Amber,
            ])
            // ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->resources([
                RegionResource::class,
                ProvinceResource::class,
                CityResource::class,
                UserResource::class,
                CompanyResource::class,
                // SectionalResource::class,
                AccrualTypeResource::class,
                ManageTypeResource::class,
                DocGroupResource::class,
                DocTypeResource::class,
                LimitMotivationTypeResource::class,
            ])
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            // ->plugins([
            //     FilamentShieldPlugin::make(),
            // ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->userMenuItems([
                MenuItem::make()
                    ->label('Gestione aziende')
                    ->url('/company')
                    ->icon('gmdi-business-center-s'),
                // ...
            ])
            ->NavigationGroups([
                NavigationGroup::make()
                ->label('Archivio')
                ->icon('heroicon-o-cog-6-tooth')
                ->collapsed()
            ]);
    }
}
