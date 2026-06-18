<?php

namespace App\Providers\Filament;

use App\Filament\Resources\Tenants\TenantResource;
use App\Http\Controllers\Auth\TenantLoginController;
use App\Http\Middleware\AuthenticatePanel;
use App\Http\Middleware\AuthenticatePanelSession;
use App\Http\Middleware\BootstrapTenantContext;
use App\Models\User;
use App\Support\Filament\PanelAppearance;
use App\Support\Tenancy\CurrentTenant;
use Filament\Actions\Action;
use Filament\FontProviders\GoogleFontProvider;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Assets\Theme;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentAsset;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Vite;
use Illuminate\Foundation\ViteException;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $panel = $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->brandName('Pest Management V2')
            ->theme($this->adminTheme())
            ->font(
                fn (): string => app(PanelAppearance::class)->resolve()['font_family'],
                provider: GoogleFontProvider::class,
            )
            ->login([TenantLoginController::class, 'redirectFromPanel'])
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
                    ->label(fn (): string => 'Tenant attivo: '.(app(CurrentTenant::class)->get()?->name ?? 'nessuno'))
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
                AuthenticatePanelSession::class,
                ShareErrorsFromSession::class,
                BootstrapTenantContext::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                AuthenticatePanel::class,
            ]);

        return $panel;
    }

    protected function adminTheme(): Theme
    {
        return Theme::make('admin-theme')
            ->html(function (): Htmlable {
                if (! $this->hasAdminThemeAsset()) {
                    return FilamentAsset::getTheme('app')->getHtml();
                }

                try {
                    return app(Vite::class)('resources/css/filament/admin/theme.css');
                } catch (ViteException) {
                    return FilamentAsset::getTheme('app')->getHtml();
                }
            });
    }

    protected function hasAdminThemeAsset(): bool
    {
        if (is_file(public_path('hot'))) {
            return true;
        }

        $manifestPath = public_path('build/manifest.json');

        if (! is_file($manifestPath)) {
            return false;
        }

        $manifest = json_decode(file_get_contents($manifestPath) ?: '[]', true);

        return is_array($manifest) && array_key_exists('resources/css/filament/admin/theme.css', $manifest);
    }
}
