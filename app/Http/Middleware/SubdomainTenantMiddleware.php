<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Services\TenantManager;
use Closure;
use Illuminate\Http\Request;

class SubdomainTenantMiddleware
{
    public function __construct(protected TenantManager $tenants)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        $host = $request->getHost();
        $parts = explode('.', $host);
        $subdomain = count($parts) > 2 ? $parts[0] : null;

        if ($subdomain) {
            $company = Company::where('subdomain', $subdomain)->first();
            if ($company) {
                $this->tenants->setTenant($company);
                app()->instance('tenant', $company);
            }
        }

        return $next($request);
    }
}

