<?php

namespace App\Support\Tenancy;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CustomerPortalUserManager
{
    public function getUser(Customer $customer): ?User
    {
        return User::query()
            ->where('tenant_id', $customer->tenant_id)
            ->where('customer_id', $customer->getKey())
            ->where('is_superuser', false)
            ->first();
    }

    public function createUser(Customer $customer, array $data): User
    {
        if ($this->getUser($customer)) {
            throw new RuntimeException('Esiste gia un accesso cliente collegato a questo cliente.');
        }

        return DB::transaction(function () use ($customer, $data): User {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'tenant_id' => $customer->tenant_id,
                'customer_id' => $customer->getKey(),
                'is_superuser' => false,
            ]);

            return $user;
        });
    }

    public function updateUser(Customer $customer, User $user, array $data): User
    {
        $this->guardUserBelongsToCustomer($customer, $user);

        return DB::transaction(function () use ($customer, $user, $data): User {
            $payload = [
                'name' => $data['name'],
                'email' => $data['email'],
                'tenant_id' => $customer->tenant_id,
                'customer_id' => $customer->getKey(),
                'is_superuser' => false,
            ];

            if (filled($data['password'] ?? null)) {
                $payload['password'] = $data['password'];
            }

            $user->update($payload);

            return $user->refresh();
        });
    }

    public function deleteUser(Customer $customer, User $user): void
    {
        $this->guardUserBelongsToCustomer($customer, $user);

        DB::transaction(function () use ($customer, $user): void {
            $user->delete();
        });
    }

    protected function guardUserBelongsToCustomer(Customer $customer, User $user): void
    {
        if (
            $user->isSuperuser()
            || ! $user->isTenantCustomer()
            || (int) $user->tenant_id !== (int) $customer->tenant_id
            || (int) $user->customer_id !== (int) $customer->getKey()
        ) {
            throw new RuntimeException('L utente selezionato non appartiene a questo cliente.');
        }
    }
}
