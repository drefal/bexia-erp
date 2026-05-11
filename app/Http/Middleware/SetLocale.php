<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class SetLocale
{
    public function handle($request, Closure $next)
    {
        $supported = config('app.supported_locales', ['es','en','pt']);

        // helper para normalizar: en-US -> en, es_MX -> es, a minúsculas
        $normalize = function ($value) use ($supported) {
            if (empty($value)) return null;
            $v = strtolower((string) $value);
            $v = str_replace('_', '-', $v);
            $v = explode('-', $v)[0]; // base language
            return in_array($v, $supported, true) ? $v : null;
        };

        $fromUrl    = $normalize($request->query('lang'));            // ?lang=en
        $fromSess   = $normalize(session('locale'));                  // sesión previa
        $fromUser   = $normalize(optional(Auth::user())->locale);     // users.locale
        $fromTenant = $normalize(app()->bound('currentTenant')
                        ? optional(app('currentTenant'))->default_locale
                        : null);
        $fromHdr    = $normalize($request->getPreferredLanguage($supported));

        // Prioridad actual: URL > Sesión > Usuario > Tenant > Header > Config
        $locale = collect([$fromUrl, $fromSess, $fromUser, $fromTenant, $fromHdr, config('app.locale')])
            ->first(fn($v) => !empty($v));

        if (! in_array($locale, $supported, true)) {
            $locale = config('app.fallback_locale');
        }

        App::setLocale($locale);
        Carbon::setLocale($locale);

        if ($fromUrl && $fromUrl !== $fromSess) {
            session(['locale' => $locale]);
        }

        return $next($request);
    }
}
