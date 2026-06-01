<?php

namespace App\Providers\Filament;

use App\Filament\Resources\Tenants\TenantResource;
use App\Http\Middleware\BootstrapTenantContext;
use App\Models\User;
use App\Support\Filament\PanelAppearance;
use App\Support\Tenancy\CurrentTenant;
use Filament\Actions\Action;
use Filament\FontProviders\GoogleFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->brandName('Pest Management V2')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->font(
                fn (): string => app(PanelAppearance::class)->resolve()['font_family'],
                provider: GoogleFontProvider::class,
            )
            ->login()
            ->colors([
                'primary' => Color::Green,
            ])
            ->renderHook(
                PanelsRenderHook::TOPBAR_LOGO_AFTER,
                fn () => view('filament.partials.panel-context', [
                    'appearance' => app(PanelAppearance::class)->resolve(),
                ]),
            )
            ->renderHook(
                PanelsRenderHook::STYLES_AFTER,
                fn () => view('filament.partials.panel-appearance', [
                    'appearance' => app(PanelAppearance::class)->resolve(),
                ]),
            )
            ->userMenuItems([
                Action::make('tenantStatus')
                    ->label(fn (): string => 'Tenant attivo: ' . (app(CurrentTenant::class)->get()?->name ?? 'nessuno'))
                    ->disabled()
                    ->visible(fn (): bool => auth()->user() instanceof User && auth()->user()->isSuperuser())
                    ->sort(10),
                Action::make('tenantDirectory')
                    ->label('Tutti i tenant')
                    ->url(fn (): string => TenantResource::getUrl('index'))
                    ->visible(fn (): bool => auth()->user() instanceof User && auth()->user()->isSuperuser())
                    ->sort(11),
                Action::make('leaveTenant')
                    ->label('Esci dal tenant')
                    ->url(fn (): string => route('admin.tenancy.leave'))
                    ->visible(fn (): bool => auth()->user() instanceof User
                        && auth()->user()->isSuperuser()
                        && app(CurrentTenant::class)->hasTenant())
                    ->sort(12),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                BootstrapTenantContext::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
