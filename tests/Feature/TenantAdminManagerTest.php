<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Support\Tenancy\TenantAdminManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class TenantAdminManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_create_update_and_delete_a_tenant_admin(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant Demo',
            'slug' => 'tenant-demo',
            'status' => 'active',
        ]);

        $manager = app(TenantAdminManager::class);

        $user = $manager->createAdmin($tenant, [
            'name' => 'Mario Rossi',
            'email' => 'mario@example.com',
            'password' => 'password123',
        ]);

        if (function_exists('setPermissionsTeamId')) {
            setPermissionsTeamId($tenant->getKey());
        }

        $this->assertSame($tenant->getKey(), $user->tenant_id);
        $this->assertNull($user->customer_id);
        $this->assertTrue($user->hasRole(TenantAdminManager::ROLE_NAME));

        $updatedUser = $manager->updateAdmin($tenant, $user, [
            'name' => 'Luigi Bianchi',
            'email' => 'luigi@example.com',
            'password' => 'password456',
        ]);

        $this->assertSame('Luigi Bianchi', $updatedUser->name);
        $this->assertSame('luigi@example.com', $updatedUser->email);
        $this->assertTrue(Hash::check('password456', $updatedUser->password));

        $manager->deleteAdmin($tenant, $updatedUser);

        $this->assertDatabaseMissing('users', [
            'id' => $updatedUser->getKey(),
        ]);
    }

    public function test_it_prevents_creating_more_than_one_tenant_admin(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant Demo',
            'slug' => 'tenant-demo',
            'status' => 'active',
        ]);

        $manager = app(TenantAdminManager::class);

        $manager->createAdmin($tenant, [
            'name' => 'Mario Rossi',
            'email' => 'mario@example.com',
            'password' => 'password123',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Esiste gia un utente admin collegato a questo tenant.');

        $manager->createAdmin($tenant, [
            'name' => 'Luigi Bianchi',
            'email' => 'luigi@example.com',
            'password' => 'password456',
        ]);
    }
}
