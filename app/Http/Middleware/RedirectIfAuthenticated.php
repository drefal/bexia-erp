<?php

namespace App\Http\Middleware;

use App\Support\Security\SafeAdminUrl;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RedirectIfAuthenticated
{
    public function handle(Request $request, Closure $next, ...$guards)
    {
        $guards = $guards ?: [null];

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                $request->session()->forget('url.intended');
                $request->session()->forget('intended');

                return redirect()->to(SafeAdminUrl::forUser(Auth::guard($guard)->user()));
            }
        }

        return $next($request);
    }
}
