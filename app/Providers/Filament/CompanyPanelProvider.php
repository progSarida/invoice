<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Tenancy\EditCompanyProfile;
use App\Http\Middleware\EnsureUserCanAccessPanel;
use App\Http\Middleware\SetSpatieTenant;
use App\Http\Middleware\CheckDbSession;
use BezhanSalleh\FilamentShield\Middleware\SyncShieldTenant;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use App\Models\Company;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Navigation\NavigationGroup;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class CompanyPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('company')
            ->path('company')
            ->profile() // Opzionale: abilita la pagina del profilo
            ->tenant(Company::class)
            ->tenantProfile(EditCompanyProfile::class)
            // ->tenantProfile(Auth::user() && !Auth::user()->is_admin ? EditCompanyProfile::class : null)
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Company/Resources'), for: 'App\\Filament\\Company\\Resources')
            ->navigationGroups([
                NavigationGroup::make('Fatturazione attiva'),
                NavigationGroup::make('Fatture passive'),
                NavigationGroup::make('Prima nota'),
                NavigationGroup::make('Costi di notifica'),
                NavigationGroup::make('Polizze'),
                NavigationGroup::make('Tabelle'),
                NavigationGroup::make('Generale'),
            ])
            ->discoverPages(in: app_path('Filament/Company/Pages'), for: 'App\\Filament\\Company\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Company/Widgets'), for: 'App\\Filament\\Company\\Widgets')
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
            // ->plugins([
            //     FilamentShieldPlugin::make(),
            // ])
            ->tenantMiddleware([
                SyncShieldTenant::class,
            ])
            ->databaseNotifications()
            ->databaseNotificationsPolling(5)
            ->authMiddleware([
                Authenticate::class,
            ])
            ->userMenuItems([
                MenuItem::make()
                ->label('Passa ad amministratore')
                // ->visible(fn (): bool => Auth::user()->is_admin)
                ->visible(fn (): bool => Auth::user()->hasAdminAccess())
                ->url('/admin')
                ->icon('ri-admin-fill'),
                'logout'=>MenuItem::make()
                    ->label('Vai al Portale')
                    ->icon('heroicon-o-arrow-left-start-on-rectangle'),
            ])
            ->renderHook(
                PanelsRenderHook::TOPBAR_START,
                fn (): string => view('filament.topbar.ticket-button')->render()
            )
            ->globalSearchKeyBindings(['f9']);
    }
}
