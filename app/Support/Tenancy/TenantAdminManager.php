<?php

namespace App\Support\Tenancy;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Spatie\Permission\Models\Role;

class TenantAdminManager
{
    public const ROLE_NAME = 'ADMIN';

    public function getAdmin(Tenant $tenant): ?User
    {
        if ($tenant->relationLoaded('tenantAdmin')) {
            /** @var ?User $tenantAdmin */
            $tenantAdmin = $tenant->getRelation('tenantAdmin');

            return $tenantAdmin;
        }

        return $tenant->tenantAdmin()->first();
    }

    public function createAdmin(Tenant $tenant, array $data): User
    {
        if ($this->getAdmin($tenant)) {
            throw new RuntimeException('Esiste gia un utente admin collegato a questo tenant.');
        }

        return DB::transaction(function () use ($tenant, $data): User {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'tenant_id' => $tenant->getKey(),
                'customer_id' => null,
                'is_superuser' => false,
            ]);

            $this->syncAdminRole($tenant, $user);

            $tenant->unsetRelation('tenantAdmin');

            return $user;
        });
    }

    public function updateAdmin(Tenant $tenant, User $user, array $data): User
    {
        $this->guardAdminBelongsToTenant($tenant, $user);

        return DB::transaction(function () use ($tenant, $user, $data): User {
            $payload = [
                'name' => $data['name'],
                'email' => $data['email'],
                'tenant_id' => $tenant->getKey(),
                'customer_id' => null,
                'is_superuser' => false,
            ];

            if (filled($data['password'] ?? null)) {
                $payload['password'] = $data['password'];
            }

            $user->update($payload);

            $this->syncAdminRole($tenant, $user);

            $tenant->unsetRelation('tenantAdmin');

            return $user->refresh();
        });
    }

    public function deleteAdmin(Tenant $tenant, User $user): void
    {
        $this->guardAdminBelongsToTenant($tenant, $user);

        DB::transaction(function () use ($tenant, $user): void {
            $user->delete();

            $tenant->unsetRelation('tenantAdmin');
        });
    }

    protected function syncAdminRole(Tenant $tenant, User $user): void
    {
        $role = Role::query()->firstOrCreate([
            'name' => static::ROLE_NAME,
            'tenant_id' => $tenant->getKey(),
            'guard_name' => 'web',
        ]);

        $user->roles()->syncWithoutDetaching([
            $role->getKey() => ['tenant_id' => $tenant->getKey()],
        ]);
    }

    protected function guardAdminBelongsToTenant(Tenant $tenant, User $user): void
    {
        if ($user->isSuperuser() || $user->isTenantCustomer() || (int) $user->tenant_id !== (int) $tenant->getKey()) {
            throw new RuntimeException('L utente selezionato non appartiene a questo tenant.');
        }
    }
}
