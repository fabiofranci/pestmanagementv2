<?php

namespace Tests\Feature;

use Database\Seeders\SuperuserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;

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
}
