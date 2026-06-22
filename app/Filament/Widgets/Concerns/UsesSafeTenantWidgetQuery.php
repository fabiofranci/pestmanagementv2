<?php

namespace App\Filament\Widgets\Concerns;

use App\Models\Tenant;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

trait UsesSafeTenantWidgetQuery
{
    protected function hasCurrentTenantWithTables(array $tables): bool
    {
        if (! app(CurrentTenant::class)->hasTenant()) {
            return false;
        }

        $schema = Schema::connection(config('tenancy.database_connection'));

        foreach ($tables as $table) {
            if (! $schema->hasTable($table)) {
                return false;
            }
        }

        return true;
    }

    protected function emptyDashboardQuery(): Builder
    {
        return Tenant::query()->whereRaw('1 = 0');
    }
}
