<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ForceHttps
{
    public function handle(Request $request, Closure $next)
    {
        // Respeta el X-Forwarded-Proto que pone Traefik
        $proto = $request->headers->get('x-forwarded-proto');
        $isHttps = $proto ? strtolower($proto) === 'https' : $request->isSecure();

        if (! $isHttps) {
            $url = 'https://' . $request->getHttpHost() . $request->getRequestUri();
            return redirect()->to($url, 301);
        }

        return $next($request);
    }
}
