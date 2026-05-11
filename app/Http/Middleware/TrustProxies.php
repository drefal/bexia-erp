<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    /**
     * Con Traefik / reverse proxy, confiar en todos los proxies.
     *
     * @var array<int, string>|string|null
     */
    protected $proxies = '*';

    /**
     * Usar TODOS los X-Forwarded-* estándar.
     *
     * @var int
     */
    protected $headers = Request::HEADER_X_FORWARDED_ALL;
}
