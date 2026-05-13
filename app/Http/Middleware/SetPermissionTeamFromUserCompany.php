<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

class SetPermissionTeamFromUserCompany
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('permission.teams')) {
            return $next($request);
        }

        $user = $request->user() ?: auth()->user();

        if (! $user) {
            return $next($request);
        }

        $companyId = $this->resolveCompanyId($request, $user);

        if ($companyId > 0) {
            app(PermissionRegistrar::class)->setPermissionsTeamId($companyId);
        }

        return $next($request);
    }

    private function resolveCompanyId(Request $request, mixed $user): int
    {
        /*
         * BEXIA_V5523S4_PERMISSION_TEAM_COMPANY
         * Spatie usa company_id como team.
         */

        foreach ([
            'company_id',
            'current_company_id',
            'active_company_id',
            'tenant_id',
            'filament.tenant.id',
        ] as $key) {
            $value = $request->session()->get($key);

            if (is_numeric($value) && (int) $value > 0) {
                return (int) $value;
            }
        }

        foreach ([
            'company_id',
            'current_company_id',
            'active_company_id',
            'tenant_id',
        ] as $field) {
            $value = $user->{$field} ?? null;

            if (is_numeric($value) && (int) $value > 0) {
                return (int) $value;
            }
        }

        if (! Schema::hasTable('model_has_roles')) {
            return 0;
        }

        $teamColumn = config('permission.column_names.team_foreign_key', 'company_id');

        if (! Schema::hasColumn('model_has_roles', $teamColumn)) {
            return 0;
        }

        $modelType = is_object($user) ? $user::class : 'App\\Models\\User';

        $row = DB::table('model_has_roles')
            ->where('model_id', (int) $user->id)
            ->where('model_type', $modelType)
            ->whereNotNull($teamColumn)
            ->orderBy($teamColumn)
            ->first();

        return (int) ($row->{$teamColumn} ?? 0);
    }
}
