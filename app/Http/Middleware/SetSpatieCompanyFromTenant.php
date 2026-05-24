<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

class SetSpatieCompanyFromTenant
{
    public function handle(Request $request, Closure $next)
    {
        $companyId = null;

        $tenant = Filament::getTenant();

        if ($tenant && method_exists($tenant, 'getKey')) {
            $companyId = (int) $tenant->getKey();
        }

        if (! $companyId && auth()->check()) {
            $companyId = DB::table('company_user')
                ->where('user_id', auth()->id())
                ->value('company_id');
        }

        if ($companyId) {
            \Log::info('SPATIE_TEAM_SET', ['user_id' => auth()->id(), 'company_id' => (int) $companyId]);
            session(['company_id' => (int) $companyId]);

            app(PermissionRegistrar::class)->setPermissionsTeamId((int) $companyId);
        }

        return $next($request);
    }
}