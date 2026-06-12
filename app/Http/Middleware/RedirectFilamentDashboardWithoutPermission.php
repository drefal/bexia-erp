<?php

namespace App\Http\Middleware;

use App\Support\Security\BexiaAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectFilamentDashboardWithoutPermission
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->isFilamentDashboardRequest($request)) {
            return $next($request);
        }

        if (BexiaAccess::dashboard()) {
            return $next($request);
        }

        $tenant = $this->tenantRouteValue($request);

        if (BexiaAccess::salidas()) {
            return redirect()->to($this->safeUrl(
                routeName: 'filament.admin.pages.salidas',
                fallbackPath: '/admin/' . $tenant . '/salidas',
                tenant: $tenant
            ));
        }

        if (BexiaAccess::inventory()) {
            return redirect()->to($this->safeUrl(
                routeName: 'filament.admin.resources.products.index',
                fallbackPath: '/admin/' . $tenant . '/products',
                tenant: $tenant
            ));
        }

        abort(403, 'No tienes permiso para acceder al escritorio.');
    }

    protected function isFilamentDashboardRequest(Request $request): bool
    {
        $routeName = (string) optional($request->route())->getName();

        if (str_contains($routeName, '.pages.dashboard')) {
            return true;
        }

        $path = trim($request->path(), '/');

        return (bool) preg_match('#^admin/[^/]+$#', $path);
    }

    protected function tenantRouteValue(Request $request): string
    {
        $tenant = $request->route('tenant');

        if (is_object($tenant) && method_exists($tenant, 'getKey')) {
            return (string) $tenant->getKey();
        }

        if ($tenant !== null && $tenant !== '') {
            return (string) $tenant;
        }

        $segments = $request->segments();

        foreach ($segments as $index => $segment) {
            if ($segment === 'admin' && isset($segments[$index + 1])) {
                return (string) $segments[$index + 1];
            }
        }

        return '1';
    }

    protected function safeUrl(string $routeName, string $fallbackPath, string $tenant): string
    {
        try {
            if (app('router')->has($routeName)) {
                return route($routeName, ['tenant' => $tenant]);
            }
        } catch (\Throwable) {
            //
        }

        return url($fallbackPath);
    }
}
