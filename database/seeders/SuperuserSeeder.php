<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class SuperuserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create or get a default tenant used for admin purposes
        $tenant = Tenant::firstOrCreate(
            ['slug' => 'super-tenant'],
            [
                'name' => 'Super Tenant',
                'domain' => 'localhost',
                'status' => 'active',
            ]
        );

        // Create a SUPERUSER role scoped to the tenant
        $role = Role::firstOrCreate([
            'name' => 'SUPERUSER',
            'tenant_id' => $tenant->id,
            'guard_name' => 'web',
        ]);

        // Create the superuser
        $user = User::firstOrCreate(
            ['email' => 'superuser@example.com'],
            [
                'name' => 'Super User',
                'password' => Hash::make('password'),
                'tenant_id' => $tenant->id,
            ]
        );

        // Assign role scoped to the tenant using the pivot
        if (! $user->hasRole('SUPERUSER')) {
            $user->roles()->syncWithoutDetaching([
                $role->id => ['tenant_id' => $tenant->id],
            ]);
        }
    }
}
