<?php

namespace App\Http\Middleware;

use App\Support\Security\SafeAdminUrl;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class KeepPathOnTenantSwitch
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenantId = $this->tenantIdFromRequest($request);
        $user = $request->user();

        if ($tenantId && $user && $this->isRedirectableRequest($request)) {
            if (! SafeAdminUrl::userCanUseTenant($user, $tenantId)) {
                return redirect()->to(SafeAdminUrl::forUser($user));
            }

            $previousTenantId = (int) $request->session()->get('bexia.current_admin_tenant_id', 0);

            if (
                $previousTenantId > 0
                && $previousTenantId !== $tenantId
                && $this->isDeepAdminPath($request)
            ) {
                $request->session()->put('bexia.current_admin_tenant_id', $tenantId);
                $request->session()->put('bexia_safe_admin_tenant_id', $tenantId);

                return redirect()->to(SafeAdminUrl::forUser($user, $tenantId));
            }
        }

        $response = $next($request);

        if ($tenantId && $user) {
            $request->session()->put('bexia.current_admin_tenant_id', $tenantId);
            $request->session()->put('bexia_safe_admin_tenant_id', $tenantId);
        }

        return $response;
    }

    private function tenantIdFromRequest(Request $request): ?int
    {
        $tenant = $request->route('tenant');

        if (is_object($tenant) && method_exists($tenant, 'getKey')) {
            return (int) $tenant->getKey();
        }

        if (is_numeric($tenant)) {
            return (int) $tenant;
        }

        return null;
    }

    private function isDeepAdminPath(Request $request): bool
    {
        return preg_match('#^admin/[^/]+/.+#', trim($request->path(), '/')) === 1;
    }

    private function isRedirectableRequest(Request $request): bool
    {
        return $request->isMethod('GET') || $request->isMethod('HEAD');
    }
}
