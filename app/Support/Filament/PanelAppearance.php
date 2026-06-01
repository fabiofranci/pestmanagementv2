<?php

namespace App\Support\Filament;

use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\CurrentTenant;

class PanelAppearance
{
    protected ?array $resolved = null;

    public function resolve(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        $user = auth()->user();
        $tenant = app(CurrentTenant::class)->get();
        $isSuperuser = $user instanceof User && $user->isSuperuser();
        $hasTenant = $tenant instanceof Tenant;

        if ($hasTenant) {
            $paletteKey = $this->normalizePaletteKey($tenant->panel_palette);
            $themeMode = $this->normalizeThemeMode($tenant->panel_theme_mode);
            $fontKey = $this->normalizeFontKey($tenant->panel_font_family);
            $context = $isSuperuser ? 'superuser-tenant' : 'tenant';
            $contextLabel = $tenant->name;
        } elseif ($isSuperuser) {
            $paletteKey = config('panel-branding.superuser.palette', 'superuser');
            $themeMode = config('panel-branding.superuser.theme_mode', 'light');
            $fontKey = $this->normalizeFontKey(config('panel-branding.superuser.font_family', 'manrope'));
            $context = 'superuser-central';
            $contextLabel = 'Vista centrale';
        } else {
            $paletteKey = $this->defaultTenantPalette();
            $themeMode = $this->defaultTenantThemeMode();
            $fontKey = $this->defaultTenantFontFamily();
            $context = 'guest';
            $contextLabel = 'Accesso centrale';
        }

        $palette = $this->paletteDefinition($paletteKey);
        $font = $this->fontDefinition($fontKey);

        return $this->resolved = [
            'context' => $context,
            'context_label' => $contextLabel,
            'is_superuser' => $isSuperuser,
            'has_tenant' => $hasTenant,
            'tenant_name' => $tenant?->name,
            'palette_key' => $paletteKey,
            'palette_label' => $palette['label'],
            'theme_mode' => $themeMode,
            'theme_label' => $this->themeLabel($themeMode),
            'font_key' => $fontKey,
            'font_label' => $font['label'],
            'font_family' => $font['family'],
            'colors' => $palette[$themeMode],
        ];
    }

    public function applyTenantDefaults(array $data): array
    {
        $data['panel_palette'] = $this->normalizePaletteKey($data['panel_palette'] ?? null);
        $data['panel_theme_mode'] = $this->normalizeThemeMode($data['panel_theme_mode'] ?? null);
        $data['panel_font_family'] = $this->normalizeFontKey($data['panel_font_family'] ?? null);

        return $data;
    }

    public function paletteOptions(): array
    {
        return collect(config('panel-branding.palettes', []))
            ->reject(fn (array $definition, string $key): bool => $key === 'superuser')
            ->mapWithKeys(fn (array $definition, string $key): array => [$key => $definition['label']])
            ->all();
    }

    public function themeOptions(): array
    {
        return config('panel-branding.themes', []);
    }

    public function fontOptions(): array
    {
        return collect(config('panel-branding.fonts', []))
            ->mapWithKeys(fn (array $definition, string $key): array => [$key => $definition['label']])
            ->all();
    }

    public function paletteLabel(?string $key): string
    {
        $key = $this->normalizePaletteKey($key);

        return $this->paletteDefinition($key)['label'];
    }

    public function themeLabel(?string $key): string
    {
        $key = $this->normalizeThemeMode($key);

        return $this->themeOptions()[$key];
    }

    public function fontLabel(?string $key): string
    {
        $key = $this->normalizeFontKey($key);

        return $this->fontDefinition($key)['label'];
    }

    public function defaultTenantPalette(): string
    {
        return config('panel-branding.defaults.tenant.palette', 'salvia');
    }

    public function defaultTenantThemeMode(): string
    {
        return config('panel-branding.defaults.tenant.theme_mode', 'light');
    }

    public function defaultTenantFontFamily(): string
    {
        return config('panel-branding.defaults.tenant.font_family', 'manrope');
    }

    protected function normalizePaletteKey(?string $key): string
    {
        $options = $this->paletteOptions();

        return array_key_exists((string) $key, $options)
            ? (string) $key
            : $this->defaultTenantPalette();
    }

    protected function normalizeThemeMode(?string $key): string
    {
        $options = $this->themeOptions();

        return array_key_exists((string) $key, $options)
            ? (string) $key
            : $this->defaultTenantThemeMode();
    }

    protected function normalizeFontKey(?string $key): string
    {
        $options = $this->fontOptions();

        return array_key_exists((string) $key, $options)
            ? (string) $key
            : $this->defaultTenantFontFamily();
    }

    protected function paletteDefinition(string $key): array
    {
        return config("panel-branding.palettes.{$key}", config('panel-branding.palettes.salvia'));
    }

    protected function fontDefinition(string $key): array
    {
        return config("panel-branding.fonts.{$key}", config('panel-branding.fonts.manrope'));
    }
}
