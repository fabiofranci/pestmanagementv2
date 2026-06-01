<?php

namespace App\Models\Concerns;

use App\Support\Tenancy\CurrentTenant;
use Illuminate\Database\Eloquent\Model;

trait UsesTenantConnection
{
    public function getConnectionName(): ?string
    {
        return app(CurrentTenant::class)->hasTenant()
            ? config('tenancy.database_connection')
            : config('database.default');
    }

    protected static function bootUsesTenantConnection(): void
    {
        static::creating(function (Model $record): void {
            $tenantId = app(CurrentTenant::class)->id();

            if ($tenantId && $record->isFillable('tenant_id') && blank($record->getAttribute('tenant_id'))) {
                $record->setAttribute('tenant_id', $tenantId);
            }
        });
    }
}
