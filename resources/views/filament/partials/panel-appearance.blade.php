@php
    $colors = $appearance['colors'];
@endphp

<style>
    :root {
        --pm-app-bg: {{ $colors['app_bg'] }};
        --pm-panel-bg: {{ $colors['panel_bg'] }};
        --pm-panel-muted: {{ $colors['panel_muted'] }};
        --pm-topbar-bg: {{ $colors['topbar_bg'] }};
        --pm-topbar-text: {{ $colors['topbar_text'] }};
        --pm-sidebar-bg: {{ $colors['sidebar_bg'] }};
        --pm-sidebar-text: {{ $colors['sidebar_text'] }};
        --pm-border: {{ $colors['border'] }};
        --pm-headline: {{ $colors['headline'] }};
        --pm-body-text: {{ $colors['body_text'] }};
        --pm-accent: {{ $colors['accent'] }};
        --pm-accent-strong: {{ $colors['accent_strong'] }};
        --pm-accent-soft: {{ $colors['accent_soft'] }};
        --pm-active-bg: {{ $colors['active_bg'] }};
        --pm-active-text: {{ $colors['active_text'] }};
        --pm-pill-bg: {{ $colors['pill_bg'] }};
        --pm-pill-text: {{ $colors['pill_text'] }};
        --pm-overlay: {{ $colors['overlay'] }};
        --pm-role-bg: #ffe49b;
        --pm-role-text: #7d4900;
        --pm-role-border: rgba(180, 108, 0, 0.28);
        --pm-card-shadow: 0 28px 60px -42px rgba(15, 23, 42, 0.45);
        --pm-soft-shadow: 0 20px 45px -38px rgba(15, 23, 42, 0.55);
        --gray-50: var(--pm-panel-bg);
        --gray-100: var(--pm-panel-muted);
        --gray-200: var(--pm-border);
        --gray-300: color-mix(in srgb, var(--pm-border) 76%, var(--pm-topbar-text) 24%);
        --gray-400: color-mix(in srgb, var(--pm-body-text) 38%, var(--pm-panel-bg) 62%);
        --gray-500: color-mix(in srgb, var(--pm-body-text) 68%, var(--pm-panel-bg) 32%);
        --gray-600: var(--pm-body-text);
        --gray-700: color-mix(in srgb, var(--pm-headline) 80%, var(--pm-body-text) 20%);
        --gray-800: color-mix(in srgb, var(--pm-headline) 88%, var(--pm-body-text) 12%);
        --gray-900: var(--pm-headline);
        --gray-950: color-mix(in srgb, var(--pm-headline) 92%, black 8%);
        --primary-50: color-mix(in srgb, var(--pm-accent) 8%, var(--pm-panel-bg) 92%);
        --primary-100: color-mix(in srgb, var(--pm-accent) 14%, var(--pm-panel-bg) 86%);
        --primary-200: color-mix(in srgb, var(--pm-accent) 25%, var(--pm-panel-bg) 75%);
        --primary-300: color-mix(in srgb, var(--pm-accent) 42%, var(--pm-panel-bg) 58%);
        --primary-400: color-mix(in srgb, var(--pm-accent) 68%, var(--pm-panel-bg) 32%);
        --primary-500: var(--pm-accent);
        --primary-600: var(--pm-accent-strong);
        --primary-700: color-mix(in srgb, var(--pm-accent-strong) 82%, var(--pm-headline) 18%);
        --primary-800: color-mix(in srgb, var(--pm-accent-strong) 64%, var(--pm-headline) 36%);
        --primary-900: color-mix(in srgb, var(--pm-accent-strong) 48%, var(--pm-headline) 52%);
        --primary-950: color-mix(in srgb, var(--pm-accent-strong) 32%, var(--pm-headline) 68%);
    }
</style>

<script>
    (() => {
        const appearance = @js([
            'context' => $appearance['context'],
            'paletteKey' => $appearance['palette_key'],
            'themeMode' => $appearance['theme_mode'],
            'isSuperuser' => $appearance['is_superuser'],
            'hasTenant' => $appearance['has_tenant'],
        ]);

        if (window.__pmv2ApplyPanelAppearance) {
            document.removeEventListener('livewire:navigated', window.__pmv2ApplyPanelAppearance);
        }

        window.__pmv2ApplyPanelAppearance = () => {
            const root = document.documentElement;

            root.dataset.panelContext = appearance.context;
            root.dataset.panelPalette = appearance.paletteKey;
            root.dataset.panelTheme = appearance.themeMode;
            root.dataset.panelSuperuser = appearance.isSuperuser ? '1' : '0';
            root.dataset.panelTenant = appearance.hasTenant ? '1' : '0';

            localStorage.setItem('theme', appearance.themeMode);
            root.classList.toggle('dark', appearance.themeMode === 'dark');
        };

        window.__pmv2ApplyPanelAppearance();
        document.addEventListener('livewire:navigated', window.__pmv2ApplyPanelAppearance);
    })();
</script>
