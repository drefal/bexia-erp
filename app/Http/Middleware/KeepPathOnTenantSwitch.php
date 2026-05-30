<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class KeepPathOnTenantSwitch
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('GET') || ! $request->is('admin/*')) {
            return $next($request);
        }

        $segments = $request->segments();

        if (($segments[0] ?? null) !== 'admin') {
            return $next($request);
        }

        $tenantId = $segments[1] ?? null;

        if (! $tenantId || ! ctype_digit((string) $tenantId)) {
            return $next($request);
        }

        $tenantId = (string) $tenantId;
        $suffixSegments = array_slice($segments, 2);
        $suffix = trim(implode('/', $suffixSegments), '/');
        $isTenantHome = $suffix === '';

        $lastTenantId = (string) session('bexia_last_tenant_id', '');
        $lastSuffix = trim((string) session('bexia_last_tenant_suffix', ''), '/');
        $lastQuery = (array) session('bexia_last_tenant_query', []);

        if (
            $isTenantHome
            && $lastTenantId !== ''
            && $lastTenantId !== $tenantId
            && $lastSuffix !== ''
            && ! session()->pull('bexia_tenant_switch_skip_redirect', false)
        ) {
            session([
                'bexia_tenant_switch_attempt' => true,
                'bexia_tenant_switch_attempt_tenant_id' => $tenantId,
            ]);

            $target = url('/admin/' . $tenantId . '/' . $lastSuffix);

            if (! empty($lastQuery)) {
                $target .= '?' . http_build_query($lastQuery);
            }

            return redirect()->to($target);
        }

        $response = $next($request);

        if (
            (int) $response->getStatusCode() === 403
            && session()->pull('bexia_tenant_switch_attempt', false)
        ) {
            session([
                'bexia_tenant_switch_skip_redirect' => true,
                'bexia_last_tenant_id' => $tenantId,
                'bexia_last_tenant_suffix' => '',
                'bexia_last_tenant_query' => [],
            ]);

            return redirect()->to(url('/admin/' . $tenantId));
        }

        if ((int) $response->getStatusCode() < 400) {
            session(['bexia_last_tenant_id' => $tenantId]);

            if (! $isTenantHome) {
                session([
                    'bexia_last_tenant_suffix' => $suffix,
                    'bexia_last_tenant_query' => $request->query(),
                ]);
            }
        }

        return $response;
    }
}
