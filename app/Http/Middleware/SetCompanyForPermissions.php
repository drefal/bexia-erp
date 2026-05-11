<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetCompanyForPermissions
{
    public function handle(Request $request, Closure $next): Response
    {
        // 1) Intenta resolver la empresa por subdominio
        //    - grupol7.bexia.com          => grupol7
        //    - staging.grupol7.bexia.com  => grupol7
        $host = $request->getHost();
        $parts = explode('.', $host);

        $subdomain = $parts[0] ?? null;
        if ($subdomain === 'staging') {
            $subdomain = $parts[1] ?? $subdomain; // toma el siguiente segmento
        }

        $companyId = null;

        if ($subdomain && !in_array($subdomain, ['www'])) {
            $companyId = \App\Models\Company::where('slug', $subdomain)->value('id');
        }

        // 2) Fallbacks: sesión o atributo del usuario
        $companyId = $companyId
            ?? (int) $request->session()->get('company_id')
            ?? (int) optional($request->user())->company_id
            ?? null;

        // 3) Fija el team para Spatie\Permission (Teams)
        if ($companyId) {
            setPermissionsTeamId($companyId);
        }

        return $next($request);
    }
}
