<?php

namespace App\Support\Tenancy;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class TenantAdminManager
{
    public const ROLE_NAME = 'ADMIN';

    public function createAdmin(Tenant $tenant, array $data): User
    {
        return DB::transaction(function () use ($tenant, $data): User {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'tenant_id' => $tenant->getKey(),
                'is_superuser' => false,
            ]);

            $role = Role::query()->firstOrCreate([
                'name' => static::ROLE_NAME,
                'tenant_id' => $tenant->getKey(),
                'guard_name' => 'web',
            ]);

            $user->roles()->syncWithoutDetaching([
                $role->getKey() => ['tenant_id' => $tenant->getKey()],
            ]);

            return $user;
        });
    }
}
