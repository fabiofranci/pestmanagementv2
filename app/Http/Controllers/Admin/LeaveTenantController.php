<?php

namespace App\Http\Controllers\Admin;

use App\Filament\Resources\Tenants\TenantResource;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Tenancy\CurrentTenant;
use App\Support\Tenancy\TenantConnectionManager;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LeaveTenantController extends Controller
{
    public function __invoke(
        Request $request,
        CurrentTenant $currentTenant,
        TenantConnectionManager $tenantConnectionManager,
    ): RedirectResponse {
        abort_unless($request->user() instanceof User && $request->user()->isSuperuser(), 403);

        $currentTenant->clear();
        $tenantConnectionManager->activate(null);

        Filament::setTenant(null, isQuiet: true);
        setPermissionsTeamId(null);

        $request->session()->save();

        return redirect()->to(TenantResource::getUrl(panel: 'admin'));
    }
}
