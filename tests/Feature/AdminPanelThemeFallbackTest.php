<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Vite;
use ReflectionProperty;
use Tests\TestCase;

class AdminPanelThemeFallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_panel_loads_when_theme_manifest_entry_is_missing(): void
    {
        $manifestPath = public_path('build/manifest.json');
        $hotPath = public_path('hot');
        $originalManifest = file_get_contents($manifestPath);
        $originalHot = is_file($hotPath) ? file_get_contents($hotPath) : null;
        $user = User::query()->create([
            'name' => 'Super Admin',
            'email' => 'super-admin@example.com',
            'password' => 'password123',
            'is_superuser' => true,
        ]);

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
            $this->actingAs($user)
                ->get('/admin')
                ->assertOk();
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
