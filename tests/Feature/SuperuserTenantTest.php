<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\SuperuserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperuserTenantTest extends TestCase
{
    use RefreshDatabase;

    public function test_superuser_and_tenant_are_seeded_and_role_assigned()
    {
        $this->seed(SuperuserSeeder::class);

        $user = User::where('email', 'superuser@example.com')->first();

        $this->assertNotNull($user, 'Superuser was not created');
        $this->assertNotNull($user->tenant_id, 'Superuser tenant_id is null');

        // Ensure the permission team resolver points to the seeded tenant
        if (function_exists('setPermissionsTeamId')) {
            setPermissionsTeamId($user->tenant_id);
        }

        $this->assertTrue($user->hasRole('SUPERUSER'));
    }

    public function test_superuser_seeder_backfills_super_tenant_database_name(): void
    {
        Tenant::query()->create([
            'name' => 'Super Tenant',
            'slug' => 'super-tenant',
            'status' => 'active',
        ]);

        $this->seed(SuperuserSeeder::class);

        $tenant = Tenant::query()->where('slug', 'super-tenant')->firstOrFail();

        $this->assertSame('tenant_super_tenant', $tenant->db_database);
    }
}
