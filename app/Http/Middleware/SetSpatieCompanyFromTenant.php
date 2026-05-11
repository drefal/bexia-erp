<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;

class SetSpatieCompanyFromTenant
{
    public function handle(Request $request, Closure $next)
    {
        $tenant = Filament::getTenant();

        if ($tenant) {
            $companyId = (int) $tenant->getKey();

            session(['company_id' => $companyId]);

            app(PermissionRegistrar::class)->setPermissionsTeamId($companyId);
        } else {
            session()->forget('company_id');

            app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        }

        return $next($request);
    }
}