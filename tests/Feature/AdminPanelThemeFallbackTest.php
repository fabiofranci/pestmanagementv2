<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Vite;
use ReflectionProperty;
use Tests\TestCase;

class AdminPanelThemeFallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_login_page_loads_when_theme_manifest_entry_is_missing(): void
    {
        $manifestPath = public_path('build/manifest.json');
        $hotPath = public_path('hot');
        $originalManifest = file_get_contents($manifestPath);
        $originalHot = is_file($hotPath) ? file_get_contents($hotPath) : null;

        if ($originalHot !== null) {
            unlink($hotPath);
        }

        file_put_contents($manifestPath, json_encode([
            'resources/css/app.css' => [
                'file' => 'assets/app.css',
                'src' => 'resources/css/app.css',
                'isEntry' => true,
            ],
            'resources/js/app.js' => [
                'file' => 'assets/app.js',
                'src' => 'resources/js/app.js',
                'isEntry' => true,
            ],
        ], JSON_THROW_ON_ERROR));

        $this->clearViteManifestCache();

        try {
            $this->get('/admin/login')->assertOk();
        } finally {
            if ($originalManifest !== false) {
                file_put_contents($manifestPath, $originalManifest);
            }

            if ($originalHot !== null) {
                file_put_contents($hotPath, $originalHot);
            }

            $this->clearViteManifestCache();
        }
    }

    protected function clearViteManifestCache(): void
    {
        $property = new ReflectionProperty(Vite::class, 'manifests');
        $property->setValue(null, []);
    }
}
