<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;

// fallback if present

class TrustProxies extends Middleware
{
    /**
     * The trusted proxies for this application.
     * Will read from TRUSTED_PROXIES env (comma-separated). Use '*' cautiously.
     *
     * @var array|string|null
     */
    protected $proxies;

    /**
     * The headers that should be used to detect proxies.
     * You can override with TRUSTED_PROXY_HEADERS env if needed.
     *
     * @var int
     */
    protected $headers;

    public function __construct()
    {
        $proxies = env('TRUSTED_PROXIES');
        $this->proxies = $proxies === null || $proxies === '' ? null : array_map('trim', explode(',', $proxies));

        // Leave headers as null to let the parent default to the framework's defaults
        $this->headers = env('TRUSTED_PROXY_HEADERS', null);
    }
}
