<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Tenancy\CurrentTenant;
use App\Support\Tenancy\TenantConnectionManager;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class BootstrapTenantContext
{
    public function __construct(
        protected CurrentTenant $currentTenant,
        protected TenantConnectionManager $tenantConnectionManager,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            $this->currentTenant->set(null);
            Filament::setTenant(null, isQuiet: true);
            setPermissionsTeamId(null);

            return $next($request);
        }

        $tenant = $this->currentTenant->resolveForUser($user);

        $this->currentTenant->set($tenant);

        Filament::setTenant($tenant, isQuiet: true);
        setPermissionsTeamId($tenant?->getKey());

        if ($tenant) {
            try {
                $this->tenantConnectionManager->activate($tenant);
            } catch (Throwable) {
                $this->currentTenant->clear();
                $this->currentTenant->set(null);
                Filament::setTenant(null, isQuiet: true);
                setPermissionsTeamId(null);
            }
        }

        return $next($request);
    }
}
