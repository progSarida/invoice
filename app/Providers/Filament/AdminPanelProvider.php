<?php

namespace App\Providers\Filament;

use App\Http\Middleware\EnsureUserCanAccessPanel;
use App\Http\Middleware\CheckDbSession;
use App\Responses\SsoLogoutResponse;
use Filament\Pages;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Panel;
use Filament\Widgets;
use Filament\PanelProvider;
use Filament\Facades\Filament;
use Filament\Navigation\MenuItem;
use Filament\Support\Colors\Color;
use Filament\Navigation\NavigationGroup;
use Filament\Http\Middleware\Authenticate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Filament\Http\Middleware\AuthenticateSession;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\View\PanelsRenderHook;
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
            ->databaseNotifications()
            ->databaseNotificationsPolling(null)
            ->login() // Abilita la pagina di login
            ->profile() // Opzionale: abilita la pagina del profilo
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->navigationGroups([
                NavigationGroup::make('Parametri'),
                NavigationGroup::make('Tabelle'),
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
                CheckDbSession::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                // EnsureUserCanAccessPanel::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->plugins([
                FilamentShieldPlugin::make(),
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->userMenuItems([
                MenuItem::make()
                    ->label('Passa alle aziende')
                    ->visible(fn (): bool => Auth::user()->hasCompanyAccess())
                    ->url('/company')
                    ->icon('tabler-briefcase-f'),
                MenuItem::make()
                    ->label('Pannello Utente')
                    ->url(config('services.sso.user_dashboard'))
                    ->icon('heroicon-o-plus')
                    ->openUrlInNewTab(),
                'logout'=>MenuItem::make()
                    ->label('Vai al Portale')
                    ->icon('heroicon-o-arrow-left-start-on-rectangle'),

            ])
            ->NavigationGroups([
                NavigationGroup::make()
                ->label('Archivio')
                ->icon('heroicon-o-cog-6-tooth')
                ->collapsed()
            ])
            ->renderHook(
                PanelsRenderHook::TOPBAR_END,
                fn (): string => view('filament.topbar.ticket-button')->render()
            );
    }
}
